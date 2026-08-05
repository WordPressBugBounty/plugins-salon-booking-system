<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

/**
 * Detects frontend pages that load Salon shortcodes or configured booking pages.
 *
 * Shared by script enqueue and conditional session bootstrap so page-builder
 * compatibility stays consistent.
 */
class SLN_Helper_FrontendPage
{
    /** @var array<int,bool> Per-request cache of shortcode detection keyed by post ID. */
    private static $postShortcodeCache = array();

    /**
     * @return string[]
     */
    public static function getSalonShortcodeNames()
    {
        return array(
            SLN_Shortcode_Salon::NAME,
            SLN_Shortcode_SalonMyAccount::NAME,
            SLN_Shortcode_SalonCalendar::NAME,
            SLN_Shortcode_SalonAssistant::NAME,
            SLN_Shortcode_SalonServices::NAME,
            SLN_Shortcode_SalonRecentComments::NAME,
            SLN_Shortcode_Container::NAME,
        );
    }

    /**
     * @param string $content
     * @return bool
     */
    public static function contentHasSalonShortcode($content)
    {
        if (!is_string($content) || $content === '') {
            return false;
        }

        foreach (self::getSalonShortcodeNames() as $name) {
            if (has_shortcode($content, $name)) {
                return true;
            }
        }

        // Unyson compatibility.
        if (has_shortcode($content, 'text_block')) {
            $atts = shortcode_parse_atts($content);
            $text_attr = isset($atts['text']) ? $atts['text'] : '';
            if (is_string($text_attr)) {
                foreach (self::getSalonShortcodeNames() as $name) {
                    if (strpos($text_attr, $name) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param WP_Post|null $post
     * @return bool
     */
    public static function postHasSalonShortcode($post = null)
    {
        if ($post === null) {
            global $post;
        }

        if (!is_a($post, 'WP_Post')) {
            return false;
        }

        // Memoize per post: the Betheme builder render below is expensive and this method runs
        // multiple times per request (session bootstrap on 'wp' + asset enqueue).
        if (isset(self::$postShortcodeCache[$post->ID])) {
            return self::$postShortcodeCache[$post->ID];
        }

        $result = false;

        if (self::contentHasSalonShortcode($post->post_content)) {
            $result = true;
        } elseif (defined('MFN_THEME_VERSION')) {
            // Betheme compatibility.
            $mfn_builder = new \Mfn_Builder_Front($post->ID);
            ob_start();
            $mfn_builder->show();
            $content = ob_get_clean();

            foreach (self::getSalonShortcodeNames() as $name) {
                if (strpos($content, $name) !== false) {
                    $result = true;
                    break;
                }
            }
        }

        self::$postShortcodeCache[$post->ID] = $result;

        return $result;
    }

    /**
     * Whether Salon frontend assets should load on the current post.
     *
     * @param WP_Post|null $post
     * @return bool
     */
    public static function shouldLoadFrontendAssets($post = null)
    {
        if (is_admin()) {
            return false;
        }

        return self::postHasSalonShortcode($post);
    }

    /**
     * @return int[]
     */
    public static function getConfiguredSalonPageIds()
    {
        $settings = SLN_Plugin::getInstance()->getSettings();

        return array_values(
            array_filter(
                array_unique(
                    array(
                        (int) $settings->getPayPageId(),
                        (int) $settings->getThankyouPageId(),
                        (int) $settings->getBookingmyaccountPageId(),
                    )
                )
            )
        );
    }

    /**
     * @param int $pageId
     * @return bool
     */
    public static function isConfiguredSalonPageId($pageId)
    {
        return $pageId > 0 && in_array((int) $pageId, self::getConfiguredSalonPageIds(), true);
    }

    /**
     * Booking/reschedule/deep-link query parameters that require session state.
     *
     * @return bool
     */
    public static function hasBookingFlowQueryParams()
    {
        if (isset($_GET['sln_step_page']) || isset($_POST['sln_step_page'])) {
            return true;
        }

        if (!empty($_GET['sln_customer_login'])) {
            return true;
        }

        if (isset($_GET['sln_reschedule_booking'])) {
            return true;
        }

        if (isset($_REQUEST['skip_service_selection'])) {
            return true;
        }

        if (!empty($_GET['sln_forecast_notify']) && !empty($_GET['sln_fdate']) && !empty($_GET['sln_ftime'])) {
            return true;
        }

        // Note: payment flows (PayPal/Stripe) always carry sln_step_page (handled above), so a
        // bare "?mode=..." is intentionally NOT treated as booking context — it is too generic and
        // would needlessly break full-page caching for unrelated public URLs.

        return false;
    }

    /**
     * Whether the main query resolved to a Salon frontend page.
     *
     * @return bool
     */
    public static function isQueriedSalonFrontendPage()
    {
        if (is_admin()) {
            return false;
        }

        $queried_id = get_queried_object_id();
        if ($queried_id && self::isConfiguredSalonPageId($queried_id)) {
            return true;
        }

        $queried = get_queried_object();
        if ($queried instanceof WP_Post && self::postHasSalonShortcode($queried)) {
            return true;
        }

        return false;
    }
}
