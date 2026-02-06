<?php class SLN_Admin_SettingTabs_DocumentationTab extends SLN_Admin_SettingTabs_AbstractTab{
	protected $fields = array(
		'debug',
		'enable_debug_logs',
		'enable_sln_worker_role'
	);
	
	function __construct($slug, $label, $plugin) {
		parent::__construct($slug, $label, $plugin);
		
		// Sync the enable_debug_logs setting with the sln_debug_enabled option
		$debugEnabled = get_option('sln_debug_enabled', '0');
		if ($debugEnabled !== $this->settings->get('enable_debug_logs')) {
			$this->settings->set('enable_debug_logs', $debugEnabled);
			$this->settings->save();
		}
	}

    protected function postProcess() {

        if (isset($this->submitted['enable_sln_worker_role']) && $this->submitted['enable_sln_worker_role']) {
            SLN_UserRole_SalonWorker::addRole();
        } else {
            SLN_UserRole_SalonWorker::removeRole();
        }
        
        // Update the debug logs option
        if (isset($this->submitted['enable_debug_logs'])) {
            update_option('sln_debug_enabled', $this->submitted['enable_debug_logs'] ? '1' : '0');
        } else {
            update_option('sln_debug_enabled', '0');
        }
    }
} ?>