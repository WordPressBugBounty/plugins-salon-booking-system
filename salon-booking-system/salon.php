<?php

/*
Plugin Name: Salon Booking System - Free Version
Description: Let your customers book you services through your website. Perfect for hairdressing salons, barber shops and beauty centers.
Version: 10.30.17
Plugin URI: http://salonbookingsystem.com/
Author: Salon Booking System
Author URI: http://salonbookingsystem.com/
Text Domain: salon-booking-system
Domain Path: /languages
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

 */

if (!function_exists('sln_deactivate_plugin')) {
	function sln_deactivate_plugin()
	{
		if (function_exists('sln_autoload')) {  //deactivate for other version
			spl_autoload_unregister('sln_autoload');
		}
		if (function_exists('my_update_notice')) {
			remove_action('in_plugin_update_message-' . SLN_PLUGIN_BASENAME, 'my_update_notice');
		}

		global $sln_autoload, $my_update_notice; //deactivate for this version
		if (isset($sln_autoload)) {
			spl_autoload_unregister($sln_autoload);
		}
		if (isset($my_update_notice)) {
			remove_action('in_plugin_update_message-' . SLN_PLUGIN_BASENAME, $my_update_notice);
		}
		deactivate_plugins(SLN_PLUGIN_BASENAME);
	}
}

if (defined('SLN_PLUGIN_BASENAME')) {
	if (! function_exists('deactivate_plugins')) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	sln_deactivate_plugin();
}

define('SLN_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SLN_PLUGIN_DIR', untrailingslashit(dirname(__FILE__)));
define('SLN_PLUGIN_URL', untrailingslashit(plugins_url('', __FILE__)));
define('SLN_VERSION', '10.30.17');
define('SLN_STORE_URL', 'https://salonbookingsystem.com');
define('SLN_AUTHOR', 'Salon Booking');
define('SLN_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/sln_uploads/');
define('SLN_UPLOADS_URL', wp_upload_dir()['baseurl'] . '/sln_uploads/');
define('SLN_ITEM_SLUG', 'salon-booking-wordpress-plugin');
define('SLN_ITEM_NAME', 'Salon booking wordpress plugin');
define('SLN_ITEM_ID', 'salon-booking-wordpress-plugin');
define('SLN_API_KEY', '0b47c255778d646aaa89b6f40859b159');
define('SLN_API_TOKEN', '7c901a98fa10dd3af65b038d6f5f190c');






$sln_autoload = function ($className) {
	if (strpos($className, 'SLN_') === 0) {
		$filename = SLN_PLUGIN_DIR . "/src/" . str_replace("_", "/", $className) . '.php';
		if (file_exists($filename)) {
			require_once $filename;
			return;
		}
	} elseif (strpos($className, 'SLN\\') === 0) {
		$filename = SLN_PLUGIN_DIR . "/src/" . str_replace("\\", "/", $className) . '.php';
		if (file_exists($filename)) {
			require_once $filename;
			return;
		}
	} elseif (strpos($className, 'Salon') === 0) {
		$filename = SLN_PLUGIN_DIR . "/src/" . str_replace("\\", "/", $className) . '.php';
		if (file_exists($filename)) {
			require_once $filename;
			return;
		}
	}

	$discountAppPrefixes = array(
		'SLB_Discount_',
		'SLN_',
	);
	foreach ($discountAppPrefixes as $prefix) {
		if (strpos($className, $prefix) === 0) {
			$classWithoutPrefix = str_replace("_", "/", substr($className, strlen($prefix)));
			$filename = SLN_PLUGIN_DIR . "/src/" . substr($prefix, 0, -1) . "/{$classWithoutPrefix}.php";
			if (file_exists($filename)) {
				require_once $filename;
				return;
			}
		}
	}

	if (strpos($className, 'SLB_API') === 0) {
		$filename = SLN_PLUGIN_DIR . "/src/" . str_replace("\\", "/", $className) . '.php';
		if (file_exists($filename)) {
			require_once $filename;
			return;
		}
	}

	if (strpos($className, 'SLB_Customization') === 0) {
		$filename = SLN_PLUGIN_DIR . "/src/" . str_replace("\\", "/", $className) . '.php';
		if (file_exists($filename)) {
			require_once $filename;
			return;
		}
	}

	if (strpos($className, 'SLB_Zapier') === 0) {
		$filename = SLN_PLUGIN_DIR . "/src/" . str_replace("\\", "/", $className) . '.php';
		if (file_exists($filename)) {
			require_once $filename;
			return;
		}
	}
	if (strpos($className, 'SLB_PWA') === 0) {
		$filename = SLN_PLUGIN_DIR . "/src/" . str_replace("\\", "/", $className) . '.php';
		if (file_exists($filename)) {
			require_once $filename;
			return;
		}
	}
};

