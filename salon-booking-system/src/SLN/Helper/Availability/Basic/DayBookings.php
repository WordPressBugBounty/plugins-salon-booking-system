<?php

class SLN_Helper_Availability_Basic_DayBookings extends SLN_Helper_Availability_AbstractDayBookings
{

    /**
     * @return DateTime
     */
    public function getTime($hour = null, $minutes = null) {
        $now = clone $this->getDate();
        $now->setTime($hour, $minutes ? $minutes : 0);

        return $now;
    }

    protected function buildTimeslots() {
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

        /** @var SLN_Wrapper_Booking[] $bookings */
        $bookings = apply_filters('sln_build_timeslots_bookings_list', $this->bookings, $this->date, $this->currentBooking);
        foreach($bookings as $booking) {
            $time = $booking->getStartsAt()->format('H:i');
	    if (apply_filters('sln_build_timeslots_add_booking_to_timeslot', true, $time, $booking, $this->bookings)) {
		$ret[$time]['booking'][] = $booking->getId();
	    }
            $bookingServices = $booking->getBookingServices();
            foreach ($bookingServices->getItems() as $bookingService) {
		if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_service_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                    @$ret[$time]['service'][$bookingService->getService()->getId()] ++;
                }
                if($bookingService->getAttendant() && @!is_array($bookingService->getAttendant())){
                    if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                        @$ret[$time]['attendant'][$bookingService->getAttendant()->getId()]++;
                        @$ret[$time]['attendant_service'][$bookingService->getAttendant()->getId()][] = $bookingService->getService()->getId();
                    }
                }elseif($bookingService->getAttendant() && @is_array($bookingService->getAttendant())){
                    $service = $bookingService->getService();
                    foreach($bookingService->getAttendant() as $attendant){
                        if($service && $attendant && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)){
                            @$ret[$time]['attendant'][$attendant->getId()]++;
                            @$ret[$time]['attendant_service'][$attendant->getId()][] = $service->getId();
                        }
                    }
                }
		if (!empty($bookingService->getResource()) && apply_filters('sln_build_timeslots_add_resource_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                    if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                        @$ret[$time]['resource'][$bookingService->getResource()->getId()] ++;
                        @$ret[$time]['resource_service'][$bookingService->getResource()->getId()][] = $bookingService->getService()->getId();
                    }
                }
            }
        }

        $bookings = $this->allBookings;
        foreach($bookings as $booking) {
            $time = $booking->getStartsAt()->format('H:i');
            $bookingServices = $booking->getBookingServices();
            foreach ($bookingServices->getItems() as $bookingService) {
                if($bookingService->getAttendant() && @!is_array($bookingService->getAttendant())){
                    if ($bookingService->getService() && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)) {
                        @$ret[$time]['attendant'][$bookingService->getAttendant()->getId()]++;
                        @$ret[$time]['attendant_service'][$bookingService->getAttendant()->getId()][] = $bookingService->getService()->getId();
                    }
                }elseif($bookingService->getAttendant() && @is_array($bookingService->getAttendant())){
                    $service = $bookingService->getService();
                    foreach($bookingService->getAttendant() as $attendant){
                        if($service && $attendant && apply_filters('sln_build_timeslots_add_attendant_to_timeslot', true, $time, $bookingService, $booking, $this->bookings)){
                            @$ret[$time]['attendant'][$attendant->getId()]++;
                            @$ret[$time]['attendant_service'][$attendant->getId()][] = $service->getId();
                        }
                    }
                }
            }
        }

        return $ret;
    }
}
