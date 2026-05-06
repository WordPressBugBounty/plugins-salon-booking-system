<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Action_Ajax_RescheduleBooking extends SLN_Action_Ajax_Abstract {
	public function execute() {

		if ( ! is_user_logged_in() ) {
			return array( 'redirect' => wp_login_url() );
		}

		// Verify nonce for CSRF protection
		if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'ajax_post_validation')) {
			wp_die(
				'<p>' . esc_html__('Invalid security token. Please refresh the page and try again.', 'salon-booking-system') . '</p>',
				403
			);
			return;
		}

		// Validate booking ID
		if (!isset($_POST['_sln_booking_id']) || !is_numeric($_POST['_sln_booking_id'])) {
			wp_die(
				'<p>' . esc_html__('Invalid booking ID.', 'salon-booking-system') . '</p>',
				403
			);
			return;
		}

		$id = intval($_POST['_sln_booking_id']);

		if(get_post_type($id) !== SLN_Plugin::POST_TYPE_BOOKING){
			wp_die(
				'<p>' . esc_html__( 'Sorry, you cannot reschedule the non-booking.' ) . '</p>',
				403
			);
		}
		if(get_current_user_id() != get_post_field('post_author', $id, 'edit')){
			wp_die(
				'<p>' . esc_html__('Sorry, you are not allowed to reschedule this booking.', 'salon-booking-system') . '</p>',
				403
			);
		}

		$curr_booking_date     = get_post_meta( $id, '_' . SLN_Plugin::POST_TYPE_BOOKING . '_date', true ) . ' ' . get_post_meta( $id, '_' . SLN_Plugin::POST_TYPE_BOOKING . '_time', true );
		$curr_booking_date     = new DateTime( $curr_booking_date );
		$reschedule_raw        = $this->plugin->getSettings()->get( 'days_before_rescheduling' );
		$reschedule_legacy_map = array( '1' => 24, '2' => 48, '3' => 72, '7' => 168, '14' => 336 );
		$reschedule_hours      = isset( $reschedule_legacy_map[ $reschedule_raw ] )
			? $reschedule_legacy_map[ $reschedule_raw ]
			: (int) $reschedule_raw;
		$curr_booking_date->modify( '+' . $reschedule_hours . ' hours' );
		if ( $curr_booking_date->getTimestamp() - time() < 0 ) {
			return wp_die(
				'<p>' . esc_html__( 'Sory, you not allowed reshedule old booking.' ) . '</p>',
				403
			);
		}

		$services          = isset( $_POST['_sln_booking']['services'] ) && is_array( $_POST['_sln_booking']['services'] ) ? $_POST['_sln_booking']['services'] : array();
		$customer_timezone = isset( $_POST['customer_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_timezone'] ) ) : '';

		$validation = SLN_Action_Ajax_RescheduleBookingCheckDate::runRescheduleValidation(
			$this->plugin,
			$id,
			isset( $_POST['_sln_booking_date'] ) ? wp_unslash( $_POST['_sln_booking_date'] ) : '',
			isset( $_POST['_sln_booking_time'] ) ? wp_unslash( $_POST['_sln_booking_time'] ) : '',
			$services,
			$customer_timezone
		);

		// runRescheduleValidation leaves the BB set up; clear it now — the save path does not
		// need the BB and we do not want stale data leaking into post-save hooks.
		$this->plugin->getBookingBuilder()->clear();

		if ( ! empty( $validation['errors'] ) ) {
			return array(
				'success' => false,
				'errors'  => $validation['errors'],
			);
		}

		// Persist using the same normalized date/time as validation (handles customer timezone).
		$date = $validation['date'];
		$time = $validation['time'];

		if ( SLN_Plugin::getInstance()->getSettings()->get( 'confirmation' ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => SLN_Enum_BookingStatus::PENDING,
				)
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
			'success'              => true,
			'booking_date'         => $format->date( $booking->getStartsAt() ),
			'booking_time'         => $format->time( $booking->getStartsAt() ),
			'booking_status'       => $booking->getStatus(),
			'booking_status_label' => SLN_Enum_BookingStatus::getLabel( $booking->getStatus() ),
			'booking_id'           => $booking->getId(),
		);
	}
}
