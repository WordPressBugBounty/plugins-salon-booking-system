<?php

class SLN_Action_Ajax_RememberTab extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation' ) ) {
            return array();
        }

        $tab = sanitize_text_field( wp_unslash( isset( $_POST['tab'] ) ? $_POST['tab'] : 'services' ) );

        $_SESSION['currentTab'] = $tab;

        return array();
    }
}
