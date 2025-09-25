<?php

namespace SLB_API_Mobile\Controller;

use SLN_Plugin;
use WP_REST_Server;
use SLN_DateTime;
use Salon\Util\Date;
use SLN_Enum_BookingStatus;
use DateTime;
use SLN_Formatter;
// phpcs:ignoreFile WordPress.DateTime.RestrictedFunctions.date_date
class HolidayRules_Controller extends REST_Controller
{
    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'holiday-rules';

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_holiday_rules'),
                'permission_callback' => '__return_true',
                'args' => apply_filters('sln_api_holiday_rules_register_routes_get_holiday_rules_args', array(
                    'date' => array(
                        'description' => __('Date.', 'salon-booking-system'),
                        'type' => 'string',
                        'format' => 'YYYY-MM-DD',
                        'required' => false,
                        'default' => '',
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                    'assistants_mode' => array(
                        'description' => __('Enable assistants mode', 'salon-booking-system'),
                        'type' => 'boolean',
                        'required' => false,
                        'default' => false
                    ),
                    'shop' => array(
                        'description' => __('Shop ID.', 'salon-booking-system'),
                        'type' => 'integer',
                        'required' => false,
                        'default' => null,
                    ),
                )),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_holiday_rule'),
                'permission_callback' => array($this, 'create_holiday_rule_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_holiday_rule'),
                'permission_callback' => array($this, 'delete_holiday_rule_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));
    }

    public function create_holiday_rule_permissions_check($request)
    {
        return current_user_can('manage_salon');
    }

    public function delete_holiday_rule_permissions_check($request)
    {
        return current_user_can('manage_salon');
    }


    public function get_holiday_rules($request)
    {
        try {
            do_action('sln_api_holiday_rules_get_holiday_rules_before', $request);
            do_action('sln_api_holiday_rules_get_holiday_rules_before_check', $request);


            $date = sanitize_text_field(wp_unslash($request->get_param('date')));
            $assistants_mode = (bool)$request->get_param('assistants_mode');
            $shop_id = $request->get_param('shop');

            $plugin = SLN_Plugin::getInstance();
            $settings = $plugin->getSettings();

            if ($assistants_mode) {
                $holidays_rules = $settings->get('holidays_daily') ?: array();

                foreach ($holidays_rules as &$rule) {
                    $rule['is_manual'] = isset($rule['is_manual']) ? (bool)$rule['is_manual'] : true;
                }

                return $this->success_response([
                    'success' => 1,
                    'rules' => $holidays_rules,
                    'assistants_rules' => $this->get_assistants_rules($shop_id, $date)
                ]);
            }

            return $this->success_response([
                'items' => $this->get_holidays($date)
            ]);

        } catch (\Exception $ex) {
            return new \WP_Error(
                'salon_rest_cannot_view',
                $ex->getMessage(),
                ['status' => $ex->getCode() ?: 500]
            );
        }
    }

    public function create_holiday_rule($request)
    {
        try {
            do_action('sln_api_holiday_rules_create_holiday_rule_before', $request);
            do_action('sln_api_holiday_rules_create_holiday_rule_before_check', $request);

            $plugin = SLN_Plugin::getInstance();
            $settings = $plugin->getSettings();
            $formatter = new SLN_Formatter($plugin);

            $data = array();
            $data['assistant_id'] = $request->get_param('assistant_id');
            $data['from_date'] = $request->get_param('from_date');
            $data['to_date'] = $request->get_param('to_date');
            $data['from_time'] = $formatter->time($request->get_param('from_time'));
            $data['to_time'] = $formatter->time($request->get_param('to_time'));
            $data['daily'] = true;
            $data['is_manual'] = (bool)$request->get_param('is_manual');

            if ($data['from_date'] != $data['to_date']) {
                $toTime = new DateTime($data['to_date'] . ' ' . $data['to_time']);
                $toTimeHour = (int)$toTime->format('H');
                if ($toTimeHour < 12) {
                    $data['to_date'] = $data['from_date'];
                    $fromTime = new DateTime($data['from_date'] . ' ' . $data['from_time']);
                    $interval = $settings->getInterval();
                    $endTime = clone $fromTime;
                    $endTime->modify('+' . $interval . ' minutes');
                    $data['to_time'] = $endTime->format('H:i');
                }
            }

            $assistants_mode = (bool)$request->get_param('assistants_mode');
            $shop_id = $request->get_param('shop');

            if ($this->validateDate($data['from_date']) && $this->validateDate($data['to_date'])) {
                if (!empty($data['assistant_id'])) {
                    $applied = apply_filters('sln.add-holiday-rule.add-holidays-daily-assistants', false, $data, $data['assistant_id']);

                    if (!$applied) {
                        $attendant = $plugin->createAttendant($data['assistant_id']);
                        $holidays_rules = $attendant->getMeta('holidays_daily') ?: array();
                        $holidays_rules[] = $data;
                        $attendant->setMeta('holidays_daily', $holidays_rules);
                    }
                } else {
                    $applied = apply_filters('sln.add-holiday-rule.add-holidays-daily', false, $data);

                    if (!$applied) {
                        $holidays_rules = $settings->get('holidays_daily') ?: array();
                        $holidays_rules[] = $data;
                        $settings->set('holidays_daily', $holidays_rules);
                        $settings->save();
                    }
                }

                $bc = $plugin->getBookingCache();
                $bc->refresh($data['from_date'], $data['to_date']);

                if ($assistants_mode) {
                    return $this->success_response([
                        'success' => 1,
                        'rules' => $settings->get('holidays_daily') ?: array(),
                        'assistants_rules' => $this->get_assistants_rules($shop_id, $date)
                    ]);
                }

                return $this->success_response(['items' => $this->get_holidays($data['from_date'])]);
            }

            throw new \Exception(__('Invalid date format', 'salon-booking-system'));

        } catch (\Exception $ex) {
            return new \WP_Error(
                'salon_rest_cannot_view',
                $ex->getMessage(),
                ['status' => $ex->getCode() ?: 500]
            );
        }
    }

    public function delete_holiday_rule($request)
    {
        try {
            do_action('sln_api_holiday_rules_delete_holiday_rule_before', $request);
            do_action('sln_api_holiday_rules_delete_holiday_rule_before_check', $request);

            $plugin = SLN_Plugin::getInstance();
            $settings = $plugin->getSettings();
            $formatter = new SLN_Formatter($plugin);

            $data = array();
            $data['assistant_id'] = $request->get_param('assistant_id');
            $data['from_date'] = $request->get_param('from_date');
            $data['to_date'] = $request->get_param('to_date');
            $data['from_time'] = $formatter->time($request->get_param('from_time'));
            $data['to_time'] = $formatter->time($request->get_param('to_time'));
            $data['daily'] = true;

            $assistants_mode = (bool)$request->get_param('assistants_mode');
            $shop_id = $request->get_param('shop');

            if (!empty($data['assistant_id'])) {
                $applied = apply_filters('sln.remove-holiday-rule.remove-holidays-daily-assistants', false, $data, $data['assistant_id']);

                if (!$applied) {
                    $attendant = $plugin->createAttendant($data['assistant_id']);

                    $holidays_rules = $attendant->getMeta('holidays_daily') ?: array();
                    $search_rule = array();

                    foreach ($holidays_rules as $rule) {
                        if (
                            $data['from_date'] === $rule['from_date'] &&
                            $data['to_date'] === $rule['to_date'] &&
                            $data['from_time'] === $formatter->time($rule['from_time']) &&
                            $data['to_time'] === $formatter->time($rule['to_time']) &&
                            $rule['daily'] === true
                        ) continue;

                        $search_rule[] = $rule;
                    }

                    $attendant->setMeta('holidays_daily', $search_rule);

                    $holidays = $attendant->getMeta('holidays') ?: array();
                    $search_holidays = array();

                    foreach ($holidays as $rule) {
                        if (
                            $data['from_date'] === $rule['from_date'] &&
                            $data['to_date'] === $rule['to_date'] &&
                            $data['from_time'] === $formatter->time($rule['from_time']) &&
                            $data['to_time'] === $formatter->time($rule['to_time'])
                        ) continue;

                        $search_holidays[] = $rule;
                    }

                    $attendant->setMeta('holidays', $search_holidays);

                    $bc = $plugin->getBookingCache();
                    $bc->refresh($data['from_date'], $data['to_date']);
                }
            } else {
                $applied = apply_filters('sln.remove-holiday-rule.remove-holidays-daily', false, $data);

                if (!$applied) {
                    $holidays_rules = $settings->get('holidays_daily');
                    $search_rule = array();

                    foreach ($holidays_rules as $rule) {
                        if (
                            $data['from_date'] === $rule['from_date'] &&
                            $data['to_date'] === $rule['to_date'] &&
                            $data['from_time'] === $formatter->time($rule['from_time']) &&
                            $data['to_time'] === $formatter->time($rule['to_time']) &&
                            $rule['daily'] === true
                        ) continue;

                        $search_rule[] = $rule;
                    }

                    $settings->set('holidays_daily', $search_rule);
                    $settings->save();
                }
            }

            $bc = $plugin->getBookingCache();
            $bc->refresh($data['from_date'], $data['to_date']);

            if ($assistants_mode) {
                $holidays_rules = $settings->get('holidays_daily') ?: array();

                foreach ($holidays_rules as &$rule) {
                    $rule['is_manual'] = isset($rule['is_manual']) ? (bool)$rule['is_manual'] : true;
                }

                return $this->success_response([
                    'success' => 1,
                    'rules' => $holidays_rules,
                    'assistants_rules' => $this->get_assistants_rules($shop_id, $date)
                ]);
            }

            return $this->success_response(['items' => $this->get_holidays($data['from_date'])]);

        } catch (\Exception $ex) {
            return new \WP_Error(
                'salon_rest_cannot_view',
                $ex->getMessage(),
                ['status' => $ex->getCode() ?: 500]
            );
        }
    }

    protected function get_assistants_rules($shop_id = null, $date = '')
    {
        $plugin = SLN_Plugin::getInstance();
        $assistants = $plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT)->getAll();

        $holidays_assistants_rules = array();
        foreach ($assistants as $att) {
            $current_attendant = $att;

            if ($shop_id && class_exists('\SalonMultishop\Addon')) {
                try {
                    $shop = $plugin->createFromPost($shop_id);
                    $current_attendant = $shop->getAttendantWrapper($att);
                } catch (\Exception $e) {
                }
            }

            $holidays_daily = $current_attendant->getMeta('holidays_daily') ?: array();

            $holidays_daily = array_filter($holidays_daily, function ($h) use ($date) {
                foreach (['from_date', 'to_date', 'from_time', 'to_time', 'daily'] as $prop) {
                    if (empty($h[$prop])) {
                        return false;
                    }
                }
                if (!empty($date)) {
                    return ($date === $h['from_date'] ||
                        $date === $h['to_date'] ||
                        ($date >= $h['from_date'] && $date <= $h['to_date']));
                }
                return true;
            });

            foreach ($holidays_daily as &$rule) {
                $rule['assistant_id'] = $att->getId();
                $rule['is_manual'] = isset($rule['is_manual']) ? (bool)$rule['is_manual'] : true;
            }

            $holidays = $current_attendant->getMeta('holidays') ?: array();

            $holidays = array_filter($holidays, function ($h) use ($date) {
                foreach (['from_date', 'to_date', 'from_time', 'to_time'] as $prop) {
                    if (empty($h[$prop])) {
                        return false;
                    }
                }
                if (!empty($date)) {
                    return ($date === $h['from_date'] ||
                        $date === $h['to_date'] ||
                        ($date >= $h['from_date'] && $date <= $h['to_date']));
                }
                return true;
            });

            foreach ($holidays as &$rule) {
                $rule['assistant_id'] = $att->getId();
                if (!isset($rule['daily'])) {
                    $rule['daily'] = true;
                }
                $rule['is_manual'] = isset($rule['is_manual']) ? (bool)$rule['is_manual'] : false;
            }

            $all_rules = array_merge($holidays_daily, $holidays);

            $unique_rules = array();
            $seen_keys = array();
            foreach ($all_rules as $rule) {
                $key = $rule['from_date'] . '|' . $rule['to_date'] . '|' . $rule['from_time'] . '|' . $rule['to_time'];
                if (!isset($seen_keys[$key])) {
                    $seen_keys[$key] = true;
                    $unique_rules[] = $rule;
                }
            }

            $holidays_assistants_rules[$att->getId()] = $unique_rules;
        }

        return apply_filters(
            'sln.get-day-holidays-assistants-rules',
            $holidays_assistants_rules,
            $assistants
        );
    }

