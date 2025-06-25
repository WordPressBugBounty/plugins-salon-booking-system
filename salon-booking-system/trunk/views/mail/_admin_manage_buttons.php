<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<table>
<tr>
<td align="center">
<?php
if ($plugin->getSettings()->get('confirmation') && $booking->hasStatus(SLN_Enum_BookingStatus::PENDING)): ?>
	<table class="es-left" cellspacing="0" cellpadding="0" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
		<tr>
		<td class="es-m-p20b" align="center" style="padding:0;Margin:0;width:273px">
		<table width="90%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;border-radius:10px" role="presentation">
			<tr>
			<td align="center" style="padding:0;Margin:0">
				<span class="es-button-border" style="border-style:solid;border-color:#1E396C;background:#1E396C;border-width:2px;display:inline-block;border-radius:5px;width:100%;mso-border-alt:10px">
					<a href="<?php echo admin_url() ?>/post.php?post=<?php echo esc_attr($booking->getId()) ?>&action=edit" target="_blank" class="es-button es-button-1" target="_blank" style="mso-style-priority:100 !important;text-decoration:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;color:#FFFFFF;font-size:18px;padding: 20px;display:inline-block;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-weight:normal;font-style:normal;line-height:22px;width:auto;text-align:center;border-color:#1e396c"><?php
						esc_html_e('CONFIRM BOOKING', 'salon-booking-system'); ?>
				</span>
			</td>
			</tr>
		</table>
		</td>
		</tr>
	</table>
<?php endif ?>
<table class="es-left" cellspacing="0" cellpadding="0" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
	<tr>
	<td class="es-m-p20b" align="center" style="padding:0;Margin:0;width:273px">
	<table width="90%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;border-radius:10px" role="presentation">
		<tr>
		<td align="center" style="padding:0;Margin:0">
			<span class="es-button-border" style="border-style:solid;border-color:#1E396C;background:#ffffff;border-width:2px;display:inline-block;border-radius:5px;width:100%;mso-border-alt:10px">
				<a href="<?php echo admin_url() ?>/post.php?post=<?php echo esc_attr($booking->getId()) ?>&action=edit" class="es-button es-button-1" target="_blank" style="mso-style-priority:100 !important;text-decoration:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;color:#1e396c;font-size:18px;padding:20px;display:inline-block;background:#ffffff;border-radius:5px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-weight:normal;font-style:normal;line-height:22px;width:auto;text-align:center;border-color:#1e396c"><?php
					esc_html_e('MANAGE BOOKING', 'salon-booking-system'); ?></a>
			</span>
		</td>
		</tr>
	</table>
	</td>
	</tr>
</table>
</td>
</tr>
</table>