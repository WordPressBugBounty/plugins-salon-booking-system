<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var int $bookingId */
/** @var string $attendanceStatus */
/** @var bool $canResolve */
?>
<div class="col-xs-12 col-sm-8 sln-rg-attendance" id="sln-rg-metabox-attendance">
	<label class="sln-rg-attendance__title">
		<?php esc_html_e( 'Attendance', 'salon-booking-system' ); ?>
	</label>

	<?php if ( $canResolve || $attendanceStatus ) : ?>

	<div class="sln-rg-attendance__bar" role="radiogroup" aria-label="<?php esc_attr_e( 'Attendance outcome', 'salon-booking-system' ); ?>">
		<?php foreach ( SLB_RevenueGuard_Enum_AttendanceStatus::resolvedStatuses() as $rg_status ) : ?>
			<label class="sln-rg-attendance__item">
				<input type="radio"
					   name="sln_rg_attendance"
					   value="<?php echo esc_attr( $rg_status ); ?>"
					   <?php checked( $attendanceStatus, $rg_status ); ?>
					   data-booking-id="<?php echo esc_attr( $bookingId ); ?>" />
				<span class="sln-rg-attendance__label"><?php echo esc_html( SLB_RevenueGuard_Enum_AttendanceStatus::getLabel( $rg_status ) ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>

	<p class="sln-rg-attendance__hint">
		<?php esc_html_e( 'Confirm whether the customer attended this appointment.', 'salon-booking-system' ); ?>
	</p>

	<?php else : ?>

	<div class="sln-rg-attendance__bar sln-rg-attendance__bar--disabled">
		<p class="sln-rg-attendance__hint sln-rg-attendance__hint--inline"><?php esc_html_e( 'Attendance can be confirmed after the appointment ends.', 'salon-booking-system' ); ?></p>
	</div>

	<?php endif; ?>
</div>
