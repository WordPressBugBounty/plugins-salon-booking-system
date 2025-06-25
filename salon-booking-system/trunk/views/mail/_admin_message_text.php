<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#505050;font-size:20px">
	<?php esc_html_e('Dear administrator', 'salon-booking-system') ?>,
        <br/>
	<?php esc_html_e('this is an e-mail notification of a new booking', 'salon-booking-system') ?>
	<?php
	    $_text = apply_filters('sln.new_booking.notifications.email.body.title', '', $booking);
	    $_text = $_text ? esc_html_e($_text) : esc_html_e(' at ', 'salon-booking-system') . $plugin->getSettings()->getSalonName();
	?>
	<?php echo esc_html($_text) ?>,
	<?php esc_html_e('please take note of the following booking details', 'salon-booking-system') ?>.
</p>