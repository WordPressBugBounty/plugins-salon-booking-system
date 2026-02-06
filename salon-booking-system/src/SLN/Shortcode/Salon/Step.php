<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
abstract class SLN_Shortcode_Salon_Step
{
    private $plugin;
    private $attrs;
    private $step;
    private $shortcode;
    private $errors = array();
    private $additional_errors = array();

    function __construct(SLN_Plugin $plugin, $shortcode, $step)
    {
        $this->plugin    = $plugin;
        $this->shortcode = $shortcode;
        $this->step      = $step;
    }

    public function isValid()
    {
        return (isset($_POST['submit_' . $this->getStep()]) || isset($_GET['submit_' . $this->getStep()])) && $this->dispatchForm();
    }

    public function render()
    {
        if($this instanceof SLN_Shortcode_Salon_AttendantStep){
            add_filter('sln.attendants.renderSortIcon', array($this, 'defaultRenderSortIcon'), 5, 3);
        }
        $bb = $this->getPlugin()->getBookingBuilder();
        $custom_url = apply_filters('sln.shortcode.render.custom_url', false, $this->getStep(), $this->getShortcode(), $bb);
        if ($custom_url) {
            $this->redirect($custom_url);
        } else {
            return $this->getPlugin()->loadView('shortcode/salon_' . $this->getStep(), $this->getViewData());
        }
    }

    protected function getViewData()
    {
        $step = $this->getStep();

	$rescheduledErrors = SLN_Action_RescheduleBooking::getErrors();

	SLN_Action_RescheduleBooking::clearErrors();

        // Check if this is the first step and validate session/cookie availability
        $sessionWarning = '';
        if ($this->isFirstStep()) {
            $sessionWarning = $this->checkSessionAndCookies();
        }

        return array(
            'formAction'        => add_query_arg(array('sln_step_page' => $this->shortcode->getCurrentStep()), SLN_Func::currPageUrl()),
            'backUrl'           => apply_filters('sln_shortcode_step_view_data_back_url', add_query_arg(array('sln_step_page' => $this->shortcode->getPrevStep())), $this->step),
            'submitName'        => 'submit_' . $step,
            'step'              => $this,
            'errors'            => $this->errors,
            'additional_errors' => array_merge($this->additional_errors, $rescheduledErrors),
            'settings'          => $this->plugin->getSettings(),
            'sessionWarning'    => $sessionWarning,
        );
    }

    public function getStep()
    {
        return $this->step;
    }

    /** @return SLN_Plugin */
    protected function getPlugin()
    {
        return $this->plugin;
    }

    public function getShortcode()
    {
        return $this->shortcode;
    }

    abstract protected function dispatchForm();

    public function addError($err)
    {
        $this->errors[] = $err;
    }

