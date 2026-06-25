<?php
/**
 * Clone booking popover.
 *
 * Shared between the calendar modal footer (views/admin/calendar.php) and the
 * standalone editor popup footer (views/metabox/booking.php).
 *
 * Behaviour is wired per-context in js/calendar.js and the inline script in
 * booking.php, but the markup and styling are identical.
 */
?>
<div class="sln-clone-popover" data-clone-popover hidden>
    <div class="sln-clone-popover__header">
        <span class="sln-clone-popover__title"><?php esc_html_e('Clone booking', 'salon-booking-system'); ?></span>
        <button type="button" class="sln-clone-popover__close" data-clone-close aria-label="<?php esc_attr_e('Close', 'salon-booking-system'); ?>">&times;</button>
    </div>

    <div class="sln-clone-popover__body">
        <label class="sln-clone-popover__option">
            <input type="radio" name="clone_mode" value="repeat" checked>
            <span><?php esc_html_e('Repeat on a schedule', 'salon-booking-system'); ?></span>
        </label>
        <label class="sln-clone-popover__option">
            <input type="radio" name="clone_mode" value="specific">
            <span><?php esc_html_e('Pick a specific date', 'salon-booking-system'); ?></span>
        </label>

        <div class="sln-clone-popover__panel" data-clone-panel="repeat">
            <div class="sln-clone-popover__row">
                <input type="number" name="unit_times_input" min="1" value="1" class="sln-clone-popover__num" />
                <span class="times" data-text_s="<?php esc_attr_e('copy', 'salon-booking-system'); ?>" data-text_m="<?php esc_attr_e('copies', 'salon-booking-system'); ?>"><?php esc_html_e('copy', 'salon-booking-system'); ?></span>
                <select name="week_time" class="sln-clone-popover__select">
                    <option value="1"><?php esc_html_e('every week', 'salon-booking-system'); ?></option>
                    <option value="2"><?php esc_html_e('every two weeks', 'salon-booking-system'); ?></option>
                    <option value="3"><?php esc_html_e('every three week', 'salon-booking-system'); ?></option>
                    <option value="4"><?php esc_html_e('every four week', 'salon-booking-system'); ?></option>
                </select>
            </div>
            <p class="sln-clone-popover__preview time_until"><?php esc_html_e('Last copy', 'salon-booking-system'); ?>: <span class="time_date">&mdash;</span></p>
        </div>

        <div class="sln-clone-popover__panel" data-clone-panel="specific" hidden>
            <p class="sln-clone-popover__note">
                <span class="dashicons dashicons-info-outline"></span>
                <span><?php printf( esc_html__('Opens an editable copy on the %1$sDate%2$s tab where you choose the new date & time (availability shown). Your original booking will not change.', 'salon-booking-system'), '<strong>', '</strong>' ); ?></span>
            </p>
        </div>
    </div>

    <div class="sln-clone-popover__footer">
        <button type="button" class="sln-clone-popover__cancel" data-clone-close><?php esc_html_e('Cancel', 'salon-booking-system'); ?></button>
        <button type="button" class="sln-clone-popover__confirm" data-clone-confirm><?php esc_html_e('Clone', 'salon-booking-system'); ?></button>
    </div>
</div>
