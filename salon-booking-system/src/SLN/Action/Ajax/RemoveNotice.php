<?php // algolplus

class SLN_Action_Ajax_RemoveNotice extends SLN_Action_Ajax_Abstract
{
	public function execute()
	{
		if ( ! current_user_can( 'manage_salon' ) ) {
			return array( 'success' => false );
		}

		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation' ) ) {
			return array( 'success' => false );
		}

        try {
            setcookie( 'remove_notice', 'true', time() + ( 30 * 24 * 60 * 60 ), '/' );
            return 'true';
        } catch ( Exception $e ) {
            $errors[] = $e->getMessage();
            return compact( 'errors' );
        }
    }
}