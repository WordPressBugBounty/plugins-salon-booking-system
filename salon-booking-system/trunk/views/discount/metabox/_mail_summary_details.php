<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<?php if ($discountText != ''): ?>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-top:5px;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px;text-transform:uppercase;">
				<strong><?php esc_html_e('DISCOUNT APPLIED', 'salon-booking-system') ?></strong>
			</p>
		</td>
	</tr>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:45px;color:#333333;font-size:30px"><?php
			echo esc_attr($discountText); ?></p>
		</td>
	</tr>
<?php endif; ?>