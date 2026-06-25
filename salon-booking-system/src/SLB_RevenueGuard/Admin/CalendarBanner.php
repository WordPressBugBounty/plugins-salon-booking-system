<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Admin_CalendarBanner {

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct( SLN_Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'sln.admin.calendar.notices', array( $this, 'renderBanner' ) );
		add_action( 'sln.admin.calendar.footer', array( $this, 'renderModal' ) );
	}

	public function renderBanner() {
		if ( ! current_user_can( 'manage_salon' ) ) {
			return;
		}

		// Mark eligible past bookings immediately (no cron wait).
		SLB_RevenueGuard_Action_Cron_MarkPendingAttendance::processEligible( 0, 50, false );

		$shop_id = $this->getCurrentShopId();
		$query   = new SLB_RevenueGuard_Service_AttendanceQuery( $this->plugin );
		$count   = $query->countUnresolved( $shop_id );

		if ( $count < 1 ) {
			return;
		}

		echo $this->plugin->loadView(
			'revenue_guard/admin/widget-unresolved',
			array(
				'count' => $count,
			)
		);
	}

	public function renderModal() {
		if ( ! current_user_can( 'manage_salon' ) ) {
			return;
		}

		echo $this->plugin->loadView( 'revenue_guard/admin/queue-modal', array() );
	}

	/**
	 * @return int
	 */
	private function getCurrentShopId() {
		if ( ! class_exists( '\SalonMultishop\Addon' ) ) {
			return 0;
		}

		try {
			$addon = \SalonMultishop\Addon::getInstance();
			if ( $addon && method_exists( $addon, 'getCurrentShop' ) ) {
				$shop = $addon->getCurrentShop();
				if ( $shop && method_exists( $shop, 'getId' ) ) {
					return (int) $shop->getId();
				}
			}
		} catch ( Exception $e ) {
			return 0;
		}

		return 0;
	}
}
