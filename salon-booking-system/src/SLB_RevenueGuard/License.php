<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_License {

	/**
	 * Monetary KPIs, policy wizard, fee automation (v0.2+).
	 *
	 * @return bool
	 */
	public static function canViewMonetaryKpis() {
		if ( defined( 'SLN_VERSION_DEV' ) && SLN_VERSION_DEV ) {
			return true;
		}

		return (bool) apply_filters(
			'slb_revenue_guard_license_active',
			defined( 'SLN_VERSION_PAY' ) && SLN_VERSION_PAY
		);
	}

	/**
	 * Attendance resolution is available on all editions when the module is enabled.
	 *
	 * @return bool
	 */
	public static function isAttendanceEnabled() {
		return (bool) apply_filters( 'sln_revenue_guard_enabled', true );
	}
}
