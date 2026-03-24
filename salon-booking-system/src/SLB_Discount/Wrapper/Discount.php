<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

class SLB_Discount_Wrapper_Discount extends SLN_Wrapper_Abstract
{
    const _CLASS = 'SLB_Discount_Wrapper_Discount';

    public function getPostType()
    {
        return SLB_Discount_Plugin::POST_TYPE_DISCOUNT;
    }

    function getAmount()
    {
        $ret = $this->getMeta('amount');
        $ret = empty($ret) ? 0 : floatval($ret);

        return $ret;
    }

    function getAmountType()
    {
        $ret = $this->getMeta('amount_type');
        $ret = empty($ret) ? 'fixed' : $ret;

        return $ret;
    }

    /**
     * @return string
     */
    public function getAmountString($isLeftCurrencySymbol = null)
    {
        $amount     = $this->getAmount();
        $amountType = $this->getAmountType();
        if ($amountType === 'fixed') {
            $amount = SLN_Plugin::getInstance()->format()->money($amount, false, true, true, false, $isLeftCurrencySymbol);
        }
        else {
            $amount = "{$amount}%";
        }

        return $amount;
    }

    function getUsagesLimit()
    {
        $ret = $this->getMeta('usages_limit');

        return $ret;
    }

	function isUnlimitedUsages() {
		$limit = intval($this->getUsagesLimit());

		return $limit <= 0;
	}

    function getTotalUsagesLimit()
    {
        $ret = $this->getMeta('usages_limit_total');

        return $ret;
    }

	function isUnlimitedTotalUsages() {
		$limit = intval($this->getTotalUsagesLimit());

		return $limit <= 0;
	}

    function getTotalUsagesNumber()
    {
        $ret = $this->getMeta('usages_total');
        $ret = empty($ret) ? 0 : intval($ret);

        return $ret;
    }

    function incrementTotalUsagesNumber()
    {
        $this->setMeta('usages_total', 1 + $this->getTotalUsagesNumber());
    }

    function decrementTotalUsagesNumber()
    {
        $count = (int) $this->getTotalUsagesNumber();
        $this->setMeta('usages_total', $count > 0 ? $count - 1 : 0);
    }

    /**
     * @param WP_User|int $customer
     *
     * @return int
     */
    function getUsagesNumber($customer)
    {
        $customer = new SLN_Wrapper_Customer($customer, false);

        $ret = $customer->getMeta("discount_{$this->getId()}");
        $ret = empty($ret) ? 0 : $ret;

        return $ret;
    }

    function incrementUsagesNumber($customer)
    {
        $customer = new SLN_Wrapper_Customer($customer, false);

        if (!$customer->isEmpty()) {
            $customer->setMeta("discount_{$this->getId()}", 1 + (int)$customer->getMeta("discount_{$this->getId()}"));

            return true;
        }

        return false;
    }

    function decrementUsagesNumber($customer)
    {
        $customer = new SLN_Wrapper_Customer($customer, false);

        if (!$customer->isEmpty()) {
            $count = (int)$customer->getMeta("discount_{$this->getId()}");
            $customer->setMeta("discount_{$this->getId()}", $count > 0 ? $count - 1 : 0);

            return true;
        }

        return false;
    }

	/**
     * @param string $timezone
     *
     * @return null|SLN_DateTime
     */
    function getStartsAt($timezone='')
    {
        $date = $this->getMeta('from');
        if (!empty($date)) {
            if($timezone)
                $date = new SLN_DateTime($date, new DateTimeZone($timezone) );
            else
                $date = new SLN_DateTime($date);
        }
        else {
            $date = null;
        }

        return $date;
    }

    /**
     * @param string $timezone
     *
     * @return null|SLN_DateTime
     */
    function getEndsAt($timezone='')
    {
        $date = $this->getMeta('to');
        if (!empty($date)) {
            if($timezone)
                $date = new SLN_DateTime($date, new DateTimeZone($timezone) );
            else
                $date = new SLN_DateTime($date);
        }
        else {
            $date = null;
        }

        return $date;
    }

