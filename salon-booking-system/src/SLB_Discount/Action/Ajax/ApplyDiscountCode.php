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
				// Fallback: builder state is empty (client_id mismatch / session loss).
				// For logged-in users, look for the most recent DRAFT booking created
				// in the last 2 hours – this covers the mobile scenario where the
				// booking was created during the summary step but the discount AJAX
				// loaded an empty builder due to a different storage context.
				$fallback = $this->findRecentDraftBooking($plugin);
				if ($fallback) {
					SLN_Plugin::addLog('[ApplyDiscountCode] Empty builder state recovered via recent DRAFT fallback (booking #' . $fallback->getId() . ')');
					$bb = $fallback;
				} else {
					$this->addError(__('Please select at least one service before applying a discount code.', 'salon-booking-system'));
					return array(
						'errors' => $this->getErrors(),
						'total'  => 0,
					);
				}
			} else {
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

	/**
	 * Attempt to recover the current booking when the BookingBuilder state is empty.
	 *
	 * This handles the mobile edge-case where the discount AJAX loads an empty
	 * builder (wrong client_id or session loss) even though a DRAFT booking was
	 * already created during the summary-step render.
	 *
	 * Strategy (in order):
	 *  1. Logged-in user  → most recent DRAFT booking authored by them (≤ 2 hours old)
	 *  2. Guest user      → most recent DRAFT booking matching the current IP (≤ 2 hours old)
	 *
	 * @param SLN_Plugin $plugin
	 * @return SLN_Wrapper_Booking|null
	 */
	protected function findRecentDraftBooking($plugin)
	{
		$twoHoursAgo = date('Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS);

		$queryArgs = array(
			'post_type'        => SLN_Plugin::POST_TYPE_BOOKING,
			'post_status'      => SLN_Enum_BookingStatus::DRAFT,
			'posts_per_page'   => 1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'date_query'       => array(
				array('after' => $twoHoursAgo, 'inclusive' => true),
			),
		);

		if (is_user_logged_in()) {
			$queryArgs['author'] = get_current_user_id();
		} else {
			// For guests, match by stored IP address meta (if available)
			$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
			if (empty($ip)) {
				SLN_Plugin::addLog('[ApplyDiscountCode] findRecentDraftBooking: guest user, no IP available');
				return null;
			}
			$queryArgs['meta_query'] = array(
				array(
					'key'   => '_' . SLN_Plugin::POST_TYPE_BOOKING . '_ip',
					'value' => $ip,
				),
			);
		}

		$query = new WP_Query($queryArgs);

		if ($query->have_posts()) {
			$post    = $query->posts[0];
			$booking = $plugin->createBooking($post->ID);
			if ($booking && !empty($booking->getServices())) {
				return $booking;
			}
		}

		SLN_Plugin::addLog('[ApplyDiscountCode] findRecentDraftBooking: no suitable DRAFT booking found');
		return null;
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