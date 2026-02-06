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
						'label' => __('Services', 'salon-booking-system'),
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
			$attCountedInBooking = array();
			foreach($booking->getBookingServices()->getItems() as $bookingService) {
				if ($bookingService->getAttendant()) {
                                        $attendants = is_array($bookingService->getAttendant()) ? $bookingService->getAttendant() : array($bookingService->getAttendant());
                                        
                                        // Count total assistants for this service to split revenue/counts fairly
                                        $attendantCount = count($attendants);
                                        
                                        // ✅ FIX: Get service price with fallback for missing prices
                                        $service_price = $bookingService->getPrice();
                                        
                                        // ✅ FALLBACK: If price is 0 or missing, calculate from service base price
                                        if (empty($service_price) || $service_price == 0) {
                                            $service = $bookingService->getService();
                                            
                                            // Get variable price by attendant if enabled
                                            $attendant_id = is_array($bookingService->getAttendant()) ? 
                                                (isset($attendants[0]) ? $attendants[0]->getId() : null) : 
                                                $bookingService->getAttendant()->getId();
                                            
                                            if ($service->getVariablePriceEnabled() && $attendant_id && $service->getVariablePrice($attendant_id) !== '') {
                                                $service_price = floatval($service->getVariablePrice($attendant_id));
                                            } else {
                                                $service_price = floatval($service->getPrice());
                                            }
                                            
                                            // Apply quantity multiplier for variable duration services
                                            $variable_duration = get_post_meta($service->getId(), '_sln_service_variable_duration', true);
                                            if ($variable_duration) {
                                                $service_price = $service_price * $bookingService->getCountServices();
                                            }
                                        }
                                        
                                        // ✅ FIX: Handle negative prices from excessive discounts (clamp to 0)
                                        $service_price = max(0, $service_price);
                                        
                                        foreach ($attendants as $attendant) {
                                                if (isset($ret['data'][$attendant->getId()])) {
                                                    // ✅ FIX: Split revenue equally among all assistants for this service
                                                    // This prevents over-counting when multiple assistants work on one service
                                                    $revenueShare = $attendantCount > 0 ? $service_price / $attendantCount : 0;
                                                    $ret['data'][$attendant->getId()][1] += $revenueShare;
                                                    
                                                    // Count services performed, avoiding double-counting
                                                    $countKey = $attendant->getId() . '_' . $bookingService->getService()->getId();
                                                    if (!in_array($countKey, $attCountedInBooking)) {
                                                        // ✅ FIX: Also split service count for multiple assistants
                                                        // Each assistant gets proportional credit for the service
                                                        $serviceCountShare = $attendantCount > 0 ? $bookingService->getCountServices() / $attendantCount : 0;
                                                        $ret['data'][$attendant->getId()][2] += $serviceCountShare;
                                                        $attCountedInBooking[] = $countKey;
                                                    }
                                                }
                                        }
				}
			}
		}
	}

		$this->data = $ret;
	}
}