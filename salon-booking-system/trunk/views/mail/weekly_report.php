<?php   // algolplus
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin           $plugin
 * @var SLN_Wrapper_Customer $customer
 * @var array $stats
 */

$data['to']      = $plugin->getSettings()->getSalonEmail();
$data['subject'] = __('Salon Booking weekly report', 'salon-booking-system');

echo $plugin->loadView('mail/weekly_report/template', compact('plugin', 'data', 'stats'));
