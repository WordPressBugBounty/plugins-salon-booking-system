<?php

/**
 * State-driven proactive suggestions for the AI Setup UI.
 *
 * Picks suggestion chips from the real site state (missing services, disabled
 * payments/SMS/discounts, add-on discovery) so merchants see what the plugin
 * can do for them instead of generic examples. Chip texts are phrased as
 * messages that route well through the assistant in every supported language.
 */
class SLN_AI_Proactive
{
	const DEFAULT_LIMIT = 4;

	/**
	 * Localized suggestion chips for the current site state (admin locale).
	 *
	 * @param SLN_Plugin $plugin
	 * @param int        $limit
	 * @return string[]
	 */
	public static function suggestions(SLN_Plugin $plugin, $limit = self::DEFAULT_LIMIT)
	{
		$settings = $plugin->getSettings();

		$serviceIds = get_posts(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_SERVICE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$state = array(
			'has_services'     => ! empty($serviceIds),
			'pay_enabled'      => (bool) $settings->get('pay_enabled'),
			'sms_enabled'      => (bool) $settings->get('sms_enabled'),
			'gcal_enabled'     => (bool) $settings->get('google_calendar_enabled'),
			'discounts_enabled'=> (bool) $settings->get('enable_discount_system'),
		);

		return self::localizeKeys(self::suggestionKeys($state, $limit));
	}

	/**
	 * Ordered chip phrase-keys for a given state (pure — unit-testable).
	 *
	 * Onboarding gaps first, then one add-on discovery chip, then defaults.
	 *
	 * @param array $state See suggestions().
	 * @param int   $limit
	 * @return string[]
	 */
	public static function suggestionKeys(array $state, $limit = self::DEFAULT_LIMIT)
	{
		$limit = max(1, (int) $limit);
		$keys  = array();

		if (empty($state['has_services'])) {
			$keys[] = 'chip_first_service';
		}
		if (empty($state['pay_enabled'])) {
			$keys[] = 'chip_payments';
		}
		if (empty($state['sms_enabled'])) {
			$keys[] = 'chip_sms';
		}
		if (empty($state['discounts_enabled'])) {
			$keys[] = 'chip_discounts';
		}
		if (empty($state['gcal_enabled'])) {
			$keys[] = 'chip_gcal';
		}

		// Add-on discovery: always propose one capability the merchant may not know.
		$keys[] = 'chip_waitlist';
		$keys[] = 'chip_kiosk';

		// Evergreen examples fill the remaining slots.
		$keys[] = 'chip_hours';
		$keys[] = 'chip_holidays';
		$keys[] = 'chip_booking';
		$keys[] = 'chip_find';

		return array_slice(array_values(array_unique($keys)), 0, $limit);
	}

	/**
	 * All chip keys with their English fallbacks.
	 *
	 * @return array<string,string>
	 */
	public static function chipFallbacks()
	{
		return array(
			'chip_first_service' => __('Create my first service: Haircut, 30 minutes, €25', 'salon-booking-system'),
			'chip_payments'      => __('How do I accept online payments?', 'salon-booking-system'),
			'chip_sms'           => __('How do I enable SMS reminders?', 'salon-booking-system'),
			'chip_discounts'     => __('How do I create a discount code?', 'salon-booking-system'),
			'chip_gcal'          => __('How do I connect Google Calendar?', 'salon-booking-system'),
			'chip_waitlist'      => __('How do I recover last-minute cancellations?', 'salon-booking-system'),
			'chip_kiosk'         => __('Can walk-ins book from a tablet in the salon?', 'salon-booking-system'),
			'chip_hours'         => __('I\'m open Monday to Saturday 9-18', 'salon-booking-system'),
			'chip_holidays'      => __('Closed on 25 December', 'salon-booking-system'),
			'chip_booking'       => __('Create a booking for Mario Rossi, Haircut, tomorrow at 10:00', 'salon-booking-system'),
			'chip_find'          => __('Find booking #16', 'salon-booking-system'),
		);
	}

	/**
	 * @param string[] $keys
	 * @return string[]
	 */
	private static function localizeKeys(array $keys)
	{
		$lang      = SLN_AI_Language::fromWpLocale();
		$fallbacks = self::chipFallbacks();
		$out       = array();
		foreach ($keys as $key) {
			if (! isset($fallbacks[ $key ])) {
				continue;
			}
			$out[] = SLN_AI_Language::phrase($lang, $key, $fallbacks[ $key ]);
		}

		return $out;
	}
}
