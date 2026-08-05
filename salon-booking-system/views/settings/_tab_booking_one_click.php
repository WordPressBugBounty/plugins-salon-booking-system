<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
/**
 * @var $plugin SLN_Plugin
 * @var $helper SLN_Admin_Settings
 *
 * Settings panel for the one-click booking / forecasting feature.
 */
?>
<div id="sln-one_click_booking" class="sln-box sln-box--main sln-box--haspanel">
    <h2 class="sln-box-title sln-box__paneltitle">
        <?php esc_html_e( 'One-click booking', 'salon-booking-system' ) ?>
    </h2>
    <div class="collapse sln-box__panelcollapse">
        <div class="row">

            <div class="col-xs-12 col-sm-8 col-md-4 form-group sln-checkbox">
                <?php $helper->row_input_checkbox(
                    'enabled_one_click_booking',
                    __( 'Enable one-click booking', 'salon-booking-system' ),
                    array(
                        'help' => __( 'When active, returning customers see a personalised booking proposal as the first step of the booking form, letting them confirm in one click. Guests see a login prompt.', 'salon-booking-system' ),
                    )
                ); ?>
            </div>

            <div class="col-xs-12 col-sm-6 col-md-4 form-group">
                <label for="sln_one_click_min_bookings">
                    <?php esc_html_e( 'Minimum past bookings required', 'salon-booking-system' ) ?>
                </label>
                <input type="number"
                       id="sln_one_click_min_bookings"
                       name="salon_settings[one_click_min_bookings]"
                       value="<?php echo (int) $plugin->getSettings()->get( 'one_click_min_bookings' ) ?: 1 ?>"
                       min="1"
                       max="20"
                       class="sln-input form-control" />
                <p class="help-block">
                    <?php esc_html_e( 'Number of completed bookings a customer must have before the forecast step is shown. Default: 1.', 'salon-booking-system' ) ?>
                </p>
            </div>

        </div>
    </div>
</div>
