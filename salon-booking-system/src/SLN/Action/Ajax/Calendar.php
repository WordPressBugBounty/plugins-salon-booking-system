<?php

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

  public function getFrom(){
    return clone $this->from;
  }

  public function execute()
  {
    $this->attedant_mode = get_user_meta(get_current_user_id(), '_assistants_mode', true) == 'true';
    $offset = intval($_GET['offset']) * 60;
    $offsetEnd = isset($_GET['offsetEnd']) ? intval($_GET['offsetEnd']) * 60 : $offset;
    $this->from = (new SLN_DateTime)->setTimestamp(sanitize_text_field(wp_unslash($_GET['from'])) / 1000 - $offset)->setTimezone(new DateTimeZone('UTC'));
    $this->to = (new SLN_DateTime)->setTimestamp(sanitize_text_field(wp_unslash($_GET['to'])) / 1000 - $offsetEnd)->setTimezone(new DateTimeZone('UTC'))->sub(new DateInterval('P1D'));
    $dateDiff = $this->to->diff($this->from);
    $this->isYearly = $dateDiff->m == 11 || $dateDiff->m == 12;

    if(isset($_GET['_assistants_mode']) && in_array(wp_unslash($_GET['_assistants_mode']), array('true', 'false'))){
      $this->attendantMode = update_user_meta(get_current_user_id(), '_assistants_mode', wp_unslash($_GET['_assistants_mode']));
      $this->attendantMode = wp_unslash($_GET['_assistants_mode']) == 'true';
    }

    if($dateDiff->days > 32){
      $this->intervalName = 'year';
    }else if($dateDiff->days > 6){
      $this->intervalName = 'month';
    }else if($dateDiff->days > 1){
      $this->intervalName = 'week';
    }else{
      $this->intervalName = 'day';
    }
    $this->buildBookings();
    $this->buildAssistants();
    $this->saveAttendantPositions($_GET['assistant_position']);

    if ($this->isYearly) {
      $ret = array(
        'success' => 1,
        'render' => $this->renderEvents(),
      );
    } else {
      $ret = array(
        'success' => 1,
        'render' => $this->renderEvents(),
      );
    }

    return $ret;
  }

  public function getAttendantMode(){
    return $this->attendantMode;
  }

  private function getStats()
  {
    $bc = $this->plugin->getBookingCache();
    $clone = clone $this->from;
    $ret = array();
    while ($clone <= $this->to) {
      $dd = new Date($clone);
      $tmp = array('text' => '', 'busy' => 0, 'free' => 0);
      $bc->processDate($dd);
      $cache = $bc->getDay($dd);
      if ($cache && $cache['status'] == 'booking_rules') {
        $tmp['text'] = __('Booking Rule', 'salon-booking-system');
      } elseif ($cache && $cache['status'] == 'holiday_rules') {
        $tmp['text'] = __('Holiday Rule', 'salon-booking-system');
      } else {
        $tot = 0;
        $cnt = 0;
        foreach ($this->bookings as $b) {
          if ($b->getDate()->format('Ymd') == $clone->format('Ymd')) {
            if (!$b->hasStatus(
              array(
                SLN_Enum_BookingStatus::CANCELED,
              )
            )
            ) {
              $tot += $b->getAmount();
              $cnt++;
            }
          }
        }
        if (isset($cache['free_slots'])) {
          $free = count($cache['free_slots']) * $this->plugin->getSettings()->getInterval();
        } else {
          $free = 0;
        }
        if (isset($cache['busy_slots'])) {
          $busy = count($cache['busy_slots']) * $this->plugin->getSettings()->getInterval();
        } elseif ($cache && $cache['status'] == 'full') {
          $busy = 1;
        } else {
          $busy = 0;
        }
        $freeH = intval($free / 60);
        $freeM = ($free % 60);
        $tot = $this->plugin->format()->money($tot, false);
        $tmp['text'] = '<div class="calbar-tooltip">'
          . "<span><strong>$cnt</strong>" . __('bookings', 'salon-booking-system') . "</span>"
          . "<span><strong>$tot</strong>" . __('revenue', 'salon-booking-system') . "</span>"
          . "<span><strong>{$freeH}" . __('hrs', 'salon-booking-system') . ' '
          . ($freeM > 0 ? "{$freeM}" . __('mns', 'salon-booking-system') : '') . '</strong>'
          . __('available left', 'salon-booking-system') . '</span></div>';
        if ($free || $busy) {
          $tmp['free'] = intval(($free / ($free + $busy)) * 100);
          $tmp['busy'] = 100 - $tmp['free'];
        }
      }
      $ret[$dd->toString('Y-m-d')] = $tmp;
      $clone->modify('+1 days');
    }
    return $ret;
  }

  private function saveAttendantPositions($positions)
  {
    if (!isset($positions)) {
      return;
    }

    foreach (explode(',', $positions) as $pos => $post_id) {
      update_post_meta($post_id, '_sln_attendant_order', $pos + 1);
    }
  }

  private function getAssistantsOrder()
  {
	$ret = array();
	  foreach ($this->assistants as $att) {
		  $position = $att->getMeta('order');
		  if (!empty($position)) {
			if (!isset($ret[$position])) {
			  $ret[$position] = array();
			}
			  $ret[$position][] = $att->getId();
		  } else {
			$ret[] = $att->getId();
		  }
	  }
		ksort($ret);
		$ordered = array();
		foreach ($ret as $pos => $ids) {
		  if (is_array($ids)) {
			foreach ($ids as $id) {
			  $ordered[] = $id;
			}
		  } else {
			$ordered[] = $ids;
		  }
		}
	return $ordered;
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

  protected function renderEvents(){
    $format = SLN_Plugin::getInstance()->format();
    $settings = SLN_Plugin::getInstance()->getSettings();

    $total = 0;
    $nonWorkingTime = true;
    $salonMode = $settings->getAvailabilityMode();
    $this->stopIteration = false;
    $stats = $this->getStats();
    if($this->intervalName == 'day'){
      $render = $this->renderDay();
    }else{
      if($this->intervalName == 'month'){
        $bookings = array();
        foreach($this->bookings as $booking){
          if(isset($bookings[$booking->getStartsAt()->format('Ymd')])){
            $bookings[$booking->getStartsAt()->format('Ymd')][] = $booking;
          }else{
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
      ));
    }
    return $render;
  }

  public function countBookingsByMonth($month){
    return $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING)->count(array(
      'day@min' => date_modify(clone $this->from, $month . ' month'),
      'day@max' => date_modify(clone $this->from, ($month+1) . ' month')
    ));
  }

  public function renderMonthDay($week_number, $day, $stats){
    $settings = $this->plugin->getSettings();
    $firstDay = $this->from->format('N');
    $weekStart = $settings->get('week_start');
    $firstDay = abs(intval($firstDay) - intval($weekStart)) % 7;
    $day -= $firstDay;
    $currDate = clone $this->from;
    $currDate->modify($day . ' day');

    $dayClass = '';
    if($currDate < $this->from || $currDate > $this->to){
      $dayClass = 'cls-day-outmonth';
    }else{
      $dayClass = 'cls-day-inmonth';
    }
    if($currDate >= $this->to){
      $this->stopIteration = true;
    }
    if($day <= 0){
      $dayClass .= ' cal-month-first-row';
    }
    switch($currDate->format('w')){
      case 0:
      case 6:
        $dayClass .= ' cal-day-weekend';
        break;
    }
    $bookings = array();
    if(isset($this->bookings[$currDate->format('Ymd')])){
      foreach($this->bookings[$currDate->format('Ymd')] as $booking){
        $bookings[] = array(
          'id' => $booking->getId(),
          'title' => mb_convert_encoding($this->getTitle($booking), 'UTF-8', 'UTF-8'),
          'time' => $booking->getTime(),
          'class' => $this->isNonWorkingTime($booking) ? '' : "event-" . SLN_Enum_BookingStatus::getColor($booking->getStatus()),
          'amount' => $this->plugin->format()->money($booking->getAmount()),
          'booking' => $booking,
        );
      }
    }
    usort($bookings, function($a, $b) {
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

  private function isNonWorkingTime($booking){
    $settings = $this->plugin->getSettings();
    $nonWorkingTime = true;
    $bookingStartAt = new DateTime($booking->getStartsAt('UTC'));
    $bookingEndAt = new DateTime($booking->getEndsAt('UTC'));

    foreach ($settings->get('availabilities') as $date) {
      if (!isset($date['days'][$bookingStartAt->format('w') + 1])) {
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

  public function isStopIteration(){
    return $this->stopIteration;
  }

  public function renderWeekDays(){
    $bookings = array();
    foreach($this->bookings as $booking){
      $bookings[] = SLN_Helper_CalendarEvent::buildForWeek($booking, $this, $this->plugin);
    }

    usort($bookings, function($a, $b) {
      return $a->timeStart->getTimestamp() <=> $b->timeStart->getTimestamp();
    });

    if($this->attendantMode){
      $bookings = $this->bookingOrderByAssistant($bookings);
    }

    return $this->plugin->loadView('admin/_calendar_render_week_day', array('calendar' => $this, 'bookings' => $bookings, 'attendant' => $this->getassistantsOrder()));
  }

  protected function renderDay(){
    $format = $this->plugin->format();
    $settings = $this->plugin->getSettings();
    $interval = $settings->getInterval();
    $dtInterval = new DateTime('@'. $interval*60);
    $msPerLine = 60000 * $interval;
    $ai = $settings->getAvailabilityItems();
    $on_page = $settings->get('parallels_hour') * 2 + 1;
    list($start, $end) = $ai->getTimeMinMax();
    $start = explode(':', $start);
    $end = explode(':', $end);
    $start = $this->getFrom()->setTime($start[0], $start[1]);
    $end = $this->getFrom()->setTime($end[0], $end[1]);
    usort($this->bookings, function($a, $b) {
        return $a->getStartsAt() <=> $b->getStartsAt();
    });

     if(isset($this->bookings[0])){
        $hours = $start->format('H');
        $minutes = $start->format('i');
        $start->setTimezone($this->bookings[0]->getStartsAt()->getTimezone())->setTime($hours, $minutes);
        $hours = $end->format('H');
        $minutes = $end->format('i');
        $end->setTimezone($this->bookings[0]->getStartsAt()->getTimezone())->setTime($hours, $minutes);
    }
    foreach($this->bookings as $booking){
      if($booking->getStartsAt() < $start){
        $start = $booking->getStartsAt();
      }
      if($booking->getEndsAt() > $end){
        $end = $booking->getEndsAt();
      }
    }
    $this->startTime = $start;
    $this->endTime = $end;

    $timeDiff = $end->diff($start);
    $lines = ($timeDiff->h*60 + $timeDiff->i) / $interval;
    $by_hour = array();

    foreach($this->bookings as $booking){
      $wrappedBooking = array();
      $isMain = true;
      $currBsServices = array();
      foreach($booking->getBookingServices()->getItems() as $bookingService){
        $isParallelServiceProcess = $bookingService->getParallelExec();
        $breakDurationMs = SLN_Func::getMinutesFromDuration($bookingService->getBreakDuration());
        if($breakDurationMs){
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

          $currServiceEnd = SLN_Helper_CalendarEvent::buildForDay( // add after break part
            $booking,
            $this,
            $bookingService,
            $start->diff($bookingService->getBreakEndsAt()),
            $bookingService->getBreakEndsAt()->diff($bookingService->getEndsAt()),
            $isMain,
            $interval,
            $lines,
            'none',
            ' break-up no-border-top'
          );
          if(empty($currBsServices) || $this->getAttendantMode()){
            $currBsServices[] = $currServiceStart;
          }else{
            $currBsServices[array_key_last($currBsServices)]->lines += $currServiceStart->lines;
            $currBsServices[array_key_last($currBsServices)]->displayClass .= $currServiceStart->displayClass;
          }
          $currBsServices[] = $currServiceEnd;
        }else{
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
          if(empty($currBsServices) || $this->getAttendantMode()){
            $currBsServices[] = $currService;
          }else{
            $currBsServices[array_key_last($currBsServices)]->lines += $currService->lines;
          }
        }

        $isMain = false;
      }
      if(!$this->attendantMode){
        $offset = array();
        foreach($currBsServices as $currBsService){
          foreach($by_hour as $bsService){
            if($currBsService->isCollide($bsService)){
              if(!is_null($bsService->left)){
                $offset[$bsService->left] = $bsService->left;
              }
            }
          }
        }
        for($index = 0; ; $index++){
          if(!isset($offset[$index])){
            $offset = $index;
            break;
          }
        }
        foreach($currBsServices as $bs){
          $bs->left = $offset;
        }
      }
      $by_hour = array_merge($by_hour, $currBsServices);
    }


    $headers = array();

    if($this->attendantMode){
      $times = SLN_Func::getMinutesIntervals();
      $eventsByAttAndId = array();
      $att_col = 0;
      foreach($this->getAssistantsOrder() as $attId){
        $attendantsEvent = array();
        $eventsByAttAndId[$attId] = array();
        foreach($by_hour as $bsEvent){
          if(in_array($attId, $bsEvent->attendant)){
              $attendantsEvent[] = $bsEvent;
              $bsEvent->left = null;
              if(count($bsEvent->attendant) > 1){
                $bsEvent = clone $bsEvent;
              }
              if(isset($eventsByAttAndId[$attId][$bsEvent->id])){
                $eventsByAttAndId[$attId][$bsEvent->id][] = $bsEvent;
              }else{
                $eventsByAttAndId[$attId][$bsEvent->id] = array($bsEvent);
              }
          }
        }
        $att_offset = 0;
        foreach($eventsByAttAndId[$attId] as $bookingId => $currBsServices){
          $offset = array();
          foreach($currBsServices as $currBsService){
            foreach($attendantsEvent as $bsService){
              if($currBsService->id == $bsService->id){
                continue;
              }
              if($currBsService->isCollide($bsService)){
                if(!is_null($bsService->left)){
                  $offset[$bsService->left] = $bsService->left;
                }
              }
            }
          }
          for($ind = $att_col; ; $ind++){
            if(!isset($offset[$ind])){
              $offset = $ind;
              break;
            }
          }
          $prev = null;
          foreach($currBsServices as $ind => $bs){
            if(!empty($prev) && $prev->top == $bs->top){
              $offset++;
            }
            $prev = $bs;
            $eventsByAttAndId[$attId][$bookingId][$ind]->left = $offset;
            $att_offset = max($offset-$att_col, $att_offset);
          }
        }
        $att_col += $att_offset+1;
      }
      $by_hour = array();
      foreach($this->getAssistantsOrder() as $attId){
        $att_offset_max = 0;
        $att_offset_min = $on_page * count($eventsByAttAndId);
        $tmpBsEvent = null;
        foreach($eventsByAttAndId[$attId] as $bsEventArray){
          foreach($bsEventArray as $bsEvent) {
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
        $unavailableTimes = array();
        foreach ($times as $time) {
          $dateTime = new DateTime(Date::create($this->from)->toString() . ' ' . $time, new DateTimeZone('UTC'));
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
        for(; $att_offset > 0; $att_offset--){
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
    ));
  }

  public function getTimeByLine($line){
    $start_time = clone $this->startTime;
    $interval = $this->plugin->getSettings()->getInterval();
    $start_time->modify($line*$interval. ' minutes');
    return $this->plugin->format()->time($start_time);
  }

  public function hasHolidaysByLine($line){
    $settings = $this->plugin->getSettings();
    $holidays = $settings->get('holidays') ?: array();
    $holidays = array_merge($holidays, $settings->get('holidays_daily') ?: array());
    if(empty($holidays) || !isset($holidays)){
      return false;
    }
    $interval = $settings->getInterval();
    $time = clone $this->startTime;
    $time->modify($line*$interval. ' minutes');
    foreach($holidays as $holidayRule){
      $startTime = new DateTime($holidayRule['from_date'] . ' ' . $holidayRule['from_time'], $time->getTimezone());
      $endTime = new DateTime($holidayRule['to_date'] . ' ' . $holidayRule['to_time'], $time->getTimezone());
      if($startTime <= $time && $time < $endTime){
        return true;
      }
    }
    return false;
  }

  public function hasAttendantHoliday($line, $attId){
    if(empty($attId)){
      return false;
    }
    $interval = $this->plugin->getSettings()->getInterval();

    $attendant = $this->plugin->createAttendant($attId);
    $holidays = $attendant->getMeta('holidays') ?: array();
    $holidays = array_merge($holidays, $attendant->getMeta('holidays_daily') ?: array());
    $time = clone $this->startTime;
    $time->modify($line*$interval. ' minutes');
    foreach($holidays as $holidayRule){
      $startTime = new DateTime($holidayRule['from_date'] . ' ' . $holidayRule['from_time'], $time->getTimezone());
      $endTime = new DateTime($holidayRule['to_date'] . ' ' . $holidayRule['to_time'], $time->getTimezone());
      if($startTime <= $time && $time < $endTime){
        return true;
      }
    }
    return false;
  }

  public function isAttendantAvailable($attendantId, $day, $isFullDay=false){
    $currDay = $this->getFrom()->modify($day. ' day');
    $interval = $this->plugin->getSettings()->getInterval();
    $interval = new DateTime('@'. $interval * 60);
    $att = $this->plugin->createAttendant($attendantId);
    if($isFullDay){
      return $att->getAvailabilityItems()->isValidDate(Date::create($currDay)) && $att->getNewHolidayItems()->isValidDate(Date::create($currDay));
    }else{
      return $att->getAvailabilityItems()->isValidDatetimeDuration($currDay, $interval) && $att->getNewHolidayItems()->isValidDatetimeDuration($currDay, $interval);
    }
  }

  private function bookingOrderByAssistant($bookings){
    $orderedBookings = array();
    foreach($this->getAssistantsOrder() as $att){
      $orderedBookings[$att] = array();
      foreach($bookings as $booking){
        if(in_array($att, $booking->attendant))
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
    $this->assistants = $this->plugin
      ->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT)
      ->getAll();

    $this->assistants = apply_filters('sln.action.ajaxcalendar.assistants', $this->assistants);

    if (in_array(SLN_Plugin::USER_ROLE_STAFF, wp_get_current_user()->roles) || in_array(SLN_Plugin::USER_ROLE_WORKER, wp_get_current_user()->roles)) {
      $assistants = array_filter($this->assistants, function ($attendant) {
        return $attendant->getMeta('staff_member_id') == get_current_user_id() && $attendant->getIsStaffMemberAssignedToBookingsOnly();
      });
      if (!empty($assistants)) {
        $this->assistants = $assistants;
      }
    }
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
    $criteria = apply_filters('sln.action.ajaxcalendar.criteria', $criteria);

    return $criteria;
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

  private function getCalendarDayAssistant($booking, $bookingService){
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
      $item['title'] = mb_convert_encoding($this->plugin->loadView('admin/_calendar_title', compact('bookingService', 'booking')), 'UTF-8', 'UTF-8');
    }
    return $bookingServiceArray;
  }
}
