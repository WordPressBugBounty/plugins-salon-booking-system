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

            // Use getWorkTimes() instead of getCachedTimes() so that the scan is
            // NOT gated by the max-booking-window range check.  This matters when a
            // holiday period is longer than the window: getCachedTimes() returns
            // empty for every day (holiday OR out-of-window), so the scanner never
            // finds a valid slot and all assistants appear unavailable.
            // getWorkTimes() only checks working-hour rules + global holidays, which
            // is all we need to decide "does this assistant ever work?".
            //
            // Scan up to 21 *open* days (days the salon is actually open).
            // Holiday/closed days don't count against the limit.
            // A 90-calendar-day ceiling prevents an infinite loop when there is
            // genuinely no availability in the foreseeable future.
            $maxOpenDaysToScan = 21;
            $openDaysScanned   = 0;
            $calendarDaysScanned  = 0;
            $maxCalendarDays      = 90;
            $fromDateTime         = $fromDate->getDateTime(); // fallback for error helper

            while ($openDaysScanned < $maxOpenDaysToScan && $calendarDaysScanned < $maxCalendarDays) {
                $times        = $ah->getWorkTimes($fromDate);
                $fromDateTime = $fromDate->getDateTime();

                if (!empty($times)) {
                    $openDaysScanned++; // only open (non-holiday, non-closed) days count
                    foreach ($times as $time) {
                        $time_obj = Time::create($time);
                        $fromDateTime->setTime($time_obj->getHours(), $time_obj->getMinutes());
                        if (!$attendant->isNotAvailableOnDate($fromDateTime)) { //if available
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
