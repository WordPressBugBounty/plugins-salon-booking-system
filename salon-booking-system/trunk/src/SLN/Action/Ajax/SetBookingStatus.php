<?php // algolplus
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_Ajax_SetBookingStatus extends SLN_Action_Ajax_Abstract
{
	private $errors = array();

	public function execute()
	{
		if (!is_user_logged_in()) {
			return array( 'redirect' => wp_login_url());
		}

        if (!defined("SLN_VERSION_PAY")) {
            return array();
        }

		$plugin = SLN_Plugin::getInstance();
        if(!isset($_POST['booking_id']) && !isset($_POST['status'])) {
            return array('success' => 0, 'status' => 'failure');
        }
		$booking = $plugin->createBooking(intval($_POST['booking_id']));

		if (in_array($_POST['status'], array(SLN_Enum_BookingStatus::CONFIRMED, SLN_Enum_BookingStatus::CANCELED))) {
			$booking->setStatus(wp_unslash($_POST['status']));
		}

		$status = SLN_Enum_BookingStatus::getLabel($booking->getStatus());
		$color  = SLN_Enum_BookingStatus::getRealColor($booking->getStatus());
		$weight = 'normal';
		if ($booking->getStatus() == SLN_Enum_BookingStatus::CONFIRMED || $booking->getStatus() == SLN_Enum_BookingStatus::PAID) $weight = 'bold';
		$statusLabel = '<div style="width:14px !important; height:14px; border-radius:14px; border:2px solid '.$color.'; float:left; margin-top:2px;"></div> &nbsp;<span style="color:'.$color.'; font-weight:'.$weight.';">' . $status . '</span>';

		return array('success' => 1, 'status' => $statusLabel);
	}
}
