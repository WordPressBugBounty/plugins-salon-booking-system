<?php
/**
 * AJAX handler — manually trigger the weekly report email from the Reports dashboard.
 */
class SLN_Action_Ajax_SendWeeklyReport
{
    /** @var SLN_Plugin */
    private $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function execute()
    {
        check_ajax_referer('sln_send_weekly_report', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied.', 'salon-booking-system'),
            ));
            return;
        }

        $to = implode(', ', $this->plugin->getSettings()->getAdminNotificationEmails());

        // Capture wp_mail() failures (SMTP errors, invalid config, etc.)
        $mail_error = null;
        $on_failure = function ($error) use (&$mail_error) {
            $mail_error = $error;
        };
        add_action('wp_mail_failed', $on_failure);

        try {
            $report = new SLN_Action_WeeklyReport($this->plugin);
            $report->executeEmail();
        } catch (Exception $e) {
            remove_action('wp_mail_failed', $on_failure);
            wp_send_json_error(array(
                'message' => __('Failed to send the report. Please check your email configuration.', 'salon-booking-system'),
            ));
            return;
        }

        remove_action('wp_mail_failed', $on_failure);

        if (null !== $mail_error) {
            $detail = $mail_error instanceof WP_Error
                ? $mail_error->get_error_message()
                : __('Unknown mail error.', 'salon-booking-system');
            wp_send_json_error(array(
                // translators: %s: error detail from wp_mail
                'message' => sprintf(__('Could not send the report: %s', 'salon-booking-system'), $detail),
            ));
            return;
        }

        wp_send_json_success(array(
            // translators: %s: recipient email address(es)
            'message' => sprintf(__('Weekly report sent to %s.', 'salon-booking-system'), $to),
        ));
    }
}
