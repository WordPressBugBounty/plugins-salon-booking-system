<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<table class="es-right" cellspacing="0" cellpadding="0" align="right" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:right">
    <tr>
        <td align="left" style="padding:0;Margin:0;width:270px">
            <table width="100%" cellspacing="0" cellpadding="0" bgcolor="#f7f8fa" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f7f8fa;border-radius:10px" role="presentation">
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:20px;color:#a2b2ce;font-size:13px">
                            <?php esc_html_e("TOP SPENDER CUSTOMERS", 'salon-booking-system') ?>
                        </p>
                    </td>
                </tr>
                <?php if (!empty($stats['customers'])) : ?>
                    <?php $i = 1 ?>
                    <?php foreach($stats['customers'] as $customer): ?>
                        <tr>
                            <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px">
                                <?php if ($i === 1): ?>
                                    <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333;font-size:16px">
                                        <strong><?php echo esc_attr($customer['name']) ?></strong>
                                    </p>
                                <?php else: ?>
                                    <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333;font-size:16px">
                                        <?php echo esc_attr($customer['name']) ?>
                                    </p>
                                <?php endif ?>
                            </td>
                        </tr>
                        <tr>
                            <td align="left" style="padding:0;Margin:0;padding-bottom:10px;padding-left:25px;padding-right:25px">
                                <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#7588a5;font-size:16px">
                                    <?php echo esc_attr($customer['count']) ?> / <?php echo $plugin->format()->money($customer['amount'], false, false, true, false, true) ?>
                                </p>
                            </td>
                        </tr>
                        <?php $i++ ?>
                        <?php
                            if ($i > 5) {
                                break;
                            }
                        ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px">
                            <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333;font-size:16px">
                                -
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table><!--[if mso]></td></tr></table><![endif]-->