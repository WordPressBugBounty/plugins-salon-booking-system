<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

use Salon\Util\Date;
use Salon\Util\Time;

class SLN_Action_Ajax_CheckDateAlt extends SLN_Action_Ajax_CheckDate
{
	/**
	 * @param array        $services
	 * @param SLN_DateTime $datetime
	 *
	 * @return bool
	 */
	private function checkDayServicesAndAttendants($services, $datetime) {
        $bb  = $this->plugin->getBookingBuilder();
		$bookingServices = SLN_Wrapper_Booking_Services::build($services, $datetime, 0, $bb->getCountServices());
		$date            = Date::create($datetime->format('Y-m-d'));
		foreach ($bookingServices->getItems() as $bookingService) {
			/** @var SLN_Helper_AvailabilityItems $avServiceItems */
			$avServiceItems = $bookingService->getService()->getAvailabilityItems();
			if(!$avServiceItems->isValidDate($date)) {
				return false;
			}

			$attendant = $bookingService->getAttendant();
			
			// SMART AVAILABILITY: Skip attendant check if false (auto-assignment marker)
			// This allows checking ALL assistants at time-slot level
			if ($attendant === false) {
				continue;
			}
			
			if (!empty($attendant)) {
				/** @var SLN_Helper_AvailabilityItems $avAttendantItems */
                if(!is_array($attendant)){
                    $avAttendantItems = $attendant->getAvailabilityItems();
                    if(!$avAttendantItems->isValidDate($date, $bookingService->getService())) {
                        return false;
                    }
                }else{
                    foreach($attendant as $att){
                        $avAttendantItems = $att->getAvailabilityItems();
                        if(!$avAttendantItems->isValidDate($date, $bookingService->getService())){
                            return false;
                        }
                    }
                }
			}
		}

		return true;
	}

