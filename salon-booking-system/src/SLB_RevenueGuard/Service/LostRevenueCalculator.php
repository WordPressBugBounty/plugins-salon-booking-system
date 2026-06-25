<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Service_LostRevenueCalculator {

	const DEFAULT_COVERAGE_THRESHOLD = 80;

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin = null ) {
		$this->plugin = $plugin ? $plugin : SLN_Plugin::getInstance();
	}

	/**
	 * @param string $start_date
	 * @param string $end_date
	 * @param int    $shop_id
	 * @return array<string, mixed>
	 */
	public function getPeriodStats( $start_date, $end_date, $shop_id = 0 ) {
		$query   = new SLB_RevenueGuard_Service_AttendanceQuery( $this->plugin );
		$coverage = $query->getCoverageStats( $start_date, $end_date, $shop_id );

		$lost_revenue   = 0.0;
		$no_show_count  = 0;
		$partial_count  = 0;

		$booking_ids = $this->getResolvedBookingIdsInPeriod( $start_date, $end_date, $shop_id );

		foreach ( $booking_ids as $booking_id ) {
			$status = get_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, true );
			if ( ! $status && (int) get_post_meta( $booking_id, 'no_show', true ) === 1 ) {
				$status = SLB_RevenueGuard_Enum_AttendanceStatus::NO_SHOW;
			}

			if ( ! SLB_RevenueGuard_Enum_AttendanceStatus::isResolved( $status ) ) {
				continue;
			}

			try {
				$booking = $this->plugin->createBooking( $booking_id );
			} catch ( Exception $e ) {
				continue;
			}

			$amount = (float) apply_filters(
				'sln.revenue_guard.lost_revenue_booking_value',
				$booking->getAmount(),
				$booking
			);

			if ( $status === SLB_RevenueGuard_Enum_AttendanceStatus::NO_SHOW ) {
				$lost_revenue += $amount;
				++$no_show_count;
			} elseif ( $status === SLB_RevenueGuard_Enum_AttendanceStatus::PARTIAL_SHOW ) {
				$fraction = (float) get_post_meta( $booking_id, 'attendance_delivered_fraction', true );
				if ( $fraction <= 0 ) {
					$fraction = 0.5;
				}
				$lost_revenue += $amount * ( 1 - $fraction );
				++$partial_count;
			}
		}

		$threshold      = (int) apply_filters( 'sln_revenue_guard_coverage_threshold', self::DEFAULT_COVERAGE_THRESHOLD );
		$show_monetary  = SLB_RevenueGuard_License::canViewMonetaryKpis()
			&& $coverage['coverage_rate'] >= $threshold;

		$no_show_rate = $coverage['resolved'] > 0
			? round( ( $no_show_count / $coverage['resolved'] ) * 100, 1 )
			: 0.0;

		return array(
			'coverage'              => $coverage,
			'coverage_threshold'    => $threshold,
			'show_monetary_kpis'    => $show_monetary,
			'lost_revenue'          => $show_monetary ? round( $lost_revenue, 2 ) : null,
			'lost_revenue_hidden'   => $show_monetary ? null : round( $lost_revenue, 2 ),
			'no_show_count'         => $no_show_count,
			'partial_show_count'    => $partial_count,
			'no_show_rate'          => $no_show_rate,
			'unresolved_count'      => $query->countUnresolved( $shop_id ),
		);
	}

	/**
	 * @param string $start_date
	 * @param string $end_date
	 * @param int    $shop_id
	 * @return int[]
	 */
	private function getResolvedBookingIdsInPeriod( $start_date, $end_date, $shop_id ) {
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date',
				'value'   => array( $start_date, $end_date ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		);

		if ( $shop_id > 0 && class_exists( '\SalonMultishop\Addon' ) ) {
			$meta_query[] = array(
				'key'   => '_sln_booking_shop',
				'value' => (int) $shop_id,
			);
		}

		$query = new WP_Query(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
				'post_status'    => array(
					SLN_Enum_BookingStatus::CONFIRMED,
					SLN_Enum_BookingStatus::PAID,
					SLN_Enum_BookingStatus::PAY_LATER,
				),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => $meta_query,
			)
		);

		return is_array( $query->posts ) ? $query->posts : array();
	}
}
