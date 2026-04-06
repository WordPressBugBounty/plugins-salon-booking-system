<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_Ajax_RescheduleBookingCheckDate extends SLN_Action_Ajax_Abstract {
	/**
	 * Shared validation for My Account reschedule: same rules as AJAX check-date (assistants, salon hours, etc.).
	 *
	 * @param SLN_Plugin $plugin            Plugin instance.
	 * @param int        $bookingId         Booking post ID.
	 * @param string     $dateRaw           Raw date from request.
	 * @param string     $timeRaw           Raw time from request.
	 * @param array      $services          Map service_id => attendant_id (from form); empty = load from booking.
	 * @param string     $customerTimezone  Optional IANA timezone when customer-timezone slots are enabled.
	 * @return array{ errors: string[], handler: SLN_Action_Ajax_CheckDateAlt, date: string, time: string, timezone: string }
	 */
	public static function runRescheduleValidation( SLN_Plugin $plugin, $bookingId, $dateRaw, $timeRaw, array $formServices, $customerTimezone = '' ) {
		$timezone = $customerTimezone ? sanitize_text_field( wp_unslash( $customerTimezone ) ) : '';

		$booking = $plugin->createBooking( $bookingId );

		// Use Booking::getAttendantsIds() as the authoritative source of service→attendant mappings.
		// That method applies the `sln_booking_attendants` filter which add-ons such as SalonMultishop
		// use to reverse-map their internal shop-attendant IDs back to the base attendant post IDs.
		// Using the base IDs is critical: the base attendant post carries the configured availability
		// rules (e.g. "Wednesday & Thursday 9:30-17:00"), whereas the shop-attendant placeholder
		// stored in _sln_booking_services after evalBookingServices() may have no rules at all,
		// which would silently let any date/time through validation.
		$services = array();
		if ( $booking ) {
			$services = $booking->getAttendantsIds();
		}
		if ( empty( $services ) ) {
			// All services use auto-assign (attendant 0) or booking not found: fall back to
			// form-submitted values, then to the raw services_meta of the booking itself.
			$services = ! empty( $formServices )
				? $formServices
				: self::getServicesAttendantsFromBooking( $booking );
		}

		SLN_Plugin::addLog( sprintf(
			'[RESCHEDULE_VALIDATION] bookingId=%d dateRaw=%s timeRaw=%s bookingAttendants=%s formServices=%s',
			$bookingId,
			$dateRaw,
			$timeRaw,
			wp_json_encode( $services ),
			wp_json_encode( $formServices )
		) );

		$bb = $plugin->getBookingBuilder();
		$bb->clear();

		$date = SLN_Func::filter( sanitize_text_field( wp_unslash( $dateRaw ) ), 'date' );
		$time = SLN_Func::filter( sanitize_text_field( wp_unslash( $timeRaw ) ), 'time' );

		if ( $plugin->getSettings()->isDisplaySlotsCustomerTimezone() && $timezone ) {
			$dateTime = ( new SLN_DateTime( $date . ' ' . $time, SLN_Func::createDateTimeZone( $timezone ) ) )->setTimezone( SLN_DateTime::getWpTimezone() );
			$date     = $dateTime->format( 'Y-m-d' );
			$time     = $dateTime->format( 'H:i' );
		}

		$bb->setDate( $date );
		$bb->setTime( $time );
		$bb->setServicesAndAttendants( $services );
		$bb->save();

		$handler = new SLN_Action_Ajax_CheckDateAlt( $plugin );
		$handler->setDate( $date );
		$handler->setTime( $time );
		$handler->setBooking( $booking );

		$handler->checkDateTime();
		$errors = $handler->getErrors();

		SLN_Plugin::addLog( sprintf(
			'[RESCHEDULE_VALIDATION] checkDateTime result: errors=%s',
			wp_json_encode( is_array( $errors ) ? $errors : array() )
		) );

		// --- Filter-bypassing direct safety check ---
		// Even when the full checkDateTime() machinery passes (e.g. because the buildAttendant
		// filter returned a shop-attendant placeholder with no availability rules), we still
		// verify each attendant directly using new SLN_Wrapper_Attendant($id) — no plugin filter,
		// no createAttendant() chain — so that the attendant's own configured working hours are
		// always enforced for reschedule operations.
		if ( empty( $errors ) ) {
			$newDateTime = new SLN_DateTime( $date . ' ' . $time );
			foreach ( $services as $serviceId => $attendantId ) {
				$attendantId = is_array( $attendantId ) ? 0 : intval( $attendantId );
				if ( $attendantId <= 0 ) {
					continue;
				}
				$directAttendant = new SLN_Wrapper_Attendant( $attendantId );
				if ( $directAttendant->isEmpty() ) {
					continue;
				}
				SLN_Plugin::addLog( sprintf(
					'[RESCHEDULE_VALIDATION] Direct attendant check: #%d (%s) at %s',
					$attendantId,
					$directAttendant->getName(),
					$newDateTime->format( 'Y-m-d H:i' )
				) );
				if ( $directAttendant->isNotAvailableOnDate( $newDateTime ) ) {
					$errors = SLN_Helper_Availability_ErrorHelper::doAttendantNotAvailable( $directAttendant, $newDateTime );
					SLN_Plugin::addLog( '[RESCHEDULE_VALIDATION] Direct check: attendant unavailable — blocking reschedule.' );
					break;
				}
			}
		}

		// NOTE: The BookingBuilder is intentionally NOT cleared here.
		// The caller's getIntervalsArray() call needs the attendant context we just configured
		// so it returns dates/times that are actually valid for this attendant (not just the salon).
		// The caller is responsible for clearing the BB after getIntervalsArray().

		return array(
			'errors'    => is_array( $errors ) ? $errors : array(),
			'handler'   => $handler,
			'date'      => $date,
			'time'      => $time,
			'timezone'  => $timezone,
		);
	}

	/**
	 * @param SLN_Wrapper_Booking $booking Booking object.
	 * @return array<int, int|array>
	 */
	private static function getServicesAttendantsFromBooking( SLN_Wrapper_Booking $booking ) {
		$services = array();
		foreach ( $booking->getBookingServices()->getItems() as $bookingService ) {
			$serviceId = $bookingService->getService()->getId();
			if ( $bookingService->getAttendant() ) {
				if ( is_array( $bookingService->getAttendant() ) ) {
					$services[ $serviceId ] = SLN_Wrapper_Attendant::getArrayAttendantsValue( 'getId', $bookingService->getAttendant() );
				} else {
					$services[ $serviceId ] = $bookingService->getAttendant()->getId();
				}
			} else {
				$services[ $serviceId ] = 0;
			}
		}
		return $services;
	}

	public function execute() {
		$date = isset( $_POST['_sln_booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_sln_booking_date'] ) ) : '';
		$time = isset( $_POST['_sln_booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['_sln_booking_time'] ) ) : '';

		// Validate date is not empty
		if ( empty( $date ) ) {
			throw new Exception( 'Missing date in rescheduling request. Please select a date.' );
		}

		$timezone = isset( $_POST['customer_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_timezone'] ) ) : '';
		$services = isset( $_POST['_sln_booking']['services'] ) && is_array( $_POST['_sln_booking']['services'] ) ? $_POST['_sln_booking']['services'] : array();
		$bookingID = isset( $_POST['_sln_booking_id'] ) ? intval( $_POST['_sln_booking_id'] ) : 0;

		$r = self::runRescheduleValidation( $this->plugin, $bookingID, $date, $time, $services, $timezone );

		$errors  = $r['errors'];
		$handler = $r['handler'];

		if ( $errors ) {
			$ret = compact( 'errors' );
		} else {
			$ret = array( 'success' => 1 );
		}

		// BB still has attendant context from runRescheduleValidation — getIntervalsArray()
		// will use it to filter available dates/times to only those valid for this attendant.
		$ret['intervals']  = $handler->getIntervalsArray( $this->plugin->getSettings()->isDisplaySlotsCustomerTimezone() ? $timezone : '' );
		$ret['booking_id'] = $bookingID;

		// Now that intervals are built, release the BB so it does not leak into later logic.
		$this->plugin->getBookingBuilder()->clear();

		return $ret;
	}

	public function getIntervals( $date, $time, array $services = array() ) {
		$handler = new SLN_Action_Ajax_CheckDateAlt( $this->plugin );

		$handler->setDate( $date );
		$handler->setTime( $time );

		$bb = $this->plugin->getBookingBuilder();

		$bb->clear();

		$bb->setDate( $date );
		$bb->setTime( $time );

		$bb->setServicesAndAttendants( $services );

		$bb->save();

		return $handler->getIntervalsArray();
	}

}