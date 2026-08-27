<?php

/**
 * Cloud LLM proxy client for AI Setup.
 *
 * Merchants need no wp-config LLM settings. Chat goes to the Salon Booking cloud proxy;
 * the store server holds the OpenRouter key and calls gpt-4o-mini.
 *
 * Priority:
 * 1. Salon Booking cloud proxy (production — default)
 * 2. Optional direct LLM when SLN_AI_LLM_API_KEY is set (Salon staging / emergency only)
 * 3. Conversational local fallback
 *
 * Built-in LLM defaults (no merchant config): OpenRouter + openai/gpt-4o-mini.
 * Optional overrides via wp-config / filters are for Salon engineering only.
 */
class SLN_AI_ProxyClient
{
	const DEFAULT_BASE               = 'https://www.salonbookingsystem.com/wp-json/salon-ai/v1';
	const DEFAULT_LLM_BASE_OPENAI    = 'https://api.openai.com/v1';
	const DEFAULT_LLM_BASE_ANTHROPIC = 'https://api.anthropic.com/v1';
	const DEFAULT_LLM_BASE_OPENROUTER = 'https://openrouter.ai/api/v1';
	/** Default direct-LLM stack when a staging key is present (merchants never set this). */
	const DEFAULT_LLM_PROVIDER       = 'openrouter';
	const DEFAULT_MODEL_OPENAI       = 'gpt-4o-mini';
	const DEFAULT_MODEL_ANTHROPIC    = 'claude-haiku-4-5';
	const DEFAULT_MODEL_OPENROUTER   = 'openai/gpt-4o-mini';
	/** @deprecated Use DEFAULT_LLM_BASE_OPENAI */
	const DEFAULT_LLM_BASE = 'https://api.openai.com/v1';
	/** @deprecated Use DEFAULT_MODEL_OPENROUTER */
	const DEFAULT_MODEL    = 'openai/gpt-4o-mini';

	/**
	 * @return string
	 */
	public function getBaseUrl()
	{
		if (defined('SLN_AI_PROXY_URL') && SLN_AI_PROXY_URL) {
			$base = SLN_AI_PROXY_URL;
		} else {
			$base = self::DEFAULT_BASE;
		}

		return untrailingslashit(apply_filters('sln_ai_proxy_url', $base));
	}

	/**
	 * Developer / staging LLM key (wp-config). Not shown in plugin settings.
	 *
	 * @return string
	 */
	public function getLlmApiKey()
	{
		$key = '';
		if (defined('SLN_AI_LLM_API_KEY') && SLN_AI_LLM_API_KEY) {
			$key = (string) SLN_AI_LLM_API_KEY;
		}

		return (string) apply_filters('sln_ai_llm_api_key', $key);
	}

	/**
	 * @return string anthropic|openrouter|openai
	 */
	public function getLlmProvider()
	{
		$provider = '';
		if (defined('SLN_AI_LLM_PROVIDER') && SLN_AI_LLM_PROVIDER) {
			$provider = strtolower((string) SLN_AI_LLM_PROVIDER);
		}

		$key = $this->getLlmApiKey();
		if ($provider === '' && strpos($key, 'sk-ant-') === 0) {
			$provider = 'anthropic';
		}
		if ($provider === '' && strpos($key, 'sk-or-') === 0) {
			$provider = 'openrouter';
		}

		$model = defined('SLN_AI_LLM_MODEL') ? strtolower((string) SLN_AI_LLM_MODEL) : '';
		if ($provider === '' && strpos($model, 'claude') === 0) {
			$provider = 'anthropic';
		}
		if ($provider === '' && (strpos($model, 'openrouter/') === 0 || substr($model, -5) === ':free')) {
			$provider = 'openrouter';
		}

		$base = defined('SLN_AI_LLM_BASE') ? strtolower((string) SLN_AI_LLM_BASE) : '';
		if ($provider === '' && strpos($base, 'anthropic') !== false) {
			$provider = 'anthropic';
		}
		if ($provider === '' && strpos($base, 'openrouter') !== false) {
			$provider = 'openrouter';
		}

		if ($provider === 'openrouter' || $provider === 'anthropic' || $provider === 'openai') {
			// keep explicit / detected provider
		} else {
			$provider = self::DEFAULT_LLM_PROVIDER;
		}

		return (string) apply_filters('sln_ai_llm_provider', $provider);
	}

	/**
	 * OpenRouter is OpenAI-compatible; used for defaults and request headers.
	 *
	 * @return bool
	 */
	public function isOpenRouter()
	{
		if ($this->getLlmProvider() === 'openrouter') {
			return true;
		}

		$base = strtolower($this->getLlmBase());

		return strpos($base, 'openrouter.ai') !== false;
	}

	/**
	 * @return string
	 */
	public function getLlmBase()
	{
		if (defined('SLN_AI_LLM_BASE') && SLN_AI_LLM_BASE) {
			$base = (string) SLN_AI_LLM_BASE;
		} else {
			switch ($this->getLlmProvider()) {
				case 'anthropic':
					$base = self::DEFAULT_LLM_BASE_ANTHROPIC;
					break;
				case 'openrouter':
					$base = self::DEFAULT_LLM_BASE_OPENROUTER;
					break;
				default:
					$base = self::DEFAULT_LLM_BASE_OPENAI;
					break;
			}
		}

		return untrailingslashit(apply_filters('sln_ai_llm_base', $base));
	}

	/**
	 * @return string
	 */
	public function getLlmModel()
	{
		if (defined('SLN_AI_LLM_MODEL') && SLN_AI_LLM_MODEL) {
			$model = (string) SLN_AI_LLM_MODEL;
		} else {
			switch ($this->getLlmProvider()) {
				case 'anthropic':
					$model = self::DEFAULT_MODEL_ANTHROPIC;
					break;
				case 'openrouter':
					$model = self::DEFAULT_MODEL_OPENROUTER;
					break;
				default:
					$model = self::DEFAULT_MODEL_OPENAI;
					break;
			}
		}

		return (string) apply_filters('sln_ai_llm_model', $model);
	}

	/**
	 * @return bool
	 */
	public function hasDirectLlm()
	{
		return $this->getLlmApiKey() !== '';
	}

	/**
	 * Explicit mock-only mode (wp-config / filter). Default: false — prefer real LLM/proxy.
	 *
	 * @return bool
	 */
	public function forceMock()
	{
		$default = defined('SLN_AI_PROXY_MOCK') ? (bool) SLN_AI_PROXY_MOCK : false;

		return (bool) apply_filters('sln_ai_proxy_mock', $default);
	}

	/**
	 * @return bool
	 * @deprecated Use forceMock(); kept for callers expecting useMock().
	 */
	public function useMock()
	{
		return $this->forceMock();
	}

	/**
	 * Preferred backend before a request runs (not after soft-fallback).
	 *
	 * @return string llm|proxy|mock
	 */
	public function getBackend()
	{
		if ($this->forceMock()) {
			return 'mock';
		}

		// Production path: Salon cloud proxy (no merchant LLM config).
		return 'proxy';
	}

	/**
	 * @param array $payload Chat request (message, context, tools, instructions, history, draft).
	 * @return array{message?:string,tool_call?:array|null,draft?:array|null,backend?:string}|WP_Error
	 */
	public function chat(array $payload)
	{
		if ($this->forceMock()) {
			return $this->tagBackend($this->mockChat($payload), 'mock');
		}

		// 1) Salon Booking cloud proxy — merchants never configure an LLM key.
		$url  = $this->getBaseUrl() . '/chat';
		$args = array(
			'timeout'   => 45,
			'sslverify' => true,
			'headers'   => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'      => wp_json_encode($this->withAuth($payload)),
		);

		$response = wp_remote_post($url, $args);
		if (! is_wp_error($response)) {
			$code = (int) wp_remote_retrieve_response_code($response);
			$body = json_decode(wp_remote_retrieve_body($response), true);

			if ($code === 402) {
				return $this->quotaErrorFromBody($body);
			}

			if ($code === 429) {
				return new WP_Error(
					'sln_ai_rate_limited',
					__('AI service rate limit reached. Please try again later.', 'salon-booking-system')
				);
			}

			if ($code >= 200 && $code < 300 && is_array($body)) {
				$out = array(
					'message'   => isset($body['message']) ? (string) $body['message'] : '',
					'tool_call' => isset($body['tool_call']) && is_array($body['tool_call'])
						? $body['tool_call']
						: null,
					'draft'     => isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : null,
				);
				if (! empty($body['usage']) && is_array($body['usage'])) {
					$out['usage'] = $body['usage'];
				}

				return $this->tagBackend($out, 'proxy');
			}

			// Auth failures stay hard errors.
			if (in_array($code, array(401, 403), true)) {
				$msg = is_array($body) && ! empty($body['message'])
					? $body['message']
					: __('AI service returned an unexpected response.', 'salon-booking-system');

				return new WP_Error('sln_ai_proxy_error', $msg, array('status' => $code));
			}
			// Other proxy errors → try optional staging LLM, then mock.
		}

		// 2) Optional direct LLM (Salon staging only — SLN_AI_LLM_API_KEY). Defaults: OpenRouter + gpt-4o-mini.
		if ($this->hasDirectLlm()) {
			$result = $this->chatViaLlm($payload);
			if (! is_wp_error($result)) {
				return $this->tagBackend($result, 'llm');
			}
		}

		// 3) Conversational local fallback.
		return $this->tagBackend($this->mockChat($payload), 'mock');
	}

	/**
	 * @param array  $result
	 * @param string $backend
	 * @return array
	 */
	private function tagBackend(array $result, $backend)
	{
		$result['backend'] = $backend;

		return $result;
	}

	/**
	 * Direct LLM with tool calling (Anthropic or OpenAI-compatible / OpenRouter).
	 *
	 * @param array $payload
	 * @return array|WP_Error
	 */
	private function chatViaLlm(array $payload)
	{
		if ($this->getLlmProvider() === 'anthropic') {
			return $this->chatViaAnthropic($payload);
		}

		// openrouter + openai both use Chat Completions.
		return $this->chatViaOpenAi($payload);
	}

	/**
	 * Headers for OpenAI-compatible endpoints (incl. OpenRouter app attribution).
	 *
	 * @return array
	 */
	private function buildOpenAiCompatibleHeaders()
	{
		$headers = array(
			'Authorization' => 'Bearer ' . $this->getLlmApiKey(),
			'Content-Type'  => 'application/json',
		);

		if ($this->isOpenRouter()) {
			// Recommended by OpenRouter for rankings / app identification.
			$headers['HTTP-Referer'] = home_url('/');
			$headers['X-Title']      = 'Salon Booking System AI Setup';
		}

		return (array) apply_filters('sln_ai_llm_openai_headers', $headers, $this);
	}

	/**
	 * Shared system prompt for direct LLM backends.
	 *
	 * @param array $payload
	 * @return string
	 */
	private function buildLlmSystemPrompt(array $payload)
	{
		$context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : array();

		// Instructions already cover language/tools/safety — keep the LLM wrapper lean for latency.
		$system  = isset($payload['instructions']) ? (string) $payload['instructions'] : '';
		$system .= "\n\nPrefer real service/assistant ids from site context. "
			. "When details are missing for a correct answer, ask clarifying questions before guessing. "
			. "Never invent payment, SMS, or OAuth secrets.\n\n"
			. SLN_AI_Ecosystem::instructionsSnippet() . "\n\n"
			. SLN_AI_ContextPack::formatForPrompt($context);

		return $system;
	}

