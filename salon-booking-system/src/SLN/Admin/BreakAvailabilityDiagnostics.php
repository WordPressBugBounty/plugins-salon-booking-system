<?php

/**
 * Read-only diagnostics for nested breaks, timeslots, and attendant validation (overlap / overbooking).
 */
class SLN_Admin_BreakAvailabilityDiagnostics {

	/**
	 * @param SLN_Plugin $plugin
	 * @param int        $booking_a_id Primary booking (e.g. long service with break).
	 * @param int        $booking_b_id Optional second booking on same calendar day to compare / validate.
	 * @return array
	 */
	public static function run( SLN_Plugin $plugin, $booking_a_id, $booking_b_id = 0 ) {
		$result = array(
			'error'                => null,
			'environment'          => array(),
			'booking_a'            => null,
			'booking_b'            => null,
			'booking_a_lines'      => array(),
			'booking_b_lines'      => array(),
			'timeslot_slice'       => array(),
			'validation_rebooking' => array(
				'label' => '',
				'lines' => array(),
			),
			'validation_booking_b' => array(
				'label' => '',
				'lines' => array(),
			),
			'insights'             => array(),
		);

		$booking_a_id = absint( $booking_a_id );
		$booking_b_id = absint( $booking_b_id );

		if ( ! $booking_a_id ) {
			$result['error'] = __( 'Enter a primary booking ID.', 'salon-booking-system' );
			return $result;
		}

		$post_a = get_post( $booking_a_id );
		if ( ! $post_a || SLN_Plugin::POST_TYPE_BOOKING !== $post_a->post_type ) {
			$result['error'] = __( 'Primary ID is not a valid salon booking.', 'salon-booking-system' );
			return $result;
		}

		if ( class_exists( 'SLN_Helper_Availability_Cache' ) ) {
			SLN_Helper_Availability_Cache::clearCache();
		}

		$settings = $plugin->getSettings();
		$result['environment'] = array(
			'availability_mode'                    => $settings->getAvailabilityMode(),
			'nested_bookings_enabled'              => $settings->isNestedBookingsEnabled() ? 'yes' : 'no',
			'do_not_nest_same_booking_services'    => $settings->isDoNotNestSameBookingServicesEnabled() ? 'yes' : 'no',
			'reservation_interval_enabled'         => $settings->get( 'reservation_interval_enabled' ) ? 'yes' : 'no',
			'minutes_between_reservation'          => (string) $settings->get( 'minutes_between_reservation' ),
			'wp_timezone'                          => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '',
			'day_bookings_class'                   => '',
		);

		$repo   = $plugin->getRepository( SLN_Plugin::POST_TYPE_BOOKING );
		$book_a = $repo->create( $booking_a_id );
		$result['booking_a'] = self::summarize_booking( $book_a );

		$result['booking_a_lines'] = self::collect_booking_lines( $plugin, $book_a );

		$slice = self::build_timeslot_slice( $plugin, $book_a, null );
		$result['timeslot_slice']       = $slice['rows'];
		$result['environment']['day_bookings_class'] = $slice['day_bookings_class'];

		$ah = $plugin->getAvailabilityHelper();
		self::append_revalidation( $result, $ah, $book_a );

		if ( $booking_b_id ) {
			$post_b = get_post( $booking_b_id );
			if ( ! $post_b || SLN_Plugin::POST_TYPE_BOOKING !== $post_b->post_type ) {
				$result['insights'][] = __( 'Secondary ID is not a booking; skipped B-specific checks.', 'salon-booking-system' );
			} else {
				$book_b = $repo->create( $booking_b_id );
				$result['booking_b']       = self::summarize_booking( $book_b );
				$result['booking_b_lines'] = self::collect_booking_lines( $plugin, $book_b );

				if ( $book_a->getDate()->format( 'Y-m-d' ) !== $book_b->getDate()->format( 'Y-m-d' ) ) {
					$result['insights'][] = sprintf(
						/* translators: 1: date A, 2: date B */
						__( 'Bookings are on different calendar days (%1$s vs %2$s). Timeslot slice is still built from booking A’s day only.', 'salon-booking-system' ),
						$book_a->getDate()->format( 'Y-m-d' ),
						$book_b->getDate()->format( 'Y-m-d' )
					);
				}

				self::append_validation_for_booking( $result, $ah, $book_b, 'validation_booking_b' );
			}
		}

		self::build_insights( $result, $book_a );

		if ( class_exists( 'SLN_Helper_Availability_Cache' ) ) {
			SLN_Helper_Availability_Cache::clearCache();
		}

		return $result;
	}

