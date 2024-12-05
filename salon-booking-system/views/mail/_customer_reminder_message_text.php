
<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
$_remind_message = $plugin->getSettings()->get('booking_update_message');
?>

<?php if ($_remind_message): ?>

    <?php
	$_remind_message = str_replace(
	    array('[DATE]', '[TIME]', '[NAME]', '[SALON NAME]', '\\\\r\\\\n', '\\r\\n', '\\\\n', '\\n', "\r\n", "\n"),
	    array(
		$plugin->format()->date($booking->getDate()),
		$plugin->format()->time($booking->getTime()),
		$booking->getDisplayName(),
		'<b style="color:#666666;">' . $plugin->getSettings()->getSalonName() . '</b>',
		'<br/>',
		'<br/>',
		'<br/>',
		'<br/>',
		'<br/>',
		'<br/>',
	    ),
	    $_remind_message
	);
    ?>

<?php else: ?>

    <?php
    	$_remind_message = __('Reminder: Your booking at ', 'salon-booking-system') . '<b style="color:#666666;">' . $plugin->getSettings()->getSalonName() . '.</b>';
    ?>

<?php endif; ?>
<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#505050;font-size:20px"><?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo  $_remind_message ?>
</p>