	/**
	 * @param array $payload
	 * @return array
	 */
	private function buildLlmHistoryMessages(array $payload)
	{
		$messages = array();
		$history  = isset($payload['history']) && is_array($payload['history']) ? $payload['history'] : array();
		foreach ($history as $turn) {
			if (empty($turn['role']) || empty($turn['content'])) {
				continue;
			}
			$role       = $turn['role'] === 'assistant' ? 'assistant' : 'user';
			$messages[] = array(
				'role'    => $role,
				'content' => (string) $turn['content'],
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => isset($payload['message']) ? (string) $payload['message'] : '',
		);

		return $messages;
	}

	/**
	 * Anthropic Messages API with tool use.
	 *
	 * @param array $payload
	 * @return array|WP_Error
	 */
	private function chatViaAnthropic(array $payload)
	{
		$base  = $this->getLlmBase();
		$model = $this->getLlmModel();

		$tools = array();
		foreach (isset($payload['tools']) && is_array($payload['tools']) ? $payload['tools'] : array() as $tool) {
			if (empty($tool['name'])) {
				continue;
			}
			$tools[] = array(
				'name'         => $tool['name'],
				'description'  => isset($tool['description']) ? $tool['description'] : '',
				'input_schema' => isset($tool['parameters']) ? $tool['parameters'] : array('type' => 'object', 'properties' => array()),
			);
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => 768,
			'system'     => $this->buildLlmSystemPrompt($payload),
			'messages'   => $this->buildLlmHistoryMessages($payload),
		);
		if ($tools) {
			$body['tools'] = $tools;
		}

		$response = wp_remote_post(
			$base . '/messages',
			array(
				'timeout' => 45,
				'headers' => array(
					'x-api-key'         => $this->getLlmApiKey(),
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode($body),
			)
		);

		if (is_wp_error($response)) {
			return new WP_Error(
				'sln_ai_llm_unreachable',
				sprintf(
					/* translators: %s: error message */
					__('AI model unreachable: %s', 'salon-booking-system'),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true);
		if ($code < 200 || $code >= 300 || ! is_array($data)) {
			$err = is_array($data) && isset($data['error']['message'])
				? $data['error']['message']
				: __('AI model returned an unexpected response.', 'salon-booking-system');

			return new WP_Error('sln_ai_llm_error', $err, array('status' => $code));
		}

		$text     = '';
		$toolCall = null;
		if (! empty($data['content']) && is_array($data['content'])) {
			foreach ($data['content'] as $block) {
				if (! is_array($block) || empty($block['type'])) {
					continue;
				}
				if ($block['type'] === 'text' && isset($block['text'])) {
					$text .= (string) $block['text'];
				}
				if ($block['type'] === 'tool_use' && $toolCall === null) {
					$toolCall = array(
						'name'      => isset($block['name']) ? $block['name'] : '',
						'arguments' => isset($block['input']) && is_array($block['input']) ? $block['input'] : array(),
					);
				}
			}
		}

		$out = array(
			'message'   => trim($text),
			'tool_call' => $toolCall,
			'draft'     => null,
		);
		if ($toolCall && $out['message'] === '') {
			$out['message'] = $this->defaultToolLeadIn($payload, $toolCall);
		}

		return $out;
	}

	/**
	 * OpenAI-compatible Chat Completions with tool calling (OpenAI, OpenRouter, etc.).
	 *
	 * @param array $payload
	 * @return array|WP_Error
	 */
	private function chatViaOpenAi(array $payload)
	{
		$base  = $this->getLlmBase();
		$model = $this->getLlmModel();

		$messages   = array(array('role' => 'system', 'content' => $this->buildLlmSystemPrompt($payload)));
		$messages   = array_merge($messages, $this->buildLlmHistoryMessages($payload));

		$tools = array();
		foreach (isset($payload['tools']) && is_array($payload['tools']) ? $payload['tools'] : array() as $tool) {
			if (empty($tool['name'])) {
				continue;
			}
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $tool['name'],
					'description' => isset($tool['description']) ? $tool['description'] : '',
					'parameters'   => isset($tool['parameters']) ? $tool['parameters'] : array('type' => 'object'),
				),
			);
		}

		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => 0.2,
			'max_tokens'  => 768,
		);
		if ($tools) {
			$body['tools']       = $tools;
			$body['tool_choice'] = 'auto';
		}

		$response = wp_remote_post(
			$base . '/chat/completions',
			array(
				'timeout' => 45,
				'headers' => $this->buildOpenAiCompatibleHeaders(),
				'body'    => wp_json_encode($body),
			)
		);

		if (is_wp_error($response)) {
			return new WP_Error(
				'sln_ai_llm_unreachable',
				sprintf(
					/* translators: %s: error message */
					__('AI model unreachable: %s', 'salon-booking-system'),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true);
		if ($code < 200 || $code >= 300 || ! is_array($data)) {
			$err = is_array($data) && isset($data['error']['message'])
				? $data['error']['message']
				: __('AI model returned an unexpected response.', 'salon-booking-system');

			return new WP_Error('sln_ai_llm_error', $err, array('status' => $code));
		}

		$choice = isset($data['choices'][0]['message']) ? $data['choices'][0]['message'] : array();
		$out    = array(
			'message'   => isset($choice['content']) ? (string) $choice['content'] : '',
			'tool_call' => null,
			'draft'     => null,
		);

		if (! empty($choice['tool_calls'][0]['function'])) {
			$fn   = $choice['tool_calls'][0]['function'];
			$args = array();
			if (! empty($fn['arguments'])) {
				$decoded = json_decode($fn['arguments'], true);
				$args    = is_array($decoded) ? $decoded : array();
			}
			$out['tool_call'] = array(
				'name'      => isset($fn['name']) ? $fn['name'] : '',
				'arguments' => $args,
			);
			if ($out['message'] === '') {
				$out['message'] = $this->defaultToolLeadIn($payload, $out['tool_call']);
			}
		} elseif ($tools && $out['message'] === '' && $this->isOpenRouter()) {
			// Free/routed models sometimes return an empty completion with no tool_calls.
			$out['message'] = __(
				'The free model did not return a usable reply. Try again, or set SLN_AI_LLM_MODEL to a specific model that supports tools (e.g. a :free model listed on OpenRouter).',
				'salon-booking-system'
			);
		}

		return $out;
	}

	/**
	 * Fetch usage / credit balance from the cloud proxy.
	 *
	 * @return array|WP_Error
	 */
	public function fetchUsage()
	{
		$url  = $this->getBaseUrl() . '/usage';
		$args = array(
			'timeout'   => 8,
			'sslverify' => true,
			'headers'   => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'      => wp_json_encode(array('auth' => SLN_AI_Usage::authPayload())),
		);

		$response = wp_remote_post($url, $args);
		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);
		if ($code < 200 || $code >= 300 || ! is_array($body)) {
			return new WP_Error(
				'sln_ai_usage_unavailable',
				__('AI usage service is unavailable.', 'salon-booking-system'),
				array('status' => $code ? $code : 503)
			);
		}

		return $body;
	}

	/**
	 * Create a DodoPayments checkout session via the cloud proxy.
	 *
	 * @param string $packId
	 * @param string $returnUrl
	 * @return array|WP_Error
	 */
	public function createCheckout($packId, $returnUrl)
	{
		$url  = $this->getBaseUrl() . '/checkout';
		$args = array(
			'timeout'   => 30,
			'sslverify' => true,
			'headers'   => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'      => wp_json_encode(
				array(
					'auth'       => SLN_AI_Usage::authPayload(),
					'pack_id'    => (string) $packId,
					'return_url' => (string) $returnUrl,
				)
			),
		);

		$response = wp_remote_post($url, $args);
		if (is_wp_error($response)) {
			return new WP_Error(
				'sln_ai_checkout_unavailable',
				__(
					'Credit purchase is not available yet. Please try again later or contact support.',
					'salon-booking-system'
				),
				array('status' => 503)
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);
		if ($code < 200 || $code >= 300 || ! is_array($body)) {
			$msg = is_array($body) && ! empty($body['message'])
				? (string) $body['message']
				: __(
					'Credit purchase is not available yet. Please try again later or contact support.',
					'salon-booking-system'
				);

			return new WP_Error(
				'sln_ai_checkout_unavailable',
				$msg,
				array('status' => $code ? $code : 503)
			);
		}

		return $body;
	}

	/**
	 * @param array $payload
	 * @return array
	 */
	private function withAuth(array $payload)
	{
		$payload['auth'] = SLN_AI_Usage::authPayload();

		return $payload;
	}

	/**
	 * @param mixed $body
	 * @return WP_Error
	 */
	private function quotaErrorFromBody($body)
	{
		$usage = new SLN_AI_Usage();
		$raw   = (is_array($body) && ! empty($body['usage']) && is_array($body['usage']))
			? $body['usage']
			: array();
		$raw['source']             = 'proxy';
		$raw['purchase_available'] = true;
		$normalized                = $usage->normalize($raw);
		$msg                       = is_array($body) && ! empty($body['message'])
			? (string) $body['message']
			: __(
				'You have used all included AI queries for this month. Buy credits to continue.',
				'salon-booking-system'
			);

		return new WP_Error(
			'sln_ai_quota_exceeded',
			$msg,
			array(
				'status' => 402,
				'usage'  => $normalized,
				'packs'  => array_values(SLN_AI_Usage::packs()),
			)
		);
	}

	/**
	 * Localized short lead-in when the model returns a tool call with empty text.
	 *
	 * @param array $payload
	 * @param array $toolCall
	 * @return string
	 */
	private function defaultToolLeadIn(array $payload, array $toolCall)
	{
		$userMsg = isset($payload['message']) ? (string) $payload['message'] : '';
		$lang    = SLN_AI_Language::detect($userMsg);
		$name    = isset($toolCall['name']) ? (string) $toolCall['name'] : '';
		$guidance = in_array(
			$name,
			array(
				'explain_setting',
				'explain_unavailable_slot',
				'find_booking',
				'count_bookings',
				'find_discount',
				'find_customer',
				'find_service',
				'find_assistant',
				'inspect_availabilities',
			),
			true
		);
		// Guidance replies are built from the tool summary in the REST controller;
		// keep model text empty so a wrong-language lead-in is never shown.
		if ($guidance) {
			return '';
		}

		return SLN_AI_Language::phrase(
			$lang,
			'confirm_lead',
			__('I put together a change from what you told me. Please review and confirm.', 'salon-booking-system')
		);
	}

	/**
	 * Conversational local engine: multi-turn, clarifying questions, fuzzy days.
	 *
	 * @param array $payload
	 * @return array
	 */
	private function mockChat(array $payload)
	{
		$raw     = isset($payload['message']) ? trim((string) $payload['message']) : '';
		$message = strtolower($raw);
		$context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : array();
		$draft   = isset($payload['draft']) && is_array($payload['draft']) ? $payload['draft'] : array();
		$lang    = SLN_AI_Language::detect($raw);

		if ($message === '') {
			return array(
				'message'   => $this->mockMsg(
					'en',
					'empty_prompt',
					__('What would you like to set up? Hours, holidays, notifications, payment behaviour, pages, services, assistants — or ask where to paste API keys.', 'salon-booking-system')
				),
				'tool_call' => null,
				'draft'     => $draft ? $draft : null,
			);
		}

		// Follow-up on the last availability diagnosis (“what does full mean?”).
		$lastGuidance = isset($payload['last_guidance']) && is_array($payload['last_guidance'])
			? $payload['last_guidance']
			: null;
		if ($lastGuidance && $this->mockIsAvailabilityFollowUp($raw)) {
			return array(
				'message'   => SLN_AI_Tools_ExplainUnavailableSlot::explainCapacityFollowUp($lastGuidance, $lang),
				'tool_call' => null,
				'draft'     => $draft ? $draft : null,
			);
		}

		// Short refinements of the previous guidance (“e alle 15?”, “where do I enable it?”).
		if ($lastGuidance) {
			$refined = $this->mockRefineFromLastGuidance($raw, $lastGuidance, $context, $lang, $draft);
			if ($refined) {
				return $refined;
			}
		}

		// Resume a tool waiting for Multi-shop location.
		if (! empty($draft['pending_tool']) && is_array($draft['pending_tool'])) {
			$shopArgs = $this->mockExtractShop($raw, $context);
			if ($shopArgs) {
				$pendingName = isset($draft['pending_tool']['name']) ? $draft['pending_tool']['name'] : '';
				$pendingArgs = isset($draft['pending_tool']['arguments']) && is_array($draft['pending_tool']['arguments'])
					? $draft['pending_tool']['arguments']
					: array();
				$merged      = array_merge($pendingArgs, $shopArgs);

				return array(
					'message'   => $this->mockMsg($lang, 'shop_got_it', __('Got it — using that shop. Please review the preview.', 'salon-booking-system')),
					'tool_call' => array(
						'name'      => $pendingName,
						'arguments' => $merged,
					),
					'draft'     => null,
				);
			}
		}

		// Opening-hours timetable (before catalog lookups so “check assistant
		// availability rules” / “change opening hours” is not a staff search).
		$parsedHours = $this->mockParseOpeningHours($raw);
		if ($parsedHours) {
			if ($this->mockIsInspectAvailabilities($raw)) {
				return $this->mockAttachShopOrAsk(
					'inspect_availabilities',
					$parsedHours,
					$context,
					$raw,
					$this->mockMsg($lang, 'check_details', __('I’ll check what I can with the details you provided.', 'salon-booking-system'))
				);
			}

			return $this->mockAttachShopOrAsk(
				'set_salon_availabilities',
				$parsedHours,
				$context,
				$raw,
				$this->mockMsg($lang, 'hours_preview', __('Got it — here’s what I understood. Review the preview and confirm to apply.', 'salon-booking-system'))
			);
		}

		// Read-only shop + assistant + service hours audit (no new timetable).
		if ($this->mockIsInspectAvailabilities($raw)) {
			return $this->mockAttachShopOrAsk(
				'inspect_availabilities',
				array(),
				$context,
				$raw,
				$this->mockMsg($lang, 'check_details', __('I’ll check what I can with the details you provided.', 'salon-booking-system'))
			);
		}

		// Slot availability diagnosis (read-only).
		$slotDiag = $this->mockParseUnavailableSlot($raw, $context);
		if ($slotDiag) {
			$args = isset($slotDiag['arguments']) ? $slotDiag['arguments'] : array();

			return $this->mockAttachShopOrAsk(
				'explain_unavailable_slot',
				$args,
				$context,
				$raw,
				$this->mockMsg($lang, 'check_details', __('I’ll check what I can with the details you provided.', 'salon-booking-system'))
			);
		}

		// Discount lookup / list (read-only) — before booking create/find.
		$discountLookup = $this->mockParseFindDiscount($raw);
		if ($discountLookup !== null) {
			return array(
				'message'   => $this->mockMsg(
					$lang,
					'find_discount',
					__('I’ll check your discounts / coupons.', 'salon-booking-system')
				),
				'tool_call' => array(
					'name'      => 'find_discount',
					'arguments' => $discountLookup,
				),
				'draft'     => null,
			);
		}

		// Customer lookup / list (read-only).
		$customerLookup = $this->mockParseFindCustomer($raw);
		if ($customerLookup !== null) {
			return array(
				'message'   => $this->mockMsg(
					$lang,
					'find_customer',
					__('I’ll look up that customer.', 'salon-booking-system')
				),
				'tool_call' => array(
					'name'      => 'find_customer',
					'arguments' => $customerLookup,
				),
				'draft'     => null,
			);
		}

		// Service / assistant lookup (read-only).
		$serviceLookup = $this->mockParseFindService($raw);
		if ($serviceLookup !== null) {
			return array(
				'message'   => $this->mockMsg(
					$lang,
					'find_service',
					__('I’ll check your services.', 'salon-booking-system')
				),
				'tool_call' => array(
					'name'      => 'find_service',
					'arguments' => $serviceLookup,
				),
				'draft'     => null,
			);
		}

		$assistantLookup = $this->mockParseFindAssistant($raw);
		if ($assistantLookup !== null) {
			return array(
				'message'   => $this->mockMsg(
					$lang,
					'find_assistant',
					__('I’ll check your assistants.', 'salon-booking-system')
				),
				'tool_call' => array(
					'name'      => 'find_assistant',
					'arguments' => $assistantLookup,
				),
				'draft'     => null,
			);
		}

		// Create / update reservation (confirm-first) — before find_booking.
		$bookingCreate = $this->mockParseCreateBooking($raw, $context);
		if ($bookingCreate) {
			if (! empty($bookingCreate['_ask'])) {
				return array(
					'message'   => $bookingCreate['_ask'],
					'tool_call' => null,
					'draft'     => null,
				);
			}

			$batch = ! empty($bookingCreate['_batch']);
			unset($bookingCreate['_batch']);
			$toolName = $batch ? 'create_bookings' : 'create_booking';
			$msgKey   = $batch ? 'create_bookings' : 'create_booking';
			$msgFb    = $batch
				? __('I’ll prepare those bookings. Review and confirm to save.', 'salon-booking-system')
				: __('I’ll prepare a new booking. Review and confirm to save.', 'salon-booking-system');

			return array(
				'message'   => $this->mockMsg($lang, $msgKey, $msgFb),
				'tool_call' => array(
					'name'      => $toolName,
					'arguments' => $bookingCreate,
				),
				'draft'     => null,
			);
		}

		$bookingUpdate = $this->mockParseUpdateBooking($raw, $context);
		if ($bookingUpdate) {
			if (! empty($bookingUpdate['_ask'])) {
				return array(
					'message'   => $bookingUpdate['_ask'],
					'tool_call' => null,
					'draft'     => null,
				);
			}

			return array(
				'message'   => $this->mockMsg(
					$lang,
					'update_booking',
					__('I’ll prepare that booking change. Review and confirm to save.', 'salon-booking-system')
				),
				'tool_call' => array(
					'name'      => 'update_booking',
					'arguments' => $bookingUpdate,
				),
				'draft'     => null,
			);
		}

		// Booking totals (“how many / quante prenotazioni…”) — before find_booking.
		$bookingCount = $this->mockParseCountBookings($raw);
		if ($bookingCount !== null) {
			return array(
				'message'   => $this->mockMsg(
					$lang,
					'count_bookings',
					__('I’ll count your bookings.', 'salon-booking-system')
				),
				'tool_call' => array(
					'name'      => 'count_bookings',
					'arguments' => $bookingCount,
				),
				'draft'     => null,
			);
		}

		// Attendance / no-show updates are not supported by any tool: say so honestly
		// instead of degrading into a booking lookup that returns “no results”.
		if ($this->mockDetectAttendanceUpdate($raw)) {
			SLN_AI_Telemetry::recordMiss($raw, $lang, 'unsupported_action');

			return array(
				'message'   => $this->mockMsg(
					$lang,
					'attendance_not_supported',
					__('I can’t update attendance or no-show statuses yet — not even in bulk. You can mark each booking as no-show from the Bookings list or the calendar: %s', 'salon-booking-system'),
					array(admin_url('edit.php?post_type=sln_booking'))
				),
				'tool_call' => null,
				'draft'     => $draft ? $draft : null,
			);
		}

		// Booking lookup (includes draft / ERROR).
		$bookingLookup = $this->mockParseFindBooking($raw);
		if ($bookingLookup) {
			return array(
				'message'   => $this->mockMsg($lang, 'find_booking', __('I’ll look up that booking (including draft and ERROR statuses).', 'salon-booking-system')),
				'tool_call' => array(
					'name'      => 'find_booking',
					'arguments' => $bookingLookup,
				),
				'draft'     => null,
			);
		}

		// Goal-based feature/add-on suggestions (waitlist, kiosk, multi-shop, “what can I do”…).
		$capQuery = SLN_AI_CapabilityCatalog::detectIntentQuery($raw);
		if ($capQuery) {
			$topics = SLN_AI_Tools_ExplainSetting::topics();
			$wantsWhere = (bool) preg_match(
				'/\b(where|dove|open|apri|configure|configura|settings|impostazioni|sezione|pagina)\b/i',
				$raw
			);
			if ($wantsWhere && isset($topics[ $capQuery ])) {
				return array(
					'message'   => $this->mockMsg($lang, 'here_configure', __('Here’s where to configure that:', 'salon-booking-system')),
					'tool_call' => array(
						'name'      => 'explain_setting',
						'arguments' => array('topic' => $capQuery),
					),
					'draft'     => null,
				);
			}

			return array(
				'message'   => $this->mockMsg(
					$lang,
					'capability_lead',
					__('I’ll match that to the right feature or add-on.', 'salon-booking-system')
				),
				'tool_call' => array(
					'name'      => 'suggest_capability',
					'arguments' => array(
						'query' => $capQuery,
						'limit' => 5,
					),
				),
				'draft'     => null,
			);
		}

		// Guidance for sensitive / high-risk domains.
		$guidanceTopic = $this->mockDetectGuidanceTopic($message);
		if ($guidanceTopic) {
			return array(
				'message'   => $this->mockMsg($lang, 'here_configure', __('Here’s where to configure that:', 'salon-booking-system')),
				'tool_call' => array(
					'name'      => 'explain_setting',
					'arguments' => array('topic' => $guidanceTopic),
				),
				'draft'     => null,
			);
		}

		// Salon identity.
		if (preg_match('/\b(salon name|my (salon|shop) is called|rename (the )?salon|set (the )?salon name)\b/i', $raw)
			|| preg_match('/\b(email|phone|address)\b.*\b(salon|shop)\b/i', $message)
		) {
			$values = array();
			if (preg_match('/(?:salon|shop)\s+(?:name\s+)?(?:is|to|:)\s*[\"\']?([^\"\'\n,.]+)/i', $raw, $m)) {
				$values['gen_name'] = trim($m[1]);
			}
			if (preg_match('/\bemail\s*(?:is|to|:)\s*([^\s,]+@[^\s,]+)/i', $raw, $m)) {
				$values['gen_email'] = trim($m[1]);
			}
			if (preg_match('/\bphone\s*(?:is|to|:)\s*([+\d][\d\s\-()]{5,})/i', $raw, $m)) {
				$values['gen_phone'] = trim($m[1]);
			}
			if (preg_match('/\baddress\s*(?:is|to|:)\s*(.+)$/i', $raw, $m)) {
				$values['gen_address'] = trim($m[1]);
			}
			if ($values) {
				return array(
					'message'   => $this->mockMsg($lang, 'update_identity', __('I’ll update your salon identity. Confirm the preview to apply.', 'salon-booking-system')),
					'tool_call' => array(
						'name'      => 'set_salon_identity',
						'arguments' => array('values' => $values),
					),
					'draft'     => null,
				);
			}
		}

		// Create service: "create service Taglio 30 euro 30 min"
		// Never treat availability questions (“why isn’t X bookable…”) as service creates.
		if (! $this->isAvailabilityQuestion($raw)
			&& preg_match('/\b(create|add|new|crea|aggiungi|nouveau|crear|erstellen)\b.*\b(service|servizio|servicio|dienstleistung)\b/i', $raw)
		) {
			$name = null;
			if (preg_match('/\b(?:service|servizio|servicio|dienstleistung)\s+[\"\']?([A-Za-zÀ-ÿ][\w\s\-]{1,40})/iu', $raw, $m)) {
				$name = $this->mockTrimEntityName($m[1]);
				$name = preg_replace('/\s+\d+.*$/', '', $name);
				$name = $this->mockStripNameConnectors($name);
			}
			if ($name) {
				$args = array('name' => $name);
				if (preg_match('/(\d+[.,]?\d*)\s*(€|eur|euro|\$)/i', $raw, $m)) {
					$args['price'] = (float) str_replace(',', '.', $m[1]);
				}
				if (preg_match('/(\d+)\s*(min|minutes|mins|minuti)\b/i', $raw, $m)) {
					$args['duration'] = $m[1];
				}

				return array(
					'message'   => $this->mockMsg($lang, 'create_service', __('I’ll create/update that service. Confirm the preview to apply.', 'salon-booking-system')),
					'tool_call' => array(
						'name'      => 'upsert_service',
						'arguments' => $args,
					),
					'draft'     => null,
				);
			}
		}

		// Create assistant — require an explicit create verb (not “assistente 2 … non prenotabile”).
		if (! $this->isAvailabilityQuestion($raw)
			&& preg_match('/\b(create|add|new|crea|aggiungi|nouveau|crear|erstellen)\b.*\b(assistant|attendant|staff|stylist|assistente|operatore)\b/i', $raw)
		) {
			$name = null;
			if (preg_match('/\b(?:assistant|attendant|staff|stylist|assistente|operatore)\s+[\"\']?([A-Za-zÀ-ÿ0-9][\w\s\-]{0,40})/iu', $raw, $m)) {
				$name = $this->mockTrimEntityName($m[1]);
				$name = $this->mockStripNameConnectors($name);
			}
			if ($name) {
				return array(
					'message'   => $this->mockMsg($lang, 'create_assistant', __('I’ll create/update that assistant. Confirm the preview to apply.', 'salon-booking-system')),
					'tool_call' => array(
						'name'      => 'upsert_assistant',
						'arguments' => array('name' => $name),
					),
					'draft'     => null,
				);
			}
		}

		// Show holidays.
		if (preg_match('/\b(show|list|current|what are|mostra|elenca|mostrar|afficher|zeigen)\b.*\b(holidays?|festiv|feriados?|feiertage)\b/i', $message)
			|| preg_match('/\b(my holidays|mie chiusure|mis festivos)\b/i', $message)
		) {
			$summary = isset($context['holidays_summary'])
				? $context['holidays_summary']
				: $this->mockMsg($lang, 'none', __('(none)', 'salon-booking-system'));

			return array(
				'message'   => $this->mockMsg($lang, 'current_holidays', __('Here are your current holiday rules:', 'salon-booking-system')) . "\n\n" . $summary,
				'tool_call' => null,
				'draft'     => null,
			);
		}

		// Clear holidays.
		if (preg_match('/\b(clear|remove|delete|reset|elimina|cancella|borrar|effacer|löschen)\b.*\b(holidays?|festiv|feriados?|feiertage)\b/i', $message)
			|| preg_match('/\b(no holidays|nessuna festivit|sin festivos)\b/i', $message)
		) {
			return $this->mockAttachShopOrAsk(
				'set_salon_holidays',
				array(
					'mode'    => 'replace_all',
					'summary' => __('Clear all holidays', 'salon-booking-system'),
					'rules'   => array(),
				),
				$context,
				$raw,
				$this->mockMsg($lang, 'clear_holidays', __('I’ll remove all holiday rules. Confirm the preview to apply.', 'salon-booking-system'))
			);
		}

		// Holiday / closure intent.
		$holidayParsed = $this->mockParseHolidays($raw, $context);
		if ($holidayParsed) {
			return $this->mockAttachShopOrAsk(
				'set_salon_holidays',
				$holidayParsed,
				$context,
				$raw,
				$this->mockMsg($lang, 'holiday_preview', __('Got it — here’s the holiday closure I understood. Review and confirm to apply.', 'salon-booking-system'))
			);
		}

		// Show current hours.
		if (preg_match('/\b(show|current|what are|list|mostra|mostrar|afficher|zeigen)\b.*\b(hours|schedule|opening|availabilit|orari|horarios|horaires|öffnungszeiten|oeffnungszeiten)\b/i', $message)
			|| preg_match('/\b(show my|my current|i miei orari|mis horarios)\b/i', $message)
		) {
			$summary = isset($context['availabilities_summary'])
				? $context['availabilities_summary']
				: $this->mockMsg($lang, 'no_hours', __('No opening hours configured yet.', 'salon-booking-system'));
			if (! empty($context['shops_detail']) && is_array($context['shops_detail'])) {
				$bits = array($summary, '', $this->mockMsg($lang, 'per_shop', __('Per shop:', 'salon-booking-system')));
				foreach ($context['shops_detail'] as $row) {
					$bits[] = (isset($row['name']) ? $row['name'] : '') . ':';
					$bits[] = isset($row['availabilities_summary']) ? $row['availabilities_summary'] : '';
					$bits[] = '';
				}
				$summary = implode("\n", $bits);
			}

			return array(
				'message'   => $this->mockMsg($lang, 'current_hours', __('Here are your current opening hours:', 'salon-booking-system')) . "\n\n" . $summary,
				'tool_call' => null,
				'draft'     => null,
			);
		}

		// Merge draft: previous days waiting for times, or times waiting for days.
		if (! empty($draft['pending_days']) && $this->messageHasTimes($raw)) {
			$intervals = $this->extractIntervals($raw);
			if ($intervals) {
				$days = array_values(array_diff($draft['pending_days'], $this->extractClosedDays($raw)));
				if ($days) {
					return $this->mockToolFromDaysIntervals(
						$days,
						$intervals,
						isset($draft['utterance']) ? $draft['utterance'] . ' / ' . $raw : $raw,
						$context,
						$raw
					);
				}
			}
		}

		if (! empty($draft['pending_intervals']) && $this->messageHasDays($raw)) {
			$days = $this->extractDayRangeOrList($raw);
			$days = array_values(array_diff($days, $this->extractClosedDays($raw)));
			if ($days) {
				return $this->mockToolFromDaysIntervals(
					$days,
					$draft['pending_intervals'],
					isset($draft['utterance']) ? $draft['utterance'] . ' / ' . $raw : $raw,
					$context,
					$raw
				);
			}
		}

		// Ambiguous “late”.
		if (preg_match('/\b(until\s+late|open\s+late|closes?\s+late|fino a tardi|hasta tarde)\b/i', $raw)
			&& ! $this->messageHasTimes($raw)
		) {
			return array(
				'message'   => $this->mockMsg($lang, 'ask_closing', __('Sure — what closing time should I use? For example 20:00 or 8pm.', 'salon-booking-system')),
				'tool_call' => null,
				'draft'     => array_filter(
					array(
						'pending_days' => isset($draft['pending_days']) ? $draft['pending_days'] : null,
						'utterance'    => $raw,
					)
				),
			);
		}

		// Days without times → ask for hours (natural follow-up).
		$daysOnly = $this->extractDayRangeOrList($raw);
		$closed   = $this->extractClosedDays($raw);
		$daysOnly = array_values(array_diff($daysOnly, $closed));
		if ($daysOnly && ! $this->messageHasTimes($raw)) {
			$label = $this->formatDaysLabel($daysOnly);

			return array(
				'message'   => $this->mockMsg(
					$lang,
					'ask_hours',
					__('Perfect — open %s. What hours? You can say “9 to 18” or “9am–6pm”. (Mention a lunch break only if you want two shifts.)', 'salon-booking-system'),
					array($label)
				),
				'tool_call' => null,
				'draft'     => array(
					'pending_days' => $daysOnly,
					'utterance'    => $raw,
				),
			);
		}

		// Times without days → ask which days.
		if ($this->messageHasTimes($raw) && ! $this->messageHasDays($raw)) {
			$intervals = $this->extractIntervals($raw);
			if ($intervals) {
				return array(
					'message'   => $this->mockMsg($lang, 'ask_days', __('Noted those hours. Which days should they apply to? For example “Monday to Saturday” or “weekdays”.', 'salon-booking-system')),
					'tool_call' => null,
					'draft'     => array(
						'pending_intervals' => $intervals,
						'utterance'         => $raw,
					),
				);
			}
		}

		// Q&A about existing rules.
		if (preg_match('/\?/', $raw)
			|| preg_match('/\b(am i|are we|is (the )?salon)\b.*\bclosed\b/i', $message)
		) {
			$summary  = isset($context['availabilities_summary']) ? $context['availabilities_summary'] : '';
			$dayQuery = $this->detectQueriedClosedDay($message);
			if ($dayQuery) {
				$hasDay = (bool) preg_match('/\b' . preg_quote($dayQuery['label'], '/') . '/i', $summary);

				return array(
					'message'   => $hasDay
						? sprintf(
							/* translators: %s: weekday */
							__('Looking at your current rules, %s is included as an open day.', 'salon-booking-system'),
							$dayQuery['full']
						)
						: sprintf(
							/* translators: %s: weekday */
							__('Looking at your current rules, %s is not an open day.', 'salon-booking-system'),
							$dayQuery['full']
						),
					'tool_call' => null,
					'draft'     => $draft ? $draft : null,
				);
			}
		}

		// Holiday intent without a parseable date → ask.
		if (preg_match('/\b(holiday|holidays|closed on|vacation|bank holiday)\b/i', $raw)
			&& ! preg_match('/\bclosed\s+(on\s+)?(sundays?|saturdays?|mondays?|tuesdays?|wednesdays?|thursdays?|fridays?)\b/i', $raw)
		) {
			return array(
				'message'   => $this->mockMsg(
					$lang,
					'ask_holiday_dates',
					__('Which dates should be closed? For example “25 December”, “August 10 to August 20”, or “Christmas Day”.', 'salon-booking-system')
				),
				'tool_call' => null,
				'draft'     => $draft ? $draft : null,
			);
		}

		// Nothing matched: log the miss so parsing/catalog gaps surface with real data.
		if (class_exists('SLN_AI_Telemetry')) {
			SLN_AI_Telemetry::recordMiss($raw, $lang, 'mock_fallback');
		}

		return array(
			'message'   => $this->mockMsg(
				$lang,
				'fallback_help',
				__(
					'Tell me what to configure — opening hours, holidays, notifications, payment behaviour, pages, services/assistants — or ask where to paste payment/SMS/OAuth secrets.',
					'salon-booking-system'
				)
			),
			'tool_call' => null,
			'draft'     => $draft ? $draft : null,
		);
	}

	/**
	 * Continue the previous guidance turn instead of restarting intent detection.
	 *
	 * Handles two conversational follow-ups:
	 * - after an availability diagnosis: a bare new time/date (“e alle 15?”, “and the 28th?”)
	 *   re-runs the diagnosis with the previous arguments merged;
	 * - after a capability suggestion: “where do I enable/configure it?” deep-links the
	 *   top suggestion via explain_setting.
	 *
	 * @param string $raw
	 * @param array  $lastGuidance
	 * @param array  $context
	 * @param string $lang
	 * @param array  $draft
	 * @return array|null Chat response, or null when the message is not a refinement.
	 */
	private function mockRefineFromLastGuidance($raw, array $lastGuidance, array $context, $lang, $draft)
	{
		$tool   = isset($lastGuidance['tool']) ? (string) $lastGuidance['tool'] : '';
		$folded = strtolower(preg_replace('/[`\'"^~]/', '', SLN_AI_Language::fold((string) $raw)));
		$len    = function_exists('mb_strlen') ? mb_strlen(trim($raw)) : strlen(trim($raw));

		if ($tool === 'suggest_capability' && $len <= 80 && preg_match(
			'/\b(dove|where|donde|ou|wo)\b.{0,40}\b(configur\w*|attiv\w*|abilit\w*|enable|set\s?up|turn\s(?:it\s)?on|trov\w*|find|activ\w*|einricht\w*|aktivier\w*)/i',
			$folded
		)) {
			$topic = '';
			if (! empty($lastGuidance['hits'][0]['topic'])) {
				$topic = sanitize_key((string) $lastGuidance['hits'][0]['topic']);
			}
			$topics = SLN_AI_Tools_ExplainSetting::topics();
			if ($topic !== '' && isset($topics[ $topic ])) {
				return array(
					'message'   => $this->mockMsg($lang, 'here_configure', __('Here’s where to configure that:', 'salon-booking-system')),
					'tool_call' => array(
						'name'      => 'explain_setting',
						'arguments' => array('topic' => $topic),
					),
					'draft'     => null,
				);
			}
		}

		if ($tool === 'explain_unavailable_slot' && $len <= 40) {
			$prevArgs = isset($lastGuidance['arguments']) && is_array($lastGuidance['arguments'])
				? $lastGuidance['arguments']
				: array();
			$newTime = null;
			$newDate = null;

			// Bare time: “e alle 15?”, “and at 3pm?”, “alle 16:30?”.
			if (preg_match(
				'/^\s*(?:e|ed|and|y|et|o|or|oppure)?\s*(?:se\s+|what about\s+|che ne dici\s+(?:delle\s+)?)?(?:alle|at|a las|as|um|a|à)?\s*(\d{1,2}(?:[:.]\d{2})?\s*(?:[ap]m)?)\s*\??\s*$/iu',
				$folded,
				$m
			)) {
				$newTime = $this->normTime(str_replace(array('.', ' '), array(':', ''), trim($m[1])));
			} elseif (preg_match(
				// Bare day, month optional (“e il 28?”, “il 28 agosto?”, “on the 28th?”).
				'/^\s*(?:e|ed|and|y|et)?\s*(?:il|on(?:\sthe)?|el|le|am)?\s*(\d{1,2})(?:st|nd|rd|th)?(?:\s+(?:de\s+)?([a-z]{3,}))?\s*\??\s*$/iu',
				$folded,
				$m
			)) {
				$day = (int) $m[1];
				if ($day >= 1 && $day <= 31) {
					$year = isset($context['today']) ? (int) substr($context['today'], 0, 4) : (int) gmdate('Y');
					if (! empty($m[2])) {
						$newDate = $this->parseFlexibleDate($day . ' ' . $m[2], $year);
					} elseif (! empty($prevArgs['date']) && preg_match('/^\d{4}-\d{2}$/', substr($prevArgs['date'], 0, 7))) {
						// Borrow month/year from the previous diagnosis.
						$newDate = substr($prevArgs['date'], 0, 8) . sprintf('%02d', $day);
					}
				}
			}

			if ($newTime || $newDate) {
				$args = $prevArgs;
				unset($args['_user_message']);
				if ($newTime) {
					$args['time'] = $newTime;
				}
				if ($newDate) {
					$args['date'] = $newDate;
				}

				return $this->mockAttachShopOrAsk(
					'explain_unavailable_slot',
					$args,
					$context,
					$raw,
					$this->mockMsg($lang, 'check_details', __('I’ll check what I can with the details you provided.', 'salon-booking-system'))
				);
			}
		}

		return null;
	}

	/**
	 * True when the merchant asks to clarify the previous availability answer.
	 *
	 * @param string $text
	 * @return bool
	 */
	private function mockIsAvailabilityFollowUp($text)
	{
		$folded = strtolower(SLN_AI_Language::fold((string) $text));
		$asksExplain = (bool) preg_match(
			'/\b('
			. 'spiega|explain|clarify|meglio|more detail|piu dettagli|più dettagli'
			. '|cosa vuol dire|che significa|what does|what mean|que significa|que veut dire'
			. ')\b/i',
			$folded
		);
		$mentionsFull = (bool) preg_match(
			'/\b('
			. 'al completo|attualmente completo|currently full|at capacity|pieno'
			. '|units? per hour|unita per ora|unità per ora'
			. ')\b/i',
			$folded
		);

		// Prefer explicit “explain …” intents; bare capacity words only on short follow-ups.
		return $asksExplain || ( $mentionsFull && strlen(trim((string) $text)) <= 160 );
	}

	/**
	 * True when the merchant asks to change attendance / no-show statuses —
	 * an action no whitelisted tool supports (single or bulk).
	 *
	 * @param string $text
	 * @return bool
	 */
	private function mockDetectAttendanceUpdate($text)
	{
		$folded = strtolower(SLN_AI_Language::fold((string) $text));

		$mentionsAttendance = (bool) preg_match(
			'/\b('
			. 'attendance|attended|no[\s-]?shows?'
			. '|presenz[ae]|presente|presenti|assenz[ae]|assente|assenti|non\s+presentat\w*'
			. '|asistencia|asistio|no\s+se\s+presento'
			. '|comparecimento|compareceu|nao\s+compareceu'
			. '|anwesenheit|erschienen|nicht\s+erschienen'
			. '|presence\s+client|absent\w*'
			. ')\b/i',
			$folded
		);
		if (! $mentionsAttendance) {
			return false;
		}

		return (bool) preg_match(
			'/\b('
			. 'mark|set|update|change|toggle|flag'
			. '|segna\w*|imposta|aggiorna|cambia|metti'
			. '|marca\w*|actualiza|atualiza|cambiar|poner'
			. '|marquer|mettre|changer'
			. '|markier\w*|setz\w*|andern|aktualisier\w*'
			. ')\b/i',
			$folded
		);
	}

	/**
	 * Parse “list/find services …” (Local Assistant).
	 *
	 * @param string $text
	 * @return array|null
	 */
	private function mockParseFindService($text)
	{
		$folded = SLN_AI_Language::fold($text);
		if (! preg_match(
			'/\b(find|list|show|check|which|what|trova|trovami|cerca|mostra|elenca|quali|buscar|mostrar|listar|trouver|lister|afficher|finden|zeige|liste)\b.{0,40}\b(service|services|servizio|servizi|servicio|servicios|dienstleistung)\b/i',
			$folded
		)) {
			return null;
		}
		// Avoid colliding with “service Haircut” unavailability questions
		// and with opening-hours / consistency checks.
		if (preg_match('/\b(unavailable|not available|non disponibile|perche|why)\b/i', $folded)
			|| $this->isHoursRulesIntent($text)
		) {
			return null;
		}

		return $this->mockParseCatalogFindArgs($text, 'service');
	}

	/**
	 * Parse “list/find assistants …” (Local Assistant).
	 *
	 * @param string $text
	 * @return array|null
	 */
	private function mockParseFindAssistant($text)
	{
		$folded = SLN_AI_Language::fold($text);
		if (! preg_match(
			'/\b(find|list|show|check|which|what|trova|trovami|cerca|mostra|elenca|quali|buscar|mostrar|listar|trouver|lister|afficher|finden|zeige|liste)\b.{0,40}\b(assistant|assistants|attendant|attendants|staff|assistente|assistenti|asistente|stylist)\b/i',
			$folded
		)) {
			return null;
		}
		if ($this->isHoursRulesIntent($text) || $this->isAvailabilityQuestion($text)) {
			return null;
		}

		return $this->mockParseCatalogFindArgs($text, 'assistant');
	}

	/**
	 * @param string $text
	 * @param string $kind service|assistant
	 * @return array
	 */
	private function mockParseCatalogFindArgs($text, $kind)
	{
		$folded = SLN_AI_Language::fold($text);
		$args   = array();
		$label  = $kind === 'assistant'
			? 'assistant|attendant|assistente|staff|id'
			: 'service|servizio|servicio|id';
		if (preg_match('/\b(?:' . $label . ')\s*#?\s*(\d{1,})\b/i', $folded, $m)
			|| preg_match('/\b#(\d{2,})\b/', $text, $m)
		) {
			$args['id'] = (int) $m[1];
		}
		if (preg_match('/\b(?:named?|called|per|pour|para|für)\s+[\'\"]?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'-]{1,40})/iu', $text, $m)) {
			$name = trim($m[1]);
			if ($name !== '') {
				$args['name']  = $name;
				$args['query'] = $name;
			}
		}

		return $args;
	}

	/**
	 * Parse “list/find customers …” (Local Assistant).
	 *
	 * @param string $text
	 * @return array|null
	 */
	private function mockParseFindCustomer($text)
	{
		$folded = SLN_AI_Language::fold($text);
		if (! preg_match(
			'/\b(find|list|show|check|which|what|trova|trovami|cerca|mostra|elenca|quali|buscar|mostrar|listar|trouver|lister|afficher|finden|zeige|liste)\b.{0,40}\b(customer|customers|client|clients|cliente|clienti|kunden)\b/i',
			$folded
		)) {
			return null;
		}

		$args = array();
		if (preg_match('/\b(?:customer|client|cliente|user|id)\s*#?\s*(\d{1,})\b/i', $folded, $m)
			|| preg_match('/\b#(\d{2,})\b/', $text, $m)
		) {
			$args['id'] = (int) $m[1];
		}
		if (preg_match('/\b([\w.+-]+@[\w.-]+\.\w+)\b/', $text, $m)) {
			$args['email'] = $m[1];
			$args['query'] = $m[1];
		}
		if (preg_match('/\b(?:named?|called|per|pour|para|für)\s+[\'\"]?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'-]{1,30}(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'-]{1,30})?)/iu', $text, $m)
			|| preg_match('/\b(?:customer|client|cliente)\s+[\'\"]?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'-]{1,30}(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'-]{1,30})?)/iu', $text, $m)
		) {
			$name = trim($m[1]);
			$name = preg_replace('/\b(customer|client|cliente|booking|prenotazione)\b/i', '', $name);
			$name = trim($name);
			if ($name !== '') {
				$args['name']  = $name;
				$args['query'] = isset($args['query']) ? $args['query'] : $name;
			}
		}

		return $args;
	}

	/**
	 * Parse “list/find discounts/coupons …” (Local Assistant).
	 *
	 * @param string $text
	 * @return array|null
	 */
	private function mockParseFindDiscount($text)
	{
		$folded = SLN_AI_Language::fold($text);
		if (! preg_match(
			'/\b(find|list|show|check|which|what|trova|trovami|cerca|mostra|elenca|quali|buscar|mostrar|listar|trouver|lister|afficher|finden|zeige|liste)\b.{0,40}\b(discount|discounts|coupon|coupons|sconto|sconti|codigo|codigo de descuento|codigo sconto|reduction|rabatt|gutschein)\b|\b(discount|discounts|coupon|coupons|sconto|sconti)\b.{0,20}\b(#?\d{1,}|\b[A-Z0-9_-]{3,}\b)/i',
			$folded
		)) {
			return null;
		}

		$args = array();
		if (preg_match('/\b(?:discount|coupon|sconto|id)\s*#?\s*(\d{1,})\b/i', $folded, $m)
			|| preg_match('/\b#(\d{2,})\b/', $text, $m)
		) {
			$args['id'] = (int) $m[1];
		}
		if (preg_match('/\b(?:code|codice|codigo|coupon)\s*[:=]?\s*[\'\"]?([A-Za-z0-9_-]{3,})\b/i', $text, $m)) {
			$args['code']  = $m[1];
			$args['query'] = $m[1];
		} elseif (preg_match('/\b([A-Z][A-Z0-9_-]{2,})\b/', $text, $m)
			&& ! preg_match('/\b(FIND|LIST|SHOW|CHECK|WHAT|WHICH|TROVA|CERCA|MOSTRA)\b/', $m[1])
		) {
			// Likely coupon code in ALL CAPS (e.g. SUMMER20).
			$args['code']  = $m[1];
			$args['query'] = $m[1];
		}

		return $args;
	}

	/**
	 * Parse “create/book a reservation …” (Local Assistant).
	 *
	 * @param string $text
	 * @param array  $context
	 * @return array|null
	 */
	private function mockParseCreateBooking($text, array $context)
	{
		$folded = SLN_AI_Language::fold($text);
		$lang   = SLN_AI_Language::detect($text);
		if (! preg_match(
			'/\b(create|book|add|schedule|make)\b.{0,40}\b(booking|appointment|reservation)s?\b|\b(crea|creami|prenota|nuova)\b.{0,40}\b(prenotazione|appuntamento|prenotazioni)\b|\b(crear|haz|reserva)\b.{0,40}\b(reserva|cita)s?\b|\b(creer|prendre)\b.{0,40}\b(rendez-vous|reservation)s?\b|\b(erstelle|buche)\b.{0,40}\b(termin|buchung)(en)?\b/i',
			$folded
		)) {
			return null;
		}

		$args       = $this->mockExtractBookingFields($text, $context);
		$batchDates = $this->mockExtractBookingDates($text);
		$recurrence = $this->mockParseBookingRecurrence($folded);
		$isBatch    = count($batchDates) >= 2 || $recurrence !== null;

		if ($isBatch) {
			if (count($batchDates) >= 2) {
				$args['dates'] = $batchDates;
				unset($args['date']);
			} elseif ($recurrence) {
				$args['recurrence'] = $recurrence;
				unset($args['date']);
			}
			$args['_batch'] = true;
		}

		$missing = array();
		if (! $isBatch && empty($args['date'])) {
			$missing[] = 'date (Y-m-d)';
		}
		if ($isBatch && empty($args['dates']) && empty($args['recurrence'])) {
			$missing[] = 'dates or recurrence';
		}
		if (empty($args['time'])) {
			$missing[] = 'time (H:i)';
		}
		if (empty($args['service_id']) && empty($args['service_name'])) {
			$missing[] = 'service';
		}
		if (empty($args['customer_name']) && empty($args['customer_first_name']) && empty($args['customer_email'])) {
			$missing[] = 'customer name or email';
		}
		if ($missing) {
			$askKey = $isBatch ? 'ask_create_bookings' : 'ask_create_booking';
			$askFb  = $isBatch
				? __('To create several bookings I need time, service, customer, and either dates or “next N Mondays” (etc.).', 'salon-booking-system')
				: __('To create a booking I need date, time, service, and customer name or email. For example: “Create a booking for Mario Rossi, Haircut, 2026-08-12 at 10:00”.', 'salon-booking-system');

			return array(
				'_ask' => SLN_AI_Language::phrase($lang, $askKey, $askFb),
			);
		}

		return $args;
	}

	/**
	 * All Y-m-d dates mentioned in free text.
	 *
	 * @param string $text
	 * @return string[]
	 */
	private function mockExtractBookingDates($text)
	{
		$dates = array();
		if (preg_match_all('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
			foreach ($m[1] as $d) {
				$dates[] = $d;
			}
		}

		return array_values(array_unique($dates));
	}

	/**
	 * Parse “next three mondays” / “3 lunedì” style recurrence (Local Assistant).
	 *
	 * @param string $folded Accent-folded lowercase-ish text
	 * @return array|null { weekday, count }
	 */
	private function mockParseBookingRecurrence($folded)
	{
		$weekdays = 'monday|mon|lunedi|lunes|lundi|montag|tuesday|tue|martedi|martes|mardi|dienstag|'
			. 'wednesday|wed|mercoledi|miercoles|mercredi|mittwoch|thursday|thu|giovedi|jueves|jeudi|donnerstag|'
			. 'friday|fri|venerdi|viernes|vendredi|freitag|saturday|sat|sabato|sabado|samedi|samstag|'
			. 'sunday|sun|domenica|domingo|dimanche|sonntag';
		$words = array(
			'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6,
			'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
			'due' => 2, 'tre' => 3, 'quattro' => 4, 'cinque' => 5, 'sei' => 6,
			'sette' => 7, 'otto' => 8, 'nove' => 9, 'dieci' => 10,
			'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
			'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5,
			'zwei' => 2, 'drei' => 3, 'vier' => 4, 'funf' => 5,
			'dois' => 2, 'tres' => 3, 'quatro' => 4, 'cinco' => 5,
		);
		$wordAlt = implode('|', array_keys($words));

		// next three mondays / prossimi 3 lunedi / the next 3 mondays
		if (preg_match(
			'/\b(?:next|prossimi?|proxim[oa]s?|prochains?|nachsten?|proximos?)\s+(?:(\d{1,2})|(' . $wordAlt . '))\s+(' . $weekdays . ')s?\b/i',
			$folded,
			$m
		)) {
			$count = ! empty($m[1]) ? (int) $m[1] : (isset($words[ strtolower($m[2]) ]) ? $words[ strtolower($m[2]) ] : 0);
			if ($count >= 2) {
				return array(
					'weekday' => strtolower($m[3]),
					'count'   => min(10, $count),
				);
			}
		}

		// three mondays / 3 lunedi / tre lunedi
		if (preg_match(
			'/\b(?:(\d{1,2})|(' . $wordAlt . '))\s+(' . $weekdays . ')s?\b/i',
			$folded,
			$m
		)) {
			$count = ! empty($m[1]) ? (int) $m[1] : (isset($words[ strtolower($m[2]) ]) ? $words[ strtolower($m[2]) ] : 0);
			if ($count >= 2) {
				return array(
					'weekday' => strtolower($m[3]),
					'count'   => min(10, $count),
				);
			}
		}

		// three reservations … monday (weekday elsewhere)
		if (preg_match(
			'/\b(?:(\d{1,2})|(' . $wordAlt . '))\s+(?:booking|appointment|reservation|prenotazione|prenotazioni|reserva|cita|termin|buchung)s?\b/i',
			$folded,
			$m
		) && preg_match('/\b(' . $weekdays . ')s?\b/i', $folded, $wm)) {
			$count = ! empty($m[1]) ? (int) $m[1] : (isset($words[ strtolower($m[2]) ]) ? $words[ strtolower($m[2]) ] : 0);
			if ($count >= 2) {
				return array(
					'weekday' => strtolower($wm[1]),
					'count'   => min(10, $count),
				);
			}
		}

		return null;
	}

	/**
	 * Parse “edit/move/reschedule booking #id …” (Local Assistant).
	 *
	 * @param string $text
	 * @param array  $context
	 * @return array|null
	 */
	private function mockParseUpdateBooking($text, array $context)
	{
		$folded = SLN_AI_Language::fold($text);
		$lang   = SLN_AI_Language::detect($text);
		if (! preg_match(
			'/\b(edit|update|change|move|reschedule|modify)\b.{0,50}\b(booking|appointment|reservation|#?\d{1,})\b|\b(modifica|aggiorna|sposta|cambia|riprogramma)\b.{0,50}\b(prenotazione|appuntamento|#?\d{1,})\b|\b(editar|actualizar|mover|cambiar)\b.{0,50}\b(reserva|cita|#?\d{1,})\b|\b(modifier|deplacer|reporter)\b.{0,50}\b(reservation|rendez-vous|#?\d{1,})\b|\b(bearbeiten|verschiebe|aendere)\b.{0,50}\b(termin|buchung|#?\d{1,})\b/i',
			$folded
		)) {
			return null;
		}

		$args = $this->mockExtractBookingFields($text, $context);
		$id   = 0;
		if (preg_match('/\b(?:booking|appointment|reservation|prenotazione|appuntamento|reserva|cita|termin|buchung|id)\s*#?\s*(\d{1,})\b/i', $folded, $m)
			|| preg_match('/\b#(\d{2,})\b/', $text, $m)
		) {
			$id = (int) $m[1];
		}
		if (! $id) {
			return array(
				'_ask' => SLN_AI_Language::phrase(
					$lang,
					'ask_update_booking_id',
					__('Which booking should I change? Give the booking id (e.g. #16), or ask me to find it first.', 'salon-booking-system')
				),
			);
		}
		$args['id'] = $id;

		$hasChange = ! empty($args['date']) || ! empty($args['time'])
			|| ! empty($args['service_id']) || ! empty($args['service_name'])
			|| ! empty($args['customer_name']) || ! empty($args['customer_first_name'])
			|| ! empty($args['customer_email']) || ! empty($args['customer_phone'])
			|| ! empty($args['status']);
		if (! $hasChange) {
			return array(
				'_ask' => SLN_AI_Language::phrase(
					$lang,
					'ask_update_booking_fields',
					__('What should I change on that booking — date, time, service, customer, or status?', 'salon-booking-system')
				),
			);
		}

		return $args;
	}

	/**
	 * Shared field extraction for create/update booking mocks.
	 *
	 * @param string $text
	 * @param array  $context
	 * @return array
	 */
	private function mockExtractBookingFields($text, array $context)
	{
		$args = array();
		if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
			$args['date'] = $m[1];
		}
		if (preg_match('/\b(\d{1,2}:\d{2})\s*(am|pm)?\b/i', $text, $m)) {
			$args['time'] = trim($m[1] . (! empty($m[2]) ? ' ' . $m[2] : ''));
		} elseif (preg_match('/\b(\d{1,2})\s*(am|pm)\b/i', $text, $m)) {
			$h = (int) $m[1];
			$ap = strtolower($m[2]);
			if ($ap === 'pm' && $h < 12) {
				$h += 12;
			} elseif ($ap === 'am' && $h === 12) {
				$h = 0;
			}
			$args['time'] = sprintf('%02d:00', $h);
		} elseif (preg_match('/\b(?:at|alle|a|à|um)\s+(\d{1,2})(?:[:.](\d{2}))?\b/i', $text, $m)) {
			$h = (int) $m[1];
			$i = isset($m[2]) ? (int) $m[2] : 0;
			$args['time'] = sprintf('%02d:%02d', $h, $i);
		}

		if (preg_match('/\b([\w.+-]+@[\w.-]+\.\w+)\b/', $text, $m)) {
			$args['customer_email'] = $m[1];
		}
		if (preg_match('/\b(?:for|customer|client|cliente|named?|per|pour|para|für)\s+[\'\"]?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'-]{1,30}(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'-]{1,30})?)/iu', $text, $m)) {
			$name = trim($m[1]);
			$name = preg_replace('/\b(booking|appointment|reservation|prenotazione|service|servizio)\b/i', '', $name);
			$name = trim($name);
			if ($name !== '') {
				$args['customer_name'] = $name;
			}
		}

		if (preg_match('/\b(confirmed|pending|paid|canceled|cancelled|confermata|in attesa|pagata|cancellata)\b/i', $text, $m)) {
			$st = strtolower($m[1]);
			if (in_array($st, array('confermata'), true)) {
				$st = 'confirmed';
			} elseif (in_array($st, array('in attesa'), true)) {
				$st = 'pending';
			} elseif (in_array($st, array('pagata'), true)) {
				$st = 'paid';
			} elseif (in_array($st, array('cancellata', 'cancelled'), true)) {
				$st = 'canceled';
			}
			$args['status'] = $st;
		}

		$svcName = $this->mockMatchNamedEntity($text, isset($context['services']) ? $context['services'] : array());
		if ($svcName) {
			$args['service_name'] = $svcName;
		}
		$attName = $this->mockMatchNamedEntity($text, isset($context['assistants']) ? $context['assistants'] : array());
		if ($attName) {
			$args['assistant_name'] = $attName;
		}

		return $args;
	}

	/**
	 * Match a catalog entity name mentioned in free text.
	 *
	 * @param string $text
	 * @param array  $entities list of {id,name} or strings
	 * @return string|null
	 */
	private function mockMatchNamedEntity($text, array $entities)
	{
		$folded = SLN_AI_Language::fold(mb_strtolower($text, 'UTF-8'));
		$best   = null;
		$bestLen = 0;
		foreach ($entities as $ent) {
			$name = '';
			if (is_array($ent)) {
				$name = isset($ent['name']) ? (string) $ent['name'] : (isset($ent['title']) ? (string) $ent['title'] : '');
			} else {
				$name = (string) $ent;
			}
			$name = trim($name);
			if ($name === '') {
				continue;
			}
			$needle = SLN_AI_Language::fold(mb_strtolower($name, 'UTF-8'));
			if ($needle !== '' && strpos($folded, $needle) !== false && strlen($needle) > $bestLen) {
				$best    = $name;
				$bestLen = strlen($needle);
			}
		}

		return $best;
	}

	/**
	 * Parse “how many / quante prenotazioni …” (Local Assistant).
	 *
	 * @param string $text
	 * @return array|null
	 */
	private function mockParseCountBookings($text)
	{
		$folded = SLN_AI_Language::fold($text);
		if (! preg_match(
			'/\b(how\s+many|quante|quanti|totale|total|number\s+of|count|cuantas|cuantos|combien|wie\s+viele|quantas|quantos)\b.{0,40}\b(booking|bookings|appointment|appointments|reservation|reservations|prenotazione|prenotazioni|appuntamento|appuntamenti|reserva|reservas|cita|citas|termin|buchung|buchungen)\b|\b(booking|bookings|prenotazioni|prenotazione|reservas|reservations)\b.{0,40}\b(fino\s+ad\s+oggi|until\s+today|up\s+to\s+today|to\s+date|collezionat|collected)\b/i',
			$folded
		)) {
			return null;
		}

		$args = array();
		try {
			$tz    = wp_timezone();
			$now   = new DateTimeImmutable('now', $tz);
			$today = $now->format('Y-m-d');
			if (preg_match('/\b(this\s+month|questo\s+mese|este\s+mes|ce\s+mois|diesen\s+monat)\b/i', $folded)) {
				$args['date_from'] = $now->modify('first day of this month')->format('Y-m-d');
				$args['date_to']   = $now->modify('last day of this month')->format('Y-m-d');
			} elseif (
				preg_match('/\b(today|oggi|hoy|aujourd.?hui|heute|hoje)\b/i', $folded)
				&& ! preg_match('/\b(fino\s+ad\s+oggi|until\s+today|up\s+to\s+today|to\s+date|hasta\s+hoy)\b/i', $folded)
			) {
				$args['date'] = $today;
			} else {
				// Default: all bookings up to today (covers “fino ad oggi” / bare “how many”).
				$args['date_to'] = $today;
			}
		} catch (Exception $e) {
			// leave empty — tool defaults
		}

		if (preg_match('/\b(draft|bozza|borrador|brouillon|entwurf)\b/i', $folded)) {
			$args['include_draft'] = true;
		}
		if (preg_match('/\b(error|errore)\b/i', $folded)) {
			$args['include_error'] = true;
		}

		return $args;
	}

	/**
	 * Parse “find/show booking …” (Local Assistant).
	 *
	 * @param string $text
	 * @return array|null
	 */
	private function mockParseFindBooking($text)
	{
		$folded = SLN_AI_Language::fold($text);
		if (! preg_match(
			'/\b(find|search|look\s*up|locate|show|where\s+is|open|trova|trovami|cerca|mostra|buscar|encontrar|trouver|chercher|finden|suche)\b.{0,60}\b(booking|appointment|reservation|prenotazione|prenotazioni|appuntamento|reserva|cita|termin|buchung)\b|\b(booking|appointment|reservation|prenotazione|appuntamento|reserva)\b.{0,20}\b(#?\d{2,}|\berror\b|\bdraft\b|\berrore\b|\bbozza\b|\bborrador\b|\bbrouillon\b)/i',
			$folded
		)) {
			return null;
		}

		$args = array();
		if (preg_match('/\b(?:booking|appointment|reservation|prenotazione|appuntamento|reserva|cita|termin|buchung|id)\s*#?\s*(\d{2,})\b/i', $folded, $m)
			|| preg_match('/\b#(\d{2,})\b/', $text, $m)
		) {
			$args['id'] = (int) $m[1];
		}
		if (preg_match('/\b([\w.+-]+@[\w.-]+\.\w+)\b/', $text, $m)) {
			$args['email'] = $m[1];
			$args['query'] = $m[1];
		}
		if (preg_match('/\b(?:for|customer|client|cliente|named?|per|pour|para|für)\s+[\'\"]?([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\s\'-]{1,40})/iu', $text, $m)) {
			$name = trim($m[1]);
			$name = preg_replace('/\b(booking|appointment|reservation|prenotazione|error|draft|errore|bozza)\b/i', '', $name);
			$name = trim($name);
			if ($name !== '') {
				$args['name']  = $name;
				$args['query'] = isset($args['query']) ? $args['query'] : $name;
			}
		}
		if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
			$args['date'] = $m[1];
		}
		if (preg_match('/\b(this\s+month|questo\s+mese|este\s+mes|ce\s+mois|diesen\s+monat)\b/i', $folded)) {
			try {
				$tz  = wp_timezone();
				$now = new DateTimeImmutable('now', $tz);
				$args['date_from'] = $now->modify('first day of this month')->format('Y-m-d');
				$args['date_to']   = $now->modify('last day of this month')->format('Y-m-d');
			} catch (Exception $e) {
				// ignore
			}
		}
		if (preg_match('/\b(error|errore|draft|bozza|borrador|brouillon|entwurf)\b/i', $folded, $m)) {
			$st = strtolower($m[1]);
			$args['status'] = in_array($st, array('errore', 'error'), true) ? 'error' : 'draft';
		}
		if (! $args) {
			if (preg_match('/\b(error|errore|draft|bozza|borrador|brouillon|entwurf)\b/i', $folded, $m)) {
				$st = strtolower($m[1]);
				$args['status'] = in_array($st, array('errore', 'error'), true) ? 'error' : 'draft';
			} else {
				return null;
			}
		}

		return $args;
	}

