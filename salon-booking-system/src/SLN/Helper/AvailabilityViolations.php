<?php

use Salon\Util\Date;
use Salon\Util\Time;

/**
 * Lists the availability rules a staff booking breaks, in words the salon staff
 * can act on ("Laura works 09:00-13:00 on Tuesday", not "assistant unavailable").
 *
 * The back-end booking editor and the PWA both run this check before saving.
 * When it returns violations, the booking is saved only after the user confirms
 * them in a modal ("Save anyway"). The confirmation carries the signature() of the
 * list the user saw, so a booking is never forced over rules the user didn't see.
 *
 * Each violation is an array with:
 *  - code    one of the CODE_* constants
 *  - title   short label for the rule
 *  - message what exactly is broken, with names, dates and times
 */
class SLN_Helper_AvailabilityViolations
{
    const CODE_SALON_CLOSED_DAY   = 'salon_closed_day';
    const CODE_SALON_CLOSED       = 'salon_closed';
    const CODE_NOT_ENOUGH_TIME    = 'not_enough_time';
    const CODE_SALON_HOLIDAY      = 'salon_holiday';
    const CODE_MANUAL_LOCK        = 'manual_lock';
    const CODE_GCAL_EVENT         = 'gcal_event';
    const CODE_ASSISTANT_SERVICE  = 'assistant_service';
    const CODE_ASSISTANT_OFF      = 'assistant_off';
    const CODE_ASSISTANT_HOLIDAY  = 'assistant_holiday';
    const CODE_ASSISTANT_BUSY     = 'assistant_busy';
    const CODE_SERVICE_UNAVAILABLE = 'service_unavailable';
    const CODE_SERVICE_FULL       = 'service_full';
    const CODE_RESOURCE_BUSY      = 'resource_busy';
    const CODE_DAY_LIMIT          = 'day_limit';
    const CODE_SLOT_LIMIT         = 'slot_limit';

    /** Booking meta holding the history of saves forced over availability rules. */
    const META_OVERRIDES = '_sln_booking_availability_overrides';
    /** Booking meta set while the saved booking breaks availability rules. */
    const META_FORCED    = '_sln_booking_availability_forced';

    /** @var SLN_Plugin */
    private $plugin;
    /** @var SLN_Settings */
    private $settings;
    /** @var int */
    private $interval;
    /** @var array */
    private $violations = array();

    public function __construct(?SLN_Plugin $plugin = null)
    {
        $this->plugin   = $plugin ? $plugin : SLN_Plugin::getInstance();
        $this->settings = $this->plugin->getSettings();
        $this->interval = max(1, (int) $this->settings->getInterval());
    }

    /**
     * @param SLN_Wrapper_Booking_Services $bookingServices The booking as it would be saved.
     * @param SLN_Wrapper_Booking|null     $currentBooking  The booking being edited, left out of the other bookings.
     *
     * @return array[]
     */
    public function check(SLN_Wrapper_Booking_Services $bookingServices, ?SLN_Wrapper_Booking $currentBooking = null)
    {
        $this->violations = array();

        $items = $bookingServices->getItems();
        if (empty($items)) {
            return array();
        }

        $lines = array();
        foreach ($items as $bookingService) {
            if (!($bookingService->getService() instanceof SLN_Wrapper_ServiceInterface)) {
                continue;
            }
            $lines[] = array(
                'bs'         => $bookingService,
                'minutes'    => $this->getWorkMinutes($bookingService),
                'attendants' => $this->getAttendants($bookingService),
            );
        }
        if (empty($lines)) {
            return array();
        }

        $start = clone $lines[0]['bs']->getStartsAt();
        $end   = clone $lines[0]['bs']->getEndsAt();
        $allMinutes = array();
        foreach ($lines as $line) {
            if ($line['bs']->getStartsAt() < $start) {
                $start = clone $line['bs']->getStartsAt();
            }
            if ($line['bs']->getEndsAt() > $end) {
                $end = clone $line['bs']->getEndsAt();
            }
            foreach ($line['minutes'] as $minute) {
                $allMinutes[$minute->format('Y-m-d H:i')] = $minute;
            }
        }
        ksort($allMinutes);
        $allMinutes = array_values($allMinutes);

        // A fresh helper: the shared one may hold a day built without $currentBooking left out.
        $ah = new SLN_Helper_Availability($this->plugin);
        $ah->setDate($start, $currentBooking);
        $dayBookings = $ah->getDayBookings();

        $this->checkOpeningHours($start, $end, $allMinutes);
        $this->checkSalonHolidays($allMinutes);
        $this->checkDailyLocks($lines, $start);
        $this->checkBookingLimits($dayBookings, $start, $allMinutes);

        $otherBookings = $dayBookings->getBookings();
        foreach ($lines as $line) {
            $service = $line['bs']->getService();
            foreach ($line['attendants'] as $attendant) {
                $this->checkAttendant($attendant, $service, $line, $start, $otherBookings);
            }
            $this->checkService($service, $line, $dayBookings);
            $this->checkResource($line, $dayBookings);
        }

        return array_values($this->violations);
    }

