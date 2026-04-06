<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Shortcode_Salon_Step $step
 * @var $submitName
 * @var $nextLabel
 * @var $backUrl
 */

if (!isset($nextLabel)) {
    $nextLabel = __('Next step', 'salon-booking-system');
}
$i       = 0;
$salon  = $step->getShortcode();
$steps   = $salon->getSteps();
$count   = count($steps);
$current = $salon->getCurrentStep();
$count   = count($steps);
$style = $salon->getStyleShortcode();
$size = SLN_Enum_ShortcodeStyle::getSize($style);
$ajaxSecurity = wp_create_nonce('ajax_post_validation');
$builder = $plugin->getBookingBuilder();
$clientIdFieldValue = $builder->getClientId();
$lastBookingObject = $builder->getLastBooking();
$lastBookingId = $lastBookingObject ? $lastBookingObject->getId() : null;
if (!empty($clientIdFieldValue)) {
    echo '<input type="hidden" name="sln_client_id" value="' . esc_attr($clientIdFieldValue) . '">';
}
echo '<input type="hidden" name="action" value="salon">';
echo '<input type="hidden" name="method" value="salonStep">';
echo '<input type="hidden" name="security" value="' . esc_attr($ajaxSecurity) . '">';
if (!empty($lastBookingId)) {
    echo '<input type="hidden" name="sln_booking_id" value="' . esc_attr($lastBookingId) . '">';
}
if (isset($_GET['lang'])) {
    echo '<input type="hidden" name="lang" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['lang']))) . '">';
}

foreach ($steps as $step_iter) {
    $i++;
    if ($current == $step_iter) {
        $currentNum = $i;
    }
}
$ajaxEnabled = $plugin->getSettings()->isAjaxEnabled(); ?>
<?php if($size == '900'): ?>
    <div id="sln-box__bottombar" class="col-xs-12 col-md-4 sln-box__bottombar sln-box__bottombar--l sln-box__bottombar--<?php echo $current; ?>">
    <!-- <div class="sln-box__bottombar__fkbg"></div> -->
    <div class="sln-box__bottombar__fkbg--customcolors"></div>
<?php else: ?>
<div id="sln-box__bottombar" class="row sln-box__bottombar sln-box__bottombar--<?php echo $current; ?>">
    <!-- <div class="sln-box__bottombar__fkbg"></div> -->
    <div class="sln-box__bottombar__fkbg--customcolors"></div>
    <div class="col-xs-12">

