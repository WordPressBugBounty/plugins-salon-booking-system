<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Shortcode_Salon_SummaryStep extends SLN_Shortcode_Salon_Step
{
    const SLOT_UNAVAILABLE    = 'slotunavailable';
    const SERVICES_DATA_EMPTY = 'servicesdataempty';

    private $op;

    protected function dispatchForm()
    {
        error_log('=== SummaryStep::dispatchForm() START ===');
        error_log('Mode: ' . (isset($_GET['mode']) ? $_GET['mode'] : 'NONE'));
        error_log('Booking ID in URL: ' . (isset($_GET['sln_booking_id']) ? $_GET['sln_booking_id'] : 'NONE'));
        
        // Session validation
        if (session_status() !== PHP_SESSION_ACTIVE) {
            SLN_Plugin::addLog("ERROR: Session not active in SummaryStep::dispatchForm");
            $this->addError(__('Your session has expired. Please start the booking process again.', 'salon-booking-system'));
            return false;
        }

        // Bot protection: reCAPTCHA verification
        // Note: Verification is done in the AJAX handler (SalonStep.php) before this step
        // We skip verification here because:
        // 1. AJAX handler already verified the token
        // 2. reCAPTCHA tokens can only be used once
        // 3. Re-verifying would fail and block legitimate bookings
        if (SLN_Helper_RecaptchaVerifier::isEnabled()) {
            SLN_Plugin::addLog('[reCAPTCHA] SummaryStep: Skipping verification (already done in AJAX handler)');
            error_log('[Salon reCAPTCHA] SummaryStep: Skipping verification (already done in AJAX handler)');
        }

        $bookingBuilder = $this->getPlugin()->getBookingBuilder();
        $bb     = $bookingBuilder->getLastBooking();
        error_log('After getLastBooking(): ' . ($bb ? 'FOUND (ID: ' . $bb->getId() . ', Status: ' . $bb->getStatus() . ')' : 'NULL'));
        
        $value = isset($_POST['sln']) && isset($_POST['sln']['note']) ? sanitize_text_field(wp_unslash($_POST['sln']['note'])) : '';
        $plugin = $this->getPlugin();
        $isCreateAfterPay = /*$plugin->getSettings()->get('create_booking_after_pay') &&*/ $plugin->getSettings()->isPayEnabled();
        if(isset($_GET['sln_booking_id']) && intval($_GET['sln_booking_id'])){
            error_log('Getting booking from URL parameter: ' . intval($_GET['sln_booking_id']));
            $bb = $plugin->createBooking(intval(sanitize_text_field($_GET['sln_booking_id'])));
        }

        if(empty($bb) && isset($_GET['op'])){
            error_log('Getting booking from op parameter');
            $bb = $plugin->createBooking(explode('-', sanitize_text_field($_GET['op']))[1]);
        }

        // If booking still empty, check if we're processing a form submission
        // In that case, try to create the booking from BookingBuilder data
        if(empty($bb)) {
            error_log('Booking is EMPTY - attempting to create from BookingBuilder');
            // Check both GET and POST for mode parameter (can be in either depending on form method)
            $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : 
                    (isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : null);
            error_log('Mode for creation: ' . ($mode ? $mode : 'NONE'));
            
            // If user is submitting the form (confirm/later/payment), create booking first
            // Skip strict validation - let render() create the booking, dispatchForm() will process it
            if(!empty($mode)) {
                error_log('Mode present, creating booking from BookingBuilder in DRAFT status');
                try {
                    $bookingBuilder->create(SLN_Enum_BookingStatus::DRAFT);
                    $bb = $bookingBuilder->getLastBooking();
                    error_log('After creation: ' . ($bb ? 'SUCCESS (ID: ' . $bb->getId() . ')' : 'FAILED'));
                } catch (\Exception $e) {
                    error_log('Exception creating booking: ' . $e->getMessage());
                }
            } else {
                error_log('No mode parameter - not creating booking');
            }
            
            // If still no booking, return false (this is normal for initial page load)
            if(empty($bb)) {
                error_log('dispatchForm() returning FALSE - no booking available');
                return false;
            }
        } else {
            error_log('Booking exists, proceeding with ID: ' . $bb->getId());
        }
        if(!empty($value)){
            $bb->setMeta('note', SLN_Func::filter($value));
        }
        $handler = new SLN_Action_Ajax_CheckDateAlt( $plugin );

        $paymentMethod = $plugin->getSettings()->isPayEnabled() ? SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) : false;
        // Check both GET and POST for mode parameter
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : 
                (isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : null);

        if($mode == 'confirm' || empty($paymentMethod) || $bb->getAmount() <= 0.0){
            $errors = $handler->checkDateTimeServicesAndAttendants($bb->getAttendantsIds(), $bb->getStartsAt());
            if(!empty($errors) && !class_exists('\\SalonMultishop\\Addon')){
                $this->addError(self::SLOT_UNAVAILABLE);
                return false;
            }
            foreach ($bb->getMeta('discounts') as $discount){
                $discount_ = new SLB_Discount_Wrapper_Discount($discount);
                $discount_->incrementUsagesNumber($bb->getUserId());
                $discount_->incrementTotalUsagesNumber();
            }
            if($bb->getStatus() == SLN_Enum_BookingStatus::DRAFT){
                ///$bb->setStatus($bb->getCreateStatus()); // SLN_Wrapper_Booking::getCreateStatus
                if($bb->getAmount() <= 0.0 && !SLN_Plugin::getInstance()->getSettings()->get('confirmation')){
                    $bb->setStatus(SLN_Enum_BookingStatus::CONFIRMED);
                } else if(SLN_Plugin::getInstance()->getSettings()->get('confirmation')) {
                    $bb->setStatus(SLN_Enum_BookingStatus::PENDING);
                } else if(empty($paymentMethod)) {
                    $bb->setStatus(SLN_Enum_BookingStatus::CONFIRMED);
                }  else {
                    $bb->setStatus(SLN_Enum_BookingStatus::PAID);
                }
            }
            $bb->setPrepaidServices();
            $bookingBuilder->clear($bb->getId());
            $this->cleanupBookingLock($bb);
            return !$this->hasErrors();
        } elseif($mode == 'later'){
            $errors = $handler->checkDateTimeServicesAndAttendants($bb->getAttendantsIds(), $bb->getStartsAt());
            if(!empty($errors) && !class_exists('\\SalonMultishop\\Addon')){
                $this->addError(self::SLOT_UNAVAILABLE);
                return false;
            }
            if(in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::PENDING_PAYMENT, SLN_Enum_BookingStatus::DRAFT))){
                if($bb->getAmount() > 0.0){
                    $bb->setStatus(SLN_Enum_BookingStatus::PAY_LATER);
                }else{
                    $bb->setPrepaidServices();
                    $bb->setStatus($bb->getCreateStatus()); // SLN_Wrapper_Booking::getCreateStatus
                }
            }
            
            $bookingBuilder->clear($bb->getId());
            $this->cleanupBookingLock($bb);
            return !$this->hasErrors();
        }elseif(isset($_GET['op']) || $mode){
            if ($bookingBuilder && method_exists($bookingBuilder, 'forceTransientStorage')) {
                $bookingBuilder->forceTransientStorage();
            }
            if($error = $paymentMethod->dispatchThankyou($this, $bb)){
                $this->addError($error);
            }
        }
        if(!$this->hasErrors()){
            $bookingBuilder->clear($bb->getId());
            $this->cleanupBookingLock($bb);
        }
        if(!empty($paymentMethod) && in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::PAY_LATER, SLN_Enum_BookingStatus::PENDING_PAYMENT))){
            return false;
        }
        if($bb->getStatus() == SLN_Enum_BookingStatus::DRAFT){
            $bb->setStatus($bb->getCreateStatus());
        }

        return !$this->hasErrors();
    }

    public function render()
    {
        $bb = $this->getPlugin()->getBookingBuilder();
        
        // Debug: Log booking builder state
        error_log('SummaryStep::render() - Start');
        error_log('Has services: ' . ($bb->get('services') ? 'YES' : 'NO'));
        error_log('Is valid: ' . ($bb->isValid() ? 'YES' : 'NO'));
        error_log('Has last booking: ' . ($bb->getLastBooking() ? 'YES (ID: ' . $bb->getLastBooking()->getId() . ')' : 'NO'));
        if (!$bb->get('services')) {
            error_log('Services data: ' . print_r($bb->get('services'), true));
        }
        
        // Check if we have services data - this is enough to display the summary
        // Full validation (isValid) is too strict for display - it checks availability
        // which can fail if time passed or slots got taken. Strict validation happens
        // in dispatchForm() when user submits the booking.
        if($bb->get('services')){
            error_log('SummaryStep::render() - Path A: Has services data, rendering summary');
            
            // Check validation for logging purposes only
            $isValid = $bb->isValid();
            error_log('SummaryStep::render() - Validation status: ' . ($isValid ? 'VALID' : 'INVALID (will validate again on submission)'));
            
            $data = $this->getViewData();
            
            // Only apply lock check if booking doesn't already exist
            // This prevents blocking the same user on subsequent renders
            $existingBooking = $bb->getLastBooking();
            
            // Lock check removed - was causing false positives
            // The system has other availability checks that prevent double-bookings

            do_action('sln.shortcode.summary.dispatchForm.before_booking_creation', $this, $bb);
            
            if ( ! $this->hasErrors() ) {
                error_log('SummaryStep::render() - Creating booking in DRAFT status');
                $bb->create(SLN_Enum_BookingStatus::DRAFT);
                
                // Pass BookingBuilder to extensions for backward compatibility
                // Extensions like SalonMultishop and Discount expect the builder object
                // They can call $bb->getLastBooking() themselves if they need the booking
                do_action('sln.shortcode.summary.dispatchForm.after_booking_creation', $bb);
            } else {
                error_log('SummaryStep::render() - Has errors, not creating booking: ' . print_r($this->getErrors(), true));
            }
            return parent::render();
        }elseif($bb->getLastBooking()){
            error_log('SummaryStep::render() - Path B: Has existing booking');
            $data = $this->getViewData();
            $bb = $bb->getLastBooking();
            
            $custom_url = apply_filters('sln.shortcode.render.custom_url', false, $this->getStep(), $this->getShortcode(), $bb);
            if ($custom_url) {
                error_log('SummaryStep::render() - Redirecting to custom URL: ' . $custom_url);
                $this->redirect($custom_url);
                wp_die();
            }
            return parent::render();
        }else{
            error_log('SummaryStep::render() - Path C: Fallback (ERROR PATH)');
            if(empty($bb->get('services'))){
                error_log('SummaryStep::render() - ERROR: No services data, redirecting to services step');
                $this->addError(self::SERVICES_DATA_EMPTY);
                $this->redirect(add_query_arg(array('sln_step_page' => 'services')));
                return parent::render(); // Return content after redirect for AJAX
            }else{
                error_log('SummaryStep::render() - ERROR: Slot unavailable');
                $this->addError(self::SLOT_UNAVAILABLE);
                return parent::render();
            }
        }
    }

    public function setOp($op){
        $this->op = $op;
    }

    public function getViewData(){
        $ret = parent::getViewData();
        $formAction = $ret['formAction'];

        $requestArgs = $this->getSanitizedRequestArgs();
        $baseAction  = $this->buildBaseUrl($formAction, $requestArgs);

        $bookingBuilder = $this->getPlugin()->getBookingBuilder();
        $lastBooking = $bookingBuilder->getLastBooking();
        $lastBookingId = $lastBooking ? $lastBooking->getId() : null;
        $clientId = $bookingBuilder->getClientId();

        if ($this->getPlugin()->getSettings()->isPayEnabled() && empty($clientId) && method_exists($bookingBuilder, 'forceTransientStorage')) {
            $clientId = $bookingBuilder->forceTransientStorage();
        }

        $commonArgs = $this->getCommonUrlArgs($requestArgs, $clientId);

        $laterUrl = add_query_arg(
            array_merge(
                $commonArgs,
                array(
                    'mode' => 'later',
                    'submit_'. $this->getStep() => 'next',
                    'sln_step_page' => $this->getStep(),
                )
            ),
            $baseAction
        );

        $confirmUrl = add_query_arg(
            array_merge(
                $commonArgs,
                array(
                    'mode' => 'confirm',
                    'submit_'. $this->getStep() => 'next',
                    'sln_step_page' => $this->getStep(),
                )
            ),
            $baseAction
        );
        $confirmUrl = apply_filters('sln.booking.thankyou-step.get-confirm-url', $confirmUrl);

        if (!empty($clientId)) {
            $confirmUrl = add_query_arg('sln_client_id', $clientId, $confirmUrl);
            $laterUrl   = add_query_arg('sln_client_id', $clientId, $laterUrl);
        }

        if (!empty($lastBookingId)) {
            $confirmUrl = add_query_arg('sln_booking_id', $lastBookingId, $confirmUrl);
            $laterUrl   = add_query_arg('sln_booking_id', $lastBookingId, $laterUrl);
        }

        $data = array(
            'booking' => $lastBooking,
            'confirmUrl' => $confirmUrl,
            'laterUrl' => $laterUrl,
        );
        
        if($this->getPlugin()->getSettings()->isPayEnabled()){
            $payBase = $this->getPlugin()->getSettings()->getPayPageId()
                ? get_permalink($this->getPlugin()->getSettings()->getPayPageId())
                : $baseAction;

            $payUrl = add_query_arg(
                array_merge(
                    $commonArgs,
                    array(
                        'mode' => $this->getPlugin()->getSettings()->getPaymentMethod(),
                        'submit_'. $this->getStep() => 'next',
                        'sln_step_page' => $this->getStep(),
                    )
                ),
                $payBase
            );

            if (!empty($clientId)) {
                $payUrl = add_query_arg('sln_client_id', $clientId, $payUrl);
            }
            if (!empty($lastBookingId)) {
                $payUrl = add_query_arg('sln_booking_id', $lastBookingId, $payUrl);
            }

            $payUrl = apply_filters('sln.booking.thankyou-step.get-pay-url', $payUrl);
            $data['payUrl'] = $payUrl;
            $data['payOp'] = $this->op;
        }
        return array_merge($ret, $data);
    }

    private function getSanitizedRequestArgs()
    {
        $args = $_GET;
        if (isset($args['page_id']) && strpos($args['page_id'], '?') !== false) {
            $parts = explode('?', $args['page_id'], 2);
            $args['page_id'] = $parts[0];
            if (!empty($parts[1])) {
                parse_str($parts[1], $extra);
                $args = array_merge($extra, $args);
            }
        }
        return $args;
    }

    private function buildBaseUrl($url, array $queryArgs)
    {
        $base = remove_query_arg(array_keys($queryArgs), $url);
        if (isset($queryArgs['page_id'])) {
            $base = add_query_arg('page_id', $queryArgs['page_id'], $base);
        }
        return $base;
    }

    private function getCommonUrlArgs(array $requestArgs, $clientId)
    {
        $common = array();
        if (isset($requestArgs['lang'])) {
            $common['lang'] = $requestArgs['lang'];
        }
        if (isset($requestArgs['pay_remaining_amount'])) {
            $common['pay_remaining_amount'] = $requestArgs['pay_remaining_amount'];
        }
        if (isset($requestArgs['mode']) && !in_array($requestArgs['mode'], array('confirm', 'later'), true)) {
            $common['mode'] = $requestArgs['mode'];
        }
        if (!empty($clientId) && !isset($common['sln_client_id'])) {
            $common['sln_client_id'] = $clientId;
        }
        return $common;
    }

    public function redirect($url)
    {
        if ($this->isAjax()) {
            throw new SLN_Action_Ajax_RedirectException($url);
        } else {
            wp_redirect($url);die;
        }
    }

    public function isAjax()
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    public function getTitleKey(){
        return 'Booking summary';
    }

    public function getTitleLabel(){
        return __('Booking summary', 'salon-booking-system');
    }

    /**
     * Clean up the transient lock when a booking is deleted
     * 
     * @param SLN_Wrapper_Booking $booking
     */
    private function cleanupBookingLock($booking){
        $service_ids   = implode('-', $booking->getServicesIds());
        $attendant_ids = implode('-', array_values($booking->getAttendantsIds()));
        $start_time    = $booking->getStartsAt()->format('Y-m-d H:i:s');
        
        $lock_key = 'booking_lock_' . md5($service_ids . '_' . $attendant_ids . '_' . $start_time);
        delete_transient($lock_key);
    }
}
