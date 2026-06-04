<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Forecast step – email notification opt-in widget.
 *
 * Variables:
 *   $notify_optin  bool  Whether the customer is already subscribed.
 */
$notify_optin = ! empty( $notify_optin );
?>
<div class="sln-forecast-notify" role="group" aria-labelledby="sln-forecast-notify-label">
    <div class="sln-forecast-notify__icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
            <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
        </svg>
    </div>

    <div class="sln-forecast-notify__text" id="sln-forecast-notify-label">
        <strong class="sln-forecast-notify__title">
            <?php esc_html_e( 'Notify me on suitable slots by email', 'salon-booking-system' ) ?>
        </strong>
        <span class="sln-forecast-notify__desc">
            <?php esc_html_e( 'We\'ll email you available slots when it\'s time to book — no spam ever.', 'salon-booking-system' ) ?>
        </span>
    </div>

    <label class="sln-forecast-notify__toggle" title="<?php esc_attr_e( 'Toggle email notifications', 'salon-booking-system' ) ?>">
        <input type="checkbox"
               name="sln_forecast_notify_optin"
               value="1"
               class="sln-forecast-notify__checkbox"
               <?php checked( $notify_optin ) ?> />
        <span class="sln-forecast-notify__switch" aria-hidden="true"></span>
        <span class="screen-reader-text">
            <?php esc_html_e( 'Notify me on suitable slots by email', 'salon-booking-system' ) ?>
        </span>
    </label>
</div>
