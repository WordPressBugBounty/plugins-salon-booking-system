<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

use Salon\Util\Date;

/**
 * Computes the weekly class schedule shown by the [salon_booking_weekly_schedule]
 * shortcode, the Elementor widget and the REST endpoint.
 *
 * Lessons are derived from the availability rules saved on each service: every
 * time range of a rule that is active on a day produces lessons back to back,
 * starting at the beginning of the range, for as long as the service duration
 * fits. Services without their own rules are skipped, because they would
 * otherwise produce a slot every booking interval.
 *
 * Whether a lesson is bookable, full or hidden is always decided by the core
 * availability engine (SLN_Helper_Availability), so holidays, daily locks,
 * parallel limits, busy assistants and resources behave exactly like the
 * booking form. The output never contains customer data, only counts.
 */
class SLN_WeeklySchedule_Builder
{
    const STATUS_AVAILABLE = 'available';
    const STATUS_FULL      = 'full';
    const STATUS_PAST      = 'past';
    const STATUS_CLOSED    = 'closed';

    /** @var SLN_Plugin */
    private $plugin;

    /** @var array */
    private $args;

    /** @var DateTimeInterface|null */
    private $now;

    /**
     * @param SLN_Plugin $plugin
     * @param array      $args Normalized args, see SLN_WeeklySchedule_Args::normalize().
     */
    public function __construct(SLN_Plugin $plugin, array $args)
    {
        $this->plugin = $plugin;
        $this->args   = $args;
    }

    /**
     * Override the current time (tests).
     */
    public function setNow(DateTimeInterface $now)
    {
        $this->now = $now;

        return $this;
    }

    private function getNow()
    {
        return $this->now ? $this->now : new SLN_DateTime('now');
    }

    /**
     * @return array{week_start:string,week_end:string,prev:string|null,next:string|null,days:array}
     */
    public function build()
    {
        $weekStart = new SLN_DateTime($this->args['week'] . ' 00:00');
        $today     = $this->getNow()->format('Y-m-d');
        $services  = $this->getServices();

        $days = array();
        for ($i = 0; $i < 7; $i++) {
            $day = clone $weekStart;
            $day->modify('+' . $i . ' days');
            // ISO-8601 weekday: 1 = Monday ... 7 = Sunday.
            if (!in_array((int) $day->format('N'), $this->args['days'], true)) {
                continue;
            }
            $days[] = array(
                'date'     => $day->format('Y-m-d'),
                'weekday'  => (int) $day->format('N'),
                'is_today' => $day->format('Y-m-d') === $today,
                'events'   => $this->buildDayEvents($day, $services),
            );
        }

        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');

        return array(
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end'   => $weekEnd->format('Y-m-d'),
            'prev'       => $this->getAdjacentWeek($weekStart, -1),
            'next'       => $this->getAdjacentWeek($weekStart, 1),
            'days'       => $days,
        );
    }

    /**
     * Previous/next week start, or null when it falls outside the navigable
     * range (from the current week up to weeks_ahead weeks later).
     */
    private function getAdjacentWeek(DateTime $weekStart, $direction)
    {
        $current = self::getWeekStart($this->getNow(), $this->args['start_of_week']);
        $target  = clone $weekStart;
        $target->modify(($direction > 0 ? '+' : '-') . '7 days');
        $last = new SLN_DateTime($current . ' 00:00');
        $last->modify('+' . (7 * (int) $this->args['weeks_ahead']) . ' days');

        if ($target->format('Y-m-d') < $current || $target->format('Y-m-d') > $last->format('Y-m-d')) {
            return null;
        }

        return $target->format('Y-m-d');
    }

