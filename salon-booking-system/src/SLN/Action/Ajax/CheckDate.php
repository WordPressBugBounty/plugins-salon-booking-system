<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

use Salon\Util\Date;
use Salon\Util\Time;


class SLN_Action_Ajax_CheckDate extends SLN_Action_Ajax_Abstract
{
    protected $date;
    protected $time;
    protected $errors = array();
    protected $duration;
    protected $booking;

    public function setDuration(Time $duration){
        $this->duration = $duration;
        return $this;
    }

    public function execute()
    {
        if (!isset($this->date)) {
            if(isset($_POST['sln'])){
                $date = isset($_POST['sln']['date']) ? sanitize_text_field(wp_unslash($_POST['sln']['date'])) : '';
                $time = isset($_POST['sln']['time']) ? sanitize_text_field(wp_unslash($_POST['sln']['time'])) : '';
                
                // Only set if not empty to prevent errors
                if (!empty($date)) {
                    $this->date = $date;
                }
                if (!empty($time)) {
                    $this->time = $time;
                }
                
                $settings  = SLN_Plugin::getInstance()->getSettings();
                $newDebug  = (bool) ( $_POST['sln']['debug'] ?? false );
                if ( (bool) $settings->get( 'debug' ) !== $newDebug ) {
                    $settings->set( 'debug', $newDebug );
                    $settings->save();
                }
            }
            if(isset($_POST['_sln_booking_date'])) {
                $date = sanitize_text_field(wp_unslash($_POST['_sln_booking_date']));
                $time = isset($_POST['_sln_booking_time']) ? sanitize_text_field(wp_unslash($_POST['_sln_booking_time'])) : '';
                
                // Only set if not empty to prevent errors
                if (!empty($date)) {
                    $this->date = $date;
                }
                if (!empty($time)) {
                    $this->time = $time;
                }
            }
            $timezone   = $this->plugin->getSettings()->isDisplaySlotsCustomerTimezone() ? sanitize_text_field(wp_unslash($_POST['sln']['customer_timezone'])) : '';
            if (!empty($timezone) && isset($this->date) && isset($this->time)) {
                $dateTime = (new SLN_DateTime(SLN_Func::filter($this->date, 'date') . ' ' . SLN_Func::filter($this->time, 'time'.':00'), SLN_Func::createDateTimeZone($timezone)))->setTimezone(SLN_DateTime::getWpTimezone());
                $this->date = $this->plugin->format()->date($dateTime);
                $this->time = $this->plugin->format()->time($dateTime);
            }
        }

        SLN_Plugin::addLog(sprintf(
            '[TRACE_DATEFLOW][CheckDate.execute][IN] date=%s time=%s timezone=%s has_sln=%s',
            isset($this->date) ? $this->date : 'NULL',
            isset($this->time) ? $this->time : 'NULL',
            isset($timezone) ? $timezone : '',
            isset($_POST['sln']) ? 'yes' : 'no'
        ));

        // Early validation: if no date was provided, return a user-facing error immediately.
        // This prevents a generic Exception from bubbling up to Plugin::ajax() which would
        // trigger an error notification email for what is simply a missing-input scenario.
        if (empty($this->date)) {
            return array(
                'errors'                 => array(__('Please select a date before proceeding.', 'salon-booking-system')),
                'can_override_validation' => false,
            );
        }

        // Back-end booking editor: establish the same duration- and booking-aware
        // context the front-end uses. Without this the admin path returned raw
        // per-minute availability (no duration overflow filter), so a day that is
        // already full for the selected service still offered start times where
        // the service could never actually fit (late slots, short gaps between
        // existing bookings). It also excludes the booking being edited from its
        // own availability so its current slot is not counted against itself.
        $this->applyAdminBookingContext();

        $this->checkDateTime();
        if ($errors = $this->getErrors()) {
            $ret = compact('errors');
        } else {
            $ret = array('success' => 1);
        }
        
        // Check if current user is administrator or salon staff
        $currentUser = wp_get_current_user();
        $isAdminOrStaff = current_user_can('administrator') || 
                          in_array(SLN_Plugin::USER_ROLE_STAFF, $currentUser->roles);
        
        // Send flag to frontend indicating user can override validation
        $ret['can_override_validation'] = $isAdminOrStaff;

        // Performance diagnostics: only for administrators with debug enabled, so
        // there is no overhead for normal visitors. Captures cache hit/miss counts
        // and timings of the availability engine to distinguish a caching problem
        // (full window re-processed each request) from an intrinsic getTimes() cost.
        $perfEnabled = SLN_Plugin::getInstance()->getSettings()->get('debug') && current_user_can('administrator');
        if ($perfEnabled) {
            SLN_Helper_Availability::$perfEnabled = true;
            SLN_Helper_Availability::perfReset();
            // Read the persisted day-cache option BEFORE the availability engine
            // runs, to confirm whether it survives across requests.
            $cacheOpt = get_option(SLN_Wrapper_Booking_AbstractCache::KEY);
            SLN_Helper_Availability::$perf['cache_option_count'] = is_array($cacheOpt) ? count($cacheOpt) : -1;
        }

        // FIX RISCHIO #1: Sempre ritornare intervals, anche senza timezone
        // Problema precedente: se !isset($timezone), ritornava array vuoto
        // Questo causava AJAX refresh a fallire silently, usando dati stale dall'HTML
        // Soluzione: Sempre chiamare getIntervalsArray(), passando stringa vuota se no timezone
        $ret['intervals'] = $this->getIntervalsArray(isset($timezone) ? $timezone : '');

        if ($perfEnabled) {
            SLN_Helper_Availability::$perfEnabled = false;
        }

        $isFromAdmin = isset($_POST['_sln_booking_date']);
        if (!$isFromAdmin) {
            $suggestedDate = isset($ret['intervals']['suggestedDate']) ? $ret['intervals']['suggestedDate'] : null;
            $suggestedTime = isset($ret['intervals']['suggestedTime']) ? $ret['intervals']['suggestedTime'] : null;
            $hasDates      = !empty($ret['intervals']['dates']);
            $hasTimes      = !empty($ret['intervals']['times']);
        
            // Clear errors when:
            // (a) the system is suggesting a different date/time than what was requested — the JS
            //     will automatically advance to the suggested slot, so validation errors for the old
            //     slot are irrelevant; OR
            // (b) dates are available but times are empty for the current slot — this means the JS
            //     autoRetryEmptyTimes logic needs to kick in and try the next date. Returning
            //     success:false here blocks that retry entirely.
            if ($suggestedDate !== $this->date || $suggestedTime !== $this->time || ($hasDates && !$hasTimes)) {
                unset($ret['errors']);
                $ret['success'] = 1;
            }
        }

        if ( true == SLN_Plugin::getInstance()->getSettings()->get( 'debug' ) && current_user_can( 'administrator' ) ){
            $ret['debug']['times'] = SLN_Helper_Availability_AdminRuleLog::getInstance()->getLog();
            $ret['debug']['dates'] = SLN_Helper_Availability_AdminRuleLog::getInstance()->getDateLog();
            SLN_Helper_Availability_AdminRuleLog::getInstance()->clear();
        }
        if ( $perfEnabled ) {
            $ret['debug']['perf'] = SLN_Helper_Availability::perfGet();
        }

        $datesCount = (isset($ret['intervals']['dates']) && is_array($ret['intervals']['dates'])) ? count($ret['intervals']['dates']) : 0;
        $timesCount = (isset($ret['intervals']['times']) && is_array($ret['intervals']['times'])) ? count($ret['intervals']['times']) : 0;
        SLN_Plugin::addLog(sprintf(
            '[TRACE_DATEFLOW][CheckDate.execute][OUT] success=%s errors=%d dates=%d times=%d suggestedDate=%s suggestedTime=%s',
            isset($ret['success']) ? (string) $ret['success'] : '0',
            isset($ret['errors']) && is_array($ret['errors']) ? count($ret['errors']) : 0,
            $datesCount,
            $timesCount,
            isset($ret['intervals']['suggestedDate']) ? $ret['intervals']['suggestedDate'] : '',
            isset($ret['intervals']['suggestedTime']) ? $ret['intervals']['suggestedTime'] : ''
        ));

        return $ret;
    }

