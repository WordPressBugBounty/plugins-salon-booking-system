<?php
// phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
use SLB_API_Mobile\Helper\UserRoleHelper;

class SLN_Action_Ajax_SearchUser extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
       if(!current_user_can( 'manage_salon' )) throw new Exception('not allowed');
       $result = array();
       $search = sanitize_text_field(wp_unslash( isset($_GET['s']) ? $_GET['s'] : '' ));
       if(isset($search)){
           $result = $this->getResult($search);
       }
       if(!$result){
           $ret = array(
               'success' => 0,
               'errors' => array(__('User not found','salon-booking-system'))
           );
       }else{
           $ret = array(
               'success' => 1,
               'result' => $result,
               'message' => __('User updated','salon-booking-system')
           );
       }
       return $ret;
    }
    private function getResult($search)
    {
        $user_role_helper = new UserRoleHelper();
        $hide_email = $user_role_helper->is_hide_customer_email();
        $include = $this->userSearch($search);
        $number = 10;
        if(!$include)
            $user_query = new WP_User_Query( compact('search', 'number') );
        else
            $user_query = new WP_User_Query( compact('include', 'number') );

        if(!$user_query->results) return array();
        else $results = $user_query->results;

        $value = array();
        foreach($results as $u){
            $values[] = array(
                'id' => $u->ID,
                'text' => $u->user_firstname.' '.$u->user_lastname.' ('.($hide_email ? '*******' : $u->user_email).')',
            );
        }
        return $values;
    }

    public function userSearch($wp_user_query) {
            global $wpdb;

            $uids=array();			
            if(isset($wp_user_query)){
			$flsiwa_add = "";
			// Escaped query string
			$qstr = addslashes($wp_user_query);
			if(preg_match('/\s/',$qstr)){
				$pieces = explode(" ", $qstr);
				$user_ids_collector = $wpdb->get_results(
				    $wpdb->prepare(
					"SELECT DISTINCT user_id FROM $wpdb->usermeta WHERE (meta_key='first_name' AND LOWER(meta_value) LIKE %s)",
					'%' . $pieces[0] . '%'
				    )
				);

	            foreach($user_ids_collector as $maf) {
	                if(strtolower(get_user_meta($maf->user_id, 'last_name', true)) == strtolower($pieces[1])){
						array_push($uids,$maf->user_id);
	                }
	            }

			}else{

				$user_ids_collector = $wpdb->get_results(
				    $wpdb->prepare(
					"SELECT DISTINCT user_id FROM $wpdb->usermeta WHERE (meta_key='first_name' OR meta_key='last_name'".$flsiwa_add.") AND LOWER(meta_value) LIKE %s",
					'%' . $qstr . '%'
				    )
				);
					foreach($user_ids_collector as $maf) {
	                array_push($uids,$maf->user_id);
	            }
			}

            $users_ids_collector = $wpdb->get_results(
		$wpdb->prepare(
		    "SELECT DISTINCT $wpdb->users.ID FROM $wpdb->users JOIN $wpdb->usermeta ON $wpdb->users.ID = $wpdb->usermeta.user_id WHERE LOWER($wpdb->users.user_nicename) LIKE %s OR LOWER($wpdb->users.user_email) LIKE %s or ($wpdb->usermeta.meta_key = %s AND $wpdb->usermeta.meta_value LIKE %s)",
		    '%' . $qstr . '%',
		    '%' . $qstr . '%',
            '_sln_phone',
            '%' . $qstr . '%',
		)
	    );
            foreach($users_ids_collector as $maf) {
                if(!in_array($maf->ID,$uids)) {
                    array_push($uids,$maf->ID);
                }
            }
        }
        return $uids;
    }
}
