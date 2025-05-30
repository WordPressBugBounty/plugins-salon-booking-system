<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Shortcode_Salon
{
    const NAME = 'salon_booking';
    const STEP_KEY = 'sln_step_page';
    const STEP_DEFAULT = 'date';

    private $plugin;
    private $attrs;

    private $steps;
    private $currentStep;

    function __construct(SLN_Plugin $plugin, $attrs)
    {
        $this->plugin = $plugin;
        $this->attrs = $attrs;
    }

    public static function init(SLN_Plugin $plugin)
    {
        add_shortcode(self::NAME, array(__CLASS__, 'create'));
    }

    public static function create($attrs)
    {
        SLN_TimeFunc::startRealTimezone();
        SLN_Action_InitScripts::preloadEnqueueScript();

        $obj = new self(SLN_Plugin::getInstance(), $attrs);

        $ret = $obj->execute();

        SLN_TimeFunc::endRealTimezone();

        return $ret;
    }

    public function execute()
    {
        return $this->dispatchStep($this->getCurrentStep());
    }

    private function dispatchStep($curr)
    {
        $found = false;
        $settings = $this->plugin->getSettings();

        $steps = $this->maybeReverseSteps($this->getSteps());
        $stepsList = $this->maybeReverseSteps($this->getStepsList());
        foreach ($steps as $step) {
            if ($curr == $step || $found) {
                $found = true;
                $this->currentStep = $step;
                $obj = $this->getStepObject($step);
                if (!$obj->isValid()) {
                    return $this->render($obj->render());
                }

                if (!$settings->isFormStepsAltOrder()) {
                    if ($step === 'details' && $curr === 'details') {
                        if (!$obj->setResources()) {
                            return $this->render($obj->render());
                        }
                        if ($settings->isAttendantsEnabled() && !$obj->setAttendantsAuto()) {
                            return $this->render($obj->render());
                        }
                    }
                }else{
                    if ($step === 'date') {
                        if (!$obj->setResources()) {
                            return $this->render($obj->render());
                        }
                        if ($settings->isAttendantsEnabled() && !$obj->setAttendantsAuto()) {
                            return $this->render($obj->render());
                        }
                    }
                }
            }
        }
        if (!$found) {
            $index = array_search($curr, $stepsList);
            if ($index !== false) {
                $_stepsList = array_slice($stepsList, $index);
                foreach ($steps as $step) {
                    foreach ($_stepsList as $_step) {
                        if ($step == $_step || $found) {
                            $found = true;
                            $this->currentStep = $_step;
                            $obj = $this->getStepObject($_step);
                            if (!$obj->isValid()) {
                                return $this->render($obj->render());
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $step
     * @return SLN_Shortcode_Salon_Step
     * @throws Exception
     */
    public function getStepObject($step)
    {
        $obj = apply_filters('sln.shortcode_salon.getStepObject', null, $this, $step);
        if($obj)
            return $obj;

        $class = __CLASS__.'_'.ucwords($step).'Step';
        $class_alt = __CLASS__.'_'.ucwords($step).'AltStep';

        if ($this->plugin->getSettings()->isFormStepsAltOrder() && class_exists($class_alt)) {
            $obj = new $class_alt($this->plugin, $this, $step);
        }
        else {
            $obj = new $class($this->plugin, $this, $step);
        }
        return $obj;
    }

    protected function render($content)
    {
        $salon = $this;
        $step = $this->getStepObject($this->getCurrentStep());
        $mixpanelTrackScript = $step->getMixpanelTrackScript();
            return $this->plugin->loadView('shortcode/salon', compact('content', 'salon', 'mixpanelTrackScript'));
        }


    public function getCurrentStep()
    {
        if (!isset($this->currentStep)) {
            $steps = $this->getSteps();
            $stepDefault = array_shift($steps);

            if (!$stepDefault) {
                $stepDefault = self::STEP_DEFAULT;
            }
            $this->currentStep = isset($_GET[self::STEP_KEY]) ? sanitize_text_field(wp_unslash($_GET[self::STEP_KEY])) : $stepDefault;
            unset($steps);
        }

        return $this->currentStep;
    }

    public function getPrevStep()
    {
	//fix sms step after login
	if (isset($this->steps)) {
	    $this->steps = $this->initSteps();
	}

        $curr = $this->getCurrentStep();
        $prev = null;
        foreach ($this->getSteps() as $step) {
            if ($curr == $step) {
                return $prev;
            } else {
                $prev = $step;
            }
        }
    }

    private function needServices()
    {
        /** @var SLN_Repository_ServiceRepository $repo */
        $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
        $primary_services = array_filter($repo->getAllPrimary(), function($service) {
            return !$service->isHideOnFrontend();
        });

        if(count($primary_services) === 1) {
            $bb = $this->plugin->getBookingBuilder();
            $bb->addService(reset($primary_services));
            $bb->save();
            return false;
        }
        return true;
    }

    private function needSecondary()
    {
        /** @var SLN_Repository_ServiceRepository $repo */
        $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
        foreach ($repo->getAll() as $service) {
            if ($service->isSecondary() && !$service->isHideOnFrontend()) {
                return true;
            }
        }
    }

    private function needPayment()
    {
        return true;
    }

    private function needAttendant()
    {
        if(!$this->plugin->getSettings()->isAttendantsEnabled()) {
            return false;
        }

        if($this->plugin->getSettings()->isAttendantsEnabledOnlyBackend()) {
            return false;
        }

        $bb = $this->plugin->getBookingBuilder();

        if (!empty($bb->getServices())){
            foreach ($bb->getServices() as $service) {
                if ($service->isAttendantsEnabled()) {
                    return true;
                }
            }
        } else {
            return true;
        }


        return false;
    }

    public function needSms()
    {
        return (
            $this->plugin->getSettings()->get('sms_enabled')
            && !is_user_logged_in()
            && SLN_Enum_CheckoutFields::getField('phone')->isRequiredNotHidden()
        ) ? true : false;
    }

    public function getSteps()
    {
        if (!isset($this->steps)) {
            $this->steps = $this->initSteps();
        }

        return $this->steps;
    }

    protected function initSteps()
    {
        $ret = array(
            'date',
            'services',
            'secondary',
            'attendant',
            'details',
            'fbphone',
            'sms',
            'summary',
            'thankyou',
        );

        if ($this->plugin->getSettings()->isFormStepsAltOrder()) {
            $ret = array(
                'services',
                'secondary',
                'attendant',
                'date',
                'details',
                'fbphone',
                'sms',
                'summary',
                'thankyou',
            );
        }

        if (!$this->needSecondary()) {
            unset($ret[array_search('secondary', $ret)]);
            if(!$this->needServices()) {
                unset($ret[array_search('services', $ret)]);
            }
        }

        if (!$this->needPayment()) {
            unset($ret[array_search('thankyou', $ret)]);
        }
        if (!$this->needAttendant()) {
            unset($ret[array_search('attendant', $ret)]);
        }
        if (!$this->needSms()) {
            unset($ret[array_search('sms', $ret)]);
        }

        return apply_filters('sln.shortcode_salon.initSteps', $ret, $this->attrs);
    }

    protected function maybeReverseSteps($steps) {
        if (!(isset($_GET['submit_'.$this->getCurrentStep()]) && $_GET['submit_'.$this->getCurrentStep()] === 'next' ||
            isset($_POST['submit_'.$this->getCurrentStep()]) && $_POST['submit_'.$this->getCurrentStep()] === 'next')) {
            $steps = array_reverse($steps);
        }

        return $steps;
    }

    public function getStyleShortcode()
    {
        return isset($this->attrs['style']) ?
            $this->attrs['style']
            : $this->plugin->getSettings()->getStyleShortcode();
    }

    protected function getStepsList()
    {
        $ret = array(
            'date',
            'services',
            'secondary',
            'attendant',
            'details',
            'fbphone',
            'sms',
            'summary',
            'thankyou',
        );

        if ($this->plugin->getSettings()->isFormStepsAltOrder()) {
            $ret = array(
                'services',
                'secondary',
                'attendant',
                'date',
                'details',
                'fbphone',
                'sms',
                'summary',
                'thankyou',
            );
        }

        return apply_filters('sln.shortcode_salon.initSteps', $ret, $this->attrs);
    }
}
