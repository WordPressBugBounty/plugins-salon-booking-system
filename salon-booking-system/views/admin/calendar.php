<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$addAjax = apply_filters('sln.template.calendar.ajaxUrl', '');
$ai = $plugin->getSettings()->getAvailabilityItems();
list($timestart, $timeend) = $ai->getTimeMinMax();
$timesplit = $plugin->getSettings()->getInterval();
$holidays_rules = apply_filters('sln.get-day-holidays-rules', $plugin->getSettings()->getDailyHolidayItems());

$holidays_assistants_rules  = array();
$assistants                 = $plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT)->getAll();
foreach ($assistants as $att) {
    $holidays_assistants_rules[$att->getId()] = $att->getMeta('holidays_daily') ?: array();
}
$holidays_assistants_rules = apply_filters('sln.get-day-holidays-assistants-rules', $holidays_assistants_rules, $assistants);
$day_calendar_holydays_ajax_data = apply_filters('sln.get-day-calendar-holidays-ajax-data', array());
$day_calendar_columns = $plugin->getSettings()->get('parallels_hour') * 2 + 1;
$replace_booking_modal_with_popup = $plugin->getSettings()->get('replace_booking_modal_with_popup');

$holidays = $plugin->getSettings()->get('holidays');

function expirePopup() {
    global $sln_license;
    $html = '';

    if (!defined('SLN_VERSION_PAY') || !SLN_VERSION_PAY || !$sln_license) {
        return $html;
    }

    $expire_days = 0;
    $day_in_seconds = (24 * 3600);
    $sln_license->checkSubscription();
    $timestamp = current_time('timestamp');
    
    // Use centralized subscription status method (handles both status locations and fallback logic)
    $subscription_status_data = $sln_license->getSubscriptionStatus();
    
    error_log('[Salon Subscription Banner] Subscription status data: ' . print_r($subscription_status_data, true));
    
    // Extract status and expiration
    $subscription_status = isset($subscription_status_data['status']) ? $subscription_status_data['status'] : null;
    $subscription_expiration = isset($subscription_status_data['expiration']) ? $subscription_status_data['expiration'] : null;
    
    error_log('[Salon Subscription Banner] Subscription status: ' . ($subscription_status ?? 'null'));
    error_log('[Salon Subscription Banner] Subscription expiration: ' . ($subscription_expiration ?? 'null'));

    // Calculate expire days based on subscription status
    if ($subscription_expiration && $subscription_expiration !== 'lifetime') {
        $expire_days = ceil((strtotime($subscription_expiration) - $timestamp) / $day_in_seconds);
    } else {
        // Fallback to license expiration
        $license_data = $sln_license->get('license_data');
        if ($license_data && isset($license_data->expires) && $license_data->expires !== 'lifetime') {
            $expire_days = ceil((strtotime($license_data->expires) - $timestamp) / $day_in_seconds);
        } else {
            $expire_days = 999999; // Lifetime
        }
    }

    error_log('[Salon Subscription Banner] Expire days: ' . $expire_days);

    $is_active = $subscription_status === 'active';
    $is_cancelled = $subscription_status === 'cancelled';
    $is_expired = $subscription_status === 'expired';

    error_log('[Salon Subscription Banner] Is active: ' . ($is_active ? 'yes' : 'no'));
    error_log('[Salon Subscription Banner] Is cancelled: ' . ($is_cancelled ? 'yes' : 'no'));
    error_log('[Salon Subscription Banner] Is expired: ' . ($is_expired ? 'yes' : 'no'));

    // NEVER show banner if subscription is active
    if ($is_active) {
        error_log('[Salon Subscription Banner] Subscription is ACTIVE - banner will NOT show');
        return $html;
    }

    $param = 'remind_me_7_days';
    $cookie_name = 'sln_remind_timestamp';

    if (isset($_GET[$param])) {
        setcookie($cookie_name, $timestamp, time() + 7 * $day_in_seconds, '/');
        $_COOKIE[$cookie_name] = $timestamp;
        return $html;
    }

    $is_expiring = $expire_days <= 10;
    $remind_timestamp = isset($_COOKIE[$cookie_name]) ? (int)$_COOKIE[$cookie_name] : 0;
    $seven_days_passed = ($timestamp - $remind_timestamp) > 7 * $day_in_seconds;

    error_log('[Salon Subscription Banner] Is expiring (<=10 days): ' . ($is_expiring ? 'yes' : 'no'));
    error_log('[Salon Subscription Banner] Seven days passed since reminder: ' . ($seven_days_passed ? 'yes' : 'no'));
    error_log('[Salon Subscription Banner] Will show banner: ' . (((($is_cancelled && $is_expiring) || $is_expired) && $seven_days_passed) ? 'YES' : 'NO'));

    // ((the subscription is cancelled and will expires soon) OR subscription has already expired) AND More than 7 days have passed since the user clicked 'Remind me in 7 days'
    if ((($is_cancelled && $is_expiring) || $is_expired) && $seven_days_passed) {
        $link = "https://www.salonbookingsystem.com/checkout?edd_license_key=" . $sln_license->get('license_key') . "&download_id=697772"; ?>

        <div id="sln-wrap-popup" class="wrap-popup">
            <section class="card" role="alertdialog" aria-labelledby="dlg-title" aria-describedby="dlg-desc">
            <img src="<?php echo SLN_PLUGIN_URL . '/img/expired.png'; ?>" alt="Expired calendar icon" class="icon" />
            <h1 id="dlg-title" class="title">Your subscription is expired</h1>
            <p id="dlg-desc" class="subtitle">Don’t lose your access to our product updates and email customers support.</p>
            <div class="actions" role="group" aria-label="Actions">
                <a class="btn btn-primary" href="<?php echo $link ?>">Renew now</a>
                <a class="link" href="<?php echo esc_url(add_query_arg($param, 1)); ?>">Remind me in seven days</a>
            </div>
            </section>
        </div>
        <style>
            :root {
                --p: #78838B;
                --card-bg: #e9eff5;
                --text: #0f172a;
                --muted: #667085;
                --primary: #2171B1;
                --primary-hover: #1d4ed8;
                --radius: 10px;
            }

            .wrap-popup {
                display: grid;
                place-items: center;
                min-height: 100vh;
                padding: 24px;
            }

            .wrap-popup .card {
                background: var(--card-bg);
                width: 80%;
                max-width: 420px;
                border-radius: var(--radius);
                padding: 40px 32px;
                text-align: center;
                border: 1px solid rgba(2, 6, 23, 0.06);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }

            .wrap-popup .icon {
                width: 64px;
                height: 64px;
                margin-bottom: 16px;
            }

            .wrap-popup .title {
                font-size: 22px;
                font-weight: 700;
                margin-bottom: 8px;
            }

            .wrap-popup .subtitle {
                color: var(--p);
                font-size: 16px;
                margin-top: 24px;
                margin-bottom: 24px;
            }

            .wrap-popup .actions {
                display: flex;
                justify-content: center;
                align-items: center;
                flex-wrap: wrap;
                gap: 16px;
            }

            .wrap-popup .btn {
                appearance: none;
                border: none;
                border-radius: 4px;
                padding: 12px 20px;
                font-weight: 600;
                cursor: pointer;
                font-family: inherit;
                transition: background-color 0.2s ease;
                text-decoration: none;
            }

            .wrap-popup .btn-primary {
                background: var(--primary);
                color: #fff;
            }

            .wrap-popup .btn-primary:hover {
                background: var(--primary-hover);
            }

            .wrap-popup .link {
                background: transparent;
                color: var(--p);
                font-weight: 400;
                padding: 12px 8px;
                border-radius: 6px;
                font-size: 12px;
                text-decoration: none;
            }

            .wrap-popup .link:hover {
                text-decoration: underline;
            }

            .wrap-popup .btn:focus-visible,
            .wrap-popup .link:focus-visible {
                outline: 3px solid rgba(37, 99, 235, 0.45);
                outline-offset: 2px;
            }
        </style>
    <?php }

    return $html;
}

