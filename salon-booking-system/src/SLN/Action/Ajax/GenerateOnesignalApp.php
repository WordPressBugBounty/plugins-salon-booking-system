<?php

class SLN_Action_Ajax_GenerateOnesignalApp extends SLN_Action_Ajax_Abstract
{
    public function execute()  {
        if(current_user_can ('manage_options') && isset($_POST['security']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation')){
            $url  = 'https://onesignal.com/api/v1/apps';

            $info = wp_parse_url(home_url());

            $args = array(
                'headers' => array(
                    'Authorization' => 'Basic ' . SLN_ONESIGNAL_USER_AUTH_KEY,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(array(
                    'name'                  => 'Salon Booking Plugin App ' . $info['host'],
                    'chrome_web_origin'     => $info['scheme'] . '://' . $info['host'],
                    'site_name'             => $info['host'],
                    'safari_site_origin'    => $info['scheme'] . '://' . $info['host'],
                )),
                'timeout' => 20,
            );

            $response = wp_remote_post($url, $args);

            if ( is_wp_error($response) ) {
                return array(
                    'success' => false,
                    'error'   => $response->get_error_message(),
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response));

            if ( empty($body->id) ) {
                $errors = ! empty($body->errors) ? wp_json_encode($body->errors) : wp_remote_retrieve_body($response);
                return array(
                    'success' => false,
                    'error'   => $errors ? $errors : 'OneSignal did not return an App ID',
                );
            }

            $rest_key = ! empty($body->basic_auth_key) ? $body->basic_auth_key : '';
            $settings = $this->plugin->getSettings();
            $settings->set('onesignal_app_id', $body->id);
            if ( $rest_key ) {
                $settings->set('onesignal_rest_api_key', $rest_key);
            }
            $settings->save();

            return array(
                'success'      => true,
                'app_id'       => $body->id,
                'rest_api_key' => $rest_key,
            );
        } else {
            wp_send_json_error('Not authorized',403);
        }

    }

}
