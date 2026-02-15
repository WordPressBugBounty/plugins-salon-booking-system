<?php
/**
 * Enhanced Error Notification System
 * 
 * Sends intelligent error reports to support with:
 * - Deduplication to prevent spam
 * - Frequency tracking for severity assessment
 * - Known issues database with instant solutions
 * - Severity classification for prioritization
 * - Comprehensive system context
 * 
 * @version 2.0
 * @since 10.30.0
 */
class SLN_Helper_ErrorNotification
{
    const SUPPORT_EMAIL = 'support@salonbookingsystem.com';
    const RATE_LIMIT_OPTION = '_sln_error_notification_count';
    const RATE_LIMIT_RESET_OPTION = '_sln_error_notification_reset';
    const MAX_EMAILS_PER_HOUR = 3;
    const MAX_EMAILS_PER_DAY = 10;
    
    // Transient keys for error tracking
    const TRANSIENT_ERROR_COUNTS = 'sln_error_counts';
    const TRANSIENT_SENT_SIGNATURES = 'sln_sent_error_signatures';
    const TRANSIENT_RECENT_ERRORS = 'sln_recent_errors';
    
    // Time constants
    const DEDUP_WINDOW = 3600; // 1 hour - don't send same error within this window
    const ERROR_COUNT_TTL = 86400; // 24 hours - track error counts for 1 day
    const RECENT_ERRORS_TTL = 3600; // 1 hour - keep recent errors list
    
    // Version checking
    const VERSION_TOLERANCE = 1; // Allow notifications from 1 minor version behind latest

