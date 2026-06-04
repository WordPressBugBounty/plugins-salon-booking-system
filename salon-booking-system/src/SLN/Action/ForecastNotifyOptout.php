<?php

/**
 * Handles one-click unsubscribe from forecast slot notification emails.
 */
class SLN_Action_ForecastNotifyOptout {

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function execute() {
		if ( empty( $_GET['sln_forecast_notify_optout'] ) ) {
			return;
		}

		$user_id = isset( $_GET['sln_forecast_notify_uid'] )
			? (int) $_GET['sln_forecast_notify_uid']
			: 0;
		$token   = sanitize_text_field( wp_unslash( $_GET['sln_forecast_notify_optout'] ) );

		if ( ! SLN_Action_ForecastNotify::verifyOptoutToken( $user_id, $token ) ) {
			wp_die(
				'<p>' . esc_html__( 'Invalid unsubscribe link.', 'salon-booking-system' ) . '</p>',
				esc_html__( 'Unsubscribe', 'salon-booking-system' ),
				array( 'response' => 403 )
			);
		}

		$customer = new SLN_Wrapper_Customer( $user_id );
		if ( $customer->isEmpty() ) {
			wp_die(
				'<p>' . esc_html__( 'Customer not found.', 'salon-booking-system' ) . '</p>',
				esc_html__( 'Unsubscribe', 'salon-booking-system' ),
				array( 'response' => 404 )
			);
		}

		$customer->setMeta( SLN_Action_ForecastNotify::META_OPTIN, 0 );

		wp_die(
			'<p>' . esc_html__( 'You have been unsubscribed from forecast slot notification emails.', 'salon-booking-system' ) . '</p>',
			esc_html__( 'Unsubscribed', 'salon-booking-system' ),
			array( 'response' => 200 )
		);
	}
}
