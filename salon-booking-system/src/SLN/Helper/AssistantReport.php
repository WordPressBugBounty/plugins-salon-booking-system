<?php

/**
 * Per-assistant figures for the Reports dashboard and the bookings CSV export.
 *
 * Assistants are not stored in their own booking meta: they live inside the
 * serialized `_sln_booking_services` items (an ID, or an array of IDs when a
 * service is shared). A booking can also mix services done by different
 * assistants, so "booking value for an assistant" is the sum of the services
 * they performed, split evenly when a service has more than one assistant.
 * This is the same attribution the dashboard's assistants table uses, so the
 * filtered KPIs add up to that table's row.
 */
class SLN_Helper_AssistantReport
{
    private static $bookingIds = array();

    /**
     * IDs of every booking with at least one service assigned to the assistant.
     *
     * @param int $assistantId
     * @return int[]
     */
    public static function getBookingIds($assistantId)
    {
        global $wpdb;

        $assistantId = (int) $assistantId;
        if (isset(self::$bookingIds[$assistantId])) {
            return self::$bookingIds[$assistantId];
        }

        // LIKE only narrows the rows down; the unserialized check below is the real match.
        // The serialized attendant is i:ID; (or s:N:"ID";), alone or inside an array of
        // IDs; matching those exact tokens keeps other bookings' meta out of memory.
        $intToken = 'i:' . $assistantId . ';';
        $strToken = 's:' . strlen((string) $assistantId) . ':"' . $assistantId . '";';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = %s AND pm.meta_key = '_sln_booking_services'
             AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s)",
            SLN_Plugin::POST_TYPE_BOOKING,
            '%' . $wpdb->esc_like('"attendant";' . $intToken) . '%',
            '%' . $wpdb->esc_like('"attendant";' . $strToken) . '%',
            '%' . $wpdb->esc_like('"attendant";a:') . '%' . $wpdb->esc_like(';' . $intToken) . '%',
            '%' . $wpdb->esc_like('"attendant";a:') . '%' . $wpdb->esc_like(';' . $strToken) . '%'
        ));

        $ids = array();
        foreach ($rows as $row) {
            $services = maybe_unserialize($row->meta_value);
            if (!is_array($services)) {
                continue;
            }
            foreach ($services as $service) {
                if (isset($service['attendant']) && in_array($assistantId, self::normalizeIds($service['attendant']), true)) {
                    $ids[] = (int) $row->post_id;
                    break;
                }
            }
        }

        return self::$bookingIds[$assistantId] = $ids;
    }

    /**
     * Restrict WP_Query args to the assistant's bookings. No-op when $assistantId is empty.
     *
     * @param array $queryArgs
     * @param int   $assistantId
     * @return array
     */
    public static function filterQueryArgs(array $queryArgs, $assistantId)
    {
        $assistantId = (int) $assistantId;
        if ($assistantId <= 0) {
            return $queryArgs;
        }
        $ids = self::getBookingIds($assistantId);
        if (!empty($queryArgs['post__in'])) {
            $ids = array_values(array_intersect($ids, array_map('intval', $queryArgs['post__in'])));
        }
        // array(0) keeps WP_Query from treating an empty list as "no restriction".
        $queryArgs['post__in'] = $ids ? $ids : array(0);

        return $queryArgs;
    }

    /**
     * Assistant IDs assigned to a booking service.
     *
     * @param SLN_Wrapper_Booking_Service $bookingService
     * @return int[]
     */
    public static function getServiceAssistantIds($bookingService)
    {
        return self::normalizeIds($bookingService->getAttendant());
    }

    /**
     * Price of a booking service, falling back to the service price for older
     * bookings that did not store it, and never negative. A stored 0 (package
     * credits) stays 0.
     *
     * @param SLN_Wrapper_Booking_Service $bookingService
     * @return float
     */
    public static function getServicePrice($bookingService)
    {
        $price = $bookingService->getPrice();

        if (!$bookingService->hasPrice()) {
            $service = $bookingService->getService();
            if (null === $service || $service->isEmpty()) {
                return 0.0;
            }

            $assistantIds = self::getServiceAssistantIds($bookingService);
            $assistantId  = $assistantIds ? $assistantIds[0] : null;
            if ($service->getVariablePriceEnabled() && $assistantId && $service->getVariablePrice($assistantId) !== '') {
                $price = floatval($service->getVariablePrice($assistantId));
            } else {
                $price = floatval($service->getPrice());
            }

            if (get_post_meta($service->getId(), '_sln_service_variable_duration', true)) {
                $price = $price * $bookingService->getCountServices();
            }
        }

        return max(0.0, (float) $price);
    }

    /**
     * The assistant's part of the booking value.
     *
     * @param SLN_Wrapper_Booking $booking
     * @param int                 $assistantId
     * @return float
     */
    public static function getBookingShare($booking, $assistantId)
    {
        $assistantId = (int) $assistantId;
        $share       = 0.0;
        $counted     = array();

        foreach ($booking->getBookingServices()->getItems() as $bookingService) {
            $assistantIds = self::getServiceAssistantIds($bookingService);
            if (!in_array($assistantId, $assistantIds, true)) {
                continue;
            }

            // One share per service, like the assistants table.
            $service    = $bookingService->getService();
            $serviceKey = ($service && !$service->isEmpty()) ? $service->getId() : 'deleted';
            if (isset($counted[$serviceKey])) {
                continue;
            }
            $counted[$serviceKey] = true;

            $share += self::getServicePrice($bookingService) / count($assistantIds);
        }

        return $share;
    }

    /**
     * Booking value to report: the assistant's share when filtering by one, the full amount otherwise.
     *
     * @param SLN_Wrapper_Booking $booking
     * @param int                 $assistantId
     * @return float
     */
    public static function getBookingAmount($booking, $assistantId)
    {
        return (int) $assistantId > 0 ? self::getBookingShare($booking, $assistantId) : (float) $booking->getAmount();
    }

    /**
     * @param mixed $attendant ID, numeric string, wrapper, or array of those
     * @return int[]
     */
    private static function normalizeIds($attendant)
    {
        $ids = array();
        foreach (is_array($attendant) ? $attendant : array($attendant) as $item) {
            if ($item instanceof SLN_Wrapper_AttendantInterface) {
                $ids[] = (int) $item->getId();
            } elseif (is_numeric($item) && (int) $item > 0) {
                $ids[] = (int) $item;
            }
        }
        return array_values(array_unique($ids));
    }
}
