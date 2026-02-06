<?php
/**
 * Debug Update Check
 * 
 * Upload to site root and access via browser to debug update checking
 * URL: https://your-site.com/debug-update-check.php
 * 
 * DELETE THIS FILE AFTER USE!
 */

require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

echo "<h1>Update Check Debug</h1>";
echo "<style>pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }</style>";

// Get current version
echo "<h2>Current Plugin Version</h2>";
echo "<pre>";
echo "Defined Version: " . (defined('SLN_VERSION') ? SLN_VERSION : 'NOT DEFINED') . "\n";
echo "</pre>";

// Check WordPress update transient
echo "<h2>WordPress Update Transient</h2>";
$update_plugins = get_site_transient('update_plugins');
echo "<pre>";
if ($update_plugins && isset($update_plugins->response)) {
    echo "Has Response: Yes\n";
    if (isset($update_plugins->response[SLN_PLUGIN_BASENAME])) {
        echo "Update Available: Yes\n";
        print_r($update_plugins->response[SLN_PLUGIN_BASENAME]);
    } else {
        echo "Update Available: No\n";
    }
} else {
    echo "No transient data\n";
}
if ($update_plugins && isset($update_plugins->checked[SLN_PLUGIN_BASENAME])) {
    echo "\nCached Version Check: " . $update_plugins->checked[SLN_PLUGIN_BASENAME] . "\n";
}
echo "</pre>";

// Test direct API call
echo "<h2>Direct EDD API Call</h2>";
global $sln_license;
if ($sln_license) {
    echo "<h3>Getting Version Info...</h3>";
    $version_info = $sln_license->getVersion();
    echo "<pre>";
    if (is_wp_error($version_info)) {
        echo "ERROR: " . $version_info->get_error_message() . "\n";
    } elseif ($version_info) {
        echo "API Response:\n";
        print_r($version_info);
        
        if (isset($version_info->new_version)) {
            echo "\n\nVersion Comparison:\n";
            echo "Current: " . SLN_VERSION . "\n";
            echo "Latest:  " . $version_info->new_version . "\n";
            echo "Result:  ";
            if (version_compare(SLN_VERSION, $version_info->new_version, '<')) {
                echo "UPDATE AVAILABLE ✅\n";
            } elseif (version_compare(SLN_VERSION, $version_info->new_version, '=')) {
                echo "UP TO DATE ✅\n";
            } else {
                echo "CURRENT VERSION IS NEWER 🤔\n";
            }
        }
    } else {
        echo "No version info returned (false)\n";
    }
    echo "</pre>";
} else {
    echo "<pre>License manager not initialized</pre>";
}

// Show cache keys
echo "<h2>EDD Version Cache</h2>";
$cache_key = md5('edd_plugin_'.sanitize_key(SLN_PLUGIN_BASENAME).'_version_info');
$cached_version = get_transient($cache_key);
echo "<pre>";
echo "Cache Key: $cache_key\n";
if ($cached_version) {
    echo "Cached Data:\n";
    print_r($cached_version);
} else {
    echo "No cached version info\n";
}
echo "</pre>";

// Clear cache button
echo "<h2>Actions</h2>";
if (isset($_GET['clear_cache'])) {
    delete_site_transient('update_plugins');
    delete_transient($cache_key);
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; color: #155724;'>";
    echo "✅ Cache cleared! <a href='?'>Refresh to check again</a>";
    echo "</div>";
} else {
    echo "<a href='?clear_cache=1' class='button button-primary'>Clear All Update Caches</a>";
}

echo "<hr>";
echo "<p><strong>Remember to delete this file after debugging!</strong></p>";

