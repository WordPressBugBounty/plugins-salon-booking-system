<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<div id="sln-setting-error" class="updated error">
    <h3><?php esc_html_e('Salon booking plugin needs a valid license','salon-booking-system') ?></h3>

    <p><a href="<?php echo esc_url($licenseUrl); ?>"><?php esc_html_e('Please insert your license key', 'salon-booking-system'); ?></a></p>
</div>
