<?php

/**
 * REST: /chat — the LLM relay for merchant sites (namespace salon-ai/v1).
 *
 * Merchants never hold an LLM key: the plugin posts
 * {auth, message, instructions, context, tools, history} here, the store
 * server verifies the site, consumes one query from the ledger, calls the
 * configured OpenAI-compatible endpoint (OpenRouter by default, see
 * config.sample.php) and returns {message, tool_call, draft, usage}.
 */
class SLN_AI_Proxy_Chat
{
	const NS = 'salon-ai/v1';

	public static function register()
	{
		register_rest_route(
			self::NS,
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				// Site auth (token + ledger) is enforced in the callback.
				'permission_callback' => '__return_true',
				'callback'            => array(__CLASS__, 'handle'),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle(WP_REST_Request $request)
	{
		$payload = $request->get_json_params();
		if (! is_array($payload)) {
			return new WP_Error('sln_ai_chat_body', 'Invalid JSON body.', array('status' => 400));
		}

		$rawAuth = isset($payload['auth']) && is_array($payload['auth']) ? $payload['auth'] : array();
		$auth    = SLN_AI_Proxy_REST::verifyAuth($rawAuth);
		if (is_wp_error($auth)) {
			return $auth;
		}

		$apiKey = defined('SLN_AI_LLM_API_KEY') ? (string) SLN_AI_LLM_API_KEY : '';
		if ($apiKey === '' || $apiKey === 'REPLACE_ME_OPENROUTER_KEY') {
			return new WP_Error(
				'sln_ai_chat_unconfigured',
				'The Salon AI cloud is not configured yet (missing LLM key).',
				array('status' => 503)
			);
		}

		// Bill first (atomic), refund below when the LLM call itself fails.
		$usage = SLN_AI_Proxy_Ledger::consume($auth);
		if (is_wp_error($usage)) {
			return $usage; // 402 with usage payload, or 503 when the ledger is busy.
		}

		$result = self::callLlm($payload);
		if (is_wp_error($result)) {
			SLN_AI_Proxy_Ledger::refundOne($auth);

			return $result;
		}

		$result['usage'] = $usage;

		return rest_ensure_response($result);
	}

	/**
	 * OpenAI-compatible Chat Completions call (OpenRouter default), mirroring
	 * the request/response mapping of the plugin's direct-LLM path.
	 *
	 * @param array $payload
	 * @return array{message:string,tool_call:array|null,draft:null}|WP_Error
	 */
	private static function callLlm(array $payload)
	{
		$base  = defined('SLN_AI_LLM_BASE') ? untrailingslashit((string) SLN_AI_LLM_BASE) : 'https://openrouter.ai/api/v1';
		$model = defined('SLN_AI_LLM_MODEL') ? (string) SLN_AI_LLM_MODEL : 'openai/gpt-4o-mini';

		$messages = array(
			array(
				'role'    => 'system',
				'content' => self::systemPrompt($payload),
			),
		);
		$history  = isset($payload['history']) && is_array($payload['history']) ? $payload['history'] : array();
		foreach ($history as $turn) {
			if (empty($turn['role']) || empty($turn['content'])) {
				continue;
			}
			$messages[] = array(
				'role'    => $turn['role'] === 'assistant' ? 'assistant' : 'user',
				'content' => (string) $turn['content'],
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => isset($payload['message']) ? (string) $payload['message'] : '',
		);

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
					'parameters'  => isset($tool['parameters']) ? $tool['parameters'] : array('type' => 'object'),
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

		$headers = array(
			'Authorization' => 'Bearer ' . SLN_AI_LLM_API_KEY,
			'Content-Type'  => 'application/json',
		);
		if (strpos($base, 'openrouter.ai') !== false) {
			$headers['HTTP-Referer'] = home_url('/');
			$headers['X-Title']      = 'Salon Booking System AI Setup';
		}

		$response = wp_remote_post(
			$base . '/chat/completions',
			array(
				'timeout' => 45,
				'headers' => $headers,
				'body'    => wp_json_encode($body),
			)
		);

		if (is_wp_error($response)) {
			return new WP_Error(
				'sln_ai_llm_unreachable',
				'AI model unreachable: ' . $response->get_error_message(),
				array('status' => 502)
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true);
		if ($code === 429) {
			return new WP_Error(
				'sln_ai_rate_limited',
				'AI service rate limit reached. Please try again later.',
				array('status' => 429)
			);
		}
		if ($code < 200 || $code >= 300 || ! is_array($data)) {
			$err = is_array($data) && isset($data['error']['message'])
				? (string) $data['error']['message']
				: 'AI model returned an unexpected response.';

			return new WP_Error('sln_ai_llm_error', $err, array('status' => 502));
		}

		$choice = isset($data['choices'][0]['message']) ? $data['choices'][0]['message'] : array();
		$out    = array(
			'message'   => isset($choice['content']) ? trim((string) $choice['content']) : '',
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
				'name'      => isset($fn['name']) ? (string) $fn['name'] : '',
				'arguments' => $args,
			);
		}

		return $out;
	}

	/**
	 * The plugin already ships the full instruction set (language, tools,
	 * safety, edition gates) in the payload; the site context is appended as
	 * a labelled JSON block.
	 *
	 * @param array $payload
	 * @return string
	 */
	private static function systemPrompt(array $payload)
	{
		$system = isset($payload['instructions']) ? (string) $payload['instructions'] : '';

		$context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : array();
		if ($context) {
			$system .= "\n\nSite context (JSON):\n" . wp_json_encode($context);
		}

		$system .= "\n\nPrefer real service/assistant ids from site context. "
			. 'When details are missing for a correct answer, ask clarifying questions before guessing. '
			. 'Never invent payment, SMS, or OAuth secrets.';

		return $system;
	}
}
