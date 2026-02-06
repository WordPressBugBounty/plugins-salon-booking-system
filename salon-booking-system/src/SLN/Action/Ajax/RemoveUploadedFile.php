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
            // Sanitize and validate filename
            $file_name = sanitize_file_name(basename(wp_unslash($_POST['file'])));
            if (empty($file_name)) {
                $ret = array(
                    'success' => 0,
                    'error' => __('Invalid filename.', 'salon-booking-system'),
                );
                return $ret;
            }
            
            $user_id = get_current_user_id();
            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/salonbookingsystem/user/' . $user_id . '/';
            
            // Use realpath to prevent path traversal attacks
            $file = realpath($target_dir . $file_name);
            $target_dir_real = realpath($target_dir);
            
            // Verify file is within the allowed directory (prevent path traversal)
            if ($file === false || $target_dir_real === false || strpos($file, $target_dir_real) !== 0) {
                $ret = array(
                    'success' => 0,
                    'error' => __('Invalid file path.', 'salon-booking-system'),
                );
                return $ret;
            }

            if (file_exists($file) && is_user_logged_in() && is_file($file)) {
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
