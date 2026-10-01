<?php

/**
 * Redacted salon context for LLM prompts (no secrets).
 */
class SLN_AI_ContextPack
{
	const CATALOG_LIMIT   = 25;
	const CACHE_TTL       = 45;
	const CACHE_KEY_PREFIX = 'sln_ai_context_pack_';

	/**
	 * Drop per-user context cache (call after AI apply / undo so the next turn sees fresh settings).
	 */
	public static function bustCache()
	{
		if (! function_exists('delete_transient') || ! function_exists('get_current_user_id')) {
			return;
		}
		delete_transient(self::CACHE_KEY_PREFIX . (int) get_current_user_id());
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @return array
	 */
	public static function build(SLN_Plugin $plugin)
	{
		$cacheKey = self::CACHE_KEY_PREFIX . (int) get_current_user_id();
		$cached   = get_transient($cacheKey);
		if (is_array($cached) && ! empty($cached['_built_at'])) {
			unset($cached['_built_at']);

			return $cached;
		}

		SLN_AI_ToolRegistry::bootstrap();

		$settings = $plugin->getSettings();
		$hours    = new SLN_AI_Tools_SetAvailabilities($plugin);
		$holidays = new SLN_AI_Tools_SetHolidays($plugin);

		$avail = $settings->get('availabilities');
		$hols  = $settings->get('holidays');

		$services   = self::catalogServices($plugin);
		$assistants = self::catalogAssistants($plugin);
		$discounts  = SLN_AI_Tools_FindDiscount::catalogForContext($plugin, self::CATALOG_LIMIT);

		$pack = array(
			'timezone'               => wp_timezone_string(),
			'locale'                 => get_locale(),
			'admin_locale'           => function_exists('get_user_locale') ? get_user_locale() : get_locale(),
			'user_locale'            => function_exists('get_user_locale') ? get_user_locale() : get_locale(),
			'today'                  => wp_date('Y-m-d'),
			'edition'                => SLN_AI_Edition::key(),
			'edition_label'          => SLN_AI_Edition::label(),
			'is_pro'                 => SLN_AI_Edition::isPro(),
			'salon_name'             => (string) $settings->get('gen_name'),
			'salon_email'            => (string) $settings->get('gen_email'),
			'salon_phone'            => (string) $settings->get('gen_phone'),
			'interval'               => $settings->get('interval'),
			'parallels_hour'         => $settings->get('parallels_hour'),
			'hours_before_from'      => $settings->getHoursBeforeFrom(),
			'hours_before_to'        => $settings->getHoursBeforeTo(),
			'availability_mode'      => $settings->getAvailabilityMode(),
			'date_format'            => (string) $settings->get('date_format'),
			'time_format'            => (string) $settings->get('time_format'),
			'week_start'             => $settings->get('week_start'),
			'attendant_enabled'      => (bool) $settings->get('attendant_enabled'),
			'pay_enabled'            => (bool) $settings->get('pay_enabled'),
			'pay_method'             => (string) $settings->get('pay_method'),
			'sms_enabled'            => (bool) $settings->get('sms_enabled'),
			'sms_remind'             => (bool) $settings->get('sms_remind'),
			'email_remind'           => (bool) $settings->get('email_remind'),
			'email_remind_interval'  => (string) $settings->get('email_remind_interval'),
			'follow_up_interval'     => (string) $settings->get('follow_up_interval'),
			'google_calendar_enabled'=> (bool) $settings->get('google_calendar_enabled'),
			'onesignal_new'          => (bool) $settings->get('onesignal_new'),
			'confirmation'           => (bool) $settings->get('confirmation'),
			'booking_disabled'       => (bool) $settings->get('disabled'),
			'booking_page_id'        => (int) $settings->get('pay'),
			'thankyou_page_id'       => (int) $settings->get('thankyou'),
			'myaccount_page_id'      => (int) $settings->get('bookingmyaccount'),
			'enable_discount_system' => (bool) $settings->get('enable_discount_system'),
			'services_count'         => count($services),
			'assistants_count'       => count($assistants),
			'discounts_count'        => count($discounts),
			'customers_count'        => SLN_AI_Tools_FindCustomer::countForContext(),
			'calendar_url'           => admin_url('admin.php?page=salon'),
			'calendar_view'          => (string) $settings->get('calendar_view'),
			'services'               => $services,
			'assistants'             => $assistants,
			'discounts'              => $discounts,
			'pro_features'           => array_keys(SLN_AI_Edition::proFeatures()),
			'availabilities_summary' => $hours->formatAvailabilitiesSummary(is_array($avail) ? $avail : array()),
			'assistant_hours_custom' => SLN_AI_AvailabilityCascade::contextLines(
				$plugin,
				SLN_Plugin::POST_TYPE_ATTENDANT,
				SLN_AI_AvailabilityCascade::ATTENDANT_META
			),
			'service_hours_custom'   => SLN_AI_AvailabilityCascade::contextLines(
				$plugin,
				SLN_Plugin::POST_TYPE_SERVICE,
				SLN_AI_AvailabilityCascade::SERVICE_META
			),
			'holidays_summary'       => $holidays->formatHolidaysSummary(is_array($hols) ? $hols : array()),
			'ecosystem'              => SLN_AI_Ecosystem::contextFields(),
			'recent_changelog'       => SLN_AI_Changelog::contextFields(),
			'plugin_version'         => SLN_AI_Changelog::installedVersion(),
		);

		$pack = array_merge($pack, SLN_AI_Multishop::contextFields());

		if (! empty($pack['multishop_active']) && ! empty($pack['shops'])) {
			$shopSummaries = array();
			foreach ($pack['shops'] as $row) {
				$shop = SLN_AI_Multishop::loadShop((int) $row['id']);
				if (! $shop) {
					continue;
				}
				$shopAvail = SLN_AI_Multishop::getScopedSetting($shop, 'availabilities');
				$shopHols  = SLN_AI_Multishop::getScopedSetting($shop, 'holidays');
				$shopSummaries[] = array(
					'id'                     => (int) $row['id'],
					'name'                   => $row['name'],
					'availabilities_summary' => $hours->formatAvailabilitiesSummary(is_array($shopAvail) ? $shopAvail : array()),
					'holidays_summary'       => $holidays->formatHolidaysSummary(is_array($shopHols) ? $shopHols : array()),
				);
			}
			$pack['shops_detail'] = $shopSummaries;
			$pack['availabilities_summary'] = __('(global defaults — prefer per-shop hours when Multi-shop is active)', 'salon-booking-system')
				. "\n" . $pack['availabilities_summary'];
		}

		$toStore              = $pack;
		$toStore['_built_at'] = time();
		set_transient($cacheKey, $toStore, self::CACHE_TTL);

		return $pack;
	}

	/**
	 * Compact text block for Anthropic / OpenAI system prompts.
	 *
	 * @param array $context From build().
	 * @return string
	 */
	public static function formatForPrompt(array $context)
	{
		$lines   = array();
		$lines[] = 'Salon site context (authoritative; use real names/ids below):';
		$lines[] = sprintf(
			'Name: %s | Edition: %s | Plugin version: %s | Timezone: %s | Site locale: %s | Admin locale: %s | Today: %s',
			self::str($context, 'salon_name', '(unnamed)'),
			self::str($context, 'edition_label', self::str($context, 'edition', '?')),
			self::str($context, 'plugin_version', SLN_AI_Changelog::installedVersion()),
			self::str($context, 'timezone', ''),
			self::str($context, 'locale', ''),
			self::str($context, 'admin_locale', self::str($context, 'user_locale', '')),
			self::str($context, 'today', '')
		);
		$lines[] = 'Reply language: always match the latest user message language (not site/admin locale). Site locale is for formats/settings only.';
		$lines[] = 'Reservations: create_booking (one), create_bookings (2–10 dates or next-N weekday recurrence), update_booking (confirm-first), find_booking (lookup), count_bookings (how many / totals / up to today). Prefer service/assistant ids from the catalog below.';
		$lines[] = sprintf(
			'Discount system enabled: %s | discounts on site: %s (use find_discount to list/check; upsert_discount to create/update)',
			! empty($context['enable_discount_system']) ? 'yes' : 'no',
			isset($context['discounts_count']) ? (int) $context['discounts_count'] : 0
		);
		$lines[] = sprintf(
			'Customers (salon role) on site: %s — use find_customer to list/search (no customer PII in this context pack)',
			isset($context['customers_count']) ? (int) $context['customers_count'] : 0
		);
		$lines[] = sprintf(
			'Admin calendar: %s (default view setting: %s). Open via explain_setting topic=calendar. Bookings on a day → find_booking; create/edit → create_booking / create_bookings / update_booking.',
			self::str($context, 'calendar_url', ''),
			self::str($context, 'calendar_view', '(default)')
		);
		$lines[] = sprintf(
			'Slot interval: %s min | Customers per session (parallels_hour): %s | Availability mode: %s | Assistants enabled: %s',
			self::str($context, 'interval', '?'),
			self::str($context, 'parallels_hour', '?'),
			self::str($context, 'availability_mode', '?'),
			! empty($context['attendant_enabled']) ? 'yes' : 'no'
		);
		$lines[] = sprintf(
			'Booking window (hours_before): from %s · to %s | Online booking disabled: %s | Confirmation required: %s',
			self::str($context, 'hours_before_from', '?'),
			self::str($context, 'hours_before_to', '?'),
			! empty($context['booking_disabled']) ? 'yes' : 'no',
			! empty($context['confirmation']) ? 'yes' : 'no'
		);
		$lines[] = sprintf(
			'Payments enabled: %s (method: %s) | SMS enabled: %s | Email remind: %s (%s) | Follow-up interval: %s | GCal sync: %s | OneSignal notify: %s',
			! empty($context['pay_enabled']) ? 'yes' : 'no',
			self::str($context, 'pay_method', '(none)'),
			! empty($context['sms_enabled']) ? 'yes' : 'no',
			! empty($context['email_remind']) ? 'yes' : 'no',
			self::str($context, 'email_remind_interval', '?'),
			self::str($context, 'follow_up_interval', '?'),
			! empty($context['google_calendar_enabled']) ? 'yes' : 'no',
			! empty($context['onesignal_new']) ? 'yes' : 'no'
		);
		$lines[] = sprintf(
			'Pages — booking(pay): %s | thankyou: %s | my-account: %s (never write gateway/API/OAuth secrets)',
			self::str($context, 'booking_page_id', '0'),
			self::str($context, 'thankyou_page_id', '0'),
			self::str($context, 'myaccount_page_id', '0')
		);

		$lines[] = 'Services (id:name[:duration]): ' . self::formatCatalogLines(
			isset($context['services']) && is_array($context['services']) ? $context['services'] : array(),
			true
		);
		$lines[] = 'Assistants (id:name): ' . self::formatCatalogLines(
			isset($context['assistants']) && is_array($context['assistants']) ? $context['assistants'] : array(),
			false
		);
		$lines[] = 'Discounts (id:name[:code][:amount]): ' . self::formatDiscountLines(
			isset($context['discounts']) && is_array($context['discounts']) ? $context['discounts'] : array()
		);

		$hours = self::str($context, 'availabilities_summary', '(none)');
		$hols  = self::str($context, 'holidays_summary', '(none)');
		$lines[] = "Opening hours:\n" . $hours;
		$asstHours = isset($context['assistant_hours_custom']) && is_array($context['assistant_hours_custom'])
			? $context['assistant_hours_custom']
			: array();
		$svcHours = isset($context['service_hours_custom']) && is_array($context['service_hours_custom'])
			? $context['service_hours_custom']
			: array();
		$lines[] = 'Custom assistant hours (empty = inherit salon): ' . ( $asstHours ? implode('; ', $asstHours) : '(none)' );
		$lines[] = 'Custom service hours (empty = inherit salon): ' . ( $svcHours ? implode('; ', $svcHours) : '(none)' );
		$lines[] = 'Verify a desired timetable: inspect_availabilities with those rules (weekly hours always=true). Apply a timetable: set_salon_availabilities (also aligns blocking assistant/service rules).';
		$lines[] = "Holidays:\n" . $hols;

		if (! empty($context['multishop_active']) && ! empty($context['shops_detail']) && is_array($context['shops_detail'])) {
			$lines[] = 'Multi-shop is active. Per-shop hours/holidays:';
			foreach ($context['shops_detail'] as $row) {
				$lines[] = sprintf(
					'- #%d %s | hours: %s | holidays: %s',
					isset($row['id']) ? (int) $row['id'] : 0,
					isset($row['name']) ? $row['name'] : '',
					isset($row['availabilities_summary']) ? str_replace("\n", ' | ', (string) $row['availabilities_summary']) : '',
					isset($row['holidays_summary']) ? str_replace("\n", ' | ', (string) $row['holidays_summary']) : '(none)'
				);
			}
		}

		$changelogCtx = isset($context['recent_changelog']) && is_array($context['recent_changelog'])
			? $context['recent_changelog']
			: array();
		$lines[] = SLN_AI_Changelog::formatForPrompt($changelogCtx);

		$addonNames = array();
		if (! empty($context['ecosystem']['official_addons']) && is_array($context['ecosystem']['official_addons'])) {
			foreach ($context['ecosystem']['official_addons'] as $addon) {
				if (! empty($addon['name'])) {
					$addonNames[] = (string) $addon['name'];
				}
			}
		}
		$lines[] = 'Capability discovery: use suggest_capability for goal→feature/add-on matches; official add-ons: '
			. ( $addonNames ? implode(', ', $addonNames) : '(see Extensions)' )
			. '.';
		$docsUrl = '';
		if (! empty($context['ecosystem']['docs_url'])) {
			$docsUrl = (string) $context['ecosystem']['docs_url'];
		} elseif (class_exists('SLN_AI_DocsCatalog')) {
			$docsUrl = SLN_AI_DocsCatalog::KB_BASE . '/';
		}
		$lines[] = 'Official docs (second source after this site context / tools): lookup_docs. KB: '
			. ( $docsUrl !== '' ? $docsUrl : 'https://salonbookingsystem.helpscoutdocs.com/' )
			. ' — do not invent how-to steps; if docs disagree with live settings, trust live settings.';

		return implode("\n", $lines);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @return array<int,array{id:int,name:string,duration?:string}>
	 */
	private static function catalogServices(SLN_Plugin $plugin)
	{
		$posts = get_posts(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_SERVICE,
				'post_status'    => array('publish', 'draft', 'private'),
				'posts_per_page' => self::CATALOG_LIMIT,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ($posts as $p) {
			$s = $plugin->createService($p->ID);
			if (! $s || $s->isEmpty()) {
				continue;
			}
			$duration = $s->getDuration();
			$durStr   = '';
			if ($duration instanceof DateTimeInterface) {
				$durStr = $duration->format('H:i');
			} elseif ($duration) {
				$durStr = (string) $duration;
			}
			$row = array(
				'id'   => (int) $s->getId(),
				'name' => (string) $s->getName(),
			);
			if ($durStr !== '') {
				$row['duration'] = $durStr;
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @return array<int,array{id:int,name:string}>
	 */
	private static function catalogAssistants(SLN_Plugin $plugin)
	{
		$posts = get_posts(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_ATTENDANT,
				'post_status'    => array('publish', 'draft', 'private'),
				'posts_per_page' => self::CATALOG_LIMIT,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ($posts as $p) {
			$a = $plugin->createAttendant($p->ID);
			if (! $a || $a->isEmpty()) {
				continue;
			}
			$out[] = array(
				'id'   => (int) $a->getId(),
				'name' => (string) $a->getName(),
			);
		}

		return $out;
	}

	/**
	 * @param array $rows
	 * @param bool  $withDuration
	 * @return string
	 */
	private static function formatCatalogLines(array $rows, $withDuration)
	{
		if (! $rows) {
			return '(none)';
		}
		$bits = array();
		foreach ($rows as $row) {
			$id   = isset($row['id']) ? (int) $row['id'] : 0;
			$name = isset($row['name']) ? $row['name'] : '';
			if ($withDuration && ! empty($row['duration'])) {
				$bits[] = sprintf('%d:%s:%s', $id, $name, $row['duration']);
			} else {
				$bits[] = sprintf('%d:%s', $id, $name);
			}
		}

		return implode('; ', $bits);
	}

	/**
	 * @param array $rows
	 * @return string
	 */
	private static function formatDiscountLines(array $rows)
	{
		if (! $rows) {
			return '(none)';
		}
		$bits = array();
		foreach ($rows as $row) {
			$id     = isset($row['id']) ? (int) $row['id'] : 0;
			$name   = isset($row['name']) ? $row['name'] : '';
			$code   = isset($row['code']) ? (string) $row['code'] : '';
			$amount = isset($row['amount']) ? (string) $row['amount'] : '';
			$bit    = sprintf('%d:%s', $id, $name);
			if ($code !== '') {
				$bit .= ':' . $code;
			}
			if ($amount !== '') {
				$bit .= ':' . $amount;
			}
			$bits[] = $bit;
		}

		return implode('; ', $bits);
	}

	/**
	 * @param array  $context
	 * @param string $key
	 * @param string $default
	 * @return string
	 */
	private static function str(array $context, $key, $default = '')
	{
		if (! isset($context[ $key ]) || $context[ $key ] === '' || $context[ $key ] === null) {
			return $default;
		}

		return (string) $context[ $key ];
	}
}
