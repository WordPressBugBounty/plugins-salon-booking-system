<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<tr>
    <td align="left" valign="top" style="font-size:18px;line-height:20px;color:#4d4d4d;font-weight:bold;font-family: 'Avenir-Medium',sans-serif,arial;padding: 0 0 0 8px;">
	<?php esc_html_e('Dear', 'salon-booking-system') ?> <?php echo esc_attr($booking->getDisplayName()) ?>,
    </td>
</tr>
<tr>
    <td align="center" valign="top" height="5" style="font-size:1px;line-height:1px;">&nbsp;</td>
</tr>
<tr>
    <td align="left" valign="top" style="font-size:18px;line-height:29px;color:#4d4d4d;font-weight:500;font-family: 'Avenir-Medium',sans-serif,arial;padding: 0 0 0 8px;" class="font1">
	<?php esc_html_e('We received the payment for your booking.', 'salon-booking-system') ?>
    </td>
</tr>
<tr>
    <td align="center" valign="top" height="22" style="font-size:1px;line-height:1px;">&nbsp;</td>
</tr>