<?php
// phpcs:ignoreFile WordPress.PHP.DevelopmentFunctions.error_log_print_r

use Salon\Util\Date;

class SLN_Wrapper_Booking_AbstractCache
{
    const KEY = 'salon_cache';

    /** @var SLN_Plugin */
    protected $plugin;
    protected $settings;
    /** @var SLN_Helper_Availability */
    protected $ah;

    public function load()
    {
        $this->settings = get_option($this->getKey());
    }

    public function save()
    {
        //fix full days incorrect calculation
        foreach($this->settings as $day => $data) {
            if ($data['status'] === 'full' && !$data['busy_slots'] && !$data['free_slots']) {
                unset($this->settings[$day]);
            }
        }

        $ah   = $this->ah;
        $hb = $ah->getHoursBeforeHelper();
        $from = $hb->getFromDate();
        $to = $hb->getToDate();

        foreach($this->settings as $day => $data) {
            $d = new SLN_DateTime( $day . ' '. $from->format('H:i:s') );
            if ($d > $to || $d < $from) {
                unset($this->settings[$day]);
            }
        }

        update_option($this->getKey(), $this->settings, false);

        return $this;
    }

    public function getKey(){
        return self::KEY;
    }

    public function removeOld()
    {
        $ah   = $this->ah;
        $hb   = $ah->getHoursBeforeHelper();
        $from = $hb->getFromDate()->format('Ymd');
        foreach (array_keys($this->settings) as $k) {
            $tmp = str_replace('-', '', $k);
            if ($tmp < $from) {
                unset($this->settings[$k]);
            }
        }
    }

    /**
     * @return $this
     */
    public function refreshAll()
    {
        $ah             = $this->plugin->getAvailabilityHelper();
        $hb             = $ah->getHoursBeforeHelper();
        $this->refresh($hb->getFromDate(),$hb->getToDate());

        return $this;
    }

    public function refresh($from,$to)
    {
        // Don't clear entire cache - only update specific dates
        // $this->settings = array();  // REMOVED: This was clearing ALL cached dates
        $from           = Date::create($from);
        $to             = Date::create($to);
        while ($from->isLte($to)) {
            $this->processDate($from);
            $from = $from->getNextDate();
        }
        $this->save();

        SLN_Plugin::addLogCacheData(print_r($this->settings, true));

        try {
            throw new Exception;
        } catch (Exception $e) {
            SLN_Plugin::addLogCacheData($e->getTraceAsString());
        }

        return $this;
    }

    public function processDate(Date $day)
    {
        $ah   = $this->ah;
        $data = array();
        $d = $day->getDateTime();
        $ah->setDate($d);
        $hb = $ah->getHoursBeforeHelper();
        $from = $hb->getFromDate();
        $to = $hb->getToDate();
        
        $day_obj = new Date($day);
        
        // PERFORMANCE OPTIMIZATION: Check basic availability rules FIRST (fast)
        // before calling expensive getTimes() operation
        
        // Check if day is valid according to booking rules (day of week, date range)
        if (!$ah->getItems()->isValidDate($day_obj)) {
            $data['status'] = 'booking_rules';
            $data['free_slots'] = array();
            $data['busy_slots'] = array();
            $this->settings[$day_obj->toString()] = $data;
            return $data;
        }
        
        // FIX: Check if ENTIRE day is outside booking time range
        // Issue: Was comparing day start (00:00) with $from, blocking days with evening slots
        // Example: Jan 7 at 00:00 < Jan 7 at 20:15 = blocked entire day (wrong!)
        // Solution: Only block if the LAST moment of day (23:59:59) is before $from
        $dayEnd = clone $d;
        $dayEnd->setTime(23, 59, 59);
        if ($dayEnd < $from || $d > $to) {
            // Entire day is outside the booking range
            $data['status'] = 'booking_rules';
            $data['free_slots'] = array();
            $data['busy_slots'] = array();
            $this->settings[$day_obj->toString()] = $data;
            return $data;
        }
        
        if (!$ah->getHolidaysItems()->isValidDate($day_obj)) {
            // Date is a holiday - no need to check times
            $data['status'] = 'holiday_rules';
            $data['free_slots'] = array();
            $data['busy_slots'] = array();
            $this->settings[$day_obj->toString()] = $data;
            return $data;
        }

        // Only call getTimes() if date passes basic checks (EXPENSIVE OPERATION)
        // PHP 8+ compatibility: Ensure getTimes returns array and map result is always an array
        $times = $ah->getTimes($day);
        if (!is_array($times)) {
            $times = array();
        }
        $data['free_slots'] = array_map(function ($item) { 
            return is_object($item) && method_exists($item, 'format') ? $item->format('H:i') : (string)$item; 
        }, array_values($times));
        
        // Final safety: Ensure free_slots is an array
        if (!is_array($data['free_slots'])) {
            $data['free_slots'] = array();
        }
        
        if (!$data['free_slots']) {
            $data['status'] = 'full';
        } else {
            $data['status'] = 'free';
        }

        foreach ($ah->getDayBookings()->getTimeslots() as $k => $v) {
            if (isset($v['booking']) && $v['booking'] || isset($v['service']) && $v['service'] || isset($v['attendant']) && $v['attendant'] || isset($v['holidays']) && $v['holidays']) {
                $data['busy_slots'][$k] = $v;
            }
        }
        $this->settings[$day_obj->toString()] = $data;

        return $data;
    }

    public function getFullDays()
    {
        if (!$this->settings) {
            $this->refreshAll();
        }
        $ret = array();
        $now = SLN_TimeFunc::date('Y-m-d');
        foreach ($this->settings as $day => $v) {
            if ($v['status'] == 'full' && $now <= $day) {
                $ret[] = $day;
            }
        }
        return $ret;
    }

    public function processBooking(SLN_Wrapper_Booking $booking, $isNew = false)
    {
        $id = $booking->getId();
        $this->removeOld();
        if (!$isNew) {
            foreach ($this->settings as $day => $v) {
                if (isset($v['busy_slots'])) {
                    $dayHasBooking = false;
                    foreach ($v['busy_slots'] as $slot) {
                        if (in_array($id, $slot['booking'])) {
                            $dayHasBooking = true;
                        }
                    }
                    if ($dayHasBooking && $booking->getDate()->format('Y-m-d') != $day) {
                        $this->processDate(Date::create($day));
                    }
                }
            }
        }
        $this->processDate(Date::create($booking->getDate()));
        $this->save();

        SLN_Plugin::addLogCacheData(print_r($this->settings, true));

        try {
            throw new Exception;
        } catch (Exception $e) {
            SLN_Plugin::addLogCacheData($e->getTraceAsString());
        }
    }

    public function getDay(Date $day)
    {
    	$k = $day->toString();
        if (!isset($this->settings[$k])) {
            // Return empty structure instead of null to prevent PHP 8+ errors
            $ret = array(
                'free_slots' => array(),
                'busy_slots' => array(),
                'status' => 'unknown'
            );
        } else {
            $ret = $this->settings[$k];
        }
        
        // PHP 8+ compatibility: Ensure return is always an array
        if (!is_array($ret)) {
            return array(
                'free_slots' => array(),
                'busy_slots' => array(),
                'status' => 'error'
            );
        }
        
        // PHP 8+ compatibility: Ensure free_slots and busy_slots are arrays
        if (!isset($ret['free_slots']) || !is_array($ret['free_slots'])) {
            $ret['free_slots'] = array();
        }
        if (!isset($ret['busy_slots']) || !is_array($ret['busy_slots'])) {
            $ret['busy_slots'] = array();
        }
        
        return $ret;
    }
}
