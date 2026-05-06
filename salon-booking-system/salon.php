<?php

/*
Plugin Name: Salon Booking System - Free Version
Description: Let your customers book you services through your website. Perfect for hairdressing salons, barber shops and beauty centers.
Version: 10.30.27
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
define('SLN_VERSION', '10.30.27');
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

spl_autoload_register($sln_autoload);

// WP 6.7+: load translations and boot the main plugin on init (not plugins_loaded) to avoid
// _load_textdomain_just_in_time notices and accidental output before session_start().
add_action(
	'init',
	function () {
		static $sln_bootstrapped = false;
		if ( $sln_bootstrapped ) {
			return;
		}
		$sln_bootstrapped = true;

		add_filter(
			'plugin_locale',
			function ( $locale, $domain ) {
				if ( $domain === 'salon-booking-system' ) {
					$locale = SLN_Helper_Multilingual::getDateLocale();
				}
				return $locale;
			},
			10,
			2
		);

		load_plugin_textdomain( 'salon-booking-system', false, basename( SLN_PLUGIN_DIR ) . '/languages' );

		global $sln_plugin;
		$sln_plugin = SLN_Plugin::getInstance();
		do_action( 'sln.init', $sln_plugin );
	},
	0
);
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

// Initialize rollback handler after WordPress is fully loaded (PRO only)
if (defined('SLN_VERSION_PAY')) {
	add_action('plugins_loaded', function() {
		global $sln_rollback_handler;
		$sln_rollback_handler = new \SLN\Update\RollbackHandler();
	});
}

add_action('init', function () {
	if ( headers_sent() ) {
		return;
	}
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
		if ( session_status() === PHP_SESSION_NONE ) {
			session_start();
		}
	}
}, 1);

add_action('init', function () {

	if (!empty($_GET['action']) && $_GET['action'] === 'updraftmethod-googledrive-auth') {
		return;
	}

	//TODO[feature-gcalendar]: move this require in the right place
	require_once SLN_PLUGIN_DIR . "/src/SLN/Third/GoogleScope.php";
	require_once SLN_PLUGIN_DIR . "/src/SLN/Third/GoogleCalendarSlotLocker.php";
	$sln_googlescope = new SLN_GoogleScope();
	$GLOBALS['sln_googlescope'] = $sln_googlescope;
	$sln_googlescope->set_settings_by_plugin(SLN_Plugin::getInstance());
	$sln_googlescope->wp_init();
	SLN_Third_GoogleCalendarImport::launch($GLOBALS['sln_googlescope']);
	SLN_Third_GoogleCalendarSlotLocker::launch($GLOBALS['sln_googlescope']);
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
	// PWA: next salon-booking-pwa load rewrites dist assets from *.template.* (new plugin zip).
	\SLB_PWA\Plugin::invalidate_dist_regeneration_cache();

	// Record activation time for churn analysis
	if (!get_option('sln_activation_time')) {
		update_option('sln_activation_time', current_time('timestamp'));
	}

	// Flag to redirect to onboarding wizard on next admin load (first activation)
	set_transient('sln_redirect_to_onboarding', true, 30);
	
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
		
		// Send follow-up email if user provided detailed feedback
		if ($survey_data && !empty($survey_data['feedback']) && trim($survey_data['feedback']) !== '') {
			sln_send_deactivation_followup_email($survey_data);
		}
		
		// Clean up transient
		delete_transient('sln_deactivation_survey_data');
		
	} catch (Error $e) {
		// Fail silently - don't break deactivation
		return;
	}
});

/**
 * Send follow-up email to user after deactivation with feedback
 * 
 * This function sends a personalized email from support to users who:
 * - Deactivated the plugin
 * - Provided detailed written feedback
 * - Encountered issues they're willing to discuss
 * 
 * @param array $survey_data Survey data from deactivation form
 * @return bool True if email sent successfully, false otherwise
 */
if (!function_exists('sln_send_deactivation_followup_email')) {
	function sln_send_deactivation_followup_email($survey_data) {
		try {
			// Get current user (person who deactivated the plugin)
			$current_user = wp_get_current_user();
			$user_email = $current_user->user_email;
			
			// Fallback to admin email if current user has no email
			if (empty($user_email)) {
				$user_email = get_option('admin_email');
			}
			
			// Don't send if we still don't have a valid email
			if (empty($user_email) || !is_email($user_email)) {
				SLN_Plugin::addLog('Deactivation follow-up email skipped - no valid email address');
				return false;
			}
			
			// Get user's first name for personalization (fallback to generic greeting)
			$user_name = '';
			if ($current_user->exists()) {
				$user_name = !empty($current_user->first_name) ? $current_user->first_name : $current_user->display_name;
			}
			
			// Build email subject
			$subject = 'May I help you with Salon Booking System activation?';
			
			// Build email body (HTML format)
			$body = sln_build_deactivation_email_body($user_name, $survey_data);
			
			// Set email headers
			$headers = array(
				'From: Salon Booking System Support <support@salonbookingsystem.com>',
				'Reply-To: support@salonbookingsystem.com',
				'Content-Type: text/html; charset=UTF-8'
			);
			
			// Send email (non-blocking - don't delay deactivation)
			$sent = wp_mail($user_email, $subject, $body, $headers);
			
			if ($sent) {
				SLN_Plugin::addLog("Deactivation follow-up email sent to: {$user_email}");
			} else {
				SLN_Plugin::addLog("Deactivation follow-up email failed to send to: {$user_email}");
			}
			
			return $sent;
			
		} catch (Exception $e) {
			// Fail silently - we don't want email failures to break deactivation
			SLN_Plugin::addLog("Deactivation follow-up email exception: " . $e->getMessage());
			return false;
		}
	}
}