	/**
	 * Opening-hours / availability-rules wording (not a staff/service lookup).
	 *
	 * @param string $text
	 * @return bool
	 */
	private function isHoursRulesIntent($text)
	{
		$folded = strtolower(preg_replace('/[`\'"^~]/', '', SLN_AI_Language::fold((string) $text)));

		return (bool) preg_match(
			'/\b('
			. 'opening hours|orari di apertura|horaires d.?ouverture|horario[s]? de apertura|offnungszeiten'
			. '|availabilit\w*\s+rules|regole di disponibil|reglas de disponibil|regles de disponibil'
			. '|consistent|coeren\w*|alline\w*|align'
			. '|closed days?|giorni chius|jours ferm'
			. '|change.{0,50}(hours|orari|horaires|horarios)'
			. '|modifica.{0,50}orar'
			. ')\b/i',
			$folded
		);
	}

	/**
	 * “Check if assistant/service hours are consistent” without a new timetable.
	 *
	 * @param string $text
	 * @return bool
	 */
	private function mockIsInspectAvailabilities($text)
	{
		$folded = strtolower(preg_replace('/[`\'"^~]/', '', SLN_AI_Language::fold((string) $text)));
		if (preg_match('/\b(why|come mai|perche|slot|prenotabile|not bookable|unbookable|can\'?t book)\b/i', $folded)) {
			return false;
		}
		// Apply verbs win over verify when the user is clearly changing hours.
		if (preg_match(
			'/\b(change|set|update|replace|apply|modifica|imposta|cambia|mettre|modifier|andern)\b.{0,40}\b(hours|orari|horaires|horarios|offnungszeiten|opening)\b/i',
			$folded
		) && ! preg_match('/\b(verify|check|compare|consistent|desired|verifica|controlla|souhait|desir)\b/i', $folded)) {
			return false;
		}
		$asks = (bool) preg_match(
			'/\b(check|verify|inspect|review|compare|consistent|coeren\w*|verifica|controlla|verifie|prufer|desired|souhait)\b/i',
			$folded
		);

		return $asks && $this->isHoursRulesIntent($text);
	}

