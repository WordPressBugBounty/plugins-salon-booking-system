<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Action_Ajax_RescheduleBooking extends SLN_Action_Ajax_Abstract {
	public function execute() {

		if ( ! is_user_logged_in() ) {
			return array( 'redirect' => wp_login_url() );
		}

		$id = $_POST['_sln_booking_id'];

		if(get_post_type($id) !== SLN_Plugin::POST_TYPE_BOOKING){
			wp_die(
				'<p>' . esc_html__( 'Sorry, you cannot reschedule the non-booking.' ) . '</p>',
				403
			);
		}
		if(get_current_user_id() != get_post_field('post_author', $id, 'edit')){
			wp_die(
				'<p>'. get_post_field('post_author', $id) . esc_html__('Sorry, you are not allowed to reshedule this booking.'). get_current_user_id(). '</p>',
				403
			);
		}

		$date = SLN_Func::filter( sanitize_text_field( wp_unslash( $_POST['_sln_booking_date'] ) ), 'date' );
		$time = SLN_Func::filter( sanitize_text_field( wp_unslash( $_POST['_sln_booking_time'] ) ), 'time' );

        if (SLN_Plugin::getInstance()->getSettings()->get('confirmation')) {
            wp_update_post(array(
                    'ID' => $id,
                    'post_status' => SLN_Enum_BookingStatus::PENDING,
            ));
        }
        $curr_booking_date = get_post_meta($id, '_'. SLN_Plugin::POST_TYPE_BOOKING .'_date', true) . ' '. get_post_meta($id, '_'. SLN_Plugin::POST_TYPE_BOOKING. '_time', true);
        $curr_booking_date = new DateTime($curr_booking_date);
        $curr_booking_date->modify($this->plugin->getSettings()->get('days_before_rescheduling'). ' days');
        if($curr_booking_date->getTimestamp() - time() < 0){
        	return wp_die(
        		'<p>' . esc_html__('Sory, you not allowed reshedule old booking.'). '</p>',
        		403
        	);
        }
		update_post_meta( $id, '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date', $date );
		update_post_meta( $id, '_' . SLN_Plugin::POST_TYPE_BOOKING . '_time', $time );

        $plugin = SLN_Plugin::getInstance();

        $booking = $plugin->createBooking( $id );

		synch_a_booking( $booking );

		$format = $plugin->format();

		( new SLN_Service_Messages( $plugin ) )->sendRescheduledMail( $booking );

		return array(
			'booking_date' => $format->date( $booking->getStartsAt() ),
			'booking_time' => $format->time( $booking->getStartsAt() ),
			'booking_status' => $booking->getStatus(),
			'booking_status_label' => SLN_Enum_BookingStatus::getLabel($booking->getStatus()),
            'booking_id' => $booking->getId(),
		);
	}
}
