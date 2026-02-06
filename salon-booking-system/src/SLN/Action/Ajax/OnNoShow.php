<?php

class SLN_Action_Ajax_OnNoShow extends SLN_Action_Ajax_Abstract
{
    const STATUS_ERROR = -1;
    const STATUS_UNCHECKED = 0;
    const STATUS_CHECKED = 1;

    /** @var  SLN_Wrapper_Booking_Builder */
    protected $bb;
    /** @var  SLN_Helper_Availability */
    protected $ah;

    protected $date;
    protected $time;
    protected $errors = array();

    public function execute()
    {
        // Security: Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'ajax_post_validation')) {
            wp_send_json_error(array('error' => __('Invalid security token', 'salon-booking-system')));
            return;
        }

        // Security: Check permissions
        if (!current_user_can('manage_salon')) {
            wp_send_json_error(array('error' => __('Insufficient permissions', 'salon-booking-system')));
            return;
        }

        $bookingId = isset($_POST['bookingId']) ? (int)$_POST['bookingId'] : 0;
        $noShow = isset($_POST['noShow']) ? (int)$_POST['noShow'] : 0;
        $currentUserId = get_current_user_id();
        $currentTime = current_time('mysql');

        // Validate booking exists
        if (!$bookingId || !get_post($bookingId)) {
            wp_send_json_error(array('error' => __('Booking not found', 'salon-booking-system')));
            return;
        }

        // Toggle the value
        if ($noShow === 0) {
            $noShow = 1;
            
            // Store metadata when marking as no-show
            update_post_meta($bookingId, 'no_show', 1);
            update_post_meta($bookingId, 'no_show_marked_at', $currentTime);
            update_post_meta($bookingId, 'no_show_marked_by', $currentUserId);
            
            // Log action
            SLN_Plugin::getInstance()->addLog(sprintf(
                'Booking #%d marked as no-show by user #%d (%s)',
                $bookingId,
                $currentUserId,
                wp_get_current_user()->display_name
            ));
            
            // Fire action hook for extensions
            do_action('sln.booking.marked_no_show', $bookingId, $currentUserId);
            
            // Check if this is a walk-in booking and notify next customer
            $isWalkIn = get_post_meta($bookingId, '_' . SLN_Plugin::POST_TYPE_BOOKING . '_is_walkin', true);
            if ($isWalkIn) {
                // Notify next customer in walk-in queue
                do_action('sbs_walkin_notify_next_customer', $bookingId);
            }
            
        } else {
            $noShow = 0;
            
            // Store metadata when unmarking
            update_post_meta($bookingId, 'no_show', 0);
            update_post_meta($bookingId, 'no_show_unmarked_at', $currentTime);
            
            // Keep marked_at and marked_by for history
            // Don't delete them - useful for audit trail
            
            // Log action
            SLN_Plugin::getInstance()->addLog(sprintf(
                'Booking #%d unmarked as no-show by user #%d (%s)',
                $bookingId,
                $currentUserId,
                wp_get_current_user()->display_name
            ));
            
            // Fire action hook for extensions
            do_action('sln.booking.unmarked_no_show', $bookingId, $currentUserId);
        }

        // Get booking for customer info
        try {
            $booking = SLN_Plugin::getInstance()->createBooking($bookingId);
            $customerId = $booking->getUserId();
            $customerName = $booking->getDisplayName();
        } catch (Exception $e) {
            $customerId = 0;
            $customerName = '';
        }

        wp_send_json_success(array(
            'id'           => $bookingId,
            'noShow'       => $noShow,
            'markedAt'     => $currentTime,
            'markedBy'     => $currentUserId,
            'markedByName' => wp_get_current_user()->display_name,
            'customerId'   => $customerId,
            'customerName' => $customerName,
        ));
    }
}
