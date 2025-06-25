<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<table>
<tr>
<td align="center">
<?php $size = 270;
if (($booking->hasStatus(SLN_Enum_BookingStatus::PENDING_PAYMENT) || isset($payRemainingAmount) && $payRemainingAmount) && $booking->getAmount() !== 0):
	$size = $size * 2 / 3; ?>
	<table class="es-left" cellspacing="0" cellpadding="0" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
		<tr>
		<td class="es-m-p20b" align="center" style="padding:0;Margin:0;width:<?php echo esc_attr($size) ?>px">
		<table width="90%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;border-radius:10px" role="presentation">
			<tr>
			<td align="center" style="padding:0;Margin:0">
				<span class="es-button-border" style="border-style:solid;border-color:#1e396c;background:#1e396c;border-width:2px;display:inline-block;border-radius:5px;width:100%;mso-border-alt:10px">
					<a href="<?php echo $booking->getPayUrl(isset($payRemainingAmount) && $payRemainingAmount) ?>" target="_blank" class="es-button es-button-1" target="_blank" style="mso-style-priority:100 !important;text-decoration:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;color:#FFFFFF;font-size:18px;padding: 20px;display:inline-block;background:#1e396c;border-radius:5px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-weight:normal;font-style:normal;line-height:22px;width:auto;text-align:center;border-color:#1e396c"><?php
						esc_html_e('PAY', 'salon-booking-system');?>
                                        <?php if (isset($payRemainingAmount) && $payRemainingAmount): ?>
                                            <strong><?php echo $plugin->format()->moneyFormatted($booking->getRemaingAmountAfterPay()) ?></strong>
					<?php else: ?>
                                            <?php if ($booking->getDeposit()): ?>
                                                <strong><?php echo $plugin->format()->moneyFormatted($booking->getDeposit()) ?></strong>
                                            <?php else: ?>
                                                <strong><?php echo $plugin->format()->moneyFormatted($booking->getAmount()) ?></strong>
                                            <?php endif?>
					<?php endif?></a>
				</span>
			</td>
			</tr>
		</table>
		</td>
		</tr>
	</table>
<?php endif ?>
<?php if ($customer && $plugin->getSettings()->getBookingmyaccountPageId()): ?>
<table class="es-left" cellspacing="0" cellpadding="0" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
	<tr>
	<td class="es-m-p20b" align="center" style="padding:0;Margin:0;width:<?php echo esc_attr($size) ?>px">
	<table width="90%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;border-radius:10px" role="presentation">
		<tr>
		<td align="center" style="padding:0;Margin:0">
			<span class="es-button-border" style="border-style:solid;border-color:#1e396c;background:#ffffff;border-width:2px;display:inline-block;border-radius:5px;width:100%;mso-border-alt:10px">
				<a href="<?php echo home_url() . '?sln_customer_login=' . $customer->getHash(); ?>" class="es-button es-button-1" target="_blank" style="mso-style-priority:100 !important;text-decoration:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;color:#1e396c;font-size:18px;padding:20px;display:inline-block;background:#ffffff;border-radius:5px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-weight:normal;font-style:normal;line-height:22px;width:auto;text-align:center;border-color:#1e396c"><?php
					esc_html_e('MANAGE', 'salon-booking-system') ?></a>
			</span>
		</td>
		</tr>
	</table>
	</td>
	</tr>
</table>
<?php endif ?>
<?php if ($plugin->getSettings()->get('cancellation_enabled') && !$booking->hasStatus(SLN_Enum_BookingStatus::CANCELED)): ?>
<table class="es-right" cellspacing="0" cellpadding="0" align="right" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:right">
	<tr>
	<td align="center" style="padding:0;Margin:0;width:<?php echo esc_attr($size) ?>px">
		<table width="90%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;border-radius:10px" role="presentation">
			<tr>
			<td align="center" style="padding:0;Margin:0">
				<span class="es-button-border" style="border-style:solid;border-color:#707070;background:#ffffff;border-width:2px;display:inline-block;border-radius:5px;width:100%;mso-border-alt:10px">
					<a href="<?php echo $booking->getCancelUrl() ?>" class="es-button es-button-1683814254067" target="_blank" style="mso-style-priority:100 !important;text-decoration:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;color:#707070;font-size:18px;padding:10px 20px 10px 20px;display:inline-block;background:#ffffff;border-radius:5px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-weight:normal;font-style:normal;line-height:22px;width:auto;text-align:center;padding-top:20px;padding-bottom:20px"><?php
						esc_html_e('CANCEL', 'salon-booking-system') ?></a>
				</span>
			</td>
			</tr>
		</table>
	</td>
	</tr>
</table>
<?php endif ?>
</td>
</tr>
</table>