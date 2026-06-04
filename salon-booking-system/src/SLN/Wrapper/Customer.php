<?php
// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query
class SLN_Wrapper_Customer {

	private $bookings = array();
	/** @var array<int, SLN_Wrapper_Booking[]> Per-shop completed booking cache. */
	private $bookingsByShop = array();
	private $object;
	private $countOfBookingsForEstimateNextBooking = 7;

	/**
	 * SLN_Wrapper_Customer constructor.
	 *
	 * @param WP_User|int $object
	 */
	public function __construct($object, $checkRole = true) {
		if (!is_object($object)) {
			$object = get_user_by('id', $object);
		}
		if ((!$checkRole && $object) || self::isCustomer($object) || self::isAdmin($object)) {
			$this->object = $object;
		}
		else {
			$this->object = new WP_User();
		}
	}

	public function isEmpty() {
		return empty($this->object->ID);
	}

	function getId() {
		if (!$this->isEmpty()) {
			return $this->object->ID;
		}
	}

	public function get($key) {
		return $this->object->get($key);
	}

	public function getMeta($key) {
		$key = "_sln_{$key}";
		return apply_filters("$key.get", get_user_meta($this->getId(), $key, true));
	}

	public function setMeta($key, $value) {
		$key = "_sln_{$key}";
		update_user_meta($this->getId(), $key, apply_filters("$key.set", $value));
	}

	public function deleteMeta($key) {
		$key = "_sln_{$key}";
		delete_user_meta($this->getId(), $key);
	}

	public function getName() {
		if (!$this->isEmpty()) {
			$name      = array();
			$firstname = $this->get('first_name');
			$lastname  = $this->get('last_name');
			if (!empty($firstname)) {
				$name[] = $firstname;
			}
			if (!empty($lastname)) {
				$name[] = $lastname;
			}

			return implode(' ', $name);
		}
	}

	/**
	 * @param int|null $period In seconds
	 *
	 * @return int|float
	 */
	public function getCountOfReservations($period = null) {
		$count = 0;
		if (!$this->isEmpty()) {

			$bookings = $this->getCompletedBookings();

			$count    = count($bookings);

			if (!empty($period)) {
				$customer_time = SLN_TimeFunc::getPostTimestamp($this->object);
				$current_time  = time();

				$count /= ($current_time - $customer_time) / $period;
			}
		}

		return round($count);
	}

	/**
	 * @param int|null $period In seconds
	 *
	 * @return string
	 */
	public function getAmountOfReservations($period = null) {
		$amount = 0;
		if (!$this->isEmpty()) {

			$bookings = $this->getCompletedBookings();

			foreach ($bookings as $booking) {
				$amount += $booking->getAmount();
			}

			if (!empty($period)) {
				$customer_time = SLN_TimeFunc::getPostTimestamp($this->object);
				$current_time  = time();

				$amount /= ($current_time - $customer_time) / $period;
			}
		}

		return floatval($amount);
	}

	public function getAverageCountOfServices() {
		$countServices = 0;
		if (!$this->isEmpty()) {

			$bookings      = $this->getCompletedBookings();

			$countBookings = count($bookings);

			if ($countBookings) {
				foreach ($bookings as $booking) {
					$countServices += count($booking->getServicesIds());
				}

				$countServices /= $countBookings;
			}
		}

		return empty($countServices) ? 0 : (floor($countServices) === floatval($countServices) ? $countServices : number_format(floatval($countServices), 2));
	}

	/**
	 * @return array|bool Array of int[0-6] or false
	 */
	public function getFavouriteWeekDays() {
		$favDays = false;
		if (!$this->isEmpty()) {
			$bookings = $this->getCompletedBookings();

			if (!empty($bookings)) {
				$daysOfWeek = SLN_Enum_DaysOfWeek::toArray();
				$daysOfWeek = array_fill_keys(array_keys($daysOfWeek), 0);

				foreach ($bookings as $booking) {
					$daysOfWeek[$booking->getStartsAt()->format('N') % 7]++;
				}

				$favDays = array_keys($daysOfWeek, max($daysOfWeek));
			}
		}

		return $favDays;
	}

