<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin                $plugin
 * @var string                    $formAction
 * @var string                    $submitName
 * @var SLN_Shortcode_Salon_Step $step
 */
$bb = $plugin->getBookingBuilder();
$style = $step->getShortcode()->getStyleShortcode();
$size = SLN_Enum_ShortcodeStyle::getSize($style);
global $current_user;
wp_get_current_user();

$current     = $step->getShortcode()->getCurrentStep();
$ajaxEnabled = $plugin->getSettings()->isAjaxEnabled();

$bookingDetailsPageUrl = add_query_arg(array('sln_step_page' => 'details', 'submit_details' => 'next'), get_permalink($plugin->getSettings()->getPayPageId()));
$additional_errors = !empty($additional_errors)? $additional_errors : $step->getAddtitionalErrors();
$errors = !empty($errors) ? $errors : $step->getErrors();
include '_errors.php';
include '_additional_errors.php';
?>
<?php if (!is_user_logged_in()): ?>
    <?php if (!$plugin->getSettings()->get('enabled_force_guest_checkout')): ?>
        <form method="post" action="<?php echo esc_html($formAction) ?>" role="form" enctype="multipart/form-data" id="salon-step-details">
            <?php 
            include '_salon_detail_login.php'; ?>
        </form>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_html($formAction) ?>" role="form" enctype="multipart/form-data" id="salon-step-details-new">
        <div class="row">
            <?php if($plugin->getSettings()->get('enabled_force_guest_checkout')): ?>
                <?php SLN_Form::fieldCheckbox(
                    'sln[no_user_account]',
                    $bb->get('no_user_account'),
                    array(
                        'type' => 'hidden',
                        'attrs' => array(
                            'checked' => 'checked',
                            'style' => 'display:none'
                        )
                    )
                ) ?>
            <?php elseif($plugin->getSettings()->get('enabled_guest_checkout')): ?>
                <div class="col-xs-2 col-sm-1 sln-checkbox">
                    <div class="sln-checkbox">
                        <?php SLN_Form::fieldCheckbox(
                            'sln[no_user_account]',
                            $bb->get('no_user_account'),
                            array()
                        ) ?>
                        <label for="<?php echo esc_html(SLN_Form::makeID('sln[no_user_account]')) ?>"></label>
                    </div>
                </div>
                <div class="col-xs-12 col-md-11">
                    <label for="<?php echo esc_html(SLN_Form::makeID('sln[no_user_account]')) ?>"><h2 class="salon-step-title"><?php esc_html_e('checkout as a guest', 'salon-booking-system') ?>, <?php _e('no account will be created', 'salon-booking-system') ?></h2></label>
                </div>
            <?php else: ?>
            <div class="col-xs-12">
                    <h2 class="salon-step-title"><?php esc_html_e('Checkout as a guest', 'salon-booking-system') ?>, <?php esc_html_e('An account will be automatically created', 'salon-booking-system') ?></h2>
                </div>
            <?php endif; ?>

        </div>
    <?php
        $fields = $plugin->getSettings()->get('enabled_force_guest_checkout') ?  SLN_Enum_CheckoutFields::forGuestCheckout() : SLN_Enum_CheckoutFields::forDetailsStep()->appendPassword();

        foreach($fields as $field) { //remove excessive quotes escaping
            if(!$field->isDefault()) {
                $field->offsetSet('label', stripcslashes($field->offsetGet('label')));
                $field->offsetSet('default_value', stripcslashes($field->offsetGet('default_value')));
            }
        }
    if ($size == '900') { ?>
        <div class="row"> <!-- The div closed inside _form_actions.php -->
            <div class="col-xs-12 col-md-8">
                <div class="row">
                    <?php include '_salon_detail_content.php'; ?>
                </div>
            </div>
    <?php
    // IF SIZE 900 // END
    } else if ($size == '600') { ?>
    <div class="row">
        <?php include '_salon_detail_content.php'; ?>
    </div>
    <?php
    // IF SIZE 600 // END
    } else if ($size == '400') { ?>
    <div class="row">
        <?php include '_salon_detail_content.php'; ?>
    </div>
    <?php
    // IF SIZE 400 // END
    } ?>
    <?php include "_form_actions.php" ?>
    </form>
<?php else: ?>

    <form method="post" action="<?php echo esc_html($formAction) ?>" role="form" enctype="multipart/form-data">
        <?php
        $fields = SLN_Enum_CheckoutFields::forDetailsStep()->filter('booking_hidden',false);
        foreach($fields as $field) { //remove excessive quotes escaping
            if(!$field->isDefault()) {
                $field->offsetSet('default_value', stripcslashes($field->offsetGet('default_value')));
            }
        }
    if ($size == '900') { ?>
    <div class="row"> <!-- The row closed inside _form_actions.php -->
        <div class="col-xs-12 col-md-8">
            <div class="row">
                <?php include '_salon_detail_content.php' ?>
            </div>
        </div>
    <?php
    // IF SIZE 900 // END
    } else{ ?>
    <div class="row">
        <?php include '_salon_detail_content.php' ?>
    </div>
    <?php
    // IF SIZE 600 AND  400 // END
    }?>
    <?php include "_form_actions.php" ?>
    </form>
<?php endif ?>

