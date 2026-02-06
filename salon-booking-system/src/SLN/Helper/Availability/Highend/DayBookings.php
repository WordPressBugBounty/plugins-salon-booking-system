<?php

class SLN_Helper_Availability_Highend_DayBookings extends SLN_Helper_Availability_AbstractDayBookings
{
    protected $ignoreServiceBreaks = false;
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
        // Impact: 100x faster on subsequent checks (5-10s → 10-50ms)
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
        $ret = array();
        $formattedDate = $this->getDate()->format('Y-m-d');

        foreach($this->minutesIntervals as $t) {
            $ret[$t] = array(
                'booking'   => array(),
                'service'   => array(),
                'attendant' => array(),
                'holidays'  => array(),
                'break'     => array(),
            );
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
        
        // Cache nested bookings setting to avoid repeated calls in loops (performance)
        $nestedBookingsEnabled = $settings->isNestedBookingsEnabled();

        /** @var SLN_Wrapper_Booking[] $bookings */
        $bookings = apply_filters('sln_build_timeslots_bookings_list', $this->bookings, $this->date, $this->currentBooking);
        foreach ($bookings as $booking) {
            $bookingServices = $booking->getBookingServices();
            foreach ($bookingServices->getItems() as $bookingService) {
                $breakStart = $bookingService->getBreakStartsAt();
                $breakEnd = $bookingService->getBreakEndsAt();
                $hasBreak = $breakStart && $breakEnd && $breakStart != $breakEnd;
                $times = SLN_Func::filterTimes(
                    $this->minutesIntervals,
                    $bookingService->getStartsAtForDayBooking($this->date),
                    $bookingService->getEndsAtForDayBooking($this->date)
                );
                foreach ($times as $time) {
                    $key = $time->format('H:i');
                    
                    // PHP 8+ compatibility: Ensure $ret[$key] is initialized FIRST
                    if (!isset($ret[$key])) {
                        $ret[$key] = array(
                            'booking'   => array(),
                            'service'   => array(),
                            'attendant' => array(),
                            'holidays'  => array(),
                            'break'     => array(),
                        );
                    }
                    
                    $isWithinBooking = $booking->getStartsAt() <= $time && $time <= $booking->getEndsAt();
                    $isDuringBreak = $hasBreak && $time >= $breakStart && $time < $breakEnd;
                    $isOutsideBreak = !$hasBreak || ($time < $breakStart || $time >= $breakEnd);
                    
                    if ($hasBreak && $isDuringBreak && $nestedBookingsEnabled) {
                        // Mark as available break slot (allows nested bookings)
                        $ret[$key]['break'][] = $booking->getId();
                    }
                    
                    if($isWithinBooking){
                        // Add booking to slot if:
                        // 1. Outside break period, OR
                        // 2. During break but nested bookings NOT allowed (slot should be busy)
                        $shouldAddBooking = $isOutsideBreak || ($isDuringBreak && !$nestedBookingsEnabled);
                        
                        if ($shouldAddBooking && apply_filters('sln_build_timeslots_add_booking_to_timeslot', true, $key, $booking, $this->bookings)) {
                            $ret[$key]['booking'][] = $booking->getId();
                        } elseif ($hasBreak && $nestedBookingsEnabled) {
                            // Only mark as available break if nested bookings ARE allowed
                            $ret[$key]['break'][] = $booking->getId();
                        }
                    }
                    if ($isOutsideBreak) {
                        if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_service_to_timeslot', true, $key, $bookingService, $booking, $this->bookings)) {
                            $serviceId = $bookingService->getService()->getId();
                            $ret[$key]['service'][$serviceId] = isset($ret[$key]['service'][$serviceId]) ? $ret[$key]['service'][$serviceId] + 1 : 1;
                        }
                        if (!empty($bookingService->getResource()) && apply_filters('sln_build_timeslots_add_resource_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                            if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                                $resourceId = $bookingService->getResource()->getId();
                                $ret[$key]['resource'][$resourceId] = isset($ret[$key]['resource'][$resourceId]) ? $ret[$key]['resource'][$resourceId] + 1 : 1;
                                $ret[$key]['resource_service'][$resourceId][] = $bookingService->getService()->getId();
                            }
                        }
                    } elseif ($hasBreak && $nestedBookingsEnabled) {
                        $ret[$key]['break'][] = $booking->getId();
                    }
                }

                if ($bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
                    $offsetStart = $bookingService->getEndsAt();
                    $offsetEnd = clone  $bookingService->getEndsAt();
                    $offsetEnd = $offsetEnd->modify('+'.$bookingOffset.' minutes');
                    $times = SLN_Func::filterTimes($this->minutesIntervals, $offsetStart, $offsetEnd);
                    foreach ($times as $time) {
                        $time = $time->format('H:i');
                        
                        // PHP 8+ compatibility: Ensure $ret[$time] is initialized
                        if (!isset($ret[$time])) {
                            $ret[$time] = array(
                                'booking'   => array(),
                                'service'   => array(),
                                'attendant' => array(),
                                'holidays'  => array(),
                                'break'     => array(),
                            );
                        }
                        
			if (apply_filters('sln_build_timeslots_add_booking_to_timeslot', true, $time, $booking, $this->bookings)
			) {
			    $ret[$time]['booking'][] = $booking->getId();
                            foreach ($bookingServices->getItems() as $bookingService) {
                                if ($bookingService->getService()) {
                                    $serviceId = $bookingService->getService()->getId();
                                    $ret[$time]['service'][$serviceId] = isset($ret[$time]['service'][$serviceId]) ? $ret[$time]['service'][$serviceId] + 1 : 1;
                                }
                                if ($bookingService->getResource() && apply_filters('sln_build_timeslots_add_resource_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                                    if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                                        $resourceId = $bookingService->getResource()->getId();
                                        $ret[$time]['resource'][$resourceId] = isset($ret[$time]['resource'][$resourceId]) ? $ret[$time]['resource'][$resourceId] + 1 : 1;
                                        $ret[$time]['resource_service'][$resourceId][] = $bookingService->getService()->getId();
                                    }
                                }
                            }
			}
                    }
                }
            }
        }

