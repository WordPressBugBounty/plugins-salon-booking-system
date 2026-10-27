<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$current_user = wp_get_current_user();
$plugin       = SLN_Plugin::getInstance();
$values       = array();
$sms_prefix = '';
$errors = isset($sln_update_profile) && is_array($sln_update_profile) && isset($sln_update_profile['errors']) ? $sln_update_profile['errors'] : array();

$last_update = get_user_meta(get_current_user_id(), '_sln_last_update', true);

$sln_autocomplete = array(
    'firstname'        => 'given-name',
    'lastname'         => 'family-name',
    'email'            => 'email',
    'phone'            => 'tel-national',
    'address'          => 'street-address',
    'password'         => 'new-password',
    'password_confirm' => 'new-password',
);
$sln_fields          = SLN_Enum_CheckoutFields::forCustomer()->appendPassword();
$sln_password_keys   = array('password', 'password_confirm');
$sln_has_required    = false;
$sln_has_password    = false;
foreach ($sln_fields as $key => $field) {
    $sln_has_password = $sln_has_password || in_array($key, $sln_password_keys, true);
    $sln_has_required = $sln_has_required || ($field['type'] !== 'html' && !in_array($key, $sln_password_keys, true) && $field->isRequired());
}

$sln_render_field = function ($key, $field) use ($plugin, $sln_autocomplete, $sln_password_keys) {
    $value = $field->getValue(get_current_user_id());
    $type = $field['type'];
    $is_required = $type !== 'html' && !in_array($key, $sln_password_keys, true) && $field->isRequired();
    $text_attrs = array('class' => 'sln-input sln-input--text');
    if (isset($sln_autocomplete[$key])) {
        $text_attrs['autocomplete'] = $sln_autocomplete[$key];
    }
    $sms_prefix = '';
    ?>
    <div class="sln-account__field field-<?php echo esc_attr($key); ?> sln-<?php echo esc_attr($type); ?> <?php echo $type !== 'checkbox' ? 'sln-input sln-input--simple' : '';?>">
        <?php if($type == 'html'){
            echo $field['default_value'];
        }else{?>
            <label class="sln-account__label" for="<?php echo esc_attr(SLN_Form::makeID("sln[{$key}]")); ?>">
                <?php esc_html_e(sprintf('%s', $field['label']), 'salon-booking-system'); ?>
                <?php if ($is_required): ?>
                    <span class="sln-account__required" aria-hidden="true">*</span>
                <?php endif; ?>
            </label>
            <?php switch($key){
                case 'password':
                case 'password_confirm': ?>
                    <div class="sln-account__password">
                        <?php SLN_Form::fieldText("sln[{$key}]", '', array('type' => 'password', 'attrs' => $text_attrs)); ?>
                        <button type="button" class="sln-account__iconbtn sln-account__password__toggle" aria-controls="<?php echo esc_attr(SLN_Form::makeID("sln[{$key}]")); ?>" aria-label="<?php esc_attr_e('Show password', 'salon-booking-system'); ?>" data-label-show="<?php esc_attr_e('Show password', 'salon-booking-system'); ?>" data-label-hide="<?php esc_attr_e('Hide password', 'salon-booking-system'); ?>">
                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <?php break;
                case 'email':
                    SLN_Form::fieldText("sln[{$key}]", $value, array('required' => $field->isRequired(), 'type' => 'email', 'attrs' => $text_attrs));
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
                            array('required' => $field->isRequired(), 'attrs' => $text_attrs),
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
                                unset($additional_opts[2]['attrs']);
                                $additional_opts[3] = array_merge($additional_opts[2], array(
                                    'attrs' => array('data-placeholder' => __('select an option', 'salon-booking-system')),
                                    'empty_value' => true,
                                ));
                                $additional_opts[2] = $additional_opts[1];
                                $additional_opts[1] = $field->getSelectOptions();
                                $additional_opts[] = true;
                                break;
                            case 'file':
                                unset($additional_opts[2]['attrs']);
                                if($field->getFileType()){
                                    $additional_opts[2] = array_merge($field->getFileType(), $additional_opts[2]);
                                }
                                break;
                        }
                        call_user_func_array(array('SLN_Form', $method_name), $additional_opts);
                    }else{
                        SLN_Form::fieldText("sln[{$key}]", $value, array('required' => $field->isRequiredNotHidden(), 'attrs' => $text_attrs));
                    }
                    if ($key === 'phone'): ?>
                        <p class="sln-account__hint"><?php esc_html_e('Used for appointment reminders', 'salon-booking-system'); ?></p>
                    <?php endif;
            }
        } ?>
    </div>
    <?php
};
?>
<form method="post"  role="form" id="salon-my-account-profile-form" class="sln-account__profile" enctype="multipart/form-data">
    <input type="hidden" name="action" value="sln_update_profile">
    <?php wp_nonce_field('slnUpdateProfileNonce', 'slnUpdateProfileNonceField');?>

    <section class="sln-account__panel">
        <div class="sln-account__panel__head">
            <h2 class="sln-account__panel__title"><?php esc_html_e('Personal details', 'salon-booking-system'); ?></h2>
            <?php if ($sln_has_required): ?>
                <span class="sln-account__panel__legend"><span class="sln-account__required" aria-hidden="true">*</span> <?php esc_html_e('Required', 'salon-booking-system'); ?></span>
            <?php endif; ?>
        </div>
        <?php
        foreach ($sln_fields as $key => $field) {
            // sms_prefix is posted as a hidden input next to the phone field.
            if ($key !== 'sms_prefix' && !in_array($key, $sln_password_keys, true)) {
                $sln_render_field($key, $field);
            }
        }
        ?>
    </section>

    <?php if ($sln_has_password): ?>
        <section class="sln-account__panel">
            <div class="sln-account__panel__head sln-account__panel__head--stacked">
                <h2 class="sln-account__panel__title"><?php esc_html_e('Change password', 'salon-booking-system'); ?></h2>
                <p class="sln-account__hint"><?php esc_html_e('Leave both fields empty to keep your current password.', 'salon-booking-system'); ?></p>
            </div>
            <?php
            foreach ($sln_password_keys as $key) {
                if (isset($sln_fields[$key])) {
                    $sln_render_field($key, $sln_fields[$key]);
                }
            }
            ?>
        </section>
    <?php endif; ?>

    <div class="sln-form-actions">
        <button type="submit" id="sln-accout-profile-submit" name="sln-accout-profile-submit" class="sln-account__btn sln-account__btn--primary sln-account__btn--cta"><?php esc_html_e('Save changes', 'salon-booking-system');?></button>
    </div>
    <?php if ($last_update): ?>
        <p class="sln-account--last-update">
            <?php echo sprintf(
                // translators: %1$s will be replaced by the last update date, %2$s will be replaced by the last update time
                esc_html__('Last update on %1$s at %2$s', 'salon-booking-system'), $plugin->format()->date((new SLN_DateTime())->setTimestamp($last_update)), $plugin->format()->time((new SLN_DateTime())->setTimestamp($last_update))); ?>
        </p>
    <?php endif; ?>
    <div class="sln-account__status" <?php if(!$errors) echo 'style="display:none;"' ?>>
        <div class="statusContainer sln-notifications--fix--tr">
            <?php if ($errors): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="sln-alert sln-alert--problem"><?php echo $error ?></div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>
</form>
<a class="sln-account__btn sln-account__btn--secondary sln-account__btn--wide sln-account__logout-bottom" href="<?php echo esc_url(wp_logout_url(home_url())); ?>">
    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
    <?php esc_html_e('Log out', 'salon-booking-system'); ?>
</a>
