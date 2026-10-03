<?php
// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.NoCaching
namespace SLB_API\Helper;

class TokenHelper {

	const TOKEN_SCHEME = 'hmac-sha256-v1';

	const TOKEN_TTL = 2592000; // 30 days

	private $userApiTokenMetaKey = '_sln_customer_api_token';

	private $userApiTokenExpiresMetaKey = '_sln_customer_api_token_expires';

	/**
	 * Issue a new bearer token. Only a digest is stored, so an existing token
	 * cannot be read back: each call rotates the credential.
	 *
	 * @param int $userId
	 * @return string Plaintext token, returned once to the client.
	 */
	public function getUserAccessToken($userId) {
		return $this->createUserAccessToken($userId);
	}

	public function isValidUserAccessToken($accessToken) {
		return !empty($this->getUserIdByAccessToken($accessToken));
	}

	public function deleteUserAccessToken($accessToken) {
		$userId = $this->getUserIdByAccessToken($accessToken);

		if (empty($userId)) {
			return;
		}

		delete_user_meta($userId, $this->userApiTokenMetaKey);
		delete_user_meta($userId, $this->userApiTokenExpiresMetaKey);
	}

	public function createUserAccessToken($userId) {
		$accessToken = bin2hex(random_bytes(32));

		$this->saveUserAccessToken($userId, $accessToken);

		return $accessToken;
	}

	public function getUserIdByAccessToken($accessToken) {
		$accessToken = $this->normalizeToken($accessToken);
		if ($accessToken === '') {
			return null;
		}

		global $wpdb;

		$userId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key=%s AND meta_value=%s",
				$this->userApiTokenMetaKey,
				$this->hashToken($accessToken)
			)
		);

		if (empty($userId)) {
			return null;
		}

		$expires = (int) get_user_meta($userId, $this->userApiTokenExpiresMetaKey, true);
		if ($expires <= time()) {
			delete_user_meta($userId, $this->userApiTokenMetaKey);
			delete_user_meta($userId, $this->userApiTokenExpiresMetaKey);
			return null;
		}

		return $userId;
	}

	/**
	 * Drop tokens issued by the predictable SHA1 scheme. Runs once per site.
	 */
	public static function revokePredictableTokens() {
		if (get_site_option('sln_api_token_scheme') === self::TOKEN_SCHEME) {
			return;
		}

		delete_metadata('user', 0, '_sln_customer_api_token', '', true);
		delete_metadata('user', 0, '_sln_customer_api_token_expires', '', true);
		update_site_option('sln_api_token_scheme', self::TOKEN_SCHEME);
	}

	private function saveUserAccessToken($userId, $accessToken) {
		update_user_meta($userId, $this->userApiTokenMetaKey, $this->hashToken($accessToken));
		update_user_meta($userId, $this->userApiTokenExpiresMetaKey, time() + self::TOKEN_TTL);
	}

	private function hashToken($accessToken) {
		return hash_hmac('sha256', $accessToken, wp_salt('auth'));
	}

	private function normalizeToken($accessToken) {
		if (!is_string($accessToken)) {
			return '';
		}

		$accessToken = strtolower(trim($accessToken));
		if (!preg_match('/\A[a-f0-9]{64}\z/', $accessToken)) {
			return '';
		}

		return $accessToken;
	}
}
