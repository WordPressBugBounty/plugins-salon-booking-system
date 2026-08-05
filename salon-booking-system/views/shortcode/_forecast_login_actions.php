<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Forecast login CTAs for the large-format actions column.
 *
 * Expects: $submitName, $ajaxEnabled, $current
 */
?>
<div class="sln-forecast-login__submit sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth">
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
