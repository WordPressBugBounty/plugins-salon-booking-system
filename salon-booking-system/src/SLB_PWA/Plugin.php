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

        $data = array(
            'api'               => home_url('wp-json/salon/api/mobile/v1/'),
            'token'             => (new TokenHelper())->getUserAccessToken($user->ID),
            'onesignal_app_id'  => \SLN_Plugin::getInstance()->getSettings()->get('onesignal_app_id'),
            'locale'            => explode('_', \SLN_Plugin::getInstance()->getSettings()->getDateLocale())[0],
            'is_shops'          => apply_filters('sln_is_shops_enabled', false),
            'labels'            => $labels,
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
            
            file_put_contents(
                $dist_directory_path . '/js/app.js.map', 
                str_replace('{SLN_PWA_DIST_PATH}', $dist_url_path, 
                    file_get_contents($dist_directory_path . '/js/app.js.template.map'))
            );
            
            file_put_contents(
                $dist_directory_path . '/service-worker.js', 
                str_replace('{SLN_PWA_DIST_PATH}', $dist_url_path, 
                    file_get_contents($dist_directory_path . '/service-worker.template.js'))
            );
            
            file_put_contents(
                $dist_directory_path . '/service-worker.js.map', 
                str_replace('{SLN_PWA_DIST_PATH}', $dist_url_path, 
                    file_get_contents($dist_directory_path . '/service-worker.js.template.map'))
            );
            
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
        <style>
            .free-version-wrapper {
                text-align: center;
                padding: 20px;
                background-color: #ecf1fa9b;
                margin: 10px;
            }
            .free-version-button {
                background-color: #0d6efd;
                color: #fff;
                padding: 6px 12px;
                border-radius: 4px;
                text-decoration: none;
                margin-top: 10px;
                display: inline-block;
            }
        </style>
        <body>
            <noscript>
                <strong>We're sorry but salon-booking-plugin-pwa doesn't work properly without JavaScript enabled. Please enable it to continue.</strong></noscript>
            <script>
                var slnPWA = JSON.parse('<?php echo addslashes(wp_json_encode($data)) ?>')
            </script>
            <?php if ( ! defined('SLN_VERSION_PAY') ):  ?>
                <div class="free-version-wrapper">
                    <p><?php echo sprintf(
                            // translators: %s will be replaced by the username
                            __('Dear <b>%s</b>,<br/> to use our mobile app you need a PRO version of <b>Salon Booking System</b>', 'salon-booking-system'), $user->display_name); ?></p>
                    <p><a href="https://www.salonbookingsystem.com/plugin-pricing/" target="_blank" class="free-version-button"><?php  esc_html_e('Switch to PRO', 'salon-booking-system') ?></a></p>
                </div>
            <?php else: ?>
                <div id="app"></div>
            <?php endif; ?>
        </body>
    </html>
    <?php
        exit();
    }
}