<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var string $content
 * @var SLN_Shortcode_Salon $salon
 * @var SLN_Plugin $plugin
 */

$style = $salon->getStyleShortcode();
$cce = !$plugin->getSettings()->isCustomColorsEnabled();
$class = SLN_Enum_ShortcodeStyle::getClass($style);

$class_salon = $class;
$class_salon .= ' sln-step-' . $salon->getCurrentStep();
$class_salon .= !$cce ? ' sln-customcolors' : '';

$class_salon_content = $class . '__content';
$class_salon_content .= ' sln-salon__content-step-' . $salon->getCurrentStep();

$bookingMyAccountPageId = $plugin->getSettings()->getBookingmyaccountPageId();
$builder = $plugin->getBookingBuilder();
$clientId = $builder->getClientId();
$storageStrategy = $builder->isUsingTransient() ? 'transient' : 'session';
?>

<script>
window.SLN_BOOKING_CLIENT = {
    id: <?php echo $clientId ? "'" . esc_js($clientId) . "'" : 'null'; ?>,
    storage: '<?php echo esc_js($storageStrategy); ?>'
};
</script>

<div id="sln-salon-booking" class="sln-shortcode sln-is-initializing <?php echo $class_salon ?>"
     data-client-id="<?php echo esc_attr($clientId); ?>"
     data-storage="<?php echo esc_attr($storageStrategy); ?>">
    <div class="sln-init-loader" aria-hidden="true">
        <div class="sln-loader-wrapper">
            <div class="sln-loader">Loading...</div>
        </div>
    </div>
    <div id="sln-salon-booking__content" class="<?php echo $class_salon_content ?>">
        <?php
        if ($bookingMyAccountPageId && !$plugin->getSettings()->get('enabled_force_guest_checkout')) {
            $accountUrl  = get_permalink($bookingMyAccountPageId);
            $accountIcon = '<svg class="sln-topbar__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><path d="M16,2A14,14,0,1,0,30,16,14,14,0,0,0,16,2ZM10,26.39a6,6,0,0,1,11.94,0,11.87,11.87,0,0,1-11.94,0Zm13.74-1.26a8,8,0,0,0-15.54,0,12,12,0,1,1,15.54,0ZM16,8a5,5,0,1,0,5,5A5,5,0,0,0,16,8Zm0,8a3,3,0,1,1,3-3A3,3,0,0,1,16,16Z"></path></svg>';
            $accountLabel = is_user_logged_in()
                ? wp_get_current_user()->display_name
                : __('Your account', 'salon-booking-system');
            echo '<div class="sln-topbar">';
            echo '<a class="sln-topbar__account" href="' . esc_url($accountUrl) . '" title="' . esc_attr($accountLabel) . '" aria-label="' . esc_attr($accountLabel) . '">';
            echo $accountIcon;
            echo '</a>';
            echo '</div>';
        } //// $bookingMyAccountPageId  && !$plugin->getSettings()->get('enabled_force_guest_checkout') // END ////
        $args = array(
            'key' => 'Book an appointment',
            'label' => __('Book an appointment', 'salon-booking-system'),
            'tag' => 'h2',
            'textClasses' => 'sln-salon-title',
            'inputClasses' => '',
            'tagClasses' => 'sln-salon-title',
        );
        echo $plugin->loadView('shortcode/_editable_snippet', $args);
        do_action('sln.booking.salon.before_content', $salon, $content);

        $step = $salon->getStepObject($salon->getCurrentStep());
        $additional_errors = !empty($additional_errors) ? $additional_errors : $step->getAddtitionalErrors();
        $errors = !empty($errors) ? $errors : $step->getErrors();
        echo $plugin->loadView('shortcode/_errors', ['errors' => $errors]);
        echo $plugin->loadView('shortcode/_additional_errors', ['additional_errors' => $additional_errors]);
        echo apply_filters('sln.booking.salon.' . $step->getStep() . '-step.add-params-html', '');
        $args = array(
            'key' => $step->getTitleKey(),
            'label' => $step->getTitleLabel(),
            'tag' => 'h2',
            'textClasses' => 'salon-step-title',
            'inputClasses' => '',
            'tagClasses' => 'salon-step-title',
        );
        echo $plugin->loadView('shortcode/_editable_snippet', $args);
        echo $plugin->loadView('shortcode/_progbar', ['salon' => $salon]);
        ?>
        <?php echo $content ?>
        <div id="sln-notifications" class="sln-notifications--fix--tr"></div>
        <div id="sln-salon__follower"></div>
    </div>
    <!-- .sln-salon__wrapper // END -->
</div>