	/**
     * @return array
     */
    function getServicesIds()
    {
        $ret = $this->getMeta('services');
        if (!is_array($ret)) {
            $ret = array();
        }

        return $ret;
    }

    /**
     * @return array
     */
    function geAttendantsIds()
    {
        $ret = $this->getMeta('attendants');
        if (!is_array($ret)) {
            $ret = array();
        }

        return $ret;
    }

    /**
     * @return array
     */
    function geShopsIds()
    {
        $ret = $this->getMeta('shops');
        if (!is_array($ret)) {
            $ret = array();
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

    function getDiscountType()
    {
        $ret = $this->getMeta('type');
        $ret = empty($ret) ? SLB_Discount_Enum_DiscountType::getDefaultType() : $ret;

        return $ret;
    }

    function getCouponCode()
    {
        $ret = (string) $this->getMeta('code');

        return $ret;
    }

	/**
     * @return array
     */
    function getDiscountRules()
    {
        $ret = (array) $this->getMeta('rules');
        $ret = array_filter($ret);

        return $ret;
    }

    /**
     * Returns stored exclusion rules regardless of edition (used by the admin UI).
     *
     * @return array
     */
    public function getExclusionRulesRaw()
    {
        $ret = (array) $this->getMeta('exclusion_rules');
        $ret = array_filter($ret);

        return $ret;
    }

    /**
     * Returns exclusion rules for validation; empty array on free edition.
     *
     * @return array
     */
    public function getExclusionRules()
    {
        if (!defined('SLN_VERSION_PAY')) {
            return array();
        }

        return $this->getExclusionRulesRaw();
    }

    public function getName()
    {
        if ($this->object) {
            return $this->object->post_title;
        } else {
            return 'n.d.';
        }
    }

    public function __toString()
    {
        return $this->getName();
    }

    public static function generateCouponCode() {
        $maxAttempts = 10;

        for ( $i = 0; $i < $maxAttempts; $i++ ) {
            $code = self::generateRandomCode( 8 );

            if ( ! self::couponCodeExists( $code ) ) {
                return $code;
            }
        }

        // Extremely unlikely fallback: append a timestamp suffix to guarantee uniqueness.
        return self::generateRandomCode( 8 ) . substr( (string) time(), -4 );
    }

    /**
     * Generates a random uppercase alphanumeric code of the given length.
     *
     * @param int $length
     * @return string
     */
    private static function generateRandomCode( $length ) {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max    = strlen( $chars ) - 1;
        $result = '';

        for ( $i = 0; $i < $length; $i++ ) {
            $result .= $chars[ random_int( 0, $max ) ];
        }

        return $result;
    }

    /**
     * Checks whether a coupon code already exists in the database.
     *
     * @param string $code
     * @return bool
     */
    private static function couponCodeExists( $code ) {
        global $wpdb;

        $meta_key = '_' . SLB_Discount_Plugin::POST_TYPE_DISCOUNT . '_code';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                $meta_key,
                $code
            )
        );

        return intval( $count ) > 0;
    }

	/**
     * @param float $value
     *
     * @return float
     */
    private function calculateDiscount($value) {
        $amount     = $this->getAmount();
        $amountType = $this->getAmountType();

        if ($amountType === 'fixed') {
            $ret = $amount;
        }
        else {
            $ret = round(($value/100)*$amount, 2);
        }
        return $ret;
    }

    public static function wrapperToID($wrapper){
        if(!is_object($wrapper)){
            return $wrapper;
        }
        return $wrapper->getId();
    }

