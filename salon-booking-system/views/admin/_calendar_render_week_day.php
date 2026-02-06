<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * 
 */
$weekStart = $calendar->getFrom();
$isPro = (defined('SLN_VERSION_PAY') && SLN_VERSION_PAY);

if ($calendar->getAttendantMode()) {
    foreach ($attendant as $att): ?>
        <div class="cal-row-fluid">
            <div class="weekday-attendant cal-cell0">
                <!-- <div class="day-highlight dh-attendant"><?php echo SLN_Plugin::getInstance()->createAttendant($att)->getName(); ?></div> -->
                <div class="day-highlight dh-attendant">
                    <?php
                    $attendant_obj = SLN_Plugin::getInstance()->createAttendant($att);
                    $assistant_id = $attendant_obj->getId();
                    $has_thumbnail = has_post_thumbnail($assistant_id);
                    $thumbnail_url = $has_thumbnail ? get_the_post_thumbnail_url($assistant_id, 'thumbnail') : '';
                    ?>
                    <span class="assistant-name"><?php echo $attendant_obj->getName(); ?></span>
                    <?php if ($has_thumbnail): ?>
                        <div class="assistant-avatar">
                            <span class="assistant-image"><img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($attendant_obj->getName()); ?>" class=""></span>
                        </div>
                    <?php else: ?>
                        <div class="assistant-avatar assistant-placeholder">
                            <span class="assistant-initials"><?php echo strtoupper(substr($attendant_obj->getName(), 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php for ($i = 0; $i < 7; $i++): ?>
                <div class="weekday<?php echo $i; ?> cal-cell0 <?php echo $calendar->isAttendantAvailable($att, $i, true) ? '' : 'not-available'; ?>">
                    <?php foreach ($bookings[$att] as $booking):
                        if ($booking->from == $weekStart->format('N')): ?>
                            <div class="<?php echo (get_post_meta($booking->id, 'no_show', true) == 1 ? 'no-show' : ''); ?> day-highlight dh-<?php echo $booking->displayClass; ?>"
                                data-event-class="<?php echo $booking->displayClass; ?>"
                                <?php
                                // Include the centralized data attributes function
                                include_once SLN_PLUGIN_DIR . '/views/admin/_calendar_tooltip_data_attributes.php';
                                echo generateTooltipDataAttributes($booking, $isPro, 'booking');
                                ?>>
                                <span class="sln-week-days-tooltip" data-tooltip-id="<?php echo $booking->id; ?>">
                                    <span data-event-id="<?php echo $booking->id; ?>" class="event-item cal-event-week event<?php echo $booking->id; ?>"><?php echo $booking->title; ?></span>
                                    <span class="sln-event-header-more-icon sln-event-header-more-icon-vertical" data-tooltip-id="<?php echo $booking->id; ?>"></span>
                                </span>
                            </div>
                    <?php endif;
                    endforeach; ?>
                </div>
            <?php $weekStart->modify('1 day');
            endfor; ?>
        </div>
    <?php endforeach;
} else { ?>
    <div class="cal-row-fluid">
        <?php for ($i = 0; $i < 7; $i++): ?>
            <div class="weekday<?php echo $i; ?> cal-cell1">
                <?php foreach ($bookings as $booking):
                    if ($booking->from == $weekStart->format('N')): ?>
                        <div class="<?php echo (get_post_meta($booking->id, 'no_show', true) == 1 ? 'no-show' : ''); ?> day-highlight dh-<?php echo $booking->displayClass; ?>"
                            data-event-class="<?php echo $booking->displayClass; ?>"
                            <?php
                            // Include the centralized data attributes function
                            include_once SLN_PLUGIN_DIR . '/views/admin/_calendar_tooltip_data_attributes.php';
                            echo generateTooltipDataAttributes($booking, $isPro, 'booking');
                            ?>>
                            <span class="sln-week-days-tooltip" data-tooltip-id="<?php echo $booking->id; ?>">
                                <span data-event-id="<?php echo $booking->id; ?>" class="event-item cal-event-week event<?php echo $booking->id; ?>"><?php echo $booking->title; ?></span>
                                <span class="sln-event-header-more-icon sln-event-header-more-icon-vertical" data-tooltip-id="<?php echo $booking->id; ?>"></span>
                            </span>
                        </div>
                <?php endif;
                endforeach; ?>
            </div>
        <?php $weekStart->modify('1 day');
        endfor; ?>
    </div>
<?php } ?>