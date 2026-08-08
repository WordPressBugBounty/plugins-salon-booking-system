<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API\Controller;

use WP_REST_Server;
use WP_REST_Response;
use WP_Error;
use SLB_RevenueGuard_Service_AttendanceQuery as AttendanceQuery;
use SLB_RevenueGuard_Service_AttendanceService as AttendanceService;
use SLB_RevenueGuard_Service_LostRevenueCalculator as LostRevenueCalculator;
use SLB_RevenueGuard_License;

class RevenueGuard_Controller extends REST_Controller {

	protected $rest_base = 'revenue-guard';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'start_date' => array(
							'required' => true,
							'type'     => 'string',
						),
						'end_date'   => array(
							'required' => true,
							'type'     => 'string',
						),
						'shop'       => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/unresolved',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_unresolved' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'shop'  => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'limit' => array(
							'type'    => 'integer',
							'default' => 50,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/resolve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resolve' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'booking_id'         => array(
							'required' => true,
							'type'     => 'integer',
						),
						'status'             => array(
							'required' => true,
							'type'     => 'string',
						),
						'delivered_fraction' => array(
							'type' => 'number',
						),
					),
				),
			)
		);
	}

	public function check_permissions( $request ) {
		return current_user_can( 'manage_salon' ) || current_user_can( 'manage_options' ) || $this->is_shop_manager();
	}

	public function get_stats( $request ) {
		if ( ! SLB_RevenueGuard_License::isAttendanceEnabled() ) {
			return new WP_Error( 'disabled', __( 'Revenue Guard is disabled.', 'salon-booking-system' ), array( 'status' => 403 ) );
		}

		$start_date = sanitize_text_field( $request->get_param( 'start_date' ) );
		$end_date   = sanitize_text_field( $request->get_param( 'end_date' ) );
		$shop       = (int) $request->get_param( 'shop' );

		$calculator = new LostRevenueCalculator();
		$stats      = $calculator->getPeriodStats( $start_date, $end_date, $shop );

		return new WP_REST_Response( $stats, 200 );
	}

	public function get_unresolved( $request ) {
		if ( ! SLB_RevenueGuard_License::isAttendanceEnabled() ) {
			return new WP_Error( 'disabled', __( 'Revenue Guard is disabled.', 'salon-booking-system' ), array( 'status' => 403 ) );
		}

		$shop  = (int) $request->get_param( 'shop' );
		$limit = (int) $request->get_param( 'limit' );
		$query = new AttendanceQuery();

		return new WP_REST_Response(
			array(
				'count'    => $query->countUnresolved( $shop ),
				'bookings' => $query->getUnresolvedBookings( $limit, $shop ),
			),
			200
		);
	}

	public function resolve( $request ) {
		if ( ! SLB_RevenueGuard_License::isAttendanceEnabled() ) {
			return new WP_Error( 'disabled', __( 'Revenue Guard is disabled.', 'salon-booking-system' ), array( 'status' => 403 ) );
		}

		$booking_id = (int) $request->get_param( 'booking_id' );
		$status     = sanitize_key( $request->get_param( 'status' ) );
		$args       = array();

		if ( $request->get_param( 'delivered_fraction' ) !== null ) {
			$args['delivered_fraction'] = (float) $request->get_param( 'delivered_fraction' );
		}

		$service = new AttendanceService();
		$result  = $service->resolve( $booking_id, $status, get_current_user_id(), $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}
}
