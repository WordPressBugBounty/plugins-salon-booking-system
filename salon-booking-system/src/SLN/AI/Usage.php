<?php

/**
 * AI query quotas: Free/Pro included allowance + purchased credit packs (Dodo via cloud proxy).
 *
 * Source of truth for purchased credits is the Salon Booking cloud proxy.
 * When the proxy is unreachable, a local monthly counter enforces the included allowance only.
 */
class SLN_AI_Usage
{
	const OPTION_LOCAL = 'sln_ai_query_usage';
	const FREE_INCLUDED = 10;
	const PRO_INCLUDED  = 100;

	/**
	 * Option A credit packs (Dodo one-time products on the proxy).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function packs()
	{
		$packs = array(
			'small'  => array(
				'id'         => 'small',
				'queries'    => 50,
				'price_usd'  => 9,
				'label'      => __('50 queries', 'salon-booking-system'),
				'price_label'=> __('$9', 'salon-booking-system'),
			),
			'medium' => array(
				'id'         => 'medium',
				'queries'    => 200,
				'price_usd'  => 29,
				'label'      => __('200 queries', 'salon-booking-system'),
				'price_label'=> __('$29', 'salon-booking-system'),
			),
			'large'  => array(
				'id'         => 'large',
				'queries'    => 500,
				'price_usd'  => 59,
				'label'      => __('500 queries', 'salon-booking-system'),
				'price_label'=> __('$59', 'salon-booking-system'),
			),
		);

		return apply_filters('sln_ai_credit_packs', $packs);
	}

	/**
	 * @return int
	 */
	public static function includedLimit()
	{
		$limit = SLN_AI_Edition::isPro() ? self::PRO_INCLUDED : self::FREE_INCLUDED;

		return (int) apply_filters('sln_ai_included_query_limit', $limit, SLN_AI_Edition::key());
	}

	/**
	 * Staging / support escape hatch.
	 *
	 * @return bool
	 */
	public static function bypass()
	{
		$default = defined('SLN_AI_QUOTA_BYPASS') && SLN_AI_QUOTA_BYPASS;

		return (bool) apply_filters('sln_ai_quota_bypass', $default);
	}

	/**
	 * Current UTC calendar month key (YYYY-MM).
	 *
	 * @return string
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
		// First moment of next UTC month.
		$next = gmdate('Y-m-01', strtotime('first day of next month UTC'));

		return gmdate('c', strtotime($next . ' 00:00:00 UTC'));
	}

	/**
	 * Per-site secret sent with every proxy request. The proxy pins it on first
	 * contact and rejects later requests with a different token, so third parties
	 * cannot spend a site's credits by knowing its public URL.
	 *
	 * @return string
	 */
	public static function siteToken()
	{
		$token = get_option('sln_ai_site_token', '');
		if (is_string($token) && strlen($token) >= 32) {
			return $token;
		}

		// add_option() is atomic on the option_name unique key: when two
		// requests race to create the token, only one INSERT wins and both
		// requests end up using the same stored value.
		$candidate = wp_generate_password(64, false, false);
		if (! add_option('sln_ai_site_token', $candidate, '', false)) {
			wp_cache_delete('sln_ai_site_token', 'options');
			$stored = get_option('sln_ai_site_token', '');
			if (is_string($stored) && strlen($stored) >= 32) {
				return $stored;
			}
			// Present but invalid (legacy/corrupt): overwrite, then return the
			// persisted value so concurrent callers converge on one token.
			update_option('sln_ai_site_token', $candidate, false);
			wp_cache_delete('sln_ai_site_token', 'options');
			$stored = get_option('sln_ai_site_token', '');
			if (is_string($stored) && strlen($stored) >= 32) {
				return $stored;
			}
		}

		return $candidate;
	}

	/**
	 * @return array Auth fragment for proxy requests.
	 */
	public static function authPayload()
	{
		$license = '';
		if (defined('SLN_ITEM_SLUG')) {
			$license = (string) get_option(SLN_ITEM_SLUG . '_license_key', '');
		}

		return array(
			'site_url'    => home_url(),
			'site_token'  => self::siteToken(),
			'license_key' => $license,
			'api_key'     => defined('SLN_API_KEY') ? SLN_API_KEY : '',
			'api_token'   => defined('SLN_API_TOKEN') ? SLN_API_TOKEN : '',
			'plugin'      => defined('SLN_ITEM_SLUG') ? SLN_ITEM_SLUG : 'salon-booking-wordpress-plugin',
			'version'     => defined('SLN_VERSION') ? SLN_VERSION : '',
			// Hint only: the proxy resolves the real edition from the license key.
			'edition'     => SLN_AI_Edition::key(),
		);
	}

