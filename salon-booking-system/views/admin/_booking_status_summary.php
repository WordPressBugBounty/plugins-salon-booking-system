<?php

/**
 * Booking Status Summary Component
 * 
 * Displays a summary of booking statuses (paid/confirmed, pay later, pending, cancelled, errors)
 * 
 * Expected variables:
 * @var array $statusCounts Array containing booking status counts with keys:
 *                         - paid_confirmed
 *                         - pay_later
 *                         - pending
 *                         - cancelled
 *                         - error
 */

// Only display if status counts are available
if (isset($statusCounts) && is_array($statusCounts)): ?>
  <div class="sln-booking-status-summary" style="margin: 15px 0; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
    <h4 style="margin-top: 0;">Booking Status Summary</h4>
    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
      <span class="sln-status-summary__item--paid-confirmed" style="display: inline-block; margin-right: 15px;">
        <strong><?php echo $statusCounts['paid_confirmed']; ?></strong> <?php esc_html_e('Paid/Confirmed', 'salon-booking-system') ?>
      </span>
      <span class="sln-status-summary__item--pay-later" style="display: inline-block; margin-right: 15px;">
        <strong><?php echo $statusCounts['pay_later']; ?></strong> <?php esc_html_e('Pay Later', 'salon-booking-system') ?>
      </span>
      <span class="sln-status-summary__item--pending" style="display: inline-block; margin-right: 15px;">
        <strong><?php echo $statusCounts['pending']; ?></strong> <?php esc_html_e('Pending', 'salon-booking-system') ?>
      </span>
      <span class="sln-status-summary__item--cancelled" style="display: inline-block; margin-right: 15px;">
        <strong><?php echo $statusCounts['cancelled']; ?></strong> <?php esc_html_e('Cancelled', 'salon-booking-system') ?>
      </span>
      <span class="sln-status-summary__item--noshow" style="display: inline-block; margin-right: 15px;">
        <strong><?php echo $statusCounts['noshow']; ?></strong> <?php esc_html_e('No Show', 'salon-booking-system') ?>
      </span>
    </div>
  </div>
<?php endif; ?>