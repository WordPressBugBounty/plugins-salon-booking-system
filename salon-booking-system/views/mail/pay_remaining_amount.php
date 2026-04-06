<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin                $plugin
 * @var SLN_Wrapper_Booking       $booking
 */
if(!isset($data['to'])){
    $data['to'] = $booking->getEmail();
}

$data['subject'] = __('Payment of remaining amount of booking','salon-booking-system')
    . ' ' . $plugin->format()->date($booking->getDate())
    . ' - ' . $plugin->format()->time($booking->getTime());

$data['subject'] = apply_filters('sln.new_booking.notifications.email.subject', $data['subject'], $booking);

$manageBookingsLink = true;

$payRemainingAmount = true;

$contentTemplate = '_summary_content';

echo $plugin->loadView('mail/template', compact('booking', 'plugin', 'data', 'manageBookingsLink', 'contentTemplate', 'payRemainingAmount'));

