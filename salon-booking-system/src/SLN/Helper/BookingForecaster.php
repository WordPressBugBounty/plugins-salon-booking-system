<?php
/**
 * One-click booking forecasting engine.
 *
 * Given a returning logged-in customer, builds up to $count verified, bookable
 * date/time/attendant proposals centred around the statistically predicted
 * next-visit date.
 *
 * Results are cached per user per calendar-day in a 10-minute transient so the
 * availability scan does not re-run on every AJAX step transition.
 */
class SLN_Helper_BookingForecaster {

	const CACHE_TTL       = 600; // 10 minutes
	const MAX_SCAN_DAYS   = 45;  // stop scanning after this many days
	const DEFAULT_COUNT   = 3;

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Build forecast proposals for a customer.
	 *
	 * Returns an array of up to $count items:
	 * [
	 *   'date'          => 'Y-m-d',
	 *   'time'          => 'H:i',
	 *   'service_id'    => int,
	 *   'attendant_id'  => int|0,
	 *   'label'         => string  // human-readable relative label
	 * ]
	 *
	 * Returns an empty array when no proposal can be built (no history, service
	 * unavailable, etc.).
	 *
	 * @param SLN_Wrapper_Customer $customer
	 * @param int                  $count
	 * @param int                  $shop_id  Current shop ID (0 = single-shop / no add-on).
	 * @return array
	 */
	public function getSuggestions( SLN_Wrapper_Customer $customer, $count = self::DEFAULT_COUNT, $shop_id = 0 ) {
		if ( $customer->isEmpty() ) {
			return array();
		}

		// The availability scans below (verifySlots/scanSlots) borrow the shared,
		// persisted BookingBuilder as scratch space — they call emptyData()/save()
		// on it, which would otherwise wipe a customer's in-progress booking that
		// is mid-checkout. Snapshot the real booking data up front and restore it
		// in the finally block so a forecast computation can never corrupt it.
		$bb            = $this->plugin->getBookingBuilder();
		$bookingBackup = $bb->getData();

		try {
			$shop_id   = (int) $shop_id;
			$cache_key = 'sln_forecast_' . $customer->getId() . '_' . $shop_id . '_' . gmdate( 'Ymd' );
			$cached    = get_transient( $cache_key );

			if ( false !== $cached ) {
				// Re-verify each cached slot is still actually available.
				// If any slot has been booked since caching, drop the cache and
				// rebuild from scratch so stale suggestions are never shown.
				$valid = $this->verifySlots( $cached, $shop_id );
				if ( count( $valid ) === count( $cached ) ) {
					return $cached;
				}
				// At least one slot went stale — bust and fall through to rebuild.
				delete_transient( $cache_key );
			}

			$result = $this->buildSuggestions( $customer, $count, $shop_id );

			set_transient( $cache_key, $result, self::CACHE_TTL );

			return $result;
		} finally {
			// Always restore the customer's real booking, whatever happened above
			// (success, empty result, or exception part-way through a scan).
			$bb->setData( $bookingBackup );
			$bb->save();
		}
	}

