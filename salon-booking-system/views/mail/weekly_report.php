<?php   // algolplus
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var array      $stats
 * @var array      $lifetime  All-time totals: total_bookings, revenue, loyal_customers
 * @var bool       $is_free   True when PRO bundle is not active
 */

$data['to']      = implode(',', $plugin->getSettings()->getAdminNotificationEmails());
$data['subject'] = sprintf(
    /* translators: %s: salon name */
    __('%s weekly report', 'salon-booking-system'),
    $plugin->getSettings()->getSalonName()
);

// Fallback safety in case called outside the normal WeeklyReport flow
if (!isset($lifetime)) {
    $lifetime = array('total_bookings' => 0, 'revenue' => 0.0, 'loyal_customers' => 0);
}
if (!isset($is_free)) {
    $is_free = !(defined('SLN_VERSION_PAY') || defined('SLN_VERSION_CODECANYON'));
}

echo $plugin->loadView('mail/weekly_report/template', compact('plugin', 'data', 'stats', 'lifetime', 'is_free'));