        $bookings = $this->allBookings;
        
        // FIX: For multi-attendant bookings, mark ALL attendants as busy for the ENTIRE booking duration
        // UPDATED: Now respects break periods (matches version 10.29.6 behavior)
        // This prevents availability conflicts when different services in the same booking have different attendants
        // Example: Booking with Service A (08:30-09:00, Attendant A) and Service B (09:00-09:30, Attendant B)
        // Both attendants should be marked as busy from 08:30-09:30 to prevent double-booking the same customer
        // BUT: Break periods are NEVER marked as busy for attendants (backward compatible with 10.29.6)
        // In 10.29.6: Break times were excluded from resource/attendant busy marking
        // This fix maintains that behavior while adding multi-attendant protection
        foreach ($bookings as $booking) {
            // Collect all unique attendants assigned to this booking
            $allBookingAttendants = array();
            $bookingServices = $booking->getBookingServices();
            foreach ($bookingServices->getItems() as $bookingService) {
                $serviceAttendant = $bookingService->getAttendant();
                if ($serviceAttendant && !is_array($serviceAttendant)) {
                    $allBookingAttendants[$serviceAttendant->getId()] = $serviceAttendant;
                } elseif (is_array($serviceAttendant)) {
                    foreach ($serviceAttendant as $att) {
                        $allBookingAttendants[$att->getId()] = $att;
                    }
                }
            }
            
            // If this booking has multiple attendants, mark ALL of them as busy for the entire booking duration
            if (count($allBookingAttendants) > 1) {
                // Process each service separately to respect break periods
                foreach ($bookingServices->getItems() as $bookingService) {
                    $breakStart = $bookingService->getBreakStartsAt();
                    $breakEnd = $bookingService->getBreakEndsAt();
                    $hasBreak = $breakStart && $breakEnd && $breakStart != $breakEnd;
                    
                    $bookingTimes = SLN_Func::filterTimes(
                        $this->minutesIntervals,
                        $bookingService->getStartsAt(),
                        $bookingService->getEndsAt()
                    );
                    
                    foreach ($bookingTimes as $time) {
                        $key = $time->format('H:i');
                        
                        // CRITICAL FIX: Skip break periods (regardless of nested bookings setting)
                        // In version 10.29.6, break periods were NEVER marked as busy for resources/attendants
                        // This maintains backward compatibility while respecting the nested bookings feature
                        $isDuringBreak = $hasBreak && $time >= $breakStart && $time < $breakEnd;
                        if ($isDuringBreak) {
                            continue; // Don't mark as busy during breaks (matches 10.29.6 behavior)
                        }
                        
                        if (!isset($ret[$key])) {
                            $ret[$key] = array(
                                'booking'   => array(),
                                'service'   => array(),
                                'attendant' => array(),
                                'holidays'  => array(),
                                'break'     => array(),
                            );
                        }
                        
                        // Mark all attendants as busy (except during breaks)
                        foreach ($allBookingAttendants as $attendantId => $attendant) {
                            $previousCount = isset($ret[$key]['attendant'][$attendantId]) ? $ret[$key]['attendant'][$attendantId] : 0;
                            $ret[$key]['attendant'][$attendantId] = $previousCount + 1;
                        }
                    }
                }
            }
        }
        
