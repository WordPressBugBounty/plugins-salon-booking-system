<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_Ajax_CheckServices extends SLN_Action_Ajax_Abstract
{
	const STATUS_ERROR = -1;
	const STATUS_UNCHECKED = 0;
	const STATUS_CHECKED = 1;

	/** @var  SLN_Wrapper_Booking_Builder */
	protected $bb;
	/** @var  SLN_Helper_Availability */
	protected $ah;

	protected $date;
	protected $time;
	protected $errors = array();

	public function execute()
	{
		$this->setBookingBuilder($this->plugin->getBookingBuilder());
		$this->setAvailabilityHelper($this->plugin->getAvailabilityHelper());
		$this->bindDate($_POST);

		$ret = array();

		$services = isset($_POST['sln']['services']) ? array_map('intval',$_POST['sln']['services']) : array();
		if(!empty($_POST['all_services'])){
			$services_repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE)->getIds();
			$services = array();
			foreach ($services_repo as $service) {
				$services[intval($service)] = $service;
			}
		}
		if (isset($_POST['part'])) {
			$part = sanitize_text_field($_POST['part']);
			if ($part == 'primaryServices') { // for frontend user
				$ret = $this->initPrimaryServices($services);
			} elseif ($part == 'secondaryServices') { // for frontend user
				$ret = $this->initSecondaryServices($services);
			} elseif ($part == 'allServices' && !empty($_POST['all_services'])) { // for admin
				$bookingData = isset($_POST['_sln_booking']) ? $_POST['_sln_booking'] : null;
				$selectedServicesList = isset($_POST['_sln_booking']['service']) ? array_map('intval',$_POST['_sln_booking']['service']) : array();
				$ret = $this->initAllServicesForAdmin($_POST['post_ID'], $services, $bookingData, $selectedServicesList, true);
			} elseif ($part == 'allServices' && ! empty($_POST['_sln_booking']['service'])) { // for admin
				$services = is_array($_POST['_sln_booking']['service']) ? array_map('intval',$_POST['_sln_booking']['service']) : intval($_POST['_sln_booking']['service']) ;
				$selectedServicesList = $services;
				$ret = $this->initAllServicesForAdmin($_POST['post_ID'], $services, $_POST['_sln_booking'], $selectedServicesList, false);
			}
		}

	$ret = array(
			'success'  => 1,
			'services' => $ret,
		);

		return $ret;
	}

	public function initPrimaryServices($services)
	{
		return $this->innerInitServices($services, $this->bb->getSecondaryServices(), $this->getPrimaryServices());
	}


	public function initSecondaryServices($services)
	{
		return $this->innerInitServices($services, $this->bb->getPrimaryServices(), $this->getSecondaryServices());
	}


	private function bindDate($data)
	{
		if ( ! isset($this->date)) {
			if (isset($data['sln'])) {
				$date = isset($data['sln']['date']) ? sanitize_text_field($data['sln']['date']) : '';
				$time = isset($data['sln']['time']) ? sanitize_text_field($data['sln']['time']) : '';
				
				// Only set date/time if they're not empty to prevent errors
				if (!empty($date)) {
					$this->date = $date;
				}
				if (!empty($time)) {
					$this->time = $time;
				}
			}
			if (isset($data['_sln_booking_date'])) {
				$date = sanitize_text_field($data['_sln_booking_date']);
				$time = isset($data['_sln_booking_time']) ? sanitize_text_field($data['_sln_booking_time']) : '';
				
				// Only set date/time if they're not empty to prevent errors
				if (!empty($date)) {
					$this->date = $date;
				}
				if (!empty($time)) {
					$this->time = $time;
				}
			}
		}
	}

	protected function innerInitServices($services, $merge, $newServices)
	{

		$ret      = array();
		$mergeIds = array();
		foreach($merge as $s){
			$mergeIds[] = $s->getId();
		}

		$services = array_merge(
			array_keys($services),
			$mergeIds
		); // merge primary services from form & secondary services from booking builder
		$this->ah->setDate($this->bb->getDateTime());
		$validated = $this->ah->returnValidatedServices($services);
		$validatedPrimary = array_intersect($this->getPrimaryServicesIds(), $validated);

		$this->bb->removeServices();

		if ( ! empty($validatedPrimary)) { // if order primary services count > 0  --->  set validated services
			foreach ($validated as $sId) {
				$this->bb->addService($this->plugin->createService($sId));
				$ret[$sId] = array('status' => self::STATUS_CHECKED, 'error' => '');
			}
		} else {
			$validated = array();
		}
		$this->bb->save();

		$servicesErrors = $this->ah->checkEachOfNewServicesForExistOrder($validated, $newServices);
		foreach ($servicesErrors as $sId => $error) {
			if (empty($error)) {
				$ret[$sId] = array('status' => self::STATUS_UNCHECKED, 'error' => '');
			} else {
				$ret[$sId] = array('status' => self::STATUS_ERROR, 'error' => $error[0]);
			}
		}

		$servicesExclusiveErrors = $this->ah->checkExclusiveServices( $validated, array_merge( $merge, $newServices ) );
		foreach ($servicesExclusiveErrors as $sId => $error) {
			if (empty($error)) {
				$ret[$sId] = array('status' => self::STATUS_UNCHECKED, 'error' => '');
			} else {
				$ret[$sId] = array('status' => self::STATUS_ERROR, 'error' => $error[0]);
			}
		}
		return $ret;
	}

	public function initAllServicesForAdmin($bookingID, $checkServicesList, $bookingData, $selectedServicesList = array(), $checkAddService = true)
	{
		$date = $this->getDateTime();
		$this->ah->setDate($date, $this->plugin->createBooking(intval($bookingID)));

		$data = array();
		foreach ($checkServicesList as $key => $sId) {

			if(0 == $sId){
				continue;
			}

			$attendant = isset($bookingData['attendants'][$sId]) ? $bookingData['attendants'][$sId] : null;

			$data[$sId] = array(
				'service'        => $sId,
				'attendant'      => sanitize_text_field(wp_unslash($attendant)),
				'price'          => isset($bookingData['price'][$sId]) ? sanitize_text_field(wp_unslash($bookingData['price'][$sId])) : 0,
				'duration'       => isset($bookingData['duration'][$sId]) ? SLN_Func::convertToHoursMins(sanitize_text_field(wp_unslash($bookingData['duration'][$sId]))) : 0,
				'break_duration' => isset($bookingData['break_duration'][$sId]) ? SLN_Func::convertToHoursMins(sanitize_text_field(wp_unslash($bookingData['break_duration'][$sId]))) : 0,
				'selected' => in_array($sId, $bookingData['service']),
			);
		}
		$ret             = array();
		$bb = $this->plugin->createBooking(intval($bookingID));
		$bookingServices = SLN_Wrapper_Booking_Services::build($data, $date, 0, $bb->getCountServices());
		$settings = $this->plugin->getSettings();
		$primaryServicesCount   = $settings->get('primary_services_count');
		$secondaryServiceCount  = $settings->get( 'secondary_services_count' );
		$bookingOffsetEnabled   = $settings->get('reservation_interval_enabled');
		$bookingOffset          = $settings->get('minutes_between_reservation');
		$isMultipleAttSelection = $settings->get('m_attendant_enabled');

		// $isServicesCountPrimaryServices = $settings->get('is_services_count_primary_services');

		$firstSelectedAttendant = null;
		foreach ($bookingServices->getItems() as $key => $bookingService) {
			$serviceErrors   = array();
			$attendantErrors = array();
			$sId = $bookingService->getService()->getId();

			if ($primaryServicesCount) {

				$_services = $selectedServicesList;

				// if ($isServicesCountPrimaryServices) {

				if (!$bookingService->getService()->isSecondary()) {

					$_services = array_filter($_services, function ($serviceID) {
						return !$this->plugin->createService($serviceID)->isSecondary();
					});

					if (count($_services) >= $primaryServicesCount) {
						$serviceErrors[] = sprintf(
							// translators: %s will be replaced by the count primary services
							__('You can select up to %d items', 'salon-booking-system'), $primaryServicesCount);
					}
				}
				// } else {
				//     if (count($_services) >= $servicesCount) {
				// 	$serviceErrors[] = sprintf(__('You can select up to %d items', 'salon-booking-system'), $servicesCount);
				//     }
				// }
			}
			if( $secondaryServiceCount ){
				if ( $bookingService->getService()->isSecondary() ) {

					$_services = array_filter( $_services, function ( $serviceID ) {
						return $this->plugin->createService( $serviceID )->isSecondary();
					});

					if (count($_services) >= $secondaryServicesCount) {
						$serviceErrors[] = sprintf(
							// translators: %s will be replaced by the count secondary services
							__('You can select up to %d items', 'salon-booking-system'), $secondaryServicesCount);
					}
				}
			}

			if ( empty($serviceErrors) ) {

				$serviceErrors = $this->ah->validateServiceFromOrder($bookingService->getService(), $bookingServices);

				if (empty($serviceErrors) && $bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
					$offsetStart   = $bookingService->getEndsAt();
					$offsetEnd     = $bookingService->getEndsAt()->modify('+'.$bookingOffset.' minutes');
					$serviceErrors = $this->ah->validateTimePeriod($offsetStart, $offsetEnd);
				}

				if (empty($serviceErrors)) {
					$serviceErrors = $this->ah->validateBookingService($bookingService, $bookingServices->isLast($bookingService));
				}

				if ( ! $isMultipleAttSelection) {
					if ( ! $firstSelectedAttendant) {
						$firstSelectedAttendant = ($bookingService->getAttendant() ? $bookingService->getAttendant()->getId() : false);
					}
					if ($bookingService->getAttendant() && $bookingService->getAttendant()->getId() != $firstSelectedAttendant) {
						$attendantErrors = array(
							__(
							'Multiple attendants selection is disabled. You must select one attendant for all services.',
							'salon-booking-system'
							),
						);
					}
				}
				if (empty($attendantErrors) && $bookingService->getAttendant()) {
					$attendantErrors = $this->ah->validateAttendantService(
						$bookingService->getAttendant(),
						$bookingService->getService()
					);
					if (empty($attendantErrors)) {
						$attendantErrors = $this->ah->validateBookingAttendant($bookingService, $bookingServices->isLast($bookingService));
					}
				}
			}

			if($bookingService->getService()->isExclusive() && ($checkAddService ? count($selectedServicesList) > 0 : count($selectedServicesList) > 1)) {
				$serviceErrors[] = __('This service is exclusive. Please remove other services.', 'salon-booking-system');
			}

			$filteredSelectedServiceList = array_filter($selectedServicesList, function($sId) use ($checkAddService, $bookingService){
				$service = $this->plugin->createService($sId);
				return (!$service->isEmpty() && $service->isExclusive()) && ($checkAddService || $bookingService->getService()->getId() != $sId);
			});

			if( ! empty($filteredSelectedServiceList) ) {

				$serviceErrors[] = __('These selected services have exclusive service. Please remove it before add.', 'salon-booking-system');
			}

			$errors = array();
			if ( ! empty($attendantErrors)) {
				$errors[] = $attendantErrors[0];
			}
			if ( ! empty($serviceErrors)) {
				$errors[] = $serviceErrors[0];
			}

			$ret[$sId] = array(
				'status'               => empty($errors) ? self::STATUS_CHECKED : self::STATUS_ERROR,
				'errors'               => $errors,
				'serviceErrorCount'    => is_array($serviceErrors) ? count($serviceErrors) : count(array_filter(array($serviceErrors))),
				'attendantErrorsCount' => is_array($attendantErrors) ? count($attendantErrors) : count(array_filter(array($attendantErrors))),
				'startsAt'             => $this->plugin->format()->time($bookingService->getStartsAt()),
				'endsAt'               => $this->plugin->format()->time($bookingService->getEndsAt()),
			);
		}
		return $ret;
	}

	/**
	 * @param bool $primary
	 * @param bool $secondary
	 *
	 * @return SLN_Wrapper_Service[]
	 */
	protected function getServices($primary = true, $secondary = false)
	{
		$services = array();
		/** @var SLN_Repository_ServiceRepository $repo */
		$repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);

		foreach ($repo->sortByExec($repo->getAll()) as $service) {
			if ($secondary && $service->isSecondary()) {
				$services[] = $service;
			} elseif ($primary && ! $service->isSecondary()) {
				$services[] = $service;
			}
		}

		return $services;
	}

	protected function getPrimaryServicesIds()
	{
		$ret = array();
		foreach ($this->getServices(true, false) as $service) {
			if ( ! $service->isSecondary()) {
				$ret[] = $service->getId();
			}
		}

		return $ret;
	}

	protected function getPrimaryServices()
	{
		return $this->getServices(true, false);
	}

	protected function getSecondaryServices()
	{
		return $this->getServices(false, true);
	}

	protected function getSecondaryServicesIds(){
		$ret = array();
		foreach( $this->getServices( true, false ) as $service ){
			if( !$service->isSecondary() ){
				$ret[] = $service->getId();
			}
		}
		return $ret;
	}

	protected function getDateTime()
	{
		$date = isset($this->date) ? $this->date : null;
		$time = isset($this->time) ? $this->time : null;
		
		// Validate date is not empty
		if (empty($date)) {
			throw new Exception(
				'Missing date in request. Date: "' . ($date ?? 'null') . '". Please select a date before checking services.'
			);
		}
		
		// If time is empty, use a default placeholder time
		// This allows checking date availability without requiring a specific time
		if (empty($time)) {
			$time = '00:00';
		}
		
		$ret  = new SLN_DateTime(
			SLN_Func::filter($date, 'date').' '.SLN_Func::filter($time, 'time') . ':00'
		);

		return $ret;
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

	public function setBookingBuilder($bb)
	{
		$this->bb = $bb;

		return $this;
	}

	public function setAvailabilityHelper($ah)
	{
		$this->ah = $ah;

		return $this;
	}

}