    /**
     * @param SLN_Wrapper_Booking_Services $bookingServices
     *
     * @param bool $split Split discount sum by services
     *
     * @return array|float
     */
    public function applyDiscountToBookingServices($bookingServices, $split = false, $bookingAttendants = array()) {
        $ret = array(
            'total'    => 0.0,
            'services' => array()
        );  
        $discountServices = $this->getServicesIds();
        $attendants = $this->geAttendantsIds();
        $bookingAttendants = array_map(array('SLB_Discount_Wrapper_Discount', 'wrapperToID'), $bookingAttendants);
        
        // For fixed discounts, calculate total of eligible services first
        $amountType = $this->getAmountType();
        $eligibleServicesTotal = 0.0;
        $eligibleServices = array();
        
        foreach ($bookingServices->getItems() as $bookingService) {
            if(!empty($attendants)){
                $atts = $bookingService->getAttendant();
                $atts = is_array($atts)? array_map(array('SLB_Discount_Wrapper_Discount', 'wrapperToID'), $atts) : $atts->getId();
                $check_att = !is_array($atts) ? 
                                !in_array($atts, $attendants) :
                                !empty(array_intersect($atts, $attendants));
                $check_att = $check_att || (
                    (!empty($bookingAttendants) && (
                        !is_array($atts) ? 
                            !in_array($atts, $bookingAttendants) :
                            !empty(array_intersect($atts, $bookingAttendants))
                        )
                    ) || empty($bookingAttendants)
                );
            }else{
                $check_att = false;
            }
            
            $serviceId = $bookingService->getService()->getId();
            $servicePrice = SLN_Func::filter($bookingService->getPrice(), 'float');
            
            // Check if service is eligible for discount
            $isEligible = true;
            if (!empty($discountServices) && !in_array($serviceId, $discountServices)) {
                $isEligible = false;
            } elseif($check_att) {
                $isEligible = false;
            }
            
            if ($isEligible) {
                $eligibleServices[$serviceId] = $servicePrice;
                $eligibleServicesTotal += $servicePrice;
            }
        }
        
        // Now calculate discount for each service
        foreach ($bookingServices->getItems() as $bookingService) {
            $serviceId = $bookingService->getService()->getId();
            $servicePrice = SLN_Func::filter($bookingService->getPrice(), 'float');
            
            if (isset($eligibleServices[$serviceId])) {
                // Service is eligible for discount
                if ($amountType === 'fixed' && $eligibleServicesTotal > 0) {
                    // For fixed discounts, distribute proportionally
                    $proportion = $servicePrice / $eligibleServicesTotal;
                    $ret['services'][$serviceId] = round($this->getAmount() * $proportion, 2);
                } else {
                    // For percentage discounts, calculate normally
                    $ret['services'][$serviceId] = $this->calculateDiscount($servicePrice);
                }
            } else {
                // Service not eligible
                $ret['services'][$serviceId] = 0.0;
            }
        }
        $ret['total'] = array_sum($ret['services']);

        if ($split) {
            return $ret['services'];
        }
        else {
            return $ret['total'];
        }
    }

    /**
     * @param SLN_Wrapper_Booking_Builder $bb
     *
     * @return bool
     */
    public function isValidDiscountFullForBB($bb) {
        $errors = $this->validateDiscountFullForBB($bb);

        return empty($errors);
    }

    public function isHideFromAccount(){
        $ret = $this->getMeta('hide_from_account');
        return !empty($ret) && $ret;
    }

    /**
     * @param SLN_Wrapper_Booking_Builder $bb
     *
     * @return array
     */
    public function validateDiscountFullForBB($bb) {
        // CRITICAL FIX: Validate booking builder is not null
        if (!$bb) {
            return array(__('Booking session is invalid. Please start a new booking.', 'salon-booking-system'));
        }
        
        $customer = new SLN_Wrapper_Customer(get_current_user_id(), false);

        $bookingServices = $bb->getBookingServices();
        $bookingAttendants = $bb->getAttendantsIds();
        $first = $bookingServices->getFirstItem();
        
        // DEFENSIVE FIX: Validate booking has services
        if (!$first) {
            return array(__('Please select at least one service before applying a discount code.', 'salon-booking-system'));
        }
        
        $date  = $first->getStartsAt()->getTimestamp();

        $errors = $this->validateDiscountFull($date, $bookingServices, $customer, $bookingAttendants);
        $isShopEnabled = false;
        $isShopEnabled = apply_filters('sln_is_shops_enabled',$isShopEnabled);

        if($isShopEnabled && empty($errors)){
            $bookingShops = $this->getMeta('shop');
            $discountShops = $this->geShopsIds();

            $errors   = $this->validateDiscountForShops($bookingShops,$discountShops);
        }
        return $errors;
    }