	/**
	 * @param SLN_Wrapper_Booking $booking
	 * @return array
	 */
	private static function summarize_booking( SLN_Wrapper_Booking $booking ) {
		return array(
			'id'         => $booking->getId(),
			'edit_url'   => admin_url( 'post.php?post=' . (int) $booking->getId() . '&action=edit' ),
			'date'       => $booking->getDate() ? $booking->getDate()->format( 'Y-m-d' ) : '',
			'starts_at'  => $booking->getStartsAt() ? $booking->getStartsAt()->format( 'Y-m-d H:i' ) : '',
			'ends_at'    => $booking->getEndsAt() ? $booking->getEndsAt()->format( 'Y-m-d H:i' ) : '',
			'status'     => $booking->getStatus(),
		);
	}

	/**
	 * @param SLN_Plugin          $plugin
	 * @param SLN_Wrapper_Booking $booking
	 * @return array
	 */
	private static function collect_booking_lines( SLN_Plugin $plugin, SLN_Wrapper_Booking $booking ) {
		$out = array();
		foreach ( $booking->getBookingServices()->getItems() as $idx => $bs ) {
			$svc = $bs->getService();
			$att = $bs->getAttendant();
			$row = array(
				'index'             => $idx,
				'service_id'        => $svc ? $svc->getId() : 0,
				'service_title'     => $svc ? $svc->getName() : '',
				'starts_at'         => $bs->getStartsAt() ? $bs->getStartsAt()->format( 'Y-m-d H:i' ) : '',
				'ends_at'           => $bs->getEndsAt() ? $bs->getEndsAt()->format( 'Y-m-d H:i' ) : '',
				'duration_his'      => $bs->getDuration() ? $bs->getDuration()->format( 'H:i' ) : '',
				'break_duration_his'=> $bs->getBreakDuration() ? $bs->getBreakDuration()->format( 'H:i' ) : '',
				'total_duration_his'=> $bs->getTotalDuration() ? $bs->getTotalDuration()->format( 'H:i' ) : '',
				'break_starts_at'   => '',
				'break_ends_at'     => '',
				'break_duration_data'=> null,
				'service_meta_break_data' => null,
				'attendant_id'      => ( is_object( $att ) && method_exists( $att, 'getId' ) ) ? $att->getId() : null,
				'attendant_name'    => ( is_object( $att ) && method_exists( $att, 'getName' ) ) ? $att->getName() : '',
			);
			if ( $svc ) {
				$row['break_duration_data'] = $svc->getBreakDurationData();
				$row['service_meta_break_data'] = array(
					'_sln_service_break_duration'      => get_post_meta( $svc->getId(), '_sln_service_break_duration', true ),
					'_sln_service_break_duration_data'=> get_post_meta( $svc->getId(), '_sln_service_break_duration_data', true ),
				);
			}
			try {
				$bsa = $bs->getBreakStartsAt();
				$bea = $bs->getBreakEndsAt();
				$row['break_starts_at'] = $bsa ? $bsa->format( 'Y-m-d H:i' ) : '';
				$row['break_ends_at']   = $bea ? $bea->format( 'Y-m-d H:i' ) : '';
			} catch ( Throwable $e ) {
				$row['break_starts_at'] = '(error: ' . $e->getMessage() . ')';
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * @param SLN_Plugin               $plugin
	 * @param SLN_Wrapper_Booking      $booking
	 * @param SLN_Wrapper_Booking|null $current_for_daybookings
	 * @return array{rows: array, day_bookings_class: string}
	 */
	private static function build_timeslot_slice( SLN_Plugin $plugin, SLN_Wrapper_Booking $booking, $current_for_daybookings ) {
		$ah    = $plugin->getAvailabilityHelper();
		$items = $booking->getBookingServices()->getItems();
		if ( empty( $items ) ) {
			return array( 'rows' => array(), 'day_bookings_class' => '' );
		}
		$day = $booking->getDate();
		if ( ! $day ) {
			return array( 'rows' => array(), 'day_bookings_class' => '' );
		}
		$probe = new SLN_DateTime( $day->format( 'Y-m-d' ) . ' 12:00:00', SLN_TimeFunc::getWpTimezone() );
		$ah->setDate( $probe, $current_for_daybookings );
		$db  = $ah->getDayBookings();
		$cls = get_class( $db );

		$starts = array();
		$ends   = array();
		foreach ( $items as $bs ) {
			if ( $bs->getStartsAt() ) {
				$starts[] = clone $bs->getStartsAt();
			}
			if ( $bs->getEndsAt() ) {
				$ends[] = clone $bs->getEndsAt();
			}
		}
		if ( empty( $starts ) || empty( $ends ) ) {
			return array( 'rows' => array(), 'day_bookings_class' => $cls );
		}
		$minStart = $starts[0];
		foreach ( $starts as $dt ) {
			if ( $dt < $minStart ) {
				$minStart = $dt;
			}
		}
		$maxEnd = $ends[0];
		foreach ( $ends as $dt ) {
			if ( $dt > $maxEnd ) {
				$maxEnd = $dt;
			}
		}

		$attendant_ids = array();
		foreach ( $items as $bs ) {
			$a = $bs->getAttendant();
			if ( is_object( $a ) && method_exists( $a, 'getId' ) ) {
				$attendant_ids[ $a->getId() ] = true;
			}
		}
		$attendant_ids = array_map( 'intval', array_keys( $attendant_ids ) );

		$rows   = array();
		$cursor = clone $minStart;
		$cursor->modify( '-30 minutes' );
		$endCur = clone $maxEnd;
		$endCur->modify( '+30 minutes' );

		while ( $cursor <= $endCur ) {
			$key = $cursor->format( 'H:i' );
			$h   = (int) $cursor->format( 'G' );
			$m   = (int) $cursor->format( 'i' );
			$tobj = $db->getTime( $h, $m );
			$isBreak = $db->isBreakSlot( $tobj );
			$counts  = $db->countAttendantsByHour( $h, $m );
			$pick    = array();
			foreach ( $attendant_ids as $aid ) {
				$pick[ 'attendant_' . $aid ] = isset( $counts[ $aid ] ) ? (int) $counts[ $aid ] : 0;
			}
			$rows[] = array(
				'time'        => $key,
				'is_break'    => $isBreak ? 'yes' : 'no',
				'bookings_h'  => $db->countBookingsByHour( $h, $m ),
				'attendants'  => $pick,
				'raw_counts'  => $counts,
			);
			$cursor->modify( '+5 minutes' );
		}

		return array(
			'rows'               => $rows,
			'day_bookings_class' => $cls,
		);
	}

	/**
	 * @param SLN_Helper_Availability $ah
	 * @param SLN_Wrapper_Booking     $booking
	 */
	private static function append_revalidation( array &$result, SLN_Helper_Availability $ah, SLN_Wrapper_Booking $booking ) {
		$result['validation_rebooking'] = array(
			'label'  => __( 'Re-validate primary booking (booking excluded from grid, same as edit-save)', 'salon-booking-system' ),
			'lines'  => array(),
		);
		foreach ( $booking->getBookingServices()->getItems() as $bs ) {
			if ( ! $bs->getStartsAt() ) {
				continue;
			}
			$ah->setDate( clone $bs->getStartsAt(), $booking );
			$errs = array();
			if ( $bs->getAttendant() && ! is_array( $bs->getAttendant() ) ) {
				$errs = $ah->validateBookingAttendant( $bs, $booking->getBookingServices()->isLast( $bs ) );
			} elseif ( $bs->getAttendant() && is_array( $bs->getAttendant() ) ) {
				$errs = $ah->validateBookingAttendants( $bs, $booking->getBookingServices()->isLast( $bs ) );
			}
			$result['validation_rebooking']['lines'][] = array(
				'service_id' => $bs->getService() ? $bs->getService()->getId() : 0,
				'errors'     => is_array( $errs ) ? $errs : array(),
			);
		}
	}

	/**
	 * @param SLN_Helper_Availability $ah
	 * @param SLN_Wrapper_Booking     $booking
	 */
	private static function append_validation_for_booking( array &$result, SLN_Helper_Availability $ah, SLN_Wrapper_Booking $booking, $result_key ) {
		$result[ $result_key ] = array( 'label' => __( 'Validate secondary booking (excluded from grid)', 'salon-booking-system' ), 'lines' => array() );
		foreach ( $booking->getBookingServices()->getItems() as $bs ) {
			if ( ! $bs->getStartsAt() ) {
				continue;
			}
			$ah->setDate( clone $bs->getStartsAt(), $booking );
			$errs = array();
			if ( $bs->getAttendant() && ! is_array( $bs->getAttendant() ) ) {
				$errs = $ah->validateBookingAttendant( $bs, $booking->getBookingServices()->isLast( $bs ) );
			} elseif ( $bs->getAttendant() && is_array( $bs->getAttendant() ) ) {
				$errs = $ah->validateBookingAttendants( $bs, $booking->getBookingServices()->isLast( $bs ) );
			}
			$result[ $result_key ]['lines'][] = array(
				'service_id' => $bs->getService() ? $bs->getService()->getId() : 0,
				'starts'     => $bs->getStartsAt()->format( 'Y-m-d H:i' ),
				'errors'     => is_array( $errs ) ? $errs : array(),
			);
		}
	}

	/**
	 * @param array               $result
	 * @param SLN_Wrapper_Booking $book_a
	 */
	private static function build_insights( array &$result, SLN_Wrapper_Booking $book_a ) {
		$nested = ( isset( $result['environment']['nested_bookings_enabled'] ) && 'yes' === $result['environment']['nested_bookings_enabled'] );
		foreach ( $result['booking_a_lines'] as $line ) {
			$hasBreak = ( $line['break_duration_his'] && '00:00' !== $line['break_duration_his'] );
			if ( ! $hasBreak ) {
				continue;
			}
			$bstart = $line['break_starts_at'];
			$bend   = $line['break_ends_at'];
			if ( ! $bstart || ! $bend || $bstart === $bend ) {
				$result['insights'][] = sprintf(
					/* translators: %s: service title */
					__( 'Line "%s" has break duration set but break start/end resolve equal or empty — check break_duration_data on the service.', 'salon-booking-system' ),
					$line['service_title']
				);
				continue;
			}
			$lineStart = $line['starts_at'];
			if ( $nested && $lineStart && $bstart ) {
				$tz = SLN_TimeFunc::getWpTimezone();
				try {
					$lineStartDt = new SLN_DateTime( $lineStart, $tz );
					$bstartDt    = new SLN_DateTime( $bstart, $tz );
				} catch ( Throwable $e ) {
					continue;
				}
				if ( $lineStartDt < $bstartDt ) {
					foreach ( $result['timeslot_slice'] as $row ) {
						try {
							$slot = new SLN_DateTime( $book_a->getDate()->format( 'Y-m-d' ) . ' ' . $row['time'] . ':00', $tz );
						} catch ( Throwable $e ) {
							continue;
						}
						if ( $slot >= $lineStartDt && $slot < $bstartDt && 'yes' === $row['is_break'] ) {
							$result['insights'][] = sprintf(
								/* translators: 1: HH:MM, 2: configured break start */
								__( 'Inconsistency: time %1$s is flagged is_break=yes but configured break starts at %2$s (pre-break work minutes must not be nested-break slots).', 'salon-booking-system' ),
								$row['time'],
								$bstartDt->format( 'H:i' )
							);
							break;
						}
						if ( $slot >= $lineStartDt && $slot < $bstartDt ) {
							$aid = isset( $line['attendant_id'] ) ? (int) $line['attendant_id'] : 0;
							if ( $aid && isset( $row['attendants'][ 'attendant_' . $aid ] ) && 0 === (int) $row['attendants'][ 'attendant_' . $aid ] && 'no' === $row['is_break'] ) {
								$result['insights'][] = sprintf(
									/* translators: 1: HH:MM, 2: attendant id */
									__( 'Possible issue: before the break window, time %1$s shows attendant %2$s count 0 while is_break=no (expect busy during active segment).', 'salon-booking-system' ),
									$row['time'],
									(string) $aid
								);
								break;
							}
						}
					}
				}
			}
		}
		if ( empty( $result['insights'] ) ) {
			$result['insights'][] = __( 'No automatic red flags in the sampled window. Copy the JSON export below for deeper review.', 'salon-booking-system' );
		}
	}
}