	/**
	 * Filter a suggestions array to only those slots whose date/time is still
	 * available according to the live availability engine.
	 *
	 * Mirrors the BookingBuilder setup used in scanSlots() so the availability
	 * engine has the correct service context when running its checks.
	 * Restores the BB to an empty state after verification.
	 *
	 * @param array $suggestions
	 * @param int   $shop_id
	 * @return array  The subset of $suggestions that are still bookable.
	 */
	private function verifySlots( array $suggestions, $shop_id = 0 ) {
		if ( empty( $suggestions ) ) {
			return array();
		}

		$ah    = $this->plugin->getAvailabilityHelper();
		$bb    = $this->plugin->getBookingBuilder();
		$valid = array();

		// Save and restore the BB state so verifySlots() has no side effects on
		// the current in-progress booking. (Previously this method left the BB
		// empty, which corrupted the availability context for any later step in
		// the same request.)
		$bbSnapshot = $bb->getData();

		// Group slots by service so we only reconfigure the BB when the service changes.
		$current_service_id = null;

		foreach ( $suggestions as $slot ) {
			if ( empty( $slot['date'] ) || empty( $slot['time'] ) || empty( $slot['service_id'] ) ) {
				continue;
			}

			// (Re)configure the BookingBuilder when service changes.
			if ( (int) $slot['service_id'] !== $current_service_id ) {
				$service = new SLN_Wrapper_Service( (int) $slot['service_id'] );
				if ( $service->isEmpty() ) {
					continue;
				}
				$bb->emptyData();
				$bb->addService( $service );
				if ( $shop_id > 0 ) {
					$bb->set( 'shop', $shop_id );
				}
				if ( ! empty( $slot['attendant_id'] ) ) {
					$attendant = $this->plugin->createAttendant( (int) $slot['attendant_id'] );
					if ( ! $attendant->isEmpty() ) {
						$bb->setAttendant( $attendant, $service );
					}
				}
				$current_service_id = (int) $slot['service_id'];
			}

			$day_dt = new SLN_DateTime( $slot['date'] . ' 00:00' );
			if ( ! $ah->isValidDate( \Salon\Util\Date::create( $day_dt ) ) ) {
				continue;
			}

			$slot_dt = new SLN_DateTime( $slot['date'] . ' ' . $slot['time'] );
			if ( ! $ah->isValidTime( $slot_dt ) || ! $this->slotPassesFullCheck( $slot_dt ) ) {
				continue;
			}

			$valid[] = $slot;
		}

		// Restore the BB to exactly the state it was in before verification.
		$bb->setData( $bbSnapshot );

		return $valid;
	}

