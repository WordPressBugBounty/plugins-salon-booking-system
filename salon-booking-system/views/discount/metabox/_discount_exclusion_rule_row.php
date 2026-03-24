<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var string     $prefix
 * @var array      $row
 * @var string|int $rulenumber
 */

if ( empty( $row ) ) {
	$row = array(
		'mode'                 => 'weekdays',
		'days'                 => array(),
		'specific_dates'       => '',
		'always'               => true,
		'from_date'            => null,
		'to_date'              => null,
		'apply_time_range'     => false,
		'from'                 => array( '9:00', '14:00' ),
		'to'                   => array( '13:00', '19:00' ),
		'disable_second_shift' => false,
	);
}

$mode               = isset( $row['mode'] ) ? $row['mode'] : 'weekdays';
$specificDates      = isset( $row['specific_dates'] ) ? $row['specific_dates'] : '';
$always             = isset( $row['always'] ) ? (bool) $row['always'] : true;
$dateFrom           = new SLN_DateTime( isset( $row['from_date'] ) ? $row['from_date'] : null );
$dateTo             = new SLN_DateTime( isset( $row['to_date'] ) ? $row['to_date'] : null );
$applyTimeRange     = isset( $row['apply_time_range'] ) ? (bool) $row['apply_time_range'] : false;
$fromTimes          = isset( $row['from'] ) && is_array( $row['from'] ) ? $row['from'] : array( '9:00', '14:00' );
$toTimes            = isset( $row['to'] ) && is_array( $row['to'] ) ? $row['to'] : array( '13:00', '19:00' );
$disableSecondShift = isset( $row['disable_second_shift'] ) ? (bool) $row['disable_second_shift'] : false;
?>
<div class="col-xs-12 sln-box--sub sln-booking-rule" data-n="<?php echo esc_attr( $rulenumber ); ?>">
	<h2 class="sln-box-title">
		<?php esc_html_e( 'Exclusion rule', 'salon-booking-system' ); ?>
		<strong><?php echo esc_html( $rulenumber ); ?></strong>
	</h2>

	<!-- Mode selector -->
	<div class="row">
		<div class="col-xs-12 col-md-6 form-group sln-select">
			<label><?php esc_html_e( 'Exclude bookings on', 'salon-booking-system' ); ?></label>
			<?php SLN_Form::fieldSelect(
				$prefix . '[mode]',
				array(
					'weekdays'       => __( 'Specific days of the week', 'salon-booking-system' ),
					'specific_dates' => __( 'Specific dates', 'salon-booking-system' ),
				),
				$mode,
				array( 'attrs' => array( 'data-type' => 'discount-exclusion-rule-mode' ) ),
				true
			); ?>
		</div>
	</div>

	<!-- Weekdays mode -->
	<div class="sln-discount-exclusion-mode sln-discount-exclusion-mode--weekdays <?php echo $mode === 'weekdays' ? '' : 'hide'; ?>">
		<div class="sln-title-wrapper">
			<h3 class="sln-box-title--sec">
				<?php esc_html_e( 'Days to exclude', 'salon-booking-system' ); ?>
				<span class="block"><?php esc_html_e( 'Checked days will be excluded from this discount.', 'salon-booking-system' ); ?></span>
			</h3>
		</div>
		<div class="sln-checkbutton-group">
			<?php foreach ( SLN_Func::getDays() as $k => $day ) : ?>
				<div class="sln-checkbutton">
					<?php SLN_Form::fieldCheckboxButton(
						$prefix . "[days][{$k}]",
						( isset( $row['days'][ $k ] ) ? 1 : null ),
						substr( $day, 0, 3 )
					); ?>
				</div>
			<?php endforeach; ?>
			<div class="clearfix"></div>
		</div>
	</div>

	<!-- Specific dates mode -->
	<div class="sln-discount-exclusion-mode sln-discount-exclusion-mode--specific_dates sln-select-specific-dates-calendar <?php echo $mode === 'specific_dates' ? '' : 'hide'; ?>">
		<div class="sln-specific-dates-wrapper">
			<div class="sln-datepicker-section">
				<?php SLN_Form::fieldJSDate( $prefix . '[specific_dates]', '', array( 'inline' => true ) ); ?>
				<?php SLN_Form::fieldText(
					$prefix . '[specific_dates]',
					$specificDates,
					array( 'attrs' => array( 'hidden' => '' ) )
				); ?>
				<div class="sln-selected-dates-panel">
					<div class="sln-selected-dates-header">
						<h4><?php esc_html_e( 'Excluded Dates', 'salon-booking-system' ); ?>:</h4>
						<span class="sln-selected-count">0</span>
					</div>
					<div class="sln-selected-dates-list">
						<div class="sln-selected-dates-empty"><?php esc_html_e( 'No dates selected', 'salon-booking-system' ); ?></div>
					</div>
					<button type="button" class="sln-clear-all-dates sln-btn sln-btn--light sln-btn--small">
						<?php esc_html_e( 'Clear All Dates', 'salon-booking-system' ); ?>
					</button>
				</div>
			</div>
		</div>
		<div class="clearfix"></div>
	</div>

	<!-- Time restriction -->
	<div class="sln-exclusion-time-restriction">
		<div class="col-12 form-group sln-switch">
			<?php SLN_Form::fieldCheckboxSwitch(
				$prefix . '[apply_time_range]',
				$applyTimeRange,
				__( 'All day', 'salon-booking-system' ),
				__( 'Time restriction', 'salon-booking-system' ),
				array(
					'attrs' => array(
						'data-type' => 'exclusion-time-restriction',
					),
				)
			); ?>
		</div>
		<div class="sln-exclusion-time-shifts <?php echo ! $applyTimeRange ? 'hide' : ''; ?>">
			<div class="row">
				<div class="col-xs-12">
					<div class="row">
						<div class="col-xs-12 col-md-6 sln-slider-wrapper sln-slider-wrapper-first-shift">
							<div class="sln-slider">
								<h2 class="sln-box-title"><?php esc_html_e( 'First shift', 'salon-booking-system' ); ?></h2>
								<div class="sln-slider__inner">
									<div class="col col-time">
										<h2 class="sln-slider--title col-time-title">
											<em>
												<strong class="slider-time-from"><?php echo esc_html( $fromTimes[0] ); ?></strong>
												to
												<strong class="slider-time-to"><?php echo esc_html( $toTimes[0] ); ?></strong>
											</em>
										</h2>
										<input type="text" name="<?php echo esc_attr( $prefix ); ?>[from][0]"
										       value="<?php echo esc_attr( $fromTimes[0] ); ?>"
										       class="slider-time-input-from hidden">
										<input type="text" name="<?php echo esc_attr( $prefix ); ?>[to][0]"
										       value="<?php echo esc_attr( $toTimes[0] ); ?>"
										       class="slider-time-input-to hidden">
									</div>
									<div class="sliders_step1 col col-slider">
										<div class="slider-range"></div>
									</div>
									<div class="clearfix"></div>
								</div>
							</div>
						</div>
						<div class="col-xs-12 col-md-6 sln-slider-wrapper sln-slider-wrapper-second-shift">
							<div class="sln-slider sln-second-shift">
								<h2 class="sln-box-title"><?php esc_html_e( 'Second shift', 'salon-booking-system' ); ?></h2>
								<div class="sln-slider__inner" <?php echo $disableSecondShift ? 'hidden' : ''; ?>>
									<div class="col col-time">
										<h2 class="sln-slider--title col-time-title">
											<em>
												<strong class="slider-time-from"><?php echo esc_html( isset( $fromTimes[1] ) ? $fromTimes[1] : '14:00' ); ?></strong>
												to
												<strong class="slider-time-to"><?php echo esc_html( isset( $toTimes[1] ) ? $toTimes[1] : '19:00' ); ?></strong>
											</em>
										</h2>
										<input type="text" name="<?php echo esc_attr( $prefix ); ?>[from][1]"
										       value="<?php echo esc_attr( isset( $fromTimes[1] ) ? $fromTimes[1] : '14:00' ); ?>"
										       class="slider-time-input-from hidden"
										       <?php echo $disableSecondShift ? 'disabled="disabled"' : ''; ?>>
										<input type="text" name="<?php echo esc_attr( $prefix ); ?>[to][1]"
										       value="<?php echo esc_attr( isset( $toTimes[1] ) ? $toTimes[1] : '19:00' ); ?>"
										       class="slider-time-input-to hidden"
										       <?php echo $disableSecondShift ? 'disabled="disabled"' : ''; ?>>
									</div>
									<div class="sliders_step1 col col-slider">
										<div class="slider-range"></div>
									</div>
									<div class="clearfix"></div>
								</div>
							</div>
							<div class="sln-switch sln-switch--inverted sln-switch--bare sln-disable-second-shift">
								<?php SLN_Form::fieldCheckboxSwitch(
									$prefix . '[disable_second_shift]',
									$disableSecondShift,
									__( 'Shift enabled', 'salon-booking-system' ),
									__( 'Shift disabled', 'salon-booking-system' )
								); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Date range scope (visible in weekdays mode only) -->
	<div class="sln-always-valid-section <?php echo $mode === 'specific_dates' ? 'hide' : ''; ?>">
		<div class="col-12 form-group sln-switch">
			<?php SLN_Form::fieldCheckboxSwitch(
				$prefix . '[always]',
				$always,
				__( 'Always apply ( default )', 'salon-booking-system' ),
				__( 'Apply from', 'salon-booking-system' ),
				array(
					'attrs' => array(
						'data-unhide' => '#' . SLN_Form::makeID( $prefix . '[always]' . 'unhide' ),
					),
				)
			); ?>
		</div>
		<div id="<?php echo SLN_Form::makeID( $prefix . '[always]' . 'unhide' ); ?>" class="col-xs-12">
			<div class="row sln-box--tertiary">
				<div class="col-xs-12">
					<h3 class="sln-box-title--sec">
						<?php esc_html_e( 'Set a time range for this exclusion rule', 'salon-booking-system' ); ?>:
					</h3>
				</div>
				<div class="col-xs-12 col-md-4 sln-input--simple sln-input--datepicker">
					<label><?php esc_html_e( 'Apply from', 'salon-booking-system' ); ?></label>
					<?php SLN_Form::fieldJSDate( $prefix . '[from_date]', $dateFrom ); ?>
				</div>
				<div class="col-xs-12 col-md-4 sln-input--simple sln-input--datepicker">
					<label><?php esc_html_e( 'Until', 'salon-booking-system' ); ?></label>
					<div class="sln_datepicker">
						<?php SLN_Form::fieldJSDate( $prefix . '[to_date]', $dateTo ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Actions -->
	<div class="col-xs-12 sln-booking-rules__actions">
		<button class="sln-btn sln-btn--problem sln-btn--big sln-btn--icon sln-icon--trash"
		        type="button" data-collection="remove">
			<?php esc_html_e( 'Remove this rule', 'salon-booking-system' ); ?>
		</button>
	</div>
	<div class="clearfix"></div>
</div>