    protected function get_holidays($date = '')
    {
        $plugin = SLN_Plugin::getInstance();
        $settings = $plugin->getSettings();
        $formatter = new SLN_Formatter($plugin);

        $holidays_rules = $settings->get('holidays_daily') ?: array();
        $holidays = $settings->get('holidays') ?: array();
        $all_rules = array_merge($holidays_rules, $holidays);

        $ret = array();

        if (!empty($date)) {
            foreach ($all_rules as $rule) {
                if (
                    ($date === $rule['from_date'] ||
                        $date === $rule['to_date'] ||
                        ($date >= $rule['from_date'] && $date <= $rule['to_date']))
                ) {
                    if (isset($rule['from_time'])) {
                        $rule['from_time'] = $formatter->time($rule['from_time']);
                    }
                    if (isset($rule['to_time'])) {
                        $rule['to_time'] = $formatter->time($rule['to_time']);
                    }
                    $ret[] = $rule;
                }
            }
        } else {
            foreach ($all_rules as $rule) {
                if (isset($rule['from_time'])) {
                    $rule['from_time'] = $formatter->time($rule['from_time']);
                }
                if (isset($rule['to_time'])) {
                    $rule['to_time'] = $formatter->time($rule['to_time']);
                }
                $ret[] = $rule;
            }
        }

        return $ret;
    }

