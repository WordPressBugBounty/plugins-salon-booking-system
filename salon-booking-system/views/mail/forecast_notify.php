<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Forecast slot notification email entry point.
 *
 * @var SLN_Plugin           $plugin
 * @var SLN_Wrapper_Customer $customer
 * @var SLN_Wrapper_Service  $service
 * @var array                $suggestions
 */

$data['to'] = $customer->get( 'user_email' );

$salon_name = $plugin->getSettings()->getSalonName() ?: get_bloginfo( 'name' );

$data['subject'] = sprintf(
	/* translators: %1$s: service name, %2$s: salon name */
	__( 'Time for your next %1$s at %2$s', 'salon-booking-system' ),
	$service->getName(),
	$salon_name
);

$data['subject'] = apply_filters( 'sln.forecast_notify.email.subject', $data['subject'], $customer, $service, $suggestions );

$contentTemplate    = '_forecast_notify_content';
$skipBookingDetails = true;
$forAdmin           = false;
$manageBookingsLink = false;

echo $plugin->loadView(
	'mail/template',
	compact(
		'plugin',
		'customer',
		'service',
		'suggestions',
		'data',
		'contentTemplate',
		'skipBookingDetails',
		'forAdmin',
		'manageBookingsLink'
	)
);
