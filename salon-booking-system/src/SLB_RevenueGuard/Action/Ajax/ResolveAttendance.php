<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Action_Ajax_ResolveAttendance extends SLN_Action_Ajax_Abstract {

	public function execute() {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation' ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid security token', 'salon-booking-system' ) ) );
		}

		if ( ! current_user_can( 'manage_salon' ) ) {
			wp_send_json_error( array( 'error' => __( 'Insufficient permissions', 'salon-booking-system' ) ) );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		$status     = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$fraction   = isset( $_POST['delivered_fraction'] ) ? (float) $_POST['delivered_fraction'] : null;

		$args = array();
		if ( null !== $fraction ) {
			$args['delivered_fraction'] = $fraction;
		}
		if ( ! empty( $_POST['waive_reason'] ) ) {
			$args['waive_reason'] = sanitize_text_field( wp_unslash( $_POST['waive_reason'] ) );
		}

		$service = new SLB_RevenueGuard_Service_AttendanceService( $this->plugin );
		$result  = $service->resolve( $booking_id, $status, get_current_user_id(), $args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'error' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}
}
