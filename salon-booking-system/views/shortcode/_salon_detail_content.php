<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php
/**
 * @var array   $fields
 */

foreach($fields as $key => $field){
    $value = !$bb->get($key) && !is_null($field['default_value']) ? $field['default_value'] : $bb->get($key);
    $type = $field['type'];
    $width = $field['width'];
    if($key === 'password'){?>
        </div><div class="row">
        <?php do_action('sln.template.details.before_password', $bb, $size);
    }
    $style_class = 'col-xs-12';
    if($size != '400'){
        $style_class = ($size == '900'? 'col-sm-': 'col-sm-6 col-md-'). ($key == 'address' ? '12' : $width);
    }
    ?>

    <div class="<?php echo $style_class ?> field-<?php echo $key ?> sln-<?php echo $type ?> <?php echo $type !== 'checkbox'?'sln-input sln-input--simple':''?> <?php echo $field->isCustomer() && $field->isAdditional()? 'sln-customer-fields':''?>">
        <?php if($type == 'html'){
            echo $field['default_value'];
        }else{ ?>
            <label for="<?php echo SLN_Form::makeID("sln[{$key}]")?>"><?php esc_html_e(sprintf('%s', $field['label']), 'salon-booking-system'); ?></label>
            <?php switch($key){
                case 'password_confirm':
                case 'password':
                    SLN_Form::fieldText("sln[{$key}]", $value, array('required' => true, 'type' => 'password'));
                    break;
                case 'email':
                    SLN_Form::fieldText("sln[{$key}]", $value, array('required' => $field->isRequiredNotHidden(), 'type' => 'email'));
                    break;
                case 'phone': 
                    $prefix = !empty($bb->get('sms_prefix')) ? $bb->get('sms_prefix') : $plugin->getSettings()->get('sms_prefix');
                    if(!empty($prefix)): ?>
                        <?php SLN_Form::fieldText('sln[sms_prefix]', $prefix, array('type' => 'hidden')); ?>
                    <?php
                    endif;
                default:
                    if($type){
                        $additional_opts = array(
                            "sln[{$key}]",
                            $value,
                            array('required' => $field->isRequiredNotHidden()),
                        );
                        if(!empty($prefix) && trim($key) == 'phone'){

                            $additional_opts[2]['type'] = 'tel';

                        }else{

                            $additional_opts[2]['type'] = 'text';

                        }
                        $method_name ='field'.ucfirst($type);
                        if($type == 'checkbox'){
                            $additional_opts = array(
                                "sln[{$key}]",
                                $value,
                                '',
                                array('required' => $field->isRequiredNotHidden()),
                            );
                            $method_name = $method_name . 'Button';
                        }
                        if($type == 'select'){
                            $additional_opts[3] = array_merge($additional_opts[2], array(
                                'attrs' => array('data-placeholder' => __('Select an option', 'salon-booking-system')),
                                'empty_value' => true,
                            ));
                            $additional_opts[2] = $additional_opts[1];
                            $additional_opts[1] = $field->getSelectOptions();
                            $additional_opts[] = true;
                        }
                        if($type == 'file') {
                            $additional_opts[1] = '';
                            if ($field->getFileType()) {
                                $additional_opts[2] = array_merge($field->getFileType(), $additional_opts[2]);
                            }
                        }
                        call_user_func_array(array('SLN_Form', $method_name), $additional_opts);
                    }else{
                        SLN_Form::fieldText("sln[{$key}]", $value, array('required' => $field->isRequiredNotHidden()));
                    }
            }
        } ?>
    </div>
<?php }
if(!is_user_logged_in()): ?>
    <div class="col-xs-12 sln-input sln-input--simple">
        <div class="row">
            <?echo apply_filters('login_form_middle', '', array()); ?>
        </div>
    </div>
    <div class="col-xs-12 sln-input sln-input--simple">
        <div class="row">
            <?echo apply_filters('login_form_bottom', '', array()); ?>
        </div>
    </div>
<?php endif;
do_action('sln.template.details.after_form', $bb, $size);