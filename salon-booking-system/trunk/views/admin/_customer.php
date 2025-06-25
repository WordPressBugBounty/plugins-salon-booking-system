<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var WP_Error|null $error
 */
use SLB_API_Mobile\Helper\UserRoleHelper;
$user_role_helper = new UserRoleHelper();

$hide_phone = $user_role_helper->is_hide_customer_phone();
$hide_email = $user_role_helper->is_hide_customer_email();
?>
<div class="wrap sln-bootstrap" id="sln-salon--admin">
	<h1>
		<?php /** @var SLN_Wrapper_Customer $customer */ ?>
		<?php esc_html_e(sprintf('%s',$customer->isEmpty()) ? 'New Customer' : 'Edit Customer', 'salon-booking-system') ?>
		<?php /** @var string $new_link */ ?>
		<a href="<?php echo $new_link; ?>" class="page-title-action"><?php esc_html_e('Add Customer', 'salon-booking-system'); ?></a>
	</h1>
	<br>

	<?php if(is_wp_error($error)): ?>
        <div class="error">
            <?php foreach ($error->get_error_messages() as $message): ?>
                <p><?php echo $message ?></p>
            <?php endforeach; ?>
        </div>
	<?php endif; ?>
	<form method="post">
	<div class="sln-tab">

	<div class="sln-admin-sidebar mobile affix-top">
		<input type="submit" name="save" value="<?php esc_html_e(sprintf('%s', $customer->isEmpty()) ? 'Publish' : 'Update', 'salon-booking-system'); ?>" class="sln-btn sln-btn--main sln-btn--big" />
	</div>

		<input type="hidden" name="id" id="id" value="<?php echo $customer->getId(); ?>">
			<div class="sln-box sln-box--main">
				<div class="row">
					<div class="col-xs-12"><h2 class="sln-box-title"><?php esc_html_e('Customer details', 'salon-booking-system') ?></h2></div>
				</div>
				<div class="row">

					<?php
					$customer_fields = SLN_Enum_CheckoutFields::forCustomer()->appendSmsPrefix();
					if($customer_fields){
					$helper = new SLN_Metabox_Helper();
					foreach ($customer_fields  as $key => $field) {
						$value = $field->getValue($customer->getId()) ;
						$method_name= 'field'.ucfirst($field['type']);
						$width = $field['width'];
						if ($key === 'sms_prefix') {
							continue;
						}

					 ?>
					 <div class="col-xs-12 col-md-<?php echo $width ?> form-group sln_meta_field sln-input--simple <?php echo 'sln-'.$field['type']; ?>">
						<label for="<?php echo 'sln_customer_meta[_sln_'.$key.']' ?>"><?php echo esc_html__( sprintf('%s', $field['label']), 'salon-booking-system') ?></label>
                        <?php
                            $additional_opts = array(
                                'sln_customer_meta['.'_sln_'.$key.']', $value,
                                array('required' => $field->isRequired())
                            );
							if($key == 'phone'){
								$additional_opts[2]['type'] = 'tel';
                                if($hide_phone){
                                    $additional_opts[2]['type'] = 'password';
                                }
							}
                            if ($key === 'email') {
                                if($hide_email){
                                    $additional_opts[2]['type'] = 'password';
                                }

                            }
                            if($field['type'] === 'checkbox'){

								echo '<input type=\'hidden\' value=\'0\' name=\'sln_customer_meta[_sln_'. $key. ']\'>';
                            	$additional_opts = array_merge(array_slice($additional_opts, 0, 2), array(''), array_slice($additional_opts, 2));
                                $method_name = $method_name .'Button';
                            }
							if($field['type'] === 'file'){
								$files = $customer->getMeta($field['key']);
								if(!is_array($files)){
									$files = array($files);
								}?>
								<div class="sln_meta_field_file">
								<?php foreach($files as $file): ?>
									<?php
                                    if ($file) {
                                        $upload_dir = wp_get_upload_dir();
                                        $custom_path = implode('/', array_filter(array($upload_dir['baseurl'], 'salonbookingsystem/user/' . $customer->getId(), $file['file'])));
                                        $custom_path2 = implode('/', array_filter(array($upload_dir['baseurl'], 'salonbookingsystem/user/0', $file['file'])));

                                        $default_path = implode('/', array_filter(array($upload_dir['baseurl'], trim($file['subdir'], '/'), $file['file'])));

                                        if (file_exists(str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $default_path))) {
                                            $file_url = $default_path;
                                        }elseif (file_exists(str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $custom_path))) {
                                            $file_url = $custom_path;
                                        }elseif (file_exists(str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $custom_path2))) {
                                            $file_url = $custom_path2;
                                        }else {
                                            $file_url = null;
                                        }

                                        $file_name = preg_replace('/^[0-9]+_/i', '', $file['file']);
                                    }
									?>
								<a href="<?php echo $file_url ?>" download="<?php echo $file_url ?>"><?php echo $file_name; ?></a>
								<?php endforeach; ?>
								</div></div><?php
								continue;
							}
                            if($field['type'] === 'select') $additional_opts = array_merge(array_slice($additional_opts, 0, 1), [$field->getSelectOptions()], array_slice($additional_opts, 1),[true]);
                            call_user_func_array(array('SLN_Form',$method_name), $additional_opts );
                        ?>
					</div>
						<?php }}	 ?>
						<?php if ($customer_fields->getField('sms_prefix')): ?>
    						<?php SLN_Form::fieldText('sln_customer_meta[_sln_sms_prefix]', $customer_fields->getField('sms_prefix')->getValue($customer->getId()) ? $customer_fields->getField('sms_prefix')->getValue($customer->getId()) : $plugin->getSettings()->get('sms_prefix'), array('type' => 'hidden')); ?>
						<?php endif; ?>

					<?php do_action('sln.template.customer.metabox', $customer); ?>
					
				</div>
				<div >
				<div class="sln-box--sub row">
					<div class="col-xs-12  form-group sln_meta_field sln-input--simple">
							<label for="_sln_customer_sln_personal_note"><?php esc_html_e('Personal note', 'salon-booking-system') ?></label>
							<textarea type="text" name="sln_customer_meta[_sln_personal_note]" id="_sln_customer_sln_personal_note" class="form-control" rows="5"><?php echo $customer->get('_sln_personal_note'); ?></textarea>
					</div>
				</div>
				</div>
				<div class="sln-box--sub row">
					<div class="col-xs-12  form-group sln_meta_field sln-input--simple">
							<label for="_sln_customer_sln_admininstration_note"><?php esc_html_e('Administration note', 'salon-booking-system') ?></label>
							<textarea type="text" name="sln_customer_meta[_sln_administration_note]" id="_sln_customer_sln_administration_note" class="form-control" rows="5"><?php echo $customer->get('_sln_administration_note'); ?></textarea>
					</div>
				</div>
			</div>
		<div class="sln-box sln-box--main">
			<h2 class="sln-box-title"><?php esc_html_e('Customer\'s bookings', 'salon-booking-system') ?></h2>
			<div class="sln-box--sub row">
				<div class="col-xs-12"><h2 class="sln-box-title"><?php esc_html_e('Booking statistics', 'salon-booking-system') ?></h2></div>
				<div class="col-xs-12">
				<div class="statistics_block sln-table">
			<div class="row statistics_row hidden-xs">
				<div class="col-xs-2 col-md-2 col-lg-2 col-sm-2">
					<?php esc_html_e('Reservations made and value', 'salon-booking-system') ?>
				</div>
				<div class="col-xs-2 col-md-2 col-lg-2 col-sm-2">
					<?php esc_html_e('Reservations per month', 'salon-booking-system') ?>
				</div>
				<div class="col-xs-2 col-md-2 col-lg-2 col-sm-2">
					<?php esc_html_e('Reservations per week', 'salon-booking-system') ?>
				</div>
				<div class="col-xs-2 col-md-2 col-lg-2 col-sm-2">
					<?php esc_html_e('Services booked per single reservation', 'salon-booking-system') ?>
				</div>
				<div class="col-xs-2 col-md-2 col-lg-2 col-sm-2">
					<?php esc_html_e('Favourite week days', 'salon-booking-system') ?>
				</div>
				<div class="col-xs-2 col-md-2 col-lg-2 col-sm-2">
					<?php esc_html_e('Favourite time', 'salon-booking-system') ?>
				</div>
			</div>
			<div class="row statistics_row">
				<div class="col-xs-12 visible-xs-block">
					<span class="statistics_block_desc"><?php esc_html_e('Reservations made and value', 'salon-booking-system') ?></span>
				</div>
				<div class="col-xs-12 col-md-2 col-lg-2 col-sm-2">
					<span>
						<?php
						$count  = $customer->getCountOfReservations();
						$amount = SLN_Plugin::getInstance()->format()->money($customer->getAmountOfReservations(), false);

						echo "$count ($amount)";
						?>
					</span>
				</div>
				<div class="col-xs-12 visible-xs-block">
					<span class="statistics_block_desc"><?php esc_html_e('Reservations per month', 'salon-booking-system') ?></span>
				</div>
				<div class="col-xs-12 col-md-2 col-lg-2 col-sm-2">
					<span>
						<?php
						$countPerMonth  = $customer->getCountOfReservations(MONTH_IN_SECONDS);
						$amountPerMonth = SLN_Plugin::getInstance()->format()->money($customer->getAmountOfReservations(MONTH_IN_SECONDS), false);

						echo "$countPerMonth ($amountPerMonth)";
						?>
					</span>
				</div>
				<div class="col-xs-12 visible-xs-block">
					<span class="statistics_block_desc"><?php esc_html_e('Reservations per week', 'salon-booking-system') ?></span>
				</div>
				<div class="col-xs-12 col-md-2 col-lg-2 col-sm-2">
					<span>
						<?php
						$countPerWeek  = $customer->getCountOfReservations(WEEK_IN_SECONDS);
						$amountPerWeek = SLN_Plugin::getInstance()->format()->money($customer->getAmountOfReservations(WEEK_IN_SECONDS), false);

						echo "$countPerWeek ($amountPerWeek)";
						?>
					</span>
				</div>
				<div class="col-xs-12 visible-xs-block">
					<span class="statistics_block_desc"><?php esc_html_e('Services booked per single reservation', 'salon-booking-system') ?></span>
				</div>
				<div class="col-xs-12 col-md-2 col-lg-2 col-sm-2">
					<span>
						<?php echo $customer->getAverageCountOfServices(); ?>
					</span>
				</div>
				<div class="col-xs-12 visible-xs-block">
					<span class="statistics_block_desc"><?php esc_html_e('Favourite week days', 'salon-booking-system') ?></span>
				</div>
				<div class="col-xs-12 col-md-2 col-lg-2 col-sm-2">
					<span>
						<?php
						$favDays = $customer->getFavouriteWeekDays();
						if ($favDays) {
							foreach($favDays as &$favDay) {
								$favDay = SLN_Enum_DaysOfWeek::getLabel($favDay);
							}

							$favDaysText = implode(', ', $favDays);
						}
						else {
							$favDaysText = __('not avalable yet', 'salon-booking-system');
						}

						echo $favDaysText;
						?>
					</span>
				</div>
				<div class="col-xs-12 visible-xs-block">
					<span class="statistics_block_desc"><?php esc_html_e('Favourite time', 'salon-booking-system') ?></span>
				</div>
				<div class="col-xs-12 col-md-2 col-lg-2 col-sm-2">
					<span>
						<?php
						$favTimes = $customer->getFavouriteTimes();
						if ($favTimes) {
							$favTimesText = implode(', ', $favTimes);
						}
						else {
							$favTimesText = __('not avalable yet', 'salon-booking-system');
						}

						echo $favTimesText;
						?>
					</span>
				</div>
			</div>

					</div>
				</div>
			<!-- .sln-box-sub.row END-->
			</div>

		<?php if ($customer->getBookings()): ?>
			<div class="sln-box--sub row">
				<div class="col-xs-12"><h2 class="sln-box-title"><?php esc_html_e('Booking history', 'salon-booking-system') ?></h2></div>
				<div class="col-xs-12 sln-table">
				<?php

				$_GET['post_type'] = SLN_Plugin::POST_TYPE_BOOKING;
				$_GET['author'] = $customer->getId();
				get_current_screen()->add_option('post_type', SLN_Plugin::POST_TYPE_BOOKING);
				get_current_screen()->id = 'edit-sln_booking';
				get_current_screen()->post_type = SLN_Plugin::POST_TYPE_BOOKING;

				/** @var SLN_Admin_Customers_BookingsList $wp_list_table */
				$wp_list_table = new SLN_Admin_Customers_BookingsList();

				$wp_list_table->prepare_items();

				$wp_list_table->display();
				?>
				</div>
			</div>
		<?php endif; ?>
		<!-- sln-box-main END -->
		</div>
	</div>
        <?php wp_nonce_field('sln_update_user_'. ($customer->isEmpty() ? 0 : $customer->getId())); ?>
	</form>

    <?php if (class_exists('\SalonSOAP\Addon')) { ?>
        <div id="sln-booking-editor-modal" class="modal fade soap-notes-modal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="sln-booking-editor--wrapper">
                            <div class="sln-booking-editor--wrapper--sub">
                                <iframe src="" name="booking_editor" class="booking-editor" width="100%"
                                        height="600px"></iframe>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="modal-footer-meta pull-left-">
                            <span>
                                <span><?php esc_html_e('Booking ID', 'sln-soap') ?></span>
                                <span data-type="modal-footer-meta-booking-id"></span>
                                <span>|</span>
                            </span>
                            <span>
                                <span data-type="modal-footer-meta-booking-date"></span>
                                <span>|</span>
                            </span>
                            <span class="modal-footer-meta-last-author">
                                <span><?php esc_html_e('Updated by', 'sln-soap') ?></span>
                                <span data-type="modal-footer-meta-last-author"></span>
                            </span>
                        </div>
                        <div class="pull-right- modal-footer__actions">
                            <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--highemph sln-btn--big"
                                    aria-hidden="true" data-action="save-edited-booking">
                                <?php esc_html_e('Save', 'salon-booking-system') ?></button>
                            <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--medhemph sln-btn--big"
                                    data-dismiss="modal"
                                    aria-hidden="true"><?php esc_html_e('Close', 'sln-soap') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

</div>

