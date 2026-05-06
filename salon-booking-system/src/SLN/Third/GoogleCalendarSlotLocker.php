<?php
use Google\Service\Calendar\Event as Google_Service_Calendar_Event;

/**
 * Locks time slots on the booking form calendar when the salon's Google Calendar
 * contains external (non-SLN) events during those times.
 *
 * HOW IT WORKS:
 * - During the cron sync, fetches all events from google_client_calendar.
 * - Filters OUT events that are SLN-pushed bookings (identified by a matching
 *   _sln_calendar_event_id post meta — the DB lookup is the definitive guard).
 * - Stores the remaining "external" events' time intervals as WP transients,
 *   keyed by date: sln_gcal_locked_{YYYY-MM-DD}.
 * - Hooks into the 'sln_gcal_time_check' filter called from
 *   SLN_Helper_Availability::getTimes() to mark a slot as unavailable when
 *   its start time falls within any stored blocked interval.
 * - If a transient is expired at booking-form load time, a lazy single-day
 *   API fetch is performed (with per-request deduplication).
 */
class SLN_Third_GoogleCalendarSlotLocker
{
    const TRANSIENT_PREFIX     = 'sln_gcal_locked_';
    const TRANSIENT_TTL        = 3600; // seconds — 1 hour
    const DAYS_AHEAD           = 60;
    const EVENT_DELETED_STATUS = 'cancelled';
    const SETTING_KEY          = 'google_calendar_lock_slots';

    /** @var self */
    private static $instance;
    /** @var SLN_GoogleScope */
    private $gScope;
    /** @var \DateTimeZone|null */
    private $timezone;
    /** @var array Dates already lazy-refreshed this request (dedup guard) */
    private $lazyRefreshed = array();
    /** @var array Per-date cache of holiday-rule arrays built from stored intervals */
    private $holidayRulesCache = array();

    public static function launch($gScope)
    {
        if (empty(self::$instance)) {
            self::$instance = new self($gScope);
        }
    }

    private function __construct($gScope)
    {
        $this->gScope = $gScope;

        if (!$this->isEnabled()) {
            return;
        }

        // Piggyback on the same cron trigger used by SLN_Third_GoogleCalendarImport
        if (defined('DOING_CRON') && isset($_GET['action']) && $_GET['action'] === 'sln_sync_from_google_calendar') {
            add_action('wp_loaded', array($this, 'refreshBlockedIntervals'), 20);
        }

        // Also refresh when the admin manually clicks "Synchronize Bookings"
        add_action('sln.google_calendar.synch_complete', array($this, 'refreshBlockedIntervals'));

        // Inject blocked intervals into the admin calendar (both server-side render and JSON rules)
        add_filter('sln.calendar.holidays_daily_rules', array($this, 'injectCalendarRules'), 10, 3);

        add_filter('sln_gcal_time_check', array($this, 'checkTime'), 10, 2);
    }

    public function isEnabled()
    {
        return defined('SLN_VERSION_PAY')
            && (bool) SLN_Plugin::getInstance()->getSettings()->get(self::SETTING_KEY)
            && (bool) SLN_Plugin::getInstance()->getSettings()->get('google_calendar_enabled');
    }

    // -------------------------------------------------------------------------
    // Cron refresh
    // -------------------------------------------------------------------------