/**
 * Build HTML email body for deactivation follow-up
 * 
 * @param string $user_name User's first name or display name
 * @param array $survey_data Survey data including reason and feedback
 * @return string HTML email body
 */
if (!function_exists('sln_build_deactivation_email_body')) {
	function sln_build_deactivation_email_body($user_name, $survey_data) {
		// Get site URL for context
		$site_url = home_url();
		$site_name = get_bloginfo('name');
		
		// Greeting personalization
		$greeting = !empty($user_name) ? "Hi {$user_name}" : "Hi";
		
		// Get deactivation reason for reference
		$reason_labels = array(
			'setup_too_complex' => 'Setup too complex',
			'not_what_expected' => "It's not what I expected",
			'missing_feature' => 'Missing a key feature',
			'just_trying' => 'Just trying it out',
			'other' => 'Other'
		);
		$reason = isset($survey_data['reason']) ? $survey_data['reason'] : 'other';
		$reason_text = isset($reason_labels[$reason]) ? $reason_labels[$reason] : 'Other';
		
		// Build HTML email
		$html = '<!DOCTYPE html>';
		$html .= '<html lang="en">';
		$html .= '<head>';
		$html .= '<meta charset="UTF-8">';
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
		$html .= '<title>May I help you with Salon Booking System?</title>';
		$html .= '</head>';
		$html .= '<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">';
		
		// Email content
		$html .= '<div style="background: #f8f9fa; padding: 30px; border-radius: 8px; border-left: 4px solid #2271b1;">';
		
		$html .= '<p style="font-size: 16px; margin-bottom: 20px;">' . esc_html($greeting) . ',</p>';
		
		$html .= '<p style="font-size: 16px; margin-bottom: 20px;">';
		$html .= 'I\'m Dimitri, founder of <strong>Salon Booking System</strong>. Thank you for giving our plugin a chance.';
		$html .= '</p>';
		
		$html .= '<p style="font-size: 16px; margin-bottom: 20px;">';
		$html .= 'We noticed that you had a problem during the plugin activation and on-boarding process. ';
		$html .= 'If you don\'t mind, we would take a closer look at that problem and try to find a solution.';
		$html .= '</p>';
		
		$html .= '<p style="font-size: 16px; margin-bottom: 20px;">';
		$html .= 'If you agree, please <strong>reply to this email</strong> providing the link of your website and login credentials. ';
		$html .= 'We\'ll take a look as soon as possible.';
		$html .= '</p>';
		
		$html .= '<div style="background: white; padding: 20px; border-radius: 6px; margin: 25px 0; border: 1px solid #ddd;">';
		$html .= '<p style="margin: 0 0 10px; font-size: 14px; color: #666;"><strong>Your feedback:</strong></p>';
		$html .= '<p style="margin: 0 0 10px; font-size: 14px; color: #666;"><em>Reason: ' . esc_html($reason_text) . '</em></p>';
		$html .= '<p style="margin: 0; font-size: 15px; font-style: italic; color: #444;">';
		$html .= '"' . esc_html($survey_data['feedback']) . '"';
		$html .= '</p>';
		$html .= '</div>';
		
		$html .= '<p style="font-size: 16px; margin-bottom: 20px;">';
		$html .= 'Thank you for your attention.';
		$html .= '</p>';
		
		$html .= '<p style="font-size: 16px; margin-bottom: 0;">';
		$html .= 'All the best,<br>';
		$html .= '<strong>The Staff - Salon Booking System</strong>';
		$html .= '</p>';
		
		$html .= '</div>';
		
		// Footer
		$html .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; font-size: 13px; color: #666; text-align: center;">';
		$html .= '<p style="margin: 0 0 10px;">This email was sent because you deactivated Salon Booking System on:</p>';
		$html .= '<p style="margin: 0 0 10px;"><strong>' . esc_html($site_name) . '</strong><br>';
		$html .= '<a href="' . esc_url($site_url) . '" style="color: #2271b1; text-decoration: none;">' . esc_html($site_url) . '</a></p>';
		$html .= '<p style="margin: 20px 0 0; font-size: 12px; color: #999;">Salon Booking System | <a href="https://www.salonbookingsystem.com" style="color: #2271b1;">salonbookingsystem.com</a></p>';
		$html .= '</div>';
		
		$html .= '</body>';
		$html .= '</html>';
		
		return $html;
	}
}

ob_start();
