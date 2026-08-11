<?php
/**
 * Plugin Name: Salon AI Cloud Proxy (billing)
 * Description: Usage ledger, Dodo Payments checkout, and webhooks for Salon Booking AI credits. Deploy on salonbookingsystem.com (namespace salon-ai/v1).
 * Version: 0.1.0
 * Author: Salon Booking System
 *
 * Install on the STORE site (not merchant sites). Copy config.sample.php → config.php and fill secrets.
 */

if (! defined('ABSPATH')) {
	exit;
}

define('SLN_AI_PROXY_DIR', __DIR__);
define('SLN_AI_PROXY_VERSION', '0.1.0');

$configFile = SLN_AI_PROXY_DIR . '/config.php';
if (is_readable($configFile)) {
	require_once $configFile;
}

require_once SLN_AI_PROXY_DIR . '/includes/class-ledger.php';
require_once SLN_AI_PROXY_DIR . '/includes/class-dodo.php';
require_once SLN_AI_PROXY_DIR . '/includes/class-rest.php';
require_once SLN_AI_PROXY_DIR . '/includes/class-webhook.php';
require_once SLN_AI_PROXY_DIR . '/includes/class-chat.php';

add_action(
	'rest_api_init',
	static function () {
		SLN_AI_Proxy_REST::register();
		SLN_AI_Proxy_Webhook::register();
		SLN_AI_Proxy_Chat::register();
	}
);
