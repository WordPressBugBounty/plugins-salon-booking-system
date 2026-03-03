<?php // algolplus

class SLN_Action_Ajax_SetBookingStatus extends SLN_Action_Ajax_Abstract
{
	private $errors = array();

	public function execute()
	{
		if (!is_user_logged_in()) {
			return array( 'redirect' => wp_login_url());
		}

		if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'ajax_post_validation')) {
			return array('errors' => array(__('Invalid security token. Please refresh the page and try again.', 'salon-booking-system')));
		}

		if (!current_user_can('manage_salon')) {
			return array('errors' => array(__('You do not have permission to perform this action.', 'salon-booking-system')));
		}

        if (!defined("SLN_VERSION_PAY")) {
            return array();
        }

		$plugin = SLN_Plugin::getInstance();
        if(!isset($_POST['booking_id']) && !isset($_POST['status'])) {
            return array('success' => 0, 'status' => 'failure');
        }
		$booking = $plugin->createBooking(intval($_POST['booking_id']));
		$booking_id = intval($_POST['booking_id']);

		if (in_array($_POST['status'], array(SLN_Enum_BookingStatus::CONFIRMED, SLN_Enum_BookingStatus::CANCELED))) {
			$booking->setStatus(wp_unslash($_POST['status']));

			// Re-fetch booking to ensure status is saved
			$booking = $plugin->createBooking($booking_id);

			// Process booking cache
			$plugin->getBookingCache()->processBooking($booking);

			// Auto-trash if status is being changed to CANCELED
			if ($_POST['status'] === SLN_Enum_BookingStatus::CANCELED) {
				$trashAction = new SLN_Action_TrashCancelledBooking($plugin);
				$trashAction->execute($booking);

				// After trashing, the booking post_status becomes 'trash'
				// So we need to use the CANCELED status for the label display
				$displayStatus = SLN_Enum_BookingStatus::CANCELED;
			}
		}

		// Use displayStatus if booking was trashed, otherwise use booking's current status
		$statusForLabel = isset($displayStatus) ? $displayStatus : $booking->getStatus();
		$status = SLN_Enum_BookingStatus::getLabel($statusForLabel);
		$color  = SLN_Enum_BookingStatus::getRealColor($statusForLabel);
		$weight = 'normal';
		if ($statusForLabel == SLN_Enum_BookingStatus::CONFIRMED || $statusForLabel == SLN_Enum_BookingStatus::PAID) $weight = 'bold';
		$statusLabel = '<div style="width:14px !important; height:14px; border-radius:14px; border:2px solid '.$color.'; float:left; margin-top:2px;"></div> &nbsp;<span style="color:'.$color.'; font-weight:'.$weight.';">' . $status . '</span>';

		return array('success' => 1, 'status' => $statusLabel);
	}
}