echo expirePopup();

?>
<script type="text/javascript">
    var salon;
    var calendar_translations = {
        'Go to daily view': '<?php esc_html_e('Go to daily view', 'salon-booking-system') ?>'
    };
    var salon_default_duration = <?php echo $timesplit; ?>;
    var daily_rules = JSON.parse('<?php echo wp_json_encode($holidays_rules); ?>');
    var daily_assistants_rules = JSON.parse('<?php echo wp_json_encode($holidays_assistants_rules); ?>');
    var holidays_rules_locale = {
        'block': '<?php esc_html_e('Block', 'salon-booking-system'); ?>',
        'block_confirm': '<?php esc_html_e('Confirm', 'salon-booking-system'); ?>',
        'unblock': '<?php esc_html_e('Unlock', 'salon-booking-system'); ?>',
        'unblock_these_rows': '<?php esc_html_e('Unlock', 'salon-booking-system'); ?>',
    }
    var sln_search_translation = {
        'tot': '<?php esc_html_e('Tot.', 'salon-booking-system'); ?>',
        'edit': '<?php esc_html_e('Edit', 'salon-booking-system'); ?>',
        'cancel': '<?php esc_html_e('Cancel', 'salon-booking-system'); ?>',
        'no_results': '<?php esc_html_e('No results', 'salon-booking-system'); ?>'
    }
    var calendar_locale = {
        'add_event': '<?php esc_html_e('Add book', 'salon-booking-system'); ?>',
    }

    var dayCalendarHolydaysAjaxData = JSON.parse('<?php echo wp_json_encode($day_calendar_holydays_ajax_data); ?>');

    var dayCalendarColumns = '<?php echo $day_calendar_columns ?>';

    <?php $today = new DateTime() ?>
    jQuery(function($) {
        sln_initSalonCalendar(
            $,
            salon.ajax_url + "&action=salon&method=calendar&security=" + salon.ajax_nonce + '<?php echo $addAjax ?>',
            //        '<?php echo SLN_PLUGIN_URL ?>/js/events.json.php',
            '<?php echo $today->format('Y-m-d') ?>',
            '<?php echo SLN_PLUGIN_URL ?>/views/js/calendar/',
            '<?php echo $plugin->getSettings()->get('calendar_view') ?: 'month' ?>',
            '<?php echo $plugin->getSettings()->get('week_start') ?: 0 ?>'
        );
    });
    jQuery("body").addClass("sln-body");

    var replaceBookingModalWithPopup = +'<?php echo $replace_booking_modal_with_popup ?>';
