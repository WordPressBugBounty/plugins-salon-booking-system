<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * High-end only: combined nesting-related settings (see tab_booking.php).
 *
 * @var $plugin SLN_Plugin
 */
$nested_enabled    = $plugin->getSettings()->get('nested_bookings_enabled');
$sequential_strict = $plugin->getSettings()->get('do_not_nest_same_booking_services');
?>
<div id="sln-nesting_logic_options" class="sln-box sln-box--main sln-box--haspanel">
	<h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e('Nesting logic options', 'salon-booking-system'); ?>
		<span><?php esc_html_e('High-end mode: control how breaks interact with other bookings and with multiple services in one booking.', 'salon-booking-system'); ?></span>
	</h2>
	<div class="collapse sln-box__panelcollapse">
		<div class="row sln-moremargin--bottom">
			<div class="col-xs-12">
				<h3 class="sln-fake-label sln-moremargin--bottom"><?php esc_html_e('Other customers (nested bookings)', 'salon-booking-system'); ?></h3>
			</div>
			<div class="col-xs-12 col-sm-6 sln-profeature <?php echo ! defined('SLN_VERSION_PAY') ? 'sln-profeature--disabled sln-profeature__tooltip-wrapper' : ''; ?>">
				<?php
				echo $plugin->loadView(
					'metabox/_pro_feature_tooltip',
					array(
						'trigger'              => 'sln-nested_bookings',
						'additional_classes'   => 'sln-profeature--box',
					)
				);
				?>
				<div class="sln-switch sln-moremargin--bottom <?php echo ! defined('SLN_VERSION_PAY') ? 'sln-disabled' : ''; ?>">
					<h6 class="sln-fake-label"><?php esc_html_e('Nested bookings using service break', 'salon-booking-system'); ?></h6>
					<?php
					SLN_Form::fieldCheckboxSwitch(
						'salon_settings[nested_bookings_enabled]',
						defined('SLN_VERSION_PAY') ? $nested_enabled : 0,
						__('Nested bookings ON', 'salon-booking-system'),
						__('Nested bookings OFF', 'salon-booking-system')
					);
					?>
				</div>
			</div>
			<div class="col-xs-12 col-sm-6 form-group sln-box-maininfo">
				<p class="sln-box-info">
					<?php esc_html_e('When enabled, customers can book services that start during another service\'s break period. This applies to all services that have a break time configured.', 'salon-booking-system'); ?>
				</p>
				<p class="sln-box-info">
					<strong><?php esc_html_e('Example:', 'salon-booking-system'); ?></strong><br>
					<?php esc_html_e('Service A: 16:00-18:00 with break at 17:00-17:30. With nested bookings enabled, Service B can start at 17:00 or 17:10, etc.', 'salon-booking-system'); ?>
				</p>
			</div>
		</div>

		<div class="row">
			<div class="col-xs-12">
				<hr class="sln-moremargin--bottom">
				<h3 class="sln-fake-label sln-moremargin--bottom"><?php esc_html_e('Same booking (multiple services)', 'salon-booking-system'); ?></h3>
			</div>
			<div class="col-xs-12 col-sm-6 sln-switch sln-moremargin--bottom">
				<h6 class="sln-fake-label"><?php esc_html_e('Do not nest same booking services', 'salon-booking-system'); ?></h6>
				<?php
				SLN_Form::fieldCheckboxSwitch(
					'salon_settings[do_not_nest_same_booking_services]',
					$sequential_strict,
					__('Do not nest same booking services ON', 'salon-booking-system'),
					__('Do not nest same booking services OFF', 'salon-booking-system')
				);
				?>
			</div>
			<div class="col-xs-12 col-sm-6 form-group sln-box-maininfo">
				<p class="sln-box-info">
					<?php esc_html_e('When ON, each service in the same booking starts only after the previous service’s full duration and break. A custom break position will no longer move the next service to start during the previous service’s break.', 'salon-booking-system'); ?>
				</p>
				<p class="sln-box-info">
					<?php esc_html_e('Use parallel execution on a service if two lines must share the same start time.', 'salon-booking-system'); ?>
				</p>
			</div>
		</div>
	</div>
</div>
