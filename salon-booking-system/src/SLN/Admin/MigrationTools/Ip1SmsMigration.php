<?php

/**
 * IP1SMS API Migration Tool
 * 
 * Handles migration from deprecated IP1SMS APIs to API V2
 * Displays admin notices and provides migration assistance
 * 
 * @since 10.30.5
 */
class SLN_Admin_MigrationTools_Ip1SmsMigration
{
    private $plugin;
    
    /**
     * Deadline when old API will stop working
     */
    const DEPRECATION_DEADLINE = '2025-10-29';
    
    /**
     * Deprecated provider keys
     */
    const DEPRECATED_PROVIDERS = array('ip1smswebservice', 'ip1smshttp');
    
    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }
    
    /**
     * Check if user is using deprecated IP1SMS API
     * 
     * @return bool True if migration is needed
     */
    public function needsMigration()
    {
        $provider = $this->plugin->getSettings()->get('sms_provider');
        return in_array($provider, self::DEPRECATED_PROVIDERS);
    }
    
    /**
     * Get days remaining until deprecation deadline
     * 
     * @return int Days remaining (negative if past deadline)
     */
    public function getDaysUntilDeadline()
    {
        $deadline = strtotime(self::DEPRECATION_DEADLINE);
        $now = current_time('timestamp');
        $diff = $deadline - $now;
        
        return (int) floor($diff / DAY_IN_SECONDS);
    }
    
    /**
     * Check if deadline has passed
     * 
     * @return bool True if past deadline
     */
    public function isPastDeadline()
    {
        return $this->getDaysUntilDeadline() < 0;
    }
    
    /**
     * Get urgency level based on days remaining
     * 
     * @return string 'critical', 'warning', or 'notice'
     */
    public function getUrgencyLevel()
    {
        $days = $this->getDaysUntilDeadline();
        
        if ($this->isPastDeadline()) {
            return 'critical';
        } elseif ($days <= 30) {
            return 'critical';
        } elseif ($days <= 90) {
            return 'warning';
        } else {
            return 'notice';
        }
    }
    
    /**
     * Display migration notice in admin
     * 
     * Shows urgent notice if using deprecated IP1SMS API
     */
    public function showMigrationNotice()
    {
        // Only show to users who can manage settings
        if (!current_user_can('manage_salon_settings') && !current_user_can('manage_options')) {
            return;
        }
        
        // Check if migration is needed
        if (!$this->needsMigration()) {
            return;
        }
        
        // Check if user dismissed the notice (but show again if critical)
        $dismissed = get_option('sln_ip1sms_migration_notice_dismissed', false);
        $urgency = $this->getUrgencyLevel();
        
        if ($dismissed && $urgency !== 'critical') {
            return;
        }
        
        $days_remaining = $this->getDaysUntilDeadline();
        $is_past_deadline = $this->isPastDeadline();
        
        // Determine notice class based on urgency
        $notice_class = 'notice-error'; // Default to error for high visibility
        if ($urgency === 'critical') {
            $notice_class = 'notice-error';
        } elseif ($urgency === 'warning') {
            $notice_class = 'notice-warning';
        }
        
        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?> sln-ip1sms-migration-notice" style="position: relative; padding-right: 38px;">
            <?php if (!$is_past_deadline && $urgency !== 'critical'): ?>
            <button type="button" class="notice-dismiss" onclick="slnDismissIp1SmsMigrationNotice()">
                <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice', 'salon-booking-system'); ?></span>
            </button>
            <?php endif; ?>
            
            <h3 style="margin-top: 0.5em;">
                <?php if ($is_past_deadline): ?>
                    ⛔ <?php esc_html_e('URGENT: IP1SMS API Has Been Deprecated - SMS Notifications Not Working!', 'salon-booking-system'); ?>
                <?php elseif ($days_remaining <= 7): ?>
                    🚨 <?php esc_html_e('URGENT: IP1SMS API Migration Required - Only', 'salon-booking-system'); ?> <?php echo esc_html($days_remaining); ?> <?php esc_html_e('Days Left!', 'salon-booking-system'); ?>
                <?php elseif ($days_remaining <= 30): ?>
                    ⚠️ <?php esc_html_e('IP1SMS API Migration Required -', 'salon-booking-system'); ?> <?php echo esc_html($days_remaining); ?> <?php esc_html_e('Days Remaining', 'salon-booking-system'); ?>
                <?php else: ?>
                    📢 <?php esc_html_e('IP1SMS API Migration Required', 'salon-booking-system'); ?>
                <?php endif; ?>
            </h3>
            
            <?php if ($is_past_deadline): ?>
                <p>
                    <strong style="color: #d63638;">
                        <?php esc_html_e('Your SMS notifications stopped working because IP1SMS has shut down the old API. You need to migrate immediately!', 'salon-booking-system'); ?>
                    </strong>
                </p>
            <?php else: ?>
                <p>
                    <?php
                    printf(
                        esc_html__('IP1SMS is shutting down the old API on %s. Your SMS notifications will stop working unless you migrate to the new API V2.', 'salon-booking-system'),
                        '<strong>' . date_i18n(get_option('date_format'), strtotime(self::DEPRECATION_DEADLINE)) . '</strong>'
                    );
                    ?>
                </p>
            <?php endif; ?>
            
            <h4><?php esc_html_e('Quick Migration Steps:', 'salon-booking-system'); ?></h4>
            <ol style="margin-left: 2em;">
                <li>
                    <?php esc_html_e('Go to', 'salon-booking-system'); ?>
                    <strong><?php esc_html_e('Settings > SMS Services', 'salon-booking-system'); ?></strong>
                </li>
                <li>
                    <?php esc_html_e('Select', 'salon-booking-system'); ?>
                    <strong>"IP1SMS (API V2)"</strong>
                    <?php esc_html_e('as your SMS provider', 'salon-booking-system'); ?>
                </li>
                <li>
                    <?php esc_html_e('Log in to', 'salon-booking-system'); ?>
                    <a href="https://portal.ip1.net" target="_blank" rel="noopener"><strong>portal.ip1.net</strong></a>
                    <?php esc_html_e('and generate a new API key', 'salon-booking-system'); ?>
                    <br>
                    <small style="color: #646970;">
                        (<?php esc_html_e('Navigate to: Konton > API-nycklar > Lägg till nyckel', 'salon-booking-system'); ?>)
                    </small>
                </li>
                <li>
                    <?php esc_html_e('Paste the API key in your SMS settings', 'salon-booking-system'); ?>
                </li>
                <li>
                    <?php esc_html_e('Ensure your sender ID (phone number) is registered in the portal', 'salon-booking-system'); ?>
                </li>
                <li>
                    <?php esc_html_e('Save settings and send a test SMS', 'salon-booking-system'); ?>
                </li>
            </ol>
            
            <p style="margin-top: 1em;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=salon-settings#sln-sms_services')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-settings" style="margin-top: 3px;"></span>
                    <?php esc_html_e('Update SMS Settings Now', 'salon-booking-system'); ?>
                </a>
                <a href="https://ip1sms.com/en/developer/" target="_blank" rel="noopener" class="button">
                    <span class="dashicons dashicons-book" style="margin-top: 3px;"></span>
                    <?php esc_html_e('View Migration Guide', 'salon-booking-system'); ?>
                </a>
                <a href="https://ip1sms.com/en/customer-support/hur-skapar-jag-en-ny-api-nyckel-for-en-egen-eller-tredjeparts-integration/" target="_blank" rel="noopener" class="button">
                    <span class="dashicons dashicons-info" style="margin-top: 3px;"></span>
                    <?php esc_html_e('API Key Setup Guide', 'salon-booking-system'); ?>
                </a>
            </p>
            
            <?php if ($is_past_deadline): ?>
                <p style="background: #fff; padding: 10px; border-left: 4px solid #d63638; margin-top: 1em;">
                    <strong><?php esc_html_e('Important:', 'salon-booking-system'); ?></strong>
                    <?php esc_html_e('The old API is no longer functioning. Your customers are not receiving SMS notifications for bookings, reminders, or updates. Please migrate immediately to restore service.', 'salon-booking-system'); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <?php if (!$is_past_deadline && $urgency !== 'critical'): ?>
        <script>
        function slnDismissIp1SmsMigrationNotice() {
            jQuery('.sln-ip1sms-migration-notice').fadeOut();
            jQuery.post(ajaxurl, {
                action: 'sln_dismiss_ip1sms_migration_notice',
                security: '<?php echo wp_create_nonce('sln_dismiss_ip1sms_migration_notice'); ?>'
            });
        }
        </script>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Handle AJAX request to dismiss the migration notice
     */
    public function handleDismissNotice()
    {
        check_ajax_referer('sln_dismiss_ip1sms_migration_notice', 'security');
        
        if (!current_user_can('manage_salon_settings') && !current_user_can('manage_options')) {
            wp_die();
        }
        
        // Dismiss for 7 days
        update_option('sln_ip1sms_migration_notice_dismissed', time() + (7 * DAY_IN_SECONDS));
        
        wp_send_json_success();
    }
    
    /**
     * Reset dismissed notice (should be called daily via cron)
     * This ensures the notice reappears as deadline approaches
     */
    public function checkDismissedNoticeExpiry()
    {
        $dismissed_until = get_option('sln_ip1sms_migration_notice_dismissed', 0);
        
        if ($dismissed_until && $dismissed_until < current_time('timestamp')) {
            delete_option('sln_ip1sms_migration_notice_dismissed');
        }
    }
    
    /**
     * Get current provider name for display
     * 
     * @return string Provider display name
     */
    public function getCurrentProviderName()
    {
        $provider = $this->plugin->getSettings()->get('sms_provider');
        
        switch ($provider) {
            case 'ip1smswebservice':
                return 'IP1SMS (SOAP - Deprecated)';
            case 'ip1smshttp':
                return 'IP1SMS (HTTP - Deprecated)';
            default:
                return $provider;
        }
    }
}

