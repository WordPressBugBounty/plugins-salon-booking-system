<?php

class SLN_Update_Manager
{
    private $data;
    private $processor;
    public $page;

    public function __construct($data)
    {
        $this->data = $data;
        add_action('admin_init', array($this, 'hook_admin_init'), 0);
        add_action('admin_menu', array($this, 'hook_admin_menu'));
        add_action('init', array($this, 'hook_init'));
        add_action('sln_update_check', array($this,'checkLicense'));
        add_action('sln_update_subscription', array($this,'checkSubscription'));
        add_action('wp_ajax_sln_refresh_license_status', array($this, 'refreshLicense'));
    }

    public function hook_init(){
        if(!wp_next_scheduled( 'sln_update_check' )){
            wp_schedule_event( time(), 'daily', 'sln_update_check' );
        }
        if(!wp_next_scheduled( 'sln_update_subscription' )){
            wp_schedule_event( time(), 'daily', 'sln_update_subscription' );
        }
    }
    public function hook_admin_menu()
    {
        $this->page = new SLN_Update_Page($this);
    }


    public function hook_admin_init()
    {
        global $pagenow;
        if ('plugins.php' == $pagenow || 'plugin-install.php' == $pagenow) {
            $this->processor = new SLN_Update_Processor($this);
        }
    }

    public function get($k)
    {
        if ($k == 'license_key') {
            return (isset($_REQUEST['key']) ? $_REQUEST['key'] : get_option($this->data['slug'].'_license_key') );
        }
        if ($k == 'license_status') {
            return get_option($this->data['slug'].'_license_status');
        }
        if ($k == 'license_data') {
            return get_option($this->data['slug'].'_license_data');
        }

        if ($k == 'subscriptions_data') {
            return get_option($this->data['slug'].'_subscriptions_data');
        }

        return $this->data[$k];
    }

    /**
     * @param $license
     * @return null|WP_Error
     */
    public function activateLicense($key)
    {
        SLN_Func::updateOption($this->get('slug').'_license_key', $key);
        $response = $this->doCall('activate_license');
        //$response->license = 'valid';
        if (is_wp_error($response)) {
            SLN_Func::updateOption($this->get('slug').'_license_status', $response->get_error_message());
        } else {
            SLN_Func::updateOption($this->get('slug').'_license_status', $response->license, true);
            SLN_Func::updateOption($this->get('slug').'_license_data', $response, true);
        }

        return $response;
    }

    /**
     * @return null|WP_Error
     * @throws Exception
     */
    public function deactivateLicense()
    {
        $response = $this->doCall('deactivate_license');
        if (is_wp_error($response)) {
            return $response;
        } elseif ($response->license == 'deactivated') {
            SLN_Func::deleteOption($this->get('slug').'_license_key');
            SLN_Func::deleteOption($this->get('slug').'_license_status');
            SLN_Func::deleteOption($this->get('slug').'_license_data');
        } else {
            SLN_Func::updateOption($this->get('slug').'_license_status', $response->license, true);
            SLN_Func::updateOption($this->get('slug').'_license_data', $response, true);
        }
    }

    public function getVersion(){
        $response = $this->doCall('get_version');

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response && isset($response->sections)) {
            $response->sections = maybe_unserialize($response->sections);
        } else {
            $response = false;
        }

        return $response;
    }

    public function checkLicense(){
        $response = $this->doCall('check_license');

        if (is_wp_error($response)) {
            error_log("Error checking license of {$this->get('slug')}: {$response->get_error_message()}");
        }

        if ($response->license == 'expired' ) {
            SLN_Func::updateOption($this->get('slug').'_license_status', $response->license, true);
            SLN_Func::updateOption($this->get('slug').'_license_data', $response, true);
        }

        return $response;
    }

    public function getEddProducts($productID = false) {
        $productsKey = $this->get('slug').'_products_data';
        if ($productID && ($products = get_option($productsKey))) {
            $match = array_values(array_filter($products, fn($p) => isset($p->info->id) && $p->info->id == $productID));
            if ($match)
                return $match;
        }

        $transientKey = $this->get('slug').'_products_cache';
        if (!$productID && ($cached = get_transient($transientKey)))
            return $cached;

        $request = [
            'key'	=> $this->get('api_key'),
            'token'	=> $this->get('api_token'),
            'number'  => $productID ? 1 : -1,
        ];

        if ($productID)
            $request['product'] = $productID;

        $response = wp_remote_get(
            add_query_arg($request, $this->get('store') . '/edd-api/products'),
            ['timeout' => 15, 'sslverify' => false]
        );

        if (is_wp_error($response)) {
            error_log("Error fetching EDD products: {$response->get_error_message()}");
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response));
        $products = isset($data->products) ? $data->products : [];
        SLN_Func::updateOption($productsKey, $products, true);

        if (!$productID)
            set_transient($transientKey, $products, HOUR_IN_SECONDS);

        return $products;
    }

    public function refreshLicense(){
        $response = $this->doCall('check_license');
        $response = (array)$response;
        $this->activateLicense($_REQUEST['key']);

        if($response['success']){
            wp_send_json_success([
                'status' => true,
                'status_title' => __('active', 'salon-booking-system'),
                'payment_id' => $response['payment_id'],
                'customer_name' => $response['customer_name'],
                'customer_email' => $response['customer_email'],
                'expires' => $response['expires'],
            ]);
        } else {
            wp_send_json_success([
                'status' => false,
                'status_title' => __('invalid', 'salon-booking-system'),
                'payment_id' => '',
                'customer_name' => '',
                'customer_email' => '',
                'expires' => '',
            ]);
        }


        return $response;
    }

    public function checkSubscription(){

	$license_data = $this->get('license_data');

	SLN_Func::updateOption($this->get('slug').'_subscriptions_data', array(), true);

	if ( ! $this->get('api_key') || ! $this->get('api_token') || ! isset( $license_data->customer_email ) ) {
	    return;
	}

	$request  = array(
            'key'	=> $this->get('api_key'),
            'token'	=> $this->get('api_token'),
            'customer'  => $license_data->customer_email,
        );
        $response = wp_remote_get(
            add_query_arg($request, $this->get('store') . '/edd-api/subscriptions'),
            array('timeout' => 15, 'sslverify' => false)
        );

        if (is_wp_error($response)) {
	    return;
        }

	$subscriptions_data = json_decode(wp_remote_retrieve_body($response));

	if (isset($subscriptions_data->error)) {
	    return;
	}

	SLN_Func::updateOption($this->get('slug').'_subscriptions_data', $subscriptions_data, true);

        return $response;
    }

    /**
     * @param $action
     * @param $license
     * @return string|WP_Error
     */
    public function doCall($action)
    {
        $license  = $this->get('license_key');
        $request  = array(
            'edd_action' => urlencode($action),
            'license'    => urlencode($license),
            'item_name'  => urlencode($this->get('name')),
            'url'        => urlencode(home_url()),
        );
        $response = wp_remote_get(
            add_query_arg($request, $this->get('store')),
            array('timeout' => 15, 'sslverify' => false)
        );

        if (is_wp_error($response)) {
            return $response;
        } else {
            $license_data = json_decode(wp_remote_retrieve_body($response));

            return $license_data;
        }
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return $this->get('license_status') == 'valid';
    }
}