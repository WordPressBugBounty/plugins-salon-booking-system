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
     * Line price of a booking service: unit price × quantity, as in
     * SLN_Wrapper_Booking::evalTotal(). Older bookings that did not store a
     * price fall back to the service price. A stored 0 (package credits)
     * stays 0, never negative.
     *
     * @param SLN_Wrapper_Booking_Service $bookingService
     * @return float
     */
    public static function getServicePrice($bookingService)
    {
        if (!$bookingService->hasPrice()) {
            return self::getCatalogPrice($bookingService);
        }

        return max(0.0, (float) $bookingService->getPrice()) * $bookingService->getCountServices();
    }

    /**
     * Current service price × quantity (variable-price aware).
     *
     * @param SLN_Wrapper_Booking_Service $bookingService
     * @return float
     */
    private static function getCatalogPrice($bookingService)
    {
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

        return max(0.0, $price) * $bookingService->getCountServices();
    }

    /**
     * What each service of the booking brought in, keyed like getBookingServices()->getItems().
     *
     * The booking total without tips is spread over its services in proportion
     * to their value, so the assistants' parts always add up to the booking
     * total the unfiltered reports show. Line prices are used as they are when
     * they add up to that total (a 0 line is then really free, e.g. package
     * credits). Many bookings store 0 or out-of-date line prices (back-end
     * edits, booking-wide discounts, price changes): then a 0 line not paid
     * with a package credit is valued at the current service price, and when
     * nothing has a price the services share the total evenly.
     *
     * @param SLN_Wrapper_Booking $booking
     * @return float[]
     */
    public static function getServiceAmounts($booking)
    {
        $items   = $booking->getBookingServices()->getItems();
        $total   = max(0.0, (float) $booking->getAmount() - $booking->getTips());
        $weights = array();
        foreach ($items as $i => $bookingService) {
            $weights[$i] = self::getServicePrice($bookingService);
        }
        if (abs(array_sum($weights) - $total) > 0.01) {
            $credits = array_map('strval', (array) $booking->getMeta('service_credit', false, false));
            foreach ($items as $i => $bookingService) {
                $service = $bookingService->getService();
                $credit  = $service && in_array((string) $service->getId(), $credits, true);
                if ($weights[$i] <= 0 && !$credit) {
                    $weights[$i] = self::getCatalogPrice($bookingService);
                }
            }
        }
        if (array_sum($weights) <= 0) {
            $weights = array_fill_keys(array_keys($items), 1.0);
        }

        $sum     = array_sum($weights);
        $amounts = array();
        foreach ($weights as $i => $weight) {
            $amounts[$i] = $sum > 0 ? $total * $weight / $sum : 0.0;
        }

        return $amounts;
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
        $amounts = self::getServiceAmounts($booking);
        $share   = 0.0;
        foreach (self::getAssistantServices($booking, $assistantId) as $i => $bookingService) {
            $share += self::getServiceShare($bookingService, $amounts[$i]);
        }

        return $share;
    }

    /**
     * Booking services the assistant performed, keyed like getBookingServices()->getItems().
     * A service booked twice is two lines with their own amounts.
     *
     * @param SLN_Wrapper_Booking $booking
     * @param int                 $assistantId
     * @return SLN_Wrapper_Booking_Service[]
     */
    public static function getAssistantServices($booking, $assistantId)
    {
        $assistantId = (int) $assistantId;
        $items       = array();

        foreach ($booking->getBookingServices()->getItems() as $i => $bookingService) {
            if (in_array($assistantId, self::getServiceAssistantIds($bookingService), true)) {
                $items[$i] = $bookingService;
            }
        }

        return $items;
    }

    /**
     * One assistant's part of a booking service: its amount split evenly among its assistants.
     *
     * @param SLN_Wrapper_Booking_Service $bookingService
     * @param float                       $amount From getServiceAmounts()
     * @return float
     */
    public static function getServiceShare($bookingService, $amount)
    {
        $assistants = count(self::getServiceAssistantIds($bookingService));

        return $assistants > 0 ? $amount / $assistants : 0.0;
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
