<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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

			<div id="import-matching-modal" class="modal" role="dialog">
				<div class="modal-dialog">
					<div class="modal-content">
<!--						<div class="modal-header"></div>-->
						<div class="modal-body">
							<div class="row">
								<div class="col-xs-12 form-group">
									<h2 class="sln-box-title"><?php esc_html_e('You need to match your CSV file data with Salon Booking database','salon-booking-system') ?></h2>
									<h6 class="sln-fake-label"><?php esc_html_e('Select for each column the corresponding one inside your file.','salon-booking-system')?></h6>
								</div>
								<div class="col-xs-12">
									<table class="table sln-import-table" cellspacing="0"><tbody></tbody></table>
								</div>
								<div class="col-xs-12">
									<div class="row">
										<div class="col-xs-12 col-md-8">
											<div class="alert alert-danger hide">
												<span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
												<?php esc_html_e('Please provide all requested columns', 'salon-booking-system') ?>
											</div>
										</div>
										<div class="col-xs-12 col-md-4">
											<button type="button" class="sln-btn sln-btn--main sln-btn--big sln-file__btn" data-action="sln_import_matching"
											        data-loading-text="<span class='glyphicon glyphicon-repeat sln-import-loader' aria-hidden='true'></span> <?php esc_html_e('loading', 'salon-booking-system') ?>">
												<?php esc_html_e('Import', 'salon-booking-system') ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
<!--						<div class="modal-footer"></div>-->
					</div>
				</div>
			</div>

			<div id="import-skipped-booking-modal" class="modal">
				<div class="modal-content">
					<div class="modal-body">
						<div class="row">
							<div class="col-xs-12">
								<h2 class="sln-box-title"><?php esc_html_e('Skipped bookings', 'salon-booking-system');?></h2>
								<h3 class="sln-box-title"><b class="skipped-bookings--number"></b> <?php esc_html_e('of', 'salon-booking-system'); ?> <b class="skipped-bookings--total"></b> <?php esc_html_e('records have been skipped due to errors', 'salon-booking-system')?>:</h3>
							</div>
						</div>
						<div class="alert-skipped">
							<div class="skipped-bookings--title">
								<span class="skipped-booking--id"><?php esc_html_e('ID', 'salon-booking-system') ?></span>
								<span class="skipped-booking--datetime"><?php esc_html_e('Date/Time', 'salon-booking-system') ?></span>
								<span class="skipped-booking--first-name"><?php esc_html_e('First name', 'salon-booking-system') ?></span>
								<span class="skipped-booking--last-name"><?php esc_html_e('Last name', 'salon-booking-system') ?></span>
								<span class="skipped-booking--email"><?php esc_html_e('Email', 'salon-booking-system') ?></span>
								<span class="skipped-booking--error"><?php esc_html_e('Error message', 'salon-booking-system'); ?></span>
							</div>
							<ul class="skipped-bookings"></ul>
						</div>
					</div>
<!--						<div class="modal-footer"></div>-->
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
