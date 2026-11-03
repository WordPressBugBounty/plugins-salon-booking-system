<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$staff_repair_request = SLN_Admin_StaffBookingsRepair::handleRequest();
$staff_repair_report  = $staff_repair_request['report'];
$staff_repair_done    = $staff_repair_request['done'];
$staff_repair_error   = $staff_repair_request['error'];
$staff_repair_fixable = array();
$staff_repair_review  = array();
if ( $staff_repair_report ) {
	foreach ( $staff_repair_report['rows'] as $staff_repair_row ) {
		if ( SLN_Admin_StaffBookingsRepair::ACTION_REVIEW === $staff_repair_row['action'] ) {
			$staff_repair_review[] = $staff_repair_row;
		} else {
			$staff_repair_fixable[] = $staff_repair_row;
		}
	}
}
?>
<div class="sln-tab" id="sln-tab-staff-bookings-repair">
	<div class="sln-box sln-box--main">
		<h2 class="sln-box-title"><?php esc_html_e( 'Bookings assigned to staff', 'salon-booking-system' ); ?></h2>
		<p class="help-block">
			<?php esc_html_e( 'Finds bookings linked to an administrator or staff account instead of their customer. Nothing changes until you confirm, no notification is sent, and WordPress profiles are not restored: check overwritten staff names, phones and emails under Users.', 'salon-booking-system' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . SLN_Admin_Tools::PAGE . '#sln-tab-staff-bookings-repair' ) ); ?>" style="margin-bottom: 20px;">
			<?php wp_nonce_field( SLN_Admin_StaffBookingsRepair::NONCE_ACTION, 'sln_staff_repair_nonce' ); ?>
			<button type="submit" name="sln_staff_repair_scan" value="1" class="sln-btn sln-btn--main sln-btn--big"><?php esc_html_e( 'Scan bookings', 'salon-booking-system' ); ?></button>
		</form>

		<?php if ( $staff_repair_error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $staff_repair_error ); ?></p></div>
		<?php endif; ?>

		<?php if ( null !== $staff_repair_done ) : ?>
			<div class="notice notice-success">
				<p><?php echo esc_html( sprintf( /* translators: %d: number of bookings */ _n( '%d booking repaired.', '%d bookings repaired.', $staff_repair_done, 'salon-booking-system' ), $staff_repair_done ) ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $staff_repair_report ) : ?>
			<?php if ( ! $staff_repair_report['total'] ) : ?>
				<p><?php esc_html_e( 'No bookings to repair.', 'salon-booking-system' ); ?></p>
			<?php elseif ( $staff_repair_report['total'] > count( $staff_repair_report['rows'] ) ) : ?>
				<p>
					<?php echo esc_html( sprintf( /* translators: 1: rows shown, 2: total rows */ __( 'Showing the latest %1$d of %2$d bookings. Repair these and scan again to see the rest.', 'salon-booking-system' ), count( $staff_repair_report['rows'] ), $staff_repair_report['total'] ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $staff_repair_fixable ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . SLN_Admin_Tools::PAGE . '#sln-tab-staff-bookings-repair' ) ); ?>">
					<?php wp_nonce_field( SLN_Admin_StaffBookingsRepair::NONCE_ACTION, 'sln_staff_repair_nonce' ); ?>
					<h3><?php esc_html_e( 'Ready to repair', 'salon-booking-system' ); ?></h3>
					<table class="widefat striped" style="max-width: 1100px;">
						<thead>
							<tr>
								<th></th>
								<th><?php esc_html_e( 'Booking', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'Date', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'Customer on the booking', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'Assigned to', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'Will become', 'salon-booking-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $staff_repair_fixable as $staff_repair_row ) : ?>
							<tr>
								<td><input type="checkbox" name="sln_staff_repair_ids[]" value="<?php echo (int) $staff_repair_row['id']; ?>" checked /></td>
								<td><a href="<?php echo esc_url( get_edit_post_link( $staff_repair_row['id'] ) ); ?>">#<?php echo (int) $staff_repair_row['id']; ?></a></td>
								<td><?php echo esc_html( $staff_repair_row['date'] ); ?></td>
								<td><?php echo esc_html( $staff_repair_row['name'] ); ?><br /><?php echo esc_html( $staff_repair_row['email'] ); ?></td>
								<td><?php echo esc_html( $staff_repair_row['staff_name'] ); ?></td>
								<td>
									<?php
									echo esc_html(
										SLN_Admin_StaffBookingsRepair::ACTION_REASSIGN === $staff_repair_row['action']
											? sprintf( /* translators: 1: customer name, 2: customer ID */ __( 'Customer %1$s (#%2$d)', 'salon-booking-system' ), $staff_repair_row['customer'], $staff_repair_row['customer_id'] )
											: __( 'Guest (no customer account)', 'salon-booking-system' )
									);
									?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="submit" name="sln_staff_repair_apply" value="1" class="sln-btn sln-btn--main sln-btn--big"><?php esc_html_e( 'Repair selected bookings', 'salon-booking-system' ); ?></button>
					</p>
				</form>
			<?php endif; ?>

			<?php if ( $staff_repair_review ) : ?>
				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Needs your review', 'salon-booking-system' ); ?></h3>
				<p class="help-block">
					<?php esc_html_e( 'These bookings show a customer name with a staff email or no email, so the customer\'s contact details were never saved and notifications may have reached staff. Open each one and add the customer.', 'salon-booking-system' ); ?>
				</p>
				<table class="widefat striped" style="max-width: 1100px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Booking', 'salon-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Date', 'salon-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Name on the booking', 'salon-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Email on the booking', 'salon-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Staff account', 'salon-booking-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $staff_repair_review as $staff_repair_row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $staff_repair_row['id'] ) ); ?>">#<?php echo (int) $staff_repair_row['id']; ?></a></td>
							<td><?php echo esc_html( $staff_repair_row['date'] ); ?></td>
							<td><?php echo esc_html( $staff_repair_row['name'] ); ?></td>
							<td><?php echo esc_html( $staff_repair_row['email'] ); ?></td>
							<td><?php echo esc_html( $staff_repair_row['staff_name'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