	// -------------------------------------------------------------------------
	// Forecast engine constants
	// -------------------------------------------------------------------------

	/**
	 * Decay constant (days) for recency weighting.
	 * A booking N days ago receives weight exp(-N / RECENCY_DECAY_DAYS).
	 * At 90 days: ~0.37×; at 180 days: ~0.14×; at 365 days: ~0.02×.
	 */
	const RECENCY_DECAY_DAYS = 90;

	/** Maximum number of stored forecast outcomes per customer. */
	const FORECAST_OUTCOMES_MAX = 50;

	// -------------------------------------------------------------------------

	/**
	 * Returns the highest-scoring primary service ID using recency-weighted
	 * frequency. Recent bookings carry exponentially more weight than old ones.
	 *
	 * @return int|false  Service post ID, or false if no completed bookings exist.
	 */
	public function getFavouriteService() {
		if ( $this->isEmpty() ) {
			return false;
		}

		$bookings = $this->getCompletedBookings();
		if ( empty( $bookings ) ) {
			return false;
		}

		$now    = time();
		$scores = array();

		foreach ( $bookings as $booking ) {
			$days_ago = max( 0, ( $now - $booking->getStartsAt()->getTimestamp() ) / DAY_IN_SECONDS );
			$weight   = exp( -$days_ago / self::RECENCY_DECAY_DAYS );

			foreach ( $booking->getServicesIds() as $service_id ) {
				$service_id = (int) $service_id;
				$service    = new SLN_Wrapper_Service( $service_id );
				if ( $service->isEmpty() || $service->isSecondary() ) {
					continue;
				}
				$scores[ $service_id ] = isset( $scores[ $service_id ] )
					? $scores[ $service_id ] + $weight
					: $weight;
			}
		}

		if ( empty( $scores ) ) {
			return false;
		}

		arsort( $scores );
		reset( $scores );
		return key( $scores );
	}

	/**
	 * Returns the highest-scoring attendant ID for a given service using
	 * recency-weighted frequency. Returns false when no preference can be
	 * inferred.
	 *
	 * @param int $service_id  Service post ID.
	 * @return int|false  Attendant post ID, or false.
	 */
	public function getFavouriteAttendantForService( $service_id ) {
		if ( $this->isEmpty() || empty( $service_id ) ) {
			return false;
		}

		$bookings = $this->getCompletedBookings();
		if ( empty( $bookings ) ) {
			return false;
		}

		$now    = time();
		$scores = array();

		foreach ( $bookings as $booking ) {
			$attendants_map = $booking->getAttendantsIds();
			if ( ! isset( $attendants_map[ $service_id ] ) ) {
				continue;
			}
			$att_id = $attendants_map[ $service_id ];
			// Skip "any" (0) and multi-attendant arrays
			if ( is_array( $att_id ) || (int) $att_id <= 0 ) {
				continue;
			}
			$att_id   = (int) $att_id;
			$days_ago = max( 0, ( $now - $booking->getStartsAt()->getTimestamp() ) / DAY_IN_SECONDS );
			$weight   = exp( -$days_ago / self::RECENCY_DECAY_DAYS );

			$scores[ $att_id ] = isset( $scores[ $att_id ] )
				? $scores[ $att_id ] + $weight
				: $weight;
		}

		if ( empty( $scores ) ) {
			return false;
		}

		arsort( $scores );
		reset( $scores );
		return key( $scores );
	}

	/**
	 * @return array|bool Array of times or false
	 */
	public function getFavouriteTimes() {
		$favTimes = false;
		if (!$this->isEmpty()) {
			$bookings = $this->getCompletedBookings();

			if (!empty($bookings)) {
				$times    = array();
				foreach ($bookings as $booking) {
					$time = SLN_Plugin::getInstance()->format()->time($booking->getStartsAt());
					if (!isset($times[$time])) {
						$times[$time] = 0;
					}
					$times[$time]++;
				}

				$favTimes = array_keys($times, max($times));
			}
		}

		return $favTimes;
	}

