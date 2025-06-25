<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$shopPrices = !$plugin->getSettings()->isHidePrices();
$depositText = ($booking->getDeposit() && $booking->hasStatus(SLN_Enum_BookingStatus::PAID)) ?
    $plugin->format()->moneyFormatted($booking->getDeposit(), false, false, true) : null;

$bookingDateTime = empty($forAdmin) && $plugin->getSettings()->isDisplaySlotsCustomerTimezone() && $booking->getCustomerTimezone() ? (new SLN_DateTime($booking->getDate()->format('Y-m-d') . ' ' . $booking->getTime()->format('H:i')))->setTimezone(new DateTimeZone($booking->getCustomerTimezone())) : new SLN_DateTime($booking->getDate()->format('Y-m-d') . ' ' . $booking->getTime()->format('H:i'));
?>

<table cellpadding="0" cellspacing="0" width="100%" bgcolor="#F2F6FD" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f2f6fd;border-radius:10px" role="presentation">
	<tr><td align="left" style="padding:25px 25px 0px 25px;Margin:0">
		<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px;text-transform: uppercase;">
		<strong><?php esc_html_e('Status', 'salon-booking-system') ?></strong></p>
	</td></tr>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:45px;color:#333333;font-size:30px">
				<strong><?php echo SLN_Enum_BookingStatus::getLabel($booking->getStatus()); ?></strong>
			</p>
		</td>
	</tr>
	<tr>
		<td align="center" style="Margin:0;padding-top:5px;padding-bottom:20px;padding-left:20px;padding-right:20px;font-size:0">
		<p style="padding:0;Margin:0;border-bottom:1px solid #c6d1e5;background:unset;height:1px;width:100%;margin:0px"></p>
		</td>
	</tr>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px;text-transform: uppercase;">
			<b><?php esc_html_e('BOOKING ID', 'salon-booking-system') ?></b>
			</p>
		</td>
	</tr>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:38px;color:#333333;font-size:25px"><?php echo esc_attr($booking->getId()); ?></p>
		</td>
	</tr>
	<tr>
		<td align="center" style="Margin:0;padding-top:5px;padding-bottom:20px;padding-left:20px;padding-right:20px;font-size:0">
		<p style="padding:0;Margin:0;border-bottom:1px solid #c6d1e5;background:unset;height:1px;width:100%;margin:0px"></p>
		</td>
	</tr>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px;text-transform:uppercase;">
				<strong><?php esc_html_e('DATE & TIME', 'salon-booking-system');?>&nbsp;</strong>
			</p>
		</td>
	</tr>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:38px;color:#333333;font-size:25px">
			<?php echo $plugin->format()->date($bookingDateTime); ?>
			</p>
		</td>
	</tr>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:38px;color:#333333;font-size:25px">
				<?php echo $plugin->format()->time($bookingDateTime); ?> <?php
				if($booking->getDuration() && !$plugin->getSettings()->get('hide_service_duration')):?><span style="font-size:18px">(<?php echo $plugin->format()->duration($booking->getDuration()); ?>)</span>
				<?php endif ?>
			</p>
		</td>
	</tr>
	<tr>
		<td align="center" style="Margin:0;padding-top:5px;padding-bottom:20px;padding-left:20px;padding-right:20px;font-size:0">
		<p style="padding:0;Margin:0;border-bottom:1px solid #c6d1e5;background:unset;height:1px;width:100%;margin:0px"></p>
		</td>
	</tr>
	<tr>
		<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
			<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px;text-transform:uppercase;">
				<strong><?php if ($plugin->getSettings()->isAttendantsEnabled()): ?><?php esc_html_e('SERVICES & ASSISTANTS', 'salon-booking-system') ?><?php else: ?><?php esc_html_e('SERVICES', 'salon-booking-system') ?><?php endif ?></strong>
			</p>
		</td>
	</tr>
	<?php foreach($booking->getBookingServices()->getItems() as $bookingService): ?>
		<tr >
			<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
				<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:38px;color:#333333;font-size:25px"><?php if($bookingService->getService()->getServiceCategory()){
					echo esc_html__(sprintf('%s', $bookingService->getService()->getServiceCategory()->getName()), 'salon-booking-system'), ' / ';
				}
				esc_html_e(sprintf('%s', $bookingService->getService()->getName()), 'salon-booking-system');
				$attendant = $bookingService->getAttendant(); ?></p>
			</td>
		</tr>
		<?php if($attendant): ?>
			<tr>
				<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
					<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:27px;color:#333333;font-size:18px"><?php
					if(!is_array($attendant)){
						esc_html_e(sprintf('%s', $attendant->getName()));
						if($attendant->isDisplayPhoneInsideBookingNotification()){
							echo ' (' . esc_attr($attendant->getPhone()) . ') ';
						}
					}else{
						foreach($attendant as $att){
							echo esc_html__(sprintf('%s', $att->getName())), ' ';
							if($att->isDisplayPhoneInsideBookingNotification()){
								echo ' (' . $att->getPhone() . ') ';
							}
						}
					} ?></p>
				</td>
			</tr>
		<?php endif;
		do_action('sln.mail.service_details', $bookingService->getService());
		endforeach; ?>
        <tr>
            <td align="center" style="Margin:0;padding-top:5px;padding-bottom:20px;padding-left:20px;padding-right:20px;font-size:0">
                <table border="0" width="100%" height="100%" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px">
                    <tbody>
                        <tr>
                            <td style="padding:0;Margin:0;border-bottom:1px solid #c6d1e5;background:unset;height:1px;width:100%;margin:0px"></td>
                        </tr>
                    </tbody>
                </table>
            </td>
       </tr>
	<?php if($shopPrices): ?>
		<tr>
			<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
				<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px;text-transform: uppercase;">
					<strong><?php esc_html_e('TOTAL AMOUNT', 'salon-booking-system') ?></strong>
				</p>
			</td>
		</tr>
		<tr>
			<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
				<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:45px;color:#333333;font-size:30px">
					<strong><?php echo $plugin->format()->moneyFormatted($booking->getAmount(), true, false, true) ?></strong>
				</p>
			</td>
		</tr>
	<?php endif; ?>
	<?php if($depositText): ?>
		<tr>
			<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
				<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px;text-transform: uppercase;">
					<strong><?php esc_html_e('Already paid', 'salon-booking-system') ?></strong>
				</p>
			</td>
		</tr>
		<tr>
			<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
				<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:45px;color:#333333;font-size:30px">
					<?php echo esc_attr($depositText); ?>
				</p>
			</td>
		</tr>
	<?php endif; ?>
	<?php do_action('sln.mail.summary_details', $booking); ?>
	<?php if($plugin->getSettings()->isTipRequestEnabled() && $booking->getTips()): ?>
		<tr>
			<td align="left" style="padding:0;Margin:0;padding-top:5px;padding-left:25px;padding-right:25px">
				<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:21px;color:#a2b2ce;font-size:14px">
					<strong><?php esc_html_e('TIP', 'salon-booking-system') ?></strong>
				</p>
			</td>
		</tr>
		<tr>
			<td align="left" style="padding:0;Margin:0;padding-left:25px;padding-right:25px">
				<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:45px;color:#333333;font-size:30px"><?php
				echo $plugin->format()->moneyFormatted($booking->getTips(), false, false, true) ?></p>
			</td>
		</tr>
	<?php endif; ?>
	<tr>
		<td align="center" style="Margin:0;padding-top:5px;padding-bottom:20px;padding-left:20px;padding-right:20px;font-size:0">
		<p style="padding:0;Margin:0;border-bottom:0px solid #cccccc;background:unset;height:1px;width:100%;margin:0px"></p>
		</td>
	</tr>
</table>