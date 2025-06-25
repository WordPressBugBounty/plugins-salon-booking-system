<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
?>
<tr>
	<td align="left" style="padding:0;Margin:0;padding-top:20px;padding-right:20px;padding-left:25px">
		<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#333333;font-size:14px"><?php echo esc_html__('Add to your calendars', 'salon-booking-system') ?>:</p>
	</td>
</tr>
<tr>
<td class="esdev-adapt-off" align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px">
<table cellpadding="0" cellspacing="0" class="esdev-mso-table" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;width:560px">
	<tr>
	<td class="esdev-mso-td" valign="top" style="padding:0;Margin:0">
	<table cellpadding="0" cellspacing="0" class="es-left" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
		<tr>
		<td align="left" style="padding:0;Margin:0;width:167px">
		<table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
			<tr>
			<td align="center" style="padding:0;Margin:0;font-size:0px">
                            <a href="<?php echo SLN_Helper_CalendarLink::getGoogleLink($booking) ?>" target="_blank">
				<img src="<?php echo SLN_PLUGIN_URL . '/img/email/calendar-google-48.png' ?>" alt="<?php esc_html_e('Google calendar', 'salon-booking-system'); ?>" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" width="50" height="49">
                            </a>
			</td>
			</tr>
		</table></td>
		</tr>
	</table></td>
	<td style="padding:0;Margin:0;width:30px"></td>
	<td class="esdev-mso-td" valign="top" style="padding:0;Margin:0">
	<table cellpadding="0" cellspacing="0" class="es-left" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
		<tr>
		<td align="left" style="padding:0;Margin:0;width:167px">
		<table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
			<tr>
			<td align="center" style="padding:0;Margin:0;font-size:0px">
                            <a href="<?php echo SLN_Helper_CalendarLink::getICallLink($booking, $data) ?>" target="_blank">
				<img src="<?php echo SLN_PLUGIN_URL . '/img/email/calendar-ical-50.png'; ?>" alt="<?php esc_html_e('iCal calendar', 'salon-booking-system'); ?>" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" width="50" height="51">
                            </a>
                        </td>
			</tr>
		</table></td>
		</tr>
	</table></td>
	<td style="padding:0;Margin:0;width:30px"></td>
	<td class="esdev-mso-td" valign="top" style="padding:0;Margin:0">
	<table cellpadding="0" cellspacing="0" class="es-right" align="right" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:right">
		<tr>
		<td align="left" style="padding:0;Margin:0;width:166px">
		<table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
			<tr>
			<td align="center" style="padding:0;Margin:0;font-size:0px">
                            <a href="<?php echo SLN_Helper_CalendarLink::getOutlookLink($booking) ?>" target="_blank">
                                <img src="<?php echo SLN_PLUGIN_URL . '/img/email/calendar-outlook-48.png'; ?>" alt="<?php esc_html_e('Outlook calendar', 'salon-booking-system'); ?>" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic" width="50" height="50">
                            </a>
                        </td>
			</tr>
		</table></td>
		</tr>
	</table></td>
	</tr>
</table></td>
</tr>