	/**
	 * @param array $args
	 *
	 * @return SLN_Wrapper_Booking[]
	 */
	public function getCompletedBookings($args = array()) {
		$args['post_status'] = array(SLN_Enum_BookingStatus::PAY_LATER, SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED);

		$bookings            = $this->getBookings($args);

		return $bookings;
	}

	/**
	 * Completed bookings scoped to a specific shop (Multi-Shops add-on).
	 *
	 * Uses BookingRepository shop criteria so only bookings assigned to the
	 * given shop (or legacy bookings with no shop meta) are returned.
	 *
	 * @param int   $shop_id Shop post ID.
	 * @param array $args    Optional extra WP_Query args.
	 * @return SLN_Wrapper_Booking[]
	 */
	public function getCompletedBookingsByShop( $shop_id, $args = array() ) {
		$shop_id = (int) $shop_id;
		if ( $this->isEmpty() || $shop_id <= 0 ) {
			return array();
		}

		if ( ! isset( $this->bookingsByShop[ $shop_id ] ) ) {
			$args['post_status'] = array(
				SLN_Enum_BookingStatus::PAY_LATER,
				SLN_Enum_BookingStatus::PAID,
				SLN_Enum_BookingStatus::CONFIRMED,
			);
			$args['author'] = $this->object->ID;

			$repo = SLN_Plugin::getInstance()->getRepository( SLN_Plugin::POST_TYPE_BOOKING );
			$this->bookingsByShop[ $shop_id ] = $repo->get(
				array(
					'@query'    => '',
					'@wp_query' => $args,
					'shop'      => $shop_id,
				)
			);
		}

		return $this->bookingsByShop[ $shop_id ];
	}

	/**
	 * @param array $args
	 *
	 * @return SLN_Wrapper_Booking[]
	 * @throws Exception
	 */
	public function getBookings($args = array()) {
		if (!$this->isEmpty() && empty($this->bookings)) {
			$args['author'] = $this->object->ID;

			$repo           = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_BOOKING);
			$this->bookings = $repo->get(
				array(
					'@query' => '',
					'@wp_query' => $args,
				)
			);
		}

