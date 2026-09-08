<?php

/**
 * Store telemetry client (salonbookingsystem.com / sbs-download-tracker).
 *
 * Sends activation, deactivation, onboarding, weekly heartbeat, first booking
 * and review-prompt events. No personal data, no site URL in the payload.
 *
 * Opt-out: add_filter('sln_tracker_enabled', '__return_false').
 * Heartbeat / first-booking / review pings are skipped when SLN_VERSION_DEV is defined.
 *
 * @since 10.31.1
 */
class SLN_Helper_Tracker
{
    const ENDPOINT_BASE = 'https://www.salonbookingsystem.com/wp-json/sbs-tracker/v1';
    const CRON_HOOK     = 'sln_tracker_heartbeat';

    const OPTION_FIRST_BOOKING  = 'sln_tracker_first_booking_sent';
    const OPTION_REVIEW_SHOWN    = 'sln_tracker_review_prompt_shown';
    const OPTION_REVIEW_POSITIVE = 'sln_tracker_review_prompt_positive';
    const OPTION_REVIEW_CLICKED  = 'sln_tracker_review_prompt_clicked';

    /**
     * Register cron and booking hooks. Called from SLN_Action_Init.
     */
    public static function init()
    {
        add_action(self::CRON_HOOK, array(__CLASS__, 'sendHeartbeat'));
        add_action('init', array(__CLASS__, 'schedule'));
        add_action('sln.booking.setStatus', array(__CLASS__, 'maybeSendFirstBooking'), 20, 3);
    }

    /**
     * @return bool
     */
    public static function isEnabled()
    {
        return (bool) apply_filters('sln_tracker_enabled', true);
    }

    /**
     * Operational pings (heartbeat, first booking, review) stay off on dev builds.
     *
     * @return bool
     */
    public static function isOperationalPingEnabled()
    {
        if (defined('SLN_VERSION_DEV')) {
            return false;
        }

        return self::isEnabled();
    }

    /**
     * Full SHA-256 of home_url. Never truncate.
     *
     * @return string
     */
    public static function getSiteHash()
    {
        return hash('sha256', home_url());
    }

    /**
     * @return string 'pro' or 'free'
     */
    public static function getEdition()
    {
        return defined('SLN_VERSION_PAY') && SLN_VERSION_PAY ? 'pro' : 'free';
    }

    /**
     * Same successful-booking count as SLN_Admin_ReviewRequest.
     *
     * @return int
     */
    public static function getSuccessfulBookingsCount()
    {
        if (!class_exists('SLN_Plugin') || !class_exists('SLN_Enum_BookingStatus')) {
            return 0;
        }

        $counts = wp_count_posts(SLN_Plugin::POST_TYPE_BOOKING);
        if (!$counts) {
            return 0;
        }

        $total = 0;
        foreach (array(
            SLN_Enum_BookingStatus::CONFIRMED,
            SLN_Enum_BookingStatus::PAID,
            SLN_Enum_BookingStatus::PAY_LATER,
        ) as $status) {
            if (isset($counts->{$status})) {
                $total += (int) $counts->{$status};
            }
        }

        return $total;
    }

    /**
     * Non-blocking POST to a tracker endpoint.
     *
     * @param string               $path Endpoint path (no leading slash required).
     * @param array<string, mixed> $body Extra body fields.
     */
    public static function ping($path, $body = array())
    {
        if (!self::isEnabled()) {
            return;
        }

        $body = array_merge(
            array(
                'version'        => self::getEdition(),
                'plugin_version' => defined('SLN_VERSION') ? SLN_VERSION : '',
                'locale'         => function_exists('get_locale') ? get_locale() : '',
                'site_hash'      => self::getSiteHash(),
            ),
            $body
        );

        $args = array(
            'blocking'  => false,
            'timeout'   => 3,
            'sslverify' => true,
            'body'      => $body,
            'headers'   => array(),
        );

        if (defined('SBS_TRACKER_API_SECRET') && SBS_TRACKER_API_SECRET) {
            $args['body']['api_key']           = SBS_TRACKER_API_SECRET;
            $args['headers']['X-SBS-API-Key'] = SBS_TRACKER_API_SECRET;
        }

        wp_remote_post(self::ENDPOINT_BASE . '/' . ltrim((string) $path, '/'), $args);
    }

    public static function sendActivation()
    {
        self::ping(
            'activation',
            array(
                'wp_version'  => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
                'php_version' => phpversion(),
            )
        );
    }

