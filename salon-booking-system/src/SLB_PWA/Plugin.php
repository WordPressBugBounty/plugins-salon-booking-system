<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
namespace SLB_PWA;

use SLB_API_Mobile\Helper\TokenHelper;
use SLB_API_Mobile\Helper\UserRoleHelper;

class Plugin {

    private static $instance;

    public static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('parse_request', array($this, 'render_page'));
    }
    
    /**
     * Ensure template files exist by copying from source files
     * Only runs once when templates don't exist
     *
     * @param string $dist_directory_path Path to dist directory
     */
    private function ensure_templates_exist($dist_directory_path)
    {
        $templates = array(
            '/js/app.template.js' => '/js/app.js',
            '/js/app.js.template.map' => '/js/app.js.map',
            '/service-worker.template.js' => '/service-worker.js',
            '/service-worker.js.template.map' => '/service-worker.js.map',
            '/index.template.html' => '/index.html',
        );
        
        foreach ($templates as $template => $source) {
            $template_path = $dist_directory_path . $template;
            $source_path = $dist_directory_path . $source;
            
            if (!file_exists($template_path) && file_exists($source_path)) {
                file_put_contents($template_path, file_get_contents($source_path));
            }
        }
    }

    public function render_page()
    {
        global $wp;

        $current_url = home_url(add_query_arg(array(), $wp->request));
        $salon_booking_pwa_url = home_url('salon-booking-pwa');

        if ($salon_booking_pwa_url !== trim($current_url, '/')) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( home_url($_SERVER['REQUEST_URI']) ) );
            exit();
        }

        $user               = wp_get_current_user();
	$user_role_helper   = new UserRoleHelper();

        if ( ! $user_role_helper->is_allowed_user($user) ) {
	        esc_html_e( 'Sorry, your user role is not allowed.', 'salon-booking-system' );
            exit();
	}

        $dist = SLN_PLUGIN_URL . '/src/SLB_PWA/pwa/dist';
        
        // Cache-busting: Use build time from app.js modification time
        $app_js_path = SLN_PLUGIN_DIR . '/src/SLB_PWA/pwa/dist/js/app.js';
        $cache_buster = file_exists($app_js_path) ? filemtime($app_js_path) : time();

        $labels = LabelProvider::getInstance()->getLabels();

        $user_roles = (array) $user->roles;
        $can_use_assistant_filter = in_array('administrator', $user_roles, true)
            || in_array('shop_manager', $user_roles, true)
            || in_array('sln_shop_manager', $user_roles, true);
        $can_use_assistant_filter = apply_filters('sln_pwa_can_use_assistant_filter', $can_use_assistant_filter, $user);

        $can_access_booking_resize_pref = in_array('administrator', $user_roles, true)
            || in_array('shop_manager', $user_roles, true)
            || in_array('sln_shop_manager', $user_roles, true);
        $can_access_booking_resize_pref = apply_filters('sln_pwa_can_access_booking_resize_pref', $can_access_booking_resize_pref, $user);

        $data = array(
            'is_pro'                           => defined('SLN_VERSION_PAY') && SLN_VERSION_PAY,
            'pro_pricing_url'                  => apply_filters('sln_pwa_pro_pricing_url', 'https://www.salonbookingsystem.com/plugin-pricing/'),
            'api'                              => home_url('wp-json/salon/api/mobile/v1/'),
            'token'                            => (new TokenHelper())->getUserAccessToken($user->ID),
            'onesignal_app_id'                 => \SLN_Plugin::getInstance()->getSettings()->get('onesignal_app_id'),
            'locale'                           => explode('_', \SLN_Plugin::getInstance()->getSettings()->getDateLocale())[0],
            'is_shops'                         => apply_filters('sln_is_shops_enabled', false),
            'labels'                           => $labels,
            'can_use_assistant_filter'         => (bool) $can_use_assistant_filter,
            'can_access_booking_resize_pref'   => (bool) $can_access_booking_resize_pref,
            /** Sample promo cards when store featured add-ons are unavailable. Disable: add_filter('sln_pwa_dummy_promo_cards', '__return_false'). */
            'dummy_promo_cards'                => (bool) apply_filters('sln_pwa_dummy_promo_cards', true),
            /** EDD downloads: add-on category + "featured" term; see FeaturedAddonPromos. */
            'featured_addon_promos'            => FeaturedAddonPromos::get_slides(),
            /** First promo card: free → PRO or Basic → Business; see PwaLicensePromo. */
            'license_upgrade_promo'            => PwaLicensePromo::get_for_pwa(),
        );

        $dist_directory_path = SLN_PLUGIN_DIR . '/src/SLB_PWA/pwa/dist';
        $dist_url_path = trim(str_replace(home_url(), '', $dist), '/');
        
        // Cache key to track if files need regeneration
        $cache_key = 'sln_pwa_dist_path_' . md5($dist_url_path);
        $cached_path = get_transient($cache_key);
        
        // Check if source files are newer than cache (e.g., after rebuild)
        $app_js_mtime = file_exists($app_js_path) ? filemtime($app_js_path) : 0;
        $cache_timestamp_key = $cache_key . '_timestamp';
        $cached_timestamp = get_transient($cache_timestamp_key);
        
        // Regenerate if: path changed, cache expired, OR source files are newer than cache
        if ($cached_path !== $dist_url_path || $cached_timestamp < $app_js_mtime) {
            // Ensure template files exist (only create once)
            $this->ensure_templates_exist($dist_directory_path);
            
            // Regenerate processed files with current path
            file_put_contents(
                $dist_directory_path . '/js/app.js', 
                str_replace('{SLN_PWA_DIST_PATH}', $dist_url_path, 
                    file_get_contents($dist_directory_path . '/js/app.template.js'))
            );
            
            $app_js_map_template = $dist_directory_path . '/js/app.js.template.map';
            if ( file_exists( $app_js_map_template ) ) {
                file_put_contents(
                    $dist_directory_path . '/js/app.js.map',
                    str_replace(
                        '{SLN_PWA_DIST_PATH}',
                        $dist_url_path,
                        file_get_contents( $app_js_map_template )
                    )
                );
            }

            file_put_contents(
                $dist_directory_path . '/service-worker.js', 
                str_replace('{SLN_PWA_DIST_PATH}', $dist_url_path, 
                    file_get_contents($dist_directory_path . '/service-worker.template.js'))
            );
            
            $sw_map_template = $dist_directory_path . '/service-worker.js.template.map';
            if ( file_exists( $sw_map_template ) ) {
                file_put_contents(
                    $dist_directory_path . '/service-worker.js.map',
                    str_replace(
                        '{SLN_PWA_DIST_PATH}',
                        $dist_url_path,
                        file_get_contents( $sw_map_template )
                    )
                );
            }
            
            file_put_contents(
                $dist_directory_path . '/index.html', 
                str_replace(
                    array('{SLN_PWA_DIST_PATH}', '{SLN_PWA_DATA}'), 
                    array($dist_url_path, addslashes(wp_json_encode($data))), 
                    file_get_contents($dist_directory_path . '/index.template.html')
                )
            );
            
            // Cache for 1 hour to avoid regenerating on every request
            set_transient($cache_key, $dist_url_path, HOUR_IN_SECONDS);
            set_transient($cache_timestamp_key, $app_js_mtime, HOUR_IN_SECONDS);
        }

    ?>
    <!doctype html>
    <html lang="">
        <head>
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <!--[if IE]><link rel="icon" href="<?php echo $dist ?>/favicon.ico"><![endif]-->
            <title>salon-booking-plugin-pwa</title>
            <script defer="defer" src="<?php echo $dist ?>/js/fontawesome.js?v=<?php echo $cache_buster ?>"></script>
            <script defer="defer" src="<?php echo $dist ?>/js/chunk-vendors.js?v=<?php echo $cache_buster ?>"></script>
            <script defer="defer" src="<?php echo $dist ?>/js/app.js?v=<?php echo $cache_buster ?>"></script>
            <link href="<?php echo $dist ?>/css/chunk-vendors.css?v=<?php echo $cache_buster ?>" rel="stylesheet">
            <link href="<?php echo $dist ?>/css/app.css?v=<?php echo $cache_buster ?>" rel="stylesheet">
            <?php if (file_exists(SLN_PLUGIN_DIR . '/src/SLB_PWA/pwa/dist/img/icons/favicon.svg')): ?>
            <link rel="icon" type="image/svg+xml" href="<?php echo $dist ?>/img/icons/favicon.svg">
            <?php endif; ?>
            <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $dist ?>/img/icons/favicon-32x32.png">
            <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $dist ?>/img/icons/favicon-16x16.png">
            <link rel="manifest" href="<?php echo $dist ?>/manifest.json">
            <meta name="theme-color" content="#ffd100">
            <meta name="apple-mobile-web-app-capable" content="no">
            <meta name="apple-mobile-web-app-status-bar-style" content="default">
            <meta name="apple-mobile-web-app-title" content="Salon Booking Plugin">
            <link rel="apple-touch-icon" href="<?php echo $dist ?>/img/icons/apple-touch-icon-152x152.png">
            <link rel="mask-icon" href="<?php echo $dist ?>/img/icons/safari-pinned-tab.svg" color="#ffd100">
            <meta name="msapplication-TileImage" content="<?php echo $dist ?>/img/icons/msapplication-icon-144x144.png">
            <meta name="msapplication-TileColor" content="#000000">
        </head>
        <body>
            <noscript>
                <strong>We're sorry but salon-booking-plugin-pwa doesn't work properly without JavaScript enabled. Please enable it to continue.</strong></noscript>
            <script>
                var slnPWA = JSON.parse('<?php echo addslashes(wp_json_encode($data)) ?>')
            </script>
            <div id="app"></div>
        </body>
    </html>
    <?php
        exit();
    }
}