    public function validateDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    public function get_item_schema()
    {
        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'holiday rule',
            'type' => 'object',
            'properties' => array(
                'from_date' => array(
                    'description' => __('From date.', 'salon-booking-system'),
                    'type' => 'string',
                    'format' => 'YYYY-MM-DD',
                    'context' => array('view', 'edit'),
                    'arg_options' => array(
                        'required' => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                ),
                'to_date' => array(
                    'description' => __('To date.', 'salon-booking-system'),
                    'type' => 'string',
                    'format' => 'YYYY-MM-DD',
                    'context' => array('view', 'edit'),
                    'arg_options' => array(
                        'required' => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                ),
                'from_time' => array(
                    'description' => __('From time.', 'salon-booking-system'),
                    'type' => 'string',
                    'format' => 'HH:ii',
                    'context' => array('view', 'edit'),
                    'arg_options' => array(
                        'required' => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                ),
                'to_time' => array(
                    'description' => __('To time.', 'salon-booking-system'),
                    'type' => 'string',
                    'format' => 'HH:ii',
                    'context' => array('view', 'edit'),
                    'arg_options' => array(
                        'required' => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                ),
                'daily' => array(
                    'description' => __('Daily.', 'salon-booking-system'),
                    'type' => 'boolean',
                    'context' => array('view'),
                ),
            ),
        );

        return apply_filters('sln_api_holiday_rules_get_item_schema', $schema);
    }

}
