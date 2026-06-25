<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Enum_AttendanceStatus {

	const META_KEY = 'attendance_status';

	const PENDING       = 'pending';
	const ATTENDED      = 'attended';
	const PARTIAL_SHOW  = 'partial_show';
	const NO_SHOW       = 'no_show';
	const LATE_CANCEL   = 'late_cancel';
	const EXCUSED       = 'excused';

	/**
	 * @return string[]
	 */
	public static function resolvedStatuses() {
		return array(
			self::ATTENDED,
			self::PARTIAL_SHOW,
			self::NO_SHOW,
			self::LATE_CANCEL,
			self::EXCUSED,
		);
	}

	/**
	 * @return string[]
	 */
	public static function all() {
		return array_merge( array( self::PENDING ), self::resolvedStatuses() );
	}

	/**
	 * @param string $status
	 * @return bool
	 */
	public static function isValid( $status ) {
		return in_array( $status, self::all(), true );
	}

	/**
	 * @param string $status
	 * @return bool
	 */
	public static function isResolved( $status ) {
		return in_array( $status, self::resolvedStatuses(), true );
	}

	/**
	 * @param string $status
	 * @return string
	 */
	public static function getLabel( $status ) {
		$labels = array(
			self::PENDING      => __( 'Pending confirmation', 'salon-booking-system' ),
			self::ATTENDED     => __( 'Attended', 'salon-booking-system' ),
			self::PARTIAL_SHOW => __( 'Partial show', 'salon-booking-system' ),
			self::NO_SHOW      => __( 'No-show', 'salon-booking-system' ),
			self::LATE_CANCEL  => __( 'Late cancellation', 'salon-booking-system' ),
			self::EXCUSED      => __( 'Excused', 'salon-booking-system' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
