<?php
/**
 * Admin-only, read-only diagnostic for the "wrong location in SMS reminders" issue.
 *
 * SENDS NO SMS. An administrator visits, while logged in:
 *
 *     /wp-admin/?sln_sms_loc_diag=1
 *
 * and gets a table showing, for every upcoming SMS-reminder booking, how the
 * location resolves before/after the booking's Multi-Shops shop context is applied,
 * plus the fully rendered reminder text. This lets us diagnose the problem while
 * text messages remain switched off.
 *
 * Add ?only_pending=1 to limit to bookings whose reminder hasn't been sent yet.
 */
class SLN_Action_SmsLocationDiagnostic
{
    /** @var SLN_Plugin */
    private $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        add_action('admin_init', array($this, 'maybeRun'));
    }

    public function maybeRun()
    {
        if (!isset($_GET['sln_sms_loc_diag'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'salon-booking-system'));
        }

        $includeAlreadySent = !isset($_GET['only_pending']);

        try {
            $reminder = new SLN_Action_Reminder($this->plugin);
            $rows     = $reminder->diagnoseSmsLocations($includeAlreadySent);
        } catch (Exception $e) {
            wp_die(esc_html('Diagnostic error: ' . $e->getMessage()));
        }

        $this->render($rows, $includeAlreadySent);
        exit;
    }

    private function render($rows, $includeAlreadySent)
    {
        $multishop = class_exists('\SalonMultishop\Addon') ? 'ACTIVE' : 'NOT ACTIVE';
        $interval  = $this->plugin->getSettings()->get('sms_remind_interval');
        $smsRemind = $this->plugin->getSettings()->get('sms_remind') ? 'ON' : 'OFF';

        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>SMS Location Diagnostic</title>';
        echo '<style>
            body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#222;}
            h1{font-size:20px;} .meta{background:#f5f5f5;padding:12px 16px;border-radius:6px;margin-bottom:16px;}
            table{border-collapse:collapse;width:100%;font-size:12px;}
            th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;vertical-align:top;}
            th{background:#2171B1;color:#fff;position:sticky;top:0;}
            tr:nth-child(even){background:#fafafa;}
            .bad{background:#ffe5e5;} .good{background:#e6f7e6;}
            .rendered{max-width:420px;white-space:pre-wrap;font-family:monospace;}
            code{background:#eee;padding:1px 4px;border-radius:3px;}
        </style></head><body>';

        echo '<h1>Salon Booking &mdash; SMS Location Diagnostic (no messages sent)</h1>';
        echo '<div class="meta">';
        echo 'Multi-Shops add-on: <strong>' . esc_html($multishop) . '</strong> &nbsp;|&nbsp; ';
        echo 'SMS reminders setting: <strong>' . esc_html($smsRemind) . '</strong> &nbsp;|&nbsp; ';
        echo 'Reminder window (sms_remind_interval): <strong>' . esc_html($interval ? $interval : '(not set)') . '</strong> &nbsp;|&nbsp; ';
        echo 'Scope: <strong>' . esc_html($includeAlreadySent ? 'all upcoming bookings in window' : 'only not-yet-reminded') . '</strong>';
        echo '<br><br>How to read this: if <code>meta_shop</code> is empty the booking never stored its shop. '
            . 'If <code>salon_name_after</code> is still the wrong location, setting the shop context is NOT changing the salon name '
            . '(deeper add-on issue). If <code>salon_name_after</code> is correct but <code>rendered_sms</code> shows the wrong location, '
            . 'the problem is in the template/another source.';
        echo '</div>';

        if (empty($rows)) {
            echo '<p><strong>No bookings found in the current reminder window.</strong> '
                . 'Try widening it or add <code>?sln_sms_loc_diag=1</code> during a period that has upcoming appointments.</p>';
            echo '</body></html>';
            return;
        }

        echo '<table><thead><tr>'
            . '<th>Booking</th><th>Starts</th><th>Status</th>'
            . '<th>raw_shop_meta</th><th>meta_shop</th><th>context applied</th>'
            . '<th>gen_name before</th><th>gen_name after</th>'
            . '<th>salon_name before</th><th>salon_name after</th>'
            . '<th>rendered_sms</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $shopEmpty     = ($r['meta_shop'] === '' || $r['meta_shop'] === null);
            $contextClass  = $r['shop_context_applied'] === 'yes' ? 'good' : 'bad';
            $shopMetaClass = $shopEmpty ? 'bad' : '';

            echo '<tr>';
            echo '<td>#' . esc_html($r['booking_id']) . '</td>';
            echo '<td>' . esc_html($r['starts_at']) . '</td>';
            echo '<td>' . esc_html($r['status']) . '</td>';
            echo '<td class="' . esc_attr($shopMetaClass) . '">' . esc_html($r['raw_shop_meta']) . '</td>';
            echo '<td class="' . esc_attr($shopMetaClass) . '">' . esc_html($r['meta_shop']) . '</td>';
            echo '<td class="' . esc_attr($contextClass) . '">' . esc_html($r['shop_context_applied']) . '</td>';
            echo '<td>' . esc_html($r['gen_name_before']) . '</td>';
            echo '<td>' . esc_html($r['gen_name_after']) . '</td>';
            echo '<td>' . esc_html($r['salon_name_before']) . '</td>';
            echo '<td>' . esc_html($r['salon_name_after']) . '</td>';
            echo '<td class="rendered">' . esc_html($r['rendered_sms']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-top:16px;color:#666;">A copy of each row was also written to the plugin log (log.txt) tagged <code>[SMS_LOC_DIAG]</code> when debug logging is enabled.</p>';
        echo '</body></html>';
    }
}
