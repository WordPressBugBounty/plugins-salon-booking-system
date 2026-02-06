<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

use Salon\Util\Date;
use Salon\Util\Time;

class SLN_Helper_Intervals
{
    /** @var  SLN_Helper_Availability */
    protected $availabilityHelper;
    protected $initialDate;
    protected $suggestedDate;

    protected $times;
    protected $years;
    protected $months;
    protected $days;
    protected $dates;
    protected $fullDays  = array();
    protected $workTimes = array();

    public function __construct(SLN_Helper_Availability $availabilityHelper)
    {
        $this->availabilityHelper = $availabilityHelper;
    }

    public function setDatetime(DateTime $date, $duration = null)
    {
        $this->initialDate = $this->bindInitialDate($date);
        $ah                = $this->availabilityHelper;
        $times             = $ah->getCachedTimes(Date::create($date), $duration);
        $interval          = $ah->getHoursBeforeHelper();
        $to                = $interval->getToDate();
        $clone             = clone $date;
        $dateDiff = $to->diff($date);
        if($dateDiff->y > 10){
            $date->modify(($dateDiff->invert ? '+' : '-'). ($dateDiff->y - 10 ). ' years');
        }
        while (empty($times) && $date <= $to) {
            $this->fullDays[] = clone $date;
            $date->modify('+1 days');
            $times = $ah->getCachedTimes( Date::create($date), $duration);
        }
        if (empty($times)) {
            $date = $clone;
            $from = $interval->getFromDate();
            while (empty($times) && $date >= $from) {
                $this->fullDays[] = clone $date;
                $date->modify('-1 days');
                $times = $ah->getCachedTimes(Date::create($date), $duration);
            }
        }
        if ($duration) {
            $originalTimes = $times;
            // Check if any service allows nested bookings (per-service setting)
            // If yes, include break slots; otherwise use strict duration filtering
            $times = $this->filterTimesByDurationWithBreakAllowance($times, $duration);
            SLN_Helper_AvailabilityDebugger::logFilteredTimes($originalTimes, $times, 'Duration + Break Allowance Filter');
        }

        SLN_Helper_AvailabilityDebugger::logAvailableTimes($times, 'After all filtering');
        $this->times   = $times;
        $suggestedTime = $date->format('H:i');
        
        // FIX: SuggestedDate 2026 Bug - Replace buggy loop that advances date
        // If suggested time is not in available times array, pick first available time
        // This prevents date advancement bug that occurred after 86400 iterations
        if(!isset($times[$suggestedTime])){
            // Safety check: if no times available, keep current time
            // CheckDateAlt will handle moving to next available day
            if (!empty($times)) {
                // Pick the first available time from the times array
                // This ensures we only use validated, available times
                reset($times);
                $firstAvailableTime = key($times);
                
                // Parse time safely and update the date object
                if ($firstAvailableTime !== null) {
                    $timeParts = explode(':', $firstAvailableTime);
                    if (count($timeParts) === 2) {
                        $date->setTime((int)$timeParts[0], (int)$timeParts[1], 0);
                    }
                }
            }
            // If $times is empty, do nothing - CheckDateAlt will handle it
        }
        $this->suggestedDate = $date;
        $this->bindDates($ah->getCachedDays());
        ksort($this->times);
        ksort($this->years);
        ksort($this->days);
        ksort($this->months);

        $this->workTimes = $ah->getWorkTimes(Date::create($date));
        $this->interval = $ah->getInterval();
    }

    public function bindInitialDate($date)
    {
        $from = $this->availabilityHelper->getHoursBeforeHelper()->getFromDate();
        if ($date < $from) {
            $date = $from;
        }

        return $date;
    }

    private function bindDates($dates)
    {
        $this->years  = array();
        $this->months = array();
        $this->days   = array();
        $this->dates  = array();
        $checkDay     = $this->suggestedDate->format('Y-m-');
        $checkMonth   = $this->suggestedDate->format('Y-');
        foreach ($dates as $date) {
            list($year, $month, $day) = explode('-', $date->getDateTime()->format('Y-m-d'));
            $this->years[$year] = $date;
            if (strpos($date->getDateTime()->format('Y-m-d'), $checkMonth) === 0) {
                $this->months[$month] = $date;
            }
            if (strpos($date->getDateTime()->format('Y-m-d'), $checkDay) === 0) {
                $this->days[$day] = $date;
            }
            $this->dates[] = $date;
        }
        ksort($this->years);
        ksort($this->months);
        ksort($this->days);
    }

