<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
if (!isset($forAdmin)) {
	$forAdmin = false;
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office" style="font-family:arial, 'helvetica neue', helvetica, sans-serif">
 <head>
  <meta charset="UTF-8">
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <meta name="x-apple-disable-message-reformatting">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta content="telephone=no" name="format-detection">
  <title>Salon Booking email</title>
  <link href="https://fonts.googleapis.com/css?family=Lato:400,400i,700,700i" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700,700i" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,400i,700,700i" rel="stylesheet"><!--<![endif]-->
  <style type="text/css">
#outlook a {
	padding:0;
}
.es-button {
	mso-style-priority:100!important;
	text-decoration:none!important;
}
a[x-apple-data-detectors] {
	color:inherit!important;
	text-decoration:none!important;
	font-size:inherit!important;
	font-family:inherit!important;
	font-weight:inherit!important;
	line-height:inherit!important;
}
.es-desk-hidden {
	display:none;
	float:left;
	overflow:hidden;
	width:0;
	max-height:0;
	line-height:0;
	mso-hide:all;
}
[data-ogsb] .es-button.es-button-1 {
	padding:20px!important;
}
[data-ogsb] .es-button.es-button-2 {
	padding:15px 30px!important;
}
@media only screen and (max-width:600px) {p, ul li, ol li, a { line-height:150%!important } h1, h2, h3, h1 a, h2 a, h3 a { line-height:120% } h1 { font-size:30px!important; text-align:left } h2 { font-size:24px!important; text-align:left } h3 { font-size:20px!important; text-align:left } .es-header-body h1 a, .es-content-body h1 a, .es-footer-body h1 a { font-size:30px!important; text-align:left } .es-header-body h2 a, .es-content-body h2 a, .es-footer-body h2 a { font-size:24px!important; text-align:left } .es-header-body h3 a, .es-content-body h3 a, .es-footer-body h3 a { font-size:20px!important; text-align:left } .es-menu td a { font-size:14px!important } .es-header-body p, .es-header-body ul li, .es-header-body ol li, .es-header-body a { font-size:14px!important } .es-content-body p, .es-content-body ul li, .es-content-body ol li, .es-content-body a { } .es-footer-body p, .es-footer-body ul li, .es-footer-body ol li, .es-footer-body a { font-size:14px!important } .es-infoblock p, .es-infoblock ul li, .es-infoblock ol li, .es-infoblock a { font-size:12px!important } *[class="gmail-fix"] { display:none!important } .es-m-txt-c, .es-m-txt-c h1, .es-m-txt-c h2, .es-m-txt-c h3 { text-align:center!important } .es-m-txt-r, .es-m-txt-r h1, .es-m-txt-r h2, .es-m-txt-r h3 { text-align:right!important } .es-m-txt-l, .es-m-txt-l h1, .es-m-txt-l h2, .es-m-txt-l h3 { text-align:left!important } .es-m-txt-r img, .es-m-txt-c img, .es-m-txt-l img { display:inline!important } .es-button-border { display:inline-block!important } a.es-button, button.es-button { font-size:18px!important; display:inline-block!important } .es-adaptive table, .es-left, .es-right { width:100%!important } .es-content table, .es-header table, .es-footer table, .es-content, .es-footer, .es-header { width:100%!important; max-width:600px!important } .es-adapt-td { display:block!important; width:100%!important } .adapt-img { /*width:100%!important;*/ height:auto!important } .es-m-p0 { padding:0!important } .es-m-p0r { padding-right:0!important } .es-m-p0l { padding-left:0!important } .es-m-p0t { padding-top:0!important } .es-m-p0b { padding-bottom:0!important } .es-m-p20b { padding-bottom:20px!important } .es-mobile-hidden, .es-hidden { display:none!important } tr.es-desk-hidden, td.es-desk-hidden, table.es-desk-hidden { width:auto!important; overflow:visible!important; float:none!important; max-height:inherit!important; line-height:inherit!important } tr.es-desk-hidden { display:table-row!important } table.es-desk-hidden { display:table!important } td.es-desk-menu-hidden { display:table-cell!important } .es-menu td { width:1%!important } table.es-table-not-adapt, .esd-block-html table { width:auto!important } table.es-social { display:inline-block!important } table.es-social td { display:inline-block!important } .es-desk-hidden { display:table-row!important; width:auto!important; overflow:visible!important; max-height:inherit!important } .es-m-p5 { padding:5px!important } .es-m-p5t { padding-top:5px!important } .es-m-p5b { padding-bottom:5px!important } .es-m-p5r { padding-right:5px!important } .es-m-p5l { padding-left:5px!important } .es-m-p10 { padding:10px!important } .es-m-p10t { padding-top:10px!important } .es-m-p10b { padding-bottom:10px!important } .es-m-p10r { padding-right:10px!important } .es-m-p10l { padding-left:10px!important } .es-m-p15 { padding:15px!important } .es-m-p15t { padding-top:15px!important } .es-m-p15b { padding-bottom:15px!important } .es-m-p15r { padding-right:15px!important } .es-m-p15l { padding-left:15px!important } .es-m-p20 { padding:20px!important } .es-m-p20t { padding-top:20px!important } .es-m-p20r { padding-right:20px!important } .es-m-p20l { padding-left:20px!important } .es-m-p25 { padding:25px!important } .es-m-p25t { padding-top:25px!important } .es-m-p25b { padding-bottom:25px!important } .es-m-p25r { padding-right:25px!important } .es-m-p25l { padding-left:25px!important } .es-m-p30 { padding:30px!important } .es-m-p30t { padding-top:30px!important } .es-m-p30b { padding-bottom:30px!important } .es-m-p30r { padding-right:30px!important } .es-m-p30l { padding-left:30px!important } .es-m-p35 { padding:35px!important } .es-m-p35t { padding-top:35px!important } .es-m-p35b { padding-bottom:35px!important } .es-m-p35r { padding-right:35px!important } .es-m-p35l { padding-left:35px!important } .es-m-p40 { padding:40px!important } .es-m-p40t { padding-top:40px!important } .es-m-p40b { padding-bottom:40px!important } .es-m-p40r { padding-right:40px!important } .es-m-p40l { padding-left:40px!important } .h-auto { height:auto!important } }
</style>
 </head>
 <body style="width:100%;font-family:arial, 'helvetica neue', helvetica, sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;padding:0;Margin:0">
  <div class="es-wrapper-color" style="background-color:#F7F7F7">
   <table class="es-wrapper" width="100%" cellspacing="0" cellpadding="0" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;padding:0;Margin:0;width:100%;height:100%;background-repeat:repeat;background-position:center top;background-color:#F7F7F7">
     <tr>
      <td valign="top" style="padding:0;Margin:0">
      <!-- Start Gen logo -->
       <table class="es-header" cellspacing="0" cellpadding="0" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%;background-color:transparent;background-repeat:repeat;background-position:center top">
         <tr>
          <td align="center" style="padding:0;Margin:0">
           <table class="es-header-body" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px">
             <tr class="es-mobile-hidden">
              <td align="left" bgcolor="#f7f7f7" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px;background-color:#f7f7f7">
              </td>
             </tr>
             <tr>
              <td align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px;padding-bottom: 8px;">
                <?php $logo = $plugin->getSettings()->get('gen_logo'); ?>
                <img class="adapt-img" src="<?php echo ($logo ? wp_get_attachment_image_url($logo, 'sln_gen_logo') : apply_filters('sln_default_email_logo', SLN_PLUGIN_URL . '/img/email/logo.png')); ?>" <?php echo ($logo ? '' : 'width="145" height="37"') ?> alt="img" style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;max-width: 100%;width: auto;height: auto;">
              </td>
             </tr>
           </table></td>
         </tr>
       </table>
       <!-- End Gen logo -->
       <table class="es-content" cellspacing="0" cellpadding="0" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%">
         <tr>
          <td align="center" style="padding:0;Margin:0">
           <table class="es-content-body" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px">
             <tr>
              <td align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px">
                <?php
                if(empty($updated_message)){
                    $updated_message = isset($data['updated_message']) ? $data['updated_message'] : '';
                }
                if(empty($updated)){
                    $updated = isset($data['updated']) ? $data['updated'] : false;
                }
                if(empty($remind)){
                    $remind = isset($data['remind']) ? $data['remind'] : false;
                }
                if(empty($customer)){
                    $customer = $booking->getCustomer();
                }
                if(empty($payRemainingAmount)){
                    $payRemainingAmount = isset($data['pay_remaining_amount']) ? $data['pay_remaining_amount'] : false;
                }
                echo $plugin->loadView('mail/' . $contentTemplate, compact('booking', 'plugin', 'updated_message', 'customer', 'forAdmin', 'updated', 'remind', 'payRemainingAmount')) ?>
              </td>
             </tr>
             <tr>
                <td align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px;border-radius:10px">
                    <?php echo $plugin->loadView('mail/_booking_info', compact('booking')) ?>
                </td>
             </tr>
             <tr>
              <td align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px">
                  <?php if ($booking->getNote()): ?>
                    <?php echo $plugin->loadView('mail/_customer_note', compact('booking')); ?>
                  <?php endif ?>
              </td>
             </tr>
             <tr>
              <td align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px">
                  <?php if ($plugin->getSettings()->get('gen_timetable')): ?>
                    <?php echo $plugin->loadView('mail/_salon_note', compact('plugin')) ?>
                  <?php endif ?>
              </td>
             </tr>
             <tr>
              <td class="es-m-p0r es-m-p0l" align="left" style="Margin:0;padding-left:20px;padding-right:20px;padding-top:40px;padding-bottom:40px; display: flex; justify-content: space-between;">
               <?php
               if(!$forAdmin){
                  echo $plugin->loadView('mail/_customer_manage_buttons', compact('booking', 'plugin', 'customer', 'payRemainingAmount'));
                }else{
                  echo $plugin->loadView('mail/_admin_manage_buttons', compact('plugin', 'booking'));
                } ?>
              </td>
             </tr><td>
             <?php echo $plugin->loadView('mail/_add_to_calendar', compact('booking')) ?>
               </td><tr>
              <td align="left" style="Margin:0;padding-bottom:20px;padding-left:20px;padding-right:20px;padding-top:40px">
               <?php echo $plugin->loadView('mail/_customer_info', compact('booking', 'customer'));
               echo $plugin->loadView('mail/_custom_fields', compact('booking', 'customer')); ?>
               </td>
             </tr>
             <tr>
                <td align="left" style="padding:20px;Margin:0">
                    <?php do_action('sln.mail.special', $booking, $customer); ?>
                    <?php if ($plugin->getSettings()->get('enable_customer_fidelity_score')): ?>
                        <?php echo $plugin->loadView('mail/_customer_current_score', compact('booking', 'plugin', 'customer')) ?>
                    <?php endif ?>
                </td>
             </tr>
           </table></td>
         </tr>
       </table>
       <table class="es-footer" cellspacing="0" cellpadding="0" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed !important;width:100%;background-color:transparent;background-repeat:repeat;background-position:center top">
         <tr>
          <td align="center" style="padding:0;Margin:0">
           <table class="es-footer-body" cellspacing="0" cellpadding="0" bgcolor="#ffffff" align="center" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#FFFFFF;width:600px">
             <tr>
              <td align="left" style="padding:0;Margin:0;padding-left:20px;padding-right:20px;padding-top:40px">
              <?php echo $plugin->loadView('mail/_salon_info', compact('plugin')) ?>
               </td>
             </tr>
             <tr>
              <td align="left" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px;height: 40px;">
             <?php if(defined('SLN_SPECIAL_EDITION') && SLN_SPECIAL_EDITION): ?>
                     <h3 align="center" valign="top" height="21" style="color:#7d7d7d;font-size:0.87em;padding:10px;"><?php esc_html_e('Proudly powered by', 'salon-booking-system') ?> <a target="_blanck" style="color:#127dc0;font-size: 1.03em;font-weight: 800;" href="https://www.salonbookingsystem.com/plugin-pricing/#utm_source=plugin-credits&utm_medium=email-notification&utm_campaign=email-notification&utm_id=plugin-credits"><?php esc_html_e('Salon Booking System', 'salon-booking-system'); ?></a></h3>
                     <?php endif; ?>
               </td>
             </tr>
             <tr class="es-mobile-hidden">
              <td align="left" bgcolor="#f7f7f7" style="padding:0;Margin:0;padding-top:20px;padding-left:20px;padding-right:20px;background-color:#f7f7f7">
               </td>
             </tr>
           </table></td>
         </tr>
       </table></td>
     </tr>
   </table>
  </div>
 </body>
</html>