    public function getIntervals() {
        return $this->plugin->getIntervals($this->getDateTime(), $this->duration);
    }

    public function getIntervalsArray($timezone = '') {
        return $this->getIntervals()->toArray($timezone);
    }

    /**
     * When the request originates from the back-end booking editor (metabox), set
     * the service duration and the edited booking on this handler so the time
     * picker is computed exactly like the front-end:
     *   - the duration drives SLN_Helper_Intervals' duration/auto-align filters,
     *     dropping start times where the whole service would not fit;
     *   - the booking excludes its own occupied slots from availability counts.
     *
     * The duration is derived from the CURRENTLY POSTED services (not the saved
     * booking) so that changing services in the editor immediately updates the
     * offered time slots.
     *
     * No-ops for front-end requests and when no services are posted.
     */
    private function applyAdminBookingContext()
    {
        if (!isset($_POST['post_ID']) || !isset($_POST['_sln_booking']) || !is_array($_POST['_sln_booking'])) {
            return;
        }

        $postId = intval(wp_unslash($_POST['post_ID']));
        if ($postId <= 0) {
            return;
        }

        try {
            $booking = $this->plugin->createFromPost($postId);
        } catch (Exception $e) {
            return;
        }

        if ($booking instanceof SLN_Wrapper_Booking) {
            $this->setBooking($booking);
        }

        $services = $this->processAdminServicesSubmission(wp_unslash($_POST['_sln_booking']));
        if (empty($services)) {
            return;
        }

        $startsAt        = $this->getDateTime();
        $countServices   = ($booking instanceof SLN_Wrapper_Booking) ? $booking->getCountServices() : array();
        $bookingServices = SLN_Wrapper_Booking_Services::build($services, $startsAt, 0, $countServices);

        // Total duration = work + break of each service, summing sequential
        // services and taking the longest parallel one (mirrors the booking
        // builder so admin and front-end agree on the duration filter).
        $sequentialMinutes = 0;
        $maxParallel       = 0;
        foreach ($bookingServices->getItems() as $bookingService) {
            $d          = $bookingService->getTotalDuration();
            $dInMinutes = (intval($d->format('H')) * 60) + intval($d->format('i'));
            if ($bookingService->getParallelExec()) {
                if ($dInMinutes > $maxParallel) {
                    $maxParallel = $dInMinutes;
                }
            } else {
                $sequentialMinutes += $dInMinutes;
            }
        }
        $minutes = $sequentialMinutes + $maxParallel;

        if ($minutes > 0) {
            $this->setDuration(new Time(SLN_Func::convertToHoursMins($minutes)));
        }
    }