$my_update_notice = function () {
	$info = __('-', 'salon-booking-system');
	echo '<span class="spam">' . wp_kses($info, array(
		'br' => array(),
		'a' => array(
			'href' => array(),
			'title' => array()
		),
		'b' => array(),
		'i' => array(),
		'span' => array()
	)) . '</span>';
};

if (is_admin()) {
	add_action('in_plugin_update_message-' . plugin_basename(__FILE__), $my_update_notice);
}

add_action("in_plugin_update_message-" . plugin_basename(__FILE__), function ($plugin_data, $response) {
	echo '<span style="background-color: #d54e21; padding: 10px; color: #f9f9f9; margin-top: 10px; display: block"><strong>';
	esc_html_e('Attention: this is a major release, please make sure to clear your browser cache after the plugin update.', 'salon-booking-system');
	echo '</strong></span>';
}, 10, 2);

add_action('plugins_loaded', function () {
	add_filter('plugin_locale', function ($locale, $domain) {
		if ($domain === 'salon-booking-system') {
			// Get proper locale for multilingual plugins (WPML/Polylang)
			$locale = SLN_Helper_Multilingual::getDateLocale();
		}
		return $locale;
	}, 10, 2);
	
	// Load translations - WordPress will check all standard locations:
	// 1. WP_LANG_DIR/plugins/ (Sistema/Global - Loco Translate default)
	// 2. WP_LANG_DIR/loco/plugins/ (Loco Translate custom location)
	// 3. Plugin's own /languages/ directory (Autore location)
	load_plugin_textdomain('salon-booking-system', false, basename(SLN_PLUGIN_DIR) . '/languages');
});
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

spl_autoload_register($sln_autoload);
$sln_plugin = SLN_Plugin::getInstance();
do_action('sln.init', $sln_plugin);

// Initialize rollback handler after WordPress is fully loaded (PRO only)
if (defined('SLN_VERSION_PAY')) {
	add_action('plugins_loaded', function() {
		global $sln_rollback_handler;
		$sln_rollback_handler = new \SLN\Update\RollbackHandler();
	});
}

add_action('init', function () {
	if ((!session_id() || session_status() !== PHP_SESSION_ACTIVE)
		&& !strstr($_SERVER['REQUEST_URI'], '/wp-admin/site-health.php')
		&& !strstr($_SERVER['REQUEST_URI'], '/wp-json/wp-site-health')
		&& !(isset($_POST['action']) && $_POST['action'] === 'health-check-loopback-requests')
		&& !(isset($_REQUEST['action']) && $_REQUEST['action'] === 'wp_async_send_server_events')
	) {
		// Use a custom session name to avoid Edge browser tracking prevention blocking PHPSESSID
		// Edge's Enhanced Tracking Prevention can block cookies named PHPSESSID as "tracking cookies"
		session_name('sln_booking_session');
		
		// Configure session cookie parameters for better browser compatibility (especially Edge)
		// Set SameSite to Lax for better compatibility while maintaining security
		if (PHP_VERSION_ID >= 70300) {
			// PHP 7.3+ supports SameSite attribute directly
			session_set_cookie_params([
				'lifetime' => 0,
				'path' => COOKIEPATH ? COOKIEPATH : '/',
				'domain' => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax'
			]);
		} else {
			// PHP < 7.3 workaround for SameSite
			session_set_cookie_params(
				0,
				COOKIEPATH ? COOKIEPATH . '; SameSite=Lax' : '/; SameSite=Lax',
				COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				is_ssl(),
				true
			);
		}
		session_start();
	}
}, 1);

