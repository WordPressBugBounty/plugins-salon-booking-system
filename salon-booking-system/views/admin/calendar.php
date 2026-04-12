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
<?php if (apply_filters('sln.show_branding', true)) : ?>
    <div class="sln-bootstrap sln-calendar-plugin-update-notice--wrapper">
        <?php if (!defined("SLN_VERSION_PAY")): ?>
            <div class="row">
                <div class="col-xs-12 sln-notice__wrapper">
                    <div class="sln-calendar-carousel" id="sln-calendar-carousel" role="region" aria-label="<?php esc_attr_e( 'Premium features carousel', 'salon-booking-system' ); ?>">
                        <button class="sln-calendar-carousel__close" id="sln-calendar-carousel-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'salon-booking-system' ); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        
                        <div class="sln-calendar-carousel__track">
                            
                            <!-- Slide 1: No-Show Revenue Protection -->
                            <div class="sln-calendar-carousel__slide sln-calendar-carousel__slide--active" data-slide-id="payments-noshows">
                                <div class="sln-calendar-carousel__content">
                                    <h3 class="sln-calendar-carousel__headline"><?php esc_html_e( 'Missing revenue from no-shows and cancellations?', 'salon-booking-system' ); ?></h3>
                                    <ul class="sln-calendar-carousel__benefits">
                                        <li><?php esc_html_e( 'Accept deposits via PayPal, Stripe, or Mollie', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Reduce no-shows by 67% with upfront payments', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Automated refund handling', 'salon-booking-system' ); ?></li>
                                    </ul>
                                    <p class="sln-calendar-carousel__proof"><?php esc_html_e( 'Trusted by 2,000+ salon professionals worldwide', 'salon-booking-system' ); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=salon-settings&tab=payments'); ?>" class="sln-calendar-carousel__cta">
                                        <?php esc_html_e( 'Start Accepting Payments', 'salon-booking-system' ); ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                                <div class="sln-calendar-carousel__illustration">
                                    <svg height="150" viewBox="0 0 512 512" width="150" xmlns="http://www.w3.org/2000/svg"><g id="flat"><path d="m344 316v176h-80v-96h-16v96h-80v-176z" fill="#0e3e86"/><path d="m168 316-6.757 2.7a106.456 106.456 0 0 1 -67.765 3.8l-5.478-1.5 9.9 8.66a106.459 106.459 0 0 0 70.1 26.34l24.01-40z" fill="#092f6a"/><path d="m425.95 136.94-9.82 47.08-72.13-12.02v144h-176v-144l-72.13 12.02-9.82-47.08 71.1-11.23a139.976 139.976 0 0 1 21.83-1.71h154.04a139.976 139.976 0 0 1 21.83 1.71z" fill="#855ef7"/><path d="m104.083 316.25 3.2 12.811a2.56 2.56 0 0 1 -2.49 3.181 13.005 13.005 0 0 1 -8.72-3.386l-7.993-7.276v31.79a3.987 3.987 0 0 1 -2.74 3.79l-17.25 5.75a3.986 3.986 0 0 1 -5.12-2.74l-45.457-166.69a40.452 40.452 0 0 1 32.73-50.62l35.89-5.67 9.82 47.08-23.87 3.98z" fill="#ffb3d0"/><path d="m272.015 124 .063-40h-32.078v40l16 12z" fill="#ffb3d0"/><path d="m252 92h20a0 0 0 0 1 0 0v24a0 0 0 0 1 0 0 20 20 0 0 1 -20-20v-4a0 0 0 0 1 0 0z" fill="#e67199"/><path d="m240 44.849h48v47.151a16 16 0 0 1 -16 16 32 32 0 0 1 -32-32z" fill="#ffb3d0"/><path d="m304 36v11.423a20.6 20.6 0 0 1 -20.721 20.6 20.6 20.6 0 0 1 -6.39-1.057l-20.889-6.966a20 20 0 0 1 -20 20h-4v-26.507a25.494 25.494 0 0 1 25.493-25.493 25.492 25.492 0 0 1 11 2.5l6.287 3.008a25.5 25.5 0 0 0 11 2.5z" fill="#092f6a"/><circle cx="244" cy="80" fill="#ffb3d0" r="12"/><path d="m271.93 148-7.93 16 12 152-20 32-20-32 12-152-8-16 16-8z" fill="#603ec5"/><path d="m288 160a4 4 0 0 1 -1.788-.422l-30.212-15.106-30.211 15.106a4 4 0 0 1 -4.618-.749l-16-16a4 4 0 0 1 5.658-5.658l13.961 13.962 29.421-14.711a4 4 0 0 1 3.578 0l29.421 14.711 13.961-13.962a4 4 0 0 1 5.658 5.658l-16 16a4 4 0 0 1 -2.829 1.171z" fill="#7048e8"/><path d="m418.522 322.506a106.456 106.456 0 0 1 -67.765-3.8l-6.757-2.706h-24l24 40a106.459 106.459 0 0 0 70.1-26.34l9.9-8.66z" fill="#092f6a"/><g fill="#f5a000"><path d="m120 392a4 4 0 0 1 -2.829-6.829l8-8a4 4 0 0 1 5.658 5.658l-8 8a3.994 3.994 0 0 1 -2.829 1.171z"/><path d="m144 400a4 4 0 0 1 -4-4v-12a4 4 0 0 1 8 0v12a4 4 0 0 1 -4 4z"/><path d="m120 368h-12a4 4 0 0 1 0-8h12a4 4 0 0 1 0 8z"/><path d="m392 400a3.994 3.994 0 0 1 -2.829-1.171l-8-8a4 4 0 0 1 5.658-5.658l8 8a4 4 0 0 1 -2.829 6.829z"/><path d="m368 408a4 4 0 0 1 -4-4v-12a4 4 0 0 1 8 0v12a4 4 0 0 1 -4 4z"/><path d="m404 376h-12a4 4 0 0 1 0-8h12a4 4 0 0 1 0 8z"/></g><path d="m496 182.58a40.321 40.321 0 0 1 -1.43 10.65l-45.46 166.69a3.986 3.986 0 0 1 -5.12 2.74l-17.25-5.75a3.987 3.987 0 0 1 -2.74-3.79v-31.79l-7.708 7.016a14 14 0 0 1 -9.39 3.647 2.26 2.26 0 0 1 -2.2-2.808l3.298-13.185 32-128-23.87-3.98 9.82-47.08 35.89 5.67a40.454 40.454 0 0 1 34.16 39.97z" fill="#ffb3d0"/></g></svg>
                                </div>
                            </div>

                            <!-- Slide 2: Accept Online Payments -->
                            <div class="sln-calendar-carousel__slide" data-slide-id="payment-gateways">
                                <div class="sln-calendar-carousel__content">
                                    <h3 class="sln-calendar-carousel__headline"><?php esc_html_e( 'Still collecting cash and checks in 2026?', 'salon-booking-system' ); ?></h3>
                                    <ul class="sln-calendar-carousel__benefits">
                                        <li><?php esc_html_e( 'Accept Stripe, PayPal, Square, Mollie', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( '22 payment gateways for any country', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Automated payment reminders & receipts', 'salon-booking-system' ); ?></li>
                                    </ul>
                                    <p class="sln-calendar-carousel__proof"><?php esc_html_e( 'Process payments in 50+ countries', 'salon-booking-system' ); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=salon-extensions'); ?>" class="sln-calendar-carousel__cta">
                                        <?php esc_html_e( 'View Payment Gateways', 'salon-booking-system' ); ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                                <div class="sln-calendar-carousel__illustration">
                                    <svg id="Layer_1" enable-background="new 0 0 512 512" height="150" viewBox="0 0 512 512" width="150" xmlns="http://www.w3.org/2000/svg"><g><path d="m284.845 512h-220.459c-22.758 0-41.207-18.449-41.207-41.207v-429.586c0-22.758 18.449-41.207 41.207-41.207h220.459c22.758 0 41.207 18.449 41.207 41.207v429.586c0 22.758-18.449 41.207-41.207 41.207z" fill="#7b727b"/><path d="m284.845 0h-30.905c22.758 0 41.207 18.449 41.207 41.207v429.586c0 22.758-18.449 41.207-41.207 41.207h30.905c22.758 0 41.207-18.449 41.207-41.207v-429.586c0-22.758-18.449-41.207-41.207-41.207z" fill="#686169"/><path d="m299.268 41.207v429.586c0 7.965-6.457 14.423-14.423 14.423h-220.459c-7.965 0-14.423-6.457-14.423-14.423v-429.586c0-7.965 6.457-14.423 14.423-14.423h41.314c2.402 0 4.538 1.524 5.32 3.795l5.444 15.816c.782 2.271 2.918 3.795 5.32 3.795h105.664c2.402 0 4.538-1.524 5.32-3.795l5.444-15.816c.782-2.271 2.918-3.795 5.32-3.795h41.314c7.964.001 14.422 6.458 14.422 14.423z" fill="#d6faec"/><g><path d="m284.845 26.785h-30.905c7.965 0 14.423 6.457 14.423 14.423v429.586c0 7.965-6.457 14.423-14.423 14.423h30.905c7.965 0 14.423-6.457 14.423-14.423v-429.587c0-7.965-6.458-14.422-14.423-14.422z" fill="#ccfae8"/></g><path d="m214.793 418.769h-80.354c-11.948 0-21.634-9.686-21.634-21.634s9.686-21.634 21.634-21.634h80.354c11.948 0 21.634 9.686 21.634 21.634s-9.686 21.634-21.634 21.634z" fill="#2fd9e7"/><path d="m468.598 329.143h-355.144c-11.169 0-20.223-9.054-20.223-20.223v-206.798c0-11.169 9.054-20.223 20.223-20.223h355.143c11.169 0 20.223 9.054 20.223 20.223v206.798c.001 11.169-9.053 20.223-20.222 20.223z" fill="#2fd9e7"/><path d="m468.598 81.899h-30.905c11.169 0 20.223 9.054 20.223 20.223v206.798c0 11.169-9.054 20.223-20.223 20.223h30.905c11.169 0 20.223-9.054 20.223-20.223v-206.798c0-11.168-9.054-20.223-20.223-20.223z" fill="#00d0e4"/><g fill="#f8f7f7"><path d="m384.571 232.459c-.025-.067-.052-.135-.079-.202-1.37-3.333-4.582-5.486-8.186-5.486h-.004c-3.605.001-6.819 2.158-8.187 5.494-.023.058-.047.116-.069.176l-22.374 58.746c-1.475 3.87.469 8.203 4.34 9.678.878.335 1.78.493 2.668.493 3.021 0 5.87-1.84 7.01-4.833l3.732-9.8h25.595l3.691 9.779c1.462 3.875 5.79 5.832 9.665 4.368 3.875-1.462 5.831-5.789 4.368-9.665zm-15.435 39.266 7.144-18.756 7.078 18.756z"/><path d="m451.185 228.136c-3.471-2.261-8.118-1.277-10.377 2.193l-12.601 19.357-12.744-19.517c-2.265-3.469-6.913-4.443-10.38-2.179-3.468 2.265-4.444 6.911-2.18 10.38l17.808 27.271-.07 28.195c-.01 4.143 3.34 7.509 7.481 7.519h.019c4.134 0 7.49-3.346 7.5-7.481l.07-28.221 17.667-27.14c2.26-3.472 1.278-8.118-2.193-10.377z"/><path d="m326.079 226.771h-16.591c-1.993 0-3.904.794-5.312 2.205-1.407 1.412-2.195 3.326-2.188 5.319v59.561c0 4.143 3.357 7.5 7.5 7.5s7.5-3.357 7.5-7.5v-18.422c3.324-.017 6.976-.033 9.091-.033 13.589 0 24.645-10.908 24.645-24.315s-11.056-24.315-24.645-24.315zm0 33.629c-2.101 0-5.707.016-9.016.032-.018-3.424-.033-7.188-.033-9.348 0-1.831-.01-5.729-.02-9.314h9.068c5.228 0 9.645 4.266 9.645 9.314.001 5.051-4.416 9.316-9.644 9.316z"/></g><path d="m209.642 175.646h-63.871c-3.414 0-6.181-2.767-6.181-6.181v-41.207c0-3.414 2.767-6.181 6.181-6.181h63.871c3.414 0 6.181 2.767 6.181 6.181v41.207c0 3.414-2.767 6.181-6.181 6.181z" fill="#ffe589"/><path d="m183.887 290.847h-90.656v-53.569h90.656c14.793 0 26.785 11.992 26.785 26.785 0 14.792-11.992 26.784-26.785 26.784z" fill="#ff6167"/><g fill="#f8f7f7"><path d="m282.171 133.191c-4.143 0-7.5 3.357-7.5 7.5v12.362c0 4.143 3.357 7.5 7.5 7.5s7.5-3.357 7.5-7.5v-12.362c0-4.142-3.358-7.5-7.5-7.5z"/><path d="m409.606 133.191c-4.143 0-7.5 3.357-7.5 7.5v12.362c0 4.143 3.357 7.5 7.5 7.5s7.5-3.357 7.5-7.5v-12.362c0-4.142-3.357-7.5-7.5-7.5z"/><path d="m437.148 133.191c-4.143 0-7.5 3.357-7.5 7.5v12.362c0 4.143 3.357 7.5 7.5 7.5s7.5-3.357 7.5-7.5v-12.362c0-4.142-3.357-7.5-7.5-7.5z"/><path d="m322.161 133.191c-4.143 0-7.5 3.357-7.5 7.5v12.362c0 4.143 3.357 7.5 7.5 7.5s7.5-3.357 7.5-7.5v-12.362c0-4.142-3.357-7.5-7.5-7.5z"/><path d="m351.353 133.191c-4.143 0-7.5 3.357-7.5 7.5v12.362c0 4.143 3.357 7.5 7.5 7.5s7.5-3.357 7.5-7.5v-12.362c0-4.142-3.358-7.5-7.5-7.5z"/></g></g></svg>
                                </div>
                            </div>

                            <!-- Slide 3: Mobile App for Team -->
                            <div class="sln-calendar-carousel__slide" data-slide-id="mobile-app">
                                <div class="sln-calendar-carousel__content">
                                    <h3 class="sln-calendar-carousel__headline"><?php esc_html_e( 'Staff can\'t manage bookings on the go?', 'salon-booking-system' ); ?></h3>
                                    <ul class="sln-calendar-carousel__benefits">
                                        <li><?php esc_html_e( 'Mobile Web App for iOS & Android', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Staff view schedules from anywhere', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Real-time booking notifications', 'salon-booking-system' ); ?></li>
                                    </ul>
                                    <p class="sln-calendar-carousel__proof"><?php esc_html_e( 'Manage your salon from your phone', 'salon-booking-system' ); ?></p>
                                    <a href="<?php echo esc_url( SLN_STORE_URL . '/homepage/plugin-pricing/' ); ?>" target="_blank" class="sln-calendar-carousel__cta">
                                        <?php esc_html_e( 'See All PRO Features', 'salon-booking-system' ); ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                                <div class="sln-calendar-carousel__illustration">
                                    <svg id="Layer_1" enable-background="new 0 0 512 512" height="150" viewBox="0 0 512 512" width="150" xmlns="http://www.w3.org/2000/svg"><g><path d="m424.316 445.018c47.219-44.66 76.685-107.896 76.685-178.019 0-135.31-109.691-245.001-245.001-245.001s-245.001 109.691-245.001 245.001 109.691 245.001 245.001 245.001c10.603 0 21.049-.675 31.297-1.981z" fill="#816ae2"/><path d="m396.836 365.563c-2.15-7.901-2.908-16.116-2.24-24.277l4.001-37.685c1.953-23.645-1.651-47.335-12.118-68.626-27.045-55.013-20.841-59.005-21.681-113.604 0-11.153-9.042-20.196-20.196-20.196-11.154 0-20.196 9.042-20.196 20.196 0 222.032.413 205.739-.956 212.249v29.074c0 24.496-19.929 44.426-44.425 44.426-16.269 0-72.374 0-110.552 0 16.984 23.62 40.219 42.114 67.183 53.313.167.069.334.139.501.21 20.833 8.799 44.094 26.357 47.981 48.142l.376 1.556c54.861-6.36 104.192-30.834 141.86-67.297z" fill="#f9ba8f"/><path d="m93.463 101.881-6.151-5.699c-9.483-8.786-24.43-8.216-33.216 1.266-8.786 9.483-8.216 24.43 1.266 33.216l38.101 35.303z" fill="#f9ba8f"/><path d="m67.863 112.997c-5.817-5.39-8.264-13.096-7.271-20.439-2.374 1.215-4.585 2.829-6.496 4.89-8.786 9.483-8.216 24.43 1.266 33.216l38.101 35.303v-29.249z" fill="#fcad6d"/><g><path d="m398.676 370.388c-8.342 57.526-57.852 101.719-117.69 101.719-10.331 0-20.353-1.319-29.909-3.794 15.843 9.687 30.095 23.845 33.061 40.471l.376 1.556c54.861-6.36 104.191-30.834 141.86-67.297z" fill="#fd995b"/></g><g><path d="m289.615 408.075h-162.235c-19.185 0-34.792-15.608-34.792-34.793v-338.489c0-19.185 15.607-34.793 34.792-34.793h162.235c19.185 0 34.792 15.608 34.792 34.793v338.49c0 19.184-15.608 34.792-34.792 34.792z" fill="#2a428c"/></g><path d="m127.38 0c-19.185 0-34.792 15.608-34.792 34.792v338.49c0 19.185 15.608 34.793 34.792 34.793h48.364c-10.734-10.95 1.786-21.647 1.786-21.647-17.667-4.635-16.167-23.202-16.167-23.202s0-283.746 0-301.496 18.03-22.233 18.03-22.233c-7.102-7.102 0-14.565 0-14.565v-12.025c-6.551-4.152-5.261-9.805-3.835-12.907z" fill="#142766"/><g><path d="m182.867 24.933h-3.474c-3.321 0-6.013-2.692-6.013-6.013s2.692-6.013 6.013-6.013h3.474c3.321 0 6.013 2.692 6.013 6.013s-2.692 6.013-6.013 6.013z" fill="#3c58a0"/></g><g><path d="m236.771 24.933h-33.382c-3.321 0-6.013-2.692-6.013-6.013s2.692-6.013 6.013-6.013h33.382c3.321 0 6.013 2.692 6.013 6.013s-2.692 6.013-6.013 6.013z" fill="#3c58a0"/></g><path d="m289.615 21.646h-24.383c-4.546 0-8.23 3.685-8.23 8.23 0 5.291-4.329 9.621-9.62 9.621h-77.767c-5.291 0-9.621-4.329-9.621-9.621 0-4.545-3.685-8.23-8.23-8.23h-24.384c-7.26 0-13.146 5.886-13.146 13.146v338.49c0 7.26 5.886 13.146 13.146 13.146h162.235c7.26 0 13.146-5.886 13.146-13.146 0-34.819 0-329.197 0-338.49 0-7.26-5.886-13.146-13.146-13.146z" fill="#73c3f9"/><path d="m177.529 386.429c-2.5-10.787 10.012-13.307 10.012-13.307s7.958-69.709 0-77.667-0-15.473 0-15.473 8.694-68.972 0-77.667c-8.694-8.694 0-15.473 0-15.473v-77.667c-15.572-9.832-7.381-23.979-7.381-23.979v-22.275c-11.767-11.767-.772-23.419-.768-23.423h-9.778c-5.291 0-9.621-4.329-9.621-9.621 0-4.545-3.685-8.23-8.23-8.23h-24.383c-7.26 0-13.146 5.885-13.146 13.146v338.491c0 7.26 5.886 13.146 13.146 13.146h50.149z" fill="#4fabf7"/><g><path d="m159.588 85.195h97.819c6.151 0 11.137-4.986 11.137-11.137s-4.986-11.137-11.137-11.137h-97.819c-6.151 0-11.138 4.986-11.138 11.137 0 6.152 4.987 11.137 11.138 11.137z" fill="#e9efff"/></g><path d="m159.588 62.921h20.572v22.275h-20.572c-6.151 0-11.137-4.986-11.137-11.137-.001-6.153 4.986-11.138 11.137-11.138z" fill="#9bd8f9"/><g fill="#e9efff"><path d="m277.174 186.841h-137.353c-5.51 0-10.018-4.508-10.018-10.018v-57.631c0-5.51 4.508-10.018 10.018-10.018h137.353c5.51 0 10.018 4.508 10.018 10.018v57.631c0 5.51-4.508 10.018-10.018 10.018z"/><path d="m277.174 279.981h-137.353c-5.51 0-10.018-4.508-10.018-10.018v-57.631c0-5.51 4.508-10.018 10.018-10.018h137.353c5.51 0 10.018 4.508 10.018 10.018v57.631c0 5.51-4.508 10.018-10.018 10.018z"/><path d="m277.174 373.121h-137.353c-5.51 0-10.018-4.508-10.018-10.018v-57.631c0-5.51 4.508-10.018 10.018-10.018h137.353c5.51 0 10.018 4.508 10.018 10.018v57.631c0 5.51-4.508 10.018-10.018 10.018z"/><path d="m277.174 186.841h-137.353c-5.51 0-10.018-4.508-10.018-10.018v-57.631c0-5.51 4.508-10.018 10.018-10.018h137.353c5.51 0 10.018 4.508 10.018 10.018v57.631c0 5.51-4.508 10.018-10.018 10.018z"/><path d="m277.174 279.981h-137.353c-5.51 0-10.018-4.508-10.018-10.018v-57.631c0-5.51 4.508-10.018 10.018-10.018h137.353c5.51 0 10.018 4.508 10.018 10.018v57.631c0 5.51-4.508 10.018-10.018 10.018z"/><path d="m277.174 373.121h-137.353c-5.51 0-10.018-4.508-10.018-10.018v-57.631c0-5.51 4.508-10.018 10.018-10.018h137.353c5.51 0 10.018 4.508 10.018 10.018v57.631c0 5.51-4.508 10.018-10.018 10.018z"/></g><path d="m177.523 176.823v-57.63c0-5.51 4.508-10.018 10.018-10.018h-47.72c-5.51 0-10.018 4.508-10.018 10.018v57.63c0 5.51 4.508 10.018 10.018 10.018h47.72c-5.51 0-10.018-4.508-10.018-10.018z" fill="#d3dcfb"/><path d="m177.523 269.963v-57.631c0-5.51 4.508-10.018 10.018-10.018h-47.72c-5.51 0-10.018 4.508-10.018 10.018v57.631c0 5.51 4.508 10.018 10.018 10.018h47.72c-5.51 0-10.018-4.508-10.018-10.018z" fill="#d3dcfb"/><path d="m177.523 363.103v-57.631c0-5.51 4.508-10.018 10.018-10.018h-47.72c-5.51 0-10.018 4.508-10.018 10.018v57.631c0 5.51 4.508 10.018 10.018 10.018h47.72c-5.51 0-10.018-4.508-10.018-10.018z" fill="#d3dcfb"/><g><path d="m203.082 141.387h-40.169c-4.142 0-7.5-3.357-7.5-7.5s3.358-7.5 7.5-7.5h40.169c4.142 0 7.5 3.357 7.5 7.5 0 4.142-3.358 7.5-7.5 7.5z" fill="#3c58a0"/></g><g><path d="m254.082 169.629h-91.169c-4.142 0-7.5-3.357-7.5-7.5s3.358-7.5 7.5-7.5h91.169c4.142 0 7.5 3.357 7.5 7.5s-3.358 7.5-7.5 7.5z" fill="#bec8f7"/></g><g><path d="m234.415 141.387h-5.667c-4.142 0-7.5-3.357-7.5-7.5s3.358-7.5 7.5-7.5h5.667c4.142 0 7.5 3.357 7.5 7.5 0 4.142-3.357 7.5-7.5 7.5z" fill="#bec8f7"/></g><g><path d="m203.082 234.527h-40.169c-4.142 0-7.5-3.357-7.5-7.5s3.358-7.5 7.5-7.5h40.169c4.142 0 7.5 3.357 7.5 7.5s-3.358 7.5-7.5 7.5z" fill="#3c58a0"/></g><g><path d="m254.082 262.769h-91.169c-4.142 0-7.5-3.357-7.5-7.5s3.358-7.5 7.5-7.5h91.169c4.142 0 7.5 3.357 7.5 7.5 0 4.142-3.358 7.5-7.5 7.5z" fill="#bec8f7"/></g><g><path d="m234.415 234.527h-5.667c-4.142 0-7.5-3.357-7.5-7.5s3.358-7.5 7.5-7.5h5.667c4.142 0 7.5 3.357 7.5 7.5s-3.357 7.5-7.5 7.5z" fill="#bec8f7"/></g><g><path d="m203.082 327.667h-40.169c-4.142 0-7.5-3.358-7.5-7.5 0-4.143 3.358-7.5 7.5-7.5h40.169c4.142 0 7.5 3.357 7.5 7.5s-3.358 7.5-7.5 7.5z" fill="#3c58a0"/></g><g><path d="m254.082 355.909h-91.169c-4.142 0-7.5-3.357-7.5-7.5s3.358-7.5 7.5-7.5h91.169c4.142 0 7.5 3.357 7.5 7.5s-3.358 7.5-7.5 7.5z" fill="#bec8f7"/></g><g><path d="m234.415 327.667h-5.667c-4.142 0-7.5-3.358-7.5-7.5 0-4.143 3.358-7.5 7.5-7.5h5.667c4.142 0 7.5 3.357 7.5 7.5s-3.357 7.5-7.5 7.5z" fill="#bec8f7"/></g><path d="m127.669 225.842c-7.731 8.343-20.761 8.84-29.104 1.109l-34.497-31.964c-8.343-7.73-8.84-20.761-1.109-29.104 7.731-8.343 20.761-8.84 29.104-1.109l34.497 31.964c8.343 7.731 8.84 20.761 1.109 29.104z" fill="#f9ba8f"/><path d="m112.757 212.422-34.497-31.964c-5.77-5.346-7.781-13.226-5.92-20.338-3.478 1.004-6.741 2.915-9.381 5.764-7.731 8.343-7.234 21.374 1.109 29.104l34.497 31.964c8.343 7.73 21.373 7.234 29.104-1.109 2.384-2.573 3.98-5.594 4.81-8.766-6.707 1.935-14.228.436-19.722-4.655z" fill="#fcad6d"/><g><path d="m127.669 338.191c-7.731 8.343-20.761 8.84-29.104 1.109l-34.497-31.964c-8.343-7.73-8.84-20.761-1.109-29.104 7.731-8.343 20.761-8.84 29.104-1.109l34.497 31.964c8.343 7.73 8.84 20.761 1.109 29.104z" fill="#f9ba8f"/></g><path d="m112.757 324.77-34.497-31.964c-5.77-5.346-7.781-13.226-5.92-20.338-3.478 1.004-6.741 2.915-9.381 5.764-7.731 8.343-7.234 21.374 1.109 29.104l34.497 31.964c8.343 7.73 21.373 7.234 29.104-1.109 2.384-2.573 3.98-5.594 4.81-8.766-6.707 1.936-14.228.436-19.722-4.655z" fill="#fcad6d"/><path d="m127.669 282.017c-7.731 8.343-20.761 8.84-29.104 1.109l-34.497-31.964c-8.343-7.73-8.84-20.761-1.109-29.104 7.731-8.343 20.761-8.84 29.104-1.109l34.497 31.964c8.343 7.73 8.84 20.76 1.109 29.104z" fill="#f9ba8f"/><path d="m112.757 268.596-34.497-31.964c-5.77-5.346-7.781-13.226-5.92-20.338-3.478 1.004-6.742 2.915-9.381 5.764-7.731 8.343-7.234 21.373 1.109 29.104l34.497 31.964c8.343 7.731 21.373 7.234 29.104-1.109 2.384-2.573 3.98-5.594 4.81-8.766-6.707 1.936-14.228.436-19.722-4.655z" fill="#fcad6d"/><path d="m422.068 86.887c-13.299 16.916-25.301 41.22-15.706 67.742 18 49.753 46.667 91.99 33.333 165.538-6.05 33.372 13.803 47.361 37.296 52.687 15.379-32.042 24.009-67.938 24.009-105.855.001-71.228-30.405-135.345-78.932-180.112z" fill="#9181f2"/></g></svg>
                                </div>
                            </div>

                            <!-- Slide 4: Sell Service Packages -->
                            <div class="sln-calendar-carousel__slide" data-slide-id="service-packages">
                                <div class="sln-calendar-carousel__content">
                                    <h3 class="sln-calendar-carousel__headline"><?php esc_html_e( 'Want to sell memberships and package deals?', 'salon-booking-system' ); ?></h3>
                                    <ul class="sln-calendar-carousel__benefits">
                                        <li><?php esc_html_e( 'Create bundled service packages', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Offer membership programs', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Increase revenue with recurring bookings', 'salon-booking-system' ); ?></li>
                                    </ul>
                                    <p class="sln-calendar-carousel__proof"><?php esc_html_e( 'Boost revenue by 40% with packages', 'salon-booking-system' ); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=salon-extensions'); ?>" class="sln-calendar-carousel__cta">
                                        <?php esc_html_e( 'Discover Package Solutions', 'salon-booking-system' ); ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                                <div class="sln-calendar-carousel__illustration">
                                    <svg id="Layer_1" enable-background="new 0 0 66 66" viewBox="0 0 66 66" height="150" width="150" xmlns="http://www.w3.org/2000/svg"><g><g><g><g><g><path d="m13.59 10.95c-.94.48-2.2.26-3.29-.57-1.45-1.09-2.9-3.53-2.72-5.57.1-1.11.67-1.96 1.65-2.46l.47.93c-.66.34-1.01.87-1.08 1.62-.14 1.6 1.1 3.73 2.31 4.65.87.65 1.72.71 2.19.47z" fill="#f93d4a"/></g></g><g><g><g><g><g><g><path d="m12.24 8.19-.22-.06.08-1.33-2.28 1.16c-.35.18-.63.48-.8.92l-1.78 4.6c-.3.78-.2 1.83.28 2.77l6 11.84c.77 1.51 2.23 2.3 3.28 1.77l6.54-3.31c1.04-.53 1.27-2.18.51-3.69l-2.69-5.31c-5.52 1.15-9.76-4.48-8.92-9.36z" fill="#0c5bbd"/></g><g><g><path d="m9.79 15.1 6 11.84c.77 1.51 2.24 2.3 3.28 1.77l6.54-3.32c1.04-.53 1.28-2.18.52-3.69l-6-11.84c-.48-.94-1.26-1.64-2.07-1.86l-4.77-1.28c-.9-.24-1.67.15-2.01 1.02l-1.78 4.6c-.08.2-.12.41-.15.63-.07.66.08 1.43.44 2.13zm5.13-5.62c.42.83.23 1.77-.42 2.1s-1.52-.08-1.94-.91-.23-1.77.42-2.1 1.52.08 1.94.91z" fill="#1685f6"/></g></g></g><g><path d="m14.93 9.48c.42.83.23 1.77-.42 2.1-.25.12-.52.15-.8.08.17-.43.15-.99-.12-1.5-.26-.51-.69-.86-1.14-.98.11-.26.29-.47.53-.6.65-.34 1.53.07 1.95.9z" fill="#0c5bbd"/></g></g></g></g></g><g><g><path d="m13.59 10.95c-.31.16-.78-.78-.47-.93.43-.22.93-.89.92-2.05-.02-1.51-1.01-3.77-2.38-4.61-.66-.39-1.3-.41-1.96-.08-.3.16-.78-.77-.47-.93.98-.5 2-.45 2.96.12 1.75 1.06 2.86 3.67 2.89 5.48.02 1.38-.55 2.53-1.49 3z" fill="#f93d4a"/></g></g></g><g><path d="m16.19 19.52c-.32.16-.64.21-.95.14s-.6-.23-.87-.48-.49-.56-.69-.94c-.19-.38-.31-.75-.35-1.11-.05-.36 0-.69.12-.98.13-.29.35-.52.68-.69.32-.16.64-.21.95-.14s.6.23.87.48.49.57.69.94c.19.38.31.75.36 1.11.04.36 0 .69-.12.98-.15.29-.37.52-.69.69zm-.38-.74c.27-.14.41-.37.43-.69s-.07-.67-.25-1.03-.41-.63-.68-.81-.54-.2-.81-.06-.41.37-.43.69.07.66.25 1.03c.18.36.41.63.68.81.27.17.54.19.81.06zm2.49 4.9c-.08.04-.17.04-.27 0s-.17-.11-.23-.22c-.03-.07-.05-.14-.05-.22l-.01-9.28c0-.15.05-.26.16-.31.09-.05.18-.05.27-.02s.16.1.21.21c.04.08.06.18.06.28l.02 9.22c0 .07-.01.13-.04.2-.02.06-.06.11-.12.14zm3.76-1.79c-.32.16-.64.21-.95.14s-.6-.23-.87-.48c-.26-.25-.49-.56-.69-.94-.19-.38-.31-.75-.36-1.11-.04-.36 0-.69.12-.98s.35-.52.68-.69c.32-.16.64-.21.95-.14s.6.23.87.48.49.57.69.94c.19.38.31.75.36 1.11.04.36 0 .69-.12.98-.13.29-.35.52-.68.69zm-.37-.74c.27-.14.41-.37.43-.69s-.07-.67-.25-1.03-.41-.63-.68-.81-.54-.2-.81-.06-.41.37-.43.69.07.67.25 1.03.41.63.68.81c.27.17.54.19.81.06z" fill="#fff"/></g></g><g><g><g><path d="m2.7 46.89h14.73c.6 0 1.15-.3 1.48-.8l5.49-8.33h-16.96l-5.32 8.06c-.3.46.03 1.07.58 1.07z" fill="#e7660a"/></g><g><path d="m5.88 46.89h11.55c.6 0 1.15-.3 1.48-.8l5.49-8.33h-16.96z" fill="#a54300"/></g><g><g><path d="m63.88 29.71-5.31 8.06h-35.54l5.49-8.33c.33-.5.88-.8 1.48-.8h33.3c.55 0 .88.61.58 1.07z" fill="#d15602"/></g><g><path d="m50.94 28.64-1.92 2.11c-1.1 1.2-2.58 1.73-3.56 1.37 0 0-9.58-3.36-9.91-3.48z" fill="#a54300"/></g><g><g><g><path d="m49.91 14.75c-.89-.46-1.43-1.55-1.4-2.86.04-1.73 1.11-4.21 2.78-5.2.91-.54 1.89-.58 2.82-.1l-.45.88c-.63-.32-1.23-.3-1.85.07-1.31.78-2.26 2.93-2.29 4.37-.02 1.03.42 1.72.86 1.95z" fill="#ffc608"/></g></g><g><g><g><g><g><g><path d="m51.28 12.17-.08-.2 1.07-.68-2.17-1.11c-.33-.17-.73-.22-1.15-.11l-4.53 1.19c-.77.2-1.52.87-1.98 1.76l-5.78 11.23c-.74 1.43-.53 3 .47 3.51l6.2 3.19c.99.51 2.39-.23 3.12-1.66l2.59-5.04c-3.96-3.59-1.99-10 2.24-12.08z" fill="#d10010"/></g><g><g><path d="m44.59 14.14-5.78 11.23c-.74 1.43-.52 3 .47 3.51l6.2 3.19c.99.51 2.39-.23 3.13-1.66l5.78-11.23c.46-.89.57-1.89.28-2.63l-1.67-4.39c-.31-.83-1.05-1.2-1.9-.98l-4.53 1.19c-.2.05-.38.14-.57.24-.56.31-1.07.86-1.41 1.53zm7.2.82c-.41.79-1.24 1.17-1.86.85s-.79-1.21-.38-2 1.24-1.17 1.85-.85c.62.31.79 1.21.39 2z" fill="#f93d4a"/></g></g></g><g><path d="m51.79 14.96c-.41.79-1.24 1.17-1.86.85-.23-.12-.4-.32-.5-.57.43-.11.84-.44 1.09-.93s.28-1.02.12-1.43c.26-.06.52-.04.76.08.62.32.79 1.21.39 2z" fill="#d10010"/></g></g></g><g><g><g><path d="m47.4 25.85c-.59 1.15-1.8 1.76-2.85 1.51l-.28.54c-.15.28-.47.41-.71.28s-.33-.46-.18-.75l.28-.54c-.81-.72-1.02-2.04-.42-3.2.15-.28.47-.41.71-.28s.33.46.18.75c-.39.76-.18 1.65.48 1.99s1.51 0 1.9-.77c.39-.76.17-1.65-.49-1.99-.03-.02-.06-.04-.09-.05-1.08-.62-1.42-2.13-.76-3.42.59-1.15 1.79-1.75 2.85-1.51l.28-.54c.15-.28.47-.41.71-.28s.33.46.18.75l-.28.54c.82.72 1.02 2.05.43 3.19-.15.29-.47.41-.71.28s-.33-.46-.18-.75c.39-.76.17-1.65-.49-1.99s-1.51.01-1.9.76c-.39.76-.18 1.65.48 1.99 1.16.61 1.54 2.17.86 3.49z" fill="#fff"/></g></g></g></g></g><g><g><g><path d="m49.91 14.75c-.29-.15.16-1.03.45-.88.41.21 1.2.22 2.09-.43 1.15-.86 2.35-2.88 2.22-4.4-.06-.72-.39-1.23-1.02-1.55-.29-.15.16-1.03.45-.88.93.48 1.47 1.29 1.56 2.35.16 1.94-1.23 4.25-2.62 5.28-1.04.77-2.24.97-3.13.51z" fill="#ffc608"/></g></g></g></g><g><path d="m58.56 37.77v24.17c0 1.13-.92 2.05-2.05 2.05h-31.43c-1.13 0-2.05-.92-2.05-2.05v-24.17z" fill="#e7660a"/></g><g><path d="m41.6 37.76 3.81 9.95c.41 1.06 1.42 1.76 2.55 1.76h10.6v-11.71z" fill="#a54300"/></g><g><path d="m41.6 37.77v26.23h-32.11c-1.13 0-2.05-.92-2.05-2.05v-24.18z" fill="#fc7612"/></g><g><path d="m7.44 37.77h34.16v.78h-34.16z" fill="#d15602"/></g><g><path d="m63.3 46.89h-14.72c-.6 0-1.15-.3-1.48-.8l-5.49-8.33h16.96l5.32 8.06c.29.46-.04 1.07-.59 1.07z" fill="#fc7612"/></g><g><path d="m2.7 28.64h31.93c.6 0 1.15.3 1.48.8l5.49 8.33h-34.16l-5.32-8.07c-.3-.45.03-1.06.58-1.06z" fill="#fc7612"/></g></g></g><g><g><g><path d="m48.94 61.19c0 .28.19.51.41.51h6.61c.23 0 .41-.23.41-.51 0-.28-.19-.51-.41-.51h-6.61c-.23-.01-.41.22-.41.51z" fill="#fff"/></g></g><g><path d="m56.38 58.83c0 .28-.19.51-.41.51h-4.15c-.23 0-.41-.23-.41-.51 0-.28.19-.51.41-.51h4.15c.22-.01.41.22.41.51z" fill="#fff"/></g></g><g fill="#fff"><path d="m12.24 57.79h-.55v3.86c0 .1-.07.17-.17.17h-1.02c-.1 0-.17-.07-.17-.17v-3.86h-.55c-.15 0-.23-.18-.12-.29l1.23-1.22c.06-.06.17-.06.24 0l1.23 1.22c.1.11.03.29-.12.29z"/><path d="m16.13 57.79h-.55v3.86c0 .1-.07.17-.17.17h-1.01c-.1 0-.17-.07-.17-.17v-3.86h-.55c-.15 0-.23-.18-.12-.29l1.23-1.22c.06-.06.17-.06.24 0l1.23 1.22c.09.11.02.29-.13.29z"/></g></g><g fill="#ffc608"><path d="m32.57 9.94v2.75c0 .38-.3.68-.68.68s-.68-.3-.68-.68v-2.75c0-.3.19-.55.45-.65.07-.03.15-.04.23-.04.37 0 .68.31.68.69z"/><path d="m27.82 14.46c-.11.06-.23.09-.34.09-.24 0-.47-.12-.59-.34l-1.38-2.38c-.16-.28-.1-.64.14-.85.03-.03.07-.06.12-.08.11-.06.23-.09.34-.09.24 0 .47.12.59.34l1.21 2.1.17.28c.18.32.07.74-.26.93z"/><path d="m38.35 11.49c0 .12-.03.24-.09.34l-1.29 2.24-.08.14c-.19.33-.61.44-.94.25-.22-.13-.34-.36-.34-.59 0-.12.03-.23.09-.34l1.38-2.38c.19-.33.61-.44.94-.25.08.05.14.1.19.17.08.11.14.27.14.42z"/></g><g fill="#d69f05"><path d="m27.82 14.46c-.11.06-.23.09-.34.09-.24 0-.47-.12-.59-.34l-1.38-2.38c-.16-.28-.1-.64.14-.85-.11.38-.01.81.24 1.12.15.18.36.32.5.51.15.19.22.43.3.66s.19.46.38.61.48.19.67.03c.12-.19.19-.43.17-.66l.17.28c.18.32.07.74-.26.93z"/><path d="m32.57 12.54v.15c0 .38-.3.68-.68.68s-.68-.3-.68-.68v-2.75c0-.3.19-.55.45-.65l.14.13c-.34.27-.51.74-.43 1.16.05.24.16.46.18.7.03.24-.03.48-.07.72-.05.24-.06.49.02.72.09.23.32.41.56.36.22-.12.4-.32.51-.54z"/><path d="m38.2 11.09c-.44.02-.85.3-1.04.68-.1.22-.15.46-.26.67-.12.21-.31.37-.48.54s-.34.36-.4.59c-.06.24.02.52.24.63.24.04.5-.01.72-.14l-.08.14c-.19.33-.61.44-.94.25-.22-.13-.34-.36-.34-.59 0-.12.03-.23.09-.34l1.38-2.38c.19-.33.61-.44.94-.25.08.05.14.1.19.17z"/></g></g></svg>
                                </div>
                            </div>

                            <!-- Slide 5: Manage Multiple Locations -->
                            <div class="sln-calendar-carousel__slide" data-slide-id="multi-location">
                                <div class="sln-calendar-carousel__content">
                                    <h3 class="sln-calendar-carousel__headline"><?php esc_html_e( 'Juggling bookings across multiple salon locations?', 'salon-booking-system' ); ?></h3>
                                    <ul class="sln-calendar-carousel__benefits">
                                        <li><?php esc_html_e( 'Centralized multi-location management', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Auto-route customers to nearest branch', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Unified reporting across all locations', 'salon-booking-system' ); ?></li>
                                    </ul>
                                    <p class="sln-calendar-carousel__proof"><?php esc_html_e( 'Scale from 1 to 50+ locations', 'salon-booking-system' ); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=salon-extensions'); ?>" class="sln-calendar-carousel__cta">
                                        <?php esc_html_e( 'Explore Multi-Location Tools', 'salon-booking-system' ); ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                                <div class="sln-calendar-carousel__illustration">
                                    <svg id="Flat" viewBox="0 0 64 64" height="150" width="150" xmlns="http://www.w3.org/2000/svg"><path d="m22 1h20v4h-20z" fill="#f57182"/><path d="m24 3h18v2h-18z" fill="#f05a7a"/><path d="m19 8h26v19h-26z" fill="#8cd4fe"/><path d="m20.5 8h24.5v19h-24.5z" fill="#7dc3fa"/><path d="m19 13.247a3.713 3.713 0 0 0 3.667-.561 3.75 3.75 0 0 0 4.666 0 3.752 3.752 0 0 0 4.667 0 3.752 3.752 0 0 0 4.667 0 3.75 3.75 0 0 0 4.666 0 3.713 3.713 0 0 0 3.667.561v-5.247h-26z" fill="#7dc3fa"/><path d="m20.5 13.485a3.807 3.807 0 0 0 2.167-.8 3.75 3.75 0 0 0 4.666 0 3.752 3.752 0 0 0 4.667 0 3.752 3.752 0 0 0 4.667 0 3.75 3.75 0 0 0 4.666 0 3.713 3.713 0 0 0 3.667.562v-5.247h-24.5z" fill="#72b4f7"/><path d="m31.25 27h1.5v19.75h-1.5z" fill="#7f7f83"/><path d="m32 46.803-21.968-8.369.534-1.402 21.434 8.165 21.434-8.165.534 1.402z" fill="#7f7f83"/><path d="m17 5h30v3h-30z" fill="#8cd4fe"/><path d="m19 6.5h28v1.5h-28z" fill="#7dc3fa"/><path d="m22 15h6v12h-6z" fill="#f6faff"/><path d="m28 14.25h-6a.75.75 0 0 0 -.75.75v12h1.5v-11.25h4.5v11.25h1.5v-12a.75.75 0 0 0 -.75-.75z" fill="#f05a7a"/><path d="m31 15h11v8h-11z" fill="#f6faff"/><path d="m42 23.75h-11a.75.75 0 0 1 -.75-.75v-8a.75.75 0 0 1 .75-.75h11a.75.75 0 0 1 .75.75v8a.75.75 0 0 1 -.75.75zm-10.25-1.5h9.5v-6.5h-9.5z" fill="#f05a7a"/><path d="m47 27.75h-30a.75.75 0 0 1 0-1.5h30a.75.75 0 0 1 0 1.5z" fill="#89898c"/><path d="m18 8h4.667a0 0 0 0 1 0 0v1.667a2.333 2.333 0 0 1 -2.334 2.333 2.333 2.333 0 0 1 -2.333-2.333v-1.667a0 0 0 0 1 0 0z" fill="#f6faff"/><path d="m22.667 8h4.667a0 0 0 0 1 0 0v1.667a2.333 2.333 0 0 1 -2.334 2.333 2.333 2.333 0 0 1 -2.333-2.333v-1.667a0 0 0 0 1 0 0z" fill="#f05a7a"/><path d="m27.333 8h4.667a0 0 0 0 1 0 0v1.667a2.333 2.333 0 0 1 -2.333 2.333 2.333 2.333 0 0 1 -2.333-2.333v-1.667a0 0 0 0 1 -.001 0z" fill="#f6faff"/><path d="m32 8h4.667a0 0 0 0 1 0 0v1.667a2.333 2.333 0 0 1 -2.334 2.333 2.333 2.333 0 0 1 -2.333-2.333v-1.667a0 0 0 0 1 0 0z" fill="#f05a7a"/><path d="m36.667 8h4.667a0 0 0 0 1 0 0v1.667a2.333 2.333 0 0 1 -2.334 2.333 2.333 2.333 0 0 1 -2.333-2.333v-1.667a0 0 0 0 1 0 0z" fill="#f6faff"/><path d="m41.333 8h4.667a0 0 0 0 1 0 0v1.667a2.333 2.333 0 0 1 -2.333 2.333 2.333 2.333 0 0 1 -2.333-2.333v-1.667a0 0 0 0 1 -.001 0z" fill="#f05a7a"/><ellipse cx="11" cy="52" fill="#d2f182" rx="6" ry="3"/><path d="m7 53a2.067 2.067 0 0 0 .881 1.559 10.773 10.773 0 0 0 3.119.441c3.314 0 6-1.343 6-3a2.067 2.067 0 0 0 -.881-1.559 10.773 10.773 0 0 0 -3.119-.441c-3.314 0-6 1.343-6 3z" fill="#bee86d"/><path d="m11 30a8 8 0 0 0 -8 8c0 6 8 14 8 14s8-8 8-14a8 8 0 0 0 -8-8z" fill="#f57182"/><path d="m5 38c0 4.683 4.861 10.568 7 12.933 2.139-2.365 7-8.25 7-12.933a7.989 7.989 0 0 0 -3.454-6.579 7.977 7.977 0 0 0 -2.546-.421c-4.418 0-8 2.582-8 7z" fill="#f05a7a"/><circle cx="11" cy="38" fill="#f6faff" r="3"/><ellipse cx="32" cy="60" fill="#d2f182" rx="6" ry="3"/><path d="m28 61a2.067 2.067 0 0 0 .881 1.559 10.773 10.773 0 0 0 3.119.441c3.314 0 6-1.343 6-3a2.067 2.067 0 0 0 -.881-1.559 10.773 10.773 0 0 0 -3.119-.441c-3.314 0-6 1.343-6 3z" fill="#bee86d"/><path d="m32 38a8 8 0 0 0 -8 8c0 6 8 14 8 14s8-8 8-14a8 8 0 0 0 -8-8z" fill="#f57182"/><path d="m26 46c0 4.683 4.861 10.568 7 12.933 2.139-2.365 7-8.25 7-12.933a7.989 7.989 0 0 0 -3.454-6.579 7.977 7.977 0 0 0 -2.546-.421c-4.418 0-8 2.582-8 7z" fill="#f05a7a"/><circle cx="32" cy="46" fill="#f6faff" r="3"/><ellipse cx="53" cy="52" fill="#d2f182" rx="6" ry="3"/><path d="m49 53a2.067 2.067 0 0 0 .881 1.559 10.773 10.773 0 0 0 3.119.441c3.314 0 6-1.343 6-3a2.067 2.067 0 0 0 -.881-1.559 10.773 10.773 0 0 0 -3.119-.441c-3.314 0-6 1.343-6 3z" fill="#bee86d"/><path d="m53 30a8 8 0 0 0 -8 8c0 6 8 14 8 14s8-8 8-14a8 8 0 0 0 -8-8z" fill="#f57182"/><path d="m47 38c0 4.683 4.861 10.568 7 12.933 2.139-2.365 7-8.25 7-12.933a7.989 7.989 0 0 0 -3.454-6.579 7.977 7.977 0 0 0 -2.546-.421c-4.418 0-8 2.582-8 7z" fill="#f05a7a"/><circle cx="53" cy="38" fill="#f6faff" r="3"/></svg>
                                </div>
                            </div>

                            <!-- Slide 6: Discover All Add-ons -->
                            <div class="sln-calendar-carousel__slide" data-slide-id="all-addons">
                                <div class="sln-calendar-carousel__content">
                                    <h3 class="sln-calendar-carousel__headline"><?php esc_html_e( 'Missing tools to enhance your daily job?', 'salon-booking-system' ); ?></h3>
                                    <ul class="sln-calendar-carousel__benefits">
                                        <li><?php esc_html_e( '22 payment gateways for global reach', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( '10 SMS & notification providers', 'salon-booking-system' ); ?></li>
                                        <li><?php esc_html_e( 'Calendar sync, multi-location, analytics & more', 'salon-booking-system' ); ?></li>
                                    </ul>
                                    <p class="sln-calendar-carousel__proof"><?php esc_html_e( 'Everything you need to run a professional salon', 'salon-booking-system' ); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=salon-extensions'); ?>" class="sln-calendar-carousel__cta">
                                        <?php esc_html_e( 'Explore All Add-ons', 'salon-booking-system' ); ?>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                                <div class="sln-calendar-carousel__illustration sln-calendar-carousel__illustration--number">
                                    <div class="sln-calendar-carousel__number">
                                        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 499.54 499.54" style="enable-background:new 0 0 499.54 499.54;" xml:space="preserve" width="80" height="80">
                                            <g><g><g><rect x="-37.514" y="246.798" transform="matrix(0.7071 -0.7071 0.7071 0.7071 -137.9861 235.4681)" style="fill:#93B2F4;" width="505.511" height="75"/></g><g><rect x="289.081" y="111.518" transform="matrix(0.7071 -0.7071 0.7071 0.7071 -2.7062 291.5029)" style="fill:#FDCB50;" width="122.883" height="75"/></g><g><rect x="19.268" y="383.881" transform="matrix(0.7071 -0.7071 0.7071 0.7071 -275.0696 178.6863)" style="fill:#FDCB50;" width="117.781" height="75"/></g></g><g><g><path style="fill:#052A75;" d="M63.033,499.54c-2.652,0-5.195-1.054-7.071-2.929c0,0-53.033-53.033-53.033-53.033 c-3.905-3.905-3.905-10.237,0-14.143c0,0,357.45-357.451,357.45-357.451c3.715-3.859,10.425-3.883,14.143,0 c0,0,53.033,53.034,53.033,53.034c3.905,3.905,3.905,10.237,0,14.142L70.105,496.611C68.229,498.486,65.686,499.54,63.033,499.54 z M24.143,436.507l38.891,38.891l343.308-343.309L367.45,93.198L24.143,436.507z"/></g><g><path style="fill:#FDCB50;" d="M219.114,78.753c-5.522,0-10,4.477-10,10v30c0,5.523,4.478,10,10,10s10-4.477,10-10v-30 C229.114,83.23,224.636,78.753,219.114,78.753z"/><path style="fill:#FDCB50;" d="M410.787,270.426h-30c-5.522,0-10,4.477-10,10s4.478,10,10,10h30 C424.03,289.939,424.042,270.917,410.787,270.426z"/><path style="fill:#FDCB50;" d="M410.787,59.377h29.376v29.376c0,5.523,4.478,10,10,10s10-4.477,10-10V59.377h29.377 c5.522,0,10-4.477,10-10s-4.478-10-10-10h-29.377V10c0-5.523-4.478-10-10-10s-10,4.477-10,10v29.376h-29.376 C397.538,39.866,397.54,58.888,410.787,59.377z"/><path style="fill:#FDCB50;" d="M311.725,39.377h-29.376V10c0-5.523-4.478-10-10-10s-10,4.477-10,10v29.376h-29.376 c-5.522,0-10,4.477-10,10s4.478,10,10,10h29.376v29.376c0,5.523,4.478,10,10,10s10-4.477,10-10V59.377h29.376 C324.974,58.887,324.973,39.865,311.725,39.377z"/><path style="fill:#FDCB50;" d="M489.54,206.942h-29.377v-29.376c0-5.523-4.478-10-10-10s-10,4.477-10,10v29.376h-29.376 c-5.522,0-10,4.477-10,10s4.478,10,10,10h29.376v29.376c0,5.523,4.478,10,10,10s10-4.477,10-10v-29.376h29.377 C502.79,226.453,502.788,207.431,489.54,206.942z"/></g></g></g>
                                        </svg>
                                        <span class="sln-calendar-carousel__number-text">36</span>
                                    </div>
                                    <p class="sln-calendar-carousel__number-label"><?php esc_html_e( 'Productivity tools', 'salon-booking-system' ); ?></p>
                                </div>
                            </div>

                        </div>
                        
                        <!-- Dots Navigation (Outside Track - Always Visible) -->
                        <div class="sln-calendar-carousel__dots" role="tablist" aria-label="<?php esc_attr_e( 'Carousel navigation', 'salon-booking-system' ); ?>">
                            <button class="sln-calendar-carousel__dot sln-calendar-carousel__dot--active" data-slide="0" role="tab" aria-selected="true" aria-label="<?php esc_attr_e( 'Slide 1', 'salon-booking-system' ); ?>"></button>
                            <button class="sln-calendar-carousel__dot" data-slide="1" role="tab" aria-selected="false" aria-label="<?php esc_attr_e( 'Slide 2', 'salon-booking-system' ); ?>"></button>
                            <button class="sln-calendar-carousel__dot" data-slide="2" role="tab" aria-selected="false" aria-label="<?php esc_attr_e( 'Slide 3', 'salon-booking-system' ); ?>"></button>
                            <button class="sln-calendar-carousel__dot" data-slide="3" role="tab" aria-selected="false" aria-label="<?php esc_attr_e( 'Slide 4', 'salon-booking-system' ); ?>"></button>
                            <button class="sln-calendar-carousel__dot" data-slide="4" role="tab" aria-selected="false" aria-label="<?php esc_attr_e( 'Slide 5', 'salon-booking-system' ); ?>"></button>
                            <button class="sln-calendar-carousel__dot" data-slide="5" role="tab" aria-selected="false" aria-label="<?php esc_attr_e( 'Slide 6', 'salon-booking-system' ); ?>"></button>
                        </div>
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
        if ($is_phone && defined('SLN_VERSION_PAY') && SLN_VERSION_PAY) :
            $pro_pwa_url = apply_filters('sln_pro_mobile_pwa_promo_url', site_url('/salon-booking-pwa'));
            ?>
            <div id="sln-pro-pwa-calendar-promo-wrap" class="sln-free-pwa-calendar-promo-wrap" role="presentation">
                <button type="button" class="sln-free-pwa-calendar-promo-backdrop" id="sln-pro-pwa-calendar-promo-backdrop" aria-label="<?php esc_attr_e('Close', 'salon-booking-system'); ?>"></button>
                <div id="sln-note-phone-device" class="sln-popup sln-free-pwa-calendar-promo" role="dialog" aria-modal="true" aria-labelledby="sln-pro-pwa-calendar-promo-title">
                    <button type="button" class="sln-popup--close sln-free-pwa-calendar-promo__close-x" id="sln-pro-pwa-calendar-promo-close" aria-label="<?php esc_attr_e('Close', 'salon-booking-system'); ?>"></button>
                    <div class="sln-popup-content sln-free-pwa-calendar-promo__content">
                        <p id="sln-pro-pwa-calendar-promo-title" class="sln-popup--text sln-popup--question sln-free-pwa-calendar-promo__headline"><?php esc_html_e('Why don\'t you use our brand-new Web App?', 'salon-booking-system'); ?></p>
                        <p class="sln-popup--text sln-popup--offer sln-free-pwa-calendar-promo__lede"><?php esc_html_e('It\'s easy and optimised for mobile device.', 'salon-booking-system'); ?></p>
                    </div>
                    <a class="sln-popup--button sln-free-pwa-calendar-promo__cta" href="<?php echo esc_url($pro_pwa_url); ?>"><?php esc_html_e('Open the Web App', 'salon-booking-system'); ?></a>
                </div>
            </div>
        <?php elseif ($is_phone && ! defined('SLN_VERSION_PAY') && apply_filters('sln_show_free_mobile_pwa_calendar_popup', true)) :
            $free_pwa_promo_url       = apply_filters('sln_free_mobile_pwa_promo_url', home_url('salon-booking-pwa'));
            $free_pwa_pro_pricing_url = apply_filters(
                'sln_free_mobile_pwa_pro_pricing_url',
                'https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=mobile_screen&utm_medium=free-edition-back-end&utm_campaign=mobile_cta&utm_id=GOPRO'
            );
            ?>
            <script>
            (function () {
                try {
                    if (sessionStorage.getItem('sln_free_pwa_calendar_promo_dismissed') === '1') {
                        document.documentElement.classList.add('sln-hide-free-pwa-calendar-promo');
                    }
                } catch (e) {}
            })();
            </script>
            <div id="sln-free-pwa-calendar-promo-wrap" class="sln-free-pwa-calendar-promo-wrap" role="presentation">
                <button type="button" class="sln-free-pwa-calendar-promo-backdrop" id="sln-free-pwa-calendar-promo-backdrop" aria-label="<?php esc_attr_e('Close', 'salon-booking-system'); ?>"></button>
                <div id="sln-free-pwa-calendar-promo" class="sln-popup sln-free-pwa-calendar-promo" role="dialog" aria-modal="true" aria-labelledby="sln-free-pwa-calendar-promo-title">
                    <button type="button" class="sln-popup--close sln-free-pwa-calendar-promo__close-x" id="sln-free-pwa-calendar-promo-close" aria-label="<?php esc_attr_e('Close', 'salon-booking-system'); ?>"></button>
                    <div class="sln-popup-content sln-free-pwa-calendar-promo__content">
                        <p id="sln-free-pwa-calendar-promo-title" class="sln-popup--text sln-popup--question sln-free-pwa-calendar-promo__headline"><?php esc_html_e('Check out our mobile web app — free', 'salon-booking-system'); ?></p>
                        <p class="sln-popup--text sln-popup--offer sln-free-pwa-calendar-promo__lede"><?php esc_html_e('Book and manage appointments on the go, right from your phone.', 'salon-booking-system'); ?></p>
                    </div>
                    <a class="sln-popup--button sln-free-pwa-calendar-promo__cta" href="<?php echo esc_url($free_pwa_promo_url); ?>"><?php esc_html_e('Open the Web App', 'salon-booking-system'); ?></a>
                    <div class="sln-free-pwa-calendar-promo__actions">
                        <button type="button" class="sln-free-pwa-calendar-promo__not-now" id="sln-free-pwa-calendar-promo-not-now"><?php esc_html_e('Not now', 'salon-booking-system'); ?></button>
                        <a class="sln-free-pwa-calendar-promo__pro-link" href="<?php echo esc_url($free_pwa_pro_pricing_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade to PRO', 'salon-booking-system'); ?></a>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var wrap = document.getElementById('sln-free-pwa-calendar-promo-wrap');
                if (!wrap || document.documentElement.classList.contains('sln-hide-free-pwa-calendar-promo')) {
                    return;
                }
                function dismiss() {
                    try {
                        sessionStorage.setItem('sln_free_pwa_calendar_promo_dismissed', '1');
                    } catch (e) {}
                    wrap.style.display = 'none';
                }
                var b = document.getElementById('sln-free-pwa-calendar-promo-backdrop');
                var c = document.getElementById('sln-free-pwa-calendar-promo-close');
                var n = document.getElementById('sln-free-pwa-calendar-promo-not-now');
                if (b) {
                    b.addEventListener('click', dismiss);
                }
                if (c) {
                    c.addEventListener('click', dismiss);
                }
                if (n) {
                    n.addEventListener('click', dismiss);
                }
            })();
            </script>
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