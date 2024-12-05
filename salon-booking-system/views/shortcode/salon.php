<?php
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
?>

<div id="sln-salon-booking" class="sln-shortcode <?php echo $class_salon ?>">
    <div id="sln-salon-booking__content" class="<?php echo $class_salon_content ?>">
        <?php
        if ($bookingMyAccountPageId && !$plugin->getSettings()->get('enabled_force_guest_checkout') ) {
            echo '<div class="sln-topbar"><h6>';
            if (is_user_logged_in()){
                $current_user = wp_get_current_user();
                echo __('Hi', 'salon-booking-system'), ' ', $current_user->display_name . ' <a href="' . get_permalink($bookingMyAccountPageId) . '">' . __('Go to your account', 'salon-booking-system') . '</a>';
            } else {
                echo __('Already a customer?', 'salon-booking-system') . ' <a href="' . get_permalink($bookingMyAccountPageId) . '">' . __('Log-in', 'salon-booking-system') . '</a>';
            }
            echo '</h6></div>';
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
        include '_mixpanel_track.php';
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