    /**
     * Fetch events from the salon's Google Calendar and store blocked intervals
     * (non-SLN events) as per-date WP transients.
     * Called during the scheduled cron sync.
     */
    public function refreshBlockedIntervals()
    {
        $gScope = $this->gScope;

        if (!$gScope->is_connected() || empty($gScope->google_client_calendar)) {
            return;
        }

        $this->resolveTimezone();

        $now     = new SLN_DateTime();
        $timeMin = $now->format(DateTime::RFC3339);
        $timeMax = (clone $now)->modify('+' . self::DAYS_AHEAD . ' days')->format(DateTime::RFC3339);

        $params = array(
            'timeMin'      => $timeMin,
            'timeMax'      => $timeMax,
            'showDeleted'  => true,
            'singleEvents' => true,
            'orderBy'      => 'startTime',
        );

        $blockedByDate = array();
        $nextPageToken = null;

        do {
            if ($nextPageToken) {
                $params['pageToken'] = $nextPageToken;
            }

            try {
                $result = $gScope->get_google_service()->events->listEvents(
                    $gScope->google_client_calendar,
                    $params
                );
            } catch (\Exception $e) {
                SLN_Plugin::addLog('[GoogleCalendarSlotLocker] API error during refresh: ' . $e->getMessage());
                return;
            }

            foreach ($result->getItems() as $gEvent) {
                $this->processEvent($gEvent, $blockedByDate);
            }

            $nextPageToken = $result->getNextPageToken();

        } while ($nextPageToken);

        $this->storeBlockedIntervals($blockedByDate);

        // Bust the availability cache so freshly stored intervals take effect immediately
        SLN_Helper_Availability_Cache::clearCache();

        SLN_Plugin::addLog('[GoogleCalendarSlotLocker] Refresh complete. Dates with blocks: ' . count($blockedByDate));
    }

    // -------------------------------------------------------------------------
    // Event processing
    // -------------------------------------------------------------------------

    /**
     * Evaluate a single Google Calendar event and add its interval to
     * $blockedByDate if it is an external (non-SLN) event.
     *
     * @param Google_Service_Calendar_Event $gEvent
     * @param array                         $blockedByDate  Passed by reference
     */
    private function processEvent(Google_Service_Calendar_Event $gEvent, array &$blockedByDate)
    {
        // Removed/cancelled events clear automatically because transients are rebuilt from scratch
        if ($gEvent->getStatus() === self::EVENT_DELETED_STATUS) {
            return;
        }

        $startObj = $gEvent->getStart();
        $endObj   = $gEvent->getEnd();

        // All-day events have no dateTime — cannot map to specific minute slots, skip
        if (!$startObj || empty($startObj->getDateTime())) {
            return;
        }

        // Skip events that are SLN-pushed bookings (linked by _sln_calendar_event_id post meta).
        // This is the definitive check — DB-backed, not description-based — so it correctly
        // allows through personal events that happen to contain URLs (e.g. Google Meet links).
        if ($this->isSLNBookingEvent($gEvent->getId())) {
            return;
        }

        // Parse start / end into WP site timezone
        try {
            $tz = $startObj->getTimeZone()
                ? SLN_Func::createDateTimeZone($startObj->getTimeZone())
                : $this->timezone;

            $startDt = SLN_DateTime::createFromFormat(DateTime::RFC3339, $startObj->getDateTime(), $tz);
            $startDt->setTimezone(SLN_TimeFunc::getWpTimezone());

            $endDt = SLN_DateTime::createFromFormat(DateTime::RFC3339, $endObj->getDateTime(), $tz);
            $endDt->setTimezone(SLN_TimeFunc::getWpTimezone());
        } catch (\Exception $e) {
            SLN_Plugin::addLog(
                '[GoogleCalendarSlotLocker] Date parse error for event ' . $gEvent->getId() . ': ' . $e->getMessage()
            );
            return;
        }

        $startTs     = $startDt->getTimestamp();
        $endTs       = $endDt->getTimestamp();
        $startDate   = $startDt->format('Y-m-d');
        $endDate     = $endDt->format('Y-m-d');

        if (!isset($blockedByDate[$startDate])) {
            $blockedByDate[$startDate] = array();
        }
        $blockedByDate[$startDate][] = array($startTs, $endTs);

        // Event spans midnight: register the interval for the end date as well
        if ($endDate !== $startDate) {
            if (!isset($blockedByDate[$endDate])) {
                $blockedByDate[$endDate] = array();
            }
            $blockedByDate[$endDate][] = array($startTs, $endTs);
        }

        SLN_Plugin::addLog(sprintf(
            '[GoogleCalendarSlotLocker] External event will block slots: "%s" (%s → %s)',
            $gEvent->getSummary(),
            $startDt->format('Y-m-d H:i'),
            $endDt->format('Y-m-d H:i')
        ));
    }

