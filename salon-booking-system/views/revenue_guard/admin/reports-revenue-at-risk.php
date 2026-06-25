<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var bool $can_view_money */
?>
<div class="sln-rg-reports-section" id="sln-rg-reports-section">
	<div class="sln-rg-reports-header">
		<h2><?php esc_html_e( 'Revenue at Risk', 'salon-booking-system' ); ?></h2>
		<p class="sln-section-description">
			<?php esc_html_e( 'Lost revenue from no-shows and partial appointments. Confirm attendance to unlock accurate metrics.', 'salon-booking-system' ); ?>
		</p>
	</div>

	<div class="sln-rg-reports-kpis">
		<div class="sln-rg-kpi">
			<div class="sln-rg-kpi__label"><?php esc_html_e( 'Unresolved', 'salon-booking-system' ); ?></div>
			<div class="sln-rg-kpi__value" id="rg-unresolved-count">--</div>
		</div>
		<div class="sln-rg-kpi">
			<div class="sln-rg-kpi__label"><?php esc_html_e( 'Coverage', 'salon-booking-system' ); ?></div>
			<div class="sln-rg-kpi__value" id="rg-coverage-rate">--</div>
		</div>
		<div class="sln-rg-kpi <?php echo $can_view_money ? '' : 'sln-rg-kpi--locked'; ?>">
			<div class="sln-rg-kpi__label"><?php esc_html_e( 'Lost Revenue', 'salon-booking-system' ); ?></div>
			<div class="sln-rg-kpi__value" id="rg-lost-revenue">--</div>
			<?php if ( ! $can_view_money ) : ?>
				<p class="sln-rg-kpi__hint">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=salon-extensions' ) ); ?>">
						<?php esc_html_e( 'Upgrade to see € impact', 'salon-booking-system' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<div class="sln-rg-kpi">
			<div class="sln-rg-kpi__label"><?php esc_html_e( 'No-show Rate', 'salon-booking-system' ); ?></div>
			<div class="sln-rg-kpi__value" id="rg-no-show-rate">--</div>
		</div>
	</div>

	<div id="rg-coverage-warning" class="sln-rg-coverage-warning" style="display:none;">
		<p>
			<?php esc_html_e( 'Confirm more appointments to unlock lost revenue (80% coverage required).', 'salon-booking-system' ); ?>
			<button type="button" class="button button-small sln-rg-open-queue"><?php esc_html_e( 'Review queue', 'salon-booking-system' ); ?></button>
		</p>
	</div>
</div>