	/**
	 * True for “why isn’t this bookable / available?” questions.
	 *
	 * @param string $text
	 * @return bool
	 */
	private function isAvailabilityQuestion($text)
	{
		$folded = SLN_AI_Language::fold((string) $text);
		$folded = strtolower(preg_replace('/[`\'"^~]/', '', $folded));

		return (bool) preg_match(
			'/\b('
			. 'why|how come|come mai|perche|por que|pourquoi|warum'
			. '|not available|unavailable|non disponibile|no disponible|indisponible|nicht verfugbar'
			. '|can\'?t book|cannot book|non posso prenotare|no puedo reservar'
			. '|prenotabile|non e prenotabile|non prenotabile|not bookable|unbookable'
			. '|greyed|grayed|missing slot|no slot|availability|disponibil\w*'
			. ')\b/i',
			$folded
		);
	}

	/**
	 * Parse “why isn’t date/time available for service X with assistant Y?”.
	 *
	 * @param string $text
	 * @param array  $context
	 * @return array{arguments?:array,ask?:string}|null
	 */
	private function mockParseUnavailableSlot($text, array $context)
	{
		if (! $this->isAvailabilityQuestion($text)) {
			return null;
		}
		// “Are availability rules consistent?” is an audit, not a slot diagnosis.
		if ($this->isHoursRulesIntent($text)
			&& ! preg_match('/\b(why|come mai|perche|slot|prenotabile|not bookable|unbookable)\b/i', SLN_AI_Language::fold($text))
		) {
			return null;
		}

		$folded = SLN_AI_Language::fold($text);
		$folded = strtolower(preg_replace('/[`\'"^~]/', '', (string) $folded));

		if (! preg_match(
			'/\b(date|time|slot|book|booking|appointment|service|servizio|servicio|assistant|attendant|staff|stylist|assistente|operatore|available|unavailable|availability|prenotare|prenotabile|disponib|orario|agosto|gennaio|febbraio)\b/i',
			$folded
		)) {
			return null;
		}

		$assistantFocused = (bool) preg_match(
			'/\b(assistant|attendant|staff|stylist|assistente|operatore)\b/i',
			$folded
		);
		$serviceFocused = (bool) preg_match('/\b(service|servizio|servicio)\b/i', $folded);

		$year = isset($context['today']) ? (int) substr($context['today'], 0, 4) : (int) gmdate('Y');
		$date = null;
		$time = null;

		$monthAlt = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?'
			. '|gennaio|gen|febbraio|feb|marzo|mar|aprile|apr|maggio|mag|giugno|giu|luglio|lug|agosto|ago|settembre|set|ottobre|ott|novembre|nov|dicembre|dic'
			. '|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre'
			. '|janvier|fevrier|mars|avril|mai|juin|juillet|aout|septembre|octobre|novembre|decembre'
			. '|januar|februar|marz|april|mai|juni|juli|august|september|oktober|november|dezember';

		if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
			$date = $m[1];
		} elseif (preg_match(
			'/\b(?:il\s+|on\s+(?:the\s+)?)?(\d{1,2})\s+(' . $monthAlt . ')\b(?:\s+(\d{4}))?/iu',
			$text,
			$m
		)) {
			$date = $this->parseFlexibleDate(
				trim($m[1] . ' ' . $m[2] . ( ! empty($m[3]) ? ' ' . $m[3] : '' )),
				$year
			);
		} elseif (preg_match(
			'/\b(?:il\s+|on\s+(?:the\s+)?)?(' . $monthAlt . ')\s+(\d{1,2})(?:\s*,?\s*(\d{4}))?\b/iu',
			$text,
			$m
		)) {
			$date = $this->parseFlexibleDate(
				trim($m[1] . ' ' . $m[2] . ( ! empty($m[3]) ? ' ' . $m[3] : '' )),
				$year
			);
		}

