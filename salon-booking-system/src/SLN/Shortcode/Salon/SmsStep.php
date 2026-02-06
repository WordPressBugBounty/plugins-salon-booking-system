<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Shortcode_Salon_SmsStep extends SLN_Shortcode_Salon_AbstractUserStep
{
    public function render()
    {
        if (!isset($_SESSION['sln_sms_tests'])) {
            $_SESSION['sln_sms_tests'] = 0;
        }
        $tests = intval($_SESSION['sln_sms_tests']);
        $valid = isset($_SESSION['sln_sms_valid']) ? $_SESSION['sln_sms_valid'] : false;

        if (!$valid) {
            if (!isset($_POST['sln_verification'])) {
                // Check phone from multiple sources
                $values = isset($_SESSION['sln_detail_step']) ? $_SESSION['sln_detail_step'] : array();
                $phone = isset($values['phone']) ? $values['phone'] : '';
                
                // If not in session, check logged-in user's phone
                if (empty($phone) && is_user_logged_in()) {
                    $user_id = get_current_user_id();
                    $phone = get_user_meta($user_id, '_sln_phone', true);
                }
                
                // Get booking builder for phone and SMS prefix
                $bb = $this->getPlugin()->getBookingBuilder();
                
                // If still empty, check booking builder
                if (empty($phone)) {
                    $phone = $bb->get('phone');
                }
                
                if (!empty($phone)) {
                    $_SESSION['sln_sms_tests']++;
                    $_SESSION['sln_sms_code'] = rand(0, 999999);
                    try {
                        $sms_prefix = isset($values['sms_prefix']) ? $values['sms_prefix'] : 
                                     ($bb->get('sms_prefix') ?? '');
                        $this->sendSms($phone, $_SESSION['sln_sms_code'], $sms_prefix);
                    } catch (Exception $e) {
                        $this->addError($e->getMessage());
                    }
                } else {
                    $this->addError(
                        __(
                            'Phone number wrong or not defined, you need to define a valid phone number',
                            'salon-booking-system'
                        )
                    );
                }
            }
        }

        return parent::render();
    }

    private function sendSms($phone, $code, $sms_prefix = '')
    {
        $p = $this->getPlugin();
        $sms = $p->sms();
        $sms->send($phone, $p->loadView('sms/verify', compact('code')), $sms_prefix);
        if ($sms->hasError()) {
            $this->addError($sms->getError());
        }
    }

    protected function dispatchForm()
    {
        $values = isset($_SESSION['sln_detail_step']) ? $_SESSION['sln_detail_step'] : array();
        $valid = isset($_SESSION['sln_sms_valid']) ? $_SESSION['sln_sms_valid'] : false;

        if (!$valid) {
            if (isset($_POST['sln_verification'])) {
                if ($_POST['sln_verification'] == $_SESSION['sln_sms_code']) {
                    $_SESSION['sln_sms_valid'] = true;

		    if (!empty($_SESSION['sln_detail_step_need_register_user'])) {
			unset($_SESSION['sln_detail_step_need_register_user']);
			if ($this->successRegistration($values) === false) {
			    return false;
			}
		    }

                    return true;
                } else {
                    $_SESSION['sln_sms_valid'] = false;
                    $this->addError(__('Your verification code is not valid', 'salon-booking-system'));

                    return false;
                }
            }

	    return false;
        }

	if (!empty($_SESSION['sln_detail_step_need_register_user'])) {
	    unset($_SESSION['sln_detail_step_need_register_user']);
	    if ($this->successRegistration($values) === false) {
		return false;
	    }
	}

	return true;
    }
    public function getTitleKey(){
        return 'SMS';
    }
    public function getTitleLabel(){
        return __('SMS', 'salon-booking-system');
    }
    public function isValid() {
        // Skip SMS step if already verified
        $already_verified = isset($_SESSION['sln_sms_valid']) && $_SESSION['sln_sms_valid'];
        
        // Skip SMS step if explicitly told to skip (e.g., after fbphone step)
        $dont_check = isset($_SESSION['sln_sms_dontcheck']) && $_SESSION['sln_sms_dontcheck'];
        unset($_SESSION['sln_sms_dontcheck']);

        // Skip if verified or told to skip, otherwise validate normally
        return $already_verified || $dont_check || parent::isValid();
    }
}
