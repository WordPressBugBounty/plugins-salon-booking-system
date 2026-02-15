<?php
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

/**
 * IP1SMS HTTP Integration (DEPRECATED)
 * 
 * @deprecated This API will stop working on October 29, 2025
 * @link https://ip1sms.com/en/developer/
 * 
 * IMPORTANT: Please migrate to SLN_Action_Sms_Ip1SmsV2
 * The old HTTP API at web.smscom.se will be shut down.
 * Update your SMS provider to "IP1SMS (API V2)" in settings.
 */
class SLN_Action_Sms_Ip1SmsHttp extends SLN_Action_Sms_Abstract
{
    const API_URL = 'https://web.smscom.se/sendsms.aspx';

    public function send($to, $message, $sms_prefix = '')
    {
        // Log deprecation warning
        if (defined('WP_DEBUG') && WP_DEBUG) {
            SLN_Plugin::addLog('[IP1SMS DEPRECATION WARNING] The HTTP API is deprecated and will stop working on October 29, 2025. Please migrate to IP1SMS API V2 in SMS Settings.');
        }
        
        // Show admin notice (will be displayed on next page load)
        $this->flagDeprecationNotice();
        
        $to = $this->processTo($to, $sms_prefix);
        // Set parameters
        $data = http_build_query(
            array(
                'acc' => $this->plugin->getSettings()->get('sms_account'),
                'pass' => $this->plugin->getSettings()->get('sms_password'),
                'msg' => $message,
                'from' => $this->plugin->getSettings()->get('sms_from'),
                'to' => $to,
                'prio' => 1,
                'type' => ''
            )
        );
        $opts = array('http' =>
            array(
                'method' => 'GET',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $data
            )
        );
        $context = stream_context_create($opts);
        $ret = file_get_contents(self::API_URL . '?' . $data, false, $context);
    }
    
    /**
     * Flag that deprecation notice should be shown
     */
    private function flagDeprecationNotice()
    {
        // Force the migration notice to show even if dismissed
        delete_option('sln_ip1sms_migration_notice_dismissed');
    }
}