</script>
<?php 
// Show subscription debug info for admins when WP_DEBUG is enabled
if (defined('WP_DEBUG') && WP_DEBUG && defined('SLN_VERSION_PAY') && SLN_VERSION_PAY) {
    global $sln_license;
    if ($sln_license) {
        $debug_subscription_status = $sln_license->getSubscriptionStatus();
        $subscriptions_data = $sln_license->get('subscriptions_data');
        $last_check = get_transient('sln_subscription_last_check');
        
        echo '<div class="notice notice-info" style="margin: 20px 0; padding: 15px; border-left: 4px solid #0073aa;">';
        echo '<h3 style="margin-top: 0;">🔍 Subscription Debug Info (WP_DEBUG Mode)</h3>';
        echo '<p><strong>Subscription Status:</strong> ' . esc_html($debug_subscription_status['status'] ?? 'unknown') . '</p>';
        echo '<p><strong>Expiration:</strong> ' . esc_html($debug_subscription_status['expiration'] ?? 'unknown') . '</p>';
        echo '<p><strong>Last API Check:</strong> ' . ($last_check ? human_time_diff($last_check) . ' ago' : 'Never or expired') . '</p>';
        echo '<p><strong>Subscriptions Count:</strong> ' . (isset($subscriptions_data->subscriptions) ? count($subscriptions_data->subscriptions) : 0) . '</p>';
        if (isset($subscriptions_data->subscriptions[0])) {
            $sub = $subscriptions_data->subscriptions[0];
            $sub_status = isset($sub->status) ? $sub->status : (isset($sub->info->status) ? $sub->info->status : 'unknown');
            echo '<p><strong>First Subscription Status:</strong> ' . esc_html($sub_status) . '</p>';
        }
        echo '<p style="margin: 0;"><em>Go to Plugins → License to refresh subscription data</em></p>';
        echo '</div>';
    }
}
?>
<?php if (apply_filters('sln.show_branding', true)) : ?>
    <div class="sln-bootstrap sln-calendar-plugin-update-notice--wrapper">
        <?php if (!defined("SLN_VERSION_PAY")): ?>
            <div class="row">
                <div class="col-xs-12 sln-notice__wrapper">
                    <div class="sln-notice sln-notice--bold sln-notice--subscription-free-version">
                        <img src="<?php echo SLN_PLUGIN_URL ?>/img/crown-pro-icon.png" alt="PRO" class="sln-notice--subscription-icon" style="grid-column: 1; grid-row: 1; width: 3rem; height: 3rem; object-fit: contain;">
                        <div class="sln-notice--bold__text">
                            <h2><?php _e('<strong>You\'re booking appointments — but not getting paid upfront 💰</strong>', 'salon-booking-system') ?></h2>
                            <p><?php _e('<br />♔ Accept online payments via PayPal or Stripe<br />♕ Reduce no-shows with upfront deposits<br /> ♘ Manage staff schedules and availability more efficiently<br />
<br /><strong>Tools already trusted by 2,000+ professionals worldwide.</strong>', 'salon-booking-system') ?></p>
                        </div>
                        <a href="https://www.salonbookingsystem.com/checkout/?edd_action=add_to_cart&download_id=789093" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Start accepting online payments', 'salon-booking-system') ?></a>
                        
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php
            global $sln_license;
            if ($sln_license) {
                // Check subscription (uses 1-hour cache unless force refreshed)
                $sln_license->checkSubscription();
                $subscriptions_data = $sln_license->get('subscriptions_data');
            }
            $subscription = isset($subscriptions_data->subscriptions[0]) ? $subscriptions_data->subscriptions[0] : null;
            
            // Use centralized subscription status logic with fallback
            $subscription_status = $sln_license ? $sln_license->getSubscriptionStatus() : null;
            
            // Calculate expiration days based on subscription status
            if ($subscription_status && $subscription_status['status'] === 'active') {
                if ($subscription_status['expiration'] && $subscription_status['expiration'] !== 'lifetime') {
                    $expire_days = ceil((strtotime($subscription_status['expiration']) - current_time('timestamp')) / (24 * 3600));
                } else {
                    $expire_days = 999999; // Lifetime
                }
            } elseif ($subscription && !empty($subscription->info->expiration)) {
                $expire_days = ceil((strtotime($subscription->info->expiration) - current_time('timestamp')) / (24 * 3600));
            } else {
                $expire_days = 0;
            }
            
            $expire = sprintf(
                // translators: %s the name of the expire days
                _n('%s day', '%s days', $expire_days, 'salon-booking-system'),
                $expire_days
            );
            ?>
            <?php if ($sln_license && !$sln_license->get('license_data') && !in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles)): ?>
                <?php
                $page_slug = $sln_license->get('slug') . '-license';
                $license_url = admin_url('/plugins.php?page=' . $page_slug);
                ?>
                <div class="row">
                    <div class="col-xs-12 sln-notice__wrapper">
                        <div class="sln-notice sln-notice--bold sln-notice--subscription-expired">
                            <div class="sln-notice--bold__text">
                                <h2><?php _e('<strong>Attention:</strong> Please activate your license first', 'salon-booking-system') ?></h2>
                            </div>
                            <a href="<?php echo $license_url ?>" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Activate your license', 'salon-booking-system') ?></a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($subscription_status && !in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles)): ?>
                <?php if ($subscription_status['status'] === 'cancelled'): ?>
                    <div class="row">
                        <div class="col-xs-12 sln-notice__wrapper">
                            <div class="sln-notice sln-notice--bold sln-notice--subscription-cancelled">
                                <div class="sln-notice--bold__text">
                                    <h2><?php _e('<strong>Your subscription has been cancelled!</strong>', 'salon-booking-system') ?></h2>
                                    <p><?php echo sprintf(
                                            // translators: %s will be replaced by the license expiration time
                                            esc_html__('Your license will expire in %s, then you need to purchase a new one at its full price to continue using our services.', 'salon-booking-system'),
                                            $expire
                                        ) ?></p>
                                    <p><?php _e('<strong>Renew it before the expiration and get a discounted price.</strong>', 'salon-booking-system') ?></p>
                                </div>
                                <a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=plugin-back-end_pro&utm_medium=license-status-notice&utm_campaign=renew-license&utm_id=renew-license" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Renew for 15% off', 'salon-booking-system') ?></a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($subscription_status['status'] === 'active'): ?>
                    <?php 
                    // Debug info for WP_DEBUG mode
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Salon Subscription Banner] Active subscription detected - banner should NOT show');
                        error_log('[Salon Subscription Banner] Subscription expiration: ' . ($subscription_status['expiration'] ?? 'unknown'));
                    }
                    ?>
                    <?php if (!isset($_COOKIE['remove_notice'])) { ?>
                        <div class="row notice_custom">
                            <div class="col-xs-12 sln-notice__wrapper">
                                <div class="sln-notice sln-notice--bold sln-notice--subscription-active" style="position:relative;">
                                    <div class="sln-notice--bold__text">
                                        <h2><?php _e('<strong>Your subscription is active</strong>', 'salon-booking-system') ?></h2>
                                        <p><?php 
                                            if ($subscription_status['expiration'] === 'lifetime') {
                                                esc_html_e('Your license has lifetime access.', 'salon-booking-system');
                                            } else {
                                                echo sprintf(
                                                    // translators: %s will be replaced by the license expiration time
                                                    esc_html__('Your license will expire in %s, then will be automatically renewed.', 'salon-booking-system'),
                                                    $expire
                                                );
                                            }
                                        ?></p>
                                        <p><?php _e('<strong>If you are happy with us, please submit a positive review.</strong>', 'salon-booking-system') ?></p>
                                    </div>
                                    <a href="https://reviews.capterra.com/new/166320?utm_source=vp&utm_medium=none&utm_campaign=vendor_request_paid" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Leave a review', 'salon-booking-system') ?></a>
                                    <button style="position: absolute;right: 0px;top: 0px;background: transparent;" class="custom sln-btn sln-btn--main sln-btn--small sln-btn--icon sln-icon--close">info</button>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                <?php elseif ($subscription_status['status'] === 'expired'): ?>
                    <?php
                    $expire_days = ceil((strtotime($sln_license->get('license_data')->expires) - current_time('timestamp')) / (24 * 3600));
                    $expire = sprintf(
                        // translators: %s the name of the expire days
                        _n('%s day', '%s days', $expire_days, 'salon-booking-system'),
                        $expire_days
                    );
                    ?>
                    <div class="row">
                        <div class="col-xs-12 sln-notice__wrapper">
                            <div class="sln-notice sln-notice--bold sln-notice--subscription-cancelled">
                                <div class="sln-notice--bold__text">
                                    <h2><?php _e('<strong>Your subscription is expired!</strong>', 'salon-booking-system') ?></h2>
                                    <p><?php echo sprintf(
                                            // translators: %s will be replaced by the license expiration time
                                            __('<strong>Attention:</strong> your subscription to <strong>Salon Booking System “Business Plan”</strong> is expired but your license is still active and <strong>it will expire in %s</strong>', 'salon-booking-system'),
                                            $expire
                                        ) ?></p>
                                    <p><?php _e('<strong>Renew it now and get a discounted price.</strong>', 'salon-booking-system') ?></p>
                                </div>
                                <a href="https://www.salonbookingsystem.com/checkout?edd_action=add_to_cart&download_id=64398&edd_options%5Bprice_id%5D=2&discount=GETBACK30&utm_source=plugin-back-end_pro&utm_medium=license-status-notice&utm_campaign=renew-license&utm_id=renew-expired-license" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Renew for 30% off', 'salon-booking-system') ?></a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="clearfix"></div>
<style>
    body {
        overflow: hidden;
    }

    body.sln-body--scrolldef {
        overflow: auto;
    }

    .custom.sln-btn--small.sln-btn--icon:after {
        font-size: 1rem !important;
        line-height: 1rem !important;
    }
    #sln-wrap-popup,
    #sln-pageloading,
    #sln-viewloading,
    #sln-modalloading {
        position: absolute;
        top: 0;
        right: 0;
        left: -20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    #sln-pageloading {
        min-height: calc(100vh - 32px);
        background-color: rgb(231, 237, 241);
    }

    @media screen and (max-width: 789px) {
        #sln-pageloading {
            min-height: calc(100vh - 46px);
            top: 0;
            left: -10px;
        }
    }

    @media screen and (max-width: 660px) {
        #sln-pageloading {
            min-height: calc(100vh - 46px);
            top: 46px;
            left: -10px;
        }
    }

    #sln-viewloading {
        bottom: 0;
        left: 0;
        justify-content: start;
        padding-top: 4.55rem;
        background-color: rgba(231, 237, 241, 0.75);
    }

    #sln-modalloading {
        bottom: 0;
        left: 0;
        background-color: rgba(231, 237, 241, 0.75);
    }
    #sln-wrap-popup img,
    #sln-pageloading img,
    #sln-viewloading img,
    #sln-modalloading img {
        max-width: 60px;
        animation: swing ease-in-out 1s infinite alternate;
    }

    #sln-pageloading h1,
    #sln-viewloading h1,
    #sln-modalloading h1 {
        margin: 1.2em 0 0 0;
        color: #375F99;
        font-weight: 500;
        font-size: 1.5em;
    }

    #sln-wrap-popup img,
    #sln-wrap-popup h1,
    #sln-pageloading img,
    #sln-pageloading h1,
    #sln-viewloading img,
    #sln-viewloading h1,
    #sln-modalloading img,
    #sln-modalloading h1 {
        transition: all 150ms ease-out;
        transform: scale(1);
    }

    @keyframes swing {
        0% {
            transform: rotate(3deg);
        }

        100% {
            transform: rotate(-3deg);
        }
    }
