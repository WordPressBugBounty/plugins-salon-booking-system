<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/** @var SLN_Plugin $plugin */
/** @var SLN_Settings $settings */
$usage_goals = array(
    'barbershop' => __('Barbershop', 'salon-booking-system'),
    'hairdresser' => __('Hairdresser', 'salon-booking-system'),
    'beauty_salon' => __('Beauty Salon', 'salon-booking-system'),
    'spa' => __('Spa', 'salon-booking-system'),
    'physiotherapy' => __('Physiotherapy', 'salon-booking-system'),
    'counselling' => __('Counselling', 'salon-booking-system'),
    'workshop' => __('Workshop', 'salon-booking-system'),
    'other' => __('Other', 'salon-booking-system'),
);
$step_labels = array(
    1 => __('General Settings', 'salon-booking-system'),
    2 => __('Availability', 'salon-booking-system'),
    3 => __('Assistants', 'salon-booking-system'),
    4 => __('Services', 'salon-booking-system'),
    5 => __('Payment', 'salon-booking-system'),
);
$current_goal = get_option('_sln_usage_goal', '');
$intervals = SLN_Enum_Interval::toArray();
$current_interval = $settings->get('interval') ? $settings->get('interval') : 15;
$availabilities = $settings->get('availabilities');
if (!is_array($availabilities) || empty($availabilities)) {
    $availabilities = array(array(
        'days' => array(1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1),
        'from' => array('09:00', '14:00'),
        'to' => array('13:00', '18:00'),
    ));
}
$days_labels = SLN_Func::getDays();
$first = isset($availabilities[0]) ? $availabilities[0] : array('days' => array(1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1), 'from' => array('09:00', '14:00'), 'to' => array('13:00', '18:00'));
$days = isset($first['days']) ? $first['days'] : array(1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1);
$assistants_url = admin_url('post-new.php?post_type=sln_attendant');
$services_url = admin_url('post-new.php?post_type=sln_service');
$assistants_list_url = admin_url('edit.php?post_type=sln_attendant');
$services_list_url = admin_url('edit.php?post_type=sln_service');
$payments_url = admin_url('admin.php?page=salon-settings&tab=payments');
$is_pro = defined('SLN_VERSION_PAY') && SLN_VERSION_PAY;
$assistants_count = (int) wp_count_posts('sln_attendant')->publish;
$services_count = (int) wp_count_posts('sln_service')->publish;
?>
<div class="wrap sln-onboarding-wrap">
<div class="sln-onboarding-wizard">
    <header class="sln-onboarding-wizard__header">
        <div class="sln-onboarding-wizard__logo">
            <span class="sln-onboarding-wizard__logo-icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span class="sln-onboarding-wizard__logo-check">✓</span>
            </span>
            <span class="sln-onboarding-wizard__logo-text">Salonbookingsystem</span>
        </div>
        <h1 class="sln-onboarding-wizard__title"><?php esc_html_e('Welcome to Salon Booking', 'salon-booking-system'); ?></h1>
        <p class="sln-onboarding-wizard__subtitle"><?php esc_html_e("Let's set up your booking system in just a few steps", 'salon-booking-system'); ?></p>
    </header>

    <nav class="sln-onboarding-wizard__stepper" role="progressbar" aria-valuenow="1" aria-valuemin="1" aria-valuemax="5">
        <div class="sln-onboarding-wizard__stepper-inner">
            <?php for ($i = 1; $i <= 5; $i++) : ?>
                <button type="button" class="sln-onboarding-wizard__step" data-step="<?php echo $i; ?>" aria-label="<?php echo esc_attr($step_labels[$i]); ?>">
                    <span class="sln-onboarding-wizard__step-num"><?php echo $i; ?></span>
                    <span class="sln-onboarding-wizard__step-label"><?php echo esc_html($step_labels[$i]); ?></span>
                </button>
                <?php if ($i < 5) : ?>
                    <div class="sln-onboarding-wizard__step-line" data-step="<?php echo $i; ?>"></div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </nav>

    <div class="sln-onboarding-wizard__content">
        <!-- Step 1: General Settings (Business Type only, per Figma) -->
        <div class="sln-onboarding-wizard__panel sln-onboarding-wizard__panel--active" data-step="1">
            <div class="sln-onboarding-wizard__card">
                <h2 class="sln-onboarding-wizard__card-title"><?php esc_html_e('General Settings', 'salon-booking-system'); ?></h2>
                <p class="sln-onboarding-wizard__card-desc"><?php esc_html_e('Tell us about your business to customize your booking experience', 'salon-booking-system'); ?></p>
                <div class="sln-onboarding-wizard__section">
                    <h3 class="sln-onboarding-wizard__section-title"><?php esc_html_e('Business Type', 'salon-booking-system'); ?></h3>
                    <p class="sln-onboarding-wizard__section-desc"><?php esc_html_e('Select the category that best describes your business', 'salon-booking-system'); ?></p>
                    <div class="sln-onboarding-wizard__goals">
                        <?php foreach ($usage_goals as $key => $label) : ?>
                            <label class="sln-onboarding-wizard__goal <?php echo $current_goal === $key ? 'sln-onboarding-wizard__goal--selected' : ''; ?>">
                                <input type="radio" name="sln_usage_goal" value="<?php echo esc_attr($key); ?>" <?php checked($current_goal, $key); ?>>
                                <span class="sln-onboarding-wizard__goal-icon">
                                    <?php
                                    $icons = array(
                                        'barbershop' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3v18M18 3v18M6 12h12M9 6l3 3-3 3M15 6l-3 3 3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                                        'hairdresser' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l1.5 4.5L18 8l-4.5 1.5L12 14l-1.5-4.5L6 8l4.5-1.5L12 2z" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
                                        'beauty_salon' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
                                        'spa' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C8 6 6 10 6 14c0 3.31 2.69 6 6 6s6-2.69 6-6c0-4-2-8-6-12z" stroke="currentColor" stroke-width="2"/><path d="M12 14v4M9 18h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                                        'physiotherapy' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M8 21v-6c0-2 2-4 4-4s4 2 4 4v6M12 11v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                                        'counselling' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                                        'workshop' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 21h18M3 10h18M5 10v11M19 10v11M3 10l9-7 9 7M12 3v7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                                        'other' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="6" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="18" cy="12" r="1.5" fill="currentColor"/></svg>',
                                    );
                                    echo isset($icons[$key]) ? $icons[$key] : $icons['other'];
                                    ?>
                                </span>
                                <span class="sln-onboarding-wizard__goal-label"><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" id="sln_onboarding_gen_name" name="gen_name" value="<?php echo esc_attr($settings->get('gen_name') ?: get_bloginfo('name')); ?>">
                <input type="hidden" name="attendant_enabled" value="<?php echo esc_attr($settings->get('attendant_enabled') ?: '1'); ?>">
                <input type="hidden" id="sln_onboarding_parallels_hour" name="parallels_hour" value="<?php echo esc_attr($settings->get('parallels_hour') ?: 1); ?>">
                <input type="hidden" id="sln_onboarding_interval" name="interval" value="<?php echo esc_attr($current_interval); ?>">
                <div class="sln-onboarding-wizard__card-actions">
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary sln-onboarding-wizard__btn-next" data-step="1"><?php esc_html_e('Continue', 'salon-booking-system'); ?></button>
                </div>
            </div>
        </div>

        <!-- Step 2: Availability -->
        <div class="sln-onboarding-wizard__panel" data-step="2">
            <div class="sln-onboarding-wizard__card">
                <h2 class="sln-onboarding-wizard__card-title"><?php esc_html_e('Availability Settings', 'salon-booking-system'); ?></h2>
                <p class="sln-onboarding-wizard__card-desc"><?php esc_html_e('Set your business hours and available days', 'salon-booking-system'); ?></p>
                <div class="sln-onboarding-wizard__section">
                    <h3 class="sln-onboarding-wizard__section-title"><?php esc_html_e('Which days are you available?', 'salon-booking-system'); ?></h3>
                    <div class="sln-onboarding-wizard__days">
                        <?php foreach ($days_labels as $k => $day) : $checked = !empty($days[$k]); ?>
                            <label class="sln-onboarding-wizard__day <?php echo $checked ? 'sln-onboarding-wizard__day--selected' : ''; ?>">
                                <input type="checkbox" name="avail_days[<?php echo $k; ?>]" value="1" <?php checked($checked); ?>>
                                <span class="sln-onboarding-wizard__day-abbr"><?php echo esc_html(substr($day, 0, 3)); ?></span>
                                <span class="sln-onboarding-wizard__day-full"><?php echo esc_html($day); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="sln-onboarding-wizard__shift">
                        <span class="sln-onboarding-wizard__shift-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                        <div class="sln-onboarding-wizard__shift-fields">
                            <label><?php esc_html_e('Morning Shift', 'salon-booking-system'); ?></label>
                            <input type="text" name="avail_from_0" value="<?php echo esc_attr(isset($first['from'][0]) ? $first['from'][0] : '09:00'); ?>" class="sln-onboarding-wizard__time" placeholder="09:00">
                            <span>–</span>
                            <input type="text" name="avail_to_0" value="<?php echo esc_attr(isset($first['to'][0]) ? $first['to'][0] : '13:00'); ?>" class="sln-onboarding-wizard__time" placeholder="13:00">
                        </div>
                    </div>
                    <div class="sln-onboarding-wizard__shift">
                        <span class="sln-onboarding-wizard__shift-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                        <div class="sln-onboarding-wizard__shift-fields">
                            <label><?php esc_html_e('Afternoon Shift', 'salon-booking-system'); ?></label>
                            <input type="text" name="avail_from_1" value="<?php echo esc_attr(isset($first['from'][1]) ? $first['from'][1] : '14:00'); ?>" class="sln-onboarding-wizard__time" placeholder="14:00">
                            <span>–</span>
                            <input type="text" name="avail_to_1" value="<?php echo esc_attr(isset($first['to'][1]) ? $first['to'][1] : '18:00'); ?>" class="sln-onboarding-wizard__time" placeholder="18:00">
                        </div>
                    </div>
                </div>
                <div class="sln-onboarding-wizard__card-actions">
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--secondary sln-onboarding-wizard__btn-back" data-step="2"><?php esc_html_e('Back', 'salon-booking-system'); ?></button>
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary sln-onboarding-wizard__btn-next" data-step="2"><?php esc_html_e('Continue', 'salon-booking-system'); ?></button>
                </div>
            </div>
        </div>

        <!-- Step 3: Assistants -->
        <div class="sln-onboarding-wizard__panel" data-step="3">
            <div class="sln-onboarding-wizard__card">
                <h2 class="sln-onboarding-wizard__card-title"><?php esc_html_e('Assistants', 'salon-booking-system'); ?></h2>
                <p class="sln-onboarding-wizard__card-desc"><?php esc_html_e('Add your team members so customers can choose who they want to book with', 'salon-booking-system'); ?></p>
                <div class="sln-onboarding-wizard__cta">
                    <?php if ($assistants_count > 0) : ?>
                        <p><?php echo esc_html(sprintf(__('You have %d assistant(s).', 'salon-booking-system'), $assistants_count)); ?></p>
                        <a href="<?php echo esc_url($assistants_list_url); ?>" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary" target="_blank" rel="noopener"><?php esc_html_e('View assistants', 'salon-booking-system'); ?></a>
                    <?php else : ?>
                        <p><?php esc_html_e('Add your first assistant to get started.', 'salon-booking-system'); ?></p>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($assistants_url); ?>" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary" target="_blank" rel="noopener"><?php esc_html_e('Add assistant', 'salon-booking-system'); ?></a>
                </div>
                <div class="sln-onboarding-wizard__card-actions">
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--secondary sln-onboarding-wizard__btn-back" data-step="3"><?php esc_html_e('Back', 'salon-booking-system'); ?></button>
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--secondary sln-onboarding-wizard__btn-skip" data-step="3"><?php esc_html_e('Skip', 'salon-booking-system'); ?></button>
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary sln-onboarding-wizard__btn-next" data-step="3"><?php esc_html_e('Continue', 'salon-booking-system'); ?></button>
                </div>
            </div>
        </div>

        <!-- Step 4: Services -->
        <div class="sln-onboarding-wizard__panel" data-step="4">
            <div class="sln-onboarding-wizard__card">
                <h2 class="sln-onboarding-wizard__card-title"><?php esc_html_e('Services', 'salon-booking-system'); ?></h2>
                <p class="sln-onboarding-wizard__card-desc"><?php esc_html_e('Add the services you offer so customers can book them', 'salon-booking-system'); ?></p>
                <div class="sln-onboarding-wizard__cta">
                    <?php if ($services_count > 0) : ?>
                        <p><?php echo esc_html(sprintf(__('You have %d service(s).', 'salon-booking-system'), $services_count)); ?></p>
                        <a href="<?php echo esc_url($services_list_url); ?>" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary" target="_blank" rel="noopener"><?php esc_html_e('View services', 'salon-booking-system'); ?></a>
                    <?php else : ?>
                        <p><?php esc_html_e('Add your first service to enable bookings.', 'salon-booking-system'); ?></p>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($services_url); ?>" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary" target="_blank" rel="noopener"><?php esc_html_e('Add service', 'salon-booking-system'); ?></a>
                </div>
                <div class="sln-onboarding-wizard__card-actions">
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--secondary sln-onboarding-wizard__btn-back" data-step="4"><?php esc_html_e('Back', 'salon-booking-system'); ?></button>
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--secondary sln-onboarding-wizard__btn-skip" data-step="4"><?php esc_html_e('Skip', 'salon-booking-system'); ?></button>
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary sln-onboarding-wizard__btn-next" data-step="4"><?php esc_html_e('Continue', 'salon-booking-system'); ?></button>
                </div>
            </div>
        </div>

        <!-- Step 5: Payment -->
        <div class="sln-onboarding-wizard__panel" data-step="5">
            <div class="sln-onboarding-wizard__card">
                <h2 class="sln-onboarding-wizard__card-title"><?php esc_html_e('Payment Settings', 'salon-booking-system'); ?></h2>
                <p class="sln-onboarding-wizard__card-desc"><?php esc_html_e('Configure how you want to receive payments from customers', 'salon-booking-system'); ?></p>
                <?php if ($is_pro) : ?>
                    <div class="sln-onboarding-wizard__cta">
                        <p><?php esc_html_e('Set up Stripe, PayPal, or other payment methods in Settings.', 'salon-booking-system'); ?></p>
                        <a href="<?php echo esc_url($payments_url); ?>" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary" target="_blank" rel="noopener"><?php esc_html_e('Open payment settings', 'salon-booking-system'); ?></a>
                    </div>
                <?php else : ?>
                    <div class="sln-onboarding-wizard__upgrade">
                        <h3><?php esc_html_e('Unlock Payments with Salon Booking PRO', 'salon-booking-system'); ?></h3>
                        <p><?php esc_html_e('Accept online payments and grow your business with our premium features', 'salon-booking-system'); ?></p>
                        <ul>
                            <li><?php esc_html_e('Accept payments via Stripe & PayPal', 'salon-booking-system'); ?></li>
                            <li><?php esc_html_e('Require deposits for bookings', 'salon-booking-system'); ?></li>
                            <li><?php esc_html_e('Set custom deposits per service', 'salon-booking-system'); ?></li>
                            <li><?php esc_html_e('Automatic payment reminders', 'salon-booking-system'); ?></li>
                            <li><?php esc_html_e('Full payment reporting & analytics', 'salon-booking-system'); ?></li>
                            <li><?php esc_html_e('Priority support', 'salon-booking-system'); ?></li>
                        </ul>
                        <a href="https://www.salonbookingsystem.com/?utm_source=onboarding&utm_medium=plugin&utm_campaign=upgrade_pro" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--upgrade" target="_blank" rel="noopener"><?php esc_html_e('Upgrade to PRO', 'salon-booking-system'); ?></a>
                        <p class="sln-onboarding-wizard__upgrade-trial"><?php esc_html_e('15 days free trial - cancel anytime', 'salon-booking-system'); ?></p>
                        <p class="sln-onboarding-wizard__upgrade-free"><?php esc_html_e('Want to continue with the free version? You can always upgrade later from the Settings panel.', 'salon-booking-system'); ?></p>
                    </div>
                <?php endif; ?>
                <div class="sln-onboarding-wizard__card-actions">
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--secondary sln-onboarding-wizard__btn-back" data-step="5"><?php esc_html_e('Back', 'salon-booking-system'); ?></button>
                    <button type="button" class="sln-onboarding-wizard__btn sln-onboarding-wizard__btn--primary sln-onboarding-wizard__btn-complete"><?php echo $is_pro ? esc_html__('Go to Calendar', 'salon-booking-system') : esc_html__('Continue with Free', 'salon-booking-system'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="sln-onboarding-wizard__info">
        <span class="sln-onboarding-wizard__info-icon">ℹ</span>
        <p><?php esc_html_e('You can refine and complete the full setup later in the plugin settings', 'salon-booking-system'); ?></p>
    </div>

    <footer class="sln-onboarding-wizard__footer">
        <a href="https://www.salonbookingsystem.com/support/" target="_blank" rel="noopener"><?php esc_html_e('Need help? Contact our support team', 'salon-booking-system'); ?></a>
    </footer>
</div>
</div>
