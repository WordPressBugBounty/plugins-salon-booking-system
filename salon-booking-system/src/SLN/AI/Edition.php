<?php

/**
 * Free vs PRO awareness for AI Setup (context, prompts, guidance, tool gates).
 */
class SLN_AI_Edition
{
	/**
	 * @return bool
	 */
	public static function isPro()
	{
		return defined('SLN_VERSION_PAY') && SLN_VERSION_PAY;
	}

	/**
	 * @return string free|pro
	 */
	public static function key()
	{
		return self::isPro() ? 'pro' : 'free';
	}

	/**
	 * @return string
	 */
	public static function label()
	{
		return self::isPro()
			? __('PRO', 'salon-booking-system')
			: __('Free', 'salon-booking-system');
	}

	/**
	 * @return string
	 */
	public static function extensionsUrl()
	{
		return admin_url('admin.php?page=salon-extensions');
	}

	/**
	 * Official pricing page — same Free→PRO CTA used across the plugin.
	 *
	 * @return string
	 */
	public static function pricingUrl()
	{
		$url = defined('SLN_PRICING_URL')
			? SLN_PRICING_URL
			: 'https://www.salonbookingsystem.com/plugin-pricing-2/';

		return (string) apply_filters('sln_ai_pricing_url', $url);
	}

	/**
	 * PRO-oriented capabilities the assistant should know about.
	 * Keys align with explain_setting topics where possible.
	 *
	 * @return array<string,string> feature_key => short label
	 */
	public static function proFeatures()
	{
		return array(
			'payments'         => __('Online payments (Stripe/PayPal), deposits, tips, tax', 'salon-booking-system'),
			'resources'        => __('Resources (rooms/equipment) in the booking flow', 'salon-booking-system'),
			'onesignal'        => __('OneSignal push notifications', 'salon-booking-system'),
			'nested_bookings'  => __('Nested bookings', 'salon-booking-system'),
			'specific_dates'   => __('Availability rules limited to specific dates', 'salon-booking-system'),
			'fidelity_score'   => __('Customer fidelity score', 'salon-booking-system'),
			'summary_countdown'=> __('Disable summary skip countdown', 'salon-booking-system'),
			'customer_timezone'=> __('Display slots in the customer’s timezone', 'salon-booking-system'),
			'gcal_lock_slots'  => __('Google Calendar lock busy slots (advanced GCal option)', 'salon-booking-system'),
		);
	}

	/**
	 * Compact Free/PRO map for the LLM system prompt.
	 *
	 * @return string
	 */
	public static function instructionsSnippet()
	{
		$proList = array();
		foreach (self::proFeatures() as $label) {
			$proList[] = $label;
		}

		return 'Plugin edition on this site: ' . self::key() . ' (' . self::label() . '). '
			. 'PRO-only product features include: ' . implode('; ', $proList) . '. '
			. 'On Free: never claim those features can be enabled here; call explain_setting '
			. '(topics: edition, payments, resources, onesignal, nested_bookings, fidelity_score) '
			. 'and mention upgrading via Salon → Extensions. '
			. 'Free AI Setup can still configure hours, holidays, identity, locale, booking rules, '
			. 'notification schedules, SMS behaviour toggles, booking pages, services/assistants, style, currency display, and guest checkout. '
			. 'PRO-gated tools return upgrade guidance on Free. '
			. 'Secrets (API keys, OAuth, license) are never written — always guidance + Settings deep-link.';
	}

	/**
	 * Guidance payload when a Free site asks to configure a PRO feature.
	 *
	 * @param string $featureKey Key from proFeatures() or a topic name.
	 * @return array
	 */
	public static function proRequiredGuidance($featureKey)
	{
		$features = self::proFeatures();
		$label    = isset($features[ $featureKey ])
			? $features[ $featureKey ]
			: __('This feature', 'salon-booking-system');
		$url      = self::extensionsUrl();

		$lines = array(
			sprintf(
				/* translators: %s: feature label */
				__('%s is available on the PRO edition.', 'salon-booking-system'),
				$label
			),
			'',
			sprintf(
				/* translators: %s: current edition label */
				__('This site is running the %s edition.', 'salon-booking-system'),
				self::label()
			),
			'',
			__('Upgrade to PRO to unlock it, then configure it in Settings (or ask again after upgrading).', 'salon-booking-system'),
			'',
			sprintf(
				/* translators: %s: admin URL */
				__('Open Extensions: %s', 'salon-booking-system'),
				$url
			),
		);

		return array(
			'ok'       => true,
			'guidance' => true,
			'summary'  => implode("\n", $lines),
			'url'      => $url,
			'edition'  => self::key(),
			'feature'  => $featureKey,
			'tool'     => 'explain_setting',
			'tier'     => 'guidance',
		);
	}

	/**
	 * @param string $featureKey
	 * @return array|null Guidance array when blocked on Free; null when allowed.
	 */
	public static function blockIfFree($featureKey)
	{
		if (self::isPro()) {
			return null;
		}

		return self::proRequiredGuidance($featureKey);
	}
}
