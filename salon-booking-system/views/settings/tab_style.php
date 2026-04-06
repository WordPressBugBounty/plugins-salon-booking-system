<?php
/**
 * @var $this SLN_Plugin
 */
$enum = new SLN_Enum_ShortcodeStyle();
$curr = $this->settings->getStyleShortcode();
$colors = $this->settings->get('style_colors') ? $this->settings->get('style_colors') : array();
include $this->plugin->getViewFile('admin/utilities/settings_inpage_navbar');
sum(
	// link anchor, link text
	array('#sln-booking_form_layout', __('Select booking form layout', 'salon-booking-system')),
	array('#sln-custom_colors', __('Custom colors', 'salon-booking-system')),
	array('#sln-ajax_steps', __('Ajax steps', 'salon-booking-system')),
	array('#sln-disable_bootstrap_assets', __('Bootstrap assets', 'salon-booking-system'))
);
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
    <div id="sln-booking_form_layout" class="sln-box sln-box--main sln-box--haspanel sln-box--haspanel--open">
        <h2 class="sln-box-title sln-box__paneltitle sln-box__paneltitle--open">
            <?php esc_html_e('Select your favorite booking form layout', 'salon-booking-system');?>
            <span><?php esc_html_e('Choose the one that best fits your page', 'salon-booking-system');?></span>
        </h2>
        <div class="collapse in sln-box__panelcollapse">
        <div class="row">
            <?php foreach ($enum->toArray() as $key => $label):
?>
                <div class="col-sm-4">
                    <div class="sln-radiobox__wrapper--bd">
                        <div class="sln-radiobox sln-radiobox--fullwidth">
                        <input type="radio" name="salon_settings[style_shortcode]"
                               value="<?php echo esc_attr($key) ?>"
                               id="style_shortcode_<?php echo esc_attr($key) ?>"
                            <?php echo ($curr == $key) ? 'checked="checked"' : '' ?> >
                        <label for="style_shortcode_<?php echo esc_attr($key) ?>"><?php echo esc_attr($label) ?></label>
                        </div>
                        <div class="sln-box-maininfo">
                            <p class="sln-box-info"><?php echo esc_attr($enum->getDescription($key)) ?></p>
                        </div>
                       
                        <label class="sln-radiobox__wrapper__labelfull" for="style_shortcode_<?php echo esc_attr($key) ?>"></label>
                    </div>
                </div>
            <?php endforeach?>

            <div class="clearfix"></div>
        </div>
        </div>
    </div>
    <div id="sln-custom_colors" class="sln-box sln-box--main sln-box--haspanel">
                <h2 class="sln-box-title sln-box__paneltitle">
                    <?php esc_html_e('Custom colors', 'salon-booking-system');?>
                    <span><?php esc_html_e('Choose the one that best fits your page', 'salon-booking-system');?></span>
                </h2>
