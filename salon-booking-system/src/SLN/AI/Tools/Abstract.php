<?php

/**
 * Shared base for AI Setup apply tools.
 */
abstract class SLN_AI_Tools_Abstract
{
	/** @var SLN_Plugin */
	protected $plugin;

	public function __construct(SLN_Plugin $plugin)
	{
		$this->plugin = $plugin;
	}

	/**
	 * @return string Tool name for registry / undo.
	 */
	abstract public function getName();

	/**
	 * Risk tier: apply | confirm | guidance
	 *
	 * @return string
	 */
	public function getTier()
	{
		return 'apply';
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	abstract public function preview(array $arguments);

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	abstract public function apply(array $arguments);

	/**
	 * @param mixed $previous
	 * @return array|WP_Error
	 */
	abstract public function restore($previous);

	/**
	 * @return SLN_Settings
	 */
	protected function settings()
	{
		return $this->plugin->getSettings();
	}

	protected function refreshBookingCaches()
	{
		if (class_exists('SLN_Helper_Availability_Cache')) {
			SLN_Helper_Availability_Cache::clearCache();
		}
		$this->plugin->getBookingCache()->refreshAll();
		$this->plugin->getBookingCache()->save();

		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sln_%'");
	}

	/**
	 * @param array $keys
	 * @return array
	 */
	protected function snapshotKeys(array $keys)
	{
		$out = array();
		$s   = $this->settings();
		foreach ($keys as $key) {
			$out[ $key ] = $s->get($key);
		}

		return $out;
	}

	/**
	 * @param array $before
	 * @param array $after
	 * @return string
	 */
	protected function formatKeyValueDiff(array $before, array $after)
	{
		$lines = array();
		$keys  = array_unique(array_merge(array_keys($before), array_keys($after)));
		foreach ($keys as $key) {
			$b = isset($before[ $key ]) ? $this->stringifyValue($before[ $key ]) : '(unset)';
			$a = isset($after[ $key ]) ? $this->stringifyValue($after[ $key ]) : '(unset)';
			if ($b === $a) {
				continue;
			}
			$lines[] = sprintf('%s: %s → %s', $key, $b, $a);
		}

		return $lines
			? implode("\n", $lines)
			: __('No changes detected.', 'salon-booking-system');
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	protected function stringifyValue($value)
	{
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		if (is_array($value)) {
			return wp_json_encode($value);
		}
		if ($value === null || $value === '') {
			return '(empty)';
		}

		return (string) $value;
	}

	/**
	 * @param array $values
	 */
	protected function writeSettings(array $values)
	{
		$s = $this->settings();
		foreach ($values as $k => $v) {
			$s->set($k, $v);
		}
		$s->save();
	}

	/**
	 * Normalize bool-ish tool args to '1'/'0' for salon_settings toggles.
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function toFlag($value)
	{
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}
		if (is_numeric($value)) {
			return ((int) $value) ? '1' : '0';
		}
		$v = strtolower(trim((string) $value));

		return in_array($v, array('1', 'true', 'yes', 'on'), true) ? '1' : '0';
	}

	/**
	 * @param string $title
	 * @param string $postType
	 * @return WP_Post|null
	 */
	protected function findPostByTitle($title, $postType)
	{
		$posts = get_posts(
			array(
				'post_type'      => $postType,
				'title'          => $title,
				'post_status'    => 'any',
				'posts_per_page' => 1,
			)
		);

		return $posts ? $posts[0] : null;
	}
}