	/**
	 * Invalidate the forecast cache for a customer (call after a new booking is saved).
	 *
	 * @param int $user_id
	 * @param int $shop_id Current shop ID (0 = single-shop / no add-on).
	 */
	public static function bustCache( $user_id, $shop_id = 0 ) {
		$user_id = (int) $user_id;
		$shop_id = (int) $shop_id;
		$suffix  = gmdate( 'Ymd' );

		delete_transient( 'sln_forecast_' . $user_id . '_' . $shop_id . '_' . $suffix );
		if ( $shop_id > 0 ) {
			delete_transient( 'sln_forecast_' . $user_id . '_0_' . $suffix );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * @param SLN_Wrapper_Customer $customer
	 * @param int                  $count
	 * @param int                  $shop_id
	 * @return array
	 */
	private function buildSuggestions( SLN_Wrapper_Customer $customer, $count, $shop_id = 0 ) {
		$shop_id = (int) $shop_id;

		// Resolve booking history: shop-scoped when multi-shop, with fallback.
		$history = array();
		if ( $shop_id > 0 ) {
			$history = $customer->getCompletedBookingsByShop( $shop_id );
		}
		if ( empty( $history ) ) {
			$history = $customer->getCompletedBookings();
		}
		if ( empty( $history ) ) {
			return array();
		}

		// --- 1. Determine favourite service (recency-weighted) ----------------
		$service_id = $this->getFavouriteServiceFromBookings( $history );
		if ( ! $service_id ) {
			return array();
		}

		$service = new SLN_Wrapper_Service( $service_id );
		if ( $service->isEmpty() || $service->isHideOnFrontend() ) {
			return array();
		}

		// Ensure the inferred service is available at the current shop.
		if ( $shop_id > 0 ) {
			$service_shops = array_map( 'intval', (array) $service->getMeta( 'shops' ) );
			if ( ! empty( $service_shops ) && ! in_array( $shop_id, $service_shops, true ) ) {
				return array();
			}
		}

		// --- 2. Determine favourite attendant (recency-weighted, may be 0) ----
		$attendant_id = (int) $this->getFavouriteAttendantFromBookings( $history, $service_id );

		if ( $attendant_id > 0 ) {
			$attendant = $this->plugin->createAttendant( $attendant_id );
			if ( $attendant->isEmpty() ) {
				$attendant_id = 0;
			}
		}

		// --- 3. Determine scan start date (recency-weighted interval) ----------
		$predicted_ts = $this->calcNextBookingTimeFromBookings( $history );
		$settings     = $this->plugin->getSettings();
		$hours_before = $settings->getHoursBeforeFrom();

		$earliest = new SLN_DateTime( 'now' );
		$earliest->modify( $hours_before );

		if ( $predicted_ts ) {
			$predicted_date = new SLN_DateTime( '@' . $predicted_ts );
			$predicted_date->setTimezone( SLN_DateTime::getWpTimezone() );
			$scan_start = clone $predicted_date;
			$scan_start->modify( '-7 days' );
			if ( $scan_start < $earliest ) {
				$scan_start = clone $earliest;
			}
		} else {
			$scan_start = clone $earliest;
		}

		$scan_start->setTime( 0, 0, 0 );

		// --- 4. Build preferred time list and preferred day-of-week list ------
		$preferred_times = $this->getPreferredTimesFromBookings( $history );
		$preferred_days  = $this->getPreferredDaysFromBookings( $history );

		// --- 5. Scan for available slots (day-of-week biased) -----------------
		$slots = $this->scanSlots( $service, $attendant_id, $scan_start, $preferred_times, $preferred_days, $count, array(), $shop_id );

		if ( count( $slots ) < $count && $attendant_id > 0 ) {
			$extra = $this->scanSlots( $service, 0, $scan_start, $preferred_times, $preferred_days, $count - count( $slots ), $slots, $shop_id );
			$slots = array_merge( $slots, $extra );
		}

		if ( empty( $slots ) ) {
			return array();
		}

		// --- 6. Attach metadata -----------------------------------------------
		foreach ( $slots as &$slot ) {
			$slot['service_id']   = $service_id;
			$slot['attendant_id'] = isset( $slot['attendant_id'] ) ? $slot['attendant_id'] : $attendant_id;
			$slot['label']        = $this->buildLabel( $slot['date'] );
		}
		unset( $slot );

		return $slots;
	}

	/**
	 * Scan days from $scan_start for available slots, prioritising the
	 * customer's preferred days of the week.
	 *
	 * Strategy: build a two-pass candidate list over MAX_SCAN_DAYS —
	 *   Pass 1: only days whose day-of-week is in $preferred_days.
	 *   Pass 2: remaining days, in calendar order.
	 * This ensures preferred days are offered first without skipping
	 * non-preferred days entirely (fallback when preferred days are full).
	 *
	 * @param SLN_Wrapper_Service $service
	 * @param int                 $attendant_id    0 = any
	 * @param SLN_DateTime        $scan_start
	 * @param array               $preferred_times ['H:i', …]
	 * @param array               $preferred_days  [0-6, …]  (0=Sun … 6=Sat)
	 * @param int                 $needed
	 * @param array               $already_found   slots already collected
	 * @param int                 $shop_id         Current shop ID (0 = single-shop).
	 * @return array
	 */
	private function scanSlots(
		$service,
		$attendant_id,
		SLN_DateTime $scan_start,
		array $preferred_times,
		array $preferred_days,
		$needed,
		array $already_found = array(),
		$shop_id = 0
	) {
		$shop_id    = (int) $shop_id;
		$skip_dates = array_column( $already_found, 'date' );
		$ah         = $this->plugin->getAvailabilityHelper();
		$bb         = $this->plugin->getBookingBuilder();

		$bb->emptyData();
		$bb->addService( $service );
		if ( $shop_id > 0 ) {
			$bb->set( 'shop', $shop_id );
		}
		if ( $attendant_id > 0 ) {
			$attendant = $this->plugin->createAttendant( $attendant_id );
			if ( ! $attendant->isEmpty() ) {
				$bb->setAttendant( $attendant, $service );
			}
		}
		$bb->save();

		// Build sorted candidate day list over the scan window
		$preferred_candidates = array();
		$fallback_candidates  = array();
		$cursor               = clone $scan_start;

		for ( $d = 0; $d < self::MAX_SCAN_DAYS; $d++ ) {
			$date_str = $cursor->format( 'Y-m-d' );
			// PHP N % 7 maps Mon(1)→1 … Sat(6)→6, Sun(7)→0
			$dow = (int) $cursor->format( 'N' ) % 7;
			if ( ! empty( $preferred_days ) && in_array( $dow, $preferred_days, true ) ) {
				$preferred_candidates[] = $date_str;
			} else {
				$fallback_candidates[] = $date_str;
			}
			$cursor->modify( '+1 day' );
		}

		$candidates = array_merge( $preferred_candidates, $fallback_candidates );

		$slots = array();
		foreach ( $candidates as $date_str ) {
			if ( count( $slots ) >= $needed ) {
				break;
			}
			if ( in_array( $date_str, $skip_dates, true ) ) {
				continue;
			}
			$day_dt = new SLN_DateTime( $date_str . ' 00:00' );
			if ( ! $ah->isValidDate( \Salon\Util\Date::create( $day_dt ) ) ) {
				continue;
			}
			$found_time = $this->findTimeOnDay( $ah, $day_dt, $preferred_times, $attendant_id );
			if ( $found_time ) {
				$slots[]      = array(
					'date'         => $date_str,
					'time'         => $found_time,
					'attendant_id' => $attendant_id,
				);
				$skip_dates[] = $date_str;
			}
		}

		$bb->emptyData();
		$bb->save();

		return $slots;
	}

	/**
	 * Find the first available time on a given day, preferring $preferred_times.
	 *
	 * @param SLN_Helper_Availability $ah
	 * @param SLN_DateTime            $day
	 * @param array                   $preferred_times
	 * @param int                     $attendant_id
	 * @return string|false  'H:i' or false
	 */
	private function findTimeOnDay( $ah, SLN_DateTime $day, array $preferred_times, $attendant_id ) {
		$date_str = $day->format( 'Y-m-d' );

		// Try preferred times first, then all available times for the day
		$times_to_try = $preferred_times;

		// Supplement with available times from intervals if preferred list is short
		$intervals_obj = $this->plugin->getIntervals( new SLN_DateTime( $date_str . ' 00:00' ) );
		$intervals_arr = $intervals_obj->toArray();
		if ( ! empty( $intervals_arr['times'] ) ) {
			foreach ( $intervals_arr['times'] as $t ) {
				if ( ! in_array( $t, $times_to_try, true ) ) {
					$times_to_try[] = $t;
				}
			}
		}

		foreach ( $times_to_try as $time_str ) {
			$dt = new SLN_DateTime( $date_str . ' ' . $time_str );
			// isValidTime() only checks the general salon hourly capacity
			// (parallels_hour); it does NOT verify that the specific attendant is
			// free. We must run the full attendant-aware check so the forecaster
			// never suggests a slot that the Summary step would later reject with
			// "Time-slot already booked".
			if ( $ah->isValidTime( $dt ) && $this->slotPassesFullCheck( $dt ) ) {
				return $time_str;
			}
		}

		return false;
	}

	/**
	 * Run the same attendant-aware availability check the Summary step uses
	 * (SLN_Action_Ajax_CheckDateAlt::checkDateTimeServicesAndAttendants).
	 *
	 * The BookingBuilder must already be configured with the target service and
	 * attendant by the caller (scanSlots / verifySlots set this up).
	 *
	 * @param SLN_DateTime $dt  Candidate slot date+time.
	 * @return bool  true when the slot is fully bookable for the chosen attendant.
	 */
	private function slotPassesFullCheck( SLN_DateTime $dt ) {
		$bb      = $this->plugin->getBookingBuilder();
		$services = $bb->get( 'services' );
		if ( empty( $services ) ) {
			return false;
		}
		$handler = new SLN_Action_Ajax_CheckDateAlt( $this->plugin );
		$errors  = $handler->checkDateTimeServicesAndAttendants( $services, $dt );

		return empty( $errors );
	}

	/**
	 * Build the preferred-time list from a booking history array.
	 *
	 * @param SLN_Wrapper_Booking[] $bookings
	 * @return array  ['H:i', ...]
	 */
	private function getPreferredTimesFromBookings( array $bookings ) {
		$preferred = array();

		if ( ! empty( $bookings ) ) {
			$times = array();
			foreach ( $bookings as $booking ) {
				$time = SLN_Plugin::getInstance()->format()->time( $booking->getStartsAt() );
				if ( ! isset( $times[ $time ] ) ) {
					$times[ $time ] = 0;
				}
				$times[ $time ]++;
			}

			$max_count = max( $times );
			foreach ( $times as $time_label => $count ) {
				if ( $count === $max_count ) {
					$parsed = $this->parseTimeLabel( $time_label );
					if ( $parsed ) {
						$preferred[] = $parsed;
					}
				}
			}
		}

		// Fallback slots spanning a typical business day in 30-min increments
		$fallback = array(
			'09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
			'14:00', '14:30', '15:00', '15:30', '16:00', '16:30',
		);

		return array_unique( array_merge( $preferred, $fallback ) );
	}

	/**
	 * Return preferred days of the week from a booking history array.
	 *
	 * @param SLN_Wrapper_Booking[] $bookings
	 * @return array [0-6] where 0=Sunday … 6=Saturday
	 */
	private function getPreferredDaysFromBookings( array $bookings ) {
		if ( empty( $bookings ) ) {
			return array();
		}

		$days_of_week = SLN_Enum_DaysOfWeek::toArray();
		$days_of_week = array_fill_keys( array_keys( $days_of_week ), 0 );

		foreach ( $bookings as $booking ) {
			$days_of_week[ $booking->getStartsAt()->format( 'N' ) % 7 ]++;
		}

		return array_map( 'intval', array_keys( $days_of_week, max( $days_of_week ) ) );
	}

	/**
	 * Recency-weighted favourite primary service from booking history.
	 *
	 * @param SLN_Wrapper_Booking[] $bookings
	 * @return int|false
	 */
	private function getFavouriteServiceFromBookings( array $bookings ) {
		if ( empty( $bookings ) ) {
			return false;
		}

		$now    = time();
		$scores = array();

		foreach ( $bookings as $booking ) {
			$days_ago = max( 0, ( $now - $booking->getStartsAt()->getTimestamp() ) / DAY_IN_SECONDS );
			$weight   = exp( -$days_ago / SLN_Wrapper_Customer::RECENCY_DECAY_DAYS );

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
	 * Recency-weighted favourite attendant for a service from booking history.
	 *
	 * @param SLN_Wrapper_Booking[] $bookings
	 * @param int                   $service_id
	 * @return int|false
	 */
	private function getFavouriteAttendantFromBookings( array $bookings, $service_id ) {
		if ( empty( $bookings ) || empty( $service_id ) ) {
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
			if ( is_array( $att_id ) || (int) $att_id <= 0 ) {
				continue;
			}
			$att_id   = (int) $att_id;
			$days_ago = max( 0, ( $now - $booking->getStartsAt()->getTimestamp() ) / DAY_IN_SECONDS );
			$weight   = exp( -$days_ago / SLN_Wrapper_Customer::RECENCY_DECAY_DAYS );

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
	 * Predict next booking timestamp from booking history (recency-weighted gaps).
	 *
	 * @param SLN_Wrapper_Booking[] $bookings
	 * @return int|false Unix timestamp
	 */
	private function calcNextBookingTimeFromBookings( array $bookings ) {
		if ( empty( $bookings ) ) {
			return false;
		}

		usort( $bookings, array( $this, 'sortDescByStartsAt' ) );
		$last_ts = $bookings[0]->getStartsAt()->getTimestamp();

		$bookings = array_slice( $bookings, 0, 7 );
		$last_id  = count( $bookings ) - 1;

		$weighted_days = 0.0;
		$total_weight  = 0.0;

		foreach ( $bookings as $k => $b ) {
			if ( $k < $last_id ) {
				$interval = (int) $b->getStartsAt()->diff( $bookings[ $k + 1 ]->getStartsAt() )->days;
				$interval = $interval > 0 ? $interval : 0;
				$weight   = exp( -$k * 0.5 );
				$weighted_days += $interval * $weight;
				$total_weight  += $weight;
			}
		}

		if ( $total_weight < 0.0001 ) {
			return false;
		}

		$predicted_interval = (int) round( $weighted_days / $total_weight ) + 1;
		return strtotime( "+{$predicted_interval} days", $last_ts );
	}

	/**
	 * @param SLN_Wrapper_Booking $a
	 * @param SLN_Wrapper_Booking $b
	 * @return int
	 */
	private function sortDescByStartsAt( $a, $b ) {
		return ( $a->getStartsAt()->getTimestamp() >= $b->getStartsAt()->getTimestamp() ? -1 : 1 );
	}

	/**
	 * Build the preferred-time list for a customer: their historical favourites
	 * come first, followed by a fallback set of common afternoon slots.
	 *
	 * @param SLN_Wrapper_Customer $customer
	 * @return array  ['H:i', ...]
	 */
	private function getPreferredTimes( SLN_Wrapper_Customer $customer ) {
		return $this->getPreferredTimesFromBookings( $customer->getCompletedBookings() );
	}

	/**
	 * Return the customer's preferred days of the week as integer array [0-6]
	 * where 0=Sunday … 6=Saturday (PHP N%7 convention).
	 * Returns an empty array when there is no data.
	 *
	 * @param SLN_Wrapper_Customer $customer
	 * @return array
	 */
	private function getPreferredDays( SLN_Wrapper_Customer $customer ) {
		return $this->getPreferredDaysFromBookings( $customer->getCompletedBookings() );
	}

	/**
	 * Attempt to parse a localised time label (e.g. "9:00 AM", "14:30") into H:i.
	 *
	 * @param string $label
	 * @return string|false
	 */
	private function parseTimeLabel( $label ) {
		// Try strtotime first (handles "9:00 AM", "14:30", etc.)
		$ts = strtotime( $label );
		if ( false !== $ts ) {
			return gmdate( 'H:i', $ts );
		}
		// Regex fallback: extract HH:MM
		if ( preg_match( '/(\d{1,2}):(\d{2})/', $label, $m ) ) {
			return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
		}
		return false;
	}

	/**
	 * Build a human-readable relative label for a date string.
	 *
	 * @param string $date_str  'Y-m-d'
	 * @return string
	 */
	private function buildLabel( $date_str ) {
		$today    = new SLN_DateTime( 'today' );
		$date     = new SLN_DateTime( $date_str );
		$diff     = (int) $today->diff( $date )->days;
		$sign     = $date >= $today ? 1 : -1;
		$diff_signed = $diff * $sign;

		if ( $diff_signed === 0 ) {
			return __( 'Today', 'salon-booking-system' );
		}
		if ( $diff_signed === 1 ) {
			return __( 'Tomorrow', 'salon-booking-system' );
		}
		if ( $diff_signed > 0 && $diff_signed <= 6 ) {
			return sprintf(
				// translators: %d = number of days
				__( 'In %d days', 'salon-booking-system' ),
				$diff_signed
			);
		}
		if ( $diff_signed > 6 && $diff_signed <= 13 ) {
			return __( 'Next week', 'salon-booking-system' );
		}
		$weeks = (int) round( $diff_signed / 7 );
		if ( $weeks > 1 && $weeks <= 8 ) {
			return sprintf(
				// translators: %d = number of weeks
				__( 'In %d weeks', 'salon-booking-system' ),
				$weeks
			);
		}
		return $date->format( 'D, d M' );
	}
}
