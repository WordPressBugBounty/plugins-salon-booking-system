<?php
/**
 * @var SLN_Plugin $plugin
 * @var string $formAction
 * @var string $submitName
 * @var SLN_Shortcode_Salon_Step $step
 */
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

$bb = $plugin->getBookingBuilder();
$valid = isset($_SESSION['sln_sms_valid']) ? $_SESSION['sln_sms_valid'] : false;
$currentStep = $step->getShortcode()->getCurrentStep();
$ajaxData = "sln_step_page=$currentStep&submit_$currentStep=1";
$ajaxEnabled = $plugin->getSettings()->isAjaxEnabled();
$style = $step->getShortcode()->getStyleShortcode();
$size = SLN_Enum_ShortcodeStyle::getSize($style);
?>
<?php if (isset($_GET['resend'])): ?>
    <div class="alert alert-success">
        <p><?php esc_html_e('SMS sent successfully.', 'salon-booking-system') ?></p>
    </div>
<?php endif ?>
<h2 class="sln-sms-message-title"><?php esc_html_e('SMS Verification', 'salon-booking-system') ?></h2>
<h2 class="sln-sms-message-text"><?php esc_html_e('We have sent an SMS text on your mobile phone.', 'salon-booking-system') ?></h2>
<form method="post" action="<?php echo esc_html($formAction) ?>" role="form">
    <?php if ($valid): ?>
        <div class="alert alert-success">
            <p><?php esc_html_e('Your telephone number is verified', 'salon-booking-system') ?></p>
        </div>
        <?php
        $size = 400;
        include "_form_actions.php" ?>
    <?php else: ?>
        <div class="row">
            <div class="col-xs-12 col-md-6">
                <div class="form-group">
                    <label for="<?php echo esc_html(SLN_Form::makeID('sln_verification')) ?>">
                        <?php esc_html_e('digit your verification code', 'salon-booking-system'); ?>
                    </label>
                </div>
            </div>
                        <div class="col-xs-12 col-md-6">
                <div class="form-group">
                   <?php SLN_Form::fieldText('sln_verification', '', array('required' => true)) ?>
                    <a href="<?php echo esc_html($formAction) ?>&resend=1" class="recover"
                        <?php if($ajaxEnabled): ?>
                        data-salon-data="<?php echo esc_html($ajaxData).'&resend=1' ?>" data-salon-toggle="direct"
                        <?php endif ?>>
                        <?php esc_html_e('I didn\'t receive the code, please send it again', 'salon-booking-system') ?>
                    </a>
                </div>
            </div>
        </div>
        <?php 
        $size = 400;
        include "_form_actions.php"; ?>
    <?php endif ?>
</form>

