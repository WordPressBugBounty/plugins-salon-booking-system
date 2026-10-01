<?php

/**
 * Provider-neutral transcript used by the AI agent loop, plus adapters for
 * OpenAI-compatible Chat Completions and the Anthropic Messages API.
 *
 * Canonical message shapes:
 *   user:      {role:'user', content:string}
 *   assistant: {role:'assistant', content:string, tool_calls?:[{id,name,arguments:array}]}
 *   tool:      {role:'tool', tool_call_id:string, name:string, content:string, is_error?:bool}
 *
 * The cloud proxy carries a mirror of toOpenAi() (SLN_AI_Proxy_Chat::toOpenAiMessages).
 */
class SLN_AI_MessageFormat
{
	const ERROR_PREFIX = 'ERROR: ';

	/**
	 * Keep only valid messages; pair every tool call with a result (synthetic
	 * error when missing) and drop results whose call is not in the transcript.
	 * Both providers reject unpaired tool calls/results with a 400.
	 *
	 * @param array $messages
	 * @return array
	 */
	public static function sanitize(array $messages)
	{
		$out     = array();
		$pending = array();

		foreach ($messages as $msg) {
			if (! is_array($msg) || empty($msg['role'])) {
				continue;
			}
			$role = (string) $msg['role'];

			if ($role === 'tool') {
				$id = isset($msg['tool_call_id']) ? (string) $msg['tool_call_id'] : '';
				if ($id === '' || ! isset($pending[ $id ])) {
					continue;
				}
				$out[] = array(
					'role'         => 'tool',
					'tool_call_id' => $id,
					'name'         => isset($msg['name']) ? (string) $msg['name'] : $pending[ $id ],
					'content'      => isset($msg['content']) ? (string) $msg['content'] : '',
					'is_error'     => ! empty($msg['is_error']),
				);
				unset($pending[ $id ]);
				continue;
			}

			$out     = array_merge($out, self::missingResults($pending));
			$pending = array();

			if ($role === 'assistant') {
				$calls = array();
				foreach (isset($msg['tool_calls']) && is_array($msg['tool_calls']) ? $msg['tool_calls'] : array() as $call) {
					if (empty($call['id']) || empty($call['name'])) {
						continue;
					}
					$calls[] = array(
						'id'        => (string) $call['id'],
						'name'      => (string) $call['name'],
						'arguments' => isset($call['arguments']) && is_array($call['arguments']) ? $call['arguments'] : array(),
					);
					$pending[ (string) $call['id'] ] = (string) $call['name'];
				}
				$content = isset($msg['content']) ? (string) $msg['content'] : '';
				if ($content === '' && ! $calls) {
					continue;
				}
				$entry = array(
					'role'    => 'assistant',
					'content' => $content,
				);
				if ($calls) {
					$entry['tool_calls'] = $calls;
				}
				$out[] = $entry;
				continue;
			}

			if ($role === 'user') {
				$content = isset($msg['content']) ? (string) $msg['content'] : '';
				if ($content !== '') {
					$out[] = array(
						'role'    => 'user',
						'content' => $content,
					);
				}
			}
		}

		return array_merge($out, self::missingResults($pending));
	}

	/**
	 * @param array $messages Canonical transcript.
	 * @return array OpenAI Chat Completions messages (without the system message).
	 */
	public static function toOpenAi(array $messages)
	{
		$out = array();
		foreach (self::sanitize($messages) as $msg) {
			if ($msg['role'] === 'tool') {
				$content = $msg['content'];
				if ($msg['is_error'] && strpos($content, 'ERROR') !== 0) {
					$content = self::ERROR_PREFIX . $content;
				}
				$out[] = array(
					'role'         => 'tool',
					'tool_call_id' => $msg['tool_call_id'],
					'content'      => $content,
				);
				continue;
			}
			if ($msg['role'] === 'assistant' && ! empty($msg['tool_calls'])) {
				$calls = array();
				foreach ($msg['tool_calls'] as $call) {
					$calls[] = array(
						'id'       => $call['id'],
						'type'     => 'function',
						'function' => array(
							'name'      => $call['name'],
							'arguments' => $call['arguments'] ? wp_json_encode($call['arguments']) : '{}',
						),
					);
				}
				$out[] = array(
					'role'       => 'assistant',
					'content'    => $msg['content'] !== '' ? $msg['content'] : null,
					'tool_calls' => $calls,
				);
				continue;
			}
			$out[] = array(
				'role'    => $msg['role'],
				'content' => $msg['content'],
			);
		}

		return $out;
	}

