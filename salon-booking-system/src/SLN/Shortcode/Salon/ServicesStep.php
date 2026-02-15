<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

class SLN_Shortcode_Salon_ServicesStep extends SLN_Shortcode_Salon_Step
{
    private $services;

    protected function dispatchForm()
    {
        $bb = $this->getPlugin()->getBookingBuilder();
        
        // DEBUG: Log received POST data for services
        SLN_Plugin::addLog('[ServicesStep] RAW $_REQUEST sln data: ' . print_r(isset($_REQUEST['sln']) ? $_REQUEST['sln'] : 'NOT SET', true));
        
        $values = isset($_REQUEST['sln']) && isset($_REQUEST['sln']['services']) && is_array($_REQUEST['sln']['services'])  ? $_REQUEST['sln']['services'] : array();
        $timezone = isset($_REQUEST['sln']['customer_timezone']) ? SLN_Func::filter(sanitize_text_field( wp_unslash( $_REQUEST['sln']['customer_timezone']  ) ), '') : '';
        $countService = isset($_REQUEST['sln']) && isset($_REQUEST['sln']['service_count']) && is_array($_REQUEST['sln']['service_count'])  ? $_REQUEST['sln']['service_count'] : array();
        
        // DEBUG: Log parsed services
        SLN_Plugin::addLog('[ServicesStep] Parsed services: ' . print_r($values, true));
        foreach ($this->getServices() as $service) {
            if (isset($values) && isset($values[$service->getId()])) {
                $bb->addService($service);
            } else {
                $bb->removeService($service);
            }
            if (isset($countService) && isset($countService[$service->getId()])) {
                $bb->addCountService($service->getId(), $countService[$service->getId()]);
            } else {
                $bb->removeCountService($service->getId());
            }
        }
        $bb->setCustomerTimezone($timezone);
        $bb->save();
        if(isset($_GET['sln']) && !isset($_GET['skip_service_selection'])){
            return false;
        }

        if (empty($values)) {
            $this->addError(__('You must choose at least one service', 'salon-booking-system'));

            return false;
        }

	if ( ! in_array('secondary', $this->getShortcode()->getSteps()) && ! $this->validateMinimumOrderAmount() ) {
	    return false;
	}

        return true;
    }

    /**
     * @return SLN_Wrapper_Service[]
     */
    public function getServices()
    {
        if (!isset($this->services)) {
            /** @var SLN_Repository_ServiceRepository $repo */
            $repo = $this->getPlugin()->getRepository(SLN_Plugin::POST_TYPE_SERVICE);

	    $services = $repo->getAllPrimary();

	    $services = array_filter($services, function ($service) {
		return !$service->isHideOnFrontend();
	    });

            $this->services = $repo->sortByExecAndTitleDESC($services);
            $this->services = apply_filters('sln.shortcode.salon.ServicesStep.getServices', $this->services);
        }

        return $this->services;
    }

    public function getTitleKey(){
        return 'What do you need?';
    }

    public function getTitleLabel(){
        return __('What do you need?', 'salon-booking-system');
    }

    public function isNeedTotal(){
        return $this->getPlugin()->getSettings()->get('hide_prices') != '1';
    }

}
