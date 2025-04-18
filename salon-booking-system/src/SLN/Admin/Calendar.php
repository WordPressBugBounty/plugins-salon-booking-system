<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Admin_Calendar extends SLN_Admin_AbstractPage
{
    const PAGE = 'salon';
    const PRIORITY = 0;

    public function admin_menu()
    {

	$admin_menu_title = apply_filters('sln_admin_menu_title', __('Salon', 'salon-booking-system'));

        add_menu_page(
            $admin_menu_title,
            $admin_menu_title,
            'manage_salon',
            'salon',
            array($this, 'show'),
            apply_filters('sln_admin_menu_icon', SLN_PLUGIN_URL.'/img/admin_icon.png')
        );

        $this->classicAdminMenu(
            __('Salon Calendar', 'salon-booking-system'),
            __('Calendar', 'salon-booking-system')
        );
    }

    public function show()
    {
        echo $this->plugin->loadView(
            'admin/calendar',
            array(
                'x' => 'x',
            )
        );
    }

    public function enqueueAssets(){
        parent::enqueueAssets();
        $locale = str_replace('_', '-', $this->plugin->getSettings()->getDateLocale());

	$wpLang	    = array('el', 'fi', 'hr', 'ja', 'nb-NO', 'sl-SI');
	$calLang    = array('el-GR', 'fi-FI', 'hr-HR', 'ja-JP', 'no-NO', 'sl-SL');
	$locale	    = str_replace($wpLang, $calLang, $locale);

        $av = SLN_Action_InitScripts::ASSETS_VERSION;
        wp_register_script(
            'salon-calendar-language',
            SLN_PLUGIN_URL.'/js/calendar_language/template.js',
            array('jquery'),
            $av,
            true
        );
        if($locale != 'en-US' && $locale != 'en-GB') {
            // require_once SLN_PLUGIN_DIR.'/views/js/calendar_language/admin_calendar.php';
            wp_localize_script(
                'smalot-datepicker-lang',
                'sln_calendar_languages', SLN_TimeFunc::wpLocale2CalendarLocale(SLN_TimeFunc::getWpLocale())
            );

            wp_enqueue_script(
                'salon-calendar-language',
                SLN_PLUGIN_URL.'/js/calendar_language/template.js',
                array('jquery'),
                $av,
                true
            );
        }
        wp_enqueue_script(
            'salon-bootstrap',
            SLN_PLUGIN_URL.'/js/bootstrap.min.js',
            array('jquery'),
            $av,
            true
        );
        wp_enqueue_script(
            'underscore'
        );
        wp_enqueue_script(
            'salon-moment',
            SLN_PLUGIN_URL.'/js/moment.min.js',
            array('jquery'),
            $av,
            true
        );
        wp_enqueue_script(
            'salon-calendar-app',
            SLN_PLUGIN_URL.'/js/admin/customCalendar.js',
            array('jquery'),
            $av,
            true
        );
        wp_enqueue_script(
            'salon-calendar',
            SLN_PLUGIN_URL.'/js/calendar.js',
            array('jquery'),
            $av,
            true
        );
        wp_enqueue_style(
            'salon-calendar',
            SLN_PLUGIN_URL.'/css/calendar.css',
            array(),
            $av,
            'all'
        );
        if($this->plugin->getSettings()->isGoogleFontsDisabled()){
            wp_enqueue_style('salon-calendar--icon', SLN_PLUGIN_URL. '/css/calendar--salon-icon.css', array(), $av, 'all');
        }else{
            wp_enqueue_style('salon-calendar--icon', SLN_PLUGIN_URL. '/css/calendar--google-icon.css', array(), $av, 'all');
        }
	//Rtl support
	wp_style_add_data( 'salon-calendar', 'rtl', 'replace' );

	wp_localize_script( 'salon-calendar', 'salon_calendar', array(
            'locale' => $locale,
            'confirm_title' =>  __('Are you sure?', 'salon-booking-system'),
            'delete_title' =>  __('Yes, delete.', 'salon-booking-system'),
        ) );

        $event = 'Page views of back-end plugin pages';
        $data  = array(
            'page' => 'calendar',
        );

        SLN_Action_InitScripts::mixpanelTrack($event, $data);
    }
}
