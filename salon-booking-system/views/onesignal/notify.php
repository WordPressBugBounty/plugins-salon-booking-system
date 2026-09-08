<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin          $plugin
 * @var SLN_Wrapper_Booking $booking
 */

$default_template = SLN_Admin_SettingTabs_GeneralTab::getDefaultOnesignalNotificationMessage();
$template	  = $plugin->getSettings()->get('onesignal_notification_message') ? $plugin->getSettings()->get('onesignal_notification_message') : $default_template;

$name       = $booking->getDisplayName();
$salon_name = $plugin->getSettings()->getSalonName();
$date       = $plugin->format()->date($booking->getDate());
$time       = $plugin->format()->time($booking->getTime());
$price      = $booking->getAmount();
$booking_id = $booking->getId();

$message = str_replace(
    array(
	'[NAME]',
	'[SALON NAME]',
	'[SALONNAME]',
	'[DATE]',
	'[DATUM]',
	'[TIME]',
	'[UHRZEIT]',
	'[PRICE]',
	'[PREIS]',
	'[BOOKING ID]',
	'[BUCHUNGS-ID]',
    ),
    array(
	$name,
	$salon_name,
	$salon_name,
	$date,
	$date,
	$time,
	$time,
	$price,
	$price,
	$booking_id,
	$booking_id,
    ),
    __(sprintf('%s', $template), 'salon-booking-system')
);

echo $message;