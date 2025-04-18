<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Shortcode_SalonMyAccount_ProfileUpdater{

	function __construct(SLN_Plugin $plugin){
		$this->plugin    = $plugin;
	}

    public function dispatchForm(){
        if( !isset( $_POST['slnUpdateProfileNonceField'] ) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['slnUpdateProfileNonceField'])), 'slnUpdateProfileNonce') )
            {
                $this->addError(__('Wrong Nonce.', 'salon-booking-system'));
                return array( "status" => "error", "errors"=> $this->getErrors());
            }

        return $this->process();
    }

	private function process(){

	    $values = $this->bindValues($_POST['sln']);
	    $this->validate($values);

	    if ($this->hasErrors()) {
		return array( "status" => "error", "errors"=> $this->getErrors());
	    }

	    $this->updateUser($values);
	    $this->updateUserMeta($values);

	    $last_update = get_user_meta(get_current_user_id(), '_sln_last_update', true);

	    return array(
		"status"	=> "success",
		'last_update'	=> $last_update ? sprintf(
            // translators: %1$s will be replaced by the date last update, %2$s will be replaced by the time last update
		    __('Last update on %1$s at %2$s', 'salon-booking-system'),
		    $this->plugin->format()->date((new SLN_DateTime())->setTimestamp($last_update)),
		    $this->plugin->format()->time((new SLN_DateTime())->setTimestamp($last_update))
		) : '',
	    );
	}

    private function updateUserMeta($values=array()){
        $user_meta_fields = SLN_Enum_CheckoutFields::forCustomer()->appendSmsPrefix()->keys();
        foreach($user_meta_fields as $k){
            if (SLN_Enum_CheckoutFields::getField($k) && SLN_Enum_CheckoutFields::getField($k)->get('type') === 'file' && isset($values[$k]) && is_array($values[$k])) {
                $data = array_map(function($file) {
                    return array(
                        'subdir' => wp_upload_dir()['subdir'],
                        'file'   => $file,
                    );
                }, $values[$k]);
                update_user_meta(get_current_user_id(), '_sln_'.$k, $data);
            }else{
                if(isset($values[$k])){
                    update_user_meta(get_current_user_id(), '_sln_'.$k, $values[$k]);
                }
            }
        }
    }

    private function updateUser($values=array()){
        if(array_intersect( array_keys($values),array('firstname','lastname','email') )){
             $current_user = wp_get_current_user();
             $userdata = [
                'ID' => $current_user->ID,
            ];
            if(isset($values['firstname'])){
                $userdata['first_name'] = $values['firstname'];
            }
            if(isset($values['lastname'])){
                $userdata['last_name'] = $values['lastname'];
            }

             if(!array_intersect(['administrator'],$current_user->roles)){
                $userdata = array_merge($userdata,[
                    'user_email' => $values['email'],
                    'nickname' => $values['email'],
                    'user_nicename' => $values['email'],
                    'display_name'=> $values['email'],
                ]);
             }
             if(!empty($values['password'])){
                $userdata['user_pass'] = $values['password'];
             }
             $updated = wp_update_user( $userdata );
             if ( is_wp_error( $updated ) ) {
                $this->addError(__('Something goes wrong', 'salon-booking-system'));
             }
            return $updated;
        }
    }

	private function validate($values){
        $fields = SLN_Enum_CheckoutFields::forCustomer();
        foreach ($fields as $key => $field) {
            if ($field->isRequired() && empty($values[$key]) ){
                $this->addError(esc_html__( $field['label'].' can\'t be empty', 'salon-booking-system'));

            }
            if (!empty($values['email']) && $key === 'email' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                   $this->addError(__('e-mail is not valid', 'salon-booking-system'));
            }
        }
        if ($this->hasErrors()) {
            return false;
        }
        $current_user = wp_get_current_user();
        if ($values['email'] !== $current_user->user_email && email_exists($values['email'])) {
            $this->addError(__('E-mail exists', 'salon-booking-system'));
            return false;

        }
        if ($values['password'] != $values['password_confirm']) {

            $this->addError(__('Passwords are different', 'salon-booking-system'));
            return false;

        }
    }

	protected function bindValues($values)
    {
        $fields = SLN_Enum_CheckoutFields::forCustomer()->appendPassword()->appendSmsPrefix()->keys();
        foreach ($fields as $field ) {
            if(isset($values[$field]) && is_array($values[$field])){
                foreach($values[$field] as $value){
                    $data[$field][] = sanitize_file_name($value);
                }
            }else{
                $data[$field] = isset($values[$field]) ? sanitize_text_field($values[$field]) : '';
            }
        }

        return $data;
    }

    protected function getPlugin()
    {
        return $this->plugin;
    }

    public function addError($err)
    {
        $this->errors[] = $err;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }
}