	/**
	 * Fast local-only snapshot (no remote call). Safe for admin asset bootstrap.
	 *
	 * @return array
	 */
	public function getBootstrapUsage()
	{
		if (self::bypass()) {
			return $this->normalize(
				array(
					'source'             => 'bypass',
					'edition'            => SLN_AI_Edition::key(),
					'period'             => self::currentPeriod(),
					'included_limit'     => self::includedLimit(),
					'included_used'      => 0,
					'included_remaining' => self::includedLimit(),
					'credits'            => 0,
					'total_remaining'    => 999999,
					'can_chat'           => true,
					'reset_at'           => self::periodResetAt(),
					'purchase_available' => true,
				)
			);
		}

		return $this->normalize($this->localUsageSnapshot());
	}

	/**
	 * Normalized usage snapshot for REST/UI (prefers cloud proxy).
	 *
	 * @return array
	 */
	public function getUsage()
	{
		if (self::bypass()) {
			return $this->getBootstrapUsage();
		}

		$proxy  = new SLN_AI_ProxyClient();
		$remote = $proxy->fetchUsage();
		if (! is_wp_error($remote) && is_array($remote)) {
			$remote['source'] = 'proxy';

			return $this->normalize($remote);
		}

		return $this->getBootstrapUsage();
	}

	/**
	 * Block chat when no queries remain. Returns WP_Error or null when allowed.
	 *
	 * @return WP_Error|null
	 */
	public function assertCanChat()
	{
		$usage = $this->getUsage();
		if (! empty($usage['can_chat'])) {
			return null;
		}

		return $this->quotaExceededError($usage);
	}

	/**
	 * Consume one query after a successful billable chat turn.
	 * Skip only when the proxy already returned a usage snapshot (it deducted server-side).
	 *
	 * @param string $backend llm|proxy|mock
	 * @param bool   $proxyReportedUsage
	 */
	public function consumeAfterChat($backend, $proxyReportedUsage = false)
	{
		if (self::bypass()) {
			return;
		}

		if ($backend === 'proxy' && $proxyReportedUsage) {
			return;
		}

		// Direct LLM, mock, or proxy without billing yet — enforce included allowance locally.
		$this->consumeLocal();
	}

	/**
	 * @param string $packId
	 * @param string $returnUrl
	 * @return array{checkout_url:string}|WP_Error
	 */
	public function createCheckout($packId, $returnUrl = '')
	{
		$packId = sanitize_key((string) $packId);
		$packs  = self::packs();
		if ($packId === '' || empty($packs[ $packId ])) {
			return new WP_Error(
				'sln_ai_invalid_pack',
				__('Unknown credit pack.', 'salon-booking-system'),
				array('status' => 400)
			);
		}

		if ($returnUrl === '') {
			$returnUrl = admin_url('admin.php?page=salon-ai-setup&ai_credits=1');
		}

		$proxy  = new SLN_AI_ProxyClient();
		$result = $proxy->createCheckout($packId, $returnUrl);
		if (is_wp_error($result)) {
			return $result;
		}

		if (empty($result['checkout_url'])) {
			return new WP_Error(
				'sln_ai_checkout_unavailable',
				__(
					'Credit purchase is not available yet. Please try again later or contact support.',
					'salon-booking-system'
				),
				array('status' => 503)
			);
		}

		return array(
			'checkout_url' => esc_url_raw((string) $result['checkout_url']),
			'pack'         => $packs[ $packId ],
		);
	}

