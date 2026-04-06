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
<div id="sln-auto_align_slots" class="sln-box sln-box--main sln-box--main--small">
    <h2 class="sln-box-title">
        <?php esc_html_e('Auto-align time slots', 'salon-booking-system'); ?>
        <span class="block">
            <?php esc_html_e('Align available time slots with service duration to prevent booking conflicts', 'salon-booking-system'); ?>
        </span>
    </h2>
    <div class="row">
        <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-checkbox">
            <?php $helper->row_input_checkbox(
                'auto_align_slots',
                __('Enable auto-align time slots', 'salon-booking-system'),
                array('help' => __('When enabled, customers will only see time slots aligned with the service duration. For example, a 2-hour service will show slots every 2 hours (09:00, 11:00, 13:00...) instead of every 15 minutes. This prevents customers from booking at misaligned times that would block optimal scheduling.', 'salon-booking-system'))
            ); ?>
            <div class="sln-alert sln-alert--info">
                <p><strong><?php esc_html_e('How it works:', 'salon-booking-system'); ?></strong></p>
                <p><?php esc_html_e('Slots are aligned starting from your first opening slot each day. Examples assuming a 09:00 opening time:', 'salon-booking-system'); ?></p>
                <ul style="margin-left: 20px; list-style-type: disc;">
                    <li><?php esc_html_e('30-minute service → Slots every 30 min: 09:00, 09:30, 10:00, 10:30...', 'salon-booking-system'); ?></li>
                    <li><?php esc_html_e('45-minute service → Slots every 45 min: 09:00, 09:45, 10:30, 11:15...', 'salon-booking-system'); ?></li>
                    <li><?php esc_html_e('60-minute service → Slots every 60 min: 09:00, 10:00, 11:00, 12:00...', 'salon-booking-system'); ?></li>
                    <li><?php esc_html_e('75-minute service → Slots every 75 min: 09:00, 10:15, 11:30, 12:45...', 'salon-booking-system'); ?></li>
                    <li><?php esc_html_e('2-hour service → Slots every 2 hours: 09:00, 11:00, 13:00, 15:00...', 'salon-booking-system'); ?></li>
                </ul>
                <p style="margin-top: 10px;">
                    <em><?php esc_html_e('For multi-service bookings, the combined total duration of all selected services is used for alignment.', 'salon-booking-system'); ?></em>
                </p>
            </div>
        </div>
    </div>
</div>
