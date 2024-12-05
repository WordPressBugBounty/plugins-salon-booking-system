<?php
/**
 * @var $helper SLN_Admin_Settings
 */
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<div id="sln-salon_booking_system_reset_all_settings" class="sln-box sln-box--main sln-box--haspanel">
    <h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e( 'Reset all settings', 'salon-booking-system' ); ?></h2>
    <div class="collapse sln-box__panelcollapse">
        <div class="row">
            <div class="col-xs-12">
                <div class="sln-btn sln-btn--main--tonal sln-btn--big  sln-btn--icon sln-icon--reset sln-reset-settings">
                    <input type="submit" name="reset" id="reset" class="sln-reset-settings-button" value="Reset Settings" onClick="return confirm('<?php echo esc_js( __('Do you really want to reset?', 'salon-booking-system' ) ); ?>');">
                </div>
            </div>
        </div>
    </div>
</div>