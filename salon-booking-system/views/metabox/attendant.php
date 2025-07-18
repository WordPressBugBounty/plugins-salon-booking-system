<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$helper->showNonce($postType);
/** @var SLN_Repository_ServiceRepository $sRepo */
$sRepo = $plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
$services = $sRepo->getAll();

usort($services, function ($service1, $service2) {

	$service1Title = strtolower($service1->getName());
	$service2Title = strtolower($service2->getName());

	if ($service1Title === $service2Title) {
		return 0;
	}

	return $service1Title > $service2Title ? 1 : -1;
});

?>

<div class="sln-box sln-box--main sln-box--haspanel sln-box--haspanel--open <?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
	<h2 class="sln-box-title sln-box__paneltitle sln-box__paneltitle--open"><?php esc_html_e('Assistant Details', 'salon-booking-system');?></h2>
<div class="collapse in sln-box__panelcollapse">
<div class="row sln-service-price-time">
    <div class="col-xs-12 col-sm-6 col-md-3 form-group sln-input--simple sln-attendant-email-block">
            <label for="_sln_attendant_email"><?php echo esc_html__('E-mail', 'salon-booking-system') ?></label>
            <select name="_sln_attendant_email" id="_sln_attendant_email" data-nomatches="<?php esc_html_e('no users found', 'salon-booking-system')?>" data-placeholder="<?php esc_html_e('Start typing the email', 'salon-booking-system')?>" class="form-control">
		<option selected="selected" value="<?php echo $attendant->getEmail() ?>" data-staff-member-id="<?php echo $attendant->getMeta('staff_member_id') ?>"><?php echo $attendant->getEmail() ?></option>
	    </select>
	    <input type="hidden" name="_sln_attendant_staff_member_id" value="<?php echo $attendant->getMeta('staff_member_id') ?>">
    </div>
    <div class="col-xs-12 col-sm-6 col-md-3 form-group sln-select">
            <label for="_sln_attendant_phone"><?php echo esc_html__('Phone', 'salon-booking-system') ?></label>
            <input type="tel" name="_sln_attendant_phone" id="_sln_attendant_phone" value="<?php echo $attendant->getPhone() ?>" class="form-control">
            <input type="hidden" name="_sln_attendant_sms_prefix" id="_sln_attendant_sms_prefix" value="<?php echo $attendant->getSmsPrefix() ?>" class="form-control">
    </div>

    <div class="col-xs-12 col-md-6 form-group sln-checkbox">
        <?php SLN_Form::fieldCheckbox('_sln_attendant_display_phone_inside_booking_notification', $attendant->isDisplayPhoneInsideBookingNotification(), array())?>
        <label for="_sln_attendant_display_phone_inside_booking_notification"><?php esc_html_e('Display phone inside booking notification', 'salon-booking-system');?></label>
    </div>
</div>
<div class="row sln-service-price-time">
    <div class="col-xs-12 col-md-6 form-group sln-select sln-select--multiple sln-select2-selection__search-primary">
            <label><?php echo esc_html__('Limit reservations to the following services', 'salon-booking-system') ?></label>
            <div class="sln_attendant_services_list closed-">
            <select class="sln-select select2-hidden-accessible" multiple="multiple" data-placeholder="<?php esc_html_e('Select or search one or more services', 'salon-booking-system')?>"
                    name="_sln_attendant_services[]" id="_sln_attendant_services" tabindex="-1" aria-hidden="true">
                <?php foreach ($services as $service): ?>
                    <?php if (!$service->isAttendantsEnabled()) {
	continue;
}
?>
                    <option
                        class="red"
                        value="sln_attendant_services_<?php echo $service->getId() ?>"
                        data-price="<?php echo $service->getPrice(); ?>"
                        <?php echo $attendant->hasService($service) ? 'selected="selected"' : '' ?>
                        ><?php echo $service->getName(); ?>
                        (<?php echo $plugin->format()->money($service->getPrice()) ?>)
                    </option>
                <?php endforeach?>
            </select>
            </div>
            <a href="#nogo" class="sln-service__collapsetrigger"><span class="sr-only">more</span></a>
            <p><?php echo esc_html__('Use this option only if this assistant is able to provide specific services. If not leave it blank', 'salon-booking-system') ?></p>
    </div>
    <div class="col-xs-12 col-md-6 form-group sln-checkbox">
        <?php SLN_Form::fieldCheckbox('_sln_attendant_multiple_customers', $attendant->canMultipleCustomers(), array())?>
        <label for="_sln_attendant_multiple_customers"><?php esc_html_e('Multiple Customers per Session', 'salon-booking-system');?></label>
    </div>
</div>
<div class="row sln-service-price-time">
    <div class="col-xs-12 col-md-6 form-group sln-checkbox sln-staff-member-assigned-bookings-only <?php echo $attendant->getMeta('staff_member_id') ? '' : 'hide' ?>">
	<?php if (defined("SLN_VERSION_PAY")): ?>
	    <div>
		<?php SLN_Form::fieldCheckbox('_sln_attendant_limit_staff_member_to_assigned_bookings_only', $attendant->getIsStaffMemberAssignedToBookingsOnly(), array())?>
		<label for="_sln_attendant_limit_staff_member_to_assigned_bookings_only"><?php esc_html_e('Limit access to assigned bookings only', 'salon-booking-system');?></label>
	    </div>
	<?php else: ?>
	   <div class="sln-staff-member-assigned-bookings-only--alert">
        <p>
	       <span class="icon"></span>
		<?php
        // phpcs:ignoreFile WordPress.Security.EscapeOutput.UnsafePrintingFunction
        _e('In the <strong>Pro</strong> version you could limit this assistant to view and manage only his own reservations.', 'salon-booking-system')?>
		<a href="https://bit.ly/3wanK8V" target="_blank">
		    <strong><?php esc_html_e('Ok, I need this.', 'salon-booking-system')?></strong>
		</a>
    </p>
	    </div>
	<?php endif;?>
    </div>
</div>
<div class="row sln-service-price-time">
    <div class="col-xs-12 col-md-6 form-group sln-checkbox sln-staff-member-backend-calendar-only <?php echo $attendant->getMeta('staff_member_id') ? '' : 'hide' ?>">
        <div>
            <?php SLN_Form::fieldCheckbox('_sln_attendant_limit_staff_member_to_backend_calendar_only', $attendant->getIsStaffMemberToBackendCalendarOnly(), array())?>
            <label for="_sln_attendant_limit_staff_member_to_backend_calendar_only"><?php esc_html_e('Limit access to back-end calendar only', 'salon-booking-system');?></label>
        </div>
    </div>
</div>
<!-- collapse END -->
</div>
</div>

<div class="booking-wrapper">
    <?php echo $plugin->loadView(
        'metabox/_tab_attendant_rules',
        array(
            'availabilities' => $attendant->getMeta('availabilities'),
            'base' => '_sln_attendant_availabilities',
            'show_specific_dates' => true,
            'attendant' => $attendant
        )
    ); ?>
    <?php echo $plugin->loadView(
        'settings/_availability_preview',
        array(
            'availabilities' => $attendant->getMeta('availabilities'),
            'base' => '_sln_attendant_availabilities',
        )
    ); ?>
</div>
<?php echo $plugin->loadView(
    'settings/_tab_booking_holiday_rules',
    array(
        'holidays' => $attendant->getMeta('holidays'),
        'base' => '_sln_attendant_holidays',
    )
); ?>

<div class="sln-clear"></div>
<?php do_action('sln.template.attendant.metabox', $attendant);?>
