<?php

/**
 * WordPress.org review request (free version only).
 *
 * One prompt, two placements, same dismiss/snooze state:
 * - Dashboard / Settings: WordPress admin notice
 * - Calendar: in-page sln-notice banner (WP notices are hidden there)
 *
 * Two-step: ask if the plugin is helping first. Only people who say yes
 * are sent to WP.org, so unhappy users are not pushed into a public review.
 * Step 1 uses the real booking count; “already reviewed” lives on step 2.
 *
 * @since 10.31.0
 */
class SLN_Admin_ReviewRequest
{
    const REVIEW_URL = 'https://wordpress.org/support/plugin/salon-booking-system/reviews/#new-post';

    /** Minimum successful bookings before asking */
    const MIN_BOOKINGS = 10;

    /** Minimum days since first check before asking */
    const MIN_INSTALL_AGE_DAYS = 14;

    /** Snooze duration when the user picks "Maybe later" or closes the notice */
    const SNOOZE_DAYS = 30;

    const OPTION_STATE = 'sln_review_request_state';
    const OPTION_FIRST_SEEN = 'sln_review_request_first_seen';

    /** User said the plugin is helping; show the WP.org step */
    const STATE_POSITIVE = 'positive';

    /** @var SLN_Plugin */
    private $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        // Hooks are registered externally in SLN_Action_Init: admin_notices from
        // initAdmin(), the wp_ajax dismiss handler from initAjax() (initAdmin()
        // does not run during admin-ajax.php requests).
        $this->plugin = $plugin;
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    public function enqueueAssets()
    {
        if (!current_user_can('manage_salon_settings') && !current_user_can('manage_options')) {
            return;
        }

        $css = SLN_PLUGIN_DIR . '/css/admin-review-request.css';
        wp_enqueue_style(
            'sln-review-request',
            SLN_PLUGIN_URL . '/css/admin-review-request.css',
            array(),
            file_exists($css) ? (string) filemtime($css) : SLN_Action_InitScripts::ASSETS_VERSION
        );
    }

    public function showNotice()
    {
        if (!$this->shouldShowNotice()) {
            return;
        }

        $this->render('notice');
    }

    /**
     * Calendar banner. Same prompt and state as the Dashboard notice.
     */
    public function showCalendarBanner()
    {
        if (!$this->shouldShowCalendarBanner()) {
            return;
        }

        $this->render('banner');
    }

    public function handleDismiss()
    {
        check_ajax_referer('sln_review_request_dismiss', 'security');

        if (!current_user_can('manage_salon_settings') && !current_user_can('manage_options')) {
            wp_die();
        }

        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'later';

        if ('reviewed' === $mode) {
            SLN_Helper_Tracker::sendReviewPromptClicked();
            update_option(self::OPTION_STATE, 'done');
        } elseif (self::STATE_POSITIVE === $mode) {
            SLN_Helper_Tracker::sendReviewPromptPositive();
            update_option(self::OPTION_STATE, self::STATE_POSITIVE);
        } elseif ('done' === $mode) {
            update_option(self::OPTION_STATE, 'done');
        } else {
            update_option(self::OPTION_STATE, time() + self::SNOOZE_DAYS * DAY_IN_SECONDS);
        }

        wp_send_json_success();
    }

    /**
     * Dev-only preview: add ?sln_preview_review_request=1 on Calendar, Settings, or Dashboard.
     * Production builds never honor this (SLN_VERSION_DEV is stripped).
     */
    private function isPreview()
    {
        return defined('SLN_VERSION_DEV')
            && !empty($_GET['sln_preview_review_request']);
    }

    /**
     * Shared eligibility (edition, capability, state, install age, bookings).
     * No screen check — callers decide placement.
     */
    private function isEligible()
    {
        $preview = $this->isPreview();

        // Free version only: the goal is reviews on the WP.org listing.
        if (!$preview && (defined('SLN_VERSION_PAY') || defined('SLN_VERSION_CODECANYON'))) {
            return false;
        }

        if (!current_user_can('manage_salon_settings') && !current_user_can('manage_options')) {
            return false;
        }

        if ($preview) {
            return true;
        }

        $state = get_option(self::OPTION_STATE, 0);
        if ('done' === $state) {
            return false;
        }
        if (is_numeric($state) && (int) $state > time()) {
            return false; // snoozed
        }
        // 'positive' stays eligible so step 2 is shown after "Yes".

        // Require a minimum install age so brand-new users are not prompted.
        $first_seen = (int) get_option(self::OPTION_FIRST_SEEN, 0);
        if (!$first_seen) {
            update_option(self::OPTION_FIRST_SEEN, time());

            return false;
        }
        if (time() - $first_seen < self::MIN_INSTALL_AGE_DAYS * DAY_IN_SECONDS) {
            return false;
        }

        return SLN_Helper_Tracker::getSuccessfulBookingsCount() >= self::MIN_BOOKINGS;
    }

    private function isCalendarScreen()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        return $screen && 'toplevel_page_salon' === $screen->id;
    }

    private function isNoticeScreen()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) {
            return false;
        }

        return false !== strpos($screen->id, 'sln')
            || false !== strpos($screen->id, 'salon')
            || 'dashboard' === $screen->id;
    }

    private function shouldShowNotice()
    {
        // Calendar hides WP .notice; the banner is the placement there.
        if ($this->isCalendarScreen()) {
            return false;
        }

        return $this->isEligible() && $this->isNoticeScreen();
    }

    private function shouldShowCalendarBanner()
    {
        return $this->isEligible();
    }

    private function getStep()
    {
        if ($this->isPreview()) {
            return 1;
        }

        return self::STATE_POSITIVE === get_option(self::OPTION_STATE, 0) ? 2 : 1;
    }

    /**
     * @param string $variant 'notice' or 'banner'
     */
    private function render($variant)
    {
        if (!$this->isPreview()) {
            SLN_Helper_Tracker::sendReviewPromptShown();
        }

        $bookings_count = SLN_Helper_Tracker::getSuccessfulBookingsCount();
        if ($this->isPreview() && $bookings_count < self::MIN_BOOKINGS) {
            $bookings_count = self::MIN_BOOKINGS;
        }

        echo $this->plugin->loadView('admin/_review_request', array(
            'variant'         => $variant,
            'step'            => $this->getStep(),
            'review_url'      => self::REVIEW_URL,
            'nonce'           => wp_create_nonce('sln_review_request_dismiss'),
            'bookings_count'  => $bookings_count,
        ));
    }
}
