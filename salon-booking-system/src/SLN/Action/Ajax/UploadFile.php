<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

class SLN_Action_Ajax_UploadFile extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if(current_user_can( 'upload_files' ) && isset($_POST['security']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation')){

        $errors    = array();
        $file_name = '';

        if(!empty($_FILES) && isset($_FILES['file'])) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
            }
            
            $tmp_file = $_FILES['file'];
            
            // Validate file before processing
            if ($tmp_file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = __('File upload error occurred.', 'salon-booking-system');
                $ret = array(
                    'success' => false,
                    'errors'  => $errors,
                    'file'    => '',
                );
                return $ret;
            }
            
            // Validate file type using WordPress function
            $file_type = wp_check_filetype($tmp_file['name']);
            if (empty($file_type['ext']) || empty($file_type['type'])) {
                $errors[] = __('Invalid file type. Please upload a valid file.', 'salon-booking-system');
                $ret = array(
                    'success' => false,
                    'errors'  => $errors,
                    'file'    => '',
                );
                return $ret;
            }
            
            // Validate file size (max 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB
            if ($tmp_file['size'] > $max_size) {
                $errors[] = __('File size exceeds maximum allowed size of 10MB.', 'salon-booking-system');
                $ret = array(
                    'success' => false,
                    'errors'  => $errors,
                    'file'    => '',
                );
                return $ret;
            }
            
            // Sanitize filename
            $tmp_file['name'] = sanitize_file_name(str_replace(' ','_',$tmp_file['name']));
            $file_name = $this->unique_filename(null, $tmp_file['name']);

            $upload_dir = wp_upload_dir();
            $user_id = get_current_user_id();
            $target_dir = $upload_dir['basedir'] . '/salonbookingsystem/user/' . $user_id . '/';

            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }

            add_filter('upload_dir', function ($dirs) use ($target_dir) {
                $dirs['path'] = $target_dir;
                $dirs['subdir'] = '/salonbookingsystem/user/' . get_current_user_id();
                $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
                $dirs['basedir'] = $target_dir;
                return $dirs;
            });

            $overrides = array(
                'test_form' => false,
                'unique_filename_callback' => array($this, 'unique_filename'),
            );

            $movefile = wp_handle_upload($tmp_file, $overrides);
            if(isset($movefile['error'])){
                $errors[] = $movefile['error'];
            }

            remove_filter('upload_dir', '__return_false');
        }

	    $ret = array(
            'success' => empty($errors),
            'errors'  => $errors,
            'file'    => $file_name,
        );

        return $ret;
    } else {
            wp_send_json_error('Not authorized',403);
        }
    }

    public function unique_filename($path, $filename){
        return (new DateTime())->getTimestamp(). '_'. $filename;
    }

}
