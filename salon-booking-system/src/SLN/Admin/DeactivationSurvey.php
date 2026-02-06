<?php

/**
 * Deactivation Survey - Collects feedback when users deactivate the plugin
 * Integrates with sbs-download-tracker to send survey data
 */

class SLN_Admin_DeactivationSurvey
{
    private $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        
        // Only load on plugins page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handler for survey submission
        add_action('wp_ajax_sln_deactivation_survey', array($this, 'handle_survey_submission'));
        
        // Add modal HTML to plugins page
        add_action('admin_footer', array($this, 'render_survey_modal'));
    }

    /**
     * Enqueue scripts and styles only on plugins page
     */
    public function enqueue_scripts($hook)
    {
        // Only load on plugins.php page
        if ($hook !== 'plugins.php') {
            return;
        }

        // Enqueue script
        wp_enqueue_script(
            'sln-deactivation-survey',
            SLN_PLUGIN_URL . '/js/admin/deactivation-survey.js',
            array('jquery'),
            SLN_VERSION,
            true
        );

        // Pass data to JavaScript
        wp_localize_script('sln-deactivation-survey', 'slnDeactivationSurvey', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sln_deactivation_survey'),
            'plugin_slug' => SLN_PLUGIN_BASENAME,
            'days_active' => $this->get_days_since_activation(),
            'setup_progress' => $this->calculate_setup_progress(),
            'completed_first_booking' => $this->has_completed_first_booking()
        ));

        // Inline CSS for modal
        wp_add_inline_style('common', $this->get_modal_css());
    }

    /**
     * Calculate days since plugin activation
     */
    private function get_days_since_activation()
    {
        $activation_time = get_option('sln_activation_time');
        
        if (!$activation_time) {
            // Set it now if not set
            $activation_time = current_time('timestamp');
            update_option('sln_activation_time', $activation_time);
        }
        
        $days = floor((current_time('timestamp') - $activation_time) / DAY_IN_SECONDS);
        return max(0, $days);
    }

    /**
     * Calculate setup progress percentage
     */
    private function calculate_setup_progress()
    {
        $progress = 0;
        $total_steps = 6;
        $completed = 0;

        // 1. Has services (beyond samples)?
        $services = get_posts(array(
            'post_type' => SLN_Plugin::POST_TYPE_SERVICE,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        if (!empty($services)) {
            $completed++;
        }

        // 2. Has availability rules?
        $availability = $this->plugin->getSettings()->get('availabilities');
        if (!empty($availability)) {
            $completed++;
        }

        // 3. Has business info configured?
        $salon_name = $this->plugin->getSettings()->get('gen_name');
        $salon_email = $this->plugin->getSettings()->get('gen_email');
        if (!empty($salon_name) || !empty($salon_email)) {
            $completed++;
        }

        // 4. Has staff/attendants (if enabled)?
        $attendants_enabled = $this->plugin->getSettings()->get('attendant_enabled');
        if (!$attendants_enabled) {
            $completed++; // Not required, count as completed
        } else {
            $attendants = get_posts(array(
                'post_type' => SLN_Plugin::POST_TYPE_ATTENDANT,
                'posts_per_page' => 1,
                'post_status' => 'publish'
            ));
            if (!empty($attendants)) {
                $completed++;
            }
        }

        // 5. Has configured payment method?
        $payment_methods = $this->plugin->getSettings()->get('pay_enabled');
        if (!empty($payment_methods)) {
            $completed++;
        }

        // 6. Has booking page configured?
        $booking_page = $this->plugin->getSettings()->get('booking');
        if (!empty($booking_page)) {
            $completed++;
        }

        $progress = round(($completed / $total_steps) * 100);
        return $progress;
    }

    /**
     * Check if user has completed at least one real booking (not sample)
     */
    private function has_completed_first_booking()
    {
        // Check for any non-sample booking
        $bookings = get_posts(array(
            'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
            'posts_per_page' => 1,
            'post_status' => array('publish', 'sln-b-confirmed', 'sln-b-paid'),
            'meta_query' => array(
                array(
                    'key' => '_sln_booking_status',
                    'value' => 'confirmed',
                    'compare' => 'EXISTS'
                )
            )
        ));

        return !empty($bookings);
    }

    /**
     * Handle AJAX survey submission
     */
    public function handle_survey_submission()
    {
        // Verify nonce
        check_ajax_referer('sln_deactivation_survey', 'nonce');

        // Get survey data
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        $feedback = isset($_POST['feedback']) ? sanitize_textarea_field($_POST['feedback']) : '';
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

        // Fallback for empty reason
        if (empty($reason)) {
            $reason = 'other';
        }

        // Store survey data temporarily (will be sent on deactivation hook)
        set_transient('sln_deactivation_survey_data', array(
            'reason' => $reason,
            'feedback' => $feedback,
            'rating' => $rating,
            'days_active' => $this->get_days_since_activation(),
            'setup_progress' => $this->calculate_setup_progress(),
            'completed_first_booking' => $this->has_completed_first_booking(),
            'timestamp' => current_time('mysql')
        ), HOUR_IN_SECONDS); // Keep for 1 hour

        wp_send_json_success(array(
            'message' => 'Thank you for your feedback!'
        ));
    }

    /**
     * Render the survey modal HTML
     */
    public function render_survey_modal($hook = '')
    {
        // Only show on plugins page
        $screen = get_current_screen();
        
        if (!$screen || $screen->id !== 'plugins') {
            return;
        }

        include SLN_PLUGIN_DIR . '/views/admin/deactivation-survey-modal.php';
    }

    /**
     * Get modal CSS
     */
    private function get_modal_css()
    {
        return "
        .sln-deactivation-survey-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 999999;
            animation: fadeIn 0.2s;
        }
        
        .sln-deactivation-survey-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            z-index: 9999999;
            animation: slideIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translate(-50%, -45%); opacity: 0; }
            to { transform: translate(-50%, -50%); opacity: 1; }
        }
        
        .sln-survey-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .sln-survey-header h2 {
            margin: 0;
            font-size: 22px;
            color: #1d2327;
        }
        
        .sln-survey-header p {
            margin: 8px 0 0;
            color: #646970;
            font-size: 14px;
        }
        
        .sln-survey-body {
            padding: 25px 30px;
        }
        
        .sln-survey-question {
            margin-bottom: 25px;
        }
        
        .sln-survey-label {
            display: block;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1d2327;
            font-size: 14px;
        }
        
        .sln-survey-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .sln-survey-option {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            border: 2px solid #dcdcde;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .sln-survey-option:hover {
            border-color: #2271b1;
            background: #f6f7f7;
        }
        
        .sln-survey-option input[type='radio'] {
            margin: 3px 10px 0 0;
            cursor: pointer;
        }
        
        .sln-survey-option label {
            cursor: pointer;
            flex: 1;
            margin: 0;
            font-size: 14px;
        }
        
        .sln-survey-option.selected {
            border-color: #2271b1;
            background: #f0f6fc;
        }
        
        .sln-survey-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            font-size: 14px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
            resize: vertical;
            min-height: 80px;
        }
        
        .sln-survey-textarea:focus {
            border-color: #2271b1;
            outline: none;
            box-shadow: 0 0 0 1px #2271b1;
        }
        
        .sln-survey-footer {
            padding: 20px 30px;
            background: #f6f7f7;
            border-top: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 0 8px 8px;
        }
        
        .sln-survey-skip {
            color: #646970;
            text-decoration: none;
            font-size: 14px;
        }
        
        .sln-survey-skip:hover {
            color: #2271b1;
        }
        
        .sln-survey-submit {
            background: #2271b1;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .sln-survey-submit:hover {
            background: #135e96;
        }
        
        .sln-survey-submit:disabled {
            background: #dcdcde;
            cursor: not-allowed;
        }
        
        .sln-survey-loading {
            display: none;
            text-align: center;
            padding: 30px;
        }
        
        .sln-survey-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2271b1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        ";
    }

    /**
     * Send survey data to tracking API
     * This is called from the modified deactivation hook in salon.php
     */
    public static function send_survey_to_tracker()
    {
        // Get stored survey data
        $survey_data = get_transient('sln_deactivation_survey_data');
        
        if (!$survey_data) {
            // No survey data - user skipped or survey not shown
            $survey_data = array(
                'reason' => 'skipped',
                'feedback' => '',
                'rating' => 0,
                'days_active' => self::get_days_active_static(),
                'setup_progress' => 0,
                'completed_first_booking' => false
            );
        }

        // Send to tracker API with survey data
        try {
            wp_remote_post('https://www.salonbookingsystem.com/wp-json/sbs-tracker/v1/deactivation', array(
                'blocking' => false,
                'timeout' => 2,
                'sslverify' => true,
                'body' => array(
                    'version' => defined('SLN_VERSION_PAY') && SLN_VERSION_PAY ? 'pro' : 'free',
                    'plugin_version' => SLN_VERSION,
                    'site_hash' => hash('sha256', home_url()),
                    
                    // Survey data
                    'deactivation_reason' => $survey_data['reason'],
                    'deactivation_feedback' => isset($survey_data['feedback']) ? $survey_data['feedback'] : '',
                    'deactivation_rating' => isset($survey_data['rating']) ? intval($survey_data['rating']) : 0,
                    'days_active' => intval($survey_data['days_active']),
                    'setup_progress' => intval($survey_data['setup_progress']),
                    'completed_first_booking' => (bool) $survey_data['completed_first_booking']
                )
            ));
        } catch (Exception $e) {
            // Fail silently - don't break deactivation
        }

        // Clean up transient
        delete_transient('sln_deactivation_survey_data');
    }

    /**
     * Static version of get_days_active for deactivation hook
     */
    private static function get_days_active_static()
    {
        $activation_time = get_option('sln_activation_time');
        if (!$activation_time) {
            return 0;
        }
        return floor((current_time('timestamp') - $activation_time) / DAY_IN_SECONDS);
    }
}