    public function getIntervalsArray($timezone = '') {
        if ($this->isAdmin()) {
            return parent::getIntervalsArray();
        }
        $fullDays = array();
        $plugin = $this->plugin;
        $ah   = $plugin->getAvailabilityHelper();
        $bc = $plugin->getBookingCache();
        $hb = $ah->getHoursBeforeHelper();
        $dateTimeLog = SLN_Helper_Availability_AdminRuleLog::getInstance();

        $bb = $plugin->getBookingBuilder();
        $bservices = $bb->getAttendantsIds();
        $this->setDuration(new Time($bb->getDuration()));
        $intervals = parent::getIntervals();
        $intervalsArray = array();
        
        // Check if Smart Availability is enabled with "Choose assistant for me"
        $isSmartAvailability = $this->isSmartAvailabilityMode($bservices);
        
        // PHP 8+ compatibility: Ensure getDates returns array
        $dates = $intervals->getDates();
        if (!is_array($dates)) {
            $dates = array();
        }
        
        foreach($dates as $k => $v) {
            $available = false;
            $tmpDate   = new SLN_DateTime($v->getDateTime());
            $dateLog = $v->getDateTime()->format('Y-m-d');
            $dateTimeLog->addDateLog( $dateLog, $this->checkDayServicesAndAttendants($bservices, $tmpDate), __( 'The attendant is unavailable on this day', 'salon-booking-system' ) );
            if ($this->checkDayServicesAndAttendants($bservices, $tmpDate)) {
	            $ah->setDate($tmpDate, $this->booking);
	            
	            // SMART AVAILABILITY: Use all attendants' availability when appropriate
	            if ($isSmartAvailability) {
	                $times = $this->getAllAttendantsAvailableTimes(Date::create($tmpDate), $bservices);
	            } else {
	                // PHP 8+ compatibility: Safely access array elements
	                $dayData = $bc->getDay(Date::create($tmpDate));
	                // Double-check that free_slots is actually an array, not a string
	                $times = (is_array($dayData) && isset($dayData['free_slots']) && is_array($dayData['free_slots'])) ? $dayData['free_slots'] : array();
	            }
	            
	            foreach ($times as $timeKey => $timeValue) {
	                // Handle both formats: cache returns strings, getAllAttendantsAvailableTimes returns objects
	                if (is_object($timeValue)) {
	                    $time = $timeKey; // Key is the time string like "12:00"
	                } else {
	                    $time = $timeValue; // Value is the time string (from cache)
	                }
	                
                    $d = $v->getDateTime()->format('Y-m-d');
                    $tmpDateTime = new SLN_DateTime("$d $time");
                    if(!$hb->check($tmpDateTime)) {
                        continue;
                    }
		            $errors = $this->checkDateTimeServicesAndAttendants($bservices, $tmpDateTime);
		            if (empty($errors)) {
			            $available = true;
			            break;
		            }
	            }
            }
            $dateTimeLog->addDateLog( $dateLog, $available, __( 'There are no free time slots on this day', 'salon-booking-system' ) );

            if (!$available) {
                $fullDays[] = $v->getDateTime();
            } else {
                $intervalsArray['dates'][$k] = $v;
            }
        }

        // PHP 8+ compatibility: Check if dates is an array and not empty
        if (!isset($intervalsArray['dates']) || !is_array($intervalsArray['dates']) || empty($intervalsArray['dates'])) {
            $intervalsArray = $intervals->toArray($timezone);
            $intervalsArray['dates'] = array();
            $intervalsArray['times'] = array();
            return $intervalsArray;
        }

        $suggestedDate = $intervals->getSuggestedDate()->format('Y-m-d');
        
        // PHP 8+ compatibility: Ensure dates is an array before array operations
        // FIX: SuggestedDate 2026 Bug - Layer 2 protection (safety net)
        // Don't call setDatetime() again to avoid recursive buggy calculation
        if (is_array($intervalsArray['dates']) && !empty($intervalsArray['dates'])) {
            // Check if suggested date exists in available dates
            $availableDates = array_map(function ($date) { 
                return $date->getDateTime()->format('Y-m-d'); 
            }, $intervalsArray['dates']);
            
            if (!in_array($suggestedDate, $availableDates)) {
                // Suggested date is invalid - use first available date
                $firstAvailableDate = reset($intervalsArray['dates'])->getDateTime();
                $suggestedDate = $firstAvailableDate->format('Y-m-d');
                
                // Don't call setDatetime() again - directly update intervals object
                // This prevents re-running the potentially buggy calculation
                $intervals->setSuggestedDate($firstAvailableDate);
                
                // Also update the array that will be returned to frontend
                // This ensures correct values even if calculation was wrong
                $intervalsArray['suggestedDate'] = $plugin->format()->date($firstAvailableDate);
                $intervalsArray['suggestedYear'] = $firstAvailableDate->format('Y');
                $intervalsArray['suggestedMonth'] = $firstAvailableDate->format('m');
                $intervalsArray['suggestedDay'] = $firstAvailableDate->format('d');
                $intervalsArray['universalSuggestedDate'] = $suggestedDate;
            }
        }
        $tmpDate = new SLN_DateTime($suggestedDate);

        $ah->setDate($tmpDate, $this->booking);
        
        // PHP 8+ compatibility: Safely get first service from bservices array
        $firstService = null;
        if (!empty($bservices)) {
            $firstItem = reset($bservices);
            if (is_array($firstItem) && isset($firstItem['service'])) {
                $firstService = $plugin->createService($firstItem['service']);
            }
        }
        SLN_Helper_AvailabilityDebugger::logSessionStart($tmpDate, $firstService, 'Frontend CheckDateAlt');
        SLN_Helper_AvailabilityDebugger::logExistingBookings($tmpDate, $ah->getDayBookings()->getBookings());
        
        // SMART AVAILABILITY: Use all attendants' availability for times as well
        if ($isSmartAvailability) {
            $times = $this->getAllAttendantsAvailableTimes(Date::create($tmpDate), $bservices, $this->duration);
        } else {
            $times = $ah->getCachedTimes(Date::create($tmpDate), $this->duration);
        }
        
        // PHP 8+ compatibility: Ensure $times is always an array
        if (!is_array($times)) {
            $times = array();
        }
        
        SLN_Helper_AvailabilityDebugger::logAvailableTimes($times, 'From cache/getAllAttendants (before validation)');
        SLN_Helper_AvailabilityDebugger::logTimeslots($tmpDate, $ah->getDayBookings()->getTimeslots());

        //for SLB_API_Mobile purposes
        $customTimeFormat = $_GET['time_format'] ?? false;

        foreach ($times as $k => $t) {
            // Handle both string keys and numeric keys
            if (is_object($t) && method_exists($t, 'format')) {
                $time = $t->format('H:i');
            } else {
                continue;
            }
            
            $tmpDateTime = new SLN_DateTime("$suggestedDate $time");
            $ah->setDate($tmpDateTime, $this->booking);
            $errors = $this->checkDateTimeServicesAndAttendants($bservices, $tmpDateTime, true);
            
            if (empty($errors)) {
                $intervalsArray['times'][$k] = $t;
                $dateTimeLog->addLog( $t->format('H:i'), empty($errors), __( 'Time is free for services and attendants.', 'salon-booking-system') );
                SLN_Helper_AvailabilityDebugger::logSlotValidation($t, true, 'Passed validation');
            }else{
                $errorMsg = is_array($errors) ? implode(' | ', array_map(function($err){ return is_array($err) ? reset($err) : $err; }, $errors)) : $errors;
                SLN_Plugin::addLog(sprintf('[DateStep] filtered time %s: %s', $t->format('H:i'), $errorMsg));
                SLN_Helper_AvailabilityDebugger::logSlotValidation($t, false, $errorMsg);
                $dateTimeLog->addArrayErrors( $t->format('H:i'), $errors );
            }
        }
        
        // Log final frontend response
        SLN_Helper_AvailabilityDebugger::logFrontendResponse($intervalsArray);

        $intervalsArray['suggestedTime'] = $intervals->getSuggestedDate()->format($customTimeFormat ?: 'H:i');

        // PHP 8+ compatibility: Ensure times is an array before using reset()
        if (!isset($intervalsArray['times'][$intervals->getSuggestedDate()->format('H:i')]) && isset($intervalsArray['times']) && is_array($intervalsArray['times']) && !empty($intervalsArray['times'])) {
            $tmpTime = new SLN_DateTime(reset($intervalsArray['times'])->format('H:i'));
            $intervalsArray['suggestedTime'] = $tmpTime->format($customTimeFormat ?: 'H:i');
        }

        $tmpDate = $timezone ? (new SLN_DateTime($suggestedDate . ' ' . $intervalsArray['suggestedTime']))->setTimezone(new DateTimezone($timezone)) : new SLN_DateTime($suggestedDate . ' ' . $intervalsArray['suggestedTime']);

        $intervalsArray['suggestedTime']  = $plugin->format()->time($tmpDate, $customTimeFormat);
        $intervalsArray['suggestedDate']  = $plugin->format()->date($tmpDate);
        $intervalsArray['suggestedYear']  = $tmpDate->format('Y');
        $intervalsArray['suggestedMonth'] = $tmpDate->format('m');
        $intervalsArray['suggestedDay']   = $tmpDate->format('d');
        $intervalsArray['universalSuggestedDate'] = $tmpDate->format('Y-m-d');

        $fullDays = array_merge($intervals->getFullDays(), $fullDays);

        $years = array();

        // PHP 8+ compatibility: Ensure getYears returns array
        $yearsData = $intervals->getYears();
        if (is_array($yearsData)) {
            foreach ($yearsData as $v) {
                $v = $timezone ? $v->getDateTime()->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v->getDateTime();
                $intervalsArray['years'][$v->format('Y')] = $v->format('Y');
            }
        }

        $months = SLN_Func::getMonths();
        $monthsList = array();

        // PHP 8+ compatibility: Ensure getMonths returns array
        $monthsData = $intervals->getMonths();
        if (is_array($monthsData)) {
            foreach ($monthsData as $v) {
                $v = $timezone ? $v->getDateTime()->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v->getDateTime();
                $intervalsArray['months'][$v->format('m')] = $months[intval($v->format('m'))];
            }
        }

        $days = array();

        // PHP 8+ compatibility: Ensure getDays returns array
        $daysData = $intervals->getDays();
        if (is_array($daysData)) {
            foreach ($daysData as $v) {
                $v = $timezone ? $v->getDateTime()->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v->getDateTime();
                $intervalsArray['days'][$v->format('d')] = $v->format('d');
            }
        }

        $workTimes = array();

        // PHP 8+ compatibility: Ensure getWorkTimes returns array
        $workTimesData = $intervals->getWorkTimes();
        if (is_array($workTimesData)) {
            foreach ($workTimesData as $v) {
                $v = $timezone ? $v->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v;
                $intervalsArray['workTimes'][$v->format($customTimeFormat ?: 'H:i')] = $v->format($customTimeFormat ?: 'H:i');
            }
        }

        $dates = array();

        // PHP 8+ compatibility: Ensure dates is an array before iterating
        if (isset($intervalsArray['dates']) && is_array($intervalsArray['dates'])) {
            foreach ($intervalsArray['dates'] as $v) {
                $dates[] = $v->getDateTime()->format('Y-m-d');
            }
        }

        $intervalsArray['dates'] = $dates;

        $times = array();

        // PHP 8+ compatibility: Ensure times is an array before iterating
        if (isset($intervalsArray['times']) && is_array($intervalsArray['times'])) {
            foreach ($intervalsArray['times'] as $v) {
                $v = $timezone ? $v->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v;
                $times[$v->format($customTimeFormat ?: 'H:i')] = $v->format($customTimeFormat ?: 'H:i');
            }
        }
        
        $intervalsArray['times'] = $times;

        // PHP 8+ compatibility: Ensure fullDays is an array before iterating
        if (is_array($fullDays)) {
            foreach ($fullDays as $v) {
                $v = $timezone ? $v->setTimezone(SLN_Func::createDateTimeZone($timezone)) : $v;
                $intervalsArray['fullDays'][] = $v->format('Y-m-d');
            }
        }

        return $intervalsArray;
    }

