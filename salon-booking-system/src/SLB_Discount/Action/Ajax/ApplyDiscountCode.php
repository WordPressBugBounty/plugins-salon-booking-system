<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

class SLB_Discount_Action_Ajax_ApplyDiscountCode extends SLN_Action_Ajax_Abstract
{
	protected $date;
	protected $time;
	protected $errors = array();

	public function execute()
	{
		$plugin = $this->plugin;
		$code   = sanitize_text_field(wp_unslash($_POST['sln']['discount']));

		$criteria = array(
			'@wp_query' => array(
				'meta_query' => array(
					array(
						'key'   => '_' . SLB_Discount_Plugin::POST_TYPE_DISCOUNT . '_code',
						'value' => $code,
					),
					array(
						'key'   => '_' . SLB_Discount_Plugin::POST_TYPE_DISCOUNT . '_type',
						'value' => SLB_Discount_Enum_DiscountType::DISCOUNT_CODE,
					),
				),
			),
            'post_status' => 'publish',
		);
		$discounts = $plugin->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT)->get($criteria);
		
		// FIX: Try to get the last booking first, if not available use the booking builder
		// This handles both cases: booking already created (DRAFT) or still in progress
		$bookingBuilder = $plugin->getBookingBuilder();
		$bb = $bookingBuilder->getLastBooking();
		
            // If no saved booking exists, the booking is still in progress in the builder
		// Create a temporary booking object from the builder data for discount validation
		if (!$bb) {
			// Check if booking builder has data (services, date, time, etc.)
			if (!$bookingBuilder->get('services') || empty($bookingBuilder->get('services'))) {
				$this->addError(__('Please select at least one service before applying a discount code.', 'salon-booking-system'));
				return array(
					'errors' => $this->getErrors(),
					'total'  => 0,
				);
			}
			
			// Create the booking now so we can apply the discount to it
			try {
				$bb = $bookingBuilder->create(SLN_Enum_BookingStatus::DRAFT);
			} catch (Exception $e) {
				$this->addError(__('Unable to process discount. Please try again.', 'salon-booking-system'));
				return array(
					'errors' => $this->getErrors(),
					'total'  => 0,
				);
			}
		}
		
		// Final validation: ensure we have a booking object
		if (!$bb) {
			$this->addError(__('Your booking session has expired. Please start a new booking to apply a discount code.', 'salon-booking-system'));
			return array(
				'errors' => $this->getErrors(),
				'total'  => 0,
			);
		}
		
		if (!empty($discounts)) {
			/** @var SLB_Discount_Wrapper_Discount $discount */
			$discount = reset($discounts);
			

			$errors   = $discount->validateDiscountFullForBB($bb);
			if (empty($errors)) {
				do_action('sln.api.booking.pre_eval', $bb, $discounts);
				$bb->evalTotal();
				$discountValue = array_sum($bb->getMeta('discount_amount'));
			}
			else {
				$this->addError(reset($errors));
			}
		}
		else {
			$this->addError(__('Coupon is not valid', 'salon-booking-system'));
			do_action('sln.api.booking.pre_eval', $bb, array());
			$bb->evalTotal();
		}

		if ($errors = $this->getErrors()) {
			$ret = compact('errors');
			$ret['total'] = $plugin->format()->money($bb->getToPayAmount(false), false, false, true);
			$ret['button'] = $plugin->loadView('shortcode/_salon_summary_next_button', array('plugin' => $plugin));
		} else {
            $paymentMethod = $plugin->getSettings()->isPayEnabled() ? SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) : false;


            $ret = array(
				'success'  => 1,
				'discount' => $plugin->format()->money($discountValue, false, false, true),
				'total'    => $plugin->format()->money($bb->getToPayAmount(false), true, false, true),
				'errors'   => array(
					__('Coupon was applied', 'salon-booking-system')
				)
			);
			if($bb->getToPayAmount(false) <= 0.0){
				$ret['button'] = $plugin->loadView('shortcode/_salon_summary_next_button', array('plugin' => $plugin));
			}

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