		return $this->bookings;
	}

	/**
	 * @return string|false 'Y-m-d H:i'
	 */
	public function getLastBookingTime() {
		$timestamp = $this->getMeta('last_booking_time');

		if (!$timestamp) {
			$timestamp = $this->calcLastBookingTime();
		}

		return $timestamp;
	}

	/**
	 * @return string|false 'Y-m-d H:i'
	 */
	public function calcLastBookingTime() {
		$timestamp = false;
		$args = array(
			'meta_key' => '_sln_booking_date',
			'orderby'  => 'meta_value',
			'order'    => 'DESC'
		);
		$bookings = $this->getCompletedBookings($args);

		if (!empty($bookings)) {
			usort($bookings, array($this, 'sortDescByStartsAt'));
			/** @var SLN_Wrapper_Booking $last */
			$last = reset($bookings);
			$timestamp = $last->getStartsAt()->getTimestamp();
		}

		return $timestamp;
	}

	/**
	 * @return string|false 'Y-m-d'
	 */
	public function getNextBookingTime() {
		$timestamp = $this->getMeta('next_booking_time');

		if (!$timestamp) {
			$timestamp = $this->calcNextBookingTime();
		}

		return $timestamp;
	}

	/**
	 * Predict the next booking date using a recency-weighted average of past
	 * inter-booking intervals. The most recent gap counts most, older gaps
	 * decay exponentially (half-weight every ~1.4 intervals, k=0 → weight 1.0).
	 *
	 * @return int|false  Unix timestamp of predicted next visit, or false.
	 */
	public function calcNextBookingTime() {
		$lastDate = $this->getLastBookingTime();
		if ( ! $lastDate ) {
			return false;
		}

		$args = array(
			'meta_key' => '_sln_booking_date',
			'orderby'  => 'meta_value',
			'order'    => 'DESC',
		);
		$bookings = $this->getCompletedBookings( $args );
		if ( empty( $bookings ) ) {
			return false;
		}

		usort( $bookings, array( $this, 'sortDescByStartsAt' ) );
		$bookings = array_slice( $bookings, 0, $this->countOfBookingsForEstimateNextBooking );

		$last_id       = count( $bookings ) - 1;
		$weighted_days = 0.0;
		$total_weight  = 0.0;

		foreach ( $bookings as $k => $b ) {
			if ( $k < $last_id ) {
				$interval = (int) $b->getStartsAt()->diff( $bookings[ $k + 1 ]->getStartsAt() )->days;
				$interval = $interval > 0 ? $interval : 0;
				// k=0 is the most-recent pair → highest weight
				$weight        = exp( -$k * 0.5 );
				$weighted_days += $interval * $weight;
				$total_weight  += $weight;
			}
		}

		if ( $total_weight < 0.0001 ) {
			return false;
		}

		$predicted_interval = (int) round( $weighted_days / $total_weight ) + 1;
		return strtotime( "+{$predicted_interval} days", $lastDate );
	}

	/**
	 * @param SLN_Wrapper_Booking $a
	 * @param SLN_Wrapper_Booking $b
	 *
	 * @return int
	 */
	private function sortDescByStartsAt($a, $b) {
		return ($a->getStartsAt()->getTimestamp() >= $b->getStartsAt()->getTimestamp() ? -1 : 1 );
	}

	public function setLastBookingTime($timestamp) {
		$this->setMeta('last_booking_time', $timestamp);
	}

	public function setNextBookingTime($timestamp) {
		$this->setMeta('next_booking_time', $timestamp);
	}

	public function getHash() {
	    $hash = $this->getMeta('hash');
	    if (empty($hash)) {
            $hash = $this->generateHash();
            $this->setMeta('hash', $hash);
        }

		// SECURITY FIX: Reduce token validity from 24 hours to 1 hour
		// Previous: DAY_IN_SECONDS (86,400 seconds) - gave attackers 24-hour window
		// New: HOUR_IN_SECONDS (3,600 seconds) - 96% reduction in attack window
		// Still sufficient for email delivery while dramatically improving security
		set_transient("sln_customer_login_{$this->getId()}", $hash, HOUR_IN_SECONDS);

        return $hash;
    }

	private function generateHash() {
		do {
			// SECURITY FIX: Use cryptographically secure random token instead of weak MD5
			// Previous: md5(user_id:time) - predictable and vulnerable to brute-force
			// New: random_bytes(32) - 256 bits of entropy, impossible to predict
			// This fixes Critical Security Vulnerability: Broken Authentication (CVSS 8.1)
			
			// Generate 32 bytes of cryptographically secure random data
			$random_bytes = random_bytes(32);
			
			// Convert to URL-safe base64 (replace +/ with -_, remove = padding)
			$hash = rtrim(strtr(base64_encode($random_bytes), '+/', '-_'), '=');
			
			// Ensure consistent length by padding with additional secure random data if needed
			while (strlen($hash) < 64) {
				$additional_bytes = random_bytes(8);
				$hash .= rtrim(strtr(base64_encode($additional_bytes), '+/', '-_'), '=');
			}
			
			// Use exactly 64 characters (384 bits of entropy) - vastly more secure than 8-char MD5
			$hash = substr($hash, 0, 64);
			
		} while(self::getCustomerIdByHash($hash));

		return $hash;
	}

	public static function getCustomerIdByHash($hash) {
		global $wpdb;

		$userid = $wpdb->get_var(
		    $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='_sln_hash' AND meta_value=%s",
			$hash
		    )
		);

		return $userid;
	}

	public static function getCustomerIdByFacebookID($fbID) {
		global $wpdb;

		$userid = $wpdb->get_var(
		    $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='_sln_fb_id' AND meta_value=%s",
			$fbID
		    )
		);

		return $userid;
	}

	public static function isCustomer($object) {
		$settings = SLN_Plugin::getInstance()->getSettings();
		if($settings->get('enabled_force_guest_checkout')){
			return true;
		}
		if (!is_object($object)) {
			$object = get_user_by('id', $object);
			if (!$object) {
				return false;
			}
		}

		if (in_array(SLN_Plugin::USER_ROLE_CUSTOMER, $object->roles) || $settings->get('enabled_guest_checkout')) {
			return true;
		}
		else {
			return false;
		}
	}

	public static function isAdmin($object) {
		if (!is_object($object)) {
			$object = get_user_by('id', $object);
			if (!$object) {
				return false;
			}
		}

		if (in_array('administrator', $object->roles)) {
			return true;
		}
		else {
			return false;
		}
	}

        public function getCountOfYearsCustomerExists() {

            $countYears = 0;

            if (!$this->isEmpty()) {
                $countYears = (int)((time() - strtotime($this->get('user_registered'))) / YEAR_IN_SECONDS);
            }

            return $countYears;
	}

        public function getFidelityScore() {

            $fidelityScore = 0;

            if (!$this->isEmpty()) {
                $discountScore = (int)apply_filters('sln.customer.fidelity_score.discounts_score', 0, $this);
                $fidelityScore   = (int)(($this->getAmountOfReservations() * 0.6 + $this->getCountOfReservations() * 0.3 + $this->getCountOfYearsCustomerExists() * 0.1) / 10) - $discountScore;
                $fidelityScore   = $fidelityScore > 0 ? $fidelityScore : 0;
            }

            return $fidelityScore;
	}

        public function getPhotos() {
            $photos = $this->getMeta('photos');
            return is_array($photos) ? $photos : array();
	}

        public function setPhotos($photos) {
            $this->setMeta('photos', $photos);
	}

	// -------------------------------------------------------------------------
	// Forecast outcome tracking
	// -------------------------------------------------------------------------

	/**
	 * Record whether the forecast suggestion was accepted or rejected.
	 *
	 * @param int    $service_id  The service that was suggested.
	 * @param string $outcome     'accepted' | 'rejected'
	 */
	public function recordForecastOutcome( $service_id, $outcome ) {
		if ( $this->isEmpty() ) {
			return;
		}
		$outcomes = $this->getMeta( 'forecast_outcomes' );
		if ( ! is_array( $outcomes ) ) {
			$outcomes = array();
		}

		array_unshift( $outcomes, array(
			'service_id' => (int) $service_id,
			'outcome'    => $outcome,
			'timestamp'  => time(),
		) );

		// Keep only the most recent N outcomes
		$outcomes = array_slice( $outcomes, 0, self::FORECAST_OUTCOMES_MAX );
		$this->setMeta( 'forecast_outcomes', $outcomes );
	}

	/**
	 * Returns the forecast acceptance rate (0.0–1.0) for an optional service.
	 * Returns null when there is not enough data (fewer than 3 outcomes).
	 *
	 * @param int|null $service_id  Scope to a specific service, or null for all.
	 * @return float|null
	 */
	public function getForecastAcceptanceRate( $service_id = null ) {
		$outcomes = $this->getMeta( 'forecast_outcomes' );
		if ( empty( $outcomes ) || ! is_array( $outcomes ) ) {
			return null;
		}

		if ( null !== $service_id ) {
			$outcomes = array_values( array_filter( $outcomes, function ( $o ) use ( $service_id ) {
				return isset( $o['service_id'] ) && (int) $o['service_id'] === (int) $service_id;
			} ) );
		}

		if ( count( $outcomes ) < 3 ) {
			return null; // not enough data
		}

		$accepted = count( array_filter( $outcomes, function ( $o ) {
			return isset( $o['outcome'] ) && $o['outcome'] === 'accepted';
		} ) );

		return (float) $accepted / count( $outcomes );
	}

	/**
	 * Returns the raw forecast outcome log.
	 *
	 * @return array
	 */
	public function getForecastOutcomes() {
		$outcomes = $this->getMeta( 'forecast_outcomes' );
		return is_array( $outcomes ) ? $outcomes : array();
	}

}