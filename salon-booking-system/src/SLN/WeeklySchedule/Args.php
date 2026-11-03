<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Normalizes the attributes shared by the shortcode, the Elementor widget and
 * the REST endpoint, so every entry point builds exactly the same schedule.
 */
class SLN_WeeklySchedule_Args
{
    const MAX_WEEKS_AHEAD = 52;

    private static $dayNames = array('mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7);

    public static function defaults()
    {
        return array(
            'days'           => 'mon,tue,wed,thu,fri,sat,sun',
            'services'       => '',
            'categories'     => '',
            'assistants'     => '',
            'show_spots'     => 'yes',
            'show_assistant' => 'yes',
            'show_empty_days' => 'no',
            'booking_url'    => '',
            'book_step'      => 'details',
            'note'           => '',
            'weeks_ahead'    => 4,
            'week'           => '',
        );
    }

    /**
     * @param array $raw Shortcode attributes or request params.
     *
     * @return array
     */
    public static function normalize($raw)
    {
        $raw = shortcode_atts(self::defaults(), is_array($raw) ? $raw : array());

        $startOfWeek = (int) get_option('start_of_week', 1);
        $weeksAhead  = max(0, min(self::MAX_WEEKS_AHEAD, (int) $raw['weeks_ahead']));

        $args = array(
            'days'           => self::parseDays($raw['days']),
            'services'       => self::parseIds($raw['services']),
            'categories'     => self::parseCategories($raw['categories']),
            'assistants'     => self::parseIds($raw['assistants']),
            'show_spots'     => self::parseBool($raw['show_spots']),
            'show_assistant' => self::parseBool($raw['show_assistant']),
            'show_empty_days' => self::parseBool($raw['show_empty_days']),
            'booking_url'    => self::parseBookingUrl($raw['booking_url']),
            'book_step'      => 'date' === $raw['book_step'] ? SLN_WeeklySchedule_BookRedirect::STEP_DATE : SLN_WeeklySchedule_BookRedirect::STEP_DETAILS,
            'note'           => mb_substr(sanitize_text_field((string) $raw['note']), 0, 500),
            'weeks_ahead'    => $weeksAhead,
            'start_of_week'  => $startOfWeek,
        );
        $args['week'] = self::parseWeek($raw['week'], $startOfWeek, $weeksAhead);

        return apply_filters('sln_weekly_schedule_args', $args, $raw);
    }

    /**
     * The attributes needed to rebuild the same widget for another week (sent
     * back by the frontend script to the REST endpoint).
     */
    public static function toPublic(array $args)
    {
        return array(
            'days'           => implode(',', $args['days']),
            'services'       => implode(',', $args['services']),
            'categories'     => implode(',', $args['categories']),
            'assistants'     => implode(',', $args['assistants']),
            'show_spots'     => $args['show_spots'] ? 'yes' : 'no',
            'show_assistant' => $args['show_assistant'] ? 'yes' : 'no',
            'show_empty_days' => $args['show_empty_days'] ? 'yes' : 'no',
            'booking_url'    => $args['booking_url'],
            'book_step'      => $args['book_step'],
            'note'           => $args['note'],
            'weeks_ahead'    => $args['weeks_ahead'],
        );
    }

    /**
     * "mon,wed" or ISO numbers "1,3" (1 = Monday ... 7 = Sunday).
     *
     * @return int[]
     */
    public static function parseDays($value)
    {
        $ret = array();
        foreach (self::split($value) as $day) {
            $day = strtolower(substr($day, 0, 3));
            if (isset(self::$dayNames[$day])) {
                $ret[] = self::$dayNames[$day];
            } elseif (ctype_digit($day) && (int) $day >= 1 && (int) $day <= 7) {
                $ret[] = (int) $day;
            }
        }
        $ret = array_values(array_unique($ret));

        return $ret ? $ret : array_values(self::$dayNames);
    }

    /**
     * @return int[]
     */
    public static function parseIds($value)
    {
        return array_values(array_unique(array_filter(array_map('absint', self::split($value)))));
    }

    /**
     * Category ids or slugs.
     *
     * @return int[]
     */
    public static function parseCategories($value)
    {
        $ret = array();
        foreach (self::split($value) as $item) {
            if (ctype_digit($item)) {
                $ret[] = (int) $item;
                continue;
            }
            $term = get_term_by('slug', sanitize_title($item), SLN_Plugin::TAXONOMY_SERVICE_CATEGORY);
            if ($term) {
                $ret[] = (int) $term->term_id;
            }
        }

        return array_values(array_unique(array_filter($ret)));
    }

    public static function parseBool($value)
    {
        return !in_array(strtolower((string) $value), array('no', 'false', '0', 'off', ''), true);
    }

    /**
     * Only URLs of this site are accepted. The default is the current page when
     * it also holds the booking form, otherwise the booking page.
     */
    public static function parseBookingUrl($value)
    {
        $default = self::getCurrentBookingPageUrl();
        $pageId  = SLN_Plugin::getInstance()->getSettings()->getPayPageId();
        if (!$default && $pageId && 'publish' === get_post_status($pageId) && is_post_type_viewable(get_post_type($pageId))
            && SLN_Plugin::POST_TYPE_BOOKING !== get_post_type($pageId)) {
            $default = (string) get_permalink($pageId);
        }
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }
        if (strpos($value, '/') === 0 && strpos($value, '//') !== 0) {
            $value = home_url($value);
        }

        return esc_url_raw(wp_validate_redirect(esc_url_raw($value), $default));
    }

    /**
     * URL of the page being displayed when it contains the booking form ([salon]
     * or [salon_booking])
     * (in its content or in its Elementor data).
     */
    private static function getCurrentBookingPageUrl()
    {
        if (!did_action('wp') || !is_singular()) {
            return '';
        }
        $post = get_queried_object();
        if (!$post instanceof WP_Post) {
            return '';
        }
        $elementor = (string) get_post_meta($post->ID, '_elementor_data', true);
        foreach (array(SLN_Shortcode_Container::NAME, SLN_Shortcode_Salon::NAME) as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode) || preg_match('/\\[' . $shortcode . '[\\s\\]\\/]/', $elementor)) {
                return (string) get_permalink($post);
            }
        }

        return '';
    }

    /**
     * Week start (Y-m-d) clamped to the navigable range.
     */
    public static function parseWeek($value, $startOfWeek, $weeksAhead)
    {
        $current = SLN_WeeklySchedule_Builder::getWeekStart(new SLN_DateTime('now'), $startOfWeek);
        $value   = (string) $value;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || !strtotime($value)) {
            return $current;
        }
        $week = SLN_WeeklySchedule_Builder::getWeekStart(new SLN_DateTime($value), $startOfWeek);
        $last = new SLN_DateTime($current);
        $last->modify('+' . (7 * $weeksAhead) . ' days');

        if ($week < $current) {
            return $current;
        }
        if ($week > $last->format('Y-m-d')) {
            return $last->format('Y-m-d');
        }

        return $week;
    }

    private static function split($value)
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        return array_filter(array_map('trim', explode(',', sanitize_text_field((string) $value))), 'strlen');
    }
}
