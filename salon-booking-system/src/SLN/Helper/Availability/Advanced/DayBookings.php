<?php

class SLN_Helper_Availability_Advanced_DayBookings extends SLN_Helper_Availability_AbstractDayBookings
{
    /**
     * @return DateTime
     */
    public function getTime($hour = null, $minutes = null)
    {
        if (!isset($hour)) {
            $hour = $this->getDate()->format('H');
        }
        $now = clone $this->getDate();
        $now->setTime($hour, $minutes ? $minutes : 0);

        return $now;
    }

    protected function buildTimeslots()
    {
        // PERFORMANCE OPTIMIZATION: Check cache first with callback pattern
        // Reference: PERFORMANCE_OPTIMIZATION_ANALYSIS.md - Issue #2
        // Impact: 100x faster on subsequent checks
        $cached = SLN_Helper_Availability_Cache::getOrBuildTimeslots(
            $this->getDate(),
            $this->currentBooking,
            function() {
                // This callback only executes on cache MISS
                return $this->buildTimeslotsInternal();
            }
        );
        
        return $cached;
    }
    
    /**
     * Internal method to build timeslots when cache misses
     * @return array
     */
    private function buildTimeslotsInternal()
    {
        SLN_Plugin::addLog('=== START BUILDING TIMESLOTS (ADVANCED MODE) ===');
        SLN_Plugin::addLog(__CLASS__.' - Date: '.$this->getDate()->format('Y-m-d'));
        SLN_Plugin::addLog(__CLASS__.' - isIgnoreServiceBreaks: '.($this->isIgnoreServiceBreaks() ? 'TRUE' : 'FALSE'));
        
        $ret = array();
        $formattedDate = $this->getDate()->format('Y-m-d');

        foreach($this->minutesIntervals as $t) {
            $ret[$t] = array('booking' => array(), 'service' => array(), 'attendant' => array(),'holidays' => array());
            if($this->holidays){
                foreach ($this->holidays as $holiday){
                    $hData = $holiday->getData();
                    if( !$holiday->isValidTime($formattedDate.' '.$t)) $ret[$t]['holidays'][] = $hData;
                }
            }
        }
        $settings = SLN_Plugin::getInstance()->getSettings();
        $bookingOffsetEnabled = $settings->get('reservation_interval_enabled');
        $bookingOffset = $settings->get('minutes_between_reservation');

        /** @var SLN_Wrapper_Booking[] $bookings */
        $bookings = apply_filters('sln_build_timeslots_bookings_list', $this->bookings, $this->date, $this->currentBooking);
        foreach ($bookings as $booking) {
            $bookingServices = $booking->getBookingServices();
            foreach ($bookingServices->getItems() as $bookingService) {
                $times = SLN_Func::filterTimes(
                    $this->minutesIntervals,
                    $bookingService->getStartsAtForDayBooking($this->date),
                    $bookingService->getEndsAtForDayBooking($this->date)
                );
                
                // If service has a break and breaks should be available for other bookings,
                // we need to track which times are break times for services/resources
                $breakTimes = array();
                if ($this->isIgnoreServiceBreaks()) {
                    $breakStart = $bookingService->getBreakStartsAt();
                    $breakEnd = $bookingService->getBreakEndsAt();
                    
                    if ($breakStart && $breakEnd && $breakStart != $breakEnd) {
                        SLN_Plugin::addLog(__CLASS__.' - Booking #'.$booking->getId().' Service #'.$bookingService->getService()->getId().' has break: '.$breakStart->format('H:i').' to '.$breakEnd->format('H:i'));
                        foreach ($times as $t) {
                            if ($t >= $breakStart && $t < $breakEnd) {
                                $breakTimes[] = $t->format('H:i');
                            }
                        }
                        SLN_Plugin::addLog(__CLASS__.' - Break times (service/resource excluded): '.implode(', ', $breakTimes));
                    }
                }
                
                foreach ($times as $time) {
                    $dt = $time;
                    $time = $time->format('H:i');
                    $isBreakTime = in_array($time, $breakTimes);
                    
                    if($booking->getStartsAt() <= $dt && $dt <= $booking->getEndsAt()){
                        if (!in_array($booking->getId(), $ret[$time]['booking']) && apply_filters('sln_build_timeslots_add_booking_to_timeslot', true, $time, $booking, $this->bookings)) {
                            $ret[$time]['booking'][] = $booking->getId();
                        }
                    }
                    
                    // Don't count service as busy during break time
                    if (!$isBreakTime && $bookingService->getService() && apply_filters('sln_build_timeslots_add_service_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                        @$ret[$time]['service'][$bookingService->getService()->getId()]++;
                    }
                    
                    // Don't count resource as busy during break time
                    if (!$isBreakTime && $bookingService->getResource() && apply_filters('sln_build_timeslots_add_resource_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                        if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                            @$ret[$time]['resource'][$bookingService->getResource()->getId()] ++;
                            @$ret[$time]['resource_service'][$bookingService->getResource()->getId()][] = $bookingService->getService()->getId();
                        }
                    }
                }

                if ($bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
                    $offsetStart = $booking->getEndsAt();
                    $offsetEnd = clone $booking->getEndsAt();
                    $offsetEnd->modify('+'.$bookingOffset.' minutes');
                    $times = SLN_Func::filterTimes($this->minutesIntervals, $offsetStart, $offsetEnd);
                    foreach ($times as $time) {
                        $time = $time->format('H:i');
                        if (apply_filters('sln_build_timeslots_add_booking_to_timeslot', true, $time, $booking, $this->bookings)
                        ) {
                            $ret[$time]['booking'][] = $booking->getId();
                            foreach ($bookingServices->getItems() as $bookingService) {
                                if ($bookingService->getService()) {
                                    @$ret[$time]['service'][$bookingService->getService()->getId()]++;
                                }
                                if (!empty($bookingService->getResource()) && apply_filters('sln_build_timeslots_add_resource_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                                    if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                                        @$ret[$time]['resource'][$bookingService->getResource()->getId()] ++;
                                        @$ret[$time]['resource_service'][$bookingService->getResource()->getId()][] = $bookingService->getService()->getId();
                                    }
                                }
                            }
			}
                    }
                }
            }
        }


        $bookings = $this->allBookings;
        foreach ($bookings as $booking) {
            $bookingServices = $booking->getBookingServices();
            foreach ($bookingServices->getItems() as $bookingService) {
                $times = SLN_Func::filterTimes(
                    $this->minutesIntervals,
                    $bookingService->getStartsAt(),
                    $bookingService->getEndsAt()
                );
                
                // If service has a break and breaks should be available for other bookings,
                // exclude break times when marking attendants as busy
                if ($this->isIgnoreServiceBreaks()) {
                    $breakStart = $bookingService->getBreakStartsAt();
                    $breakEnd = $bookingService->getBreakEndsAt();
                    
                    if ($breakStart && $breakEnd && $breakStart != $breakEnd) {
                        $beforeCount = count($times);
                        $times = array_filter($times, function($time) use ($breakStart, $breakEnd) {
                            // Keep times OUTSIDE the break window (attendant available during break)
                            return $time < $breakStart || $time >= $breakEnd;
                        });
                        $afterCount = count($times);
                        SLN_Plugin::addLog(__CLASS__.' - Attendant availability: excluded '.($beforeCount - $afterCount).' break time slots');
                    }
                }
                
                foreach ($times as $time) {
                    $time = $time->format('H:i');
                    if($bookingService->getAttendant() && @!is_array($bookingService->getAttendant())){
                        if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                            @$ret[$time]['attendant'][$bookingService->getAttendant()->getId()]++;
                            @$ret[$time]['attendant_service'][$bookingService->getAttendant()->getId()][] = $bookingService->getService()->getId();
                        }
                    }elseif($bookingService->getAttendant() && @is_array($bookingService->getAttendant())){
                        $service = $bookingService->getService();
                        foreach($bookingService->getAttendant() as $attendant){
                            if(!empty($service) && !empty($attendant) && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)){
                                @$ret[$time]['attendant'][$attendant->getId()]++;
                                @$ret[$time]['attendant_service'][$attendant->getId()][] = $service->getId();
                            }
                        }
                    }
                }

                if ($bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
                    // Offset times are AFTER the service ends, so they're not affected by breaks
                    // (breaks happen DURING the service, not after)
                    $offsetStart = $booking->getEndsAt();
                    $offsetEnd = clone $booking->getEndsAt();
                    $offsetEnd->modify('+'.$bookingOffset.' minutes');
                    $times = SLN_Func::filterTimes($this->minutesIntervals, $offsetStart, $offsetEnd);
                    foreach ($times as $time) {
                        $time = $time->format('H:i');
			if (apply_filters('sln_build_timeslots_add_booking_to_timeslot', true, $time, $booking, $this->bookings)
			) {
                            foreach ($bookingServices->getItems() as $bookingService) {
                                if ($bookingService->getService() && $bookingService->getAttendant()) {
                                    $attendant = $bookingService->getAttendant();
                                    if(!is_array($attendant)){
                                        @$ret[$time]['attendant'][$bookingService->getAttendant()->getId()]++;
                                        @$ret[$time]['attendant_service'][$bookingService->getAttendant()->getId()][] = $bookingService->getService()->getId();
                                    }else{
                                        foreach($attendant as $attObj){
                                            @$ret[$time]['attendant'][$attObj->getId()]++;
                                            @$ret[$time]['attendant_service'][$attObj->getId()][] = $bookingService->getService()->getId();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Debug: Log final timeslot state for key times
        SLN_Plugin::addLog(__CLASS__.' - === FINAL TIMESLOT STATE ===');
        foreach ($ret as $time => $data) {
            if (!empty($data['service']) || !empty($data['attendant'])) {
                SLN_Plugin::addLog(__CLASS__.' - Time '.$time.': Services='.json_encode($data['service']).', Attendants='.json_encode($data['attendant']));
            }
        }
        SLN_Plugin::addLog(__CLASS__.' - === END TIMESLOT STATE ===');
        SLN_Plugin::addLog('=== END BUILDING TIMESLOTS (ADVANCED MODE) ===');
        
        return $ret;
    }
}
