<?php

/**
 * Merchant-side agent loop for AI Setup.
 *
 * Each iteration calls the model with the full transcript; read-only (guidance)
 * tool calls are executed and their results go back to the model; the loop ends
 * when the model answers with text only, when a write tool produces a preview
 * card (human confirmation required), or when the iteration budget is spent
 * (then one last call without new tool calls writes an honest final answer).
 *
 * The model step and the tool registry are injected so the loop runs without
 * WordPress in tests/ai/agent.php.
 */
class SLN_AI_Agent
{
	const MAX_ITERATIONS = 5;
	/** Wall-clock seconds before forcing the final answer (hosts cap PHP at ~30–60 s). */
	const TIME_BUDGET = 40;
	/** Characters of a tool result the model sees in the current turn (summary + data). */
	const RESULT_SUMMARY_MAX = 1500;
	const RESULT_DATA_MAX    = 1500;
	/** Readable URLs and accents in tool results (fewer tokens, verbatim links). */
	const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

	const STATUS_FINAL   = 'final';
	const STATUS_PREVIEW = 'preview';
	const STATUS_LEGACY  = 'legacy';
	const STATUS_ERROR   = 'error';

	/** @var object has(), getToolMeta(), buildPreview() — SLN_AI_ToolRegistry in production. */
	private $registry;

	/** @var SLN_Plugin|null */
	private $plugin;

	/** @var callable (array $payload, bool $allowMock): array|WP_Error */
	private $step;

	/** @var callable (): float */
	private $clock;

	/**
	 * @param object          $registry
	 * @param SLN_Plugin|null $plugin
	 * @param callable        $step
	 * @param callable|null   $clock
	 */
	public function __construct($registry, $plugin, $step, $clock = null)
	{
		$this->registry = $registry;
		$this->plugin   = $plugin;
		$this->step     = $step;
		$this->clock    = $clock ? $clock : function () {
			return microtime(true);
		};
	}

	/**
	 * @return int
	 */
	public static function maxIterations()
	{
		$max = defined('SLN_AI_AGENT_MAX_ITERATIONS') ? (int) SLN_AI_AGENT_MAX_ITERATIONS : self::MAX_ITERATIONS;

		return max(1, (int) apply_filters('sln_ai_agent_max_iterations', $max));
	}

	/**
	 * @param array $request message (user text), history (canonical window), turn_id,
	 *                       tools, instructions, context, last_guidance,
	 *                       legacy (message/history/draft/last_guidance for the mock).
	 * @return array status, message, messages (canonical, this turn incl. the user
	 *               message), preview, guidance, legacy, backend, usage, steps, error.
	 */
	public function run(array $request)
	{
		$userText = isset($request['message']) ? (string) $request['message'] : '';
		$history  = isset($request['history']) && is_array($request['history']) ? $request['history'] : array();
		$lang     = SLN_AI_Language::detect($userText);

		$turn = array(
			array(
				'role'    => 'user',
				'content' => $userText,
			),
		);
		$out  = array(
			'status'   => self::STATUS_FINAL,
			'message'  => '',
			'messages' => array(),
			'preview'  => null,
			'guidance' => array(),
			'legacy'   => null,
			'backend'  => null,
			'usage'    => null,
			'steps'    => 0,
			'error'    => null,
		);

		$max     = self::maxIterations();
		$started = call_user_func($this->clock);

		for ($i = 0; $i < $max; $i++) {
			if ($i > 0 && ( call_user_func($this->clock) - $started ) > self::TIME_BUDGET) {
				break;
			}

			$result = $this->callModel($request, array_merge($history, $turn), false, $i === 0, $out);
			if (is_wp_error($result)) {
				return $this->finishWithError($out, $turn, $result, $i === 0, $lang);
			}

			if ((int) $result['protocol'] !== 2) {
				if ($i === 0) {
					$out['status'] = self::STATUS_LEGACY;
					$out['legacy'] = $result;

					return $out;
				}
				$result['tool_calls'] = array();
			}

			$text  = trim((string) $result['message']);
			$calls = $this->normalizeCalls($result['tool_calls']);

			if (! $calls) {
				return $this->finish($out, $turn, $text, $lang);
			}

			$turn[] = array(
				'role'       => 'assistant',
				'content'    => $text,
				'tool_calls' => $calls,
			);

			$preview = $this->executeCalls($calls, $userText, $turn, $out);
			if ($preview) {
				$out['status']   = self::STATUS_PREVIEW;
				$out['preview']  = $preview;
				$out['message']  = $text;
				$out['messages'] = $turn;

				return $out;
			}
		}

		$result = $this->callModel($request, array_merge($history, $turn), true, false, $out);
		if (is_wp_error($result)) {
			return $this->finishWithError($out, $turn, $result, false, $lang);
		}

		return $this->finish($out, $turn, trim((string) $result['message']), $lang);
	}

