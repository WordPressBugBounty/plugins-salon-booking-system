<?php
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Action_Cron_MarkPendingAttendance {

	public static function execute() {
		self::processEligible( 0, 100, true );
	}

	/**
	 * Mark a single booking pending when eligible (admin save / immediate).
	 *
	 * @param int  $booking_id
	 * @param bool $respect_buffer
	 */
	public static function processBooking( $booking_id, $respect_buffer = false ) {
		if ( ! SLB_RevenueGuard_License::isAttendanceEnabled() ) {
			return;
		}

		$booking_id = (int) $booking_id;
		if ( $booking_id < 1 ) {
			return;
		}

		$plugin = SLN_Plugin::getInstance();
		$service = new SLB_RevenueGuard_Service_AttendanceService( $plugin );

		try {
			$booking = $plugin->createBooking( $booking_id );
		} catch ( Exception $e ) {
			return;
		}

		if ( ! $service->isEligibleForResolution( $booking ) ) {
			return;
		}

		if ( $respect_buffer && ! self::hasPassedBuffer( $booking ) ) {
			return;
		}

		$service->markPending( $booking_id );
	}

	/**
	 * Batch: find eligible bookings without attendance and mark pending.
	 *
	 * @param int  $booking_id      Process one booking only when > 0.
	 * @param int  $limit
	 * @param bool $respect_buffer  Cron uses 15-min buffer; admin UI does not.
	 */
	public static function processEligible( $booking_id = 0, $limit = 100, $respect_buffer = true ) {
		if ( ! SLB_RevenueGuard_License::isAttendanceEnabled() ) {
			return;
		}

		if ( $booking_id > 0 ) {
			self::processBooking( $booking_id, $respect_buffer );
			return;
		}

		$plugin  = SLN_Plugin::getInstance();
		$service = new SLB_RevenueGuard_Service_AttendanceService( $plugin );

		$query = new WP_Query(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
				'post_status'    => array(
					SLN_Enum_BookingStatus::CONFIRMED,
					SLN_Enum_BookingStatus::PAID,
					SLN_Enum_BookingStatus::PAY_LATER,
				),
				'posts_per_page' => (int) $limit,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date',
						'value'   => SLN_TimeFunc::date( 'Y-m-d' ),
						'compare' => '<=',
						'type'    => 'DATE',
					),
					array(
						'key'     => '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date',
						'value'   => SLB_RevenueGuard_Plugin::getActivationDate(),
						'compare' => '>=',
						'type'    => 'DATE',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'   => SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY,
							'value' => '',
						),
					),
				),
			)
		);

		if ( ! is_array( $query->posts ) ) {
			return;
		}

		foreach ( $query->posts as $id ) {
			try {
				$booking = $plugin->createBooking( $id );
			} catch ( Exception $e ) {
				continue;
			}

			if ( ! $service->isEligibleForResolution( $booking ) ) {
				continue;
			}

			if ( $respect_buffer && ! self::hasPassedBuffer( $booking ) ) {
				continue;
			}

			$service->markPending( (int) $id );
		}
	}

	/**
	 * @param SLN_Wrapper_Booking $booking
	 * @return bool
	 */
	private static function hasPassedBuffer( $booking ) {
		$buffer = SLB_RevenueGuard_Plugin::getBufferMinutes();

		SLN_TimeFunc::startRealTimezone();
		$cutoff  = SLN_TimeFunc::currentDateTime()->modify( '-' . $buffer . ' minutes' );
		$passed  = $booking->getEndsAt()->getTimestamp() <= $cutoff->getTimestamp();
		SLN_TimeFunc::endRealTimezone();

		return $passed;
	}
}
