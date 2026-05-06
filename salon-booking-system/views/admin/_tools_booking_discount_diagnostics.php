<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var array|null $discount_diag_report
 * @var string     $discount_diag_error
 * @var int        $discount_diag_booking_id
 */
?>
<div class="sln-tab" id="sln-tab-booking-discount-diagnostics">
	<div class="sln-box sln-box--main">
		<h2 class="sln-box-title"><?php esc_html_e( 'Booking discount diagnostics', 'salon-booking-system' ); ?></h2>
		<p class="help-block">
			<?php esc_html_e( 'Read-only report: compares discount-related post meta, discount catalog, and validation rules for one booking. Use when staff report a missing discount on the Totals tab.', 'salon-booking-system' ); ?>
		</p>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="sln-discount-diag-form" style="margin-bottom: 20px;">
			<input type="hidden" name="page" value="<?php echo esc_attr( SLN_Admin_Tools::PAGE ); ?>" />
			<?php wp_nonce_field( 'sln_booking_discount_diag', '_wpnonce', false ); ?>
			<div class="row">
				<div class="col-xs-12 col-md-4 form-group sln-input--simple">
					<label for="sln_discount_diag_booking"><?php esc_html_e( 'Booking post ID', 'salon-booking-system' ); ?></label>
					<input
						type="number"
						min="1"
						class="form-control"
						id="sln_discount_diag_booking"
						name="sln_discount_diag_booking"
						value="<?php echo $discount_diag_booking_id ? (int) $discount_diag_booking_id : ''; ?>"
						placeholder="<?php esc_attr_e( 'e.g. 84267', 'salon-booking-system' ); ?>"
					/>
				</div>
				<div class="col-xs-12 col-md-4 form-group sln-input--simple" style="padding-top: 24px;">
					<button type="submit" class="sln-btn sln-btn--main sln-btn--big"><?php esc_html_e( 'Run diagnostic', 'salon-booking-system' ); ?></button>
				</div>
			</div>
		</form>

		<?php if ( $discount_diag_error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $discount_diag_error ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $discount_diag_report ) && empty( $discount_diag_report['error'] ) ) : ?>
			<div class="sln-discount-diag-report">
				<p>
					<a class="sln-btn sln-btn--borderonly" href="<?php echo esc_url( $discount_diag_report['booking_edit'] ); ?>">
						<?php esc_html_e( 'Open booking in admin', 'salon-booking-system' ); ?>
					</a>
				</p>
				<h3><?php esc_html_e( 'Booking post', 'salon-booking-system' ); ?></h3>
				<table class="widefat striped" style="max-width: 900px;">
					<tbody>
					<?php foreach ( $discount_diag_report['post'] as $k => $v ) : ?>
						<tr>
							<th style="width: 200px;"><?php echo esc_html( $k ); ?></th>
							<td><?php echo esc_html( (string) $v ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Environment', 'salon-booking-system' ); ?></h3>
				<table class="widefat striped" style="max-width: 900px;">
					<tbody>
					<?php foreach ( $discount_diag_report['environment'] as $k => $v ) : ?>
						<tr>
							<th style="width: 280px;"><?php echo esc_html( str_replace( '_', ' ', $k ) ); ?></th>
							<td><?php echo esc_html( (string) $v ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Stored discount IDs (primary reference)', 'salon-booking-system' ); ?></h3>
				<p><code><?php echo esc_html( empty( $discount_diag_report['discount_ids'] ) ? '—' : implode( ', ', $discount_diag_report['discount_ids'] ) ); ?></code></p>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Discount-related meta (raw)', 'salon-booking-system' ); ?></h3>
				<table class="widefat striped" style="max-width: 900px;">
					<tbody>
					<?php foreach ( $discount_diag_report['related_meta'] as $meta_key => $meta_val ) : ?>
						<tr>
							<th style="width: 280px; vertical-align: top;"><code><?php echo esc_html( $meta_key ); ?></code></th>
							<td><pre style="white-space: pre-wrap; margin: 0; font-size: 12px;"><?php echo esc_html( $meta_val ); ?></pre></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( ! empty( $discount_diag_report['per_discount'] ) ) : ?>
					<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Per discount ID', 'salon-booking-system' ); ?></h3>
					<table class="widefat striped" style="max-width: 900px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'In current catalog', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'Coupon post', 'salon-booking-system' ); ?></th>
								<th><?php esc_html_e( 'validateDiscount() at booking date', 'salon-booking-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $discount_diag_report['per_discount'] as $row ) : ?>
							<tr>
								<td><?php echo (int) $row['id']; ?></td>
								<td><?php echo $row['in_catalog'] ? esc_html__( 'Yes', 'salon-booking-system' ) : esc_html__( 'No', 'salon-booking-system' ); ?></td>
								<td><?php echo esc_html( $row['coupon_post'] ); ?></td>
								<td>
									<?php
									if ( empty( $row['validate_msgs'] ) ) {
										echo esc_html__( 'OK (no errors)', 'salon-booking-system' );
									} else {
										echo esc_html( implode( ' | ', $row['validate_msgs'] ) );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( ! empty( $discount_diag_report['catalog'] ) ) : ?>
					<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Current discount catalog (getAll)', 'salon-booking-system' ); ?></h3>
					<p class="help-block"><?php esc_html_e( 'IDs the booking editor dropdown can list today (may differ from historical bookings if coupons were removed).', 'salon-booking-system' ); ?></p>
					<ul style="columns: 2; max-width: 900px;">
						<?php foreach ( $discount_diag_report['catalog'] as $cid => $ctitle ) : ?>
							<li><code><?php echo (int) $cid; ?></code> — <?php echo esc_html( $ctitle ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Interpretation', 'salon-booking-system' ); ?></h3>
				<ul class="sln-discount-diag-insights" style="max-width: 900px;">
					<?php foreach ( $discount_diag_report['insights'] as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="help-block" style="max-width: 900px;">
					<?php esc_html_e( 'Server logs may contain lines like “[Discount] Skipping invalid discount … (admin path)” when a coupon fails validation on save.', 'salon-booking-system' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>
