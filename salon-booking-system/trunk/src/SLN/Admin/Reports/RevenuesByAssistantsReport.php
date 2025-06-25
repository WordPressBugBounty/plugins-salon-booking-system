<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

class SLN_Admin_Reports_RevenuesByAssistantsReport extends SLN_Admin_Reports_AbstractReport {

	protected $type = 'bar';

	protected function getBookingStatuses() {
		return array(
			SLN_Enum_BookingStatus::PAID,
			SLN_Enum_BookingStatus::PAY_LATER,
			SLN_Enum_BookingStatus::CONFIRMED,
		);
	}

	protected function processBookings($day = null, $month_num = null, $year = null, $hour = null) {

		$ret = array();
		$ret['title'] = __('Reservations and revenues by assistants', 'salon-booking-system');
		$ret['subtitle'] = '';

		$ret['labels']['x'] = array(
				array(
						'label'  => sprintf(
                            // translators: %s will be replaced by the currency string
                            __('Earnings (%s)', 'salon-booking-system'), $this->getCurrencyString()),
						'type'   => 'number',
						'format_axis' => array(
								'pattern' => '####.##'.$this->getCurrencySymbol(),
						),
						'format_data' => array(
								'pattern' => '####.##'.$this->getCurrencySymbol(),
						),
				),
				array(
						'label' => __('Bookings', 'salon-booking-system'),
						'type'  => 'number',
				),
		);
		$ret['labels']['y'] = array(
				array(
						'label' => '',
						'type'  => 'string',
				),
		);

		$sRepo =  $this->plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);
		$allAttendants = $sRepo->getAll();
		foreach($allAttendants as $attendant) {
			$ret['data'][$attendant->getId()] = array($attendant->getName(), 0.0, 0);
		}


		foreach($this->bookings as $k => $bookings) {
			/** @var SLN_Wrapper_Booking $booking */
			foreach($bookings as $booking) {
				$attWasAdded = array();
				foreach($booking->getBookingServices()->getItems() as $bookingService) {
					if ($bookingService->getAttendant()) {
                                            $attendants = is_array($bookingService->getAttendant()) ? $bookingService->getAttendant() : array($bookingService->getAttendant());
                                            foreach ($attendants as $attendant) {
                                                    if (!in_array($attendant->getId(), $attWasAdded)) {

                                                            if (isset($ret['data'][$attendant->getId()])) {
                                                                $ret['data'][$attendant->getId()][2] ++;
                                                            }

                                                            $attWasAdded[] = $attendant->getId();
                                                    }
                                                    if (isset($ret['data'][$attendant->getId()])) {
                                                        $ret['data'][$attendant->getId()][1] += $booking->getAmount() > 0 ? $booking->getAmount() : 0;
                                                    }
                                            }
					}
				}
			}
		}

		$this->data = $ret;
	}
}