    /**
     * Same list, same signature: the confirmation sent with "Save anyway".
     *
     * @param array[] $violations
     *
     * @return string
     */
    public static function signature(array $violations)
    {
        $parts = array();
        foreach ($violations as $violation) {
            $parts[] = $violation['code'].'|'.$violation['message'];
        }

        return md5(implode("\n", $parts));
    }

    /**
     * Whether the user confirmed exactly these violations.
     *
     * @param array[] $violations
     * @param string  $token Signature sent by the client.
     *
     * @return bool
     */
    public static function isConfirmed(array $violations, $token)
    {
        if (empty($violations)) {
            return true;
        }

        return is_string($token) && $token !== '' && hash_equals(self::signature($violations), $token);
    }

    /**
     * Administrators, salon staff and Multi-Shops shop managers can save a booking
     * over availability rules.
     *
     * @return bool
     */
    public static function currentUserCanForce()
    {
        $user = wp_get_current_user();
        $can  = $user && $user->ID && (
            current_user_can('manage_options')
            || in_array('administrator', (array) $user->roles, true)
            || in_array(SLN_Plugin::USER_ROLE_STAFF, (array) $user->roles, true)
            || in_array('sln_shop_manager', (array) $user->roles, true) // Multi-Shops add-on role.
        );

        return (bool) apply_filters('sln.availability_override.can_force', $can, $user);
    }

    /**
     * Store the forced rules in the booking history and flag the booking, or clear
     * the flag when the booking no longer breaks any rule.
     *
     * @param int     $bookingId
     * @param array[] $violations
     */
    public static function recordOverride($bookingId, array $violations)
    {
        $bookingId = (int) $bookingId;
        if (!$bookingId) {
            return;
        }
        if (empty($violations)) {
            delete_post_meta($bookingId, '_sln_booking_availability_forced');

            return;
        }

        $user    = wp_get_current_user();
        $history = get_post_meta($bookingId, '_sln_booking_availability_overrides', true);
        $history = is_array($history) ? $history : array();
        $history[] = array(
            'time'       => current_time('mysql'),
            'user_id'    => $user ? (int) $user->ID : 0,
            'user_name'  => $user && $user->ID ? $user->display_name : '',
            'violations' => array_map(function ($violation) {
                return array(
                    'code'    => $violation['code'],
                    'title'   => $violation['title'],
                    'message' => $violation['message'],
                );
            }, $violations),
        );
        update_post_meta($bookingId, '_sln_booking_availability_overrides', $history);
        update_post_meta($bookingId, '_sln_booking_availability_forced', 1);

        SLN_Plugin::addLog(sprintf(
            '[AvailabilityOverride] Booking #%d saved over %d availability rule(s) by user #%d: %s',
            $bookingId,
            count($violations),
            $user ? (int) $user->ID : 0,
            implode(' | ', wp_list_pluck($violations, 'message'))
        ));
    }

    /**
     * Rules the booking broke when it was last forced, or an empty array.
     *
     * @param int $bookingId
     *
     * @return array[]
     */
    public static function getForcedViolations($bookingId)
    {
        if (!get_post_meta($bookingId, '_sln_booking_availability_forced', true)) {
            return array();
        }
        $history = get_post_meta($bookingId, '_sln_booking_availability_overrides', true);
        if (!is_array($history) || empty($history)) {
            return array();
        }
        $last = end($history);

        return isset($last['violations']) && is_array($last['violations']) ? $last['violations'] : array();
    }

    /**
     * @param int $bookingId
     *
     * @return array[]
     */
    public static function getOverrideHistory($bookingId)
    {
        $history = get_post_meta($bookingId, '_sln_booking_availability_overrides', true);

        return is_array($history) ? $history : array();
    }

