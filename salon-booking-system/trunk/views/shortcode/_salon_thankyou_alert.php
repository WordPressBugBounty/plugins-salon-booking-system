<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var $confirmation bool
 * @var $plugin SLN_Plugin
 */
$genPhone = $plugin->getSettings()->get('gen_phone');
$genMail = $plugin->getSettings()->getSalonEmail();

$payOffsetEnabled = $plugin->getSettings()->get('pay_offset_enabled');
?>
<div class="sln-alert sln-alert--info <?php if ($confirmation) : ?> sln-alert--topicon<?php endif ?>">
    <?php if ($confirmation) : ?>
        <p><strong><?php esc_html_e(
                    'You will receive a confirmation of your booking by email.',
                    'salon-booking-system'
                ) ?></strong></p>
        <p><?php echo sprintf(
                // translators: %1$s will be replaced by the phone number, %2$s will be replaced by the email
		        esc_html__(
                    'If you don\'t receive any news from us or you need to change your reservation please call the %1$s or send an e-mail to %2$s',
                    'salon-booking-system'
                ),
                $genPhone,
                $genMail
            ); ?></p>
    <?php else : ?>
        <?php if ($paymentMethod && $payOffsetEnabled) : ?>
            <p><?php
                $payLeftTime      = $booking->getTimeStringToChangeStatusFromPending();
                echo sprintf(
                    // translators: %s will be replaced by the remaining payment time
	                esc_html__(
                        'You have <strong>%s</strong> to complete your payment before this reservation is canceled',
                        'salon-booking-system'
                    ),
                    $payLeftTime
                ); ?></p>
        <?php endif ?>
	    <p><?php echo $plugin->getSettings()->get('last_step_note') ? str_replace(array('[SALON PHONE]', '[SALON EMAIL]'), array($genPhone, $genMail), $plugin->getSettings()->get('last_step_note')) : sprintf(
                // translators: %1$s will be replaced by the phone number, %2$s will be replaced by the email
			    esc_html__(
                    'You will receive a booking confirmation by email.If you do not receive an email in 5 minutes, check your Junk Mail or Spam Folder. If you need to change your reservation, please call <strong>%1$s</strong> or send an e-mail to <strong>%2$s</strong>.',
                    'salon-booking-system'
                ),
                $genPhone,
                $genMail
            ); ?>
        </p>
        <!-- form actions -->
    <?php endif ?>
</div>
