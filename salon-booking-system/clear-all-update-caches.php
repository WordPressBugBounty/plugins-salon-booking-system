<?php
/**
 * Clear ALL Update Caches
 * 
 * Upload to site root and visit once, then DELETE
 */

require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

echo '<html><head><title>Clear Update Caches</title></head><body style="font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 20px;">';
echo '<h1>🧹 Clearing All Update Caches</h1>';

// Clear WordPress update transient
delete_site_transient('update_plugins');
echo '<p>✅ Cleared: update_plugins transient</p>';

// Clear EDD version cache
$cache_keys = [
    'edd_plugin_salon-booking-system_version_info',
    'edd_sl_' . md5('salon-booking-wordpress-plugin'),
    md5('edd_plugin_'.sanitize_key('salon-booking-system/salon.php').'_version_info'),
];

foreach ($cache_keys as $key) {
    delete_transient($key);
    echo '<p>✅ Cleared: ' . $key . '</p>';
}

// Clear object cache
wp_cache_flush();
echo '<p>✅ Flushed object cache</p>';

// Force WordPress to check for updates
wp_update_plugins();
echo '<p>✅ Forced WordPress update check</p>';

echo '<hr>';
echo '<h2>✅ All Caches Cleared!</h2>';
echo '<p><strong>Now test:</strong></p>';
echo '<ol>';
echo '<li>Go to: <a href="' . admin_url('plugins.php') . '">Plugins page</a></li>';
echo '<li>Look for update notice under "Salon Booking System - Pro Version"</li>';
echo '<li>Expected: "There is a new version of Salon booking wordpress plugin available..."</li>';
echo '</ol>';

echo '<hr>';
echo '<p style="color: red;"><strong>⚠️ DELETE THIS FILE NOW!</strong></p>';

echo '</body></html>';
?>

