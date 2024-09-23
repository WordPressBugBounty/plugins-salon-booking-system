<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
    <tr>
    <td align="left" style="padding:0;Margin:0;width:560px">
        <table width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;border-radius:10px;background-color:#f8f8f8" bgcolor="#F8F8F8" role="presentation">
            <tr>
                <td align="left" style="Margin:0;padding-top:10px;padding-bottom:10px;padding-right:10px;padding-left:15px">
                    <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px;text-transform:uppercase;"><?php
	                    esc_html_e('Important notes', 'salon-booking-system') ?></p>
                </td>
            </tr>
            <tr>
                <td align="left" style="padding:0;Margin:0;padding-bottom:10px;padding-left:15px;padding-right:15px">
                    <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#5a5a5a;font-size:14px"><?php 
                    echo $plugin->getSettings()->get('gen_timetable') ?></p></td>
            </tr>
        </table>
    </td>
    </tr>
</table>