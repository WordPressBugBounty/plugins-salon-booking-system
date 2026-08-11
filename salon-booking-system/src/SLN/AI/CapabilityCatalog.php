<?php

/**
 * Unified searchable catalog of core features + official add-ons for AI suggestions.
 *
 * Merges SLN_Data_FeatureIndex with SLN_AI_Ecosystem add-ons and explain_setting topics.
 */
class SLN_AI_CapabilityCatalog
{
	/**
	 * Full catalog entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all()
	{
		$out = array();

		if (class_exists('SLN_Data_FeatureIndex')) {
			foreach (SLN_Data_FeatureIndex::get() as $row) {
				if (! is_array($row) || empty($row['id'])) {
					continue;
				}
				$id = sanitize_key($row['id']);
				$out[] = array(
					'id'          => $id,
					'kind'        => 'feature',
					'title'       => isset($row['title']) ? (string) $row['title'] : $id,
					'blurb'       => isset($row['description']) ? (string) $row['description'] : '',
					'keywords'    => isset($row['keywords']) ? (string) $row['keywords'] : '',
					'pro'         => ! empty($row['pro']),
					'topic'       => self::featureTopic($id),
					'url'         => self::featureUrl($row),
					'extensions'  => false,
				);
			}
		}

		foreach (SLN_AI_Ecosystem::officialAddons() as $row) {
			if (! is_array($row) || empty($row['id'])) {
				continue;
			}
			$id = sanitize_key($row['id']);
			$out[] = array(
				'id'         => $id,
				'kind'       => 'addon',
				'title'      => isset($row['name']) ? (string) $row['name'] : $id,
				'blurb'      => isset($row['blurb']) ? (string) $row['blurb'] : '',
				'keywords'   => isset($row['keywords']) ? (string) $row['keywords'] : '',
				'pro'        => false,
				'topic'      => isset($row['topic']) ? sanitize_key($row['topic']) : $id,
				'url'        => isset($row['url']) && $row['url'] !== ''
					? (string) $row['url']
					: SLN_AI_Edition::extensionsUrl(),
				'extensions' => true,
			);
		}

		// Staff PWA / mobile web app (core product capability, not a separate FeatureIndex row).
		$out[] = array(
			'id'         => 'pwa',
			'kind'       => 'feature',
			'title'      => __('Staff mobile web app (PWA)', 'salon-booking-system'),
			'blurb'      => __('Mobile web app for staff to manage the calendar and customers on the go.', 'salon-booking-system'),
			'keywords'   => 'pwa progressive web app mobile app staff worker tablet phone app staff',
			'pro'        => false,
			'topic'      => 'pwa',
			'url'        => admin_url('admin.php?page=salon'),
			'extensions' => false,
		);

		return apply_filters('sln_ai_capability_catalog', $out);
	}

	/**
	 * Search / suggest capabilities for a merchant question.
	 *
	 * @param string $query
	 * @param int    $limit
	 * @return array<int,array<string,mixed>>
	 */
	public static function search($query, $limit = 5)
	{
		$query = trim((string) $query);
		$limit = max(1, min(10, (int) $limit));
		$all   = self::all();

		// Browse intents: return a balanced sample (add-ons first, then core features).
		if ($query === '' || $query === 'capabilities' || $query === 'addons') {
			if ($query === 'addons') {
				$addons = array_values(
					array_filter(
						$all,
						function ($row) {
							return isset($row['kind']) && $row['kind'] === 'addon';
						}
					)
				);

				return array_slice($addons, 0, $limit);
			}
			$addons   = array();
			$features = array();
			foreach ($all as $row) {
				if (isset($row['kind']) && $row['kind'] === 'addon') {
					$addons[] = $row;
				} else {
					$features[] = $row;
				}
			}
			$mixed = array_merge(array_slice($addons, 0, 4), array_slice($features, 0, max(1, $limit - 4)));

			return array_slice($mixed, 0, $limit);
		}

		$needle = self::normalize($query);
		$tokens = preg_split('/\s+/', $needle);
		$tokens = array_values(
			array_filter(
				$tokens,
				function ($t) {
					return strlen($t) >= 3;
				}
			)
		);
		if (! $tokens) {
			$tokens = array($needle);
		}

		$scored = array();
		foreach ($all as $row) {
			$hay = self::normalize(
				$row['id'] . ' ' . $row['title'] . ' ' . $row['blurb'] . ' ' . $row['keywords']
				. ( ! empty($row['topic']) ? ' ' . $row['topic'] : '' )
			);
			$score = 0;
			if ($hay === $needle || $row['id'] === sanitize_key($query)) {
				$score += 100;
			}
			if (strpos($hay, $needle) !== false) {
				$score += 40;
			}
			foreach ($tokens as $tok) {
				if (strpos($hay, $tok) !== false) {
					$score += 10;
				}
			}
			if ($score > 0) {
				$row['_score'] = $score;
				$scored[]      = $row;
			}
		}

		usort(
			$scored,
			function ($a, $b) {
				return $b['_score'] - $a['_score'];
			}
		);

		$scored = array_slice($scored, 0, $limit);
		foreach ($scored as &$row) {
			unset($row['_score']);
		}

		return $scored;
	}

