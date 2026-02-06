<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

use Salon\Util\Date;
use Salon\Util\Time;


class SLN_Helper_Availability
{
    const MAX_DAYS = 365;

    private $settings;
    private $date;
    /** @var  SLN_Helper_Availability_AbstractDayBookings */
    private $dayBookings;
    /** @var  SLN_Helper_HoursBefore */
    private $hoursBefore;
    private $attendantsEnabled;
    private $items;
    private $itemsWithoutServiceOffset;
    private $holidayItemsWithWeekDayRules;
    private $holidayItems;
    private $offset;
    protected $initialDate;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->settings = $plugin->getSettings();
        $this->initialDate = $plugin->getBookingBuilder()->getEmptyValue();
        $this->attendantsEnabled = $this->settings->isAttendantsEnabled();
    }

    public function getHoursBeforeHelper()
    {
        if (!isset($this->hoursBefore)) {
            $this->hoursBefore = new SLN_Helper_HoursBefore($this->settings);
        }

        return $this->hoursBefore;
    }

    public function getHoursBeforeString()
    {
        return $this->getHoursBeforeHelper()->getHoursBeforeString();
    }

    public function getCachedDays() {
        $bc = SLN_Plugin::getInstance()->getBookingCache();
        $interval = $this->getHoursBeforeHelper();
        $from = Date::create($interval->getFromDate());
        $count = $interval->getCountDays();
        $ret = array();
        $avItems = $this->getItems();
        $hItems  = $this->getHolidaysItemsWithWeekDayRules($avItems->getWeekDayRules());
        $dayLog = SLN_Helper_Availability_AdminRuleLog::getInstance();
        
        // PERFORMANCE OPTIMIZATION: Batch process all missing dates at once
        // Instead of: processDate() → save() → processDate() → save() (89 times)
        // We do: processDate() → processDate() → ... → save() (once)
        $dates_to_process = array();
        $temp_from = clone $from;
        $temp_count = $count;
        
        while ($temp_count > 0) {
            $temp_count--;
            if(empty($bc->getDay($temp_from))) {
                $dates_to_process[] = clone $temp_from;
            }
            $temp_from = $temp_from->getNextDate();
        }
        
        // Process all missing dates, then save ONCE
        if (!empty($dates_to_process)) {
            foreach ($dates_to_process as $date) {
                $bc->processDate($date);
            }
            $bc->save(); // Single save instead of N saves
        }
        
        // Now build the result array
        while ($count > 0) {
            $count--;
            $day = $bc->getDay($from);
            
            if ($dayLog->isEnabled()) {
                $dayLog->addDateLog( $from->toString(), $hItems->isValidDate($from), __( 'The day is holiday', 'salon-booking-system' ) );
                $dayLog->addDateLog( $from->toString(), !(isset($day) && $day['status'] == 'booking_rules'), __( 'This is non-working day', 'salon-booking-system' ) );
                $dayLog->addDateLog( $from->toString(), !(isset($day) && $day['status'] == 'full'), __( 'The day is full', 'salon-booking-system' ));
            }
            if((!isset($day) && $avItems->isValidDate($from) && $hItems->isValidDate($from) && $this->isValidDate($from)) ||
            (isset($day) && $day['status'] == 'free')) {
                $ret[] = $from;
            }
            $from = $from->getNextDate();
        }
        
        return $ret;
    }

    public function getDays()
    {
        $interval = $this->getHoursBeforeHelper();
        $from = Date::create($interval->getFromDate());
        $count = $interval->getCountDays();
        $ret = array();
        $avItems = $this->getItems();
        $hItems  = $this->getHolidaysItemsWithWeekDayRules($avItems->getWeekDayRules());
        while ($count > 0) {
            $count--;
            if ($avItems->isValidDate($from) && $hItems->isValidDate($from) && $this->isValidDate($from)) {
                $ret[] = $from->toString();
            }
            $from = $from->getNextDate();
        }

        return $ret;
    }

    public function getCachedTimes(Date $date, Time $duration = null)
    {
        $bc = SLN_Plugin::getInstance()->getBookingCache();
        if ($bc->hasFullDay($date)) {
            return array();
        }
        $ret = $this->getTimes($date);
        if(empty($ret)){
            $bc->processDate($date);
            $bc->save();
        }

        return $ret;
    }

    public function getTimes(Date $date)
    {
        $ret = array();
        $avItems = $this->getItems();
        $hItems = $this->getHolidaysItems();
        $hb = $this->getHoursBeforeHelper();
        $from = $hb->getFromDate();
        $to = $hb->getToDate();
        
        // Debug: Log from/to times for November 12
        if ($date->toString() === '2025-11-12') {
            SLN_Plugin::addLog(sprintf('[getTimes DEBUG] November 12 - From: %s | To: %s', $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')));
        }
        
        $timeLog = SLN_Helper_Availability_AdminRuleLog::getInstance();
        foreach (SLN_Func::getMinutesIntervals() as $time) {
        	$d = new SLN_DateTime($date->toString().' '.$time);

            if ($timeLog->isEnabled()) {
                foreach( $avItems->getDateSubset( Salon\Util\Date::create($d) ) as $data){
                    $debugMessage = __( 'Working hours', 'salon-booking-system' ). ': ';
                    if(isset($data->getData()['from']) && is_array($data->getData()['from']) && is_array($data->getData()['to'])){
                        foreach( array_map( null, $data->getData()['from'], $data->getData()['to'] ) as $t ){
                            $debugMessage .= ' '. __( 'from', 'salon-booking-system' ). ' '. $t[0];
                            $debugMessage .= ' '. __( 'to', 'salon-booking-system' ). ' '. $t[1]. ',';
                        }
                    }
                    $debugMessage[-1] = '.';
                    $timeLog->addLog( $time,  $avItems->isValidDatetime( $d ), $debugMessage );
                }
                $debugMessage = __( 'Parallels hours for booking', 'salon-booking-system' );
                $debugMessage .= ' '. $this->settings->get('parallels_hour'). '.';
                try{
                    $debugMessage .= ' '. __( 'Number of reserved booking', 'salon-booking-system' );
                    $keyRegex = '/'. $debugMessage. ' (\d*)./';
                    $debugMessage .= ' '. $this->getBookingsHourCount($d->format('H'), $d->format('i') ). '.';
                }catch( Throwable $ex){
                    $debugMessage .= __( 'Time out of the booking time range.', 'salon-booking-system' );
                }
                $timeLog->replaceKeyRegex( $time, $keyRegex, $debugMessage, $this->isValidTime( $d ) );
                $debugMessage = __( 'Available booking time range: from', 'salon-booking-system' ). ' '. $from->format('d-M H:i');
                $debugMessage .= ' '. __( 'to', 'salon-booking-system' ). $to->format('d-M H:i'). '.';
                $timeLog->addLog( $time, $d >= $from && $d <= $to, $debugMessage );
            }

            $avCheck = $avItems->isValidDatetime($d);
            $hCheck = $hItems->isValidDatetime($d);
            $timeCheck = $this->isValidTime($d);
            $rangeCheck = $d >= $from && $d <= $to;
            
            // Check if this is a break slot (should bypass range check for "hours before")
            $time_obj = $this->getDayBookings()->getTime($d->format('H'), $d->format('i'));
            $isBreakSlot = $this->getDayBookings()->isBreakSlot($time_obj);
            
            // Break slots should only check: availability, holidays, and time (not range)
            // Regular slots check all four
            if ($isBreakSlot && $avCheck && $hCheck && $timeCheck && $d <= $to) {
                $ret[$time] = $d;
            } elseif (!$isBreakSlot && $avCheck && $hCheck && $timeCheck && $rangeCheck) {
                $ret[$time] = $d;
            }
        }
        SLN_Plugin::addLog(__CLASS__.' getTimes '.print_r($ret, true));

        return $ret;
    }

    public function  setDate(DateTime $date, SLN_Wrapper_Booking $booking = null)
    {
        if (empty($this->date) || ($this->date->format('Ymd') != $date->format('Ymd'))) {
            $mode = $this->settings->getAvailabilityMode();
            SLN_Plugin::addLog('');
            SLN_Plugin::addLog('==================================================');
            SLN_Plugin::addLog('🔍 AVAILABILITY MODE: '.strtoupper($mode));
            SLN_Plugin::addLog('==================================================');
            
            $obj = SLN_Enum_AvailabilityModeProvider::getService(
                $mode,
                $date,
                $booking
            );
            SLN_Plugin::addLog(__CLASS__.sprintf(' - Started DayBookings class: %s', get_class($obj)));
            SLN_Plugin::addLog(__CLASS__.sprintf(' - Date: %s', $date->format('Y-m-d H:i')));
            SLN_Plugin::addLog(__CLASS__.sprintf(' - Booking: %s', $booking ? '#'.$booking->getId() : 'none'));
            $this->dayBookings = $obj;
        }
        $this->dayBookings->setTime($date->format('H'), $date->format('i'));
        $this->date = $date;

        return $this;
    }

    /**
     * @return SLN_Helper_Availability_AbstractDayBookings
     */
    public function getDayBookings()
    {
        return $this->dayBookings;
    }

    public function getBookingsDayCount()
    {
        return $this->getDayBookings()->countBookingsByDay();
    }

    public function getBookingsHourCount($hour = null, $minutes = null)
    {
        return $this->getDayBookings()->countBookingsByHour($hour, $minutes);
    }

    public function getMinutesIntervals()
    {
        return $this->getDayBookings()->getMinutesIntervals();
    }

    public function validateAttendantService($attendant, SLN_Wrapper_ServiceInterface $service)
    {
        if(!is_array($attendant)){
            if (!$attendant->hasAllServices()) {
                if (!$attendant->hasService($service)) {
                    return array(
                        __('This assistant is not available for the selected service', 'salon-booking-system'),
                    );
                }
            }
        }else{
            foreach($attendant as $att){
                if(!$att->hasAllServices()){
                    if(!$att->hasService($service)){
                        return array(
                            __('This assistant is not available for the selected service', 'salon-booking-system'),
                        );
                    }
                }
            }
        }
    }

    public function validateAttendant(
        SLN_Wrapper_AttendantInterface $attendant,
        DateTime $date = null,
        DateTime $duration = null,
        SLN_Wrapper_ServiceInterface $service = null,
        DateTime $breakStartsAt = null,
        DateTime $breakEndsAt = null,
        $isLastService = false
    ) {
        $date = empty($date) ? $this->date : $date;
        $durationMinutes = !empty($duration) ? SLN_Func::getMinutesFromDuration($duration) : 0;

        $ignoreExistingBreaks = $this->getDayBookings()->isIgnoreServiceBreaks();
        $noBreak = $ignoreExistingBreaks || $breakStartsAt == $breakEndsAt || !$breakStartsAt || !$breakEndsAt;
        $nestedBookingsEnabled = SLN_Plugin::getInstance()->getSettings()->isNestedBookingsEnabled();

        SLN_Plugin::addLog(
            __CLASS__.sprintf(
                ' - validate attendant %s by date(%s) and duration(%s) | nestedBookings=%s',
                $attendant,
                $date->format('Ymd H:i'),
                $durationMinutes,
                $nestedBookingsEnabled ? 'YES' : 'NO'
            )
        );

        $startAt = clone $date;
        $endAt = clone $date;
        $endAt->modify('+'. $durationMinutes .'minutes');

        SLN_Plugin::addLog(
            sprintf(
                '%s - Multi-attendant validation window %s -> %s | break %s -> %s | ignoreExisting=%s',
                __CLASS__,
                $startAt->format('H:i'),
                $endAt->format('H:i'),
                $breakStartsAt ? $breakStartsAt->format('H:i') : 'n/a',
                $breakEndsAt ? $breakEndsAt->format('H:i') : 'n/a',
                $this->getDayBookings()->isIgnoreServiceBreaks() ? 'YES' : 'NO'
            )
        );

        SLN_Plugin::addLog(
            sprintf(
                '%s - Attendant #%s validation window %s -> %s | break %s -> %s | ignoreExisting=%s',
                __CLASS__,
                method_exists($attendant, 'getId') ? $attendant->getId() : 'n/a',
                $startAt->format('H:i'),
                $endAt->format('H:i'),
                $breakStartsAt ? $breakStartsAt->format('H:i') : 'n/a',
                $breakEndsAt ? $breakEndsAt->format('H:i') : 'n/a',
                $ignoreExistingBreaks ? 'YES' : 'NO'
            )
        );

        $bookingOffsetEnabled   = SLN_Plugin::getInstance()->getSettings()->get('reservation_interval_enabled');
        $bookingOffset          = SLN_Plugin::getInstance()->getSettings()->get('minutes_between_reservation');

        if ($isLastService && $bookingOffsetEnabled) {
            $endAt = $endAt->modify('+'.$bookingOffset.' minutes');
        }

        $times = SLN_Func::filterTimes($this->getMinutesIntervals(), $startAt, $endAt);
        if($duration && $times && $attendant->isNotAvailableOnDateDuration($times[0], $duration, $service)) {
            return SLN_Helper_Availability_ErrorHelper::doAttendantNotAvailable($attendant, $times[0]);
        }
        foreach ($times as $time) {
            $b = $this->getDayBookings();
            $bTime = $b->getTime($time->format('H'), $time->format('i'));
            $outsideBreak = $noBreak || ($bTime < $breakStartsAt || $bTime >= $breakEndsAt);
            
            // Check if this slot is a break slot in an EXISTING booking (where nested bookings are allowed)
            $isExistingBreakSlot = $b->isBreakSlot($bTime);
            
            SLN_Plugin::addLog(
                sprintf(
                    '%s - Checking slot %s | outsideBreak=%s | existingBreakSlot=%s',
                    __CLASS__,
                    $time->format('H:i'),
                    $outsideBreak ? 'YES' : 'NO',
                    $isExistingBreakSlot ? 'YES' : 'NO'
                )
            );
            
            // Skip validation if:
            // 1. Outside the new booking's break period, AND
            // 2. NOT an existing booking's break slot (where nested bookings are allowed)
            if ($outsideBreak && !$isExistingBreakSlot) {
                if ($ret = $this->validateAttendantOnTime($attendant, $time, $service)) {
                    return $ret;
                }
            } elseif ($isExistingBreakSlot) {
                SLN_Plugin::addLog(
                    sprintf(
                        '%s - ✅ Skipping validation for %s (existing break slot with nested bookings)',
                        __CLASS__,
                        $time->format('H:i')
                    )
                );
            }
        }
    }

    public function validateAttendants(
        $attendants,
        DateTime $date = null,
        DateTime $duration = null,
        SLN_Wrapper_ServiceInterface $service = null,
        DateTime $breakStartsAt = null,
        DateTime $breakEndsAt = null,
        $isLastService = false
    ){
        $date = empty($date) ? $this->date : $date;
        $durationMinutes = !empty($duration) ? SLN_Func::getMinutesFromDuration($duration) : 0;

        $noBreak = $this->getDayBookings()->isIgnoreServiceBreaks() || $breakStartsAt == $breakEndsAt || !$breakStartsAt || !$breakEndsAt;

        SLN_Plugin::addLog(
            __CLASS__.sprintf(
                ' - validate attendant %s by date(%s) and duration(%s)',
                print_r($attendants, true),
                $date->format('Ymd H:i'),
                $durationMinutes
            )
        );

        $startAt = clone $date;
        $endAt = clone $date;
        $endAt->modify('+'. $durationMinutes .'minutes');

        $bookingOffsetEnabled   = SLN_Plugin::getInstance()->getSettings()->get('reservation_interval_enabled');
        $bookingOffset          = SLN_Plugin::getInstance()->getSettings()->get('minutes_between_reservation');

        if ($isLastService && $bookingOffsetEnabled) {
            $endAt = $endAt->modify('+'.$bookingOffset.' minutes');
        }

        $times = SLN_Func::filterTimes($this->getMinutesIntervals(), $startAt, $endAt);

        foreach($attendants as $attendant){
            if($duration && $times && $attendant->isNotAvailableOnDateDuration($times[0], $duration, $service)) {
                return SLN_Helper_Availability_ErrorHelper::doAttendantNotAvailable($attendant, $times[0]);
            }
            foreach ($times as $time) {
                $b = $this->getDayBookings();
                $bTime = $b->getTime($time->format('H'), $time->format('i'));
                $outsideBreak = $noBreak || ($bTime < $breakStartsAt || $bTime >= $breakEndsAt);
                
                // Check if this slot is a break slot in an EXISTING booking (where nested bookings are allowed)
                $isExistingBreakSlot = $b->isBreakSlot($bTime);
                
                // Skip validation if outside new booking's break AND not an existing break slot
                if ($outsideBreak && !$isExistingBreakSlot) {
                    if ($ret = $this->validateAttendantOnTime($attendant, $time, $service)) {
                        return $ret;
                    }
                }
            }
        }
    }

    private function validateAttendantOnTime(SLN_Wrapper_AttendantInterface $attendant, SLN_DateTime $time, SLN_Wrapper_ServiceInterface $service=null)
    {
        SLN_Plugin::addLog(__CLASS__.sprintf(' checking time %s', $time->format('Ymd H:i')));
        $time = $this->getDayBookings()->getTime($time->format('H'), $time->format('i'));

        if ($attendant->isNotAvailableOnDate($time, $service)) {
            return SLN_Helper_Availability_ErrorHelper::doAttendantNotAvailable($attendant, $time);
        }

        $ids = $this->getDayBookings()->countAttendantsByHour($time->format('H'), $time->format('i'));
        $isAttendant_booked = isset($ids[$attendant->getId()]);
        $can_multiple = $attendant->canMultipleCustomers();
        
        SLN_Plugin::addLog(__CLASS__.' - Attendant #'.$attendant->getId().' validation at '.$time->format('H:i').': Booked='.$isAttendant_booked.', Count='.($ids[$attendant->getId()] ?? 0).', Can multiple='.$can_multiple);
        
        if($isAttendant_booked && $can_multiple){
            $plugin = SLN_Plugin::getInstance();
            $setts = $plugin->getSettings();

            $services = $this->getDayBookings()->getAttendantServiceIdsByHour($attendant->getId(),$time->format('H'), $time->format('i'));
            if(!empty($services) && is_array($services))
            foreach($services as $service_id){
                $service = new SLN_Wrapper_Service($service_id);
                $unit = $service->getUnitPerHour();
                $limit = $unit ? $unit : $setts->get('parallels_hour');
                $busy = $isAttendant_booked && $ids[$attendant->getId()] >= $limit;
            }

        }else{
            $busy = $isAttendant_booked && $ids[$attendant->getId()] >= 0;
        }

        if ( $busy ) {
            SLN_Plugin::addLog(__CLASS__.' - ❌ Attendant BUSY: count='.($ids[$attendant->getId()] ?? 0));
            return SLN_Helper_Availability_ErrorHelper::doAttendantBusy($attendant, $time);
        } else {
            SLN_Plugin::addLog(__CLASS__.' - ✅ Attendant available');
        }
    }

    public function validateBookingService(SLN_Wrapper_Booking_Service $bookingService, $isLastService = false)
    {
        if($bookingService->getCountServices() > 1){
            return $this->validateService(
                $bookingService->getService(),
                $bookingService->getStartsAt(),
                $bookingService->getTotalDuration(),
                $bookingService->getBreakStartsAt(),
                $bookingService->getBreakEndsAt(),
                $isLastService,
                $bookingService->getCountServices(),
            );
        }
        return $this->validateService(
            $bookingService->getService(),
            $bookingService->getStartsAt(),
            $bookingService->getTotalDuration(),
            $bookingService->getBreakStartsAt(),
            $bookingService->getBreakEndsAt(),
            $isLastService
        );
    }

    public function validateBookingAttendant(SLN_Wrapper_Booking_Service $bookingService, $isLastService = false){
        //
        return $this->validateAttendant(
            $bookingService->getAttendant(),
            $bookingService->getStartsAt(),
            $bookingService->getTotalDuration(),
            $bookingService->getService(),
            $bookingService->getBreakStartsAt(),
            $bookingService->getBreakEndsAt(),
            $isLastService
        );
    }

    public function validateBookingAttendants(SLN_Wrapper_booking_Service $bookingService, $isLastService = false){
        return $this->validateAttendants(
            $bookingService->getAttendant(),
            $bookingService->getStartsAt(),
            $bookingService->getTotalDuration(),
            $bookingService->getService(),
            $bookingService->getBreakStartsAt(),
            $bookingService->getBreakEndsAt(),
            $isLastService
        );
    }


    public function validateService(SLN_Wrapper_ServiceInterface $service, DateTimeInterface $date = null, DateTimeInterface $duration = null, DateTimeInterface $breakStartsAt = null, DateTimeInterface $breakEndsAt = null, $isLastService = false, $count = 1)
    {
        $date = empty($date) ? $this->date : $date;
        $totalDuration = $service->getTotalDuration();
        if($count > 1) {
            $currentMinutes = (int)$totalDuration->format('i');
            $newMinutes = $currentMinutes * $count - $currentMinutes;
            $totalDuration->modify("+{$newMinutes} minutes");
        }

        $duration = empty($totalDuration) ? $duration : $totalDuration;

        $noBreak = $this->getDayBookings()->isIgnoreServiceBreaks() || $breakStartsAt == $breakEndsAt || !$breakStartsAt || !$breakEndsAt;

        SLN_Plugin::addLog(
            __CLASS__.sprintf(
                ' - validate service %s by date(%s) and duration(%s)',
                $service,
                $date->format('Ymd H:i'),
                $duration->format('H:i')
            )
        );

        $startAt = clone $date;
        $endAt = clone $date;
        $endAt->modify('+'.SLN_Func::getMinutesFromDuration($duration).'minutes');

        $bookingOffsetEnabled   = SLN_Plugin::getInstance()->getSettings()->get('reservation_interval_enabled');
        $bookingOffset          = SLN_Plugin::getInstance()->getSettings()->get('minutes_between_reservation');

        // if offset is considered, process differently
        if ($isLastService && $bookingOffsetEnabled) {
            $endAtWithoutOffset = clone $endAt;
            $endAtWithOffset = $endAt->modify('+'.$bookingOffset.' minutes');
            $times = SLN_Func::filterTimes($this->getMinutesIntervals(), $startAt, $endAtWithoutOffset);
            $timesWithOffset = SLN_Func::filterTimes($this->getMinutesIntervals(), $startAt, $endAtWithOffset);
            if($times && $ret = $this->validateServiceOnTime($service, $times[0], true)){
                return $ret;
            }
            foreach ($timesWithOffset as $time) {
                $bTime = $this->getDayBookings()->getTime($time->format('H'), $time->format('i'));
                if ($noBreak || ($bTime < $breakStartsAt || $bTime >= $breakEndsAt)) {
                    // if $time is part of duration without offset, validate normally
                    // if not, validate without booking and holiday rules (offset should consider only other bookings)
                    if ($ret = $this->validateServiceOnTime($service, $time, false, in_array($time, $times))) {
                        return $ret;
                    }
                }
            }
        } else {
            $times = SLN_Func::filterTimes($this->getMinutesIntervals(), $startAt, $endAt);
            if($times && $ret = $this->validateServiceOnTime($service, $times[0], true)){
                return $ret;
            }
            foreach ($times as $time) {
                $bTime = $this->getDayBookings()->getTime($time->format('H'), $time->format('i'));
                if ($noBreak || ($bTime < $breakStartsAt || $bTime >= $breakEndsAt)) {
                    if ($ret = $this->validateServiceOnTime($service, $time, false)) {
                        return $ret;
                    }
                }
            }
        }
    }

    private function validateServiceOnTime(SLN_Wrapper_ServiceInterface $service, SLN_DateTime $time, $checkDuration = true, $checkBookingAndHolidayRules = true)
    {
        SLN_Plugin::addLog(__CLASS__.sprintf(' checking time %s', $time->format('Ymd H:i')));
        $time = $this->getDayBookings()->getTime($time->format('H'), $time->format('i'));

        $avItems = $this->getItemsWithoutServiceOffset();
        $hItems  = $this->getHolidaysItems();
        $duration = null;
        if ($checkBookingAndHolidayRules) {
            if ($checkDuration) {
                $duration = $service->getDuration();
                $check = (!$avItems->isValidDatetimeDuration($time, $duration) || !$hItems->isValidDatetimeDuration($time, $duration));
            } else {
                $check = (!$avItems->isValidDatetime($time) || !$hItems->isValidDatetime($time));
            }
            if ($check) {
                return SLN_Helper_Availability_ErrorHelper::doServiceNotEnoughTime($service, $time);
            }
        }

        // Skip parallel booking check if this is a break slot (nested bookings allowed)
        $isBreakSlot = $this->getDayBookings()->isBreakSlot($time);
        if (!$isBreakSlot && !$this->isValidOnlyTime($time)) {
            SLN_Plugin::addLog(sprintf('[validateServiceOnTime] %s failed parallel check (not a break slot)', $time->format('H:i')));
            return SLN_Helper_Availability_ErrorHelper::doLimitParallelBookings($time);
        } elseif ($isBreakSlot) {
            SLN_Plugin::addLog(sprintf('[validateServiceOnTime] %s is break slot - skipping parallel check', $time->format('H:i')));
        }
        if ($checkDuration && $service->isNotAvailableOnDate($time)) {
            return SLN_Helper_Availability_ErrorHelper::doServiceNotAvailableOnDate($service, $time);
        }
        if ($ret = $this->validateServiceAttendantsOnTime($service, $time, $duration)) {
            return $ret;
        }
        $ids = $this->getDayBookings()->countServicesByHour($time->format('H'), $time->format('i'));
        $unit = $service->getUnitPerHour();
        
        SLN_Plugin::addLog(__CLASS__.' - Service #'.$service->getId().' validation at '.$time->format('H:i').': Unit='.$unit.', Current count='.($ids[$service->getId()] ?? 0));
        
        if (
            $unit > 0
            && isset($ids[$service->getId()])
            && $ids[$service->getId()] >= $unit
        ) {
            SLN_Plugin::addLog(__CLASS__.' - ❌ Service FULL: '.$ids[$service->getId()].' >= '.$unit);
            return SLN_Helper_Availability_ErrorHelper::doServiceFull($service, $time);
        } else {
            SLN_Plugin::addLog(__CLASS__.' - ✅ Service available: '.($ids[$service->getId()] ?? 0).' < '.$unit);
        }

        if ($ret = $this->validateServiceResourcesOnTime($service, $time, $duration)) {
            return $ret;
        }
    }

    private function validateServiceAttendantsOnTime(SLN_Wrapper_ServiceInterface $service, DateTime $time, DateTime $duration = null)
    {
        if (!$this->attendantsEnabled) {
            return;
        }
        if (!$service->isAttendantsEnabled()) {
            return;
        }
        $attendants = $service->getAttendants();
        foreach ($attendants as $k => $attendant) {
            if ($this->validateAttendant($attendant, $time, $duration, $service)) {
                unset($attendants[$k]);
            }
        }

        if (empty($attendants) || $service->isMultipleAttendantsForServiceEnabled() && count($attendants) < $service->getCountMultipleAttendants()) {
            return SLN_Helper_Availability_ErrorHelper::doServiceAllAttendantsBusy($service, $time);
        }
    }

    public function validateServiceFromOrder(SLN_Wrapper_ServiceInterface $service, SLN_Wrapper_Booking_Services $bookingServices)
    {
        if($service->isSecondary()) {
            $serviceDisplayMode = $service->getMeta('secondary_display_mode');
            if ($serviceDisplayMode !== 'always') {
                foreach($bookingServices->getItems() as $bookingService) {
                    if ($bookingService->getService()->getId() !== $service->getId() && !$bookingService->getService()->isSecondary()) {
                        if ($serviceDisplayMode === 'service') {
                            if(in_array($bookingService->getService()->getId(), (array)$service->getMeta('secondary_parent_services'))) {
                                return array();
                            }
                        }
                        elseif ($serviceDisplayMode === 'category') {
                            $serviceCategories = wp_get_post_terms($service->getId(), 'sln_service_category', array( "fields" => "ids" ) );
                            $serviceCategory   = reset($serviceCategories);

                            $bServiceCategories = wp_get_post_terms($bookingService->getService()->getId(), 'sln_service_category', array( "fields" => "ids" ) );
                            if(in_array($serviceCategory, $bServiceCategories)) {
                                return array();
                            }
                        }
                    }
                }

                if ($serviceDisplayMode === 'service') {
                    return SLN_Helper_Availability_ErrorHelper::doSecondaryServiceNotAvailableWOParentService($service);
                }
                elseif ($serviceDisplayMode === 'category') {
                    return SLN_Helper_Availability_ErrorHelper::doSecondaryServiceNotAvailableWOSameCategoryPrimaryService($service);
                }
            }
        }

	    return array();
    }

    /**
     * @param array $servicesIds
     *
     * @return array of validated services
     */
    public function returnValidatedServices(array $servicesIds)
    {
        $date = $this->date;
        $bb = SLN_Plugin::getInstance()->getBookingBuilder();
        $bookingServices = SLN_Wrapper_Booking_Services::build(array_fill_keys($servicesIds, 0), $date, 0, $bb->getCountServices());
        $validated = array();
        $primaryServicesCount	= $this->settings->get('primary_services_count');
        $secondaryServicesCount = $this->settings->get( 'secondary_services_count' );

        foreach ($bookingServices->getItems() as $bookingService) {

            if($primaryServicesCount) {

                if (!$bookingService->getService()->isSecondary()) {
                    $_validated = array_filter($validated, function ($serviceID) {
                        return !SLN_Plugin::getInstance()->createService($serviceID)->isSecondary();
                    });
                    if (count($_validated) >= $primaryServicesCount) {
                        break;
                    }
                }
	        }

            if( $secondaryServicesCount ){
                if( $bookingService->getService()->isSecondary() ){
                    $_validated = array_filter( $validated, function( $serviceID ){
                        return SLN_Plugin::getInstance()->createService( $serviceID )->isSecondary();
                    });
                   /* if( count( $_services ) >= $secondaryServicesCount ){
                        break;
                    }*/
                }
            }

            $serviceErrors = $this->validateService(
                $bookingService->getService(),
                $bookingService->getStartsAt(),
                null,
                $bookingService->getBreakStartsAt(),
                $bookingService->getBreakEndsAt()
            );
            if (empty($serviceErrors)) {
                $validated[] = $bookingService->getService()->getId();
            } else {
                break;
            }
        }

        $bb = SLN_Plugin::getInstance()->getBookingBuilder();
        $bookingServices = SLN_Wrapper_Booking_Services::build(array_fill_keys($validated, 0), $date, 0, $bb->getCountServices());
        $validated       = array();
        foreach ($bookingServices->getItems() as $bookingService) {
            $serviceErrors = $this->validateServiceFromOrder($bookingService->getService(), $bookingServices);
            if (empty($serviceErrors)) {
                $validated[] = $bookingService->getService()->getId();
            } else {
                break;
            }
        }

        return $validated;
    }

    /**
     * @param array $order
     * @param SLN_Wrapper_ServiceInterface[] $newServices
     *
     * @return array
     */
    public function checkEachOfNewServicesForExistOrder($order, $newServices, $altMode = false)
    {
        $ret = array();
        $date = $this->date;

        $s = $this->settings;
        $bookingOffsetEnabled = $s->get('reservation_interval_enabled');
        $bookingOffset = $s->get('minutes_between_reservation');
        $isMultipleAttSelection = $s->get('m_attendant_enabled');
        $interval = $this->settings->getInterval();
        $primaryServicesCount   = $s->get('primary_services_count');
        $secondaryServicesCount = $s->get( 'secondary_services_count' );

        foreach ($newServices as $service) {
            $services = $order;
            if (!in_array($service->getId(), $services)) {

                if( $primaryServicesCount && !$service->isSecondary() ) {
                    $_services = array_filter($services, function ($serviceID) {
                        return !SLN_Plugin::getInstance()->createService($serviceID)->isSecondary();
                    });

                    if(count($_services) >= $primaryServicesCount) {
                        $ret[$service->getId()] = array(sprintf(
                            // translators: %d will be replaced by the primary services count
                            __('You can select up to %d items', 'salon-booking-system'), $primaryServicesCount));
                        continue;
                    }
                }

                if( $secondaryServicesCount && $service->isSecondary() ){
                    $_services = array_filter( $services, function( $serviceID ){
                        return SLN_Plugin::getInstance()->createService( $serviceID )->isSecondary();
                    } );

                    if( count( $_services ) >= $secondaryServicesCount ){
                        $ret[$service->getId()] = array( sprintf(
                            // translators: %d will be replaced by the secondary services count
                            __('You can select up to %d items', 'salon-booking-system'), $secondaryServicesCount ) );
                        continue;
                    }
                }

                $bb = SLN_Plugin::getInstance()->getBookingBuilder();
                $services[] = $service->getId();
                $bookingServices = SLN_Wrapper_Booking_Services::build(array_fill_keys($services, 0), $date, 0, $bb->getCountServices());
                $availAtts = null;
                foreach ($bookingServices->getItems() as $bookingService) {
                    $serviceErrors = array();
                    $errorMsg = '';

	                $serviceErrors = $this->validateServiceFromOrder($bookingService->getService(), $bookingServices);

	                if (!$altMode) {
		                if (empty($serviceErrors) && $bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
			                $offsetStart = $bookingService->getEndsAt();
			                $offsetEnd = clone $offsetStart;
			                $offsetEnd->modify('+'.$bookingOffset.' minutes');
			                $serviceErrors = $this->validateTimePeriod($offsetStart, $offsetEnd);
		                }

		                if (empty($serviceErrors)) {
			                $serviceErrors = $this->validateService(
					                $bookingService->getService(),
					                $bookingService->getStartsAt(),
					                null,
					                $bookingService->getBreakStartsAt(),
					                $bookingService->getBreakEndsAt()
			                );
		                }

		                if (empty($serviceErrors) && $this->attendantsEnabled && !$isMultipleAttSelection && $bookingService->getService()->isAttendantsEnabled()) {
			                $availAtts = $this->getAvailableAttendantForService($availAtts, $bookingService);
			                if (empty($availAtts)) {
				                $errorMsg = __(
						                'An assistant for selected services can\'t perform this service',
						                'salon-booking-system'
				                );
				                $serviceErrors = array($errorMsg);
			                }
		                }
	                }

                    if (!empty($serviceErrors)) {
                        $ret[$service->getId()] = $this->processServiceErrors(
                            $bookingServices,
                            $bookingService,
                            $service,
                            $serviceErrors
                        );
                        break;
                    }
                }

                if (!isset($ret[$service->getId()])) {
                    $ret[$service->getId()] = array();
                }
            }
        }
        return $ret;
    }

    public function checkExclusiveServices($order, $services) {
        $ret = array();
        $exclusiveServiceId = false;
        foreach ($services as $service) {
            if (in_array($service->getId(), $order)) {
                if($service->isExclusive()) {
                    $exclusiveServiceId = $service->getId();
                    break;
                }
            }
        }
        foreach ($services as $service) {
            if($exclusiveServiceId && $exclusiveServiceId != $service->getId()) {
                $errorMsg = __(
                    'This service is not available with exclusive service.',
                    'salon-booking-system'
                );
                $ret[$service->getId()] = array($errorMsg);
            }
        }
        return $ret;
    }

    private function processServiceErrors(
        SLN_Wrapper_Booking_Services $bookingServices,
        SLN_Wrapper_Booking_Service $bookingService,
        SLN_Wrapper_ServiceInterface $service,
        $serviceErrors
    ) {
        if ($bookingService->getService()->getId() == $service->getId()) {
            $error = $serviceErrors[0];
        } else {
            $tmp = $bookingServices->findByService($service->getId());
            $error = !empty($errorMsg) ? $errorMsg : __(
                    'You already selected service at',
                    'salon-booking-system'
                ).($tmp ? ' '.$tmp->getStartsAt()->format('H:i') : '');
        }

        return array($error);
    }

    public function getAvailableAttendantForService($availAtts = null, SLN_Wrapper_Booking_Service $bookingService = null)
    {
        $intersect = $this->getAvailableAttsIdsForBookingService($bookingService);
        if (is_null($availAtts)) {
            $availAtts = $intersect;
        }
        $availAtts = array_intersect($availAtts, $intersect);

        return $availAtts;
    }

    public function getAvailableAttsIdsForBookingService(SLN_Wrapper_Booking_Service $bs)
    {
        return $this->getAvailableAttsIdsForServiceOnTime(
            $bs->getService(),
            $bs->getStartsAt(),
            $bs->getTotalDuration(),
            $bs->getBreakStartsAt(),
            $bs->getBreakEndsAt()
        );
    }

    public function getAvailableAttsIdsForServiceOnTime(
        SLN_Wrapper_ServiceInterface $service,
        DateTime $date = null,
        DateTime $duration = null,
        DateTime $breakStartsAt = null,
        DateTime $breakEndsAt = null
    ) {
        $date = empty($date) ? $this->date : $date;
        $duration = empty($duration) ? $service->getTotalDuration() : $duration;
        $ret = array();

        $startAt = clone $date;
        $endAt = clone $date;
        $endAt->modify('+'.SLN_Func::getMinutesFromDuration($duration).'minutes');

        $attendants = apply_filters('sln.availability.getAvailableAttsIdsForServiceOnTime.attendants', $service->getAttendants());
        $times = SLN_Func::filterTimes($this->getMinutesIntervals(), $startAt, $endAt);
        foreach ($times as $time) {
            foreach ($attendants as $k => $attendant) {
                if ($this->validateAttendant($attendant, $time, null, $service, $breakStartsAt, $breakEndsAt)) {
                    unset($attendants[$k]);
                }
            }
        }
        foreach ($attendants as $attendant) {
            $ret[] = $attendant->getId();
        }

        return $ret;
    }

    public function addAttendantForServices(SLN_Wrapper_Booking_Services $bookingServices)
    {
        if ($this->settings->isMultipleAttendantsEnabled()) {
            $this->addMultipleAttendantForServices($bookingServices);
        } else {
            $this->addSingleAttendantForServices($bookingServices);
        }
    }

    private function addMultipleAttendantForServices(SLN_Wrapper_Booking_Services $bookingServices)
    {
        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();
            if (!$service->isAttendantsEnabled()) {
                continue;
            }

            $availAttsForEachService[$service->getId()]
                = $availAttsForCurrentService = $this->getAvailableAttsIdsForBookingService($bookingService);
            if (empty($availAttsForCurrentService)) {
                throw new SLN_Exception(
                    esc_html(sprintf(
                        // translators: %s will be replaced by the service name
                        __('No one of the attendants isn\'t available for %s service', 'salon-booking-system'),
                        $service->getName()
                    ))
                );
            }

            if (is_object($bookingService->getAttendant())) {
                $selectedAttId = $bookingService->getAttendant()->getId();
                if (!in_array($selectedAttId, $availAttsForCurrentService)) {
                    throw new SLN_Exception(
                        sprintf(
                            // translators: s%1$ will be replaced by attendant name, %2$s will be replaced by service name
                            esc_html__('Attendant %1$s isn\'t available for %2$s service', 'salon-booking-system'),
                            esc_html($bookingService->getAttendant()->getName()),
                            esc_html($service->getName())
                        )
                    );
                }
            } else {
                SLN_Plugin::getInstance();
                $selectedAttId = $availAttsForCurrentService[array_rand($availAttsForCurrentService)];
                $attendant     = SLN_Plugin::getInstance()->createAttendant($selectedAttId);
                $bookingService->setAttendant($attendant);
            }
        }
    }

    private function addSingleAttendantForServices(SLN_Wrapper_Booking_Services $bookingServices)
    {
        $availAttsForAllServices = null;
        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();
            if (!$service->isAttendantsEnabled()) {
                continue;
            }

            $availAttsForAllServices = $this->getAvailableAttendantForService(
                $availAttsForAllServices,
                $bookingService
            );
            if (is_object($bookingService->getAttendant())) {
                $availAttsForAllServices = array_intersect(
                    $availAttsForAllServices,
                    array($bookingService->getAttendant()->getId())
                );
            }

            if (empty($availAttsForAllServices)) {
                throw new SLN_Exception(
                    esc_html__('No one of the attendants isn\'t available for selected services', 'salon-booking-system')
                );
            }
        }

        if (!empty($availAttsForAllServices)) {
            $selectedAttId = $availAttsForAllServices[array_rand($availAttsForAllServices)];
            $attendant     = SLN_Plugin::getInstance()->createAttendant($selectedAttId);
            foreach ($bookingServices->getItems() as $bookingService) {
                $service = $bookingService->getService();
                if ($service->isAttendantsEnabled()) {
                    $bookingService->setAttendant($attendant);
                }
            }
        }
    }

    public function validateTimePeriod($start, $end)
    {
        $times = SLN_Func::filterTimes($this->getMinutesIntervals(), $start, $end);
        
        // Check if the START time is a break slot with nested bookings allowed
        $startTime = $this->getDayBookings()->getTime($start->format('H'), $start->format('i'));
        $startsInBreak = $this->getDayBookings()->isBreakSlot($startTime);
        
        if ($startsInBreak) {
            return array();
        }
        
        foreach ($times as $time) {
            $time = $this->getDayBookings()->getTime($time->format('H'), $time->format('i'));
            
            if (!$this->isValidOnlyTime($time)) {
                return array(__('Limit of parallels bookings at ', 'salon-booking-system').$time->format('H:i'));
            }
        }

        return array();
    }

    /**
     * @return SLN_Helper_AvailabilityItems
     */
    public function getItems()
    {
        if (!isset($this->items)) {
            $this->items = $this->settings->getNewAvailabilityItems();
        }

        return $this->items;
    }

    private function getOffset()
    {
        if(!$this->offset){
            $duration = SLN_Plugin::getInstance()->getRepository(
                SLN_Plugin::POST_TYPE_SERVICE
            )->getMinPrimaryServiceDuration();
            $this->offset = SLN_Func::getMinutesFromDuration($duration);
        }
        return $this->offset;
    }

    public function resetItems()
    {
        $this->hoursBefore                  = null;
        $this->items                        = null;
        $this->itemsWithoutServiceOffset    = null;
        $this->holidayItems                 = null;
        $this->holidayItemsWithWeekDayRules = null;
    }

    /**
     * @return SLN_Helper_AvailabilityItems
     */
    public function getItemsWithoutServiceOffset()
    {
        if (!isset($this->itemsWithoutServiceOffset)) {
            $this->itemsWithoutServiceOffset = $this->settings->getAvailabilityItems();
        }

        return $this->itemsWithoutServiceOffset;
    }

    /**
     * @return SLN_Helper_HolidayItems
     */
    public function getHolidaysItems()
    {
        if(!isset($this->holidayItems)){
            $this->holidayItems = $this->settings->getHolidayItems();
        }
        return $this->holidayItems;
    }

    /**
     * @return SLN_Helper_HolidayItems
     */
    public function getHolidaysItemsWithWeekDayRules($weekDayRules)
    {
        if (!isset($this->holidayItemsWithWeekDayRules)) {
            $this->holidayItemsWithWeekDayRules = $this->settings->getNewHolidayItems();
            $this->holidayItemsWithWeekDayRules->setWeekDayRules($weekDayRules);
        }

        return $this->holidayItemsWithWeekDayRules;
    }

    public function isValidDate(Date $date)
    {
        $this->setDate($date->getDateTime());
        $countDay = $this->settings->get('parallels_day');

        return !($countDay && $this->getBookingsDayCount() >= $countDay);
    }

    public function isValidTime($date)
    {
        if (!$this->isValidDate(Date::create($date))) {
            return false;
        }

        return $this->isValidOnlyTime($date);
    }

    public function isValidOnlyTime($date)
    {
        $countHour = $this->settings->get('parallels_hour');
        
        // Skip parallel booking check for break slots (where nested bookings are allowed)
        $time = $this->getDayBookings()->getTime($date->format('H'), $date->format('i'));
        if ($this->getDayBookings()->isBreakSlot($time)) {
            return $date >= $this->initialDate;
        }

        return ($date >= $this->initialDate) && !($countHour && $this->getBookingsHourCount(
                $date->format('H'),
                $date->format('i')
            ) >= $countHour);
    }

    public function getFreeMinutes($date)
    {
        $date = clone $date;
        $ret = 0;
        $interval = $this->settings->getInterval();
        $max = 24 * 60;

        $avItems = $this->getItems();
        while ($avItems->isValidDatetime($date) && $this->isValidTime($date) && $ret <= $max) {
            $ret += $interval;
            $date->modify(sprintf('+%s minutes', $interval));
        }

        return $ret;
    }

    public function getWorkTimes(Date $date)
    {
        $ret     = array();
        $avItems = $this->settings->getNewAvailabilityItems();
        $hItems  = $this->getHolidaysItems();
        foreach (SLN_Func::getMinutesIntervals() as $time) {
            $d = new SLN_DateTime($date->toString().' '.$time);
            if (
                $avItems->isValidDatetime($d)
                && $hItems->isValidDatetime($d)
            ) {
                $ret[$time] = $d;
            }
        }
        SLN_Plugin::addLog(__CLASS__.' getWorkTimes '.print_r($ret, true));

        return $ret;
    }
    
    /**
     * Get working time slots for a date without time-based restrictions (Hours Before, past dates)
     * Used for calendar stats/bar display to show historical data
     */
    public function getWorkTimesForStats(Date $date)
    {
        $ret     = array();
        $avItems = $this->settings->getNewAvailabilityItems();
        $hItems  = $this->getHolidaysItems();
        
        // Check if the date itself is valid (day of week, availability period, holidays)
        if (!$avItems->isValidDate($date) || !$hItems->isValidDate($date)) {
            return $ret; // Date not valid (wrong day of week or holiday)
        }
        
        // Get all time slots that match the availability rules (ignoring "Hours Before")
        foreach (SLN_Func::getMinutesIntervals() as $time) {
            $d = new SLN_DateTime($date->toString().' '.$time);
            $timeObj = Time::create($d);
            
            // Check if this time is within the business hours (without date/time restrictions)
            $dateSubset = $avItems->getDateSubset($date);
            foreach ($dateSubset as $av) {
                if ($av->isValidDate($date) && $av->isValidTime($timeObj)) {
                    $ret[$time] = $d;
                    break; // Time slot is valid, move to next
                }
            }
        }
        
        return $ret;
    }

    public function getInterval()
    {
        return $this->settings->get('interval');
    }

    public function getAvailableResourcesIdsForBookingService(SLN_Wrapper_Booking_Service $bs)
    {
        return $this->getAvailableResourcesIdsForServiceOnTime(
            $bs->getService(),
            $bs->getStartsAt(),
            $bs->getTotalDuration(),
            $bs->getBreakStartsAt(),
            $bs->getBreakEndsAt()
        );
    }

    public function getAvailableResourcesIdsForServiceOnTime(
        SLN_Wrapper_ServiceInterface $service,
        DateTime $date = null,
        DateTime $duration = null,
        DateTime $breakStartsAt = null,
        DateTime $breakEndsAt = null
    ) {
        $date = empty($date) ? $this->date : $date;
        $duration = empty($duration) ? $service->getTotalDuration() : $duration;
        $ret = array();

        $startAt = clone $date;
        $endAt = clone $date;
        $endAt->modify('+'.SLN_Func::getMinutesFromDuration($duration).'minutes');

        $resources = apply_filters('sln.availability.getAvailableResourcesIdsForServiceOnTime.resources', $service->getResources());
        $times = SLN_Func::filterTimes($this->getMinutesIntervals(), $startAt, $endAt);
        foreach ($times as $time) {
            foreach ($resources as $k => $resource) {
                if ($this->validateResource($resource, $time, null, $breakStartsAt, $breakEndsAt)) {
                    unset($resources[$k]);
                }
            }
        }
        foreach ($resources as $resource) {
            $ret[] = $resource->getId();
        }

        return $ret;
    }

    public function validateResource(
        SLN_Wrapper_ResourceInterface $resource,
        DateTime $date = null,
        DateTime $duration = null,
        DateTime $breakStartsAt = null,
        DateTime $breakEndsAt = null,
        $isLastService = false
    ) {
        $date = empty($date) ? $this->date : $date;
        $durationMinutes = !empty($duration) ? SLN_Func::getMinutesFromDuration($duration) : 0;

        $noBreak = $this->getDayBookings()->isIgnoreServiceBreaks() || $breakStartsAt == $breakEndsAt || !$breakStartsAt || !$breakEndsAt;

        SLN_Plugin::addLog(
            __CLASS__.sprintf(
                ' - validate resource %s by date(%s) and duration(%s)',
                $resource,
                $date->format('Ymd H:i'),
                $durationMinutes
            )
        );

        $startAt = clone $date;
        $endAt = clone $date;
        $endAt->modify('+'. $durationMinutes .'minutes');

        $bookingOffsetEnabled   = SLN_Plugin::getInstance()->getSettings()->get('reservation_interval_enabled');
        $bookingOffset          = SLN_Plugin::getInstance()->getSettings()->get('minutes_between_reservation');

        if ($isLastService && $bookingOffsetEnabled) {
            $endAt = $endAt->modify('+'.$bookingOffset.' minutes');
        }

        $times = SLN_Func::filterTimes($this->getMinutesIntervals(), $startAt, $endAt);
        foreach ($times as $time) {
            $b = $this->getDayBookings();
            $bTime = $b->getTime($time->format('H'), $time->format('i'));
            if ($noBreak || ($bTime < $breakStartsAt || $bTime >= $breakEndsAt)) {
                if ($ret = $this->validateResourceOnTime($resource, $time)) {
                    return $ret;
                }
            }
        }
    }

    private function validateResourceOnTime(SLN_Wrapper_ResourceInterface $resource, SLN_DateTime $time)
    {
        SLN_Plugin::addLog(__CLASS__.sprintf(' checking time %s', $time->format('Ymd H:i')));
        $time = $this->getDayBookings()->getTime($time->format('H'), $time->format('i'));

        $booked_resources = $this->getDayBookings()->countResourcesByHour($time->format('H'), $time->format('i'));
        $resources_unit = isset($booked_resources[$resource->getId()]) ? $resource->getUnitPerHour() - $booked_resources[$resource->getId()] : $resource->getUnitPerHour();
        $free_resources_unit = $resources_unit > 0 ? $resources_unit : 0;

        if (!$free_resources_unit) {
            return SLN_Helper_Availability_ErrorHelper::doResourceBusy($resource, $time);
        }
    }

    private function validateServiceResourcesOnTime(SLN_Wrapper_ServiceInterface $service, DateTime $time, DateTime $duration = null)
    {
        $resources = $service->getResources();

        if (empty($resources)) {
            return;
        }

        foreach ($resources as $k => $resource) {
            if ($this->validateResource($resource, $time, $duration)) {
                unset($resources[$k]);
            }
        }

        if (empty($resources)) {
            return SLN_Helper_Availability_ErrorHelper::doServiceAllResourcesBusy($service, $time);
        }
    }

    public function validateResourceService(SLN_Wrapper_ResourceInterface $resource, SLN_Wrapper_ServiceInterface $service)
    {
        if (!in_array($service->getId(), $resource->getServices())) {
            return array(
                __('This resource is not available for the selected service', 'salon-booking-system'),
            );
        }
    }

}