    public function validateDiscountForShops($bookingShops,$discountShops) {
        $errors   = array();

        if(!empty($discountShops)){
            $intersect = in_array($bookingShops,$discountShops);
            if (!$intersect) {
                $errors[] = __('This coupon is not valid for selected shop', 'salon-booking-system');
            }
        }

        return $errors;
    }

    /**
     * @param string $date
     * @param SLN_Wrapper_Booking_Services $bookingServices
     * @param SLN_Wrapper_Customer $customer
     *
     * @return bool
     */
    public function isValidDiscountFull($date, $bookingServices, $customer, $bookingAttende) {
        $errors = $this->validateDiscountFull($date, $bookingServices, $customer, $bookingAttende);

        return empty($errors);
    }

    /**
     * @param string $date
     * @param SLN_Wrapper_Booking_Services $bookingServices
     * @param SLN_Wrapper_Customer $customer
     *
     * @return array
     */
    public function validateDiscountFull($date, $bookingServices, $customer, $bookingAttendants) {
        $errors = $this->validateDiscount($date);
        if (!empty($errors)) {
            return $errors;
        }

        $errors = $this->validateDiscountForBookingServices($bookingServices, $bookingAttendants);
        if (!empty($errors)) {
            return $errors;
        }

        $errors = $this->validateDiscountForBookingAttendants($bookingAttendants);
        if (!empty($errors)) {
            return $errors;
        }

        $errors = $this->validateDiscountForCustomer($customer);
        if (!empty($errors)) {
            return $errors;
        }

        $errors = $this->validateDiscountRules($date, $customer);
        if (!empty($errors)) {
            return $errors;
        }

        $errors = $this->validateDiscountExclusionRules($date);
        if (!empty($errors)) {
            return $errors;
        }

        return $errors;
    }

    /**
     * Returns errors when any exclusion rule matches the booking date.
     * Always returns empty on the free edition (rules are stored but not enforced).
     *
     * @param int $date Unix timestamp of the booking date
     * @return array
     */
    public function validateDiscountExclusionRules($date)
    {
        $ret   = array();
        $rules = $this->getExclusionRules(); // empty on free edition

        if (empty($rules)) {
            return $ret;
        }

        foreach ($rules as $rule) {
            if (!$this->isExclusionRuleInScope($rule, $date)) {
                continue;
            }

            $mode = isset($rule['mode']) ? $rule['mode'] : 'weekdays';

            if ($mode === 'weekdays') {
                // Day key: (date('w') + 1) maps 0–6 → 1–7 matching SLN_Func::getDays() keys
                $dayKey = (int) SLN_TimeFunc::date('w', $date) + 1;
                if (!empty($rule['days']) && isset($rule['days'][$dayKey])) {
                    if (!$this->isExclusionTimeMatch($rule, $date)) {
                        continue;
                    }
                    $ret[] = __('This discount is not available on this day.', 'salon-booking-system');
                    break;
                }
            } elseif ($mode === 'specific_dates') {
                $specificDates = !empty($rule['specific_dates'])
                    ? array_filter(array_map('trim', explode(',', $rule['specific_dates'])))
                    : array();
                $bookingDate = SLN_TimeFunc::date('Y-m-d', $date);
                if (in_array($bookingDate, $specificDates, true)) {
                    if (!$this->isExclusionTimeMatch($rule, $date)) {
                        continue;
                    }
                    $ret[] = __('This discount is not available on this date.', 'salon-booking-system');
                    break;
                }
            }
        }

        return $ret;
    }

