<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$plugin = SLN_Plugin::getInstance();
include $this->plugin->getViewFile('admin/utilities/settings_inpage_navbar');
sum(
    // link anchor, link text
    array('#sln-checkout_options', __('Checkout options', 'salon-booking-system')),
    array('#sln-facebook_login', __('Facebook login', 'salon-booking-system')),
    array('#sln-checkout_form_fields', __('Checkout form fields', 'salon-booking-system')),
    array('#sln-advanced_discount_system', __('Advanced Discount System', 'salon-booking-system')),
    array('#sln-services_selection_limit', __('Services selection limit', 'salon-booking-system')),
    array('#sln-booking_notes', __('Booking notes', 'salon-booking-system'))
);
?>
<div id="sln-checkout_options" class="sln-box sln-box--main sln-box--haspanel  sln-box--haspanel--open">
    <h2 class="sln-box-title sln-box__paneltitle sln-box__paneltitle--open"><?php esc_html_e('Checkout options', 'salon-booking-system') ?></h2>
    <div class="collapse in sln-box__panelcollapse">
        <div class="row">
            <div class="col-xs-12 col-md-6 ">
                <div class="sln-checkbox">
                    <?php $this->row_input_checkbox('enabled_guest_checkout', __('Enable guest checkout', 'salon-booking-system')); ?>
                </div>
                <div class="sln-box-maininfo">
                    <p class="sln-box-info"><?php esc_html_e('If enabled users can checkout as a guest and no account will be created for them.', 'salon-booking-system') ?></p>
                </div>
            </div>
            <div class="col-xs-12 col-md-6 ">
                <div class="sln-checkbox">
                    <?php $this->row_input_checkbox('enabled_force_guest_checkout', __('Enable force guest checkout', 'salon-booking-system')); ?>
                </div>
                <div class="sln-box-maininfo">
                    <p class="sln-box-info"><?php esc_html_e('If enabled all users will checkout as a guest and no account will be created for them.', 'salon-booking-system') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="sln-facebook_login" class="sln-box sln-box--main sln-box--haspanel">
    <h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e('Facebook login', 'salon-booking-system') ?></h2>
    <div class="collapse sln-box__panelcollapse">
        <div class="row">
            <div class="col-xs-12 col-sm-6 col-md-3 ">
                <div class="sln-checkbox">
                    <?php $this->row_input_checkbox('enabled_fb_login', __('Enable Facebook login', 'salon-booking-system')); ?>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-3  sln-input--simple">
                <?php $this->row_input_text('fb_app_id', __('Facebook application ID', 'salon-booking-system')); ?>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-3  sln-input--simple">
                <?php $this->row_input_text('fb_app_secret', __('Facebook application Secret', 'salon-booking-system')); ?>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12 col-sm-6 sln-input--simple">
                <?php $this->row_input_text('fb_app_redirect', __('Facebook application Redirect URI', 'salon-booking-system'), array(
                    'default' => SLN_Helper_FacebookLogin::getRedirectUri(),
                    'attrs' => array(
                        'readonly' => 'readonly',
                    ),
                )); ?></div>
            <div class="col-xs-12 col-sm-6 sln-box-maininfo align-top">
                <p class="sln-box-info"><?php esc_html_e('Please, set this url to Facebook Login Valid Redirect URI. If empty, please set the Booking Page in Booking Rules settings', 'salon-booking-system') ?></p>
            </div>
        </div>
    </div>
</div>

