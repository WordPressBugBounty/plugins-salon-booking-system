<?php

/**
 * REST API for AI Setup: session, chat, confirm, undo.
 */
class SLN_AI_REST_Controller
{
	const NS = 'salon/v1';

	/** @var SLN_Plugin */
	private $plugin;

	public function __construct(SLN_Plugin $plugin)
	{
		$this->plugin = $plugin;
		add_action('rest_api_init', array($this, 'registerRoutes'));
	}

	public function registerRoutes()
	{
		register_rest_route(
			self::NS,
			'/ai-setup/session',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'session'),
				'permission_callback' => array($this, 'permissions'),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-setup/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'chat'),
				'permission_callback' => array($this, 'permissions'),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-setup/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'confirm'),
				'permission_callback' => array($this, 'permissions'),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-setup/undo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'undo'),
				'permission_callback' => array($this, 'permissions'),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-setup/usage',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'usage'),
				'permission_callback' => array($this, 'permissions'),
			)
		);

		register_rest_route(
			self::NS,
			'/ai-setup/buy-credits',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'buyCredits'),
				'permission_callback' => array($this, 'permissions'),
			)
		);

		// Public site-ownership proof for the cloud proxy: exposes only the
		// SHA-256 of the site token, so the proxy can pin the token to this URL
		// on first contact without anyone being able to read the plaintext token.
		register_rest_route(
			self::NS,
			'/ai-setup/site-verify',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'siteVerify'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/ai-setup/telemetry',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array($this, 'telemetry'),
					'permission_callback' => array($this, 'permissions'),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array($this, 'telemetryClear'),
					'permission_callback' => array($this, 'permissions'),
				),
			)
		);
	}

	/**
	 * Ownership proof endpoint queried by the Salon cloud proxy when it
	 * registers this site's token. Public but harmless: the hash cannot be
	 * reversed into the token the proxy actually authenticates against.
	 *
	 * @return WP_REST_Response
	 */
	public function siteVerify()
	{
		return rest_ensure_response(
			array(
				'site'         => home_url(),
				'token_sha256' => hash('sha256', SLN_AI_Usage::siteToken()),
			)
		);
	}

	/**
	 * Intent misses recorded locally (messages the assistant could not route).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function telemetry(WP_REST_Request $request)
	{
		$limit = absint($request->get_param('limit'));

		return rest_ensure_response(
			array(
				'misses' => SLN_AI_Telemetry::recent($limit > 0 ? $limit : 50),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function telemetryClear(WP_REST_Request $request)
	{
		SLN_AI_Telemetry::clear();

		return rest_ensure_response(array('cleared' => true));
	}

	/**
	 * @return bool
	 */
	public function permissions()
	{
		$cap = apply_filters('salonviews/settings/capability', 'manage_salon');

		return current_user_can($cap);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function session(WP_REST_Request $request)
	{
		$store     = new SLN_AI_SessionStore();
		$sessionId = $store->createSessionId();
		$store->save($sessionId, array('messages' => array(), 'draft' => null));
		$proxy     = new SLN_AI_ProxyClient();
		$usage     = new SLN_AI_Usage();

		$welcome = SLN_AI_Language::welcomeMessage('page', SLN_AI_Multishop::isActive());

		return rest_ensure_response(
			array(
				'session_id'     => $sessionId,
				'can_undo'       => $store->canUndo(),
				'welcome'        => $welcome,
				'backend'        => $proxy->getBackend(),
				'has_direct_llm' => $proxy->hasDirectLlm(),
				'force_mock'     => $proxy->forceMock(),
				'usage'          => $usage->getUsage(),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function usage(WP_REST_Request $request)
	{
		$usage = new SLN_AI_Usage();

		return rest_ensure_response($usage->getUsage());
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function buyCredits(WP_REST_Request $request)
	{
		$usage    = new SLN_AI_Usage();
		$packId   = (string) $request->get_param('pack_id');
		$returnUrl = (string) $request->get_param('return_url');
		if ($returnUrl === '') {
			$returnUrl = admin_url('admin.php?page=salon-ai-setup&ai_credits=1');
		}

		$result = $usage->createCheckout($packId, $returnUrl);
		if (is_wp_error($result)) {
			return $result;
		}

		return rest_ensure_response($result);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat(WP_REST_Request $request)
	{
		$store     = new SLN_AI_SessionStore();
		$sessionId = $this->resolveSessionId($request, $store);
		$message   = trim((string) $request->get_param('message'));

		if ($message === '') {
			return new WP_Error(
				'sln_ai_empty',
				__('Please enter a message.', 'salon-booking-system'),
				array('status' => 400)
			);
		}

		$usageSvc = new SLN_AI_Usage();
		$blocked  = $usageSvc->assertCanChat();
		if (is_wp_error($blocked)) {
			return $blocked;
		}

		$message  = $this->redact($message);
		$context  = SLN_AI_ContextPack::build($this->plugin);
		$session  = $store->get($sessionId);
		$history  = array();
		if (! empty($session['messages']) && is_array($session['messages'])) {
			// Keep recent turns only — long histories inflate latency on every request.
			$slice = array_slice($session['messages'], -6);
			foreach ($slice as $turn) {
				if (empty($turn['role']) || empty($turn['content'])) {
					continue;
				}
				$content = (string) $turn['content'];
				// Cap oversized assistant dumps (e.g. long guidance) in history.
				if (strlen($content) > 1200) {
					$content = substr($content, 0, 1199) . '…';
				}
				$history[] = array(
					'role'    => $turn['role'],
					'content' => $content,
				);
			}
		}
		$draft = ! empty($session['draft']) && is_array($session['draft']) ? $session['draft'] : null;

		$registry = new SLN_AI_ToolRegistry();
		$proxy    = new SLN_AI_ProxyClient();
		$lastGuidance = ( ! empty($session['last_guidance']) && is_array($session['last_guidance']) )
			? $session['last_guidance']
			: null;

		$result   = $proxy->chat(
			array(
				'message'       => $message,
				'context'       => $context,
				'tools'         => $registry->getToolDefinitionsForLlm(),
				'history'       => $history,
				'draft'         => $draft,
				'last_guidance' => $lastGuidance,
				'instructions'  => $registry->getSystemInstructions(),
			)
		);

		if (is_wp_error($result)) {
			return $this->enrichQuotaError($result);
		}

		$backend             = isset($result['backend']) ? $result['backend'] : $proxy->getBackend();
		$proxyReportedUsage  = ! empty($result['usage']) && is_array($result['usage']);
		$usageSvc->consumeAfterChat($backend, $proxyReportedUsage);

		if ($proxyReportedUsage) {
			$usage = $usageSvc->normalize($result['usage']);
		} else {
			$usage = $usageSvc->getUsage();
		}

		$response = array(
			'session_id' => $sessionId,
			'message'    => isset($result['message']) ? $result['message'] : '',
			'preview'    => null,
			'can_undo'   => $store->canUndo(),
			'backend'    => $backend,
			'usage'      => $usage,
		);

		if (array_key_exists('draft', $result)) {
			$session['draft'] = $result['draft'];
		}

		if (! empty($result['tool_call']) && is_array($result['tool_call'])) {
			$name = isset($result['tool_call']['name']) ? $result['tool_call']['name'] : '';
			$args = isset($result['tool_call']['arguments']) && is_array($result['tool_call']['arguments'])
				? $result['tool_call']['arguments']
				: array();

			$lang = SLN_AI_Language::detect($message);
			if (! $registry->has($name)) {
				$response['message'] = __(
					'That action is not supported. Ask me to explain a setting, or describe opening hours, holidays, services, or assistants.',
					'salon-booking-system'
				);
			} else {
				// Let tools localize human text to the user’s message language.
				$args['_user_message'] = $message;
				$preview               = $registry->buildPreview($name, $args, $this->plugin);
				if (is_wp_error($preview)) {
					// Missing/invalid required args → chat reply only, never a confirm card.
					$response['preview'] = null;
					$response['message'] = $preview->get_error_message();
					$store->clearPreview($sessionId);
					$session['draft']    = null;
				} elseif (! empty($preview['guidance'])) {
					// Guidance tools: no confirm card. Always use the tool summary in the
					// user’s message language — ignore model lead-ins (wrong language).
					$toolMsg = isset($preview['summary']) ? trim((string) $preview['summary']) : '';
					if ($toolMsg !== '') {
						$response['message'] = $toolMsg;
					} else {
						$response['message'] = SLN_AI_Language::phrase(
							$lang,
							'guidance_lead',
							__('Here’s what I found:', 'salon-booking-system')
						);
					}
					$session['draft'] = null;
					// Keep last guidance context for short follow-ups
					// (“what does full mean?”, “e alle 15?”, “where do I enable it?”).
					$session['last_guidance'] = $this->buildLastGuidance($name, $toolMsg, $preview, $args);
				} else {
					$previewId = $store->storePreview($sessionId, $preview);
					$session   = $store->get($sessionId);
					$session['draft'] = null;
					$response['preview'] = array(
						'id'      => $previewId,
						'summary' => $preview['summary'],
						'tool'    => $name,
					);
					if ($response['message'] === '' || $this->isGenericConfirmLead($response['message'])) {
						$response['message'] = SLN_AI_Language::phrase(
							$lang,
							'confirm_lead',
							__('I put together a change from what you told me. Please review and confirm.', 'salon-booking-system')
						);
					}
				}
			}
		}

		$session['messages'][] = array('role' => 'user', 'content' => $message, 'at' => time());
		$session['messages'][] = array('role' => 'assistant', 'content' => $response['message'], 'at' => time());
		$store->save($sessionId, $session);

		return rest_ensure_response($response);
	}

	/**
	 * Compact follow-up context stored in the session after any guidance tool.
	 *
	 * @param string $name    Tool name.
	 * @param string $summary Guidance text shown to the merchant.
	 * @param array  $preview Tool preview payload.
	 * @param array  $args    Original tool-call arguments (fallback).
	 * @return array
	 */
	private function buildLastGuidance($name, $summary, array $preview, array $args)
	{
		$arguments = isset($preview['arguments']) && is_array($preview['arguments'])
			? $preview['arguments']
			: $args;
		unset($arguments['_user_message']);

		$out = array(
			'tool'      => $name,
			'summary'   => function_exists('mb_substr') ? mb_substr((string) $summary, 0, 800) : substr((string) $summary, 0, 800),
			'arguments' => $arguments,
			'at'        => time(),
		);

		if (! empty($preview['diagnosis']) && is_array($preview['diagnosis'])) {
			$out['diagnosis'] = $preview['diagnosis'];
		}

		if (! empty($preview['hits']) && is_array($preview['hits'])) {
			$hits = array();
			foreach (array_slice($preview['hits'], 0, 5) as $hit) {
				if (! is_array($hit)) {
					continue;
				}
				$hits[] = array(
					'id'    => isset($hit['id']) ? (string) $hit['id'] : '',
					'title' => isset($hit['title']) ? (string) $hit['title'] : '',
					'kind'  => isset($hit['kind']) ? (string) $hit['kind'] : '',
					'topic' => isset($hit['topic']) ? (string) $hit['topic'] : '',
					'url'   => isset($hit['url']) ? (string) $hit['url'] : '',
				);
			}
			$out['hits'] = $hits;
		}

		return $out;
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm(WP_REST_Request $request)
	{
		$store     = new SLN_AI_SessionStore();
		$sessionId = $this->resolveSessionId($request, $store);
		$previewId = (string) $request->get_param('preview_id');
		$cancel    = (bool) $request->get_param('cancel');

		$preview = $store->getPreview($sessionId, $previewId);
		if (! $preview) {
			return new WP_Error(
				'sln_ai_preview_missing',
				__('This preview expired or was already handled. Send a new message.', 'salon-booking-system'),
				array('status' => 404)
			);
		}

		if ($cancel) {
			$store->clearPreview($sessionId);

			return rest_ensure_response(
				array(
					'session_id' => $sessionId,
					'message'    => __('Change cancelled. Nothing was saved.', 'salon-booking-system'),
					'can_undo'   => $store->canUndo(),
				)
			);
		}

		$registry = new SLN_AI_ToolRegistry();
		$result   = $registry->apply($preview['tool'], $preview['arguments'], $this->plugin);
		if (is_wp_error($result)) {
			// Drop the pending card so the UI cannot re-confirm a failed proposal.
			$store->clearPreview($sessionId);

			return $result;
		}

		$store->setUndoSnapshot(
			$preview['tool'],
			isset($result['before']) ? $result['before'] : array(),
			isset($result['after']) ? $result['after'] : array()
		);
		$store->appendAudit(
			$preview['tool'],
			array(
				'action'     => 'apply',
				'session_id' => $sessionId,
			)
		);
		$store->clearPreview($sessionId);
		SLN_AI_ContextPack::bustCache();

		return rest_ensure_response(
			array(
				'session_id' => $sessionId,
				'message'    => isset($result['message'])
					? $result['message']
					: __('Changes applied.', 'salon-booking-system'),
				'can_undo'   => true,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function undo(WP_REST_Request $request)
	{
		$store = new SLN_AI_SessionStore();
		$snap  = $store->getUndoSnapshot();
		if (! $snap || empty($snap['tool'])) {
			return new WP_Error(
				'sln_ai_no_undo',
				__('Nothing to undo.', 'salon-booking-system'),
				array('status' => 400)
			);
		}

		$registry = new SLN_AI_ToolRegistry();
		$result   = $registry->restore(
			$snap['tool'],
			isset($snap['before']) ? $snap['before'] : array(),
			$this->plugin
		);
		if (is_wp_error($result)) {
			return $result;
		}

		$store->appendAudit(
			$snap['tool'],
			array(
				'action' => 'undo',
			)
		);
		$store->clearUndoSnapshot();
		SLN_AI_ContextPack::bustCache();

		return rest_ensure_response(
			array(
				'message'  => isset($result['message'])
					? $result['message']
					: __('Last AI change undone.', 'salon-booking-system'),
				'can_undo' => false,
			)
		);
	}

	/**
	 * @param WP_REST_Request    $request
	 * @param SLN_AI_SessionStore $store
	 * @return string
	 */
	private function resolveSessionId(WP_REST_Request $request, SLN_AI_SessionStore $store)
	{
		$sessionId = (string) $request->get_param('session_id');
		$data      = $store->get($sessionId);
		if ($sessionId && $data) {
			return $sessionId;
		}
		$sessionId = $store->createSessionId();
		$store->save($sessionId, array('messages' => array()));

		return $sessionId;
	}

	/**
	 * True when the model/proxy used the generic English “confirm a change” lead-in.
	 *
	 * @param string $text
	 * @return bool
	 */
	private function isGenericConfirmLead($text)
	{
		$t = strtolower(trim((string) $text));
		if ($t === '') {
			return false;
		}

		return (bool) preg_match(
			'/\b(put together a change|please review and confirm|review the proposed change|confirm to apply)\b/i',
			$t
		);
	}

	/**
	 * Strip patterns that look like secrets before sending to the proxy.
	 *
	 * @param string $text
	 * @return string
	 */
	private function redact($text)
	{
		$text = preg_replace('/\bsk-[a-zA-Z0-9]{10,}\b/', '[redacted]', $text);
		$text = preg_replace('/\bBearer\s+[a-zA-Z0-9\-._~+\/]+=*\b/i', 'Bearer [redacted]', $text);

		return $text;
	}

	/**
	 * Ensure quota WP_Error payloads always expose usage + packs for the UI.
	 *
	 * @param WP_Error $error
	 * @return WP_Error
	 */
	private function enrichQuotaError(WP_Error $error)
	{
		if ($error->get_error_code() !== 'sln_ai_quota_exceeded') {
			return $error;
		}

		$data = $error->get_error_data();
		if (! is_array($data)) {
			$data = array('status' => 402);
		}
		$usageSvc = new SLN_AI_Usage();
		if (empty($data['usage']) || ! is_array($data['usage'])) {
			$data['usage'] = $usageSvc->getUsage();
		} else {
			$data['usage'] = $usageSvc->normalize($data['usage']);
		}
		if (empty($data['packs']) || ! is_array($data['packs'])) {
			$data['packs'] = array_values(SLN_AI_Usage::packs());
		}
		$data['status'] = 402;
		$error->add_data($data);

		return $error;
	}
}
