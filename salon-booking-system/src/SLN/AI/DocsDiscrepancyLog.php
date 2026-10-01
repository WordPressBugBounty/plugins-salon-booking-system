<?php

/**
 * Discrepancies between the Help Scout docs and the plugin code, reported by
 * the AI assistant (report_docs_discrepancy) so the docs can be corrected.
 *
 * Ring buffer in wp_options, deduplicated by article + setting + claim; never
 * leaves the site. REST: GET/DELETE salon/v1/ai-setup/docs-discrepancies.
 */
class SLN_AI_DocsDiscrepancyLog
{
	const OPTION   = 'sln_ai_docs_discrepancies';
	const MAX      = 100;
	const TEXT_MAX = 500;

	/**
	 * @param array $entry article_id, article_title, article_url, docs_claim, code_truth, setting_key, key_verified
	 * @return array Stored entry (with count / timestamps).
	 */
	public static function record(array $entry)
	{
		$clean = array(
			'article_id'    => sanitize_key(isset($entry['article_id']) ? (string) $entry['article_id'] : ''),
			'article_title' => self::text(isset($entry['article_title']) ? $entry['article_title'] : ''),
			'article_url'   => isset($entry['article_url']) ? (string) $entry['article_url'] : '',
			'docs_claim'    => self::text(isset($entry['docs_claim']) ? $entry['docs_claim'] : ''),
			'code_truth'    => self::text(isset($entry['code_truth']) ? $entry['code_truth'] : ''),
			'setting_key'   => sanitize_key(isset($entry['setting_key']) ? (string) $entry['setting_key'] : ''),
			'key_verified'  => ! empty($entry['key_verified']),
		);
		$hash = md5($clean['article_id'] . '|' . $clean['setting_key'] . '|' . strtolower($clean['docs_claim']));
		$now  = time();

		$log = self::all();
		foreach ($log as $i => $row) {
			if (isset($row['hash']) && $row['hash'] === $hash) {
				$log[ $i ]['count']      = (isset($row['count']) ? (int) $row['count'] : 1) + 1;
				$log[ $i ]['last_seen']  = $now;
				$log[ $i ]['code_truth'] = $clean['code_truth'];
				$stored                  = $log[ $i ];
				unset($log[ $i ]);
				$log[] = $stored;
				update_option(self::OPTION, array_values($log), false);

				return $stored;
			}
		}

		$stored = array_merge(
			$clean,
			array(
				'hash'       => $hash,
				'count'      => 1,
				'first_seen' => $now,
				'last_seen'  => $now,
			)
		);
		$log[]  = $stored;
		if (count($log) > self::MAX) {
			$log = array_slice($log, -self::MAX);
		}
		update_option(self::OPTION, array_values($log), false);

		return $stored;
	}

	/**
	 * @param int $limit
	 * @return array Most recent first.
	 */
	public static function recent($limit = 50)
	{
		return array_slice(array_reverse(self::all()), 0, max(1, (int) $limit));
	}

	public static function clear()
	{
		delete_option(self::OPTION);
	}

	/**
	 * @return array
	 */
	private static function all()
	{
		$log = get_option(self::OPTION, array());

		return is_array($log) ? array_values($log) : array();
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function text($value)
	{
		return SLN_AI_MessageFormat::truncate(sanitize_text_field((string) $value), self::TEXT_MAX);
	}
}
