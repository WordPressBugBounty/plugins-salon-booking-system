<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Warning marker on a calendar card: the booking was saved over availability
 * rules ("Save anyway"). The tooltip lists the rules it breaks.
 *
 * @var SLN_Wrapper_Booking $booking
 */
$slnForcedViolations = SLN_Helper_AvailabilityViolations::getForcedViolations($booking->getId());
if ($slnForcedViolations):
    $slnForcedTip = __('Saved over availability rules', 'salon-booking-system');
    foreach ($slnForcedViolations as $slnForcedViolation) {
        $slnForcedTip .= "\n• " . $slnForcedViolation['message'];
    }
    ?>
    <span class="sln-forced-booking-badge"
          title="<?php echo esc_attr($slnForcedTip); ?>"
          aria-label="<?php echo esc_attr($slnForcedTip); ?>"
          style="display:inline-flex;align-items:center;justify-content:center;flex:0 0 16px;width:16px;height:16px;margin-left:4px;border-radius:50%;background:#f0b849;color:#1d2327;font-size:11px;font-weight:700;line-height:1;cursor:help;">!</span>
<?php endif; ?>