        // Original attendant processing (per-service granular tracking)
        foreach ($bookings as $booking) {
            $bookingServices = $booking->getBookingServices();
            foreach ($bookingServices->getItems() as $bookingService) {
                $breakStart = $bookingService->getBreakStartsAt();
                $breakEnd = $bookingService->getBreakEndsAt();
                $hasBreak = $breakStart && $breakEnd && $breakStart != $breakEnd;
                $times = SLN_Func::filterTimes(
                    $this->minutesIntervals,
                    $bookingService->getStartsAt(),
                    $bookingService->getEndsAt()
                );
                foreach ($times as $time) {
                    $key = $time->format('H:i');
                    
                    // PHP 8+ compatibility: Ensure $ret[$key] is initialized
                    if (!isset($ret[$key])) {
                        $ret[$key] = array(
                            'booking'   => array(),
                            'service'   => array(),
                            'attendant' => array(),
                            'holidays'  => array(),
                            'break'     => array(),
                        );
                    }
                    
                    $isOutsideBreak = !$hasBreak || ($time < $breakStart || $time >= $breakEnd);
                    
                    if ($hasBreak && !$isOutsideBreak && $nestedBookingsEnabled) {
                        $ret[$key]['break'][] = $booking->getId();
                    }
                    
                    // Add attendant to slot if:
                    // 1. Outside break period, OR
                    // 2. During break but nested bookings NOT allowed (slot should be busy)
                    $shouldAddAttendant = $isOutsideBreak || (!$isOutsideBreak && !$nestedBookingsEnabled);
                    
                    if ($shouldAddAttendant) {
                        if($bookingService->getAttendant() && !@is_array($bookingService->getAttendant())){
                            if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $key, $bookingService, $booking, $this->bookings)) {
                                $attendantId = $bookingService->getAttendant()->getId();
                                $ret[$key]['attendant'][$attendantId] = isset($ret[$key]['attendant'][$attendantId]) ? $ret[$key]['attendant'][$attendantId] + 1 : 1;
                                $ret[$key]['attendant_service'][$attendantId][] = $bookingService->getService()->getId();
                            }
                        }elseif(@is_array($bookingService->getAttendant())){
                            $service = $bookingService->getService();
                            foreach($bookingService->getAttendant() as $attendant){
                                if($service && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $key, $bookingService, $booking, $this->bookings)){
                                    $attendantId = $attendant->getId();
                                    $ret[$key]['attendant'][$attendantId] = isset($ret[$key]['attendant'][$attendantId]) ? $ret[$key]['attendant'][$attendantId] + 1 : 1;
                                    $ret[$key]['attendant_service'][$attendantId][] = $service->getId();
                                }
                            }
                        }
                    }
                }

                if ($bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
                    $offsetStart = $bookingService->getEndsAt();
                    $offsetEnd = clone  $bookingService->getEndsAt();
                    $offsetEnd = $offsetEnd->modify('+'.$bookingOffset.' minutes');
                    $times = SLN_Func::filterTimes($this->minutesIntervals, $offsetStart, $offsetEnd);
                    foreach ($times as $time) {
                        $time = $time->format('H:i');
                        
                        // PHP 8+ compatibility: Ensure $ret[$time] is initialized
                        if (!isset($ret[$time])) {
                            $ret[$time] = array(
                                'booking'   => array(),
                                'service'   => array(),
                                'attendant' => array(),
                                'holidays'  => array(),
                                'break'     => array(),
                            );
                        }
                        
		            	if (apply_filters('sln_build_timeslots_add_booking_to_timeslot', true, $time, $booking, $this->allBookings)
			            ) {
                            foreach ($bookingServices->getItems() as $bookingService) {
                                if ($bookingService->getService() && $bookingService->getAttendant()) {
                                    $attendant = $bookingService->getAttendant();
                                    if(!is_array($attendant)){
                                        $attendantId = $bookingService->getAttendant()->getId();
                                        $ret[$time]['attendant'][$attendantId] = isset($ret[$time]['attendant'][$attendantId]) ? $ret[$time]['attendant'][$attendantId] + 1 : 1;
                                        $ret[$time]['attendant_service'][$attendantId][] = $bookingService->getService()->getId();
                                    }else{
                                        foreach($attendant as $attObj){
                                            $attendantId = $attObj->getId();
                                            $ret[$time]['attendant'][$attendantId] = isset($ret[$time]['attendant'][$attendantId]) ? $ret[$time]['attendant'][$attendantId] + 1 : 1;
                                            $ret[$time]['attendant_service'][$attendantId][] = $bookingService->getService()->getId();
                                        }
                                    }
                                }
                            }
			}
                    }
                }
            }
        }
        
        return $ret;
    }
}
