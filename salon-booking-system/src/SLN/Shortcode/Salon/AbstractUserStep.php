<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
abstract class SLN_Shortcode_Salon_AbstractUserStep extends SLN_Shortcode_Salon_Step
{
    protected function successRegistration($values){
        SLN_Plugin::addLog("Attempting user registration for email: " . (isset($values['email']) ? $values['email'] : 'unknown'));
        
        $errors = wp_create_user($values['email'], $values['password'], $values['email']);
        if (is_wp_error($errors)) {
            SLN_Plugin::addLog("ERROR: User registration failed: " . $errors->get_error_message());
            $this->addError($errors->get_error_message());
	    return false;
        }
        
        SLN_Plugin::addLog("User created successfully with ID: " . $errors);
        
        $update = [
            'ID' => $errors,
            'role' => SLN_Plugin::USER_ROLE_CUSTOMER,
        ];
        if(isset($values['firstname'])){
            $update['first_name'] = $values['firstname'];
        }
        if(isset($values['lastname'])){
            $update['last_name'] = $values['lastname'];
        }
        
        $update_result = wp_update_user($update);
        if (is_wp_error($update_result)) {
            SLN_Plugin::addLog("WARNING: User update failed: " . $update_result->get_error_message());
        }
        
        $additional_fields = SLN_Enum_CheckoutFields::forRegistration()->appendSmsPrefix()->keys();
        foreach($additional_fields as $k){
            if(in_array($k,['firstname','lastname', 'sms_prefix'])) continue;
            if (SLN_Enum_CheckoutFields::getField($k) && SLN_Enum_CheckoutFields::getField($k)->get('type') === 'file' && isset($values[$k]) && is_array($values[$k])) {
                $data = array_map(function($file) {
                    return array(
                        'subdir' => wp_upload_dir()['subdir'],
                        'file'   => $file,
                    );
                }, $values[$k]);
                update_user_meta($errors, '_sln_'.$k, $data);
            } else {
                if(isset($values[$k])){
                   update_user_meta($errors, '_sln_'.$k, $values[$k]);
                }
            }
        }
        
        if (!$this->getPlugin()->getSettings()->isDisableNewUserWelcomeEmail()) {
            try {
                wp_new_user_notification($errors, null, 'both');
                SLN_Plugin::addLog("Welcome email sent successfully for user ID: " . $errors);
            } catch (Exception $e) {
                SLN_Plugin::addLog("WARNING: Welcome email failed: " . $e->getMessage());
            }
        }

	do_action('sln.shortcode.details.successRegistration.after_create_user', $errors, $values, $this);
        if (!$this->dispatchAuth($values['email'], $values['password'])) {
            $this->bindValues($values);
            return false;
        }
    }

    protected function dispatchAuth($username, $password)
    {
        if(empty($username)){
            $this->addError(__('username can\'t be empty', 'salon-booking-system'));
        }
        if(empty($password)){
            $this->addError(__('password can\'t be empty', 'salon-booking-system'));
        }
        if(empty($username) || empty($password)){
            return;
        }
        
        // CRITICAL FIX: Preserve booking data before login (Safari/Edge compatibility)
        // WordPress login creates a NEW session with new PHPSESSID
        // This causes booking data stored in old session to become inaccessible
        // Solution: Force save to transient BEFORE login, restore AFTER
        // Fixes "blank page with 0" issue after customer login
        $bb = $this->getPlugin()->getBookingBuilder();
        $clientId = $bb->getClientId();
        
        if ($clientId) {
            // Save current booking data to transient using client_id
            // This preserves data even when session ID changes
            // Force switch to transient storage to persist data across session change
            $bb->forceTransientStorage();
            
            SLN_Plugin::addLog(sprintf('[Login] Saved booking data to transient with client_id: %s', $clientId));
        }
        
        global $user;
        $creds                  = array();
        $creds['user_login']    = $username;
        $creds['user_password'] = $password;
        $creds['remember']      = true;
        $user                   = wp_signon($creds, false);

        if (is_wp_error($user)) {
            $this->addError($user->get_error_message());

            return false;
        }else{
            wp_set_current_user($user->ID);
        }
        
        // AFTER login: Ensure client_id is available in new session context
        // This allows BookingBuilder to retrieve data from transient
        if ($clientId) {
            $_GET['sln_client_id'] = $clientId;
            $_POST['sln_client_id'] = $clientId;
            
            SLN_Plugin::addLog(sprintf('[Login] Login successful, client_id preserved: %s', $clientId));
        }

        return true;
    }

    public function isValid()
    {
        if ( is_user_logged_in()) {
            global $current_user;
            wp_get_current_user();
            $customer_fields = SLN_Enum_CheckoutFields::forRegistration()->appendSmsPrefix();
            if($customer_fields){
                foreach ($customer_fields as $key => $field ) {
                    $values[$key] = $field->getValue(get_current_user_id());
                }
            }
            $this->bindValues($values);
        }

        return parent::isValid();
    }

    public function unique_filename($path, $filename){
        return get_current_user_id(). '_'. (new DateTime())->getTimestamp(). '_'. $filename;
    }

    protected function bindValues($values)
    {
        $bb     = $this->getPlugin()->getBookingBuilder();
        $fields = SLN_Enum_CheckoutFields::forDetailsStep()->appendSmsPrefix();
        $fields['no_user_account'] = '';
        foreach ($fields as $fieldName => $field ) {
            $data = isset($values[$fieldName]) ? $values[$fieldName] : '';
            $filter = '';
            if (!empty($field) && $field->get('type') === 'file' && is_array($data)) {
                $data = array_map(function($file) {
                    return array(
                        'subdir' => wp_upload_dir()['subdir'],
                        'file'   => $file,
                    );
                }, $data);
            }
            $bb->set($fieldName, SLN_Func::filter($data, $filter));
        }

        $bb->save();
    }
}
