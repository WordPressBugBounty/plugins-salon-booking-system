<?php
/**
 * Force Update Check
 * 
 * This script forces WordPress to check for plugin updates by clearing all caches
 * and triggering a fresh check.
 * 
 * USAGE:
 * 1. Upload to site root
 * 2. Visit: https://your-site.com/force-update-check.php
 * 3. DELETE this file after use!
 */

require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Unauthorized - You must be an administrator to run this script.');
}

echo "<!DOCTYPE html><html><head><title>Force Update Check</title>";
echo "<style>body{font-family:sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;padding:15px;border:1px solid #c3e6cb;color:#155724;margin:10px 0;}";
echo ".info{background:#d1ecf1;padding:15px;border:1px solid #bee5eb;color:#0c5460;margin:10px 0;}";
echo "pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;overflow-x:auto;}";
echo ".button{display:inline-block;padding:10px 20px;background:#0073aa;color:#fff;text-decoration:none;border-radius:3px;}</style>";
echo "</head><body>";

echo "<h1>🔄 Force Update Check</h1>";

// Show current version
echo "<div class='info'>";
echo "<strong>Current Plugin Version:</strong> " . (defined('SLN_VERSION') ? SLN_VERSION : 'Unknown');
echo "</div>";

// Step 1: Clear WordPress update transients
echo "<h2>Step 1: Clearing WordPress Update Transients</h2>";
delete_site_transient('update_plugins');
delete_site_transient('update_themes');
echo "<div class='success'>✅ WordPress update transients cleared</div>";

// Step 2: Clear EDD version cache
echo "<h2>Step 2: Clearing EDD Version Cache</h2>";
$cache_key = md5('edd_plugin_'.sanitize_key(SLN_PLUGIN_BASENAME).'_version_info');
$cleared = delete_transient($cache_key);
echo "<div class='success'>✅ EDD version cache cleared (cache key: $cache_key)</div>";

// Step 3: Force check with EDD API
echo "<h2>Step 3: Forcing Fresh API Check</h2>";
global $sln_license;
if ($sln_license) {
    $version_info = $sln_license->getVersion();
    
    if (is_wp_error($version_info)) {
        echo "<div style='background:#f8d7da;padding:15px;border:1px solid #f5c6cb;color:#721c24;'>";
        echo "❌ <strong>API Error:</strong> " . $version_info->get_error_message();
        echo "</div>";
    } elseif ($version_info && isset($version_info->new_version)) {
        echo "<div class='success'>";
        echo "✅ <strong>API Response Received</strong><br>";
        echo "Latest Version Available: <strong>" . $version_info->new_version . "</strong><br>";
        echo "Your Current Version: <strong>" . SLN_VERSION . "</strong><br><br>";
        
        $comparison = version_compare(SLN_VERSION, $version_info->new_version, '<');
        if ($comparison) {
            echo "📦 <strong style='color:#d63301;'>UPDATE AVAILABLE!</strong> You can update from " . SLN_VERSION . " to " . $version_info->new_version;
        } else {
            echo "✅ <strong>You are on the latest version</strong>";
        }
        echo "</div>";
        
        echo "<h3>Full API Response:</h3>";
        echo "<pre>" . print_r($version_info, true) . "</pre>";
    } else {
        echo "<div style='background:#fff3cd;padding:15px;border:1px solid #ffeeba;color:#856404;'>";
        echo "⚠️ <strong>No version information returned from API</strong>";
        echo "</div>";
    }
} else {
    echo "<div style='background:#f8d7da;padding:15px;border:1px solid #f5c6cb;color:#721c24;'>";
    echo "❌ <strong>License manager not initialized</strong>";
    echo "</div>";
}

// Step 4: Trigger WordPress update check
echo "<h2>Step 4: Triggering WordPress Update Check</h2>";
wp_update_plugins();
echo "<div class='success'>✅ WordPress update check triggered</div>";

// Step 5: Show results
echo "<h2>Step 5: Check Results</h2>";
$update_plugins = get_site_transient('update_plugins');
if ($update_plugins && isset($update_plugins->response[SLN_PLUGIN_BASENAME])) {
    echo "<div style='background:#d4edda;padding:15px;border:1px solid #c3e6cb;color:#155724;'>";
    echo "✅ <strong>WordPress detected an update is available!</strong><br><br>";
    $update_info = $update_plugins->response[SLN_PLUGIN_BASENAME];
    echo "Update to: <strong>" . $update_info->new_version . "</strong><br>";
    if (isset($update_info->package)) {
        echo "Download package: " . $update_info->package . "<br>";
    }
    echo "</div>";
    echo "<h3>Update Info:</h3>";
    echo "<pre>" . print_r($update_info, true) . "</pre>";
} else {
    echo "<div class='info'>";
    echo "ℹ️ WordPress did not detect an available update. This means you're on the latest version.";
    echo "</div>";
}

// Done
echo "<hr>";
echo "<h2>✅ Update Check Complete</h2>";
echo "<p><a href='" . admin_url('plugins.php?page=salon-booking-wordpress-plugin-license') . "' class='button'>Go to License Page</a></p>";
echo "<p><strong style='color:#d63301;'>⚠️ IMPORTANT: Delete this file (force-update-check.php) after use for security!</strong></p>";

echo "</body></html>";

