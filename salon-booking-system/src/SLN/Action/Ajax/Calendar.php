<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

use Salon\Util\Date;

class SLN_Action_Ajax_Calendar extends SLN_Action_Ajax_Abstract
{
  private $from;
  private $to;
  private $startTime; // for render booking day
  private $endTime; // for render booking day
  /** @var  SLN_Wrapper_Booking[] */
  private $bookings;
  /** @var  SLN_Wrapper_Attendant[] */
  private $assistants;
  protected $intervalName;
  private $stopIteration = false;
  protected $attendantMode;

  public function getFrom()
  {
    return clone $this->from;
  }

  public function execute()
  {
    $this->attendantMode = get_user_meta(get_current_user_id(), '_assistants_mode', true) == 'true';
    $offset = intval($_GET['offset']) * 60;
    $offsetEnd = isset($_GET['offsetEnd']) ? intval($_GET['offsetEnd']) * 60 : $offset;
    $this->from = (new SLN_DateTime)->setTimestamp(sanitize_text_field(wp_unslash($_GET['from'])) / 1000 - $offset)->setTimezone(new DateTimeZone('UTC'));
    $this->to = (new SLN_DateTime)->setTimestamp(sanitize_text_field(wp_unslash($_GET['to'])) / 1000 - $offsetEnd)->setTimezone(new DateTimeZone('UTC'))->sub(new DateInterval('P1D'));
    $dateDiff = $this->to->diff($this->from);
    $this->isYearly = $dateDiff->m == 11 || $dateDiff->m == 12;

    if (isset($_GET['_assistants_mode']) && in_array(wp_unslash($_GET['_assistants_mode']), array('true', 'false'))) {
      $this->attendantMode = update_user_meta(get_current_user_id(), '_assistants_mode', wp_unslash($_GET['_assistants_mode']));
      $this->attendantMode = wp_unslash($_GET['_assistants_mode']) == 'true';
    }

    if ($dateDiff->days > 32) {
      $this->intervalName = 'year';
    } else if ($dateDiff->days > 6) {
      $this->intervalName = 'month';
    } else if ($dateDiff->days > 1) {
      $this->intervalName = 'week';
    } else {
      $this->intervalName = 'day';
    }

    $this->buildBookings();
    $this->buildAssistants();
    $this->saveAttendantPositions(isset($_GET['assistant_position']) ? $_GET['assistant_position'] : '');

    // Get status counts before any bookings modification
    $statusCounts = $this->getBookingStatusCounts();
    $upcomingTodayData = $this->getUpcomingTodayCount();

    if ($this->isYearly) {
      $ret = array(
        'success' => 1,
        'render' => $this->renderEvents(),
        'statusCounts' => $statusCounts,
        'upcomingToday' => $upcomingTodayData['count'],
        'upcomingTodayBookings' => $upcomingTodayData['bookings'],
        'isFreeVersion' => !defined('SLN_VERSION_PAY'),
      );
    } else {
      $settings = $this->plugin->getSettings();
      $holidays_rules = $settings->get('holidays_daily') ?: array();

      foreach ($holidays_rules as &$rule) {
        $rule['is_manual'] = isset($rule['is_manual']) ? (bool)$rule['is_manual'] : true;
      }

      $holidays_assistants_rules = array();
      $assistants = $this->plugin->getRepository(\SLN_Plugin::POST_TYPE_ATTENDANT)->getAll();

      foreach ($assistants as $att) {
        $holidays_daily = $att->getMeta('holidays_daily') ?: array();
        foreach ($holidays_daily as &$rule) {
          $rule['assistant_id'] = $att->getId();
          $rule['is_manual'] = isset($rule['is_manual']) ? (bool)$rule['is_manual'] : true;
        }
        $holidays = $att->getMeta('holidays') ?: array();
        foreach ($holidays as &$rule) {
          $rule['assistant_id'] = $att->getId();
          if (!isset($rule['daily'])) {
            $rule['daily'] = true;
          }
          $rule['is_manual'] = isset($rule['is_manual']) ? (bool)$rule['is_manual'] : false;
        }

        $holidays_assistants_rules[$att->getId()] = array_merge($holidays_daily, $holidays);
      }

      $ret = array(
        'success' => 1,
        'render' => $this->renderEvents(),
        'rules' => $holidays_rules,
        'assistants_rules' => apply_filters('sln.get-day-holidays-assistants-rules', $holidays_assistants_rules, $assistants),
        'statusCounts' => $statusCounts,
        'upcomingToday' => $upcomingTodayData['count'],
        'upcomingTodayBookings' => $upcomingTodayData['bookings'],
        'isFreeVersion' => !defined('SLN_VERSION_PAY'),
      );
    }

    return $ret;
  }

  public function getAttendantMode()
  {
    return $this->attendantMode;
  }

