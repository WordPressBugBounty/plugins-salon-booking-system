<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

class SLN_Admin_SettingTabs_GcalendarTab extends SLN_Admin_SettingTabs_AbstractTab
{
	protected $fields = array(
        'google_calendar_enabled',
        'google_outh2_client_id',
        'google_outh2_client_secret',
        'google_outh2_redirect_uri',
        'google_client_calendar',
        'google_calendar_publish_pending_payment',
        'google_calendar_lock_slots',
    );

	/** @var bool Captured in validate() before save, consumed in postProcess(). */
	protected $pending_revoke = false;

	protected function validate(){
        // Compare submitted values to the still-unsaved settings. After
        // saveSettings() the old/new check would no longer see a change.
        $this->pending_revoke = $this->needsGCalendarRevokeToken();
	}

	protected function postProcess()
	{
        if ( ! $this->pending_revoke ) {
            return;
        }

        wp_safe_redirect(add_query_arg(
            '_wpnonce',
            wp_create_nonce('google_calendar'),
            admin_url('admin.php?page=salon-settings&tab=gcalendar&revoketoken=1')
        ));
        exit;
	}
	
	protected function needsGCalendarRevokeToken()
    {
        
        $s = $this->submitted;
        $ret = false;
        $keys = array('google_calendar_enabled','google_outh2_client_id','google_outh2_client_secret');
        foreach ($keys as $k) {
        	$old = $this->settings->get($k);
        	if(isset($s[$k]) && $old != $s[$k]){
        		$ret = true;
        		break;
        	}
        }

        return $ret;
    }
}
 ?>