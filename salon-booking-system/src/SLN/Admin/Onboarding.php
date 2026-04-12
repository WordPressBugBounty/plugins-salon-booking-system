<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Admin_Onboarding extends SLN_Admin_AbstractPage
{
    const PAGE = 'salon-onboarding';
    const PRIORITY = 1;

    public function __construct(SLN_Plugin $plugin)
    {
        parent::__construct($plugin);
        add_action('admin_head', array($this, 'hideSetupWizardMenuItem'));
        add_action('in_admin_header', array($this, 'in_admin_header'));
        add_action('wp_ajax_sln_onboarding_save_step', array($this, 'ajaxSaveStep'));
        add_action('wp_ajax_sln_onboarding_complete', array($this, 'ajaxComplete'));
        add_action('wp_ajax_sln_onboarding_upload_logo', array($this, 'ajaxUploadLogo'));
    }

    /**
     * Override in_admin_header to suppress the Beacon floating launcher on the onboarding page.
     * The launcher is hidden via display.style:'manual' so it only opens when the
     * "Contact Support" button explicitly calls Beacon('open').
     */
    public function in_admin_header()
    {
        parent::in_admin_header();
        if (isset($_GET['page']) && $_GET['page'] === static::PAGE) {
            ?>
            <script type="text/javascript">
            // Hide the floating Beacon launcher on the onboarding wizard.
            // Beacon queues calls made before the SDK loads, so this is safe to call immediately.
            window.Beacon('config', { display: { style: 'manual' } });
            </script>
            <?php
        }
    }

    /**
     * Hide the Setup Wizard from the Salon sidebar menu (page remains accessible via direct URL).
     */
    public function hideSetupWizardMenuItem()
    {
        // Target only the submenu item, not the parent Salon menu (which would hide the entire menu)
        echo '<style id="sln-hide-onboarding-menu">#adminmenu .wp-submenu li:has(a[href*="page=salon-onboarding"]){display:none!important}</style>';
    }

    public function admin_menu()
    {
        $pagename = add_submenu_page(
            'salon',
            __('Setup Wizard', 'salon-booking-system'),
            __('Setup Wizard', 'salon-booking-system'),
            $this->getCapability(),
            self::PAGE,
            array($this, 'show')
        );
        add_action('load-' . $pagename, array($this, 'enqueueAssets'), 0);
    }

    public function show()
    {
        if (
            isset($_GET['sln_complete']) &&
            '1' === $_GET['sln_complete'] &&
            current_user_can($this->getCapability())
        ) {
            update_option('_sln_onboarding_completed', 1);
            wp_safe_redirect(admin_url('admin.php?page=salon'));
            exit();
        }

        $use_react = apply_filters('sln_onboarding_use_react', true);
        $react_bundle = defined('SLN_PLUGIN_DIR') ? SLN_PLUGIN_DIR . '/onboarding-app/dist/onboarding.index.js' : '';
        $react_available = $use_react && $react_bundle && is_readable($react_bundle);

        if ($react_available) {
            echo $this->plugin->loadView('admin/onboarding-react', array());
            return;
        }

        echo $this->plugin->loadView(
            'admin/onboarding',
            array(
                'plugin' => $this->plugin,
                'settings' => $this->plugin->getSettings(),
            )
        );
    }

    public function enqueueAssets()
    {
        $av = SLN_Action_InitScripts::ASSETS_VERSION;
        $use_react = apply_filters('sln_onboarding_use_react', true);
        $react_js = defined('SLN_PLUGIN_DIR') ? SLN_PLUGIN_DIR . '/onboarding-app/dist/onboarding.index.js' : '';
        $react_css = defined('SLN_PLUGIN_DIR') ? SLN_PLUGIN_DIR . '/onboarding-app/dist/onboarding.index.css' : '';
        $react_available = $use_react && $react_js && is_readable($react_js);

        if ($react_available) {
            wp_enqueue_style(
                'salon-onboarding',
                SLN_PLUGIN_URL . '/onboarding-app/dist/onboarding.index.css',
                array(),
                $av,
                'all'
            );
            wp_enqueue_script(
                'salon-onboarding',
                SLN_PLUGIN_URL . '/onboarding-app/dist/onboarding.index.js',
                array(),
                $av,
                true
            );
            // Add module type attribute for ES modules (import.meta support)
            add_filter('script_loader_tag', function($tag, $handle) {
                if ($handle === 'salon-onboarding') {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);
        } else {
            SLN_Action_InitScripts::enqueueTwitterBootstrap(true);
            wp_enqueue_style(
                'salon-onboarding',
                SLN_PLUGIN_URL . '/css/admin-onboarding.css',
                array(),
                $av,
                'all'
            );
            wp_enqueue_script(
                'salon-onboarding',
                SLN_PLUGIN_URL . '/js/admin/onboarding.js',
                array('jquery'),
                $av,
                true
            );
        }

        $feature_index = SLN_Data_FeatureIndex::get();
        $is_pro = defined('SLN_VERSION_PAY') || defined('SLN_VERSION_CODECANYON');
        $pay_page_id = $this->plugin->getSettings()->getPayPageId();
        $booking_form_url = ($pay_page_id && get_post_status($pay_page_id)) ? get_permalink($pay_page_id) : home_url('/');
        $locale = $this->getLocaleFromTimezone();
        wp_localize_script('salon-onboarding', 'slnOnboarding', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sln_onboarding'),
            'featureIndex' => $feature_index,
            'settingsUrl' => admin_url('admin.php?page=salon-settings'),
            'calendarUrl' => admin_url('admin.php?page=salon'),
            'bookingFormUrl' => $booking_form_url,
            'isPro' => $is_pro,
            'detectedCountryCode' => $locale['countryCode'],
            'detectedCurrency' => $locale['currency'],
            'detectedCurrencySymbol' => $locale['currencySymbol'],
            'i18n' => array(
                'step' => __('Step', 'salon-booking-system'),
                'of' => __('of', 'salon-booking-system'),
                'continue' => __('Continue', 'salon-booking-system'),
                'back' => __('Back', 'salon-booking-system'),
                'skip' => __('Skip', 'salon-booking-system'),
                'goToCalendar' => __('Go to Calendar', 'salon-booking-system'),
                'saving' => __('Saving...', 'salon-booking-system'),
                'error' => __('Something went wrong. Please try again.', 'salon-booking-system'),
                'searchPlaceholder' => __('Search for a feature...', 'salon-booking-system'),
                'noResults' => __('No features found.', 'salon-booking-system'),
                'openInSettings' => __('Open in Settings', 'salon-booking-system'),
            ),
        ));
    }

    /**
     * Detect country code and currency from WordPress General Settings timezone.
     *
     * @return array{countryCode: string, currency: string, currencySymbol: string}
     */
    private function getLocaleFromTimezone()
    {
        $tz_string = get_option('timezone_string');
        $gmt_offset = get_option('gmt_offset');
        $country_code = null;
        $currency = null;

        if (!empty($tz_string)) {
            $map = $this->getTimezoneToLocaleMap();
            foreach ($map as $prefix => $locale) {
                if (strpos($tz_string, $prefix) === 0) {
                    $country_code = $locale['countryCode'];
                    $currency = $locale['currency'];
                    break;
                }
            }
        }

        if ($country_code === null && $gmt_offset !== '' && is_numeric($gmt_offset)) {
            $offset = (float) $gmt_offset;
            $fallback = $this->getLocaleFromGmtOffset($offset);
            $country_code = $fallback['countryCode'];
            $currency = $fallback['currency'];
        }

        $country_code = $country_code ?: '+1';
        $currency = $currency ?: 'USD';
        $symbol = class_exists('SLN_Currency') ? SLN_Currency::getSymbolAsIs($currency) : '$';

        return array(
            'countryCode' => $country_code,
            'currency' => $currency,
            'currencySymbol' => $symbol,
        );
    }

    /** @return array<string, array{countryCode: string, currency: string}> */
    private function getTimezoneToLocaleMap()
    {
        return array(
            'America/New_York' => array('countryCode' => '+1', 'currency' => 'USD'),
            'America/Chicago' => array('countryCode' => '+1', 'currency' => 'USD'),
            'America/Denver' => array('countryCode' => '+1', 'currency' => 'USD'),
            'America/Los_Angeles' => array('countryCode' => '+1', 'currency' => 'USD'),
            'America/Phoenix' => array('countryCode' => '+1', 'currency' => 'USD'),
            'America/Detroit' => array('countryCode' => '+1', 'currency' => 'USD'),
            'America/Anchorage' => array('countryCode' => '+1', 'currency' => 'USD'),
            'America/Toronto' => array('countryCode' => '+1', 'currency' => 'CAD'),
            'America/Vancouver' => array('countryCode' => '+1', 'currency' => 'CAD'),
            'America/Montreal' => array('countryCode' => '+1', 'currency' => 'CAD'),
            'America/Mexico_City' => array('countryCode' => '+52', 'currency' => 'MXN'),
            'America/Sao_Paulo' => array('countryCode' => '+55', 'currency' => 'BRL'),
            'America/Buenos_Aires' => array('countryCode' => '+55', 'currency' => 'ARS'),
            'Europe/London' => array('countryCode' => '+44', 'currency' => 'GBP'),
            'Europe/Paris' => array('countryCode' => '+33', 'currency' => 'EUR'),
            'Europe/Berlin' => array('countryCode' => '+49', 'currency' => 'EUR'),
            'Europe/Rome' => array('countryCode' => '+39', 'currency' => 'EUR'),
            'Europe/Madrid' => array('countryCode' => '+34', 'currency' => 'EUR'),
            'Europe/Amsterdam' => array('countryCode' => '+49', 'currency' => 'EUR'),
            'Europe/Brussels' => array('countryCode' => '+49', 'currency' => 'EUR'),
            'Europe/Vienna' => array('countryCode' => '+49', 'currency' => 'EUR'),
            'Europe/Warsaw' => array('countryCode' => '+49', 'currency' => 'PLN'),
            'Europe/Moscow' => array('countryCode' => '+7', 'currency' => 'RUB'),
            'Europe/Istanbul' => array('countryCode' => '+39', 'currency' => 'TRY'),
            'Asia/Tokyo' => array('countryCode' => '+81', 'currency' => 'JPY'),
            'Asia/Shanghai' => array('countryCode' => '+86', 'currency' => 'CNY'),
            'Asia/Hong_Kong' => array('countryCode' => '+86', 'currency' => 'HKD'),
            'Asia/Kolkata' => array('countryCode' => '+91', 'currency' => 'INR'),
            'Asia/Seoul' => array('countryCode' => '+82', 'currency' => 'KRW'),
            'Asia/Singapore' => array('countryCode' => '+82', 'currency' => 'SGD'),
            'Asia/Dubai' => array('countryCode' => '+44', 'currency' => 'AED'),
            'Australia/Sydney' => array('countryCode' => '+61', 'currency' => 'AUD'),
            'Australia/Melbourne' => array('countryCode' => '+61', 'currency' => 'AUD'),
            'Australia/Perth' => array('countryCode' => '+61', 'currency' => 'AUD'),
            'Pacific/Auckland' => array('countryCode' => '+61', 'currency' => 'NZD'),
            'Africa/Johannesburg' => array('countryCode' => '+27', 'currency' => 'ZAR'),
            'Africa/Cairo' => array('countryCode' => '+49', 'currency' => 'EGP'),
        );
    }

    /**
     * @return array{countryCode: string, currency: string}
     */
    private function getLocaleFromGmtOffset($offset)
    {
        $map = array(
            '0' => array('countryCode' => '+44', 'currency' => 'GBP'),
            '1' => array('countryCode' => '+33', 'currency' => 'EUR'),
            '2' => array('countryCode' => '+33', 'currency' => 'EUR'),
            '-5' => array('countryCode' => '+1', 'currency' => 'USD'),
            '-6' => array('countryCode' => '+1', 'currency' => 'USD'),
            '-7' => array('countryCode' => '+1', 'currency' => 'USD'),
            '-8' => array('countryCode' => '+1', 'currency' => 'USD'),
            '5.5' => array('countryCode' => '+91', 'currency' => 'INR'),
            '5.75' => array('countryCode' => '+91', 'currency' => 'INR'),
            '8' => array('countryCode' => '+86', 'currency' => 'CNY'),
            '9' => array('countryCode' => '+81', 'currency' => 'JPY'),
            '10' => array('countryCode' => '+61', 'currency' => 'AUD'),
            '12' => array('countryCode' => '+61', 'currency' => 'NZD'),
            '3' => array('countryCode' => '+7', 'currency' => 'RUB'),
            '4' => array('countryCode' => '+7', 'currency' => 'RUB'),
        );
        $key = (string) (round($offset * 2) / 2);
        return isset($map[$key]) ? $map[$key] : array('countryCode' => '+1', 'currency' => 'USD');
    }

    public function ajaxSaveStep()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sln_onboarding')) {
                if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                    error_log('[Salon Onboarding] ajaxSaveStep: nonce missing or invalid. POST keys: ' . implode(', ', array_keys($_POST)));
                }
                wp_send_json_error(array('message' => 'Security check failed'));
            }
            if (!current_user_can($this->getCapability())) {
                if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                    error_log('[Salon Onboarding] ajaxSaveStep: user lacks capability: ' . $this->getCapability());
                }
                wp_send_json_error(array('message' => 'Forbidden'));
            }
            $step = isset($_POST['step']) ? absint($_POST['step']) : 0;
            $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
            $data = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : array());
            $data = is_array($data) ? $data : array();
            $settings = $this->plugin->getSettings();
            if ($step === 1) {
                $usage_goal = isset($data['usage_goal']) ? $data['usage_goal'] : (isset($_POST['usage_goal']) ? sanitize_text_field(wp_unslash($_POST['usage_goal'])) : '');
                update_option('_sln_usage_goal', sanitize_text_field($usage_goal));
                if (isset($data['gen_name'])) {
                    $settings->set('gen_name', sanitize_text_field($data['gen_name']));
                }
                if (isset($data['gen_email']) && is_string($data['gen_email'])) {
                    $settings->set('gen_email', sanitize_email($data['gen_email']));
                }
                if (isset($data['gen_phone']) && is_string($data['gen_phone'])) {
                    $settings->set('gen_phone', sanitize_text_field($data['gen_phone']));
                }
                if (isset($data['gen_address']) && is_string($data['gen_address'])) {
                    $settings->set('gen_address', sanitize_textarea_field($data['gen_address']));
                }
                if (isset($data['attendant_enabled'])) {
                    $settings->set('attendant_enabled', $data['attendant_enabled'] === '1' ? '1' : '0');
                }
                if (isset($data['parallels_hour'])) {
                    $settings->set('parallels_hour', max(1, min(1000, absint($data['parallels_hour']))));
                }
                if (isset($data['interval'])) {
                    $settings->set('interval', sanitize_text_field($data['interval']));
                }
                if (isset($data['gen_logo']) && is_numeric($data['gen_logo'])) {
                    $att_id = absint($data['gen_logo']);
                    if ($att_id && get_post_type($att_id) === 'attachment') {
                        $settings->set('gen_logo', (string) $att_id);
                    }
                }
                $settings->save();

                // Send a non-blocking background ping to track which business type was selected.
                // This powers the "Top Salon Business Types" chart on salonbookingsystem.com.
                $business_type = sanitize_key(isset($data['usage_goal']) ? $data['usage_goal'] : '');
                if ($business_type) {
                    $ping_body = array(
                        'business_type' => $business_type,
                        'site_hash'     => hash('sha256', home_url()),
                    );
                    // Include API key if defined in wp-config.php or the plugin file.
                    if (defined('SBS_TRACKER_API_SECRET') && SBS_TRACKER_API_SECRET) {
                        $ping_body['api_key'] = SBS_TRACKER_API_SECRET;
                    }
                    wp_remote_post(
                        'https://www.salonbookingsystem.com/wp-json/sbs-tracker/v1/business-type',
                        array(
                            'blocking'  => false,
                            'timeout'   => 2,
                            'sslverify' => true,
                            'body'      => $ping_body,
                        )
                    );
                }

                wp_send_json_success();
            }
            if ($step === 2) {
                if (isset($data['parallels_hour'])) {
                    $settings->set('parallels_hour', max(1, min(1000, absint($data['parallels_hour']))));
                }
                if (isset($data['interval'])) {
                    $settings->set('interval', sanitize_text_field($data['interval']));
                }
            if (isset($data['avail_days']) && is_array($data['avail_days'])) {
                $days = array();
                foreach ($data['avail_days'] as $k => $v) {
                    // Only store enabled days. isValidDayOfWeek() uses isset(), so a key
                    // with value 0 would still be treated as enabled — omit disabled days entirely.
                    if ($v) {
                        $days[absint($k)] = 1;
                    }
                }
                $from = array(
                    isset($data['avail_from_0']) ? $this->sanitizeTime($data['avail_from_0']) : '09:00',
                    isset($data['avail_from_1']) ? $this->sanitizeTime($data['avail_from_1']) : '14:00',
                );
                $to = array(
                    isset($data['avail_to_0']) ? $this->sanitizeTime($data['avail_to_0']) : '13:00',
                    isset($data['avail_to_1']) ? $this->sanitizeTime($data['avail_to_1']) : '18:00',
                );
                $availabilities = $settings->get('availabilities');
                if (!is_array($availabilities)) {
                    $availabilities = array();
                }
                $availabilities[0] = array(
                    'days' => $days,
                    'from' => $from,
                    'to' => $to,
                    'always' => true,
                );
                $availabilities = SLN_Helper_AvailabilityItems::processSubmission($availabilities);
                $settings->set('availabilities', $availabilities);
            }
            $settings->save();

            // Clear caches so new availability rules take effect immediately
            SLN_Helper_Availability_Cache::clearCache();
            $this->plugin->getBookingCache()->refreshAll();
            $this->plugin->getBookingCache()->save();
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sln_%'");

            wp_send_json_success();
        }
            if ($step === 3) {
                if (isset($data['attendant_enabled'])) {
                    $settings->set('attendant_enabled', $data['attendant_enabled'] === true || $data['attendant_enabled'] === '1' ? '1' : '0');
                    $settings->save();
                }
                if (!empty($data['assistants']) && is_array($data['assistants'])) {
                    $stored = array();
                    foreach ($data['assistants'] as $a) {
                        $name = isset($a['name']) ? sanitize_text_field($a['name']) : '';
                        if ($name !== '') {
                            $stored[] = array(
                                'name'  => $name,
                                'email' => isset($a['email']) ? sanitize_email($a['email']) : '',
                            );
                        }
                    }
                    update_option('_sln_onboarding_pending_assistants', wp_json_encode($stored));
                }
                wp_send_json_success();
            }
            if ($step === 4) {
                if (isset($data['pay_currency']) && is_string($data['pay_currency'])) {
                    $currency = strtoupper(sanitize_text_field($data['pay_currency']));
                    if (array_key_exists($currency, SLN_Currency::toArray())) {
                        $settings->set('pay_currency', $currency);
                        $settings->save();
                    }
                }
                if (!empty($data['services']) && is_array($data['services'])) {
                    $stored = array();
                    foreach ($data['services'] as $s) {
                        $name = isset($s['name']) ? sanitize_text_field($s['name']) : '';
                        if ($name !== '') {
                            $stored[] = array(
                                'name'     => $name,
                                'duration' => isset($s['duration']) ? absint($s['duration']) : 30,
                                'price'    => isset($s['price']) ? sanitize_text_field($s['price']) : '0',
                                'category' => isset($s['category']) ? sanitize_text_field($s['category']) : '',
                            );
                        }
                    }
                    update_option('_sln_onboarding_pending_services', wp_json_encode($stored));
                }
                wp_send_json_success();
            }
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('[Salon Onboarding] ajaxSaveStep: invalid step or no handler. step=' . $step . ' POST keys: ' . implode(', ', array_keys($_POST)));
            }
            wp_send_json_error(array('message' => 'Invalid step'));
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('[Salon Onboarding] ajaxSaveStep exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    private function sanitizeTime($t)
    {
        $t = sanitize_text_field($t);
        if (preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $t)) {
            return $t;
        }
        return '09:00';
    }

    public function ajaxComplete()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sln_onboarding')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        if (!current_user_can($this->getCapability())) {
            wp_send_json_error(array('message' => 'Forbidden'));
        }
        $settings = $this->plugin->getSettings();
        $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
        $data = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : array());
        $data = is_array($data) ? $data : array();

        // Fall back to stored wizard data if POST is empty (e.g. page refresh on /complete)
        if (empty($data['assistants']) || empty($data['services'])) {
            $pending_assistants = get_option('_sln_onboarding_pending_assistants', '');
            $pending_services   = get_option('_sln_onboarding_pending_services', '');
            if ($pending_assistants !== '') {
                $decoded = json_decode($pending_assistants, true);
                if (is_array($decoded) && (!isset($data['assistants']) || empty($data['assistants']))) {
                    $data['assistants'] = $decoded;
                }
            }
            if ($pending_services !== '') {
                $decoded = json_decode($pending_services, true);
                if (is_array($decoded) && (!isset($data['services']) || empty($data['services']))) {
                    $data['services'] = $decoded;
                }
            }
        }

        if (isset($data['assistantSelectionEnabled'])) {
            $settings->set('attendant_enabled', $data['assistantSelectionEnabled'] ? '1' : '0');
            $settings->save();
        }

        if (!empty($data['assistants']) && is_array($data['assistants'])) {
            $order = 0;
            foreach ($data['assistants'] as $a) {
                $name = isset($a['name']) ? sanitize_text_field($a['name']) : '';
                if ($name === '') {
                    continue;
                }
                $post_id = wp_insert_post(array(
                    'post_title'   => $name,
                    'post_excerpt' => '',
                    'post_status'  => 'publish',
                    'post_type'   => SLN_Plugin::POST_TYPE_ATTENDANT,
                ));
                if ($post_id && !is_wp_error($post_id)) {
                    if (!empty($a['email'])) {
                        update_post_meta($post_id, '_sln_attendant_email', sanitize_email($a['email']));
                    }
                    update_post_meta($post_id, '_sln_attendant_order', (string) $order);
                    $order++;
                }
            }
        }

        if (!empty($data['services']) && is_array($data['services'])) {
            $order = 0;
            foreach ($data['services'] as $s) {
                $name = isset($s['name']) ? sanitize_text_field($s['name']) : '';
                if ($name === '') {
                    continue;
                }
                $duration_min = isset($s['duration']) ? absint($s['duration']) : 30;
                $duration_str = sprintf('%02d:%02d', floor($duration_min / 60), $duration_min % 60);
                $price = isset($s['price']) ? sanitize_text_field($s['price']) : '0';
                $post_id = wp_insert_post(array(
                    'post_title'   => $name,
                    'post_excerpt' => '',
                    'post_status'  => 'publish',
                    'post_type'   => SLN_Plugin::POST_TYPE_SERVICE,
                ));
                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, '_sln_service_price', $price);
                    update_post_meta($post_id, '_sln_service_duration', $duration_str);
                    update_post_meta($post_id, '_sln_service_unit', '1');
                    update_post_meta($post_id, '_sln_service_order', (string) $order);
                    $order++;
                }
            }
        }

        update_option('_sln_onboarding_completed', 1);
        delete_option('_sln_onboarding_pending_assistants');
        delete_option('_sln_onboarding_pending_services');
        wp_send_json_success(array('redirect' => admin_url('admin.php?page=salon')));
    }

    /**
     * Handle logo file upload from onboarding wizard. Returns WordPress attachment ID.
     */
    public function ajaxUploadLogo()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sln_onboarding')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        if (!current_user_can($this->getCapability())) {
            wp_send_json_error(array('message' => 'Forbidden'));
        }
        if (!isset($_FILES['gen_logo']) || empty($_FILES['gen_logo']['tmp_name']) || $_FILES['gen_logo']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'No valid file uploaded'));
        }
        $file = $_FILES['gen_logo'];
        if (!function_exists('exif_imagetype') || !exif_imagetype($file['tmp_name'])) {
            wp_send_json_error(array('message' => 'Invalid image file'));
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $_FILES['gen_logo']['name'] = 'gen_logo.png';
        if (!has_image_size('sln_gen_logo')) {
            add_image_size('sln_gen_logo', 240, 135);
        }
        $att_id = media_handle_upload('gen_logo', 0);
        if (is_wp_error($att_id)) {
            wp_send_json_error(array('message' => $att_id->get_error_message()));
        }
        wp_send_json_success(array('attachment_id' => (int) $att_id));
    }
}
