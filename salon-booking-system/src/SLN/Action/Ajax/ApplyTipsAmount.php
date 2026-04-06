<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

class SLN_Action_Ajax_ApplyTipsAmount extends SLN_Action_Ajax_Abstract
{
	protected $date;
	protected $time;
	protected $errors = array();

	public function execute()
	{
	    $plugin = $this->plugin;

	    if (!isset($_POST['sln']['tips'])) {
		$this->addError(__('Tips amount is missing', 'salon-booking-system'));
		return array('errors' => $this->getErrors());
	    }

	    $tips = sanitize_text_field(wp_unslash($_POST['sln']['tips']));
	    $tips = trim($tips);

	    if (empty($tips)) {
		$this->addError(__('Please enter a tip amount', 'salon-booking-system'));
	    } elseif ( ! is_numeric($tips) ) {
		$this->addError(__('Tips must be a valid number', 'salon-booking-system'));
	    } elseif ( floatval($tips) < 0.0 ) {
		$this->addError(__('Tips cannot be negative', 'salon-booking-system'));
	    } else {
		$tips           = floatval($tips);
		$bookingBuilder = $plugin->getBookingBuilder();

		// Use the explicit booking ID sent from the form when available.
		// The session/transient can hold a stale last_id (a booking from a
		// previous flow), causing the tip to be applied to the wrong booking.
		$bb = null;
		if ( isset( $_POST['sln_booking_id'] ) ) {
		    $bookingId = intval( $_POST['sln_booking_id'] );
		    if ( $bookingId > 0 ) {
			$bb = $plugin->createBooking( $bookingId );
			SLN_Plugin::addLog( sprintf( 'ApplyTipsAmount: resolved booking #%d from POST sln_booking_id', $bookingId ) );
		    }
		}
		if ( ! $bb ) {
		    $bb = $bookingBuilder->getLastBooking();
		}

		if (!$bb) {
		    SLN_Plugin::addLog('=== ApplyTipsAmount: BOOKING NOT FOUND ===');
		    SLN_Plugin::addLog('Client ID: ' . $bookingBuilder->getClientId());
		    SLN_Plugin::addLog('Using transient: ' . ($bookingBuilder->isUsingTransient() ? 'YES' : 'NO (session)'));
		    SLN_Plugin::addLog('Session ID: ' . session_id());
		    $this->addError(__('Unable to find booking. Please refresh the page and try again.', 'salon-booking-system'));
		} else {
		    $bb->setMeta('tips', $tips);
		    // Capture the return value directly — avoids a meta re-read that can
		    // return stale/zero data if the object cache or a filter interferes.
		    $computedAmount = $bb->evalTotal();
		    $tipsValue      = $bb->getTips();
		    SLN_Plugin::addLog(sprintf('ApplyTipsAmount: Tips applied successfully - Booking ID: %d, Tip: %s, Computed: %s', $bb->getId(), $tips, $computedAmount));
		}
	    }

	    if ($errors = $this->getErrors()) {
		$ret = compact('errors');
	    } else {
		// Build amounts from the freshly-computed value rather than reading
		// back from meta, so a stale cache can never return 0 to the client.
		$settings      = $plugin->getSettings();
		$fee           = SLN_Helper_TransactionFee::getFee($computedAmount);
		$totalAmount   = $computedAmount + $fee;
		$depositAmount = SLN_Helper_PayDepositAdvancedRules::getDeposit($computedAmount, $settings);

		SLN_Plugin::addLog(sprintf(
		    'ApplyTipsAmount: Calculation details - Computed: %s, ToPayAmount: %s, Tips: %s, Deposit: %s',
		    $computedAmount,
		    $totalAmount,
		    $tipsValue,
		    $depositAmount
		));

		$ret = array(
		    'success' => 1,
		    'tips'    => $plugin->format()->money($tipsValue, false, false, true),
		    'total'   => $plugin->format()->money($totalAmount, false, false, true),
		    'deposit' => $plugin->format()->money($depositAmount, false, false, true),
		    'errors'  => array(
			__('Tips was applied', 'salon-booking-system')
		    )
		);
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