    // ---------------------------------------------------------------------
    // Salon rules
    // ---------------------------------------------------------------------

    private function checkOpeningHours(DateTime $start, DateTime $end, array $minutes)
    {
        $avItems   = $this->settings->getNewAvailabilityItems();
        $day       = Date::create($start);
        $intervals = $this->getDayIntervals($avItems, $day);

        if (empty($intervals)) {
            $this->add(
                self::CODE_SALON_CLOSED_DAY,
                __('Salon closed', 'salon-booking-system'),
                sprintf(
                    // translators: %1$s weekday, %2$s date
                    __('The salon is closed on %1$s %2$s.', 'salon-booking-system'),
                    $this->weekday($start),
                    $this->date($start)
                )
            );

            return;
        }

        $closed = array();
        foreach ($minutes as $minute) {
            if (!$avItems->isValidDatetime($minute)) {
                $closed[] = $minute;
            }
        }
        if (empty($closed)) {
            return;
        }

        $closing = $this->closingTimeFor($intervals, $start);
        if ($avItems->isValidDatetime($start) && $closing) {
            $closingAt = clone $start;
            $closingAt->setTime((int) substr($closing, 0, 2), (int) substr($closing, 3, 2));
            $over = (int) round(($end->getTimestamp() - $closingAt->getTimestamp()) / 60);
            $this->add(
                self::CODE_NOT_ENOUGH_TIME,
                __('Not enough time', 'salon-booking-system'),
                sprintf(
                    // translators: %1$s total duration, %2$s booking end time, %3$s minutes after closing, %4$s closing time
                    __('The services take %1$s, so the booking ends at %2$s, %3$s after closing time (%4$s).', 'salon-booking-system'),
                    $this->duration((int) round(($end->getTimestamp() - $start->getTimestamp()) / 60)),
                    $this->time($end),
                    $this->duration(max(1, $over)),
                    $this->time($closingAt)
                )
            );

            return;
        }

        $this->add(
            self::CODE_SALON_CLOSED,
            __('Salon closed', 'salon-booking-system'),
            sprintf(
                // translators: %1$s time, %2$s weekday, %3$s opening hours list
                __('The salon is closed at %1$s on %2$s. Opening hours that day: %3$s.', 'salon-booking-system'),
                $this->time($closed[0]),
                $this->weekday($start),
                $this->intervalsToString($intervals)
            )
        );
    }

