<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

/**
 * Keeps booking pages out of full-page caches.
 *
 * The booking wizard renders per-visitor state into the page HTML — most importantly the
 * client_id that keys the in-progress booking (see views/shortcode/salon.php) — alongside
 * time-sensitive availability data. When a page cache stores that HTML, every visitor is
 * served the same client_id and therefore shares one booking-builder record: concurrent
 * customers overwrite each other's selection, and when one of them finishes the shared
 * record is deleted and the others are bounced back through the wizard.
 *
 * A cache plugin cannot detect any of this on its own, so the plugin has to opt these
 * pages out itself rather than relying on site owners configuring an exclusion rule.
 */
class SLN_Helper_PageCache
{
    /** @var bool Guard so the exclusion signals are only emitted once per request. */
    private static $applied = false;

    /**
     * @return void
     */
    public static function bootstrap()
    {
        add_action('template_redirect', array(__CLASS__, 'maybeExcludeCurrentPage'), 0);
    }

    /**
     * @return void
     */
    public static function maybeExcludeCurrentPage()
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!self::isBookingPage()) {
            return;
        }

        self::exclude();
    }

    /**
     * @return bool
     */
    private static function isBookingPage()
    {
        $bypass = SLN_Helper_FrontendPage::isQueriedSalonFrontendPage()
            || SLN_Helper_FrontendPage::hasBookingFlowQueryParams();

        /**
         * Filter whether the current request must bypass full-page caching.
         *
         * @param bool $bypass
         */
        return (bool) apply_filters('sln_should_bypass_page_cache', $bypass);
    }

    /**
     * Emit every bypass signal we can, because the major cache plugins each honour a
     * different one and a site can run more than one layer at a time.
     *
     * @return void
     */
    public static function exclude()
    {
        if (self::$applied) {
            return;
        }

        self::$applied = true;

        // Honoured by W3 Total Cache, WP Super Cache, WP Rocket, Comet Cache and others.
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        // WP Fastest Cache ignores DONOTCACHEPAGE; this is its documented exclusion hook.
        if (function_exists('wpfc_exclude_current_page')) {
            wpfc_exclude_current_page();
        }

        // LiteSpeed Cache.
        do_action('litespeed_control_set_nocache', 'Salon booking page carries per-visitor state');

        if (!headers_sent()) {
            nocache_headers();
            // SiteGround Optimizer dynamic cache.
            header('X-Cache-Enabled: False');
        }

        if (SLN_Plugin::isDebugEnabled()) {
            SLN_Plugin::addLog(sprintf(
                '[PageCache] Bypassing full-page cache for %s',
                isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : 'n/a'
            ));
        }
    }
}