	/**
	 * Run one batch of tool calls, appending one result message per call.
	 * Returns the preview descriptor when a write tool produced a confirm card.
	 *
	 * @param array  $calls
	 * @param string $userText
	 * @param array  $turn
	 * @param array  $out
	 * @return array|null
	 */
	private function executeCalls(array $calls, $userText, array &$turn, array &$out)
	{
		$results = array();
		$reads   = array();
		$writes  = array();

		foreach ($calls as $call) {
			if (! $this->registry->has($call['name'])) {
				$results[ $call['id'] ] = $this->errorResult(
					$call,
					sprintf('Tool "%s" is not available. Use only the provided tools.', $call['name'])
				);
				continue;
			}
			if ($this->tierOf($call['name']) === 'guidance') {
				$reads[] = $call;
			} else {
				$writes[] = $call;
			}
		}

		foreach ($reads as $call) {
			$results[ $call['id'] ] = $this->runGuidance($call, $userText, $out);
		}

		$preview = null;
		foreach ($writes as $n => $call) {
			if ($reads) {
				$results[ $call['id'] ] = $this->toolResult(
					$call,
					'Deferred: not executed because it was requested together with lookups. Read the lookup results above, then call this tool again with the correct arguments if the change is still needed.'
				);
				continue;
			}
			if ($n > 0 || $preview) {
				$results[ $call['id'] ] = $this->toolResult(
					$call,
					'Skipped: only one change can be proposed at a time. Propose it again after the merchant confirms or cancels the pending one.'
				);
				continue;
			}

			$built = $this->registry->buildPreview($call['name'], $this->withUserMessage($call['arguments'], $userText), $this->plugin);
			if (is_wp_error($built)) {
				$results[ $call['id'] ] = $this->errorResult($call, $built->get_error_message());
				continue;
			}
			if (! empty($built['guidance'])) {
				$results[ $call['id'] ] = $this->guidanceResult($call, $built, $out);
				continue;
			}

			$preview = array(
				'tool'         => $call['name'],
				'arguments'    => $call['arguments'],
				'data'         => $built,
				'tool_call_id' => $call['id'],
			);
			$results[ $call['id'] ] = $this->toolResult($call, self::pendingResultText());
		}

		foreach ($calls as $call) {
			$turn[] = $results[ $call['id'] ];
		}

		return $preview;
	}

	/**
	 * Placeholder result for a write tool awaiting the merchant; rewritten by the
	 * controller on confirm / cancel / supersede.
	 *
	 * @return string
	 */
	public static function pendingResultText()
	{
		return 'PENDING: a preview card was shown to the merchant, awaiting confirmation. Nothing is saved yet. Do not call this tool again for the same change.';
	}

	/**
	 * @param array  $call
	 * @param string $userText
	 * @param array  $out
	 * @return array
	 */
	private function runGuidance(array $call, $userText, array &$out)
	{
		$built = $this->registry->buildPreview($call['name'], $this->withUserMessage($call['arguments'], $userText), $this->plugin);
		if (is_wp_error($built)) {
			return $this->errorResult($call, $built->get_error_message());
		}

		return $this->guidanceResult($call, $built, $out);
	}

