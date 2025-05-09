<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.MissingUnslash

class SLN_Action_Ajax_SalonStep extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if (isset($_POST['sln_step_page'])) {
            $_GET['sln_step_page'] = sanitize_text_field(wp_unslash( $_POST['sln_step_page'] ));
        }
        if (isset($_POST['mode'])) {
            $_GET['mode'] = sanitize_text_field( $_POST['mode'] );
        }

        if (isset($_POST['pay_remaining_amount'])) {
            $_GET['pay_remaining_amount'] = sanitize_text_field( $_POST['pay_remaining_amount'] );
        }

        try {
            $ret = do_shortcode('[' . SLN_Shortcode_Salon::NAME . '][/' . SLN_Shortcode_Salon::NAME . ']');
            $ret = array(
                'content' => $ret,
                'nonce' => wp_create_nonce('ajax_post_validation')
            );
        } catch (SLN_Action_Ajax_RedirectException $ex) {
            $ret = array(
                'redirect' => $ex->getMessage()
            );
        }
        return $ret;
    }
}