<div class="collapse sln-box__panelcollapse">
        <div class="row">
            <div class="col-xs-12 col-sm-6">
                <div class="sln-switch">
                <?php $this->row_input_checkbox_switch(
	'style_colors_enabled',
	'Custom colors',
	array(
		'bigLabelOn' => __('Custom colors are enabled', 'salon-booking-system'),
		'bigLabelOff' => __('Custom colors are disabled', 'salon-booking-system'),
	)
);?>
    </div>
                <div class="sln-box-maininfo">
                    <p class="sln-box-info"><?php esc_html_e('Customize colors of the salon shortcode.', 'salon-booking-system');?></p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12 col-lg-8 sln-colors-sample">
                <div class="wrapper">
                    <h1 class="sln-box-title"><?php esc_html_e('Sample page/step title', 'salon-booking-system')?></h1>
                    <label><?php esc_html_e('Sample label', 'salon-booking-system')?></label><br>
                    <input type="text" value="<?php esc_html_e('Sample input', 'salon-booking-system')?>" /><br>
                    <button value="Sample button"><?php esc_html_e('Sample button', 'salon-booking-system')?> <i class="glyphicon glyphicon-chevron-right"></i></button>
                    <p>
                        Sample text. Pellentesque viverra dictum lectus eu fringilla. Nam metus sapien, pharetra id nunc sit amet, feugiat auctor ipsum.
                    </p>
                    <p>
                        Sample text. Pellentesque viverra dictum lectus eu fringilla. Nam metus sapien, pharetra id nunc sit amet, feugiat auctor ipsum.
                    </p>
                    <small class="sln-input-help">Morbi non erat elementum neque lacinia finibus. Sed rutrum viverra tortor. Sed laoreet, quam vestibulum molestie laoreet, dui justo egestas.</small>

                </div>
            </div>
            <div class="col-xs-12 col-lg-4">  
            <?php
            /* CUSTOM COLORS / DISPLAY ALL NUANCES */
            $current_user = wp_get_current_user();
            if ($current_user->user_email == "me@nicovece.com") {
                $colors = $this->settings->get('style_colors');
                if ($colors) {
                    foreach ($colors as $k => $v) {
                        echo '<div style="background-color:' . esc_attr($v) . ';">' . esc_attr($k) . ' - ' . esc_attr($v) . '<br></div>';
                    }
                }
            }
            ?>
            <div class="row">
                    <div id="color-background" class="col-xs-12 col-sm-4  col-lg-12 sln-input--simple sln-colorpicker">
                        <label><?php esc_html_e('Background color', 'salon-booking-system');?></label>
                        <div class="sln-colorpicker--subwrapper">
                            <span id="thisone" class="input-group-addon sln-colorpicker-addon"><i>color sample</i></span>
                            <input type="text" value="<?php echo isset($colors['background-a']) ? esc_attr($colors['background-a']) : 'rgba(255, 255, 255, 1)' ?>" class="sln-input sln-input--text  sln-colorpicker--trigger" />
                        </div>
                    </div>
                    <div id="color-main" class="col-xs-12 col-sm-4  col-lg-12 sln-input--simple sln-colorpicker">
                        <label for="salon_settings_gen_name"><?php esc_html_e('Main color', 'salon-booking-system');?></label>
                        <div class="sln-colorpicker--subwrapper">
                            <span id="thisone" class="input-group-addon sln-colorpicker-addon"><i>color sample</i></span>
                            <input type="text" value="<?php echo isset($colors['main-a']) ? esc_attr($colors['main-a']) : 'rgba(2,119,189,1)' ?>" class="sln-input sln-input--text  sln-colorpicker--trigger" />
                        </div>
                    </div>
                    <div id="color-text" class="col-xs-12 col-sm-4  col-lg-12 sln-input--simple sln-colorpicker">
                        <label for="salon_settings_gen_name"><?php esc_html_e('Text color', 'salon-booking-system');?></label>
                        <div class="sln-colorpicker--subwrapper">
                            <span id="thisone" class="input-group-addon sln-colorpicker-addon"><i>color sample</i></span>
                            <input type="text" value="<?php echo isset($colors['text-a']) ? esc_attr($colors['text-a']) : 'rgba(68,68,68,1)' ?>" class="sln-input sln-input--text  sln-colorpicker--trigger" />
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-6  col-lg-12 form-group sln-box-maininfo">
                        <?php foreach (array('background-a', 'background-b', 'background-c', 'background-d', 'main-a', 'main-b', 'main-c', 'main-d', 'text-a', 'text-b', 'text-c', 'text-d', 'text-e') as $k): ?>
                            <input class="hidden" name="salon_settings[style_colors][<?php echo esc_attr($k) ?>]" id="color-<?php echo esc_attr($k) ?>" type="text" value="<?php echo isset($colors[$k]) ? esc_attr($colors[$k]) : '' ?>">
                        <?php endforeach?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <div class="row">
    <div class="col-xs-12 col-sm-8 col-md-4">
    <div id="sln-ajax_steps" class="sln-box sln-box--main sln-box--main--small">
    <h2 class="sln-box-title">
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction
        _e('Ajax steps <span>This allows loading steps via ajax</span>', 'salon-booking-system')?>
    </h2>
    <div class="row">
            <div class="col-xs-12">
                <div class="sln-checkbox">
            <?php $this->row_input_checkbox('ajax_enabled', __('Enable ajax steps', 'salon-booking-system'));?>
            </div>
            <div class="sln-box-maininfo">
                <p class="sln-box-info"><?php esc_html_e('This allows loading steps via ajax for a more smooth booking form transition.', 'salon-booking-system')?></p>
            </div>
            </div>
        </div>
    </div>
    </div>
    <div class="col-xs-12 col-sm-8 col-md-8">
    <div id="sln-disable_bootstrap_assets" class="sln-box sln-box--main sln-box--main--small">
    <h2 class="sln-box-title"><?php esc_html_e('Disable bootstrap assets', 'salon-booking-system')?></h2>
    <div class="row">
            <div class="col-xs-12 col-md-6">
                <div class="sln-checkbox">
                <?php $this->row_input_checkbox('no_bootstrap', __('CSS on front-end', 'salon-booking-system'));?>
                </div>
                <div class="sln-box-maininfo">
                    <p class="sln-box-info"><?php esc_html_e('Use it in case of conflicts with your theme', 'salon-booking-system')?></p>
                </div>
            </div>
            <div class="col-xs-12 col-md-6">
                <div class="sln-checkbox">
                <?php $this->row_input_checkbox('no_bootstrap_js', __('JS on front-end', 'salon-booking-system'));?>
                </div>
                <div class="sln-box-maininfo">
                    <p class="sln-box-info"><?php esc_html_e('Use it in case of conflicts with your theme', 'salon-booking-system')?></p>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12 col-sm-8 col-md-4">
        <div id="sln-booking-modal" class="sln-box sln-box--main">
            <div class="row">
                <div class="col-xs-12">
                    <div class="sln-checkbox">
                        <?php $this->row_input_checkbox('replace_booking_modal_with_popup', __('Replace booking modal window with a pop-up', 'salon-booking-system'));?>
                    </div>
                    <div class="sln-box-maininfo">
                        <p class="sln-box-info"><?php esc_html_e('This allows replace booking modal window with a pop-up.', 'salon-booking-system')?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-8 col-md-4">
        <div id="sln-booking-modal" class="sln-box sln-box--main">
            <div class="row">
                <div class="col-xs-12">
                    <div class="sln-checkbox">
                        <?php $this->row_input_checkbox('disable_google_fonts', __('Disable Google fonts', 'salon-booking-system'));?>
                    </div>
                    <div class="sln-box-maininfo">
                        <p class="sln-box-info"><?php esc_html_e('Disable all Google fonts according to the standard GDPR.', 'salon-booking-system')?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-8 col-md-4">
        <div id="sln-booking-modal" class="sln-box sln-box--main">
            <div class="row">
                <div class="col-xs-12">
                    <div class="sln-checkbox">
                        <?php $this->row_input_checkbox('hide_service_duration', __('Hide service duration', 'salon-booking-system'));?>
                    </div>
                    <div class="sln-box-maininfo">
                        <p class="sln-box-info"><?php esc_html_e('When enabled, service duration will be hidden on booking form and email notification', 'salon-booking-system')?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