	/**
	 * @param array $call
	 * @param array $built Tool preview payload.
	 * @param array $out
	 * @return array
	 */
	private function guidanceResult(array $call, array $built, array &$out)
	{
		// Internal tools (e.g. report_docs_discrepancy) never surface to the merchant.
		if (empty($built['internal'])) {
			$out['guidance'][] = array(
				'name'      => $call['name'],
				'arguments' => $call['arguments'],
				'preview'   => $built,
			);
		}

		// Tools may hand the model a dedicated structured payload instead of their
		// merchant-facing summary (e.g. lookup_docs: title, url, updated_at, excerpt).
		if (isset($built['model_data']) && is_array($built['model_data'])) {
			return $this->toolResult(
				$call,
				'DATA (JSON):' . "\n" . SLN_AI_MessageFormat::truncate(
					(string) wp_json_encode($built['model_data'], self::JSON_FLAGS),
					self::RESULT_SUMMARY_MAX + self::RESULT_DATA_MAX
				)
			);
		}

		$summary = isset($built['summary']) ? (string) $built['summary'] : '';
		$data    = $built;
		unset($data['summary'], $data['tool'], $data['tier'], $data['guidance'], $data['ok'], $data['internal']);
		if (isset($data['arguments']) && is_array($data['arguments'])) {
			unset($data['arguments']['_user_message']);
		}

		$content = 'SUMMARY:' . "\n" . SLN_AI_MessageFormat::truncate($summary, self::RESULT_SUMMARY_MAX);
		if ($data) {
			$content .= "\n\nDATA (JSON):\n" . SLN_AI_MessageFormat::truncate((string) wp_json_encode($data, self::JSON_FLAGS), self::RESULT_DATA_MAX);
		}

		return $this->toolResult($call, $content);
	}

	/**
	 * @param array $request
	 * @param array $transcript
	 * @param bool  $final
	 * @param bool  $allowMock
	 * @param array $out
	 * @return array|WP_Error
	 */
	private function callModel(array $request, array $transcript, $final, $allowMock, array &$out)
	{
		$payload = array(
			'turn_id'       => isset($request['turn_id']) ? (string) $request['turn_id'] : '',
			'messages'      => SLN_AI_MessageFormat::sanitize($transcript),
			'tools'         => isset($request['tools']) ? $request['tools'] : array(),
			'instructions'  => isset($request['instructions']) ? (string) $request['instructions'] : '',
			'context'       => isset($request['context']) ? $request['context'] : array(),
			'last_guidance' => isset($request['last_guidance']) ? $request['last_guidance'] : null,
			'final'         => (bool) $final,
		);
		// Legacy single-shot keys, only read by the local mock fallback.
		if (! empty($request['legacy']) && is_array($request['legacy'])) {
			$payload = array_merge($request['legacy'], $payload);
		}

		$out['steps']++;
		$result = call_user_func($this->step, $payload, $allowMock);
		if (is_wp_error($result)) {
			return $result;
		}

		// Once the proxy has billed this turn, a later direct-LLM fallback step must not trigger local billing too.
		$proxyBilled = $out['backend'] === 'proxy' && $out['usage'] !== null;
		if (! empty($result['backend']) && ! $proxyBilled) {
			$out['backend'] = $result['backend'];
		}
		if (! empty($result['usage']) && is_array($result['usage'])) {
			$out['usage'] = $result['usage'];
		}
		$result['protocol']   = isset($result['protocol']) ? (int) $result['protocol'] : 1;
		$result['message']    = isset($result['message']) ? (string) $result['message'] : '';
		$result['tool_calls'] = isset($result['tool_calls']) && is_array($result['tool_calls']) ? $result['tool_calls'] : array();

		return $result;
	}