    /**
     * Parse the metabox `_sln_booking` payload into the service-array shape
     * expected by SLN_Wrapper_Booking_Services::build(). Mirrors the parsing in
     * SLN_Action_Ajax_CalcBookingTotal so the picker duration matches the totals
     * shown in the editor.
     *
     * @param array $data The `_sln_booking` POST payload.
     * @return array
     */
    private function processAdminServicesSubmission($data)
    {
        $services     = array();
        $services_ids = isset($data['service']) ? array_map('intval', (array) $data['service']) : array();

        foreach ($services_ids as $key => $serviceId) {
            if (0 === $serviceId) {
                continue;
            }

            $duration      = isset($data['duration'][$serviceId]) ? SLN_Func::convertToHoursMins($data['duration'][$serviceId]) : '';
            $breakDuration = isset($data['break_duration'][$serviceId]) ? SLN_Func::convertToHoursMins($data['break_duration'][$serviceId]) : '';

            if (isset($data['attendants'][$key])) {
                $attendant = $data['attendants'][$key];
            } elseif (isset($data['attendant'])) {
                $attendant = $data['attendant'];
            } else {
                $attendant = null;
            }

            $service = $this->plugin->createService($serviceId);
            $service = apply_filters('sln.booking_services.buildService', $service);

            if (0 == $attendant && $this->plugin->getSettings()->isAttendantsEnabled() && $service->isAttendantsEnabled()) {
                continue;
            }

            $services[$serviceId] = array(
                'service'             => $serviceId,
                'attendant'           => $attendant,
                'duration'            => $duration,
                'break_duration'      => $breakDuration,
                'break_duration_data' => $service->getBreakDurationData(),
            );
        }

        return $services;
    }

    public function checkDateTime()
    {

        $plugin = $this->plugin;
        if ($this->time && !SLN_Func::isTimeAlignedToInterval($this->time)) {
            $this->addError(
                __(
                    'The selected time is not valid. Please choose one of the available time slots.',
                    'salon-booking-system'
                )
            );
            return;
        }

        $date   = $this->getDateTime();
        $ah   = $plugin->getAvailabilityHelper();
        $hb   = $ah->getHoursBeforeHelper();
        $from = $hb->getFromDate();
        $to   = $hb->getToDate();
        if (!$hb->isValidFrom($date)) {
            $txt = $plugin->format()->datetime($from);
            $this->addError(sprintf(__('The date is too near, the minimum allowed is:', 'salon-booking-system') . '<br /><strong>%s</strong>', $txt));
        } elseif (!$hb->isValidTo($date)) {
            $txt = $plugin->format()->datetime($to);
            $this->addError(sprintf(__('The date is too far, the maximum allowed is:', 'salon-booking-system') . '<br /><strong>%s</strong>', $txt));
        } elseif (!$ah->getItems()->isValidDatetime($date) || !$ah->getHolidaysItems()->isValidDatetime($date)) {
            $txt = $plugin->format()->datetime($date);
            $this->addError(sprintf(__('We are unavailable at:', 'salon-booking-system') . '<br /><strong>%s</strong>', $txt));
        } else {
            $ah->setDate($date, $this->booking);
            if (!$ah->isValidDate( Date::create($date))) {
                $this->addError(
                    __(
                        'There are no time slots available today - Please select a different day',
                        'salon-booking-system'
                    )
                );
            } elseif (!$ah->isValidTime($this->getDateTime())) {
                $this->addError(
                    __(
                        'There are no time slots available for this period - Please select a  different hour',
                        'salon-booking-system'
                    )
                );
            }
        }
    }

    protected function addError($err)
    {
        $this->errors[] = $err;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param mixed $date
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @param mixed $time
     * @return $this
     */
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }

    protected function getDateTime()
    {
        $date = isset($this->date) ? $this->date : null;
        $time = isset($this->time) ? $this->time : null;
        
        // Validate date is not empty
        if (empty($date)) {
            throw new Exception(
                'Missing date in request. Date: "' . ($date ?? 'null') . '". Please select a date before proceeding.'
            );
        }
        
        // If time is empty, use a default placeholder time
        // This allows checking date availability without requiring a specific time
        if (empty($time)) {
            $time = '00:00';
        }
        
        $ret = new SLN_DateTime(
            SLN_Func::filter($date, 'date') . ' ' . SLN_Func::filter($time, 'time')
        );
        return $ret;
    }

    public function setBooking(SLN_Wrapper_Booking $booking){
        $this->booking = $booking;
        return $this;
    }

}
