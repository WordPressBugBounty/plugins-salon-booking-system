<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Forecast step – STATE A (guest / not logged in).
 *
 * Guest-first layout (progressive disclosure):
 *   - A single dominant "Continue to booking" primary action.
 *   - The returning-customer login is revealed on demand via a quiet toggle.
 *
 * The login panel is rendered visible by default and collapsed by JavaScript
 * once enhanced (see sln_stepForecast in js/salon.js), so returning customers
 * can still log in if JavaScript is unavailable.
 */
$fbLoginEnabled        = $settings->get( 'enabled_fb_login' );
$bookingDetailsPageUrl = SLN_Func::currPageUrl();
$size                  = $settings->getStyleShortcode();
$is_large              = isset( $is_large ) ? $is_large : ( 900 === (int) SLN_Enum_ShortcodeStyle::getSize( $size ) );
?>
<div class="sln-forecast-login<?php echo $is_large ? ' sln-forecast-login--large' : '' ?>">

    <div class="sln-forecast-login__primary">
        <input type="hidden" name="sln_skip_forecast" value="1" class="js-sln-forecast-skip-input" disabled />
        <div class="sln-forecast-login__submit">
            <button type="submit"
                class="js-sln-forecast-skip"
                <?php if ( $ajaxEnabled ) : ?>
                    data-salon-data="<?php echo esc_attr( "sln_step_page={$current}&{$submitName}=next&sln_skip_forecast=1" ) ?>"
                    data-salon-toggle="next"
                <?php endif ?>
                name="<?php echo esc_attr( $submitName ) ?>"
                value="next">
                <?php esc_html_e( 'Continue to booking', 'salon-booking-system' ) ?>
            </button>
        </div>
        <p class="sln-forecast-login__primary-hint">
            <?php esc_html_e( 'No account needed — book in just a few steps.', 'salon-booking-system' ) ?>
        </p>
    </div>

    <div class="sln-forecast-login__returning">
        <button type="button"
            class="sln-forecast-login__toggle js-sln-forecast-login-toggle"
            aria-expanded="false"
            aria-controls="sln-forecast-login-panel">
            <span class="sln-forecast-login__toggle-text"><?php esc_html_e( 'Returning customer?', 'salon-booking-system' ) ?></span>
            <span class="sln-forecast-login__toggle-action"><?php esc_html_e( 'Log in', 'salon-booking-system' ) ?></span>
        </button>
    </div>

    <div id="sln-forecast-login-panel" class="sln-forecast-login__panel">
        <div class="sln-forecast-login__form">

            <div class="sln-forecast-login__layout">

                <div class="sln-forecast-login__fields">

                    <div class="sln-forecast-login__field">
                        <label class="sln-forecast-login__label" for="sln_login_name">
                            <?php esc_html_e( 'Email', 'salon-booking-system' ) ?>
                        </label>
                        <input id="sln_login_name" name="login_name" type="text" class="sln-input sln-input--text"
                               placeholder="<?php esc_attr_e( 'Email Address', 'salon-booking-system' ) ?>"
                               autocomplete="email" />
                    </div>

                    <div class="sln-forecast-login__field">
                        <label class="sln-forecast-login__label" for="sln_login_password">
                            <?php esc_html_e( 'Password', 'salon-booking-system' ) ?>
                        </label>
                        <input id="sln_login_password" name="login_password" type="password" class="sln-input sln-input--text"
                               placeholder="<?php esc_attr_e( 'Password', 'salon-booking-system' ) ?>"
                               autocomplete="current-password" />
                        <span class="sln-forecast-login__forgot">
                            <a href="<?php echo esc_url( wp_lostpassword_url() ) ?>">
                                <?php esc_html_e( 'Forgot password?', 'salon-booking-system' ) ?>
                            </a>
                        </span>
                    </div>

                    <?php if ( ! $is_large ) : ?>
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
                            <a href="<?php echo esc_url( add_query_arg( array( 'referrer' => urlencode( $bookingDetailsPageUrl ) ), SLN_Helper_FacebookLogin::getRedirectUri() ) ) ?>"
                               class="sln-btn sln-btn--fullwidth sln-btn--nobkg sln-btn--medium sln-btn--fb">
                                <svg class="sln-fblogin--icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M0 0v24h24v-24h-24zm16 7h-1.923c-.616 0-1.077.252-1.077.889v1.111h3l-.239 3h-2.761v8h-3v-8h-2v-3h2v-1.923c0-2.022 1.064-3.077 3.461-3.077h2.539v3z"/>
                                </svg>
                                <?php esc_html_e( 'log-in with Facebook', 'salon-booking-system' ) ?>
                            </a>
                        <?php endif ?>
                    <?php endif ?>

                </div><!-- /.fields -->

                <?php if ( $is_large ) : ?>
                    <div class="sln-forecast-login__actions">
                        <?php include __DIR__ . '/_forecast_login_actions.php' ?>

                        <?php if ( $fbLoginEnabled ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( array( 'referrer' => urlencode( $bookingDetailsPageUrl ) ), SLN_Helper_FacebookLogin::getRedirectUri() ) ) ?>"
                               class="sln-btn sln-btn--fullwidth sln-btn--nobkg sln-btn--medium sln-btn--fb">
                                <svg class="sln-fblogin--icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M0 0v24h24v-24h-24zm16 7h-1.923c-.616 0-1.077.252-1.077.889v1.111h3l-.239 3h-2.761v8h-3v-8h-2v-3h2v-1.923c0-2.022 1.064-3.077 3.461-3.077h2.539v3z"/>
                                </svg>
                                <?php esc_html_e( 'log-in with Facebook', 'salon-booking-system' ) ?>
                            </a>
                        <?php endif ?>
                    </div>
                <?php endif ?>

            </div><!-- /.layout -->

        </div><!-- /.form -->
    </div><!-- /.panel -->

</div><!-- /.sln-forecast-login -->
