<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<table class="es-left" cellspacing="0" cellpadding="0" align="left" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:left">
  <tr>
  <td class="es-m-p20b" align="left" style="padding:0;Margin:0;width:270px">
    <table width="100%" cellspacing="0" cellpadding="0" bgcolor="#F7F8FA" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f7f8fa;border-radius:10px" role="presentation">
      <tr>
      <td align="left" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:20px;color:#a2b2ce;font-size:13px"><?php esc_html_e('AVAILABLE DISCOUNTS' , 'salon-booking-system') ?></p></td>
      </tr>
      <?php foreach($discounts as $discount): ?>
      <tr>
        <td align="left" style="Margin:0;padding-top:10px;padding-bottom:10px;padding-left:25px;padding-right:25px">
          <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:27px;color:#1e396c;font-size:20px"><?php
          echo esc_attr($discount->getAmountString()); ?> <?php echo esc_attr($discount->getName()); ?></p>
          <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#1e396c;font-size:16px">
            <?php if($discount->getDiscountType() == SLB_Discount_Enum_DiscountType::DISCOUNT_CODE): ?>
              <strong><?php echo esc_attr($discount->getCouponCode()) ?></strong>
            <?php else: ?>
              <strong><?php esc_html_e('Automatic discount', 'salon-booking-system') ?></strong>
            <?php endif ?>
          </p>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr>
      <td align="center" height="82" style="padding:0;Margin:0;padding-top:10px;padding-bottom:15px">
        <span class="es-button-border" style="border-style:solid;border-color:#2cb543;background:#1e396c;border-width:0px;display:inline-block;border-radius:5px;width:auto;mso-border-alt:10px">
          <a href="<?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo get_permalink($settings->getPayPageId()) ?>" class="es-button es-button-2" target="_blank" style="mso-style-priority:100 !important;text-decoration:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;color:#FFFFFF;font-size:14px;padding:15px 30px;display:inline-block;background:#1e396c;border-radius:5px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-weight:normal;font-style:normal;line-height:17px;width:auto;text-align:center;border-color:#1e396c"><?php esc_html_e('NEW RESERVATION', 'salon-booking-system')?></a>
        </span>
      </td>
      </tr>
    </table></td>
  </tr>
</table><!--[if mso]></td><td style="width:20px"></td><td style="width:270px" valign="top"><![endif]-->