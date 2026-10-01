<?php

/**
 * REST: /chat — the LLM relay for merchant sites (namespace salon-ai/v1).
 *
 * Merchants never hold an LLM key: the plugin posts its request here, the store
 * server verifies the site, bills the ledger, calls the configured
 * OpenAI-compatible endpoint (OpenRouter by default, see config.sample.php).
 *
 * Protocol 1 (legacy plugins): {auth, message, instructions, context, tools, history}
 *   → {message, tool_call, draft, usage}; one query billed per request.
 * Protocol 2 (agent loop): {auth, protocol:2, turn_id, messages, instructions,
 *   context, tools, last_guidance, final} → {protocol:2, message, tool_calls,
 *   tool_call, draft, usage}; one query billed per turn_id. The proxy stays
 *   stateless: the plugin sends the whole transcript on every call.
 */
class SLN_AI_Proxy_Chat
{
	const NS = 'salon-ai/v1';

	/** Guard rails on what a (possibly modified) client can make us send upstream. */
	const MAX_MESSAGES    = 80;
	const MAX_CONTENT_LEN = 8000;

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

		$protocol = isset($payload['protocol']) ? (int) $payload['protocol'] : 1;
		$turnId   = isset($payload['turn_id']) ? (string) $payload['turn_id'] : '';
		$perTurn  = $protocol >= 2 && SLN_AI_Proxy_Ledger::isValidTurnId($turnId);

		// Bill first (atomic), refund below when the charging LLM call fails.
		if ($perTurn) {
			$billing = SLN_AI_Proxy_Ledger::consumeForTurn($auth, $turnId);
			if (is_wp_error($billing)) {
				return $billing;
			}
			$usage   = $billing['usage'];
			$charged = $billing['charged'];
		} else {
			$usage = SLN_AI_Proxy_Ledger::consume($auth);
			if (is_wp_error($usage)) {
				return $usage; // 402 with usage payload, or 503 when the ledger is busy.
			}
			$charged = true;
		}

		$result = $protocol >= 2 ? self::callLlmTurn($payload) : self::callLlm($payload);
		if (is_wp_error($result)) {
			if ($charged) {
				if ($perTurn) {
					SLN_AI_Proxy_Ledger::refundTurn($auth, $turnId);
				} else {
					SLN_AI_Proxy_Ledger::refundOne($auth);
				}
			}

			return $result;
		}

		$result['usage'] = $usage;