	/**
	 * @param array $calls
	 * @return array
	 */
	private function normalizeCalls(array $calls)
	{
		$out  = array();
		$seen = array();
		foreach ($calls as $i => $call) {
			if (! is_array($call) || empty($call['name'])) {
				continue;
			}
			$id = ! empty($call['id']) ? (string) $call['id'] : SLN_AI_MessageFormat::syntheticId($i);
			if (isset($seen[ $id ])) {
				$id = SLN_AI_MessageFormat::syntheticId($i);
			}
			$seen[ $id ] = true;
			$out[]       = array(
				'id'        => $id,
				'name'      => (string) $call['name'],
				'arguments' => isset($call['arguments']) && is_array($call['arguments']) ? $call['arguments'] : array(),
			);
		}

		return $out;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	private function tierOf($name)
	{
		$meta = $this->registry->getToolMeta($name);

		return is_array($meta) && isset($meta['tier']) ? (string) $meta['tier'] : 'apply';
	}

	/**
	 * Tools localize their human text from the merchant's message; never stored.
	 *
	 * @param array  $args
	 * @param string $userText
	 * @return array
	 */
	private function withUserMessage(array $args, $userText)
	{
		$args['_user_message'] = $userText;

		return $args;
	}

	/**
	 * @param array  $call
	 * @param string $content
	 * @return array
	 */
	private function toolResult(array $call, $content)
	{
		return array(
			'role'         => 'tool',
			'tool_call_id' => $call['id'],
			'name'         => $call['name'],
			'content'      => (string) $content,
			'is_error'     => false,
		);
	}

	/**
	 * @param array  $call
	 * @param string $message
	 * @return array
	 */
	private function errorResult(array $call, $message)
	{
		$result             = $this->toolResult($call, $message);
		$result['is_error'] = true;

		return $result;
	}

	/**
	 * @param array  $out
	 * @param array  $turn
	 * @param string $text
	 * @param string $lang
	 * @return array
	 */
	private function finish(array $out, array $turn, $text, $lang)
	{
		if ($text === '') {
			$text = $this->guidanceFallback($out, $lang);
		}
		$turn[] = array(
			'role'    => 'assistant',
			'content' => $text,
		);

		$out['status']   = self::STATUS_FINAL;
		$out['message']  = $text;
		$out['messages'] = $turn;

		return $out;
	}

	/**
	 * First-step failures surface as errors (quota, auth, rate limit keep their
	 * codes for the UI). Later failures keep what was found so far.
	 *
	 * @param array    $out
	 * @param array    $turn
	 * @param WP_Error $error
	 * @param bool     $firstStep
	 * @param string   $lang
	 * @return array
	 */
	private function finishWithError(array $out, array $turn, $error, $firstStep, $lang)
	{
		if ($firstStep) {
			$out['status'] = self::STATUS_ERROR;
			$out['error']  = $error;

			return $out;
		}

		$lead = SLN_AI_Language::phrase(
			$lang,
			'agent_interrupted',
			__('I could not finish the analysis: the AI service did not respond. Please try again in a moment.', 'salon-booking-system')
		);
		$found = $this->guidanceFallback($out, $lang, false);

		return $this->finish($out, $turn, $found !== '' ? $lead . "\n\n" . $found : $lead, $lang);
	}

	/**
	 * Tool summaries are already localized to the merchant's language.
	 *
	 * @param array  $out
	 * @param string $lang
	 * @param bool   $withLead
	 * @return string
	 */
	private function guidanceFallback(array $out, $lang, $withLead = true)
	{
		$parts = array();
		foreach ($out['guidance'] as $item) {
			if (! empty($item['preview']['summary'])) {
				$parts[] = trim((string) $item['preview']['summary']);
			}
		}
		if (! $parts) {
			return $withLead
				? SLN_AI_Language::phrase(
					$lang,
					'agent_interrupted',
					__('I could not finish the analysis: the AI service did not respond. Please try again in a moment.', 'salon-booking-system')
				)
				: '';
		}

		$body = implode("\n\n", array_slice($parts, -2));
		if (! $withLead) {
			return $body;
		}

		return SLN_AI_Language::phrase($lang, 'guidance_lead', __('Here’s what I found:', 'salon-booking-system')) . "\n\n" . $body;
	}
}
