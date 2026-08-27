<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

/**
 * One-click booking forecasting step.
 *
 * Injected as the FIRST step of the booking wizard whenever the
 * "enabled_one_click_booking" setting is ON.
 *
 * Three render states:
 *
 *   STATE A  – Guest (not logged in)
 *              Shows a "Login" link that toggles an inline login form, plus a
 *              "Continue as guest" link to skip to the normal wizard.
 *
 *   STATE B  – Logged-in customer with ≥ one_click_min_bookings completed
 *              bookings.  Renders the personalised forecast card UI with up to 3
 *              date/time proposals and a "Book now" one-click CTA.
 *
 *   STATE C  – Logged-in customer with insufficient history, or feature skipped.
 *              isValid() returns true immediately so the wizard moves to the next
 *              normal step without any interaction.
 */
class SLN_Shortcode_Salon_ForecastStep extends SLN_Shortcode_Salon_AbstractUserStep {

	const STEP_NAME   = 'forecast';
	const SKIP_BB_KEY = 'skip_forecast';

	/** User meta key (via Customer::getMeta/setMeta): forecast_notify_optin */
	const META_NOTIFY_OPTIN = 'forecast_notify_optin';

	/** @deprecated Legacy session key — cleared on read; skip state lives in BookingBuilder. */
	const SESSION_SKIP_KEY = 'sln_skip_forecast';

	/**
	 * Per-request memoised forecast suggestions.
	 *
	 * isValid() and getForecastViewData() both need the suggestions within the
	 * same render; computing them twice doubles an expensive availability scan.
	 * null = not yet computed.
	 *
	 * @var array|null
	 */
	private $suggestionsCache = null;

	public function getTitleKey() {
		return 'Your next appointment';
	}

	public function getTitleLabel() {
		return __( 'Your next appointment', 'salon-booking-system' );
	}

	// -------------------------------------------------------------------------
	// Step contract
	// -------------------------------------------------------------------------

	/**
	 * Determine whether this step is already satisfied (should be skipped) or
	 * whether it still needs user interaction.
	 *
	 * Returning true means "done, advance" – false means "render me".
	 */
	public function isValid() {

		// Skipped for the current booking attempt (stored on BookingBuilder, not PHP session).
		if ( $this->isForecastSkipped() ) {
			return true;
		}

		// Slot already applied — the wizard iterates every step's isValid() on every
		// request.  Without this guard, a subsequent request (e.g. Summary "Book Now")
		// would fall through to getForecastSuggestions() → verifySlots(), which wipes
		// the BookingBuilder state mid-request, corrupting the availability check in
		// SummaryStep::dispatchForm() and producing a false "slot unavailable" error.
		$bb = $this->getPlugin()->getBookingBuilder();
		if ( $bb->get( 'forecast_origin' ) ) {
			return true;
		}

		// Deep-link from forecast notification email is handled on parse_request
		// (SLN_Action_ForecastNotifyDeepLink) before the wizard renders.

		// Form submitted — process it
		$submitted = isset( $_POST[ 'submit_' . self::STEP_NAME ] ) || isset( $_GET[ 'submit_' . self::STEP_NAME ] );
		if ( $submitted ) {
			return $this->dispatchForm();
		}

		// Not submitted — determine render state
		$state = $this->resolveState();

		if ( 'login' === $state ) {
			// Guest: show login form
			return false;
		}

		if ( 'skip' === $state ) {
			// Logged-in but not enough history → auto-skip
			return true;
		}

		// 'cards' state: verify suggestions are actually available before rendering
		try {
			$suggestions = $this->getForecastSuggestions();

			if ( empty( $suggestions ) ) {
				// No bookable slots found → skip gracefully for this request.
				// Do NOT persist the skip flag in the session: availability can
				// change between page loads (bookings confirmed, new slots opened),
				// so we always re-check on the next visit.
				return true;
			}
		} catch ( Exception $e ) {
			// Forecasting failed → skip gracefully so the normal wizard is shown.
			// Again, do not persist — a transient error should not lock the user
			// out of the forecast feature permanently within the session.
			return true;
		}

		// Suggestions are available: render the cards
		return false;
	}

