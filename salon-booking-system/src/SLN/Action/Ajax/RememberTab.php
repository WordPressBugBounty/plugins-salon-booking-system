<?php

class SLN_Action_Ajax_RememberTab extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        $tab = $_POST['tab'] ?? 'services';

        $_SESSION['currentTab'] = $tab;

        return [];
    }
}