    /**
     * Checks whether an exclusion rule is currently active based on its date-range scope.
     *
     * @param array $rule
     * @param int   $date Unix timestamp
     * @return bool
     */
    private function isExclusionRuleInScope($rule, $date)
    {
        $always = isset($rule['always']) ? (bool) $rule['always'] : true;
        if ($always) {
            return true;
        }

        $fromDate = !empty($rule['from_date'])
            ? (new SLN_DateTime($rule['from_date']))->setTime(0, 0)->getTimestamp()
            : null;
        $toDate = !empty($rule['to_date'])
            ? (new SLN_DateTime($rule['to_date']))->setTime(23, 59, 59)->getTimestamp()
            : null;

        if ($fromDate !== null && $date < $fromDate) {
            return false;
        }
        if ($toDate !== null && $date > $toDate) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the booking time falls within the exclusion rule's time restriction.
     *
     * Returns true when the exclusion should apply:
     *   - Always true when no time restriction is configured (apply_time_range is false).
     *   - True when the booking time falls inside at least one enabled shift.
     *   - False when the booking time is outside all defined shifts (rule should not trigger).
     *
     * @param array $rule
     * @param int   $date Unix timestamp of the booking start
     * @return bool
     */
    private function isExclusionTimeMatch($rule, $date)
    {
        if (empty($rule['apply_time_range'])) {
            return true;
        }

        $bookingMins = (int) SLN_TimeFunc::date('G', $date) * 60
                     + (int) SLN_TimeFunc::date('i', $date);

        $inShift = function ($from, $to) use ($bookingMins) {
            if (empty($from) || empty($to)) {
                return false;
            }
            list($h, $m) = array_map('intval', explode(':', $from));
            $fromMins = $h * 60 + $m;
            list($h, $m) = array_map('intval', explode(':', $to));
            $toMins = $h * 60 + $m;
            return $bookingMins >= $fromMins && $bookingMins < $toMins;
        };

        $from = isset($rule['from']) && is_array($rule['from']) ? $rule['from'] : array();
        $to   = isset($rule['to'])   && is_array($rule['to'])   ? $rule['to']   : array();

        if ($inShift(isset($from[0]) ? $from[0] : '', isset($to[0]) ? $to[0] : '')) {
            return true;
        }

        $secondShiftDisabled = ! empty($rule['disable_second_shift']);
        if (! $secondShiftDisabled
            && $inShift(isset($from[1]) ? $from[1] : '', isset($to[1]) ? $to[1] : '')) {
            return true;
        }

        return false;
    }

    private function validateDiscountForBookingAttendants($bookingAttendants) {
        $ret = array();
        $attendants = $this->geAttendantsIds();
        $discountServices = $this->getServicesIds();

        $intersect = array_intersect($attendants, $bookingAttendants);
        if (empty($intersect) && empty($discountServices) && !empty($attendants)) {
            $ret[] = __('This coupon is not valid for selected assistant', 'salon-booking-system');
        }
        return $ret;
    }

    public function validateDiscountForMail($date, $customer){
        $errors = $this->validateDiscount($date);
        if(!empty($errors)){
            return $errors;
        }
        $errors = $this->validateDiscountForCustomer($customer);
        if(!empty($errors)){
            return $errors;
        }
        $errors = $this->validateDiscountRules($date, $customer);
        if(!empty($errors)){
            return $errors;
        }
    }

	/**
     * @param string $date
     *
     * @return array
     */
    public function validateDiscount($date) {
        $dateT = $date;
        $ret   = array();
        $start = $this->getStartsAt() ? $this->getStartsAt()->setTime(0,0)->getTimestamp() : null;
        $end =   $this->getEndsAt() ? $this->getEndsAt()->setTime(23,59,59)->getTimestamp() : null;

        if (($start !== null && $dateT < $start) || ($end !== null && $dateT > $end)) {
            $ret[] = __('Coupon expired', 'salon-booking-system');
        }
        elseif(!$this->isUnlimitedTotalUsages() && $this->getTotalUsagesNumber() >= intval($this->getTotalUsagesLimit())) {
            $ret[] = __('This coupon was applied maximum number of times', 'salon-booking-system');
        }

        return $ret;
    }

    /**
     * @param SLN_Wrapper_Customer $customer
     *
     * @return array
     */
    public function validateDiscountForCustomer($customer) {
        $ret = array();
        if (!$this->isUnlimitedUsages() && $this->getUsagesNumber($customer->getId()) >= intval($this->getUsagesLimit())) {
            $ret[] = __('You applied this coupon maximum number of times', 'salon-booking-system');
        }
        return $ret;
    }

    /**
     * @param SLN_Wrapper_Booking_Services $bookingServices
     *
     * @return array
     */
    private function validateDiscountForBookingServices($bookingServices, $bookingAttendants) {
        $ret = array();

        $discountServices = $this->getServicesIds();
        $attendants = $this->geAttendantsIds();

        if (empty($discountServices)) {
            return $ret;
        }

        $services = array();
        foreach($bookingServices->getItems() as $bookingService) {
            $services[] = $bookingService->getService()->getId();
        }

        $intersect = array_intersect($services, $discountServices);
        $intersectAt = array_intersect($attendants, $bookingAttendants);
        if (empty($intersect) && empty($intersectAt)) {
            $ret[] = __('This coupon is not valid for selected services', 'salon-booking-system');
        }
        return $ret;
    }

    /**
     * @param array $servicesIds
     * @return array
     */
    public function validateDiscountForServicesIds($servicesIds) {
        $ret = array();

        $discountServices = $this->getServicesIds();
        if (empty($discountServices)) {
            return $ret;
        }

        $intersect = array_intersect($servicesIds, $discountServices);
        if (empty($intersect)) {
            $ret[] = __('This coupon is not valid for selected services', 'salon-booking-system');
        }
        return $ret;
    }

    /**
     * @param string $date
     * @param SLN_Wrapper_Customer $customer
     *
     * @return array
     */
    public function validateDiscountRules($date, $customer) {
        $ret = array();

        if ( $this->getDiscountType() === SLB_Discount_Enum_DiscountType::DISCOUNT_CODE) {
            return $ret;
        }
        $dateT = $date;

        $rules = $this->getDiscountRules();
        if (!empty($rules)) {
            foreach($rules as $rule) {
                if ($rule['mode'] === 'daterange') {
                    $from = new SLN_DateTime($rule['daterange_from']);
                    $to = new SLN_DateTime($rule['daterange_to']);

                    if (!($dateT >= $from->setTime(0,0)->getTimestamp() && $dateT <= $to->setTime(23,59,59)->getTimestamp())) {
                        $ret[] = __('Coupon expired', 'salon-booking-system');
                        break;
                    }
                }
                elseif ($rule['mode'] === 'weekdays') {
                    $week_day = SLN_TimeFunc::date('w',$dateT);
                    if (!in_array((int) $week_day, $rule['weekdays'])) {
                        $ret[] = sprintf(
                            // translators: %s will be replaced by the days Of week
                            __('This coupon is not valid on %s', 'salon-booking-system'), SLN_Enum_DaysOfWeek::getLabel((int) $week_day));
                        break;
                    }
                }
                elseif ($rule['mode'] === 'bookings' || $rule['mode'] === 'amount') {
                    $criteria = array(
                        '@wp_query' => array(
                            'author' => $customer->getId()
                        )
                    );
                    $bookings = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_BOOKING)->get($criteria);

                    if ($rule['mode'] === 'bookings') {
                        if (count($bookings) < ((int) $rule['bookings_number'])) {
                            $ret[] = __('Make more bookings to be able to use this coupon', 'salon-booking-system');
                            break;
                        }
                    }
                    else {
                        $total = 0.0;
                        /** @var SLN_Wrapper_Booking $booking */
                        foreach($bookings as $booking) {
                            $total += $booking->getAmount();
                        }

                        if ($total < ((float) $rule['amount_number'])) {
                            $ret[] = __('Make more bookings to be able to use this coupon', 'salon-booking-system');
                            break;
                        }
                    }
                }
                elseif ($rule['mode'] === 'score' && !empty($customer)) {
                    if ($customer->getFidelityScore() < ((int) $rule['score_number'])) {
                        $ret[] = __('Make more customer score to be able to use this coupon', 'salon-booking-system');
                        break;
                    }
                }
            }
        }

        return $ret;
    }
}