    public function addErrors($errors){
        $this->errors = array_merge($this->errors, $errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function addAdditionalError($err) {
        $this->additional_errors[] = $err;
    }

    public function getAddtitionalErrors() {
        return $this->additional_errors;
    }

    public function setAttendantsAuto() {

        if( ! $this->getPlugin()->getSettings()->isAttendantsEnabled() ) {
            return true;
        }

	    $attendantsNeeds = false;

        $bb = $this->getPlugin()->getBookingBuilder();

        $booking_attendants = $bb->getAttendantsIds();
        foreach ($bb->getServices() as $service) {
            $sId = $service->getId();
            if ($service->isAttendantsEnabled() && (!isset($booking_attendants[$sId]) || empty($booking_attendants[$sId]) || $booking_attendants[$sId] === false)) {
                $attendantsNeeds = true;
                break;
            }
            try{
                if($service->isMultipleAttendantsForServiceEnabled() && $service->getCountMultipleAttendants() != count($booking_attendants[$sId])){
                    $attendantsNeeds = true;
                    break;
                }
            }catch(TypeError $e){
                if($service->isMultipleAttendantsForServiceEnabled() && $service->getCountMultipleattendants() != 1){
                    $attendantsNeeds = true;
                    break;
                }
            }
        }

        if ( ! $attendantsNeeds ) {
            return true;
        }

        // SMART AVAILABILITY: When feature enabled, use direct assignment
        // This bypasses random attendant pre-selection and assigns based on actual availability
        if ($this->getPlugin()->getSettings()->isAutoAttendantCheckEnabled()) {
            try {
                $ah = $this->getPlugin()->getAvailabilityHelper();
                $ah->setDate($bb->getDateTime());
                $bookingServices = $bb->getBookingServices();
                
                // This method checks ALL available attendants and assigns appropriately
                $ah->addAttendantForServices($bookingServices);
                
                // Extract assigned attendant IDs and save to booking builder
                $assignedIds = array();
                foreach ($bookingServices->getItems() as $bookingService) {
                    $service = $bookingService->getService();
                    if ($service->isAttendantsEnabled()) {
                        $attendant = $bookingService->getAttendant();
                        if ($attendant) {
                            if (is_array($attendant)) {
                                $assignedIds[$service->getId()] = array_map(function($att) {
                                    return $att->getId();
                                }, $attendant);
                            } else {
                                $assignedIds[$service->getId()] = $attendant->getId();
                            }
                        }
                    } else {
                        $assignedIds[$service->getId()] = 0;
                    }
                }
                
                $bb->setServicesAndAttendants($assignedIds);
                $bb->save();
                
                return true;
            } catch (Exception $e) {
                $this->addError($e->getMessage());
                return false;
            }
        }

        // LEGACY BEHAVIOR: Use old random assignment flow
        if ($this->getPlugin()->getSettings()->isMultipleAttendantsEnabled()) {

            $ids = $bb->getAttendantsIds();
            foreach ($bb->getAttendantsIds() as $sId => $aId) {
                if($aId === 0)
                    $ids[$sId] = '';
            }

            $_POST['sln']['attendants'] = $ids;
        } else {
            $_POST['sln']['attendant'] = '';
        }

        $_POST['submit_attendant'] = 'next';
        $_POST['attendant_auto'] = true;

        $attendantStep = new SLN_Shortcode_Salon_AttendantStep($this->plugin, $this->getShortcode(), 'attendant');

        if ($attendantStep->isValid()) {
            return true;
        }
        $this->addErrors($attendantStep->getErrors());

        return false;
    }

    protected function validateMinimumOrderAmount() {

	$minimumOrderAmount = (float)$this->plugin->getSettings()->get('pay_minimum_order_amount');

	$bb = $this->plugin->getBookingBuilder();

	if ( ! empty( $minimumOrderAmount ) && $bb->getTotal() < $minimumOrderAmount ) {
	    $this->addError(sprintf(
        // translators: %s will be replaced by the minimum order amount
		__('The minimum order amount is %s', 'salon-booking-system'),
		$this->plugin->format()->moneyFormatted($minimumOrderAmount, false)
	    ));
	    return false;
	}

	return true;
    }

    public function redirect($url)
    {
        if ($this->isAjax()) {
            throw new SLN_Action_Ajax_RedirectException($url);
        } else {
            wp_redirect($url);
        }
    }

    protected function isAjax()
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    public function setResources() {

        $_POST['submit_resource'] = 'next';
        $_POST['set_resources'] = true;

        $resourceStep = (new SLN_Shortcode_Salon($this->getPlugin(), array()))->getStepObject('resource');

        if ($resourceStep->isValid()) {
            return true;
        }

        foreach ($resourceStep->getErrors() as $error) {
            $this->addAdditionalError($error);
        }

        return false;
    }

    public function isNeedTotal(){
        return false;
    }

    /**
     * Check if this is the first step in the booking flow
     * 
     * @return bool
     */
    protected function isFirstStep()
    {
        $steps = $this->shortcode->getSteps();
        if (empty($steps)) {
            return false;
        }
        $firstStep = reset($steps);
        return $this->getStep() === $firstStep;
    }

    /**
     * Check if sessions and cookies are working properly
     * Returns warning message if there are issues, empty string otherwise
     * 
     * @return string Warning message or empty string
     */
    protected function checkSessionAndCookies()
    {
        $warnings = array();

        // Check if PHP sessions are working
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $warnings[] = __('PHP sessions are not active. The booking process requires sessions to work properly.', 'salon-booking-system');
        }

        // Test if session data can be stored and retrieved
        $testKey = '_sln_session_test_' . time();
        $testValue = 'test_' . rand(1000, 9999);
        $_SESSION[$testKey] = $testValue;
        
        if (!isset($_SESSION[$testKey]) || $_SESSION[$testKey] !== $testValue) {
            $warnings[] = __('Session data cannot be stored. Please enable cookies and sessions in your browser.', 'salon-booking-system');
        } else {
            // Clean up test session
            unset($_SESSION[$testKey]);
        }

        // Check if booking builder session data exists (for subsequent visits)
        // If user has been through steps before, session should have data
        $bb = $this->getPlugin()->getBookingBuilder();
        $sessionData = $bb->get('services');
        
        // Only warn about cookies on the very first load (no session data yet)
        // We'll use JavaScript to detect cookie support
        if (empty($sessionData)) {
            // Add JavaScript-based cookie check (will be handled by the view)
            $warnings[] = 'CHECK_COOKIES_JS';
        }

        if (!empty($warnings)) {
            // Build user-friendly message with admin contact info
            $admin_email = get_option('admin_email');
            $message = implode(' ', array_filter($warnings, function($w) { return $w !== 'CHECK_COOKIES_JS'; }));
            
            if (!empty($message)) {
                // Add helpful instructions and admin contact
                $message .= ' ' . sprintf(
                    // translators: %s is the website administrator email address
                    __('If the problem persists after enabling cookies and refreshing this page, please report this issue to the website administrator at %s', 'salon-booking-system'),
                    '<a href="mailto:' . esc_attr($admin_email) . '">' . esc_html($admin_email) . '</a>'
                );
            }
            
            return $message;
        }

        return '';
    }
}