add_action('init', function () {

	if (!empty($_GET['action']) && $_GET['action'] === 'updraftmethod-googledrive-auth') {
		return;
	}

	//TODO[feature-gcalendar]: move this require in the right place
	require_once SLN_PLUGIN_DIR . "/src/SLN/Third/GoogleScope.php";
	$sln_googlescope = new SLN_GoogleScope();
	$GLOBALS['sln_googlescope'] = $sln_googlescope;
	$sln_googlescope->set_settings_by_plugin(SLN_Plugin::getInstance());
	$sln_googlescope->wp_init();
	SLN_Third_GoogleCalendarImport::launch($GLOBALS['sln_googlescope']);
});

$sln_api = \SLB_API\Plugin::get_instance();
$sln_api_mobile = \SLB_API_Mobile\Plugin::get_instance();

$sln_customization = \SLB_Customization\Plugin::get_instance();

$sln_zapier = \SLB_Zapier\Plugin::get_instance();

$sln_pwa = \SLB_PWA\Plugin::get_instance();

add_filter('body_class', function ($classes) {
	return array_merge($classes, array('sln-salon-page'));
});

register_activation_hook(__FILE__, function () {
	// Record activation time for churn analysis
	if (!get_option('sln_activation_time')) {
		update_option('sln_activation_time', current_time('timestamp'));
	}
	
	// Track activation to salonbookingsystem.com
	wp_remote_post('https://www.salonbookingsystem.com/wp-json/sbs-tracker/v1/activation', array(
		'blocking' => false, // Don't slow down activation
		'timeout' => 2,
		'sslverify' => true,
		'body' => array(
			'version' => defined('SLN_VERSION_PAY') && SLN_VERSION_PAY ? 'pro' : 'free',
			'plugin_version' => SLN_VERSION,
			'wp_version' => get_bloginfo('version'),
			'php_version' => phpversion(),
			'locale' => get_locale(),
			'site_hash' => hash('sha256', home_url())
		)
	));
});

register_deactivation_hook(__FILE__, function () {
	// Track deactivation to salonbookingsystem.com with survey data
	try {
		// Get survey data if available (set by AJAX handler)
		$survey_data = get_transient('sln_deactivation_survey_data');
		
		// Calculate activation metrics
		$activation_time = get_option('sln_activation_time', current_time('timestamp'));
		$days_active = floor((current_time('timestamp') - $activation_time) / DAY_IN_SECONDS);
		
		// Prepare payload
		$payload = array(
			'version' => defined('SLN_VERSION_PAY') && SLN_VERSION_PAY ? 'pro' : 'free',
			'plugin_version' => SLN_VERSION,
			'site_hash' => hash('sha256', home_url()),
			'days_active' => intval($days_active)
		);
		
		// Add survey data if available
		if ($survey_data) {
			$payload['deactivation_reason'] = isset($survey_data['reason']) ? $survey_data['reason'] : 'skipped';
			$payload['deactivation_feedback'] = isset($survey_data['feedback']) ? $survey_data['feedback'] : '';
			$payload['deactivation_rating'] = isset($survey_data['rating']) ? intval($survey_data['rating']) : 0;
			$payload['setup_progress'] = isset($survey_data['setup_progress']) ? intval($survey_data['setup_progress']) : 0;
			$payload['completed_first_booking'] = isset($survey_data['completed_first_booking']) ? (bool) $survey_data['completed_first_booking'] : false;
		} else {
			// No survey data - user skipped
			$payload['deactivation_reason'] = 'skipped';
			$payload['deactivation_feedback'] = '';
			$payload['deactivation_rating'] = 0;
			$payload['setup_progress'] = 0;
			$payload['completed_first_booking'] = false;
		}
		
		wp_remote_post('https://www.salonbookingsystem.com/wp-json/sbs-tracker/v1/deactivation', array(
			'blocking' => false,
			'timeout' => 2,
			'sslverify' => true,
			'body' => $payload
		));
		
		// Clean up transient
		delete_transient('sln_deactivation_survey_data');
		
	} catch (Error $e) {
		// Fail silently - don't break deactivation
		return;
	}
});

ob_start();
