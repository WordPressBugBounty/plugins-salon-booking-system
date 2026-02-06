<?php

/**
 * IP1SMS API V2 Integration
 * 
 * Implements the new IP1SMS RESTful API V2
 * Replaces deprecated SOAP and HTTP APIs
 * 
 * @link https://ip1sms.com/en/developer/
 * @since 10.30.5
 */
class SLN_Action_Sms_Ip1SmsV2 extends SLN_Action_Sms_Abstract
{
    const API_BASE_URL = 'https://api.ip1sms.com/v2';
    
    /**
     * Send SMS using IP1SMS API V2
     * 
     * @param string $to Recipient phone number
     * @param string $message SMS message content
     * @param string $sms_prefix Country prefix
     * @throws SLN_Action_Sms_Exception
     */
    public function send($to, $message, $sms_prefix = '')
    {
        // Process phone number to international format
        $to = $this->processTo($to, $sms_prefix);
        
        // IP1SMS API V2 requires phone numbers WITHOUT the + prefix
        // Format: country code + number (e.g., "46701234567" not "+46701234567")
        $to = ltrim($to, '+');
        
        // Get API key and sender
        $api_key = $this->getApiKey();
        $sender = $this->getFrom();
        
        // Validate required fields
        if (empty($api_key)) {
            $this->createException(
                __('IP1SMS API Key is not configured. Please add your API key in SMS Settings.', 'salon-booking-system'),
                1001
            );
        }
        
        if (empty($sender)) {
            $this->createException(
                __('IP1SMS Sender ID is not configured. Please add your sender ID in SMS Settings.', 'salon-booking-system'),
                1002
            );
        }
        
        // Prepare request payload according to API V2 specs
        $payload = array(
            'sender' => $sender,
            'recipients' => array($to),
            'body' => $message,
        );
        
        // Send request to API
        $response = $this->sendRequest('/batches', $payload);
        
        // Validate and handle response
        $this->validateResponse($response);
    }
    
    /**
     * Send HTTP request to IP1SMS API V2
     * 
     * Uses WordPress HTTP API for better compatibility and security
     * 
     * @param string $endpoint API endpoint (e.g., '/batches')
     * @param array $data Request payload
     * @param string $method HTTP method (POST, GET, PUT, DELETE)
     * @return array Response data with code, body, and raw response
     */
    protected function sendRequest($endpoint, $data, $method = 'POST')
    {
        $url = self::API_BASE_URL . $endpoint;
        $api_key = $this->getApiKey();
        
        // Prepare request arguments
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'sslverify' => true,
        );
        
        // Add body for POST/PUT requests
        if (in_array($method, array('POST', 'PUT')) && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }
        
        // Use WordPress HTTP API for consistency with plugin standards
        $response = wp_remote_request($url, $args);
        
        // Check for WordPress HTTP errors (network issues, timeouts, etc.)
        if (is_wp_error($response)) {
            $error_message = sprintf(
                __('IP1SMS API Request Failed: %s', 'salon-booking-system'),
                $response->get_error_message()
            );
            
            $this->createException($error_message, 1003);
        }
        
        // Get response body and code
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        // Decode JSON response
        $decoded = json_decode($body, true);
        