	/**
	 * Intent → catalog query for proactive mock/LLM routing.
	 *
	 * @param string $text Raw merchant message.
	 * @return string|null Query string for search(), or null.
	 */
	public static function detectIntentQuery($text)
	{
		$folded = self::normalize($text);

		$map = array(
			'waitlist'      => '/\b(waitlist|lista d.?attesa|no-?shows?|cancellazion\w*|slot vuot\w*|recover.{0,20}(cancel|slot)|posti liber\w*|last minute)\b/',
			'kiosk'         => '/\b(kiosk|totem|walk-?ins?|camminat\w*|reception|tablet.{0,20}(salon|salone)|self.?service.?booking)\b/',
			'communicator'  => '/\b(communicator|email marketing|newsletter|campagne|campaigns?|mass email|marketing.{0,20}(client|customer|clienti))\b/',
			'multishop'     => '/\b(multi[- ]?shops?|multishop|piu sedi|seconda sede|multiple locations?|altra sede|piu location)\b/',
			'migrator'      => '/\b(migrat\w*|import\w*.{0,30}(booking|prenotaz\w*|from another|da altro)|passare da|switch from)\b/',
			'woo_checkout'  => '/\b(woocommerce|woo.?commerce|woo checkout)\b/',
			'pwa'           => '/\b(pwa|app (mobile|staff)|mobile app|app per (staff|assistenti|operatori)|web app)\b/',
			'payments'      => '/\b(online payments?|pagament[oi] online|accept (card|payments?)|stripe|paypal|accont[oa]r\w* (carta|pagament\w*))\b/',
			'discounts'     => '/\b(coupons?|sconto|sconti|promo codes?|codice sconto|discounts?)\b/',
			'sms'           => '/\b(sms|whatsapp|promemoria sms|sms reminders?)\b/',
			'addons'        => '/\b(add-?ons?|estensioni|plugin aggiuntiv\w*|what add-?ons|quali add-?on|ecosystem)\b/',
			'capabilities'  => '/\b(what can (you|it|the plugin|salon)|cosa (puo|sai) (fare|configurare)|quali funzionalit\w*|feature list|capabilities|cosa offre)\b/',
		);

		foreach ($map as $query => $pattern) {
			if (preg_match($pattern, $folded)) {
				return $query;
			}
		}

		return null;
	}

	/**
	 * Fold + lowercase + strip fold artifacts for accent-safe matching.
	 *
	 * SLN_AI_Language::fold() turns "può" into "pu`o" (accent → backtick),
	 * so patterns/keywords must be matched against the stripped form.
	 *
	 * @param string $text
	 * @return string
	 */
	private static function normalize($text)
	{
		$folded = strtolower(SLN_AI_Language::fold((string) $text));

		return (string) preg_replace('/[`\'"^~]/', '', $folded);
	}

	/**
	 * @param string $featureId
	 * @return string
	 */
	private static function featureTopic($featureId)
	{
		$map = array(
			'calendar'              => 'calendar',
			'assistant_selection'   => 'assistants',
			'customers_per_session' => 'booking_rules',
			'opening_hours'         => 'opening_hours',
			'email_notifications'   => 'general',
			'sms'                   => 'sms',
			'payments'              => 'payments',
			'google_calendar'       => 'google_calendar',
			'discounts'             => 'discounts',
			'services'              => 'services',
			'assistants'            => 'assistants',
			'session_duration'      => 'booking_rules',
			'holidays'              => 'holidays',
			'reports'               => 'calendar',
		);

		return isset($map[ $featureId ]) ? $map[ $featureId ] : '';
	}

	/**
	 * @param array $row FeatureIndex row.
	 * @return string
	 */
	private static function featureUrl(array $row)
	{
		$id = isset($row['id']) ? $row['id'] : '';
		if ($id === 'calendar' || $id === 'reports') {
			return admin_url('admin.php?page=salon');
		}
		if ($id === 'services') {
			return admin_url('edit.php?post_type=' . SLN_Plugin::POST_TYPE_SERVICE);
		}
		if ($id === 'assistants') {
			return admin_url('edit.php?post_type=' . SLN_Plugin::POST_TYPE_ATTENDANT);
		}
		if ($id === 'discounts') {
			return admin_url('edit.php?post_type=sln_discount');
		}

		$tab = isset($row['settings_tab']) ? (string) $row['settings_tab'] : '';
		if ($tab !== '') {
			$url = admin_url('admin.php?page=salon-settings&tab=' . rawurlencode($tab));
			if (! empty($row['settings_anchor'])) {
				$url .= '#' . $row['settings_anchor'];
			}

			return $url;
		}

		return admin_url('admin.php?page=salon-settings');
	}
}
