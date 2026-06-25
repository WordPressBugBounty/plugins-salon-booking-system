<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
/**
 * @var SLN_Plugin $plugin
 * @var SLN_Wrapper_Booking $booking
 */
$salonData = 'sln_step_page=summary&submit_summary=next&mode=confirm';
if (!empty($booking) && $booking->getId()) {
	$salonData .= '&sln_booking_id=' . (int) $booking->getId();
}
$builder  = $plugin->getBookingBuilder();
$clientId = $builder->getClientId();
if (!empty($clientId)) {
	$salonData .= '&sln_client_id=' . rawurlencode($clientId);
}
?>
<button
    <?php if($plugin->getSettings()->isAjaxEnabled()): ?>
        data-salon-data="<?php echo esc_attr($salonData); ?>" data-salon-toggle="next"
    <?php endif?>
    id="sln-step-submit" type="submit" name="submit_summary" value="next">
    <?php echo esc_html__('Next step', 'salon-booking-system'); ?> <i class="glyphicon glyphicon-chevron-right"></i>
</button>
<button
        id="sln-step-submit-complete" value="next hidden">
    <?php echo esc_html__('Complete', 'salon-booking-system'); ?> <i class="glyphicon glyphicon-chevron-right"></i>
</button>
