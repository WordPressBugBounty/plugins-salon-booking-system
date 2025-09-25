<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

class SLN_Action_Ajax_RemoveUploadedFile extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if(current_user_can( 'upload_files' ) && isset($_POST['security']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation')) {
            if (!isset($_POST['file'])) {
                $ret = array(
                    'success' => 0,
                );

                return $ret;
            }
            $file_name = wp_unslash($_POST['file']);
            $user_id = get_current_user_id();
            $upload_dir = wp_upload_dir();
            $user_id = get_current_user_id();
            $target_dir = $upload_dir['basedir'] . '/salonbookingsystem/user/' . $user_id . '/';
            $file = $target_dir . sanitize_file_name($file_name);

            if (file_exists($file) && is_user_logged_in()) {
                unlink($file);
            }

            $ret = array(
                'success' => 1,
            );

            return $ret;
        } else {
            wp_send_json_error('Not authorized',403);
        }
    }

}
