<?php
class SLN_Admin_SettingTabs_BookingTab extends SLN_Admin_SettingTabs_AbstractTab {
	protected $fields = array(
		'confirmation',
		'reservation_interval_enabled', // algolplus
		'minutes_between_reservation', // algolplus
		'availabilities',
		'holidays', // algolplus
		'availability_mode',
		'do_not_nest_same_booking_services', // sequential multi-service bookings: no start-during-break chaining
		'nested_bookings_enabled', // nested bookings feature
		'cancellation_enabled', // algolplus
		'hours_before_cancellation', // algolplus
		'auto_trash_cancelled', // auto-trash cancelled bookings
		'disabled',
		'disabled_message',
		'confirmation',
		'parallels_day',
		'parallels_hour',
		'hours_before_from',
		'hours_before_to',
		'interval',
		'auto_align_slots', // auto-align time slots to service duration
		'form_steps_alt_order',
		'multiple_customers_for_assistant',
		'rescheduling_disabled',
		'days_before_rescheduling',
	);

	protected function validate() {
		if (!isset($this->submitted['disabled'])) {
			$this->submitted['disabled'] = 0;
		}

		$availability_mode = isset($this->submitted['availability_mode']) && $this->submitted['availability_mode'] !== ''
			? $this->submitted['availability_mode']
			: $this->settings->getAvailabilityMode();

		// Nesting logic options panel: High-end only (matches settings UI).
		if ($availability_mode !== 'highend') {
			$this->submitted['do_not_nest_same_booking_services'] = 0;
		} elseif (!isset($this->submitted['do_not_nest_same_booking_services'])) {
			$this->submitted['do_not_nest_same_booking_services'] = 0;
		}

		if (!defined('SLN_VERSION_PAY') || $availability_mode !== 'highend') {
			$this->submitted['nested_bookings_enabled'] = 0;
		} elseif (!isset($this->submitted['nested_bookings_enabled'])) {
			$this->submitted['nested_bookings_enabled'] = 0;
		}

		if (isset($this->submitted['availabilities'])) {
			$this->submitted['availabilities'] = SLN_Helper_AvailabilityItems::processSubmission(
				$this->submitted['availabilities']
			);
		}

		if (isset($this->submitted['holidays'])) {
			$this->submitted['holidays'] = SLN_Helper_HolidayItems::processSubmission($this->submitted['holidays']);
		}
	}

	protected function postProcess() {
		$this->plugin->getBookingCache()->refreshAll();
		if ($this->settings->getAvailabilityMode() != 'highend') {
			$repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
			foreach ($repo->getAll() as $service) {
				$service->setMeta('break_duration', SLN_Func::convertToHoursMins(0));
			}
		}
	}
}
?>
