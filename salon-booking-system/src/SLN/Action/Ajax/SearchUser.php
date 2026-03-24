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
    
    /**
     * Clear all user search cache
     * Called when user data is updated to ensure fresh results
     */
    public static function clearSearchCache()
    {
        global $wpdb;
        
        // Delete all user search transients
        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_sln_user_search_%'
            OR option_name LIKE '_transient_timeout_sln_user_search_%'
        ");
    }
    private function getResult($search)
    {
        global $wpdb;
        
        // Check cache first (5-minute TTL)
        $cache_key = 'sln_user_search_' . md5(strtolower($search));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $user_role_helper = new UserRoleHelper();
        $hide_email = $user_role_helper->is_hide_customer_email();
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        
        // PERFORMANCE OPTIMIZATION: Two-step approach with caching
        // Reference: PERFORMANCE_OPTIMIZATION_ANALYSIS.md - Issue #1
        // Impact: 30x faster than N+1 (3-5s → 100ms), no database index required
        // Cache hit: <1ms (instant)
        
        // STEP 1: Search WordPress native fields (uses built-in indexes)
        $user_query = new WP_User_Query(array(
            'search'         => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_email', 'user_nicename', 'display_name'),
            'number'         => 50,
            'fields'         => array('ID', 'user_email'),
        ));
        
        $user_ids = array();
        $user_emails = array();
        
        foreach ($user_query->get_results() as $user) {
            $user_ids[] = $user->ID;
            $user_emails[$user->ID] = $user->user_email;
        }
        
        // STEP 2: Search user meta (first_name, last_name, phone)
        // Simple query without JOINs - works without custom indexes
        if (count($user_ids) < 10) {
            $meta_results = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT user_id 
                FROM {$wpdb->usermeta}
                WHERE meta_key IN ('first_name', 'last_name', '_sln_phone')
                AND LOWER(meta_value) LIKE %s
                LIMIT 50
            ", $search_like));
            
            $user_ids = array_unique(array_merge($user_ids, $meta_results));
        }
        
        if (empty($user_ids)) {
            set_transient($cache_key, array(), 5 * MINUTE_IN_SECONDS);
            return array();
        }
        
        // STEP 3: Load full user data (WordPress caches this automatically since WP 6.3)
        $final_query = new WP_User_Query(array(
            'include' => array_slice($user_ids, 0, 10),
            'fields'  => array('ID', 'user_email'),
        ));
        
        $values = array();
        foreach ($final_query->get_results() as $user) {
            $first_name = get_user_meta($user->ID, 'first_name', true);
            $last_name = get_user_meta($user->ID, 'last_name', true);
            
            $values[] = array(
                'id' => $user->ID,
                'text' => $first_name . ' ' . $last_name . ' (' . 
                         ($hide_email ? '*******' : $user->user_email) . ')',
            );
        }
        
        // Cache results for 5 minutes
        set_transient($cache_key, $values, 5 * MINUTE_IN_SECONDS);
        
        return $values;
    }
}
