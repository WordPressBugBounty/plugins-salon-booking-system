<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Weekly report email template — redesigned.
 *
 * @var SLN_Plugin $plugin
 * @var array      $data     Mail headers (to, subject)
 * @var array      $stats    Weekly stats from SLN_Action_WeeklyReport::getData()
 * @var array      $lifetime All-time totals: total_bookings, revenue, loyal_customers
 * @var bool       $is_free  True when PRO bundle is not active
 */

// ── Period label — last complete Mon–Sun week ─────────────────────────────────
// currentDateTime() returns DateTimeImmutable; modify() returns a NEW object.
// Using clone + discarding modify()'s return value was a bug — $_period_start stayed as "now".
$_period_end   = SLN_TimeFunc::currentDateTime()->modify('last Sunday');
$_period_start = $_period_end->modify('-6 days');
// Show full month on both sides when they differ (e.g. "Apr 27 – May 3, 2026")
if ($_period_start->format('m') === $_period_end->format('m')) {
    $_period_label = $_period_start->format('M j') . '&#8211;' . $_period_end->format('j, Y');
} else {
    $_period_label = $_period_start->format('M j') . ' &#8211; ' . $_period_end->format('M j, Y');
}

// ── Narrative sentence ────────────────────────────────────────────────────────
$_narrative = sprintf(
    /* translators: 1: number of appointments this week, 2: formatted revenue amount */
    __('"This week, Salon Booking System automatically managed %1$d appointments and %2$s in revenue &mdash; so you could focus on your clients."', 'salon-booking-system'),
    $stats['total']['count'],
    $plugin->format()->money($stats['total']['amount'], false, false, true, false, true)
);

// ── Pre-sort performance lists to indexed arrays ──────────────────────────────
$_services_list   = array_values($stats['services']);
$_attendants_list = array_values($stats['attendants']);
$_customers_list  = array_values($stats['customers']);

// Top weekdays (already sorted by amount desc in getData) — build flat array
$_weekdays_list = array();
foreach ($stats['weekdays'] as $_w => $_wd) {
    $_weekdays_list[] = array_merge($_wd, array('day_w' => $_w));
}

// ── Max amounts for share-bar percentages ─────────────────────────────────────
$_max_svc_amount  = !empty($_services_list)   ? max(1.0, (float)$_services_list[0]['amount'])   : 1.0;
$_max_att_amount  = !empty($_attendants_list) ? max(1.0, (float)$_attendants_list[0]['amount']) : 1.0;
$_max_cust_amount = !empty($_customers_list)  ? max(1.0, (float)$_customers_list[0]['amount'])  : 1.0;
$_max_wd_count    = !empty($_weekdays_list)   ? max(1,   (int)$_weekdays_list[0]['count'])      : 1;

