<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<div class="sln-box__fl__item sln-input--simple sln-box__fl__item--full sln-booking-discounts">
	<div class="form-group sln-input--simple sln-booking-discounts--infotext">
		<?php foreach ($discounts as $discount): ?>
		    <label for=""><?php esc_html_e('Discount applied', 'salon-booking-system'); ?>: <span><?php echo esc_attr($discount->getAmountString()); ?></span></label>
		<?php endforeach; ?>
	</div>
</div>