    /**
     * @return SLN_Wrapper_Service[]
     */
    private function getServices()
    {
        /** @var SLN_Repository_ServiceRepository $repo */
        $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
        $ret  = array();
        foreach ($repo->getAll() as $service) {
            // Secondary services are add-ons of a booking, never a class of their own.
            if ($service->isHideOnFrontend() || $service->isSecondary()) {
                continue;
            }
            if ($this->args['services'] && !in_array((int) $service->getId(), $this->args['services'], true)) {
                continue;
            }
            if ($this->args['categories']) {
                $terms = wp_get_post_terms($service->getId(), SLN_Plugin::TAXONOMY_SERVICE_CATEGORY, array('fields' => 'ids'));
                if (is_wp_error($terms) || !array_intersect(array_map('intval', $terms), $this->args['categories'])) {
                    continue;
                }
            }
            if (!self::hasOwnRules($service->getMeta('availabilities'))) {
                continue;
            }
            $ret[] = $service;
        }

        return apply_filters('sln_weekly_schedule_services', $ret, $this->args);
    }

    /**
     * @param DateTime              $day
     * @param SLN_Wrapper_Service[] $services
     *
     * @return array
     */
    private function buildDayEvents(DateTime $day, array $services)
    {
        $events = array();
        $date   = new Date($day->format('Y-m-d'));

        foreach ($services as $service) {
            $duration = SLN_Func::getMinutesFromDuration($service->getTotalDuration());
            if ($duration <= 0) {
                continue;
            }
            foreach (self::getRangesForDate($service->getAvailabilityItems(), $date, $service) as $range) {
                foreach (self::getStartTimes($range['from'], $range['to'], $duration) as $time) {
                    foreach ($this->buildEvents($service, $day->format('Y-m-d'), $time) as $event) {
                        $events[] = $event;
                    }
                }
            }
        }

        usort($events, function ($a, $b) {
            return strcmp($a['time'], $b['time']) ?: strcmp($a['service_name'], $b['service_name']);
        });

        return apply_filters('sln_weekly_schedule_day_events', $events, $day->format('Y-m-d'), $this->args);
    }

    /**
     * One event per assistant who teaches the lesson (or a single event when the
     * service does not use assistants).
     *
     * @return array
     */
    private function buildEvents(SLN_Wrapper_Service $service, $ymd, $time)
    {
        $ah       = $this->plugin->getAvailabilityHelper();
        $dt       = new SLN_DateTime($ymd . ' ' . $time);
        $duration = $service->getTotalDuration();

        // Holidays, daily locks and salon closing hours hide the lesson entirely.
        if (!$ah->getHolidaysItems()->isValidDatetimeDuration($dt, $duration)
            || !$ah->getItemsWithoutServiceOffset()->isValidDatetimeDuration($dt, $duration)) {
            return array();
        }

        $base = $this->getBaseEvent($service, $ymd, $time, $dt);

        $assistants = $this->getAssistants($service, $dt);
        if ($assistants === false) {
            // The service needs assistants but none works at this time.
            return array();
        }

        $status = $this->getTimeStatus($dt);
        if (!$status) {
            $ah->setDate($dt);
            $base['booked'] = $this->countBooked($service, $dt);
            $base['spots']  = self::computeSpots($base['capacity'], $base['booked']);
        }

        if (!$assistants) {
            $base['status'] = $status ?: ($ah->validateService($service, $dt) ? self::STATUS_FULL : self::STATUS_AVAILABLE);
            if ($base['status'] === self::STATUS_AVAILABLE && $base['spots'] === 0) {
                $base['status'] = self::STATUS_FULL;
            }

            return array($base);
        }

        $serviceError = $status ? null : $ah->validateService($service, $dt);
        $ret          = array();
        foreach ($assistants as $assistant) {
            $event                   = $base;
            $event['assistant_id']   = $assistant->getId();
            $event['assistant_name'] = $assistant->getName();
            if ($status) {
                $event['status'] = $status;
            } elseif ($base['spots'] === 0) {
                $event['status'] = self::STATUS_FULL;
            } elseif ($serviceError) {
                // Includes "all assistants busy": then every assistant is busy too.
                $event['status'] = self::STATUS_FULL;
            } else {
                $busy            = $ah->validateAttendant($assistant, $dt, $duration, $service);
                $event['status'] = $busy ? self::STATUS_FULL : self::STATUS_AVAILABLE;
            }
            $ret[] = $event;
        }

        return $ret;
    }

