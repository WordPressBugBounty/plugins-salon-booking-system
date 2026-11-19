<?php

/**
 * Pre-save check of the back-end booking editor: lists the availability rules the
 * booking breaks, so the editor can ask the user to confirm them before saving.
 * Reads the same form the editor submits.
 */
class SLN_Action_Ajax_CheckBookingAvailability extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
        $postId = isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0;

        if (
            !wp_verify_nonce($nonce, 'ajax_post_validation')
            || !(current_user_can('manage_salon') || ($postId && current_user_can('edit_post', $postId)))
        ) {
            return array(
                'success' => 0,
                'errors'  => array(__('You do not have permission to perform this action.', 'salon-booking-system')),
            );
        }

        $postType  = SLN_Plugin::POST_TYPE_BOOKING;
        $dateField = SLN_Metabox_Helper::getFieldName($postType, 'date');
        $timeField = SLN_Metabox_Helper::getFieldName($postType, 'time');

        // Only the fields the check reads; each one is sanitized where it is used.
        $request = array(
            '_sln_booking_status' => isset($_POST['_sln_booking_status']) ? sanitize_text_field(wp_unslash($_POST['_sln_booking_status'])) : '',
            $dateField            => isset($_POST[$dateField]) ? sanitize_text_field(wp_unslash($_POST[$dateField])) : '',
            $timeField            => isset($_POST[$timeField]) ? sanitize_text_field(wp_unslash($_POST[$timeField])) : '',
        );
        // Service lines: ids, durations and prices, parsed by buildServicesFromSubmission().
        $services = isset($_POST['_sln_booking']) && is_array($_POST['_sln_booking'])
            ? map_deep(wp_unslash($_POST['_sln_booking']), 'sanitize_text_field')
            : array();
        $customer = trim(
            (isset($_POST['_sln_booking_firstname']) ? sanitize_text_field(wp_unslash($_POST['_sln_booking_firstname'])) : '')
            .' '.
            (isset($_POST['_sln_booking_lastname']) ? sanitize_text_field(wp_unslash($_POST['_sln_booking_lastname'])) : '')
        );

        // Multi-Shops: the shop chosen in the editor (null when the form has no shop field).
        $postedShop = isset($_POST['_sln_booking_shop']) && is_scalar($_POST['_sln_booking_shop'])
            ? absint(wp_unslash($_POST['_sln_booking_shop']))
            : null;
        $this->applyBookingShop($postId, $postedShop);

        $servicesData = SLN_Metabox_Booking::buildServicesFromSubmission($services);
        $violations   = SLN_Metabox_Booking::getEditorAvailabilityViolations($postId, $servicesData, $request);
        $violations   = is_array($violations) ? $violations : array();

        return array(
            'success'    => 1,
            'violations' => $violations,
            'signature'  => $violations ? SLN_Helper_AvailabilityViolations::signature($violations) : '',
            'can_force'  => SLN_Helper_AvailabilityViolations::currentUserCanForce(),
            'summary'    => $violations ? $this->getSummary($servicesData, $request[$dateField], $request[$timeField], $customer) : '',
        );
    }

    /**
     * Multi-Shops: check against the booking's shop. This request goes through
     * admin-ajax.php, where the add-on doesn't read the editor's shop field and falls
     * back to the last front-end session shop (or none), so the salon's global hours
     * and the services' global availability were checked instead of the shop's.
     * The shop chosen in the editor wins, then the booking's saved shop.
     *
     * @param int      $postId
     * @param int|null $postedShop
     */
    private function applyBookingShop($postId, $postedShop)
    {
        if (!class_exists('\SalonMultishop\Addon')) {
            return;
        }

        if ($postedShop !== null) {
            $shopId = (int) $postedShop;
        } elseif ($postId) {
            $shopId = absint(get_post_meta($postId, '_sln_booking_shop', true));
        } else {
            return;
        }

        try {
            // SalonSettings::get() only reads the shop's hours when $_GET['shop'] is set.
            \SalonMultishop\Addon::getInstance()->setCurrentShop($shopId ? $shopId : null);
            $_GET['shop'] = (string) $shopId;
        } catch (Exception $e) {
            SLN_Plugin::addLog('CheckBookingAvailability: shop context error: '.$e->getMessage());
        }
    }

    /**
     * "Raffaella Purcaro · Tuesday 10 Nov 2026 · 13:30 → 15:00 · Laura"
     */
    private function getSummary(array $servicesData, $date, $time, $customer)
    {
        try {
            $day   = SLN_Func::filter($date, 'date');
            $time  = SLN_Func::filter($time, 'time');
            $start = new SLN_DateTime($day.' '.$time, SLN_TimeFunc::getWpTimezone());
        } catch (Exception $e) {
            return '';
        }

        $format          = $this->plugin->format();
        $bookingServices = SLN_Wrapper_Booking_Services::build($servicesData, $start);
        $end             = clone $start;
        $names           = array();
        foreach ($bookingServices->getItems() as $bookingService) {
            if ($bookingService->getEndsAt() > $end) {
                $end = $bookingService->getEndsAt();
            }
            $attendants = $bookingService->getAttendant();
            foreach (is_array($attendants) ? $attendants : array($attendants) as $attendant) {
                if ($attendant instanceof SLN_Wrapper_AttendantInterface) {
                    $names[$attendant->getId()] = $attendant->getName();
                }
            }
        }

        $parts = array_filter(array(
            $customer,
            SLN_TimeFunc::translateDate('l', $start->getTimestamp(), $start->getTimezone()).' '.$format->date($start),
            $format->time($start).' → '.$format->time($end),
            implode(', ', $names),
        ));

        return implode(' · ', $parts);
    }
}
