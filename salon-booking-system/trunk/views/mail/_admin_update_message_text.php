<?php
    // phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
    $updated_message = esc_html__('Reservation addt [SALON NAME] has been modified', 'salon-booking-system');
    $updated_message = str_replace('[SALON NAME]', $plugin->getSettings()->getSalonName(), $updated_message);
?>
<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#505050;font-size:20px"><?php echo esc_html($updated_message) ?></p>