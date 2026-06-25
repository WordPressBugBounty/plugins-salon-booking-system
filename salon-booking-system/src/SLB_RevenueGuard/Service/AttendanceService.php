<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Service_AttendanceService {

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin = null ) {
		$this->plugin = $plugin ? $plugin : SLN_Plugin::getInstance();
	}

	/**
	 * @param int    $booking_id
	 * @param string $status
	 * @param int    $user_id
	 * @param array  $args delivered_fraction, collected_amount, waive_reason
	 * @return array|WP_Error
	 */
	public function resolve( $booking_id, $status, $user_id, $args = array() ) {
		$booking_id = (int) $booking_id;
		$user_id    = (int) $user_id;
		$status     = sanitize_key( $status );

		if ( ! SLB_RevenueGuard_Enum_AttendanceStatus::isValid( $status ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid attendance status.', 'salon-booking-system' ) );
		}

		if ( $status === SLB_RevenueGuard_Enum_AttendanceStatus::PENDING ) {
			return new WP_Error( 'invalid_status', __( 'Cannot resolve to pending.', 'salon-booking-system' ) );
		}

		if ( ! $booking_id || ! get_post( $booking_id ) ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'salon-booking-system' ) );
		}

		if ( get_post_type( $booking_id ) !== SLN_Plugin::POST_TYPE_BOOKING ) {
			return new WP_Error( 'invalid_type', __( 'Invalid booking.', 'salon-booking-system' ) );
		}

		$booking = $this->plugin->createBooking( $booking_id );

		if ( ! $this->isEligibleForResolution( $booking ) && $status !== SLB_RevenueGuard_Enum_AttendanceStatus::EXCUSED ) {
			return new WP_Error( 'not_eligible', __( 'This booking is not eligible for attendance resolution.', 'salon-booking-system' ) );
		}

		$delivered_fraction = null;
		if ( $status === SLB_RevenueGuard_Enum_AttendanceStatus::PARTIAL_SHOW ) {
			$delivered_fraction = isset( $args['delivered_fraction'] ) ? (float) $args['delivered_fraction'] : 0.5;
			$delivered_fraction = max( 0.1, min( 0.9, $delivered_fraction ) );
		}

		$now = current_time( 'mysql' );

		update_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, $status );
		update_post_meta( $booking_id, 'attendance_resolved_at', $now );
		update_post_meta( $booking_id, 'attendance_resolved_by', $user_id );

		if ( $delivered_fraction !== null ) {
			update_post_meta( $booking_id, 'attendance_delivered_fraction', $delivered_fraction );
		} else {
			delete_post_meta( $booking_id, 'attendance_delivered_fraction' );
		}

		if ( ! empty( $args['collected_amount'] ) ) {
			update_post_meta( $booking_id, 'attendance_collected_amount', (float) $args['collected_amount'] );
		}

		if ( ! empty( $args['waive_reason'] ) && $status === SLB_RevenueGuard_Enum_AttendanceStatus::EXCUSED ) {
			update_post_meta( $booking_id, 'attendance_waive_reason', sanitize_text_field( $args['waive_reason'] ) );
		}

		$this->syncNoShowMeta( $booking_id, $status, $user_id, $now );

		do_action( 'sln.booking.attendance_resolved', $booking_id, $status, $user_id );

		return array(
			'booking_id'         => $booking_id,
			'attendance_status'  => $status,
			'attendance_label'   => SLB_RevenueGuard_Enum_AttendanceStatus::getLabel( $status ),
			'resolved_at'        => $now,
			'resolved_by'        => $user_id,
			'delivered_fraction' => $delivered_fraction,
		);
	}

	/**
	 * Legacy no-show toggle → attendance_status.
	 *
	 * @param int  $booking_id
	 * @param bool $mark_no_show
	 * @param int  $user_id
	 * @return array|WP_Error
	 */
	public function applyLegacyNoShowToggle( $booking_id, $mark_no_show, $user_id ) {
		$status = $mark_no_show
			? SLB_RevenueGuard_Enum_AttendanceStatus::NO_SHOW
			: SLB_RevenueGuard_Enum_AttendanceStatus::ATTENDED;

		return $this->resolve( $booking_id, $status, $user_id );
	}

	/**
	 * @param SLN_Wrapper_Booking $booking
	 * @return bool
	 */
	public function isEligibleForResolution( $booking ) {
		if ( ! $booking || ! $booking->getId() ) {
			return false;
		}

		if ( $booking->hasStatus( SLN_Enum_BookingStatus::CANCELED ) ) {
			return false;
		}

		$active = array(
			SLN_Enum_BookingStatus::CONFIRMED,
			SLN_Enum_BookingStatus::PAID,
			SLN_Enum_BookingStatus::PAY_LATER,
		);

		$is_active = false;
		foreach ( $active as $status_code ) {
			if ( $booking->hasStatus( $status_code ) ) {
				$is_active = true;
				break;
			}
		}
		if ( ! $is_active ) {
			return false;
		}

		SLN_TimeFunc::startRealTimezone();
		$ended = $booking->getEndsAt()->getTimestamp() <= SLN_TimeFunc::currentDateTime()->getTimestamp();
		SLN_TimeFunc::endRealTimezone();

		return $ended;
	}

	/**
	 * @param int    $booking_id
	 * @param string $status
	 * @param int    $user_id
	 * @param string $timestamp
	 */
	private function syncNoShowMeta( $booking_id, $status, $user_id, $timestamp ) {
		if ( $status === SLB_RevenueGuard_Enum_AttendanceStatus::NO_SHOW ) {
			update_post_meta( $booking_id, 'no_show', 1 );
			if ( ! get_post_meta( $booking_id, 'no_show_marked_at', true ) ) {
				update_post_meta( $booking_id, 'no_show_marked_at', $timestamp );
				update_post_meta( $booking_id, 'no_show_marked_by', $user_id );
			}
			do_action( 'sln.booking.marked_no_show', $booking_id, $user_id );
			return;
		}

		update_post_meta( $booking_id, 'no_show', 0 );
		update_post_meta( $booking_id, 'no_show_unmarked_at', $timestamp );
		do_action( 'sln.booking.unmarked_no_show', $booking_id, $user_id );
	}

	/**
	 * @param SLN_Wrapper_Booking $booking
	 * @param string|null         $activation_date Y-m-d
	 * @return bool
	 */
	public function isWithinResolutionWindow( $booking, $activation_date = null ) {
		if ( ! $booking || ! $booking->getId() ) {
			return false;
		}

		if ( null === $activation_date ) {
			$activation_date = SLB_RevenueGuard_Plugin::getActivationDate();
		}

		$booking_date = $booking->getDate();
		if ( ! $booking_date ) {
			return false;
		}

		return $booking_date->format( 'Y-m-d' ) >= $activation_date;
	}

	/**
	 * Whether staff must confirm this booking (queue / banner).
	 *
	 * @param SLN_Wrapper_Booking $booking
	 * @return bool
	 */
	public function requiresStaffConfirmation( $booking ) {
		if ( ! $this->isEligibleForResolution( $booking ) ) {
			return false;
		}

		if ( ! $this->isWithinResolutionWindow( $booking ) ) {
			return false;
		}

		$status = get_post_meta( $booking->getId(), SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, true );

		return $status === SLB_RevenueGuard_Enum_AttendanceStatus::PENDING || $status === '';
	}

	/**
	 * Migration / cron: set status without staff action.
	 *
	 * @param int    $booking_id
	 * @param string $activation_date Y-m-d
	 */
	public function normalizeAttendanceStatus( $booking_id, $activation_date ) {
		$booking_id = (int) $booking_id;
		$current    = get_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, true );

		if ( $current && SLB_RevenueGuard_Enum_AttendanceStatus::isResolved( $current ) && SLB_RevenueGuard_Enum_AttendanceStatus::PENDING !== $current ) {
			return;
		}

		if ( (int) get_post_meta( $booking_id, 'no_show', true ) === 1 ) {
			$this->backfillStatus( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::NO_SHOW );
			return;
		}

		try {
			$booking = $this->plugin->createBooking( $booking_id );
		} catch ( Exception $e ) {
			return;
		}

		$booking_date = $booking->getDate() ? $booking->getDate()->format( 'Y-m-d' ) : '';

		if ( $booking_date && $booking_date < $activation_date ) {
			$this->backfillStatus( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::ATTENDED );
			return;
		}

		if ( $this->isEligibleForResolution( $booking ) ) {
			update_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, SLB_RevenueGuard_Enum_AttendanceStatus::PENDING );
		}
	}

	/**
	 * @param int    $booking_id
	 * @param string $status
	 */
	private function backfillStatus( $booking_id, $status ) {
		$now = current_time( 'mysql' );
		update_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, $status );
		update_post_meta( $booking_id, 'attendance_resolved_at', $now );
		update_post_meta( $booking_id, 'attendance_resolved_by', 0 );
		$this->syncNoShowMeta( $booking_id, $status, 0, $now );
	}

	/**
	 * Mark past eligible bookings as pending (cron). Only from activation date forward.
	 *
	 * @param int $booking_id
	 */
	public function markPending( $booking_id ) {
		$current = get_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, true );
		if ( $current && SLB_RevenueGuard_Enum_AttendanceStatus::isResolved( $current ) ) {
			return;
		}

		if ( (int) get_post_meta( $booking_id, 'no_show', true ) === 1 ) {
			update_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, SLB_RevenueGuard_Enum_AttendanceStatus::NO_SHOW );
			return;
		}

		try {
			$booking = $this->plugin->createBooking( $booking_id );
		} catch ( Exception $e ) {
			return;
		}

		if ( ! $this->isWithinResolutionWindow( $booking ) ) {
			$this->normalizeAttendanceStatus( $booking_id, SLB_RevenueGuard_Plugin::getActivationDate() );
			return;
		}

		if ( ! $this->isEligibleForResolution( $booking ) ) {
			return;
		}

		update_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, SLB_RevenueGuard_Enum_AttendanceStatus::PENDING );
	}
}
