<?php

class SLN_Action_Ajax_RemoveUploadedFile extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        $file_name  = $_POST['file'];
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
