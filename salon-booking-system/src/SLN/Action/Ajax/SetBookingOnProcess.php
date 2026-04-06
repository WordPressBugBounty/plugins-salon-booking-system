<?php // algolplus

class SLN_Action_Ajax_SetBookingOnProcess extends SLN_Action_Ajax_Abstract
{
	public function execute()
	{
		if ( ! current_user_can( 'manage_salon' ) ) {
			return array( 'success' => 0, 'errors' => array( __( 'You do not have permission to perform this action.', 'salon-booking-system' ) ) );
		}

		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation' ) ) {
			return array( 'success' => 0, 'errors' => array( __( 'Invalid security token. Please refresh the page and try again.', 'salon-booking-system' ) ) );
		}

        try {
            $booking    = SLN_Plugin::getInstance()->createBooking( intval( isset( $_REQUEST['id'] ) ? wp_unslash( $_REQUEST['id'] ) : '' ) );
            $on_process = $booking->getOnProcess();
            $booking->setOnProcess( ! $on_process );
        } catch ( Exception $e ) {
            $errors[] = $e->getMessage();
            return compact( 'errors' );
        }

		return array( 'success' => 1, 'on_process' => ! $on_process );
    }
}