    private function getBaseEvent(SLN_Wrapper_Service $service, $ymd, $time, DateTime $dt)
    {
        $category = $service->getServiceCategory();
        $minutes  = SLN_Func::getMinutesFromDuration($service->getDuration());
        $end      = clone $dt;
        $end->modify('+' . $minutes . ' minutes');
        $capacity = $service->getUnitPerHour();

        return array(
            'date'             => $ymd,
            'time'             => $time,
            'end_time'         => $end->format('H:i'),
            'time_label'       => $this->plugin->format()->time($dt),
            'service_id'       => $service->getId(),
            'service_name'     => $service->getName(),
            'category_id'      => $category ? $category->getId() : 0,
            'category_name'    => $category ? $category->getName() : '',
            'category_slug'    => $category ? $this->getCategorySlug($category->getId()) : '',
            'category_color'   => $category ? SLN_WeeklySchedule_CategoryColor::get($category->getId()) : '',
            'assistant_id'     => 0,
            'assistant_name'   => '',
            'duration_minutes' => $minutes,
            'duration_label'   => self::formatDuration($minutes),
            'capacity'         => $capacity > 0 ? $capacity : null,
            'booked'           => null,
            'spots'            => null,
            'status'           => self::STATUS_AVAILABLE,
        );
    }

    private function getCategorySlug($termId)
    {
        $term = get_term($termId, SLN_Plugin::TAXONOMY_SERVICE_CATEGORY);

        return $term && !is_wp_error($term) ? $term->slug : '';
    }

    /**
     * Past lessons and lessons outside the booking window (hours before / max
     * days ahead) can't be booked, whatever the occupancy.
     *
     * @return string|null
     */
    private function getTimeStatus(DateTime $dt)
    {
        if ($dt <= $this->getNow()) {
            return self::STATUS_PAST;
        }
        $hb = $this->plugin->getAvailabilityHelper()->getHoursBeforeHelper();
        if (!$hb->isValidFrom($dt) || !$hb->isValidTo($dt)) {
            return self::STATUS_CLOSED;
        }

        return null;
    }

    /**
     * Bookings of this service occupying the lesson start, counted on the same
     * timeslot the availability engine uses for the "service full" check (the
     * first booking interval inside the lesson).
     */
    private function countBooked(SLN_Wrapper_Service $service, DateTime $dt)
    {
        $ah  = $this->plugin->getAvailabilityHelper();
        $end = clone $dt;
        $end->modify('+' . SLN_Func::getMinutesFromDuration($service->getTotalDuration()) . ' minutes');
        $times = SLN_Func::filterTimes($ah->getMinutesIntervals(), $dt, $end);
        if (!$times) {
            return 0;
        }
        $counts = $ah->getDayBookings()->countServicesByHour($times[0]->format('H'), $times[0]->format('i'));

        return is_array($counts) && isset($counts[$service->getId()]) ? (int) $counts[$service->getId()] : 0;
    }

    /**
     * Assistants shown for the lesson: the service's assistants that are visible
     * on the frontend, match the assistants filter and work at that time.
     *
     * Only assistants assigned to the service by name get a row of their own.
     * Assistants without services ("all services" in SBS) can take the lesson
     * but are not its teacher: listing them would repeat every lesson once per
     * staff member, so the lesson is then listed once and the booking form
     * assigns the assistant as usual.
     *
     * @return SLN_Wrapper_Attendant[]|false Empty array for a single row without
     *                                       assistant, false when the service needs
     *                                       assistants but nobody works then.
     */
    private function getAssistants(SLN_Wrapper_Service $service, SLN_DateTime $dt)
    {
        if (!$this->plugin->getSettings()->isAttendantsEnabled() || !$service->isAttendantsEnabled()) {
            return $this->args['assistants'] ? false : array();
        }

        $ret = array();
        foreach ($service->getAttendants() as $assistant) {
            if ($assistant->isHideOnFrontend()) {
                continue;
            }
            if ($this->args['assistants'] && !in_array((int) $assistant->getId(), $this->args['assistants'], true)) {
                continue;
            }
            if ($assistant->isNotAvailableOnDateDuration($dt, $service->getTotalDuration(), $service)) {
                continue;
            }
            $ret[] = $assistant;
        }

        if (!$ret) {
            return false;
        }

        // A service taught by several assistants together is a single lesson:
        // list it once without preselecting anyone.
        if ($service->isMultipleAttendantsForServiceEnabled()) {
            return array();
        }

        // An explicit assistants filter names the teachers to show.
        if ($this->args['assistants']) {
            return $ret;
        }

        return array_values(array_filter($ret, function (SLN_Wrapper_Attendant $assistant) {
            return !$assistant->hasAllServices();
        }));
    }

