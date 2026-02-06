<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Action_CancelBookingLink {

	protected $plugin;

    public function __construct(SLN_Plugin $plugin) {
	$this->plugin = $plugin;
    }

    public static function getUrl() {
	return add_query_arg('sln-api', 'cancel_booking', home_url('/'));
    }

    public function isCancelBookingPage() {
	return isset($_GET['sln-api']) && $_GET['sln-api'] == 'cancel_booking';
    }

    public function execute() {

	if ( ! $this->isCancelBookingPage() ) {
	    return;
	}

	// Validate booking_id parameter exists
	if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
	    wp_die(
		'<p>' . esc_html__('Invalid booking ID.', 'salon-booking-system') . '</p>',
		esc_html__('Error', 'salon-booking-system'),
		array('response' => 400)
	    );
	    return;
	}

	// Sanitize booking ID (supports unique ID format: "123-hash")
	$booking_id_raw = sanitize_text_field(wp_unslash($_GET['booking_id']));
	$booking = null;

	try {
	    // createBooking() handles both plain IDs and unique ID format (123-hash)
	    // It validates the secure ID hash matches the booking
	    $booking = $this->plugin->createBooking($booking_id_raw);
	} catch (Exception $ex) {
	    // Invalid booking ID or secure ID mismatch
	    wp_die(
		'<p>' . esc_html__('Invalid booking ID.', 'salon-booking-system') . '</p>',
		esc_html__('Error', 'salon-booking-system'),
		array('response' => 400)
	    );
	    return;
	}

	if (!$booking || !$booking->getId()) {
	    wp_die(
		'<p>' . esc_html__('Invalid booking ID.', 'salon-booking-system') . '</p>',
		esc_html__('Error', 'salon-booking-system'),
		array('response' => 400)
	    );
	    return;
	}

	// Get numeric booking ID for nonce verification
	$booking_id = $booking->getId();

	do_action('sln_before_cancel_booking_link', $booking);

	$settings = $this->plugin->getSettings();

	$cancellationEnabled = $settings->get('cancellation_enabled');
	$outOfTime	     = ($booking->getStartsAt()->getTimestamp() - time()) < $settings->get('hours_before_cancellation') * 3600;

	$startTimestamp = $booking->getStartsAt();
	$cancelUntil =  $startTimestamp->setTimeStamp( $startTimestamp->getTimestamp() - $settings->get('hours_before_cancellation') * 3600);

	// Verify nonce for CSRF protection on POST requests
	if (isset($_POST['cancel_booking'])) {
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cancel_booking_' . $booking_id)) {
			wp_die(
				'<p>' . esc_html__('Invalid security token. Please refresh the page and try again.', 'salon-booking-system') . '</p>',
				403
			);
			return;
		}
	}

	if ($cancellationEnabled && !$outOfTime && isset($_POST['cancel_booking'])) {
	    // Verify nonce for CSRF protection
	    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'cancel_booking_' . $booking_id)) {
		wp_die(
		    '<p>' . esc_html__('Security check failed. Please try again.', 'salon-booking-system') . '</p>',
		    esc_html__('Error', 'salon-booking-system'),
		    array('response' => 403)
		);
		return;
	    }

	    $booking->setStatus(SLN_Enum_BookingStatus::CANCELED);

	    $booking = $this->plugin->createBooking($booking_id);

		$this->plugin->getBookingCache()->processBooking($booking);

	    // Auto-trash if enabled
	    $trashAction = new SLN_Action_TrashCancelledBooking($this->plugin);
	    $trashAction->execute($booking);

	    $args = compact('booking');

	    $args['forAdmin'] = true;

	    $args['to'] = $this->plugin->getSettings()->getSalonEmail();

	    $this->plugin->sendMail('mail/status_canceled', $args);
	}

	echo $this->plugin->loadView('cancel_booking', array(
	    'cancellation_enabled'  => $cancellationEnabled,
	    'out_of_time'	    => $outOfTime,
	    'booking'		    => $booking,
	    'cancel_until'	    => $this->plugin->format()->datetime($cancelUntil->format('Y-m-d H:i')),
	    'booking_url'	    => get_permalink($settings->getPayPageId()),
	));

	header('HTTP/1.1 200 OK');

	exit();
    }

}