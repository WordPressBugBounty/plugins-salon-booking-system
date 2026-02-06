<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Wrapper_Booking extends SLN_Wrapper_Abstract
{
    private $bookingServices;
    private $attendants;

    const _CLASS = 'SLN_Wrapper_Booking';

    public function getPostType()
    {
        return SLN_Plugin::POST_TYPE_BOOKING;
    }

    function getAmount($include_fee=false)
    {
        $ret = $this->getMeta('amount');
        $ret = empty($ret) ? 0 : floatval($ret);
        if($include_fee){
            $ret += SLN_Helper_TransactionFee::getFee($ret);
        }

        return $ret;
    }

    function getDeposit($include_fee=false)
    {
        $ret = $this->getMeta('deposit');
        $ret = empty($ret) ? 0 : floatval($ret);
        if($include_fee){
            $ret += SLN_Helper_TransactionFee::getFee($ret);
        }

        return $ret;
    }

    function getToPayAmount($format = true, $include_fee = true)
    {
        $ret = $this->getAmount();

	    if ( $include_fee ) {
	        $ret += SLN_Helper_TransactionFee::getFee($ret);
	    }

        return $format ? number_format($ret, 2) : $ret;
    }

    function getRemaingAmountAfterPay($format = true)
    {
        $ret = $this->getDeposit() > 0 ? ( $this->getAmount() - $this->getDeposit() ) : 0;

        return $format ? number_format($ret, 2) : $ret;
    }

    function getFirstname()
    {
        return $this->getMeta('firstname');
    }

    function getLastname()
    {
        return $this->getMeta('lastname');
    }

    function getDisplayName()
    {
        return $this->getFirstname().' '.$this->getLastname();
    }

    function getEmail()
    {
        return $this->getMeta('email');
    }

	function getOrigin(){
		return $this->getMeta('origin_source');
	}

	function setOrigin($origin){
		$this->setMeta('origin_source', $origin);
	}

	function getCreatedByUserId(){
		return $this->getMeta('created_by_user_id');
	}

	function getLastEditedByUserId(){
		return $this->getMeta('last_edited_by_user_id');
	}

	function getLastEditedAt(){
		return $this->getMeta('last_edited_at');
	}

    function getPhone()
    {
        return $this->getMeta('phone');
    }

    function getAddress()
    {
        return $this->getMeta('address');
    }


    function getTime($timezone = null)
    {
        return new SLN_DateTime($this->getMeta('time'),$timezone);
    }

    function getDate($timezone = null)
    {
        return new SLN_DateTime($this->getMeta('date'),$timezone);
    }

    function getServicesMeta()
    {
        $data = $this->getMeta('services');
        $data = empty($data) ? array() : $data;

        return $data;
    }

    function getBookingServices()
    {
        if (!$this->bookingServices) {
            $this->maybeProcessBookingServices();
            $this->bookingServices = SLN_Wrapper_Booking_Services::build($this->getServicesMeta(), $this->getStartsAt(), 0, $this->getCountServices(), $this->getResources());
        }

        return $this->bookingServices;
    }

    function getOnProcess() {
        $ret = $this->getMeta('on_process');
        $ret = empty($ret) ? false : boolval($ret);

        return $ret;
    }

    function setOnProcess($on_process) {
        $this->setMeta('on_process', $on_process);
    }

    function maybeProcessBookingServices()
    {

        $servicesProcessed = $this->getMeta('services_processed');

        if (empty($servicesProcessed)) {
            $this->evalBookingServices();
        }
    }

    function evalBookingServices()
    {
        $data = $this->getServicesMeta();
        $this->bookingServices = SLN_Wrapper_Booking_Services::build($data, $this->getStartsAt(), 0, $this->getCountServices(), $this->getResources());
        $ret = $this->bookingServices->toArrayRecursive();
        $this->setMeta('services', $ret);
        $this->setMeta('services_processed', 1);
    }

    function getDuration()
    {
        $ret = $this->getMeta('duration');

        $ret = $this->clampDuration($ret);

        if (empty($ret)) {
            $ret = '00:00';
        }
        $ret = SLN_Func::filter($ret, 'time');
        if ($ret == '00:00') {
            $ret = $this->evalDuration();
        }

        $ret = SLN_Func::getMinutesFromDuration($ret)*60;
        $date = new SLN_DateTime('@'.$ret);

        return  $date;
    }

    function clampDuration($duration = '00:00'): string
    {   if(empty($duration)){
            return '00:00';
        }
        $time = explode(':', $duration);
        if($time[0] > 23) $time[0] = 23;
        if($time[0] < 0) $time[0] = 0;
        if($time[1] > 59) $time[1] = 59;
        if($time[1] < 0) $time[1] = 0;
        $duration_clamped = implode(':', $time);
        $this->setMeta('duration', $duration_clamped);
        return $duration_clamped;
    }

    function evalDuration()
    {
        $settings = SLN_Plugin::getInstance()->getSettings();
        if('basic' !== $settings->getAvailabilityMode()){
            $h = 0;
            $i = 0;
			$max = 0;
            SLN_Plugin::addLog(__CLASS__.' eval duration of'.$this->getId());
            foreach ($this->getBookingServices()->getItems() as $bookingService) {
                $d = $bookingService->getTotalDuration();
	            $dInMinutes = (intval($d->format('H')) * 60 + intval($d->format('i'))) * $bookingService->getCountServices();
	            if ($bookingService->getParallelExec()) {
		            if ($dInMinutes > $max) {
			            $max = $dInMinutes;
		            }
	            } else {
		            $i += $dInMinutes;
	            }
                SLN_Plugin::addLog(' - service '.$bookingService.' +'.$d->format('H:i'));
            }
	        $i += $max;
            if ($i == 0) {
                $i = $settings->getInterval();
            }
        }else{
            $i = $settings->getInterval();
        }
        $str = SLN_Func::convertToHoursMins($i);
        $this->setMeta('duration', $str);

        return $str;
    }

    function evalTotal()
    {
        $settings = SLN_Plugin::getInstance()->getSettings();

        $amount = 0;

        SLN_Plugin::addLog(__CLASS__ . ' eval total of' . $this->getId());
        
        // Check for prepaid services (package credits)
        $user_id = $this->getUserId();
        if ($user_id) {
            $prepaid_services = get_user_meta($user_id, '_sln_prepaid_services', true);
            
            SLN_Plugin::addLog('Checking prepaid services for user ' . $user_id);
            SLN_Plugin::addLog('Prepaid services data: ' . print_r($prepaid_services, true));
            
            if (!empty($prepaid_services) && is_array($prepaid_services)) {
                // Apply prepaid services to booking services
                foreach ($this->getBookingServices()->getItems() as $bookingService) {
                    $service_id = $bookingService->getService()->getId();
                    $original_price = $bookingService->getPrice();
                    
                    SLN_Plugin::addLog('Checking service #' . $service_id . ' (current price: ' . $original_price . ')');
                    
                    // Check data structure - handle both old flat structure and new nested structure
                    $hasCredits = false;
                    $packageOrderId = null;
                    $creditsAvailable = 0;
                    
                    // Check if it's the new nested structure [package_order_id][service_id] => credits
                    $firstKey = key($prepaid_services);
                    if (is_array($prepaid_services[$firstKey])) {
                        // New nested structure
                        foreach ($prepaid_services as $package_order_id => $services) {
                            if (isset($services[$service_id]) && $services[$service_id] > 0) {
                                $hasCredits = true;
                                $packageOrderId = $package_order_id;
                                $creditsAvailable = $services[$service_id];
                                break;
                            }
                        }
                    } else {
                        // Old flat structure [service_id] => credits
                        if (isset($prepaid_services[$service_id]) && $prepaid_services[$service_id] > 0) {
                            $hasCredits = true;
                            $packageOrderId = 'unknown';
                            $creditsAvailable = $prepaid_services[$service_id];
                        }
                    }
                    
                    if ($hasCredits) {
                        // User has credits for this service - set price to 0
                        $bookingService->setPrice(0);
                        
                        SLN_Plugin::addLog('Prepaid service applied - Service #' . $service_id . ' price set from ' . $original_price . ' to 0 (Package Order #' . $packageOrderId . ' has ' . $creditsAvailable . ' credits)');
                        
                        // Mark this service as using a credit
                        $this->addMeta('service_credit', $service_id);
                    }
                }
            }
        }
        
        // Apply prepaid services filter (for add-ons)
        $bookingServices = apply_filters('sln.calc_booking_total.apply_prepaid_services', $this->getBookingServices(), $this);

        foreach ($bookingServices->getItems() as $bookingService) {
            if(isset($bookingService->toArray()['service'])){
                $variable = get_post_meta($bookingService->toArray()['service'],'_sln_service_variable_duration', true);
            }
            if($variable){
                $price = $bookingService->getPrice() * $bookingService->getCountServices();
            } else {
                $price = $bookingService->getPrice();
            }
            $amount += $price;
            SLN_Plugin::addLog(' - service ' . $bookingService->getService() . ' +' . $price);
        }

        if ($settings->get('enable_booking_tax_calculation') && 'inclusive' !== $settings->get('enter_tax_price')) {
            $amount = $amount * (1 + floatval($settings->get('tax_value')) / 100);
        }

        $amount += $this->getTips();

        SLN_Plugin::addLog(' - tips +' . $this->getTips());

        $amount = apply_filters('sln.booking.getTotal', $amount, $this);

        $this->setMeta('amount', $amount);

        $deposit = SLN_Helper_PayDepositAdvancedRules::getDeposit($amount, $settings);

        $this->setMeta('deposit', $deposit);

        return $amount;
    }

    public function getTaxFromTotal(){
        $settings = SLN_Plugin::getInstance()->getSettings();
        if(!$settings->get('enable_booking_tax_calculation')){
            return 0;
        }
        $priceExcludeTax = $this->getAmount() / (1 + floatval($settings->get('tax_value')) / 100);
        return $priceExcludeTax * (floatval($settings->get('tax_value')) / 100);
    }


    function hasAttendant(SLN_Wrapper_Attendant $attendant)
    {
        return in_array($attendant->getId(), $this->getAttendantsIds());
    }

    function hasService(SLN_Wrapper_Service $service)
    {
        return in_array($service->getId(), $this->getServicesIds());
    }

    /**
     * @param bool|false $unique
     *
     * @return array
     */
    function getAttendantsIds($unique = false)
    {
        $post_id = $this->getId();
        $data = apply_filters('sln_booking_attendants', get_post_meta($post_id, '_sln_booking_services', true));
        $ret = array();
        if (is_array($data)) {
            foreach ($data as $item) {
                if($item['attendant'])
                    $ret[$item['service']] = $item['attendant'];
            }
        }

        return $unique ? array_unique($ret, SORT_NUMERIC) : $ret;
    }

    function getShopsIds($unique = false)
    {
        $data = $this->getMeta('shops');
        $ret = array();
        if (is_array($data)) {
            foreach ($data as $item) {
                $ret[] = $item['shops'];
            }
        }

        return $ret;
    }

    /**
     * @return SLN_Wrapper_Attendant|false
     */
    public function getAttendant()
    {
        $ret = $this->getAttendants();
        return empty($ret) ? false : array_pop($ret);
    }

    /**
     * @param bool $unique
     *
     * @return SLN_Wrapper_Attendant[]
     */
    public function getAttendants($unique = false)
    {
        if (!$this->attendants) {
            $plugin = SLN_Plugin::getInstance();
            $this->attendants = array();
            $attIds = $this->getAttendantsIds($unique);
            foreach ($attIds as $service_id => $id) {
                if (!$id) {
                    continue;
                }
                /** @var SLN_Wrapper_Attendant $tmp */
                $tmp = $plugin->createAttendant($id);
                if(!is_array($id)){
                    if (!$tmp->isEmpty()) {
                        $this->attendants[$service_id] = $tmp;
                    }
                }else{
                    if(!in_array(true, SLN_Wrapper_Attendant::getArrayAttendantsValue('isEmpty', $tmp))){
                        $this->attendants[$service_id] = $tmp;
                    }
                }
            }
        }

        return $this->attendants;
    }

    function getAttendantsString()
    {
        $attendants = $this->getAttendants(true);
        if (empty($attendants)) {
            return '';
        } else {
            $ret = array();
            foreach ($attendants as $attendant) {
                if(!is_array($attendant)){
                    $ret[] = $attendant->getName();
                }else{
                    $ret = array_merge($ret, SLN_Wrapper_Attendant::getArrayAttendantsValue('getName', $attendant));
                }
            }

            return implode(', ', $ret);
        }
    }

    function getServicesIds()
    {
        $data = $this->getMeta('services');
        $ret = array();
        if (is_array($data)) {
            foreach ($data as $item) {
                $ret[] = $item['service'];
            }
        }

        return $ret;
    }

    /**
     * @return SLN_Wrapper_Service[]
     */
    function getServices()
    {
        $ret = array();
        foreach ($this->getServicesIds() as $id) {
            $tmp = new SLN_Wrapper_Service($id);
            if (!$tmp->isEmpty()) {
                $ret[] = $tmp;
            }
        }

        return $ret;
    }

    function getNote()
    {
        return $this->getMeta('note');
    }

    function getAdminNote()
    {
        return $this->getMeta('admin_note');
    }


    function getTransactionId()
    {
        $transactions = $this->getMeta('transaction_id');
        return is_array($transactions) ? $transactions : ($transactions ? array($transactions) : array());
    }

    function getStartsAt( $timezone='' )
    {
		if($timezone)
			return new SLN_DateTime($this->getDate()->format('Y-m-d').' '.$this->getTime()->format('H:i'), SLN_Func::createDateTimeZone($timezone) );
		else
			return new SLN_DateTime($this->getDate()->format('Y-m-d').' '.$this->getTime()->format('H:i'));
    }

    function getEndsAt( $timezone='' )
    {
        $start = $this->getStartsAt( $timezone );
        $minutes = SLN_Func::getMinutesFromDuration($this->getDuration());
        if ($minutes == 0) {
            $minutes = 60;
        }
        $start->modify('+'.$minutes.' minutes');

        return $start;
    }

    public function getUserId()
    {
        return $this->object->post_author;
    }

	/**
     * @return null|SLN_Wrapper_Customer
     */
    public function getCustomer()
    {
        $customer = new SLN_Wrapper_Customer($this->getUserId());

        return $customer;
    }

    function isNew()
    {
        return strpos($this->object->post_status, 'sln-b-') !== 0;
    }

    public function setPrepaidServices(): void
    {
        $user_id = $this->getUserId();
        $services_credits = $this->getMeta('service_credit', false, false);
        $prepaid_services = get_user_meta($user_id, '_sln_prepaid_services', true);

        if (empty($services_credits)) {
            return;
        }

        foreach ($services_credits as $service_credit) {
            foreach ($prepaid_services as $package_order=>$ps){
                if($prepaid_services[$package_order][$service_credit] > 0){
                    $prepaid_services[$package_order][$service_credit] -= 1;
                    $this->setMeta('service_credit_package',$package_order);
                    break;
                }
            }
        }
        $this->setStatus(SLN_Enum_BookingStatus::PAID);
        update_user_meta($user_id, '_sln_prepaid_services', $prepaid_services);
    }

    public function markPaid($transactionId, $remainedAmount = 0)
    {
        // Debug logging (only when debug mode enabled)
        SLN_Plugin::addLog(sprintf(
            'markPaid() called for Booking #%d: current_status=%s, transaction_id=%s',
            $this->getId(),
            $this->getStatus(),
            $transactionId
        ));
        
        $transactions = $this->getTransactionId();
        
        // CRITICAL FIX: Check if transaction ID already exists before adding
        if (!in_array($transactionId, $transactions)) {
            $this->setMeta('transaction_id', array_merge($transactions, array($transactionId)));
            
            SLN_Plugin::addLog(sprintf(
                'markPaid: Added transaction ID "%s" to booking #%d (total transactions: %d)',
                $transactionId,
                $this->getId(),
                count($transactions) + 1
            ));
        } else {
            SLN_Plugin::addLog(sprintf(
                'markPaid: Transaction ID "%s" already exists for booking #%d (existing: %s), skipping duplicate',
                $transactionId,
                $this->getId(),
                implode(', ', $transactions)
            ));
            // Continue processing to ensure status is updated if needed
        }
        
        if ($remainedAmount) {
            $this->setMeta('deposit', $this->getDeposit() + $remainedAmount);
            $this->setMeta('paid_remained_amount', $remainedAmount);
            $this->setMeta('remainedAmount', 0);
        }
        if (class_exists('SalonPackages\Addon') && slnpackages_is_pro_version_salon() && slnpackages_is_license_active()) {
            $this->setPrepaidServices();
        }
        
        // Only change status if not already paid/confirmed
        if (!in_array($this->getStatus(), [SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED])) {
            $this->setStatus(SLN_Enum_BookingStatus::PAID);
            
            SLN_Plugin::addLog(sprintf(
                'markPaid: Changed booking #%d status to PAID',
                $this->getId()
            ));
        } else {
            SLN_Plugin::addLog(sprintf(
                'markPaid: Booking #%d already has paid status (%s), skipping status change',
                $this->getId(),
                $this->getStatus()
            ));
        }

	do_action('sln_booking_mark_paid_after', $this);
    }

    public function getPayUrl($isPayRemainingAmount = false)
    {
        $payUrl = add_query_arg(
            array(
                'sln_step_page' => 'summary',
                'submit_summary' => 'next',
                'sln_booking_id' => $this->getUniqueId(),
                'pay_remaining_amount' => $isPayRemainingAmount ? 1 : 0,
            ),
            get_permalink(SLN_Plugin::getInstance()->getSettings()->getPayPageId() )
        );

	return apply_filters('sln.booking.get-pay-url', $payUrl, $this);
    }

    public function getRescheduleUrl()
    {
        return add_query_arg(
            array(
                'booking_id'		 => $this->getId(),
                'sln_reschedule_booking' => 1,
            ),
            get_permalink(SLN_Plugin::getInstance()->getSettings()->getPayPageId() )
        );
    }

    public function getCancelUrl()
    {
	return add_query_arg(
            array('booking_id' => $this->getUniqueId()),
            SLN_Action_CancelBookingLink::getUrl()
        );
    }

    public function getTimeStringToChangeStatusFromPending() {
        $plugin = SLN_Plugin::getInstance();
        $left   = '';

        if (in_array($this->getStatus(), array(SLN_Enum_BookingStatus::PENDING, SLN_Enum_BookingStatus::PENDING_PAYMENT, SLN_Enum_BookingStatus::DRAFT)) && $plugin->getSettings()->get('pay_offset_enabled')) {
            $payOffset      = $plugin->getSettings()->get('pay_offset');
            $checkTimestamp = SLN_TimeFunc::getPostTimestamp($this->object);
            $leftSeconds    = $checkTimestamp + $payOffset*MINUTE_IN_SECONDS - time();
            $leftMinutes    = $leftSeconds > 0 ? $leftSeconds/60 : 0;

            $left = sprintf(
                // translators: %1$d will be replaced by the left hours, %2$d will be replaced by the left minutes
                __("%1\$d hours and %2\$d minutes", 'salon-booking-system'), (int) $leftMinutes/60, (int) $leftMinutes%60);
        }

        return $left;
    }

    public function getUniqueId()
    {
        $id = $this->getMeta('uniqid');
        if (!$id) {
            $id = md5(uniqid().$this->getId());
            $this->setMeta('uniqid', $id);
        }

        return $this->getId().'-'.$id;
    }

    public function getRating()
    {
        return $this->getMeta('rating');
    }

    public function setRating($rating)
    {
        $this->setMeta('rating', $rating);
    }

    /**
     * @param $status
     * @return $this
     */
    public function setStatus($status)
    {
        $oldStatus = $this->getStatus();
        $status = SLN_Enum_BookingStatus::getForceStatus($status);
        $ret       = parent::setStatus($status);
        do_action('sln.booking.setStatus', $this, $oldStatus, $this->getStatus());

        $this->evalCustomerDetails();
        return $ret;
    }

    public function evalCustomerDetails()
    {
        if (SLN_Wrapper_Customer::isCustomer($this->getUserId())) {
            $customer = new SLN_Wrapper_Customer($this->getUserId());

            $thisBookingTime = $this->getStartsAt()->getTimestamp();
            $lastBookingTime = $customer->getLastBookingTime();

            $allStatuses          = array_keys(SLN_Enum_BookingStatus::toArray());
            $completedStatuses    = array(SLN_Enum_BookingStatus::PAY_LATER, SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED);
            $nonCompletedStatuses = array_diff($allStatuses, $completedStatuses);


            if (in_array($this->getStatus(), $nonCompletedStatuses) && $thisBookingTime === $lastBookingTime) {
                $customer->setLastBookingTime($customer->calcLastBookingTime());
            }
            elseif (in_array($this->getStatus(), $completedStatuses) && ($thisBookingTime > $lastBookingTime || !$lastBookingTime)) {
                $customer->setLastBookingTime($thisBookingTime);
            }

            $customer->setNextBookingTime($customer->calcNextBookingTime());
        }
    }

    public function getEmailCancellationDetails(&$cancellationText, &$bookingMyAccountUrl)
    {
		$cancellationText = $bookingMyAccountUrl = '';
		$plugin = SLN_Plugin::getInstance();

		$cancellationEnabled = $plugin->getSettings()->get('cancellation_enabled');
		if( !$cancellationEnabled )
			return false;

		$cancellationHours = $plugin->getSettings()->get('hours_before_cancellation');
		$outOfTime = ($this->getStartsAt()->getTimeStamp() - time()) < $cancellationHours * 3600;
		if( $outOfTime )
			return false;

		$bookingMyAccountPageId = $plugin->getSettings()->getBookingmyaccountPageId();
		if( !$bookingMyAccountPageId )
			return false;

		// have time and know page ?
		$cancellationText = $cancellationHours<24 ? $cancellationHours . __(" hours", 'salon-booking-system') :
							($cancellationHours==24? __("1 day", 'salon-booking-system') : round($cancellationHours/24) . __("days", 'salon-booking-system'));
		$bookingMyAccountUrl = get_permalink($bookingMyAccountPageId);
		return true;
	}

    function getTips()
    {
        $data = $this->getMeta('tips');
        $data = empty($data) ? 0 : (float)$data;

        return $data;
    }

    function getNotifyCustomer() {
        return !$this->getMeta('dont_notify_customer');
    }

    function getSmsPrefix() {
        return $this->getMeta('sms_prefix');
    }

    function getCustomerTimezone() {
        return $this->getMeta('customer_timezone');
    }

    function getCountServices() {
        return $this->getMeta('service_count') ? $this->getMeta('service_count') : array();
    }

    public function getCountService($service_id){
        $services = $this->getMeta('service_count');
        return (!empty($services) && isset($services[$service_id])) ? $services[$service_id] : 1;
    }

    function getPaidRemainedAmount() {
        return $this->getMeta('paid_remained_amount') ? $this->getMeta('paid_remained_amount') : 0;
    }

    function getResources() {
        return $this->getMeta('services_resources') ? $this->getMeta('services_resources') : array();
    }

    public function getSOAPNotes()
    {
        return $this->getMeta('soap_notes') ?: [];
    }

    public function getCreateStatus()
    {
        $settings = SLN_Plugin::getInstance()->getSettings();
        $is_api_request = defined('REST_REQUEST') && REST_REQUEST;
        if($settings->isPayEnabled() && !$settings->get('confirmation') /*&& $settings->get('create_booking_after_pay')*/ && !$is_api_request){
            return SLN_Enum_BookingStatus::DRAFT;
        }

        $status = $settings->get('confirmation') ?
            SLN_Enum_BookingStatus::PENDING
            : ($settings->isPayEnabled() && $this->getAmount() > 0 ?
                SLN_Enum_BookingStatus::PENDING_PAYMENT
                : SLN_Enum_BookingStatus::CONFIRMED);

        return apply_filters('sln.booking_builder.getCreateStatus', $status);
    }


    public function getData(){
        return array(
            'date' => $this->getDate(),
            'time' => $this->getTime(),
            'services' => $this->getAttendantsIds(),
            'customer_timezone' => $this->getCustomerTimezone(),
            'firstname' => $this->getFirstname(),
            'lastname' => $this->getLastname(),
            'email' => $this->getEmail(),
        );
    }
}
