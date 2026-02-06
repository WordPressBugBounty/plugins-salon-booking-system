<?php
/**
 * Rate Limiter Helper
 * 
 * Provides rate limiting protection to prevent bot spam attacks
 * and brute force attempts on booking submissions.
 * 
 * @package SalonBookingSystem
 * @subpackage Helper
 * @since 10.30.12
 */

class SLN_Helper_RateLimiter
{
    /**
     * Maximum booking attempts allowed within time window
     */
    const MAX_ATTEMPTS = 5;
    
    /**
     * Time window in seconds (5 minutes)
     */
    const TIME_WINDOW = 300;
    
    /**
     * Strict mode: Lower limits for suspicious IPs
     */
    const STRICT_MAX_ATTEMPTS = 3;
    const STRICT_TIME_WINDOW = 600; // 10 minutes
    
    /**
     * Check if rate limit has been exceeded
     * 
     * @param string $identifier Optional identifier (defaults to IP address)
     * @param bool $strict Use strict mode (lower limits)
     * @return bool True if within rate limit, false if exceeded
     */
    public static function checkRateLimit($identifier = null, $strict = false)
    {
        // Skip rate limiting for logged-in admins
        if (is_user_logged_in() && current_user_can('manage_salon_settings')) {
            return true;
        }
        
        if (!$identifier) {
            $identifier = self::getClientIP();
        }
        
        // Sanitize identifier for use in transient key
        $identifier_hash = md5($identifier);
        $transient_key = 'sln_rate_limit_' . $identifier_hash;
        
        // Get current attempt count
        $attempts = get_transient($transient_key);
        if ($attempts === false) {
            $attempts = 0;
        }
        
        // Determine limits based on mode
        $max_attempts = $strict ? self::STRICT_MAX_ATTEMPTS : self::MAX_ATTEMPTS;
        $time_window = $strict ? self::STRICT_TIME_WINDOW : self::TIME_WINDOW;
        
        // Check if limit exceeded
        if ($attempts >= $max_attempts) {
            SLN_Plugin::addLog('[Rate Limit] Blocked: ' . $identifier . ' (attempts: ' . $attempts . '/' . $max_attempts . ')');
            error_log('[Salon Rate Limit] Blocked IP: ' . $identifier . ' - Too many booking attempts');
            
            // Extend the block time on repeated violations
            if ($attempts >= $max_attempts * 2) {
                set_transient($transient_key, $attempts + 1, $time_window * 2);
            }
            
            return false;
        }
        
        // Increment attempt counter
        set_transient($transient_key, $attempts + 1, $time_window);
        
        SLN_Plugin::addLog('[Rate Limit] Allowed: ' . $identifier . ' (attempts: ' . ($attempts + 1) . '/' . $max_attempts . ')');
        
        return true;
    }
    
    /**
     * Reset rate limit for an identifier
     * Useful after successful booking or admin override
     * 
     * @param string $identifier Optional identifier (defaults to IP address)
     */
    public static function resetRateLimit($identifier = null)
    {
        if (!$identifier) {
            $identifier = self::getClientIP();
        }
        
        $identifier_hash = md5($identifier);
        $transient_key = 'sln_rate_limit_' . $identifier_hash;
        
        delete_transient($transient_key);
        
        SLN_Plugin::addLog('[Rate Limit] Reset for: ' . $identifier);
    }
    
    /**
     * Get remaining attempts before rate limit
     * 
     * @param string $identifier Optional identifier (defaults to IP address)
     * @param bool $strict Use strict mode
     * @return int Number of remaining attempts
     */
    public static function getRemainingAttempts($identifier = null, $strict = false)
    {
        if (!$identifier) {
            $identifier = self::getClientIP();
        }
        
        $identifier_hash = md5($identifier);
        $transient_key = 'sln_rate_limit_' . $identifier_hash;
        
        $attempts = get_transient($transient_key);
        if ($attempts === false) {
            $attempts = 0;
        }
        
        $max_attempts = $strict ? self::STRICT_MAX_ATTEMPTS : self::MAX_ATTEMPTS;
        
        return max(0, $max_attempts - $attempts);
    }
    
    /**
     * Get the client's IP address
     * Handles proxies, load balancers, and CDNs
     * 
     * @return string Client IP address
     */
    public static function getClientIP()
    {
        $ip = '';
        
        // Check for CloudFlare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // Check for client IP header
        elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for forwarded IP (may contain multiple IPs)
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // May contain multiple IPs, take the first one (original client)
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        // Check for real IP header (used by some proxies)
        elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }
        // Fallback to remote address
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Sanitize and validate IP
        $ip = sanitize_text_field($ip);
        
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = 'unknown';
        }
        
        return $ip;
    }
    
    /**
     * Check if rate limiting is enabled
     * 
     * @return bool
     */
    public static function isEnabled()
    {
        $settings = SLN_Plugin::getInstance()->getSettings();
        return (bool) $settings->get('rate_limit_enabled', true); // Enabled by default
    }
}