  /**
   * Get stats for calendar bar visualization (works for all dates: past, present, future)
   * This method is decoupled from booking availability logic and "Hours Before" restrictions
   */
  private function getStatsForBar()
  {
    $ah = $this->plugin->getAvailabilityHelper();
    $interval = $this->plugin->getSettings()->getInterval();
    $clone = clone $this->from;
    $ret = array();

    while ($clone <= $this->to) {
      $dd = new Date($clone);
      $tmp = array('text' => '', 'busy' => 0, 'free' => 0);

      // Calculate booking stats (revenue, count)
      $tot = 0;
      $cnt = 0;
      foreach ($this->bookings as $b) {
        if ($b->getDate()->format('Ymd') == $clone->format('Ymd')) {
          if (!$b->hasStatus(array(SLN_Enum_BookingStatus::CANCELED))) {
            $tot += $b->getAmount();
            $cnt++;
          }
        }
      }

      // Get total working time slots for this day (independent of "Hours Before")
      // Use getWorkTimesForStats() to show bars for past dates
      $workTimes = $ah->getWorkTimesForStats($dd);
      $totalWorkingMinutes = count($workTimes) * $interval;
      
      // Debug: log working minutes for troubleshooting
      if (SLN_Plugin::isDebugEnabled()) {
        SLN_Plugin::addLog("Calendar Stats - Date: " . $dd->toString('Y-m-d') . " | Working minutes: " . $totalWorkingMinutes . " | Work time slots: " . count($workTimes));
      }

      // Calculate busy time from actual bookings
      $busyMinutes = 0;
      foreach ($this->bookings as $b) {
        if ($b->getDate()->format('Ymd') == $clone->format('Ymd')) {
          if (!$b->hasStatus(array(SLN_Enum_BookingStatus::CANCELED))) {
            // Calculate duration of this booking
            $services = $b->getBookingServices()->getItems();
            foreach ($services as $service) {
              $duration = $service->getDuration();
              $busyMinutes += SLN_Func::getMinutesFromDuration($duration);
            }
          }
        }
      }

      // Calculate free time
      $freeMinutes = max(0, $totalWorkingMinutes - $busyMinutes);

      // Determine status based on working hours
      if ($totalWorkingMinutes == 0) {
        // No working hours = holiday or non-working day
        $avItems = $ah->getItems();
        $hItems = $ah->getHolidaysItems();
        if (!$hItems->isValidDate($dd)) {
          $tmp['text'] = __('Holiday Rule', 'salon-booking-system');
        } else {
          $tmp['text'] = __('Booking Rule', 'salon-booking-system');
        }
        
        // For past dates with bookings, still show progress bar (100% busy if there were bookings)
        // Calculate based on typical day or actual bookings
        if ($cnt > 0) {
          // If there were bookings, show them as busy (100% busy if no working hours defined)
          $tmp['busy'] = 100;
          $tmp['free'] = 0;
        } else {
          // No bookings and no working hours - show empty bar
          $tmp['busy'] = 0;
          $tmp['free'] = 100;
        }
      } else {
        // Show stats
        $freeH = intval($freeMinutes / 60);
        $freeM = ($freeMinutes % 60);
        $tot = $this->plugin->format()->money($tot, false);
        $tmp['text'] = '<div class="calbar-tooltip">'
          . "<span><strong>$cnt</strong>" . __('bookings', 'salon-booking-system') . "</span>"
          . "<span><strong>$tot</strong>" . __('revenue', 'salon-booking-system') . "</span>"
          . "<span><strong>{$freeH}" . __('hrs', 'salon-booking-system') . ' '
          . ($freeM > 0 ? "{$freeM}" . __('mns', 'salon-booking-system') : '') . '</strong>'
          . __('available left', 'salon-booking-system') . '</span></div>';

        // Calculate percentages for the bar
        $tmp['busy'] = intval(($busyMinutes / $totalWorkingMinutes) * 100);
        $tmp['free'] = 100 - $tmp['busy'];
      }

      $ret[$dd->toString('Y-m-d')] = $tmp;
      $clone->modify('+1 days');
    }
    return $ret;
  }

  /**
   * Legacy method - kept for backward compatibility if needed elsewhere
   * For calendar bar display, use getStatsForBar() instead
   */
  private function getStats()
  {
    return $this->getStatsForBar();
  }


  private function saveAttendantPositions($positions)
  {
    if (!isset($positions) || empty($positions)) {
      return;
    }

    foreach (explode(',', $positions) as $pos => $post_id) {
      if (empty($post_id)) {
        continue;
      }
      update_post_meta($post_id, '_sln_attendant_order', $pos + 1);
    }
  }

  private function getAjaxDayWeekStart(Date $day, $weekStart = null)
  {
    if (empty($weekStart)) {
      $weekStart = $this->plugin->getSettings()->get('week_start');
    }
    $ret = ($day->getWeekday() - $weekStart) % 7;
    if (0 > $ret) $ret = 7 + $ret;
    return $ret;
  }

  private function getBookingStatusCounts()
  {
    $counts = array(
      'paid_confirmed' => 0,
      'pay_later' => 0,
      'pending' => 0,
      'cancelled' => 0,
      'noshow' => 0,
    );

    // Skip expensive calculations for FREE version
    // This avoids iterating through bookings and making meta queries
    if (!defined('SLN_VERSION_PAY')) {
      return $counts;
    }

    if (!empty($this->bookings) && is_array($this->bookings)) {
      foreach ($this->bookings as $booking) {
        if ($booking && method_exists($booking, 'getStatus')) {
          $status = $booking->getStatus();

          // Use string comparison to be safe
          if ($status === 'sln-b-paid' || $status === 'sln-b-confirmed') {
            $counts['paid_confirmed']++;
          } elseif ($status === 'sln-b-paylater') {
            $counts['pay_later']++;
          } elseif ($status === 'sln-b-pending' || $status === 'sln-b-pendingpayment') {
            $counts['pending']++;
          } elseif ($status === 'sln-b-canceled') {
            $counts['cancelled']++;
          }
        }

        // Check if booking is marked as no-show (no_show meta field = 1)
        if ($booking && method_exists($booking, 'getId')) {
          $no_show_meta = get_post_meta($booking->getId(), 'no_show', true);
          if ($no_show_meta == 1) {
            $counts['noshow']++;
          }
        }
      }
    }

    return $counts;
  }

