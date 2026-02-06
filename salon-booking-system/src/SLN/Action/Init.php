<?php
// phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

class SLN_Action_Init
{
    private $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        add_action('init',function(){
            $this->initEnum();
            if (is_admin()) {
                $this->initAdmin();
            } else {
                $this->initFrontend();
            }
            // Initialize AJAX handlers (must run for both admin and frontend for wp_ajax_nopriv_)
            $this->initAjax();
        });
        $this->init();
    }

    function initEnum(){
        SLN_Enum_BookingStatus::init();
        SLN_Enum_CheckoutFields::init();
        SLN_Enum_DateFormat::init();
        SLN_Enum_DaysOfWeek::init();
        SLN_Enum_PaymentDepositType::init();
        if(class_exists('SLN_Enum_PaymentMethodProvider')){
            SLN_Enum_PaymentMethodProvider::addService('paypal', 'PayPal', 'SLN_PaymentMethod_Paypal');
            if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
                SLN_Enum_PaymentMethodProvider::addService('stripe', 'Stripe', 'SLN_PaymentMethod_Stripe');
            }
        }
        SLN_Enum_SmsProvider::init();
        SLN_Enum_TimeFormat::init();
    }

    private function init()
    {
        $p = $this->plugin;
        if(!defined("SLN_VERSION_CODECANYON") && defined("SLN_VERSION_PAY") && SLN_VERSION_PAY ) { $this->initLicense(); }
        
        // CRITICAL DEBUG: Log ALL booking status transitions to catch status reversion
        // Only logs when plugin debug mode is enabled (respects sln_debug_enabled option)
        add_action('transition_post_status', function($new_status, $old_status, $post) {
            if ($post->post_type === SLN_Plugin::POST_TYPE_BOOKING) {
                // Get the call stack to see what's triggering the status change
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
                $caller_info = array();
                foreach ($backtrace as $i => $trace) {
                    if (isset($trace['file']) && isset($trace['line'])) {
                        $caller_info[] = basename($trace['file']) . ':' . $trace['line'];
                    }
                }
                
                $context_flags = array();
                if (defined('DOING_AJAX') && DOING_AJAX) $context_flags[] = 'AJAX';
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) $context_flags[] = 'AUTOSAVE';
                if (defined('DOING_CRON') && DOING_CRON) $context_flags[] = 'CRON';
                $context = !empty($context_flags) ? ' [' . implode(',', $context_flags) . ']' : '';
                
                // Use plugin's standard logging (respects debug mode setting)
                SLN_Plugin::addLog(sprintf(
                    'BOOKING STATUS TRANSITION: #%d: %s → %s%s | Caller: %s',
                    $post->ID,
                    $old_status,
                    $new_status,
                    $context,
                    implode(' → ', array_slice($caller_info, 0, 3))
                ));
            }
        }, 10, 3);


        new SLN_TaxonomyType_ServiceCategory(
            $p,
            SLN_Plugin::TAXONOMY_SERVICE_CATEGORY,
            array(SLN_Plugin::POST_TYPE_SERVICE)
        );
        $this->initSchedules();

        add_action('template_redirect', array($this, 'template_redirect'));
        new SLN_Privacy();
        new SLN_Action_InitScripts($this->plugin, is_admin());
        $this->initPolylangSupport();
        SLB_Discount_Plugin::getInstance();

        add_action('init', array($this, 'hook_action_init'));
        if (!SLN_Action_Install::isInstalled()) {
            add_action('init', function(){
                SLN_Action_Install::execute();
                $post_types = array(SLN_Plugin::POST_TYPE_BOOKING, SLN_Plugin::POST_TYPE_SERVICE, SLN_Plugin::POST_TYPE_ATTENDANT);
                if($this->plugin->getSettings()->get('enable_discount_system')){
                    $post_types[] = SLB_Discount_Plugin::POST_TYPE_DISCOUNT;
                }

                // temp fix staff role add default caps
                SLN_UserRole_SalonStaff::changeCapabilitiesByPostType($post_types, true);
            });
        }

	if(defined("SLN_VERSION_CODECANYON")){
            new SLN_Action_InitEnvatoAutomaticPluginUpdate();
        }

	add_action( 'profile_update', array($this, 'updateProfileLastUpdateTime') );

        new SLN_Action_UpdatePhoneCountryDialCode($p);
    }


    private function initAdmin()
    {
        global $sln_license;

        $p = $this->plugin;
        new SLN_Metabox_Service($p, SLN_Plugin::POST_TYPE_SERVICE);
        new SLN_Metabox_Attendant($p, SLN_Plugin::POST_TYPE_ATTENDANT);
        new SLN_Metabox_Booking($p, SLN_Plugin::POST_TYPE_BOOKING);
        new SLN_Metabox_BookingActions($p, SLN_Plugin::POST_TYPE_BOOKING);
        new SLN_Metabox_Resource($p, SLN_Plugin::POST_TYPE_RESOURCE);

        new SLN_Admin_Calendar($p);
        new SLN_Admin_Tools($p);
        new SLN_Admin_Customers($p);
        new SLN_Admin_Reports($p);
        new SLN_Admin_Settings($p);
        new SLN_Admin_DeactivationSurvey($p);
        
        // Cache Warmer Setup Notice
        // Cache warmer is now in Tools section, no admin notice needed
        
        // IP1SMS API Migration Notice (API V2 Migration)
        $migration = new SLN_Admin_MigrationTools_Ip1SmsMigration($p);
        add_action('admin_notices', array($migration, 'showMigrationNotice'));
        add_action('wp_ajax_sln_dismiss_ip1sms_migration_notice', array($migration, 'handleDismissNotice'));
        
        // Check migration notice expiry daily
        if (!wp_next_scheduled('sln_check_ip1sms_migration_notice_expiry')) {
            wp_schedule_event(time(), 'daily', 'sln_check_ip1sms_migration_notice_expiry');
        }
        add_action('sln_check_ip1sms_migration_notice_expiry', array($migration, 'checkDismissedNoticeExpiry'));
        if (defined('SLN_VERSION_PAY') && SLN_VERSION_PAY) {
            new SLN_Admin_Extensions($p);
        }

        add_action('admin_init', array($this, 'hook_admin_init'));
        // Note: initAjax() is now called from __construct() to support wp_ajax_nopriv_ actions
        new SLN_Action_InitComments($p);

	if (!current_user_can('delete_permanently_sln_booking')) {
	    $this->disablePermanentlyDeleteBookings();
	}

    add_action( 'admin_menu', function () {
        if ( in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            remove_menu_page( 'edit.php' );                   //Posts
            remove_menu_page( 'edit-comments.php' );          //Comments
            remove_menu_page( 'tools.php' );                  //Tools
            remove_menu_page( 'profile.php' );                  //Profile
            remove_menu_page( 'index.php' );                  //Dashboard
            remove_submenu_page( 'salon', 'edit.php?post_type=sln_service' ); //Services Salon menu
            remove_submenu_page( 'salon', 'edit-tags.php?taxonomy=sln_service_category&post_type=sln_service'); //Service Categories Salon menu
            remove_submenu_page( 'salon', 'edit.php?post_type=sln_resource' ); //Resources Salon menu
            remove_submenu_page( 'salon', 'edit.php?post_type=sln_discount'); //Discounts Salon menu
            remove_submenu_page( 'salon', SLN_Admin_Reports::PAGE); //Reports Salon menu
            remove_submenu_page( 'salon', SLN_Admin_Customers::PAGE ); //Customers Salon menu
        }
    }, 1000);

    add_action( 'admin_head', function () {
        if ( in_array(SLN_Plugin::USER_ROLE_STAFF,  wp_get_current_user()->roles) ) {
            remove_submenu_page( 'salon', 'edit-tags.php?taxonomy=sln_shop_category&post_type=sln_shop'); // Salon Shops Categories
            $args = array(
                'meta_key' => '_sln_attendant_email',
                'meta_value' => wp_get_current_user()->user_email,
                'post_type' => SLN_Plugin::POST_TYPE_ATTENDANT,
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
            );
            $posts_ids = get_posts($args);
            foreach ($posts_ids as $post_id) {
                $backend_calendar_only = get_post_meta( $post_id, '_sln_attendant_limit_staff_member_to_backend_calendar_only', true );
                if($backend_calendar_only){
                    remove_menu_page( 'edit.php' ); // Posts
                    remove_menu_page( 'edit-comments.php' ); // Comments
                    remove_menu_page( 'tools.php' ); // Tools
                    remove_menu_page( 'profile.php' ); // Profile
                    remove_menu_page( 'upload.php' ); // Upload
                    remove_menu_page( 'index.php' );                  //Dashboard
                    remove_submenu_page( 'salon', 'edit.php?post_type=' . SLN_Plugin::POST_TYPE_BOOKING ); // Salon Bookings
                    remove_submenu_page( 'salon', 'edit.php?post_type=' . SLN_Plugin::POST_TYPE_ATTENDANT); // Salon Assistants
                    remove_submenu_page( 'salon', 'edit.php?post_type=' . SLB_Discount_Plugin::POST_TYPE_DISCOUNT); // Salon Discounts
                    remove_submenu_page( 'salon', 'edit.php?post_type=' . SLN_Plugin::POST_TYPE_SERVICE); // Salon Services
                    remove_submenu_page( 'salon', 'edit-tags.php?taxonomy=' . SLN_Plugin::TAXONOMY_SERVICE_CATEGORY . '&post_type=' . SLN_Plugin::POST_TYPE_SERVICE); // Salon Services Categories
                    remove_submenu_page( 'salon', SLN_Admin_Customers::PAGE ); // Salon Customers
                    remove_submenu_page( 'salon', SLN_Admin_Reports::PAGE); // Salon Reports
                    remove_submenu_page( 'salon', SLN_Admin_Tools::PAGE); // Salon Tools
                    break;
                }
            }
        }
    }, 1000);

    add_action( 'admin_bar_menu', function ($wp_admin_bar) {
        if ( in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            $wp_admin_bar->remove_node( 'edit-profile' );
            $wp_admin_bar->remove_node( 'user-info' );
            $wp_admin_bar->remove_node( 'comments' );
            $wp_admin_bar->remove_node( 'new-content' );
            $wp_admin_bar->remove_node( 'view' );
        }
    }, 1000 );

    add_action('wp_before_admin_bar_render', function () {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('my-account');

        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        if (!$user_id)
            return;

        $avatar = get_avatar($user_id, 26);
        $howdy = sprintf(
            // translators: %s will be replaced by the current username
            __('Howdy, %s'), '<span class="display-name">' . $current_user->display_name . '</span>');
        $class = empty($avatar) ? '' : 'with-avatar';

        $wp_admin_bar->add_menu(array(
            'id' => 'my-account',
            'parent' => 'top-secondary',
            'title' => $howdy . $avatar,
            'meta' => array(
                'class' => $class,
            ),
        ));
    });

    add_action( 'current_screen', function() {
        $screen = get_current_screen();
        if ( isset( $screen->id ) && $screen->id == 'dashboard' && in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles)  ) {
            wp_redirect( admin_url( 'admin.php?page=salon' ) );
            exit();
        }
    } );

    add_filter('bulk_actions-edit-sln_attendant', function ($actions) {
        if ( in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            unset( $actions['edit'] );
	    }
        return $actions;
    }, 10, 2);

    add_filter('post_row_actions',function ($actions, $post) {
        if ( in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            unset($actions['trash']);
            unset($actions['inline hide-if-no-js']);
            unset($actions['clone']);
            unset($actions['view']);
        }
        return $actions;
    },1000,2);

    add_filter('bulk_actions-edit-sln_booking', function ($actions) {
        if ( in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            unset( $actions['edit'] );
        }
        return $actions;
    }, 10, 2);

    add_action('admin_head-post.php', function() {
        if ( in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            echo '
                <style type="text/css">
                    #misc-publishing-actions,
                    #sln_booking-notify,
                    #sln_booking-actions,
                    #post-body-content {
                        opacity: 0.5;
                        pointer-events: none;
                    }
                </style>
            ';
        }
    });

    add_action( 'load-profile.php', function() {
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            wp_die(
                '<p>' . esc_html__( 'Sorry, you are not allowed access to this page.' ) . '</p>',
                403
            );
        }
    });

    add_action( 'load-edit-comments.php', function() {
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            wp_die(
                '<p>' . esc_html__( 'Sorry, you are not allowed access to this page.' ) . '</p>',
                403
            );
        }
    });

    add_action( 'load-comment.php', function() {
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            wp_die(
                '<p>' . esc_html__( 'Sorry, you are not allowed access to this page.' ) . '</p>',
                403
            );
        }
    });

    add_action( 'load-edit.php', function() {
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) && empty($_GET['post_type']) ) {
            wp_die(
                '<p>' . esc_html__( 'Sorry, you are not allowed access to this page.' ) . '</p>',
                403
            );
        }
    });

    add_action( 'load-post.php', function() {
        $postID = isset($_GET['post']) ? $_GET['post'] : (isset($_POST['post_ID']) ? $_POST['post_ID'] : 0);
        $post = get_post($postID);
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) && (!$post || !in_array($post->post_type, array('sln_attendant', 'sln_booking')))) {
            wp_die(
                '<p>' . esc_html__( 'Sorry, you are not allowed access to this page.' ) . '</p>',
                403
            );
        }

        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) && $post && $post->post_type === 'sln_attendant' && get_post_meta($post->ID, '_sln_attendant_staff_member_id', true) != get_current_user_id() ) {
            wp_die(
                '<p>' . esc_html__( 'Sorry, you are not allowed access to this page.' ) . '</p>',
                403
            );
        }

        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) && $post && $post->post_type === 'sln_booking' ) {

            $repo	    = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);
            $attendants = $repo->getAll();

            foreach ($attendants as $attendant) {
                if ($attendant->getMeta('staff_member_id') == get_current_user_id() && $attendant->getIsStaffMemberAssignedToBookingsOnly()) {
                $assistantsIDs[] = $attendant->getId();
                }
            }

            if (!array_filter(get_post_meta($post->ID, '_sln_booking_services', true), function($item) use($assistantsIDs) { return in_array($item['attendant'], $assistantsIDs); }) ) {
                wp_die(
                    '<p>' . esc_html__( 'Sorry, you are not allowed access to this page.' ) . '</p>',
                    403
                );
            }
        }
    });

    add_action( 'load-post-new.php', function() {
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            wp_die(
                '<p>' . esc_html__( 'Sorry, you are not allowed access to this page.' ) . '</p>',
                403
            );
        }
    });

    add_filter( "views_edit-sln_attendant", function ($views) {
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ) {
            return array();
        }
        return $views;
    });

    add_filter( 'wp_count_posts', function ($counts, $type, $perm) {
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) && $type === 'sln_booking' ) {

            global $wpdb;

            if ( ! post_type_exists( $type ) ) {
                return new stdClass;
            }

		    $cache_key = _count_posts_cache_key( $type, $perm );

		    $assistantsIDs = array();

            $repo	    = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);
            $attendants = $repo->getAll();

            foreach ($attendants as $attendant) {
                if ($attendant->getMeta('staff_member_id') == get_current_user_id() && $attendant->getIsStaffMemberAssignedToBookingsOnly()) {
                    $assistantsIDs[] = $attendant->getId();
                }
            }

            $query = "SELECT p.post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} p";

            if ( ! empty( $assistantsIDs ) ) {
                $query .= " INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sln_booking_services' ";
            }

            $query .= " WHERE p.post_type = %s ";

            if ( 'readable' === $perm && is_user_logged_in() ) {
                $post_type_object = get_post_type_object( $type );
                if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
                    $query .= $wpdb->prepare(
                        " AND (p.post_status != 'private' OR ( p.post_author = %d AND p.post_status = 'private' ))",
                        get_current_user_id()
                    );
                }
            }

            if ( ! empty( $assistantsIDs ) ) {
                $query .= $wpdb->prepare(
                    " AND pm.meta_value REGEXP %s ",
                    implode('|', array_map(function ($v) {
                        return sprintf('"attendant";i:%s;', $v);
                    }, $assistantsIDs))
                );
		    }

            $query .= ' GROUP BY p.post_status';

            $results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
            $counts  = array_fill_keys( get_post_stati(), 0 );

            foreach ( $results as $row ) {
                $counts[ $row['post_status'] ] = $row['num_posts'];
            }

            $counts = (object) $counts;
            wp_cache_set( $cache_key, $counts, 'counts' );

            return $counts;
        }
        return $counts;
    }, 10, 3);

    add_filter( 'disable_months_dropdown', function ($result, $post_type ) {
        if (  in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) && $post_type === 'sln_attendant' ) {
            return true;
        }
        return $result;
    }, 10, 2);

    }

    private function initFrontend()
    {
	add_action('parse_request', array(new SLN_Action_RescheduleBooking($this->plugin), 'execute'));
	add_action('parse_request', array(new SLN_Action_CancelBookingLink($this->plugin), 'execute'));
	add_action('parse_request', array(new SLN_Action_LinkServicesBooking($this->plugin), 'execute'));
        if (class_exists('SLN_Payment_Stripe')) {
            add_action('parse_request', array(new SLN_Payment_Stripe($this->plugin), 'execute'));
        }
    }

    private function initAjax()
    {
        $callback = array($this->plugin, 'ajax');
        //http://codex.wordpress.org/AJAX_in_Plugins
        add_action('wp_ajax_salon', $callback);
        add_action('wp_ajax_nopriv_salon', $callback);
        add_action('wp_ajax_saloncalendar', $callback);
        add_action('wp_ajax_sln_send_feedback_email', array(new SLN_Action_Ajax_SendFeedback($this->plugin), 'execute'));
        add_action('wp_ajax_sln_send_bulk_feedback', array(new SLN_Action_Ajax_SendBulkFeedback($this->plugin), 'execute'));
        add_action('wp_ajax_sln_preview_bulk_feedback', array(new SLN_Action_Ajax_PreviewBulkFeedback($this->plugin), 'execute'));
        add_action('wp_ajax_sln_ajax_noshow', array(new SLN_Action_Ajax_OnNoShow($this->plugin), 'execute'));
        
        // Cache warmer AJAX endpoint (public, for external cron services)
        new SLN_Action_Ajax_CacheWarmer($this->plugin);
    }

    private function initSchedules() {
        add_filter('cron_schedules', array($this, 'cron_schedules'));

        if (!wp_get_schedule('sln_email_weekly_report')) {
            SLN_TimeFunc::startRealTimezone();
            if (((int)current_time('w')) === (SLN_Enum_DaysOfWeek::MONDAY) &&
                SLN_Func::getMinutesFromDuration(current_time('H:i')) < 8*60) {

                $time  = time();
                $time -= $time % (24*60*60);
            }
            else {
                $time  = SLN_TimeFunc::strtotime("next Monday");
            }

            $time += 8 * 60 * 60; // Monday 8:00
            wp_schedule_event($time, 'weekly', 'sln_email_weekly_report');
            unset($time);
            SLN_TimeFunc::endRealTimezone();
        }

        add_action('sln_sms_reminder', 'sln_sms_reminder');
        add_action('sln_email_reminder', 'sln_email_reminder');
        add_action('sln_sms_followup', 'sln_sms_followup');
        add_action('sln_email_followup', 'sln_email_followup');
        add_action('sln_email_feedback', 'sln_email_feedback');
        add_action('sln_cancel_bookings', 'sln_cancel_bookings');
        add_action('sln_email_weekly_report', 'sln_email_weekly_report');
        add_action('sln.helper.calendar_link.remove', array('SLN_Helper_CalendarLink', 'cronUnlinkCall'), 10, 1);
        
        // Clear reminder metadata when booking status changes to prevent reminders for canceled bookings
        add_action('sln.booking.setStatus', array($this, 'clearReminderMetaOnStatusChange'), 10, 3);

	if ( ! wp_get_schedule('sln_clean_up_database') ) {
	    wp_schedule_event(time(), 'daily', 'sln_clean_up_database');
	}

	add_action('sln_clean_up_database', 'sln_clean_up_database');
    }

    public function hook_action_init()
    {
        $p = $this->plugin;
        SLN_Shortcode_Container::init($p);
        SLN_Shortcode_Salon::init($p);
        SLN_Shortcode_SalonMyAccount::init($p);
        SLN_Shortcode_SalonMyAccount_Details::init($p);
        SLN_Shortcode_SalonCalendar::init($p);
        SLN_Shortcode_SalonAssistant::init($p);
        SLN_Shortcode_SalonServices::init($p);
        SLN_Shortcode_SalonRecentComments::init($p);

        SLN_Enum_AvailabilityModeProvider::init();
        $this->plugin->addRepository(
            new SLN_Repository_BookingRepository(
                $this->plugin,
                new SLN_PostType_Booking($p, SLN_Plugin::POST_TYPE_BOOKING)
            )
        );

        $this->plugin->addRepository(
            new SLN_Repository_ServiceRepository(
                $this->plugin,
                new SLN_PostType_Service($p, SLN_Plugin::POST_TYPE_SERVICE)
            )
        );
        $this->plugin->addRepository(
            new SLN_Repository_AttendantRepository(
                $this->plugin,
                new SLN_PostType_Attendant($p, SLN_Plugin::POST_TYPE_ATTENDANT)
            )
        );
        $this->plugin->addRepository(
            new SLN_Repository_ResourceRepository(
                $this->plugin,
                new SLN_PostType_Resource($p, SLN_Plugin::POST_TYPE_RESOURCE)
            )
        );
    }

    public function hook_admin_init()
    {
        new SLN_Action_Update($this->plugin);
        if(!get_option('_sln_welcome_show_page')){
            if (isset($_GET['page']) && $_GET['page'] == 'salon') {
                return;
            }
    
            wp_safe_redirect(add_query_arg(array('page' => 'salon'), admin_url('admin.php')));
    
            exit();
        }
    }

    public function initPolylangSupport()
    {
        add_filter('pll_get_post_types', array($this, 'hook_pll_get_post_types'));
    }

    public function hook_pll_get_post_types($types)
    {
        unset ($types['sln_booking']);
        //decomment this to have "single language services and attendant
        //unset($types['sln_service']);
        //unset($types['sln_attendant']);

        return $types;
    }

    public function template_redirect() {
        $customerHash = isset($_GET['sln_customer_login']) ? sanitize_text_field(wp_unslash( $_GET['sln_customer_login'] )) : '';
        $feedback_id = isset($_GET['feedback_id']) ? sanitize_text_field(wp_unslash($_GET['feedback_id'])) : '';
        
        if (!empty($customerHash)) {
            // SECURITY FIX: Add rate limiting to prevent brute-force attacks
            // This protects against attackers trying to guess login tokens
            // Strict mode: 3 attempts per 10 minutes per IP address
            $client_ip = SLN_Helper_RateLimiter::getClientIP();
            $rate_limit_identifier = 'autologin_' . $client_ip;
            
            // Log all auto-login attempts for security monitoring
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 100) : 'unknown';
            SLN_Plugin::addLog(sprintf(
                '[Security] Auto-login attempt - Hash: %s, IP: %s, User-Agent: %s',
                substr($customerHash, 0, 16) . '...',
                $client_ip,
                $user_agent
            ));
            
            // Check rate limit BEFORE processing login (strict mode: 3 attempts per 10 minutes)
            if (!SLN_Helper_RateLimiter::checkRateLimit($rate_limit_identifier, true)) {
                // Rate limit exceeded - log security event
                SLN_Plugin::addLog(sprintf(
                    '[Security] BLOCKED - Auto-login brute-force attempt from IP: %s',
                    $client_ip
                ));
                error_log(sprintf(
                    '[Salon Security] Auto-login rate limit exceeded from IP: %s, User-Agent: %s',
                    $client_ip,
                    $user_agent
                ));
                
                // Show generic error (don't reveal we're rate limiting to attackers)
                wp_die(
                    '<h1>' . esc_html__('Access Denied', 'salon-booking-system') . '</h1>' .
                    '<p>' . esc_html__('Too many login attempts. Please try again later.', 'salon-booking-system') . '</p>',
                    esc_html__('Access Denied', 'salon-booking-system'),
                    array('response' => 403)
                );
            }
            
            $userid = SLN_Wrapper_Customer::getCustomerIdByHash($customerHash);
            
            // Log failed attempts - invalid hash
            if (!$userid) {
                SLN_Plugin::addLog(sprintf(
                    '[Security] FAILED - Invalid hash from IP: %s',
                    $client_ip
                ));
                error_log(sprintf(
                    '[Salon Security] Auto-login failed - Invalid hash from IP: %s',
                    $client_ip
                ));
                return; // Exit early, don't process further
            }
            
            // Check if token is valid (exists in transient and matches)
            $stored_hash = get_transient("sln_customer_login_{$userid}");
            
            if ($stored_hash !== $customerHash) {
                // Log failed attempts - expired or mismatched token
                SLN_Plugin::addLog(sprintf(
                    '[Security] FAILED - Expired/invalid token for user %d from IP: %s (stored: %s, provided: %s)',
                    $userid,
                    $client_ip,
                    $stored_hash ? substr($stored_hash, 0, 16) . '...' : 'none',
                    substr($customerHash, 0, 16) . '...'
                ));
                error_log(sprintf(
                    '[Salon Security] Auto-login failed - Expired/mismatched token for user %d from IP: %s',
                    $userid,
                    $client_ip
                ));
                return; // Exit early
            }
            
            // Token is valid, proceed with authentication
            $user = get_user_by('id', (int) $userid);
            if ($user) {
                $customer = new SLN_Wrapper_Customer($user);
                if (!$customer->isEmpty()) {
                    // SUCCESSFUL LOGIN - Reset rate limit for this IP
                    SLN_Helper_RateLimiter::resetRateLimit($rate_limit_identifier);
                    
                    // Log successful authentication
                    SLN_Plugin::addLog(sprintf(
                        '[Security] SUCCESS - Auto-login successful for user %d (%s) from IP: %s',
                        $userid,
                        $user->user_login,
                        $client_ip
                    ));
                    error_log(sprintf(
                        '[Salon Security] Auto-login successful for user %d (%s) from IP: %s',
                        $userid,
                        $user->user_login,
                        $client_ip
                    ));
                    
                    wp_set_auth_cookie($user->ID, false);
                    do_action('wp_login', $user->user_login, $user);

                    // Invalidate token immediately (single-use tokens)
                    $customer->deleteMeta('hash');
                    delete_transient("sln_customer_login_{$userid}");

                    // Create redirect URL without autologin code
                    $id = $this->plugin->getSettings()->getBookingmyaccountPageId();
                    
                    // Fallback: If translated ID is null, try getting the original page ID
                    if (!$id) {
                        $id = $this->plugin->getSettings()->get('bookingmyaccount');
                        error_log('[Salon Auto-Login] Translated page ID was null, using original: ' . ($id ? $id : 'STILL NULL'));
                    }
                    
                    // Debug logging
                    error_log('[Salon Auto-Login] Customer hash: ' . substr($customerHash, 0, 16) . '...');
                    error_log('[Salon Auto-Login] User ID: ' . $userid);
                    error_log('[Salon Auto-Login] Booking My Account Page ID: ' . ($id ? $id : 'NOT SET'));
                    error_log('[Salon Auto-Login] Current locale: ' . get_locale());
                    
                    if ($id) {
                        $url = get_permalink($id);
                        error_log('[Salon Auto-Login] Redirecting to: ' . $url);
                        if(!empty($feedback_id)) {
                            $url .= '?feedback_id='. $feedback_id;
                        }
                    }else{
                        $url = home_url();
                        error_log('[Salon Auto-Login] No account page set, redirecting to home: ' . $url);
                    }
                    wp_redirect($url);
                    exit;
                }
            }
        }
    }

    public function cron_schedules($schedules) {
        $schedules['weekly'] = array(
            'interval' => 60 * 60 * 24 * 7,
            'display' => __('Weekly', 'salon-booking-system')
        );
        
        // Add 25-minute schedule for cache warming (refreshes before 30-min cache expires)
        $schedules['sln_25min'] = array(
            'interval' => 25 * 60, // 25 minutes in seconds
            'display' => __('Every 25 Minutes', 'salon-booking-system')
        );

        return $schedules;
    }

    private function initLicense()
    {
        global $sln_license;
        /** @var SLN_Update_Manager $sln_license */
        $sln_license = new SLN_Update_Manager(
            array(
                'slug'     => SLN_ITEM_SLUG,
                'basename' => SLN_PLUGIN_BASENAME,
                'name'     => SLN_ITEM_NAME,
                'version'  => SLN_VERSION,
                'author'   => SLN_AUTHOR,
                'store'    => SLN_STORE_URL,
                'api_key'  => SLN_API_KEY,
                'api_token'=> SLN_API_TOKEN,
            )
        );
    }

    public function disablePermanentlyDeleteBookings() {

	add_filter( 'pre_delete_post', function ($check, $post, $force_delete) {
	    if ($post->post_type === SLN_Plugin::POST_TYPE_BOOKING) {
		return false;
	    }
	    return $check;
	}, 10, 3);

	if (isset($_GET['post_type']) && $_GET['post_type'] === SLN_Plugin::POST_TYPE_BOOKING) {
	    add_action( 'admin_enqueue_scripts', function () {
		wp_enqueue_style('admin-disable-delete-permanently', SLN_PLUGIN_URL.'/css/admin-disable-delete-permanently.css', array(), SLN_Action_InitScripts::ASSETS_VERSION, 'all');
	    });
	}
    }

    public function updateProfileLastUpdateTime($user_id) {
	update_user_meta($user_id, '_sln_last_update', current_time('timestamp', true));
    }

    /**
     * Clear reminder metadata when booking status changes to non-remindable status.
     * This prevents race conditions where reminders are sent to canceled bookings.
     * 
     * Follows the same pattern as SLB_Discount::hook_booking_setStatus() which clears
     * discount metadata when bookings are canceled.
     * 
     * @param SLN_Wrapper_Booking $booking The booking object
     * @param string $oldStatus Previous booking status
     * @param string $newStatus New booking status
     */
    public function clearReminderMetaOnStatusChange($booking, $oldStatus, $newStatus) {
        // Define statuses that are eligible for reminders
        $remindableStatuses = array(
            SLN_Enum_BookingStatus::PAID,
            SLN_Enum_BookingStatus::CONFIRMED,
            SLN_Enum_BookingStatus::PAY_LATER
        );
        
        $wasRemindable = in_array($oldStatus, $remindableStatuses);
        $isRemindable = in_array($newStatus, $remindableStatuses);
        
        // If status changed from remindable to non-remindable, clear reminder metadata
        if ($wasRemindable && !$isRemindable) {
            // Clear both SMS and email reminder metadata to prevent sending
            $booking->setMeta('sms_remind', false);
            $booking->setMeta('email_remind', false);
            
            $this->plugin->addLog(sprintf(
                'Reminder metadata cleared for booking #%d (status changed: %s → %s)',
                $booking->getId(),
                $oldStatus,
                $newStatus
            ));
        }
    }
}
