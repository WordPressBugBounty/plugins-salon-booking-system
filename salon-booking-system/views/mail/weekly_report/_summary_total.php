<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<table cellpadding="0" cellspacing="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
    <tr>
        <td align="center" valign="top" style="padding:0;Margin:0;width:560px">
            <table cellpadding="0" cellspacing="0" width="100%" bgcolor="#F2F6FD" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f2f6fd;border-radius:10px;background-image:url(<?php echo SLN_PLUGIN_URL.'/img/email/trend_HXZ.png' ?>);background-repeat:no-repeat;background-position:90% 20%" background="<?php echo SLN_PLUGIN_URL.'/img/email/trend_HXZ.png' ?>" role="presentation">
                <tr>
                    <td align="left" style="padding:25px;Margin:0">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px">
                            <strong><?php esc_html_e("TOTAL RESERVATIONS / REVENUES", 'salon-booking-system') ?></strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:45px;color:#333333;font-size:30px">
                            <strong><?php echo esc_attr($stats['total']['count']) ?> / <?php echo $plugin->format()->money($stats['total']['amount'], false, false, true, false, true) ?></strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="Margin:0;padding-top:5px;padding-bottom:20px;padding-left:20px;padding-right:20px;font-size:0">
                        <table border="0" width="100%" height="100%" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td style="padding:0;Margin:0;border-bottom:1px solid #c6d1e5;background:unset;height:1px;width:100%;margin:0px"></td>
                            </tr>
                        </table></td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px">
                            <strong><?php esc_html_e("PAID ONLINE", 'salon-booking-system') ?></strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:38px;color:#333333;font-size:25px">
                            <?php echo esc_attr($stats['paid']['count']) ?> / <?php echo $plugin->format()->money($stats['paid']['amount'], false, false, true, false, true) ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="Margin:0;padding-top:5px;padding-bottom:20px;padding-left:20px;padding-right:20px;font-size:0">
                        <table border="0" width="100%" height="100%" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td style="padding:0;Margin:0;border-bottom:1px solid #c6d1e5;background:unset;height:1px;width:100%;margin:0px"></td>
                            </tr>
                        </table></td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px">
                            <strong><?php esc_html_e("PAID LATER", 'salon-booking-system') ?></strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:38px;color:#333333;font-size:25px">
                            <?php echo esc_attr($stats['pay_later']['count']) ?> / <?php echo $plugin->format()->money($stats['pay_later']['amount'], false, false, true, false, true) ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="Margin:0;padding-top:5px;padding-bottom:20px;padding-left:20px;padding-right:20px;font-size:0">
                        <table border="0" width="100%" height="100%" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td style="padding:0;Margin:0;border-bottom:1px solid #c6d1e5;background:unset;height:1px;width:100%;margin:0px"></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px">
                            <strong><?php esc_html_e("CANCELLED", 'salon-booking-system') ?></strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-bottom:15px;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:38px;color:#ff0000;font-size:25px">
                            <?php echo esc_attr($stats['canceled']['count']) ?> / <?php echo $plugin->format()->money($stats['canceled']['amount'], false, false, true, false, true) ?>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>