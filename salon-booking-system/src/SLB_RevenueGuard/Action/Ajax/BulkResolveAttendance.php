<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Action_Ajax_BulkResolveAttendance extends SLN_Action_Ajax_Abstract {

	public function execute() {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation' ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid security token', 'salon-booking-system' ) ) );
		}

		if ( ! current_user_can( 'manage_salon' ) ) {
			wp_send_json_error( array( 'error' => __( 'Insufficient permissions', 'salon-booking-system' ) ) );
		}

		$booking_ids = isset( $_POST['booking_ids'] ) ? array_map( 'intval', (array) $_POST['booking_ids'] ) : array();
		$status      = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : SLB_RevenueGuard_Enum_AttendanceStatus::ATTENDED;

		if ( empty( $booking_ids ) ) {
			wp_send_json_error( array( 'error' => __( 'No bookings selected.', 'salon-booking-system' ) ) );
		}

		$service = new SLB_RevenueGuard_Service_AttendanceService( $this->plugin );
		$user_id = get_current_user_id();
		$results = array();
		$errors  = array();

		foreach ( $booking_ids as $booking_id ) {
			$result = $service->resolve( $booking_id, $status, $user_id );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'booking_id' => $booking_id,
					'message'    => $result->get_error_message(),
				);
			} else {
				$results[] = $result;
			}
		}

		wp_send_json_success(
			array(
				'resolved' => $results,
				'errors'   => $errors,
				'count'    => count( $results ),
			)
		);
	}
}
