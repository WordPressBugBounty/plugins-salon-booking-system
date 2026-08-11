<?php

/**
 * Generic settings-key patch tool (Wave 1+ simple domains).
 */
abstract class SLN_AI_Tools_SimpleSettings extends SLN_AI_Tools_Abstract
{
	/**
	 * Map of setting_key => sanitizer callback name or 'text'|'int'|'flag'|'raw'
	 *
	 * @return array<string,string>
	 */
	abstract protected function allowedKeys();

	/**
	 * @return string
	 */
	abstract protected function successMessage();

	/**
	 * Whether apply should refresh booking caches.
	 *
	 * @return bool
	 */
	protected function needsCacheRefresh()
	{
		return false;
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function preview(array $arguments)
	{
		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$keys   = array_keys($mapped);
		$before = $this->snapshotKeys($keys);
		$diff   = $this->formatKeyValueDiff($before, $mapped);

		return array(
			'ok'        => true,
			'summary'   => $diff,
			'proposed'  => $mapped,
			'current'   => $before,
			'arguments' => array(
				'values'  => $mapped,
				'summary' => isset($arguments['summary']) ? sanitize_text_field($arguments['summary']) : '',
			),
			'tool'      => $this->getName(),
			'tier'      => $this->getTier(),
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$before = $this->snapshotKeys(array_keys($mapped));
		$this->writeSettings($mapped);
		if ($this->needsCacheRefresh()) {
			$this->refreshBookingCaches();
		}

		return array(
			'ok'      => true,
			'before'  => $before,
			'after'   => $mapped,
			'message' => $this->successMessage(),
		);
	}

	/**
	 * @param mixed $previous
	 * @return array|WP_Error
	 */
	public function restore($previous)
	{
		if (! is_array($previous)) {
			return new WP_Error('sln_ai_restore', __('Nothing to restore.', 'salon-booking-system'));
		}
		$this->writeSettings($previous);
		if ($this->needsCacheRefresh()) {
			$this->refreshBookingCaches();
		}

		return array(
			'ok'      => true,
			'message' => __('Settings restored.', 'salon-booking-system'),
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	protected function mapArguments(array $arguments)
	{
		$values = array();
		if (! empty($arguments['values']) && is_array($arguments['values'])) {
			$values = $arguments['values'];
		} else {
			// Allow flat args matching allowed keys.
			foreach ($this->allowedKeys() as $key => $type) {
				if (array_key_exists($key, $arguments)) {
					$values[ $key ] = $arguments[ $key ];
				}
			}
		}

		if (! $values) {
			return new WP_Error(
				'sln_ai_empty_values',
				__('No setting values provided.', 'salon-booking-system')
			);
		}

		$allowed = $this->allowedKeys();
		$out     = array();
		foreach ($values as $key => $value) {
			if (! isset($allowed[ $key ])) {
				continue;
			}
			$san = $this->sanitizeByType($value, $allowed[ $key ]);
			if ($san === null && $allowed[ $key ] !== 'flag') {
				return new WP_Error(
					'sln_ai_bad_value',
					sprintf(
						/* translators: %s: setting key */
						__('Invalid value for %s.', 'salon-booking-system'),
						$key
					)
				);
			}
			$out[ $key ] = $san;
		}

		if (! $out) {
			return new WP_Error(
				'sln_ai_empty_values',
				__('No allowed setting values provided.', 'salon-booking-system')
			);
		}

		return $out;
	}

	/**
	 * @param mixed  $value
	 * @param string $type
	 * @return mixed|null
	 */
	protected function sanitizeByType($value, $type)
	{
		switch ($type) {
			case 'flag':
				return $this->toFlag($value);
			case 'int':
				return absint($value);
			case 'text':
				return sanitize_text_field((string) $value);
			case 'email':
				$email = sanitize_email((string) $value);
				return $email ? $email : null;
			case 'url':
				$url = esc_url_raw((string) $value);
				return $url ? $url : '';
			case 'textarea':
				return sanitize_textarea_field((string) $value);
			case 'raw':
				return $value;
			default:
				return sanitize_text_field((string) $value);
		}
	}
}
