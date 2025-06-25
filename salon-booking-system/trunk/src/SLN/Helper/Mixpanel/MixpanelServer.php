<?php

require_once __DIR__ . '/../../Third/mixpanel/vendor/autoload.php';

class SLN_Helper_Mixpanel_MixpanelServer
{
    protected $mp;

    public function __construct($token)
    {
        $this->mp = Mixpanel::getInstance($token, array(
            'debug'           => true,
            'use_ssl'         => true,
            'timeout'         => 3,
            'connect_timeout' => 3,
        ));
    }

    public static function create(){
        $token = defined('SLN_VERSION_DEV') && SLN_VERSION_DEV ? '8e3aa8ca7d3f1003992c4cf27ed95bad' : '004629c1f2b4669e977762d07b671092';
        return new self($token);
    }

    public function track($event, $data = array())
    {
        $data["distinct_id"] = $this->getDistinctID();
        $data['version'] = defined('SLN_VERSION_PAY') && SLN_VERSION_PAY ? 'pro' : 'free';
        $data['enviroment'] = defined('SLN_VERSION_DEV') && SLN_VERSION_DEV ? 'dev' : 'live';
        // track an event
        $this->mp->track($event, $data);
    }

    private function getDistinctID(){
        $settings = SLN_Plugin::getInstance()->getSettings();
        if(!$settings->get('_sln_mixpanel_user_id')){
            $settings->set('_sln_mixpanel_user_id', (new DateTime())->getTimeStamp());
            $settings->save();
        }
        return $settings->get('_sln_mixpanel_user_id');
    }
}
