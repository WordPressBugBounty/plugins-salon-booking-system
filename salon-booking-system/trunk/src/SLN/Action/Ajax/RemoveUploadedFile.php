<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

class SLN_Action_Ajax_RemoveUploadedFile extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if (!isset($_POST['file'])) {
            $ret = array(
                'success'  => 0,
            );

            return $ret;
        }
        $file_name  = wp_unslash($_POST['file']);
        $user_id = get_current_user_id();
        $file       = wp_upload_dir()['path'].'/salonbookingsystem/user/'.$user_id.'/'. sanitize_file_name($file_name);

        if(file_exists($file) && is_user_logged_in()){
            unlink($file);
        }

	    $ret = array(
            'success'  => 1,
        );

        return $ret;
    }

}
