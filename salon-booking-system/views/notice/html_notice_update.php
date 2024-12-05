<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<div id="sln-setting-success" class="updated settings-success success">
	<p>
		<strong><?php esc_html_e('Salon Data Update Required','salon-booking-system') ?></strong> -
		<?php echo esc_html__('A database update is required for this version. Please make a back-up of your database before proceed.','salon-booking-system') ?>
	</p>

	<p>
		<a href="<?php echo esc_url( add_query_arg( 'do_update_sln', 'true', admin_url( 'admin.php?page=salon-settings' ) ) ); ?>"
		   class="button button-default"><?php esc_html_e( 'Run the updater', 'salon-booking-system' ); ?></a>
	</p>
</div>
