<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Settings {
	const KEY = 'salon_settings';
	private $settings;
	private $availabilityItems;
	private $holidayItems;

	public function __construct() {
		$this->load();
	}

	public function load() {
		$this->settings = get_option(self::KEY);
	}

	public function get($k) {
		$val = isset($this->settings[$k]) ? $this->settings[$k] : null;

		return apply_filters('sln.settings.get', $val, $k);
	}

	public function set($key, $val) {
		if (is_string($val)) {
			$val = trim($val);
		}
		if (empty($val) && $val != 0) {
			unset($this->settings[$key]);
		} else {
			$this->settings[$key] = $val;
		}
	}

	public function all() {
		return $this->settings;
	}

	public function save() {
		update_option(self::KEY, $this->settings);

		return $this;
	}

	public function clear() {
		delete_option(self::KEY);
	}

	public function getVersion() {
		return SLN_VERSION;
	}

	public function getDbVersion() {
		$val = $this->get('sln_db_version');

		return empty($val) ? '0.0.0' : $val;
	}

	public function setDbVersion($version = null) {
		$this->settings['sln_db_version'] = is_null($version) ? $this->getVersion() : $version;

		return $this;
	}

	public function getCurrency() {
		$val = $this->get('pay_currency');

		return empty($val) ? 'USD' : $val;
	}

	public function getCurrencySymbol() {
		return SLN_Currency::getSymbol($this->getCurrency());
	}

	public function getInterval() {
		$val = $this->get('interval');
		$val = apply_filters('sln.settings.interval', $val);

		return isset($val) ? $val : SLN_Constants::DEFAULT_INTERVAL;
	}

	public function getNoticesDisabled() {
		$val = $this->get('notices_disabled');

		return isset($val) ? $val : false;
	}

	public function setNoticesDisabled($val) {
		$this->settings['notices_disabled'] = $val;

		return $this;
	}

	public function isPaypalTest() {
		return $this->get('pay_paypal_test') ? true : false;
	}

	public function getPaypalEmail() {
		return $this->get('pay_paypal_email');
	}

	public function getThankyouPageId() {
		return SLN_Func::get_translated_page_id($this->get('thankyou'), get_locale());
	}

	public function getBookingmyaccountPageId() {
		return SLN_Func::get_translated_page_id($this->get('bookingmyaccount'), get_locale());
	}

	public function getPayPageId() {
		return SLN_Func::get_translated_page_id($this->get('pay'), get_locale());
	}

	public function isDisabled() {
		return $this->get('disabled') ? true : false;
	}

	public function getDisabledMessage() {
		return nl2br(htmlentities($this->get('disabled_message')));
	}

	public function isAjaxEnabled() {
		return $this->get('ajax_enabled') ? true : false;
	}

	public function getDateFormat() {
		return $this->get('date_format') ? $this->get('date_format') : SLN_Enum_DateFormat::_DEFAULT;
	}

	public function getTimeFormat() {
		return $this->get('time_format') ? $this->get('time_format') : SLN_Enum_TimeFormat::_DEFAULT;
	}

	public function getSalonName() {
		$ret = $this->get('gen_name');
		if (!$ret) {
			$ret = get_bloginfo('name');
		}

		return apply_filters('sln.settings.get_salon_name', $ret);
	}

	public function getSalonEmail() {
		$ret = $this->get('gen_email');
		if (!$ret) {
			$ret = get_bloginfo('admin_email');
		}

		return $ret;

	}

	public function getHoursBeforeFrom() {
		$ret = $this->get('hours_before_from');

		return $ret ? $ret : SLN_Constants::HOURS_BEFORE_FROM_ALWAYS;
	}

	public function getHoursBeforeTo() {
		$ret = $this->get('hours_before_to');

		return $ret ? $ret : SLN_Constants::HOURS_BEFORE_TO_ALWAYS;
	}

	public function getAvailabilityMode() {
		$ret = $this->get('availability_mode');

		return $ret ? $ret : 'basic';
	}

	/**
	 * Check if nested bookings (bookings during service breaks) are enabled globally
	 * When enabled, all services with breaks will allow nested bookings
	 * Note: This is a PRO-only feature
	 * 
	 * @return bool
	 */
	public function isNestedBookingsEnabled() {
		// PRO-only feature
		if (!defined('SLN_VERSION_PAY')) {
			return false;
		}
		return (bool) $this->get('nested_bookings_enabled');
	}

	public function getPaymentMethod() {
		$val = $this->get('pay_method');

		return isset($val) ? $val : 'paypal';
	}

    public function isPaymentDepositAdvancedRules(): bool
    {
        return (bool) $this->get('enable_pay_deposit_advanced_rules');
    }

    public function getPaymentDepositAdvancedRules(): array
    {
        return (array) $this->get('pay_deposit_advanced_rules');
    }

	public function getPaymentDepositAmount() {
		return $this->isPaymentDepositFixedAmount() ? $this->getPaymentDepositFixedValue() : $this->getPaymentDepositValue();
	}

	public function isPaymentDepositFixedAmount() {
		return ($this->getPaymentDepositValue() === SLN_Enum_PaymentDepositType::FIXED);
	}

	public function getPaymentDepositValue() {
		$val = $this->get('pay_deposit');

		return isset($val) ? $val : SLN_Enum_PaymentDepositType::_DEFAULT;
	}

	public function getPaymentDepositFixedValue() {
		$val = $this->get('pay_deposit_fixed_amount');

		return isset($val) ? $val : 0;
	}

	public function getStyleShortcode() {
		$val = $this->get('style_shortcode');

		return isset($val) ? $val : SLN_Enum_ShortcodeStyle::_DEFAULT;
	}

	public function getGoogleAccessToken(){
		$val = isset($this->settings['sln_access_token']) ? $this->settings['sln_access_token'] : '';
		return apply_filters('sln.settings.access_token.get', $val);
	}

	public function getGoogleRefreshToken(){
		$val = isset($this->settings['sln_refresh_token']) ? $this->settings['sln_refresh_token'] : '';
		return apply_filters('sln.settings.refresh_token.get', $val);
	}

	public function isCustomColorsEnabled() {
		return $this->get('style_colors_enabled') == 1 ? true : false;
	}

	public function isHidePrices() {
		return $this->get('hide_prices') == 1 ? true : false;
	}

	/**
	 * @return bool
	 */
	public function isAttendantsEnabled() {
		return $this->get('attendant_enabled') ? true : false;
	}

	public function isAttendantsEnabledOnlyBackend() {
		return $this->get('only_from_backend_attendant_enabled') ? true : false;
	}

	/**
	 * @return bool
	 */
	public function isMultipleAttendantsEnabled() {
		return $this->get('m_attendant_enabled') ? true : false;
	}

	public function isChooseAttendantForMeDisabled() {
		return $this->get('choose_attendant_for_me_disabled') ? true : false;
	}

	/**
	 * @return bool
	 */
	public function isPayEnabled() {
		return defined("SLN_VERSION_PAY") && SLN_VERSION_PAY && $this->get('pay_enabled');
	}

	public function getCustomText($key, $label = '') {
		$custom_texts = $this->get('custom_texts');
		if (isset($custom_texts[$key]) && !empty($custom_texts[$key])) {
			return __($custom_texts[$key], 'salon-booking-system');
		}

		return $label ? $label : __($key, 'salon-booking-system');
	}

	public function setCustomText($key, $value) {
		$custom_texts = $this->get('custom_texts');
		$custom_texts[$key] = $value;
		$this->set('custom_texts', $custom_texts);

                $text = "<?php \r\n";

                foreach ($custom_texts as $key => $value) {
                    $text .= "__('$value', 'salon-booking-system'); \r\n";
                }

                file_put_contents(SLN_PLUGIN_DIR . '/translate-constants.php', $text);

		return true;
	}

	public function isFormStepsAltOrder() {
		return $this->get('form_steps_alt_order') ? true : false;
	}

	/**
	 * Check if auto-attendant availability check is enabled
	 * Feature flag for attendant-aware time slot filtering
	 * 
	 * @return bool True if enabled (default), false if disabled
	 */
	public function isAutoAttendantCheckEnabled() {
		// Default to enabled (true) unless explicitly disabled
		return $this->get('auto_attendant_check_enabled') !== '0';
	}

	public function getDecimalSeparator() {
		return !is_null($this->get('pay_decimal_separator')) ? $this->get(
			'pay_decimal_separator'
		) : $this->getDecimalSeparatorDefault();
	}

	public function getDecimalSeparatorDefault() {
		return '.';
	}

	public function getThousandSeparator() {
		return !is_null($this->get('pay_thousand_separator')) ? $this->get(
			'pay_thousand_separator'
		) : $this->getThousandSeparatorDefault();
	}

	public function getThousandSeparatorDefault() {
		return ',';
	}

	/**
	 * @return SLN_Helper_AvailabilityItems
	 */
	public function getAvailabilityItems() {
		if (!isset($this->availabilityItems)) {
			$this->availabilityItems = new SLN_Helper_AvailabilityItems($this->get('availabilities'));
		}

		return apply_filters('sln.settings.availability_items', $this->availabilityItems);
	}

	/**
	 * @return SLN_Helper_AvailabilityItems
	 */
	public function getNewAvailabilityItems() {
		$ret = new SLN_Helper_AvailabilityItems($this->get('availabilities'));

		return apply_filters('sln.settings.availability_items', $ret);
	}

	public function getDailyHolidayItems() {
		$holidays = $this->get('holidays_daily') ?: array();

		$holidays = array_filter($holidays, function ($h) {
			foreach (['from_date',
				'to_date',
				'from_time',
				'to_time',
				'daily'] as $prop) {
				if (empty($h[$prop])) {
					return false;
				}
			}
			return true;
		});

		return $holidays;
	}

	/**
	 * @return SLN_Helper_HolidayItems
	 */
	public function getHolidayItems() {
		if (!isset($this->holidayItems)) {
			$holidays = $this->get('holidays') ?: array();
			$daily_holidays = $this->getDailyHolidayItems();
            $this->holidayItems = new SLN_Helper_HolidayItems(array_filter(array_merge($holidays, $daily_holidays), function($h) {
                $toDateTime = new SLN_DateTime($h['to_date'] . ' ' . $h['to_time']);
                $now = new SLN_DateTime();
                if ($toDateTime < $now) {
                    return false;
                }
                return true;
            }));
		}

		return apply_filters('sln.settings.availability_holiday_items', $this->holidayItems);
	}

	/**
	 * @return SLN_Helper_HolidayItems
	 */
	public function getNewHolidayItems() {
		$ret = new SLN_Helper_HolidayItems($this->get('holidays'));

		return apply_filters('sln.settings.availability_holiday_items', $ret);
	}

	public function getLocale() {
		return SLN_Helper_Multilingual::getCurrentLanguage();
	}

	public function getDateLocale() {
		return SLN_Helper_Multilingual::getDateLocale();
	}

	public function isTipRequestEnabled() {
		return defined("SLN_VERSION_PAY") && SLN_VERSION_PAY && $this->get('pay_tip_request');
	}

	public function isDisableNewUserWelcomeEmail() {
		return $this->get('disable_new_user_welcome_email');
	}

	public function isDisplaySlotsCustomerTimezone() {
		return $this->get('display_slots_customer_timezone');
	}

    public function getDefaultBookingStatus() {
        return $this->get('default_booking_status');
    }

    public function setDefaultBookingStatus($status) {
        return $this->set('default_booking_status', $status);
    }

	public function isGoogleFontsDisabled(){
		return $this->get('disable_google_fonts');
	}

    public function isResourcesEnabled() {
        return $this->get('enable_resources');
    }
}
