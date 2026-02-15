<?php

/**
 * IP1SMS SOAP Webservice Integration (DEPRECATED)
 * 
 * @deprecated This API will stop working on October 29, 2025
 * @link https://ip1sms.com/en/developer/
 * 
 * IMPORTANT: Please migrate to SLN_Action_Sms_Ip1SmsV2
 * The old SOAP API at web.smscom.se will be shut down.
 * Update your SMS provider to "IP1SMS (API V2)" in settings.
 */
class SLN_Action_Sms_Ip1SmsWebservice extends SLN_Action_Sms_Abstract
{
    const API_URL = 'https://web.smscom.se/sendsms/sendsms.asmx?wsdl';
    
    public function send($to, $message, $sms_prefix = '')
    {
        // Log deprecation warning
        if (defined('WP_DEBUG') && WP_DEBUG) {
            SLN_Plugin::addLog('[IP1SMS DEPRECATION WARNING] The SOAP API is deprecated and will stop working on October 29, 2025. Please migrate to IP1SMS API V2 in SMS Settings.');
        }
        
        // Show admin notice (will be displayed on next page load)
        $this->flagDeprecationNotice();
        
        $to = $this->processTo($to, $sms_prefix);
        $client = new SoapClient(self::API_URL);
        $ret = $client->sms(array(
            'konto' => $this->plugin->getSettings()->get('sms_account'),
            'passwd' => $this->plugin->getSettings()->get('sms_password'),
            'till' => $to,
            'from' => $this->plugin->getSettings()->get('sms_from'),
            'meddelande' => $message,
            'prio' => 1
        ));
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
