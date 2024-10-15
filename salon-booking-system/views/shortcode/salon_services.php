<?php
/**
 * @var SLN_Plugin                        $plugin
 * @var string                            $formAction
 * @var string                            $submitName
 * @var SLN_Shortcode_Salon_ServicesStep $step
 */
if ($plugin->getSettings()->isDisabled()) {
	$message = $plugin->getSettings()->getDisabledMessage();
	?>
	<div class="sln-alert sln-alert--paddingleft sln-alert--problem">
		<?php echo empty($message) ? esc_html__('On-line booking is disabled', 'salon-booking-system') : esc_html($message) ?>
	</div>
	<?php
} else {
	$style = $step->getShortcode()->getStyleShortcode();
	$size = SLN_Enum_ShortcodeStyle::getSize($style);
	$bb             = $plugin->getBookingBuilder();
	$currencySymbol = $plugin->getSettings()->getCurrencySymbol();
	$services = $step->getServices();
	$additional_errors = !empty($additional_errors)? $additional_errors : $step->getAddtitionalErrors();
	$errors = !empty($errors) ? $errors : $step->getErrors();
	?>
	<form id="salon-step-services" method="post" action="<?php echo esc_html($formAction) ?>" role="form">
	<?php
	include '_errors.php';
	include '_additional_errors.php';
	?>
	<?php if ($size == '900') { ?>
		<div class="row sln-box--main sln-box--flatbottom--phone">
			<div class="col-xs-12 col-md-8">
				<div id="sln-box--fixed_height" class="sln-box--fixed_height is_scrollable"><?php include "_services.php"; ?></div>
			</div> <!-- The row closed inside _form_actions.php -->
	<?php } else {  // IF SIZE 900 // END ?>
		<div class="row sln-box--main  sln-box--fixed_height">
			<div class="col-xs-12"><?php include "_services.php"; ?></div>
		</div>
	<?php } // IF SIZE 600 AND 400 // END ?>
	<?php include "_form_actions.php" ?>
        <input type="hidden" name="sln[customer_timezone]" value="<?php echo esc_html($bb->get('customer_timezone')) ?>">
	</form>
	<?php
}