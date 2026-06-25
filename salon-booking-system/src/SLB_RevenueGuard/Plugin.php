<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLB_RevenueGuard_Plugin {

	const CRON_HOOK              = 'sln_revenue_guard_mark_pending';
	const MIGRATION_OPTION       = 'sln_revenue_guard_migrated_v1';
	const MIGRATION_V2_OPTION    = 'sln_revenue_guard_migrated_v2';
	const ACTIVATION_DATE_OPTION = 'sln_revenue_guard_activated_at';
	const DEFAULT_BUFFER_MINS    = 15;

	/** @var SLB_RevenueGuard_Plugin */
	private static $instance;

	/** @var SLN_Plugin|null */
	private $plugin;

	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'hook_plugins_loaded' ) );
		add_action( 'init', array( $this, 'hook_init' ) );
	}

	public function hook_plugins_loaded() {
		if ( ! SLB_RevenueGuard_License::isAttendanceEnabled() ) {
			return;
		}

		$this->plugin = SLN_Plugin::getInstance();
		$this->plugin->templating()->addPath( SLN_PLUGIN_DIR . '/views/revenue_guard/%s.php', 12 );
	}

	public function hook_init() {
		if ( ! SLB_RevenueGuard_License::isAttendanceEnabled() ) {
			return;
		}

		if ( is_null( $this->plugin ) ) {
			$this->hook_plugins_loaded();
		}

		if ( ! $this->plugin ) {
			return;
		}

		$this->maybeRunMigration();
		$this->registerCron();

		if ( is_admin() ) {
			new SLB_RevenueGuard_Admin_CalendarBanner( $this->plugin );
			new SLB_RevenueGuard_Admin_ReportsIntegration( $this->plugin );
			$this->registerAjax();
			$this->enqueueAdminAssets();
			add_action( 'sln.booking_builder.create.booking_created', array( $this, 'onBookingSaved' ), 20, 1 );
		}
	}

	private function registerCron() {
		add_action( self::CRON_HOOK, array( 'SLB_RevenueGuard_Action_Cron_MarkPendingAttendance', 'execute' ) );
		add_action( 'sln_revenue_guard_migration_v2_batch', array( $this, 'runMigrationV2Batch' ) );

		if ( ! wp_get_schedule( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	private function registerAjax() {
		add_action( 'wp_ajax_sln_revenue_guard_resolve', array( new SLB_RevenueGuard_Action_Ajax_ResolveAttendance( $this->plugin ), 'execute' ) );
		add_action( 'wp_ajax_sln_revenue_guard_bulk_resolve', array( new SLB_RevenueGuard_Action_Ajax_BulkResolveAttendance( $this->plugin ), 'execute' ) );
		add_action( 'wp_ajax_sln_revenue_guard_unresolved', array( $this, 'ajaxGetUnresolved' ) );
	}

	public function ajaxGetUnresolved() {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation' ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid security token', 'salon-booking-system' ) ) );
		}

		if ( ! current_user_can( 'manage_salon' ) ) {
			wp_send_json_error( array( 'error' => __( 'Insufficient permissions', 'salon-booking-system' ) ) );
		}

		SLB_RevenueGuard_Action_Cron_MarkPendingAttendance::processEligible( 0, 50, false );

		$shop_id = isset( $_POST['shop_id'] ) ? (int) $_POST['shop_id'] : 0;
		$limit   = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 50;

		$query = new SLB_RevenueGuard_Service_AttendanceQuery( $this->plugin );

		wp_send_json_success(
			array(
				'count'    => $query->countUnresolved( $shop_id ),
				'bookings' => $query->getUnresolvedBookings( $limit, $shop_id ),
			)
		);
	}

	/**
	 * After admin saves a booking, queue attendance if the appointment already ended.
	 *
	 * @param SLN_Wrapper_Booking $booking
	 */
	public function onBookingSaved( $booking ) {
		if ( ! $booking || ! $booking->getId() ) {
			return;
		}

		SLB_RevenueGuard_Action_Cron_MarkPendingAttendance::processBooking( $booking->getId(), false );
	}

	/**
	 * Register script enqueue on admin pages.
	 */
	private function enqueueAdminAssets() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueueQueueScripts' ) );
	}

	/**
	 * Enqueue attendance queue JS (calendar, reports, booking metabox).
	 *
	 * @param string $hook_suffix
	 */
	public static function enqueueQueueScripts( $hook_suffix = '' ) {
		if ( ! SLB_RevenueGuard_License::isAttendanceEnabled() ) {
			return;
		}

		if ( wp_script_is( 'sln-revenue-guard-queue', 'enqueued' ) ) {
			return;
		}

		$load = false;

		// Top-level Salon menu uses toplevel_page_salon; Calendar submenu uses salon_page_salon.
		$admin_hooks = array(
			'toplevel_page_salon',
			'salon_page_salon',
			'toplevel_page_salon-reports',
			'salon_page_salon-reports',
		);

		if ( $hook_suffix && in_array( $hook_suffix, $admin_hooks, true ) ) {
			$load = true;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'salon', 'salon-reports' ), true ) ) {
			$load = true;
		}

		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && SLN_Plugin::POST_TYPE_BOOKING === $screen->post_type ) {
				$load = true;
			}
		}

		if ( ! $load ) {
			return;
		}

		wp_enqueue_script(
			'sln-revenue-guard-queue',
			SLN_PLUGIN_URL . '/js/admin/revenue-guard-queue.js',
			array( 'jquery' ),
			SLN_VERSION,
			true
		);

		wp_localize_script(
			'sln-revenue-guard-queue',
			'slnRevenueGuard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ajax_post_validation' ),
				'i18n'    => array(
					'confirmBulk' => __( 'Mark all listed appointments as attended?', 'salon-booking-system' ),
					'error'       => __( 'Could not save attendance. Please try again.', 'salon-booking-system' ),
					'partial'     => __( 'Partial show', 'salon-booking-system' ),
					'attended'    => __( 'Attended', 'salon-booking-system' ),
					'noShow'      => __( 'No-show', 'salon-booking-system' ),
					'excused'     => __( 'Excused', 'salon-booking-system' ),
				),
			)
		);
	}

	/**
	 * First day attendance confirmation applies (Y-m-d). Bookings before this date are backfilled, not queued.
	 *
	 * @return string
	 */
	public static function getActivationDate() {
		$stored = get_option( self::ACTIVATION_DATE_OPTION, '' );
		if ( $stored && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $stored ) ) {
			return (string) apply_filters( 'sln_revenue_guard_activation_date', $stored );
		}

		$date = SLN_TimeFunc::date( 'Y-m-d' );
		update_option( self::ACTIVATION_DATE_OPTION, $date, false );

		return (string) apply_filters( 'sln_revenue_guard_activation_date', $date );
	}

	/**
	 * @return int
	 */
	public static function getBufferMinutes() {
		return (int) apply_filters( 'sln_revenue_guard_buffer_minutes', self::DEFAULT_BUFFER_MINS );
	}

	/**
	 * One-time migration: legacy no_show + historical backfill; queue only from activation date.
	 */
	private function maybeRunMigration() {
		self::getActivationDate();

		if ( ! get_option( self::MIGRATION_OPTION ) ) {
			update_option( self::MIGRATION_OPTION, SLN_VERSION, false );
		}

		if ( get_option( self::MIGRATION_V2_OPTION ) ) {
			return;
		}

		$this->runMigrationV2Batch();

		if ( ! get_option( self::MIGRATION_V2_OPTION ) && ! wp_next_scheduled( 'sln_revenue_guard_migration_v2_batch' ) ) {
			wp_schedule_single_event( time() + 2, 'sln_revenue_guard_migration_v2_batch' );
		}
	}

	/**
	 * Backfill historical bookings; clear bogus pending queue. Runs in batches.
	 */
	public function runMigrationV2Batch() {
		if ( get_option( self::MIGRATION_V2_OPTION ) || ! $this->plugin ) {
			return;
		}

		$service    = new SLB_RevenueGuard_Service_AttendanceService( $this->plugin );
		$activation = self::getActivationDate();
		$batch_size = (int) apply_filters( 'sln_revenue_guard_migration_batch_size', 500 );

		$query = new WP_Query(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
				'post_status'    => array(
					SLN_Enum_BookingStatus::CONFIRMED,
					SLN_Enum_BookingStatus::PAID,
					SLN_Enum_BookingStatus::PAY_LATER,
				),
				'posts_per_page' => $batch_size,
				'offset'         => 0,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY,
						'value' => '',
					),
					array(
						'key'   => SLB_RevenueGuard_Enum_AttendanceStatus::META_KEY,
						'value' => SLB_RevenueGuard_Enum_AttendanceStatus::PENDING,
					),
				),
			)
		);

		$ids = is_array( $query->posts ) ? $query->posts : array();

		foreach ( $ids as $booking_id ) {
			$service->normalizeAttendanceStatus( (int) $booking_id, $activation );
		}

		if ( count( $ids ) < $batch_size ) {
			update_option( self::MIGRATION_V2_OPTION, SLN_VERSION, false );
			delete_option( 'sln_revenue_guard_migration_v2_offset' );
			return;
		}

		if ( ! wp_next_scheduled( 'sln_revenue_guard_migration_v2_batch' ) ) {
			wp_schedule_single_event( time() + 2, 'sln_revenue_guard_migration_v2_batch' );
		}
	}
}
