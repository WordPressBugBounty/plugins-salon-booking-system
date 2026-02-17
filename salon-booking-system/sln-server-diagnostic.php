<?php
/**
 * Salon Booking System - Server Diagnostic Tool
 * 
 * USAGE:
 * 1. Upload this file to WordPress root directory
 * 2. Access: https://your-website.com/sln-server-diagnostic.php
 * 3. Review the server configuration
 * 4. DELETE THIS FILE after checking (security risk!)
 * 
 * SECURITY WARNING:
 * This file exposes server configuration details.
 * DELETE it immediately after use!
 */

// Prevent direct access without WordPress
$wp_load = dirname(__FILE__) . '/wp-load.php';
if (file_exists($wp_load)) {
    require_once($wp_load);
}

// Check if user is admin (if WordPress is loaded)
if (function_exists('is_admin') && function_exists('current_user_can')) {
    if (!current_user_can('manage_options')) {
        die('Access denied. Admin privileges required.');
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Salon Booking System - Server Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2271b1;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #2271b1;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-good {
            color: #46b450;
            font-weight: bold;
        }
        .status-warning {
            color: #f0b849;
            font-weight: bold;
        }
        .status-critical {
            color: #dc3232;
            font-weight: bold;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #f0b849;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .critical-box {
            background: #f8d7da;
            border: 2px solid #dc3232;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info-box {
            background: #d1ecf1;
            border: 2px solid #2271b1;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .delete-warning {
            background: #dc3232;
            color: white;
            padding: 20px;
            margin: 30px 0;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Salon Booking System - Server Diagnostic</h1>
        
        <div class="delete-warning">
            ⚠️ SECURITY WARNING: DELETE THIS FILE AFTER USE!<br>
            This file exposes sensitive server information.
        </div>

        <?php
        // Analyze server configuration
        $issues = array();
        $warnings = array();
        
        // Check PHP limits
        $max_execution_time = ini_get('max_execution_time');
        $memory_limit = ini_get('memory_limit');
        $max_input_time = ini_get('max_input_time');
        $post_max_size = ini_get('post_max_size');
        
        // Convert memory limit to MB for comparison
        $memory_limit_mb = intval($memory_limit);
        
        // Check for critical issues
        if ($max_execution_time > 0 && $max_execution_time < 60) {
            $issues[] = "max_execution_time is too low ($max_execution_time seconds). Recommended: 120+ seconds.";
        }
        
        if ($memory_limit_mb < 128) {
            $issues[] = "memory_limit is too low ($memory_limit). Recommended: 256M or higher.";
        } elseif ($memory_limit_mb < 256) {
            $warnings[] = "memory_limit is adequate ($memory_limit) but could be improved. Recommended: 256M or higher.";
        }
        
        // Check PHP version
        $php_version = PHP_VERSION;
        if (version_compare($php_version, '7.4', '<')) {
            $issues[] = "PHP version is outdated ($php_version). Recommended: PHP 7.4 or higher.";
        } elseif (version_compare($php_version, '8.0', '<')) {
            $warnings[] = "PHP version ($php_version) is adequate but could be upgraded. Recommended: PHP 8.0+.";
        }
        
        // Display issues
        if (!empty($issues)) {
            echo '<div class="critical-box">';
            echo '<h3>🚨 Critical Issues Found</h3>';
            echo '<p>These issues may cause timeout errors in the booking process:</p>';
            echo '<ul>';
            foreach ($issues as $issue) {
                echo '<li>' . htmlspecialchars($issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        if (!empty($warnings)) {
            echo '<div class="warning-box">';
            echo '<h3>⚠️ Warnings</h3>';
            echo '<p>These settings could be improved for better performance:</p>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . htmlspecialchars($warning) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        if (empty($issues) && empty($warnings)) {
            echo '<div class="info-box">';
            echo '<h3>✅ Server Configuration Looks Good</h3>';
            echo '<p>No critical issues detected. If you\'re still experiencing timeouts, the issue may be related to:</p>';
            echo '<ul>';
            echo '<li>Database performance (slow queries, missing indexes)</li>';
            echo '<li>External API calls (Google Calendar, payment gateways)</li>';
            echo '<li>Plugin or theme conflicts</li>';
            echo '<li>Aggressive caching blocking AJAX requests</li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>

        <h2>PHP Configuration</h2>
        <table>
            <tr>
                <th>Setting</th>
                <th>Current Value</th>
                <th>Recommended</th>
                <th>Status</th>
            </tr>
            <?php
            $checks = array(
                'max_execution_time' => array(
                    'current' => $max_execution_time,
                    'recommended' => '120-180',
                    'min' => 60
                ),
                'memory_limit' => array(
                    'current' => $memory_limit,
                    'recommended' => '256M-512M',
                    'min' => 128
                ),
                'max_input_time' => array(
                    'current' => $max_input_time,
                    'recommended' => '60-120',
                    'min' => 30
                ),
                'post_max_size' => array(
                    'current' => $post_max_size,
                    'recommended' => '64M',
                    'min' => 32
                ),
            );
            
            foreach ($checks as $setting => $data) {
                $current = $data['current'];
                $recommended = $data['recommended'];
                
                // Determine status
                $status = '';
                $status_class = '';
                
                if ($setting === 'max_execution_time') {
                    if ($current == 0) {
                        $status = '✅ Unlimited';
                        $status_class = 'status-good';
                    } elseif ($current >= 120) {
                        $status = '✅ Good';
                        $status_class = 'status-good';
                    } elseif ($current >= 60) {
                        $status = '⚠️ Adequate';
                        $status_class = 'status-warning';
                    } else {
                        $status = '❌ Too Low';
                        $status_class = 'status-critical';
                    }
                } elseif (strpos($setting, 'memory') !== false || strpos($setting, 'size') !== false) {
                    $current_mb = intval($current);
                    if ($current_mb >= 256) {
                        $status = '✅ Good';
                        $status_class = 'status-good';
                    } elseif ($current_mb >= 128) {
                        $status = '⚠️ Adequate';
                        $status_class = 'status-warning';
                    } else {
                        $status = '❌ Too Low';
                        $status_class = 'status-critical';
                    }
                } else {
                    $status = '✅ OK';
                    $status_class = 'status-good';
                }
                
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($setting) . '</strong></td>';
                echo '<td>' . htmlspecialchars($current) . '</td>';
                echo '<td>' . htmlspecialchars($recommended) . '</td>';
                echo '<td class="' . $status_class . '">' . $status . '</td>';
                echo '</tr>';
            }
            ?>
        </table>

        <h2>Server Information</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <tr>
                <td><strong>PHP Version</strong></td>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <td><strong>Server Software</strong></td>
                <td><?php echo isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown'; ?></td>
            </tr>
            <?php if (defined('WP_MEMORY_LIMIT')): ?>
            <tr>
                <td><strong>WordPress Memory Limit</strong></td>
                <td><?php echo WP_MEMORY_LIMIT; ?></td>
            </tr>
            <?php endif; ?>
            <?php if (defined('WP_MAX_MEMORY_LIMIT')): ?>
            <tr>
                <td><strong>WordPress Max Memory Limit</strong></td>
                <td><?php echo WP_MAX_MEMORY_LIMIT; ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Operating System</strong></td>
                <td><?php echo PHP_OS; ?></td>
            </tr>
        </table>

        <?php if (defined('WP_DEBUG')): ?>
        <h2>WordPress Debug Settings</h2>
        <table>
            <tr>
                <th>Setting</th>
                <th>Status</th>
            </tr>
            <tr>
                <td><strong>WP_DEBUG</strong></td>
                <td><?php echo WP_DEBUG ? '<span class="status-warning">Enabled</span>' : '<span class="status-good">Disabled</span>'; ?></td>
            </tr>
            <?php if (defined('WP_DEBUG_LOG')): ?>
            <tr>
                <td><strong>WP_DEBUG_LOG</strong></td>
                <td><?php echo WP_DEBUG_LOG ? '<span class="status-good">Enabled</span>' : 'Disabled'; ?></td>
            </tr>
            <?php endif; ?>
            <?php if (defined('WP_DEBUG_DISPLAY')): ?>
            <tr>
                <td><strong>WP_DEBUG_DISPLAY</strong></td>
                <td><?php echo WP_DEBUG_DISPLAY ? '<span class="status-warning">Enabled</span>' : '<span class="status-good">Disabled</span>'; ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php endif; ?>

        <h2>Recommended Actions</h2>
        <div class="info-box">
            <h3>How to Fix Server Configuration Issues</h3>
            
            <h4>Option 1: Add to wp-config.php</h4>
            <p>Add this code to <code>wp-config.php</code> before <code>/* That's all, stop editing! */</code>:</p>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;">
// Increase PHP execution time and memory limits
@ini_set('max_execution_time', 120);
@ini_set('memory_limit', '256M');
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');</pre>

            <h4>Option 2: Add to .htaccess (Apache servers only)</h4>
            <p>Add this to <code>.htaccess</code> file:</p>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;">
# Increase PHP limits
php_value max_execution_time 120
php_value memory_limit 256M
php_value max_input_time 120
php_value post_max_size 64M</pre>

            <h4>Option 3: Contact Hosting Provider</h4>
            <p>If the above methods don't work, contact your hosting provider and request:</p>
            <ul>
                <li>Increase <code>max_execution_time</code> to 120 seconds</li>
                <li>Increase <code>memory_limit</code> to 256M</li>
                <li>Upgrade to PHP 8.0 or higher (if applicable)</li>
            </ul>
        </div>

        <div class="delete-warning">
            ⚠️ IMPORTANT: DELETE THIS FILE NOW!<br>
            Run: <code>rm sln-server-diagnostic.php</code> or delete via FTP
        </div>

        <p style="text-align: center; color: #666; margin-top: 30px;">
            Generated: <?php echo date('Y-m-d H:i:s'); ?><br>
            Salon Booking System - Server Diagnostic Tool v1.0
        </p>
    </div>
</body>
</html>
