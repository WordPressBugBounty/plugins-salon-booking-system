<?php
/**
 * Test Update Detection Fix
 * 
 * USAGE:
 * 1. Upload to site root
 * 2. Visit: https://your-site.com/test-update-fix.php
 * 3. This will clear caches and force a fresh update check
 * 4. DELETE this file after testing!
 */

require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Update Detection Fix</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .step { background: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa; }
        .success { background: #d4edda; padding: 15px; margin: 10px 0; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; padding: 15px; margin: 10px 0; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border: 1px solid #bee5eb; color: #0c5460; }
        .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid #ffeeba; color: #856404; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; }
        h2 { color: #23282d; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        .button { display: inline-block; padding: 10px 20px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 3px; margin: 10px 5px; }
        .button-delete { background: #dc3232; }
    </style>
</head>
<body>

<h1>🧪 Test Update Detection Fix</h1>

<?php
// Display current version
echo '<div class="info">';
echo '<strong>📦 Current Plugin Version:</strong> ' . (defined('SLN_VERSION') ? SLN_VERSION : 'Unknown');
echo '</div>';

// Step 1: Clear all caches
echo '<div class="step">';
echo '<h2>Step 1: Clearing All Caches</h2>';
delete_site_transient('update_plugins');
$cache_key = md5('edd_plugin_'.sanitize_key(SLN_PLUGIN_BASENAME).'_version_info');
delete_transient($cache_key);
echo '<div class="success">✅ All update caches cleared</div>';
echo '</div>';

// Step 2: Force WordPress to check for updates
echo '<div class="step">';
echo '<h2>Step 2: Forcing WordPress Update Check</h2>';
wp_update_plugins();
echo '<div class="success">✅ WordPress update check completed</div>';
echo '</div>';

// Step 3: Check if update is detected
echo '<div class="step">';
echo '<h2>Step 3: Checking Update Detection</h2>';

$update_plugins = get_site_transient('update_plugins');

if ($update_plugins && isset($update_plugins->response[SLN_PLUGIN_BASENAME])) {
    $update_info = $update_plugins->response[SLN_PLUGIN_BASENAME];
    
    echo '<div class="success">';
    echo '<h3>🎉 SUCCESS! WordPress Detected Update</h3>';
    echo '<strong>Update Available:</strong> ' . $update_info->new_version . '<br>';
    echo '<strong>Current Version:</strong> ' . SLN_VERSION . '<br>';
    echo '<strong>Download Package:</strong> Available ✅<br>';
    echo '</div>';
    
    echo '<h4>Full Update Info:</h4>';
    echo '<pre>' . print_r($update_info, true) . '</pre>';
    
} else {
    echo '<div class="info">';
    echo '<h3>ℹ️ No Update Detected</h3>';
    echo '<p>This is normal if you\'re already on the latest version.</p>';
    
    // Double-check by calling API directly
    global $sln_license;
    if ($sln_license) {
        $version_info = $sln_license->getVersion();
        if ($version_info && isset($version_info->new_version)) {
            echo '<p><strong>Latest version on server:</strong> ' . $version_info->new_version . '</p>';
            
            if (version_compare(SLN_VERSION, $version_info->new_version, '<')) {
                echo '<div class="error">';
                echo '<strong>⚠️ PROBLEM:</strong> API says update is available but WordPress didn\'t register it!<br>';
                echo 'This suggests the update processor filter is not working correctly.';
                echo '</div>';
            } else {
                echo '<div class="success">';
                echo '✅ You are on the latest version (' . SLN_VERSION . ')';
                echo '</div>';
            }
        }
    }
    echo '</div>';
}
echo '</div>';

// Final instructions
echo '<div class="step">';
echo '<h2>✅ Test Complete</h2>';
echo '<p><a href="' . admin_url('plugins.php') . '" class="button">Go to Plugins Page</a></p>';
echo '<p><a href="' . admin_url('plugins.php?page=salon-booking-wordpress-plugin-license') . '" class="button">Go to License Page</a></p>';
echo '<p><strong style="color: #dc3232;">⚠️ IMPORTANT: Delete this file after testing!</strong></p>';
echo '</div>';

?>

</body>
</html>