	/**
	 * Handle form submission.  There are two possible payloads:
	 *
	 *  1. Login form  (login_name + login_password present)
	 *  2. Skip        (sln_skip_forecast = 1)
	 *  3. Slot chosen (sln_forecast_date + sln_forecast_time present)
	 */
	protected function dispatchForm() {
		// --- Skip / "Continue as guest" / "I want different options" ----------
		if ( ! empty( $_POST['sln_skip_forecast'] ) ) {
			$this->saveNotifyOptinPreference();
			$this->setForecastSkipped();

			// Record rejection if the customer was logged in and had suggestions
			if ( is_user_logged_in() ) {
				$customer = new SLN_Wrapper_Customer( get_current_user_id() );
				$service_id = (int) ( $_POST['sln_forecast_service_id'] ?? 0 );
				if ( $service_id > 0 && ! $customer->isEmpty() ) {
					$customer->recordForecastOutcome( $service_id, 'rejected' );
				}
			}

			return true;
		}

		// --- Login form submission ---------------------------------------------
		// Only treat this as a login attempt when credentials were actually
		// entered. Previously this triggered whenever the login field merely
		// existed in the POST, so a guest who continued without typing anything
		// was treated as a failed login and trapped on the login screen.
		if ( ! is_user_logged_in() && ! empty( $_POST['login_name'] ) ) {
			$username = sanitize_text_field( wp_unslash( $_POST['login_name'] ) );
			$password = isset( $_POST['login_password'] ) ? wp_unslash( $_POST['login_password'] ) : '';

			if ( ! $this->dispatchAuth( $username, $password ) ) {
				// Login failed – re-render with errors
				return false;
			}
			// Login succeeded: bust the forecast cache so the cards are built
			// from live availability, not a stale transient from a prior session.
			SLN_Helper_BookingForecaster::bustCache( get_current_user_id(), $this->getCurrentShopId() );

			// Fall through to check whether the newly-logged-in user has
			// booking history.  If not, auto-skip; otherwise re-render in
			// STATE B so they see the forecast cards.
			if ( ! $this->customerHasSufficientHistory() ) {
				return true; // No history → proceed normally
			}
			// Has history → re-render in STATE B (return false so the wizard
			// calls render() again with the updated login state)
			return false;
		}

		// --- Slot selection ---------------------------------------------------
		if ( is_user_logged_in() && isset( $_REQUEST['sln_forecast_date'] ) ) {
			return $this->applyForecastSlot();
		}

		// --- Guest fallthrough -------------------------------------------------
		// A guest submitted the step without entering credentials and without
		// choosing a slot (clicked "Continue to booking", or the skip flag was
		// lost because JavaScript did not run). Never trap a guest on the login
		// screen: proceed into the normal booking wizard as a guest.
		if ( ! is_user_logged_in() ) {
			$this->saveNotifyOptinPreference();
			$this->setForecastSkipped();
			return true;
		}

		// Nothing we understand → re-render
		return false;
	}