    /**
     * Persist blocked intervals into per-date WP transients.
     * Every date in the upcoming window is written (even if empty) so we
     * know the data is fresh and avoid spurious lazy-refreshes.
     *
     * @param array $blockedByDate  date-string => [[startTs, endTs], ...]
     */
    private function storeBlockedIntervals(array $blockedByDate)
    {
        $now = new SLN_DateTime();
        for ($i = 0; $i <= self::DAYS_AHEAD; $i++) {
            $date    = (clone $now)->modify("+{$i} days")->format('Y-m-d');
            $key     = self::TRANSIENT_PREFIX . $date;
            $payload = isset($blockedByDate[$date]) ? $blockedByDate[$date] : array();
            set_transient($key, $payload, self::TRANSIENT_TTL);
        }
    }

    // -------------------------------------------------------------------------
    // Admin calendar filter
    // -------------------------------------------------------------------------

    /**
     * Filter callback for 'sln.calendar.holidays_daily_rules'.
     *
     * Injects GCal blocked intervals as holiday-format rules so the admin
     * calendar marks those rows as blocked (blocked-daily CSS class) in both
     * the server-side rendered HTML and the client-side rules JSON.
     *
     * For single-day contexts (day view, from === to) a lazy API refresh is
     * performed when the transient is missing. For multi-day ranges (week /
     * month view) only already-cached transients are used — no extra API calls.
     *
     * @param  array         $rules  Existing holiday rules
     * @param  DateTime      $from   Start of the date range being rendered
     * @param  DateTime|null $to     End of the date range (null = same as $from)
     * @return array
     */
    public function injectCalendarRules($rules, $from, $to = null)
    {
        if (!($from instanceof DateTime)) {
            return $rules;
        }

        $to          = ($to instanceof DateTime) ? $to : $from;
        $isSingleDay = ($from->format('Y-m-d') === $to->format('Y-m-d'));
        $current     = clone $from;

        while ($current->format('Y-m-d') <= $to->format('Y-m-d')) {
            $date      = $current->format('Y-m-d');
            $intervals = $this->getIntervalsForDate($date, $isSingleDay);

            foreach ($intervals as $interval) {
                list($startTs, $endTs) = $interval;
                // Use wp_date() so times are expressed in the WP site timezone,
                // matching the timezone used by hasHolidaysDaylyByLine() comparisons.
                $rules[] = array(
                    'from_date'   => wp_date('Y-m-d', $startTs),
                    'to_date'     => wp_date('Y-m-d', $endTs),
                    'from_time'   => wp_date('H:i', $startTs),
                    'to_time'     => wp_date('H:i', $endTs),
                    'is_manual'   => false,
                    'gcal_locked' => true,
                );
            }

            $current->modify('+1 day');
        }

        return $rules;
    }

    /**
     * Return stored intervals for a date, optionally lazy-refreshing.
     *
     * @param  string $date        Y-m-d
     * @param  bool   $allowLazy   Whether to hit the API when transient is missing
     * @return array               [[startTs, endTs], ...]
     */
    private function getIntervalsForDate($date, $allowLazy = false)
    {
        if (!isset($this->holidayRulesCache[$date])) {
            $intervals = get_transient(self::TRANSIENT_PREFIX . $date);
            if ($intervals === false) {
                $intervals = $allowLazy ? $this->lazyRefreshDate($date) : array();
            }
            $this->holidayRulesCache[$date] = $intervals ?: array();
        }
        return $this->holidayRulesCache[$date];
    }

    // -------------------------------------------------------------------------
    // Availability filter
    // -------------------------------------------------------------------------

