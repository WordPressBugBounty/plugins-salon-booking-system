<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin          $plugin
 * @var SLN_Wrapper_Booking $booking
 */

$message = __('Hi', 'salon-booking-system'). " [NAME],\n" . __('you reservation well be reviewd soon', 'salon-booking-system'). ",\n" .__('you\'ll recevi an email when approved.', 'salon-booking-system'). "\n [SALON NAME]";

$message = str_replace(
    array(
	'[NAME]',
	'[SALON NAME]',
	// '[DATE]',
	// '[TIME]',
	// '[PRICE]',
	// '[BOOKING ID]',
    ),
    array(
	$booking->getDisplayName(),
	$plugin->getSettings()->getSalonName(),
    ),
    $message
);

if (strlen($message) > 160) {
    $more_string = __('...more details in the email confirmation', 'salon-booking-system');
    $message	 = substr($message, 0, ( 159 - strlen($more_string))) . $more_string;
}

echo $plugin->getSettings()->get('sms_ascii_mode') ? remove_accents($message) : $message;