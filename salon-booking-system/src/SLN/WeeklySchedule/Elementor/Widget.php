<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Elementor widget for the weekly schedule. Loaded only from the
 * "elementor/widgets/register" hook, so Elementor is always available here.
 * Rendering goes through the same SLN_WeeklySchedule_Renderer as the shortcode.
 */
class SLN_WeeklySchedule_Elementor_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'sln_weekly_schedule';
    }

    public function get_title()
    {
        return __('Weekly schedule', 'salon-booking-system');
    }

    public function get_icon()
    {
        return 'eicon-calendar';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('salon', 'booking', 'schedule', 'calendar', 'classes', 'timetable');
    }

    public function get_style_depends()
    {
        return array(SLN_WeeklySchedule_Assets::HANDLE);
    }

    public function get_script_depends()
    {
        return array(SLN_WeeklySchedule_Assets::HANDLE);
    }

    protected function register_controls()
    {
        $this->start_controls_section('content', array(
            'label' => __('Weekly schedule', 'salon-booking-system'),
        ));

        $this->add_control('days', array(
            'label'    => __('Days', 'salon-booking-system'),
            'type'     => \Elementor\Controls_Manager::SELECT2,
            'multiple' => true,
            'options'  => array(
                'mon' => __('Monday', 'salon-booking-system'),
                'tue' => __('Tuesday', 'salon-booking-system'),
                'wed' => __('Wednesday', 'salon-booking-system'),
                'thu' => __('Thursday', 'salon-booking-system'),
                'fri' => __('Friday', 'salon-booking-system'),
                'sat' => __('Saturday', 'salon-booking-system'),
                'sun' => __('Sunday', 'salon-booking-system'),
            ),
            'default'  => array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'),
        ));

        $this->add_control('categories', array(
            'label'       => __('Categories', 'salon-booking-system'),
            'type'        => \Elementor\Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $this->getCategoryOptions(),
            'description' => __('Leave empty to show every category.', 'salon-booking-system'),
        ));

        $this->add_control('services', array(
            'label'       => __('Services', 'salon-booking-system'),
            'type'        => \Elementor\Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $this->getPostOptions(SLN_Plugin::POST_TYPE_SERVICE),
            'description' => __('Leave empty to show every service with its own availability rules.', 'salon-booking-system'),
        ));

        $this->add_control('assistants', array(
            'label'    => __('Assistants', 'salon-booking-system'),
            'type'     => \Elementor\Controls_Manager::SELECT2,
            'multiple' => true,
            'options'  => $this->getPostOptions(SLN_Plugin::POST_TYPE_ATTENDANT),
        ));

        $this->add_control('show_spots', array(
            'label'        => __('Show remaining spots', 'salon-booking-system'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('show_assistant', array(
            'label'        => __('Show assistant', 'salon-booking-system'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ));

        $this->add_control('show_empty_days', array(
            'label'        => __('Show days without classes', 'salon-booking-system'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => '',
        ));

        $this->add_control('weeks_ahead', array(
            'label'   => __('Weeks ahead', 'salon-booking-system'),
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'min'     => 0,
            'max'     => SLN_WeeklySchedule_Args::MAX_WEEKS_AHEAD,
            'default' => 4,
        ));

        $this->add_control('note', array(
            'label'       => __('Note', 'salon-booking-system'),
            'type'        => \Elementor\Controls_Manager::TEXTAREA,
            'description' => __('Optional text shown below the classes.', 'salon-booking-system'),
        ));

        $this->add_control('book_step', array(
            'label'   => __('"Book" opens', 'salon-booking-system'),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array(
                'details' => __('Customer details (skip the choices already made)', 'salon-booking-system'),
                'date'    => __('Date and time step', 'salon-booking-system'),
            ),
            'default' => 'details',
        ));

        $this->add_control('booking_url', array(
            'label'       => __('Booking page URL', 'salon-booking-system'),
            'type'        => \Elementor\Controls_Manager::URL,
            'options'     => false,
            'description' => __('Leave empty to use the booking page set in Salon Booking settings.', 'salon-booking-system'),
        ));

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        $html = SLN_WeeklySchedule_Renderer::render(array(
            'days'           => $settings['days'],
            'categories'     => $settings['categories'],
            'services'       => $settings['services'],
            'assistants'     => $settings['assistants'],
            'show_spots'     => $settings['show_spots'] === 'yes' ? 'yes' : 'no',
            'show_assistant' => $settings['show_assistant'] === 'yes' ? 'yes' : 'no',
            'show_empty_days' => $settings['show_empty_days'] === 'yes' ? 'yes' : 'no',
            'weeks_ahead'    => $settings['weeks_ahead'],
            'booking_url'    => isset($settings['booking_url']['url']) ? $settings['booking_url']['url'] : '',
            'book_step'      => isset($settings['book_step']) ? $settings['book_step'] : 'details',
            'note'           => isset($settings['note']) ? $settings['note'] : '',
        ));

        echo wp_kses($html, SLN_WeeklySchedule_Renderer::getAllowedHtml());
    }

    private function getCategoryOptions()
    {
        $ret   = array();
        $terms = get_terms(array('taxonomy' => SLN_Plugin::TAXONOMY_SERVICE_CATEGORY, 'hide_empty' => false));
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $ret[$term->term_id] = $term->name;
            }
        }

        return $ret;
    }

    private function getPostOptions($postType)
    {
        $ret = array();
        foreach (get_posts(array('post_type' => $postType, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC')) as $post) {
            $ret[$post->ID] = $post->post_title;
        }

        return $ret;
    }
}
