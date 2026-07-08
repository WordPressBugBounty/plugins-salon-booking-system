<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
use Salon\Util\Date;
use Salon\Util\Time;

class SLN_Shortcode_Salon_AttendantHelper
{
    /**
     * @param                               $plugin
     * @param SLN_Wrapper_Booking_Service[] $services
     * @param SLN_Helper_Availability       $ah
     * @param SLN_Wrapper_Attendant         $attendant
     * @return bool
     */
    public static function validateItem($services, $ah, $attendant)
    {
        $plugin = SLN_Plugin::getInstance();

        if (!$plugin->getSettings()->isFormStepsAltOrder()) {
            foreach ($services as $bookingService) {
                if (!$bookingService->getService()->isAttendantsEnabled()) {
                    continue;
                }

                return $ah->validateAttendant(
                    $attendant,
                    $bookingService->getStartsAt(),
                    $bookingService->getTotalDuration(),
                    $bookingService->getService(),
                    $bookingService->getBreakStartsAt(),
                    $bookingService->getBreakEndsAt()
                );
            }
        } else {
            $hb = $ah->getHoursBeforeHelper();
            $fromDate = Date::create($hb->getFromDate());

            // Use getWorkTimes() instead of getCachedTimes() so the scan is NOT gated by
            // the booking-window range check or the booking cache engine (lightweight:
            // salon hours + global holidays only).
            //
            // Scan the full booking time range (getCountDays). A secondary cap on
            // non-holiday open days (21) preserves the performance guard from SBP-2233
            // without stopping early when an assistant is on a long holiday: salon-closed
            // days and assistant-holiday days do not increment that counter.
            $maxCalendarDays     = $hb->getCountDays();
            $maxOpenDaysToScan   = 21;
            $openDaysScanned     = 0;
            $calendarDaysScanned = 0;
            $fromDateTime        = $fromDate->getDateTime(); // fallback for error helper

            while ($calendarDaysScanned < $maxCalendarDays && $openDaysScanned < $maxOpenDaysToScan) {
                $times        = $ah->getWorkTimes($fromDate);
                $fromDateTime = $fromDate->getDateTime();

                if (!empty($times)) {
                    $isAttendantHolidayDay = !$attendant->getHolidayItems()->isValidDate($fromDateTime);

                    if (!$isAttendantHolidayDay) {
                        $openDaysScanned++;
                    }

                    foreach ($times as $time) {
                        $time_obj = Time::create($time);
                        $fromDateTime->setTime($time_obj->getHours(), $time_obj->getMinutes());
                        if (!$attendant->isNotAvailableOnDate($fromDateTime)) {
                            return;
                        }
                    }
                }

                $fromDate = $fromDate->getNextDate();
                $calendarDaysScanned++;
            }
            return SLN_Helper_Availability_ErrorHelper::doAttendantNotAvailable($attendant, $fromDateTime); //if available timeslot wasn't found
        }

        return false;
    }

    public static function renderItem(
        $size,
        $errors = null,
        SLN_Wrapper_AttendantInterface $attendant = null,
        SLN_Wrapper_ServiceInterface $service = null,
	$isDefaultChecked = null, array $services = array()
    ) {
        $plugin = SLN_Plugin::getInstance();
        $t      = $plugin->templating();
        $view   = 'shortcode/_attendants_item_'.intval($size);

        if (!$attendant) {
            $attendant = new SLN_Wrapper_Attendant(
                (object)array('ID' => '', 'post_title' => __('Choose an assistant for me','salon-booking-system'),'post_type'=>'sln_attendant')
            );
        }

        if (isset($service)) {
            $elemId = SLN_Form::makeID('sln[attendants]['.$service->getId().']['.$attendant->getId().']');
            $field  = 'sln[attendants]['.$service->getId().']';
        } else {
            $elemId = SLN_Form::makeID('sln[attendant]['.$attendant->getId().']');
            $field  = 'sln[attendant]';
        }
        $settings = array();
        if ($errors) {
            $settings['attrs']['disabled'] = 'disabled';
        }
        $tplErrors = $t->loadView('shortcode/_errors_area', compact('errors', 'size'));
        $thumb     = has_post_thumbnail($attendant->getId()) ? get_the_post_thumbnail(
            $attendant->getId(),
            'thumbnail'
        ) : '';
        $isChecked = $plugin->getBookingBuilder()->hasAttendant($attendant) ? $plugin->getBookingBuilder()->hasAttendant($attendant) : $isDefaultChecked;
        $isChecked = is_null($errors) ? $isChecked : false;

        return $t->loadView(
            $view,
            compact('field', 'isChecked', 'attendant', 'elemId', 'thumb', 'tplErrors', 'settings', 'service', 'services')
        );
    }
}
