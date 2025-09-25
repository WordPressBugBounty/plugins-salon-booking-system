<?php

namespace SLB_API_Mobile\Controller;

use SLN_Plugin;
use WP_REST_Server;

class App_Controller extends REST_Controller
{
    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'app';

    public function register_routes() {

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/about', array(
            array(
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => array( $this, 'get_about_info' ),
                'permission_callback' => '__return_true',
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/settings', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_settings' ),
                'permission_callback' => '__return_true',
                'args'     => apply_filters('sln_api_app_register_routes_get_settings_args', array()),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/settings', array(
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_settings' ),
                'permission_callback' => '__return_true',
                'args'     => apply_filters('sln_api_app_register_routes_get_settings_args', array(
                    'shop' => array(
                        'description' => __('Shop ID.', 'salon-booking-system'),
                        'type'        => 'integer',
                        'default'     => null,
                    ),
                )),
            ),
        ) );
    }

    public function get_about_info() {

        $info = array(
            'name'        => $this->get_app_name(),
            'version'	  => $this->get_app_version(),
            'pro_version' => $this->get_app_pro_version(),
            'author'	  => $this->get_app_author(),
        );

        return $this->success_response(array('info' => $info));
    }

    protected function get_app_name() {
        return defined('SLN_ITEM_NAME') ? SLN_ITEM_NAME : '';
    }

    protected function get_app_version() {
        return defined('SLN_VERSION') ? SLN_VERSION : '';
    }

    protected function get_app_pro_version() {
        return defined('SLN_VERSION_PAY') || defined('SLN_VERSION_CODECANYON');
    }

    protected function get_app_author() {
        return defined('SLN_AUTHOR') ? SLN_AUTHOR : '';
    }

    public function get_settings($request)
    {
        try {
            do_action('sln_api_app_get_settings_before', $request);
            do_action('sln_api_app_get_settings_before_check', $request);

            $plugin = SLN_Plugin::getInstance();
            $s = $plugin->getSettings();

            $shop_id = $request->get_param('shop');
            if ($shop_id && class_exists('\SalonMultishop\Addon')) {
                try {
                    $addon = \SalonMultishop\Addon::getInstance();
                    $addon->setCurrentShop($shop_id);
                } catch (\Exception $e) {
                    return $e->getMessage();
                }
            }

            $availabilities = $s->get('availabilities');
            $holidays = $s->get('holidays');
            $holidays_daily = $s->get('holidays_daily');

            $firstRule = reset($availabilities);
            $availableDays = $firstRule['days'] ?? [];

            $rulesData = array();
            foreach ($availabilities as $idx => $rule) {
                $shifts = array();
                if (isset($rule['from']) && isset($rule['to'])) {
                    $shifts[] = array(
                        'from' => $rule['from'][0] ?? null,
                        'to' => $rule['to'][0] ?? null,
                        'disabled' => false,
                    );
                    $shifts[] = array(
                        'from' => $rule['from'][1] ?? null,
                        'to' => $rule['to'][1] ?? null,
                        'disabled' => !empty($rule['disable_second_shift']),
                    );
                }
                $rulesData[] = array(
                    'days' => $rule['days'] ?? array(),
                    'shifts' => $shifts,
                    'always' => !empty($rule['always']),
                    'from_date' => $rule['from_date'] ?? null,
                    'to_date' => $rule['to_date'] ?? null,
                );
                $availableDays += isset($rule['days']) && is_array($rule['days']) ? $rule['days'] : array();
            }
            $date_format = $s->get('date_format');
            $time_format = $s->get('time_format');

            $userRoleHelper = new \SLB_API_Mobile\Helper\UserRoleHelper();

            $settings = array(
                'attendant_enabled' => (bool)$s->get('attendant_enabled'),
                'date_format' => array(
                    'type' => $date_format,
                    'php_format' => \SLN_Enum_DateFormat::getPhpFormat($date_format),
                    'js_format' => \SLN_Enum_DateFormat::getJsFormat($date_format),
                ),
                'time_format' => array(
                    'type' => $time_format,
                    'php_format' => \SLN_Enum_TimeFormat::getPhpFormat($time_format),
                    'js_format' => \SLN_Enum_TimeFormat::getJsFormat($time_format),
                ),
                'interval_mins' => $s->get('interval'),
                'locale' => $s->getDateLocale(),
                'discounts_enabled' => (bool)$s->get('enable_discount_system'),
                'currency' => $s->getCurrency(),
                'currency_symbol' => $s->getCurrencySymbol(),
                'resources_enabled' => (bool)$s->get('enable_resources'),
                'available_days' => $availableDays,
                'availabilities' => $rulesData,
                'holidays' => is_array($holidays) ? $holidays : array(),
                'holidays_daily' => is_array($holidays_daily) ? $holidays_daily : array(),
                'hide_customers_email' => (bool)$userRoleHelper->is_hide_customer_email(),
                'hide_customers_phone' => (bool)$userRoleHelper->is_hide_customer_phone(),
                'current_shop_id' => $shop_id,
                'multishop_enabled' => class_exists('\SalonMultishop\Addon'),
            );
            return $this->success_response(array('settings' => $settings));
        } catch (\Exception $ex) {
            return new \WP_Error('salon_rest_cannot_view', $ex->getMessage(), array('status' => $ex->getCode() ? $ex->getCode() : 500));
        }
    }

}