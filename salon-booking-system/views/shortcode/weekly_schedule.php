<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Weekly schedule markup, shared by the shortcode, the Elementor widget and
 * the REST endpoint (which returns this same markup for another week).
 *
 * @var array      $schedule Output of SLN_WeeklySchedule_Builder::build().
 * @var array      $args     Normalized args.
 * @var string     $uid      Unique DOM id prefix.
 * @var string     $classes  Root classes.
 * @var SLN_Plugin $plugin
 */

$format     = $plugin->format();
$weekStart  = new SLN_DateTime($schedule['week_start']);
$weekEnd    = new SLN_DateTime($schedule['week_end']);
// Preselect today, or the next shown day (empty days may be hidden).
$today      = current_time('Y-m-d');
$activeDate = '';
foreach ($schedule['days'] as $day) {
    if ($day['date'] >= $today) {
        $activeDate = $day['date'];
        break;
    }
}
if (!$activeDate && $schedule['days']) {
    $activeDate = $schedule['days'][0]['date'];
}

// Categories of the week, for the legend.
$categories = array();
foreach ($schedule['days'] as $day) {
    foreach ($day['events'] as $event) {
        if ($event['category_name'] !== '' && !isset($categories[$event['category_slug']])) {
            $categories[$event['category_slug']] = $event;
        }
    }
}

