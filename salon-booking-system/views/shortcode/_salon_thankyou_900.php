<div class="row sln-box--main sln-box--fixed_height">
    <div class="col-xs-12">
        <div class="sln-thankyou__content sln-list">
            <?php include '_salon_thankyou_okbox.php' ?>
            <?php include '_salon_thankyou_alert.php' ?>
            <?php if (in_array($booking->getStatus(), array(SLN_Enum_BookingStatus::PAID))): ?>
                <?php include '_salon_thankyou_alert_paid.php' ?>
            <?php endif; ?>
            <div class="col-xs-12-- sln-input--action sln-form-actions-- sln-payment-actions--">
                <p><?php echo sprintf(
                        // translators: %s will be replaced by the number of seconds
                        esc_html__('You\'ll be redirected in %s seconds', 'salon-booking-system'),
                        '<span class="sln-go-to-thankyou-number"></span>'
                    ) ?></p>
                <a id="sln-go-to-thankyou" href="<?php echo esc_html($goToThankyou); ?>" class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth hide"></a>
            </div>
            <?php
            $bookingMyAccountPageId = $plugin->getSettings()->getBookingmyaccountPageId();
            if ($bookingMyAccountPageId && !$plugin->getSettings()->get('enabled_force_guest_checkout') && is_user_logged_in()) {
                $current_user = wp_get_current_user();
                echo '<div class="sln-box--formactions--summary">';
                echo '<a class="sln-btn sln-btn--fitcontent sln-btn--borderonly sln-btn--medium" href="' . esc_html(get_permalink($bookingMyAccountPageId)) . '">';
                esc_html_e('Go to your personal account', 'salon-booking-system');
                echo '</a>';
                echo '</div>';
            } //// $bookingMyAccountPageId  && !$plugin->getSettings()->get('enabled_force_guest_checkout') // END ////
            ?>
        </div>
    </div>
</div>