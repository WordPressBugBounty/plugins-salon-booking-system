<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
    <tr>
    <td align="left" style="padding:0;Margin:0;width:560px">
    <table width="100%" cellspacing="0" cellpadding="0" bgcolor="#F7F8FA" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f7f8fa;border-radius:10px" role="presentation">
        <tr>
            <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px">
                <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:20px;color:#a2b2ce;font-size:13px;text-transform:uppercase;"><?php
	                esc_html_e('CUSTOMER NOTES', 'salon-booking-system')?>
                </p>
            </td>
        </tr>
        <tr>
            <td align="left" style="Margin:0;padding-top:10px;padding-bottom:10px;padding-left:25px;padding-right:25px">
                <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#525252;font-size:16px">
                    <em><?php esc_html_e(sprintf('%s', $booking->getNote())); ?></em>
                </p>
            </td>
        </tr>
    </table></td>
    </tr>
</table>