 <?php
    // phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
    $additional_fields = SLN_Enum_CheckoutFields::forBooking();

    $_additional_fields = array();

    $customer = $booking->getCustomer();

    foreach ($additional_fields as $key => $field) {

	$value = $field->isCustomer() && $customer  ?  $field->getValue($customer->getId()) : (
                !is_null($booking->getMeta($key))? $booking->getMeta($key)  : ( null !== $field['default_value']  ? $field['default_value'] : '')
            );

	if($field->isHidden() || empty($value) || !$field->isAdditional() ) {
	    continue;
	}

	$_additional_fields[] = array(
	    'label' => esc_html__( sprintf('%s', $field['label']), 'salon-booking-system'),
	    'value' => $value,
		'type' => $field['type'],
	);
    }

?>

<?php if($_additional_fields): ?>
<table class="es-right" cellspacing="0" cellpadding="0" align="right" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;float:right">
    <tr>
    <td align="left" style="padding:0;Margin:0;width:270px">
    <table width="100%" cellspacing="0" cellpadding="0" bgcolor="#f7f8fa" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f7f8fa;border-radius:10px" role="presentation">
        <tr>
        <td align="center" style="padding:0;Margin:0;padding-top:10px;padding-left:25px;padding-right:25px"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:20px;color:#a2b2ce;font-size:13px;text-align:left"><?php esc_html_e('CUSTOMER PREFERENCES', 'salon-booking-system') ?></p></td>
        </tr>
		<?php foreach($_additional_fields as $field):
			if($field['type'] === 'file'){continue;} ?>
        <tr>
        <td align="left" style="Margin:0;padding-top:10px;padding-bottom:10px;padding-left:25px;padding-right:25px"><p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:24px;color:#525252;font-size:16px"><?php echo esc_html__( sprintf('%s', $field['label']), 'salon-booking-system') ?>:&nbsp; <strong><?php echo esc_attr($field['value']) ?></strong></p></td>
        </tr>
		<?php endforeach; ?>
    </table></td>
    </tr>
</table>
<?php endif; ?>
