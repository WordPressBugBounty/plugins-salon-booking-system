<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
if (!isset($data) || !($data instanceof ArrayAccess)) {
	$data = new ArrayObject();
}

$google_link  = '#';
$ical_link    = '#';
$outlook_link = '#';
try {
	$google_link = SLN_Helper_CalendarLink::getGoogleLink($booking);
} catch (Exception $e) {
	SLN_Plugin::addLog('Calendar Google link failed: ' . $e->getMessage());
}
try {
	$ical_link = SLN_Helper_CalendarLink::getICallLink($booking, $data);
} catch (Exception $e) {
	SLN_Plugin::addLog('Calendar iCal link failed: ' . $e->getMessage());
}
try {
	$outlook_link = SLN_Helper_CalendarLink::getOutlookLink($booking);
} catch (Exception $e) {
	SLN_Plugin::addLog('Calendar Outlook link failed: ' . $e->getMessage());
}
?>
<tr>
	<td align="left" style="padding:0;Margin:0;padding-top:20px;padding-right:20px;padding-left:25px">
		<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#333333;font-size:14px"><?php echo esc_html__('Add to your calendars', 'salon-booking-system') ?>:</p>
	</td>
</tr>
<tr>
	<td align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px">
		<table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:100%;max-width:560px">
			<tr>
				<td align="center" valign="top" width="33%" style="padding:0;Margin:0;width:33%">
					<a href="<?php echo esc_url($google_link) ?>" target="_blank">
						<img src="<?php echo esc_url(SLN_PLUGIN_URL . '/img/email/calendar-google-48.png') ?>" alt="<?php echo esc_attr__('Google calendar', 'salon-booking-system'); ?>" width="50" height="49" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;width:50px;height:49px">
					</a>
				</td>
				<td align="center" valign="top" width="33%" style="padding:0;Margin:0;width:33%">
					<a href="<?php echo esc_url($ical_link) ?>" target="_blank">
						<img src="<?php echo esc_url(SLN_PLUGIN_URL . '/img/email/calendar-ical-50.png') ?>" alt="<?php echo esc_attr__('iCal calendar', 'salon-booking-system'); ?>" width="50" height="51" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;width:50px;height:51px">
					</a>
				</td>
				<td align="center" valign="top" width="33%" style="padding:0;Margin:0;width:33%">
					<a href="<?php echo esc_url($outlook_link) ?>" target="_blank">
						<img src="<?php echo esc_url(SLN_PLUGIN_URL . '/img/email/calendar-outlook-48.png') ?>" alt="<?php echo esc_attr__('Outlook calendar', 'salon-booking-system'); ?>" width="50" height="50" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;width:50px;height:50px">
					</a>
				</td>
			</tr>
		</table>
	</td>
</tr>
