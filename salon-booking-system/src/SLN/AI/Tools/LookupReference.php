<?php

/**
 * Guidance-only: query the code-generated reference (settings, admin screens,
 * Free/PRO features). Source of truth over the Help Scout docs.
 */
class SLN_AI_Tools_LookupReference extends SLN_AI_Tools_Abstract
{
	const VALUE_MAX = 300;

	public function getName()
	{
		return 'lookup_reference';
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
		$key   = isset($arguments['key']) ? sanitize_key((string) $arguments['key']) : '';
		$query = isset($arguments['query']) ? sanitize_text_field((string) $arguments['query']) : '';
		$kind  = isset($arguments['kind']) && in_array($arguments['kind'], array('setting', 'screen', 'feature'), true)
			? (string) $arguments['kind']
			: '';
		$limit = isset($arguments['limit']) ? absint($arguments['limit']) : 6;
		$limit = $limit > 0 ? $limit : 6;

		$base = array(
			'ok'        => true,
			'guidance'  => true,
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'arguments' => array_filter(
				array(
					'key'   => $key,
					'query' => $query,
					'kind'  => $kind,
				)
			),
		);

		if (! SLN_AI_Reference::isAvailable()) {
			return array_merge(
				$base,
				array(
					'summary'   => 'The code reference is not available on this build. Use live tools and site context; treat documentation as possibly outdated.',
					'matches'   => array(),
					'reference' => array('available' => false),
				)
			);
		}

		$rows = array();
		if ($key !== '') {
			$row = SLN_AI_Reference::findSetting($key);
			if ($row) {
				$row['kind'] = 'setting';
				$rows[]      = $row;
			}
		}
		if (! $rows && ( $query !== '' || $key !== '' )) {
			$rows = SLN_AI_Reference::search($query !== '' ? $query : $key, $kind, $limit);
		}

		$matches = array();
		foreach ($rows as $row) {
			$matches[] = $this->present($row);
		}

		$meta = SLN_AI_Reference::meta();
		if (! $matches) {
			$summary = 'No setting, screen or feature in the code reference matches this query.';
		} else {
			$lines = array();
			foreach ($matches as $m) {
				$label   = ! empty($m['label']) ? $m['label'] : ( ! empty($m['title']) ? $m['title'] : $m['id'] );
				$where   = ! empty($m['tab_label']) ? $m['tab_label'] . ( ! empty($m['section']) ? ' → ' . $m['section'] : '' ) : '';
				$lines[] = '• ' . $label . ' [' . $m['kind'] . ': ' . $m['id'] . ']'
					. ( $where !== '' ? ' — ' . $where : '' )
					. ( ! empty($m['pro']) ? ' (PRO)' : '' )
					. ( ! empty($m['url']) ? ' — ' . $m['url'] : '' );
			}
			$summary = implode("\n", $lines);
		}

		return array_merge(
			$base,
			array(
				'summary'   => $summary,
				'hint'      => 'Keyword matches on English admin labels. If none of them answers the merchant\'s question, call lookup_reference again with other English words or synonyms from the admin UI before answering.',
				'matches'   => $matches,
				'reference' => array(
					'available'      => true,
					'plugin_version' => $meta['plugin_version'],
					'generated_at'   => $meta['generated_at'],
					'stale'          => $meta['stale'],
				),
			)
		);
	}

	/**
	 * @param array $row
	 * @return array
	 */
	private function present(array $row)
	{
		$kind = isset($row['kind']) ? $row['kind'] : 'setting';
		$out  = array(
			'kind' => $kind,
			'id'   => isset($row['key']) ? $row['key'] : ( isset($row['id']) ? $row['id'] : ( isset($row['slug']) ? $row['slug'] : '' ) ),
			'url'  => SLN_AI_Reference::url(isset($row['path']) ? $row['path'] : null),
		);
		foreach (array('label', 'title', 'description', 'tab', 'tab_label', 'section', 'type', 'help', 'pro', 'capability') as $field) {
			if (isset($row[ $field ]) && $row[ $field ] !== null && $row[ $field ] !== '') {
				$out[ $field ] = $row[ $field ];
			}
		}

		if ($kind === 'setting') {
			if (array_key_exists('default', $row)) {
				$out['default'] = SLN_AI_Reference::isSecretKey($out['id']) ? '[secret]' : $this->clip($row['default']);
			}
			if (! empty($row['allowed']) && is_array($row['allowed'])) {
				$out['allowed'] = array_slice($row['allowed'], 0, 30);
				if (count($row['allowed']) > 30) {
					$out['allowed_truncated'] = count($row['allowed']);
				}
			}
			if (! empty($row['allowed_range']) && is_array($row['allowed_range'])) {
				$out['allowed_range'] = $row['allowed_range'];
			}
			if (! empty($row['allowed_source']) && is_string($row['allowed_source'])) {
				$out['allowed_source'] = 'any ' . $row['allowed_source'] . ' of this site (value = its ID)';
			}
			$out['current_value'] = SLN_AI_Reference::isSecretKey($out['id'])
				? '[redacted]'
				: $this->clip($this->settings()->get($out['id']));
		}

		return $out;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function clip($value)
	{
		if (is_array($value) || is_object($value)) {
			return SLN_AI_MessageFormat::truncate((string) wp_json_encode($value), self::VALUE_MAX);
		}
		if (is_string($value)) {
			return SLN_AI_MessageFormat::truncate($value, self::VALUE_MAX);
		}

		return $value;
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);

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
		return new WP_Error('sln_ai_no_undo', __('Reference lookups cannot be undone.', 'salon-booking-system'));
	}
}