    public function toArray($timezone = '')
    {
        $f = SLN_plugin::getInstance()->format();

        $suggestedDate = $timezone ? $this->suggestedDate->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $this->suggestedDate;

        $times = array();
        $currentTime = (new DateTime())->modify(SLN_Plugin::getInstance()->getSettings()->getHoursBeforeFrom());
        $currentTime->setTimezone(wp_timezone());
        //for SLB_API_Mobile purposes
        $customTimeFormat = $_GET['time_format'] ?? false;
        
        foreach ($this->times as $v) {
            $v = $timezone ? $v->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v;
            if($currentTime <= $v){
                $times[$v->format($customTimeFormat ?: 'H:i')] = $v->format($customTimeFormat ?: 'H:i');
            }
        }

        $dates = array();

        foreach ($this->dates as $v) {
            $v = $timezone ? $v->getDateTime()->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v->getDateTime();
            $dates[] = $v->format('Y-m-d');
        }

        $years = array();

        foreach ($this->years as $v) {
            $v = $timezone ? $v->getDateTime()->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v->getDateTime();
            $years[$v->format('Y')] = $v->format('Y');
        }

        $months = SLN_Func::getMonths();
        $monthsList = array();

        foreach ($this->months as $v) {
            $v = $timezone ? $v->getDateTime()->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v->getDateTime();
            $monthsList[$v->format('m')] = $months[intval($v->format('m'))];
        }

        $days = array();

        foreach ($this->days as $v) {
            $v = $timezone ? $v->getDateTime()->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v->getDateTime();
            $days[$v->format('d')] = $v->format('d');
        }

        $workTimes = array();

        foreach ($this->workTimes as $v) {
            $v = $timezone ? $v->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v;
            $workTimes[$v->format($customTimeFormat ?: 'H:i')] = $v->format($customTimeFormat ?: 'H:i');
        }

        $fullDays = array();

        foreach ($this->fullDays as $v) {
            $v = $timezone ? $v->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v;
            $fullDays[] = $v->format('Y-m-d');
        }

        return array(
            'years'          => $years,
            'months'         => $monthsList,
            'days'           => $days,
            'times'          => $times,
            'dates'          => $dates,
            'workTimes'      => $workTimes,
            'interval'       => $this->interval,
            'fullDays'       => $fullDays,
            'suggestedDay'   => $suggestedDate->format('d'),
            'suggestedMonth' => $suggestedDate->format('m'),
            'suggestedYear'  => $suggestedDate->format('Y'),
            'suggestedDate'  => $f->date($suggestedDate),
            'suggestedTime'  => $f->time($suggestedDate, $customTimeFormat),
            'universalSuggestedDate' => $suggestedDate->format('Y-m-d'),
        );
    }

    /**
     * @return mixed
     */
    public function getInitialDate()
    {
        return $this->initialDate;
    }

    /**
     * @return mixed
     */
    public function getSuggestedDate()
    {
        return $this->suggestedDate;
    }

    /**
     * Set suggested date directly (used by CheckDateAlt protection)
     * @param DateTime $date
     */
    public function setSuggestedDate(DateTime $date)
    {
        $this->suggestedDate = $date;
    }

    /**
     * @return mixed
     */
    public function getTimes()
    {
        return $this->times;
    }

    /**
     * @return mixed
     */
    public function getYears()
    {
        return $this->years;
    }

    /**
     * @return mixed
     */
    public function getMonths()
    {
        return $this->months;
    }

    /**
     * @return mixed
     */
    public function getDays()
    {
        return $this->days;
    }
    public function getDates(){
        return $this->dates;
    }

    public function getFullDays(){
        return array_merge(array_unique($this->fullDays), array_map(function($date) { return new DateTime($date); }, SLN_Plugin::getInstance()->getBookingCache()->getFullDays()));
    }

    public function getWorkTimes(){
        return $this->workTimes;
    }

    private function filterTimesByDurationWithBreakAllowance(array $times, Time $duration)
    {
        $filtered = Time::filterTimesArrayByDuration($times, $duration);

        $missing = array_diff_key($times, $filtered);
        if (empty($missing)) {
            return $filtered;
        }

        $dayBookings = $this->availabilityHelper->getDayBookings();
        if (!$dayBookings) {
            return $filtered;
        }

        $breakSlots = array();
        foreach ($missing as $label => $slot) {
            if ($dayBookings->isBreakSlot($slot)) {
                $filtered[$label] = $slot;
                $breakSlots[$label] = $slot;
            }
        }

        if (empty($filtered)) {
            if (!empty($breakSlots)) {
                ksort($breakSlots);
                return $breakSlots;
            }

            return $filtered;
        }

        ksort($filtered);

        return $filtered;
    }
}
