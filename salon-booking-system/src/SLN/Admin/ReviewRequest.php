<?php

/**
 * WordPress.org review request notice (free version only).
 *
 * Shows a dismissible admin notice asking for a review on the plugin's
 * WordPress.org page after the salon has real usage (enough confirmed
 * bookings and a minimum install age). Follows the same notice/dismiss
 * pattern as SLN_Admin_MigrationTools_Ip1SmsMigration.
 *
 * Rationale (Aug 2026): WP.org review count/velocity drives listing
 * ranking, which is the plugin's main acquisition channel.
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

    /** @var SLN_Plugin */
    private $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        // Hooks are registered externally in SLN_Action_Init: admin_notices from
        // initAdmin(), the wp_ajax dismiss handler from initAjax() (initAdmin()
        // does not run during admin-ajax.php requests).
        $this->plugin = $plugin;
    }

    public function showNotice()
    {
        if (!$this->shouldShow()) {
            return;
        }

        SLN_Helper_Tracker::sendReviewPromptShown();

        $review_url = self::REVIEW_URL;
        $nonce      = wp_create_nonce('sln_review_request_dismiss');
        ?>
        <div class="notice notice-info sln-review-request-notice" style="position: relative; padding-right: 38px;">
            <button type="button" class="notice-dismiss" onclick="slnReviewRequestDismiss('later')">
                <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice', 'salon-booking-system'); ?></span>
            </button>

            <h3 style="margin-top: 0.5em;">
                <?php esc_html_e('Is Salon Booking System helping your business?', 'salon-booking-system'); ?>
            </h3>
            <p>
                <?php
                printf(
                    /* translators: %s: number of bookings managed with the plugin. */
                    esc_html__('You have managed over %s bookings with Salon Booking System. If the plugin is working well for you, a quick review on WordPress.org helps other salon owners find it — and keeps the free version alive.', 'salon-booking-system'),
                    '<strong>' . esc_html(number_format_i18n(self::MIN_BOOKINGS)) . '</strong>'
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener" class="button button-primary" onclick="slnReviewRequestDismiss('reviewed')">
                    <?php esc_html_e('Leave a review', 'salon-booking-system'); ?> ★★★★★
                </a>
                <button type="button" class="button" onclick="slnReviewRequestDismiss('later')">
                    <?php esc_html_e('Maybe later', 'salon-booking-system'); ?>
                </button>
                <button type="button" class="button-link" style="margin-left: 8px;" onclick="slnReviewRequestDismiss('done')">
                    <?php esc_html_e('I already did / don\'t ask again', 'salon-booking-system'); ?>
                </button>
            </p>
        </div>
        <script>
        function slnReviewRequestDismiss(mode) {
            jQuery('.sln-review-request-notice').fadeOut();
            jQuery.post(ajaxurl, {
                action: 'sln_review_request_dismiss',
                mode: mode,
                security: '<?php echo esc_attr($nonce); ?>'
            });
        }
        </script>
        <?php
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
        } elseif ('done' === $mode) {
            update_option(self::OPTION_STATE, 'done');
        } else {
            update_option(self::OPTION_STATE, time() + self::SNOOZE_DAYS * DAY_IN_SECONDS);
        }

        wp_send_json_success();
    }

    private function shouldShow()
    {
        // Free version only: the goal is reviews on the WP.org listing.
        if (defined('SLN_VERSION_PAY') || defined('SLN_VERSION_CODECANYON')) {
            return false;
        }

        if (!current_user_can('manage_salon_settings') && !current_user_can('manage_options')) {
            return false;
        }

        // Only on plugin screens and the dashboard, to stay unobtrusive.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || (false === strpos($screen->id, 'sln') && false === strpos($screen->id, 'salon') && 'dashboard' !== $screen->id)) {
            return false;
        }

        $state = get_option(self::OPTION_STATE, 0);
        if ('done' === $state) {
            return false;
        }
        if (is_numeric($state) && (int) $state > time()) {
            return false; // snoozed
        }

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
}