	/**
	 * Anthropic requires alternating user/assistant turns starting with user;
	 * tool results travel as tool_result blocks in the user turn that follows
	 * the assistant tool_use blocks.
	 *
	 * @param array $messages Canonical transcript.
	 * @return array Anthropic Messages API messages.
	 */
	public static function toAnthropic(array $messages)
	{
		$out = array();
		foreach (self::sanitize($messages) as $msg) {
			if ($msg['role'] === 'assistant') {
				$blocks = array();
				if ($msg['content'] !== '') {
					$blocks[] = array(
						'type' => 'text',
						'text' => $msg['content'],
					);
				}
				foreach (isset($msg['tool_calls']) ? $msg['tool_calls'] : array() as $call) {
					$blocks[] = array(
						'type'  => 'tool_use',
						'id'    => $call['id'],
						'name'  => $call['name'],
						'input' => $call['arguments'] ? $call['arguments'] : new stdClass(),
					);
				}
				self::appendAnthropic($out, 'assistant', $blocks);
				continue;
			}

			if ($msg['role'] === 'tool') {
				$block = array(
					'type'        => 'tool_result',
					'tool_use_id' => $msg['tool_call_id'],
					'content'     => $msg['content'] !== '' ? $msg['content'] : '(empty)',
				);
				if ($msg['is_error']) {
					$block['is_error'] = true;
				}
				self::appendAnthropic($out, 'user', array($block));
				continue;
			}

			self::appendAnthropic(
				$out,
				'user',
				array(
					array(
						'type' => 'text',
						'text' => $msg['content'],
					),
				)
			);
		}

		while ($out && $out[0]['role'] !== 'user') {
			array_shift($out);
		}

		return $out;
	}

	/**
	 * @param array $choice OpenAI choices[0].message
	 * @return array{message:string,tool_calls:array}
	 */
	public static function parseOpenAiChoice(array $choice)
	{
		$calls = array();
		foreach (isset($choice['tool_calls']) && is_array($choice['tool_calls']) ? $choice['tool_calls'] : array() as $i => $call) {
			if (empty($call['function']['name'])) {
				continue;
			}
			$args = array();
			if (! empty($call['function']['arguments'])) {
				$decoded = json_decode((string) $call['function']['arguments'], true);
				$args    = is_array($decoded) ? $decoded : array();
			}
			$calls[] = array(
				'id'        => ! empty($call['id']) ? (string) $call['id'] : self::syntheticId($i),
				'name'      => (string) $call['function']['name'],
				'arguments' => $args,
			);
		}

		return array(
			'message'    => isset($choice['content']) ? trim((string) $choice['content']) : '',
			'tool_calls' => $calls,
		);
	}

	/**
	 * @param array $content Anthropic response content blocks.
	 * @return array{message:string,tool_calls:array}
	 */
	public static function parseAnthropicContent(array $content)
	{
		$text  = '';
		$calls = array();
		foreach ($content as $i => $block) {
			if (! is_array($block) || empty($block['type'])) {
				continue;
			}
			if ($block['type'] === 'text' && isset($block['text'])) {
				$text .= (string) $block['text'];
			}
			if ($block['type'] === 'tool_use' && ! empty($block['name'])) {
				$calls[] = array(
					'id'        => ! empty($block['id']) ? (string) $block['id'] : self::syntheticId($i),
					'name'      => (string) $block['name'],
					'arguments' => isset($block['input']) && is_array($block['input']) ? $block['input'] : array(),
				);
			}
		}

		return array(
			'message'    => trim($text),
			'tool_calls' => $calls,
		);
	}

	/**
	 * Multibyte-safe truncation with an ellipsis marker.
	 *
	 * @param string $text
	 * @param int    $max
	 * @return string
	 */
	public static function truncate($text, $max)
	{
		$text = (string) $text;
		$max  = max(20, (int) $max);
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
		}

		return strlen($text) > $max ? substr($text, 0, $max - 1) . '…' : $text;
	}

	/**
	 * @param int|string $seed
	 * @return string
	 */
	public static function syntheticId($seed)
	{
		return 'call_' . substr(md5(uniqid((string) $seed, true)), 0, 12);
	}

	/**
	 * @param array $pending id => tool name
	 * @return array
	 */
	private static function missingResults(array $pending)
	{
		$out = array();
		foreach ($pending as $id => $name) {
			$out[] = array(
				'role'         => 'tool',
				'tool_call_id' => (string) $id,
				'name'         => (string) $name,
				'content'      => 'No result was recorded for this call.',
				'is_error'     => true,
			);
		}

		return $out;
	}

	/**
	 * Merge consecutive same-role turns (Anthropic rejects them).
	 *
	 * @param array  $out
	 * @param string $role
	 * @param array  $blocks
	 */
	private static function appendAnthropic(array &$out, $role, array $blocks)
	{
		if (! $blocks) {
			return;
		}
		$last = count($out) - 1;
		if ($last >= 0 && $out[ $last ]['role'] === $role) {
			$out[ $last ]['content'] = array_merge($out[ $last ]['content'], $blocks);

			return;
		}
		$out[] = array(
			'role'    => $role,
			'content' => $blocks,
		);
	}
}
