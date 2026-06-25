<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var int $count */
?>
<div class="sln-rg-calendar-banner" id="sln-rg-unresolved-banner" role="status">
	<div class="sln-rg-calendar-banner__icon" aria-hidden="true">
		<svg viewBox="0 0 24 24" width="22" height="22" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M8 2v3M16 2v3M3.5 9.5h17M5 4.5h14a2 2 0 0 1 2 2V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
			<path d="m9.5 15 1.8 1.8L15 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
	</div>
	<div class="sln-rg-calendar-banner__body">
		<span class="sln-rg-calendar-banner__title"><?php esc_html_e( 'Attendance confirmation needed', 'salon-booking-system' ); ?></span>
		<span class="sln-rg-calendar-banner__text">
			<?php
			printf(
				/* translators: %s: number of unresolved appointments */
				esc_html( _n( '%s past appointment is waiting for you to confirm whether the customer showed up.', '%s past appointments are waiting for you to confirm whether the customers showed up.', $count, 'salon-booking-system' ) ),
				'<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>'
			);
			?>
		</span>
	</div>
	<button type="button" class="sln-rg-calendar-banner__cta sln-rg-open-queue" id="sln-rg-open-queue">
		<?php esc_html_e( 'Review now', 'salon-booking-system' ); ?>
	</button>
</div>
