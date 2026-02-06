<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API_Mobile\Controller;

use SLN_Plugin;
use WP_REST_Server;
use SLN_DateTime;
use Salon\Util\Date;
use SLN_Enum_BookingStatus;

class AvailabilityStats_Controller extends REST_Controller
{
    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'availability/stats';

    public function register_routes() {

        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            'args' => apply_filters('sln_api_availability_stats_register_routes_get_stats_args', array(
                'from_date'     => array(
                    'description'       => __('From date.', 'salon-booking-system'),
                    'type'              => 'string',
                    'format'            => 'YYYY-MM-DD',
                    'required'          => true,
                    'validate_callback' => array($this, 'rest_validate_request_arg'),
                ),
                'to_date'     => array(
                    'description'       => __('To date.', 'salon-booking-system'),
                    'type'              => 'string',
                    'format'            => 'YYYY-MM-DD',
                    'required'          => true,
                    'validate_callback' => array($this, 'rest_validate_request_arg'),
                ),
                'debug'     => array(
                    'description'       => __('Enable diagnostic mode.', 'salon-booking-system'),
                    'type'              => 'string',
                    'required'          => false,
                ),
            )),
            array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_stats'),
		'permission_callback' => '__return_true',
            ),
        ) );
    }

    public function get_stats( $request )
    {
        try {
            // Check if debug mode is enabled
            $debugMode = $request->get_param('debug') === '1';

            do_action('sln_api_availability_stats_get_availability_stats_before', $request);

            $from = (new SLN_DateTime)->setTimestamp( strtotime( sanitize_text_field( wp_unslash( $request->get_param('from_date') ) ) ) );

            $to = (new SLN_DateTime)->setTimestamp( strtotime( sanitize_text_field( wp_unslash( $request->get_param('to_date') ) ) ) );

            do_action('sln_api_availability_stats_get_availability_stats_before_check', $request);

            $plugin   = SLN_Plugin::getInstance();
            $settings = $plugin->getSettings();
            $bc	  = $plugin->getBookingCache();
            $bookings = $this->getBookings($from, $to);
            $clone	  = clone $from;
            $ret	  = array();
            
            // Collect diagnostic info if debug mode enabled
            $diagnosticInfo = array();
            if ($debugMode) {
                $diagnosticInfo = $this->collectDiagnosticInfo($settings, $bc);
            }

            while ($clone <= $to) {
                $dd = clone $clone;
                $dd->modify('+1 hour');
                $dd = new Date($dd);

                $tmp = array('date' => $dd->toString('Y-m-d'), 'available' => true);

                $bc->processDate($dd);
                $cache = $bc->getDay($dd);
                if ($cache && $cache['status'] == 'booking_rules') {
                    $tmp['error']		 = array();
                    $tmp['available']	 = false;
                    $tmp['error']['type']	 = $cache['status'];
                    $tmp['error']['message'] = __('Booking Rule', 'salon-booking-system');
                    
                    // Add detailed debug info for this date if debug mode enabled
                    if ($debugMode) {
                        $tmp['debug'] = $this->getDateDebugInfo($dd, $cache, $settings, $bc);
                    }
                } elseif ($cache && $cache['status'] == 'holiday_rules') {
                    $tmp['error']		 = array();
                    $tmp['available']	 = false;
                    $tmp['error']['type']	 = $cache['status'];
                    $tmp['error']['message'] = __('Holiday Rule', 'salon-booking-system');
                    
                    // Add detailed debug info for this date if debug mode enabled
                    if ($debugMode) {
                        $tmp['debug'] = $this->getDateDebugInfo($dd, $cache, $settings, $bc);
                    }
                } else {
                    $tot = 0;
                    $cnt = 0;
                    foreach ($bookings as $b) {
                        if ($b->getDate()->format('Ymd') == $clone->format('Ymd')) {
                            if (!$b->hasStatus(
                                array(
                                    SLN_Enum_BookingStatus::CANCELED,
                                )
                            )
                            ) {
                                $tot += $b->getAmount();
                                $cnt++;
                            }
                        }
                    }
                    if (isset($cache['free_slots'])) {
                        $free = count($cache['free_slots']) * $settings->getInterval();
                    } else {
                        $free = 0;
                    }

                    $tmp['full_booked'] = false;

                    if ($cache && $cache['status'] == 'full') {
                        $tmp['full_booked'] = true;
                    }

                    $freeH = intval($free / 60);
                    $freeM = ($free % 60);

                    $tmp['data'] = array(
                        'bookings' => $cnt,
                        'revenue'  => $tot,
                        'currency' => $settings->getCurrencySymbol(),
                        'available_left' => array(
                            'hours' => $freeH,
                            'mins'  => $freeM > 0 ? $freeM : 0,
                        )
                    );

                }
                $ret[] = $tmp;
                $clone->modify('+1 days');
            }

            $response = array('stats' => $ret);
            
            // Include diagnostic info in response if debug mode enabled
            if ($debugMode) {
                $response['diagnostic'] = $diagnosticInfo;
            }

            return $this->success_response($response);

        } catch (\Exception $ex) {
            return new \WP_Error( 'salon_rest_cannot_view', $ex->getMessage(), array( 'status' => $ex->getCode() ? $ex->getCode() : 500 ) );
        }
    }
    
    /**
     * Collect comprehensive diagnostic information about availability settings
     * 
     * @param \SLN_Settings $settings
     * @param mixed $bc BookingCache instance
     * @return array
     */
    private function collectDiagnosticInfo($settings, $bc)
    {
        $ah = SLN_Plugin::getInstance()->getAvailabilityHelper();
        $hb = $ah->getHoursBeforeHelper();
        
        // Get availability rules
        $availabilities = $settings->get('availabilities');
        $availabilityItems = $settings->getAvailabilityItems();
        
        // Process availability rules for diagnostic output
        $processedRules = array();
        if (is_array($availabilities)) {
            foreach ($availabilities as $index => $rule) {
                $processedRules[] = array(
                    'rule_index' => $index,
                    'days_enabled' => isset($rule['days']) ? $rule['days'] : 'NOT SET',
                    'days_count' => isset($rule['days']) && is_array($rule['days']) ? count(array_filter($rule['days'])) : 0,
                    'always' => isset($rule['always']) ? $rule['always'] : 'NOT SET',
                    'from_date' => isset($rule['from_date']) ? $rule['from_date'] : 'NOT SET',
                    'to_date' => isset($rule['to_date']) ? $rule['to_date'] : 'NOT SET',
                    'from_times' => isset($rule['from']) ? $rule['from'] : 'NOT SET',
                    'to_times' => isset($rule['to']) ? $rule['to'] : 'NOT SET',
                    'select_specific_dates' => isset($rule['select_specific_dates']) ? $rule['select_specific_dates'] : false,
                );
            }
        }
        
        // Get holiday rules
        $holidays = $settings->get('holidays');
        $holidaysDaily = $settings->get('holidays_daily');
        
        return array(
            'server_time' => array(
                'current_datetime' => \SLN_TimeFunc::date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get(),
                'wp_timezone' => wp_timezone_string(),
            ),
            'hours_before' => array(
                'from_setting' => $settings->getHoursBeforeFrom(),
                'to_setting' => $settings->getHoursBeforeTo(),
                'calculated_from_date' => $hb->getFromDate()->format('Y-m-d H:i:s'),
                'calculated_to_date' => $hb->getToDate()->format('Y-m-d H:i:s'),
                'booking_window_days' => $hb->getCountDays(),
            ),
            'availability_rules' => array(
                'total_rules' => is_array($availabilities) ? count($availabilities) : 0,
                'rules_detail' => $processedRules,
                'has_valid_rules' => !empty($processedRules),
            ),
            'holidays' => array(
                'holiday_dates' => is_array($holidays) ? $holidays : array(),
                'holiday_dates_count' => is_array($holidays) ? count($holidays) : 0,
                'daily_holidays' => is_array($holidaysDaily) ? $holidaysDaily : array(),
                'daily_holidays_count' => is_array($holidaysDaily) ? count($holidaysDaily) : 0,
            ),
            'general_settings' => array(
                'interval_minutes' => $settings->getInterval(),
                'available_days_setting' => $settings->get('available_days'),
            ),
            'potential_issues' => $this->detectPotentialIssues($availabilities, $holidays, $holidaysDaily, $hb, $settings),
        );
    }
    
    /**
     * Get debug info for a specific date
     * 
     * @param Date $date
     * @param array $cache
     * @param \SLN_Settings $settings
     * @param mixed $bc
     * @return array
     */
    private function getDateDebugInfo($date, $cache, $settings, $bc)
    {
        $ah = SLN_Plugin::getInstance()->getAvailabilityHelper();
        $hb = $ah->getHoursBeforeHelper();
        $availabilityItems = $settings->getAvailabilityItems();
        $holidayItems = $settings->getHolidayItems();
        
        $dateObj = $date;
        $weekday = $dateObj->getWeekday(); // 0 = Sunday, 6 = Saturday
        
        return array(
            'date_info' => array(
                'date' => $dateObj->toString('Y-m-d'),
                'weekday_number' => $weekday,
                'weekday_name' => date('l', strtotime($dateObj->toString())),
                'weekday_check_key' => $weekday + 1, // Key used in days array (1=Sunday, 7=Saturday)
            ),
            'cache_status' => $cache['status'],
            'free_slots_count' => isset($cache['free_slots']) ? count($cache['free_slots']) : 0,
            'validation_checks' => array(
                'is_valid_by_availability_rules' => $availabilityItems->isValidDate($dateObj),
                'is_valid_by_holiday_rules' => $holidayItems->isValidDate($dateObj),
                'is_within_booking_window' => $hb->check($dateObj->getDateTime()),
                'booking_window_from' => $hb->getFromDate()->format('Y-m-d H:i:s'),
                'booking_window_to' => $hb->getToDate()->format('Y-m-d H:i:s'),
            ),
        );
    }
    
    /**
     * Detect potential configuration issues
     * 
     * @param array|null $availabilities
     * @param array|null $holidays
     * @param array|null $holidaysDaily
     * @param \SLN_Helper_HoursBefore $hb
     * @param \SLN_Settings $settings
     * @return array
     */
    private function detectPotentialIssues($availabilities, $holidays, $holidaysDaily, $hb, $settings)
    {
        $issues = array();
        
        // Issue 1: No availability rules defined
        if (empty($availabilities)) {
            $issues[] = array(
                'type' => 'warning',
                'code' => 'NO_AVAILABILITY_RULES',
                'message' => 'No availability rules are defined. This should default to allow all, but may cause issues.',
            );
        }
        
        // Issue 2: Availability rules exist but no days enabled
        if (is_array($availabilities)) {
            $anyDaysEnabled = false;
            foreach ($availabilities as $rule) {
                if (isset($rule['days']) && is_array($rule['days'])) {
                    $enabledDays = array_filter($rule['days']);
                    if (!empty($enabledDays)) {
                        $anyDaysEnabled = true;
                        break;
                    }
                }
            }
            
            if (!$anyDaysEnabled && !empty($availabilities)) {
                $issues[] = array(
                    'type' => 'critical',
                    'code' => 'NO_DAYS_ENABLED',
                    'message' => 'CRITICAL: Availability rules exist but NO days of the week are enabled. All dates will be unavailable!',
                );
            }
        }
        
        // Issue 3: Check if all rules have expired date ranges
        if (is_array($availabilities)) {
            $allRulesExpired = true;
            $today = new \DateTime(\SLN_TimeFunc::date('Y-m-d'));
            
            foreach ($availabilities as $rule) {
                if (isset($rule['always']) && $rule['always']) {
                    $allRulesExpired = false;
                    break;
                }
                
                if (!empty($rule['to_date'])) {
                    $toDate = new \DateTime($rule['to_date']);
                    if ($toDate >= $today) {
                        $allRulesExpired = false;
                        break;
                    }
                } else {
                    $allRulesExpired = false;
                    break;
                }
            }
            
            if ($allRulesExpired && !empty($availabilities)) {
                $issues[] = array(
                    'type' => 'critical',
                    'code' => 'ALL_RULES_EXPIRED',
                    'message' => 'CRITICAL: All availability rules have expired date ranges. All dates will be unavailable!',
                );
            }
        }
        
        // Issue 4: Booking window is in the past or too far in future
        $now = new \DateTime(\SLN_TimeFunc::date('Y-m-d H:i:s'));
        $bookingFrom = $hb->getFromDate();
        $bookingTo = $hb->getToDate();
        
        if ($bookingTo < $now) {
            $issues[] = array(
                'type' => 'critical',
                'code' => 'BOOKING_WINDOW_PAST',
                'message' => 'CRITICAL: Booking window end date is in the past. No dates can be booked!',
                'details' => array(
                    'booking_to' => $bookingTo->format('Y-m-d H:i:s'),
                    'current_time' => $now->format('Y-m-d H:i:s'),
                ),
            );
        }
        
        // Issue 5: Check hours_before_from setting
        $hoursBeforeFrom = $settings->getHoursBeforeFrom();
        if ($hoursBeforeFrom && strpos($hoursBeforeFrom, '+') === 0) {
            // Large offset pushing booking window into future
            if (strpos($hoursBeforeFrom, 'month') !== false || strpos($hoursBeforeFrom, 'year') !== false) {
                $issues[] = array(
                    'type' => 'warning',
                    'code' => 'LARGE_HOURS_BEFORE_FROM',
                    'message' => 'Warning: hours_before_from setting pushes booking start far into the future.',
                    'details' => array('setting' => $hoursBeforeFrom),
                );
            }
        }
        
        if (empty($issues)) {
            $issues[] = array(
                'type' => 'info',
                'code' => 'NO_ISSUES_DETECTED',
                'message' => 'No obvious configuration issues detected. Issue may be more subtle.',
            );
        }
        
        return $issues;
    }

    private function getBookings($from, $to)
    {
        return SLN_Plugin::getInstance()
            ->getRepository(SLN_Plugin::POST_TYPE_BOOKING)
            ->get($this->getCriteria($from, $to));
    }

    private function getCriteria($from, $to)
    {
        $criteria = array();
        if ($from->format('Y-m-d') == $to->format('Y-m-d')) {
            $criteria['day'] = $from;
        } else {
            $criteria['day@min'] = $from;
            $criteria['day@max'] = $to;
        }

        return $criteria;
    }


}