        return array(
            'code' => $code,
            'body' => $decoded ? $decoded : $body,
            'raw' => $body,
        );
    }
    
    /**
     * Validate API response and handle errors
     * 
     * @param array $response API response array
     * @throws SLN_Action_Sms_Exception
     */
    protected function validateResponse($response)
    {
        $code = $response['code'];
        $body = $response['body'];
        
        // Success codes: 200 (OK), 201 (Created)
        if ($code >= 200 && $code < 300) {
            return;
        }
        
        // Handle error responses
        $error_message = sprintf(
            __('IP1SMS API Error (HTTP %d)', 'salon-booking-system'),
            $code
        );
        
        // Try to extract error message from response
        if (is_array($body)) {
            if (isset($body['title'])) {
                $error_message .= ': ' . $body['title'];
            } elseif (isset($body['message'])) {
                $error_message .= ': ' . $body['message'];
            } elseif (isset($body['error'])) {
                $error_message .= ': ' . $body['error'];
            } elseif (isset($body['error_description'])) {
                $error_message .= ': ' . $body['error_description'];
            }
            
            // Extract validation errors (IP1SMS V2 format)
            if (isset($body['errors']) && is_array($body['errors'])) {
                $validation_errors = array();
                foreach ($body['errors'] as $field => $messages) {
                    if (is_array($messages)) {
                        foreach ($messages as $msg) {
                            $validation_errors[] = $msg;
                        }
                    }
                }
                if (!empty($validation_errors)) {
                    $error_message .= ' - ' . implode(', ', $validation_errors);
                }
            }
        }
        
        // Add user-friendly hints based on error code
        switch ($code) {
            case 401:
                $error_message .= ' - ' . __('Invalid API key. Please check your API key in SMS Settings.', 'salon-booking-system');
                break;
            case 403:
                $error_message .= ' - ' . __('Access denied. Please check your API key permissions.', 'salon-booking-system');
                break;
            case 404:
                $error_message .= ' - ' . __('Endpoint not found. The API may have been updated.', 'salon-booking-system');
                break;
            case 422:
                $error_message .= ' - ' . __('Invalid request data. Please check your sender ID is registered.', 'salon-booking-system');
                break;
            case 429:
                $error_message .= ' - ' . __('Too many requests. Please try again later.', 'salon-booking-system');
                break;
            case 500:
            case 502:
            case 503:
                $error_message .= ' - ' . __('IP1SMS service temporarily unavailable. Please try again later.', 'salon-booking-system');
                break;
        }
        
        $this->createException($error_message, $code);
    }
    
    /**
     * Get API Key from settings
     * 
     * @return string Bearer token for API authentication
     */
    protected function getApiKey()
    {
        return $this->plugin->getSettings()->get('ip1sms_api_key');
    }
    
    /**
     * Register a sender ID with IP1SMS
     * 
     * Sender IDs must be registered before use
     * This should be called during setup or when changing sender ID
     * 
     * @param string $sender Sender ID to register (phone number or alphanumeric)
     * @return array Response with registration status
     */
    public function registerSender($sender)
    {
        if (empty($sender)) {
            return array(
                'success' => false,
                'message' => __('Sender ID cannot be empty', 'salon-booking-system'),
            );
        }
        
        try {
            $endpoint = '/me/senders/' . rawurlencode($sender);
            $response = $this->sendRequest($endpoint, array(), 'PUT');
            
            if ($response['code'] >= 200 && $response['code'] < 300) {
                return array(
                    'success' => true,
                    'message' => sprintf(
                        __('Sender ID "%s" registered successfully', 'salon-booking-system'),
                        $sender
                    ),
                );
            }
            
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Failed to register sender ID: %s', 'salon-booking-system'),
                    is_array($response['body']) && isset($response['body']['message']) 
                        ? $response['body']['message'] 
                        : $response['raw']
                ),
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }
    
    /**
     * Get list of registered senders from IP1SMS
     * 
     * @return array List of registered senders
     */
    public function getRegisteredSenders()
    {
        try {
            $response = $this->sendRequest('/me/senders', array(), 'GET');
            
            if ($response['code'] === 200 && is_array($response['body'])) {
                return $response['body'];
            }
            
            return array();
        } catch (Exception $e) {
            return array();
        }
    }
    
    /**
     * Render settings fields for admin panel
     * 
     * @param array $data Context data with helper
     */
    public function renderSettingsFields($data)
    {
        $helper = $data['helper'];
        $api_key = $this->getApiKey();
        
        ?>
        <div class="row">
            <div class="col-xs-12">
                <div class="alert alert-info">
                    <strong><?php esc_html_e('IP1SMS API V2 Setup', 'salon-booking-system'); ?></strong>
                    <p><?php esc_html_e('IP1SMS has migrated to a new API. Follow these steps to set up:', 'salon-booking-system'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Log in to', 'salon-booking-system'); ?> <a href="https://portal.ip1.net" target="_blank" rel="noopener">portal.ip1.net</a></li>
                        <li><?php esc_html_e('Navigate to "Konton" > "API-nycklar"', 'salon-booking-system'); ?></li>
                        <li><?php esc_html_e('Click "Lägg till nyckel" to create a new API key', 'salon-booking-system'); ?></li>
                        <li><?php esc_html_e('Copy the API key and paste it below', 'salon-booking-system'); ?></li>
                        <li><?php esc_html_e('Register your sender ID (phone number) in the portal', 'salon-booking-system'); ?></li>
                        <li><?php esc_html_e('Use the "Sender\'s number" and "Country code" fields in the default section below', 'salon-booking-system'); ?></li>
                    </ol>
                    <p>
                        <a href="https://ip1sms.com/en/developer/" target="_blank" rel="noopener" class="button button-small">
                            <?php esc_html_e('View API Documentation', 'salon-booking-system'); ?>
                        </a>
                        <a href="https://ip1sms.com/en/customer-support/hur-skapar-jag-en-ny-api-nyckel-for-en-egen-eller-tredjeparts-integration/" target="_blank" rel="noopener" class="button button-small">
                            <?php esc_html_e('API Key Setup Guide', 'salon-booking-system'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12 col-sm-6 sln-input--simple">
                <?php $helper->row_input_text(
                    'ip1sms_api_key',
                    __('API Key (Bearer Token)', 'salon-booking-system'),
                    array(
                        'attrs' => array(
                            'placeholder' => __('Paste your API key here', 'salon-booking-system'),
                        ),
                    )
                ); ?>
                <p class="sln-input-help">
                    <?php esc_html_e('Get your API key from the IP1 portal', 'salon-booking-system'); ?>
                </p>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12 col-sm-3 form-group sln-input--simple">
                <?php $helper->row_input_text(
                    'sms_prefix',
                    __('Country code', 'salon-booking-system'),
                    array('attrs' => array('readonly' => 'readonly'))
                ); ?>
            </div>
            <div class="col-xs-12 col-sm-6 form-group sln-input--simple">
                <?php $helper->row_input_text(
                    'sms_from',
                    __('Sender ID (Phone Number)', 'salon-booking-system'),
                    array(
                        'attrs' => array(
                            'placeholder' => __('e.g., 46701234567', 'salon-booking-system'),
                        ),
                    )
                ); ?>
                <p class="sln-input-help">
                    <?php esc_html_e('Must be registered in the IP1 portal. Include country code without +', 'salon-booking-system'); ?>
                </p>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12 col-sm-6 form-group sln-checkbox">
                <?php $helper->row_input_checkbox(
                    'sms_trunk_prefix',
                    __('Trunk trailing 0 prefix', 'salon-booking-system')
                ); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get required settings fields for this provider
     * 
     * Only return fields that are NOT already in the default GeneralTab fields
     * (sms_from, sms_prefix, sms_trunk_prefix are already registered)
     * 
     * @return array List of setting keys required for this provider
     */
    public function getFields()
    {
        return array(
            'ip1sms_api_key',  // This is the only new field specific to API V2
        );
    }
}

