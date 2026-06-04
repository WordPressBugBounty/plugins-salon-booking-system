<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

/**
 * Handles forecast notification email deep-links.
 *
 * Populates the BookingBuilder from URL params and redirects straight to the
 * summary / checkout step on the booking page.
 */
class SLN_Action_ForecastNotifyDeepLink {

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function execute() {
		if ( empty( $_GET['sln_forecast_notify'] ) || empty( $_GET['sln_fdate'] ) || empty( $_GET['sln_ftime'] ) ) {
			return;
		}

		$booking_url = SLN_Action_ForecastNotify::getBookingPageUrl( $this->plugin );
		$return_url  = add_query_arg(
			array(
				'sln_forecast_notify' => 1,
				'sln_fdate'           => sanitize_text_field( wp_unslash( $_GET['sln_fdate'] ) ),
				'sln_ftime'           => sanitize_text_field( wp_unslash( $_GET['sln_ftime'] ) ),
				'sln_fservice'        => (int) ( $_GET['sln_fservice'] ?? 0 ),
				'sln_fattendant'      => (int) ( $_GET['sln_fattendant'] ?? 0 ),
			),
			$booking_url
		);

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $return_url ) );
			exit;
		}

		$forecast_step = new SLN_Shortcode_Salon_ForecastStep(
			$this->plugin,
			new SLN_Shortcode_Salon( $this->plugin, null ),
			SLN_Shortcode_Salon_ForecastStep::STEP_NAME
		);

		if ( ! $forecast_step->processEmailDeepLink() ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'sln_step_page' => SLN_Shortcode_Salon_ForecastStep::STEP_NAME ),
					$booking_url
				)
			);
			exit;
		}
	}
}