	/**
	 * @param array $usage
	 * @return WP_Error
	 */
	public function quotaExceededError(array $usage)
	{
		$usage = $this->normalize($usage);

		// Single source of truth: the (proxy-resolved when available) usage
		// snapshot edition, so the API message, upgrade payload, and the JS
		// paywall (which keys off usage.edition) never contradict each other.
		$isFree = isset($usage['edition']) ? ($usage['edition'] !== 'pro') : ! SLN_AI_Edition::isPro();

		if ($isFree) {
			$msg = sprintf(
				/* translators: 1: free monthly query allowance, 2: PRO monthly query allowance */
				__(
					'You have used all %1$d free AI queries for this month. Upgrade to PRO to get %2$d AI queries every month, or buy credits to continue now.',
					'salon-booking-system'
				),
				self::FREE_INCLUDED,
				self::PRO_INCLUDED
			);
		} else {
			$msg = __(
				'You have used all included AI queries for this month. Buy credits to continue.',
				'salon-booking-system'
			);
		}

		$data = array(
			'status' => 402,
			'usage'  => $usage,
			'packs'  => array_values(self::packs()),
		);

		if ($isFree) {
			$data['upgrade'] = array(
				'url'          => SLN_AI_Edition::pricingUrl(),
				'pro_included' => self::PRO_INCLUDED,
			);
		}

		return new WP_Error('sln_ai_quota_exceeded', $msg, $data);
	}

	/**
	 * @param array $raw
	 * @return array
	 */
	public function normalize(array $raw)
	{
		$limit   = isset($raw['included_limit']) ? max(0, (int) $raw['included_limit']) : self::includedLimit();
		$used    = isset($raw['included_used']) ? max(0, (int) $raw['included_used']) : 0;
		$inclRem = array_key_exists('included_remaining', $raw)
			? max(0, (int) $raw['included_remaining'])
			: max(0, $limit - $used);
		$credits = isset($raw['credits']) ? max(0, (int) $raw['credits']) : 0;
		$total   = array_key_exists('total_remaining', $raw)
			? max(0, (int) $raw['total_remaining'])
			: ($inclRem + $credits);
		$canChat = array_key_exists('can_chat', $raw) ? (bool) $raw['can_chat'] : ($total > 0);
		$source  = isset($raw['source']) ? (string) $raw['source'] : 'local';
		// Merchants always see buy packs when exhausted; cloud checkout fulfills payment.
		$purchase = array_key_exists('purchase_available', $raw)
			? (bool) $raw['purchase_available']
			: true;

		return array(
			'source'             => $source,
			'edition'            => isset($raw['edition']) ? (string) $raw['edition'] : SLN_AI_Edition::key(),
			'period'             => isset($raw['period']) ? (string) $raw['period'] : self::currentPeriod(),
			'included_limit'     => $limit,
			'included_used'      => $used,
			'included_remaining' => $inclRem,
			'credits'            => $credits,
			'total_remaining'    => $total,
			'can_chat'           => $canChat || self::bypass(),
			'reset_at'           => isset($raw['reset_at']) ? (string) $raw['reset_at'] : self::periodResetAt(),
			'purchase_available' => $purchase,
			'packs'              => array_values(self::packs()),
		);
	}

	/**
	 * @return array
	 */
	private function localUsageSnapshot()
	{
		$state = $this->getLocalState();
		$limit = self::includedLimit();
		$used  = (int) $state['used'];
		$rem   = max(0, $limit - $used);

		return array(
			'source'             => 'local',
			'edition'            => SLN_AI_Edition::key(),
			'period'             => $state['period'],
			'included_limit'     => $limit,
			'included_used'      => $used,
			'included_remaining' => $rem,
			'credits'            => 0,
			'total_remaining'    => $rem,
			'can_chat'           => $rem > 0,
			'reset_at'           => self::periodResetAt(),
			// Always offer purchase UX when exhausted; checkout is fulfilled by the Salon cloud.
			'purchase_available' => true,
		);
	}

	/**
	 * @return array{period:string,used:int}
	 */
	private function getLocalState()
	{
		$period = self::currentPeriod();
		$raw    = get_option(self::OPTION_LOCAL, array());
		if (! is_array($raw) || empty($raw['period']) || $raw['period'] !== $period) {
			$raw = array(
				'period' => $period,
				'used'   => 0,
			);
			update_option(self::OPTION_LOCAL, $raw, false);
		}

		return array(
			'period' => (string) $raw['period'],
			'used'   => max(0, (int) $raw['used']),
		);
	}

	private function consumeLocal()
	{
		$state         = $this->getLocalState();
		$state['used'] = (int) $state['used'] + 1;
		update_option(self::OPTION_LOCAL, $state, false);
	}
}
