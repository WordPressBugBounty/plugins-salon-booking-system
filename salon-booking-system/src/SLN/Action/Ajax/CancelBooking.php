<?php // algolplus
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

class SLN_Action_Ajax_CancelBooking extends SLN_Action_Ajax_Abstract
{
	private $errors = array();

	public function execute()
	{
		if (!is_user_logged_in()) {
			return array( 'redirect' => wp_login_url());
		}

		// Verify nonce for CSRF protection
		if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'ajax_post_validation')) {
			return array('errors' => array(__('Invalid security token. Please refresh the page and try again.', 'salon-booking-system')));
		}

		// Validate booking ID
		if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
			return array('errors' => array(__('Invalid booking ID.', 'salon-booking-system')));
		}

		$ret = array();
		$plugin = SLN_Plugin::getInstance();
		$booking = $plugin->createBooking(intval($_POST['id']));

		$available = $booking->getUserId() == get_current_user_id();
		$cancellationEnabled = $plugin->getSettings()->get('cancellation_enabled');
		$outOfTime = ($booking->getStartsAt()->getTimestamp() - time() ) < $plugin->getSettings()->get('hours_before_cancellation') * 3600;

		if ($cancellationEnabled && !$outOfTime && $available) {
			$booking->setStatus(SLN_Enum_BookingStatus::CANCELED);
			$booking = $plugin->createBooking(intval($_POST['id']));
			$plugin->getBookingCache()->processBooking($booking);

			// Auto-trash if enabled
			$trashAction = new SLN_Action_TrashCancelledBooking($plugin);
			$trashAction->execute($booking);

		} elseif (!$available) {
			$this->addError(__("You don't have access", 'salon-booking-system'));
		} elseif (!$cancellationEnabled) {
			$this->addError(__('Cancellation disabled', 'salon-booking-system'));
		} elseif ($outOfTime) {
			$this->addError(__('Out of time', 'salon-booking-system'));
		}

		if ($errors = $this->getErrors()) {
			$ret = compact('errors');
		} else {
			$ret = array('success' => 1);
		}

		return $ret;
	}

	protected function addError($err)
	{
		$this->errors[] = $err;
	}

	public function getErrors()
	{
		return $this->errors;
	}
}