		if (preg_match('/\b(?:at|@|alle|a)\s*(\d{1,2}(?::\d{2})?\s*[ap]m?|\d{1,2}:\d{2})\b/i', $text, $m)) {
			$time = $this->normTime($m[1]);
		} elseif (preg_match('/\b(\d{1,2}:\d{2})\b/', $text, $m)) {
			$time = $this->normTime($m[1]);
		}

		$service     = null;
		$serviceId   = 0;
		$assistant   = null;
		$assistantId = 0;

		if (preg_match('/\b(?:service|servizio|servicio)\s+(?:id\s*)?(\d+)\b/i', $text, $m)) {
			$serviceId = (int) $m[1];
		} elseif (preg_match(
			'/\b(?:service|servizio|servicio)\s+[\"\']?([A-Za-zÀ-ÿ][\w\-]*(?:\s+[A-Za-zÀ-ÿ][\w\-]*){0,4})/iu',
			$text,
			$m
		)) {
			$service = $this->mockTrimEntityName($m[1]);
		} elseif (preg_match('/\b(?:service|servizio|servicio)\s+[\"\']([^\"\']+)[\"\']/i', $text, $m)) {
			$service = $this->mockTrimEntityName($m[1]);
		}

		if (preg_match('/\b(?:assistant|attendant|staff|stylist|assistente|operatore)\s*(?:n\.?|num(?:ero)?\.?|#)?\s*(\d+)\b/i', $text, $m)) {
			$assistantId = (int) $m[1];
		} elseif (preg_match(
			'/\b(?:assistant|attendant|staff|stylist|assistente|operatore)\s+[\"\']?([A-Za-zÀ-ÿ][\w\-]*(?:\s+[A-Za-zÀ-ÿ][\w\-]*){0,2})/iu',
			$text,
			$m
		)) {
			$assistant = $this->mockTrimEntityName($m[1]);
		}

