<?php

class SLN_Action_Ajax_PreviewBulkFeedback extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        // Security checks
        if (!current_user_can('manage_salon')) {
            wp_send_json_error(__('Not authorized', 'salon-booking-system'));
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'sln_send_bulk_feedback')) {
            wp_send_json_error(__('Invalid request', 'salon-booking-system'));
        }

        // Use the same logic as SendBulkFeedback to get eligible bookings
        $bulkFeedback = new SLN_Action_Ajax_SendBulkFeedback($this->plugin);
        
        // Use reflection to access the private getEligibleBookings method
        $reflection = new ReflectionClass($bulkFeedback);
        $method = $reflection->getMethod('getEligibleBookings');
        $method->setAccessible(true);
        $bookings = $method->invoke($bulkFeedback);
        
        $count = count($bookings);
        
        // Get settings info for display
        $feedback_reminder_mail = $this->plugin->getSettings()->get('feedback_email');
        $feedback_reminder_sms = $this->plugin->getSettings()->get('feedback_sms');
        $custom_feedback_url = $this->plugin->getSettings()->get('custom_feedback_url');
        
        // Build detailed message
        $details = array();
        
        if (!$feedback_reminder_mail && !$feedback_reminder_sms) {
            $details[] = __('⚠️ Neither email nor SMS is enabled. Please enable at least one.', 'salon-booking-system');
        } else {
            if ($feedback_reminder_mail) {
                $details[] = __('✓ Email enabled', 'salon-booking-system');
            }
            if ($feedback_reminder_sms) {
                $details[] = __('✓ SMS enabled', 'salon-booking-system');
            }
        }
        
        if (!empty($custom_feedback_url)) {
            $details[] = __('✓ Custom feedback URL configured (guest bookings can be included)', 'salon-booking-system');
        } else {
            $details[] = __('⚠️ No custom feedback URL (guest bookings will be excluded)', 'salon-booking-system');
        }
        
        // Breakdown by type
        $registered = 0;
        $guests = 0;
        foreach ($bookings as $booking) {
            if (SLN_Wrapper_Customer::isCustomer($booking->getUserId())) {
                $registered++;
            } else {
                $guests++;
            }
        }
        
        $breakdown = sprintf(
            __('%d registered customer(s), %d guest(s)', 'salon-booking-system'),
            $registered,
            $guests
        );
        
        wp_send_json_success(array(
            'count' => $count,
            'details' => $details,
            'breakdown' => $breakdown,
            'message' => $count > 0 
                ? sprintf(_n('Found %d eligible booking', 'Found %d eligible bookings', $count, 'salon-booking-system'), $count)
                : __('No eligible bookings found. Check settings below.', 'salon-booking-system')
        ));
    }
}
