<?php
/**
 * Debug: Why No Update Notice?
 * 
 * This script diagnoses why WordPress isn't showing plugin update notifications
 * 
 * USAGE:
 * 1. Upload to site root
 * 2. Visit: https://salon.salonbooking.it/debug-why-no-update-notice.php
 * 3. DELETE after debugging!
 */

require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug: Why No Update Notice?</title>
    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #23282d; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-top: 30px; }
        .success { background: #d4edda; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; font-size: 11px; max-height: 300px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { padding: 8px; border: 1px solid #ddd; }
        table td:first-child { font-weight: bold; background: #f9f9f9; width: 200px; }
        .step { counter-increment: step; }
        .step::before { content: "Step " counter(step) ": "; font-weight: bold; color: #0073aa; }
    </style>
</head>
<body style="counter-reset: step;">

<div class="container">
    <h1>🔍 Debug: Why No Update Notice?</h1>
    
    <?php
    error_log('=== UPDATE DEBUG START ===');
    
    // Get plugin data
    $plugin_file = WP_CONTENT_DIR . '/plugins/salon-booking-plugin-pro/salon.php';
    $plugin_basename = 'salon-booking-system/salon.php';
    
    if (!file_exists($plugin_file)) {
        echo '<div class="error">❌ Plugin file not found!</div>';
        exit;
    }
    
    // Get current version
    $plugin_data = get_file_data($plugin_file, ['Version' => 'Version']);
    $current_version = $plugin_data['Version'];
    
    echo '<div class="info">';
    echo '<strong>📦 Current Installed Version:</strong> ' . $current_version;
    echo '</div>';
    
    // ========================================
    echo '<h2 class="step">Check if Update Processor is Initialized</h2>';
    // ========================================
    
    global $sln_license;
    
    if (!$sln_license) {
        echo '<div class="error">❌ $sln_license global not found!</div>';
        echo '<p>The license manager is not initialized. This is required for updates.</p>';
    } else {
        echo '<div class="success">✅ $sln_license global exists</div>';
        
        // Check if processor exists
        if (isset($sln_license->processor) && $sln_license->processor) {
            echo '<div class="success">✅ Update processor is initialized</div>';
            echo '<p>Type: ' . get_class($sln_license->processor) . '</p>';
        } else {
            echo '<div class="error">❌ Update processor NOT initialized!</div>';
            echo '<p><strong>This is the problem!</strong> The processor should be initialized in hook_admin_init().</p>';
            
            // Check if Manager.php has the fix
            $manager_file = WP_CONTENT_DIR . '/plugins/salon-booking-plugin-pro/src/SLN/Update/Manager.php';
            $manager_content = file_get_contents($manager_file);
            
            if (strpos($manager_content, "if ('plugins.php' == \$pagenow") !== false) {
                echo '<div class="error">❌ Manager.php still has OLD CODE with page check!</div>';
                echo '<p>The fix was NOT uploaded correctly. Upload Manager.php again.</p>';
            } else {
                echo '<div class="warning">⚠️ Manager.php looks correct, but processor not initialized</div>';
                echo '<p>The hook might not be running. Check if admin_init fired.</p>';
            }
        }
    }
    
    // ========================================
    echo '<h2 class="step">Check WordPress Update Transient</h2>';
    // ========================================
    
    $update_plugins = get_site_transient('update_plugins');
    
    if (!$update_plugins) {
        echo '<div class="warning">⚠️ update_plugins transient is empty</div>';
        echo '<p>WordPress hasn\'t checked for updates yet, or the transient was deleted.</p>';
    } else {
        echo '<div class="success">✅ update_plugins transient exists</div>';
        
        if (isset($update_plugins->response[$plugin_basename])) {
            $update_data = $update_plugins->response[$plugin_basename];
            echo '<div class="success">✅ UPDATE AVAILABLE!</div>';
            echo '<table>';
            echo '<tr><td>New Version</td><td>' . $update_data->new_version . '</td></tr>';
            echo '<tr><td>Package URL</td><td style="word-break: break-all;">' . (isset($update_data->package) ? $update_data->package : 'N/A') . '</td></tr>';
            echo '</table>';
            
            echo '<div class="error">';
            echo '<h3>🤔 Update IS detected but not showing?</h3>';
            echo '<p>This is strange. WordPress knows about the update but it\'s not displaying.</p>';
            echo '<p><strong>Possible causes:</strong></p>';
            echo '<ul>';
            echo '<li>Browser cache - Try hard refresh (Cmd+Shift+R)</li>';
            echo '<li>Plugin is in "drop-in" mode</li>';
            echo '<li>Another plugin is hiding updates</li>';
            echo '<li>Theme is filtering update notices</li>';
            echo '</ul>';
            echo '</div>';
            
        } elseif (isset($update_plugins->no_update[$plugin_basename])) {
            echo '<div class="info">ℹ️ Plugin is marked as "no_update"</div>';
            $no_update = $update_plugins->no_update[$plugin_basename];
            echo '<table>';
            echo '<tr><td>Cached Version</td><td>' . (isset($no_update->new_version) ? $no_update->new_version : 'N/A') . '</td></tr>';
            echo '<tr><td>Your Version</td><td>' . $current_version . '</td></tr>';
            echo '</table>';
            
            if (isset($no_update->new_version) && version_compare($current_version, $no_update->new_version, '<')) {
                echo '<div class="error">';
                echo '<h3>❌ BUG FOUND!</h3>';
                echo '<p>A newer version exists (' . $no_update->new_version . ') but WordPress marked it as "no_update".</p>';
                echo '<p><strong>This means the version comparison is failing!</strong></p>';
                echo '</div>';
            }
        } else {
            echo '<div class="warning">⚠️ Plugin not found in update transient at all</div>';
            echo '<p>The update check mechanism isn\'t adding our plugin to the transient.</p>';
        }
        
        echo '<h3>Full Transient Data:</h3>';
        echo '<pre>' . print_r($update_plugins, true) . '</pre>';
    }
    
    // ========================================
    echo '<h2 class="step">Direct API Call to EDD</h2>';
    // ========================================
    
    if ($sln_license) {
        echo '<div class="info">Calling getVersion() directly...</div>';
        
        $version_info = $sln_license->getVersion();
        
        if ($version_info && isset($version_info->new_version)) {
            echo '<div class="success">✅ EDD API returned version info</div>';
            echo '<table>';
            echo '<tr><td>Latest Version</td><td>' . $version_info->new_version . '</td></tr>';
            echo '<tr><td>Your Version</td><td>' . $current_version . '</td></tr>';
            echo '<tr><td>Comparison</td><td>';
            
            if (version_compare($current_version, $version_info->new_version, '<')) {
                echo '<strong style="color: #28a745;">UPDATE AVAILABLE ✅</strong>';
                echo '<br>(' . $current_version . ' < ' . $version_info->new_version . ')';
            } else {
                echo '<strong>You are on the latest version</strong>';
            }
            echo '</td></tr>';
            echo '</table>';
            
            echo '<h3>Full API Response:</h3>';
            echo '<pre>' . print_r($version_info, true) . '</pre>';
        } else {
            echo '<div class="error">❌ EDD API returned no version info!</div>';
            echo '<p>The API call failed or returned invalid data.</p>';
            
            if ($version_info === false) {
                echo '<p><strong>Return value:</strong> FALSE (complete failure)</p>';
            } else {
                echo '<pre>' . print_r($version_info, true) . '</pre>';
            }
        }
    } else {
        echo '<div class="error">❌ Cannot call API - $sln_license not initialized</div>';
    }
    
    // ========================================
    echo '<h2 class="step">Check License Status</h2>';
    // ========================================
    
    if ($sln_license) {
        $license_key = $sln_license->get('license_key');
        $license_status = $sln_license->get('license_status');
        
        echo '<table>';
        echo '<tr><td>License Key</td><td>' . ($license_key ? substr($license_key, 0, 20) . '...' : 'NOT SET') . '</td></tr>';
        echo '<tr><td>License Status</td><td>' . ($license_status ?: 'NOT SET') . '</td></tr>';
        echo '</table>';
        
        if ($license_status !== 'valid') {
            echo '<div class="error">❌ License is not valid! Updates require a valid license.</div>';
        } else {
            echo '<div class="success">✅ License is valid</div>';
        }
    }
    
    // ========================================
    echo '<h2 class="step">Force WordPress to Check for Updates</h2>';
    // ========================================
    
    echo '<div class="info">Deleting transient and forcing fresh check...</div>';
    
    delete_site_transient('update_plugins');
    wp_update_plugins();
    
    $update_plugins_fresh = get_site_transient('update_plugins');
    
    if ($update_plugins_fresh && isset($update_plugins_fresh->response[$plugin_basename])) {
        echo '<div class="success">✅ SUCCESS! Update now detected after forced check!</div>';
        $update_data = $update_plugins_fresh->response[$plugin_basename];
        echo '<p><strong>New version available:</strong> ' . $update_data->new_version . '</p>';
        echo '<p><strong>What to do:</strong> Go to <a href="' . admin_url('plugins.php') . '">Plugins page</a> and hard refresh (Cmd+Shift+R)</p>';
    } else {
        echo '<div class="error">❌ Still no update detected after forced check</div>';
        
        if (isset($update_plugins_fresh->no_update[$plugin_basename])) {
            echo '<p>Plugin is in "no_update" section. Check API response above for version mismatch.</p>';
        }
    }
    
    // ========================================
    echo '<h2 class="step">Summary & Diagnosis</h2>';
    // ========================================
    
    $issues = [];
    
    if (!$sln_license) {
        $issues[] = '❌ License manager not initialized';
    }
    
    if ($sln_license && !isset($sln_license->processor)) {
        $issues[] = '❌ Update processor not initialized (fix not deployed)';
    }
    
    if ($license_status !== 'valid') {
        $issues[] = '❌ License not valid';
    }
    
    if (!$version_info || !isset($version_info->new_version)) {
        $issues[] = '❌ EDD API not returning version data';
    }
    
    if (empty($issues)) {
        echo '<div class="success">';
        echo '<h3>✅ Everything looks correct!</h3>';
        echo '<p>The update mechanism is working. If you still don\'t see the update notice:</p>';
        echo '<ol>';
        echo '<li>Go to <a href="' . admin_url('plugins.php') . '" target="_blank">Plugins page</a></li>';
        echo '<li>Do a HARD REFRESH (Cmd+Shift+R or Ctrl+Shift+R)</li>';
        echo '<li>Look under "Salon Booking System - Pro Version" for the update notice</li>';
        echo '</ol>';
        echo '</div>';
    } else {
        echo '<div class="error">';
        echo '<h3>❌ Issues Found:</h3>';
        echo '<ul>';
        foreach ($issues as $issue) {
            echo '<li>' . $issue . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    ?>
    
    <hr>
    
    <div class="warning">
        <strong>⚠️ DELETE THIS FILE after debugging!</strong><br>
        File: <code><?php echo __FILE__; ?></code>
    </div>

</div>

</body>
</html>

