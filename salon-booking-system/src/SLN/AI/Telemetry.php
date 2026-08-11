<?php

/**
 * Local telemetry for AI Setup intents the assistant could not route.
 *
 * When a merchant message falls through to the generic fallback (no tool call,
 * no guidance topic), we record it in a small ring buffer stored in wp_options.
 * This shows what merchants actually ask that the assistant does not understand,
 * so parsing/catalog gaps can be fixed with real data.
 *
 * Privacy: data never leaves the site. Messages are truncated and lightly
 * redacted (emails, long token-like strings). Disable via the
 * `sln_ai_telemetry_enabled` filter.
 */
class SLN_AI_Telemetry
{
	const OPTION      = 'sln_ai_intent_misses';
	const MAX_ENTRIES = 100;
	const MSG_MAX_LEN = 200;

	/**
	 * @return bool
	 */
	public static function enabled()
	{
		return (bool) apply_filters('sln_ai_telemetry_enabled', true);
	}

	/**
	 * Record a message the assistant failed to route.
	 *
	 * Repeated identical messages bump a counter instead of adding entries,
	 * so frequent gaps float to the top.
	 *
	 * @param string $message Raw merchant message.
	 * @param string $lang    Detected language code ('' when unknown).
	 * @param string $source  Where the miss happened (e.g. mock_fallback).
	 */
	public static function recordMiss($message, $lang = '', $source = 'mock_fallback')
	{
		if (! self::enabled()) {
			return;
		}

		$message = self::redact(trim((string) $message));
		if ($message === '') {
			return;
		}
		if (function_exists('mb_substr')) {
			$message = mb_substr($message, 0, self::MSG_MAX_LEN);
		} else {
			$message = substr($message, 0, self::MSG_MAX_LEN);
		}

		$key     = md5(strtolower($message));
		$now     = gmdate('c');
		$entries = self::entries();

		if (isset($entries[ $key ])) {
			$entries[ $key ]['count']  += 1;
			$entries[ $key ]['last_at'] = $now;
		} else {
			$entries[ $key ] = array(
				'msg'      => $message,
				'lang'     => sanitize_key((string) $lang),
				'source'   => sanitize_key((string) $source),
				'count'    => 1,
				'first_at' => $now,
				'last_at'  => $now,
			);
		}

		if (count($entries) > self::MAX_ENTRIES) {
			uasort(
				$entries,
				function ($a, $b) {
					return strcmp($a['last_at'], $b['last_at']);
				}
			);
			$entries = array_slice($entries, count($entries) - self::MAX_ENTRIES, null, true);
		}

		update_option(self::OPTION, $entries, false);
	}

	/**
	 * Most recent misses first.
	 *
	 * @param int $limit
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent($limit = 50)
	{
		$entries = self::entries();
		uasort(
			$entries,
			function ($a, $b) {
				return strcmp($b['last_at'], $a['last_at']);
			}
		);

		return array_slice(array_values($entries), 0, max(1, (int) $limit));
	}

	public static function clear()
	{
		delete_option(self::OPTION);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function entries()
	{
		$raw = get_option(self::OPTION, array());

		return is_array($raw) ? $raw : array();
	}

	/**
	 * Strip obvious secrets/PII before storing: emails and long token-like strings.
	 *
	 * @param string $text
	 * @return string
	 */
	private static function redact($text)
	{
		$text = preg_replace('/[^\s@]+@[^\s@]+\.[^\s@]+/', '[email]', (string) $text);
		$text = preg_replace('/\b(sk|pk|rk|whsec|key|token|bearer)[-_][A-Za-z0-9_\-]{8,}\b/i', '[secret]', $text);
		$text = preg_replace('/\b[A-Za-z0-9]{24,}\b/', '[secret]', $text);

		return $text;
	}
}
