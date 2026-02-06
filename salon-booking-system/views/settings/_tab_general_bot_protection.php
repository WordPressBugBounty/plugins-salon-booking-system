<?php
/**
 * Bot Protection Settings
 * 
 * @var $helper SLN_Admin_Settings
 */
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
?>
<div class="sln-box--sub row">
    <div class="col-xs-12">
        <h2 class="sln-box-title"><?php esc_html_e('Bot Protection (reCAPTCHA v3)', 'salon-booking-system')?></h2>
        
        <!-- PROMINENT ENABLE/DISABLE TOGGLE -->
        <div class="sln-checkbox" style="margin: 20px 0; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
            <?php $helper->row_input_checkbox(
                'recaptcha_enabled',
                __('Enable reCAPTCHA Protection', 'salon-booking-system'),
                array('default' => '0')
            );?>
            <p class="sln-input-help" style="margin-top: 10px; margin-bottom: 0;">
                <?php esc_html_e('Check this box to activate bot protection. You must also configure the Site Key and Secret Key below. Disabled by default.', 'salon-booking-system')?>
            </p>
        </div>
        
        <p class="sln-box-info" style="margin-bottom: 20px;">
            <?php esc_html_e('Protect your booking system from spam and fake bookings using Google reCAPTCHA v3. This is an invisible verification that works in the background without interrupting your customers.', 'salon-booking-system')?>
            <br>
            <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">
                <?php esc_html_e('Get your free reCAPTCHA keys here', 'salon-booking-system')?>
            </a>
        </p>
    </div>
    
    <div class="col-xs-12 col-sm-6">
        <div class="row">
            <div class="col-xs-12 sln-input--simple">
                <?php $helper->row_input_text(
                    'recaptcha_site_key',
                    __('reCAPTCHA Site Key', 'salon-booking-system')
                );?>
                <p class="sln-input-help">
                    <?php esc_html_e('The site key is used in your booking form (public key)', 'salon-booking-system')?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-xs-12 col-sm-6">
        <div class="row">
            <div class="col-xs-12 sln-input--simple">
                <?php $helper->row_input_text(
                    'recaptcha_secret_key',
                    __('reCAPTCHA Secret Key', 'salon-booking-system')
                );?>
                <p class="sln-input-help">
                    <?php esc_html_e('The secret key is used to verify submissions on your server (private key)', 'salon-booking-system')?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-xs-12">
        <div class="row">
            <div class="col-xs-12 col-sm-6 sln-input--simple">
                <?php $helper->row_input_text(
                    'recaptcha_score_threshold',
                    __('Score Threshold', 'salon-booking-system'),
                    array(
                        'default' => '0.5',
                        'attrs' => array(
                            'type' => 'number',
                            'step' => '0.1',
                            'min' => '0',
                            'max' => '1'
                        )
                    )
                );?>
                <p class="sln-input-help">
                    <?php esc_html_e('Minimum score required to accept a booking (0.0 = bot, 1.0 = human). Default: 0.5', 'salon-booking-system')?>
                </p>
            </div>
            
            <div class="col-xs-12 col-sm-6">
                <div class="sln-checkbox">
                    <?php $helper->row_input_checkbox(
                        'recaptcha_fail_open',
                        __('Allow bookings if reCAPTCHA service is unavailable', 'salon-booking-system')
                    );?>
                    <p class="sln-input-help">
                        <?php esc_html_e('If enabled, bookings will be allowed when Google\'s service is down. Recommended for high-availability sites.', 'salon-booking-system')?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xs-12">
        <div class="sln-box-maininfo sln-box-maininfo--info" style="margin-top: 20px;">
            <p><strong><?php esc_html_e('How it works:', 'salon-booking-system')?></strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php esc_html_e('reCAPTCHA v3 runs invisibly in the background - no checkboxes or puzzles for customers', 'salon-booking-system')?></li>
                <li><?php esc_html_e('Every booking submission is scored from 0.0 (bot) to 1.0 (human)', 'salon-booking-system')?></li>
                <li><?php esc_html_e('Submissions below your threshold are automatically blocked', 'salon-booking-system')?></li>
                <li><?php esc_html_e('Protects all booking entry points: forms, REST API, and AJAX requests', 'salon-booking-system')?></li>
            </ul>
        </div>
    </div>
    
    <?php 
    $is_enabled_checkbox = (bool) $helper->getOpt('recaptcha_enabled');
    $has_site_key = !empty($helper->getOpt('recaptcha_site_key'));
    $has_secret_key = !empty($helper->getOpt('recaptcha_secret_key'));
    $has_both_keys = $has_site_key && $has_secret_key;
    $is_fully_active = $is_enabled_checkbox && $has_both_keys;
    ?>
    
    <?php if ($is_fully_active): ?>
    <div class="col-xs-12">
        <div class="sln-box-maininfo sln-box-maininfo--success" style="margin-top: 15px;">
            <p>
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <strong><?php esc_html_e('Bot protection is ACTIVE', 'salon-booking-system')?></strong>
            </p>
            <p><?php esc_html_e('Your booking system is now protected against automated bot attacks. All booking submissions will be verified by reCAPTCHA.', 'salon-booking-system')?></p>
        </div>
    </div>
    <?php elseif ($is_enabled_checkbox && !$has_both_keys): ?>
    <div class="col-xs-12">
        <div class="sln-box-maininfo sln-box-maininfo--error" style="margin-top: 15px;">
            <p>
                <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                <strong><?php esc_html_e('reCAPTCHA is enabled but keys are missing', 'salon-booking-system')?></strong>
            </p>
            <p><?php esc_html_e('You have enabled reCAPTCHA protection, but both Site Key and Secret Key are required. Please enter your keys above or uncheck "Enable reCAPTCHA Protection".', 'salon-booking-system')?></p>
        </div>
    </div>
    <?php elseif (!$is_enabled_checkbox && $has_both_keys): ?>
    <div class="col-xs-12">
        <div class="sln-box-maininfo sln-box-maininfo--info" style="margin-top: 15px;">
            <p>
                <span class="dashicons dashicons-info" style="color: #72aee6;"></span>
                <strong><?php esc_html_e('Bot protection is DISABLED', 'salon-booking-system')?></strong>
            </p>
            <p><?php esc_html_e('Your reCAPTCHA keys are configured, but protection is disabled. Check the "Enable reCAPTCHA Protection" box above and save to activate bot protection.', 'salon-booking-system')?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="col-xs-12">
        <div class="sln-box-maininfo sln-box-maininfo--info" style="margin-top: 15px;">
            <p>
                <span class="dashicons dashicons-info" style="color: #72aee6;"></span>
                <strong><?php esc_html_e('Bot protection is DISABLED', 'salon-booking-system')?></strong>
            </p>
            <p><?php esc_html_e('To enable bot protection: 1) Check "Enable reCAPTCHA Protection" above, 2) Enter your Site Key and Secret Key, 3) Save settings.', 'salon-booking-system')?></p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Rate Limiting Section -->
