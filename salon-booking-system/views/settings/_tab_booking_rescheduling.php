<?php
/**
 * @var $plugin SLN_Plugin
 * @var $helper SLN_Admin_Settings
 */
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
$hoursBeforeRescheduling = $plugin->getSettings()->get('days_before_rescheduling');
// Migrate legacy day-based stored values to hours on display
$sln_legacy_to_hours = array('1' => '24', '2' => '48', '3' => '72', '7' => '168', '14' => '336');
if (isset($sln_legacy_to_hours[$hoursBeforeRescheduling])) {
	$hoursBeforeRescheduling = $sln_legacy_to_hours[$hoursBeforeRescheduling];
}
?>
<div id="sln-booking_rescheduling" class="sln-box sln-box--main sln-box--haspanel">
<h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e('Booking rescheduling', 'salon-booking-system');?></h2>
<div class="collapse sln-box__panelcollapse">
	<div class="row">
    <div class="col-xs-12 col-sm-8 col-md-4 form-group sln-checkbox">
        <?php $helper->row_input_checkbox(
	'rescheduling_disabled',
	__('Disable reschedule', 'salon-booking-system'),
	array('help' => __('Select this option if you want disable the RESCHEDULE feature.', 'salon-booking-system'))
);?>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-select ">
        <label><?php esc_html_e('Time in advance', 'salon-booking-system');?></label>
        <?php $field = "salon_settings[days_before_rescheduling]";?>
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo SLN_Form::fieldSelect(
	$field,
	array(
		'6'   => '6 '  . esc_html__('hours', 'salon-booking-system'),
		'12'  => '12 ' . esc_html__('hours', 'salon-booking-system'),
		'24'  => '1 '  . esc_html__('day', 'salon-booking-system'),
		'48'  => '2 '  . esc_html__('days', 'salon-booking-system'),
		'72'  => '3 '  . esc_html__('days', 'salon-booking-system'),
		'168' => '1 '  . esc_html__('week', 'salon-booking-system'),
		'336' => '2 '  . esc_html__('weeks', 'salon-booking-system'),
	),
	esc_attr($hoursBeforeRescheduling),
	array(),
	true
) ?>
        <p class="help-block"><?php esc_html_e('How many hours before the appointment the rescheduling is still allowed', 'salon-booking-system')?></p>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-4 sln-box-maininfo  align-top">
        <p class="sln-box-info"><?php esc_html_e('Users once logged in inside the MY ACCOUNT BOOKING page will be able to see the list of their upcoming confirmed or paid reservations and eventually RESCHEDULE them. An email notification will be sent to you and to the customers.', 'salon-booking-system');?></p>
    </div>
</div>
</div>
</div>