<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var string $formAction
 * @var string $submitName
 */
if ($plugin->getSettings()->isDisabled()) {
    $message = $plugin->getSettings()->getDisabledMessage();
    ?>
    <div class="sln-alert sln-alert--paddingleft sln-alert--problem">
        <?php echo empty($message) ? esc_html__('On-line booking is disabled', 'salon-booking-system') : $message ?>
    </div>
    <?php
} else {
    $bb = $plugin->getBookingBuilder();
    $style = $step->getShortcode()->getStyleShortcode();
    $size = SLN_Enum_ShortcodeStyle::getSize($style);
    $additional_errors = !empty($additional_errors)? $additional_errors : $step->getAddtitionalErrors();
    $errors = !empty($errors) ? $errors : $step->getErrors();

    ?>

    <form method="post" action="<?php echo $formAction ?>" id="salon-step-date"
            data-intervals="<?php echo esc_attr(wp_json_encode($intervalsArray)); ?>"
            <?php if((bool)SLN_Plugin::getInstance()->getSettings()->get('debug') && current_user_can( 'administrator' ) ): ?>
            data-debug="<?php echo esc_attr( wp_json_encode( SLN_Helper_Availability_AdminRuleLog::getInstance()->getDateLog() ) ); ?>"
            <?php endif ?>>
        <?php
        include '_errors.php';
        include '_additional_errors.php';
        ?>
        <?php if('900' == $size): ?>
            <div class="row sln-box--main sln-box--main--datepicker sln-box--flatbottom--phone"> <!-- The row closed inside _form_actions.php -->
                <?php include '_salon_date_pickers.php'; ?>
        <?php else: ?>
            <div class="row sln-box--main sln-box--main--datepicker">
                <?php include '_salon_date_pickers.php'; ?>
            </div>
        <?php endif; ?>
        <?php include "_form_actions.php" ?>
    </form>
        
    <?php
}