    public function isAdmin() {
        return isset($_POST['post_ID']);
    }

    public function checkDateTime()
    {
        parent::checkDateTime();
        if ($this->isAdmin()) {
            return;
        }

        $plugin = $this->plugin;
        $errors = $this->getErrors();

        if (empty($errors)) {
            $date   = $this->getDateTime();

            $bb = $plugin->getBookingBuilder();
            $bservices = $bb->getAttendantsIds();

            $errors = $this->checkDateTimeServicesAndAttendants($bservices, $date);

            foreach($errors as $error) {
                $this->addError($error);
            }
        }

    }

    public function checkDateTimeServicesAndAttendants($services, $date, $check_duration = false) {
        $errors = array();

        $plugin = $this->plugin;
        $ah     = $plugin->getAvailabilityHelper();
        $ah->setDate($date, $this->booking);

        $isMultipleAttSelection = SLN_Plugin::getInstance()->getSettings()->get('m_attendant_enabled');
        $bookingOffsetEnabled   = SLN_Plugin::getInstance()->getSettings()->get('reservation_interval_enabled');
        $bookingOffset          = SLN_Plugin::getInstance()->getSettings()->get('minutes_between_reservation');

        $bb = $this->plugin->getBookingBuilder();
        $bookingServices = SLN_Wrapper_Booking_Services::build($services, $date, 0, $bb->getCountServices());

        $firstSelectedAttendant = null;


        foreach($bookingServices->getItems() as $bookingService) {
            $serviceErrors   = array();
            $attendantErrors = array();

            if ($bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
                $offsetStart   = $bookingService->getEndsAt();
                $offsetEnd     = $bookingService->getEndsAt()->modify('+'.$bookingOffset.' minutes');
                if(!class_exists('\SalonMultishop\Addon')){
                    $serviceErrors = $ah->validateTimePeriod($offsetStart, $offsetEnd);
                }
            }
            if (empty($serviceErrors)) {
                if(!class_exists('\SalonMultishop\Addon')){
                    $serviceErrors = $ah->validateBookingService($bookingService, $bookingServices->isLast($bookingService));
                }
            }
            if (!empty($serviceErrors)) {
                $errors[] = $serviceErrors[0];
                continue;
            }

            if ($bookingService->getAttendant() === false) {
                // AUTO-ATTENDANT MODE: Check if any attendant is available for this service
                $autoAttendantErrors = $this->checkAutoAttendantAvailability($bookingService);
                if (!empty($autoAttendantErrors)) {
                    $errors = array_merge($errors, $autoAttendantErrors);
                }
                continue;
            }
            $attendant = $bookingService->getAttendant();

            if (!$isMultipleAttSelection && !is_array($attendant)) {
                if (!$firstSelectedAttendant) {
                    $firstSelectedAttendant = $attendant->getId();
                }
                if ($attendant->getId() != $firstSelectedAttendant) {
                    $attendantErrors = array(__('Multiple attendants selection is disabled. You must select one attendant for all services.', 'salon-booking-system'));
                }
            }
            if (empty($attendantErrors)) {
                $attendantErrors = $ah->validateAttendantService(
                    $bookingService->getAttendant(),
                    $bookingService->getService()
                );
                if (empty($attendantErrors)) {
                    if(!is_array($attendant)){
                        $attendantErrors = $ah->validateBookingAttendant($bookingService, $bookingServices->isLast($bookingService));
                    }else{
                        $attendantErrors = $ah->validateBookingAttendants($bookingService, $bookingServices->isLast($bookingService));
                    }

                    if($check_duration){
                        $durationMinutes = SLN_Func::getMinutesFromDuration($bookingService->getTotalDuration());
                        if($durationMinutes){
                            $endAt = clone $date;
                            $endAt->modify('+' . ($durationMinutes - 1) . 'minutes');
                            $attendant = $bookingService->getAttendant();
                            if(!is_array($attendant)){
                                if ($attendant && $attendant->isNotAvailableOnDate($endAt)) {
                                    $errors[] = SLN_Helper_Availability_ErrorHelper::doAttendantNotAvailable($attendant, $endAt);
                                }
                            }else{
                                foreach($attendant as $att){
                                    if($att && $att->isNotAvailableOnDate($endAt)){
                                        $errors[] = SLN_Helper_Availability_ErrorHelper::doAttendantNotAvailable($att, $endAt);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($attendantErrors)) {
                $errors[] = $attendantErrors[0];
            }
        }

        return $errors;
    }

    /**
     * Check if any attendant is available for the service in auto-attendant mode
     * Includes comprehensive error handling and logging
     * 
     * @param SLN_Wrapper_Booking_Service $bookingService The booking service to check
     * @return array Empty array if available, error messages if not
     */
    private function checkAutoAttendantAvailability($bookingService)
    {
        // Safety: Check if feature is enabled (feature flag)
        if (!$this->plugin->getSettings()->isAutoAttendantCheckEnabled()) {
            if (class_exists('SLN_Helper_AutoAttendant_Logger')) {
                SLN_Helper_AutoAttendant_Logger::logSkipped('Feature flag disabled');
            }
            return array(); // Feature disabled, allow booking (fallback to old behavior)
        }

        try {
            $service = $bookingService->getService();
            
            // Safety: Null check for service
            if (!$service) {
                if (class_exists('SLN_Helper_AutoAttendant_Logger')) {
                    SLN_Helper_AutoAttendant_Logger::logFallback('Service is null');
                }
                return array(); // Allow booking if service is invalid (graceful fallback)
            }
            
            // Safety: Skip if attendants not enabled for this service
            if (!$service->isAttendantsEnabled()) {
                if (class_exists('SLN_Helper_AutoAttendant_Logger')) {
                    SLN_Helper_AutoAttendant_Logger::logSkipped('Service has attendants disabled');
                }
                return array(); // No attendant check needed
            }

            // Log check start
            if (class_exists('SLN_Helper_AutoAttendant_Logger')) {
                SLN_Helper_AutoAttendant_Logger::logCheckStart(
                    $service->getId(),
                    $bookingService->getStartsAt()->format('Y-m-d H:i:s')
                );
            }

            // Get available attendants for this service at this time
            $ah = $this->plugin->getAvailabilityHelper();
            $availableAttendants = $ah->getAvailableAttsIdsForBookingService($bookingService);
            
            // Safety: Handle null/false returns
            if ($availableAttendants === null || $availableAttendants === false) {
                if (class_exists('SLN_Helper_AutoAttendant_Logger')) {
                    SLN_Helper_AutoAttendant_Logger::logFallback('getAvailableAttsIdsForBookingService returned null/false');
                }
                return array(); // Allow booking on error (graceful degradation)
            }

            // Log result
            if (class_exists('SLN_Helper_AutoAttendant_Logger')) {
                SLN_Helper_AutoAttendant_Logger::logCheckResult($service->getId(), $availableAttendants);
            }

            // Check if any attendants are available
            if (empty($availableAttendants)) {
                return array(
                    sprintf(
                        // translators: %s will be replaced by the service name
                        __('No attendants available for %s at this time', 'salon-booking-system'),
                        $service->getName()
                    )
                );
            }

            // Success: attendants are available
            return array(); // No errors
            
        } catch (Exception $e) {
            // Safety: Catch any errors and log them
            if (class_exists('SLN_Helper_AutoAttendant_Logger')) {
                SLN_Helper_AutoAttendant_Logger::logError('Exception in checkAutoAttendantAvailability', $e);
            }
            
            error_log('=== SMART AVAILABILITY ERROR ===');
            error_log('Exception: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());
            error_log('=================================');
            
            // Allow booking on exception (fail open, not closed)
            return array();
        }
    }
    
    /**
     * Check if we should use Smart Availability mode
     * Returns true if "Choose assistant for me" is selected AND Smart Availability is enabled
     */
    private function isSmartAvailabilityMode($bservices) {
        // Check if Smart Availability feature is enabled
        if (!$this->plugin->getSettings()->isAutoAttendantCheckEnabled()) {
            return false;
        }
        
        // Check if any service has attendant = false (Choose assistant for me)
        foreach ($bservices as $serviceId => $attendantValue) {
            if ($attendantValue === false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all available time slots by checking ALL attendants individually
     * This is used when Smart Availability is enabled with "Choose assistant for me"
     * OPTIMIZED: Uses getAvailableAttsIdsForBookingService which is much faster
     */
    private function getAllAttendantsAvailableTimes($date, $bservices, $duration = null) {
        $plugin = $this->plugin;
        $ah = $plugin->getAvailabilityHelper();
        $availableTimes = array();
        
        // Get all possible times from opening hours (not limited by attendants)
        $allPossibleTimes = $ah->getTimes($date);
        
        if ($duration) {
            $allPossibleTimes = Time::filterTimesArrayByDuration($allPossibleTimes, $duration);
        }
        
        // For each time slot, check if ANY attendant is available
        $count = 0;
        $maxChecks = 100; // Limit checks to prevent timeout
        
        foreach ($allPossibleTimes as $timeStr => $timeObj) {
            // Prevent timeout by limiting iterations
            if (++$count > $maxChecks) {
                break;
            }
                
                $tmpDateTime = new SLN_DateTime($date->toString() . ' ' . $timeStr);
                
                // Build booking services for this specific time
                $bookingServices = SLN_Wrapper_Booking_Services::build(
                    $bservices,
                    $tmpDateTime,
                    0,
                    $plugin->getBookingBuilder()->getCountServices()
                );
                
                $hasAvailableAttendant = false;
                
                foreach ($bookingServices->getItems() as $bookingService) {
                    $service = $bookingService->getService();
                    
                    if (!$service || !$service->isAttendantsEnabled()) {
                        // Service doesn't require attendants
                        $hasAvailableAttendant = true;
                        break;
                    }
                    
                    // Use the optimized method that checks all attendants at once
                    $ah->setDate($tmpDateTime);
                    $availableAttendants = $ah->getAvailableAttsIdsForBookingService($bookingService);
                    
                    if (!empty($availableAttendants)) {
                        // At least one attendant is available!
                        $hasAvailableAttendant = true;
                        break;
                    }
                }
                
                // If at least one attendant is available, include this time slot
                if ($hasAvailableAttendant) {
                    $availableTimes[$timeStr] = $timeObj;
                }
            }
            
            // Return associative array (time string => DateTime object) - same format as getTimes()
            return $availableTimes;
    }
}
