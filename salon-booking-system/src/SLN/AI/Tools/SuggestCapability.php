<?php

/**
 * Guidance-only: suggest core features / official add-ons for a merchant intent.
 */
class SLN_AI_Tools_SuggestCapability extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'suggest_capability';
	}

	public function getTier()
	{
		return 'guidance';
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function preview(array $arguments)
	{
		$lang  = SLN_AI_Language::detect(
			isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : ''
		);
		$query = '';
		if (! empty($arguments['query'])) {
			$query = sanitize_text_field((string) $arguments['query']);
		} elseif (! empty($arguments['intent'])) {
			$query = sanitize_text_field((string) $arguments['intent']);
		}
		$limit = isset($arguments['limit']) ? absint($arguments['limit']) : 5;
		if ($limit < 1) {
			$limit = 5;
		}

		$hits = SLN_AI_CapabilityCatalog::search($query, $limit);
		if (! $hits) {
			return array(
				'ok'        => true,
				'guidance'  => true,
				'summary'   => SLN_AI_Language::phrase(
					$lang,
					'capability_none',
					__(
						'I couldn’t match that to a specific feature or add-on. Try naming the goal (e.g. waitlist, kiosk, payments, multi-shop), or open Extensions.',
						'salon-booking-system'
					)
				) . "\n\n[" . SLN_AI_Language::phrase(
					$lang,
					'open_extensions',
					__('Open Extensions', 'salon-booking-system')
				) . '](' . SLN_AI_Edition::extensionsUrl() . ')',
				'arguments' => array('query' => $query),
				'tool'      => $this->getName(),
				'tier'      => 'guidance',
				'hits'      => array(),
			);
		}

		$isBrowse = ( $query === '' || $query === 'capabilities' || $query === 'addons' );
		$parts    = array();
		$parts[]  = $isBrowse
			? SLN_AI_Language::phrase(
				$lang,
				'capability_browse',
				__('Here are relevant Salon Booking System capabilities and add-ons:', 'salon-booking-system')
			)
			: SLN_AI_Language::phrase(
				$lang,
				'capability_suggest',
				__('Based on what you asked, these capabilities or add-ons can help:', 'salon-booking-system')
			);

		foreach ($hits as $row) {
			$kindLabel = ( isset($row['kind']) && $row['kind'] === 'addon' )
				? SLN_AI_Language::phrase($lang, 'capability_kind_addon', __('Add-on', 'salon-booking-system'))
				: SLN_AI_Language::phrase($lang, 'capability_kind_feature', __('Feature', 'salon-booking-system'));
			$proNote = ! empty($row['pro']) && ! SLN_AI_Edition::isPro()
				? ' · ' . SLN_AI_Language::phrase($lang, 'capability_pro', __('PRO', 'salon-booking-system'))
				: '';

			$line = '• ' . $row['title'] . ' (' . $kindLabel . $proNote . ')';
			if (! empty($row['blurb'])) {
				$line .= ' — ' . $row['blurb'];
			}
			$parts[] = $line;

			$openLabel = ! empty($row['extensions'])
				? SLN_AI_Language::phrase($lang, 'open_extensions', __('Open Extensions', 'salon-booking-system'))
				: SLN_AI_Language::phrase($lang, 'open_settings', __('Open settings', 'salon-booking-system'));
			if (! empty($row['url'])) {
				$parts[] = '  [' . $openLabel . '](' . $row['url'] . ')';
			}
		}

		if (count($hits) === 1 && ! empty($hits[0]['topic'])) {
			$topic = $hits[0]['topic'];
			$topics = SLN_AI_Tools_ExplainSetting::topics();
			if (isset($topics[ $topic ]) && ! empty($topics[ $topic ]['notes'])) {
				$parts[] = '';
				$parts[] = $topics[ $topic ]['notes'];
			}
		}

		$parts[] = '';
		$parts[] = '[' . SLN_AI_Language::phrase(
			$lang,
			'open_addons_page',
			__('Official add-ons', 'salon-booking-system')
		) . '](' . SLN_AI_Ecosystem::ADDONS_URL . ')'
			. ' · [' . SLN_AI_Language::phrase(
				$lang,
				'open_extensions',
				__('Open Extensions', 'salon-booking-system')
			) . '](' . SLN_AI_Edition::extensionsUrl() . ')';

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => implode("\n", $parts),
			'arguments' => array('query' => $query, 'limit' => $limit),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'hits'      => $hits,
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);
		if (is_wp_error($preview)) {
			return $preview;
		}

		return array(
			'ok'       => true,
			'before'   => array(),
			'after'    => array(),
			'message'  => $preview['summary'],
			'guidance' => true,
		);
	}

	/**
	 * @param mixed $previous
	 * @return WP_Error
	 */
	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Capability suggestions cannot be undone.', 'salon-booking-system')
		);
	}
}
