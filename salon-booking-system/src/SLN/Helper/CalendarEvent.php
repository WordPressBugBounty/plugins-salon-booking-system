<?php

class SLN_Helper_CalendarEvent implements ArrayAccess{

	private $plugin;
	public $id;
	public $title;
	public $from;
    public $timeStart;
	public $to;
	public $isMain;
	public $customer;
	public $customerId;
	public $amount;
	public $discount;
	public $deposit;
	public $due;
	public $attendant;
	public $displayClass;
	public $top;
	public $lines;
	public $displayState;
	public $calendar_day;
	public $duplicate_url;
	public $shop;
	public $tooltipTitle;

	public function setPlugin(SLN_Plugin $plugin){
		$this->plugin = $plugin;
	}

	public function offsetSet($offset, $value){
		if($this->offsetExists($offset)){
			$this->$offset = $value;
		}
	}

	public function offsetExists($offset){
		return property_exists($this, $offset);
	}

	public function offsetGet($offset){
		if(!$this->offsetExists($offset)){
			throw new ValueError('Unexpected index of array '. esc_html($offset));
		}
		return $this->$offset;
	}

	public function offsetUnset($offset){
		if($this->offsetExists($offset)){
			$this->$offset = null;
		}
	}

	public static function buildForWeek($booking, $calendar, $plugin){
		$format = $plugin->format();
		$discountAmount = apply_filters('sln.action.ajaxcalendar.wrapBooking.discountAmount', 0, $booking);
		$event = new SLN_Helper_CalendarEvent;
		$event->setPlugin($plugin);
		$event->id = $booking->getId();
		$event->title = SLN_Func::safe_encoding($calendar->getTitle($booking), 'UTF-8', 'UTF-8');
		$event->customer = SLN_Func::safe_encoding($booking->getDisplayName(), 'UTF-8', 'UTF-8');
		$event->customerId = $booking->getUserId();
		$event->amount = $format->money($booking->getAmount()+$discountAmount, false, true);
		$event->discount = $format->money($discountAmount, false, true);
		$event->deposit = $format->money($booking->getDeposit(), false, true);
		$event->due = $format->money($booking->getAmount(), false, true);
		$event->displayClass = ($event->isNonWorkingTime($booking) ? '' : "event-" . SLN_Enum_BookingStatus::getColor($booking->getStatus()));
		$event->attendant = $booking->getAttendantsIds();
		$event->from = $booking->getStartsAt()->format('N');
        $event->timeStart = $booking->getStartsAt();
        $event = apply_filters('sln.action.ajaxcalendar.wrapBooking', $event, $booking);
		return $event;
	}

	public static function buildForDay($booking, $calendar, $bookingService, $dayStartServiceStartDiff, $serviceDuration, $isMain, $interval, $linse, $displayState, $addedClass){
		$plugin = SLN_Plugin::getInstance();
		$format = $plugin->format();
	    $top = ($dayStartServiceStartDiff->h*60 + $dayStartServiceStartDiff->i) / $interval;
	    $linesInEvent = ($serviceDuration->h*60 + $serviceDuration->i) / $interval;
	    $discountAmount = apply_filters('sln.action.ajaxcalendar.wrapBooking.discountAmount', 0, $booking);
	    $attendant = $bookingService->getAttendant();
	    if(is_object($attendant)){
	        $attendant = array($attendant->getId());
	    }elseif(isset($attendant) && $bookingService->getService()->isMultipleAttendantsForServiceEnabled()){
	        $attendant = SLN_Wrapper_Attendant::getArrayAttendantsValue('getId', $attendant);
	    }else{
	        $attendant = array($attendant);
	    }

		$event = new SLN_Helper_CalendarEvent();
		$event->setPlugin($plugin);
		$event->id = $booking->getId();
		$event->title = $isMain ? SLN_Func::safe_encoding($calendar->getTitle($booking), 'UTF-8', 'UTF-8') : '';
		$event->from = $isMain ? $format->time($booking->getStartsAt()) : '';
		$event->to = $isMain ? $format->time($booking->getEndsAt()) : '';
		$event->main = $isMain;
		$event->customer = SLN_Func::safe_encoding($booking->getDisplayName(), 'UTF-8', 'UTF-8');
		$event->customerId = $booking->getUserId();
		$event->amount = $format->money($booking->getAmount() + $discountAmount, false, true);
		$event->discount = $format->money($discountAmount, false, true);
		$event->deposit = $format->money($booking->getDeposit(), false, true);
		$event->due = $format->money($booking->getAmount()-$booking->getDeposit(), false, true);
		$event->attendant = $attendant;
		$event->displayClass = ($event->isNonWorkingTime($booking) ? '' : "event-" . SLN_Enum_BookingStatus::getColor($booking->getStatus())) . $addedClass;
		$event->top = $top;
		$event->lines = max($linesInEvent, 1);
		$event->display_state = $displayState;
		$event->calendar_day = $calendar->getAttendantMode() ? $event->getCalendarDayAssistant($booking, $bookingService) : ($isMain ? SLN_Func::safe_encoding($event->getCalendarDay($booking), 'UTF-8', 'UTF-8'): '');
		$event = apply_filters('sln.action.ajaxcalendar.wrapBooking', $event, $booking);
		$event->tooltipTitle = SLN_Func::safe_encoding($calendar->getTitle($booking), 'UTF-8', 'UTF-8');
		return $event;
	}

	private function getCalendarDayAssistant($booking, $bookingService){
	return $this->plugin->loadView('admin/_calendar_day_assistant', compact('booking', 'bookingService'));
	}

	private function getCalendarDay($booking)
	{
	return $this->plugin->loadView('admin/_calendar_day', compact('booking'));
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

	public function isCollide(SLN_Helper_CalendarEvent $event){
		return (
			$this->top <= $event->top
			&& $event->top < $this->top + $this->lines
		) || (
			$event->top <= $this->top
			&& $this->top < $event->top + $event->lines
		);
	}
}