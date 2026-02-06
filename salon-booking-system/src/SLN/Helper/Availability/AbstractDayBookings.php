<?php
// phpcs:ignoreFile WordPress.PHP.DevelopmentFunctions.error_log_print_r


abstract class SLN_Helper_Availability_AbstractDayBookings
{
    protected $currentBooking;
    protected $bookings;
    protected $allBookings;
    protected $holidays;
    protected $timeslots;
    protected $date;
    protected $interval;
    protected $minutesIntervals;
    protected $ignoreServiceBreaks = true;

    /**
     * @return array
     */
    abstract protected function buildTimeslots();

    /**
     * @return DateTime
     */
    abstract public function getTime($hour = null, $minutes = null);


    public function __construct(DateTime $date, SLN_Wrapper_Booking $booking = null)
    {
        $interval = SLN_Plugin::getInstance()->getSettings()->getInterval();
        $holidays = SLN_Plugin::getInstance()->getSettings()->getHolidayItems();

        $this->minutesIntervals = SLN_Func::getMinutesIntervals(5);
        $this->date = $date;
        $this->currentBooking = $booking;
        $this->bookings = $this->buildBookings();
        $this->allBookings = $this->buildAllBookings();
        $this->holidays = $this->buildHolidays($holidays);
        $this->timeslots = $this->buildTimeslots();
    }

    private function buildHolidays($holidays){
        $ret = array();
        foreach ($holidays->toArray() as $holiday) {
            if ( $this->date instanceof DateTime || $this->date instanceof DateTimeImmutable  ) {
            $date = $this->date->format( 'Y-m-d' );
        } elseif ( $this->date instanceof Date ) {
            $date = $this->date->toString();
        }

            if($holiday->isDateContained($date)) $ret[] = $holiday;
        }

        return $ret;
    }

    private function buildBookings()
    {
        /** @var SLN_Repository_BookingRepository $repo */
        $repo = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_BOOKING);
        $ret = $repo->getForAvailability($this->date, $this->currentBooking);

        SLN_Plugin::addLog(__CLASS__.' - buildBookings('.$this->date->format('Y-m-d').')');
        foreach ($ret as $b) {
            SLN_Plugin::addLog(' - '.$b->getId());
        }

        return $ret;
    }

    private function buildAllBookings()
    {
        /** @var SLN_Repository_BookingRepository $repo */
        $repo = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_BOOKING);
        $ret = $repo->getForAvailabilityAllBookings($this->date, $this->currentBooking);

        SLN_Plugin::addLog(__CLASS__.' - buildBookings('.$this->date->format('Y-m-d').')');
        foreach ($ret as $b) {
            SLN_Plugin::addLog(' - '.$b->getId());
        }

        return $ret;
    }

    public function isIgnoreServiceBreaks()
    {
        return $this->ignoreServiceBreaks;
    }

    public function countBookingsByDay()
    {
        return count($this->bookings);
    }

    /**
     * @return SLN_Wrapper_Booking[]
     */
    public function getBookingsByHour($hour = null, $minutes = null)
    {
        if (!isset($hour)) {
            $hour = $this->getDate()->format('H');
        }
        $now = clone $this->getDate();
        $now->setTime($hour, $minutes ? $minutes : 0);
        $time = $now->format('H:i');
        $ret = array();
        $bookings = isset($this->timeslots[$time]['booking']) ? $this->timeslots[$time]['booking'] : array();
        foreach ($bookings as $bId) {
            $ret[] = new SLN_Wrapper_Booking($bId);
        }

        if (!empty($ret)) {
            SLN_Plugin::addLog(__CLASS__.' - checking hour('.$hour.')');
            SLN_Plugin::addLog(__CLASS__.' - found('.count($ret).')');
            foreach ($ret as $b) {
                SLN_Plugin::addLog(
                    ' - '.$b->getId().' => '.$b->getStartsAt()->format('H:i').' - '.$b->getEndsAt()->format('H:i')
                );
            }
        } else {
            SLN_Plugin::addLog(__CLASS__.' - checking hour('.$hour.') EMPTY');
        }

        return $ret;
    }

    public function countBookingsByHour($hour = null, $minutes = null)
    {
        if (!isset($hour)) {
            $hour = $this->getDate()->format('H');
        }
        $now = clone $this->getDate();
        $now->setTime($hour, $minutes ? $minutes : 0);
        $time = $now->format('H:i');
        
        // Don't count bookings in break slots (where nested bookings are allowed)
        if (isset($this->timeslots[$time]['break']) && !empty($this->timeslots[$time]['break'])) {
            SLN_Plugin::addLog(sprintf('[countBookingsByHour] %s is a break slot - returning 0 (allows nested)', $time));
            return 0;
        }
        
        $bookings = isset($this->timeslots[$time]['booking']) ? $this->timeslots[$time]['booking'] : array();
        return count($bookings);
    }

    public function countAttendantsByHour($hour = null, $minutes = null)
    {
        SLN_Plugin::addLog(get_class($this).' - count attendants by hour('.$hour.') minutes('.$minutes.')');
        $now = $this->getTime($hour, $minutes);
        $time = $now->format('H:i');
        $ret = $this->timeslots[$time]['attendant'];
        SLN_Plugin::addLog(print_r($ret, true));

        return $ret;
    }

    public function getAttendantServiceIdsByHour($attendant_id, $hour = null, $minutes = null){
        $now = $this->getTime($hour, $minutes);
        $time = $now->format('H:i');
        $ret = $this->timeslots[$time]['attendant_service'][$attendant_id];
        return $ret;
    }

    public function countServicesByHour($hour = null, $minutes = null)
    {
        SLN_Plugin::addLog(get_class($this).' - count services by hour('.$hour.') minutes('.$minutes.')');
        $now = $this->getTime($hour, $minutes);
        $time = $now->format('H:i');
        $ret = $this->timeslots[$time]['service'];
        SLN_Plugin::addLog(print_r($ret, true));

        return $ret;
    }

    public function countResourcesByHour($hour = null, $minutes = null)
    {
        SLN_Plugin::addLog(get_class($this).' - count resources by hour('.$hour.') minutes('.$minutes.')');
        $now = $this->getTime($hour, $minutes);
        $time = $now->format('H:i');
        $ret = isset($this->timeslots[$time]['resource']) ? $this->timeslots[$time]['resource'] : array();
        SLN_Plugin::addLog(print_r($ret, true));

        return $ret;
    }

    public function getResourceServiceIdsByHour($resource_id, $hour = null, $minutes = null){
        $now = $this->getTime($hour, $minutes);
        $time = $now->format('H:i');
        $ret = $this->timeslots[$time]['resource_service'][$resource_id];
        return $ret;
    }

    /**
     * @return DateTime
     */
    protected function getDate()
    {
        return $this->date;
    }

    public function setTime($hour, $minutes)
    {
        $this->getDate()->setTime($hour, $minutes);
    }

    /**
     * @return SLN_Wrapper_Booking[]
     */
    public function getBookings()
    {
        return $this->bookings;
    }

    public function getMinutesIntervals()
    {
        return $this->minutesIntervals;
    }

    public function getTimeslots()
    {
        return $this->timeslots;
    }

    public function isBreakSlot(\DateTimeInterface $time)
    {
        $now = $this->getTime($time->format('H'), $time->format('i'));
        $key = $now->format('H:i');
        if (!isset($this->timeslots[$key]['break'])) {
            return false;
        }

        return !empty($this->timeslots[$key]['break']);
    }

}
