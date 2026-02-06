<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var $plugin SLN_Plugin
 */
$enabled = $plugin->getSettings()->get('nested_bookings_enabled');
?>
<div id="sln-nested_bookings" class="sln-box sln-box--main sln-box--haspanel">
<h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e('Nested bookings using service break', 'salon-booking-system');?> <span><?php esc_html_e('Allow new bookings to start during service break periods.', 'salon-booking-system')?></span></h2>
<div class="collapse sln-box__panelcollapse">
<div class="row">
    <div class="col-xs-12 col-sm-6 sln-profeature <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-profeature--disabled sln-profeature__tooltip-wrapper' : '' ?>">
        <?php echo $plugin->loadView(
            'metabox/_pro_feature_tooltip',
            array(
                'trigger' => 'sln-nested_bookings',
                'additional_classes' => 'sln-profeature--box',
            )
        ); ?>
        <div class="sln-switch sln-moremargin--bottom <?php echo !defined("SLN_VERSION_PAY") ? 'sln-disabled' : '' ?>">
            <h6 class="sln-fake-label"><?php esc_html_e('Nested Bookings Feature', 'salon-booking-system');?></h6>
            <?php SLN_Form::fieldCheckboxSwitch(
                "salon_settings[nested_bookings_enabled]",
                defined("SLN_VERSION_PAY") ? $enabled : 0,
                __('Nested bookings ON', 'salon-booking-system'),
                __('Nested bookings OFF', 'salon-booking-system')
            )?>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 form-group sln-box-maininfo">
        <p class="sln-box-info">
            <?php esc_html_e('When enabled, customers can book services that start during another service\'s break period. This applies to all services that have a break time configured.', 'salon-booking-system');?>
        </p>
        <p class="sln-box-info">
            <strong><?php esc_html_e('Example:', 'salon-booking-system');?></strong><br>
            <?php esc_html_e('Service A: 16:00-18:00 with break at 17:00-17:30. With nested bookings enabled, Service B can start at 17:00 or 17:10, etc.', 'salon-booking-system');?>
        </p>
    </div>
</div>
</div>
</div>

