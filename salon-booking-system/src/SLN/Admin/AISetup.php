<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Salon → AI Setup admin page (parallel to classic Settings).
 */
class SLN_Admin_AISetup extends SLN_Admin_AbstractPage
{
	const PAGE     = 'salon-ai-setup';
	const PRIORITY = 11;

	public function __construct(SLN_Plugin $plugin)
	{
		parent::__construct($plugin);
		add_action('in_admin_header', array($this, 'in_admin_header'));
	}

	public function admin_menu()
	{
		$this->classicAdminMenu(
			__('AI Setup', 'salon-booking-system'),
			__('AI Setup', 'salon-booking-system')
		);
	}

	/**
	 * Shared localize payload for full page + calendar widget.
	 *
	 * @param string $mode page|widget
	 * @return array
	 */
	public static function scriptConfig($mode = 'page')
	{
		$isWidget = $mode === 'widget';

		$usage = new SLN_AI_Usage();

		$config = array(
			'mode'               => $isWidget ? 'widget' : 'page',
			'restUrl'            => esc_url_raw(rest_url('salon/v1/ai-setup')),
			'nonce'              => wp_create_nonce('wp_rest'),
			'pageUrl'            => admin_url('admin.php?page=' . self::PAGE),
			'settingsBookingUrl' => admin_url('admin.php?page=salon-settings&tab=booking'),
			'returnUrl'          => admin_url('admin.php?page=' . self::PAGE . '&ai_credits=1'),
			'iconUrl'            => esc_url_raw(
				SLN_PLUGIN_URL . (
					$isWidget
						? '/img/ai-calendar-launcher.png'
						: '/img/ai-setup-calendar-check.png'
				)
			),
			'speechLang'         => SLN_AI_Language::speechBcp47(),
			'edition'            => SLN_AI_Edition::key(),
			'pricingUrl'         => esc_url_raw(SLN_AI_Edition::pricingUrl()),
			'proIncluded'        => SLN_AI_Usage::PRO_INCLUDED,
			'packs'              => array_values(SLN_AI_Usage::packs()),
			// Local bootstrap only — /session refreshes from the cloud proxy.
			'usage'              => $usage->getBootstrapUsage(),
			// Hide developer backend hints (LLM keys / mock) for normal merchants.
			'showBackendDebug'   => (defined('WP_DEBUG') && WP_DEBUG),
			'i18n'               => array(
				'placeholder'   => $isWidget
					? __('Ask about this calendar...', 'salon-booking-system')
					: __('Describe what you want to configure…', 'salon-booking-system'),
				'send'          => __('Send', 'salon-booking-system'),
				'confirm'       => __('Confirm & apply', 'salon-booking-system'),
				'cancel'        => __('Cancel', 'salon-booking-system'),
				'undo'          => $isWidget
					? __('Undo this change', 'salon-booking-system')
					: __('Undo last AI change', 'salon-booking-system'),
				'thinking'      => __('Thinking…', 'salon-booking-system'),
				'error'         => __('Something went wrong. Please try again.', 'salon-booking-system'),
				'previewTitle'  => __('Proposed change', 'salon-booking-system'),
				'applied'       => __('Changes applied.', 'salon-booking-system'),
				'cancelled'     => __('Change cancelled.', 'salon-booking-system'),
				'undone'        => __('Last AI change undone.', 'salon-booking-system'),
				'empty'         => __('Type a message to get started.', 'salon-booking-system'),
				'you'           => __('You', 'salon-booking-system'),
				'assistant'     => $isWidget
					? __('AI Assistant', 'salon-booking-system')
					: __('AI Setup', 'salon-booking-system'),
				'backendLlm'    => __('AI model', 'salon-booking-system'),
				'backendProxy'  => __('Salon AI cloud', 'salon-booking-system'),
				'backendMock'   => __('Local assistant', 'salon-booking-system'),
				'hintLlm'       => __('Connected via Salon staging LLM (OpenRouter gpt-4o-mini by default).', 'salon-booking-system'),
				'hintProxy'     => __('Using the Salon Booking cloud AI (gpt-4o-mini). No site-side API key required.', 'salon-booking-system'),
				'hintMock'      => __('Offline local assistant (cloud AI unreachable).', 'salon-booking-system'),
				'openAssistant' => __('Open AI assistant', 'salon-booking-system'),
				'closeAssistant'=> __('Close AI assistant', 'salon-booking-system'),
				'voiceStart'    => __('Start voice input', 'salon-booking-system'),
				'voiceStop'     => __('Stop voice input', 'salon-booking-system'),
				'voiceUnsupported' => __('Voice input is not supported in this browser.', 'salon-booking-system'),
				'voiceDenied'   => __('Microphone access was denied. Allow the mic in your browser settings to dictate.', 'salon-booking-system'),
				'voiceError'    => __('Voice input failed. Check your microphone and try again.', 'salon-booking-system'),
				'usageLabel'    => __('Queries left this month', 'salon-booking-system'),
				'usageCredits'  => __('Purchased credits', 'salon-booking-system'),
				'usageExhausted'=> __('No queries left. Buy credits to continue.', 'salon-booking-system'),
				'buyCredits'    => __('Buy credits', 'salon-booking-system'),
				'buyPack'       => __('Buy', 'salon-booking-system'),
				'packsTitle'    => __('Choose a credit pack', 'salon-booking-system'),
				'packsHint'     => __('Credits never expire. Secure checkout — no setup required on your site.', 'salon-booking-system'),
				'purchaseUnavailable' => __('Checkout is temporarily unavailable. Please try again in a few minutes.', 'salon-booking-system'),
				'checkoutStarting' => __('Opening checkout…', 'salon-booking-system'),
				'editionFree'   => __('Free: 10 AI queries included each month', 'salon-booking-system'),
				'editionPro'    => __('PRO: 100 AI queries included each month', 'salon-booking-system'),
				'openFullSetup' => __('Open AI Setup to buy more queries', 'salon-booking-system'),
				'upgradeProTitle' => __('Upgrade to PRO for more AI queries', 'salon-booking-system'),
				'upgradeProText'  => sprintf(
					/* translators: 1: free monthly query allowance, 2: PRO monthly query allowance */
					__('You have used all %1$d free AI queries this month. Upgrade to PRO to get %2$d AI queries every month.', 'salon-booking-system'),
					SLN_AI_Usage::FREE_INCLUDED,
					SLN_AI_Usage::PRO_INCLUDED
				),
				'viewProPlans'    => __('View PRO plans', 'salon-booking-system'),
			),
			// State-driven chips: onboarding gaps and add-on discovery first.
			'suggestions'        => SLN_AI_Proactive::suggestions(SLN_Plugin::getInstance()),
		);

		if ($isWidget) {
			$config['welcome']     = SLN_AI_Language::welcomeMessage(
				'widget',
				class_exists('SLN_AI_Multishop') && SLN_AI_Multishop::isActive()
			);
			$config['suggestions'] = array();
		}

		return $config;
	}

