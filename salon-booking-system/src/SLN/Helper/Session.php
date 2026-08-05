<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

/**
 * Conditional PHP session bootstrap for Salon booking state.
 *
 * Avoids starting sln_booking_session on cacheable public pages (homepage, blog, etc.)
 * while preserving session on booking, account, admin Salon, and AJAX flows.
 */
class SLN_Helper_Session
{
    const SESSION_NAME = 'sln_booking_session';

    /**
     * Register session bootstrap hooks.
     *
     * @return void
     */
    public static function bootstrap()
    {
        add_action('init', array(__CLASS__, 'maybeStartForAjax'), 2);
        add_action('parse_request', array(__CLASS__, 'maybeStartForEarlyRequest'), 0);
        add_action('wp', array(__CLASS__, 'maybeStartForFrontend'), 0);
        add_action('admin_init', array(__CLASS__, 'maybeStartForAdmin'), 0);
    }

    /**
     * Start session when required, or when explicitly forced (e.g. BookingBuilder).
     *
     * @param bool $force
     * @return bool
     */
    public static function maybeStart($force = false)
    {
        if (headers_sent()) {
            return session_status() === PHP_SESSION_ACTIVE;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        if (session_status() !== PHP_SESSION_NONE) {
            return false;
        }

        if (!$force && !self::isRequired()) {
            return false;
        }

        self::configure();
        session_start();

        if (SLN_Plugin::isDebugEnabled()) {
            SLN_Plugin::addLog(sprintf(
                '[Session] Started (%s) for %s',
                $force ? 'forced' : 'required',
                isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : 'n/a'
            ));
        }

        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * @return bool
     */
    public static function isRequired()
    {
        if (self::isExcludedRequest()) {
            return false;
        }

        if (SLN_Helper_FrontendPage::hasBookingFlowQueryParams()) {
            return true;
        }

        // admin-ajax.php responses are never full-page cached, so starting the session here has
        // zero caching cost while covering every Salon AJAX action (booking, discount, Google
        // OAuth callback, calendar sync, cache warmer, etc.) without a fragile action whitelist.
        if (wp_doing_ajax()) {
            // …but starting a PHP session takes an EXCLUSIVE lock on the session file for the whole
            // request (default files handler). The admin booking editor fires several read-only
            // AJAX calls at once (plus core heartbeat/autosave); if each holds the lock for its full
            // duration they serialize, and the customer search queues behind them (observed ~10s
            // TTFB). These specific requests never touch $_SESSION, so skip the session — and the
            // lock — for them. Fail-safe: any action not listed still starts the session as before.
            if (self::isSessionlessAjaxRequest()) {
                return false;
            }

            return true;
        }

        if (is_admin() && SLN_Func::isSalonPage()) {
            return true;
        }

        if (!is_admin() && SLN_Helper_FrontendPage::isQueriedSalonFrontendPage()) {
            return true;
        }

        /**
         * Filter whether the current request should start sln_booking_session.
         *
         * @param bool $required
         */
        return (bool) apply_filters('sln_should_start_session', false);
    }

    /**
     * Identify AJAX requests that never read or write $_SESSION and therefore must not take the
     * session file lock. Keeping these lock-free prevents them from serializing behind (or blocking)
     * other Salon AJAX requests on the same page.
     *
     * This is intentionally a small, explicit safe-list rather than a broad whitelist: only requests
     * we are certain are session-free are skipped, so any unlisted action keeps the previous
     * behaviour (session started) and the booking flow is untouched.
     *
     * @return bool
     */
    private static function isSessionlessAjaxRequest()
    {
        $action = isset($_REQUEST['action'])
            ? strtolower(sanitize_text_field(wp_unslash($_REQUEST['action'])))
            : '';

        // WordPress core polling on edit screens (heartbeat carries autosave) never needs the Salon
        // session, yet fires constantly on the booking editor.
        if ($action === 'heartbeat' || $action === 'wp-autosave') {
            return true;
        }

        // Everything below is dispatched by SLN_Plugin::ajax() via action=salon&method=...
        if ($action !== 'salon') {
            return false;
        }

        $method = isset($_REQUEST['method'])
            ? strtolower(sanitize_text_field(wp_unslash($_REQUEST['method'])))
            : '';

        // Only methods that are provably session-free are listed here. Notably the availability
        // endpoints (CheckServices/CheckResources/CheckAttendants/CheckDate) are deliberately NOT
        // listed: they build the BookingBuilder, whose constructor force-starts the session
        // (SLN_Wrapper_Booking_Builder::__construct → maybeStart(true)), so skipping here would be
        // ineffective and could mask a real session dependency. SearchUser only runs a user query
        // and never touches $_SESSION — it is the customer-search request that was queuing ~10s
        // behind the session lock on the booking editor.
        $sessionless_methods = array(
            'searchuser',
        );

        return in_array($method, $sessionless_methods, true);
    }

    /**
     * @return void
     */
    public static function maybeStartForAjax()
    {
        if (!wp_doing_ajax()) {
            return;
        }

        self::maybeStart();
    }

    /**
     * @return void
     */
    public static function maybeStartForEarlyRequest()
    {
        if (!SLN_Helper_FrontendPage::hasBookingFlowQueryParams()) {
            return;
        }

        self::maybeStart();
    }

    /**
     * @return void
     */
    public static function maybeStartForFrontend()
    {
        if (is_admin()) {
            return;
        }

        self::maybeStart();
    }

    /**
     * @return void
     */
    public static function maybeStartForAdmin()
    {
        if (!is_admin()) {
            return;
        }

        self::maybeStart();
    }

    /**
     * @return void
     */
    private static function configure()
    {
        session_name(self::SESSION_NAME);

        // Configure session cookie parameters for better browser compatibility (especially Edge).
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params(
                array(
                    'lifetime' => 0,
                    'path'     => COOKIEPATH ? COOKIEPATH : '/',
                    'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                )
            );
        } else {
            session_set_cookie_params(
                0,
                COOKIEPATH ? COOKIEPATH . '; SameSite=Lax' : '/; SameSite=Lax',
                COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
                is_ssl(),
                true
            );
        }
    }

    /**
     * @return bool
     */
    private static function isExcludedRequest()
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if ($request_uri !== '') {
            if (strstr($request_uri, '/wp-admin/site-health.php') || strstr($request_uri, '/wp-json/wp-site-health')) {
                return true;
            }
        }

        if (isset($_POST['action']) && sanitize_text_field(wp_unslash($_POST['action'])) === 'health-check-loopback-requests') {
            return true;
        }

        if (isset($_REQUEST['action']) && sanitize_text_field(wp_unslash($_REQUEST['action'])) === 'wp_async_send_server_events') {
            return true;
        }

        return false;
    }

}
