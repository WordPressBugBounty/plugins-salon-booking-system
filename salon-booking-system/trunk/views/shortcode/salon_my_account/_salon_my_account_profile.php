<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$current_user = wp_get_current_user();
$plugin       = SLN_Plugin::getInstance();
$values       = array();
$sms_prefix = '';
$errors = isset($sln_update_profile) && is_array($sln_update_profile) && isset($sln_update_profile['errors']) ? $sln_update_profile['errors'] : array();

$last_update = get_user_meta(get_current_user_id(), '_sln_last_update', true);

?>
<form method="post"  role="form" id="salon-my-account-profile-form" enctype="multipart/form-data">
    <input type="hidden" name="action" value="sln_update_profile">
    <?php wp_nonce_field('slnUpdateProfileNonce', 'slnUpdateProfileNonceField');?>
    <div class="container-fluid">
        <div class="row">
            <?php foreach (SLN_Enum_CheckoutFields::forCustomer()->appendPassword() as $key => $field):
                $value = $field->getValue(get_current_user_id());
                $type = $field['type'];
                $width = $field['widht'];
                $style_class = ($size == '900' ? 'col-sm-' : 'col-sm-6 col-md-') . ($key == 'address' ? 12 : $width);
            ?>
            <div class="<?php echo $style_class; ?> field-<?php echo $key; ?> sln-<?php echo $type; ?> <?php echo $type !== 'checkbox' ? 'sln-input sln-input--simple' : '';?>">
                <?php if($type == 'html'){
                    echo $field['default_value'];
                }else{?>
                    <label for="<?php echo SLN_Form::makeID("sln[{$key}]")?>"><?php esc_html_e(sprintf('%s', $field['label']), 'salon-booking-system'); ?></label>
                    <?php switch($key){
                        case 'password':
                            SLN_Form::fieldText("sln[{$key}]", '', array('type' => 'password'));
                            break;
                        case 'email':
                            SLN_Form::fieldText("sln[{$key}]", $value, array('required' => $field->isRequired(), 'type' => 'email'));
                            break;
                        case 'sms_prefix':
                            
                            break;
                        case 'phone':
                            $sms_prefix = get_user_meta(get_current_user_id(), '_sln_sms_prefix', true);
                            $sms_prefix = $sms_prefix ? $sms_prefix : $plugin->getSettings()->get('sms_prefix');
                            SLN_Form::fieldText('sln[sms_prefix]', $sms_prefix, array('type' => 'hidden'));
                        default:
                            if($type){
                                $additional_opts = array(
                                    "sln[{$key}]",
                                    $value,
                                    array('required' => $field->isRequired()),
                                );
                                if(!empty($sms_prefix) && trim($key) == 'phone'){
                                    $additional_opts[2]['type'] = 'tel';
                                }else{
                                    $additional_opts[2]['type'] = 'text';
                                }
                                $method_name = 'field'.ucfirst($type);
                                switch($type){
                                    case 'checkbox':
                                        $additional_opts = array(
                                            "sln[{$key}]",
                                            $value,
                                            '',
                                            array('required' => $field->isrequired()),
                                        );
                                        $method_name .= 'Button';
                                        break;
                                    case 'select':
                                        $additional_opts[3] = array_merge($additional_opts[2], array(
                                            'attrs' => array('data-placeholder' => __('select an option', 'salon-booking-system')),
                                            'empty_value' => true,
                                        ));
                                        $additional_opts[2] = $additional_opts[1];
                                        $additional_opts[1] = $field->getSelectOptions();
                                        $additional_opts[] = true;
                                        break;
                                    case 'file':
                                        if($field->getFileType()){
                                            $additional_opts[2] = array_merge($field->getFileType(), $additional_opts[2]);
                                        }
                                        break;
                                }
                                call_user_func_array(array('SLN_Form', $method_name), $additional_opts);
                            }else{
                                SLN_Form::fieldText("sln[{$key}]", $value, array('required' => $field->isRequiredNotHidden()));
                            }
                    }
                } ?>
            </div>
            <?php endforeach?>
        </div>
        <div class="row">
            <div class="col-xs-12 col-sm-6 col-sm-push-6 sln-form-actions">
                <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth">
                   <input type="submit" id="sln-accout-profile-submit" name="sln-accout-profile-submit" value="<?php esc_html_e('Update Profile','salon-booking-system');?>">
                </div>
            </div>
        </div>
	<div class="row">
	    <div class="col-xs-12 col-sm-6 col-sm-push-6 sln-account--last-update">
		<?php if ($last_update): ?>
		     <?php echo sprintf(
                    // translators: %1$s will be replaced by the last update date, %2$s will be replaced by the last update time
				    esc_html__('Last update on %1$s at %2$s', 'salon-booking-system'), $plugin->format()->date((new SLN_DateTime())->setTimestamp($last_update)), $plugin->format()->time((new SLN_DateTime())->setTimestamp($last_update))); ?>
		<?php endif; ?>
	    </div>
	</div>
    </div>
    <div class="row">
        <div class="col-xs-12">
                <div class="row" <?php    if(!$errors) echo 'style="display:none;"' ?>>
                    <div class="statusContainer col-md-12 sln-notifications--fix--tr">
                        <?php if ($errors): ?>
                            <?php foreach ($errors as $error): ?>
                                <div class="sln-alert sln-alert--problem"><?php echo $error ?></div>
                            <?php endforeach ?>
                        <?php endif ?>
                    </div>
                </div>
        </div>
    </div>
</form>
