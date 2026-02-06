<?php
/**
 * Google reCAPTCHA v3 Verification Helper
 * 
 * Provides bot protection for booking submissions by verifying
 * reCAPTCHA tokens from Google's API.
 * 
 * @package SalonBookingSystem
 * @subpackage Helper
 * @since 9.0.0
 */

class SLN_Helper_RecaptchaVerifier
{
    /**
     * Minimum score threshold for reCAPTCHA v3
     * Score ranges from 0.0 (bot) to 1.0 (human)
     * 0.5 is recommended by Google as a good default
     */
    const SCORE_THRESHOLD = 0.5;

    /**
     * Google reCAPTCHA API endpoint
     */
    const API_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Verify a reCAPTCHA token
     * 
     * @param string $token The reCAPTCHA token from the client
     * @param string $action Optional action name to verify (default: 'booking_submit')
     * @return bool True if verification passes, false otherwise
     */
    public static function verify($token, $action = 'booking_submit')
    {
        $settings = SLN_Plugin::getInstance()->getSettings();
        $siteKey = $settings->get('recaptcha_site_key');
        $secret = $settings->get('recaptcha_secret_key');
        
        // If reCAPTCHA is not enabled (checked by isEnabled()), allow booking
        // This method should only be called when reCAPTCHA is explicitly enabled
        if (empty($secret)) {
            SLN_Plugin::addLog('[reCAPTCHA] Secret key not configured - allowing booking');
            return true; // Allow for backward compatibility
        }
        
        // Check if using Google's test keys (always pass for development)
        // Test site key: 6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
        // Test secret key: 6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
        if ($secret === '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe' || 
            $siteKey === '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI') {
            SLN_Plugin::addLog('[reCAPTCHA] Using Google test keys, verification passed automatically');
            error_log('[Salon reCAPTCHA] Using Google test keys, verification passed automatically');
            return true;
        }
        
        // Token is required if reCAPTCHA is enabled with real keys
        if (empty($token)) {
            SLN_Plugin::addLog('[reCAPTCHA] Token missing - frontend may have failed to generate token');
            error_log('[Salon reCAPTCHA] Token missing - frontend may have failed to generate token');
            
            // Check if we should fail open (allow booking if reCAPTCHA fails)
            // SECURITY: Default to FALSE to prevent bot bypass attacks
            // Bots were exploiting fail-open=true by simply omitting the token parameter
            $failOpen = (bool) $settings->get('recaptcha_fail_open', false); // Default to false for security
            if ($failOpen) {
                SLN_Plugin::addLog('[reCAPTCHA] Fail-open enabled, allowing booking to proceed');
                error_log('[Salon reCAPTCHA] Fail-open enabled, allowing booking to proceed');
                return true;
            }
            
            SLN_Plugin::addLog('[reCAPTCHA] Token missing and fail-open disabled, blocking booking');
            error_log('[Salon reCAPTCHA] Token missing and fail-open disabled, blocking booking');
            return false;
        }
        
        // Make request to Google's verification API
        $response = wp_remote_post(self::API_ENDPOINT, array(
            'body' => array(
                'secret' => $secret,
                'response' => $token,
                'remoteip' => self::getClientIP()
            ),
            'timeout' => 10
        ));
        
        // Handle request errors
        if (is_wp_error($response)) {
            SLN_Plugin::addLog('[reCAPTCHA] API request failed: ' . $response->get_error_message());
            // Fail open: allow booking if API is down (configurable)
            return $settings->get('recaptcha_fail_open') ? true : false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Log the response for debugging
        SLN_Plugin::addLog('[reCAPTCHA] API response: ' . wp_json_encode($body));
        
        // Check if verification was successful
        if (!isset($body['success']) || !$body['success']) {
            $error_codes = isset($body['error-codes']) ? implode(', ', $body['error-codes']) : 'unknown';
            SLN_Plugin::addLog('[reCAPTCHA] Verification failed: ' . $error_codes);
            return false;
        }
        
        // Verify the action matches (optional but recommended)
        if (isset($body['action']) && $body['action'] !== $action) {
            SLN_Plugin::addLog('[reCAPTCHA] Action mismatch: expected "' . $action . '", got "' . $body['action'] . '"');
            return false;
        }
        
        // Check the score threshold
        $score = isset($body['score']) ? floatval($body['score']) : 0.0;
        $threshold = floatval($settings->get('recaptcha_score_threshold')) ?: self::SCORE_THRESHOLD;
        
        if ($score < $threshold) {
            SLN_Plugin::addLog('[reCAPTCHA] Score too low: ' . $score . ' (threshold: ' . $threshold . ')');
            return false;
        }
        
        SLN_Plugin::addLog('[reCAPTCHA] Verification passed with score: ' . $score);
        return true;
    }
    
    /**
     * Check if reCAPTCHA is enabled
     * 
     * @return bool
     */
    public static function isEnabled()
    {
        $settings = SLN_Plugin::getInstance()->getSettings();
        
        // Check if user has explicitly enabled reCAPTCHA (disabled by default)
        $explicitly_enabled = (bool) $settings->get('recaptcha_enabled', false);
        
        if (!$explicitly_enabled) {
            return false; // User hasn't enabled reCAPTCHA
        }
        
        // Only check keys if user has explicitly enabled reCAPTCHA
        $site_key = $settings->get('recaptcha_site_key');
        $secret_key = $settings->get('recaptcha_secret_key');
        
        return !empty($site_key) && !empty($secret_key);
    }
    
    /**
     * Get the client's IP address
     * Handles proxies and load balancers
     * 
     * @return string
     */
    private static function getClientIP()
    {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // May contain multiple IPs, take the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Verify token with custom error handling
     * Returns array with success status and error message
     * 
     * @param string $token The reCAPTCHA token
     * @param string $action Optional action name
     * @return array ['success' => bool, 'message' => string, 'score' => float]
     */
    public static function verifyWithDetails($token, $action = 'booking_submit')
    {
        $settings = SLN_Plugin::getInstance()->getSettings();
        $secret = $settings->get('recaptcha_secret_key');
        
        if (empty($secret)) {
            return array(
                'success' => true,
                'message' => 'reCAPTCHA not configured',
                'score' => null
            );
        }
        
        if (empty($token)) {
            return array(
                'success' => false,
                'message' => __('Bot verification token missing. Please refresh and try again.', 'salon-booking-system'),
                'score' => 0.0
            );
        }
        
        $response = wp_remote_post(self::API_ENDPOINT, array(
            'body' => array(
                'secret' => $secret,
                'response' => $token,
                'remoteip' => self::getClientIP()
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => $settings->get('recaptcha_fail_open') ? true : false,
                'message' => __('Bot verification service temporarily unavailable.', 'salon-booking-system'),
                'score' => null
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['success']) || !$body['success']) {
            return array(
                'success' => false,
                'message' => __('Bot verification failed. Please try again.', 'salon-booking-system'),
                'score' => 0.0
            );
        }
        
        $score = isset($body['score']) ? floatval($body['score']) : 0.0;
        $threshold = floatval($settings->get('recaptcha_score_threshold')) ?: self::SCORE_THRESHOLD;
        
        if ($score < $threshold) {
            return array(
                'success' => false,
                'message' => __('Suspicious activity detected. Please contact us if you need assistance.', 'salon-booking-system'),
                'score' => $score
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Verification passed',
            'score' => $score
        );
    }
}

