<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_Ajax_RememberTab extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        $tab = wp_unslash($_POST['tab']) ?? 'services';

        $_SESSION['currentTab'] = $tab;

        return [];
    }
}
