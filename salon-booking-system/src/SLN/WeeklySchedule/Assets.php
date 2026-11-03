<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Assets of the weekly schedule, enqueued only when a widget is rendered.
 */
class SLN_WeeklySchedule_Assets
{
    const HANDLE = 'sln-weekly-schedule';

    public static function init()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register'), 5);
        // Elementor's editor preview renders widgets outside wp_enqueue_scripts.
        add_action('elementor/frontend/after_register_scripts', array(__CLASS__, 'register'));
    }

    public static function register()
    {
        if (wp_script_is(self::HANDLE, 'registered')) {
            return;
        }
        $version = SLN_Action_InitScripts::ASSETS_VERSION;
        wp_register_style(self::HANDLE, SLN_PLUGIN_URL . '/css/weekly-schedule.css', array(), $version);
        wp_style_add_data(self::HANDLE, 'rtl', 'replace');
        wp_register_script(self::HANDLE, SLN_PLUGIN_URL . '/js/weekly-schedule.js', array(), $version, true);
        wp_localize_script(self::HANDLE, 'slnWeeklySchedule', array(
            'endpoint' => SLN_WeeklySchedule_Rest::getUrl(),
            'error'    => __('The schedule could not be loaded. Please try again.', 'salon-booking-system'),
            'loading'  => __('Loading…', 'salon-booking-system'),
        ));
    }

    public static function enqueue()
    {
        self::register();
        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script(self::HANDLE);

        // Inherit the colors chosen in Settings > Style, like the booking form.
        $settings = SLN_Plugin::getInstance()->getSettings();
        if ($settings->isCustomColorsEnabled() && !wp_style_is('sln-custom', 'enqueued')) {
            SLN_Helper_CustomColorsCss::maybeRegenerate($settings);
            $url = wp_upload_dir()['baseurl'];
            if (is_ssl()) {
                $url = str_replace('http://', 'https://', $url);
            }
            wp_enqueue_style('sln-custom', $url . '/' . SLN_Helper_CustomColorsCss::GENERATED_NAME, array(self::HANDLE), SLN_Action_InitScripts::ASSETS_VERSION);
        }
    }
}
