<?php

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

        return array(
            'formAction'        => add_query_arg(array('sln_step_page' => $this->shortcode->getCurrentStep()), SLN_Func::currPageUrl()),
            'backUrl'           => apply_filters('sln_shortcode_step_view_data_back_url', add_query_arg(array('sln_step_page' => $this->shortcode->getPrevStep())), $this->step),
            'submitName'        => 'submit_' . $step,
            'step'              => $this,
            'errors'            => $this->errors,
            'additional_errors' => array_merge($this->additional_errors, $rescheduledErrors),
            'settings'          => $this->plugin->getSettings(),
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
            if ($service->isAttendantsEnabled() && (!isset($booking_attendants[$sId]) || empty($booking_attendants[$sId]))) {
                $attendantsNeeds = true;
                break;
            }
            try{
                if($service->getCountMultipleAttendants() != count($booking_attendants[$sId])){
                    $attendantsNeeds = true;
                    break;
                }
            }catch(TypeError $e){
                if($service->getCountMultipleattendants() != 1){
                    $attendantsNeeds = true;
                    break;
                }
            }
        }

        if ( ! $attendantsNeeds ) {
            return true;
        }

        if ($this->getPlugin()->getSettings()->isMultipleAttendantsEnabled()) {

            $ids = array();

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

    public function getMixpanelTrackScript()
    {
        $event       = 'Front-end booking form';
        $currentStep = $this->getStep();
        $version     = defined('SLN_VERSION_PAY') && SLN_VERSION_PAY ? 'pro' : 'free';

        if ($currentStep === 'summary') {
            $currentStep = 'payment';
        }

        $style  = $this->getShortcode()->getStyleShortcode();
        $data   = array(
            'step'      => $currentStep,
            'version'   => $version,
            'layout'    => $style,
            'enviroment' => defined('SLN_VERSION_DEV') && SLN_VERSION_DEV ? 'dev' : 'live',
        );
        
        $script = SLN_Helper_Mixpanel_MixpanelWeb::trackScript($event, $data);

        return sprintf(
            "<script>%s</script>",
            $script
        );
    }

    public function isNeedTotal(){
        return false;
    }
}