  /**
   * Get count and details of upcoming bookings for today with statuses: paid, confirmed, pay later
   * Only counts bookings starting from the current time onwards
   * Returns array with count and first 6 bookings details
   */
  private function getUpcomingTodayCount()
  {
    try {
      // Use WordPress timezone - getWpTimezone() already returns a DateTimeZone object
      $timezone = SLN_TimeFunc::getWpTimezone();
      $now = new DateTime('now', $timezone);
      $today = new DateTime('today', $timezone);
    } catch (Exception $e) {
      error_log('Error creating DateTime with timezone: ' . $e->getMessage());
      // Fallback to default timezone
      $now = new DateTime('now');
      $today = new DateTime('today');
    }
    
    $count = 0;
    $upcomingBookings = array();
    $isFreeVersion = !defined('SLN_VERSION_PAY');

    if (!empty($this->bookings) && is_array($this->bookings)) {
      $tempBookings = array();
      
      foreach ($this->bookings as $booking) {
        if ($booking && method_exists($booking, 'getDate') && method_exists($booking, 'getStatus') && method_exists($booking, 'getStartsAt')) {
          $bookingDate = $booking->getDate();
          $bookingStartsAt = $booking->getStartsAt();
          $status = $booking->getStatus();
          
          // Check if booking is for today and starts at or after current time
          if ($bookingDate->format('Y-m-d') === $today->format('Y-m-d') && $bookingStartsAt >= $now) {
            // Count only the specified statuses: paid, confirmed, pay later
            if ($status === 'sln-b-paid' || $status === 'sln-b-confirmed' || $status === 'sln-b-paylater') {
              $count++;
              
              // Get shop name for Multi-Shop support
              $shopName = '';
              if (class_exists('\SalonMultishop\Addon')) {
                $shopId = get_post_meta($booking->getId(), '_sln_booking_shop', true);
                if (!empty($shopId)) {
                  $shopName = get_the_title($shopId);
                }
              }
              
              $tempBookings[] = array(
                'id' => $booking->getId(),
                'firstName' => $booking->getFirstName(),
                'lastName' => $booking->getLastName(),
                'startsAt' => $bookingStartsAt->format('Y-m-d H:i:s'),
                'status' => $status,
                'shopName' => $shopName
              );
            }
          }
        }
      }
      
      // Sort by start time
      usort($tempBookings, function($a, $b) {
        return strcmp($a['startsAt'], $b['startsAt']);
      });
      
      // Get only first 6
      $upcomingBookings = array_slice($tempBookings, 0, 6);
    }

    // For FREE version, return count but empty bookings (fake bookings will be shown in frontend)
    return array(
      'count' => $count,
      'bookings' => $isFreeVersion ? array() : $upcomingBookings
    );
  }

  protected function renderEvents()
  {
    $format = SLN_Plugin::getInstance()->format();
    $settings = SLN_Plugin::getInstance()->getSettings();

    $total = 0;
    $nonWorkingTime = true;
    $salonMode = $settings->getAvailabilityMode();
    $this->stopIteration = false;
    $stats = $this->getStats();
    $statusCounts = $this->getBookingStatusCounts();
    if ($this->intervalName == 'day') {
      $render = $this->renderDay();
    } else {
      if ($this->intervalName == 'month') {
        $bookings = array();
        foreach ($this->bookings as $booking) {
          if (isset($bookings[$booking->getStartsAt()->format('Ymd')])) {
            $bookings[$booking->getStartsAt()->format('Ymd')][] = $booking;
          } else {
            $bookings[$booking->getStartsAt()->format('Ymd')] = array($booking);
          }
        }
        $this->bookings = $bookings;
      }
      $render = $this->plugin->loadView('admin/_calendar_render_' . $this->intervalName, array(
        'calendar' => $this,
        'format' => $format,
        'settings' => $settings,
        'booking' => $this->bookings,
        'stats' => $stats,
        'statusCounts' => $statusCounts,
      ));
    }
    return $render;
  }

