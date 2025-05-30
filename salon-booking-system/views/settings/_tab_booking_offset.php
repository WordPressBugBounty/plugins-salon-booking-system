<?php
/**
 * @var $plugin SLN_Plugin
 * @var $helper SLN_Admin_Settings
 */
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
$minutesBetweenReservation = $plugin->getSettings()->get('minutes_between_reservation');
?>
    <div id="sln-offset_between_reservations" class="sln-box sln-box--main sln-box--haspanel">
        <h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e('Offset between reservations', 'salon-booking-system');?></h2>
        <div class="collapse sln-box__panelcollapse">
        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-4 form-group sln-checkbox">
                <?php $helper->row_input_checkbox(
	'reservation_interval_enabled',
	__('Enable offset', 'salon-booking-system'),
	array('help' => __('Select this option to add an OFF interval between two sequencial reservations', 'salon-booking-system'))
);?>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-select ">
                <label><?php esc_html_e('Offset duration', 'salon-booking-system');?></label>
                <?php $field = "salon_settings[minutes_between_reservation]";?>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo SLN_Form::fieldSelect(
	$field,
	array(
		'5' => '5m',
		'10' => '10m',
		'15' => '15m',
		'20' => '20m',
		'25' => '25m',
		'30' => '30m',
		'35' => '35m',
		'40' => '40m',
		'45' => '45m',
		'50' => '50m',
		'55' => '55m',
		'60' => '1h',
		'65' => '1h 5m',
		'70' => '1h 10m',
		'75' => '1h 15m',
		'80' => '1h 20m',
		'85' => '1h 25m',
		'90' => '1h 30m',
		'95' => '1h 35m',
		'100' => '1h 40m',
		'105' => '1h 45m',
		'110' => '1h 50m',
		'115' => '1h 55h',
		'120' => '2h',
	),
    esc_attr($minutesBetweenReservation),
	array(),
	true
) ?>
                <p class="help-block"><?php esc_html_e('How many minutes lasts this Offset?', 'salon-booking-system')?></p>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-4 sln-box-maininfo  align-top">
                <p class="sln-box-info"><?php esc_html_e('Note that during the Offset interval new reservations will not be available.', 'salon-booking-system');?></p>
            </div>
        </div>
    </div>
</div>