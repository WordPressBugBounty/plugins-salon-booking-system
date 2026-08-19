<?php
// phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
use SLB_API_Mobile\Helper\UserRoleHelper;

class SLN_Action_Ajax_SearchUser extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
       if(!$this->authorizeSalonAjax()) throw new Exception('not allowed');
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
     * Option name that stores the search-cache version counter.
     * Bumping this value invalidates every cached search result at once,
     * without scanning/deleting rows in wp_options.
     */
    const CACHE_VERSION_OPTION = 'sln_user_search_cache_version';

    /**
     * Meta keys that actually affect search results. Only changes to these
     * should invalidate the cache.
     */
    private static $searchable_meta_keys = array('first_name', 'last_name', 'nickname', '_sln_phone');

    /**
     * Current cache version. Included in every transient key so that a bump
     * makes existing entries unreachable (they expire on their own TTL).
     */
    private static function getCacheVersion()
    {
        $version = get_option(self::CACHE_VERSION_OPTION);
        if ($version === false) {
            $version = 1;
            add_option(self::CACHE_VERSION_OPTION, $version, '', 'no');
        }

        return (int) $version;
    }

    /**
     * Invalidate all user search caches.
     * Cheap: a single option write instead of a wildcard DELETE on wp_options.
     */
    public static function clearSearchCache()
    {
        $version = (int) get_option(self::CACHE_VERSION_OPTION);
        update_option(self::CACHE_VERSION_OPTION, $version + 1, false);
    }

    /**
     * Invalidate the cache only when a meta key relevant to search changes.
     * Hooked on updated_user_meta / added_user_meta / deleted_user_meta.
     *
     * @param int    $meta_id
     * @param int    $object_id
     * @param string $meta_key
     */
    public static function maybeClearSearchCacheOnMeta($meta_id, $object_id, $meta_key)
    {
        if (in_array($meta_key, self::$searchable_meta_keys, true)) {
            self::clearSearchCache();
        }
    }

    private function getResult($search)
    {
        global $wpdb;

        // Check cache first (5-minute TTL). The version segment lets us
        // invalidate cheaply (see clearSearchCache) instead of scanning wp_options.
        $cache_key = 'sln_user_search_' . self::getCacheVersion() . '_' . md5(strtolower($search));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $user_role_helper = new UserRoleHelper();
        $hide_email = $user_role_helper->is_hide_customer_email();
        $search_like = '%' . $wpdb->esc_like($search) . '%';

        // STEP 1: Search WordPress native fields (uses built-in indexes).
        // - Restricted to the customer role to match the Customers list page.
        // - count_total disabled: we only need rows, not SQL_CALC_FOUND_ROWS.
        $user_query = new WP_User_Query(array(
            'search'         => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_email', 'user_nicename', 'display_name'),
            'role'           => SLN_Plugin::USER_ROLE_CUSTOMER,
            'number'         => 50,
            'count_total'    => false,
            'fields'         => array('ID'),
        ));

        $user_ids = array();
        foreach ($user_query->get_results() as $user) {
            $user_ids[] = $user->ID;
        }

        // STEP 2: Search user meta (first_name, last_name, phone).
        // LIKE is already case-insensitive under MySQL's default (_ci) collation,
        // so we drop LOWER() which would force a per-row scan and block index use.
        if (count($user_ids) < 10) {
            $meta_results = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT user_id 
                FROM {$wpdb->usermeta}
                WHERE meta_key IN ('first_name', 'last_name', '_sln_phone')
                AND meta_value LIKE %s
                LIMIT 50
            ", $search_like));

            $user_ids = array_unique(array_merge($user_ids, $meta_results));
        }

        if (empty($user_ids)) {
            set_transient($cache_key, array(), 5 * MINUTE_IN_SECONDS);
            return array();
        }

        $user_ids = array_slice($user_ids, 0, 10);

        // STEP 3: Load the final users. Keeping the role filter here ensures meta
        // matches from step 2 can't leak non-customers into the results.
        $final_query = new WP_User_Query(array(
            'include'     => $user_ids,
            'role'        => SLN_Plugin::USER_ROLE_CUSTOMER,
            'count_total' => false,
            'fields'      => array('ID', 'user_email'),
        ));

        $final_users = $final_query->get_results();
        if (empty($final_users)) {
            set_transient($cache_key, array(), 5 * MINUTE_IN_SECONDS);
            return array();
        }

        // Fetch first/last name for every matched user in a single query
        // instead of two get_user_meta() calls per row.
        $final_ids    = wp_list_pluck($final_users, 'ID');
        $placeholders = implode(',', array_fill(0, count($final_ids), '%d'));
        $names        = array();
        $meta_rows    = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, meta_key, meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key IN ('first_name', 'last_name')
            AND user_id IN ($placeholders)
        ", $final_ids));

        foreach ($meta_rows as $row) {
            $names[$row->user_id][$row->meta_key] = $row->meta_value;
        }

        $values = array();
        foreach ($final_users as $user) {
            $first_name = isset($names[$user->ID]['first_name']) ? $names[$user->ID]['first_name'] : '';
            $last_name  = isset($names[$user->ID]['last_name']) ? $names[$user->ID]['last_name'] : '';

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