<div class="sln-box--sub row" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
    <div class="col-xs-12">
        <h2 class="sln-box-title"><?php esc_html_e('Rate Limiting', 'salon-booking-system')?></h2>
        <p class="sln-box-info" style="margin-bottom: 20px;">
            <?php esc_html_e('Rate limiting prevents brute force attacks by limiting the number of booking attempts from the same IP address. This adds an extra layer of protection beyond reCAPTCHA.', 'salon-booking-system')?>
        </p>
    </div>
    
    <div class="col-xs-12 col-sm-6">
        <div class="sln-checkbox">
            <?php $helper->row_input_checkbox(
                'rate_limit_enabled',
                __('Enable rate limiting', 'salon-booking-system'),
                array('default' => '1')
            );?>
            <p class="sln-input-help">
                <?php esc_html_e('Limits booking attempts to 5 per 5 minutes per IP address. Recommended for all sites.', 'salon-booking-system')?>
            </p>
        </div>
    </div>
    
    <div class="col-xs-12">
        <div class="sln-box-maininfo sln-box-maininfo--info" style="margin-top: 15px;">
            <p><strong><?php esc_html_e('How it works:', 'salon-booking-system')?></strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php esc_html_e('Each IP address is limited to 5 booking attempts within 5 minutes', 'salon-booking-system')?></li>
                <li><?php esc_html_e('After 5 attempts, the IP is temporarily blocked for 5 minutes', 'salon-booking-system')?></li>
                <li><?php esc_html_e('Logged-in administrators are automatically exempt from rate limiting', 'salon-booking-system')?></li>
                <li><?php esc_html_e('Protects against bot attacks even if they bypass reCAPTCHA', 'salon-booking-system')?></li>
            </ul>
        </div>
    </div>
    
    <?php if (class_exists('SLN_Helper_RateLimiter') && SLN_Helper_RateLimiter::isEnabled()): ?>
    <div class="col-xs-12">
        <div class="sln-box-maininfo sln-box-maininfo--success" style="margin-top: 15px;">
            <p>
                <span class="dashicons dashicons-shield-alt" style="color: #46b450;"></span>
                <strong><?php esc_html_e('Rate limiting is active', 'salon-booking-system')?></strong>
            </p>
            <p><?php esc_html_e('Your booking system has an additional layer of protection against brute force attacks.', 'salon-booking-system')?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="col-xs-12">
        <div class="sln-box-maininfo sln-box-maininfo--warning" style="margin-top: 15px;">
            <p>
                <span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
                <strong><?php esc_html_e('Rate limiting is disabled', 'salon-booking-system')?></strong>
            </p>
            <p><?php esc_html_e('Your site is more vulnerable to brute force bot attacks. Enable rate limiting for better protection.', 'salon-booking-system')?></p>
        </div>
    </div>
    <?php endif; ?>
</div>

