<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Service_AttendanceQuery {

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin = null ) {
		$this->plugin = $plugin ? $plugin : SLN_Plugin::getInstance();
	}

	/**
	 * Bookings awaiting staff confirmation.
	 *
	 * @param int $limit
	 * @param int $shop_id
	 * @return array<int, array<string, mixed>>
	 */
	public function getUnresolvedBookings( $limit = 50, $shop_id = 0 ) {
		$ids = $this->queryBookingIds(
			array(
				'attendance_status' => SLB_RevenueGuard_Enum_AttendanceStatus::PENDING,
				'limit'             => $limit,
				'shop_id'           => $shop_id,
				'past_only'         => true,
			)
		);

		$items = array();
		foreach ( $ids as $id ) {
			$row = $this->formatBookingRow( $id );
			if ( $row ) {
				$items[] = $row;
			}
		}

		return $items;
	}

	/**
	 * @param int $shop_id
	 * @return int
	 */
	public function countUnresolved( $shop_id = 0 ) {
		return count(
			$this->queryBookingIds(
				array(
					'attendance_status' => SLB_RevenueGuard_Enum_AttendanceStatus::PENDING,
					'limit'             => -1,
					'shop_id'           => $shop_id,
					'past_only'         => true,
					'fields'            => 'ids',
				)
			)
		);
	}

	/**
	 * @param string $start_date Y-m-d
	 * @param string $end_date   Y-m-d
	 * @param int    $shop_id
	 * @return array{coverage_rate: float, resolved: int, eligible: int, pending: int}
	 */
	public function getCoverageStats( $start_date, $end_date, $shop_id = 0 ) {
		$eligible = $this->queryBookingIds(
			array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'shop_id'    => $shop_id,
				'past_only'  => true,
				'limit'      => -1,
			)
		);

		$resolved = 0;
		$pending  = 0;

		foreach ( $eligible as $booking_id ) {
			$status = get_post_meta( $booking_id, SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY, true );
			if ( ! $status && (int) get_post_meta( $booking_id, 'no_show', true ) === 1 ) {
				$status = SLB_RevenueGuard_Enum_AttendanceStatus::NO_SHOW;
			}
			if ( SLB_RevenueGuard_Enum_AttendanceStatus::isResolved( $status ) ) {
				++$resolved;
			} elseif ( $status === SLB_RevenueGuard_Enum_AttendanceStatus::PENDING || $status === '' ) {
				++$pending;
			}
		}

		$total   = count( $eligible );
		$rate    = $total > 0 ? round( ( $resolved / $total ) * 100, 1 ) : 0.0;

		return array(
			'coverage_rate' => $rate,
			'resolved'      => $resolved,
			'eligible'      => $total,
			'pending'       => $pending,
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return int[]
	 */
	private function queryBookingIds( $args ) {
		$defaults = array(
			'attendance_status' => '',
			'start_date'        => '',
			'end_date'          => '',
			'shop_id'           => 0,
			'past_only'         => false,
			'limit'             => 50,
		);
		$args = wp_parse_args( $args, $defaults );

		$meta_query = array();

		if ( $args['attendance_status'] ) {
			$meta_query[] = array(
				'key'   => SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY,
				'value' => $args['attendance_status'],
			);
		}

		if ( $args['start_date'] && $args['end_date'] ) {
			$meta_query[] = array(
				'key'     => '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date',
				'value'   => array( $args['start_date'], $args['end_date'] ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			);
		} elseif ( $args['past_only'] ) {
			$meta_query[] = array(
				'key'     => '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date',
				'value'   => SLN_TimeFunc::date( 'Y-m-d' ),
				'compare' => '<=',
				'type'    => 'DATE',
			);
			$meta_query[] = array(
				'key'     => '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date',
				'value'   => SLB_RevenueGuard_Plugin::getActivationDate(),
				'compare' => '>=',
				'type'    => 'DATE',
			);
		}

		if ( $args['shop_id'] > 0 && class_exists( '\SalonMultishop\Addon' ) ) {
			$meta_query[] = array(
				'key'   => '_sln_booking_shop',
				'value' => (int) $args['shop_id'],
			);
		}

		$query_args = array(
			'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
			'post_status'    => array(
				SLN_Enum_BookingStatus::CONFIRMED,
				SLN_Enum_BookingStatus::PAID,
				SLN_Enum_BookingStatus::PAY_LATER,
			),
			'posts_per_page' => (int) $args['limit'],
			'orderby'        => 'meta_value',
			'meta_key'       => '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_query'     => $meta_query,
		);

		if ( (int) $args['limit'] === -1 ) {
			$query_args['posts_per_page'] = -1;
			$query_args['nopaging']       = true;
		}

		$query = new WP_Query( $query_args );
		$ids   = $query->posts;

		if ( ! $args['past_only'] || empty( $ids ) ) {
			return is_array( $ids ) ? $ids : array();
		}

		$service = new SLB_RevenueGuard_Service_AttendanceService( $this->plugin );
		$filtered = array();

		foreach ( $ids as $booking_id ) {
			try {
				$booking = $this->plugin->createBooking( $booking_id );
				if ( $args['attendance_status'] === SLB_RevenueGuard_Enum_AttendanceStatus::PENDING ) {
					if ( $service->requiresStaffConfirmation( $booking ) ) {
						$filtered[] = (int) $booking_id;
					}
					continue;
				}
				if ( $service->isEligibleForResolution( $booking ) ) {
					$filtered[] = (int) $booking_id;
				}
			} catch ( Exception $e ) {
				continue;
			}
		}

		return $filtered;
	}

	/**
	 * @param int $booking_id
	 * @return array<string, mixed>|null
	 */
	private function formatBookingRow( $booking_id ) {
		try {
			$booking = $this->plugin->createBooking( $booking_id );
		} catch ( Exception $e ) {
			return null;
		}

		$format = $this->plugin->format();
		$services = array();
		foreach ( $booking->getBookingServices()->getItems() as $item ) {
			$services[] = $item->getService()->getName();
		}

		return array(
			'id'            => $booking_id,
			'customer_name' => $booking->getDisplayName(),
			'date'          => $format->date( $booking->getDate() ),
			'time'          => $format->time( $booking->getTime() ),
			'amount'        => $this->plugin->format()->money( $booking->getAmount(), false, false, true, false, true ),
			'amount_raw'    => (float) $booking->getAmount(),
			'services'      => $services,
			'edit_url'      => get_edit_post_link( $booking_id, 'raw' ),
		);
	}
}
