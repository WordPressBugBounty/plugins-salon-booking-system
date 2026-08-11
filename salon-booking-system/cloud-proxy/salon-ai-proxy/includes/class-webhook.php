<?php

/**
 * Dodo Payments webhook → grant purchased AI credits.
 *
 * Dashboard URL: https://www.salonbookingsystem.com/wp-json/salon-ai/v1/dodo-webhook
 */
class SLN_AI_Proxy_Webhook
{
	const NS = 'salon-ai/v1';

	public static function register()
	{
		register_rest_route(
			self::NS,
			'/dodo-webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'handle'),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle(WP_REST_Request $request)
	{
		$raw = $request->get_body();
		if ($raw === '') {
			$raw = file_get_contents('php://input');
		}

		$headers = array(
			'webhook-id'        => (string) $request->get_header('webhook-id'),
			'webhook-timestamp' => (string) $request->get_header('webhook-timestamp'),
			'webhook-signature' => (string) $request->get_header('webhook-signature'),
		);

		if (! self::verifySignature($raw, $headers)) {
			return new WP_Error('sln_ai_webhook_sig', 'Invalid webhook signature.', array('status' => 401));
		}

		$payload = json_decode($raw, true);
		if (! is_array($payload)) {
			return new WP_Error('sln_ai_webhook_body', 'Invalid JSON.', array('status' => 400));
		}

		$type = isset($payload['type']) ? (string) $payload['type'] : '';
		$data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

		// Grant only from payment.succeeded: Dodo retries it until we answer 2xx,
		// so it is reliable on its own. Fulfilling credit.* events too would risk
		// double grants whenever the two event types carry different ids/metadata.
		if ($type === 'payment.succeeded') {
			$result = self::fulfillPayment($data);
			if (is_wp_error($result)) {
				// Non-2xx so Dodo retries the delivery (idempotency makes retries safe).
				return $result;
			}
		} elseif (in_array($type, array('credit.granted', 'credits.granted'), true)) {
			error_log('[salon-ai-proxy] credit event received (not fulfilled, payment.succeeded is authoritative): ' . wp_json_encode(isset($data['metadata']) ? $data['metadata'] : array()));
		}

		return rest_ensure_response(array('received' => true));
	}

	/**
	 * @param array $data
	 * @return true|WP_Error True when handled (or safely skipped); WP_Error when
	 *                       the delivery must be retried by Dodo.
	 */
	private static function fulfillPayment(array $data)
	{
		$meta = array();
		if (! empty($data['metadata']) && is_array($data['metadata'])) {
			$meta = $data['metadata'];
		}

		// One purchase → one grant, across original deliveries and retries.
		// Prefer the purchase_ref we set on the checkout metadata; fall back to
		// the Dodo payment id. All candidate keys are checked and marked, and
		// legacy (pre-prefix) records are honoured for compatibility.
		$candidateKeys = array();
		if (! empty($meta['purchase_ref'])) {
			$candidateKeys[] = 'ref:' . (string) $meta['purchase_ref'];
		}
		if (! empty($data['payment_id'])) {
			$paymentId       = (string) $data['payment_id'];
			$candidateKeys[] = 'pay:' . $paymentId;
			$candidateKeys[] = $paymentId; // legacy unprefixed record
		} elseif (! empty($data['id'])) {
			$paymentId       = (string) $data['id'];
			$candidateKeys[] = 'pay:' . $paymentId;
			$candidateKeys[] = $paymentId; // legacy unprefixed record
		}
		$fulfillKey = $candidateKeys ? $candidateKeys[0] : '';

		if ($fulfillKey === '') {
			// Without a stable key we cannot guarantee idempotency: granting here
			// would double-credit on every webhook retry. Log for manual handling.
			error_log('[salon-ai-proxy] webhook event without purchase_ref/payment_id, skipped: ' . wp_json_encode($meta));

			return true;
		}

		$auth = array(
			'site_url'    => isset($meta['site_url']) ? (string) $meta['site_url'] : '',
			'license_key' => isset($meta['license_key']) ? (string) $meta['license_key'] : '',
			'edition'     => isset($meta['edition']) ? (string) $meta['edition'] : 'free',
		);

		$queries = 0;
		if (! empty($meta['queries'])) {
			$queries = (int) $meta['queries'];
		} elseif (! empty($meta['pack_id'])) {
			$packs = SLN_AI_Proxy_Ledger::packs();
			$pid   = sanitize_key((string) $meta['pack_id']);
			if (! empty($packs[ $pid ])) {
				$queries = (int) $packs[ $pid ]['queries'];
			}
		}

		if ($queries < 1 || ($auth['site_url'] === '' && $auth['license_key'] === '')) {
			error_log('[salon-ai-proxy] payment.succeeded missing site/queries meta: ' . wp_json_encode($meta));

			return true;
		}

		// Serialize concurrent deliveries of the same purchase (Dodo retries,
		// payment + credit events racing) around the fulfilled check. Without
		// the lock the check-grant-mark sequence is not safe, so bail out and
		// let Dodo redeliver instead of risking a double grant.
		global $wpdb;
		$lockName = 'lock_sln_ai_fulfill_' . md5($fulfillKey);
		$locked   = (bool) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lockName, 10));
		if (! $locked) {
			return new WP_Error(
				'sln_ai_fulfill_busy',
				'Fulfillment lock unavailable, retry the delivery.',
				array('status' => 503)
			);
		}

		wp_cache_delete('sln_ai_proxy_fulfilled', 'options');
		foreach ($candidateKeys as $candidate) {
			if (self::alreadyFulfilled($candidate)) {
				$wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));

				return true;
			}
		}

		SLN_AI_Proxy_Ledger::grantCredits($auth, $queries, $fulfillKey);
		foreach ($candidateKeys as $candidate) {
			self::markFulfilled($candidate);
		}

		$wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));

		return true;
	}

	/**
	 * Standard Webhooks-style verification (Dodo).
	 *
	 * @param string $raw
	 * @param array  $headers
	 * @return bool
	 */
	private static function verifySignature($raw, array $headers)
	{
		$secret = defined('SLN_AI_DODO_WEBHOOK_SECRET') ? (string) SLN_AI_DODO_WEBHOOK_SECRET : '';
		if ($secret === '' || $secret === 'REPLACE_ME') {
			// Allow only when explicitly enabled for local smoke tests.
			return (bool) apply_filters('sln_ai_proxy_skip_webhook_verify', false);
		}

		$id        = $headers['webhook-id'];
		$timestamp = $headers['webhook-timestamp'];
		$signature = $headers['webhook-signature'];
		if ($id === '' || $timestamp === '' || $signature === '') {
			return false;
		}

		// Reject stale timestamps (>5 minutes).
		if (abs(time() - (int) $timestamp) > 300) {
			return false;
		}

		$signed = $id . '.' . $timestamp . '.' . $raw;
		$key    = $secret;
		if (strpos($secret, 'whsec_') === 0) {
			$decoded = base64_decode(substr($secret, 6), true);
			if ($decoded !== false) {
				$key = $decoded;
			}
		}

		$digest = base64_encode(hash_hmac('sha256', $signed, $key, true));

		// Header may be "v1,<sig>" or multiple space-separated versioned signatures.
		$parts = preg_split('/\s+/', $signature);
		foreach ($parts as $part) {
			$part = trim($part);
			if (strpos($part, ',') !== false) {
				$bits = explode(',', $part, 2);
				$part = isset($bits[1]) ? $bits[1] : $bits[0];
			}
			if (hash_equals($digest, $part)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $paymentId
	 * @return bool
	 */
	private static function alreadyFulfilled($paymentId)
	{
		$done = get_option('sln_ai_proxy_fulfilled', array());
		return is_array($done) && ! empty($done[ $paymentId ]);
	}

	/**
	 * @param string $paymentId
	 */
	private static function markFulfilled($paymentId)
	{
		$done = get_option('sln_ai_proxy_fulfilled', array());
		if (! is_array($done)) {
			$done = array();
		}
		$done[ $paymentId ] = time();
		// Keep last 500 ids.
		if (count($done) > 500) {
			asort($done);
			$done = array_slice($done, -500, null, true);
		}
		update_option('sln_ai_proxy_fulfilled', $done, false);
	}
}