    /**
     * Send error notification to support email with enhanced intelligence
     * 
     * @param string $error_type Type of error (e.g., 'AJAX_EXCEPTION', 'FATAL_ERROR')
     * @param string $error_message The error message
     * @param string $context Additional context (stack trace, etc.)
     * @return bool True if email was sent, false otherwise
     */
    public static function send($error_type, $error_message, $context = '')
    {
        try {
            // Generate unique signature for this error
            $signature = self::getErrorSignature($error_type, $error_message);
            
            // Track this error occurrence
            $error_info = self::trackErrorOccurrence($signature, $error_type, $error_message);
            
            // Check if we should send based on deduplication
            if (!self::shouldSendNotification($signature)) {
                SLN_Plugin::addLog("ERROR notification skipped - already sent recently (signature: {$signature})");
                return false;
            }
            
            // Collect all error data
            $data = self::collectErrorData($error_type, $error_message, $context, $error_info);
            
            // Build and send email
            $sent = self::sendEmail($data);
            
            if ($sent) {
                // Mark this error as sent
                self::markErrorAsSent($signature);
                self::incrementNotificationCount();
                
                SLN_Plugin::addLog("ERROR notification sent to " . self::SUPPORT_EMAIL . " (signature: {$signature})");
                return true;
            }
            
            return false;

        } catch (Exception $e) {
            // Fail silently - we don't want error notifications to break the application
            SLN_Plugin::addLog("ERROR notification exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique signature for error deduplication
     * 
     * @param string $error_type
     * @param string $error_message
     * @return string MD5 hash signature
     */
    private static function getErrorSignature($error_type, $error_message)
    {
        // Create signature from error type, message, and PHP version
        // This ensures same error on different PHP versions are tracked separately
        $signature_string = $error_type . '|' . $error_message . '|' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        return md5($signature_string);
    }
    
    /**
     * Track error occurrence and return statistics
     * 
     * @param string $signature
     * @param string $error_type
     * @param string $error_message
     * @return array Error statistics
     */
    private static function trackErrorOccurrence($signature, $error_type, $error_message)
    {
        $counts = get_transient(self::TRANSIENT_ERROR_COUNTS);
        if (!$counts) {
            $counts = array();
        }
        
        if (!isset($counts[$signature])) {
            $counts[$signature] = array(
                'type' => $error_type,
                'message' => substr($error_message, 0, 200), // Store truncated message
                'count' => 0,
                'first_seen' => time(),
                'last_seen' => time(),
                'affected_ips' => array()
            );
        }
        
        $counts[$signature]['count']++;
        $counts[$signature]['last_seen'] = time();
        
        // Track unique IPs (limit to 50 to prevent memory issues)
        $user_ip = self::getUserIP();
        if (!in_array($user_ip, $counts[$signature]['affected_ips']) && count($counts[$signature]['affected_ips']) < 50) {
            $counts[$signature]['affected_ips'][] = $user_ip;
        }
        
        set_transient(self::TRANSIENT_ERROR_COUNTS, $counts, self::ERROR_COUNT_TTL);
        
        // Also track in recent errors list
        self::addToRecentErrors($error_type, $error_message);
        
        return $counts[$signature];
    }
    
    /**
     * Check if notification should be sent based on deduplication and rate limits
     * 
     * @param string $signature
     * @return bool
     */
    private static function shouldSendNotification($signature)
    {
        // Get error info to check severity and known issues
        $error_counts = get_transient(self::TRANSIENT_ERROR_COUNTS);
        $is_critical = false;
        $error_type = '';
        $error_message = '';
        
        if ($error_counts && isset($error_counts[$signature])) {
            $error_type = $error_counts[$signature]['type'];
            $error_message = $error_counts[$signature]['message'];
            $severity = self::getSeverity($error_type, $error_message);
            $is_critical = ($severity === 'CRITICAL');
        }
        
        // Check if this is a known issue already fixed in a newer version
        if (!$is_critical && $error_type && $error_message) {
            $known_issue = self::getKnownIssue($error_type, $error_message);
            if ($known_issue) {
                $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
                
                // If current version is older than fix version, don't send
                if (version_compare($current_version, $known_issue['fix_version'], '<')) {
                    SLN_Plugin::addLog(sprintf(
                        "[ErrorNotification] Skipping known issue '%s' fixed in %s (current: %s)",
                        $known_issue['issue_id'],
                        $known_issue['fix_version'],
                        $current_version
                    ));
                    return false;
                }
            }
        }
        
        // Check version for non-critical errors only
        // CRITICAL errors always sent (too important to miss)
        if (!$is_critical && !self::isRunningLatestVersion(self::VERSION_TOLERANCE)) {
            return false; // Skip non-critical errors from outdated versions
        }
        
        // Check if this exact error was sent recently
        $sent_errors = get_transient(self::TRANSIENT_SENT_SIGNATURES);
        if (!$sent_errors) {
            $sent_errors = array();
        }
        
        if (isset($sent_errors[$signature])) {
            $time_since_sent = time() - $sent_errors[$signature];
            if ($time_since_sent < self::DEDUP_WINDOW) {
                return false; // Already sent within deduplication window
            }
        }
        
        // Check rate limits
        return self::checkRateLimits();
    }
    
    /**
     * Check rate limiting (existing logic)
     * 
     * @return bool
     */
    private static function checkRateLimits()
    {
        $count = get_option(self::RATE_LIMIT_OPTION, 0);
        $reset_time = get_option(self::RATE_LIMIT_RESET_OPTION, 0);
        $current_time = time();

        // Reset counter if more than 1 hour has passed
        if ($current_time > $reset_time) {
            delete_option(self::RATE_LIMIT_OPTION);
            delete_option(self::RATE_LIMIT_RESET_OPTION);
            $count = 0;
        }

        // Check hourly limit
        if ($count >= self::MAX_EMAILS_PER_HOUR) {
            return false;
        }

        // Check daily limit
        $daily_count = get_option(self::RATE_LIMIT_OPTION . '_daily', 0);
        $daily_reset = get_option(self::RATE_LIMIT_RESET_OPTION . '_daily', 0);
        
        if ($current_time > $daily_reset) {
            delete_option(self::RATE_LIMIT_OPTION . '_daily');
            delete_option(self::RATE_LIMIT_RESET_OPTION . '_daily');
            $daily_count = 0;
        }

        if ($daily_count >= self::MAX_EMAILS_PER_DAY) {
            return false;
        }

        return true;
    }
    
    /**
     * Mark error as sent to prevent duplicates
     * 
     * @param string $signature
     */
    private static function markErrorAsSent($signature)
    {
        $sent_errors = get_transient(self::TRANSIENT_SENT_SIGNATURES);
        if (!$sent_errors) {
            $sent_errors = array();
        }
        
        $sent_errors[$signature] = time();
        
        // Clean up old entries (keep only last 100)
        if (count($sent_errors) > 100) {
            asort($sent_errors);
            $sent_errors = array_slice($sent_errors, -100, 100, true);
        }
        
        set_transient(self::TRANSIENT_SENT_SIGNATURES, $sent_errors, self::ERROR_COUNT_TTL);
    }
    
    /**
     * Add error to recent errors list for pattern recognition
     * 
     * @param string $error_type
     * @param string $error_message
     */
    private static function addToRecentErrors($error_type, $error_message)
    {
        $recent = get_transient(self::TRANSIENT_RECENT_ERRORS);
        if (!$recent) {
            $recent = array();
        }
        
        $recent[] = array(
            'type' => $error_type,
            'message' => substr($error_message, 0, 100),
            'time' => time()
        );
        
        // Keep only last 20 errors
        if (count($recent) > 20) {
            $recent = array_slice($recent, -20);
        }
        
        set_transient(self::TRANSIENT_RECENT_ERRORS, $recent, self::RECENT_ERRORS_TTL);
    }
    
    /**
     * Collect all error data for email
     * 
     * @param string $error_type
     * @param string $error_message
     * @param string $context
     * @param array $error_info
     * @return array
     */
    private static function collectErrorData($error_type, $error_message, $context, $error_info)
    {
        $plugin_version = defined('SLN_VERSION') ? SLN_VERSION : 'unknown';
        
        return array(
            'error_type' => $error_type,
            'error_message' => $error_message,
            'context' => $context,
            'error_info' => $error_info,
            'severity' => self::getSeverity($error_type, $error_message),
            'known_issue' => self::getKnownIssue($error_type, $error_message),
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo('name'),
            'admin_email' => get_option('admin_email'),
            'plugin_version' => $plugin_version,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'current_time' => current_time('mysql'),
            'active_plugins' => self::getActiveSLNPlugins(),
            'theme_info' => self::getThemeInfo(),
            'server_info' => self::getServerInfo(),
            'recent_errors' => self::getRecentErrors(),
            'user_ip' => self::getUserIP(),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A',
            'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'N/A',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A',
        );
    }
    
    /**
     * Determine error severity based on patterns
     * 
     * @param string $error_type
     * @param string $error_message
     * @return string CRITICAL, HIGH, MEDIUM, or LOW
     */
    private static function getSeverity($error_type, $error_message)
    {
        // CRITICAL: Breaks core functionality completely
        $critical_patterns = array(
            'Cannot access offset',
            'Call to .* member function .* on null',
            'Call to .* member function .* on bool',
            'wp_insert_post failed',
            'Unable to create booking',
            'Fatal error',
            'Allowed memory size .* exhausted'
        );
        
        // HIGH: Significant impact on user experience
        $high_patterns = array(
            'bad date format',
            'payment .* failed',
            'Booking data not found',
            'Session expired',
            'Payment .* not managed'
        );
        
        // MEDIUM: Inconvenience but recoverable
        $medium_patterns = array(
            'cache .* failed',
            'email .* failed',
            'notification .* failed',
            'view .* not found',
            'template .* not found',
            'MISSING_EMAIL_TEMPLATE'
        );
        
        $message_lower = strtolower($error_message);
        
        foreach ($critical_patterns as $pattern) {
            if (preg_match('/' . str_replace(' ', '\s+', strtolower($pattern)) . '/i', $message_lower)) {
                return 'CRITICAL';
            }
        }
        
        foreach ($high_patterns as $pattern) {
            if (preg_match('/' . str_replace(' ', '\s+', strtolower($pattern)) . '/i', $message_lower)) {
                return 'HIGH';
            }
        }
        
        foreach ($medium_patterns as $pattern) {
            if (preg_match('/' . str_replace(' ', '\s+', strtolower($pattern)) . '/i', $message_lower)) {
                return 'MEDIUM';
            }
        }
        
        return 'LOW';
    }
    
    /**
     * Check if this is a known issue with available fix
     * 
     * @param string $error_type
     * @param string $error_message
     * @return array|null Known issue info or null
     */
    private static function getKnownIssue($error_type, $error_message)
    {
        $known_issues = array(
            'Cannot access offset of type string on string' => array(
                'issue_id' => 'PHP83-001',
                'title' => 'PHP 8+ Array Access Compatibility',
                'fix_version' => '10.30.0',
                'solution' => 'Update to version 10.30.0 or higher. This error occurs due to stricter array access in PHP 8+. The fix ensures all cache methods return proper array structures.',
                'affected_versions' => '< 10.30.0',
                'severity' => 'CRITICAL',
                'files_fixed' => 'AbstractCache.php, Cache.php, CheckDateAlt.php'
            ),
            'bad date format' => array(
                'issue_id' => 'DATE-001',
                'title' => 'Empty Date Validation',
                'fix_version' => '10.30.0',
                'solution' => 'Update to version 10.30.0 or higher. Added 4-layer validation for empty date values in AJAX handlers to prevent this error.',
                'affected_versions' => '< 10.30.0',
                'severity' => 'HIGH',
                'files_fixed' => 'CheckServices.php, CheckDate.php, CheckAttendants.php, TimeFunc.php, Func.php'
            ),
            'payment method mode not managed' => array(
                'issue_id' => 'STRIPE-001',
                'title' => 'Stripe Payment Callback Parameters',
                'fix_version' => '10.30.0',
                'solution' => 'Update to version 10.30.0 for better error messages. Also check: 1) .htaccess has QSA flag, 2) Security plugins not blocking query parameters, 3) Stripe callback URLs are correct.',
                'affected_versions' => '< 10.30.0',
                'severity' => 'HIGH',
                'files_fixed' => 'Stripe.php'
            ),
            'Call to a member function getUserId() on null' => array(
                'issue_id' => 'BOOKING-001',
                'title' => 'Null Booking Reference in Extensions',
                'fix_version' => '10.30.0',
                'solution' => 'Update to version 10.30.0. Fixed by using getLastBookingOrFail() to ensure valid booking object is always passed to extension hooks.',
                'affected_versions' => '< 10.30.0',
                'severity' => 'CRITICAL',
                'files_fixed' => 'Builder.php'
            ),
            'view "mail/summary" not found' => array(
                'issue_id' => 'TEMPLATE-001',
                'title' => 'Missing Email Template File',
                'fix_version' => '10.30.4',
                'solution' => 'This indicates incomplete plugin installation. SOLUTIONS: 1) Reinstall the plugin completely via WordPress admin. 2) Via FTP, re-upload the /views/mail/ directory from a fresh plugin download. 3) Check file permissions (files: 644, directories: 755). 4) Verify no security plugin is blocking template files. As of version 10.30.4, the system uses fallback email templates automatically, so bookings will not fail - but customers receive basic emails instead of branded ones.',
                'affected_versions' => 'All versions (graceful handling added in 10.30.4)',
                'severity' => 'MEDIUM',
                'files_fixed' => 'Plugin.php (added fallback email generation)'
            )
        );
        
        $message_lower = strtolower($error_message);
        
        foreach ($known_issues as $pattern => $issue) {
            if (stripos($message_lower, strtolower($pattern)) !== false) {
                return $issue;
            }
        }
        
        return null;
    }
    
    /**
     * Get list of active SLN-related plugins
     * 
     * @return array
     */
    private static function getActiveSLNPlugins()
    {
        $active_plugins = get_option('active_plugins', array());
        $sln_plugins = array();
        
        foreach ($active_plugins as $plugin) {
            if (stripos($plugin, 'salon') !== false || 
                stripos($plugin, 'sln') !== false ||
                stripos($plugin, 'slb') !== false) {
                
                $plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
                if (file_exists($plugin_file)) {
                    $plugin_data = get_plugin_data($plugin_file, false, false);
                    $sln_plugins[] = array(
                        'name' => $plugin_data['Name'],
                        'version' => $plugin_data['Version'],
                        'file' => $plugin
                    );
                }
            }
        }
        
        return $sln_plugins;
    }
    
    /**
     * Get active theme information
     * 
     * @return array
     */
    private static function getThemeInfo()
    {
        $theme = wp_get_theme();
        $info = array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author')
        );
        
        $parent = $theme->parent();
        if ($parent) {
            $info['parent_name'] = $parent->get('Name');
            $info['parent_version'] = $parent->get('Version');
        }
        
        return $info;
    }
    
    /**
     * Get server resource information
     * 
     * @return array
     */
    private static function getServerInfo()
    {
        return array(
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'max_input_vars' => ini_get('max_input_vars'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
        );
    }
    
    /**
     * Get recent errors for pattern recognition
     * 
     * @return array
     */
    private static function getRecentErrors()
    {
        $recent = get_transient(self::TRANSIENT_RECENT_ERRORS);
        return $recent ? $recent : array();
    }
    
    /**
     * Build and send email with all collected data
     * 
     * @param array $data
     * @return bool
     */
    private static function sendEmail($data)
    {
        // Build subject with severity
        $subject = sprintf(
            '[SLN %s] %s - %s',
            $data['severity'],
            $data['site_name'],
            $data['error_type']
        );
        
        // Build email body
        $body = self::buildEmailBody($data);
        
        // Set headers
        $headers = array(
            'From: ' . $data['admin_email'],
            'Reply-To: ' . $data['admin_email'],
            'Content-Type: text/plain; charset=UTF-8'
        );
        
        // Send email
        $sent = wp_mail(
            self::SUPPORT_EMAIL,
            $subject,
            $body,
            $headers
        );
        
        if (!$sent) {
            SLN_Plugin::addLog("ERROR notification failed to send (wp_mail returned false)");
        }
        
        return $sent;
    }
    
    /**
     * Build comprehensive email body
     * 
     * @param array $data
     * @return string
     */
    private static function buildEmailBody($data)
    {
        $body = "A critical error occurred on a Salon Booking System installation.\n\n";
        
        // Quick Summary
        $body .= "=== QUICK SUMMARY ===\n";
        $body .= "Severity: {$data['severity']}\n";
        $body .= "Occurrences: {$data['error_info']['count']} time(s)\n";
        $body .= "Unique IPs Affected: " . count($data['error_info']['affected_ips']) . "\n";
        $body .= "First Seen: " . date('Y-m-d H:i:s', $data['error_info']['first_seen']) . "\n";
        $body .= "Last Seen: " . date('Y-m-d H:i:s', $data['error_info']['last_seen']) . "\n";
        
        // Calculate occurrence rate
        $time_span = $data['error_info']['last_seen'] - $data['error_info']['first_seen'];
        if ($time_span > 0) {
            $rate = round($data['error_info']['count'] / ($time_span / 60), 2);
            $body .= "Rate: {$rate} per minute\n";
        }
        $body .= "\n";
        
        // Known Issue Section
        if ($data['known_issue']) {
            $issue = $data['known_issue'];
            $body .= "=== ⚠️ KNOWN ISSUE ===\n";
            $body .= "Issue ID: {$issue['issue_id']}\n";
            $body .= "Title: {$issue['title']}\n";
            $body .= "Fixed In: {$issue['fix_version']}\n";
            $body .= "Current Version: {$data['plugin_version']}\n";
            
            // Version comparison
            if (version_compare($data['plugin_version'], $issue['fix_version'], '<')) {
                $body .= "Status: ⚠️ OUTDATED - Update available\n";
                $body .= "ACTION REQUIRED: Update to version {$issue['fix_version']} or higher\n";
            } else {
                $body .= "Status: ✓ Should be fixed (investigate if error persists)\n";
            }
            
            $body .= "\nSolution:\n{$issue['solution']}\n";
            $body .= "\nAffected Versions: {$issue['affected_versions']}\n";
            $body .= "Files Fixed: {$issue['files_fixed']}\n";
            $body .= "\n";
        }
        
        // Website Information
        $body .= "=== WEBSITE INFORMATION ===\n";
        $body .= "Website Name: {$data['site_name']}\n";
        $body .= "Website URL: {$data['site_url']}\n";
        $body .= "Admin Email: {$data['admin_email']}\n";
        $body .= "Time: {$data['current_time']}\n\n";
        
        // System Information
        $body .= "=== SYSTEM INFORMATION ===\n";
        $body .= "Plugin Version: {$data['plugin_version']}\n";
        $body .= "WordPress Version: {$data['wp_version']}\n";
        $body .= "PHP Version: {$data['php_version']}\n";
        $body .= "Theme: {$data['theme_info']['name']} ({$data['theme_info']['version']})\n";
        if (isset($data['theme_info']['parent_name'])) {
            $body .= "Parent Theme: {$data['theme_info']['parent_name']} ({$data['theme_info']['parent_version']})\n";
        }
        $body .= "\n";
        
        // Active SLN Plugins
        $body .= "=== ACTIVE SLN PLUGINS ===\n";
        if (!empty($data['active_plugins'])) {
            foreach ($data['active_plugins'] as $plugin) {
                $body .= "- {$plugin['name']} ({$plugin['version']})\n";
            }
        } else {
            $body .= "No SLN-related plugins detected\n";
        }
        $body .= "\n";
        
        // Server Resources
        $body .= "=== SERVER RESOURCES ===\n";
        foreach ($data['server_info'] as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            $body .= "{$label}: {$value}\n";
        }
        $body .= "\n";
        
        // Error Details
        $body .= "=== ERROR DETAILS ===\n";
        $body .= "Error Type: {$data['error_type']}\n";
        $body .= "Error Message: {$data['error_message']}\n\n";
        
        // Additional Context (Stack Trace)
        if (!empty($data['context'])) {
            $body .= "=== STACK TRACE ===\n";
            $body .= $data['context'] . "\n\n";
        }
        
        // Recent Errors Pattern
        if (!empty($data['recent_errors']) && count($data['recent_errors']) > 1) {
            $body .= "=== RECENT ERRORS (Last Hour) ===\n";
            $recent_count = min(10, count($data['recent_errors']));
            for ($i = count($data['recent_errors']) - $recent_count; $i < count($data['recent_errors']); $i++) {
                $err = $data['recent_errors'][$i];
                $time_ago = time() - $err['time'];
                $body .= sprintf(
                    "[%d min ago] %s: %s\n",
                    round($time_ago / 60),
                    $err['type'],
                    $err['message']
                );
            }
            $body .= "\n";
        }
        
        // Request Information
        $body .= "=== REQUEST INFORMATION ===\n";
        $body .= "Request URI: {$data['request_uri']}\n";
        $body .= "Request Method: {$data['request_method']}\n";
        $body .= "User Agent: {$data['user_agent']}\n";
        $body .= "User IP: {$data['user_ip']}\n\n";
        
        $body .= "---\n";
        $body .= "This is an automated error notification from Salon Booking System.\n";
        $body .= "Error Signature: " . self::getErrorSignature($data['error_type'], $data['error_message']) . "\n";
        
        return $body;
    }

    /**
     * Increment notification counter
     */
    private static function incrementNotificationCount()
    {
        $count = get_option(self::RATE_LIMIT_OPTION, 0);
        $reset_time = get_option(self::RATE_LIMIT_RESET_OPTION, 0);
        $current_time = time();

        // Set reset time if not set (1 hour from now)
        if ($reset_time == 0 || $current_time > $reset_time) {
            $reset_time = $current_time + HOUR_IN_SECONDS;
            update_option(self::RATE_LIMIT_RESET_OPTION, $reset_time);
        }

        // Increment hourly counter
        update_option(self::RATE_LIMIT_OPTION, $count + 1);

        // Increment daily counter
        $daily_count = get_option(self::RATE_LIMIT_OPTION . '_daily', 0);
        $daily_reset = get_option(self::RATE_LIMIT_RESET_OPTION . '_daily', 0);

        if ($daily_reset == 0 || $current_time > $daily_reset) {
            $daily_reset = $current_time + DAY_IN_SECONDS;
            update_option(self::RATE_LIMIT_RESET_OPTION . '_daily', $daily_reset);
        }

        update_option(self::RATE_LIMIT_OPTION . '_daily', $daily_count + 1);
    }

    /**
     * Get user IP address
     * 
     * @return string
     */
    private static function getUserIP()
    {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return $ip;
    }

    /**
     * Check if site is running the latest version (or close to it)
     * 
     * @param int $tolerance Number of minor versions behind latest that's acceptable
     * @return bool True if current version is latest or within tolerance
     */
    private static function isRunningLatestVersion($tolerance = 1)
    {
        $current_version = defined('SLN_VERSION') ? SLN_VERSION : '0.0.0';
        
        // Get latest version from WordPress update transient
        $update_cache = get_site_transient('update_plugins');
        
        if (!is_object($update_cache)) {
            // No update data available - allow notification (benefit of doubt)
            SLN_Plugin::addLog("[ErrorNotification] No update data available, allowing notification");
            return true;
        }
        
        $plugin_basename = defined('SLN_PLUGIN_BASENAME') ? SLN_PLUGIN_BASENAME : 'salon-booking-system/salon.php';
        $latest_version = null;
        
        // Check if update is available
        if (isset($update_cache->response[$plugin_basename]->new_version)) {
            $latest_version = $update_cache->response[$plugin_basename]->new_version;
        } 
        // Check if plugin is up to date
        elseif (isset($update_cache->no_update[$plugin_basename]->new_version)) {
            $latest_version = $update_cache->no_update[$plugin_basename]->new_version;
        }
        // Fallback: Check last_checked version
        elseif (isset($update_cache->checked[$plugin_basename])) {
            $latest_version = $update_cache->checked[$plugin_basename];
        }
        
        if (!$latest_version) {
            // Can't determine latest version - allow notification (benefit of doubt)
            SLN_Plugin::addLog("[ErrorNotification] Cannot determine latest version, allowing notification");
            return true;
        }
        
        // Compare versions
        $version_diff = version_compare($current_version, $latest_version);
        
        if ($version_diff >= 0) {
            // Running latest or newer (dev version)
            return true;
        }
        
        // Check if within tolerance
        // Example: tolerance=1 means allow notifications from 10.30.14 if latest is 10.30.15
        if ($tolerance > 0) {
            $current_parts = explode('.', $current_version);
            $latest_parts = explode('.', $latest_version);
            
            // Only check minor version difference (10.30.X)
            if (count($current_parts) >= 3 && count($latest_parts) >= 3) {
                if ($current_parts[0] == $latest_parts[0] && 
                    $current_parts[1] == $latest_parts[1]) {
                    $minor_diff = intval($latest_parts[2]) - intval($current_parts[2]);
                    if ($minor_diff <= $tolerance) {
                        return true;
                    }
                }
            }
        }
        
        // Running outdated version
        SLN_Plugin::addLog(sprintf(
            "[ErrorNotification] Skipping - outdated version (current: %s, latest: %s, tolerance: %d)",
            $current_version,
            $latest_version,
            $tolerance
        ));
        
        return false;
    }

    /**
     * Get current notification statistics
     * Useful for debugging rate limiting
     * 
     * @return array
     */
    public static function getStats()
    {
        return array(
            'hourly_count' => get_option(self::RATE_LIMIT_OPTION, 0),
            'hourly_reset' => get_option(self::RATE_LIMIT_RESET_OPTION, 0),
            'daily_count' => get_option(self::RATE_LIMIT_OPTION . '_daily', 0),
            'daily_reset' => get_option(self::RATE_LIMIT_RESET_OPTION . '_daily', 0),
            'max_per_hour' => self::MAX_EMAILS_PER_HOUR,
            'max_per_day' => self::MAX_EMAILS_PER_DAY,
        );
    }
}

