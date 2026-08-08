<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

class SLN_Shortcode_Salon_ServicesStep extends SLN_Shortcode_Salon_AbstractUserStep
{
    private $services;

    /**
     * Keep the plain Step::isValid() behavior.
     *
     * AbstractUserStep::isValid() (the new parent, extended only to inherit
     * dispatchAuth() for the "Returning customer? Log in" tab) re-binds the
     * logged-in user's profile values onto the BookingBuilder on every
     * validation pass. On the services step that runs in the wizard's reversed
     * dispatch passes too, where it could overwrite checkout values the
     * customer edited later in the flow — so it is deliberately skipped here.
     */
    public function isValid()
    {
        return (isset($_POST['submit_' . $this->getStep()]) || isset($_GET['submit_' . $this->getStep()])) && $this->dispatchForm();
    }

    protected function dispatchForm()
    {
        // --- "Returning customer? Log in" tab ---------------------------------
        // Only treated as a login attempt when credentials were actually typed:
        // the services tab submit does not carry a login_name value.
        if (!is_user_logged_in() && !empty($_POST['login_name'])) {
            return $this->dispatchLoginTab();
        }

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
            // Only accept a quantity for services that actually allow it (variable
            // duration or the quantity feature), clamped to the service maximum.
            // This blocks count injection (price/duration multiplication) via a
            // tampered POST on services without the feature.
            $allowsCount = $service->isVariableDuration() || $service->isQuantityEnabled();
            if ($allowsCount && isset($countService[$service->getId()])) {
                $max   = $service->isVariableDuration() ? $service->getMaxVariableDuration() : $service->getMaxQuantity();
                $count = (int) $countService[$service->getId()];
                if ($count < 1) {
                    $count = 1;
                }
                if ($max > 0 && $count > $max) {
                    $count = $max;
                }
                $bb->addCountService($service->getId(), $count);
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
     * Authenticate from the services-step login tab, then restart the wizard.
     *
     * On success the customer is redirected to the booking page with no step
     * parameter: the wizard then resolves its default step naturally — the
     * forecast cards when the customer has booking history, or back to the
     * services step otherwise. Mirrors what the old forecast login screen did.
     *
     * @return bool false re-renders the services step with login errors.
     */
    private function dispatchLoginTab()
    {
        $username = sanitize_text_field(wp_unslash($_POST['login_name']));
        $password = isset($_POST['login_password']) ? wp_unslash($_POST['login_password']) : '';

        if (!$this->dispatchAuth($username, $password)) {
            return false;
        }

        // Bust the forecast cache so the cards are built from live availability
        // (covers both single-shop and current-shop cache keys).
        $shop_id = isset($_GET['shop']) ? (int) $_GET['shop'] : 0;
        SLN_Helper_BookingForecaster::bustCache(get_current_user_id(), $shop_id);

        // dispatchAuth() preserved the BookingBuilder via transient + client id;
        // propagate the client id in the redirect so the new session picks it up.
        $client_id = $this->getPlugin()->getBookingBuilder()->getClientId();

        $booking_page_id = $this->getPlugin()->getSettings()->getPayPageId();
        $base_url        = ($booking_page_id && get_post_status($booking_page_id))
            ? get_permalink($booking_page_id)
            : home_url('/');

        // Prefer the referer when it points at the same booking page, so custom
        // page setups (translated slugs, query-built pages) keep working.
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer      = wp_sanitize_redirect(wp_unslash($_SERVER['HTTP_REFERER']));
            $referer_base = strtok($referer, '?');
            if ($referer_base && (!$booking_page_id || untrailingslashit($referer_base) === untrailingslashit($base_url))) {
                $base_url = $referer_base;
            }
        }

        $redirect_url = add_query_arg(array('sln_client_id' => $client_id), $base_url);

        $this->redirect($redirect_url); // throws for AJAX requests
        exit;
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

            $this->services = $repo->sortByPosOrder($services);
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
