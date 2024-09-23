<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<!--[if mso]><table style="width:560px" cellpadding="0"
                        cellspacing="0"><tr><td style="width:270px" valign="top"><![endif]-->
<table class="es-left" cellspacing="0" cellpadding="0" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
    <tr>
        <td class="es-m-p20b" align="left" style="padding:0;Margin:0;width:270px">
            <table width="100%" cellspacing="0" cellpadding="0" bgcolor="#F7F8FA" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f7f8fa;border-radius:10px" role="presentation">
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:arial, 'helvetica neue', helvetica, sans-serif;line-height:20px;color:#a2b2ce;font-size:13px">
                            <?php esc_html_e("MOST BOOKED WEEK DAYS", 'salon-booking-system') ?>
                        </p>
                    </td>
                </tr>
                <?php if (!empty($stats['weekdays'])) : ?>
                    <?php $i = 1 ?>
                    <?php foreach($stats['weekdays'] as $weekday => $data): ?>
                        <tr>
                            <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px">
                                <?php if ($i === 1): ?>
                                    <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333;font-size:16px">
                                        <strong><?php echo SLN_Enum_DaysOfWeek::getLabel($weekday) ?></strong>
                                    </p>
                                <?php else: ?>
                                    <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#333333;font-size:16px">
                                        <?php echo SLN_Enum_DaysOfWeek::getLabel($weekday) ?>
                                    </p>
                                <?php endif ?>
                            </td>
                        </tr>
                        <tr>
                            <td align="left" style="padding:0;Margin:0;padding-bottom:10px;padding-left:25px;padding-right:25px">
                                <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#7588a5;font-size:16px">
                                    <?php echo $data['count'] ?> / <?php echo $plugin->format()->money($data['amount'], false, false, true, false, true) ?>
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
</table><!--[if mso]></td><td style="width:20px"></td><td style="width:270px" valign="top"><![endif]-->