    /**
     * @param array<string, mixed> $survey Transient payload from the deactivation modal.
     */
    public static function sendDeactivation($survey = array())
    {
        self::unschedule();

        $activation_time = get_option('sln_activation_time', current_time('timestamp'));
        $days_active     = (int) floor((current_time('timestamp') - (int) $activation_time) / DAY_IN_SECONDS);

        $payload = array(
            'days_active'              => $days_active,
            'deactivation_reason'      => 'skipped',
            'deactivation_feedback'    => '',
            'deactivation_rating'      => 0,
            'setup_progress'           => 0,
            'completed_first_booking'  => false,
        );

        if (is_array($survey) && $survey) {
            $payload['deactivation_reason']     = isset($survey['reason']) ? (string) $survey['reason'] : 'skipped';
            $payload['deactivation_feedback']   = isset($survey['feedback']) ? (string) $survey['feedback'] : '';
            $payload['deactivation_rating']     = isset($survey['rating']) ? (int) $survey['rating'] : 0;
            $payload['setup_progress']          = isset($survey['setup_progress']) ? (int) $survey['setup_progress'] : 0;
            $payload['completed_first_booking'] = !empty($survey['completed_first_booking']);
            if (isset($survey['days_active'])) {
                $payload['days_active'] = (int) $survey['days_active'];
            }
        }

        self::ping('deactivation', $payload);
    }

    /**
     * @param string $business_type Sanitized usage_goal from onboarding step 1.
     */
    public static function sendOnboarding($business_type)
    {
        $business_type = sanitize_key($business_type);
        if (!$business_type) {
            return;
        }

        self::ping('business-type', array('business_type' => $business_type));
    }

    public static function sendHeartbeat()
    {
        if (!self::isOperationalPingEnabled()) {
            return;
        }

        self::ping(
            'heartbeat',
            array(
                'bookings_count' => self::getSuccessfulBookingsCount(),
                'wp_version'     => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
                'php_version'    => phpversion(),
            )
        );
    }

    public static function sendFirstBooking()
    {
        if (!self::isOperationalPingEnabled()) {
            return;
        }
        if (get_option(self::OPTION_FIRST_BOOKING)) {
            return;
        }

        update_option(self::OPTION_FIRST_BOOKING, 1, false);
        self::ping('first-booking');
    }

    /**
     * @param SLN_Wrapper_Booking $booking
     * @param string              $oldStatus
     * @param string              $newStatus
     */
    public static function maybeSendFirstBooking($booking, $oldStatus, $newStatus)
    {
        if (!class_exists('SLN_Enum_BookingStatus')) {
            return;
        }

        $success = array(
            SLN_Enum_BookingStatus::CONFIRMED,
            SLN_Enum_BookingStatus::PAID,
            SLN_Enum_BookingStatus::PAY_LATER,
        );

        if (!in_array($newStatus, $success, true)) {
            return;
        }
        if (in_array($oldStatus, $success, true)) {
            return;
        }

        self::sendFirstBooking();
    }

    public static function sendReviewPromptShown()
    {
        if (!self::isOperationalPingEnabled()) {
            return;
        }
        if (get_option(self::OPTION_REVIEW_SHOWN)) {
            return;
        }

        update_option(self::OPTION_REVIEW_SHOWN, 1, false);
        self::ping('review-prompt', array('action_type' => 'shown'));
    }

    /**
     * Step 1 “Yes, it's helping”. Once per site.
     */
    public static function sendReviewPromptPositive()
    {
        if (!self::isOperationalPingEnabled()) {
            return;
        }
        if (get_option(self::OPTION_REVIEW_POSITIVE)) {
            return;
        }

        update_option(self::OPTION_REVIEW_POSITIVE, 1, false);
        self::ping('review-prompt', array('action_type' => 'positive'));
    }

    public static function sendReviewPromptClicked()
    {
        if (!self::isOperationalPingEnabled()) {
            return;
        }
        if (get_option(self::OPTION_REVIEW_CLICKED)) {
            return;
        }

        update_option(self::OPTION_REVIEW_CLICKED, 1, false);
        self::ping('review-prompt', array('action_type' => 'clicked'));
    }

    public static function schedule()
    {
        if (!self::isOperationalPingEnabled()) {
            return;
        }
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK);
    }

    public static function unschedule()
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
