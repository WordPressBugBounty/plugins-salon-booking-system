<?php

use Salon\Util\Date;
use Salon\Util\Time;
use Salon\Util\TimeInterval;

class SLN_Helper_AvailabilityItemNull extends SLN_Helper_AvailabilityItem
{
    public function isValidDate( Date $date, SLN_Wrapper_ServiceInterface $service=null)
    {
        return true;
    }

    public function isValidTime( Time $time, SLN_Wrapper_ServiceInterface $service=null)
    {
        return true;
    }

    public function isValidTimeInterval( TimeInterval $interval)
    {
        return true;
    }

    public function __toString()
    {
        return 'Follow general timetable';
    }
}