</style>
<div id="sln-pageloading" class="sln-pageloading">
    <img
        src="<?php echo SLN_PLUGIN_URL . '/img/admin-loading.png'; ?>"
        alt="img"
        border="0">
    <h1><?php esc_html_e('We are loading your appointments calendar..', 'salon-booking-system') ?></h1>
</div>
<div class="container-fluid sln-calendar--wrapper sln-calendar--wrapper--loading--">
    <div class="sln-calendar--wrapper--sub" style="opacity: 0;">

        <?php
        // Performance Indexes Notice
        $index_status = SLN_Helper_PerformanceIndexManager::getStatus();
        $index_message = SLN_Helper_PerformanceIndexManager::getMessage();
        $notice_dismissed = SLN_Helper_PerformanceIndexManager::isNoticeDismissed();

        if (!$notice_dismissed && in_array($index_status, array('error', 'pending'))) :
        ?>
        <div id="sln-performance-indexes-notice" class="notice notice-<?php echo $index_status === 'error' ? 'error' : 'warning'; ?> is-dismissible" style="position: relative; margin: 15px 0;">
            <p>
                <strong><?php _e('Performance Optimization:', 'salon-booking-system'); ?></strong>
                <?php echo esc_html($index_message); ?>
            </p>
            <?php if ($index_status === 'error') : ?>
            <p>
                <button type="button" class="button button-primary" id="sln-install-indexes-btn">
                    <?php _e('Install Indexes Now', 'salon-booking-system'); ?>
                </button>
                <span id="sln-install-indexes-status" style="margin-left: 10px;"></span>
            </p>
            <?php endif; ?>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle manual installation
            $('#sln-install-indexes-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#sln-install-indexes-status');
                
                $btn.prop('disabled', true).text('<?php _e('Installing...', 'salon-booking-system'); ?>');
                $status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
                
                $.ajax({
                    url: salon.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'salon',
                        method: 'installPerformanceIndexes',
                        security: salon.ajax_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span style="color: green;">✓ ' + response.message + '</span>');
                            setTimeout(function() {
                                $('#sln-performance-indexes-notice').fadeOut(function() {
                                    $(this).remove();
                                });
                            }, 3000);
                        } else {
                            $status.html('<span style="color: red;">✗ ' + response.message + '</span>');
                            $btn.prop('disabled', false).text('<?php _e('Retry Installation', 'salon-booking-system'); ?>');
                        }
                    },
                    error: function() {
                        $status.html('<span style="color: red;">✗ <?php _e('Connection error. Please try again.', 'salon-booking-system'); ?></span>');
                        $btn.prop('disabled', false).text('<?php _e('Retry Installation', 'salon-booking-system'); ?>');
                    }
                });
            });
            
            // Handle dismiss
            $('#sln-performance-indexes-notice').on('click', '.notice-dismiss', function() {
                $.post(salon.ajax_url, {
                    action: 'salon',
                    method: 'dismissPerformanceIndexesNotice',
                    security: salon.ajax_nonce
                });
            });
        });
        </script>
        <?php endif; ?>

        <div class="row">
            <div class="col-xs-12 col-md-6 col-md-push-6 btn-group">
                <?php include 'help.php' ?>
            </div>

            <?php do_action('sln.template.calendar.navtabwrapper') ?>
        </div>
        <?php
        $useragent = $_SERVER['HTTP_USER_AGENT'];

        $is_phone = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));
        if ($is_phone && defined('SLN_VERSION_PAY') && SLN_VERSION_PAY): ?>
            <div id="sln-note-phone-device" class="sln-popup">
                <div class="sln-popup--close"></div>
                <div class="sln-popup-content">
                    <p class="sln-popup--text sln-popup--question"><?php esc_html_e('Why don\'t you use our brand-new Web App?', 'salon-booking-system') ?></p>
                    <p class="sln-popup--text sln-popup--offer"><?php esc_html_e('It\'s easy and optimised for mobile device.') ?></p>
                </div>
                <a class="sln-popup--button" href="<?php echo site_url('/salon-booking-pwa') ?>"><?php esc_html_e('Open the Web App', 'salon-booking-system') ?></a>
            </div>
        <?php endif ?>
        <div class="row sln-calendar-view-topbar">
            <div class="sln-calendar-view-nav btn-group">
                <div class="sln-btn sln-btn--calendar-view--pill" data-calendar-view="day">
                    <button class="f-row" data-calendar-nav="today"><?php esc_html_e('Today', 'salon-booking-system') ?></button>
                </div>
                <div class="sln-btn sln-btn--calendar-view--icononly sln-btn--icon sln-btn--icon--clickthrough sln-icon--arrow--left" data-calendar-view="day">
                    <button class="f-row" data-calendar-nav="prev"><span class="sr-only"><?php esc_html_e('Previous', 'salon-booking-system') ?></span></button>
                </div>
                <div class="sln-btn sln-btn--calendar-view--icononly sln-btn--icon sln-btn--icon--clickthrough sln-icon--arrow--right" data-calendar-view="day">
                    <button class="f-row f-row--end" data-calendar-nav="next"><span class="sr-only"><?php esc_html_e('Next', 'salon-booking-system') ?></span></button>
                </div>
                <div class="sln-box-title current-view--title"></div>
            </div>
            <div class="sln-calendar-view-switcher">
                <div class="btn-group nav-tab-wrapper sln-nav-tab-wrapper">
                    <div class="sln-btn sln-btn--calendar-view--textonly sln-btn--large" data-calendar-view="day">
                        <button class="" data-calendar-view="day"><?php esc_html_e('Day', 'salon-booking-system') ?></button>
                    </div>
                    <div class="sln-btn sln-btn--calendar-view--textonly sln-btn--large" data-calendar-view="week">
                        <button class="" data-calendar-view="week"><?php esc_html_e('Week', 'salon-booking-system') ?></button>
                    </div>
                    <div class="sln-btn sln-btn--calendar-view--textonly sln-btn--large" data-calendar-view="month">
                        <button class=" active" data-calendar-view="month"><?php esc_html_e('Month', 'salon-booking-system') ?></button>
                    </div>
                    <div class="sln-btn sln-btn--calendar-view--textonly sln-btn--large" data-calendar-view="year">
                        <button class="" data-calendar-view="year"><?php esc_html_e('Year', 'salon-booking-system') ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <?php if (!defined("SLN_VERSION_PAY") && isset($_COOKIE['sln-notice__dismiss']) && $_COOKIE['sln-notice__dismiss']): ?>
                <div class="col-xs-12 sln-notice__wrapper">
                    <div class="sln-notice sln-notice--review">
                        <h2><?php esc_html_e('Are you happy with us?', 'salon-booking-system') ?> <?php _e('Share your love for <strong>Salon Booking System</strong> leaving a positive review.', 'salon-booking-system') ?>
                            <?php esc_html_e("Let's grow our community.", 'salon-booking-system') ?>
                            <a href="https://wordpress.org/support/plugin/salon-booking-system/reviews/?filter=5#new-post" target="_blank" class="sln-notice--action">
                                <?php esc_html_e('Submit a review', 'salon-booking-system') ?>
                            </a>
                        </h2>
                        <button type="button" class="sln-notice__dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="row">
            <div class="col-xs-12 sln-calendar-view-topbar--secondary">
                <div class="form-group sln-free-locked-slots-block">
                    <button class="sln-btn sln-btn--new sln-btn--textonly sln-free-locked-slots sln-icon--new sln-icon--left sln-icon--new--unlock">
                        <?php esc_html_e('Free locked slots', 'salon-booking-system') ?>
                    </button>
                </div>

                <?php if ($plugin->getSettings()->isAttendantsEnabled()): ?>
                    <div class="form-group sln-switch sln-switch--nu sln-switch--nu--flex cal-day-filter">
                        <span class="sln-fake-label"><?php esc_html_e('Assistants view', 'salon-booking-system') ?></span>
                        <?php
                        SLN_Form::fieldCheckbox(
                            "sln-calendar-assistants-mode-switch",
                            ($checked = get_user_meta(get_current_user_id(), '_assistants_mode', true)) !== '' ? $checked && $checked != 'false' : false
                        )
                        ?>
                        <label for="sln-calendar-assistants-mode-switch" class="sln-switch-btn" data-on="On" data-off="Off"></label>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="sln-calendar-view" class="row sln-calendar-view sln-box sln-calendar-view--holidays-data" data-holidays='<?php echo wp_json_encode($holidays); ?>'>
            <div class="row">
                <div class="col-xs-12 form-inline">
                    <div class="sln-calendar-view-header">
                        <div class="cal-day-search cal-day-filter--">
                            <!-- <div class="sln-calendar-booking-search-wrapper">
                                <div class="sln-calendar-booking-search-input-wrapper">
                                    <?php
                                    SLN_Form::fieldText(
                                        "sln-calendar-booking-search",
                                        false,
                                        [
                                            'attrs' => [
                                                'size' => 32,
                                                'placeholder' => __("Start typing customer name or booking ID", 'salon-booking-system'),
                                            ],
                                        ]
                                    );
                                    ?>
                                </div>
                                <div class="sln-calendar-booking-search-icon">

                                </div>
                            </div> -->
                            <?php
                            SLN_Form::fieldText(
                                "sln-calendar-booking-search",
                                false,
                                [
                                    'attrs' => [
                                        'size' => 32,
                                        'placeholder' => __("Start typing customer name or booking ID", 'salon-booking-system'),
                                        'class' => 'sln-25-input sln-25-input--text sln-25-input--pill sln-25-input--icon--search',
                                    ],
                                ]
                            );
                            ?>
                            <div id="search-results-list" class="sln-calendar-search-results-list sln-calendar-search-results-list25"></div>
                        </div>
                        <!-- Booking Status Summary -->
                        <div id="sln-booking-status-summary" class="sln-booking-status-summary <?php echo !defined('SLN_VERSION_PAY') ? 'sln-profeature sln-profeature--disabled sln-profeature__tooltip-wrapper' : '' ?>" data-test="v2">
                            <?php if (!defined('SLN_VERSION_PAY')): ?>
                                <!-- PRO Feature Overlay -->
                                <?php echo $plugin->loadView(
                                    'metabox/_pro_feature_tooltip',
                                    array(
                                        'additional_classes' => 'sln-profeature__cta--booking-status-summary',
                                        'trigger' => 'booking-status-summary',
                                    )
                                ); ?>
                            <?php endif; ?>
                            
                            <div class="sln-profeature__input">
                                <span class="sln-status-summary__item sln-status-summary__item--paid-confirmed">
                                    <strong id="status-paid-confirmed">0</strong> <?php esc_html_e('Paid/Confirmed', 'salon-booking-system') ?>
                                </span>
                                <span class="sln-status-summary__item sln-status-summary__item--pay-later">
                                    <strong id="status-pay-later">0</strong> <?php esc_html_e('Pay Later', 'salon-booking-system') ?>
                                </span>
                                <span class="sln-status-summary__item sln-status-summary__item--pending">
                                    <strong id="status-pending">0</strong> <?php esc_html_e('Pending', 'salon-booking-system') ?>
                                </span>
                                <span class="sln-status-summary__item sln-status-summary__item--cancelled">
                                    <strong id="status-cancelled">0</strong> <?php esc_html_e('Cancelled', 'salon-booking-system') ?>
                                </span>
                                <span class="sln-status-summary__item sln-status-summary__item--noshow">
                                    <strong id="status-noshow">0</strong> <?php esc_html_e('No Show', 'salon-booking-system') ?>
                                </span>
                                <!-- Booking Status Chart -->
                                <div id="sln-booking-status-chart-container" class="sln-booking-status-chart-container">
                                    <?php if (!defined('SLN_VERSION_PAY')): ?>
                                        <!-- Static mockup chart for FREE version -->
                                        <svg class="sln-booking-status-chart-mockup" width="75" height="75" aria-label="<?php esc_attr_e('Booking Status Chart', 'salon-booking-system'); ?>" style="overflow: hidden;"><defs id="defs"></defs><g><path d="M26.0755877,35.4712698L11.1957504,32.0750441A27.75,27.75,0,0,1,38.25,10.5L38.25,25.7625A12.4875,12.4875,0,0,0,26.0755877,35.4712698" stroke="#ffffff" stroke-width="0.75" fill="#1b1b21"></path></g><g><path d="M26.0755877,41.0287302L11.1957504,44.4249559A27.75,27.75,0,0,1,11.1957504,32.0750441L26.0755877,35.4712698A12.4875,12.4875,0,0,0,26.0755877,41.0287302" stroke="#ffffff" stroke-width="0.75" fill="#e54747"></path></g><g><path d="M28.4868794,46.0358289L16.5541764,55.5518450A27.75,27.75,0,0,1,11.1957504,44.4249559L26.0755877,41.0287302A12.4875,12.4875,0,0,0,28.4868794,46.0358289" stroke="#ffffff" stroke-width="0.75" fill="#f58120"></path></g><g><path d="M38.25,25.7625L38.25,10.5A27.75,27.75,0,1,1,16.5541764,55.5518450L28.4868794,46.0358289A12.4875,12.4875,0,1,0,38.25,25.7625" stroke="#ffffff" stroke-width="0.75" fill="#6aa84f"></path></g><g></g></svg>
                                    <?php else: ?>
                                        <!-- Real Google Chart for PRO version -->
                                        <div id="sln-booking-status-chart" style="width: 100px; height: 100px;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="clearfix"></div>
            <div id="calendar" data-timestart="<?php echo $timestart ?>" data-timeend="<?php echo $timeend ?>" data-timesplit="<?php echo $timesplit ?>"></div>
            <div class="clearfix"></div>

            <div id="sln-viewloading" class="sln-viewloading sln-viewloading--inactive">
                <img
                    src="<?php echo SLN_PLUGIN_URL . '/img/admin-loading.png'; ?>"
                    alt="img"
                    border="0">
                <h1><?php esc_html_e('We are loading your appointments..', 'salon-booking-system') ?></h1>
            </div>
            <!-- row sln-calendar-wrapper // END -->
        </div>

        <div id="sln-booking-editor-modal" class="modal fade">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div id="sln-modalloading" class="sln-modalloading">
                        <img
                            src="<?php echo SLN_PLUGIN_URL . '/img/admin-loading.png'; ?>"
                            alt="img"
                            border="0">
                        <h1 class="sln-modalloading__text--saving"><?php esc_html_e('We are processing your request..', 'salon-booking-system') ?></h1>
                        <div id="sln-modalloading__inner" class="sln-modalloading__inner">
                            <svg class="animated-check" viewBox="0 0 24 24">
                                <path d="M4.1 12.7L9 17.6 20.3 6.3" fill="none" />
                            </svg>
                            <h1 class="sln-modalloading__text--saved"><?php esc_html_e('Booking Saved', 'salon-booking-system') ?></h1>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="sln-booking-editor--wrapper">
                            <div class="sln-booking-editor--wrapper--sub" style="opacity: 0">
                                <iframe name="booking_editor" class="booking-editor" width="100%" height="600px" frameborder="0"
                                    data-src-template-edit-booking="<?php echo admin_url('/post.php?post=%id&action=edit&mode=sln_editor') ?>"
                                    data-src-template-new-booking="<?php echo admin_url('/post-new.php?post_type=sln_booking&date=%date&time=%time&mode=sln_editor') ?>"
                                    data-src-template-duplicate-booking="<?php echo admin_url('/post-new.php?post_type=sln_booking&action=duplicate&post=%id&mode=sln_editor') ?>"
                                    data-src-template-duplicate_clone-booking="<?php echo admin_url('/post-new.php?post_type=sln_booking&action=duplicate_clone&post=%id&mode=sln_editor') ?>"></iframe>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="display:flex;">
                        <!-- <div class="booking-last-edit-div pull-left-"></div>-->
                        <div class="pull-right- modal-footer__actions">
                            <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--highemph sln-btn--big" aria-hidden="true" data-action="save-edited-booking"><?php esc_html_e('Save', 'salon-booking-system') ?></button>
                            <div class="clone-info" style="font-family: 'Open Sans';display:none;">
                                <?php esc_html_e('Clone this booking', 'salon-booking-system') ?>
                                <input type="number" name="unit_times_input" min="1" value="1" style="width: 50px;" />
                                <span class="times" data-text_s="<?php esc_html_e('time', 'salon-booking-system') ?>" data-text_m="<?php esc_html_e('times', 'salon-booking-system') ?>"><?php esc_html_e('time', 'salon-booking-system') ?></span>
                                <select name="week_time" style="margin-bottom: 5px;">
                                    <option value="1"><?php esc_html_e('every week', 'salon-booking-system') ?> </option>
                                    <option value="2"><?php esc_html_e('every two weeks', 'salon-booking-system') ?> </option>
                                    <option value="3"><?php esc_html_e('every three week', 'salon-booking-system') ?> </option>
                                    <option value="4"><?php esc_html_e('every four week', 'salon-booking-system') ?> </option>
                                </select>
                                <span class="time_until" style="margin-left: 10px;font-size:13px;"><?php esc_html_e('until', 'salon-booking-system') ?> <span class="time_date">%date</span></span>
                            </div>
                            <div class=" sln-profeature sln-duplicate-booking <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-duplicate-booking--disabled sln-profeature--disabled sln-profeature__tooltip-wrapper' : '' ?>">
                                <?php echo $plugin->loadView(
                                    'metabox/_pro_feature_tooltip',
                                    array(
                                        // 'cta_url' => 'https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO',
                                        'trigger' => 'sln-duplicate-booking',
                                        'additional_classes' => 'sln-profeature--button--bare sln-profeature--modal-footer__actions',
                                    )
                                ); ?>
                                <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" data-confirm="<?php esc_html_e('Confirm', 'salon-booking-system') ?>" data-confirm="<?php esc_html_e('Clone', 'salon-booking-system') ?>" data-action="clone-edited-booking"><?php esc_html_e('Clone', 'salon-booking-system') ?></button>
                            </div>

                            <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" data-action="delete-edited-booking"><?php esc_html_e('Delete', 'salon-booking-system') ?></button>
                            <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--medhemph sln-btn--big" data-dismiss="modal" aria-hidden="true"><?php esc_html_e('Close', 'salon-booking-system') ?></button>
                        </div>
                        <div class="modal-footer__flyingactions">
                            <?php
                            if (!defined("SLN_VERSION_PAY")) {
                                $tellafriendurl = "https://www.salonbookingsystem.com/refer-a-friend/?utm_source=plugin-back-end_free&utm_medium=refer-a-friend-link&utm_campaign=refer_a_fiend&utm_id=refer-a-friend";
                            } else {
                                $tellafriendurl = "https://www.salonbookingsystem.com/refer-a-friend/?utm_source=plugin-back-end_pro&utm_medium=refer-a-friend-link&utm_campaign=refer_a_fiend&utm_id=refer-a-friend";
                            }
                            ?>
                            <?php if (! in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles)): ?>
                                <a class="sln-btn sln-btn--inline--icon" href="<?php echo $tellafriendurl; ?>" target="_blank"><span><?php esc_html_e('Refer a friend and get a 30% discount', 'salon-booking-system') ?></span></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (current_user_can('export_reservations_csv_sln_calendar')): ?>
            <div class="row">
                <div class="col-xs-12">
                    <form action="<?php echo admin_url('admin.php?page=' . SLN_Admin_Tools::PAGE) ?>" method="post">
                        <?php
                        $f = $plugin->getSettings()->get('date_format');
                        $weekStart = $plugin->getSettings()->get('week_start');
                        $jsFormat = SLN_Enum_DateFormat::getJsFormat($f);

                        // Set default dates: "to" = today, "from" = one month ago
                        $defaultToDate = new DateTime();
                        $defaultFromDate = new DateTime();
                        $defaultFromDate->modify('-1 month');

                        $phpFormat = SLN_Enum_DateFormat::getPhpFormat($f);
                        $defaultToDateFormatted = $defaultToDate->format($phpFormat);
                        $defaultFromDateFormatted = $defaultFromDate->format($phpFormat);
                        ?>

                        <div class="sln-calendar-export-wrapper">
                            <h2 class="sln-calendar-export-wrapper__title"><?php esc_html_e('Export reservations into a CSV file', 'salon-booking-system') ?></h2>
                            <div class="sln-calendar__export__bookings__field">
                                <div class="form-group sln_datepicker sln-input--simple sln-input--simple25 sln-input--cal__datepicker__wrapper">
                                    <input type="text" class="form-control sln-input sln-input--cal__datepicker" id="<?php echo SLN_Form::makeID("export[from]") ?>" name="export[from]"
                                        value="<?php echo esc_attr($defaultFromDateFormatted) ?>"
                                        required="required" data-format="<?php echo $jsFormat ?>" data-weekstart="<?php echo $weekStart ?>"
                                        data-locale="<?php echo SLN_Plugin::getInstance()->getSettings()->getDateLocale() ?>"
                                        autocomplete="off" />
                                    <label for="<?php echo SLN_Form::makeID("export[from]") ?>"><?php esc_html_e('from', 'salon-booking-system') ?></label>
                                </div>
                            </div>
                            <div class="sln-calendar__export__discounts__field">
                                <div class="form-group sln_datepicker sln-input--simple sln-input--simple25 sln-input--cal__datepicker__wrapper">
                                    <input type="text" class="form-control sln-input sln-input--cal__datepicker" id="<?php echo SLN_Form::makeID("export[to]") ?>" name="export[to]"
                                        value="<?php echo esc_attr($defaultToDateFormatted) ?>"
                                        required="required" data-format="<?php echo $jsFormat ?>" data-weekstart="<?php echo $weekStart ?>"
                                        data-locale="<?php echo SLN_Plugin::getInstance()->getSettings()->getDateLocale() ?>"
                                        autocomplete="off" />
                                    <label for="<?php echo SLN_Form::makeID("export[to]") ?>"><?php esc_html_e('to', 'salon-booking-system') ?></label>
                                </div>
                            </div>
                            <button type="submit" id="action" name="sln-tools-export-bookings" value="export"
                                class="sln-btn sln-btn--main25 sln-btn--big25 sln-btn--fullwidth sln-calendar__export__bookings__button">
                                <?php esc_html_e('Export bookings to a CSV file', 'salon-booking-system') ?></button>
                            <?php do_action('sln.tools.export_button'); ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="row sln-calendar-sidebar">
            <div class="col-xs-12 col-md-9">
                <!-- <h4><?php esc_html_e('Bookings status legend', 'salon-booking-system') ?></h4>
                <ul>
                    <li><span class="pull-left event event-warning"></span><?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::PENDING) ?></li>
                    <li><span class="pull-left event event-success"></span><?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::PAID) ?> <?php esc_html_e('or', 'salon-booking-system') ?> <?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::CONFIRMED) ?></li>
                    <li><span class="pull-left event event-info"></span><?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::PAY_LATER) ?></li>
                    <li><span class="pull-left event event-danger"></span><?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::CANCELED) ?></li>
                </ul>
                <div class="clearfix"></div> -->
            </div>
            <div class="col-xs-12 col-md-3">
                <?php if (! in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles)): ?>
                    <?php if (apply_filters('sln.show_branding', true)) : ?>
                        <div class="sln-help-button__block">
                            <button class="sln-help-button sln-btn sln-btn--nobkg sln-btn--big sln-btn--icon sln-icon--helpchat sln-btn--icon--al visible-md-inline-block visible-lg-inline-block"><?php esc_html_e('Do you need help ?', 'salon-booking-system') ?></button>
                            <button class="sln-help-button sln-btn sln-btn--mainmedium sln-btn--small--round sln-btn--icon  sln-icon--helpchat sln-btn--icon--al hidden-md hidden-lg"><?php esc_html_e('Do you need help ?', 'salon-booking-system') ?> </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>