	/**
	 * Populate the BookingBuilder with the selected forecast slot and the
	 * logged-in user's profile data, then let the wizard advance to summary
	 * (or fbphone if phone is still required).
	 *
	 * @return bool  true on success, false with errors on failure
	 */
	private function applyForecastSlot() {
		$this->saveNotifyOptinPreference();

		$date         = sanitize_text_field( wp_unslash( $_REQUEST['sln_forecast_date'] ?? '' ) );
		$time         = sanitize_text_field( wp_unslash( $_REQUEST['sln_forecast_time'] ?? '' ) );
		$service_id   = (int) ( $_REQUEST['sln_forecast_service_id'] ?? 0 );
		$attendant_id = (int) ( $_REQUEST['sln_forecast_attendant_id'] ?? 0 );

		if ( empty( $date ) || empty( $time ) || ! $service_id ) {
			$this->addError( __( 'Please select a date and time to continue.', 'salon-booking-system' ) );
			return false;
		}

		$service = new SLN_Wrapper_Service( $service_id );
		if ( $service->isEmpty() ) {
			$this->addError( __( 'The selected service is no longer available.', 'salon-booking-system' ) );
			return false;
		}

		$bb = $this->getPlugin()->getBookingBuilder();
		$bb->emptyData();
		$bb->setDate( $date );
		$bb->setTime( $time );
		$bb->addService( $service );

		if ( $attendant_id > 0 ) {
			$attendant = $this->getPlugin()->createAttendant( $attendant_id );
			if ( ! $attendant->isEmpty() ) {
				$bb->setAttendant( $attendant, $service );
			}
		}

		// Re-validate using the same attendant-aware check as SummaryStep so the
		// accept-path is fully consistent with both slot generation (forecaster) and
		// the final Summary check. If the attendant became busy between card render
		// and click, the user gets a clear message here instead of the confusing
		// "Time-slot already booked" error on the Summary step.
		$ah      = $this->getPlugin()->getAvailabilityHelper();
		$day_dt  = new SLN_DateTime( $date . ' 00:00' );
		$slot_dt = new SLN_DateTime( $date . ' ' . $time );
		$handler = new SLN_Action_Ajax_CheckDateAlt( $this->getPlugin() );
		$slotErrors = $handler->checkDateTimeServicesAndAttendants( $bb->get( 'services' ), $slot_dt );
		if ( ! $ah->isValidDate( \Salon\Util\Date::create( $day_dt ) ) || ! $ah->isValidTime( $slot_dt ) || ! empty( $slotErrors ) ) {
			$bb->emptyData();
			$bb->save();
			$this->addError( __( 'The selected time slot is no longer available. Please choose another.', 'salon-booking-system' ) );
			return false;
		}

		$shop_id = $this->getCurrentShopId();
		if ( $shop_id > 0 ) {
			$bb->set( 'shop', $shop_id );
		}

		// Auto-fill customer details from the logged-in user's profile so the
		// details step isValid() returns true and the wizard advances to summary.
		$user_id         = get_current_user_id();
		$customer_fields = SLN_Enum_CheckoutFields::forRegistration()->appendSmsPrefix();
		$values          = array();
		foreach ( $customer_fields as $key => $field ) {
			$values[ $key ] = $field->getValue( $user_id );
		}
		$this->bindValues( $values );

		// Mark this booking as originating from the forecast feature so the
		// Reports dashboard can count and display forecast adoption stats.
		$bb->set( 'forecast_origin', 1 );

		$bb->save();

		// Record acceptance for progressive learning
		$customer = new SLN_Wrapper_Customer( $user_id );
		if ( ! $customer->isEmpty() ) {
			$customer->recordForecastOutcome( $service_id, 'accepted' );
		}

		SLN_Plugin::addLog( sprintf(
			'[ForecastStep] Slot applied — user=%d service=%d attendant=%d date=%s time=%s',
			$user_id, $service_id, $attendant_id, $date, $time
		) );

		// Redirect directly to the summary/checkout step, bypassing the
		// intermediate wizard steps (services, date, attendant, details).
		// The BookingBuilder is already fully populated.
		$redirect_url = $this->buildSummaryRedirectUrl( $bb->getClientId() );

		if ( wp_doing_ajax() ) {
			throw new SLN_Action_Ajax_RedirectException( $redirect_url );
		} else {
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Build the URL for the summary step redirect, preserving the booking
	 * page URL from the HTTP Referer and appending the required query args.
	 *
	 * @param  string $client_id  The BookingBuilder client identifier.
	 * @return string
	 */
	private function buildSummaryRedirectUrl( $client_id ) {
		$booking_page_id = $this->getPlugin()->getSettings()->getPayPageId();
		$base_url        = ( $booking_page_id && get_post_status( $booking_page_id ) )
			? get_permalink( $booking_page_id )
			: home_url( '/' );

		// Prefer referer when it points at the same booking page (in-wizard submit).
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = wp_sanitize_redirect( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$referer_base = strtok( $referer, '?' );
			if ( $referer_base && ( ! $booking_page_id || untrailingslashit( $referer_base ) === untrailingslashit( $base_url ) ) ) {
				$base_url = $referer_base;
			}
		}

		return add_query_arg(
			array(
				'sln_step_page' => 'summary',
				'sln_client_id' => $client_id,
			),
			$base_url
		);
	}

	/**
	 * Side-effect-free check used by SLN_Shortcode_Salon::getCurrentStep() to
	 * decide whether the injected forecast step should be the rendered first
	 * step or skipped over so the wizard advances to the real first step.
	 *
	 * This mirrors the NON-submit branch of isValid() but deliberately omits
	 * dispatchForm() (login / slot-apply / redirect have side effects and must
	 * never run during step resolution).
	 *
	 * Without this, a logged-in user whose forecast resolves to the 'skip'
	 * state (insufficient history, or no available suggestions) keeps 'forecast'
	 * as the current step. The reversed dispatch pass then renders that step in
	 * skip-state — which outputs nothing — producing a blank booking wizard.
	 *
	 * @return bool true = render the forecast step, false = skip to next step
	 */
	public function shouldDisplayAsFirstStep() {
		if ( $this->isForecastSkipped() ) {
			return false;
		}

		if ( $this->getPlugin()->getBookingBuilder()->get( 'forecast_origin' ) ) {
			return false;
		}

		$state = $this->resolveState();

		if ( 'login' === $state ) {
			return true;
		}

		if ( 'skip' === $state ) {
			return false;
		}

		// 'cards': only render when real suggestions are available.
		try {
			return ! empty( $this->getForecastSuggestions() );
		} catch ( Exception $e ) {
			return false;
		}
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	public function render() {
		return $this->getPlugin()->loadView(
			'shortcode/salon_forecast',
			$this->getForecastViewData()
		);
	}

	protected function getForecastViewData() {
		$base = $this->getViewData();

		$state      = $this->resolveState();
		$suggestions = array();
		$service     = null;
		$attendant   = null;
		$customer_name = '';

		if ( 'cards' === $state ) {
			$customer    = new SLN_Wrapper_Customer( get_current_user_id() );
			$suggestions = $this->getForecastSuggestions();
			$customer_name = $customer->get( 'first_name' );
			$notify_optin  = (bool) $customer->getMeta( self::META_NOTIFY_OPTIN );

			if ( ! empty( $suggestions ) ) {
				$service_id  = $suggestions[0]['service_id'];
				$service     = new SLN_Wrapper_Service( $service_id );
				$att_id      = $suggestions[0]['attendant_id'];
				$attendant   = $att_id > 0 ? $this->getPlugin()->createAttendant( $att_id ) : null;

				// If suggestions came back empty after all (busy salon / no slots), downgrade
				if ( $service->isEmpty() ) {
					$suggestions = array();
					$state       = 'skip';
				}
			} else {
				// No viable slots – quietly skip this step
				$state = 'skip';
			}
		}

		// STATE skip → nothing to persist; isValid() already handles the skip.

		$settings = $this->getPlugin()->getSettings();

		return array_merge( $base, array(
			'forecast_state'  => $state,
			'suggestions'     => $suggestions,
			'service'         => $service,
			'attendant'       => $attendant,
			'customer_name'   => $customer_name,
			'notify_optin'    => isset( $notify_optin ) ? $notify_optin : false,
			'errors'          => $this->getErrors(),
			'settings'        => $settings,
			'plugin'          => $this->getPlugin(),
			'ajaxEnabled'     => $settings->isAjaxEnabled(),
			'current'         => self::STEP_NAME,
			'submitName'      => 'submit_' . self::STEP_NAME,
		) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build (and memoise for this request) the forecast suggestions for the
	 * current logged-in customer.
	 *
	 * Both isValid() and getForecastViewData() call this during a single render;
	 * memoising avoids running the expensive availability scan twice.
	 *
	 * @return array
	 */
	private function getForecastSuggestions() {
		if ( null !== $this->suggestionsCache ) {
			return $this->suggestionsCache;
		}

		$customer   = new SLN_Wrapper_Customer( get_current_user_id() );
		$forecaster = new SLN_Helper_BookingForecaster( $this->getPlugin() );
		$shop_id    = $this->getCurrentShopId();

		$this->suggestionsCache = $forecaster->getSuggestions(
			$customer,
			SLN_Helper_BookingForecaster::DEFAULT_COUNT,
			$shop_id
		);

		return $this->suggestionsCache;
	}

	/**
	 * Resolve which of the three render states applies right now.
	 *
	 * Guests: when the services step is the wizard's first regular step (the
	 * "change steps order" setting), the dedicated login screen is replaced by
	 * a "Returning customer? Log in" tab on the services step itself (see
	 * salon_services.php / _services_login_tab.php) — so the forecast resolves
	 * to 'skip' and the wizard starts one step earlier. After logging in from
	 * that tab, customers with history are routed back here ('cards' state).
	 *
	 * Force guest checkout has the same effect in the default step order
	 * (date first): skip the dedicated login screen so the wizard opens on
	 * date/time. Returning customers can still log in from the layout topbar.
	 *
	 * With the default step order and force guest checkout off there is no
	 * services screen to host the login tab as the opening step, so the
	 * login state is kept.
	 *
	 * @return string 'login' | 'cards' | 'skip'
	 */
	private function resolveState() {
		if ( ! is_user_logged_in() ) {
			$settings = $this->getPlugin()->getSettings();
			if ( $settings->isFormStepsAltOrder() || $settings->get( 'enabled_force_guest_checkout' ) ) {
				return 'skip';
			}
			return 'login';
		}
		if ( $this->customerHasSufficientHistory() ) {
			return 'cards';
		}
		return 'skip';
	}

	/**
	 * Check whether the current user has enough completed bookings to make a
	 * meaningful forecast.
	 *
	 * @return bool
	 */
	private function customerHasSufficientHistory() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$min = (int) $this->getPlugin()->getSettings()->get( 'one_click_min_bookings' );
		if ( $min < 1 ) {
			$min = 1;
		}

		$customer = new SLN_Wrapper_Customer( $user_id );
		if ( $customer->isEmpty() ) {
			return false;
		}

		$completed = $customer->getCompletedBookings();
		return count( $completed ) >= $min;
	}

	/**
	 * Resolve the current shop ID when the Multi-Shops add-on is active.
	 *
	 * @return int Shop post ID, or 0 when no shop context is available.
	 */
	private function getCurrentShopId() {
		if ( isset( $_GET['shop'] ) && intval( $_GET['shop'] ) > 0 ) {
			return (int) $_GET['shop'];
		}

		if ( ! class_exists( '\\SalonMultishop\\Addon' ) ) {
			return 0;
		}

		try {
			$addon = \SalonMultishop\Addon::getInstance();
			if ( $addon && method_exists( $addon, 'getCurrentShop' ) ) {
				$shop = $addon->getCurrentShop();
				if ( $shop && method_exists( $shop, 'getId' ) ) {
					return (int) $shop->getId();
				}
			}
		} catch ( Exception $e ) {
			SLN_Plugin::addLog( '[ForecastStep] Failed to get current shop ID: ' . $e->getMessage() );
		}

		return 0;
	}

	/**
	 * Whether the user chose to skip the forecast for this booking attempt.
	 *
	 * @return bool
	 */
	private function isForecastSkipped() {
		$this->clearLegacyForecastSessionSkip();

		return ! empty( $this->getPlugin()->getBookingBuilder()->get( self::SKIP_BB_KEY ) );
	}

	/**
	 * Persist forecast skip on the BookingBuilder for the current booking only.
	 */
	private function setForecastSkipped() {
		$this->clearLegacyForecastSessionSkip();

		$bb = $this->getPlugin()->getBookingBuilder();
		$bb->set( self::SKIP_BB_KEY, 1 );
		$bb->save();
	}

	/**
	 * Remove the old PHP-session skip flag so returning users are not stuck.
	 */
	private function clearLegacyForecastSessionSkip() {
		if ( ! empty( $_SESSION[ self::SESSION_SKIP_KEY ] ) ) {
			unset( $_SESSION[ self::SESSION_SKIP_KEY ] );
		}
	}

	/**
	 * Persist the email-notification opt-in preference from the forecast form.
	 */
	private function saveNotifyOptinPreference() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$customer = new SLN_Wrapper_Customer( get_current_user_id() );
		if ( $customer->isEmpty() ) {
			return;
		}

		$customer->setMeta(
			self::META_NOTIFY_OPTIN,
			! empty( $_POST['sln_forecast_notify_optin'] ) ? 1 : 0
		);
	}

	/**
	 * Apply slot from forecast notification email URL params and redirect to summary.
	 *
	 * @return bool  true when redirect was issued (script exits), false on validation failure.
	 */
	public function processEmailDeepLink() {
		$_REQUEST['sln_forecast_date']         = sanitize_text_field( wp_unslash( $_GET['sln_fdate'] ?? '' ) );
		$_REQUEST['sln_forecast_time']         = sanitize_text_field( wp_unslash( $_GET['sln_ftime'] ?? '' ) );
		$_REQUEST['sln_forecast_service_id']   = (int) ( $_GET['sln_fservice'] ?? 0 );
		$_REQUEST['sln_forecast_attendant_id'] = (int) ( $_GET['sln_fattendant'] ?? 0 );

		return $this->applyForecastSlot();
	}
}