	/**
	 * Enqueue AI Setup CSS/JS (+ optional calendar widget stylesheet).
	 *
	 * @param string $mode page|widget
	 */
	public static function enqueueAiAssets($mode = 'page')
	{
		$version = SLN_Action_InitScripts::ASSETS_VERSION . '-ai-setup-artifact-v20';
		$mode    = $mode === 'widget' ? 'widget' : 'page';

		wp_enqueue_style(
			'salon-ai-setup-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'salon-ai-setup',
			SLN_PLUGIN_URL . '/css/ai-setup.css',
			array('salon-ai-setup-fonts'),
			$version
		);
		if ($mode === 'widget') {
			wp_enqueue_style(
				'salon-ai-calendar-assistant',
				SLN_PLUGIN_URL . '/css/ai-calendar-assistant.css',
				array('salon-ai-setup'),
				$version
			);
		}
		wp_enqueue_script(
			'salon-ai-setup',
			SLN_PLUGIN_URL . '/js/admin/ai-setup.js',
			array('jquery'),
			$version,
			true
		);
		wp_localize_script('salon-ai-setup', 'slnAiSetup', self::scriptConfig($mode));
	}

	public function enqueueAssets()
	{
		parent::enqueueAssets();
		self::enqueueAiAssets('page');
	}

	public function show()
	{
		echo $this->plugin->loadView('admin/ai_setup', array(
			'plugin' => $this->plugin,
		));
	}
}
