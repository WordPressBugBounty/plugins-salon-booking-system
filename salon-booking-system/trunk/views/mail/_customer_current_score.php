<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<table class="es-right" cellspacing="0" cellpadding="0" align="right" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:right">
    <tr>
        <td align="left" style="padding:0;Margin:0;width:270px">
            <table width="100%" cellspacing="0" cellpadding="0" bgcolor="#f7f8fa" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f7f8fa;border-radius:10px" role="presentation">
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:20px;color:#a2b2ce;font-size:13px"><?php esc_html_e('YOUR CURRENT SCORE*', 'salon-booking-system') ?></p></td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:90px;color:#525252;font-size:60px">
                            <?php if ($customer): ?>
                                <strong><?php echo esc_attr($customer->getFidelityScore()) ?></strong>
                            <?php else: ?>
                                -
                            <?php endif ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="left" style="padding:0;Margin:0;padding-bottom:15px;padding-left:15px;padding-right:15px">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#333333;font-size:14px">
                            <?php esc_html_e('*Your personal current score is calculated considering all your past reservations with us.', 'salon-booking-system') ?>
                        </p>
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#333333;font-size:14px"><br></p>
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#333333;font-size:14px">
                            <?php esc_html_e('It\'s used to give you special offers.', 'salon-booking-system') ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:10px;Margin:0;font-size:0">
                        <table border="0" width="100%" height="100%" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                            <tr>
                                <td style="padding:0;Margin:0;border-bottom:0px solid #cccccc;background:unset;height:1px;width:100%;margin:0px"></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>