		$bareName = null;
		if (! $service && ! $assistant && ! $assistantId && ! $serviceId && preg_match(
			'/\b(?:why|how come|come mai|perche)\s+(?:is|isn\'?t|are|l[\'a])?\s*(?:the\s+|l[\'a]\s*)?([A-Za-zÀ-ÿ][\w\-]+(?:\s+[A-Za-zÀ-ÿ][\w\-]+){0,3})\s+(?:not\s+)?(?:available|unavailable|prenotabile)/iu',
			$text,
			$m
		)) {
			$bareName = $this->mockTrimEntityName($m[1]);
		}

		$args = array();
		if ($date) {
			$args['date'] = $date;
		}
		if ($time) {
			$args['time'] = $time;
		}
		if ($serviceId) {
			$args['service_id'] = $serviceId;
		}
		if ($service) {
			$args['service_name'] = $service;
		}
		// "assistente 2" is almost always a display name / list ordinal, not WP post ID 2
		// (post #2 is often "Privacy Policy"). Resolve by name/ordinal in the tool.
		if ($assistantId) {
			$args['assistant_name'] = (string) $assistantId;
		} elseif ($assistant) {
			$args['assistant_name'] = $assistant;
		}
		if ($bareName) {
			$args['entity_name'] = $bareName;
		}
		if ($serviceFocused && ! $assistantFocused) {
			$args['focus'] = 'service';
		} elseif ($assistantFocused && ! $serviceFocused) {
			$args['focus'] = 'assistant';
		} else {
			$args['focus'] = 'slot';
		}

