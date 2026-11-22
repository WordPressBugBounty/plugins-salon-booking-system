<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
 // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div class="wrap sln-bootstrap">
	<h1><?php esc_html_e( 'Tools', 'salon-booking-system' ) ?></h1>
</div>
<div class="clearfix"></div>
<div id="sln-salon--admin" class="container-fluid wpcontent sln-calendar--wrapper sln-calendar--wrapper--loading">
<div class="sln-calendar--wrapper--sub sln-tools__wrapper" style="opacity: 0;">
	<?php if (!empty($versionToRollback)): ?>
            <?php echo $plugin->loadView('admin/_tools_rollback', compact('versionToRollback', 'currentVersion', 'isFree')) ?>
	<?php endif ?>
	
	<?php wp_nonce_field('_sln_action_import'); ?>
	<form>
		<div class="sln-tab" id="sln-tab-general">
			<div class="sln-box sln-box--main">
				<h2 class="sln-box-title"><?php esc_html_e('Settings debug','salon-booking-system') ?></h2>
				<div class="row">
					<div class="col-xs-12 form-group sln-item-top">
						<h6 class="sln-fake-label"><?php esc_html_e('Copy and paste into a text file the informations of this field and provide them to Salon Booking support.','salon-booking-system')?></h6>
					</div>
					<div class="col-xs-12 form-group sln-input--simple">
						<textarea id="tools-textarea" class='tools-textarea'><?php echo $info; ?></textarea>
						<p class="help-block"><?php esc_html_e('Just click inside the textarea and copy (Ctrl+C)','salon-booking-system')?></p>
					</div>
				</div>
			</div>
		</div>
	</form>

	<?php
	echo $plugin->loadView(
		'admin/_tools_booking_discount_diagnostics',
		compact( 'discount_diag_report', 'discount_diag_error', 'discount_diag_booking_id' )
	);
	echo $plugin->loadView(
		'admin/_tools_break_availability_diagnostics',
		compact( 'break_av_diag_report', 'break_av_diag_error', 'break_av_diag_booking_a', 'break_av_diag_booking_b' )
	);
	include __DIR__ . '/_tools_staff_bookings_repair.php';
	?>

	<form method="post" action="<?php echo admin_url('admin.php?page=' . SLN_Admin_Tools::PAGE)?>">
		<div class="sln-tab" id="sln-tab-general">
			<div class="sln-box sln-box--main">
				<h2 class="sln-box-title"><?php esc_html_e('Settings import','salon-booking-system') ?></h2>
				<div class="row">
					<div class="col-xs-12 form-group">
						<h6 class="sln-fake-label"><?php esc_html_e('Copy and paste into this field settings of the plugin to import settings into the current wordpress install.','salon-booking-system')?></h6>
					</div>
					<div class="col-xs-12 form-group sln-input--simple">
						<textarea id="tools-import" name="tools-import"></textarea>
					</div>
				</div>
				<div class="row">
					<div class="col-xs-12 form-group">
						<input  disabled type="submit" class="btn_ btn-default_ sln-btn sln-btn--main sln-btn--big" value="Import" name="sln-tools-import" id="submit-import">
					</div>
				</div>
			</div>
		</div>
	</form>

	<form method="post" action="<?php echo admin_url('admin.php?page=' . SLN_Admin_Tools::PAGE)?>">
		<div class="sln-tab" id="sln-tab-import-data">
			<div class="sln-box sln-box--main">
				<div class="row">
					<div class="col-xs-12 col-lg-6">
						<div class="row">
							<div class="col-xs-12 form-group sln-item-top">
								<h2 class="sln-box-title"><?php esc_html_e('Import “Customers”','salon-booking-system') ?></h2>
								<h6 class="sln-fake-label"><?php esc_html_e('Import customers from other platforms using a CSV file that respect our csv sample file structure.','salon-booking-system')?></h6>
							</div>
							<div class="col-xs-12 form-group sln-input--simple sln-file__dropareawrap">
								<div id="import-customers-drag" class="sln-file__droparea">
                                    <div class="info">
                                        <div class="info-wrap">
                                            <span class="info-upload" aria-hidden="true"></span>
                                            <span class="text"
                                                  placeholder="<?php esc_html_e('Drag your csv file here to import your “Customers”', 'salon-booking-system') ?>">
											<?php esc_html_e('Drag your csv file here to import your “Customers”', 'salon-booking-system') ?></span>
                                        </div>
                                        <button type="button" class="sln-btn sln-btn--main sln-btn--big sln-file__btn"
                                                data-action="sln_import" data-target="import-customers-drag"
                                                data-loading-text="<span class='glyphicon glyphicon-repeat sln-import-loader' aria-hidden='true'></span> <?php esc_html_e('loading', 'salon-booking-system') ?>">
                                            <?php esc_html_e('Import', 'salon-booking-system') ?>
                                        </button>
                                    </div>

									<div class="alert alert-success hide" role="alert"><?php esc_html_e('Well done! Your import has been successfully completed.', 'salon-booking-system') ?></div>
									<div class="alert alert-danger hide" role="alert"><?php esc_html_e('Error! Something is gone wrong.', 'salon-booking-system') ?></div>
									<div class="progress hide">
										<div class="progress-bar active" role="progressbar"
										     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										</div>
									</div>
								</div>
                                <a class="sln-item-csv" href="<?php echo SLN_PLUGIN_URL . '/csv-import-samples/salon_import_sample_customers.csv'; ?>"><?php esc_html_e('Download sample CSV file', 'salon-booking-system') ?></a>
                            </div>
                        </div>
                    </div>

					<div class="col-xs-12 col-lg-6">
						<div class="row">
							<div class="col-xs-12 form-group sln-item-top">
								<h2 class="sln-box-title"><?php esc_html_e('Import “Services”','salon-booking-system') ?></h2>
								<h6 class="sln-fake-label"><?php esc_html_e('Import services from other platforms using a CSV file that respect our csv sample file structure.','salon-booking-system')?></h6>
							</div>
							<div class="col-xs-12 form-group sln-input--simple sln-file__dropareawrap">
								<div id="import-services-drag" class="sln-file__droparea">
                                    <div class="info">
                                        <div class="info-wrap">
                                            <span class="info-upload" aria-hidden="true"></span>
                                            <span class="text"
                                                  placeholder="<?php esc_html_e('Drag your csv file here to import “Services”', 'salon-booking-system') ?>">
											<?php esc_html_e('Drag your csv file here to import “Services”', 'salon-booking-system') ?></span>
                                        </div>
                                        <button type="button" class="sln-btn sln-btn--main sln-btn--big sln-file__btn"
                                                data-action="sln_import" data-target="import-services-drag"
                                                data-loading-text="<span class='glyphicon glyphicon-repeat sln-import-loader' aria-hidden='true'></span> <?php esc_html_e('loading', 'salon-booking-system') ?>">
                                            <?php esc_html_e('Import', 'salon-booking-system') ?>
                                        </button>
                                    </div>
									<div class="alert alert-success hide" role="alert"><?php esc_html_e('Well done! Your import has been successfully completed.', 'salon-booking-system') ?></div>
									<div class="alert alert-danger hide" role="alert"><?php esc_html_e('Error! Something is gone wrong.', 'salon-booking-system') ?></div>

									<div class="progress hide">
										<div class="progress-bar active" role="progressbar"
										     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										</div>
									</div>
								</div>
								<a class="sln-item-csv" href="<?php echo SLN_PLUGIN_URL . '/csv-import-samples/salon_import_sample_services.csv'; ?>"><?php esc_html_e('Download sample CSV file', 'salon-booking-system') ?></a>
							</div>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-xs-12 col-lg-6">
						<div class="row">
							<div class="col-xs-12 form-group sln-item-top">
								<h2 class="sln-box-title"><?php esc_html_e('Import “Assistants”','salon-booking-system') ?></h2>
								<h6 class="sln-fake-label"><?php esc_html_e('Import assistants from other platforms using a CSV file that respect our csv sample file structure.','salon-booking-system')?></h6>
							</div>
							<div class="col-xs-12 form-group sln-input--simple sln-file__dropareawrap">
								<div id="import-assistants-drag" class="sln-file__droparea">
                                    <div class="info">
                                        <div class="info-wrap">
                                            <span class="info-upload" aria-hidden="true"></span>
                                            <span class="text"
                                                  placeholder="<?php esc_html_e('Drag your csv file here to import your “Assistants”', 'salon-booking-system') ?>">
                                    			<?php esc_html_e('Drag your csv file here to import your “Assistants”', 'salon-booking-system') ?></span>
                                        </div>
                                        <button type="button" class="sln-btn sln-btn--main sln-btn--big sln-file__btn"
                                                data-action="sln_import" data-target="import-assistants-drag"
                                                data-loading-text="<span class='glyphicon glyphicon-repeat sln-import-loader' aria-hidden='true'></span> <?php esc_html_e('loading', 'salon-booking-system') ?>">
                                            <?php _e('Import', 'salon-booking-system') ?>
                                        </button>
                                    </div>
									<div class="alert alert-success hide" role="alert"><?php esc_html_e('Well done! Your import has been successfully completed.', 'salon-booking-system') ?></div>
									<div class="alert alert-danger hide" role="alert"><?php esc_html_e('Error! Something is gone wrong.', 'salon-booking-system') ?></div>

									<div class="progress hide">
										<div class="progress-bar active" role="progressbar"
										     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										</div>
									</div>
								</div>
								<a class="sln-item-csv" href="<?php echo SLN_PLUGIN_URL . '/csv-import-samples/salon_import_sample_assistants.csv'; ?>"><?php esc_html_e('Download sample CSV file', 'salon-booking-system') ?></a>
							</div>
						</div>
					</div>
					<div class="col-xs-12 col-lg-6">
						<div class="row">
							<div class="col-xs-12 form-group sln-item-top">
								<h2 class="sln-box-title"><?php esc_html_e('Import “Bookings”','salon-booking-system') ?></h2>
								<h6 class="sln-fake-label"><?php esc_html_e('Import bookings from other platforms using a CSV file that respect our csv sample file structure.','salon-booking-system')?></h6>
							</div>
							<div class="col-xs-12 form-group sln-input--simple sln-file__dropareawrap">
								<div id="import-bookings-drag" class="sln-file__droparea">
                                    <div class="info">
                                        <div class="info-wrap">
                                            <span class="info-upload" aria-hidden="true"></span>
                                            <span class="text"
                                                  placeholder="<?php esc_html_e('Drag your csv file here to import your “Bookings”', 'salon-booking-system') ?>">
                                    			<?php esc_html_e('Drag your csv file here to import your “Bookings”', 'salon-booking-system') ?></span>
                                        </div>
                                        <button type="button" class="sln-btn sln-btn--main sln-btn--big sln-file__btn"
                                                data-action="sln_import" data-target="import-bookings-drag"
                                                data-loading-text="<span class='glyphicon glyphicon-repeat sln-import-loader' aria-hidden='true'></span> <?php esc_html_e('loading', 'salon-booking-system') ?>">
                                            <?php esc_html_e('Import', 'salon-booking-system') ?>
                                        </button>
                                    </div>
									<div class="alert alert-success hide" role="alert">
										<?php esc_html_e('Well done! Your import has been successfully completed.', 'salon-booking-system') ?>
									</div>
									<div class="alert alert-danger hide" role="alert"><?php esc_html_e('Error! Something is gone wrong.', 'salon-booking-system') ?></div>

									<div class="progress hide">
										<div class="progress-bar active" role="progressbar"
										     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										</div>
									</div>
								</div>
								<a class="sln-item-csv" href="<?php echo SLN_PLUGIN_URL . '/csv-import-samples/salon_import_sample_booking.csv'; ?>"><?php esc_html_e('Download sample CSV file', 'salon-booking-system') ?></a>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div id="import-matching-modal" class="modal sln-import-modal sln-import-modal--map" role="dialog" aria-modal="true" aria-labelledby="import-matching-title"
			     data-i18n-matched="<?php /* translators: 1: matched fields, 2: total fields */ esc_attr_e('%1$s of %2$s fields matched', 'salon-booking-system') ?>"
			     data-i18n-missing-one="<?php esc_attr_e('1 required field still needs a column', 'salon-booking-system') ?>"
			     data-i18n-missing-many="<?php /* translators: %s: number of required fields */ esc_attr_e('%s required fields still need a column', 'salon-booking-system') ?>"
			     data-i18n-ready="<?php esc_attr_e('Ready to import', 'salon-booking-system') ?>"
			     data-i18n-rows-one="<?php esc_attr_e('1 row', 'salon-booking-system') ?>"
			     data-i18n-rows-many="<?php /* translators: %s: number of rows */ esc_attr_e('%s rows', 'salon-booking-system') ?>"
			     data-i18n-cols-one="<?php esc_attr_e('1 column', 'salon-booking-system') ?>"
			     data-i18n-cols-many="<?php /* translators: %s: number of columns */ esc_attr_e('%s columns', 'salon-booking-system') ?>"
			     data-i18n-start="<?php esc_attr_e('Import', 'salon-booking-system') ?>"
			     data-i18n-start-rows="<?php /* translators: %s: number of rows */ esc_attr_e('Import %s rows', 'salon-booking-system') ?>"
			     data-i18n-empty="<?php esc_attr_e('Empty in the first rows', 'salon-booking-system') ?>">
				<div class="modal-dialog modal-dialog--import">
					<div class="modal-content">
						<div class="modal-header sln-import-modal__header">
							<button type="button" class="sln-import-modal__close" data-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'salon-booking-system') ?>">&times;</button>
							<h2 id="import-matching-title" class="sln-box-title"><?php esc_html_e('Match your CSV columns','salon-booking-system') ?></h2>
							<p class="sln-import-modal__lead"><?php esc_html_e('We matched the columns we recognised. Check each field, pick a column from your file where needed, and compare with the samples on the right.','salon-booking-system')?></p>
							<div class="sln-import-modal__file">
								<span class="sln-import-modal__file-icon" aria-hidden="true">CSV</span>
								<span class="sln-import-modal__file-name" data-import-file-name></span>
								<span class="sln-import-modal__chip" data-import-rows></span>
								<span class="sln-import-modal__chip" data-import-cols></span>
							</div>
						</div>
						<div class="modal-body sln-import-modal__body">
							<div class="sln-import-map" data-import-map></div>
						</div>
						<div class="sln-import-modal__options" data-import-only="Customers" hidden>
							<label class="sln-import-option" for="import_customers_update_existing">
								<input type="checkbox" class="sln-import-option__input" name="import_customers_update_existing" id="import_customers_update_existing" value="1">
								<span class="sln-import-option__switch" aria-hidden="true"></span>
								<span class="sln-import-option__text">
									<span class="sln-import-option__title"><?php esc_html_e('Update customers who already exist', 'salon-booking-system') ?></span>
									<span class="sln-import-option__desc"><?php esc_html_e('When an email in the file belongs to an existing customer, replace their mobile phone and address with the values in the file and fill in empty notes. Leave off to skip existing customers. Names, passwords and bookings are never changed, and no email is sent.', 'salon-booking-system') ?></span>
								</span>
							</label>
						</div>
						<div class="modal-footer sln-import-modal__footer">
							<div class="sln-import-modal__summary" aria-live="polite">
								<div class="sln-import-modal__meter" aria-hidden="true"><span data-import-meter></span></div>
								<span class="sln-import-modal__summary-text" data-import-summary></span>
								<span class="sln-import-modal__summary-hint" data-import-hint></span>
							</div>
							<div class="sln-import-modal__actions">
								<button type="button" class="sln-btn sln-btn--borderonly sln-btn--big" data-dismiss="modal"><?php esc_html_e('Cancel', 'salon-booking-system') ?></button>
								<button type="button" class="sln-btn sln-btn--main sln-btn--big sln-file__btn" data-action="sln_import_matching"
								        data-loading-text="<span class='glyphicon glyphicon-repeat sln-import-loader' aria-hidden='true'></span> <?php esc_attr_e('Starting…', 'salon-booking-system') ?>">
									<?php esc_html_e('Import', 'salon-booking-system') ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div id="import-skipped-booking-modal" class="modal sln-import-modal sln-import-modal--skipped">
				<div class="modal-dialog modal-dialog--import">
					<div class="modal-content">
						<div class="modal-header sln-import-modal__header">
							<button type="button" class="sln-import-modal__close" data-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'salon-booking-system') ?>">&times;</button>
							<h2 class="sln-box-title"><?php esc_html_e('Import finished — some rows were skipped', 'salon-booking-system');?></h2>
							<h6 class="sln-fake-label"><b class="skipped-bookings--number"></b> <?php esc_html_e('of', 'salon-booking-system'); ?> <b class="skipped-bookings--total"></b> <?php esc_html_e('records could not be imported. Review the reasons below and re-import them after fixing your file.', 'salon-booking-system')?></h6>
						</div>
						<div class="modal-body sln-import-modal__body">
							<div class="alert-skipped">
								<div class="skipped-bookings--title">
									<span class="skipped-booking--id"><?php esc_html_e('ID', 'salon-booking-system') ?></span>
									<span class="skipped-booking--datetime"><?php esc_html_e('Date/Time', 'salon-booking-system') ?></span>
									<span class="skipped-booking--first-name"><?php esc_html_e('First name', 'salon-booking-system') ?></span>
									<span class="skipped-booking--last-name"><?php esc_html_e('Last name', 'salon-booking-system') ?></span>
									<span class="skipped-booking--email"><?php esc_html_e('Email', 'salon-booking-system') ?></span>
									<span class="skipped-booking--error"><?php esc_html_e('Reason', 'salon-booking-system'); ?></span>
								</div>
								<ul class="skipped-bookings"></ul>
							</div>
						</div>
						<div class="modal-footer sln-import-modal__footer">
							<div class="sln-import-modal__actions">
								<button type="button" class="sln-btn sln-btn--main sln-btn--big" data-dismiss="modal"><?php esc_html_e('Done', 'salon-booking-system') ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>

</div>
</div>

<script>
	jQuery(function($){
		jQuery('#wpbody #tools-textarea').on('click', function() {
			jQuery('#tools-textarea').trigger("select");
		});

		jQuery('#tools-import').on('change', function(){
			var $textarea = jQuery('#tools-import').val();
			var disable = ($textarea.length == '');
			$("#submit-import").prop("disabled", disable);
		});

		jQuery('#submit-import').on('click', function(e){
			if (!confirm('Are you sure to continue?')) {
				e.preventDefault();
				$(document.activeElement).trigger("blur");
			}
		});

	});
</script>
