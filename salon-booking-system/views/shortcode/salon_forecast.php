<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Master view for the one-click booking forecast step.
 *
 * Variables passed from ForecastStep::getForecastViewData():
 *   $forecast_state  'login' | 'cards' | 'skip'
 *   $suggestions     array of slot proposals
 *   $service         SLN_Wrapper_Service|null
 *   $attendant       SLN_Wrapper_Attendant|null
 *   $customer_name   string
 *   $errors          array
 *   $settings        SLN_Settings
 *   $plugin          SLN_Plugin
 *   $ajaxEnabled     bool
 *   $current         string  ('forecast')
 *   $submitName      string  ('submit_forecast')
 *   $formAction      string
 *   $step            SLN_Shortcode_Salon_ForecastStep
 */

// STATE: skip — should not be rendered (session flag set by step class), but
// guard here in case of direct render calls.
if ( 'skip' === $forecast_state ) {
    return;
}

$size     = SLN_Enum_ShortcodeStyle::getSize( $settings->getStyleShortcode() );
$is_large = ( 900 === (int) $size );
?>
<form method="post"
      action="<?php echo esc_url( $formAction ) ?>"
      id="salon-step-forecast"
      class="sln-forecast-step">

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="sln-forecast__errors">
            <?php foreach ( $errors as $error ) : ?>
                <p class="sln-alert sln-alert--danger"><?php echo wp_kses_post( $error ) ?></p>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if ( $is_large && 'cards' === $forecast_state ) : ?>
        <div class="row sln-box--main sln-box--flatbottom--phone">
            <div class="col-xs-12 col-md-8 sln-forecast-step__main">
    <?php endif ?>

    <?php if ( 'login' === $forecast_state ) : ?>
        <?php include __DIR__ . '/_forecast_login.php' ?>
    <?php elseif ( 'cards' === $forecast_state ) : ?>
        <?php include __DIR__ . '/_forecast_cards.php' ?>
    <?php endif ?>

    <?php include __DIR__ . '/_forecast_form_actions.php' ?>

</form>