    /**
     * True when the service has its own availability rules.
     *
     * @param mixed $availabilities The raw "availabilities" service meta.
     */
    public static function hasOwnRules($availabilities)
    {
        return !empty($availabilities) && is_array($availabilities);
    }

    /**
     * Time ranges of the rules active on a date.
     *
     * @return array<int,array{from:string,to:string}>
     */
    public static function getRangesForDate(SLN_Helper_AvailabilityItems $items, Date $date, ?SLN_Wrapper_ServiceInterface $service = null)
    {
        $ret = array();
        foreach ($items->getDateSubset($date) as $item) {
            if ($item instanceof SLN_Helper_AvailabilityItemNull || !$item->isValidDate($date, $service)) {
                continue;
            }
            foreach ($item->getTimes() as $interval) {
                $ret[] = array(
                    'from' => $interval->getFrom()->toString(),
                    'to'   => $interval->getTo()->toString(),
                );
            }
        }

        return $ret;
    }

    /**
     * Lesson start times in a range, back to back, while the lesson fits.
     *
     * @param string $from     H:i
     * @param string $to       H:i ("24:00" and "00:00" both mean end of day)
     * @param int    $duration Minutes.
     *
     * @return string[] H:i
     */
    public static function getStartTimes($from, $to, $duration)
    {
        $duration = (int) $duration;
        $start    = self::toMinutes($from);
        $end      = self::toMinutes($to);
        if ($end === 0 || $end <= $start) {
            $end = 24 * 60;
        }

        $ret = array();
        for ($t = $start; $duration > 0 && $t + $duration <= $end; $t += $duration) {
            $ret[] = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
        }

        return $ret;
    }

    /**
     * Remaining spots: null when the service has no capacity limit.
     *
     * @param int|null $capacity
     * @param int|null $booked
     *
     * @return int|null
     */
    public static function computeSpots($capacity, $booked)
    {
        if (empty($capacity)) {
            return null;
        }

        return max(0, (int) $capacity - (int) $booked);
    }

    /**
     * First day (Y-m-d) of the week containing $date.
     *
     * @param DateTimeInterface $date
     * @param int               $startOfWeek WordPress "start_of_week": 0 = Sunday ... 6 = Saturday.
     */
    public static function getWeekStart(DateTimeInterface $date, $startOfWeek)
    {
        $diff = ((int) $date->format('w') - (int) $startOfWeek + 7) % 7;
        $ret  = new DateTime($date->format('Y-m-d'), $date->getTimezone());
        $ret->modify('-' . $diff . ' days');

        return $ret->format('Y-m-d');
    }

    public static function formatDuration($minutes)
    {
        $hours   = intdiv((int) $minutes, 60);
        $minutes = (int) $minutes % 60;
        if (!$hours) {
            /* translators: %d: number of minutes */
            return sprintf(__('%d min', 'salon-booking-system'), $minutes);
        }
        if (!$minutes) {
            /* translators: %d: number of hours */
            return sprintf(__('%d h', 'salon-booking-system'), $hours);
        }

        /* translators: 1: number of hours, 2: number of minutes */
        return sprintf(__('%1$d h %2$d min', 'salon-booking-system'), $hours, $minutes);
    }

    private static function toMinutes($time)
    {
        $parts = explode(':', (string) $time);

        return (int) $parts[0] * 60 + (isset($parts[1]) ? (int) $parts[1] : 0);
    }
}
