<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Public, read-only endpoint used by the week navigation:
 * GET /wp-json/salon-booking-system/v1/weekly-schedule?week=Y-m-d&days=...
 *
 * Returns the widget markup for the requested week plus the computed data,
 * which only contains counts (never customer data).
 */
class SLN_WeeklySchedule_Rest
{
    const NAMESPACE_V1 = 'salon-booking-system/v1';
    const ROUTE        = '/weekly-schedule';

    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register'));
    }

    public static function getUrl()
    {
        return rest_url(self::NAMESPACE_V1 . self::ROUTE);
    }

    public static function register()
    {
        $string = array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field');
        register_rest_route(self::NAMESPACE_V1, self::ROUTE, array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'handle'),
            'permission_callback' => array(__CLASS__, 'canRead'),
            'args'                => array(
                'week'           => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array(__CLASS__, 'validateDate')),
                'days'           => $string,
                'services'       => $string,
                'categories'     => $string,
                'assistants'     => $string,
                'show_spots'     => $string,
                'show_assistant' => $string,
                'show_empty_days' => $string,
                'book_step'      => array('type' => 'string', 'enum' => array('details', 'date')),
                'note'           => $string,
                'booking_url'    => array('type' => 'string', 'sanitize_callback' => 'esc_url_raw'),
                'weeks_ahead'    => array('type' => 'integer', 'sanitize_callback' => 'absint', 'minimum' => 0, 'maximum' => SLN_WeeklySchedule_Args::MAX_WEEKS_AHEAD),
            ),
        ));
    }

    /**
     * Public route: it returns the same schedule shown to visitors, with
     * counts only and no customer data.
     */
    public static function canRead()
    {
        return true;
    }

    public static function validateDate($value)
    {
        return '' === $value || (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    public static function handle(WP_REST_Request $request)
    {
        $raw = array();
        foreach (array_keys(SLN_WeeklySchedule_Args::defaults()) as $key) {
            if (null !== $request->get_param($key)) {
                $raw[$key] = $request->get_param($key);
            }
        }
        $args = self::dropUnknownIds(SLN_WeeklySchedule_Args::normalize($raw));

        $response = rest_ensure_response(array(
            'week_start' => $args['week'],
            'html'       => SLN_WeeklySchedule_Renderer::renderMarkup($args),
            'schedule'   => self::publicSchedule(SLN_WeeklySchedule_Renderer::getSchedule($args)),
        ));
        $response->header('Cache-Control', 'no-cache, must-revalidate, max-age=0');

        return $response;
    }

    /**
     * Keep only IDs of existing services, assistants and categories: this route is
     * public, and made-up IDs would each build and cache a schedule of their own.
     */
    private static function dropUnknownIds(array $args)
    {
        $requested = $args;
        $postTypes = array('services' => SLN_Plugin::POST_TYPE_SERVICE, 'assistants' => SLN_Plugin::POST_TYPE_ATTENDANT);
        foreach ($postTypes as $key => $postType) {
            if ($args[$key]) {
                $args[$key] = array_map('intval', get_posts(array(
                    'post_type'      => $postType,
                    'post__in'       => $args[$key],
                    'posts_per_page' => count($args[$key]),
                    'fields'         => 'ids',
                    'orderby'        => 'post__in',
                )));
            }
        }
        if ($args['categories']) {
            $terms = get_terms(array(
                'taxonomy'   => SLN_Plugin::TAXONOMY_SERVICE_CATEGORY,
                'include'    => $args['categories'],
                'hide_empty' => false,
                'fields'     => 'ids',
            ));
            $args['categories'] = is_wp_error($terms) ? array() : array_map('intval', $terms);
        }
        // An empty list means "no filter": when every ID was unknown, keep a filter
        // that matches nothing, like the shortcode with the same IDs.
        foreach (array('services', 'assistants', 'categories') as $key) {
            if (!$args[$key] && $requested[$key]) {
                $args[$key] = array(0);
            }
        }

        return $args;
    }

    /**
     * Whitelist of the event fields exposed by the API.
     */
    private static function publicSchedule(array $schedule)
    {
        $fields = array('date', 'time', 'end_time', 'service_id', 'service_name', 'category_id', 'category_name', 'category_slug', 'category_color', 'assistant_id', 'assistant_name', 'duration_minutes', 'capacity', 'spots', 'status');
        foreach ($schedule['days'] as &$day) {
            foreach ($day['events'] as &$event) {
                $event = array_intersect_key($event, array_flip($fields));
            }
        }

        return $schedule;
    }
}