		return rest_ensure_response($result);
	}

	/**
	 * Protocol 1: single-shot OpenAI-compatible Chat Completions call.
	 *
	 * @param array $payload
	 * @return array{message:string,tool_call:array|null,draft:null}|WP_Error
	 */
	private static function callLlm(array $payload)
	{
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

		$data = self::postCompletions($messages, self::buildTools($payload), false);
		if (is_wp_error($data)) {
			return $data;
		}

		$choice = isset($data['choices'][0]['message']) ? $data['choices'][0]['message'] : array();
		$calls  = self::parseToolCalls($choice);

		return array(
			'message'   => isset($choice['content']) ? trim((string) $choice['content']) : '',
			'tool_call' => $calls ? array('name' => $calls[0]['name'], 'arguments' => $calls[0]['arguments']) : null,
			'draft'     => null,
		);
	}

	/**
	 * Protocol 2: one step of the merchant-side agent loop. Returns every tool
	 * call (with ids) so the plugin can execute them and send results back.
	 *
	 * @param array $payload
	 * @return array|WP_Error
	 */
	private static function callLlmTurn(array $payload)
	{
		$canonical = isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : array();
		if (! $canonical) {
			return new WP_Error('sln_ai_chat_body', 'Missing messages.', array('status' => 400));
		}
		if (count($canonical) > self::MAX_MESSAGES) {
			$canonical = array_slice($canonical, -self::MAX_MESSAGES);
		}

		$messages = array_merge(
			array(
				array(
					'role'    => 'system',
					'content' => self::systemPrompt($payload),
				),
			),
			self::toOpenAiMessages($canonical)
		);

		$final = ! empty($payload['final']);
		$data  = self::postCompletions($messages, self::buildTools($payload), $final);
		if (is_wp_error($data)) {
			return $data;
		}

		$choice = isset($data['choices'][0]['message']) ? $data['choices'][0]['message'] : array();
		$calls  = $final ? array() : self::parseToolCalls($choice);

		return array(
			'protocol'   => 2,
			'message'    => isset($choice['content']) ? trim((string) $choice['content']) : '',
			'tool_calls' => $calls,
			'tool_call'  => $calls ? array('name' => $calls[0]['name'], 'arguments' => $calls[0]['arguments']) : null,
			'draft'      => null,
		);
	}

	/**
	 * @param array $payload
	 * @return array
	 */
	private static function buildTools(array $payload)
	{
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

		return $tools;
	}

	/**
	 * @param array $messages OpenAI-shaped messages (system first).
	 * @param array $tools
	 * @param bool  $final    Keep tool schemas (the transcript references them) but forbid new calls.
	 * @return array|WP_Error Decoded response body.
	 */
	private static function postCompletions(array $messages, array $tools, $final)
	{
		$base  = defined('SLN_AI_LLM_BASE') ? untrailingslashit((string) SLN_AI_LLM_BASE) : 'https://openrouter.ai/api/v1';
		$model = defined('SLN_AI_LLM_MODEL') ? (string) SLN_AI_LLM_MODEL : 'openai/gpt-4o-mini';

		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => 0.2,
			'max_tokens'  => 768,
		);
		if ($tools) {
			$body['tools']       = $tools;
			$body['tool_choice'] = $final ? 'none' : 'auto';
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

		return $data;
	}

	/**
	 * @param array $choice OpenAI choices[0].message
	 * @return array<int,array{id:string,name:string,arguments:array}>
	 */
	private static function parseToolCalls(array $choice)
	{
		$calls = array();
		if (empty($choice['tool_calls']) || ! is_array($choice['tool_calls'])) {
			return $calls;
		}
		foreach ($choice['tool_calls'] as $i => $call) {
			if (empty($call['function']['name'])) {
				continue;
			}
			$args = array();
			if (! empty($call['function']['arguments'])) {
				$decoded = json_decode((string) $call['function']['arguments'], true);
				$args    = is_array($decoded) ? $decoded : array();
			}
			$calls[] = array(
				'id'        => ! empty($call['id']) ? (string) $call['id'] : 'call_' . $i,
				'name'      => (string) $call['function']['name'],
				'arguments' => $args,
			);
		}

		return $calls;
	}

	/**
	 * Canonical transcript → OpenAI Chat Completions messages. Mirrors
	 * SLN_AI_MessageFormat::toOpenAi() in the merchant plugin (kept separate:
	 * the proxy is deployed on its own). Every declared tool call is paired
	 * with a result, orphan results are dropped — the API rejects both.
	 *
	 * @param array $canonical
	 * @return array
	 */
	private static function toOpenAiMessages(array $canonical)
	{
		$out     = array();
		$pending = array();

		$flushMissing = function () use (&$out, &$pending) {
			foreach ($pending as $id => $unused) {
				$out[] = array(
					'role'         => 'tool',
					'tool_call_id' => (string) $id,
					'content'      => 'ERROR: no result recorded for this call.',
				);
			}
			$pending = array();
		};

		foreach ($canonical as $msg) {
			if (! is_array($msg) || empty($msg['role'])) {
				continue;
			}
			$role    = (string) $msg['role'];
			$content = isset($msg['content']) ? self::clip((string) $msg['content']) : '';

			if ($role === 'tool') {
				$id = isset($msg['tool_call_id']) ? (string) $msg['tool_call_id'] : '';
				if ($id === '' || ! isset($pending[ $id ])) {
					continue;
				}
				unset($pending[ $id ]);
				if (! empty($msg['is_error']) && strpos($content, 'ERROR') !== 0) {
					$content = 'ERROR: ' . $content;
				}
				$out[] = array(
					'role'         => 'tool',
					'tool_call_id' => $id,
					'content'      => $content,
				);
				continue;
			}

			$flushMissing();

			if ($role === 'assistant') {
				$entry = array(
					'role'    => 'assistant',
					'content' => $content,
				);
				$calls = isset($msg['tool_calls']) && is_array($msg['tool_calls']) ? $msg['tool_calls'] : array();
				$mapped = array();
				foreach ($calls as $call) {
					if (empty($call['id']) || empty($call['name'])) {
						continue;
					}
					$args     = isset($call['arguments']) && is_array($call['arguments']) ? $call['arguments'] : array();
					$mapped[] = array(
						'id'       => (string) $call['id'],
						'type'     => 'function',
						'function' => array(
							'name'      => (string) $call['name'],
							'arguments' => $args ? wp_json_encode($args) : '{}',
						),
					);
					$pending[ (string) $call['id'] ] = true;
				}
				if ($mapped) {
					$entry['tool_calls'] = $mapped;
					if ($content === '') {
						$entry['content'] = null;
					}
				} elseif ($content === '') {
					continue;
				}
				$out[] = $entry;
				continue;
			}

			if ($content === '') {
				continue;
			}
			$out[] = array(
				'role'    => 'user',
				'content' => $content,
			);
		}
		$flushMissing();

		return $out;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private static function clip($text)
	{
		if (strlen($text) <= self::MAX_CONTENT_LEN) {
			return $text;
		}

		return substr($text, 0, self::MAX_CONTENT_LEN) . '…';
	}

	/**
	 * The plugin already ships the full instruction set (language, tools,
	 * safety, edition gates) in the payload; the site context and the last
	 * guidance result are appended as labelled JSON blocks.
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

		$lastGuidance = isset($payload['last_guidance']) && is_array($payload['last_guidance']) ? $payload['last_guidance'] : array();
		if ($lastGuidance) {
			$system .= "\n\nLast guidance result shown to the merchant (JSON, for short follow-ups):\n"
				. self::clip((string) wp_json_encode($lastGuidance));
		}

		$system .= "\n\nPrefer real service/assistant ids from site context. "
			. 'When details are missing for a correct answer, ask clarifying questions before guessing. '
			. 'Never invent payment, SMS, or OAuth secrets.';

		if (! empty($payload['final'])) {
			$system .= "\n\nTool budget for this message is exhausted. Do not call tools. "
				. 'Answer now in the merchant\'s language: state plainly what you found, what you could not verify, and what they can do next.';
		}

		return $system;
	}
}
