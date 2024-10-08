<?php   // algolplus
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin                $plugin
 * @var SLN_Wrapper_Booking       $booking
 */
if(!isset($data['to'])){
	$data['to'] = $plugin->getSettings()->getSalonEmail();
}

$data['subject'] = __('Booking was rated','salon-booking-system');

$forAdmin = true;

$contentTemplate = '_booking_rated_content';

echo $plugin->loadView('mail/template', compact('booking', 'plugin', 'data', 'forAdmin', 'contentTemplate'));