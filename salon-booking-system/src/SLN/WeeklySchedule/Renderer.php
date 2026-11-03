<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Single renderer of the weekly schedule, used by the shortcode, the Elementor
 * widget and the REST endpoint.
 */
class SLN_WeeklySchedule_Renderer
{
    /**
     * @param array $rawArgs Raw shortcode/widget attributes.
     *
     * @return string HTML.
     */
    public static function render($rawArgs)
    {
        SLN_WeeklySchedule_Assets::enqueue();

        return self::renderMarkup(SLN_WeeklySchedule_Args::normalize($rawArgs));
    }

    /**
     * @param array $args Normalized args.
     */
    public static function renderMarkup(array $args)
    {
        static $count = 0;
        $count++;

        $plugin  = SLN_Plugin::getInstance();
        $classes = 'sln-datashortcode sln-weekly-schedule';
        if ($plugin->getSettings()->isCustomColorsEnabled()) {
            $classes .= ' sln-customcolors';
        }

        $schedule = self::getSchedule($args);
        if (!$args['show_empty_days']) {
            $schedule['days'] = array_values(array_filter($schedule['days'], function ($day) {
                return !empty($day['events']);
            }));
        }

        return $plugin->loadView('shortcode/weekly_schedule', array(
            'plugin'   => $plugin,
            'schedule' => $schedule,
            'args'     => $args,
            'uid'      => 'sln-ws-' . $count . '-' . substr(md5(wp_json_encode($args)), 0, 6),
            'classes'  => apply_filters('sln_weekly_schedule_classes', $classes, $args),
        ));
    }

    /**
     * @param array $args Normalized args.
     */
    public static function getSchedule(array $args)
    {
        SLN_TimeFunc::startRealTimezone();
        $schedule = SLN_WeeklySchedule_Cache::get($args);
        if (null === $schedule) {
            $builder  = new SLN_WeeklySchedule_Builder(SLN_Plugin::getInstance(), $args);
            $schedule = $builder->build();
            SLN_WeeklySchedule_Cache::set($args, $schedule);
        }
        SLN_TimeFunc::endRealTimezone();

        return $schedule;
    }

    /**
     * Tags and attributes of the widget markup, for contexts that must echo it
     * (Elementor widget).
     */
    public static function getAllowedHtml()
    {
        $aria = array('aria-controls' => true, 'aria-selected' => true, 'aria-labelledby' => true, 'aria-label' => true, 'aria-hidden' => true, 'aria-live' => true, 'aria-busy' => true);
        $base = array('id' => true, 'class' => true, 'role' => true, 'tabindex' => true, 'data-*' => true, 'style' => true);

        return array(
            'div'     => $base + $aria,
            'section' => $base + $aria,
            'h2'      => $base + $aria,
            'h3'      => $base,
            'p'       => $base,
            'ul'      => $base,
            'li'      => $base,
            'span'    => $base + $aria,
            'time'    => $base + array('datetime' => true),
            'a'       => $base + $aria + array('href' => true),
            'button'  => $base + $aria + array('type' => true, 'disabled' => true),
        );
    }
}