<div id="sln-checkout_form_fields" class="sln-box sln-box--main sln-checkout-fields sln-box--haspanel">
    <h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e('Checkout form fields', 'salon-booking-system'); ?>
        <span class="block"><?php esc_html_e('Use this option to control the form fields to checkout', 'salon-booking-system') ?></span>
    </h2>
    <div class="collapse sln-box__panelcollapse">
        <div class="row sln-checkout-fields--header-wrapper" style='display:flex'>
            <div class="sln-checkout-fields--grip--cell sln-checkout-fields--cell sln-checkout-fields--header-cell"></div>
            <div class="col-xs-6 col-md-2 sln-checkout-fields--cell sln-checkout-fields--header-cell"></div>
            <div class="col-xs-3 col-md-2 sln-checkout-fields--cell sln-checkout-fields--header-cell"><?php esc_html_e('Field type', 'salon-booking-system') ?></div>
            <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell sln-checkout-fields--header-cell"><?php esc_html_e('Required', 'salon-booking-system') ?></div>
            <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell sln-checkout-fields--header-cell"><?php esc_html_e('User profile', 'salon-booking-system') ?></div>
            <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell sln-checkout-fields--header-cell"><?php esc_html_e('Hide on checkout', 'salon-booking-system') ?></div>
            <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell sln-checkout-fields--header-cell"><?php esc_html_e('Hide on booking', 'salon-booking-system') ?></div>
            <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell sln-checkout-fields--header-cell"><?php esc_html_e('Export to CSV', 'salon-booking-system') ?></div>
            <div class="col-xs-3 col-md-1 sln-checkout-fields--cell sln-checkout-fields--header-cell"></div>
            <div class="col-xs-3 col-md-1 sln-checkout-fields--cell sln-checkout-fields--header-cell"></div>
        </div>
        <div class="sln-checkout-fields--row-wrapper">
            <?php foreach (SLN_Enum_CheckoutFields::all() as $key => $field) {
                $disable = ['attrs' => ['disabled' => 'disabled']];
                $is_default = $field->isDefault();
                $is_required_by_default = $field->isRequiredByDefault();
                if (!$is_default) {
                    //remove excessive quotes escaping
                    $field->offsetSet('label', stripcslashes($field->offsetGet('label')));
                }
            ?>
                <div class="sln-checkout-fields--row" data-index="<?php echo esc_attr($key); ?>">
                    <div class="sln-checkout-fields--grip--cell sln-checkout-fields--cell "></div>
                    <div class="col-xs-6 col-md-2 sln-checkout-fields--cell">
                        <div class="sln_<?php echo esc_attr($key); ?>_label_cell sln_label_cell"><?php echo $field['label']; ?></div>
                    </div>
                    <div class="col-xs-3 col-md-2 sln-checkout-fields--cell">
                        <div class="sln_<?php echo esc_attr($key); ?>_type_cell"><?php echo SLN_Enum_CheckoutFields::$field_type[$field['type']]; ?></div>
                    </div>
                    <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell">
                        <div class="sln-checkbox sln-checkbox--checkonly">
                            <input name="salon_settings[checkout_fields][<?php echo esc_attr($key) ?>][required]" type="hidden" value="<?php echo $is_required_by_default && $field["required"] ? $field["required"] : 0 ?>" />
                            <?php SLN_Form::fieldCheckbox("salon_settings[checkout_fields][{$key}][required]", $field['required'], $is_required_by_default ? $disable : []) ?>
                            <label class="sln-checkout-fields--row--label"
                                for="salon_settings_checkout_fields_<?php echo esc_attr($key) ?>_required">
                        </div>
                    </div>
                    <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell">
                        <div class="sln-checkbox sln-checkbox--checkonly">
                            <input name="salon_settings[checkout_fields][<?php echo esc_attr($key) ?>][customer_profile]" type="hidden" value="<?php echo $is_default && $field["customer_profile"] ? $field["customer_profile"] : 0 ?>" />
                            <?php SLN_Form::fieldCheckbox("salon_settings[checkout_fields][{$key}][customer_profile]", $field['customer_profile'], $is_default ? $disable : []) ?>
                            <label class="sln-checkout-fields--row--label"
                                for="salon_settings_checkout_fields_<?php echo esc_attr($key) ?>_customer_profile">
                        </div>
                    </div>
                    <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell">
                        <div class="sln-checkbox sln-checkbox--checkonly">
                            <input name="salon_settings[checkout_fields][<?php echo esc_attr($key) ?>][hidden]" type="hidden" value="<?php echo $is_required_by_default && $field["hidden"] ? $field["hidden"] : 0 ?>" />
                            <?php SLN_Form::fieldCheckbox("salon_settings[checkout_fields][{$key}][hidden]", $field['hidden'], $is_required_by_default ? $disable : []) ?>
                            <label class="sln-checkout-fields--row--label"
                                for="salon_settings_checkout_fields_<?php echo esc_attr($key) ?>_hidden">
                        </div>
                    </div>
                    <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell">
                        <div class="sln-checkbox sln-checkbox--checkonly">
                            <input name="salon_settings[checkout_fields][<?php echo esc_attr($key) ?>][booking_hidden]" type="hidden" value="<?php echo $is_required_by_default && $field["booking_hidden"] ? $field["booking_hidden"] : 0 ?>" />
                            <?php SLN_Form::fieldCheckbox("salon_settings[checkout_fields][{$key}][booking_hidden]", $field['booking_hidden'], $is_required_by_default ? $disable : []) ?>
                            <label class="sln-checkout-fields--row--label"
                                for="salon_settings_checkout_fields_<?php echo esc_attr($key) ?>_booking_hidden">
                        </div>
                    </div>
                    <div class="visible-lg col-xs-3 col-md-1 sln-checkout-fields--cell">
                        <div class="sln-checkbox sln-checkbox--checkonly">
                            <input name="salon_settings[checkout_fields][<?php echo esc_attr($key) ?>][export_csv]" type="hidden" value="<?php echo $is_required_by_default && $field["export_csv"] ? $field["export_csv"] : 0 ?>" />
                            <?php SLN_Form::fieldCheckbox("salon_settings[checkout_fields][{$key}][export_csv]", $field['export_csv']) ?>
                            <label class="sln-checkout-fields--row--label"
                                for="salon_settings_checkout_fields_<?php echo esc_attr($key) ?>_export_csv">
                        </div>
                    </div>
                    <div class="col-xs-3 col-md-1 sln-checkout-fields--cell">
                        <div class="sln-custom-field-button sln-custom-fields-edit  sln-icon--edit "></div>
                    </div>
                    <div class="col-xs-3 col-md-1 sln-checkout-fields--cell">
                        <div class="sln-custom-field-button sln-custom-fields-delete sln-icon--trash" <?php if ($is_default) {
                                                                                                            echo 'style="display:none"';
                                                                                                        } ?>></div>
                    </div>
                    <?php
                    foreach (['label', 'type', 'width', 'options', 'additional', 'default_value', 'file_type'] as $key2) {
                        if ($key2 == 'file_type') {
                            SLN_Form::fieldText("salon_settings[checkout_fields][{$key}][{$key2}]", is_array($field[$key2]) ? implode(',', $field[$key2]) : $field[$key2], ['type' => 'hidden', 'attrs' => ['multiple' => 'multiple']]);
                        } else if ($key2 == 'options') {
                            SLN_Form::fieldTextarea("salon_settings[checkout_fields][{$key}][{$key2}]", is_array($field[$key2]) ? implode(',', $field[$key2]) : $field[$key2], ['attrs' => ['hidden' => 'hidden']]);
                        } else {
                            SLN_Form::fieldText("salon_settings[checkout_fields][{$key}][{$key2}]", is_array($field[$key2]) ? implode(',', $field[$key2]) : $field[$key2], ['attrs' => ['hidden' => 'hidden']]);
                        }
                    } ?>
                </div>
            <?php } ?>
        </div>
        <div class="sln-box sln-box-fields-editor">
            <div id="fields-editor" class="fields-editor" data-mode="new">
                <div class="row close-row">
                    <div type="button" class="fields-editor-close"></div>
                </div>
                <div class="fields-editor-main-row row">
                    <div class="col-xs-12 col-md-6 col-lg-3 form-group sln-input--simple">
                        <label class="sln-checkout-fields--row--label"
                            for="fields_editor_label"><?php esc_html_e('Field name', 'salon-booking-system') ?></label>
                        <?php SLN_Form::fieldText("fields_editor[label]", '') ?>
                    </div>
                    <div class="col-xs-12 col-md-6 col-lg-2 form-group sln-select">
                        <label class="sln-checkout-fields--row--label"
                            for="fields_editor_type"><?php esc_html_e('Field type', 'salon-booking-system') ?></label>
                        <?php SLN_Form::fieldSelect('fields_editor[type]', SLN_Enum_CheckoutFields::$field_type, 'text', [], true); ?>
                    </div>
                    <div class="col-xs-12 col-md-6 col-lg-2 form-group sln-select">
                        <label class="sln-checkout-fields--row--label"
                            for="fields_editor_width"><?php esc_html_e('Field width', 'salon-booking-system') ?></label>
                        <?php SLN_Form::fieldSelect('fields_editor[width]', SLN_Enum_CheckoutFields::$field_widths, 6, [], true); ?>
                    </div>
                    <div class="col-xs-12 col-md-6 col-lg-3 form-group  sln-field-editor-default-value-col sln-select">
                        <div id="sln-fields-editor-default-field-checkbox-wrapper" class="sln-checkbox"
                            style="display:none;">
                            <?php SLN_Form::fieldCheckbox("fields_editor[default_value]", false, ['attrs' => ['hidden' => 'hidden']]) ?>
                            <label class="sln-checkout-fields--row--label"
                                for="fields_editor_default_value"><?php esc_html_e('Field default value', 'salon-booking-system') ?></label>
                        </div>
                        <div id="sln-field-editor-file-type-select-wrapper" class="sln-select" style="display:none">
                            <label class="sln-checkout-field--row--label"
                                from="field_editor_file_type"><?php esc_html_e('Select file type', 'salon-booking-system'); ?></label>
                            <?php SLN_Form::fieldSelect('fields_editor[file_type]', SLN_Enum_CheckoutFields::$file_type, 'png', ['attrs' => ['hidden' => 'hidden', 'multiple' => 'multiple']]) ?>
                        </div>
                        <div id="sln-fields-editor-default-field-text-wrapper">
                            <label class="sln-checkout-fields--row--label"
                                for="fields_editor_default_value"><?php esc_html_e('Field default value', 'salon-booking-system') ?></label>
                            <?php SLN_Form::fieldText('fields_editor[default_value]', ''); ?>
                        </div>
                    </div>
                    <div class="visible-lg col-md-2 sln-field-editor-button-col">
                        <!-- <div class="sln-btn sln-btn--main sln-btn--big"></div> -->
                        <button class="sln-btn sln-btn--main--tonal sln-btn--big sln-btn--icon sln-icon--file field-editor-button" type="button"><?php esc_html_e('Add Field', 'salon-booking-system') ?></button>
                    </div>
                    <div class="row hidden-lg">
                        <div class="col-xs-6">
                            <div class="sln-checkbox"><?php SLN_Form::fieldCheckbox("fields_editor[required]", false) ?>
                                <label class="sln-checkout-fields--row--label"
                                    for="fields_editor_required"><?php esc_html_e('Required', 'salon-booking-system') ?></label>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="sln-checkbox"><?php SLN_Form::fieldCheckbox("fields_editor[customer_profile]", false) ?>
                                <label class="sln-checkout-fields--row--label"
                                    for="fields_editor_customer_profile"><?php esc_html_e('User profile', 'salon-booking-system') ?></label>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="sln-checkbox"><?php SLN_Form::fieldCheckbox("fields_editor[hidden]", false) ?>
                                <label class="sln-checkout-fields--row--label"
                                    for="fields_editor_hidden"><?php esc_html_e('Hide on checkout', 'salon-booking-system') ?></label>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="sln-checkbox"><?php SLN_Form::fieldCheckbox('fields_editor[booking_hidden]', false) ?>
                                <label class="sln-checkout-fields--row--label"
                                    for="fields_editor_booking_hidden"><?php esc_html_e('Hide on booking', 'salon-booking-system') ?></label>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="sln-checkbox"><?php SLN_Form::fieldCheckbox('fields_editor[export_csv]', false) ?>
                                <label class="sln-checkout-fields--row--label"
                                    for="fields_editor_export_csv"><?php esc_html_e('Export to CSV', 'salon-booking-system') ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12 col-sm-9 col-lg-6  form-group sln-input--simple" style="display:none;">
                            <label for="fields_editor_options"><?php esc_html_e('Enter the list of options in the value:label format (one per row)', 'salon-booking-system') ?></label>
                            <?php SLN_Form::fieldTextarea("fields_editor[options]", ''); ?>
                        </div>
                        <div class="hidden-lg col-xs-12  sln-field-editor-button-col">
                            <div class="sln-btn sln-btn--main sln-btn--big">
                                <button class="btn field-editor-button" type="button">Add Field</button>
                            </div>
                        </div>
                    </div>
                    <?php SLN_Form::fieldText("fields_editor[additional]", true, ['attrs' => ['hidden' => 'hidden']]); ?>
                </div>
            </div>
        </div>
    </div>
