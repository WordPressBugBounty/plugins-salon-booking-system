<!-- algolplus -->
<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
$cce = !$plugin->getSettings()->isCustomColorsEnabled();?>
<div id="sln-salon" class="sln-bootstrap sln-salon--m <?php if (!$cce) {
	echo ' sln-customcolors';
}?>">
	<div id="sln-salon__content" class="sln-bootstrap container-fluid">
		<div id="sln-salon-my-account" class="sln-account">
			<div id="sln-salon-my-account-content">
			</div>
		</div>
	</div>
</div>
<?php if(defined('SLN_SPECIAL_EDITION') && SLN_SPECIAL_EDITION): ?>
<div id="sln-plugin-credits" class="sln-credits"><?php esc_html_e('Proudly powered by', 'salon-booking-system') ?> <a target="_blanck" href="https://www.salonbookingsystem.com/plugin-pricing/#utm_source=plugin-credits&utm_medium=booking-my-account&utm_campaign=booking-my-account&utm_id=plugin-credits"><?php esc_html_e('Salon Booking System', 'salon-booking-system'); ?></a></div>
<?php endif; ?>
