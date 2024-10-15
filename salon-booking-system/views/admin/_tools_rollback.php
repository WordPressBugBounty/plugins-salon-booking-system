<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/** @var string $versionToRollback */
/** @var string $currentVersion */
/** @var bool $isFree */
?>
	<form>
		<div class="sln-tab" id="sln-tab-general">
			<div class="sln-box sln-box--main">
				<h2 class="sln-box-title"><?php
					echo sprintf(
                            // translators: %s will be replaced by the version number rollback
                            esc_html__('Rollback to %s version','salon-booking-system'), $versionToRollback) ?></h2>
				<div class="row">
					<div class="col-xs-12 form-group">
						<h6 class="sln-fake-label"><?php echo sprintf(
                                // translators: %1$s will be replaced by the current version, %2$s will be replaced by the version to rollback
                                esc_html__('If after the install of the %1$s version of Salon Booking you realize that something goes wrong you can use this tool to restore the %2$s version. The rollack process consist into three steps:', 'salon-booking-system'), $currentVersion, $versionToRollback) ?></h6>
					</div>
				</div>
				<div class="row">
					<div class="col-xs-12 col-sm-4">
						<h6 class="sln-fake-label"><?php echo sprintf(
                                // translators: %s will be replaced by the version to rollback
                                esc_html__('1. Rollback your database to the %s version ','salon-booking-system'), $versionToRollback) ?></h6>
						<input type="hidden" name="page" value="salon-tools">
					</div>
					<div class="col-xs-12 col-sm-4">
						<h6 class="sln-fake-label"><?php echo sprintf(
                                // translators: %s will be replaced by the version to rollback
                                esc_html__('2. Download Salon Booking %s','salon-booking-system'), $versionToRollback) ?></h6>
					</div>
					<div class="col-xs-12 col-sm-4">
						<h6 class="sln-fake-label"><?php echo sprintf(
                                // translators: %s will be replaced by the version to rollback
                                esc_html__('3. Upload Salon Booking %s folder on your server ','salon-booking-system'), $versionToRollback) ?></h6>
					</div>
				</div>
				<div class="row">
					<div class="col-xs-12 col-sm-4 form-group">
						<p><button id="tools-rollback-btn" class='sln-btn sln-btn--main sln-btn--big' name="do_rollback_sln" value="true"><?php esc_html_e('Rollback database','salon-booking-system'); ?></button></p>
					</div>
					<div class="col-xs-12 col-sm-4 form-group">
						<p><a href="<?php echo $isFree ? 'https://downloads.wordpress.org/plugin/salon-booking-system.2.3.2.zip' : 'http://salonbookingsystem.com/salon-booking-plugin-pricing/' ?>"
						      class='sln-btn sln-btn--main sln-btn--big'><?php esc_html_e('Download','salon-booking-system'); ?></a></p>
					</div>
				</div>
			</div>
		</div>
	</form>