    /**
     * Filter callback for 'sln_gcal_time_check' (called from getTimes()).
     *
     * Returns false (= blocked) when $datetime's start falls inside any external
     * GCal event interval stored for that date.
     *
     * @param bool         $available  Current availability flag
     * @param SLN_DateTime $datetime   The booking-form time slot being evaluated
     * @return bool
     */
    public function checkTime($available, SLN_DateTime $datetime)
    {
        if (!$available) {
            return false;
        }

        $date      = $datetime->format('Y-m-d');
        $intervals = $this->getIntervalsForDate($date, true); // lazy refresh allowed

        if (empty($intervals)) {
            return true;
        }

        $slotTs = $datetime->getTimestamp();

        foreach ($intervals as $interval) {
            list($startTs, $endTs) = $interval;
            // Block if the slot's start time falls within [event_start, event_end)
            if ($slotTs >= $startTs && $slotTs < $endTs) {
                SLN_Plugin::addLogVerbose(sprintf(
                    '[GoogleCalendarSlotLocker] Slot %s blocked (GCal event %s–%s)',
                    $datetime->format('H:i'),
                    date('H:i', $startTs),
                    date('H:i', $endTs)
                ));
                return false;
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Lazy single-day refresh
    // -------------------------------------------------------------------------

    /**
     * On-demand fetch of blocked intervals for a single date.
     * Guarded by $lazyRefreshed so each date is only fetched once per PHP request,
     * regardless of how many time slots are checked.
     *
     * @param  string $date  Y-m-d
     * @return array         [[startTs, endTs], ...]
     */
    private function lazyRefreshDate($date)
    {
        if (isset($this->lazyRefreshed[$date])) {
            return $this->lazyRefreshed[$date];
        }

        // Mark immediately to prevent re-entrant calls while this one runs
        $this->lazyRefreshed[$date] = array();

        $gScope = $this->gScope;
        if (!$gScope->is_connected() || empty($gScope->google_client_calendar)) {
            return array();
        }

        $this->resolveTimezone();

        try {
            $dayStart = new SLN_DateTime($date . ' 00:00:00', SLN_TimeFunc::getWpTimezone());
            $dayEnd   = new SLN_DateTime($date . ' 23:59:59', SLN_TimeFunc::getWpTimezone());

            $result = $gScope->get_google_service()->events->listEvents(
                $gScope->google_client_calendar,
                array(
                    'timeMin'      => $dayStart->format(DateTime::RFC3339),
                    'timeMax'      => $dayEnd->format(DateTime::RFC3339),
                    'showDeleted'  => false,
                    'singleEvents' => true,
                )
            );

            $blockedByDate = array();
            foreach ($result->getItems() as $gEvent) {
                $this->processEvent($gEvent, $blockedByDate);
            }

            $intervals = isset($blockedByDate[$date]) ? $blockedByDate[$date] : array();

        } catch (\Exception $e) {
            SLN_Plugin::addLog('[GoogleCalendarSlotLocker] Lazy refresh failed for ' . $date . ': ' . $e->getMessage());
            $intervals = array();
        }

        set_transient(self::TRANSIENT_PREFIX . $date, $intervals, self::TRANSIENT_TTL);
        SLN_Helper_Availability_Cache::clearCache();

        $this->lazyRefreshed[$date] = $intervals;

        SLN_Plugin::addLog(
            '[GoogleCalendarSlotLocker] Lazy refresh for ' . $date . ': ' . count($intervals) . ' blocked interval(s)'
        );

        return $intervals;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether a Google Calendar event ID is linked to an SLN booking.
     *
     * @param  string $gEventId
     * @return bool
     */
    private function isSLNBookingEvent($gEventId)
    {
        $query = new WP_Query(array(
            'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_sln_calendar_event_id',
                    'value' => $gEventId,
                ),
            ),
        ));
        $found = $query->post_count > 0;
        wp_reset_query();
        return $found;
    }

    /**
     * Resolve and cache the Google Calendar service timezone.
     */
    private function resolveTimezone()
    {
        if (isset($this->timezone)) {
            return;
        }
        try {
            $tz = $this->gScope->get_google_service()->settings->get('timezone')->value;
            $this->timezone = SLN_Func::createDateTimeZone($tz);
        } catch (\Exception $e) {
            $this->timezone = SLN_TimeFunc::getWpTimezone();
        }
    }
}
