<?php   // algolplus
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin           $plugin
 * @var SLN_Wrapper_Customer $customer
 */

$data['to']      = $customer->get('user_email');
$data['subject'] = $plugin->getSettings()->getSalonName();
$manageBookingsLink = true;

$contentTemplate = '_follow_up_content';
$args = array(
	'meta_key' => '_sln_booking_date',
	'orderby' => 'meta_value',
	'order' => 'DESC',
	'limit' => 1,
);
$booking = $customer->getCompletedBookings($args)[0];

echo $plugin->loadView('mail/template', compact('booking', 'plugin', 'customer', 'data', 'manageBookingsLink', 'contentTemplate'));