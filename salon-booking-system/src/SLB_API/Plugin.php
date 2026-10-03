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
        // Revoke legacy predictable tokens before any request can present one.
        TokenHelper::revokePredictableTokens();

	if ( ! class_exists( '\WP_REST_Server' ) ) {
            return;
        }

        // Init REST API routes.
        add_action( 'rest_api_init', array( $this, 'rest_api_init' ));
    }

    public function rest_api_init()
    {
        add_filter('rest_pre_dispatch', array($this, 'handle_rest_authentication'), 10, 3);
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
            '\\SLB_API\\Controller\\RevenueGuard_Controller',
        );

        foreach ( $controllers as $controller ) {
            $controller = new $controller();
            $controller->register_routes();
        }
    }

    /**
     * Authenticate Salon desktop API requests from the resolved REST route.
     *
     * rest_pre_dispatch receives the WP_REST_Request WordPress will actually
     * dispatch. A query-string cannot change get_route(), so a core endpoint
     * cannot be made to look like a Salon route.
     *
     * A non-empty return value replaces the REST response. After a successful
     * login, return the incoming $result (normally null) so dispatch continues.
     *
     * @param mixed            $result  Pre-dispatch result.
     * @param \WP_REST_Server  $server  Server instance.
     * @param \WP_REST_Request $request Request.
     * @return mixed
     */
    public function handle_rest_authentication($result, $server = null, $request = null)
    {
        unset($server);

        if (!$request instanceof \WP_REST_Request) {
            return $result;
        }

        $route = strtolower(untrailingslashit((string) $request->get_route()));
        $prefix = '/' . self::BASE_API;
        $is_salon_api = ($route === $prefix || strpos($route, $prefix . '/') === 0);

        // Not one of our routes: never switch the current user.
        if (!$is_salon_api) {
            return $result;
        }

        if ($request->get_method() === 'OPTIONS') {
            return $result;
        }

        if ($route === $prefix . '/login') {
            return $result;
        }

        if (is_wp_error($result)) {
            return $result;
        }

        // Cookie session from wp-admin. Do not return true: that would replace
        // the REST response on rest_pre_dispatch.
        if (get_current_user_id() > 0) {
            return $result;
        }

        $token_helper   = new TokenHelper();
        $request_helper = new RequestHelper();
        $user_id        = $token_helper->getUserIdByAccessToken($request_helper->getAccessToken());

        if ($user_id && get_userdata($user_id)) {
            wp_set_current_user((int) $user_id);
            return $result;
        }

        return new WP_Error(
            'salon_rest_cannot_view',
            __('Sorry, you access token incorrect.', 'salon-booking-system'),
            array('status' => rest_authorization_required_code())
        );
    }

}