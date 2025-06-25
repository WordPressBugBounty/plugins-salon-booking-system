<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var $plugin SLN_Plugin
 */
$transactionFee = SLN_Helper_TransactionFee::getFee($booking->getToPayAmount(false, false));
?>
<?php if ( ! empty( $transactionFee ) ): ?>
    <div class="sln-payment-transaction-fee">
	<?php echo sprintf(
        // translators: %s will be replaced by the number of transaction fee
        esc_html__('A transaction fee of %s will be applied', 'salon-booking-system'),
	    '<strong>'.$plugin->format()->money($transactionFee, false, false, true).'</strong>'
	) ?>
    </div>
<?php endif; ?>