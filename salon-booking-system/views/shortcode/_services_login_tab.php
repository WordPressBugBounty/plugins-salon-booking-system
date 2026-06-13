<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Services step – "Returning customer? Log in" tab.
 *
 * Replaces the dedicated forecast login screen when the services step opens
 * the wizard (steps alt order): the guest picks services on the first tab or
 * logs in here. The POST is handled by ServicesStep::dispatchLoginTab(),
 * which on success restarts the wizard so customers with booking history land
 * on the forecast cards.
 *
 * Reuses the .sln-forecast-login__* card styles so the dynamic custom colors
 * already mapped in sln-colors--custom.scss apply here as well.
 *
 * Included from salon_services.php — expects $step, $plugin, $submitName,
 * $errors and $loginTabActive in scope.
 *
 * NOTE: do not use the $settings view variable here — _services.php (included
 * earlier in salon_services.php) overwrites it with a plain array.
 */
$fbLoginEnabled = $plugin->getSettings()->get( 'enabled_fb_login' );
$bookingPageUrl = SLN_Func::currPageUrl();
$current        = $step->getShortcode()->getCurrentStep();
$ajaxEnabled    = $plugin->getSettings()->isAjaxEnabled();
?>
<?php if ( $loginTabActive ) { include '_errors.php'; } ?>
<div class="sln-services-login">

    <p class="sln-services-login__hint">
        <?php esc_html_e( 'Log in to your account to book faster with your saved details.', 'salon-booking-system' ) ?>
    </p>

    <div class="sln-forecast-login__form sln-services-login__card">

        <div class="sln-forecast-login__fields">

            <div class="sln-forecast-login__field">
                <label class="sln-forecast-login__label" for="sln_services_login_name">
                    <?php esc_html_e( 'Email', 'salon-booking-system' ) ?>
                </label>
                <input id="sln_services_login_name" name="login_name" type="text" class="sln-input sln-input--text"
                       placeholder="<?php esc_attr_e( 'Email Address', 'salon-booking-system' ) ?>"
                       autocomplete="email" required />
            </div>

            <div class="sln-forecast-login__field">
                <label class="sln-forecast-login__label" for="sln_services_login_password">
                    <?php esc_html_e( 'Password', 'salon-booking-system' ) ?>
                </label>
                <input id="sln_services_login_password" name="login_password" type="password" class="sln-input sln-input--text"
                       placeholder="<?php esc_attr_e( 'Password', 'salon-booking-system' ) ?>"
                       autocomplete="current-password" required />
                <span class="sln-forecast-login__forgot">
                    <a href="<?php echo esc_url( wp_lostpassword_url() ) ?>">
                        <?php esc_html_e( 'Forgot password?', 'salon-booking-system' ) ?>
                    </a>
                </span>
            </div>

            <div class="sln-forecast-login__submit">
                <button type="submit"
                    <?php if ( $ajaxEnabled ) : ?>
                        data-salon-data="<?php echo esc_attr( "sln_step_page={$current}&{$submitName}=next" ) ?>"
                        data-salon-toggle="next"
                    <?php endif ?>
                    name="<?php echo esc_attr( $submitName ) ?>"
                    value="next">
                    <?php esc_html_e( 'Log In', 'salon-booking-system' ) ?>
                </button>
            </div>

            <?php if ( $fbLoginEnabled ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'referrer' => urlencode( $bookingPageUrl ) ), SLN_Helper_FacebookLogin::getRedirectUri() ) ) ?>"
                   class="sln-btn sln-btn--fullwidth sln-btn--nobkg sln-btn--medium sln-btn--fb">
                    <svg class="sln-fblogin--icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M0 0v24h24v-24h-24zm16 7h-1.923c-.616 0-1.077.252-1.077.889v1.111h3l-.239 3h-2.761v8h-3v-8h-2v-3h2v-1.923c0-2.022 1.064-3.077 3.461-3.077h2.539v3z"/>
                    </svg>
                    <?php esc_html_e( 'log-in with Facebook', 'salon-booking-system' ) ?>
                </a>
            <?php endif ?>

        </div><!-- /.fields -->

    </div><!-- /.card -->

</div><!-- /.sln-services-login -->
