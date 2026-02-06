<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API;

use SLB_API\Helper\TokenHelper;
use SLB_API\Helper\RequestHelper;

use WP_Error;

class Plugin {

    private static $instance;

    const BASE_API = 'salon/api/v1';

    /**
     * @var SLN_Plugin
     */
    private $plugin;

    public static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
	if ( ! class_exists( '\WP_REST_Server' ) ) {
            return;
        }

        // Init REST API routes.
        add_action( 'rest_api_init', array( $this, 'rest_api_init' ));
    }

    public function rest_api_init()
    {
        add_filter('rest_authentication_errors', array($this, 'handle_rest_authentication'), 100, 1);
        $this->register_rest_routes();
    }

    public function register_rest_routes()
    {
        $controllers = array(
            '\\SLB_API\\Controller\\Auth_Controller',
            '\\SLB_API\\Controller\\Assistants_Controller',
            '\\SLB_API\\Controller\\Services_Controller',
            '\\SLB_API\\Controller\\ServicesCategories_Controller',
            '\\SLB_API\\Controller\\Customers_Controller',
            '\\SLB_API\\Controller\\Discounts_Controller',
            '\\SLB_API\\Controller\\Bookings_Controller',
            '\\SLB_API\\Controller\\AvailabilityIntervals_Controller',
            '\\SLB_API\\Controller\\AvailabilityServices_Controller',
            '\\SLB_API\\Controller\\AvailabilityAssistants_Controller',
            '\\SLB_API\\Controller\\Users_Controller',
            '\\SLB_API\\Controller\\AvailabilityBooking_Controller',
            '\\SLB_API\\Controller\\App_Controller',
            '\\SLB_API\\Controller\\Shops_Controller',
            '\\SLB_API\\Controller\\NoShow_Controller',
        );

        foreach ( $controllers as $controller ) {
            $controller = new $controller();
            $controller->register_routes();
        }
    }

    public function handle_rest_authentication($result)
    {
        // If another authentication method already succeeded or failed, respect it
        if ($result !== null) {
            return $result;
        }

        // Only handle our API routes
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_salon_api = (stristr($request_uri, self::BASE_API) !== false);
        
        if (!$is_salon_api) {
            return $result;
        }

        // Allow login endpoint without authentication
        if (stristr($request_uri, '/login') !== false) {
            return $result;
        }

        // Check if user is already logged in via WordPress cookies
        // This happens when API is called from admin area
        $current_user_id = get_current_user_id();
        if ($current_user_id > 0) {
            // User is authenticated via WordPress session
            return true;
        }

        // Check for Bearer token (external API access)
        $token_helper   = new TokenHelper();
        $request_helper = new RequestHelper();

        $access_token = $request_helper->getAccessToken();

        if (!empty($access_token) && $token_helper->isValidUserAccessToken($access_token)) {
            // Valid Bearer token, set current user
            $user_id = $token_helper->getUserIdByAccessToken($access_token);
            wp_set_current_user($user_id);
            return true;
        }
        
        // No valid authentication found
        return new WP_Error(
            'salon_rest_cannot_view',
            __('Sorry, you access token incorrect.', 'salon-booking-system'),
            array('status' => rest_authorization_required_code())
        );
    }

}