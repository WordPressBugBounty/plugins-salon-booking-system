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
        $startTime = microtime(true);
        SLN_Plugin::addLog('[CheckDateAlt] getIntervalsArray START | ' . date('H:i:s'));
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
        // Note: No day-count limit for Smart Availability. getAllAttendantsAvailableTimes
        // with earlyExit=true stops at the first valid slot per day, so the date loop
        // is efficient even across large booking windows.
        $dateCount = count($dates);
        SLN_Plugin::addLog('[CheckDateAlt] Scanning ' . $dateCount . ' dates | smartAvail=' . ($isSmartAvailability ? 'yes' : 'no'));

        foreach($dates as $k => $v) {
            $available = false;
            $tmpDate   = new SLN_DateTime($v->getDateTime());
            $dateLog = $v->getDateTime()->format('Y-m-d');
            $dayServicesOk = $this->checkDayServicesAndAttendants($bservices, $tmpDate);
            $dateTimeLog->addDateLog( $dateLog, $dayServicesOk, __( 'The attendant is unavailable on this day', 'salon-booking-system' ) );
            if ($dayServicesOk) {
	            // A day must be shown as available ONLY if it has at least one slot that
	            // is genuinely bookable for the selected service(s) — i.e. a slot that
	            // survives the SAME strict validation the time picker applies below
	            // (checkDateTimeServicesAndAttendants with check_duration=true, plus the
	            // duration/auto-align filters). Previously the day-scan used a looser
	            // check (no duration, and for smart mode no final resource/attendant
	            // validation), so fully-constrained days (e.g. all fitting rooms booked)
	            // showed in the calendar but had an empty time picker — which silently
	            // bounced the user to another date. Keeping the day decision and the
	            // time list in lock-step removes those "visible but unbookable" days.
	            $available = $this->dateHasBookableSlot($tmpDate, $bservices, $isSmartAvailability);
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
            $elapsed = round((microtime(true) - $startTime) * 1000);
            SLN_Plugin::addLog('[CheckDateAlt] NO AVAILABLE DATES | scanned=' . $dateCount . ' | ' . $elapsed . 'ms | ' . date('H:i:s'));
            $intervalsArray = $intervals->toArray($timezone);
            $intervalsArray['dates'] = array();
            $intervalsArray['times'] = array();
            $intervalsArray['noAvailabilityMessage'] = __('No available appointments found for the selected service. No assistant pair is available in the current date range. Please try a different service or contact us.', 'salon-booking-system');

            // Override the stale session-derived suggested date with the start of the
            // current booking window (today + min-advance).  Without this, a stale past
            // date drives the calendar to a previous month and it appears frozen.
            $fromDate = $hb->getFromDate();
            $intervalsArray['suggestedDate']          = $plugin->format()->date($fromDate);
            $intervalsArray['suggestedYear']          = $fromDate->format('Y');
            $intervalsArray['suggestedMonth']         = $fromDate->format('m');
            $intervalsArray['suggestedDay']           = $fromDate->format('d');
            $intervalsArray['universalSuggestedDate'] = $fromDate->format('Y-m-d');

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
            // auto-align is already applied inside getAllAttendantsAvailableTimes() for performance
            $times = $this->getAllAttendantsAvailableTimes(Date::create($tmpDate), $bservices, $this->duration);
        } else {
            $times = $ah->getCachedTimes(Date::create($tmpDate), $this->duration);

            // Remove slots whose full service duration would overflow past closing hours.
            // getCachedTimes() only validates start times; without this filter a slot like
            // 19:45 for a 1-hour service would survive even when the shop closes at 20:00.
            if ($this->duration) {
                $beforeFilter = $times;
                $times = $ah->filterTimesArrayByDurationWithBreakAllowance($times, $this->duration);
                SLN_Helper_AvailabilityDebugger::logFilteredTimes($beforeFilter, $times, 'Duration overflow guard (CheckDateAlt non-smart)');
            }

            // Apply auto-align for non-smart path (smart path applies it inside getAllAttendantsAvailableTimes)
            if (SLN_Plugin::getInstance()->getSettings()->get('auto_align_slots') && $this->duration) {
                $originalTimes = $times;
                $times = SLN_Func::filterTimesAlignedToServiceDurationSlots($times, $this->duration);
                SLN_Helper_AvailabilityDebugger::logFilteredTimes($originalTimes, $times, 'Auto-Align Slots Filter');
            }
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

        $elapsed = round((microtime(true) - $startTime) * 1000);
        $availDateCount = is_array($intervalsArray['dates']) ? count($intervalsArray['dates']) : 0;
        SLN_Plugin::addLog('[CheckDateAlt] getIntervalsArray END | availDates=' . $availDateCount . ' | ' . $elapsed . 'ms | ' . date('H:i:s'));
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
            $skipAutoAttendantCheck = !$this->hasExplicitTimeSelection();
            SLN_Plugin::addLog(sprintf(
                '[TRACE_DATEFLOW][CheckDateAlt.checkDateTime] explicitTime=%s skipAutoAttendantCheck=%s services=%d',
                $skipAutoAttendantCheck ? 'no' : 'yes',
                $skipAutoAttendantCheck ? 'yes' : 'no',
                is_array($bservices) ? count($bservices) : 0
            ));

            $errors = $this->checkDateTimeServicesAndAttendants($bservices, $date, false, $skipAutoAttendantCheck);

            foreach($errors as $error) {
                $this->addError($error);
            }
        }

    }

    public function checkDateTimeServicesAndAttendants($services, $date, $check_duration = false, $skipAutoAttendantCheck = false) {
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
                $serviceErrors = $ah->validateTimePeriod($offsetStart, $offsetEnd);
            }
            if (empty($serviceErrors)) {
                $serviceErrors = $ah->validateBookingService($bookingService, $bookingServices->isLast($bookingService));
            }
            if (!empty($serviceErrors)) {
                $errors[] = $serviceErrors[0];
                continue;
            }

            if ($bookingService->getAttendant() === false) {
                if ($skipAutoAttendantCheck) {
                    SLN_Plugin::addLog('[TRACE_DATEFLOW][CheckDateAlt.autoAttendant] skipped strict check (init context)');
                    continue;
                }
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
     * Detect whether the current request contains an explicitly selected time.
     * We must not use empty() because "00:00" is a valid value but considered empty.
     *
     * @return bool
     */
    private function hasExplicitTimeSelection()
    {
        if (isset($_POST['sln']) && array_key_exists('time', $_POST['sln'])) {
            $time = sanitize_text_field(wp_unslash($_POST['sln']['time']));
            return $time !== '';
        }

        if (isset($_POST['_sln_booking_time'])) {
            $time = sanitize_text_field(wp_unslash($_POST['_sln_booking_time']));
            return $time !== '';
        }

        return isset($this->time) && $this->time !== '';
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
            $ah->setExcludeHiddenFromFrontend(true);
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
            
            SLN_Plugin::addLog('=== SMART AVAILABILITY ERROR ===');
            SLN_Plugin::addLog('Exception: ' . $e->getMessage());
            SLN_Plugin::addLog('Trace: ' . $e->getTraceAsString());
            SLN_Plugin::addLog('=================================');
            
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
        
        // Check if any service has no attendant selected (Choose assistant for me).
        // The booking builder stores 0 (integer) for "no attendant", not boolean false,
        // so we must accept both 0 and false to correctly activate Smart Availability.
        foreach ($bservices as $serviceId => $attendantValue) {
            if ($attendantValue === false || (is_int($attendantValue) && $attendantValue === 0)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all available time slots by checking ALL attendants individually.
     * Used for Smart Availability ("Choose assistant for me") both in the date loop
     * (where $earlyExit=true stops at the first valid slot) and for the full time
     * response (where $earlyExit=false collects all valid slots).
     *
     * @param \Salon\Util\Date $date
     * @param array            $bservices
     * @param Time|null        $duration
     * @param bool             $earlyExit When true, return as soon as one valid slot is found.
     * @return array
     */
    private function getAllAttendantsAvailableTimes($date, $bservices, $duration = null, $earlyExit = false) {
        $startTime = microtime(true);
        $plugin = $this->plugin;
        $ah = $plugin->getAvailabilityHelper();
        $availableTimes = array();
        
        // Get all possible times from opening hours (not limited by attendants)
        $allPossibleTimes = $ah->getTimes($date);
        
        if ($duration) {
            $allPossibleTimes = $ah->filterTimesArrayByDurationWithBreakAllowance($allPossibleTimes, $duration);
        }

        if ($plugin->getSettings()->get('auto_align_slots') && $duration) {
            $originalTimes    = $allPossibleTimes;
            $allPossibleTimes = SLN_Func::filterTimesAlignedToServiceDurationSlots($allPossibleTimes, $duration);
            SLN_Helper_AvailabilityDebugger::logFilteredTimes($originalTimes, $allPossibleTimes, 'Auto-Align Slots Filter');
        }

        $totalSlots = count($allPossibleTimes);
        // For each time slot, check if ANY attendant is available.
        // slotStep/maxChecks are optimisations that only make sense for the date-availability
        // scan (earlyExit=true), where we only need to confirm *any* slot exists and can
        // stop as soon as we find one. For the full time-list response (earlyExit=false)
        // every slot must be evaluated so the user sees the complete set of bookable times.
        $slotStep  = $earlyExit ? (int) apply_filters('sln_getallattendants_slot_step', 2) : 1;
        $slotStep  = max(1, min($slotStep, 5));
        $maxChecks = $earlyExit ? (int) apply_filters('sln_getallattendants_max_checks', 80) : PHP_INT_MAX;
        $count = 0;

        foreach ($allPossibleTimes as $timeStr => $timeObj) {
            if (++$count > $maxChecks) {
                SLN_Plugin::addLog('[getAllAttendantsAvailableTimes] HIT maxChecks=' . $maxChecks . ' for ' . $date->toString() . ' (total slots=' . $totalSlots . ')');
                break;
            }
            if ($slotStep > 1 && ($count - 1) % $slotStep !== 0) {
                continue; // Skip this slot (date-availability scan only)
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
                    $ah->setExcludeHiddenFromFrontend(true);
                    $ah->setDate($tmpDateTime);
                    $availableAttendants = $ah->getAvailableAttsIdsForBookingService($bookingService);
                    
                    if (!empty($availableAttendants)) {
                        // At least one attendant is available!
                        $hasAvailableAttendant = true;
                        break;
                    }
                }
                
                if ($hasAvailableAttendant) {
                    $availableTimes[$timeStr] = $timeObj;
                    if ($earlyExit) {
                        // Date-availability check: we only need to know if *any* slot
                        // is bookable — stop scanning as soon as we find one.
                        break;
                    }
                }
        }
        
        $elapsed = round((microtime(true) - $startTime) * 1000);
        if ($elapsed > 2000) {
            SLN_Plugin::addLog('[getAllAttendantsAvailableTimes] SLOW | date=' . $date->toString() . ' | found=' . count($availableTimes) . ' | earlyExit=' . ($earlyExit ? 'yes' : 'no') . ' | ' . $elapsed . 'ms');
        }
        return $availableTimes;
    }

    /**
     * Decide whether a day has at least one slot that is actually bookable for the
     * currently selected service(s)/attendant(s)/resources.
     *
     * This deliberately mirrors the time-list pipeline used later in
     * getIntervalsArray() for the suggested date:
     *   1. Build the candidate slots the SAME way the time picker does
     *      (smart: getAllAttendantsAvailableTimes; non-smart: getCachedTimes +
     *      duration + auto-align filters).
     *   2. Accept the day as soon as ONE candidate passes the strict
     *      checkDateTimeServicesAndAttendants() validation WITH $check_duration=true.
     *
     * Keeping the day decision identical to the time-list logic guarantees the
     * calendar never offers a day whose time picker would come back empty. We break
     * on the first valid slot, so bookable days stay cheap; only fully-unavailable
     * days (the ones we want to hide) pay the full per-slot scan.
     *
     * We deliberately do NOT re-apply the hours-before window check here: the
     * candidate sources already exclude out-of-window times (getTimes() enforces
     * $d >= from && $d <= to for regular slots and only the upper bound for break
     * slots), and the time picker below validates the very same candidate set
     * WITHOUT an extra hb->check. Re-adding it would make the day-scan stricter than
     * the picker and could hide a day whose only bookable slot is an in-window break
     * slot — a false negative the picker would not produce.
     *
     * @param SLN_DateTime $date
     * @param array        $bservices           services => attendant map from the builder
     * @param bool         $isSmartAvailability "Choose assistant for me" mode flag
     * @return bool
     */
    private function dateHasBookableSlot(SLN_DateTime $date, $bservices, $isSmartAvailability)
    {
        $ah = $this->plugin->getAvailabilityHelper();
        $ah->setDate($date, $this->booking);
        $dateStr = $date->format('Y-m-d');

        if ($isSmartAvailability) {
            // Same source the time list uses (no earlyExit: we need real candidates
            // to validate, not just "any attendant free" — resource checks happen below).
            $candidateTimes = $this->getAllAttendantsAvailableTimes(Date::create($date), $bservices, $this->duration);
        } else {
            $candidateTimes = $ah->getCachedTimes(Date::create($date), $this->duration);
            if ($this->duration) {
                $candidateTimes = $ah->filterTimesArrayByDurationWithBreakAllowance($candidateTimes, $this->duration);
            }
            if (SLN_Plugin::getInstance()->getSettings()->get('auto_align_slots') && $this->duration) {
                $candidateTimes = SLN_Func::filterTimesAlignedToServiceDurationSlots($candidateTimes, $this->duration);
            }
        }

        if (!is_array($candidateTimes)) {
            return false;
        }

        foreach ($candidateTimes as $timeObj) {
            if (!is_object($timeObj) || !method_exists($timeObj, 'format')) {
                continue;
            }
            $tmpDateTime = new SLN_DateTime($dateStr . ' ' . $timeObj->format('H:i'));
            $ah->setDate($tmpDateTime, $this->booking);
            $errors = $this->checkDateTimeServicesAndAttendants($bservices, $tmpDateTime, true);
            if (empty($errors)) {
                return true;
            }
        }

        return false;
    }

}