    private function checkSalonHolidays(array $minutes)
    {
        $rules = $this->settings->get('holidays') ?: array();
        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['from_date']) || empty($rule['to_date'])) {
                continue;
            }
            if (!$this->ruleCoversAnyMinute($rule, $minutes)) {
                continue;
            }
            $this->add(
                self::CODE_SALON_HOLIDAY,
                __('Salon holiday', 'salon-booking-system'),
                sprintf(
                    // translators: %s holiday period
                    __('The salon is closed for a holiday rule: %s.', 'salon-booking-system'),
                    $this->rulePeriod($rule)
                )
            );
        }
    }

    /**
     * Locks set from the day calendar (salon or assistant) and Google Calendar events.
     */
    private function checkDailyLocks(array $lines, DateTime $start)
    {
        $dayStart = clone $start;
        $dayStart->setTime(0, 0);

        $salonRules = $this->settings->get('holidays_daily') ?: array();
        $salonRules = apply_filters('sln.calendar.holidays_daily_rules', $salonRules, $dayStart, $dayStart);

        foreach ($lines as $line) {
            $lineAttendantIds = array();
            foreach ($line['attendants'] as $attendant) {
                $lineAttendantIds[] = (int) $attendant->getId();
            }

            $rules = array();
            foreach ((array) $salonRules as $rule) {
                $rules[] = array($rule, null);
            }
            foreach ($line['attendants'] as $attendant) {
                foreach ((array) ($attendant->getMeta('holidays_daily') ?: array()) as $rule) {
                    $rules[] = array($rule, $attendant);
                }
            }

            foreach ($rules as $pair) {
                list($rule, $owner) = $pair;
                if (!is_array($rule) || empty($rule['from_date']) || empty($rule['to_date'])) {
                    continue;
                }
                if (!$owner && !empty($rule['assistant_id'])) {
                    if (!in_array((int) $rule['assistant_id'], $lineAttendantIds, true)) {
                        continue;
                    }
                    $owner = $this->plugin->createAttendant((int) $rule['assistant_id']);
                }
                if (!$this->ruleCoversAnyMinute($rule, $line['minutes'])) {
                    continue;
                }

                $period = $this->ruleTimeRange($rule);
                if (!empty($rule['gcal_locked'])) {
                    $this->add(
                        self::CODE_GCAL_EVENT,
                        __('Google Calendar event', 'salon-booking-system'),
                        sprintf(
                            // translators: %s time range
                            __('A Google Calendar event blocks %s.', 'salon-booking-system'),
                            $period
                        )
                    );
                } elseif ($owner) {
                    $this->add(
                        self::CODE_MANUAL_LOCK,
                        __('Locked time', 'salon-booking-system'),
                        sprintf(
                            // translators: %1$s time range, %2$s assistant name
                            __('%1$s is locked in %2$s\'s calendar.', 'salon-booking-system'),
                            $period,
                            $owner->getName()
                        )
                    );
                } else {
                    $this->add(
                        self::CODE_MANUAL_LOCK,
                        __('Locked time', 'salon-booking-system'),
                        sprintf(
                            // translators: %s time range
                            __('%s is locked in the salon calendar.', 'salon-booking-system'),
                            $period
                        )
                    );
                }
            }
        }
    }

    private function checkBookingLimits(SLN_Helper_Availability_AbstractDayBookings $dayBookings, DateTime $start, array $minutes)
    {
        $perDay = (int) $this->settings->get('parallels_day');
        if ($perDay && $dayBookings->countBookingsByDay() >= $perDay) {
            $this->add(
                self::CODE_DAY_LIMIT,
                __('Daily booking limit', 'salon-booking-system'),
                sprintf(
                    // translators: %1$d max bookings per day, %2$s date
                    __('The limit of %1$d bookings per day is already reached on %2$s.', 'salon-booking-system'),
                    $perDay,
                    $this->date($start)
                )
            );
        }

        $perSlot = (int) $this->settings->get('parallels_hour');
        if (!$perSlot) {
            return;
        }
        foreach ($minutes as $minute) {
            $slot = $dayBookings->getTime($minute->format('H'), $minute->format('i'));
            if ($dayBookings->isBreakSlot($slot)) {
                continue;
            }
            $count = $dayBookings->countBookingsByHour($minute->format('H'), $minute->format('i'));
            if ($count >= $perSlot) {
                $this->add(
                    self::CODE_SLOT_LIMIT,
                    __('Too many bookings at the same time', 'salon-booking-system'),
                    sprintf(
                        // translators: %1$d max parallel bookings, %2$d bookings already there, %3$s time
                        __('Only %1$d bookings at the same time are allowed and %2$d are already booked at %3$s.', 'salon-booking-system'),
                        $perSlot,
                        $count,
                        $this->time($minute)
                    )
                );

                return;
            }
        }
    }

    // ---------------------------------------------------------------------
    // Assistant, service and resource rules
    // ---------------------------------------------------------------------

    private function checkAttendant(SLN_Wrapper_AttendantInterface $attendant, SLN_Wrapper_ServiceInterface $service, array $line, DateTime $start, array $otherBookings)
    {
        $name = $attendant->getName();

        if (!$attendant->hasAllServices() && !$attendant->hasService($service)) {
            $this->add(
                self::CODE_ASSISTANT_SERVICE,
                __('Assistant can\'t do this service', 'salon-booking-system'),
                sprintf(
                    // translators: %1$s service name, %2$s assistant name
                    __('%1$s isn\'t one of %2$s\'s services.', 'salon-booking-system'),
                    $service->getName(),
                    $name
                )
            );
        }

        $minutes = $line['minutes'];
        if ($minutes) {
            $lineStart = $line['bs']->getStartsAt();
            $avItems   = $attendant->getAvailabilityItems();
            $intervals = $this->getDayIntervals($avItems, Date::create($lineStart), $service);
            if (empty($intervals)) {
                $this->add(
                    self::CODE_ASSISTANT_OFF,
                    __('Assistant not working', 'salon-booking-system'),
                    sprintf(
                        // translators: %1$s assistant name, %2$s weekday, %3$s date
                        __('%1$s doesn\'t work on %2$s %3$s.', 'salon-booking-system'),
                        $name,
                        $this->weekday($lineStart),
                        $this->date($lineStart)
                    )
                );
            } else {
                foreach ($minutes as $minute) {
                    if (!$avItems->isValidDatetime($minute, $service)) {
                        $this->add(
                            self::CODE_ASSISTANT_OFF,
                            __('Assistant not working', 'salon-booking-system'),
                            sprintf(
                                // translators: %1$s assistant name, %2$s working hours, %3$s weekday
                                __('%1$s works %2$s on %3$s.', 'salon-booking-system'),
                                $name,
                                $this->intervalsToString($intervals),
                                $this->weekday($lineStart)
                            )
                        );
                        break;
                    }
                }
            }

            foreach ((array) ($attendant->getMeta('holidays') ?: array()) as $rule) {
                if (!is_array($rule) || empty($rule['from_date']) || empty($rule['to_date'])) {
                    continue;
                }
                if ($this->ruleCoversAnyMinute($rule, $minutes)) {
                    $this->add(
                        self::CODE_ASSISTANT_HOLIDAY,
                        __('Assistant on holiday', 'salon-booking-system'),
                        sprintf(
                            // translators: %1$s assistant name, %2$s holiday period
                            __('%1$s is on holiday: %2$s.', 'salon-booking-system'),
                            $name,
                            $this->rulePeriod($rule)
                        )
                    );
                }
            }
        }

        if ($attendant->canMultipleCustomers()) {
            return;
        }
        foreach ($otherBookings as $other) {
            if (!($other instanceof SLN_Wrapper_Booking)) {
                continue;
            }
            foreach ($other->getBookingServices()->getItems() as $otherService) {
                $otherIds = array();
                foreach ($this->getAttendants($otherService) as $otherAttendant) {
                    $otherIds[] = (int) $otherAttendant->getId();
                }
                if (!in_array((int) $attendant->getId(), $otherIds, true)) {
                    continue;
                }
                if (!$this->minutesOverlap($minutes, $this->getWorkMinutes($otherService))) {
                    continue;
                }
                $otherServiceObj = $otherService->getService();
                $this->add(
                    self::CODE_ASSISTANT_BUSY,
                    __('Assistant already booked', 'salon-booking-system'),
                    sprintf(
                        // translators: %1$s assistant name, %2$s booking id, %3$s customer name, %4$s service name, %5$s start time, %6$s end time
                        __('%1$s already has booking #%2$s (%3$s, %4$s) from %5$s to %6$s.', 'salon-booking-system'),
                        $name,
                        $other->getId(),
                        $other->getDisplayName(),
                        $otherServiceObj instanceof SLN_Wrapper_ServiceInterface ? $otherServiceObj->getName() : '',
                        $this->time($otherService->getStartsAt()),
                        $this->time($otherService->getEndsAt())
                    )
                );
            }
        }
    }

    private function checkService(SLN_Wrapper_ServiceInterface $service, array $line, SLN_Helper_Availability_AbstractDayBookings $dayBookings)
    {
        $lineStart = $line['bs']->getStartsAt();
        if ($service->isNotAvailableOnDate(new SLN_DateTime($lineStart->format('Y-m-d H:i:s')))) {
            $availability = trim(wp_strip_all_tags(str_replace(array('<br/>', '<br>'), '; ', $service->getNotAvailableString())));
            $this->add(
                self::CODE_SERVICE_UNAVAILABLE,
                __('Service not available', 'salon-booking-system'),
                $availability
                    ? sprintf(
                        // translators: %1$s service name, %2$s weekday, %3$s time, %4$s service availability
                        __('%1$s isn\'t available on %2$s at %3$s. Its availability: %4$s.', 'salon-booking-system'),
                        $service->getName(),
                        $this->weekday($lineStart),
                        $this->time($lineStart),
                        $availability
                    )
                    : sprintf(
                        // translators: %1$s service name, %2$s weekday, %3$s time
                        __('%1$s isn\'t available on %2$s at %3$s.', 'salon-booking-system'),
                        $service->getName(),
                        $this->weekday($lineStart),
                        $this->time($lineStart)
                    )
            );
        }

        $unit = (int) $service->getUnitPerHour();
        if ($unit <= 0) {
            return;
        }
        foreach ($line['minutes'] as $minute) {
            $ids = $dayBookings->countServicesByHour($minute->format('H'), $minute->format('i'));
            if (isset($ids[$service->getId()]) && $ids[$service->getId()] >= $unit) {
                $this->add(
                    self::CODE_SERVICE_FULL,
                    __('Service fully booked', 'salon-booking-system'),
                    sprintf(
                        // translators: %1$s service name, %2$s time, %3$d max bookings at the same time
                        __('%1$s is fully booked at %2$s (maximum %3$d at the same time).', 'salon-booking-system'),
                        $service->getName(),
                        $this->time($minute),
                        $unit
                    )
                );

                return;
            }
        }
    }

    private function checkResource(array $line, SLN_Helper_Availability_AbstractDayBookings $dayBookings)
    {
        $resource = $line['bs']->getResource();
        if (!($resource instanceof SLN_Wrapper_ResourceInterface)) {
            return;
        }
        $unit = max(1, (int) $resource->getUnitPerHour());
        foreach ($line['minutes'] as $minute) {
            $booked = $dayBookings->countResourcesByHour($minute->format('H'), $minute->format('i'));
            if (isset($booked[$resource->getId()]) && $booked[$resource->getId()] >= $unit) {
                $this->add(
                    self::CODE_RESOURCE_BUSY,
                    __('Resource in use', 'salon-booking-system'),
                    sprintf(
                        // translators: %1$s resource name, %2$s time
                        __('%1$s is already in use at %2$s.', 'salon-booking-system'),
                        method_exists($resource, 'getTitle') ? $resource->getTitle() : (string) $resource,
                        $this->time($minute)
                    )
                );

                return;
            }
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function add($code, $title, $message)
    {
        $key = $code.'|'.$message;
        if (!isset($this->violations[$key])) {
            $this->violations[$key] = array(
                'code'    => $code,
                'title'   => $title,
                'message' => $message,
            );
        }
    }

    /**
     * The minutes (one per interval step) the service really occupies: its break left out.
     *
     * @return DateTime[]
     */
    private function getWorkMinutes(SLN_Wrapper_Booking_Service $bookingService)
    {
        $start = $bookingService->getStartsAt();
        $end   = $bookingService->getEndsAt();
        if (!($start instanceof DateTime) || !($end instanceof DateTime)) {
            return array();
        }
        $breakStart = $bookingService->getBreakStartsAt();
        $breakEnd   = $bookingService->getBreakEndsAt();
        $hasBreak   = $breakStart && $breakEnd && $breakStart < $breakEnd;

        $ret     = array();
        $current = new SLN_DateTime($start->format('Y-m-d H:i:00'));
        $guard   = 0;
        while ($current < $end && $guard++ < 2000) {
            if (!$hasBreak || $current < $breakStart || $current >= $breakEnd) {
                $ret[] = clone $current;
            }
            $current->modify('+'.$this->interval.' minutes');
        }

        return $ret;
    }

    /**
     * @return SLN_Wrapper_AttendantInterface[]
     */
    private function getAttendants(SLN_Wrapper_Booking_Service $bookingService)
    {
        $attendants = $bookingService->getAttendant();
        if (empty($attendants)) {
            return array();
        }
        $attendants = is_array($attendants) ? $attendants : array($attendants);

        return array_values(array_filter($attendants, function ($attendant) {
            return $attendant instanceof SLN_Wrapper_AttendantInterface;
        }));
    }

    /**
     * Opening intervals of a rule set on a day, as array('from' => 'H:i', 'to' => 'H:i').
     * An empty array means closed all day.
     */
    private function getDayIntervals(SLN_Helper_AvailabilityItems $items, Date $day, ?SLN_Wrapper_ServiceInterface $service = null)
    {
        $ret = array();
        foreach ($items->getDateSubset($day) as $item) {
            if (!$item->isValidDate($day, $service)) {
                continue;
            }
            foreach ($item->getTimes() as $interval) {
                $ret[] = array(
                    'from' => $interval->getFrom()->toString(),
                    'to'   => $interval->getTo()->toString(),
                );
            }
        }
        usort($ret, function ($a, $b) {
            return strcmp($a['from'], $b['from']);
        });

        $merged = array();
        foreach ($ret as $interval) {
            $last = count($merged) - 1;
            if ($last >= 0 && $interval['from'] <= $merged[$last]['to']) {
                if ($interval['to'] > $merged[$last]['to']) {
                    $merged[$last]['to'] = $interval['to'];
                }
                continue;
            }
            $merged[] = $interval;
        }

        return $merged;
    }

    private function closingTimeFor(array $intervals, DateTime $start)
    {
        $time = $start->format('H:i');
        foreach ($intervals as $interval) {
            if ($time >= $interval['from'] && $time < $interval['to']) {
                return $interval['to'];
            }
        }

        return null;
    }

    private function intervalsToString(array $intervals)
    {
        $parts = array();
        foreach ($intervals as $interval) {
            if ($interval['from'] === '00:00' && in_array($interval['to'], array('24:00', '23:59'), true)) {
                $parts[] = __('all day', 'salon-booking-system');
                continue;
            }
            $parts[] = $this->time(Time::create($interval['from'])).'–'.$this->time(Time::create($interval['to']));
        }

        return implode(', ', $parts);
    }

    /**
     * @return array(SLN_DateTime, SLN_DateTime) Start and end of a holiday/lock rule.
     */
    private function ruleBounds(array $rule)
    {
        $fromTime = !empty($rule['from_time']) ? $rule['from_time'] : '00:00';
        $toTime   = !empty($rule['to_time']) ? $rule['to_time'] : '00:00';
        if ($toTime === '24:00' || ($toTime === '00:00' && $rule['from_date'] === $rule['to_date'])) {
            $toTime = '23:59:59';
        }

        return array(
            new SLN_DateTime($rule['from_date'].' '.$fromTime),
            new SLN_DateTime($rule['to_date'].' '.$toTime),
        );
    }

    private function ruleCoversAnyMinute(array $rule, array $minutes)
    {
        list($from, $to) = $this->ruleBounds($rule);
        foreach ($minutes as $minute) {
            if ($minute >= $from && $minute < $to) {
                return true;
            }
        }

        return false;
    }

    private function ruleTimeRange(array $rule)
    {
        list($from, $to) = $this->ruleBounds($rule);
        if ($from->format('Y-m-d') === $to->format('Y-m-d')) {
            return $this->time($from).'–'.$this->time($to);
        }

        return $this->date($from).' '.$this->time($from).' – '.$this->date($to).' '.$this->time($to);
    }

    private function rulePeriod(array $rule)
    {
        list($from, $to) = $this->ruleBounds($rule);
        $allDay = $from->format('H:i') === '00:00' && $to->format('H:i') >= '23:59';
        if ($from->format('Y-m-d') === $to->format('Y-m-d')) {
            return $allDay
                ? $this->date($from)
                : sprintf('%s %s–%s', $this->date($from), $this->time($from), $this->time($to));
        }
        if ($allDay) {
            return sprintf(
                // translators: %1$s start date, %2$s end date
                __('from %1$s to %2$s', 'salon-booking-system'),
                $this->date($from),
                $this->date($to)
            );
        }

        return sprintf(
            // translators: %1$s start date and time, %2$s end date and time
            __('from %1$s to %2$s', 'salon-booking-system'),
            $this->date($from).' '.$this->time($from),
            $this->date($to).' '.$this->time($to)
        );
    }

    private function minutesOverlap(array $a, array $b)
    {
        if (empty($a) || empty($b)) {
            return false;
        }
        $keys = array();
        foreach ($a as $minute) {
            $keys[$minute->format('Y-m-d H:i')] = true;
        }
        foreach ($b as $minute) {
            if (isset($keys[$minute->format('Y-m-d H:i')])) {
                return true;
            }
        }

        return false;
    }

    private function time($value)
    {
        return $this->plugin->format()->time($value);
    }

    private function date(DateTime $value)
    {
        return $this->plugin->format()->date($value);
    }

    private function weekday(DateTime $value)
    {
        return SLN_TimeFunc::translateDate('l', $value->getTimestamp(), $value->getTimezone());
    }

    private function duration($minutes)
    {
        $minutes = (int) $minutes;
        $hours   = intdiv($minutes, 60);
        $rest    = $minutes % 60;
        if ($hours && $rest) {
            // translators: %1$d hours, %2$d minutes
            return sprintf(__('%1$dh %2$dm', 'salon-booking-system'), $hours, $rest);
        }
        if ($hours) {
            // translators: %d hours
            return sprintf(__('%dh', 'salon-booking-system'), $hours);
        }

        // translators: %d minutes
        return sprintf(_n('%d minute', '%d minutes', $rest, 'salon-booking-system'), $rest);
    }
}
