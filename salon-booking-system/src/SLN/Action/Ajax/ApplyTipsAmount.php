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
		$tips = floatval($tips);
		$bookingBuilder = $plugin->getBookingBuilder();
		$bb = $bookingBuilder->getLastBooking();
		
		if (!$bb) {
		    // DIAGNOSTIC: Log detailed information about why booking is not found
		    SLN_Plugin::addLog('=== ApplyTipsAmount: BOOKING NOT FOUND ===');
		    SLN_Plugin::addLog('Client ID: ' . $bookingBuilder->getClientId());
		    SLN_Plugin::addLog('Using transient: ' . ($bookingBuilder->isUsingTransient() ? 'YES' : 'NO (session)'));
		    SLN_Plugin::addLog('Session ID: ' . session_id());
		    SLN_Plugin::addLog('Has services data: ' . ($bookingBuilder->get('services') ? 'YES' : 'NO'));
		    
		    // Check if there's a booking ID in the request
		    if (isset($_POST['sln_booking_id'])) {
			SLN_Plugin::addLog('Booking ID from POST: ' . sanitize_text_field($_POST['sln_booking_id']));
		    }
		    
		    $this->addError(__('Unable to find booking. Please refresh the page and try again.', 'salon-booking-system'));
		} else {
		    $bb->setMeta('tips', $tips);
		    $bb->evalTotal();
		    $tipsValue = $bb->getTips();
		    SLN_Plugin::addLog(sprintf('ApplyTipsAmount: Tips applied successfully - Booking ID: %d, Tip: %s', $bb->getId(), $tips));
		}
	    }

	    if ($errors = $this->getErrors()) {
		$ret = compact('errors');
	    } else {
		$totalAmount = $bb->getToPayAmount(false);
		$rawAmount = $bb->getAmount();
		$depositAmount = $bb->getDeposit();
		
		SLN_Plugin::addLog(sprintf(
		    'ApplyTipsAmount: Calculation details - Raw Amount: %s, ToPayAmount: %s, Tips: %s, Deposit: %s',
		    $rawAmount,
		    $totalAmount,
		    $tipsValue,
		    $depositAmount
		));
		
		$ret = array(
		    'success'  => 1,
		    'tips'     => $plugin->format()->money($tipsValue, false, false, true),
		    'total'    => $plugin->format()->money($totalAmount, false, false, true),
		    'deposit'  => $plugin->format()->money($depositAmount, false, false, true),
		    'errors'   => array(
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