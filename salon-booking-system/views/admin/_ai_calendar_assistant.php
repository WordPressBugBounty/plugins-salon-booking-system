<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Floating AI assistant chat launcher on the admin calendar.
 */
$ai_setup_url = admin_url( 'admin.php?page=salon-ai-setup' );
$launcher_url = SLN_PLUGIN_URL . '/img/ai-calendar-launcher.png';
?>
<div id="sln-ai-calendar" class="sln-ai-calendar" data-sln-ai-root="1">
	<div
		id="sln-ai-calendar-panel"
		class="sln-ai-calendar__panel sln-ai-setup"
		role="dialog"
		aria-modal="false"
		aria-labelledby="sln-ai-calendar-title"
		hidden
	>
		<header class="sln-ai-calendar__header">
			<div class="sln-ai-calendar__header-main">
				<div class="sln-ai-calendar__header-icon" aria-hidden="true">
					<img src="<?php echo esc_url( $launcher_url ); ?>" alt="" width="20" height="20" />
				</div>
				<span id="sln-ai-calendar-title" class="sln-ai-calendar__title"><?php esc_html_e( 'AI Assistant', 'salon-booking-system' ); ?></span>
			</div>
			<button
				type="button"
				class="sln-ai-calendar__close"
				id="sln-ai-calendar-close"
				aria-label="<?php esc_attr_e( 'Close AI assistant', 'salon-booking-system' ); ?>"
			>
				<span aria-hidden="true">&times;</span>
			</button>
		</header>

		<div class="sln-ai-calendar__body">
			<div class="sln-ai-calendar__scroll">
				<p id="sln-ai-setup-welcome" class="sln-ai-setup__chat-welcome" hidden></p>
				<div id="sln-ai-setup-backend" class="sln-ai-setup__backend sln-ai-calendar__backend" hidden>
					<div class="sln-ai-setup__backend-card">
						<span class="sln-ai-setup__backend-label"></span>
						<p class="description sln-ai-setup__backend-hint"></p>
					</div>
				</div>

				<div id="sln-ai-setup-messages" class="sln-ai-setup__messages" aria-live="polite"></div>

				<div id="sln-ai-setup-preview" class="sln-ai-setup__preview" hidden>
					<h3 class="sln-ai-setup__preview-title"></h3>
					<div class="sln-ai-setup__preview-body"></div>
					<div class="sln-ai-setup__preview-actions">
						<button type="button" class="button button-primary" id="sln-ai-setup-confirm"></button>
						<button type="button" class="button" id="sln-ai-setup-cancel"></button>
					</div>
				</div>

				<div class="sln-ai-setup__suggestions" id="sln-ai-setup-suggestions" hidden></div>
			</div>

			<form id="sln-ai-setup-form" class="sln-ai-setup__composer sln-ai-calendar__composer">
				<label class="screen-reader-text" for="sln-ai-setup-input"><?php esc_html_e( 'Message', 'salon-booking-system' ); ?></label>
				<textarea id="sln-ai-setup-input" rows="1"></textarea>
				<button
					type="button"
					class="button sln-ai-setup__mic"
					id="sln-ai-setup-mic"
					hidden
					aria-pressed="false"
					aria-label="<?php esc_attr_e( 'Start voice input', 'salon-booking-system' ); ?>"
				>
					<span class="sln-ai-setup__mic-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
					</span>
				</button>
				<button type="submit" class="button button-primary" id="sln-ai-setup-send"></button>
			</form>

			<div class="sln-ai-calendar__footer-actions" id="sln-ai-calendar-footer">
				<a class="sln-ai-calendar__full-link" href="<?php echo esc_url( $ai_setup_url ); ?>">
					<?php esc_html_e( 'Open full AI Setup', 'salon-booking-system' ); ?>
				</a>
			</div>
		</div>
	</div>

	<button
		type="button"
		id="sln-ai-calendar-fab"
		class="sln-ai-calendar__fab"
		aria-expanded="false"
		aria-controls="sln-ai-calendar-panel"
		aria-label="<?php esc_attr_e( 'Open AI assistant', 'salon-booking-system' ); ?>"
	>
		<span class="sln-ai-calendar__fab-icon" aria-hidden="true">
			<img src="<?php echo esc_url( $launcher_url ); ?>" alt="" width="28" height="28" />
		</span>
	</button>
</div>
