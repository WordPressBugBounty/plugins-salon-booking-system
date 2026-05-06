<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Helper_FacebookLogin
{
    const API_URL	= 'https://graph.facebook.com/v2.8';
    const FACEBOOK_URL	= 'https://www.facebook.com/v2.8';

    public static function getAccessTokenByCode($code) {

	$accessTokenUri = static::API_URL . '/oauth/access_token';

	$args = array(
	    'method'	    => 'POST',
	    'body'	    => array(
		'client_id'	=> static::getClientID(),
		'redirect_uri'  => static::getRedirectUri(),
		'client_secret' => static::getClientSecret(),
		'code'	        => $code,
	    ),
	    'timeout'	    => '5',
	    'redirection'   => '5',
	    'httpversion'   => '1.0',
	    'blocking'	    => true,
	    'headers'	    => array(),
	);

	$result = wp_remote_post($accessTokenUri, $args);

	if(is_wp_error($result)) {
	    throw new Exception($result['body']);
	}

	$accessTokenJsonOutput = json_decode($result['body'], true);

	if (isset($accessTokenJsonOutput['error'])) {
	    throw new Exception($accessTokenJsonOutput['error']['message']);
	}

	return $accessTokenJsonOutput['access_token'];
    }

    public static function getClientID() {
	return SLN_Plugin::getInstance()->getSettings()->get('fb_app_id');
    }

    public static function getClientSecret() {
	return SLN_Plugin::getInstance()->getSettings()->get('fb_app_secret');
    }

    public static function getRedirectUri() {

	$bookingPage = SLN_Plugin::getInstance()->getSettings()->getPayPageId();

	if (!$bookingPage) {
	    return '';
	}

	return add_query_arg(array(
	    'sln_step_page'  => 'details',
	    'submit_details' => 'next',
	    'sln_action'     => 'fb_login',
	), get_permalink($bookingPage));
    }

    public static function getFacebookLoginUrl($state = '') {
	return add_query_arg(
	    array(
		'client_id'	=> static::getClientID(),
		'redirect_uri'  => urlencode(static::getRedirectUri()),
		'response_type' => 'code',
		'scope'		=> 'email',
		'state'		=> $state,
	    ),
	    static::FACEBOOK_URL . '/dialog/oauth'
	);
    }

    public static function getUserIDByAccessToken($accessToken, $createCustomerFields = false) {

	$fbUser = static::getFBUserInfo($accessToken);

	$userID = SLN_Wrapper_Customer::getCustomerIdByFacebookID($fbUser['id']);

	if ( $userID ) {
	    return $userID;
	}

	$user = get_user_by('email', $fbUser['email']);

	if ( $user ) {
	    update_user_meta($user->ID, '_sln_fb_id', $fbUser['id']);
	    return $user->ID;
	}

	return static::createWpUser($fbUser, $createCustomerFields);
    }

    /**
     * Ensures the user access token was issued for this site's Facebook app (see Graph debug_token).
     * Without this check, any valid token from any Facebook app can be used to impersonate a WP user by email.
     *
     * @param string $accessToken User access token from the client.
     * @throws Exception When the token is missing, Facebook is not configured, or the token is not for this app.
     */
    protected static function assertUserAccessTokenForConfiguredApp($accessToken) {
	$app_id     = static::getClientID();
	$app_secret = static::getClientSecret();
	if ( ! $app_id || ! $app_secret ) {
	    throw new \Exception(esc_html__('Facebook login is not configured.', 'salon-booking-system'));
	}

	$app_access_token = $app_id . '|' . $app_secret;
	$debug_url        = add_query_arg(
	    array(
		'input_token'  => $accessToken,
		'access_token' => $app_access_token,
	    ),
	    static::API_URL . '/debug_token'
	);

	$response = wp_remote_get(
	    $debug_url,
	    array(
		'timeout'   => 5,
		'httpversion' => '1.0',
	    )
	);

	if ( is_wp_error( $response ) ) {
	    throw new \Exception( esc_html__( 'Unable to verify Facebook access token.', 'salon-booking-system' ) );
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== (int) $status ) {
	    throw new \Exception( esc_html__( 'Unable to verify Facebook access token.', 'salon-booking-system' ) );
	}

	$payload = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $payload ) ) {
	    throw new \Exception( esc_html__( 'Invalid Facebook access token.', 'salon-booking-system' ) );
	}
	if ( ! empty( $payload['error'] ) ) {
	    throw new \Exception( esc_html__( 'Invalid Facebook access token.', 'salon-booking-system' ) );
	}

	$data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
	if ( empty( $data['is_valid'] ) || (string) $data['app_id'] !== (string) $app_id ) {
	    throw new \Exception( esc_html__( 'Invalid Facebook access token.', 'salon-booking-system' ) );
	}

	// Require a user token (not an app/page token mistaken for login).
	if ( empty( $data['user_id'] ) ) {
	    throw new \Exception( esc_html__( 'Invalid Facebook access token.', 'salon-booking-system' ) );
	}
    }

    protected static function getFBUserInfo($accessToken) {

	if ( ! $accessToken ) {
	    throw new \Exception(esc_html__('Access token not found', 'salon-booking-system'));
	}

	static::assertUserAccessTokenForConfiguredApp( $accessToken );

	$response = wp_remote_get(
	    add_query_arg(
		array(
		    'fields'	    => 'id,name,email',
		    'access_token'  => $accessToken,
		),
		static::API_URL . '/me'
	    )
	);

	if ( is_wp_error( $response ) ) {
	    throw new Exception($response->get_error_message());
	}

	$data = json_decode(wp_remote_retrieve_body( $response ), true);

	if ( isset( $data['error'] ) ) {
	    throw new Exception($data['error']['message']);
	}

	$tmp       = explode(' ', $data['name']);
	$lastName  = array_pop($tmp);
	$firstName = implode(' ', $tmp);

	return array(
	    'id'	=> $data['id'],
	    'first_name'=> $firstName,
	    'last_name'	=> $lastName,
	    'email'	=> $data['email'],
	    'phone'	=> '',
	    'address'	=> '',
	);
    }

    protected static function createWpUser($fbUser, $createCustomerFields = false) {

	$userID = wp_create_user("fb_{$fbUser['id']}", wp_generate_password(), $fbUser['email']);

	if ( is_wp_error($userID) ) {
	    throw new Exception($userID->get_error_message());
	}

	wp_update_user(array(
	    'ID'           => $userID,
	    'display_name' => $fbUser['first_name'] . ' ' . $fbUser['last_name'],
	    'nickname'     => $fbUser['first_name'] . ' ' . $fbUser['last_name'],
	    'first_name'   => $fbUser['first_name'],
	    'last_name'    => $fbUser['last_name'],
	    'role'         => SLN_Plugin::USER_ROLE_CUSTOMER,
	));

	add_user_meta($userID, '_sln_fb_id', $fbUser['id']);
	add_user_meta($userID, '_sln_phone', $fbUser['phone']);
	add_user_meta($userID, '_sln_address', $fbUser['address']);

	if ( $createCustomerFields ) {

	    $customer_fields = SLN_Enum_CheckoutFields::forRegistration()->keys();

	    foreach($customer_fields as $k){
	    	if(in_array($k,['firstname','lastname'])) continue;
			add_user_meta($userID, '_sln_'.$k, '');
	    }
	}

	if (!SLN_Plugin::getInstance()->getSettings()->isDisableNewUserWelcomeEmail()) {
            wp_new_user_notification($userID, null, 'both');
        }

	return $userID;
    }

}