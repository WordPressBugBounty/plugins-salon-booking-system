<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Shortcode_Salon_SummaryStep extends SLN_Shortcode_Salon_Step
{
    const SLOT_UNAVAILABLE    = 'slotunavailable';
    const SERVICES_DATA_EMPTY = 'servicesdataempty';

    private $op;

    protected function dispatchForm()
    {
        SLN_Plugin::addLog('=== SummaryStep::dispatchForm() START ===');
        SLN_Plugin::addLog('Mode: ' . (isset($_GET['mode']) ? $_GET['mode'] : 'NONE'));
        SLN_Plugin::addLog('Booking ID in URL: ' . (isset($_GET['sln_booking_id']) ? $_GET['sln_booking_id'] : 'NONE'));
        
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
        }

        $bookingBuilder = $this->getPlugin()->getBookingBuilder();
        $bb     = $bookingBuilder->getLastBooking();
        SLN_Plugin::addLog('After getLastBooking(): ' . ($bb ? 'FOUND (ID: ' . $bb->getId() . ', Status: ' . $bb->getStatus() . ')' : 'NULL'));
        
        $value = isset($_POST['sln']) && isset($_POST['sln']['note']) ? sanitize_text_field(wp_unslash($_POST['sln']['note'])) : '';
        $plugin = $this->getPlugin();
        $isCreateAfterPay = /*$plugin->getSettings()->get('create_booking_after_pay') &&*/ $plugin->getSettings()->isPayEnabled();
        
        // CRITICAL: Check REQUEST instead of just GET to handle both URL params and POST form data
        // The booking ID can come from:
        // 1. POST: Hidden field <input name="sln_booking_id" value="123">
        // 2. GET:  URL parameter ?sln_booking_id=123
        if(isset($_REQUEST['sln_booking_id']) && intval($_REQUEST['sln_booking_id'])){
            $bookingId = intval(sanitize_text_field($_REQUEST['sln_booking_id']));
            SLN_Plugin::addLog('[SummaryStep] Found booking ID in request: ' . $bookingId);
            $bb = $plugin->createBooking($bookingId);
            if ($bb) {
                SLN_Plugin::addLog('[SummaryStep] ✅ Successfully loaded booking #' . $bookingId . ' - Status: ' . $bb->getStatus());
            } else {
                SLN_Plugin::addLog('[SummaryStep] ❌ FAILED to load booking #' . $bookingId);
            }
        }

        if(empty($bb) && isset($_GET['op'])){
            SLN_Plugin::addLog('[SummaryStep] Getting booking from op parameter: ' . intval(sanitize_text_field($_GET['op'])));
            $bb = $plugin->createBooking(explode('-', sanitize_text_field($_GET['op']))[1]);
        }

        // CRITICAL: Handle post-login scenario where lastId was lost but builder has data
        // This can happen after wp_signon() creates new session (blank page fix scenario)
        // The Nov 19 fix saves data to transient before login, Feb 5 enhances client_id handling
        // If getLastBooking() is NULL but builder HAS services data → create booking from builder
        // If getLastBooking() is NULL AND builder has NO data → show error (data truly lost)
        if(empty($bb)) {
            SLN_Plugin::addLog('[SummaryStep] LastBooking is NULL in dispatchForm - checking if builder has data');
            
            // Check both GET and POST for mode parameter (can be in either depending on form method)
            $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : 
                    (isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : null);
            
            // Check if BookingBuilder has services data (user completed booking flow)
            $hasServices = !empty($bookingBuilder->get('services'));
            
            SLN_Plugin::addLog(sprintf('[SummaryStep] Mode: %s, Has services: %s, Client ID: %s', 
                $mode ? $mode : 'NONE',
                $hasServices ? 'YES' : 'NO',
                isset($_REQUEST['sln_client_id']) ? $_REQUEST['sln_client_id'] : 'NONE'
            ));
            
            // If user is submitting form AND builder has services → create booking from builder data
            // This handles post-login scenario where lastId was lost but data preserved in transient
            if(!empty($mode) && $hasServices) {
                SLN_Plugin::addLog('[SummaryStep] POST-LOGIN SCENARIO: Creating booking from BookingBuilder data (lastId lost but data preserved)');
                try {
                    // Create booking with clear=false to preserve builder data for final confirmation
                    $bookingBuilder->create(SLN_Enum_BookingStatus::DRAFT, false);
                    $bb = $bookingBuilder->getLastBooking();
                    
                    if ($bb) {
                        SLN_Plugin::addLog('[SummaryStep] ✅ Booking created from builder data: ID #' . $bb->getId());
                    } else {
                        SLN_Plugin::addLog('[SummaryStep] ❌ FAILED to create booking from builder data');
                    }
                } catch (\Exception $e) {
                    SLN_Plugin::addLog('[SummaryStep] Exception creating booking: ' . $e->getMessage());
                }
            }
            
            // If still no booking, check why
            if(empty($bb)) {
                if (!$hasServices) {
                    // Builder has NO data → data was truly lost (Safari ITP issue not resolved by Feb 5 fixes)
                    SLN_Plugin::addLog("ERROR: Booking data was lost - BookingBuilder has no services");
                    SLN_Plugin::addLog("Session state: " . (isset($_SESSION) ? 'isset' : 'not set'));
                    SLN_Plugin::addLog("Session status: " . session_status());
                    SLN_Plugin::addLog("Client ID in request: " . (isset($_REQUEST['sln_client_id']) ? $_REQUEST['sln_client_id'] : 'NONE'));
                    SLN_Plugin::addLog("BookingBuilder storage mode: " . ($bookingBuilder->isUsingTransient() ? 'transient' : 'session'));
                    $this->addError(__('Booking data was lost. Please start the booking process again.', 'salon-booking-system'));
                } else {
                    // Builder HAS data but no mode → user just arrived at summary, not submitting yet
                    // This is normal - return false to let render() create the booking
                    SLN_Plugin::addLog('[SummaryStep] No booking yet, no mode parameter - returning false to let render() create booking');
                }
                return false;
            }
        }
        if(!empty($value)){
            $bb->setMeta('note', SLN_Func::filter($value));
        }
        $handler = new SLN_Action_Ajax_CheckDateAlt( $plugin );

        $paymentMethod = $plugin->getSettings()->isPayEnabled() ? SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) : false;
        // Check both GET and POST for mode parameter
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : 
                (isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : null);

        // CRITICAL DEBUG (Feb 10, 2026): Log all conditions for status determination
        SLN_Plugin::addLog(sprintf(
            '[SummaryStep] Status determination context - Mode: %s, Amount: %s, PayEnabled: %s, PayMethod: %s, Confirmation: %s',
            $mode ? $mode : 'NULL',
            $bb->getAmount(),
            $plugin->getSettings()->isPayEnabled() ? 'YES' : 'NO',
            empty($paymentMethod) ? 'EMPTY' : 'CONFIGURED',
            $plugin->getSettings()->get('confirmation') ? 'YES' : 'NO'
        ));

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
                
                // CRITICAL FIX (Feb 10, 2026): Correct status logic for zero-amount bookings
                // Zero-amount bookings should be CONFIRMED, not PAID
                // Enhanced with comprehensive logging to debug status issues
                
                $amount = $bb->getAmount();
                $confirmation = SLN_Plugin::getInstance()->getSettings()->get('confirmation');
                
                SLN_Plugin::addLog(sprintf(
                    '[SummaryStep] Status decision for DRAFT booking - Amount: %s, Confirmation: %s, PayMethod: %s',
                    $amount,
                    $confirmation ? 'YES' : 'NO',
                    empty($paymentMethod) ? 'EMPTY' : 'CONFIGURED'
                ));
                
                if($amount <= 0.0 && !$confirmation){
                    // Free booking, no confirmation required → CONFIRMED
                    $bb->setStatus(SLN_Enum_BookingStatus::CONFIRMED);
                    SLN_Plugin::addLog('[SummaryStep] Status set to CONFIRMED (zero amount, no confirmation)');
                } else if($amount <= 0.0 && $confirmation) {
                    // Free booking, but confirmation required → PENDING
                    $bb->setStatus(SLN_Enum_BookingStatus::PENDING);
                    SLN_Plugin::addLog('[SummaryStep] Status set to PENDING (zero amount, with confirmation)');
                } else if($confirmation) {
                    // Paid booking, confirmation required → PENDING
                    $bb->setStatus(SLN_Enum_BookingStatus::PENDING);
                    SLN_Plugin::addLog('[SummaryStep] Status set to PENDING (confirmation required)');
                } else if(empty($paymentMethod)) {
                    // No payment method configured → CONFIRMED
                    $bb->setStatus(SLN_Enum_BookingStatus::CONFIRMED);
                    SLN_Plugin::addLog('[SummaryStep] Status set to CONFIRMED (no payment method)');
                } else if($amount > 0.0) {
                    // Paid booking, payment completed → PAID
                    $bb->setStatus(SLN_Enum_BookingStatus::PAID);
                    SLN_Plugin::addLog('[SummaryStep] Status set to PAID (amount > 0, payment completed)');
                } else {
                    // Fallback for edge cases → CONFIRMED
                    $bb->setStatus(SLN_Enum_BookingStatus::CONFIRMED);
                    SLN_Plugin::addLog('[SummaryStep] Status set to CONFIRMED (fallback)');
                }
            }
            $bb->setPrepaidServices();
            $bookingBuilder->clear($bb->getId());
            $this->cleanupBookingLock($bb);
            return !$this->hasErrors();
        } elseif($mode == 'later'){
            SLN_Plugin::addLog(sprintf(
                '[SummaryStep] PAY LATER mode - Amount: %s, Current Status: %s',
                $bb->getAmount(),
                $bb->getStatus()
            ));
            
            $errors = $handler->checkDateTimeServicesAndAttendants($bb->getAttendantsIds(), $bb->getStartsAt());
            if(!empty($errors) && !class_exists('\\SalonMultishop\\Addon')){
                $this->addError(self::SLOT_UNAVAILABLE);
                return false;
            }
            if(in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::PENDING_PAYMENT, SLN_Enum_BookingStatus::DRAFT))){
                if($bb->getAmount() > 0.0){
                    $bb->setStatus(SLN_Enum_BookingStatus::PAY_LATER);
                    SLN_Plugin::addLog('[SummaryStep] PAY LATER - Status set to PAY_LATER (amount > 0)');
                }else{
                    // CRITICAL FIX (Feb 10, 2026): For zero-amount "Pay Later" bookings
                    // Set correct status BEFORE calling setPrepaidServices() to prevent override
                    $correctStatus = $bb->getCreateStatus();
                    SLN_Plugin::addLog(sprintf(
                        '[SummaryStep] PAY LATER - Zero amount, setting status to: %s (from getCreateStatus)',
                        $correctStatus
                    ));
                    $bb->setStatus($correctStatus);
                    $bb->setPrepaidServices();
                    
                    // DEFENSIVE: Verify status wasn't changed by setPrepaidServices
                    $finalStatus = $bb->getStatus();
                    if ($finalStatus !== $correctStatus) {
                        SLN_Plugin::addLog(sprintf(
                            '[SummaryStep] WARNING: Status changed after setPrepaidServices from %s to %s - reverting',
                            $correctStatus,
                            $finalStatus
                        ));
                        $bb->setStatus($correctStatus);
                    }
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
        SLN_Plugin::addLog('SummaryStep::render() - Start');
        SLN_Plugin::addLog('Has services: ' . ($bb->get('services') ? 'YES' : 'NO'));
        SLN_Plugin::addLog('Is valid: ' . ($bb->isValid() ? 'YES' : 'NO'));
        SLN_Plugin::addLog('Has last booking: ' . ($bb->getLastBooking() ? 'YES (ID: ' . $bb->getLastBooking()->getId() . ')' : 'NO'));
        if (!$bb->get('services')) {
            SLN_Plugin::addLog('Services data: ' . print_r($bb->get('services'), true));
        }
        
        // CRITICAL FIX (Feb 10, 2026): Ensure logged-in users have customer data loaded
        // Regression bug where logged-in users selecting "Pay Later" would create bookings
        // without customer data. This proactively loads their data from WordPress user profile.
        if (is_user_logged_in()) {
            $this->ensureLoggedInUserDataIsLoaded($bb);
        }
        
        // Check if we have services data - this is enough to display the summary
        // Full validation (isValid) is too strict for display - it checks availability
        // which can fail if time passed or slots got taken. Strict validation happens
        // in dispatchForm() when user submits the booking.
        if($bb->get('services')){
            SLN_Plugin::addLog('SummaryStep::render() - Path A: Has services data, rendering summary');
            
            // Check validation for logging purposes only
            $isValid = $bb->isValid();
            SLN_Plugin::addLog('SummaryStep::render() - Validation status: ' . ($isValid ? 'VALID' : 'INVALID (will validate again on submission)'));
            
            $data = $this->getViewData();
            
            // Only apply lock check if booking doesn't already exist
            // This prevents blocking the same user on subsequent renders
            $existingBooking = $bb->getLastBooking();
            
            // Lock check removed - was causing false positives
            // The system has other availability checks that prevent double-bookings

            do_action('sln.shortcode.summary.dispatchForm.before_booking_creation', $this, $bb);
            
            if ( ! $this->hasErrors() ) {
                SLN_Plugin::addLog('SummaryStep::render() - Creating booking in DRAFT status');
                // CRITICAL: Pass $clear = false to prevent wiping booking data after draft creation
                // The builder's data is needed for the final confirmation step
                // Explicit clear() calls after finalization (lines 157, 203, 215) still work as expected
                $bb->create(SLN_Enum_BookingStatus::DRAFT, false);
                
                // Pass BookingBuilder to extensions for backward compatibility
                // Extensions like SalonMultishop and Discount expect the builder object
                // They can call $bb->getLastBooking() themselves if they need the booking
                do_action('sln.shortcode.summary.dispatchForm.after_booking_creation', $bb);
            } else {
                SLN_Plugin::addLog('SummaryStep::render() - Has errors, not creating booking: ' . print_r($this->getErrors(), true));
            }
            return parent::render();
        }elseif($bb->getLastBooking()){
            SLN_Plugin::addLog('SummaryStep::render() - Path B: Has existing booking');
            $data = $this->getViewData();
            $bb = $bb->getLastBooking();
            
            $custom_url = apply_filters('sln.shortcode.render.custom_url', false, $this->getStep(), $this->getShortcode(), $bb);
            if ($custom_url) {
                SLN_Plugin::addLog('SummaryStep::render() - Redirecting to custom URL: ' . $custom_url);
                $this->redirect($custom_url);
                wp_die();
            }
            return parent::render();
        }else{
            SLN_Plugin::addLog('SummaryStep::render() - Path C: Fallback (ERROR PATH)');
            if(empty($bb->get('services'))){
                SLN_Plugin::addLog('SummaryStep::render() - ERROR: No services data, redirecting to services step');
                $this->addError(self::SERVICES_DATA_EMPTY);
                $this->redirect(add_query_arg(array('sln_step_page' => 'services')));
                return parent::render(); // Return content after redirect for AJAX
            }else{
                SLN_Plugin::addLog('SummaryStep::render() - ERROR: Slot unavailable');
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
     * Ensure logged-in user's customer data is loaded into BookingBuilder
     * 
     * CRITICAL FIX added Feb 10, 2026 to prevent regression bug where logged-in users
     * would create bookings without customer data when selecting "Pay Later"
     * 
     * This method proactively loads customer data from WordPress user profile if it's
     * missing from the BookingBuilder. This ensures the booking will always have customer
     * information regardless of how the user navigated through the booking flow.
     * 
     * @param SLN_Wrapper_Booking_Builder $bb The booking builder instance
     */
    private function ensureLoggedInUserDataIsLoaded($bb)
    {
        // Check if customer data is already present
        $hasCustomerData = !empty($bb->get('firstname')) || !empty($bb->get('email'));
        
        if ($hasCustomerData) {
            // Data already present, no action needed
            SLN_Plugin::addLog('[Summary Step] Customer data already present in BookingBuilder');
            return;
        }
        
        // Customer data missing - this is the bug scenario
        // Load data from logged-in user's WordPress profile
        SLN_Plugin::addLog('[Summary Step] WARNING: Customer data missing for logged-in user, loading from profile...');
        
        $loadedFields = array();
        $customer_fields = SLN_Enum_CheckoutFields::forRegistration()->appendSmsPrefix();
        
        foreach ($customer_fields as $key => $field) {
            $currentValue = $bb->get($key);
            
            // Only load if field is empty
            if (empty($currentValue)) {
                $value = $field->getValue(get_current_user_id());
                
                if (!empty($value)) {
                    $bb->set($key, $value);
                    $loadedFields[] = $key;
                }
            }
        }
        
        if (!empty($loadedFields)) {
            // Save the loaded data to ensure it persists
            $bb->save();
            
            $fieldsLoaded = implode(', ', $loadedFields);
            SLN_Plugin::addLog(sprintf(
                '[Summary Step] ✓ Loaded customer data from user profile: %s',
                $fieldsLoaded
            ));
            
            // Log user context for debugging
            SLN_Plugin::addLog(sprintf(
                '[Summary Step] User context - ID: %d, Session: %s, Storage: %s',
                get_current_user_id(),
                session_id(),
                $bb->isUsingTransient() ? 'transient' : 'session'
            ));
        } else {
            // This is a critical issue - logged-in user but no data in profile either
            SLN_Plugin::addLog('[Summary Step] ✗ ERROR: No customer data in user profile either');
        }
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
