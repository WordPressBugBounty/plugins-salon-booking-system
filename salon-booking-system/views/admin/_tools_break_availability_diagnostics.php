<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var array|null $break_av_diag_report
 * @var string     $break_av_diag_error
 * @var int        $break_av_diag_booking_a
 * @var int        $break_av_diag_booking_b
 */
$export = ! empty( $break_av_diag_report ) && empty( $break_av_diag_report['error'] )
	? wp_json_encode( $break_av_diag_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
	: '';
?>
<div class="sln-tab" id="sln-tab-break-availability-diagnostics">
	<div class="sln-box sln-box--main">
		<h2 class="sln-box-title"><?php esc_html_e( 'Break & availability diagnostics', 'salon-booking-system' ); ?></h2>
		<p class="help-block">
			<?php esc_html_e( 'Read-only report for nested breaks, timeslot grid (5-minute steps), and attendant validation. Use when investigating overlapping bookings or “free” slots during a service break. Copy the JSON export for support or AI analysis.', 'salon-booking-system' ); ?>
		</p>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="sln-break-av-diag-form" style="margin-bottom: 20px;">
			<input type="hidden" name="page" value="<?php echo esc_attr( SLN_Admin_Tools::PAGE ); ?>" />
			<?php wp_nonce_field( 'sln_break_availability_diag', '_wpnonce', false ); ?>
			<div class="row">
				<div class="col-xs-12 col-md-4 form-group sln-input--simple">
					<label for="sln_break_diag_booking_a"><?php esc_html_e( 'Primary booking ID', 'salon-booking-system' ); ?></label>
					<input
						type="number"
						min="1"
						class="form-control"
						id="sln_break_diag_booking_a"
						name="sln_break_diag_booking_a"
						value="<?php echo $break_av_diag_booking_a ? (int) $break_av_diag_booking_a : ''; ?>"
						placeholder="<?php esc_attr_e( 'e.g. 757', 'salon-booking-system' ); ?>"
					/>
					<p class="help-block"><?php esc_html_e( 'Usually the long service with a break (defines the timeslot window ±30 min).', 'salon-booking-system' ); ?></p>
				</div>
				<div class="col-xs-12 col-md-4 form-group sln-input--simple">
					<label for="sln_break_diag_booking_b"><?php esc_html_e( 'Secondary booking ID (optional)', 'salon-booking-system' ); ?></label>
					<input
						type="number"
						min="0"
						class="form-control"
						id="sln_break_diag_booking_b"
						name="sln_break_diag_booking_b"
						value="<?php echo $break_av_diag_booking_b ? (int) $break_av_diag_booking_b : ''; ?>"
						placeholder="<?php esc_attr_e( 'e.g. 826', 'salon-booking-system' ); ?>"
					/>
					<p class="help-block"><?php esc_html_e( 'If set, runs validateBookingAttendant for each line of this booking (booking excluded from grid).', 'salon-booking-system' ); ?></p>
				</div>
				<div class="col-xs-12 col-md-4 form-group sln-input--simple" style="padding-top: 24px;">
					<button type="submit" class="sln-btn sln-btn--main sln-btn--big"><?php esc_html_e( 'Run diagnostic', 'salon-booking-system' ); ?></button>
				</div>
			</div>
		</form>

		<?php if ( $break_av_diag_error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $break_av_diag_error ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $break_av_diag_report ) && empty( $break_av_diag_report['error'] ) ) : ?>
			<div class="sln-break-av-diag-report">
				<?php if ( ! empty( $break_av_diag_report['booking_a']['edit_url'] ) ) : ?>
					<p>
						<a class="sln-btn sln-btn--borderonly" href="<?php echo esc_url( $break_av_diag_report['booking_a']['edit_url'] ); ?>">
							<?php esc_html_e( 'Open primary booking', 'salon-booking-system' ); ?>
						</a>
						<?php if ( ! empty( $break_av_diag_report['booking_b']['edit_url'] ) ) : ?>
							<a class="sln-btn sln-btn--borderonly" href="<?php echo esc_url( $break_av_diag_report['booking_b']['edit_url'] ); ?>">
								<?php esc_html_e( 'Open secondary booking', 'salon-booking-system' ); ?>
							</a>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<h3><?php esc_html_e( 'Environment', 'salon-booking-system' ); ?></h3>
				<table class="widefat striped" style="max-width: 960px;">
					<tbody>
					<?php foreach ( $break_av_diag_report['environment'] as $k => $v ) : ?>
						<tr>
							<th style="width: 320px;"><?php echo esc_html( str_replace( '_', ' ', $k ) ); ?></th>
							<td><?php echo esc_html( (string) $v ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Primary booking summary', 'salon-booking-system' ); ?></h3>
				<table class="widefat striped" style="max-width: 960px;">
					<tbody>
					<?php foreach ( $break_av_diag_report['booking_a'] as $k => $v ) : ?>
						<tr>
							<th style="width: 200px;"><?php echo esc_html( $k ); ?></th>
							<td><?php echo esc_html( (string) $v ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Primary booking — service lines', 'salon-booking-system' ); ?></h3>
				<p class="help-block"><?php esc_html_e( 'Shows stored meta, computed break start/end per line, and attendant.', 'salon-booking-system' ); ?></p>
				<?php foreach ( $break_av_diag_report['booking_a_lines'] as $i => $line ) : ?>
					<h4><?php echo esc_html( sprintf( /* translators: %d: line index */ __( 'Line %d', 'salon-booking-system' ), (int) $i ) ); ?></h4>
					<table class="widefat striped" style="max-width: 960px;">
						<tbody>
						<?php foreach ( $line as $lk => $lv ) : ?>
							<tr>
								<th style="width: 260px; vertical-align: top;"><code><?php echo esc_html( $lk ); ?></code></th>
								<td><pre style="white-space: pre-wrap; margin: 0; font-size: 12px;"><?php echo esc_html( is_scalar( $lv ) ? (string) $lv : wp_json_encode( $lv, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>

				<?php if ( ! empty( $break_av_diag_report['booking_b'] ) ) : ?>
					<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Secondary booking summary', 'salon-booking-system' ); ?></h3>
					<table class="widefat striped" style="max-width: 960px;">
						<tbody>
						<?php foreach ( $break_av_diag_report['booking_b'] as $k => $v ) : ?>
							<tr>
								<th style="width: 200px;"><?php echo esc_html( $k ); ?></th>
								<td><?php echo esc_html( (string) $v ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Secondary booking — service lines', 'salon-booking-system' ); ?></h3>
					<?php foreach ( $break_av_diag_report['booking_b_lines'] as $i => $line ) : ?>
						<h4><?php echo esc_html( sprintf( __( 'Line %d', 'salon-booking-system' ), (int) $i ) ); ?></h4>
						<table class="widefat striped" style="max-width: 960px;">
							<tbody>
							<?php foreach ( $line as $lk => $lv ) : ?>
								<tr>
									<th style="width: 260px; vertical-align: top;"><code><?php echo esc_html( $lk ); ?></code></th>
									<td><pre style="white-space: pre-wrap; margin: 0; font-size: 12px;"><?php echo esc_html( is_scalar( $lv ) ? (string) $lv : wp_json_encode( $lv, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endforeach; ?>
				<?php endif; ?>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Timeslot slice (primary window ±30 min)', 'salon-booking-system' ); ?></h3>
				<p class="help-block"><?php esc_html_e( 'Grid from DayBookings with no booking excluded. is_break=yes means nested-break slot. attendant_N = busy count for that attendant id.', 'salon-booking-system' ); ?></p>
				<div style="max-height: 420px; overflow: auto; border: 1px solid #ccd0d4;">
					<table class="widefat striped" style="margin: 0;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'is_break', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'bookings_h', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'Attendants (subset)', 'salon-booking-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $break_av_diag_report['timeslot_slice'] as $row ) : ?>
							<tr>
								<td><code><?php echo esc_html( $row['time'] ); ?></code></td>
								<td><?php echo esc_html( $row['is_break'] ); ?></td>
								<td><?php echo esc_html( (string) $row['bookings_h'] ); ?></td>
								<td><code style="font-size: 11px;"><?php echo esc_html( wp_json_encode( isset( $row['attendants'] ) ? $row['attendants'] : array() ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<h3 style="margin-top: 1.5em;"><?php echo esc_html( $break_av_diag_report['validation_rebooking']['label'] ); ?></h3>
				<table class="widefat striped" style="max-width: 960px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Service ID', 'salon-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Validation errors', 'salon-booking-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $break_av_diag_report['validation_rebooking']['lines'] as $row ) : ?>
						<tr>
							<td><?php echo (int) $row['service_id']; ?></td>
							<td><?php echo empty( $row['errors'] ) ? esc_html__( 'None (OK)', 'salon-booking-system' ) : esc_html( implode( ' | ', $row['errors'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( ! empty( $break_av_diag_report['validation_booking_b']['lines'] ) ) : ?>
					<h3 style="margin-top: 1.5em;"><?php echo esc_html( $break_av_diag_report['validation_booking_b']['label'] ); ?></h3>
					<table class="widefat striped" style="max-width: 960px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Service ID', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'Starts', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'Validation errors', 'salon-booking-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $break_av_diag_report['validation_booking_b']['lines'] as $row ) : ?>
							<tr>
								<td><?php echo (int) $row['service_id']; ?></td>
								<td><?php echo esc_html( isset( $row['starts'] ) ? $row['starts'] : '' ); ?></td>
								<td><?php echo empty( $row['errors'] ) ? esc_html__( 'None (OK)', 'salon-booking-system' ) : esc_html( implode( ' | ', $row['errors'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Heuristics', 'salon-booking-system' ); ?></h3>
				<ul style="max-width: 960px;">
					<?php foreach ( $break_av_diag_report['insights'] as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Full JSON export', 'salon-booking-system' ); ?></h3>
				<p class="help-block"><?php esc_html_e( 'Includes raw attendant counts per slot. Paste into a ticket or chat.', 'salon-booking-system' ); ?></p>
				<textarea readonly rows="16" class="large-text code" style="width: 100%; max-width: 960px; font-size: 11px;"><?php echo esc_textarea( $export ); ?></textarea>
			</div>
		<?php endif; ?>
	</div>
</div>
