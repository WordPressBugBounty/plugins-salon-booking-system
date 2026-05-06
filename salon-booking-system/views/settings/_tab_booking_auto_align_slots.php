<?php
/**
 * Auto-Align Time Slots Setting
 *
 * This setting prevents booking chaos for long-duration services by filtering
 * available time slots to only show times aligned with the service duration.
 *
 * Example: A 2-hour service will only show slots at 09:00, 11:00, 13:00, 15:00, 17:00
 * instead of 09:00, 09:15, 09:30, 09:45... which can cause scheduling conflicts.
 */
?>
<div id="sln-auto_align_slots" class="sln-box sln-box--main">
    <h2 class="sln-box-title">
        <?php esc_html_e('Auto-align time slots', 'salon-booking-system'); ?>
        <span class="block">
            <?php esc_html_e('Align available time slots with service duration to prevent booking conflicts', 'salon-booking-system'); ?>
        </span>
    </h2>
    <div class="row">
        <div class="col-xs-12 col-md-5 form-group sln-checkbox">
            <?php $helper->row_input_checkbox(
                'auto_align_slots',
                __('Enable auto-align time slots', 'salon-booking-system'),
                array('help' => __('When enabled, customers will only see time slots aligned with the service duration. For example, a 2-hour service will show slots every 2 hours (09:00, 11:00, 13:00...) instead of every 15 minutes. This prevents customers from booking at misaligned times that would block optimal scheduling.', 'salon-booking-system'))
            ); ?>
        </div>
        <div class="col-xs-12 col-md-7">
            <div class="sln-alert sln-alert--info sln-alert--topicon sln-auto-align-info">
                <p><strong><?php esc_html_e('How it works:', 'salon-booking-system'); ?></strong></p>
                <p><?php esc_html_e('Slots are aligned starting from your first opening slot each day. Examples assuming a 09:00 opening time:', 'salon-booking-system'); ?></p>
                <ul class="sln-auto-align-examples">
                    <li>
                        <span class="sln-auto-align-examples__duration"><?php esc_html_e('30 min', 'salon-booking-system'); ?></span>
                        <span class="sln-auto-align-examples__slots">09:00, 09:30, 10:00, 10:30&hellip;</span>
                    </li>
                    <li>
                        <span class="sln-auto-align-examples__duration"><?php esc_html_e('45 min', 'salon-booking-system'); ?></span>
                        <span class="sln-auto-align-examples__slots">09:00, 09:45, 10:30, 11:15&hellip;</span>
                    </li>
                    <li>
                        <span class="sln-auto-align-examples__duration"><?php esc_html_e('60 min', 'salon-booking-system'); ?></span>
                        <span class="sln-auto-align-examples__slots">09:00, 10:00, 11:00, 12:00&hellip;</span>
                    </li>
                    <li>
                        <span class="sln-auto-align-examples__duration"><?php esc_html_e('75 min', 'salon-booking-system'); ?></span>
                        <span class="sln-auto-align-examples__slots">09:00, 10:15, 11:30, 12:45&hellip;</span>
                    </li>
                    <li>
                        <span class="sln-auto-align-examples__duration"><?php esc_html_e('2 hours', 'salon-booking-system'); ?></span>
                        <span class="sln-auto-align-examples__slots">09:00, 11:00, 13:00, 15:00&hellip;</span>
                    </li>
                </ul>
                <p class="sln-auto-align-multiservice">
                    <em><?php esc_html_e('For multi-service bookings, the combined total duration of all selected services is used for alignment.', 'salon-booking-system'); ?></em>
                </p>
            </div>
        </div>
    </div>
</div>
