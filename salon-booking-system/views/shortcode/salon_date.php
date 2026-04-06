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
        
        // Display session/cookie warning if present
        if (!empty($sessionWarning)) {
            ?>
            <div class="sln-alert sln-alert--problem sln-alert--session-warning">
                <p><strong><?php esc_html_e('Warning:', 'salon-booking-system'); ?></strong> <?php echo wp_kses_post($sessionWarning); ?></p>
            </div>
            <?php
        }
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
        if ($('#salon-step-date').length && !checkCookiesEnabled()) {
            var warningHtml = '<div class="sln-alert sln-alert--problem sln-alert--cookie-warning">' +
                '<p><strong><?php esc_html_e('Warning:', 'salon-booking-system'); ?></strong> ' +
                '<?php esc_html_e('Cookies are disabled in your browser.', 'salon-booking-system'); ?></p>' +
                '<p><?php esc_html_e('The booking process requires cookies to work properly. Please enable cookies and reload the page.', 'salon-booking-system'); ?></p>' +
                '</div>';
            
            $('#salon-step-date form').prepend(warningHtml);
        }
    });
    </script>
        
    <?php
}
