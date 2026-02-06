<?php

class SLN_Action_Ajax_SendFeedback extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Not authorized', 'salon-booking-system'));
        }

        $booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (!$booking_id || !wp_verify_nonce($nonce, 'sln_send_feedback_' . $booking_id)) {
            wp_send_json_error(__('Invalid request', 'salon-booking-system'));
        }

        if (!current_user_can('edit_post', $booking_id)) {
            wp_send_json_error(__('Insufficient permissions', 'salon-booking-system'));
        }

        $plugin = SLN_Plugin::getInstance();
        $booking = $plugin->createBooking($booking_id);

        // Send the existing feedback email using the template already in use by reminders
        $plugin->sendMail('mail/feedback', compact('booking'));

        // Optionally mark that feedback request has been sent (so scheduled jobs can skip if desired)
        // $booking->setMeta('feedback', true);

        wp_send_json_success(array('ok' => true));
    }
}