$statusLabels = array(
    SLN_WeeklySchedule_Builder::STATUS_FULL   => _x('Full', 'class status in the weekly schedule', 'salon-booking-system'),
    SLN_WeeklySchedule_Builder::STATUS_PAST   => __('Ended', 'salon-booking-system'),
    SLN_WeeklySchedule_Builder::STATUS_CLOSED => __('Booking closed', 'salon-booking-system'),
);
?>
<div id="<?php echo esc_attr($uid); ?>" class="<?php echo esc_attr($classes); ?>"
     data-sln-ws-week="<?php echo esc_attr($schedule['week_start']); ?>"
     data-sln-ws-args="<?php echo esc_attr(wp_json_encode(SLN_WeeklySchedule_Args::toPublic($args))); ?>">

    <div class="sln-weekly-schedule__header">
        <button type="button" class="sln-weekly-schedule__nav sln-weekly-schedule__nav--prev"
                data-sln-ws-goto="<?php echo esc_attr((string) $schedule['prev']); ?>"
                <?php disabled(empty($schedule['prev'])); ?>>
            <span aria-hidden="true">&lsaquo;</span>
            <span class="sr-only"><?php esc_html_e('Previous week', 'salon-booking-system'); ?></span>
        </button>
        <h2 class="sln-weekly-schedule__range" aria-live="polite">
            <?php echo esc_html($format->date($weekStart) . ' – ' . $format->date($weekEnd)); ?>
        </h2>
        <button type="button" class="sln-weekly-schedule__nav sln-weekly-schedule__nav--next"
                data-sln-ws-goto="<?php echo esc_attr((string) $schedule['next']); ?>"
                <?php disabled(empty($schedule['next'])); ?>>
            <span aria-hidden="true">&rsaquo;</span>
            <span class="sr-only"><?php esc_html_e('Next week', 'salon-booking-system'); ?></span>
        </button>
    </div>
    <p class="sln-weekly-schedule__loading sr-only" role="status"></p>

    <?php if (!$schedule['days']) : ?>
        <p class="sln-weekly-schedule__empty"><?php esc_html_e('No classes this week.', 'salon-booking-system'); ?></p>
    <?php else : ?>
        <div class="sln-weekly-schedule__tabs" role="tablist"
             aria-label="<?php esc_attr_e('Days of the week', 'salon-booking-system'); ?>"
             style="<?php echo esc_attr('--sln-ws-days: ' . count($schedule['days']) . ';'); ?>">
            <?php foreach ($schedule['days'] as $day) :
                $ts       = strtotime($day['date'] . ' 12:00');
                $isActive = $day['date'] === $activeDate;
                ?>
                <button type="button" role="tab"
                        id="<?php echo esc_attr($uid . '-tab-' . $day['date']); ?>"
                        class="sln-weekly-schedule__tab<?php echo $day['is_today'] ? ' is-today' : ''; ?><?php echo $isActive ? ' is-active' : ''; ?><?php echo $day['events'] ? '' : ' is-empty'; ?>"
                        aria-controls="<?php echo esc_attr($uid . '-day-' . $day['date']); ?>"
                        aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                        tabindex="<?php echo $isActive ? '0' : '-1'; ?>"
                        data-date="<?php echo esc_attr($day['date']); ?>">
                    <span class="sln-weekly-schedule__tab-label"><?php echo esc_html(date_i18n('D', $ts)); ?></span>
                    <span class="sr-only"><?php echo esc_html(date_i18n('j F', $ts)); ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <?php if ($categories) : ?>
            <ul class="sln-weekly-schedule__legend" aria-label="<?php esc_attr_e('Categories', 'salon-booking-system'); ?>">
                <?php foreach ($categories as $slug => $category) : ?>
                    <li class="sln-weekly-schedule__legend-item" data-category="<?php echo esc_attr($slug); ?>"
                        <?php echo $category['category_color'] ? 'style="' . esc_attr('--sln-ws-category: ' . $category['category_color'] . ';') . '"' : ''; ?>>
                        <span class="sln-weekly-schedule__dot" aria-hidden="true"></span>
                        <?php echo esc_html($category['category_name']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="sln-weekly-schedule__days">
            <?php foreach ($schedule['days'] as $day) :
                $ts       = strtotime($day['date'] . ' 12:00');
                $dayLabel = date_i18n('l j F', $ts);
                ?>
                <section id="<?php echo esc_attr($uid . '-day-' . $day['date']); ?>"
                         class="sln-weekly-schedule__day<?php echo $day['is_today'] ? ' is-today' : ''; ?><?php echo $day['date'] === $activeDate ? ' is-active' : ''; ?>"
                         role="tabpanel" tabindex="0"
                         aria-labelledby="<?php echo esc_attr($uid . '-tab-' . $day['date']); ?>"
                         data-date="<?php echo esc_attr($day['date']); ?>">
                    <h3 class="sln-weekly-schedule__day-title">
                        <?php echo esc_html(date_i18n('l', $ts)); ?>
                        <span class="sr-only"><?php echo esc_html(date_i18n('j F', $ts)); ?></span>
                    </h3>

                    <?php if (!$day['events']) : ?>
                        <p class="sln-weekly-schedule__empty"><?php esc_html_e('No classes on this day.', 'salon-booking-system'); ?></p>
                    <?php else : ?>
                        <ul class="sln-weekly-schedule__list">
                            <?php foreach ($day['events'] as $event) :
                                $status   = $event['status'];
                                $bookable = SLN_WeeklySchedule_Builder::STATUS_AVAILABLE === $status;
                                $meta     = array();
                                if ($args['show_assistant'] && $event['assistant_name'] !== '') {
                                    $meta[] = $event['assistant_name'];
                                }
                                $meta[]    = $event['duration_label'];
                                $itemStyle = $event['category_color'] ? '--sln-ws-category: ' . $event['category_color'] . ';' : '';
                                /* translators: 1: service name, 2: day, 3: time */
                                $srLabel = sprintf(__('%1$s, %2$s at %3$s', 'salon-booking-system'), $event['service_name'], $dayLabel, $event['time_label']);
                                ?>
                                <li class="sln-weekly-schedule__item sln-weekly-schedule__item--<?php echo esc_attr($status); ?>"
                                    data-service="<?php echo esc_attr($event['service_id']); ?>"
                                    data-category="<?php echo esc_attr($event['category_slug']); ?>"
                                    data-assistant="<?php echo esc_attr($event['assistant_id']); ?>"
                                    data-status="<?php echo esc_attr($status); ?>"
                                    <?php echo $itemStyle ? 'style="' . esc_attr($itemStyle) . '"' : ''; ?>>
                                    <time class="sln-weekly-schedule__time" datetime="<?php echo esc_attr($event['date'] . 'T' . $event['time']); ?>"><?php echo esc_html($event['time_label']); ?></time>
                                    <span class="sln-weekly-schedule__dot" data-category="<?php echo esc_attr($event['category_slug']); ?>" aria-hidden="true"></span>
                                    <div class="sln-weekly-schedule__info">
                                        <span class="sln-weekly-schedule__name"><?php echo esc_html($event['service_name']); ?></span>
                                        <?php if ($event['category_name'] !== '') : ?>
                                            <span class="sr-only"><?php echo esc_html($event['category_name']); ?></span>
                                        <?php endif; ?>
                                        <span class="sln-weekly-schedule__details">
                                            <span class="sln-weekly-schedule__meta"><?php echo esc_html(implode(' · ', $meta)); ?></span>
                                            <?php if ($bookable && $args['show_spots'] && null !== $event['spots']) : ?>
                                                <span class="sln-weekly-schedule__spots">
                                                    <?php
                                                    /* translators: %d: number of remaining spots */
                                                    echo esc_html(sprintf(_n('%d spot left', '%d spots left', $event['spots'], 'salon-booking-system'), $event['spots']));
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($bookable && $args['booking_url']) : ?>
                                        <a class="sln-weekly-schedule__book" href="<?php echo esc_url(SLN_WeeklySchedule_BookRedirect::getUrl($args['booking_url'], $event, $args['book_step'])); ?>">
                                            <?php esc_html_e('Book', 'salon-booking-system'); ?>
                                            <span class="sr-only"><?php echo esc_html($srLabel); ?></span>
                                        </a>
                                    <?php else : ?>
                                        <button type="button" class="sln-weekly-schedule__book" disabled>
                                            <?php echo esc_html(isset($statusLabels[$status]) ? $statusLabels[$status] : __('Book', 'salon-booking-system')); ?>
                                            <span class="sr-only"><?php echo esc_html($srLabel); ?></span>
                                        </button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <?php if ($args['note'] !== '') : ?>
            <p class="sln-weekly-schedule__note"><?php echo esc_html($args['note']); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
