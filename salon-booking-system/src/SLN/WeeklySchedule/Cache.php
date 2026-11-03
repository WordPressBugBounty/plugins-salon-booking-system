<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Transient cache of the computed weeks, one entry per week + filters + locale.
 *
 * Entries are keyed on a version number that is bumped whenever bookings,
 * services, assistants, categories or the salon settings change, so a single
 * cheap option update invalidates every cached week. The short TTL covers the
 * time-based states (lessons becoming past or leaving the booking window).
 */
class SLN_WeeklySchedule_Cache
{
    const VERSION_OPTION = 'sln_weekly_schedule_cache_version';
    // Not "sln_": that namespace has been wiped wholesale on booking changes before.
    const PREFIX         = 'slb_ws_';
    const TTL            = 600;

    /**
     * Args that only change the markup, not the computed schedule: left out of the
     * key so they can't multiply the cache entries.
     */
    const RENDER_ONLY_ARGS = array('note', 'booking_url', 'show_spots', 'show_assistant', 'show_empty_days', 'book_step');

    public static function init()
    {
        add_action('save_post', array(__CLASS__, 'onPostChange'), 10, 1);
        add_action('deleted_post', array(__CLASS__, 'onPostChange'), 10, 1);
        add_action('trashed_post', array(__CLASS__, 'onPostChange'), 10, 1);
        add_action('untrashed_post', array(__CLASS__, 'onPostChange'), 10, 1);
        add_action('transition_post_status', array(__CLASS__, 'onStatusChange'), 10, 3);
        add_action('updated_post_meta', array(__CLASS__, 'onMetaChange'), 10, 2);
        add_action('added_post_meta', array(__CLASS__, 'onMetaChange'), 10, 2);
        add_action('deleted_post_meta', array(__CLASS__, 'onMetaChange'), 10, 2);
        foreach (array('created_', 'edited_', 'delete_') as $prefix) {
            add_action($prefix . SLN_Plugin::TAXONOMY_SERVICE_CATEGORY, array(__CLASS__, 'flush'));
        }
        add_action('update_option_' . SLN_Settings::KEY, array(__CLASS__, 'flush'));
        add_action('update_option_start_of_week', array(__CLASS__, 'flush'));
        add_action('update_option_timezone_string', array(__CLASS__, 'flush'));
        add_action('update_option_gmt_offset', array(__CLASS__, 'flush'));
    }

    public static function get(array $args)
    {
        $data = get_transient(self::key($args));

        return is_array($data) ? $data : null;
    }

    public static function set(array $args, array $data)
    {
        set_transient(self::key($args), $data, apply_filters('sln_weekly_schedule_cache_ttl', self::TTL));
    }

    /** @var bool */
    private static $pending = false;

    /**
     * Invalidate every cached week. Saving a booking touches many posts and meta
     * keys, so the version is bumped once, at the end of the request: weeks
     * cached later in the same request are invalidated too.
     */
    public static function flush()
    {
        if (!self::$pending) {
            self::$pending = true;
            add_action('shutdown', array(__CLASS__, 'flushNow'));
        }
    }

    public static function isPending()
    {
        return self::$pending;
    }

    public static function flushNow()
    {
        self::$pending = false;
        update_option(self::VERSION_OPTION, self::getVersion() + 1, false);
    }

    public static function onPostChange($postId)
    {
        $post = get_post($postId);
        if ($post && self::isTrackedPostType($post->post_type) && !self::isDraftBooking($post->post_type, $post->post_status)) {
            self::flush();
        }
    }

    public static function onStatusChange($new, $old, $post)
    {
        if ($new !== $old && $post && self::isTrackedPostType($post->post_type)
            && !(self::isDraftBooking($post->post_type, $new) && self::isDraftBooking($post->post_type, $old))) {
            self::flush();
        }
    }

    /**
     * Visitors create a draft booking on reaching the summary step. Drafts don't
     * take a spot, so their changes must not throw away every cached week.
     */
    private static function isDraftBooking($postType, $status)
    {
        return SLN_Plugin::POST_TYPE_BOOKING === $postType
            && in_array($status, array('new', SLN_Enum_BookingStatus::DRAFT), true);
    }

    public static function onMetaChange($metaId, $postId)
    {
        self::onPostChange($postId);
    }

    private static function isTrackedPostType($postType)
    {
        return in_array($postType, array(
            SLN_Plugin::POST_TYPE_BOOKING,
            SLN_Plugin::POST_TYPE_SERVICE,
            SLN_Plugin::POST_TYPE_ATTENDANT,
            SLN_Plugin::POST_TYPE_RESOURCE,
        ), true);
    }

    private static function getVersion()
    {
        return (int) get_option(self::VERSION_OPTION, 0);
    }

    private static function key(array $args)
    {
        $args = array_diff_key($args, array_flip(self::RENDER_ONLY_ARGS));
        foreach ($args as &$value) {
            if (is_array($value)) {
                sort($value);
            }
        }
        unset($value);
        $args['locale'] = get_locale();

        return self::PREFIX . self::getVersion() . '_' . md5(wp_json_encode($args));
    }
}