  public function countBookingsByMonth($month)
  {
    return $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING)->count(array(
      'day@min' => date_modify(clone $this->from, $month . ' month'),
      'day@max' => date_modify(clone $this->from, ($month + 1) . ' month')
    ));
  }
  private function getAvailabilityIndex($w)
  {
    return ((int)$w) + 1;
  }

  public function renderMonthDay($week_number, $day, $stats)
  {
    $settings = $this->plugin->getSettings();

    $weekStart = $settings->get('week_start');
    $firstDayOfMonthWeekday = (int)$this->from->format('w'); // 0 (Sun) .. 6 (Sat)
    $offset = ($firstDayOfMonthWeekday - $weekStart + 7) % 7;
    $dayIndex = $day - $offset;

    $currDate = clone $this->from;
    $currDate->modify("{$dayIndex} days");

    $dayClass = '';
    if ($currDate < $this->from || $currDate > $this->to) {
      $dayClass = 'cls-day-outmonth';
    } else {
      $dayClass = 'cls-day-inmonth';
    }
    if ($currDate >= $this->to) {
      $this->stopIteration = true;
    }
    if ($day <= 0) {
      $dayClass .= ' cal-month-first-row';
    }
    switch ($currDate->format('w')) {
      case 0:
      case 6:
        $dayClass .= ' cal-day-weekend';
        break;
    }
    $bookings = array();
    if (isset($this->bookings[$currDate->format('Ymd')])) {
      foreach ($this->bookings[$currDate->format('Ymd')] as $booking) {
        $bookings[] = array(
          'id' => $booking->getId(),
          'title' => SLN_Func::safe_encoding($this->getTitle($booking), 'UTF-8', 'UTF-8'),
          'time' => $booking->getTime(),
          'class' => $this->isNonWorkingTime($booking) ? '' : "event-" . SLN_Enum_BookingStatus::getColor($booking->getStatus()),
          'amount' => $this->plugin->format()->money($booking->getAmount()),
          'booking' => $booking,
        );
      }
    }
    usort($bookings, function ($a, $b) {
      return $a['time'] <=> $b['time'];
    });

    return $this->plugin->loadView('admin/_calendar_render_month_day', array(
      'day' => $currDate->format('j'),
      'dayClass' => $dayClass,
      'start' => $currDate->modify('-1 day'),
      'end' => date_modify($currDate, '1 day'),
      'booking' => $bookings,
      'stats' => isset($stats[$currDate->format('Y-m-d')]) ? $stats[$currDate->format('Y-m-d')] : array(),
    ));
  }

  private function isNonWorkingTime($booking)
  {
    $settings = $this->plugin->getSettings();
    $nonWorkingTime = true;
    $bookingStartAt = new DateTime($booking->getStartsAt('UTC'));
    $bookingEndAt = new DateTime($booking->getEndsAt('UTC'));

    foreach ($settings->get('availabilities') as $date) {
      if (!isset($date['days'][$this->getAvailabilityIndex((int)$bookingStartAt->format('w'))])) {
        continue;
      }
      foreach (array_map(null, $date['from'], $date['to']) as $interval) {
        $dateFrom = DateTime::createFromFormat('Y-m-d H:i', $bookingStartAt->format('Y-m-d') . ' ' . $interval[0]);
        $dateTo = DateTime::createFromFormat('Y-m-d H:i', $bookingStartAt->format('Y-m-d') . ' ' . $interval[1]);
        if ($settings->getAvailabilityMode() != 'basic') {
          if ($dateFrom <= $bookingStartAt && $dateTo >= $bookingEndAt) {
            $nonWorkingTime = false;
            break;
          }
        } else {
          if ($dateFrom <= $bookingStartAt && $dateTo >= $bookingStartAt) {
            $nonWorkingTime = false;
            break;
          }
        }
        if (!$nonWorkingTime) {
          break;
        }
      }
    }
    return $nonWorkingTime;
  }

  public function isStopIteration()
  {
    return $this->stopIteration;
  }

  public function renderWeekDays()
  {
    $bookings = array();
    foreach ($this->bookings as $booking) {
      $bookings[] = SLN_Helper_CalendarEvent::buildForWeek($booking, $this, $this->plugin);
    }

    usort($bookings, function ($a, $b) {
      return $a->timeStart->getTimestamp() <=> $b->timeStart->getTimestamp();
    });

    if ($this->attendantMode) {
      $bookings = $this->bookingOrderByAssistant($bookings);
    }

    return $this->plugin->loadView('admin/_calendar_render_week_day', array('calendar' => $this, 'bookings' => $bookings, 'attendant' => $this->getassistantsOrder()));
  }

  public function isTimeUnavailable($line, $unavailableTimes)
  {
    $currentTime = $this->getTimeByLine($line);
    $currentTime24h = $this->normalizeTimeFormat($currentTime);
    foreach ($unavailableTimes as $unavailableTime) {
      if ($currentTime24h === $unavailableTime) {
        return true;
      }
    }
    return false;
  }

  private function normalizeTimeFormat($time)
  {
    $time = strtolower(str_replace(' ', '', $time));

    if (strpos($time, 'am') !== false) {
      $time = str_replace('am', '', $time);
      $parts = explode(':', trim($time));
      if ((int)$parts[0] === 12) $parts[0] = '00';
      return sprintf("%02d:%02d", (int)$parts[0], (int)$parts[1]);
    } else if (strpos($time, 'pm') !== false) {
      $time = str_replace('pm', '', $time);
      $parts = explode(':', trim($time));
      if ((int)$parts[0] !== 12) $parts[0] = (int)$parts[0] + 12;
      return sprintf("%02d:%02d", (int)$parts[0], (int)$parts[1]);
    }
    return $time;
  }

  protected function renderDay()
  {
    $format = $this->plugin->format();
    $settings = $this->plugin->getSettings();
    $statusCounts = $this->getBookingStatusCounts();
    $interval = $settings->getInterval();
    $dtInterval = new DateTime('@' . $interval * 60);
    $msPerLine = 60000 * $interval;
    $ai = $settings->getAvailabilityItems();
    $on_page = $settings->get('parallels_hour') * 2 + 1;
    list($start, $end) = $ai->getTimeMinMax();
    $start = explode(':', $start);
    $end = explode(':', $end);
    $start = $this->getFrom()->setTime($start[0], $start[1]);
    $end = $this->getFrom()->setTime($end[0], $end[1]);
    usort($this->bookings, function ($a, $b) {
      return $a->getStartsAt() <=> $b->getStartsAt();
    });

    if (isset($this->bookings[0])) {
      $hours = $start->format('H');
      $minutes = $start->format('i');
      $start->setTimezone($this->bookings[0]->getStartsAt()->getTimezone())->setTime($hours, $minutes);
      $hours = $end->format('H');
      $minutes = $end->format('i');
      $end->setTimezone($this->bookings[0]->getStartsAt()->getTimezone())->setTime($hours, $minutes);
    }
    foreach ($this->bookings as $booking) {
      if ($booking->getStartsAt() < $start) {
        $start = $booking->getStartsAt();
      }
      if ($booking->getEndsAt() > $end) {
        $end = $booking->getEndsAt();
      }
    }
    $this->startTime = $start;
    $this->endTime = $end;

    $timeDiff = $end->diff($start);
    $lines = ($timeDiff->h * 60 + $timeDiff->i) / $interval;
    if ($start->format('H:i') === '00:00' && $end->format('H:i') === '00:00') {
      $lines = (24 * 60) / $interval;
    }
    $by_hour = array();

    foreach ($this->bookings as $booking) {
      $wrappedBooking = array();
      $isMain = true;
      $currBsServices = array();
      foreach ($booking->getBookingServices()->getItems() as $bookingService) {
        $isParallelServiceProcess = $bookingService->getParallelExec();
        $breakDurationMs = SLN_Func::getMinutesFromDuration($bookingService->getBreakDuration());
        if ($breakDurationMs) {
          $currServiceStart = SLN_Helper_CalendarEvent::buildForDay( // add before break part
            $booking,
            $this,
            $bookingService,
            $start->diff($bookingService->getStartsAt()),
            $bookingService->getStartsAt()->diff($bookingService->getBreakStartsAt()),
            $isMain,
            $interval,
            $lines,
            'block',
            ' break-down no-border-top'
          );

          // Only create "after break" event if there's actual time after the break
          $afterBreakDuration = $bookingService->getBreakEndsAt()->diff($bookingService->getEndsAt());
          $afterBreakMinutes = ($afterBreakDuration->h * 60) + $afterBreakDuration->i;
          
          if ($afterBreakMinutes > 0) {
            $currServiceEnd = SLN_Helper_CalendarEvent::buildForDay( // add after break part
              $booking,
              $this,
              $bookingService,
              $start->diff($bookingService->getBreakEndsAt()),
              $afterBreakDuration,
              $isMain,
              $interval,
              $lines,
              'none',
              ' break-up no-border-top'
            );
          }
          
          if (empty($currBsServices) || $this->getAttendantMode()) {
            $currBsServices[] = $currServiceStart;
          } else {
            $currBsServices[array_key_last($currBsServices)]->lines += $currServiceStart->lines;
            $currBsServices[array_key_last($currBsServices)]->displayClass .= $currServiceStart->displayClass;
          }
          
          // Only add the "after break" event if it was created
          if ($afterBreakMinutes > 0) {
            $currBsServices[] = $currServiceEnd;
          }
        } else {
          $currService = SLN_Helper_CalendarEvent::buildForDay(
            $booking,
            $this,
            $bookingService,
            $start->diff($bookingService->getStartsAt()),
            $bookingService->getStartsAt()->diff($bookingService->getEndsAt()),
            $isMain,
            $interval,
            $lines,
            'block',
            ' no-border-top'
          );
          if (empty($currBsServices) || $this->getAttendantMode()) {
            $currBsServices[] = $currService;
          } else {
            $currBsServices[array_key_last($currBsServices)]->lines += $currService->lines;
          }
        }

        $isMain = false;
      }
      if (!$this->attendantMode) {
        $offset = array();
        foreach ($currBsServices as $currBsService) {
          foreach ($by_hour as $bsService) {
            if ($currBsService->isCollide($bsService)) {
              if (!is_null($bsService->left)) {
                $offset[$bsService->left] = $bsService->left;
              }
            }
          }
        }
        for ($index = 0;; $index++) {
          if (!isset($offset[$index])) {
            $offset = $index;
            break;
          }
        }
        foreach ($currBsServices as $bs) {
          $bs->left = $offset;
        }
      }
      $by_hour = array_merge($by_hour, $currBsServices);
    }

    $headers = array();

    if ($this->attendantMode) {
      $times = SLN_Func::getMinutesIntervals();
      $eventsByAttAndId = array();
      $att_col = 0;
      foreach ($this->getAssistantsOrder() as $attId) {
        $attendantsEvent = array();
        $eventsByAttAndId[$attId] = array();
        foreach ($by_hour as $bsEvent) {
          if (in_array($attId, $bsEvent->attendant)) {
            $attendantsEvent[] = $bsEvent;
            $bsEvent->left = null;
            if (count($bsEvent->attendant) > 1) {
              $bsEvent = clone $bsEvent;
            }
            if (isset($eventsByAttAndId[$attId][$bsEvent->id])) {
              $eventsByAttAndId[$attId][$bsEvent->id][] = $bsEvent;
            } else {
              $eventsByAttAndId[$attId][$bsEvent->id] = array($bsEvent);
            }
          }
        }
        $att_offset = 0;
        foreach ($eventsByAttAndId[$attId] as $bookingId => $currBsServices) {
          $offset = array();
          foreach ($currBsServices as $currBsService) {
            foreach ($attendantsEvent as $bsService) {
              if ($currBsService->id == $bsService->id) {
                continue;
              }
              if ($currBsService->isCollide($bsService)) {
                if (!is_null($bsService->left)) {
                  $offset[$bsService->left] = $bsService->left;
                }
              }
            }
          }
          for ($ind = $att_col;; $ind++) {
            if (!isset($offset[$ind])) {
              $offset = $ind;
              break;
            }
          }
          $prev = null;
          foreach ($currBsServices as $ind => $bs) {
            if (!empty($prev) && $prev->top == $bs->top) {
              $offset++;
            }
            $prev = $bs;
            $eventsByAttAndId[$attId][$bookingId][$ind]->left = $offset;
            $att_offset = max($offset - $att_col, $att_offset);
          }
        }
        $att_col += $att_offset + 1;
      }
      $by_hour = array();
      foreach ($this->getAssistantsOrder() as $attId) {
        $att_offset_max = 0;
        $att_offset_min = $on_page * count($eventsByAttAndId);
        $tmpBsEvent = null;
        foreach ($eventsByAttAndId[$attId] as $bsEventArray) {
          foreach ($bsEventArray as $bsEvent) {
            if (isset($tmpBsEvent) && $bsEvent->attendant == $tmpBsEvent->attendant) {
              $att_offset_max = max($att_offset_max, $bsEvent->left, $tmpBsEvent->left);
              $att_offset_min = min($att_offset_min, $bsEvent->left, $tmpBsEvent->left);
            }
            $tmpBsEvent = $bsEvent;
            $by_hour[] = $bsEvent;
          }
        }
        $att_offset = $att_offset_max - $att_offset_min;

        $attendant = $this->plugin->createAttendant($attId);

        if (class_exists('\SalonMultishop\Addon')) {
          $addon = \SalonMultishop\Addon::getInstance();
          $currentShop = $addon->getCurrentShop();
          if ($currentShop) {
            try {
              $attendant = $currentShop->getAttendantWrapper($attendant);
            } catch (\Exception $e) {
              var_dump("Calendar: Failed to get shop wrapper for attendant $attId: " . $e->getMessage());
            }
          }
        }
        $unavailableTimes = array();
        foreach ($times as $time) {
          $dateTime = new DateTime(\Salon\Util\Date::create($this->from)->toString() . ' ' . $time, new DateTimeZone('UTC'));
          //TODO: add method isNotAvailableOnDateDuration and use here
          if (!($attendant->getAvailabilityItems()->isValidDatetimeDuration($dateTime, $dtInterval) &&
            $attendant->getNewHolidayItems()->isValidDatetimeDuration($dateTime, $dtInterval))) {
            $unavailableTimes[] = $time;
          }
        }
        $headers[] = array(
          'id' => $attId,
          'offset' => $att_col,
          'name' => $attendant->getName(),
          'unavailable_times' => $unavailableTimes,
        );
        for (; $att_offset > 0; $att_offset--) {
          $headers[] = null;
        }
      }
    }

    return $this->plugin->loadView('admin/_calendar_render_day', array(
      'calendar' => $this,
      'headers' => $headers,
      'by_hour' => $by_hour,
      'borders' => $on_page,
      'start' => $start,
      'lines' => $lines,
      'format' => $format,
      'stats' => $this->getStats(),
      'statusCounts' => $statusCounts,
    ));
  }

  public function getTimeByLine($line)
  {
    $start_time = clone $this->startTime;
    $interval = $this->plugin->getSettings()->getInterval();
    $start_time->modify($line * $interval . ' minutes');
    // Always return 24-hour format (H:i) for data attributes to ensure proper parsing
    // This fixes the bug where 12-hour format (e.g., "4:00am") fails to parse correctly
    // when passed to the booking form via URL parameters
    return $start_time->format('H:i');
  }

  public function getTimeByLineFormatted($line)
  {
    $start_time = clone $this->startTime;
    $interval = $this->plugin->getSettings()->getInterval();
    $start_time->modify($line * $interval . ' minutes');
    // Return formatted time for display (respects user's time format setting)
    return $this->plugin->format()->time($start_time);
  }

  public function hasHolidaysByLine($line)
  {
    $settings = $this->plugin->getSettings();
    $holidays = $settings->get('holidays') ?: array();
    if (empty($holidays) || !isset($holidays)) {
      return false;
    }
    $interval = $settings->getInterval();
    $time = clone $this->startTime;
    $time->modify($line * $interval . ' minutes');
    
    foreach ($holidays as $holidayRule) {
      // FIX: Handle end-of-day locks (24:00 or 00:00 means end of day)
      // When to_time is 24:00 or 00:00 and it's a same-day lock, treat it as 23:59:59 (end of day)
      $toTime = $holidayRule['to_time'];
      if (($toTime === '24:00' || $toTime === '00:00') && $holidayRule['from_date'] === $holidayRule['to_date']) {
        $toTime = '23:59:59';
      }
      
      $startTime = new DateTime($holidayRule['from_date'] . ' ' . $holidayRule['from_time'], $time->getTimezone());
      $endTime = new DateTime($holidayRule['to_date'] . ' ' . $toTime, $time->getTimezone());
      
      if ($startTime <= $time && $time < $endTime) {
        return true;
      }
    }
    return false;
  }

  public function hasHolidaysDaylyByLine($line, $attId = null)
  {
    $settings = $this->plugin->getSettings();
    $holidays = $settings->get('holidays_daily') ?: array();

    if (!empty($attId)) {
      $attendant = $this->plugin->createAttendant($attId);
      $attendant_holidays = $attendant->getMeta('holidays_daily') ?: array();
      $holidays = array_merge($holidays, $attendant_holidays);
    }

    if (empty($holidays) || !isset($holidays)) {
      return false;
    }
    $interval = $settings->getInterval();
    $time = clone $this->startTime;
    $time->modify($line * $interval . ' minutes');
    
    foreach ($holidays as $holidayRule) {
      // Skip rules without assistant_id if we're checking for a specific assistant
      if (!empty($attId) && isset($holidayRule['assistant_id']) && $holidayRule['assistant_id'] != $attId) {
        continue;
      }
      
      // FIX: Handle end-of-day locks (24:00 or 00:00 means end of day)
      // When to_time is 24:00 or 00:00 and it's a same-day lock, treat it as 23:59:59 (end of day)
      $toTime = $holidayRule['to_time'];
      if (($toTime === '24:00' || $toTime === '00:00') && $holidayRule['from_date'] === $holidayRule['to_date']) {
        $toTime = '23:59:59';
      }
      
      $startTime = new DateTime($holidayRule['from_date'] . ' ' . $holidayRule['from_time'], $time->getTimezone());
      $endTime = new DateTime($holidayRule['to_date'] . ' ' . $toTime, $time->getTimezone());
      
      if ($startTime <= $time && $time < $endTime) {
        return true;
      }
    }
    return false;
  }

  public function hasAttendantHoliday($line, $attId)
  {
    if (empty($attId)) {
      return false;
    }

    $attendant = $this->plugin->createAttendant($attId);

    if (class_exists('\SalonMultishop\Addon')) {
      $addon = \SalonMultishop\Addon::getInstance();
      $currentShop = $addon->getCurrentShop();
      if ($currentShop) {
        try {
          $attendant = $currentShop->getAttendantWrapper($attendant);
        } catch (\Exception $e) {
          var_dump("hasAttendantHoliday: Failed to get shop wrapper for attendant $attId: " . $e->getMessage());
        }
      }
    }

    $interval = $this->plugin->getSettings()->getInterval();

    $holidays = $attendant->getMeta('holidays') ?: array();

    $time = clone $this->startTime;
    $time->modify($line * $interval . ' minutes');

    foreach ($holidays as $holidayRule) {
      // FIX: Handle end-of-day locks (24:00 or 00:00 means end of day)
      // When to_time is 24:00 or 00:00 and it's a same-day lock, treat it as 23:59:59 (end of day)
      $toTime = $holidayRule['to_time'];
      if (($toTime === '24:00' || $toTime === '00:00') && $holidayRule['from_date'] === $holidayRule['to_date']) {
        $toTime = '23:59:59';
      }
      
      $startTime = new DateTime($holidayRule['from_date'] . ' ' . $holidayRule['from_time'], $time->getTimezone());
      $endTime = new DateTime($holidayRule['to_date'] . ' ' . $toTime, $time->getTimezone());
      
      if ($startTime <= $time && $time < $endTime) {
        return true;
      }
    }
    return false;
  }


  public function isAttendantAvailable($attendantId, $day, $isFullDay = false)
  {
    $currDay = $this->getFrom()->modify($day . ' day');
    $interval = $this->plugin->getSettings()->getInterval();
    $interval = new DateTime('@' . $interval * 60);

    $att = $this->plugin->createAttendant($attendantId);

    if (class_exists('\SalonMultishop\Addon')) {
      $addon = \SalonMultishop\Addon::getInstance();
      $currentShop = $addon->getCurrentShop();
      if ($currentShop) {
        $att = $currentShop->getAttendantWrapper($att);
      }
    }

    if ($isFullDay) {
      return $att->getAvailabilityItems()->isValidDate(Date::create($currDay)) &&
        $att->getNewHolidayItems()->isValidDate(Date::create($currDay));
    } else {
      return $att->getAvailabilityItems()->isValidDatetimeDuration($currDay, $interval) &&
        $att->getNewHolidayItems()->isValidDatetimeDuration($currDay, $interval);
    }
  }

  public function isLineInWorkingSchedule(int $line): bool
  {
    $settings = $this->plugin->getSettings();
    $interval = $settings->getInterval();
    $time = (clone $this->startTime)->modify($line * $interval . ' minutes');

    foreach ($settings->get('availabilities') as $rule) {
      $weekday = $this->getAvailabilityIndex((int)$time->format('w'));
      if (empty($rule['days'][$weekday])) {
        continue;
      }
      foreach (array_map(null, $rule['from'], $rule['to']) as list($from, $to)) {
        $start = DateTime::createFromFormat(
          'Y-m-d H:i',
          $time->format('Y-m-d') . " $from",
          $time->getTimezone()
        );
        $end = DateTime::createFromFormat(
          'Y-m-d H:i',
          $time->format('Y-m-d') . " $to",
          $time->getTimezone()
        );
        if ($start <= $time && $time < $end) {
          return true;
        }
      }
    }
    return false;
  }

  private function bookingOrderByAssistant($bookings)
  {
    $orderedBookings = array();
    foreach ($this->getAssistantsOrder() as $att) {
      $orderedBookings[$att] = array();
      foreach ($bookings as $booking) {
        if (in_array($att, $booking->attendant))
          $orderedBookings[$att][] = $booking;
      }
    }
    return $orderedBookings;
  }

  private function buildBookings()
  {
    $this->bookings = $this->plugin
      ->getRepository(SLN_Plugin::POST_TYPE_BOOKING)
      ->get($this->getCriteria());


    if (in_array(SLN_Plugin::USER_ROLE_STAFF, wp_get_current_user()->roles) || in_array(SLN_Plugin::USER_ROLE_WORKER, wp_get_current_user()->roles)) {

      $assistantsIDs = array();

      $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);
      $attendants = $repo->getAll();

      foreach ($attendants as $attendant) {
        if ($attendant->getMeta('staff_member_id') == get_current_user_id() && $attendant->getIsStaffMemberAssignedToBookingsOnly()) {
          $assistantsIDs[] = $attendant->getId();
        }
      }

      if (!empty($assistantsIDs)) {
        $this->bookings = array_filter($this->bookings, function ($booking) use ($assistantsIDs) {
          return array_intersect($assistantsIDs, $booking->getAttendantsIds());
        });
      }
    }
  }

  private function buildAssistants()
  {
    $prepared_args = [
      'post_type'   => SLN_Plugin::POST_TYPE_ATTENDANT,
      //'post_status' => 'publish',
      'orderby'     => 'meta_value',
      'meta_key'    => '_sln_attendant_order',
      'order'       => 'ASC',
    ];

    $this->applyLanguageFilters($prepared_args);

    $current_user = wp_get_current_user();
    $roles = $current_user->roles;
    $repo  = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);

    // if (in_array(SLN_Plugin::USER_ROLE_STAFF, $roles) || in_array(SLN_Plugin::USER_ROLE_WORKER, $roles)) {
    //     $all_assistants = $repo->getAll();
    //     $this->assistants = array_filter($all_assistants, function ($attendant) use ($current_user) {
    //         return $attendant->getMeta('staff_member_id') == $current_user->ID
    //             && $attendant->getIsStaffMemberAssignedToBookingsOnly();
    //     });
    // } else {
    $this->assistants = $repo->get($prepared_args);
    // }

    if (class_exists('\SalonMultishop\Addon')) {
      $shop = isset($_GET['shop']) ? (int)($_GET['shop'])  : 0;
      if ($shop > 0) {
        foreach ($this->assistants as $key => $attendant) {
          $attendant_shops = $attendant->getMeta('shops');
          if (!is_array($attendant_shops)) {
            unset($this->assistants[$key]);
          } else {
            if (!in_array($shop, $attendant_shops)) {
              unset($this->assistants[$key]);
            }
          }
        }
      }
    }
    $this->assistants = apply_filters('sln.action.calendar.assistants', $this->assistants);
  }

  private function applyLanguageFilters(array &$prepared_args)
  {
    // check WPML language settings
    if (defined('ICL_LANGUAGE_CODE') && function_exists('icl_object_id')) {
      global $sitepress;
      if ($sitepress && method_exists($sitepress, 'get_default_language')) {
        $prepared_args['suppress_filters'] = false;
        // Switch to the primary language.
        $sitepress->switch_lang($sitepress->get_default_language());
        return;
      }
    }

    // check polylang settings
    if (function_exists('pll_languages_list')) {
      $prepared_args['lang'] = $this->getPrimaryLanguage();
    }
  }

  private function getPrimaryLanguage()
  {
    // WPML
    if (defined('ICL_LANGUAGE_CODE') && function_exists('icl_object_id')) {
      global $sitepress;
      if ($sitepress && method_exists($sitepress, 'get_default_language')) {
        return $sitepress->get_default_language();
      }
    }

    // Polylang
    if (function_exists('pll_default_language')) {
      return pll_default_language();
    }

    $locale = get_option('WPLANG');
    return !empty($locale) ? substr($locale, 0, 2) : 'en';
  }

  private function getAssistantsOrder()
  {
    $assistants = $this->assistants;

    usort($assistants, function ($a, $b) {
      $orderA = $a->getMeta('order');
      $orderB = $b->getMeta('order');

      if (empty($orderA) && empty($orderB)) {
        return 0;
      } elseif (empty($orderA)) {
        return 1;
      } elseif (empty($orderB)) {
        return -1;
      }

      return $orderA <=> $orderB;
    });

    $ordered_ids = array_map(function ($assistant) {
      return $assistant->getId();
    }, $assistants);

    return array_unique($ordered_ids);
  }

  public function getDuplicateActionPostLink($id = 0, $context = 'display')
  {

    $action_name = "sln_duplicate_post";

    if ('display' == $context) {
      $action = '?action=' . $action_name . '&amp;post=' . $id;
    } else {
      $action = '?action=' . $action_name . '&post=' . $id;
    }

    return wp_nonce_url(admin_url("admin.php" . $action), 'sln_duplicate-post_' . $id);
  }

  private function getCriteria()
  {
    $criteria = array();
    if ($this->from->format('Y-m-d') == $this->to->format('Y-m-d')) {
      $criteria['day'] = $this->from;
    } else {
      $criteria['day@min'] = $this->from;
      $criteria['day@max'] = $this->to;
    }
    
    // Apply filter FIRST - let Multi-Shop add its criteria
    $criteria = apply_filters('sln.action.ajaxcalendar.criteria', $criteria);
    
    // Multi-Shop Support: ONLY add shop filtering if Multi-Shop hasn't already done it
    // This ensures calendar events, "Upcoming Reservations" and "Calbar Tooltips" respect shop selection
    if (class_exists('\SalonMultishop\Addon') && !isset($criteria['shop'])) {
      $shopId = $this->getCurrentShopId();
      if ($shopId > 0) {
        $criteria['shop'] = $shopId;
      }
    }

    return $criteria;
  }
  
  /**
   * Get current shop ID from Multi-Shop add-on
   * Supports both GET parameter and addon instance methods
   * 
   * @return int Shop ID or 0 if no shop selected
   */
  private function getCurrentShopId()
  {
    // Method 1: Check GET parameter (standard Multi-Shop approach)
    if (isset($_GET['shop']) && intval($_GET['shop']) > 0) {
      return intval($_GET['shop']);
    }
    
    // Method 2: Check Multi-Shop addon instance
    if (class_exists('\SalonMultishop\Addon')) {
      try {
        $addon = \SalonMultishop\Addon::getInstance();
        if ($addon && method_exists($addon, 'getCurrentShop')) {
          $currentShop = $addon->getCurrentShop();
          if ($currentShop && method_exists($currentShop, 'getId')) {
            return intval($currentShop->getId());
          }
        }
      } catch (\Exception $e) {
        // Silent fail - log if needed
        error_log('Calendar: Failed to get current shop ID: ' . $e->getMessage());
      }
    }
    
    return 0; // No shop selected (all shops)
  }

  public function getTitle($booking)
  {
    return $this->plugin->loadView('admin/_calendar_title', compact('booking'));
  }

  private function getEventHtml($booking)
  {
    return $this->plugin->loadView('admin/_calendar_event', compact('booking'));
  }

  private function getCalendarDay($booking)
  {
    return $this->plugin->loadView('admin/_calendar_day', compact('booking'));
  }

  private function getCalendarDayAssistants($booking)
  {
    $calendarDayAssistants = array();

    foreach ($booking->getBookingServices()->getItems() as $bookingService) {
      $calendarDayAssistants[$bookingService->getService()->getId()] = $this->plugin->loadView('admin/_calendar_day_assistant', compact('booking', 'bookingService'));
    }

    return $calendarDayAssistants;
  }

  private function getCalendarDayAssistant($booking, $bookingService)
  {
    return $this->plugin->loadView('admin/_calendar_day_assistant', compact('booking', 'bookingService'));
  }

  private function getCalendarDayAssistantsCommon($booking)
  {
    return $this->plugin->loadView('admin/_calendar_day_assistant_common', compact('booking', 'booking'));
  }

  private function getCalendarDayTitleAssistants($booking)
  {
    $calendarDayAssistants = array();

    foreach ($booking->getBookingServices()->getItems() as $bookingService) {
      $calendarDayAssistants[$bookingService->getService()->getId()] = $this->plugin->loadView('admin/_calendar_day_title_assistant', compact('booking', 'bookingService'));
    }

    return $calendarDayAssistants;
  }

  private function getBookingServiceTitle($booking, $bookingServiceArray)
  {
    $servicesIds = array();
    foreach ($bookingServiceArray['items'] as &$item) {
      if (empty($item['service'])) {
        $item['service'] = array_diff($bookingServiceArray['services'], $servicesIds)[0];
      }
      $servicesIds[] = $item['service'];
      $bookingService = new SLN_Wrapper_Booking_Service($item);
      $item['title'] = SLN_Func::safe_encoding($this->plugin->loadView('admin/_calendar_title', compact('bookingService', 'booking')), 'UTF-8', 'UTF-8');
    }
    return $bookingServiceArray;
  }
}
