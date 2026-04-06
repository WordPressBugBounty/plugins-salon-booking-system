<?php

/**
 * AJAX handler for dismissing performance indexes notice
 */
class SLN_Action_Ajax_DismissPerformanceIndexesNotice extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array( 'success' => false, 'message' => __( 'You do not have permission to perform this action.', 'salon-booking-system' ) );
        }

        if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'ajax_post_validation' ) ) {
            return array( 'success' => false, 'message' => __( 'Invalid security token. Please refresh the page and try again.', 'salon-booking-system' ) );
        }

        SLN_Helper_PerformanceIndexManager::dismissNotice();

        return array(
            'success' => true,
        );
    }
}

