<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var string $formAction
 * @var string $submitName
 * @var SLN_Shortcode_Salon_Step $step
 */
$bb = $plugin->getBookingBuilder()->getLastBooking();
if(empty($bb) && isset($_GET['op'])){
    $bb = $plugin->createBooking(explode('-', sanitize_text_field($_GET(['op']))));
}
$currencySymbol = $plugin->getSettings()->getCurrencySymbol();
$datetime = $plugin->getSettings()->isDisplaySlotsCustomerTimezone() && $bb->getCustomerTimezone() ? $bb->getStartsAt($bb->getCustomerTimezone()) : $bb->getStartsAt();

$confirmation = $plugin->getSettings()->get('confirmation') && in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::DRAFT, SLN_Enum_BookingStatus::PENDING));
$showPrices = ($plugin->getSettings()->get('hide_prices') != '1') ? true : false;
$style = $step->getShortcode()->getStyleShortcode();
$size = SLN_Enum_ShortcodeStyle::getSize($style);
$isTipRequestEnabled = $plugin->getSettings()->isTipRequestEnabled();
$tipsValue = $bb->getTips();

$payRemainingAmount = isset($_GET['pay_remaining_amount']) && $_GET['pay_remaining_amount'];
$pendingPayment = $plugin->getSettings()->isPayEnabled() && $payRemainingAmount && !$booking->getPaidRemainedAmount();
$payLater = $plugin->getSettings()->get('pay_cash');
$ajaxData = 'sln_step_page=' .$step->getStep() .'&submit_' .$step->getStep(). '=next&pay_remaining_amount=' . intval($payRemainingAmount);
$ajaxData = apply_filters('sln.booking.thankyou-step.get-ajax-data', $ajaxData);
$ajaxEnabled = $plugin->getSettings()->isAjaxEnabled();

$paymentMethod = ((!$confirmation || $pendingPayment) && $plugin->getSettings()->isPayEnabled()) ?
SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) :
false;

// packages credit
$user_id = get_current_user_id();
$prepaid_services = get_user_meta($user_id, '_sln_prepaid_services', true);
$packages_credits = [];
foreach ($bb->getServices() as $service) {
    $service_id = $service->getId();
    $prepaid_service = isset($prepaid_services[$service_id]) ? $prepaid_services[$service_id] : 0;
    if ($prepaid_service > 0) {
        $packages_credits[] = $service->getTitle();
        $bb->setMeta('amount', $bb->getMeta('amount') - $service->getPrice());
        $bb->addMeta('service_credit', $service_id);
    }
}

if($bb->getAmount() <= 0.0){
    $pendingPayment = $payLater = $paymentMethod = false;
}

$additional_errors = !empty($additional_errors)? $additional_errors : $step->getAddtitionalErrors();
$errors = !empty($errors) ? $errors : $step->getErrors();
if ($errors && in_array(SLN_Shortcode_Salon_SummaryStep::SLOT_UNAVAILABLE, $errors)){
    echo $plugin->loadView('shortcode/_unavailable', array('step' => $step));
}else if ($errors && in_array(SLN_Shortcode_Salon_SummaryStep::SERVICES_DATA_EMPTY, $errors)){
    echo $plugin->loadView('shortcode/_services_data_empty', array('step' => $step));
}else{
?>
<form method="post" action="<?php echo $formAction ?>" role="form" id="salon-step-summary">
    <?php
    include '_errors.php';
    include '_additional_errors.php';
    ?>
    <div class="row">
        <div class="col-xs-12 col-md-12">
            <p class="sln-text--dark sln-summary__intro">
                <?php
                $name = array();
                if (!SLN_Enum_CheckoutFields::getField('firstname')->isHidden()) {
                    $firstname = esc_attr($bb->getFirstname());
                    if (!empty($firstname)) {
                        $name[] = $firstname;
                    }
                }
                if (!SLN_Enum_CheckoutFields::getField('lastname')->isHidden()) {
                    $lastname = esc_attr($bb->getLastname());
                    if (!empty($lastname)) {
                        $name[] = $lastname;
                    }
                }
                $name = implode(' ', $name);

                if (!empty($name)) {
	                esc_html_e('Dear', 'salon-booking-system');
                ?>
                    <strong><?php echo $name.','; ?></strong>
                    <br/>
                <?php } ?>
                <?php esc_html_e('please review and confirm the details of your booking:', 'salon-booking-system') ?>
            </p>
        </div>
    </div>
    <?php if ($size == '900'): ?>
        <div class="row sln-summary">
            <?php include '_salon_summary_'.$size.'.php'; ?>
             <?php $nextLabel = __('Next step', 'salon-booking-system');
    include "_form_actions.php" ?>
        </div>
    <?php else: ?>
    <?php include '_salon_summary_'.$size.'.php'; ?>
    <?php $nextLabel = __('Next step', 'salon-booking-system');
    include "_form_actions.php" ?>
    <?php endif ?>
</form>
<?php
}