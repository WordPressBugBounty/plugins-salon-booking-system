<?php

/**
 * Minimal Dodo Payments client (checkout sessions).
 */
class SLN_AI_Proxy_Dodo
{
	/**
	 * @return string
	 */
	public static function apiBase()
	{
		$env = defined('SLN_AI_DODO_ENV') ? (string) SLN_AI_DODO_ENV : 'test_mode';
		if ($env === 'live_mode') {
			return 'https://live.dodopayments.com';
		}

		return 'https://test.dodopayments.com';
	}

	/**
	 * @return string
	 */
	public static function apiKey()
	{
		return defined('SLN_AI_DODO_API_KEY') ? (string) SLN_AI_DODO_API_KEY : '';
	}

	/**
	 * @param string $packId
	 * @param string $returnUrl
	 * @param array  $auth
	 * @return array{checkout_url:string,session_id?:string}|WP_Error
	 */
	public static function createCheckout($packId, $returnUrl, array $auth)
	{
		$packs = SLN_AI_Proxy_Ledger::packs();
		if (empty($packs[ $packId ])) {
			return new WP_Error('sln_ai_invalid_pack', 'Unknown credit pack.', array('status' => 400));
		}
		$pack = $packs[ $packId ];

		$productId = defined('SLN_AI_DODO_PRODUCT_ID') ? (string) SLN_AI_DODO_PRODUCT_ID : '';
		$creditId  = defined('SLN_AI_DODO_CREDIT_ID') ? (string) SLN_AI_DODO_CREDIT_ID : '';
		$key       = self::apiKey();

		if ($productId === '' || $creditId === '' || $key === '' || $key === 'REPLACE_ME') {
			return new WP_Error(
				'sln_ai_checkout_unavailable',
				'Credit purchase is not configured on the Salon AI cloud yet.',
				array('status' => 503)
			);
		}

		$item = array(
			'product_id'          => $productId,
			'quantity'            => 1,
			'credit_entitlements' => array(
				array(
					'credit_entitlement_id' => $creditId,
					'credits_amount'        => (string) (int) $pack['queries'],
				),
			),
		);

		$useAmount = ! defined('SLN_AI_DODO_USE_AMOUNT_OVERRIDE') || SLN_AI_DODO_USE_AMOUNT_OVERRIDE;
		if ($useAmount) {
			// Requires Pay-what-you-want (or equivalent) on the one-time product.
			$item['amount'] = (int) $pack['price_cents'];
		}

		$body = array(
			'product_cart' => array($item),
			'return_url'   => $returnUrl,
			'metadata'     => array(
				'site_url'    => isset($auth['site_url']) ? (string) $auth['site_url'] : '',
				'license_key' => isset($auth['license_key']) ? (string) $auth['license_key'] : '',
				'edition'     => isset($auth['edition']) ? (string) $auth['edition'] : 'free',
				'pack_id'     => $packId,
				'queries'     => (string) (int) $pack['queries'],
				'source'      => 'salon_ai_proxy',
				// Single fulfillment key: every webhook event for this purchase
				// (payment.succeeded, credit.granted, retries) carries the same ref.
				'purchase_ref'=> 'slnai_' . wp_generate_uuid4(),
			),
		);

		if (! empty($auth['email'])) {
			$body['customer'] = array('email' => sanitize_email((string) $auth['email']));
		}

		$response = wp_remote_post(
			self::apiBase() . '/checkouts',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode($body),
			)
		);

		if (is_wp_error($response)) {
			return new WP_Error(
				'sln_ai_checkout_unavailable',
				$response->get_error_message(),
				array('status' => 503)
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true);
		if ($code < 200 || $code >= 300 || ! is_array($data)) {
			$msg = is_array($data) && ! empty($data['message'])
				? (string) $data['message']
				: 'Dodo checkout failed.';

			return new WP_Error('sln_ai_checkout_unavailable', $msg, array('status' => $code ? $code : 503, 'body' => $data));
		}

		$url = '';
		if (! empty($data['checkout_url'])) {
			$url = (string) $data['checkout_url'];
		} elseif (! empty($data['url'])) {
			$url = (string) $data['url'];
		}

		if ($url === '') {
			return new WP_Error(
				'sln_ai_checkout_unavailable',
				'Dodo checkout did not return a URL.',
				array('status' => 503, 'body' => $data)
			);
		}

		return array(
			'checkout_url' => esc_url_raw($url),
			'session_id'   => isset($data['session_id']) ? (string) $data['session_id'] : (isset($data['id']) ? (string) $data['id'] : ''),
			'pack'         => $pack,
		);
	}
}
