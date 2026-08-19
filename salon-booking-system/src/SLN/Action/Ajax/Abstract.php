<?php

abstract class SLN_Action_Ajax_Abstract
{
    /** @var  SLN_Plugin */
    protected $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    abstract public function execute();

    /**
     * Verify the standard salon AJAX nonce (`ajax_post_validation` / `security`).
     * Accepts GET or POST — calendar/slot-audit callers use query strings.
     *
     * @return bool
     */
    protected function isValidSalonAjaxNonce()
    {
        $nonce = '';
        if (isset($_REQUEST['security'])) {
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['security']));
        }

        return $nonce !== '' && (bool) wp_verify_nonce($nonce, 'ajax_post_validation');
    }

    /**
     * Capability + nonce for privileged salon AJAX.
     *
     * @param string $capability WordPress capability. Empty string = nonce only.
     * @return bool
     */
    protected function authorizeSalonAjax($capability = 'manage_salon')
    {
        if ($capability && !current_user_can($capability)) {
            return false;
        }

        return $this->isValidSalonAjaxNonce();
    }
}