		return array('arguments' => $args);
	}

	/**
	 * Strip trailing grammar from captured person/service names.
	 *
	 * @param string $name
	 * @return string
	 */
	private function mockTrimEntityName($name)
	{
		$name = trim($name, " \t\"'");
		$name = preg_replace(
			'/\s+\b(is|isn\'?t|are|was|were|not|available|unavailable|on|for|with|at|the|date|time'
			. '|non|e|è|prenotabile|prenotare|il|lo|la|per|alle|del|della|come|mai|perche|perché)\b.*$/iu',
			'',
			$name
		);

		return trim($name);
	}

	/**
	 * Strip filler words around a captured entity name:
	 * “service called Beard Trim” → “Beard Trim”, “Taglio Uomo a [30 euro]” → “Taglio Uomo”.
	 *
	 * @param string $name
	 * @return string
	 */
	private function mockStripNameConnectors($name)
	{
		$name = trim((string) $name);
		$name = preg_replace(
			'/^(?:called|named|chiamato|denominato|di nome|llamado|nombrado|appele|appelé|genannt)\s+/iu',
			'',
			$name
		);
		$name = preg_replace('/\s+(?:a|per|for|da|di|de|at|to|con|with)$/iu', '', $name);

		return trim($name);
	}

	/**
	 * Map natural-language questions to explain_setting topics.
	 *
	 * @param string $message Lowercased message.
	 * @return string|null
	 */
	private function mockDetectGuidanceTopic($message)
	{
		// Edition / upgrade questions first.
		if (preg_match('/\b(free vs pro|free or pro|pro edition|upgrade to pro|what(?:\'s| is) (included|in) pro|difference between free)\b/', $message)
			|| preg_match('/\b(is (this|my) (site|plugin) (on )?pro|am i on (free|pro))\b/', $message)
		) {
			return 'edition';
		}

		$map = array(
			'changelog'       => '/\b(what(?:\'?s|s|\s+is)\s+new|changelog|change\s*log|release\s+notes|novita|novedades|quoi\s+de\s+neuf|was\s+ist\s+neu|novidades|recent\s+(?:features?|updates?)|latest\s+(?:features?|updates?))\b/',
			'resources'       => '/\b(resources?|rooms?|equipment|cabine)\b/',
			'nested_bookings' => '/\b(nested bookings?|nest(ed)? services?)\b/',
			'fidelity_score'  => '/\b(fidelity|loyalty score|customer score)\b/',
			'discounts'       => '/\b(where.*(discount|coupon|sconto)|discount (settings?|section|page)|sezione sconti|pagina sconti)\b/',
			'customers'       => '/\b(where.*(customer|clienti|cliente)|customer (settings?|section|page|list)|sezione clienti|pagina clienti)\b/',
			'calendar'        => '/\b(where.*(calendar|calendario)|open (the )?calendar|apri (il )?calendario|salon calendar|calendario del salone)\b/',
			'services'        => '/\b(where.*(service|servizi)|service (settings?|section|page|list)|sezione servizi|pagina servizi)\b/',
			'assistants'      => '/\b(where.*(assistant|attendant|staff|assistenti)|assistant (settings?|section|page|list)|sezione assistenti|pagina assistenti)\b/',
			'payments'        => '/\b(stripe|paypal|payment|pagamenti|carta|credit card|deposit)\b/',
			'sms'             => '/\b(sms|whatsapp|twilio|ip1sms)\b/',
			'google_calendar' => '/\b(google calendar|gcal|oauth|calendar sync)\b/',
			'facebook_login'  => '/\b(facebook login|fb login)\b/',
			'recaptcha'       => '/\b(recaptcha|captcha)\b/',
			'maps'            => '/\b(google maps|maps api)\b/',
			'onesignal'       => '/\b(onesignal|push notification)\b/',
			'zapier'          => '/\b(zapier)\b/',
			'license'         => '/\b(license|licence|edd key)\b/',
			'factory_reset'   => '/\b(factory reset|reset (all )?settings)\b/',
			'multishop'       => '/\b(multi[- ]?shop|multishop)\b/',
			'waitlist'        => '/\b(waitlist|lista d.?attesa|smart waitlist)\b/',
			'kiosk'           => '/\b(kiosk|totem|walk-?in totem)\b/',
			'communicator'    => '/\b(communicator)\b/',
			'woo_checkout'    => '/\b(woocommerce checkout|woo checkout)\b/',
			'migrator'        => '/\b(migrator)\b/',
			'pwa'             => '/\b(pwa|staff (mobile )?app|app staff)\b/',
			'addons'          => '/\b(add-?ons?|estensioni|extensions page)\b/',
			'edition'         => '/\b(pro feature|need pro|requires? pro)\b/',
		);
		foreach ($map as $topic => $pattern) {
			if (preg_match($pattern, $message)) {
				return $topic;
			}
		}

		return null;
	}

	/**
	 * Parse holiday / closure phrases into set_salon_holidays arguments.
	 *
	 * @param string $text
	 * @param array  $context
	 * @return array|null
	 */
	private function mockParseHolidays($text, array $context)
	{
		$folded = SLN_AI_Language::fold($text);
		$looksHoliday = (bool) preg_match(
			'/\b(holiday|holidays|closed on|closing on|vacation|we\'?re closed|i\'?m closed|shut for|bank holiday|christmas|new\s*year|boxing\s+day|chiuso il|chiusi il|festivit|ferie|vacanza|cerrado el|festivo|feriado|ferme le|jours? feries?|geschlossen|feiertag|natale|capodanno|navidad|ano nuevo)\b/i',
			$folded
		);
		// Weekly "closed Sunday" is opening-hours, not a holiday rule.
		if (preg_match('/\b(closed|chiuso|cerrado|ferme|geschlossen)\s+(on\s+|il\s+|el\s+|le\s+)?(sundays?|saturdays?|mondays?|tuesdays?|wednesdays?|thursdays?|fridays?|domenica|sabato|lunedi|martedi|mercoledi|giovedi|venerdi|domingo|lunes|martes|miercoles|jueves|viernes|dimanche|lundi|mardi|mercredi|jeudi|vendredi|sonntag|montag|dienstag|mittwoch|donnerstag|freitag)\b/i', $folded)
			&& ! preg_match('/\d{1,4}/', $text)
			&& ! preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|christmas|new\s*year|natale|capodanno|navidad)\b/i', $folded)
		) {
			return null;
		}
		if (! $looksHoliday && ! preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|gen|mag|giu|lug|ago|set|ott|dic)[a-z]*\s+\d{1,2}\b/i', $folded)
			&& ! preg_match('/\b\d{1,2}\s+(?:de\s+)?(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|gen|mag|giu|lug|ago|set|ott|dic)/i', $folded)
			&& ! preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text)
		) {
			return null;
		}

		$year  = isset($context['today']) ? (int) substr($context['today'], 0, 4) : (int) gmdate('Y');
		$rules = array();

		if (preg_match('/\b(christmas(?:\s+day)?|natale|navidad|weihnachten)\b/i', $folded)) {
			$rules[] = $this->holidayRuleFullDay(sprintf('%04d-12-25', $year), sprintf('%04d-12-25', $year));
		}
		if (preg_match('/\b(new\s*year(?:\'?s)?(?:\s+day)?|capodanno|ano nuevo|nouvel\s*an|neujahr)\b/i', $folded)) {
			$rules[] = $this->holidayRuleFullDay(sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year));
		}
		if (preg_match('/\boxing\s+day\b/i', $text)) {
			$rules[] = $this->holidayRuleFullDay(sprintf('%04d-12-26', $year), sprintf('%04d-12-26', $year));
		}

		// Fold artifacts (accents become backticks) must be stripped before matching months.
		$clean = preg_replace('/[`\'"^~]/', '', $folded);
		// Month stems in all supported languages (parseFlexibleDate resolves them).
		$monthAlt = '(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec'
			. '|gen|mag|giu|lug|ago|set|ott|dic'
			. '|ene|abr|jui|aou|fev|avr|dez|okt)[a-z]*';
		$dateAlt  = '\d{4}-\d{2}-\d{2}|\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?'
			. '|' . $monthAlt . '\s+\d{1,2}(?:\s*,?\s*\d{4})?'
			. '|\d{1,2}\s+(?:de\s+)?' . $monthAlt . '(?:\s+\d{4})?';

		if (preg_match(
			'/\b(?:from\s+|dal\s+|du\s+|vom\s+|desde\s+(?:el\s+)?|del\s+)?(' . $dateAlt . ')\s*'
			. '(?:to|through|thru|al|au|bis|hasta(?:\s+el)?|[–\-—])\s*(' . $dateAlt . ')/iu',
			$clean,
			$m
		)) {
			$from = $this->parseFlexibleDate($m[1], $year);
			$to   = $this->parseFlexibleDate($m[2], $year);
			if ($from && $to) {
				$rules[] = $this->holidayRuleFullDay($from, $to);
			}
		} elseif (preg_match(
			// “dal 10 al 20 agosto”: bare start day borrows month/year from the end date.
			'/\b(?:dal|from|du|vom|desde(?:\s+el)?|del)\s+(\d{1,2})\s+(?:al|to|au|bis|hasta(?:\s+el)?)\s+'
			. '(\d{1,2}\s+(?:de\s+)?' . $monthAlt . '(?:\s+\d{4})?)\b/iu',
			$clean,
			$m
		)) {
			$to = $this->parseFlexibleDate($m[2], $year);
			if ($to) {
				$from = substr($to, 0, 8) . sprintf('%02d', (int) $m[1]);
				$rules[] = $this->holidayRuleFullDay($from, $to);
			}
		}

		if (! $rules && preg_match(
			'/\b(?:on\s+|il\s+|el\s+|le\s+)?(' . $dateAlt . ')\b/iu',
			$clean,
			$m
		)) {
			$d = $this->parseFlexibleDate($m[1], $year);
			if ($d) {
				$rules[] = $this->holidayRuleFullDay($d, $d);
			}
		}

		if (! $rules) {
			return null;
		}

		if (count($rules) === 1 && preg_match(
			'/\b(?:from\s+|at\s+)?(\d{1,2}(?::\d{2})?\s*[ap]m?|\d{1,2}:\d{2})\s*(?:to|[–\-—])\s*(\d{1,2}(?::\d{2})?\s*[ap]m?|\d{1,2}:\d{2})\b/i',
			$text,
			$tm
		)) {
			$fromT = $this->normTime($tm[1]);
			$toT   = $this->normTime($tm[2]);
			if ($fromT && $toT) {
				$rules[0]['full_day']  = false;
				$rules[0]['from_time'] = $fromT;
				$rules[0]['to_time']   = $toT;
			}
		}

		$mode = preg_match('/\b(replace|only|instead|clear existing)\b/i', $text) ? 'replace_all' : 'append';

		return array(
			'mode'    => $mode,
			'summary' => trim(wp_strip_all_tags($text)),
			'rules'   => $rules,
		);
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @return array
	 */
	private function holidayRuleFullDay($from, $to)
	{
		return array(
			'from_date' => $from,
			'to_date'   => $to,
			'full_day'  => true,
		);
	}

	/**
	 * @param string $raw
	 * @param int    $defaultYear
	 * @return string|null Y-m-d
	 */
	private function parseFlexibleDate($raw, $defaultYear)
	{
		$raw = trim($raw);
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
			return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
		}

		$months = array(
			'jan' => 1, 'january' => 1, 'gen' => 1, 'gennaio' => 1, 'enero' => 1, 'janvier' => 1, 'januar' => 1,
			'feb' => 2, 'february' => 2, 'febbraio' => 2, 'febrero' => 2, 'fevrier' => 2, 'februar' => 2,
			'mar' => 3, 'march' => 3, 'marzo' => 3, 'mars' => 3, 'maerz' => 3, 'marz' => 3,
			'apr' => 4, 'april' => 4, 'aprile' => 4, 'abril' => 4, 'avril' => 4,
			'may' => 5, 'mag' => 5, 'maggio' => 5, 'mayo' => 5, 'mai' => 5,
			'jun' => 6, 'june' => 6, 'giu' => 6, 'giugno' => 6, 'junio' => 6, 'juin' => 6, 'juni' => 6,
			'jul' => 7, 'july' => 7, 'lug' => 7, 'luglio' => 7, 'julio' => 7, 'juillet' => 7, 'juli' => 7,
			'aug' => 8, 'august' => 8, 'ago' => 8, 'agosto' => 8, 'aout' => 8,
			'sep' => 9, 'sept' => 9, 'september' => 9, 'set' => 9, 'settembre' => 9, 'septiembre' => 9, 'septembre' => 9,
			'oct' => 10, 'october' => 10, 'ott' => 10, 'ottobre' => 10, 'octubre' => 10, 'octobre' => 10, 'oktober' => 10,
			'nov' => 11, 'november' => 11, 'novembre' => 11, 'noviembre' => 11,
			'dec' => 12, 'december' => 12, 'dic' => 12, 'dicembre' => 12, 'diciembre' => 12, 'decembre' => 12, 'dezember' => 12,
		);

		$rawFolded = SLN_AI_Language::fold($raw);
		if (preg_match('/^([a-z]+)\s+(\d{1,2})(?:\s*,?\s*(\d{4}))?$/i', $rawFolded, $m)) {
			$key = strtolower($m[1]);
			if (isset($months[ $key ])) {
				$y = ! empty($m[3]) ? (int) $m[3] : $defaultYear;

				return sprintf('%04d-%02d-%02d', $y, $months[ $key ], (int) $m[2]);
			}
		}
		if (preg_match('/^(\d{1,2})\s+(?:de\s+)?([a-z]+)(?:\s+(\d{4}))?$/i', $rawFolded, $m)) {
			$key = strtolower($m[2]);
			if (isset($months[ $key ])) {
				$y = ! empty($m[3]) ? (int) $m[3] : $defaultYear;

				return sprintf('%04d-%02d-%02d', $y, $months[ $key ], (int) $m[1]);
			}
		}
		if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?$/', $raw, $m)) {
			$d  = (int) $m[1];
			$mo = (int) $m[2];
			$y  = ! empty($m[3]) ? (int) $m[3] : $defaultYear;
			if ($y < 100) {
				$y += 2000;
			}
			if ($mo > 12 && $d <= 12) {
				$tmp = $d;
				$d   = $mo;
				$mo  = $tmp;
			}
			if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
				return null;
			}

			return sprintf('%04d-%02d-%02d', $y, $mo, $d);
		}

		$dt = date_create($raw);
		if ($dt) {
			return $dt->format('Y-m-d');
		}

		return null;
	}

	/**
	 * @param int[]  $days
	 * @param array  $intervals
	 * @param string $summary
	 * @param array  $context
	 * @param string $raw
	 * @return array
	 */
	private function mockToolFromDaysIntervals(array $days, array $intervals, $summary, array $context = array(), $raw = '')
	{
		$utterance = $raw !== '' ? $raw : $summary;

		return $this->mockAttachShopOrAsk(
			'set_salon_availabilities',
			array(
				'mode'    => 'replace_all',
				'summary' => trim(wp_strip_all_tags($summary)),
				'rules'   => array(
					array(
						'days'      => array_values($days),
						'intervals' => $intervals,
						'always'    => true,
					),
				),
			),
			$context,
			$utterance,
			$this->mockMsg(
				SLN_AI_Language::detect($utterance),
				'hours_preview',
				__('Great — I have enough to propose your opening hours. Please confirm the preview.', 'salon-booking-system')
			)
		);
	}

	/**
	 * Local Assistant reply in the user’s message language (not admin locale).
	 *
	 * @param string $lang
	 * @param string $key
	 * @param string $fallback
	 * @param array  $args
	 * @return string
	 */
	private function mockMsg($lang, $key, $fallback, array $args = array())
	{
		return SLN_AI_Language::phrase($lang, $key, $fallback, $args);
	}

	/**
	 * Attach shop from NL when Multishop is active; ask which location if multiple and unnamed.
	 *
	 * @param string $toolName
	 * @param array  $arguments
	 * @param array  $context
	 * @param string $raw
	 * @param string $readyMessage
	 * @return array
	 */
	private function mockAttachShopOrAsk($toolName, array $arguments, array $context, $raw, $readyMessage)
	{
		$shopArgs = $this->mockExtractShop($raw, $context);
		if ($shopArgs) {
			$arguments = array_merge($arguments, $shopArgs);
		}

		if ($this->mockNeedsShop($context) && empty($arguments['shop_id']) && empty($arguments['shop_name'])) {
			$shops = isset($context['shops']) && is_array($context['shops']) ? $context['shops'] : array();

			return array(
				'message'   => SLN_AI_Multishop::askWhichShopMessage($shops),
				'tool_call' => null,
				'draft'     => array(
					'pending_tool' => array(
						'name'      => $toolName,
						'arguments' => $arguments,
					),
					'utterance'    => $raw,
				),
			);
		}

		return array(
			'message'   => $readyMessage,
			'tool_call' => array(
				'name'      => $toolName,
				'arguments' => $arguments,
			),
			'draft'     => null,
		);
	}

	/**
	 * @param array $context
	 * @return bool
	 */
	private function mockNeedsShop(array $context)
	{
		return ! empty($context['multishop_active'])
			&& isset($context['shops_count'])
			&& (int) $context['shops_count'] > 1;
	}

	/**
	 * @param string $text
	 * @param array  $context
	 * @return array{shop_id:int,shop_name:string}|null
	 */
	private function mockExtractShop($text, array $context)
	{
		if (empty($context['multishop_active']) && empty($context['multishop_addon'])) {
			return null;
		}
		$shops = isset($context['shops']) && is_array($context['shops']) ? $context['shops'] : array();
		if (! $shops) {
			return null;
		}

		$candidate = null;
		if (preg_match('/\b(?:for|at|in)\s+(?:the\s+)?(?:shop|location|store|branch)\s+[\"\']?([^\"\'\n,]+)/i', $text, $m)) {
			$candidate = trim($m[1]);
		} elseif (preg_match('/\b(?:shop|location|store|branch)\s*[:=]\s*[\"\']?([^\"\'\n,]+)/i', $text, $m)) {
			$candidate = trim($m[1]);
		}

		if ($candidate) {
			$candidate = preg_replace('/\s+\b(please|thanks|hours|holidays|on|for)\b.*$/i', '', $candidate);
			$candidate = trim($candidate, " \t\"'");
			foreach ($shops as $shop) {
				if (strcasecmp($shop['name'], $candidate) === 0
					|| stripos($shop['name'], $candidate) !== false
					|| stripos($candidate, $shop['name']) !== false
				) {
					return array(
						'shop_id'   => (int) $shop['id'],
						'shop_name' => (string) $shop['name'],
					);
				}
			}
		}

		usort(
			$shops,
			function ($a, $b) {
				return strlen($b['name']) - strlen($a['name']);
			}
		);
		foreach ($shops as $shop) {
			$name = isset($shop['name']) ? (string) $shop['name'] : '';
			if ($name !== '' && stripos($text, $name) !== false) {
				return array(
					'shop_id'   => (int) $shop['id'],
					'shop_name' => $name,
				);
			}
		}

		return null;
	}

	/**
	 * @param string $message Lowercased.
	 * @return array{label:string,full:string}|null
	 */
	private function detectQueriedClosedDay($message)
	{
		$folded = SLN_AI_Language::fold($message);
		$map    = array(
			'sunday'     => array('label' => 'Sun', 'full' => 'Sunday'),
			'saturday'   => array('label' => 'Sat', 'full' => 'Saturday'),
			'monday'     => array('label' => 'Mon', 'full' => 'Monday'),
			'tuesday'    => array('label' => 'Tue', 'full' => 'Tuesday'),
			'wednesday'  => array('label' => 'Wed', 'full' => 'Wednesday'),
			'thursday'   => array('label' => 'Thu', 'full' => 'Thursday'),
			'friday'     => array('label' => 'Fri', 'full' => 'Friday'),
			'domenica'   => array('label' => 'Sun', 'full' => 'Sunday'),
			'sabato'     => array('label' => 'Sat', 'full' => 'Saturday'),
			'lunedi'     => array('label' => 'Mon', 'full' => 'Monday'),
			'martedi'    => array('label' => 'Tue', 'full' => 'Tuesday'),
			'mercoledi'  => array('label' => 'Wed', 'full' => 'Wednesday'),
			'giovedi'    => array('label' => 'Thu', 'full' => 'Thursday'),
			'venerdi'    => array('label' => 'Fri', 'full' => 'Friday'),
			'domingo'    => array('label' => 'Sun', 'full' => 'Sunday'),
			'lunes'      => array('label' => 'Mon', 'full' => 'Monday'),
			'martes'     => array('label' => 'Tue', 'full' => 'Tuesday'),
			'miercoles'  => array('label' => 'Wed', 'full' => 'Wednesday'),
			'jueves'     => array('label' => 'Thu', 'full' => 'Thursday'),
			'viernes'    => array('label' => 'Fri', 'full' => 'Friday'),
			'dimanche'   => array('label' => 'Sun', 'full' => 'Sunday'),
			'lundi'      => array('label' => 'Mon', 'full' => 'Monday'),
			'mardi'      => array('label' => 'Tue', 'full' => 'Tuesday'),
			'mercredi'   => array('label' => 'Wed', 'full' => 'Wednesday'),
			'jeudi'      => array('label' => 'Thu', 'full' => 'Thursday'),
			'vendredi'   => array('label' => 'Fri', 'full' => 'Friday'),
			'samedi'     => array('label' => 'Sat', 'full' => 'Saturday'),
			'sonntag'    => array('label' => 'Sun', 'full' => 'Sunday'),
			'montag'     => array('label' => 'Mon', 'full' => 'Monday'),
			'dienstag'   => array('label' => 'Tue', 'full' => 'Tuesday'),
			'mittwoch'   => array('label' => 'Wed', 'full' => 'Wednesday'),
			'donnerstag' => array('label' => 'Thu', 'full' => 'Thursday'),
			'freitag'    => array('label' => 'Fri', 'full' => 'Friday'),
			'samstag'    => array('label' => 'Sat', 'full' => 'Saturday'),
		);
		foreach ($map as $needle => $info) {
			if (strpos($folded, $needle) !== false) {
				return $info;
			}
		}

		return null;
	}

	/**
	 * @param string $text
	 * @return bool
	 */
	private function messageHasTimes($text)
	{
		return (bool) preg_match(
			'/\d{1,2}\s*[:.h]\s*\d{2}|\d{1,2}\s*h\b|\d{1,2}\s*[ap]m|\b\d{1,2}\s*[–\-—to]+\s*\d{1,2}\b|\b(?:dalle|da|de|von|from)\s*\d{1,2}/i',
			$text
		);
	}

	/**
	 * @param string $text
	 * @return bool
	 */
	private function messageHasDays($text)
	{
		$folded = SLN_AI_Language::fold($text);
		if (preg_match('/\b(weekdays?|weekend|everyday|every\s+day|all\s+week|7\s*days?|feriali|giorni\s+feriali|laborables?|jours?\s+ouvres?|werktage|fin\s+de\s+semana|fine\s+settimana)\b/i', $folded)) {
			return true;
		}

		return (bool) $this->extractDayRangeOrList($text);
	}

	/**
	 * Extract open days from casual phrases like "I'm open from monday to wednesday".
	 *
	 * @param string $text
	 * @return int[]
	 */
	private function extractDayRangeOrList($text)
	{
		$text   = $this->normalizeDayTypos($text);
		$folded = SLN_AI_Language::fold($text);
		$dayTok = $this->dayTokenPattern();

		if (preg_match('/\b(weekdays?|feriali|giorni\s+feriali|laborables?|jours?\s+ouvres?|werktage)\b/i', $folded)) {
			return array(2, 3, 4, 5, 6);
		}
		if (preg_match('/\b(every\s+day|everyday|all\s+week|7\s*days?|tutti\s+i\s+giorni|todos\s+los\s+dias|tous\s+les\s+jours)\b/i', $folded)) {
			return array(1, 2, 3, 4, 5, 6, 7);
		}
		if (preg_match('/\b(weekends?|fine\s+settimana|fin\s+de\s+semana|wochenende)\b/i', $folded)) {
			return array(1, 7);
		}

		// "from monday to wednesday" / "da lunedì a mercoledì" / "mon–wed"
		if (preg_match(
			'/(?:from\s+|da\s+|del\s+|de\s+|du\s+|von\s+)?(' . $dayTok . ')\s*(?:to|a|au|bis|[–\-—]|through|thru)\s*(' . $dayTok . ')/i',
			$folded,
			$m
		)) {
			$from = $this->dayTokenToKey($m[1]);
			$to   = $this->dayTokenToKey($m[2]);
			if ($from && $to) {
				return $this->expandDayRange($from, $to);
			}
		}

		// "monday, tuesday and wednesday" / individual days
		$days = array();
		if (preg_match_all(
			'/\b(' . $dayTok . ')s?\b/i',
			$folded,
			$matches
		)) {
			foreach ($matches[1] as $token) {
				$key = $this->dayTokenToKey($token);
				if ($key !== null) {
					$days[] = $key;
				}
			}
		}

		// Fuzzy leftover tokens (typos like "wednesay")
		if (! $days) {
			$key = $this->fuzzyDayToken($text);
			if ($key !== null) {
				$days[] = $key;
			}
		}

		return array_values(array_unique($days));
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function normalizeDayTypos($text)
	{
		$fixes = array(
			'wednesay'  => 'wednesday',
			'wendsday'  => 'wednesday',
			'wednesady' => 'wednesday',
			'thurday'   => 'thursday',
			'thirsday'  => 'thursday',
			'tuesay'    => 'tuesday',
			'saterday'  => 'saturday',
			'satirday'  => 'saturday',
			'sundy'     => 'sunday',
		);
		foreach ($fixes as $wrong => $right) {
			$text = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/i', $right, $text);
		}

		return $text;
	}

	/**
	 * @param string $text
	 * @return int|null
	 */
	private function fuzzyDayToken($text)
	{
		$words = preg_split('/[^a-z]+/i', strtolower($text));
		$names = array(
			'sunday' => 1, 'monday' => 2, 'tuesday' => 3, 'wednesday' => 4,
			'thursday' => 5, 'friday' => 6, 'saturday' => 7,
		);
		foreach ($words as $word) {
			if (strlen($word) < 4) {
				continue;
			}
			foreach ($names as $name => $key) {
				if (levenshtein($word, $name) <= 2) {
					return $key;
				}
			}
		}

		return null;
	}

	/**
	 * @param int[] $days
	 * @return string
	 */
	private function formatDaysLabel(array $days)
	{
		sort($days);
		$names = array(
			1 => __('Sunday', 'salon-booking-system'),
			2 => __('Monday', 'salon-booking-system'),
			3 => __('Tuesday', 'salon-booking-system'),
			4 => __('Wednesday', 'salon-booking-system'),
			5 => __('Thursday', 'salon-booking-system'),
			6 => __('Friday', 'salon-booking-system'),
			7 => __('Saturday', 'salon-booking-system'),
		);
		if ($days === array(2, 3, 4, 5, 6)) {
			return __('weekdays', 'salon-booking-system');
		}
		if (count($days) >= 2) {
			$first = reset($days);
			$last  = end($days);
			$seq   = $this->expandDayRange($first, $last);
			if ($seq === $days) {
				return sprintf(
					/* translators: 1: start weekday, 2: end weekday */
					__('%1$s to %2$s', 'salon-booking-system'),
					$names[ $first ],
					$names[ $last ]
				);
			}
		}
		$labels = array();
		foreach ($days as $d) {
			if (isset($names[ $d ])) {
				$labels[] = $names[ $d ];
			}
		}

		return implode(', ', $labels);
	}

	/**
	 * Parse natural-language opening hours into tool arguments when times + days are present.
	 *
	 * @param string $text
	 * @return array|null
	 */
	private function mockParseOpeningHours($text)
	{
		$text = $this->normalizeDayTypos((string) $text);
		$text = $this->normalizeTimeTokens($text);
		if (! $this->textLooksLikeTimetable($text)) {
			return null;
		}

		$closedDays = $this->extractClosedDays($text);
		$workText   = $this->stripClosedClauses($text);
		$groups     = $this->extractDayHourGroups($workText, $closedDays);
		$rules      = $groups;

		if (! $rules) {
			if (preg_match(
				'/(mon(?:day)?|tue(?:s(?:day)?)?|wed(?:nesday)?|thu(?:rs(?:day)?)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?)\s*[–\-—]+\s*(mon(?:day)?|tue(?:s(?:day)?)?|wed(?:nesday)?|thu(?:rs(?:day)?)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?)/i',
				$text,
				$rangeMatch
			)) {
				$fromDay   = $this->dayTokenToKey($rangeMatch[1]);
				$toDay     = $this->dayTokenToKey($rangeMatch[2]);
				$days      = $this->expandDayRange($fromDay, $toDay);
				$days      = array_values(array_diff($days, $closedDays));
				$intervals = $this->extractIntervals($text);
				if ($days && $intervals) {
					$rules[] = array(
						'days'      => $days,
						'intervals' => $intervals,
						'always'    => true,
					);
				}
			} elseif (preg_match('/\b(weekdays?|every\s+day|everyday|all\s+week|7\s*days?)\b/i', $text)) {
				$intervals = $this->extractIntervals($text);
				if ($intervals) {
					$days    = preg_match('/\bweekdays?\b/i', $text)
						? array(2, 3, 4, 5, 6)
						: array(1, 2, 3, 4, 5, 6, 7);
					$days    = array_values(array_diff($days, $closedDays));
					$rules[] = array(
						'days'      => $days,
						'intervals' => $intervals,
						'always'    => true,
					);
				}
			}
		}

		if (! $rules && preg_match_all(
			'/(?:^|[,;]\s*)(mon(?:day)?|tue(?:s(?:day)?)?|wed(?:nesday)?|thu(?:rs(?:day)?)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?)\s+(\d{1,2}(?::\d{2})?(?:\s*[ap]m)?)\s*[–\-—to]+\s*(\d{1,2}(?::\d{2})?(?:\s*[ap]m)?)/i',
			$text,
			$singles,
			PREG_SET_ORDER
		)) {
			foreach ($singles as $m) {
				$key = $this->dayTokenToKey($m[1]);
				if ($key === null || in_array($key, $closedDays, true)) {
					continue;
				}
				$from = $this->normTime($m[2]);
				$to   = $this->normTime($m[3]);
				if (! $from || ! $to) {
					continue;
				}
				$rules[] = array(
					'days'      => array($key),
					'intervals' => array(
						array(
							'from' => $from,
							'to'   => $to,
						),
					),
					'always'    => true,
				);
			}
		}

		$rules = $this->dedupeRules($rules);

		if (! $rules) {
			$intervals = $this->extractIntervals($text);
			$days      = $this->inferOpenDays($text, $closedDays);
			if (! $intervals || ! $days) {
				return null;
			}
			$rules[] = array(
				'days'      => $days,
				'intervals' => $intervals,
				'always'    => true,
			);
		}

		$mentioned = array();
		foreach ($rules as $rule) {
			foreach (isset($rule['days']) ? $rule['days'] : array() as $day) {
				$mentioned[ (int) $day ] = true;
			}
		}
		$unmentioned = array();
		for ($day = 1; $day <= 7; $day++) {
			if (empty($mentioned[ $day ]) && ! in_array($day, $closedDays, true)) {
				$unmentioned[] = $day;
			}
		}
		$usedRangeOrWeek = (bool) preg_match(
			'/\b(weekdays?|every\s+day|everyday|all\s+week|7\s*days?)\b/i',
			$text
		) || (bool) preg_match(
			'/\b(?:mon|tue|wed|thu|fri|sat|sun)[a-z]*\s*(?:to|–|-|—)\s*(?:mon|tue|wed|thu|fri|sat|sun)/i',
			$text
		);
		$preserve = (bool) $unmentioned && (bool) $closedDays && ! $usedRangeOrWeek;

		$out = array(
			'mode'    => 'replace_all',
			'summary' => trim(wp_strip_all_tags($text)),
			'rules'   => $rules,
		);
		if ($closedDays) {
			$out['closed_days'] = $closedDays;
		}
		if ($preserve) {
			$out['preserve_unmentioned'] = true;
		}

		return $out;
	}

	/**
	 * French/EU time tokens: 13h30 → 13:30, 13h → 13:00.
	 *
	 * @param string $text
	 * @return string
	 */
	private function normalizeTimeTokens($text)
	{
		$text = preg_replace('/(\d{1,2})h(\d{2})\b/i', '$1:$2', (string) $text);
		$text = preg_replace('/(\d{1,2})h\b/i', '$1:00', $text);

		return $text;
	}

	/**
	 * Per-group “Mon, Tue, Wed from 9 to 12 then 13:30 to 19:30 / Friday from …”
	 *
	 * @param string $text
	 * @param int[]  $closedDays
	 * @return array
	 */
	private function extractDayHourGroups($text, array $closedDays)
	{
		$dayTok = $this->dayTokenPattern();
		$unit   = '(?:' . $dayTok . ')(?:\s*(?:to|through|thru|a|au|bis|[–\-—])\s*(?:' . $dayTok . ')|(?:\s*(?:,|;|and|&|e|et|y|und)\s*(?:' . $dayTok . '))*)';
		if (! preg_match_all('/\b(' . $unit . ')\b/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
			return array();
		}

		$spans = $matches[1];
		$out   = array();
		$count = count($spans);
		for ($i = 0; $i < $count; $i++) {
			$label  = $spans[ $i ][0];
			$start  = $spans[ $i ][1] + strlen($label);
			$end    = isset($spans[ $i + 1 ][1]) ? $spans[ $i + 1 ][1] : strlen($text);
			$clause = substr($text, $start, max(0, $end - $start));
			$days   = $this->extractDayRangeOrList($label);
			$days   = array_values(array_diff($days, $closedDays));
			$intervals = $this->extractIntervals($clause);
			if (! $days || ! $intervals) {
				continue;
			}
			$out[] = array(
				'days'      => $days,
				'intervals' => $intervals,
				'always'    => true,
			);
		}

		return $out;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function stripClosedClauses($text)
	{
		$dayTok = $this->dayTokenPattern();
		$closed = 'closed|chius[oi]|cerrad[oa]s?|ferm[eé]|geschlossen';
		$list   = '(?:' . $dayTok . ')(?:\s*(?:,|and|&|e|et|y|und)\s*(?:' . $dayTok . '))*';
		$text   = preg_replace('/\b' . $list . '\s+(?:' . $closed . ')\b/i', ' ', (string) $text);
		$text   = preg_replace('/\b(?:' . $closed . ')(?:\s+on|\s+il|\s+el|\s+le)?\s+' . $list . '\b/i', ' ', $text);

		return $text;
	}

	/**
	 * @param string $text
	 * @return bool
	 */
	private function textLooksLikeTimetable($text)
	{
		return $this->messageHasTimes($text) && $this->messageHasDays($text);
	}

	/**
	 * @param string $text
	 * @return int[]
	 */
	private function extractClosedDays($text)
	{
		$text   = $this->normalizeDayTypos($text);
		$folded = SLN_AI_Language::fold($text);
		$dayTok = $this->dayTokenPattern();
		$closed = array();
		$list   = '(?:' . $dayTok . ')(?:\s*(?:,|and|&|e|et|y|und)\s*(?:' . $dayTok . '))*';
		$word   = 'closed|chius[oi]|cerrad[oa]s?|ferm[eé]|geschlossen';

		if (preg_match_all('/(' . $list . ')\s+(?:' . $word . ')/i', $folded, $matches)) {
			foreach ($matches[1] as $chunk) {
				foreach ($this->extractDayRangeOrList($chunk) as $key) {
					$closed[] = $key;
				}
			}
		}
		// Only “closed on Friday” / clause-initial “closed Thursday and Sunday”.
		// Bare “closed Friday” after “Sunday closed Friday from …” would steal Friday.
		if (preg_match_all(
			'/(?:(?:' . $word . ')(?:\s+on|\s+il|\s+el|\s+le|:)|(?:^|[.;\n])\s*(?:' . $word . '))\s+(' . $list . ')/i',
			$folded,
			$matches
		)) {
			foreach ($matches[1] as $chunk) {
				foreach ($this->extractDayRangeOrList($chunk) as $key) {
					$closed[] = $key;
				}
			}
		}

		return array_values(array_unique($closed));
	}

	/**
	 * @param string $text
	 * @param int[]  $closedDays
	 * @return int[]
	 */
	private function inferOpenDays($text, array $closedDays)
	{
		$days = $this->extractDayRangeOrList($text);

		return array_values(array_diff($days, $closedDays));
	}

	/**
	 * @param string $token
	 * @return int|null
	 */
	private function dayTokenToKey($token)
	{
		$token = SLN_AI_Language::fold(strtolower((string) $token));
		$token = preg_replace('/[^a-z]/', '', $token);
		$map   = array(
			// EN
			'sun' => 1, 'sunday' => 1, 'sundays' => 1,
			'mon' => 2, 'monday' => 2, 'mondays' => 2,
			'tue' => 3, 'tues' => 3, 'tuesday' => 3, 'tuesdays' => 3,
			'wed' => 4, 'wednesday' => 4, 'wednesdays' => 4,
			'thu' => 5, 'thur' => 5, 'thurs' => 5, 'thursday' => 5, 'thursdays' => 5,
			'fri' => 6, 'friday' => 6, 'fridays' => 6,
			'sat' => 7, 'saturday' => 7, 'saturdays' => 7,
			// IT
			'dom' => 1, 'domenica' => 1, 'domeniche' => 1,
			'lun' => 2, 'lunedi' => 2,
			'mar' => 3, 'martedi' => 3,
			'mer' => 4, 'mercoledi' => 4,
			'gio' => 5, 'giovedi' => 5,
			'ven' => 6, 'venerdi' => 6,
			'sab' => 7, 'sabato' => 7, 'sabati' => 7,
			// ES
			'domingo' => 1, 'lunes' => 2, 'martes' => 3, 'miercoles' => 4, 'jueves' => 5, 'viernes' => 6, 'sabado' => 7,
			// FR
			'dim' => 1, 'dimanche' => 1, 'lundi' => 2, 'mardi' => 3, 'mercredi' => 4, 'jeudi' => 5, 'vendredi' => 6, 'samedi' => 7,
			// DE (avoid 2-letter tokens — collide with IT/ES prepositions)
			'sonntag' => 1, 'montag' => 2, 'dienstag' => 3, 'mittwoch' => 4,
			'donnerstag' => 5, 'freitag' => 6, 'samstag' => 7,
		);

		return isset($map[ $token ]) ? $map[ $token ] : null;
	}

	/**
	 * Regex alternation for weekday tokens (ASCII-folded).
	 *
	 * @return string
	 */
	private function dayTokenPattern()
	{
		return 'mon(?:day)?|tue(?:s(?:day)?)?|wed(?:nesday)?|thu(?:rs(?:day)?)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?'
			. '|lunedi|martedi|mercoledi|giovedi|venerdi|sabato|domenica|lun|mar|mer|gio|ven|sab|dom'
			. '|lunes|martes|miercoles|jueves|viernes|sabado|domingo'
			. '|lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche'
			. '|montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonntag';
	}

	/**
	 * @param int|null $from
	 * @param int|null $to
	 * @return int[]
	 */
	private function expandDayRange($from, $to)
	{
		if (! $from || ! $to) {
			return array();
		}
		$days = array();
		$cur  = $from;
		for ($i = 0; $i < 7; $i++) {
			$days[] = $cur;
			if ($cur === $to) {
				break;
			}
			$cur = $cur === 7 ? 1 : $cur + 1;
		}

		return $days;
	}

	/**
	 * @param string $text
	 * @return array
	 */
	private function extractIntervals($text)
	{
		$intervals = array();
		$folded    = SLN_AI_Language::fold($this->normalizeTimeTokens($text));
		$seen      = array();
		$patterns  = array(
			'/(\d{1,2}[:.]\d{2})\s*[–\-—to]+\s*(\d{1,2}[:.]\d{2})/i',
			'/(\d{1,2}(?::\d{2})?\s*[ap]m)\s*[–\-—to]+\s*(\d{1,2}(?::\d{2})?\s*[ap]m)/i',
			'/(?<![:\d])(\d{1,2})\s*[–\-—to]+\s*(\d{1,2})(?![:\d])/i',
			// IT/ES/FR: "dalle 9 alle 18" / "de 9 a 18" / "de 9h a 18h"
			'/\b(?:dalle|da|de|von|from)\s*(\d{1,2}(?:[:.]\d{2})?)\s*(?:alle|a|a\s*las?|bis|to|[–\-—])\s*(\d{1,2}(?:[:.]\d{2})?)/i',
		);

		foreach ($patterns as $pattern) {
			if (! preg_match_all($pattern, $folded, $matches, PREG_SET_ORDER)) {
				continue;
			}
			foreach ($matches as $m) {
				$from = $this->normTime($m[1]);
				$to   = $this->normTime($m[2]);
				if (! $from || ! $to) {
					continue;
				}
				$key = $from . '-' . $to;
				if (isset($seen[ $key ])) {
					continue;
				}
				$seen[ $key ]  = true;
				$intervals[]   = array('from' => $from, 'to' => $to);
				if (count($intervals) >= 2) {
					break 2;
				}
			}
		}

		usort(
			$intervals,
			function ($a, $b) {
				return strcmp($a['from'], $b['from']);
			}
		);

		return $intervals;
	}

	/**
	 * @param array $rules
	 * @return array
	 */
	private function dedupeRules(array $rules)
	{
		if (count($rules) < 2) {
			return $rules;
		}

		$out = array();
		foreach ($rules as $rule) {
			$dup = false;
			foreach ($out as $existing) {
				if ($existing['days'] === $rule['days'] && $existing['intervals'] === $rule['intervals']) {
					$dup = true;
					break;
				}
				if (count($rule['days']) === 1
					&& in_array($rule['days'][0], $existing['days'], true)
					&& $existing['intervals'] === $rule['intervals']
				) {
					$dup = true;
					break;
				}
			}
			if (! $dup) {
				$out[] = $rule;
			}
		}

		return $out;
	}

	/**
	 * @param string $t
	 * @return string|null
	 */
	private function normTime($t)
	{
		$t = strtolower(trim(preg_replace('/\s+/', '', $t)));
		$t = str_replace('.', ':', $t);

		if (preg_match('/^(\d{1,2}):(\d{2})(am|pm)?$/', $t, $m)) {
			$h = (int) $m[1];
			$i = (int) $m[2];
			if (! empty($m[3])) {
				if ($m[3] === 'pm' && $h < 12) {
					$h += 12;
				}
				if ($m[3] === 'am' && $h === 12) {
					$h = 0;
				}
			}
			if ($h > 24 || $i > 59) {
				return null;
			}

			return sprintf('%02d:%02d', $h, $i);
		}

		if (preg_match('/^(\d{1,2})(am|pm)$/', $t, $m)) {
			$h = (int) $m[1];
			if ($m[2] === 'pm' && $h < 12) {
				$h += 12;
			}
			if ($m[2] === 'am' && $h === 12) {
				$h = 0;
			}

			return sprintf('%02d:00', $h);
		}

		if (preg_match('/^(\d{1,2})$/', $t, $m)) {
			$h = (int) $m[1];
			if ($h > 24) {
				return null;
			}

			return sprintf('%02d:00', $h);
		}

		return null;
	}
}
