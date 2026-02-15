<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.MissingUnslash

class SLN_Action_Ajax_SalonStep extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        $requestedStep = null;
        $mode = null;
        if (isset($_POST['sln_step_page'])) {
            $requestedStep = sanitize_text_field(wp_unslash($_POST['sln_step_page']));
            $_GET['sln_step_page'] = $requestedStep;
        } elseif (isset($_GET['sln_step_page'])) {
            $requestedStep = sanitize_text_field(wp_unslash($_GET['sln_step_page']));
            $_GET['sln_step_page'] = $requestedStep;
        }
        
        // Log every AJAX request for debugging
        SLN_Plugin::addLog('[reCAPTCHA DEBUG] Step: ' . ($requestedStep ? $requestedStep : 'null') . ', isEnabled: ' . (class_exists('SLN_Helper_RecaptchaVerifier') && SLN_Helper_RecaptchaVerifier::isEnabled() ? 'yes' : 'no'));
        
        // Bot protection: Rate limiting check BEFORE reCAPTCHA (prevents brute force)
        if ($requestedStep === 'summary' && class_exists('SLN_Helper_RateLimiter') && SLN_Helper_RateLimiter::isEnabled()) {
            if (!SLN_Helper_RateLimiter::checkRateLimit()) {
                SLN_Plugin::addLog('[Rate Limit] AJAX booking submission blocked - too many attempts');
                
                return array(
                    'error' => __('Too many booking attempts. Please try again in a few minutes.', 'salon-booking-system')
                );
            }
        }
        
        // Bot protection: Verify reCAPTCHA token for summary step (final booking submission)
        // Only verify on summary step to avoid blocking navigation between steps
        if ($requestedStep === 'summary' && class_exists('SLN_Helper_RecaptchaVerifier') && SLN_Helper_RecaptchaVerifier::isEnabled()) {
            $token = isset($_POST['recaptcha_token']) ? sanitize_text_field(wp_unslash($_POST['recaptcha_token'])) : '';
            
            SLN_Plugin::addLog('[reCAPTCHA] Checking summary step - Token present: ' . (!empty($token) ? 'yes' : 'no') . ', Token length: ' . strlen($token));
            
            $verificationResult = SLN_Helper_RecaptchaVerifier::verify($token, 'booking_submit');
            
            if (!$verificationResult) {
                SLN_Plugin::addLog('[reCAPTCHA] AJAX booking submission blocked - verification failed');
                
                // Don't block the booking, just log it
                // The verify() method already handles fail-open logic
                SLN_Plugin::addLog('[reCAPTCHA] WARNING: Verification failed but booking may proceed based on fail-open setting');
            } else {
                SLN_Plugin::addLog('[reCAPTCHA] AJAX booking submission verified successfully');
            }
        }
        if (isset($_POST['mode'])) {
            $mode = sanitize_text_field($_POST['mode']);
            $_GET['mode'] = $mode;
        } elseif (isset($_GET['mode'])) {
            $mode = sanitize_text_field($_GET['mode']);
            $_GET['mode'] = $mode;
        }

        if (isset($_POST['pay_remaining_amount'])) {
            $_GET['pay_remaining_amount'] = sanitize_text_field($_POST['pay_remaining_amount']);
        }

        if (isset($_POST['sln_client_id'])) {
            $_GET['sln_client_id'] = sanitize_text_field(wp_unslash($_POST['sln_client_id']));
        }

        SLN_Plugin::addLog(sprintf('[Wizard] Ajax SalonStep request step="%s" mode="%s"', $requestedStep ? $requestedStep : '(default)', $mode ? $mode : '(none)'));

        try {
            $ret = do_shortcode('[' . SLN_Shortcode_Salon::NAME . '][/' . SLN_Shortcode_Salon::NAME . ']');
            
            // CRITICAL FIX: Include client_id in response for Safari/Edge compatibility
            // After customer login, WordPress creates a new session which changes PHPSESSID
            // We need to pass the client_id back to JavaScript so subsequent AJAX requests
            // can access booking data via transients even after session changes
            // This fixes the "blank page with 0" issue that occurs after login
            $bb = SLN_Plugin::getInstance()->getBookingBuilder();
            $clientId = $bb->getClientId();
            
            $ret = array(
                'content' => $ret,
                'nonce' => wp_create_nonce('ajax_post_validation')
            );
            
            // Always include client_id if available (critical for cross-session persistence)
            if ($clientId) {
                $ret['client_id'] = $clientId;
            }
            
            // Include debug information if debug mode is enabled
            if ((isset($_GET['sln_debug']) && $_GET['sln_debug'] === '1') || 
                (isset($_POST['sln_debug']) && $_POST['sln_debug'] === '1')) {
                $ret['debug'] = $this->getDebugInfo($bb, $clientId);
            }
            
        } catch (SLN_Action_Ajax_RedirectException $ex) {
            SLN_Plugin::addLog(sprintf('[Wizard] Ajax SalonStep redirect to "%s"', $ex->getMessage()));
            $ret = array(
                'redirect' => $ex->getMessage()
            );
            
            // Include client_id in redirect response too (maintain consistency)
            $bb = SLN_Plugin::getInstance()->getBookingBuilder();
            $clientId = $bb->getClientId();
            if ($clientId) {
                $ret['client_id'] = $clientId;
            }
        }
        return $ret;
    }
    
    /**
     * Get debug information for troubleshooting
     * Only called when sln_debug=1 is in URL
     */
    private function getDebugInfo($bb, $clientId)
    {
        global $wpdb;
        
        // Check if BookingBuilder was reset (by checking object ID)
        static $lastBookingBuilderId = null;
        $currentBuilderId = spl_object_id($bb);
        $wasReset = ($lastBookingBuilderId !== null && $lastBookingBuilderId !== $currentBuilderId);
        $lastBookingBuilderId = $currentBuilderId;
        
        // Get transient data if exists
        $transientData = null;
        if ($clientId) {
            $transientKey = 'sln_booking_builder_' . $clientId;
            $transientData = get_transient($transientKey);
        }
        
        // Get session ID
        $sessionId = session_id();
        if (empty($sessionId)) {
            @session_start();
            $sessionId = session_id();
        }
        
        return array(
            'timestamp' => current_time('mysql'),
            'step' => isset($_GET['sln_step_page']) ? $_GET['sln_step_page'] : 'default',
            'client_id' => $clientId,
            'storage_mode' => $bb->isUsingTransient() ? 'transient' : 'session',
            'session_id' => $sessionId,
            'booking_builder_object_id' => $currentBuilderId,
            'booking_builder_was_reset' => $wasReset,
            'client_id_sources' => array(
                'from_GET' => isset($_GET['sln_client_id']) ? $_GET['sln_client_id'] : null,
                'from_POST' => isset($_POST['sln_client_id']) ? $_POST['sln_client_id'] : null,
                'from_SESSION' => isset($_SESSION['sln_client_id']) ? $_SESSION['sln_client_id'] : null,
            ),
            'transient_exists' => $transientData !== false,
            'transient_data_count' => is_array($transientData) ? count($transientData) : 0,
            'user_logged_in' => is_user_logged_in(),
            'user_id' => get_current_user_id(),
            'booking_data_exists' => !empty($bb->getData()),
            'booking_data_count' => count($bb->getData()),
        );
    }
}
