<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [salon_booking_weekly_schedule days="mon,tue" categories="yoga" show_spots="yes"]
 */
class SLN_WeeklySchedule_Shortcode
{
    const NAME = 'salon_booking_weekly_schedule';

    public static function init()
    {
        add_shortcode(self::NAME, array(__CLASS__, 'create'));
    }

    public static function create($attrs)
    {
        $attrs = is_array($attrs) ? $attrs : array();
        // The week is chosen by the visitor through the navigation, never by the shortcode.
        unset($attrs['week']);

        return SLN_WeeklySchedule_Renderer::render($attrs);
    }
}
