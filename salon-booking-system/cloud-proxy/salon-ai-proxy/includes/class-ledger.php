<?php

/**
 * Per-site AI query ledger (included monthly + purchased credits).
 */
class SLN_AI_Proxy_Ledger
{
	const OPTION_PREFIX = 'sln_ai_ledger_';
	const FREE_INCLUDED = 10;
	const PRO_INCLUDED  = 100;
	/** Agent loop: max iterations (5) + final answer + retry slack. */
	const TURN_MAX_CALLS = 8;
	const TURN_TTL       = 900;

	/**
	 * @return array<string,array{queries:int,price_cents:int,label:string}>
	 */
	public static function packs()
	{
		return array(
			'small'  => array(
				'queries'     => 50,
				'price_cents' => 900,
				'label'       => '50 queries',
			),
			'medium' => array(
				'queries'     => 200,
				'price_cents' => 2900,
				'label'       => '200 queries',
			),
			'large'  => array(
				'queries'     => 500,
				'price_cents' => 5900,
				'label'       => '500 queries',
			),
		);
	}

	/**
	 * @param array $auth
	 * @return string
	 */
	public static function siteKey(array $auth)
	{
		$site = isset($auth['site_url']) ? (string) $auth['site_url'] : '';
		$site = untrailingslashit(strtolower(esc_url_raw($site)));
		if ($site === '') {
			$license = isset($auth['license_key']) ? (string) $auth['license_key'] : '';
			$site    = 'license:' . md5($license);
		}

		return self::OPTION_PREFIX . md5($site);
	}

	/**
	 * @param string $edition free|pro
	 * @return int
	 */
	public static function includedLimit($edition)
	{
		return ($edition === 'pro') ? self::PRO_INCLUDED : self::FREE_INCLUDED;
	}

	/**
	 * Pin the per-site secret token on first contact, then require it to match.
	 * Stores only a hash.
	 *
	 * On first registration the proxy calls back the claimed site_url and
	 * compares the token hash the site itself publishes, so a third party
	 * cannot squat someone else's URL just by contacting the proxy first.
	 * When the callback is unreachable it falls back to trust-on-first-use.
	 *
	 * @param array  $auth
	 * @param string $token Plaintext token from the request.
	 * @return string ok|mismatch|busy
	 */
	public static function verifyOrRegisterToken(array $auth, $token)
	{
		$key  = self::siteKey($auth);
		$hash = hash('sha256', (string) $token);

		if (! self::acquireLock($key)) {
			return 'busy';
		}

		wp_cache_delete($key, 'options');
		$raw = get_option($key, array());
		if (! is_array($raw)) {
			$raw = array();
		}

		if (! empty($raw['token_hash'])) {
			$ok = hash_equals((string) $raw['token_hash'], $hash);
			self::releaseLock($key);

			return $ok ? 'ok' : 'mismatch';
		}

		// First contact: prove ownership by asking the site for its token hash.
		$published = self::fetchPublishedTokenHash($auth);
		if ($published !== null && ! hash_equals($published, $hash)) {
			self::releaseLock($key);

			return 'mismatch';
		}

		$raw['token_hash'] = $hash;
		update_option($key, $raw, false);
		self::releaseLock($key);

		return 'ok';
	}

	/**
	 * Ask the merchant site for the SHA-256 of its own token
	 * (public salon/v1/ai-setup/site-verify endpoint, plugin >= 10.31.0).
	 *
	 * @param array $auth
	 * @return string|null Hash, or null when it cannot be determined.
	 */
	private static function fetchPublishedTokenHash(array $auth)
	{
		$site = isset($auth['site_url']) ? (string) $auth['site_url'] : '';
		if ($site === '') {
			return null;
		}

		$endpoints = array(
			trailingslashit($site) . 'wp-json/salon/v1/ai-setup/site-verify',
			// Sites without pretty permalinks do not serve /wp-json/.
			trailingslashit($site) . '?rest_route=/salon/v1/ai-setup/site-verify',
		);

		foreach ($endpoints as $endpoint) {
			$response = wp_remote_get(
				$endpoint,
				array(
					'timeout'            => 5,
					'redirection'        => 2,
					// Blocks callbacks to private/loopback addresses (SSRF).
					'reject_unsafe_urls' => true,
				)
			);
			if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
				continue;
			}

			$body = json_decode(wp_remote_retrieve_body($response), true);
			if (! is_array($body) || empty($body['token_sha256']) || ! is_string($body['token_sha256'])) {
				continue;
			}

			$hash = strtolower(trim($body['token_sha256']));
			if (preg_match('/^[a-f0-9]{64}$/', $hash)) {
				return $hash;
			}
		}

