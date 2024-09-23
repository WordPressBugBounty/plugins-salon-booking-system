<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<table cellpadding="0" cellspacing="0" class="es-left" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
	<tr>
	<td class="es-m-p20b" align="left" style="padding:0;Margin:0;width:270px">
	<table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
		<tr>
			<td align="left" style="padding:0;Margin:0">
				<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:33px;color:#333333;font-size:20px">
				<strong><?php echo $plugin->getSettings()->getSalonName() ?></strong>
				</p>
			</td>
		</tr>
		<tr>
		<td align="left" style="padding:0;Margin:0">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#333333;font-size:18px"><?php
			echo $plugin->getSettings()->get('gen_address') ?></p>
		</td>
		</tr>
	</table></td>
	</tr>
</table>
<table cellpadding="0" cellspacing="0" class="es-right" align="right" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:right">
	<tr>
	<td align="left" style="padding:0;Margin:0;width:270px">
	<table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
		<tr>
		<td align="left" style="padding:0;Margin:0">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#333333;font-size:18px;">
                            <a href="tel:<?php echo $plugin->getSettings()->get('sms_prefix') . ' ' . $plugin->getSettings()->get('gen_phone'); ?>" target="_blank" style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#333333;font-size:18px;">
				<u><?php echo $plugin->getSettings()->get('sms_prefix') . ' ' . $plugin->getSettings()->get('gen_phone'); ?></u>
                            </a>
			</p>
		</td>
		</tr>
		<tr>
		<td align="left" style="padding:0;Margin:0">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#333333;font-size:18px">
				<a href="mailto:<?php echo $plugin->getSettings()->getSalonEmail() ?>" target="_blank" style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#333333;font-size:18px"><u><?php echo $plugin->getSettings()->getSalonEmail() ?></u></a>
			</p>
		</td>
		</tr>
		<tr>
		<td align="left" style="padding:0;Margin:0">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#333333;font-size:18px">
                            <a href="<?php echo add_query_arg(array('q' => implode(', ', array_filter(array($plugin->getSettings()->getSalonName(), $plugin->getSettings()->get('gen_address'))))), 'https://maps.google.com/') ?>" target="_blank" style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#333333;font-size:18px"><u><?php echo esc_html__('Find us on', 'salon-booking-system') . ' Google Maps' ?></u></a>
			</p>
		</td>
		</tr>
	</table></td>
	</tr>
</table>