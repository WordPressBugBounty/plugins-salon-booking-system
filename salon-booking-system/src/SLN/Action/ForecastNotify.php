<?php

/**
 * Sends forecast slot notification emails to opted-in customers.
 *
 * Runs daily via wp-cron. Uses the same prediction engine as the forecast step
 * to determine when a customer is due for their next visit, then emails up to
 * three verified bookable slots.
 */
class SLN_Action_ForecastNotify {

	const META_OPTIN     = 'forecast_notify_optin';
	const META_LAST_SENT = 'forecast_notify_last_sent';

	const NOTIFY_WINDOW  = 7;  // days before predicted visit to start notifying
	const MIN_INTERVAL   = 14; // minimum days between notification emails

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function executeEmail() {
		if ( apply_filters( 'sln.scheduled.forecast_notify_email', false ) ) {
			return;
		}

		if ( ! $this->plugin->getSettings()->get( 'enabled_one_click_booking' ) ) {
			return;
		}

		SLN_TimeFunc::startRealTimezone();

		$this->plugin->addLog( 'forecast notify email execution' );

		foreach ( $this->getOptedInCustomers() as $customer ) {
			if ( $this->shouldNotify( $customer ) ) {
				$this->send( $customer );
			}
		}

		$this->plugin->addLog( 'forecast notify email execution ended' );

		SLN_TimeFunc::endRealTimezone();
	}

	/**
	 * @param SLN_Wrapper_Customer $customer
	 */
	private function send( SLN_Wrapper_Customer $customer ) {
		$shop_id     = $this->getCustomerShopId( $customer );
		$forecaster  = new SLN_Helper_BookingForecaster( $this->plugin );
		$suggestions = $forecaster->getSuggestions(
			$customer,
			SLN_Helper_BookingForecaster::DEFAULT_COUNT,
			$shop_id
		);

		if ( empty( $suggestions ) ) {
			return;
		}

		$service_id = (int) $suggestions[0]['service_id'];
		$service    = new SLN_Wrapper_Service( $service_id );
		if ( $service->isEmpty() ) {
			return;
		}

		$this->plugin->sendMail(
			'mail/forecast_notify',
			compact( 'customer', 'service', 'suggestions' )
		);

		$customer->setMeta( self::META_LAST_SENT, time() );
		$this->plugin->addLog( 'forecast notify email sent to user ' . $customer->getId() );
	}

	/**
	 * @param SLN_Wrapper_Customer $customer
	 * @return bool
	 */
	private function shouldNotify( SLN_Wrapper_Customer $customer ) {
		$predicted = $customer->getNextBookingTime();
		if ( ! $predicted ) {
			return false;
		}

		$now         = SLN_TimeFunc::currentDateTime()->getTimestamp();
		$days_until  = ( $predicted - $now ) / DAY_IN_SECONDS;

		if ( $days_until > self::NOTIFY_WINDOW ) {
			return false;
		}

		if ( $days_until < -self::NOTIFY_WINDOW ) {
			// Predicted visit is long past — still notify once within window semantics.
			return false;
		}

		$last_sent = (int) $customer->getMeta( self::META_LAST_SENT );
		if ( $last_sent > 0 && ( $now - $last_sent ) < ( self::MIN_INTERVAL * DAY_IN_SECONDS ) ) {
			return false;
		}

		// Skip if customer already has a future confirmed booking.
		if ( $this->customerHasUpcomingBooking( $customer ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @return SLN_Wrapper_Customer[]
	 */
	private function getOptedInCustomers() {
		$ret = array();

		$user_query = new WP_User_Query(
			array(
				'role'       => SLN_Plugin::USER_ROLE_CUSTOMER,
				'meta_key'   => '_sln_' . self::META_OPTIN,
				'meta_value' => '1',
				'number'     => -1,
			)
		);

		foreach ( $user_query->get_results() as $user ) {
			$customer = new SLN_Wrapper_Customer( $user );
			if ( ! $customer->isEmpty() && is_email( $customer->get( 'user_email' ) ) ) {
				$ret[] = $customer;
			}
		}

		return $ret;
	}

	/**
	 * @param SLN_Wrapper_Customer $customer
	 * @return int
	 */
	private function getCustomerShopId( SLN_Wrapper_Customer $customer ) {
		$bookings = $customer->getCompletedBookings(
			array(
				'meta_key' => '_sln_booking_date',
				'orderby'  => 'meta_value',
				'order'    => 'DESC',
				'number'   => 1,
			)
		);

		if ( empty( $bookings ) ) {
			return 0;
		}

		return (int) get_post_meta( $bookings[0]->getId(), '_sln_booking_shop', true );
	}

	/**
	 * @param SLN_Wrapper_Customer $customer
	 * @return bool
	 */
	private function customerHasUpcomingBooking( SLN_Wrapper_Customer $customer ) {
		$today = SLN_TimeFunc::currentDateTime()->format( 'Y-m-d' );

		$bookings = $customer->getBookings(
			array(
				'post_status' => array(
					SLN_Enum_BookingStatus::CONFIRMED,
					SLN_Enum_BookingStatus::PAID,
					SLN_Enum_BookingStatus::PAY_LATER,
					SLN_Enum_BookingStatus::PENDING,
					SLN_Enum_BookingStatus::PENDING_PAYMENT,
				),
				'meta_query'  => array(
					array(
						'key'     => '_sln_booking_date',
						'value'   => $today,
						'compare' => '>=',
						'type'    => 'DATE',
					),
				),
				'number'      => 1,
			)
		);

		return ! empty( $bookings );
	}

	/**
	 * Booking form page URL.
	 *
	 * @param SLN_Plugin $plugin
	 * @return string
	 */
	public static function getBookingPageUrl( SLN_Plugin $plugin ) {
		$page_id = $plugin->getSettings()->getPayPageId();
		if ( $page_id && get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}

		return home_url( '/' );
	}

	/**
	 * Deep-link URL for a forecast slot from notification email.
	 *
	 * @param SLN_Plugin $plugin
	 * @param array      $slot
	 * @return string
	 */
	public static function buildSlotDeepLink( SLN_Plugin $plugin, array $slot ) {
		return add_query_arg(
			array(
				'sln_forecast_notify' => 1,
				'sln_fdate'           => $slot['date'],
				'sln_ftime'           => $slot['time'],
				'sln_fservice'        => (int) $slot['service_id'],
				'sln_fattendant'      => (int) $slot['attendant_id'],
			),
			self::getBookingPageUrl( $plugin )
		);
	}

	/**
	 * One-click unsubscribe URL for forecast notification emails.
	 *
	 * @param SLN_Wrapper_Customer $customer
	 * @return string
	 */
	public static function getOptoutUrl( SLN_Wrapper_Customer $customer ) {
		return add_query_arg(
			array(
				'sln_forecast_notify_optout' => self::generateOptoutToken( $customer->getId() ),
				'sln_forecast_notify_uid'    => $customer->getId(),
			),
			self::getBookingPageUrl( SLN_Plugin::getInstance() )
		);
	}

	/**
	 * @param int    $user_id
	 * @param string $token
	 * @return bool
	 */
	public static function verifyOptoutToken( $user_id, $token ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || empty( $token ) ) {
			return false;
		}

		return hash_equals( self::generateOptoutToken( $user_id ), $token );
	}

	/**
	 * @param int $user_id
	 * @return string
	 */
	public static function generateOptoutToken( $user_id ) {
		return sha1( (int) $user_id . wp_salt( 'auth' ) );
	}
}