</div>
    <div class="row">
        <div class="col-xs-12 col-sm-6">
            <div id="sln-advanced_discount_system" class="sln-box sln-box--main sln-box--main--small">
                <h2 class="sln-box-title"><?php esc_html_e('Advanced Discount System', 'salon-booking-system') ?></h2>
                <div class="row">
                    <div class="col-xs-12 ">
                        <div class="sln-checkbox">
                            <?php $this->row_input_checkbox('enable_discount_system', __('Enable', 'salon-booking-system')); ?>
                        </div>
                        <div class="sln-box-maininfo">
                            <p class="sln-box-info">
                                <?php esc_html_e('Check this box if you want to enable the Discount section', 'salon-booking-system') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php do_action('sln.views.settings.checkout.after_discount', $this) ?>
        </div>
        <div class="col-xs-12 col-sm-6">
            <div id="sln-services_selection_limit" class="sln-box sln-box--main sln-box--main--small">
                <h2 class="sln-box-title"><?php esc_html_e('Services selection limit', 'salon-booking-system') ?></h2>
                <div class="row">
                    <div class="col-xs-12  sln-select ">
                        <label for="salon_settings_primary_services_count"><?php esc_html_e('Primary service', 'salon-booking-system') ?></label>
                        <?php echo SLN_Form::fieldSelect(
                            'salon_settings[primary_services_count]',
                            array(
                                '' => esc_html__("No limits", 'salon-booking-system'),
                                '1' => "1",
                                '2' => "2",
                                '3' => "3",
                                '4' => "4",
                                '5' => "5",
                                '6' => "6",
                                '7' => "7",
                                '8' => "8",
                                '9' => "9",
                                '10' => "10",
                            ),
                            $this->settings->get('primary_services_count'),
                            array(),
                            true
                        ) ?>
                    </div>
                    <div class="sln-box-maininfo">
                        <p class="sln-box-info">
                            <?php esc_html_e('Set this option if you want to limit the number of services bookable during a single reservation.', 'salon-booking-system'); ?>
                        </p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12  sln-select ">
                        <label for="salon_settings_secondary_services_count"><?php esc_html_e('Secondary services', 'salon-booking-system') ?></label>
                        <?php echo SLN_Form::fieldSelect(
                            'salon_settings[secondary_services_count]',
                            array(
                                '' => esc_html__("No limits", 'salon-booking-system'),
                                '1' => "1",
                                '2' => "2",
                                '3' => "3",
                                '4' => "4",
                                '5' => "5",
                                '6' => "6",
                                '7' => "7",
                                '8' => "8",
                                '9' => "9",
                                '10' => "10",
                            ),
                            $this->settings->get('secondary_services_count'),
                            array(),
                            true
                        ) ?>
                    </div>
                    <div class="sln-box-maininfo">
                        <p class="sln-box-info">
                            <?php esc_html_e('Set this option if you want to limit the number of services bookable during a single reservation.', 'salon-booking-system'); ?>
                        </p>
                    </div>
                </div>
                <div class="row <?php echo $this->settings->get('secondary_services_count') ? '' : 'hide' ?>">
                    <div class="col-xs-12 ">
                        <div class="sln-checkbox">
                            <?php $this->row_input_checkbox('is_secondary_services_selection_required', __('Make service selection required', 'salon-booking-system')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12 col-sm-6">
            <div id="sln-booking_notes" class="sln-box sln-box--main">
                <h2 class="sln-box-title"><?php esc_html_e('Booking notes', 'salon-booking-system') ?></h2>
                <div>
                    <div class="row">
                        <div class="col-xs-12 col-sm-12 sln-input--simple">
                            <?php $this->row_input_textarea(
                                'gen_timetable',
                                __('Use this field to provide your customers important infos about terms and conditions of their reservation.', 'salon-booking-system'),
                                array(
                                    'help' => __('Will be displayed on checkout page before booking completition.', 'salon-booking-system'),
                                    'textarea' => array(
                                        'attrs' => array(
                                            'rows' => 5,
                                            'placeholder' => __("e.g. In case of delay we will take your seat for 15 minutes, then your booking priority will be lost", 'salon-booking-system'),
                                        ),
                                    ),
                                )
                            ); ?>
                        </div>
                        <div class="col-xs-12 col-sm-4  sln-box-maininfo align-top">
                            <p class="sln-input-help"><?php __('-', 'salon-booking-system') ?></p>
                        </div>
                    </div>
                    <!-- SE SERVONO MAGGIORI INFO
	<div class="sln-box-info">
	   <div class="sln-box-info-trigger"><button class="sln-btn sln-btn--main sln-btn--small sln-btn--icon sln-icon--info">info</button></div>
	   <div class="sln-box-info-content row">
	   <div class="col-xs-12 col-sm-8 col-md-4">
	   <h5>Sed eget metus vitae enim suscipit scelerisque non sed neque. Mauris semper hendrerit erat, in consectetur arcu eleifend at. Donec orci lacus, euismod euismod luctus sed, rhoncus in tellus. Mauris tempus arcu ut luctus venenatis.</h5>
	    </div>
	    </div>
	    <div class="sln-box-info-trigger"><button class="sln-btn sln-btn--main sln-btn--small sln-btn--icon sln-icon--close">info</button></div>
	</div>
	-->
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-6">
            <div id="sln-last_step_note" class="sln-box sln-box--main">
                <h2 class="sln-box-title"><?php esc_html_e('Last step note', 'salon-booking-system') ?></h2>
                <div>
                    <div class="row">
                        <div class="col-xs-12 col-sm-12  sln-input--simple">
                            <?php $this->row_input_textarea(
                                'last_step_note',
                                __('Use this field to provide your customers important infos about terms and conditions of their reservation.', 'salon-booking-system'),
                                array(
                                    'help' => __('Will be displayed on thankyou page. You can use [SALON PHONE], [SALON EMAIL]', 'salon-booking-system'),
                                    'textarea' => array(
                                        'attrs' => array(
                                            'rows' => 5,
                                            'placeholder' => __("e.g. You will receive a booking confirmation by email.If you do not receive an email in 5 minutes, check your Junk Mail or Spam Folder. If you need to change your reservation, please call <strong>[SALON PHONE]</strong> or send an e-mail to <strong>[SALON EMAIL]</strong>", 'salon-booking-system'),
                                        ),
                                    ),
                                )
                            ); ?>
                        </div>
                        <div class="col-xs-12 col-sm-4  sln-box-maininfo align-top">
                            <p class="sln-input-help"><?php __('-', 'salon-booking-system') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12 col-sm-6">
            <div id="sln-customer-fidelity-score" class="sln-box sln-box--main sln-box--main--small sln-profeature <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-profeature--disabled sln-profeature__tooltip-wrapper' : '' ?>">
                <?php echo $plugin->loadView(
                    'metabox/_pro_feature_tooltip',
                    array(
                        // 'cta_url' => 'https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO',
                        'trigger' => 'sln-customer-fidelity-score',
                        'additional_classes' => 'sln-profeature--box',
                    )
                ); ?>
                <h2 class="sln-box-title"><?php esc_html_e('Customer fidelity score', 'salon-booking-system') ?></h2>
                <div class="row">
                    <div class="col-xs-12 ">
                        <div class="sln-checkbox <?php echo !defined("SLN_VERSION_PAY") ? 'sln-customer-fidelity-score-disabled' : '' ?>">
                            <div class="sln-customer-fidelity-score--checkbox">
                                <?php $this->row_input_checkbox('enable_customer_fidelity_score', __('Enable', 'salon-booking-system')); ?>
                                <div class="sln-box-maininfo">
                                    <p class="sln-box-info">
                                        <?php esc_html_e('Customers will collect a score based on their bookings value and their retention.', 'salon-booking-system') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-6">
            <div id="sln-disable_summary_skip_countdown" class="sln-box sln-box--main sln-box--main--small sln-profeature <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-profeature--disabled sln-profeature__tooltip-wrapper' : '' ?>">
                <?php echo $plugin->loadView(
                    'metabox/_pro_feature_tooltip',
                    array(
                        // 'cta_url' => 'https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO',
                        'trigger' => 'sln-customer-fidelity-score',
                        'additional_classes' => 'sln-profeature--box',
                    )
                ); ?>
                <h2 class="sln-box-title"><?php esc_html_e('Disable countdown on booking completion', 'salon-booking-system') ?></h2>
                <div class="row">
                    <div class="col-xs-12 ">
                        <div class="sln-checkbox <?php echo !defined("SLN_VERSION_PAY") ? 'sln-customer-fidelity-score-disabled sln-profeature__tooltip-wrapper' : '' ?>">
                            <div class="sln-customer-fidelity-score--checkbox">
                                <?php $this->row_input_checkbox('disable_summary_skip_countdown', __('Disable', 'salon-booking-system')); ?>
                                <div class="sln-box-maininfo">
                                    <p class="sln-box-info">
                                        <?php esc_html_e('After the summing up step, skip the countdown to go to the "Thank You" page.', 'salon-booking-system') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- <div class="col-xs-12 col-sm-6">
        <div id="sln-customer-fidelity-score" class="sln-box sln-box--main sln-box--main--small">
            <h2 class="sln-box-title"><?php //_e('Skip summary step', 'salon-booking-system')
                                        ?></h2>
            <div class="row">
                <div class="col-xs-12 ">
                    <div class="sln-checkbox <?php //echo !defined("SLN_VERSION_PAY") ? 'sln-customer-fidelity-score-disabled sln-profeature__tooltip-wrapper' : '' 
                                                ?>">
                        <span class="sln-profeature__tooltip">
                            <a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO" target="_blank">
                                <?php //echo __('Switch to PRO to unlock this feature', 'salon-booking-system') 
                                ?>
                            </a>
                        </span>
                        <div class="sln-customer-fidelity-score--checkbox">
                            <?php //$this->row_input_checkbox('enable_skip_summary_step', __('Enable', 'salon-booking-system'));
                            ?>
                            <div class="sln-box-maininfo">
                                <p class="sln-box-info">
                                   <?php //_e('Skip summary step if custom fields are not set as required and are not empty then the “Summary step”', 'salon-booking-system')
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> -->
    </div>