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

		// CSRF protection: reject forged/cross-origin requests. The legitimate
		// client (js/discount/salon-discount.js) sends salon.ajax_nonce, created
		// via wp_create_nonce('ajax_post_validation') in SLN_Action_InitScripts.
		if ( ! isset($_POST['security']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation' ) ) {
			$this->addError(__('Invalid security token. Please refresh the page and try again.', 'salon-booking-system'));
			return array('errors' => $this->getErrors(), 'total' => 0);
		}

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
				'orderby'  => 'modified',
				'order'    => 'DESC',
			),
            'post_status' => 'publish',
		);
		$discounts = $plugin->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT)->get($criteria);
		
		$bookingBuilder = $plugin->getBookingBuilder();

		// Prefer the explicit booking token from the form — same approach as ApplyTipsAmount.
		// The session/transient last_id can be stale (a previous booking), which causes
		// the discount to be validated and totals to be computed against the wrong booking.
		// SECURITY: the id is resolved through SLN_Helper_BookingAccess, which only accepts
		// a valid per-booking secure token, the visitor's own session booking, or (for
		// logged-in users) their own/managed booking — blocking tampering with an
		// arbitrary booking by enumerating IDs.
		$bb = null;
		if ( isset( $_POST['sln_booking_id'] ) ) {
			$bb = SLN_Helper_BookingAccess::resolve( $plugin, sanitize_text_field( wp_unslash( $_POST['sln_booking_id'] ) ) );
			if ( $bb ) {
				SLN_Plugin::addLog( '[ApplyDiscountCode] Resolved booking #' . $bb->getId() . ' from POST sln_booking_id' );
			}
		}
		if ( ! $bb ) {
			$bb = $bookingBuilder->getLastBooking();
		}
		
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
			// Create the booking now so we can apply the discount to it.
			// create() returns void; lastId is set inside create — same pattern as SummaryStep::dispatchForm().
			// Pass $clear = false so the builder session data (services, date, time, etc.)
			// is kept after the DRAFT post is created. Clearing it here would leave the
			// customer with an empty builder and force them to restart the booking form.
			try {
				$bookingBuilder->create(SLN_Enum_BookingStatus::DRAFT, false);
				$bb = $bookingBuilder->getLastBooking();
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
		
		// $evalAmount holds the freshly-computed total so both error and success
		// responses use it directly, bypassing any stale meta read.
		$evalAmount    = 0;
		$discountValue = 0;

		if (!empty($discounts)) {
			/** @var SLB_Discount_Wrapper_Discount $discount */
			$discount = reset($discounts);

			$errors = $discount->validateDiscountFullForBB($bb);
			if (empty($errors)) {
				do_action('sln.api.booking.pre_eval', $bb, $discounts);
				$evalAmount    = $bb->evalTotal();
				$discountValue = array_sum($bb->getMeta('discount_amount'));
			} else {
				$this->addError(reset($errors));
				// Recompute without the (invalid) discount so the error response
				// shows the correct current total (including any tips already applied).
				$evalAmount = $bb->evalTotal();
			}
		} else {
			$this->addError(__('Coupon is not valid', 'salon-booking-system'));
			do_action('sln.api.booking.pre_eval', $bb, array());
			$evalAmount = $bb->evalTotal();
		}

		$fee         = SLN_Helper_TransactionFee::getFee($evalAmount);
		$totalToPay  = $evalAmount + $fee;

		// Recompute the amount shown inside the PAY button so the front-end can refresh
		// just the .sln-pay-amount span (deposit vs full total), mirroring the logic in
		// views/payment_method/*/pay.php. Only relevant when online payment is enabled and
		// there is still a balance to pay (the zero-total case swaps the whole button below).
		$payButtonAmount = null;
		if ($plugin->getSettings()->isPayEnabled() && $totalToPay > 0.0) {
			$deposit = $plugin->getSettings()->isPaymentDepositFixedAmount()
				? $bb->getDeposit()
				: $bb->getDeposit(true);
			$payAmount       = $deposit > 0 ? $deposit : $bb->getToPayAmount(false);
			// No space between the currency symbol and the number, matching the initial
			// render in views/payment_method/*/pay.php (e.g. "£82.40").
			$payButtonAmount = $plugin->format()->moneyFormatted($payAmount, true, false, null, false);
		}

		if ($errors = $this->getErrors()) {
			$ret          = compact('errors');
			// Use the freshly-computed total — avoids stale meta returning £0 or
			// a negative value when the previous evalTotal() never saved to cache.
			$ret['total'] = $plugin->format()->money($totalToPay, false, false, true);
			// Do not replace the summary "next" button on error: that container holds
			// the full payment UI (renderPayButton). salon-discount.js would .html() it away.
			// The PAY amount span can still be refreshed safely (the discount was reverted).
			if ($payButtonAmount !== null) {
				$ret['payButtonAmount'] = $payButtonAmount;
			}
		} else {
			$paymentMethod = $plugin->getSettings()->isPayEnabled() ? SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) : false;

			$ret = array(
				'success'  => 1,
				'discount' => $plugin->format()->money($discountValue, false, false, true),
				'total'    => $plugin->format()->money($totalToPay, true, false, true),
				'errors'   => array(
					__('Coupon was applied', 'salon-booking-system')
				)
			);
			if ($totalToPay <= 0.0) {
				$ret['button']     = $plugin->loadView(
					'shortcode/_salon_summary_next_button',
					array(
						'plugin'  => $plugin,
						'booking' => $bb,
					)
				);
				$ret['booking_id'] = $bb->getId();
			} elseif ($payButtonAmount !== null) {
				// Non-zero balance: keep the payment button as-is and just refresh the amount.
				$ret['payButtonAmount'] = $payButtonAmount;
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