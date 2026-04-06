<?php
/**
 * @var SLN_Plugin                        $plugin
 * @var string                            $formAction
 * @var string                            $submitName
 * @var SLN_Shortcode_Salon_AttendantStep $step
 * @var bool                              $isMultipleAttSelection
 */
$bb                     = $plugin->getBookingBuilder();
$attendants             = $step->getAttendants();
$style                  = $step->getShortcode()->getStyleShortcode();
$size                   = SLN_Enum_ShortcodeStyle::getSize($style);
$isMultipleAttSelection = $plugin->getSettings()->isMultipleAttendantsEnabled();
$includeName = $isMultipleAttSelection ? '_m_attendants.php' : '_attendants.php';
$wrapperClass = $isMultipleAttSelection ? 'sln-attendants-wrapper sln-attendants-wrapper--multi' : 'sln-attendants-wrapper';
$additional_errors = !empty($additional_errors)? $additional_errors : $step->getAddtitionalErrors();
$errors = !empty($errors) ? $errors : $step->getErrors();
?>
<form id="salon-step-attendant" method="post" action="<?php echo esc_html($formAction); ?>" role="form">
    <?php
    include '_errors.php';
    include '_additional_errors.php';
    ?>
    <?php if ($size == '900'): ?>
        <div class="row sln-box--main sln-attendants-wrapper">
            <div class="col-xs-12 col-md-8">
               <div class="sln-box--fixed_height"><?php include $includeName; ?></div>
            </div> <!-- The row closed inside _form_actions.php -->
    <?php else: ?>
        <div class="row sln-box--main <?php echo esc_html($wrapperClass); ?> sln-box--fixed_height">
            <div class="col-xs-12"><?php include $includeName; ?></div>
        </div>
    <?php endif ?>
	<?php include "_form_actions.php" ?>
</form>
