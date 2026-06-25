<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Admin_ReportsIntegration {

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'sln.admin.reports.after_kpis', array( $this, 'renderRevenueAtRiskSection' ) );
		add_action( 'sln.admin.reports.footer', array( $this, 'renderQueueModal' ) );
	}

	public function renderQueueModal() {
		if ( ! current_user_can( 'manage_salon' ) ) {
			return;
		}
		echo $this->plugin->loadView( 'revenue_guard/admin/queue-modal', array() );
	}

	public function renderRevenueAtRiskSection() {
		$can_view_money = SLB_RevenueGuard_License::canViewMonetaryKpis();

		echo $this->plugin->loadView(
			'revenue_guard/admin/reports-revenue-at-risk',
			array(
				'can_view_money' => $can_view_money,
			)
		);
	}
}
