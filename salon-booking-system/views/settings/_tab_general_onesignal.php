<?php
/**
 * @var $plugin SLN_Plugin
 * @var $helper SLN_Admin_Settings
 */
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
if(defined('SLN_VERSION_PAY') && SLN_VERSION_PAY) :
?>
<div class="sln-box--sub row">
    <div class="col-xs-12">
        <h2 class="sln-box-title"><?php esc_html_e('Onesignal Notifications service', 'salon-booking-system')?></h2>
    </div>
    <div class="col-xs-12 col-sm-6">
        <div class="row">
            <div class="col-xs-12 sln-input--simple">
                <?php $helper->row_input_text(
	'onesignal_app_id',
	__('App ID', 'salon-booking-system')
);?>
                <div class="generate-onesignal-app--wrapper">
                    <a href="#"  data-nonce="<?php echo wp_create_nonce('ajax_post_validation'); ?>" class="generate-onesignal-app"><?php echo esc_html__('Generate', 'salon-booking-system') ?></a>
                </div>
            </div>
            <div class="col-xs-12">
                <div class="sln-checkbox">
                <?php $helper->row_input_checkbox('onesignal_new', __('Send Onesignal notification on new bookings', 'salon-booking-system'));?>            
                <div class="sln-box-maininfo">
                    <p class="sln-box-info"><?php esc_html_e('Onesignal notification will be sent to a staff member', 'salon-booking-system');?></p>
                </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6">
        <div class="row">
	    <div class="col-xs-12 sln-input--simple">
		<?php $helper->row_input_textarea('onesignal_notification_message', __('Customize the Onesignal notification message', 'salon-booking-system'), array(
	'textarea' => array(
		'attrs' => array(
			'placeholder' => str_replace("\r\n", " ", SLN_Admin_SettingTabs_GeneralTab::getDefaultOnesignalNotificationMessage()),
		),
	),
));?>
		<p class="sln-input-help">
		    <?php esc_html_e('You can use [NAME], [SALON NAME], [DATE], [TIME], [PRICE], [BOOKING ID]', 'salon-booking-system')?>
		</p>
	    </div>
	</div>
    </div>
    <div class="clearfix"></div>
</div>
<?php endif;