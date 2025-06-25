<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
    $nb_message = $plugin->getSettings()->get('new_booking_message');

    $nb_message = str_replace(
	array('[DATE]', '[TIME]', '[NAME]', '[SALON NAME]', '\\\\r\\\\n', '\\r\\n', '\\\\n', '\\n'), array(
	    $plugin->format()->date($booking->getDate()),
        $plugin->format()->time($booking->getTime()),
	    $booking->getDisplayName(),
	    $plugin->getSettings()->get('gen_name') ? $plugin->getSettings()->get('gen_name') : get_bloginfo('name'),
	    '<br/>',
	    '<br/>',
	    '<br/>',
	    '<br/>'
	),
	nl2br($nb_message)
    );
?>
<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#505050;font-size:20px">
    <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo  $nb_message
    ?>
</p>

<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#505050;font-size:20px">
<?php if ( $plugin->getSettings()->get('confirmation') && $booking->hasStatus(SLN_Enum_BookingStatus::PENDING) ) : ?>
	<?php echo esc_html__('Your booking is pending, please await our confirmation.','salon-booking-system') ?>
<?php endif ?>
</p>