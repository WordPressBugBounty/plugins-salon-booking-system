<?php

/**
 * REST: usage / checkout / consume (namespace salon-ai/v1).
 */
class SLN_AI_Proxy_REST
{
	const NS = 'salon-ai/v1';

	public static function register()
	{
		register_rest_route(
			self::NS,
			'/usage',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'usage'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'checkout'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/consume',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'consume'),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function usage(WP_REST_Request $request)
	{
		$auth = self::authFromRequest($request);
		if (is_wp_error($auth)) {
			return $auth;
		}

		$usage = SLN_AI_Proxy_Ledger::get($auth);
		$usage['packs'] = self::packsPublic();

		return rest_ensure_response($usage);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function checkout(WP_REST_Request $request)
	{
		$auth = self::authFromRequest($request);
		if (is_wp_error($auth)) {
			return $auth;
		}

		$packId    = sanitize_key((string) $request->get_param('pack_id'));
		$returnUrl = esc_url_raw((string) $request->get_param('return_url'));
		if ($returnUrl === '') {
			$returnUrl = isset($auth['site_url'])
				? trailingslashit((string) $auth['site_url']) . 'wp-admin/admin.php?page=salon-ai-setup&ai_credits=1'
				: home_url('/');
		}

		$result = SLN_AI_Proxy_Dodo::createCheckout($packId, $returnUrl, $auth);
		if (is_wp_error($result)) {
			return $result;
		}

		return rest_ensure_response($result);
	}

	/**
	 * Deduct one query — call from the LLM /chat handler before invoking the model.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function consume(WP_REST_Request $request)
	{
		$auth = self::authFromRequest($request);
		if (is_wp_error($auth)) {
			return $auth;
		}

		$result = SLN_AI_Proxy_Ledger::consume($auth);
		if (is_wp_error($result)) {
			$data = $result->get_error_data();
			if (! is_array($data)) {
				$data = array('status' => 402);
			}
			$data['packs'] = self::packsPublic();
			$result->add_data($data);

			return $result;
		}

		$result['packs'] = self::packsPublic();

		return rest_ensure_response(
			array(
				'ok'    => true,
				'usage' => $result,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	private static function authFromRequest(WP_REST_Request $request)
	{
		$auth = $request->get_param('auth');
		if (! is_array($auth)) {
			$json = $request->get_json_params();
			if (is_array($json) && isset($json['auth']) && is_array($json['auth'])) {
				$auth = $json['auth'];
			}
		}
		if (! is_array($auth)) {
			$auth = array();
		}

		return self::verifyAuth($auth);
	}

	/**
	 * Validate a raw auth fragment: pin the per-site token and resolve the real
	 * edition server-side. Shared with the store /chat handler — never call
	 * SLN_AI_Proxy_Ledger with a client-supplied auth array that did not pass here.
	 *
	 * @param array $auth
	 * @return array|WP_Error
	 */
	public static function verifyAuth(array $auth)
	{
		$site    = isset($auth['site_url']) ? esc_url_raw((string) $auth['site_url']) : '';
		$license = isset($auth['license_key']) ? sanitize_text_field((string) $auth['license_key']) : '';
		if ($site === '' && $license === '') {
			return new WP_Error(
				'sln_ai_auth',
				'Missing site auth (site_url or license_key).',
				array('status' => 401)
			);
		}

		$token = isset($auth['site_token']) ? (string) $auth['site_token'] : '';
		if (strlen($token) < 32) {
			return new WP_Error(
				'sln_ai_auth_token',
				'Missing or invalid site token. Update Salon Booking System to the latest version.',
				array('status' => 401)
			);
		}

		$verified = array(
			'site_url'    => $site,
			'license_key' => $license,
			// Never trust the client-declared edition: resolve it from the license.
			'edition'     => self::resolveEdition($license, $site),
			'version'     => isset($auth['version']) ? sanitize_text_field((string) $auth['version']) : '',
			'email'       => isset($auth['email']) ? sanitize_email((string) $auth['email']) : '',
		);

		$tokenState = SLN_AI_Proxy_Ledger::verifyOrRegisterToken($verified, $token);
		if ('busy' === $tokenState) {
			return new WP_Error(
				'sln_ai_auth_busy',
				'The Salon AI service is busy. Please retry in a moment.',
				array('status' => 503)
			);
		}
		if ('ok' !== $tokenState) {
			return new WP_Error(
				'sln_ai_auth_token',
				'Site token mismatch. If you migrated or reset this site, contact Salon Booking support.',
				array('status' => 403)
			);
		}

		return $verified;
	}

	/**
	 * Resolve free|pro from the EDD Software Licensing record on this store site.
	 * Falls back to free when the license is missing, unknown, expired or disabled.
	 *
	 * @param string $license
	 * @param string $site
	 * @return string free|pro
	 */
	private static function resolveEdition($license, $site)
	{
		$edition = 'free';

		if ($license !== '' && function_exists('edd_software_licensing')) {
			$record = edd_software_licensing()->get_license($license);
			if ($record && ! is_wp_error($record) && is_object($record)) {
				$status  = isset($record->status) ? (string) $record->status : '';
				$expired = method_exists($record, 'is_expired') ? (bool) $record->is_expired() : ($status === 'expired');
				if (! $expired && in_array($status, array('active', 'inactive'), true)) {
					$edition = 'pro';
				}
			}
		}

		/**
		 * Last-resort override for support cases (e.g. licenses managed outside EDD).
		 * Never derive the result from client input.
		 */
		return apply_filters('sln_ai_proxy_resolve_edition', $edition, $license, $site);
	}

	/**
	 * @return array
	 */
	private static function packsPublic()
	{
		$out = array();
		foreach (SLN_AI_Proxy_Ledger::packs() as $id => $pack) {
			$out[] = array(
				'id'          => $id,
				'queries'     => $pack['queries'],
				'price_usd'   => (int) round($pack['price_cents'] / 100),
				'label'       => $pack['label'],
				'price_label' => '€' . number_format($pack['price_cents'] / 100, 0),
			);
		}

		return $out;
	}
}
