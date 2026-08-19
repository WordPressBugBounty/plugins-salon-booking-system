<?php

class SLN_Action_Ajax_UpdateProfile extends SLN_Action_Ajax_Abstract
{
    public function execute(){
    	
    	if (!is_user_logged_in()) {
			return array( 'redirect' => wp_login_url());
		}

		if (!$this->isValidSalonAjaxNonce()) {
			return array(
				'status' => 'error',
				'errors' => array(__('Invalid security token. Please refresh the page and try again.', 'salon-booking-system')),
			);
		}
		
    	
    	$updater = new SLN_Shortcode_SalonMyAccount_ProfileUpdater($this->plugin);
    	
            $result = $updater->dispatchForm();
            return $result;
    	
    }
}