// ── Rank-badge style helper (closure avoids global function naming collisions) ─
$_badge = static function ($rank) {
    if (1 === $rank) {
        return array('#3D5F9B', '#FFFFFF', '800');
    }
    if ($rank <= 3) {
        return array('#C8D5EA', '#3D5F9B', '700');
    }
    return array('#E4ECF3', '#778189', '700');
};
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title><?php esc_html_e('Salon Booking Weekly Report', 'salon-booking-system') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet" type="text/css" />
  <!--[if mso]>
  <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
  <![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#E4ECF3; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">

<!-- Preheader (hidden summary text for email clients) -->
<div style="display:none; font-size:1px; color:#E4ECF3; line-height:1px; max-height:0px; max-width:0px; opacity:0; overflow:hidden;">
  <?php echo esc_html($stats['total']['count']) ?> <?php esc_html_e('appointments', 'salon-booking-system') ?> &middot; <?php echo $plugin->format()->money($stats['total']['amount'], false, false, true, false, true) ?> <?php esc_html_e('revenue', 'salon-booking-system') ?> &middot; <?php echo esc_html($stats['new_customers']) ?> <?php esc_html_e('new customers', 'salon-booking-system') ?>
</div>

<!-- WRAPPER -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#E4ECF3;">
  <tr>
    <td align="center" style="padding:24px 10px 36px 10px;">

      <!--[if mso]><table border="0" cellpadding="0" cellspacing="0" width="600"><tr><td><![endif]-->
      <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">


        <!-- ============================================================
             HEADER
        ============================================================ -->
        <tr>
          <td style="background-color:#3D5F9B; padding:20px 28px; border-radius:8px 8px 0 0;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td valign="middle">
                  <span style="font-family:'Montserrat',Arial,sans-serif; font-size:20px; font-weight:800; color:#FFFFFF; letter-spacing:-0.3px;"><?php echo esc_html($plugin->getSettings()->getSalonName()) ?></span>
                </td>
                <td align="right" valign="middle">
                  <span style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; color:#FFFFFF; font-weight:500; letter-spacing:0.5px;"><?php echo $_period_label ?></span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Accent bar -->
        <tr>
          <td height="4" style="background-color:#3D5F9B; font-size:1px; line-height:1px;">&nbsp;</td>
        </tr>


        <!-- ============================================================
             HERO — big numbers + stat pills
        ============================================================ -->
        <tr>
          <td style="background-color:#3D5F9B; padding:36px 28px 32px 28px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">

              <tr>
                <td style="padding-bottom:14px;">
                  <span style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:700; color:#C8D5EA; letter-spacing:2px; text-transform:uppercase;"><?php esc_html_e('Weekly Performance Report', 'salon-booking-system') ?></span>
                </td>
              </tr>

              <tr>
                <td>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr valign="top">

                      <!-- Left: big total numbers -->
                      <td width="55%" style="padding-right:20px;">
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:64px; font-weight:800; color:#FFFFFF; margin:0; line-height:1;"><?php echo esc_html($stats['total']['count']) ?></p>
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; font-weight:500; color:#C8D5EA; margin:4px 0 24px 0; letter-spacing:0.3px;"><?php esc_html_e('appointments managed', 'salon-booking-system') ?></p>
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:34px; font-weight:700; color:#FFFFFF; margin:0; line-height:1;"><?php echo $plugin->format()->money($stats['total']['amount'], false, false, true, false, true) ?></p>
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:500; color:#C8D5EA; margin:4px 0 0 0; letter-spacing:0.3px;"><?php esc_html_e('total revenue', 'salon-booking-system') ?></p>
                      </td>

                      <!-- Right: stat pills -->
                      <td width="45%" valign="top">

                        <!-- Paid Online -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;">
                          <tr>
                            <td style="background-color:#4E6FA8; border-radius:6px; padding:11px 14px;">
                              <p style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:400; color:#C8D5EA; text-transform:uppercase; letter-spacing:1.5px; margin:0 0 3px 0;"><?php esc_html_e('Confirmed & Paid', 'salon-booking-system') ?></p>
                              <p style="font-family:'Montserrat',Arial,sans-serif; font-size:17px; font-weight:700; color:#FFFFFF; margin:0; line-height:1.2;"><?php echo esc_html($stats['paid']['count']) ?> &nbsp;/&nbsp; <?php echo $plugin->format()->money($stats['paid']['amount'], false, false, true, false, true) ?></p>
                            </td>
                          </tr>
                        </table>

                        <!-- Pay Later -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;">
                          <tr>
                            <td style="background-color:#4E6FA8; border-radius:6px; padding:11px 14px;">
                              <p style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:400; color:#C8D5EA; text-transform:uppercase; letter-spacing:1.5px; margin:0 0 3px 0;"><?php esc_html_e('Pay Later', 'salon-booking-system') ?></p>
                              <p style="font-family:'Montserrat',Arial,sans-serif; font-size:17px; font-weight:700; color:#FFFFFF; margin:0; line-height:1.2;"><?php echo esc_html($stats['pay_later']['count']) ?> &nbsp;/&nbsp; <?php echo $plugin->format()->money($stats['pay_later']['amount'], false, false, true, false, true) ?></p>
                            </td>
                          </tr>
                        </table>

                        <!-- Cancelled -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                          <tr>
                            <td style="background-color:#4E6FA8; border-radius:6px; padding:11px 14px;">
                              <p style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:400; color:#FFFFFF; text-transform:uppercase; letter-spacing:1.5px; margin:0 0 3px 0;"><?php esc_html_e('Cancelled', 'salon-booking-system') ?></p>
                              <p style="font-family:'Montserrat',Arial,sans-serif; font-size:17px; font-weight:700; color:#FFFFFF; margin:0; line-height:1.2;"><?php echo esc_html($stats['canceled']['count']) ?> &nbsp;/&nbsp; <?php echo $plugin->format()->money($stats['canceled']['amount'], false, false, true, false, true) ?></p>
                            </td>
                          </tr>
                        </table>

                      </td>
                    </tr>
                  </table>
                </td>
              </tr>

            </table>
          </td>
        </tr>


        <!-- ============================================================
             NARRATIVE + NEW CUSTOMERS
        ============================================================ -->
        <tr>
          <td style="background-color:#DEE6ED; padding:24px 28px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td style="border-left:4px solid #3D5F9B; padding-left:16px;">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:14px; font-weight:400; color:#3D5F9B; line-height:1.7; margin:0 0 12px 0; font-style:italic;"><?php echo $_narrative ?></p>
                  <?php if ($stats['new_customers'] > 0) : ?>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:600; color:#3D5F9B; margin:0;">
                    &#9679;&nbsp; <?php echo sprintf(
                        /* translators: %d: number of new customers */
                        _n('%d new customer registered this week', '%d new customers registered this week', $stats['new_customers'], 'salon-booking-system'),
                        $stats['new_customers']
                    ) ?>
                  </p>
                  <?php endif ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Divider -->
        <tr><td height="1" style="background-color:#C4CFDA; font-size:1px; line-height:1px;">&nbsp;</td></tr>


        <!-- ============================================================
             DAILY BAR CHART
        ============================================================ -->
        <tr>
          <td style="background-color:#DEE6ED; padding:28px 28px 24px 28px;">

            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;">
              <tr valign="middle">
                <td>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; font-weight:700; color:#3D5F9B; letter-spacing:2px; text-transform:uppercase; margin:0;"><?php esc_html_e('Bookings This Week', 'salon-booking-system') ?></p>
                </td>
                <td align="right">
                  <span style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; font-weight:600; color:#778189;"><?php echo esc_html($stats['total']['count']) ?> <?php esc_html_e('total', 'salon-booking-system') ?></span>
                </td>
              </tr>
            </table>

            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr valign="bottom">
                <?php foreach ($stats['daily'] as $_day) :
                    $_bar_h    = $_day['count'] > 0 ? max(4, (int)round($_day['pct'] * 80 / 100)) : 0;
                    $_spacer_h = 80 - $_bar_h;
                    $_is_empty = 0 === $_day['count'];
                    $_bar_bg   = $_is_empty ? '#C4CFDA' : '#3D5F9B';
                    $_lbl_clr  = $_is_empty ? '#C4CFDA' : ($_day['pct'] >= 90 ? '#3D5F9B' : '#778189');
                    $_cnt_clr  = $_is_empty ? '#C4CFDA' : '#3D5F9B';
                    $_lbl_wgt  = $_day['pct'] >= 90 ? '600' : '500';
                ?>
                <td width="14%" align="center" valign="bottom" style="padding:0 3px;">
                  <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                      <td align="center" style="padding-bottom:5px;">
                        <span style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:700; color:<?php echo $_cnt_clr ?>;"><?php echo $_day['count'] > 0 ? esc_html($_day['count']) : '0' ?></span>
                      </td>
                    </tr>
                    <tr>
                      <td height="80" valign="bottom" style="vertical-align:bottom; height:80px;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                          <?php if ($_spacer_h > 0) : ?>
                          <tr><td height="<?php echo $_spacer_h ?>" style="font-size:1px; line-height:1px; height:<?php echo $_spacer_h ?>px;">&nbsp;</td></tr>
                          <?php endif ?>
                          <tr><td height="<?php echo max(1, $_bar_h) ?>" style="background-color:<?php echo $_bar_bg ?>; border-radius:4px 4px 0 0; font-size:1px; line-height:1px; height:<?php echo max(1, $_bar_h) ?>px;">&nbsp;</td></tr>
                        </table>
                      </td>
                    </tr>
                    <tr>
                      <td align="center" style="padding-top:7px;">
                        <span style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:<?php echo $_lbl_wgt ?>; color:<?php echo $_lbl_clr ?>; text-transform:uppercase; letter-spacing:1px;"><?php echo esc_html($_day['label']) ?></span>
                      </td>
                    </tr>
                  </table>
                </td>
                <?php endforeach ?>
              </tr>
            </table>

            <!-- Baseline rule -->
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:0;">
              <tr><td height="2" style="background-color:#C4CFDA; font-size:1px; line-height:1px;">&nbsp;</td></tr>
            </table>

          </td>
        </tr>


        <!-- ============================================================
             SECTION HEADING
        ============================================================ -->
        <tr>
          <td style="background-color:#E4ECF3; padding:28px 0 16px 0;" align="center">
            <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; font-weight:700; color:#3D5F9B; letter-spacing:2.5px; text-transform:uppercase; margin:0 0 8px 0;"><?php esc_html_e('Best Performances This Week', 'salon-booking-system') ?></p>
            <table border="0" cellpadding="0" cellspacing="0"><tr><td width="36" height="3" style="background-color:#3D5F9B; border-radius:2px; font-size:1px; line-height:1px;">&nbsp;</td></tr></table>
          </td>
        </tr>


        <!-- ============================================================
             ROW 1: TOP SERVICES + TOP STAFF
        ============================================================ -->
        <tr>
          <td style="background-color:#E4ECF3; padding:0 0 8px 0;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr valign="top">

                <!-- TOP SERVICES -->
                <td width="296" style="background-color:#DEE6ED; border-radius:8px; border:1px solid #C4CFDA; padding:20px; vertical-align:top;">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:700; color:#778189; text-transform:uppercase; letter-spacing:2px; margin:0 0 16px 0;"><?php esc_html_e('Top Services', 'salon-booking-system') ?></p>

                  <?php if (!empty($_services_list)) :
                      $_rank = 0;
                      foreach ($_services_list as $_svc) :
                          $_rank++;
                          if ($_rank > 5) break;
                          list($_bg, $_clr, $_fw) = $_badge($_rank);
                          $_pct = (int)round($_svc['amount'] / $_max_svc_amount * 100);
                          $_name_weight = 1 === $_rank ? '700' : '500';
                  ?>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:<?php echo $_rank < 5 ? '12' : '0' ?>px;">
                    <tr valign="middle">
                      <td width="24" height="24" style="background-color:<?php echo $_bg ?>; border-radius:4px; text-align:center; vertical-align:middle; width:24px; height:24px;">
                        <span style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:<?php echo $_fw ?>; color:<?php echo $_clr ?>; line-height:24px;"><?php echo $_rank ?></span>
                      </td>
                      <td style="padding-left:10px;">
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; font-weight:<?php echo $_name_weight ?>; color:#3D5F9B; margin:0 0 1px 0;"><?php echo esc_html($_svc['name']) ?></p>
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; color:#778189; margin:0 0 5px 0;"><?php echo esc_html($_svc['count']) ?> <?php esc_html_e('bookings', 'salon-booking-system') ?> &middot; <?php echo $plugin->format()->money($_svc['amount'], false, false, true, false, true) ?></p>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="height:3px;">
                          <tr>
                            <td width="<?php echo $_pct ?>%" style="background-color:#3D5F9B; height:3px; border-radius:2px 0 0 2px; font-size:1px; line-height:1px;">&nbsp;</td>
                            <?php if ($_pct < 100) : ?><td style="background-color:#C4CFDA; height:3px; border-radius:0 2px 2px 0; font-size:1px; line-height:1px;">&nbsp;</td><?php endif ?>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </table>
                  <?php endforeach; else : ?>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; color:#778189; font-style:italic; margin:0;"><?php esc_html_e('No data for this period.', 'salon-booking-system') ?></p>
                  <?php endif ?>
                </td>

                <td width="8" style="font-size:1px; line-height:1px;">&nbsp;</td>

                <!-- TOP STAFF -->
                <td width="296" style="background-color:#DEE6ED; border-radius:8px; border:1px solid #C4CFDA; padding:20px; vertical-align:top;">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:700; color:#778189; text-transform:uppercase; letter-spacing:2px; margin:0 0 16px 0;"><?php esc_html_e('Top Staff', 'salon-booking-system') ?></p>

                  <?php if (!empty($_attendants_list)) :
                      $_rank = 0;
                      foreach ($_attendants_list as $_att) :
                          $_rank++;
                          if ($_rank > 5) break;
                          list($_bg, $_clr, $_fw) = $_badge($_rank);
                          $_pct = (int)round($_att['amount'] / $_max_att_amount * 100);
                          $_name_weight = 1 === $_rank ? '700' : '500';
                  ?>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:<?php echo $_rank < 5 ? '12' : '0' ?>px;">
                    <tr valign="middle">
                      <td width="24" height="24" style="background-color:<?php echo $_bg ?>; border-radius:4px; text-align:center; vertical-align:middle; width:24px; height:24px;">
                        <span style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:<?php echo $_fw ?>; color:<?php echo $_clr ?>; line-height:24px;"><?php echo $_rank ?></span>
                      </td>
                      <td style="padding-left:10px;">
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; font-weight:<?php echo $_name_weight ?>; color:#3D5F9B; margin:0 0 1px 0;"><?php echo esc_html($_att['name']) ?></p>
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; color:#778189; margin:0 0 5px 0;"><?php echo esc_html((int)$_att['count']) ?> <?php esc_html_e('bookings', 'salon-booking-system') ?> &middot; <?php echo $plugin->format()->money($_att['amount'], false, false, true, false, true) ?></p>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="height:3px;">
                          <tr>
                            <td width="<?php echo $_pct ?>%" style="background-color:#3D5F9B; height:3px; border-radius:2px 0 0 2px; font-size:1px; line-height:1px;">&nbsp;</td>
                            <?php if ($_pct < 100) : ?><td style="background-color:#C4CFDA; height:3px; border-radius:0 2px 2px 0; font-size:1px; line-height:1px;">&nbsp;</td><?php endif ?>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </table>
                  <?php endforeach; elseif (!$plugin->getSettings()->isAttendantsEnabled()) : ?>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; color:#778189; font-style:italic; margin:0;"><?php esc_html_e('Staff tracking not enabled.', 'salon-booking-system') ?></p>
                  <?php else : ?>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; color:#778189; font-style:italic; margin:0;"><?php esc_html_e('No data for this period.', 'salon-booking-system') ?></p>
                  <?php endif ?>
                </td>

              </tr>
            </table>
          </td>
        </tr>

        <!-- Spacer between rows -->
        <tr><td height="8" style="background-color:#E4ECF3; font-size:1px; line-height:1px;">&nbsp;</td></tr>


        <!-- ============================================================
             ROW 2: BUSIEST DAYS + TOP CUSTOMERS
        ============================================================ -->
        <tr>
          <td style="background-color:#E4ECF3; padding:0 0 20px 0;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr valign="top">

                <!-- BUSIEST DAYS -->
                <td width="296" style="background-color:#DEE6ED; border-radius:8px; border:1px solid #C4CFDA; padding:20px; vertical-align:top;">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:700; color:#778189; text-transform:uppercase; letter-spacing:2px; margin:0 0 16px 0;"><?php esc_html_e('Busiest Days', 'salon-booking-system') ?></p>

                  <?php if (!empty($_weekdays_list)) :
                      $_rank = 0;
                      foreach ($_weekdays_list as $_wd) :
                          $_rank++;
                          if ($_rank > 5) break;
                          list($_bg, $_clr, $_fw) = $_badge($_rank);
                          $_pct = (int)round($_wd['count'] / $_max_wd_count * 100);
                          $_name_weight = 1 === $_rank ? '700' : '500';
                  ?>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:<?php echo $_rank < 5 ? '12' : '0' ?>px;">
                    <tr valign="middle">
                      <td width="24" height="24" style="background-color:<?php echo $_bg ?>; border-radius:4px; text-align:center; vertical-align:middle; width:24px; height:24px;">
                        <span style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:<?php echo $_fw ?>; color:<?php echo $_clr ?>; line-height:24px;"><?php echo $_rank ?></span>
                      </td>
                      <td style="padding-left:10px;">
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; font-weight:<?php echo $_name_weight ?>; color:#3D5F9B; margin:0 0 1px 0;"><?php echo esc_html(SLN_Enum_DaysOfWeek::getLabel($_wd['day_w'])) ?></p>
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; color:#778189; margin:0 0 5px 0;"><?php echo esc_html($_wd['count']) ?> <?php echo _n('booking', 'bookings', $_wd['count'], 'salon-booking-system') ?> &middot; <?php echo $plugin->format()->money($_wd['amount'], false, false, true, false, true) ?></p>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="height:3px;">
                          <tr>
                            <td width="<?php echo $_pct ?>%" style="background-color:#3D5F9B; height:3px; border-radius:2px<?php echo $_pct < 100 ? ' 0 0' : '' ?> 2px; font-size:1px; line-height:1px;">&nbsp;</td>
                            <?php if ($_pct < 100) : ?><td style="background-color:#C4CFDA; height:3px; border-radius:0 2px 2px 0; font-size:1px; line-height:1px;">&nbsp;</td><?php endif ?>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </table>
                  <?php endforeach; else : ?>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; color:#778189; font-style:italic; margin:0;"><?php esc_html_e('No data for this period.', 'salon-booking-system') ?></p>
                  <?php endif ?>
                </td>

                <td width="8" style="font-size:1px; line-height:1px;">&nbsp;</td>

                <!-- TOP CUSTOMERS -->
                <td width="296" style="background-color:#DEE6ED; border-radius:8px; border:1px solid #C4CFDA; padding:20px; vertical-align:top;">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:700; color:#778189; text-transform:uppercase; letter-spacing:2px; margin:0 0 16px 0;"><?php esc_html_e('Top Customers', 'salon-booking-system') ?></p>

                  <?php if (!empty($_customers_list)) :
                      $_rank = 0;
                      foreach ($_customers_list as $_cust) :
                          $_rank++;
                          if ($_rank > 5) break;
                          list($_bg, $_clr, $_fw) = $_badge($_rank);
                          $_pct = (int)round($_cust['amount'] / $_max_cust_amount * 100);
                          $_name_weight = 1 === $_rank ? '700' : '500';
                  ?>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:<?php echo $_rank < 5 ? '12' : '0' ?>px;">
                    <tr valign="middle">
                      <td width="24" height="24" style="background-color:<?php echo $_bg ?>; border-radius:4px; text-align:center; vertical-align:middle; width:24px; height:24px;">
                        <span style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:<?php echo $_fw ?>; color:<?php echo $_clr ?>; line-height:24px;"><?php echo $_rank ?></span>
                      </td>
                      <td style="padding-left:10px;">
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; font-weight:<?php echo $_name_weight ?>; color:#3D5F9B; margin:0 0 1px 0;"><?php echo esc_html($_cust['name']) ?></p>
                        <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; color:#778189; margin:0 0 5px 0;"><?php echo esc_html($_cust['count']) ?> <?php echo _n('visit', 'visits', $_cust['count'], 'salon-booking-system') ?> &middot; <?php echo $plugin->format()->money($_cust['amount'], false, false, true, false, true) ?></p>
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="height:3px;">
                          <tr>
                            <td width="<?php echo $_pct ?>%" style="background-color:#3D5F9B; height:3px; border-radius:2px<?php echo $_pct < 100 ? ' 0 0' : '' ?> 2px; font-size:1px; line-height:1px;">&nbsp;</td>
                            <?php if ($_pct < 100) : ?><td style="background-color:#C4CFDA; height:3px; border-radius:0 2px 2px 0; font-size:1px; line-height:1px;">&nbsp;</td><?php endif ?>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </table>
                  <?php endforeach; else : ?>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:13px; color:#778189; font-style:italic; margin:0;"><?php esc_html_e('No customer accounts tracked this week.', 'salon-booking-system') ?></p>
                  <?php endif ?>
                </td>

              </tr>
            </table>
          </td>
        </tr>


        <!-- ============================================================
             LIFETIME VALUE STRIP
        ============================================================ -->
        <tr>
          <td style="background-color:#3D5F9B; padding:32px 28px 28px 28px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr>
                <td align="center" colspan="5" style="padding-bottom:20px;">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:700; color:#C8D5EA; text-transform:uppercase; letter-spacing:2px; margin:0;"><?php esc_html_e('Your Journey With Salon Booking System', 'salon-booking-system') ?></p>
                </td>
              </tr>
              <tr valign="top">
                <td width="182" align="center">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:24px; font-weight:800; color:#FFFFFF; margin:0; line-height:1;"><?php echo esc_html(number_format_i18n($lifetime['total_bookings'])) ?></p>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:600; color:#C8D5EA; margin:14px 0 0 0; letter-spacing:1.5px; text-transform:uppercase;"><?php esc_html_e('Total Bookings', 'salon-booking-system') ?></p>
                </td>
                <td width="1" style="background-color:#4E6FA8; font-size:1px; line-height:1px;">&nbsp;</td>
                <td width="182" align="center">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:24px; font-weight:800; color:#FFFFFF; margin:0; line-height:1;"><?php echo $plugin->format()->money($lifetime['revenue'], false, false, true, false, true) ?></p>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:600; color:#C8D5EA; margin:14px 0 0 0; letter-spacing:1.5px; text-transform:uppercase;"><?php esc_html_e('Revenue Managed', 'salon-booking-system') ?></p>
                </td>
                <td width="1" style="background-color:#4E6FA8; font-size:1px; line-height:1px;">&nbsp;</td>
                <td width="182" align="center">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:24px; font-weight:800; color:#FFFFFF; margin:0; line-height:1;"><?php echo esc_html(number_format_i18n($lifetime['loyal_customers'])) ?></p>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:600; color:#C8D5EA; margin:14px 0 0 0; letter-spacing:1.5px; text-transform:uppercase;"><?php esc_html_e('Loyal Customers', 'salon-booking-system') ?></p>
                </td>
              </tr>
              <tr>
                <td align="center" colspan="5" style="padding-top:20px; border-top:1px solid #4E6FA8;">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:400; color:#C8D5EA; margin:0; line-height:1.7; font-style:italic;"><?php esc_html_e('Every booking above was handled automatically — no phone tag, no scheduling conflicts.', 'salon-booking-system') ?></p>
                </td>
              </tr>
            </table>
          </td>
        </tr>


        <!-- ============================================================
             CTA BUTTON
        ============================================================ -->
        <tr>
          <td style="background-color:#DEE6ED; padding:32px 28px;" align="center">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo esc_url(admin_url('admin.php?page=salon-reports')) ?>" style="height:50px;v-text-anchor:middle;width:260px;" arcsize="10%" stroke="f" fillcolor="#3D5F9B">
              <w:anchorlock/>
              <center style="color:#FFFFFF;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;letter-spacing:1px;"><?php esc_html_e('VIEW FULL REPORT', 'salon-booking-system') ?> &rarr;</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="<?php echo esc_url(admin_url('admin.php?page=salon-reports')) ?>" style="background-color:#3D5F9B; color:#FFFFFF; font-family:'Montserrat',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:1px; text-transform:uppercase; text-decoration:none; padding:14px 36px; border-radius:6px; display:inline-block; mso-hide:all;"><?php esc_html_e('View Full Report', 'salon-booking-system') ?> &rarr;</a>
            <!--<![endif]-->
            <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; font-weight:400; color:#778189; margin:12px 0 0 0;"><?php esc_html_e('in your WordPress dashboard', 'salon-booking-system') ?></p>
          </td>
        </tr>


        <!-- ============================================================
             PROMO BLOCK — free edition only
        ============================================================ -->
        <?php if ($is_free) : ?>
        <tr>
          <td style="background-color:#E4ECF3; border-top:3px solid #3D5F9B; padding:24px 28px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr valign="middle">
                <td>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:700; color:#3D5F9B; text-transform:uppercase; letter-spacing:2px; margin:0 0 4px 0;"><?php esc_html_e('Upgrade to Pro', 'salon-booking-system') ?></p>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:18px; font-weight:800; color:#3D5F9B; margin:0;"><?php esc_html_e('Your salon is growing.', 'salon-booking-system') ?></p>
                </td>
                <td align="right" valign="top">
                  <span style="font-family:'Montserrat',Arial,sans-serif; font-size:9px; font-weight:700; color:#FFFFFF; background-color:#3D5F9B; border-radius:4px; padding:5px 10px; letter-spacing:1.5px; text-transform:uppercase; white-space:nowrap;"><?php esc_html_e('Special Offer', 'salon-booking-system') ?></span>
                </td>
              </tr>
            </table>

            <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:400; color:#778189; line-height:1.7; margin:12px 0 14px 0;"><?php esc_html_e('Unlock these tools to accelerate:', 'salon-booking-system') ?></p>

            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr><td style="border-bottom:1px solid #C4CFDA; padding:8px 0;">
                <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:500; color:#3D5F9B; margin:0;"><span style="font-weight:700;">&#10003;</span>&nbsp;&nbsp; <?php esc_html_e('Online payment processing (Stripe, PayPal)', 'salon-booking-system') ?></p>
              </td></tr>
              <tr><td style="border-bottom:1px solid #C4CFDA; padding:8px 0;">
                <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:500; color:#3D5F9B; margin:0;"><span style="font-weight:700;">&#10003;</span>&nbsp;&nbsp; <?php esc_html_e('Automated SMS &amp; email reminders', 'salon-booking-system') ?></p>
              </td></tr>
              <tr><td style="border-bottom:1px solid #C4CFDA; padding:8px 0;">
                <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:500; color:#3D5F9B; margin:0;"><span style="font-weight:700;">&#10003;</span>&nbsp;&nbsp; <?php esc_html_e('Google Calendar two-way sync', 'salon-booking-system') ?></p>
              </td></tr>
              <tr><td style="padding:8px 0;">
                <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:500; color:#3D5F9B; margin:0;"><span style="font-weight:700;">&#10003;</span>&nbsp;&nbsp; <?php esc_html_e('Unlimited staff &amp; service management', 'salon-booking-system') ?></p>
              </td></tr>
            </table>

            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:20px;">
              <tr><td align="center">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="https://www.salonbookingsystem.com/plugin-pricing-2/" style="height:48px;v-text-anchor:middle;width:230px;" arcsize="10%" stroke="f" fillcolor="#3D5F9B">
                  <w:anchorlock/>
                  <center style="color:#FFFFFF;font-family:Arial,sans-serif;font-size:13px;font-weight:bold;letter-spacing:1px;"><?php esc_html_e('SEE PRO PLANS', 'salon-booking-system') ?> &rarr;</center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-->
                <a href="https://www.salonbookingsystem.com/plugin-pricing-2/" style="background-color:#3D5F9B; color:#FFFFFF; font-family:'Montserrat',Arial,sans-serif; font-size:13px; font-weight:700; letter-spacing:1px; text-transform:uppercase; text-decoration:none; padding:13px 32px; border-radius:6px; display:inline-block; mso-hide:all;"><?php esc_html_e('See Pro Plans', 'salon-booking-system') ?> &rarr;</a>
                <!--<![endif]-->
                <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; font-weight:400; color:#778189; margin:10px 0 0 0; font-style:italic;"><?php esc_html_e('Starting from €89/year — less than €8/month', 'salon-booking-system') ?></p>
              </td></tr>
            </table>
          </td>
        </tr>
        <?php endif ?>


        <!-- ============================================================
             FOOTER
        ============================================================ -->
        <tr>
          <td style="background-color:#3D5F9B; padding:24px 28px; border-radius:0 0 8px 8px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr valign="top">
                <td width="50%">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:14px; font-weight:700; color:#FFFFFF; margin:0 0 4px 0;"><?php echo esc_html($plugin->getSettings()->getSalonName()) ?></p>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; font-weight:400; color:#C8D5EA; margin:0 0 2px 0;"><?php echo esc_html($plugin->getSettings()->get('gen_address')) ?></p>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; color:#C8D5EA; margin:0 0 2px 0;"><a href="mailto:<?php echo esc_attr($plugin->getSettings()->getSalonEmail()) ?>" style="color:#C8D5EA; text-decoration:none;"><?php echo esc_html($plugin->getSettings()->getSalonEmail()) ?></a></p>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:11px; color:#C8D5EA; margin:0;"><?php echo esc_html($plugin->getSettings()->get('gen_phone')) ?></p>
                </td>
                <td width="50%" align="right" valign="middle">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:400; color:#C8D5EA; margin:0 0 3px 0; letter-spacing:0.5px;"><?php esc_html_e('Powered by', 'salon-booking-system') ?></p>
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:12px; font-weight:700; color:#FFFFFF; margin:0;">Salon Booking System</p>
                </td>
              </tr>
            </table>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:16px; border-top:1px solid #4E6FA8;">
              <tr>
                <td align="center" style="padding-top:14px;">
                  <p style="font-family:'Montserrat',Arial,sans-serif; font-size:10px; font-weight:400; color:#C8D5EA; margin:0; line-height:1.6;"><?php esc_html_e('You are receiving this report because you are an administrator of a site using Salon Booking System.', 'salon-booking-system') ?></p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr><td height="8" style="font-size:1px; line-height:1px;">&nbsp;</td></tr>

      </table>
      <!--[if mso]></td></tr></table><![endif]-->

    </td>
  </tr>
</table>

</body>
</html>
