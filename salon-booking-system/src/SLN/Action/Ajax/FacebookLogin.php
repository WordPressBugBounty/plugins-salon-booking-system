<?php
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
class SLN_Action_Ajax_FacebookLogin extends SLN_Action_Ajax_Abstract
{
	protected $errors = array();

	public function execute()
	{
	    try {

		$accessToken = isset($_POST['accessToken']) ? $_POST['accessToken'] : null;
		$userID	     = SLN_Helper_FacebookLogin::getUserIDByAccessToken($accessToken);

		// CRITICAL FIX: Preserve booking data before Facebook login
		// WordPress login creates a NEW session with new PHPSESSID
		// This causes booking data stored in old session to become inaccessible
		// Solution: Force save to transient BEFORE login, restore AFTER
		// Fixes "blank page with 0" issue after Facebook login
		$bb = SLN_Plugin::getInstance()->getBookingBuilder();
		$clientId = $bb->getClientId();
		
		// Debug: Log state BEFORE Facebook login
		$isDebugMode = (isset($_GET['sln_debug']) && $_GET['sln_debug'] === '1') || 
		               (isset($_POST['sln_debug']) && $_POST['sln_debug'] === '1');
		if ($isDebugMode) {
		    $oldSessionId = session_id();
		    if (empty($oldSessionId)) {
		        @session_start();
		        $oldSessionId = session_id();
		    }
		    $oldBuilderId = spl_object_id($bb);
		    
		    SLN_Plugin::addLog(sprintf('[SLN DEBUG] BEFORE FB LOGIN: session_id=%s, client_id=%s, bb_object_id=%s', 
		        $oldSessionId, $clientId, $oldBuilderId));
		}
		
		if ($clientId) {
		    // Save current booking data to transient using client_id
		    // This preserves data even when session ID changes
		    $bb->forceTransientStorage();
		    
		    SLN_Plugin::addLog(sprintf('[FB Login] Saved booking data to transient with client_id: %s', $clientId));
		}

		//login
		$user = get_user_by('id', (int)$userID);
		wp_set_auth_cookie($user->ID, false);
		do_action('wp_login', $user->user_login, $user);
		
		// AFTER login: Ensure client_id is available in new session context
		if ($clientId) {
		    $_GET['sln_client_id'] = $clientId;
		    $_POST['sln_client_id'] = $clientId;
		    
		    // Debug: Log state AFTER Facebook login, BEFORE reset
		    if ($isDebugMode) {
		        $newSessionId = session_id();
		        if (empty($newSessionId)) {
		            @session_start();
		            $newSessionId = session_id();
		        }
		        SLN_Plugin::addLog(sprintf('[SLN DEBUG] AFTER FB LOGIN (before reset): new_session_id=%s, client_id_in_GET=%s', 
		            $newSessionId, 
		            isset($_GET['sln_client_id']) ? $_GET['sln_client_id'] : 'null'));
		    }
		    
		    // Force BookingBuilder to be recreated with new client_id
		    SLN_Plugin::getInstance()->resetBookingBuilder();
		    
		    // Debug: Verify reset worked
		    if ($isDebugMode) {
		        $newBb = SLN_Plugin::getInstance()->getBookingBuilder();
		        $newBuilderId = spl_object_id($newBb);
		        $newClientId = $newBb->getClientId();
		        
		        SLN_Plugin::addLog(sprintf('[SLN DEBUG] AFTER FB RESET: new_bb_object_id=%s, new_client_id=%s, data_count=%d', 
		            $newBuilderId, $newClientId, count($newBb->getData())));
		    }
		    
		    SLN_Plugin::addLog(sprintf('[FB Login] Login successful, client_id preserved and BookingBuilder reset: %s', $clientId));
		}

	    } catch (\Exception $ex) {
		$this->addError($ex->getMessage());
	    }

	    if ( ($errors = $this->getErrors()) ) {
		$ret = compact('errors');
	    } else {
		$ret = array('success' => 1);
		
		// Include client_id in response for JavaScript to sync
		if (isset($clientId) && $clientId) {
		    $ret['client_id'] = $clientId;
		}
		
		// Include debug information if debug mode is enabled
		if ($isDebugMode) {
		    $bb = SLN_Plugin::getInstance()->getBookingBuilder();
		    $ret['debug'] = array(
		        'timestamp' => current_time('mysql'),
		        'action' => 'facebook_login',
		        'client_id' => $clientId,
		        'session_id' => session_id(),
		        'booking_builder_object_id' => spl_object_id($bb),
		        'booking_data_count' => count($bb->getData()),
		        'storage_mode' => $bb->isUsingTransient() ? 'transient' : 'session',
		    );
		}
	    }

	    return $ret;
	}

	protected function addError($err)
	{
		$this->errors[] = $err;
	}

	public function getErrors()
	{
		return $this->errors;
	}
}
