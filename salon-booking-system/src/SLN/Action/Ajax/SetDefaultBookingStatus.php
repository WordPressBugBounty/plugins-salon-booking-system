<?php // algolplus

class SLN_Action_Ajax_SetDefaultBookingStatus extends SLN_Action_Ajax_Abstract
{
	private $errors = array();

	public function execute()
	{
		if (!is_user_logged_in()) {
			return array( 'redirect' => wp_login_url());
		}

		if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'ajax_post_validation')) {
			return array('errors' => array(__('Invalid security token. Please refresh the page and try again.', 'salon-booking-system')));
		}

		if (!current_user_can('manage_salon')) {
			return array('errors' => array(__('You do not have permission to perform this action.', 'salon-booking-system')));
		}
        
        $settings = SLN_Plugin::getInstance()->getSettings();
        if (!isset($_POST['status'])){
            return array('success' => 0);
        }
		$settings->setDefaultBookingStatus(wp_unslash($_POST['status']));
        $settings->save();

		return array('success' => 1);
	}
}
