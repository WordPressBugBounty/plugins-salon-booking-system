<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Forecast step – STATE B (logged-in customer with booking history).
 *
 * Shows a personalised greeting, the predicted service/attendant, and up to 3
 * date/time selection cards.  Confirming one card submits the step and populates
 * the BookingBuilder, skipping straight to summary/checkout.
 *
 * Variables inherited from salon_forecast.php scope:
 *   $suggestions     array of slot proposals from SLN_Helper_BookingForecaster
 *   $service         SLN_Wrapper_Service
 *   $attendant       SLN_Wrapper_Attendant|null
 *   $customer_name   string
 *   $submitName      string
 *   $ajaxEnabled     bool
 *   $current         string
 *   $plugin          SLN_Plugin
 */

if ( empty( $suggestions ) || ! $service || $service->isEmpty() ) {
    return;
}

$first_suggestion  = $suggestions[0];
$service_id        = (int) $first_suggestion['service_id'];
$attendant_id      = (int) $first_suggestion['attendant_id'];
$service_name      = esc_html( $service->getName() );
$service_duration  = $service->getDuration() ? $plugin->format()->duration( $service->getDuration() ) : '';
$service_price     = $plugin->format()->money( $service->getPrice() );
$service_image_url = '';
if ( has_post_thumbnail( $service->getId() ) ) {
    $service_image_url = get_the_post_thumbnail_url( $service->getId(), 'thumbnail' );
}

