<?php

use Salon\Util\Date;

class SLN_Wrapper_Booking_Cache extends SLN_Wrapper_Booking_AbstractCache
{
    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->ah     = $plugin->getAvailabilityHelper();
        $this->load();
    }

    public function processBooking(SLN_Wrapper_Booking $booking, $isNew = false)
    {
        // Availability checks made earlier in this request memoized the day as it was
        // BEFORE the change. Rebuilding from that state stores old bookings in
        // free_slots, which the front-end reuses for every future day.
        $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING)->clearBookingCache();
        SLN_Helper_Availability_Cache::clearCache();
        $this->ah->invalidateDayBookings();

        do_action('sln.booking_cache.processBooking', $booking, $isNew);

        return parent::processBooking($booking, $isNew);
    }

    public function getKey(){
        $ret = parent::getKey();
        return apply_filters('sln.booking_cache.getKey', $ret);
    }

    public function getDay(Date $day)
    {
        $ret = parent::getDay($day);

        $filtered = apply_filters('sln.booking_cache.getDay', $ret, $day);
        
        // PHP 8+ compatibility: Ensure filtered result is always an array
        if (!is_array($filtered)) {
            return array(
                'free_slots' => array(),
                'busy_slots' => array(),
                'status' => 'filter_error'
            );
        }
        
        return $filtered;
    }

    public function getFullDays()
    {
        $ret = parent::getFullDays();

        return apply_filters('sln.booking_cache.getFullDays', $ret);
    }

    public function hasFullDay(Date $date){
    	$fullDays = $this->getFullDays();
	    return $fullDays && in_array($date->toString(), $fullDays);
    }

    public function refresh($from, $to){
        do_action('sln.booking_cache.refresh', $from, $to);
        return parent::refresh($from, $to);
    }

	public function processDate(Date $day, $staff_mode = false)
	{
		do_action('sln.booking_cache.processDate', $day, $staff_mode);

		return parent::processDate($day, $staff_mode);
	}
}
