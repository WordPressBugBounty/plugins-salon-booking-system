<?php
/**
 * @var SLN_Plugin $plugin
 * @var string     $formAction
 * @var string     $submitName
 * @var SLN_Wrapper_Booking $booking
 * @var SLN_Shortcode_Salon_ThankyouStep $step
 */
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
$confirmation = $plugin->getSettings()->get('confirmation');

$payRemainingAmount = isset($_GET['pay_remaining_amount']) && wp_unslash($_GET['pay_remaining_amount']);
$pendingPayment = $plugin->getSettings()->isPayEnabled() && $payRemainingAmount && !$booking->getPaidRemainedAmount();
$paymentMethod = ((!$confirmation || $pendingPayment) && $plugin->getSettings()->isPayEnabled()) ?
SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) :
false;

$additional_errors = !empty($additional_errors)? $additional_errors : $step->getAddtitionalErrors();
$errors = !empty($errors) ? $errors : $step->getErrors();
include '_errors.php';
include '_additional_errors.php';

$style = $step->getShortcode()->getStyleShortcode();
$size = SLN_Enum_ShortcodeStyle::getSize($style);

?>
<div id="salon-step-thankyou" class="sln-thankyou">
    <div class="row">
        <div class="col-xs-12">
            <?php include '_salon_thankyou_'.$size.'.php'; ?>
        </div>
    </div>
</div>