$attendant_name = '';
$attendant_image_url = '';
if ( $attendant && ! $attendant->isEmpty() ) {
    $attendant_name = esc_html( $attendant->getName() );
    if ( has_post_thumbnail( $attendant->getId() ) ) {
        $attendant_image_url = get_the_post_thumbnail_url( $attendant->getId(), 'thumbnail' );
    }
}
?>
<div class="sln-forecast-cards">

    <div class="sln-forecast-cards__header">
        <?php if ( ! empty( $customer_name ) ) : ?>
            <h3 class="sln-forecast-cards__greeting">
                <?php
                printf(
                    // translators: %s = customer first name
                    esc_html__( 'Welcome back, %s!', 'salon-booking-system' ),
                    '<strong>' . esc_html( $customer_name ) . '</strong>'
                )
                ?>
            </h3>
        <?php else : ?>
            <h3 class="sln-forecast-cards__greeting">
                <?php esc_html_e( 'Welcome back!', 'salon-booking-system' ) ?>
            </h3>
        <?php endif ?>
        <p class="sln-forecast-cards__subtitle">
            <?php esc_html_e( 'Ready for your next appointment? Choose a date and we\'ll take care of the rest.', 'salon-booking-system' ) ?>
        </p>
    </div>

    <div class="sln-forecast-cards__service-info">

        <?php if ( $service_image_url ) : ?>
            <img src="<?php echo esc_url( $service_image_url ) ?>"
                 alt="<?php echo $service_name ?>"
                 class="sln-forecast-cards__service-img" />
        <?php endif ?>

        <div class="sln-forecast-cards__service-details">
            <span class="sln-forecast-cards__service-name"><?php echo $service_name ?></span>
            <?php if ( $service_duration ) : ?>
                <span class="sln-forecast-cards__service-duration"><?php echo esc_html( $service_duration ) ?></span>
            <?php endif ?>
            <span class="sln-forecast-cards__service-price"><?php echo esc_html( $service_price ) ?></span>
        </div>

        <?php if ( $attendant_name ) : ?>
            <span class="sln-forecast-cards__attendant-name">
                <?php
                printf(
                    // translators: %s = attendant name in uppercase
                    esc_html__( 'with %s', 'salon-booking-system' ),
                    strtoupper( $attendant_name )
                )
                ?>
            </span>
        <?php endif ?>

    </div><!-- /.service-info -->

    <div class="sln-forecast-cards__slots" role="radiogroup"
         aria-label="<?php esc_attr_e( 'Choose your appointment date', 'salon-booking-system' ) ?>">

        <?php foreach ( $suggestions as $i => $slot ) :
            $slot_date     = esc_attr( $slot['date'] );
            $slot_time     = esc_attr( $slot['time'] );
            $slot_att_id   = (int) $slot['attendant_id'];
            $slot_label    = esc_html( $slot['label'] );
            $dt            = new SLN_DateTime( $slot['date'] . ' ' . $slot['time'] );
            $display_day   = esc_html( SLN_TimeFunc::translateDate( 'l', $dt->getTimestamp() ) );
            $display_date  = esc_html( $plugin->format()->date( $dt ) );
            $display_time  = esc_html( $plugin->format()->time( $dt ) );
            $card_id       = 'sln-forecast-slot-' . $i;
        ?>
            <label class="sln-forecast-cards__slot <?php echo $i === 0 ? 'is-selected' : '' ?>"
                   for="<?php echo esc_attr( $card_id ) ?>">
                <input type="radio"
                       id="<?php echo esc_attr( $card_id ) ?>"
                       name="sln_forecast_slot_index"
                       value="<?php echo (int) $i ?>"
                       class="sln-forecast-cards__slot-radio js-sln-forecast-slot"
                       data-date="<?php echo $slot_date ?>"
                       data-time="<?php echo $slot_time ?>"
                       data-service-id="<?php echo (int) $service_id ?>"
                       data-attendant-id="<?php echo $slot_att_id ?>"
                       <?php echo $i === 0 ? 'checked' : '' ?> />

                <span class="sln-forecast-cards__slot-text">
                    <span class="sln-forecast-cards__slot-primary"><?php echo $display_day ?>, <?php echo $display_date ?> &nbsp;&ndash;&nbsp; <?php echo $display_time ?></span>
                    <span class="sln-forecast-cards__slot-label"><?php echo $slot_label ?></span>
                </span>
            </label>
        <?php endforeach ?>

    </div><!-- /.slots -->

    <?php include __DIR__ . '/_forecast_notify_optin.php' ?>

    <!-- Hidden fields populated by JS on card selection (and pre-filled for first card) -->
    <input type="hidden" name="sln_forecast_date"        value="<?php echo esc_attr( $suggestions[0]['date'] ) ?>" id="sln-forecast-date-input" />
    <input type="hidden" name="sln_forecast_time"        value="<?php echo esc_attr( $suggestions[0]['time'] ) ?>" id="sln-forecast-time-input" />
    <input type="hidden" name="sln_forecast_service_id"  value="<?php echo esc_attr( $service_id ) ?>"            id="sln-forecast-service-input" />
    <input type="hidden" name="sln_forecast_attendant_id" value="<?php echo esc_attr( $suggestions[0]['attendant_id'] ) ?>" id="sln-forecast-attendant-input" />

    <?php if ( empty( $is_large ) ) : ?>
    <div class="sln-forecast-cards__footer">

        <?php
        // Build the initial data-salon-data with the first suggestion's slot values.
        // JS (sln_updateForecastSlotData) will update this when the user selects a
        // different card, but having the values here means the first card always works
        // even if JS hasn't run yet.
        $initial_salon_data = esc_attr(
            "sln_step_page={$current}"
            . "&{$submitName}=next"
            . "&sln_forecast_date={$suggestions[0]['date']}"
            . "&sln_forecast_time={$suggestions[0]['time']}"
            . "&sln_forecast_service_id={$service_id}"
            . "&sln_forecast_attendant_id={$suggestions[0]['attendant_id']}"
        );
        ?>
        <button type="submit"
            id="sln-forecast-submit"
            class="sln-forecast-cards__book-btn js-sln-forecast-confirm"
            <?php if ( $ajaxEnabled ) : ?>
                data-salon-data="<?php echo $initial_salon_data ?>"
                data-salon-toggle="next"
            <?php endif ?>
            name="<?php echo esc_attr( $submitName ) ?>"
            value="next">
            <?php esc_html_e( 'Book now', 'salon-booking-system' ) ?>
        </button>

        <button type="submit"
            class="sln-forecast-cards__skip js-sln-forecast-skip"
            <?php if ( $ajaxEnabled ) : ?>
                data-salon-data="<?php echo esc_attr( "sln_step_page={$current}&{$submitName}=next&sln_skip_forecast=1" ) ?>"
                data-salon-toggle="next"
            <?php endif ?>
            name="<?php echo esc_attr( $submitName ) ?>"
            value="next">
            <input type="hidden" name="sln_skip_forecast" value="1" class="js-sln-forecast-skip-input" disabled />
            <?php esc_html_e( 'I want different options', 'salon-booking-system' ) ?>
        </button>

    </div><!-- /.footer -->
    <?php endif ?>

</div><!-- /.sln-forecast-cards -->
