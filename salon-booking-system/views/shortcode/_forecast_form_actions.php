<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Forecast step form actions — mirrors the large-format sidebar used by other
 * wizard steps (size 900), with forecast-specific CTAs.
 *
 * Expects variables from salon_forecast.php scope:
 *   $size, $is_large, $forecast_state, $submitName, $current, $ajaxEnabled,
 *   $plugin, $step, $service, $suggestions, $settings
 */

$ajaxSecurity     = wp_create_nonce( 'ajax_post_validation' );
$builder          = $plugin->getBookingBuilder();
$clientIdFieldValue = $builder->getClientId();
$lastBookingObject  = $builder->getLastBooking();
$lastBookingId      = $lastBookingObject ? $lastBookingObject->getId() : null;

if ( ! empty( $clientIdFieldValue ) ) {
    echo '<input type="hidden" name="sln_client_id" value="' . esc_attr( $clientIdFieldValue ) . '">';
}
echo '<input type="hidden" name="action" value="salon">';
echo '<input type="hidden" name="method" value="salonStep">';
echo '<input type="hidden" name="security" value="' . esc_attr( $ajaxSecurity ) . '">';
if ( ! empty( $lastBookingId ) ) {
    echo '<input type="hidden" name="sln_booking_id" value="' . esc_attr( $lastBookingId ) . '">';
}
if ( isset( $_GET['lang'] ) ) {
    echo '<input type="hidden" name="lang" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) ) . '">';
}

if ( ! $is_large || 'cards' !== $forecast_state ) {
    return;
}

// Cards state: build initial data-salon-data for the Book now button.
$initial_salon_data = '';
if ( 'cards' === $forecast_state && ! empty( $suggestions ) && $service && ! $service->isEmpty() ) {
    $service_id = (int) $suggestions[0]['service_id'];
    $initial_salon_data = esc_attr(
        "sln_step_page={$current}"
        . "&{$submitName}=next"
        . "&sln_forecast_date={$suggestions[0]['date']}"
        . "&sln_forecast_time={$suggestions[0]['time']}"
        . "&sln_forecast_service_id={$service_id}"
        . "&sln_forecast_attendant_id={$suggestions[0]['attendant_id']}"
    );
}
?>
    </div><!-- /.col-md-8 main column -->

    <div id="sln-box__bottombar" class="col-xs-12 col-md-4 sln-box__bottombar sln-box__bottombar--l sln-box__bottombar--<?php echo esc_attr( $current ) ?>">
        <div class="sln-box__bottombar__fkbg--customcolors"></div>

        <div class="sln-box--formactions sln-box--formactions--<?php echo esc_attr( $current ) ?> sln-box--formactions--forecast form-actions sln-forecast-step__formactions">

            <?php if ( ! empty( $suggestions ) ) : ?>
                <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth sln-btn--nextstep">
                    <button type="submit"
                        id="sln-forecast-submit"
                        class="sln-forecast-cards__book-btn js-sln-forecast-confirm"
                        <?php if ( $ajaxEnabled && $initial_salon_data ) : ?>
                            data-salon-data="<?php echo $initial_salon_data ?>"
                            data-salon-toggle="next"
                        <?php endif ?>
                        name="<?php echo esc_attr( $submitName ) ?>"
                        value="next">
                        <?php esc_html_e( 'Book now', 'salon-booking-system' ) ?>
                    </button>
                </div>
                <div class="sln-btn--prevstep">
                    <button type="submit"
                        class="sln-btn sln-btn--fullwidth sln-btn--borderonly sln-btn--medium js-sln-forecast-skip"
                        <?php if ( $ajaxEnabled ) : ?>
                            data-salon-data="<?php echo esc_attr( "sln_step_page={$current}&{$submitName}=next&sln_skip_forecast=1" ) ?>"
                            data-salon-toggle="next"
                        <?php endif ?>
                        name="<?php echo esc_attr( $submitName ) ?>"
                        value="next">
                        <input type="hidden" name="sln_skip_forecast" value="1" class="js-sln-forecast-skip-input" disabled />
                        <?php esc_html_e( 'I want different options', 'salon-booking-system' ) ?>
                    </button>
                </div>
            <?php endif ?>

        </div>
    </div>
</div><!-- /.row sln-box--main -->
