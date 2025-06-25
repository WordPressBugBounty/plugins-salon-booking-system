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
		$bb       = $plugin->getBookingBuilder()->getLastBooking();
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