		return null;
	}

	/**
	 * Serialize read-modify-write cycles on a site's ledger row across
	 * concurrent requests (MySQL named lock).
	 *
	 * @param string $key
	 * @return bool
	 */
	private static function acquireLock($key)
	{
		global $wpdb;

		return (bool) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', 'lock_' . $key, 5));
	}

	/**
	 * @param string $key
	 */
	private static function releaseLock($key)
	{
		global $wpdb;

		$wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'lock_' . $key));
	}

	/**
	 * @return string Y-m UTC
	 */
	public static function currentPeriod()
	{
		return gmdate('Y-m');
	}

	/**
	 * @return string
	 */
	public static function periodResetAt()
	{
		$next = gmdate('Y-m-01', strtotime('first day of next month UTC'));

		return gmdate('c', strtotime($next . ' 00:00:00 UTC'));
	}

	/**
	 * Apply the monthly rollover in memory, preserving every other key
	 * (token_hash, credits, …). Callers persist under lock when mutating.
	 *
	 * @param mixed $raw
	 * @return array
	 */
	private static function withRollover($raw)
	{
		if (! is_array($raw)) {
			$raw = array();
		}
		$period = self::currentPeriod();
		if (empty($raw['period']) || $raw['period'] !== $period) {
			$raw['period']        = $period;
			$raw['included_used'] = 0;
		}

		return $raw;
	}

	/**
	 * Read-only usage snapshot (no writes — mutations happen in consume/grant).
	 *
	 * @param array $auth
	 * @return array
	 */
	public static function get(array $auth)
	{
		$key     = self::siteKey($auth);
		$raw     = self::withRollover(get_option($key, array()));
		$edition = isset($auth['edition']) && $auth['edition'] === 'pro' ? 'pro' : 'free';

		return self::normalize($raw, $edition);
	}

	/**
	 * Consume 1 query: included first, then purchased credits.
	 * The whole check-and-decrement runs under a per-site lock so concurrent
	 * requests cannot spend the same credit twice.
	 *
	 * @param array $auth
	 * @return array|WP_Error Normalized usage or error when empty.
	 */
	public static function consume(array $auth)
	{
		$key     = self::siteKey($auth);
		$edition = isset($auth['edition']) && $auth['edition'] === 'pro' ? 'pro' : 'free';

		if (! self::acquireLock($key)) {
			return self::busyError();
		}

		wp_cache_delete($key, 'options');
		$raw     = self::withRollover(get_option($key, array()));
		$charged = self::chargeOne($auth, $key, $raw, $edition);
		self::releaseLock($key);

		return $charged;
	}

	/**
	 * Bill one query per merchant turn: the first call carrying a turn_id is
	 * charged, follow-up calls of the same agent loop are free but capped
	 * (TURN_MAX_CALLS) so a reused turn_id cannot buy unlimited LLM calls.
	 *
	 * @param array  $auth
	 * @param string $turnId
	 * @return array{usage:array,charged:bool}|WP_Error
	 */
	public static function consumeForTurn(array $auth, $turnId)
	{
		$key     = self::siteKey($auth);
		$edition = isset($auth['edition']) && $auth['edition'] === 'pro' ? 'pro' : 'free';
		$turnKey = self::turnKey($key, $turnId);

		if (! self::acquireLock($key)) {
			return self::busyError();
		}

		wp_cache_delete($key, 'options');
		$raw  = self::withRollover(get_option($key, array()));
		$turn = get_transient($turnKey);

		if (is_array($turn) && ! empty($turn['charged'])) {
			$calls = isset($turn['calls']) ? (int) $turn['calls'] : 0;
			if ($calls >= self::TURN_MAX_CALLS) {
				self::releaseLock($key);

				return new WP_Error(
					'sln_ai_turn_limit',
					'Too many AI calls for a single message. Please send a new message.',
					array('status' => 429)
				);
			}
			$turn['calls'] = $calls + 1;
			set_transient($turnKey, $turn, self::TURN_TTL);
			self::releaseLock($key);

			return array(
				'usage'   => self::normalize($raw, $edition),
				'charged' => false,
			);
		}

		$usage = self::chargeOne($auth, $key, $raw, $edition);
		if (! is_wp_error($usage)) {
			set_transient(
				$turnKey,
				array(
					'charged' => true,
					'calls'   => 1,
					'at'      => time(),
				),
				self::TURN_TTL
			);
		}
		self::releaseLock($key);

		if (is_wp_error($usage)) {
			return $usage;
		}

		return array(
			'usage'   => $usage,
			'charged' => true,
		);
	}

	/**
	 * Undo the charge of a turn whose charging call failed, so a retry with the
	 * same turn_id is billed again exactly once.
	 *
	 * @param array  $auth
	 * @param string $turnId
	 */
	public static function refundTurn(array $auth, $turnId)
	{
		delete_transient(self::turnKey(self::siteKey($auth), $turnId));
		self::refundOne($auth);
	}

	/**
	 * @param string $turnId
	 * @return bool
	 */
	public static function isValidTurnId($turnId)
	{
		return is_string($turnId) && (bool) preg_match('/^[a-f0-9\-]{36}$/i', $turnId);
	}

	/**
	 * @param string $siteKey
	 * @param string $turnId
	 * @return string
	 */
	private static function turnKey($siteKey, $turnId)
	{
		// Not "sln_": Salon Booking System on the same site bulk-deletes '_transient_sln_%'.
		return 'slb_ai_turn_' . md5($siteKey . '|' . strtolower((string) $turnId));
	}

	/**
	 * @return WP_Error
	 */
	private static function busyError()
	{
		return new WP_Error(
			'sln_ai_ledger_busy',
			'The usage ledger is busy. Please retry.',
			array('status' => 503)
		);
	}

	/**
	 * Decrement one query (included first, then credits). Caller holds the lock.
	 *
	 * @param array  $auth
	 * @param string $key
	 * @param array  $raw
	 * @param string $edition
	 * @return array|WP_Error Normalized usage or quota error.
	 */
	private static function chargeOne(array $auth, $key, array $raw, $edition)
	{
		$limit   = self::includedLimit($edition);
		$used    = isset($raw['included_used']) ? (int) $raw['included_used'] : 0;
		$credits = isset($raw['credits']) ? (int) $raw['credits'] : 0;

		if ($used < $limit) {
			$raw['included_used'] = $used + 1;
		} elseif ($credits > 0) {
			$raw['credits'] = $credits - 1;
		} else {
			return new WP_Error(
				'sln_ai_quota_exceeded',
				'You have used all included AI queries for this month. Buy credits to continue.',
				array(
					'status' => 402,
					'usage'  => self::normalize($raw, $edition),
				)
			);
		}

		if (isset($auth['site_url']) && $auth['site_url']) {
			$raw['site_url'] = (string) $auth['site_url'];
		}
		$raw['edition'] = $edition;
		update_option($key, $raw, false);

		return self::normalize($raw, $edition);
	}

	/**
	 * Give back the query taken by consume() when the downstream LLM call
	 * fails after billing (merchants must not pay for failed turns).
	 *
	 * @param array $auth
	 */
	public static function refundOne(array $auth)
	{
		$key = self::siteKey($auth);

		if (! self::acquireLock($key)) {
			return; // Losing one refund on lock timeout is acceptable.
		}

		wp_cache_delete($key, 'options');
		$raw = self::withRollover(get_option($key, array()));

		$used = isset($raw['included_used']) ? (int) $raw['included_used'] : 0;
		if ($used > 0) {
			$raw['included_used'] = $used - 1;
		} else {
			$raw['credits'] = (isset($raw['credits']) ? (int) $raw['credits'] : 0) + 1;
		}
		update_option($key, $raw, false);
		self::releaseLock($key);
	}

	/**
	 * @param array $auth
	 * @param int   $queries
	 * @param string $source
	 * @return array
	 */
	public static function grantCredits(array $auth, $queries, $source = '')
	{
		$queries = max(0, (int) $queries);
		$key     = self::siteKey($auth);

		// Best effort: proceed even if the lock cannot be obtained — losing a
		// paid grant is worse than a rare concurrent overwrite.
		$locked = self::acquireLock($key);

		wp_cache_delete($key, 'options');
		$raw = self::withRollover(get_option($key, array()));

		$raw['credits'] = (isset($raw['credits']) ? (int) $raw['credits'] : 0) + $queries;
		if (isset($auth['site_url']) && $auth['site_url']) {
			$raw['site_url'] = (string) $auth['site_url'];
		}
		if ($source !== '') {
			$raw['last_grant_source'] = $source;
		}
		update_option($key, $raw, false);
		if ($locked) {
			self::releaseLock($key);
		}

		$edition = isset($auth['edition']) && $auth['edition'] === 'pro' ? 'pro' : 'free';

		return self::normalize($raw, $edition);
	}

	/**
	 * @param array  $raw
	 * @param string $edition
	 * @return array
	 */
	public static function normalize(array $raw, $edition = 'free')
	{
		$limit   = self::includedLimit($edition);
		$used    = isset($raw['included_used']) ? max(0, (int) $raw['included_used']) : 0;
		$inclRem = max(0, $limit - $used);
		$credits = isset($raw['credits']) ? max(0, (int) $raw['credits']) : 0;
		$total   = $inclRem + $credits;

		return array(
			'source'             => 'proxy',
			'edition'            => $edition,
			'period'             => isset($raw['period']) ? (string) $raw['period'] : self::currentPeriod(),
			'included_limit'     => $limit,
			'included_used'      => $used,
			'included_remaining' => $inclRem,
			'credits'            => $credits,
			'total_remaining'    => $total,
			'can_chat'           => $total > 0,
			'reset_at'           => self::periodResetAt(),
			'purchase_available' => true,
		);
	}
}
