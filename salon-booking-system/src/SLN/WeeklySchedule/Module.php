<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Weekly class schedule: shortcode, Elementor widget and REST endpoint built
 * on the services' availability rules.
 */
class SLN_WeeklySchedule_Module
{
    public static function init()
    {
        SLN_WeeklySchedule_Cache::init();
        SLN_WeeklySchedule_CategoryColor::init();
        SLN_WeeklySchedule_BookRedirect::init();
        SLN_WeeklySchedule_Shortcode::init();
        SLN_WeeklySchedule_Rest::init();
        SLN_WeeklySchedule_Assets::init();
        add_action('elementor/widgets/register', array(__CLASS__, 'registerElementorWidget'));
    }

    public static function registerElementorWidget($widgetsManager)
    {
        $widgetsManager->register(new SLN_WeeklySchedule_Elementor_Widget());
    }
}
