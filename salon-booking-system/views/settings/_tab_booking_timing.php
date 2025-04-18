<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.UnsafePrintingFunction
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var $plugin SLN_Plugin
 */
$from = $plugin->getSettings()->getHoursBeforeFrom();
$to = $plugin->getSettings()->getHoursBeforeTo();
?>
<div id="sln-booking_time_range" class="sln-box sln-box--main sln-box--haspanel">
    <h2 class="sln-box-title sln-box__paneltitle"><?php _e('Booking time range <span>Define the time range in wich customers may book an appointment</span>', 'salon-booking-system');?></h2>
    <div class="collapse sln-box__panelcollapse">
    <div class="row">
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-select  sln-select--info-label">
        <label for="salon_settings_hours_before_from"><?php esc_html_e('Range starts', 'salon-booking-system');?></label>
        <div class="row">
            <div class="col-xs-7">
                <?php $field = "salon_settings[hours_before_from]";?>
                <?php echo SLN_Form::fieldSelect(
	$field,
	SLN_Func::getIntervalItems(),
	$from,
	array(),
	true
) ?>
            </div>
            <div class="col-xs-5 sln-label--big"><label for="salon_settings_hours_before_from"><?php esc_html_e('Minimum', 'salon-booking-system');?></label></div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-select   sln-select--boxedoptions sln-select--info-label">
        <label for="salon_settings_hours_before_to"><?php esc_html_e('Range ends', 'salon-booking-system');?></label>
        <div class="row">
            <div class="col-xs-7">
                <?php $field = "salon_settings[hours_before_to]";?>
                <?php echo SLN_Form::fieldSelect(
	$field,
	SLN_Func::getIntervalItems(),
	$to,
	array(),
	true
) ?>
            </div>
            <div class="col-xs-5 sln-label--big"><label for="salon_settings_hours_before_to"><?php esc_html_e('Maximum', 'salon-booking-system');?></label></div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-4 sln-box-maininfo  align-top">
        <p class="sln-box-info"><?php esc_html_e('If you want for example that your customer can make a reservation up to two days before the appointment date and from a maximum of one month before the appointment date use this range options to set your desired rule.', 'salon-booking-system');?></p>
    </div>
</div>
</div>
</div>
