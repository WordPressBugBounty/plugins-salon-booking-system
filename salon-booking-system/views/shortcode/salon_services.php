<?php
/**
 * @var SLN_Plugin                        $plugin
 * @var string                            $formAction
 * @var string                            $submitName
 * @var SLN_Shortcode_Salon_ServicesStep $step
 */
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
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
	
	// Display session/cookie warning if present
	if (!empty($sessionWarning)) {
		?>
		<div class="sln-alert sln-alert--problem sln-alert--session-warning">
			<p><strong><?php esc_html_e('Warning:', 'salon-booking-system'); ?></strong> <?php echo wp_kses_post($sessionWarning); ?></p>
		</div>
		<?php
	}
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
	
	<script>
	jQuery(document).ready(function($) {
		// Check if cookies are enabled in the browser
		function checkCookiesEnabled() {
			// Try to set a test cookie
			document.cookie = "sln_cookie_test=1; path=/; SameSite=Lax";
			var cookiesEnabled = document.cookie.indexOf("sln_cookie_test=") !== -1;
			
			// Clean up test cookie
			document.cookie = "sln_cookie_test=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT";
			
			return cookiesEnabled;
		}
		
		// Only show warning on first step
		if ($('#salon-step-services').length && !checkCookiesEnabled()) {
			var warningHtml = '<div class="sln-alert sln-alert--problem sln-alert--cookie-warning">' +
				'<p><strong><?php esc_html_e('Warning:', 'salon-booking-system'); ?></strong> ' +
				'<?php esc_html_e('Cookies are disabled in your browser.', 'salon-booking-system'); ?></p>' +
				'<p><?php esc_html_e('The booking process requires cookies to work properly. Please enable cookies and reload the page.', 'salon-booking-system'); ?></p>' +
				'</div>';
			
			$('#salon-step-services form').prepend(warningHtml);
		}
	});
	</script>
	<?php
}