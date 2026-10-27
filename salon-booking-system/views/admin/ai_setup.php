<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 */
$icon_url      = SLN_PLUGIN_URL . '/img/ai-setup-calendar-check.png';
$aiEnabled     = SLN_AI_Access::isEnabledForCurrentUser();
$canManage     = SLN_AI_Access::canManage();
$accessOptions = SLN_AI_Access::options();
?>
<div id="sln-salon--admin" class="wrap sln-bootstrap sln-ai-setup">
	<div class="sln-ai-setup__container">
		<nav class="sln-ai-setup__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'salon-booking-system' ); ?>">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=salon' ) ); ?>"><?php esc_html_e( 'Salon Booking', 'salon-booking-system' ); ?></a>
			<span class="sln-ai-setup__breadcrumb-sep" aria-hidden="true">/</span>
			<span class="sln-ai-setup__breadcrumb-current"><?php esc_html_e( 'AI Setup', 'salon-booking-system' ); ?></span>
		</nav>

		<h1 class="sln-ai-setup__title"><?php esc_html_e( 'AI Setup', 'salon-booking-system' ); ?></h1>

		<div class="sln-ai-setup__intro">
			<p>
				<?php esc_html_e( 'Describe your salon setup in plain language — hours, holidays, identity, services, assistants, and more. The assistant proposes changes; nothing is saved until you confirm. Classic Settings remain for advanced options and secrets.', 'salon-booking-system' ); ?>
				<a class="sln-ai-setup__intro-link" href="<?php echo esc_url( admin_url( 'admin.php?page=salon-settings&tab=booking' ) ); ?>">
					<?php esc_html_e( 'Open Booking Rules →', 'salon-booking-system' ); ?>
				</a>
			</p>
		</div>

		<?php if ( filter_input( INPUT_GET, 'ai_access_saved' ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'AI assistant settings saved.', 'salon-booking-system' ); ?></p></div>
		<?php endif; ?>

		<div class="sln-ai-setup__layout">
			<?php if ( ! $aiEnabled ) : ?>
			<div class="sln-ai-setup__chat-panel sln-ai-setup__disabled">
				<h2><?php esc_html_e( 'The AI assistant is disabled', 'salon-booking-system' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'The AI chat is turned off on this site, including the assistant on the calendar. Uncheck "Disable AI assistant" to turn it back on.', 'salon-booking-system' ); ?>
				</p>
			</div>
			<?php else : ?>
			<div class="sln-ai-setup__chat-panel">
				<div class="sln-ai-setup__chat-top">
					<div class="sln-ai-setup__chat-header">
						<div class="sln-ai-setup__chat-icon" aria-hidden="true">
							<img src="<?php echo esc_url( $icon_url ); ?>" alt="" width="22" height="22" />
						</div>
						<span class="sln-ai-setup__chat-label"><?php esc_html_e( 'AI Setup', 'salon-booking-system' ); ?></span>
					</div>
					<div id="sln-ai-setup-usage" class="sln-ai-setup__usage" hidden>
						<div class="sln-ai-setup__usage-row">
							<span class="sln-ai-setup__usage-label"></span>
							<strong class="sln-ai-setup__usage-count"></strong>
						</div>
						<div class="sln-ai-setup__usage-bar" aria-hidden="true"><span class="sln-ai-setup__usage-bar-fill"></span></div>
						<p class="sln-ai-setup__usage-meta"></p>
						<button type="button" class="button sln-ai-setup__buy-btn" id="sln-ai-setup-buy" hidden></button>
					</div>
					<p id="sln-ai-setup-welcome" class="sln-ai-setup__chat-welcome"></p>
				</div>

				<div id="sln-ai-setup-paywall" class="sln-ai-setup__paywall" hidden>
					<div id="sln-ai-setup-upgrade" class="sln-ai-setup__upgrade" hidden>
						<h3 class="sln-ai-setup__upgrade-title"></h3>
						<p class="sln-ai-setup__upgrade-text"></p>
						<a class="button button-primary sln-ai-setup__upgrade-cta" href="#" target="_blank" rel="noopener noreferrer"></a>
					</div>
					<h3 class="sln-ai-setup__paywall-title"></h3>
					<p class="sln-ai-setup__paywall-text"></p>
					<div class="sln-ai-setup__packs" id="sln-ai-setup-packs"></div>
					<p class="description sln-ai-setup__paywall-hint"></p>
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

				<div class="sln-ai-setup__suggestions" id="sln-ai-setup-suggestions"></div>

				<form id="sln-ai-setup-form" class="sln-ai-setup__composer">
					<label class="screen-reader-text" for="sln-ai-setup-input"><?php esc_html_e( 'Message', 'salon-booking-system' ); ?></label>
					<textarea id="sln-ai-setup-input" rows="2"></textarea>
					<button
						type="button"
						class="button sln-ai-setup__mic"
						id="sln-ai-setup-mic"
						hidden
						aria-pressed="false"
						aria-label="<?php esc_attr_e( 'Start voice input', 'salon-booking-system' ); ?>"
					>
						<span class="sln-ai-setup__mic-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
						</span>
					</button>
					<button type="submit" class="button button-primary" id="sln-ai-setup-send"></button>
				</form>
			</div>
			<?php endif; ?>

			<aside class="sln-ai-setup__sidebar">
				<?php if ( $canManage ) : ?>
				<form class="sln-ai-setup__access" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( SLN_AI_Access::SAVE_ACTION ); ?>" />
					<?php wp_nonce_field( SLN_AI_Access::SAVE_ACTION ); ?>
					<h3 class="sln-ai-setup__access-title"><?php esc_html_e( 'Access', 'salon-booking-system' ); ?></h3>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( SLN_AI_Access::OPT_DISABLED ); ?>" value="1" <?php checked( $accessOptions[ SLN_AI_Access::OPT_DISABLED ] ); ?> />
						<?php esc_html_e( 'Disable AI assistant', 'salon-booking-system' ); ?>
					</label>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( SLN_AI_Access::OPT_DISABLED_ASSISTANTS ); ?>" value="1" <?php checked( $accessOptions[ SLN_AI_Access::OPT_DISABLED_ASSISTANTS ] ); ?> />
						<?php esc_html_e( 'Disable for Assistants users only', 'salon-booking-system' ); ?>
					</label>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( SLN_AI_Access::OPT_RESTRICT_CUSTOMER_DATA ); ?>" value="1" <?php checked( $accessOptions[ SLN_AI_Access::OPT_RESTRICT_CUSTOMER_DATA ] ); ?> />
						<?php esc_html_e( 'Restrict access to customers data for Assistant users', 'salon-booking-system' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Assistant users are users with the Salon staff role. With restricted customer data they cannot search customers or see customer email and phone.', 'salon-booking-system' ); ?>
					</p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'salon-booking-system' ); ?></button>
				</form>
				<?php endif; ?>
				<?php if ( $aiEnabled ) : ?>
				<div id="sln-ai-setup-backend" class="sln-ai-setup__backend" hidden>
					<div class="sln-ai-setup__backend-card">
						<span class="sln-ai-setup__backend-label"></span>
						<p class="description sln-ai-setup__backend-hint"></p>
					</div>
				</div>
				<button type="button" class="button sln-ai-setup__undo" id="sln-ai-setup-undo" disabled></button>
				<div class="sln-ai-setup__guidance">
					<p class="description">
						<?php esc_html_e( 'Can apply hours, holidays, identity, catalog, booking rules, style, and more after you confirm. Payments, SMS keys, OAuth, and license are guidance-only — never written by the assistant.', 'salon-booking-system' ); ?>
					</p>
				</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
</div>
