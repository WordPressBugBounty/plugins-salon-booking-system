<?php

/**
 * Guidance-only: explain where to configure a setting (Wave 0 + Wave 5 secrets).
 */
class SLN_AI_Tools_ExplainSetting extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'explain_setting';
	}

	public function getTier()
	{
		return 'guidance';
	}

	/**
	 * Topic catalog: topic => [label, tab, anchor, notes, sensitive]
	 *
	 * @return array
	 */
	public static function topics()
	{
		$base = admin_url('admin.php?page=salon-settings');

		return array(
			'opening_hours'   => array(
				'label'  => __('Opening hours / availability rules', 'salon-booking-system'),
				'url'    => $base . '&tab=booking#sln-online_booking_available_days',
				'notes'  => __('You can also describe hours in AI Setup chat and confirm a preview.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'holidays'        => array(
				'label'  => __('Holiday closures', 'salon-booking-system'),
				'url'    => $base . '&tab=booking#sln-holidays_days',
				'notes'  => __('Describe holidays in AI Setup chat, or edit Booking Rules → Holidays.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'payments'        => array(
				'label'     => __('Payments / Stripe / PayPal', 'salon-booking-system'),
				'url'       => $base . '&tab=payments',
				'notes'     => __('AI can adjust payment behaviour (enable, method, deposit, tips) via set_payment_behaviour, but cannot store gateway API keys. Paste credentials in Payments yourself.', 'salon-booking-system'),
				'sensitive' => true,
				'pro'       => true,
			),
			'sms'             => array(
				'label'     => __('SMS / WhatsApp', 'salon-booking-system'),
				'url'       => $base . '&tab=general',
				'notes'     => __('AI can toggle SMS behaviour (set_sms_behaviour) but cannot store SMS passwords or API keys. Configure the provider under General → SMS.', 'salon-booking-system'),
				'sensitive' => true,
			),
			'google_calendar' => array(
				'label'     => __('Google Calendar sync', 'salon-booking-system'),
				'url'       => $base . '&tab=gcalendar',
				'notes'     => __('OAuth client ID/secret must be set manually. After connection, AI can toggle sync behaviour (set_gcalendar_behaviour). Locking busy slots is a PRO option.', 'salon-booking-system'),
				'sensitive' => true,
			),
			'facebook_login'  => array(
				'label'     => __('Facebook login', 'salon-booking-system'),
				'url'       => $base . '&tab=checkout',
				'notes'     => __('App ID and secret are sensitive — paste them in Checkout settings only.', 'salon-booking-system'),
				'sensitive' => true,
			),
			'recaptcha'       => array(
				'label'     => __('reCAPTCHA', 'salon-booking-system'),
				'url'       => $base . '&tab=general',
				'notes'     => __('Site and secret keys must be entered manually in General settings.', 'salon-booking-system'),
				'sensitive' => true,
			),
			'maps'            => array(
				'label'     => __('Google Maps API key', 'salon-booking-system'),
				'url'       => $base . '&tab=general',
				'notes'     => __('API keys are not applied by AI. Paste the key in General settings.', 'salon-booking-system'),
				'sensitive' => true,
			),
			'onesignal'       => array(
				'label'     => __('OneSignal push', 'salon-booking-system'),
				'url'       => $base . '&tab=general',
				'notes'     => __('Configure OneSignal App ID manually under General.', 'salon-booking-system'),
				'sensitive' => true,
				'pro'       => true,
			),
			'zapier'          => array(
				'label'     => __('Zapier', 'salon-booking-system'),
				'url'       => $base . '&tab=general',
				'notes'     => __('Zapier API keys are managed in General settings, not via AI write.', 'salon-booking-system'),
				'sensitive' => true,
			),
			'license'         => array(
				'label'     => __('Plugin license', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-extensions'),
				'notes'     => __('License keys are never handled by AI Setup.', 'salon-booking-system'),
				'sensitive' => true,
			),
			'factory_reset'   => array(
				'label'     => __('Factory reset', 'salon-booking-system'),
				'url'       => $base . '&tab=documentation',
				'notes'     => __('Destructive reset is not available via AI. Use Support/Tools carefully if needed.', 'salon-booking-system'),
				'sensitive' => true,
			),
			'style'           => array(
				'label'     => __('Booking form style / colors', 'salon-booking-system'),
				'url'       => $base . '&tab=style',
				'notes'     => __('Style can be adjusted in Settings → Style, or via AI when style tools are available.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'checkout'        => array(
				'label'     => __('Checkout fields & guest options', 'salon-booking-system'),
				'url'       => $base . '&tab=checkout',
				'notes'     => __('Checkout behaviour lives under Settings → Checkout. Customer fidelity score is PRO.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'services'        => array(
				'label'     => __('Services', 'salon-booking-system'),
				'url'       => admin_url('edit.php?post_type=sln_service'),
				'notes'     => __('Manage services under Salon → Services. Ask AI to list them with find_service, or create/update via upsert_service.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'assistants'      => array(
				'label'     => __('Assistants / staff', 'salon-booking-system'),
				'url'       => admin_url('edit.php?post_type=sln_attendant'),
				'notes'     => __('Manage assistants under Salon → Assistants. Ask AI to list them with find_assistant, or create/update via upsert_assistant.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'calendar'        => array(
				'label'     => __('Salon Calendar', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon'),
				'notes'     => __('The admin calendar (Salon → Calendar) shows day/week/month bookings. AI cannot change calendar view UI, but can find bookings (find_booking), create/update reservations, and diagnose unavailable slots. Open the calendar from the link above — the floating AI assistant is also available on that page.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'discounts'       => array(
				'label'     => __('Discounts / coupons', 'salon-booking-system'),
				'url'       => admin_url('edit.php?post_type=sln_discount'),
				'notes'     => __('Manage coupons under Salon → Discounts (requires Discount add-on and enable_discount_system). Ask AI to list them with find_discount, or create/update via upsert_discount.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'customers'       => array(
				'label'     => __('Customers', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-customers'),
				'notes'     => __('Manage client profiles under Salon → Customers. Ask AI to find/list them with find_customer; for their appointments use find_booking.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'general'         => array(
				'label'     => __('General salon settings', 'salon-booking-system'),
				'url'       => $base . '&tab=general',
				'notes'     => __('Identity, locale, SMS, and more under Settings → General.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'booking_rules'   => array(
				'label'     => __('Booking rules', 'salon-booking-system'),
				'url'       => $base . '&tab=booking',
				'notes'     => __('Intervals, confirmation, cancellation under Settings → Booking Rules. Resources and nested bookings are PRO.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'resources'       => array(
				'label'     => __('Resources (rooms / equipment)', 'salon-booking-system'),
				'url'       => $base . '&tab=booking',
				'notes'     => __('Enable resources and manage resource items under Booking Rules (PRO).', 'salon-booking-system'),
				'sensitive' => false,
				'pro'       => true,
			),
			'nested_bookings' => array(
				'label'     => __('Nested bookings', 'salon-booking-system'),
				'url'       => $base . '&tab=booking',
				'notes'     => __('Nested bookings let overlapping services share an appointment slot where supported (PRO).', 'salon-booking-system'),
				'sensitive' => false,
				'pro'       => true,
			),
			'fidelity_score'  => array(
				'label'     => __('Customer fidelity score', 'salon-booking-system'),
				'url'       => $base . '&tab=checkout',
				'notes'     => __('Fidelity score helps prioritise returning customers (PRO).', 'salon-booking-system'),
				'sensitive' => false,
				'pro'       => true,
			),
			'edition'         => array(
				'label'     => __('Free vs PRO edition', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-extensions'),
				'notes'     => '',
				'sensitive' => false,
			),
			'changelog'       => array(
				'label'     => __('What\'s new / plugin changelog', 'salon-booking-system'),
				'url'       => SLN_AI_Changelog::PUBLIC_URL,
				'notes'     => '',
				'sensitive' => false,
			),
			'multishop'       => array(
				'label'     => __('Multi-shop', 'salon-booking-system'),
				'url'       => class_exists('\SalonMultishop\Addon')
					? admin_url('edit.php?post_type=sln_shop')
					: $base . '&tab=general',
				'notes'     => class_exists('\SalonMultishop\Addon')
					? __(
						'Multi-shop is active. Opening hours, holidays, and availability checks in AI Setup can target a shop (say “for shop Downtown”). Other per-shop overrides (identity, Google Calendar, managers) are edited on each shop in Salon → Shops.',
						'salon-booking-system'
					)
					: __(
						'Multi-shop overrides depend on the Multi-shop addon; install/activate it, then configure per-shop settings in Salon → Shops.',
						'salon-booking-system'
					),
				'sensitive' => false,
			),
			'debug'           => array(
				'label'     => __('Debug / worker role', 'salon-booking-system'),
				'url'       => $base . '&tab=documentation',
				'notes'     => __('Debug logging and worker role are under Support settings.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'onboarding'      => array(
				'label'     => __('Setup wizard / onboarding', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-onboarding'),
				'notes'     => __('Complete the setup wizard, or finish remaining steps via AI Setup for supported domains.', 'salon-booking-system'),
				'sensitive' => false,
			),
			'addons'          => array(
				'label'     => __('Official add-ons / Extensions', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-extensions'),
				'notes'     => sprintf(
					/* translators: %s: public add-ons URL */
					__('Browse and install official add-ons under Salon → Extensions. Product overview: %s. Ask AI with suggest_capability for a goal-based recommendation (waitlist, kiosk, multi-shop, etc.).', 'salon-booking-system'),
					SLN_AI_Ecosystem::ADDONS_URL
				),
				'sensitive' => false,
			),
			'waitlist'        => array(
				'label'     => __('Smart Waitlist add-on', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-extensions'),
				'notes'     => __(
					'Smart Waitlist helps recover cancelled or empty slots (no-shows / last-minute openings). It is a separate add-on — install from Salon → Extensions, then configure in the Waitlist settings. AI Setup cannot write Waitlist options; it can only guide you there.',
					'salon-booking-system'
				),
				'sensitive' => false,
			),
			'kiosk'           => array(
				'label'     => __('Walk-In Totem / Kiosk add-on', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-extensions'),
				'notes'     => __(
					'The Kiosk / Walk-In Totem add-on is for in-salon self-service booking (reception tablet). Install from Salon → Extensions and configure in the Kiosk settings. AI Setup provides guidance only for this add-on.',
					'salon-booking-system'
				),
				'sensitive' => false,
			),
			'communicator'    => array(
				'label'     => __('Communicator add-on', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-extensions'),
				'notes'     => __(
					'Communicator is the email-marketing add-on for customer campaigns. Install from Salon → Extensions; campaign content and provider credentials stay in Communicator settings (not written by AI Setup).',
					'salon-booking-system'
				),
				'sensitive' => false,
			),
			'woo_checkout'    => array(
				'label'     => __('WooCommerce Checkout add-on', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-extensions'),
				'notes'     => __(
					'WooCommerce Checkout lets booking payments run through WooCommerce. Requires WooCommerce plus this add-on from Salon → Extensions. Gateway keys stay in WooCommerce / the add-on — AI Setup will not store them.',
					'salon-booking-system'
				),
				'sensitive' => true,
			),
			'migrator'        => array(
				'label'     => __('Migrator add-on', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon-extensions'),
				'notes'     => __(
					'Migrator imports reservations from other booking platforms. Install from Salon → Extensions and run the import from the Migrator screens. AI Setup cannot run migrations for you.',
					'salon-booking-system'
				),
				'sensitive' => false,
			),
			'pwa'             => array(
				'label'     => __('Staff mobile web app (PWA)', 'salon-booking-system'),
				'url'       => admin_url('admin.php?page=salon'),
				'notes'     => __(
					'Salon Booking System includes a staff-oriented mobile web experience for the calendar and day-to-day booking work. Open Salon → Calendar on a phone/tablet (or the staff PWA entry your site exposes). AI Setup cannot change PWA install UI; ask if you need help with calendar bookings instead.',
					'salon-booking-system'
				),
				'sensitive' => false,
			),
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function preview(array $arguments)
	{
		$topic = isset($arguments['topic']) ? sanitize_key($arguments['topic']) : '';
		$topics = self::topics();
		if ($topic === '' || ! isset($topics[ $topic ])) {
			$list = implode(', ', array_keys($topics));

			return new WP_Error(
				'sln_ai_unknown_topic',
				sprintf(
					/* translators: %s: topic list */
					__('Unknown topic. Use one of: %s', 'salon-booking-system'),
					$list
				)
			);
		}

		$t = $topics[ $topic ];

		if ($topic === 'edition') {
			return $this->editionOverview();
		}
		if ($topic === 'changelog') {
			return $this->changelogOverview();
		}

		// On Free, PRO topics become upgrade guidance (still useful on PRO as normal deep-link).
		if (! empty($t['pro']) && ! SLN_AI_Edition::isPro()) {
			$blocked = SLN_AI_Edition::proRequiredGuidance(
				isset($t['pro_feature']) ? $t['pro_feature'] : $topic
			);
			// Keep topic-specific settings URL when available.
			if (! empty($t['url'])) {
				$blocked['summary'] .= "\n\n"
					. '[' . __('Feature settings page', 'salon-booking-system') . '](' . $t['url'] . ')';
				$blocked['url'] = $t['url'];
			}
			$blocked['arguments'] = array('topic' => $topic);

			return $blocked;
		}

		$lines = array(
			$t['label'],
			'',
			'[' . __('Open settings', 'salon-booking-system') . '](' . $t['url'] . ')',
			'',
			$t['notes'],
		);
		if ($topic === 'multishop' && SLN_AI_Multishop::isActive()) {
			$shops = SLN_AI_Multishop::listShops();
			if ($shops) {
				$lines[] = '';
				$lines[] = __('Published shops on this site:', 'salon-booking-system');
				foreach ($shops as $shop) {
					$lines[] = '• ' . $shop['name'];
				}
			}
		}
		if (! empty($t['pro'])) {
			$lines[] = '';
			$lines[] = sprintf(
				/* translators: %s: edition label */
				__('Edition note: this is a PRO feature. This site is %s.', 'salon-booking-system'),
				SLN_AI_Edition::label()
			);
		}
		if (! empty($t['sensitive'])) {
			$lines[] = '';
			$lines[] = __('Sensitive credentials will not be written by the assistant.', 'salon-booking-system');
		}
		$summary = implode("\n", $lines);

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => $summary,
			'url'       => $t['url'],
			'label'     => $t['label'],
			'arguments' => array('topic' => $topic),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
		);
	}

	/**
	 * @return array
	 */
	private function changelogOverview()
	{
		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => SLN_AI_Changelog::formatDetailSummary(),
			'url'       => SLN_AI_Changelog::PUBLIC_URL,
			'label'     => __('What\'s new / plugin changelog', 'salon-booking-system'),
			'arguments' => array('topic' => 'changelog'),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
		);
	}

	/**
	 * @return array
	 */
	private function editionOverview()
	{
		$lines   = array(
			sprintf(
				/* translators: %s: Free or PRO */
				__('This site is running Salon Booking System — %s edition.', 'salon-booking-system'),
				SLN_AI_Edition::label()
			),
			'',
			__('PRO-only features include:', 'salon-booking-system'),
		);
		foreach (SLN_AI_Edition::proFeatures() as $label) {
			$lines[] = '• ' . $label;
		}
		$lines[] = '';
		if (SLN_AI_Edition::isPro()) {
			$lines[] = __('You already have PRO. Ask how to configure a feature, or describe the change you want.', 'salon-booking-system');
		} else {
			$lines[] = __('On Free, AI Setup can still help with hours, holidays, identity, services, assistants, and basic booking rules.', 'salon-booking-system');
			$lines[] = '';
			$lines[] = sprintf(
				/* translators: %s: extensions URL */
				__('To unlock PRO features: %s', 'salon-booking-system'),
				SLN_AI_Edition::extensionsUrl()
			);
		}

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => implode("\n", $lines),
			'url'       => SLN_AI_Edition::extensionsUrl(),
			'arguments' => array('topic' => 'edition'),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
		);
	}

	/**
	 * Guidance tools do not persist.
	 *
	 * @param array $arguments
	 * @return array
	 */
	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);
		if (is_wp_error($preview)) {
			return $preview;
		}

		return array(
			'ok'      => true,
			'before'  => array(),
			'after'   => array(),
			'message' => $preview['summary'],
			'guidance'=> true,
		);
	}

	/**
	 * @param mixed $previous
	 * @return array|WP_Error
	 */
	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Guidance replies cannot be undone.', 'salon-booking-system')
		);
	}
}
