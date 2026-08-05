<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

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

	// "Returning customer? Log in" tab — replaces the dedicated forecast login
	// screen when the services step opens the wizard (steps alt order). See
	// ForecastStep::resolveState() for the matching skip logic.
	$showLoginTab   = !is_user_logged_in()
		&& !$plugin->getSettings()->get('enabled_force_guest_checkout')
		&& $plugin->getSettings()->isFormStepsAltOrder();
	// Keep the login tab active after a failed login attempt so the customer
	// sees the error next to the form they just used.
	$loginTabActive = $showLoginTab && isset($_POST['login_name']);
	?>
	<?php if ($showLoginTab): ?>
	<ul class="nav nav-tabs sln-content__tabs__nav sln-services-tabs__nav">
		<li class="sln-content__tabs__nav__item<?php echo $loginTabActive ? '' : ' current' ?>">
			<a href="#sln-services-tab--book" data-target="#sln-services-tab--book" data-toggle="tab" role="tab">
				<?php esc_html_e('Book an appointment', 'salon-booking-system') ?>
			</a>
		</li>
		<li class="sln-content__tabs__nav__item<?php echo $loginTabActive ? ' current' : '' ?>">
			<a href="#sln-services-tab--login" data-target="#sln-services-tab--login" data-toggle="tab" role="tab">
				<?php esc_html_e('Returning customer? Log in', 'salon-booking-system') ?>
			</a>
		</li>
	</ul>
	<div class="tab-content sln-services-tabs__content">
	<div id="sln-services-tab--book" class="tab-pane<?php echo $loginTabActive ? '' : ' active' ?>">
	<?php endif; ?>
	<form id="salon-step-services" method="post" action="<?php echo esc_html($formAction) ?>" role="form"
		<?php if (isset($_GET['sln_step_page']) && $_GET['sln_step_page'] === 'services'): ?>data-sln-direct-nav="1"<?php endif; ?>>
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
				<div class="sln-service-search sln-input sln-service-search--<?php echo esc_attr($size) ?>"
					data-no-results="<?php esc_attr_e('No services found for your search.', 'salon-booking-system') ?>">
					<span class="sln-service-search__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
					<input type="text" id="sln-service-search-input" class="sln-input sln-input--text"
						placeholder="<?php esc_attr_e('Search services…', 'salon-booking-system') ?>"
						autocomplete="off" />
					<button type="button" id="sln-service-search-clear" class="sln-service-search__clear" aria-label="<?php esc_attr_e('Clear search', 'salon-booking-system') ?>">&#x2715;</button>
				</div>
				<div id="sln-box--fixed_height" class="sln-box--fixed_height is_scrollable"><?php include "_services.php"; ?></div>
			</div> <!-- The row closed inside _form_actions.php -->
	<?php } else {  // IF SIZE 900 // END ?>
		<div class="row sln-box--main  sln-box--fixed_height">
			<div class="sln-service-search sln-input sln-service-search--<?php echo esc_attr($size) ?>"
				data-no-results="<?php esc_attr_e('No services found for your search.', 'salon-booking-system') ?>">
				<span class="sln-service-search__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
				<input type="text" id="sln-service-search-input" class="sln-input sln-input--text"
					placeholder="<?php esc_attr_e('Search services…', 'salon-booking-system') ?>"
					autocomplete="off" />
				<button type="button" id="sln-service-search-clear" class="sln-service-search__clear" aria-label="<?php esc_attr_e('Clear search', 'salon-booking-system') ?>">&#x2715;</button>
			</div>
			<div class="col-xs-12">
				<?php include "_services.php"; ?>
			</div>
		</div>
	<?php } // IF SIZE 600 AND 400 // END ?>
	<?php include "_form_actions.php" ?>
        <input type="hidden" name="sln[customer_timezone]" value="<?php echo esc_html($bb->get('customer_timezone')) ?>">
	</form>
	<?php if ($showLoginTab): ?>
	</div><!-- /#sln-services-tab--book -->
	<div id="sln-services-tab--login" class="tab-pane<?php echo $loginTabActive ? ' active' : '' ?>">
		<form id="salon-step-services-login" method="post" action="<?php echo esc_html($formAction) ?>" role="form">
			<?php include '_services_login_tab.php'; ?>
		</form>
	</div>
	</div><!-- /.sln-services-tabs__content -->
	<?php endif; ?>

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