<?php endif; ?>

        <?php if ($step->isNeedTotal()) { ?>
            <div class="sln-total">
                <?php if ($size == '900'): ?>
                    <h3 class="col-xs-6 col-sm-6 col-md-6 sln-total-label">
                        <?php esc_html_e('Subtotal', 'salon-booking-system') ?>
                    </h3>
                    <h3 class="col-xs-6 col-sm-6 col-md-6 sln-total-price" id="services-total"
                        data-symbol-left="<?php echo $symbolLeft ?>"
                        data-symbol-right="<?php echo $symbolRight ?>"
                        data-symbol-decimal="<?php echo $decimalSeparator ?>"
                        data-symbol-thousand="<?php echo $thousandSeparator ?>">
                        <?php echo $plugin->format()->money(0, false) ?>
                    </h3>
                <?php elseif ($size == '600'): ?>
                    <h3 class="sln-total-label">
                        <?php esc_html_e('Subtotal', 'salon-booking-system') ?>
                    </h3>
                    <h3 class="sln-total-price" id="services-total"
                        data-symbol-left="<?php echo $symbolLeft ?>"
                        data-symbol-right="<?php echo $symbolRight ?>"
                        data-symbol-decimal="<?php echo $decimalSeparator ?>"
                        data-symbol-thousand="<?php echo $thousandSeparator ?>">
                        <?php echo $plugin->format()->money(0, false) ?>
                    </h3>
                <?php elseif ($size == '400'): ?>
                    <h3 class="sln-total-label">
                        <?php esc_html_e('Subtotal', 'salon-booking-system') ?>
                    </h3>
                    <h3 class="sln-total-price" id="services-total"
                        data-symbol-left="<?php echo $symbolLeft ?>"
                        data-symbol-right="<?php echo $symbolRight ?>"
                        data-symbol-decimal="<?php echo $decimalSeparator ?>"
                        data-symbol-thousand="<?php echo $thousandSeparator ?>">
                        <?php echo $plugin->format()->money(0, false) ?>
                    </h3>
                <?php else: throw new Exception('size not supported'); ?>
                <?php endif ?>
            </div>
        <?php } ?>

        <?php ob_start();
        if(!empty($paymentMethod) && $paymentMethod){
            echo $paymentMethod->renderPayButton(array('booking' => $bb, 'paymentMethod' => $paymentMethod, 'ajaxData' => $ajaxData, 'payUrl' => $payUrl, 'payRemainingAmount' => $payRemainingAmount));
        }else{
        ?>
        <button
            <?php if($ajaxEnabled): ?>
                data-salon-data="<?php echo "sln_step_page=$current&$submitName=next" ?>" data-salon-toggle="next"
            <?php endif?>
            id="sln-step-submit" type="submit" name="<?php echo $submitName ?>" value="next">
            <?php echo $nextLabel ?> <i class="glyphicon glyphicon-chevron-right"></i>
        </button>
        <button
                id="sln-step-submit-complete" value="next" class="hidden">
            <?php echo __('Complete', 'salon-booking-system') ?>
        </button>
        <?php
        }
        $nextBtn = ob_get_clean();

        ob_start();
        if(!empty($paymentMethod) && $paymentMethod && !empty($payLater) && $payLater):
        ?>
            <a href="<?php echo $laterUrl; ?>" class="sln-btn sln-btn--fullwidth sln-btn--borderonly sln-btn--medium"
            <?php if($ajaxEnabled): ?>
                data-salon-data="<?php echo $ajaxData. '&mode=later'; ?>" data-salon-toggle="direct"
            <?php endif ?>>
                <?php esc_html_e('Pay later', 'salon-booking-system'); ?>
            </a>
        <?php elseif(($backUrl && $currentNum > 1)): ?>
            <a class="sln-btn sln-btn--fullwidth sln-btn--borderonly sln-btn--medium"
                <?php if($ajaxEnabled): ?>
                    data-salon-data="<?php echo "sln_step_page=".$salon->getPrevStep() ?>" data-salon-toggle="direct"
                <?php endif?>
                href="<?php echo $backUrl ?> ">
                <i class="glyphicon glyphicon-chevron-left"></i> <?php esc_html_e('Back', 'salon-booking-system') ?>
                <span id="start-over" class="hidden"><?php echo esc_html__('Start over', 'salon-booking-system'); ?></span>
            </a> 
        <?php
        endif;
        $backBtn = ob_get_clean();
        ?>
        <?php if ($size == '900') { ?>
            <div class="sln-box--formactions sln-box--formactions--<?php echo $current; ?> form-actions">
                <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth sln-btn--nextstep">
                    <?php echo $nextBtn ?>
                </div>
                <?php if (isset($backBtn)) : ?>
                       <div class="sln-btn--prevstep"><?php echo $backBtn ?></div>
                <?php endif ?>
            </div>
        <?php } else if ($size == '600') {      // IF SIZE == 900 // END ?>
            <div class="sln-box--formactions sln-box--formactions--<?php echo $current; ?> form-actions">
                <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth sln-btn--nextstep">
                    <?php echo $nextBtn ?>
                </div>
                <?php if (isset($backBtn)) : ?>
                       <div class="sln-btn--prevstep"><?php echo $backBtn ?></div>
                <?php endif ?>
            </div>
        <?php } else if ($size == '400') {        // IF SIZE == 900 // END ?>
            <div class="sln-box--formactions sln-box--formactions--<?php echo $current; ?> form-actions">
                <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth sln-btn--nextstep">
                    <?php echo $nextBtn ?>
                </div>
                <?php if (isset($backBtn)) : ?>
                       <div class="sln-btn--prevstep"><?php echo $backBtn ?></div>
                <?php endif ?>
            </div>
        <?php } else {        // IF SIZE == 400 // END ?>
            <div class="form-actions row">
                <div class="col-xs-12 col-md-7 pull-right">
                    <div class="sln-btn sln-btn--emphasis sln-btn--big sln-btn--fullwidth">
                        <?php echo $nextBtn ?>
                    </div>
                </div>
                <div class="col-xs-12 col-md-4 pull-right">
                    <?php if (isset($backBtn)) : ?>
                        <?php echo $backBtn ?>
                    <?php endif ?>
                </div>
                <div class="col-xs-12 col-md-1 pull-right"></div>
            </div>
        <?php }     // IF SIZE ELSE // END ?>
    </div>
</div> <!-- sln-box--main closing tag if size == 900 else sln-box--formactions closing tag -->
