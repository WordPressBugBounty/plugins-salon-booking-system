<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin                        $plugin
 * @var string                            $formAction
 * @var string                            $submitName
 * @var SLN_Shortcode_Salon_ServicesStep $step
 */
$bb = $plugin->getBookingBuilder();
$services = $step->getServices();

$style = $step->getShortcode()->getStyleShortcode();
$size = SLN_Enum_ShortcodeStyle::getSize($style);
$additional_errors = !empty($additional_errors)? $additional_errors : $step->getAddtitionalErrors();
$errors = !empty($errors) ? $errors : $step->getErrors();
?>
<form id="salon-step-secondary" method="post" action="<?php echo $formAction ?>" role="form">
	<?php
	include '_errors.php';
	include '_additional_errors.php';
	?>
	<?php if ($size == '900') { ?>
		<div class="row sln-box--main sln-box--flatbottom--phone">
			<div class="col-xs-12 col-md-8">
				<div class="sln-box--fixed_height"><?php include "_services.php"; ?></div>
			</div> <!-- The row closed inside _form_actions.php -->

	<?php } else { // IF SIZE 900 // END ?>
		<div class="row sln-box--main sln-box--fixed_height"><div class="col-xs-12"><?php include "_services.php"; ?></div></div>

	<?php } // IF SIZE 600 AND 400 // END ?>
	<?php include "_form_actions.php" ?>
</form>

