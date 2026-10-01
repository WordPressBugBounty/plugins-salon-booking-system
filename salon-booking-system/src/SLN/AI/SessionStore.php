<?php

/**
 * AI Setup session state: pending previews, undo snapshot, light audit log.
 */
class SLN_AI_SessionStore
{
	/**
	 * Keep OUT of the "sln_" transient namespace: booking create/status changes run
	 * DELETE ... LIKE '_transient_sln_%' (see SLN_PostType_Booking::clearIntervalsCache),
	 * which would wipe live conversations and pending confirm cards.
	 */
	const OPTION_PREFIX = 'slb_ai_setup_session_';
	const AUDIT_OPTION  = 'sln_ai_setup_audit';
	const UNDO_OPTION   = 'sln_ai_setup_undo';
	const TTL           = 86400; // 24h

	/** Model context: last N merchant turns (tool messages do not count). */
	const HISTORY_USER_TURNS = 4;
	const HISTORY_CHAR_BUDGET = 12000;
	/** Stored sizes — keeps the transient small and the prompt fast. */
	const STORED_TOOL_RESULT_MAX = 1500;
	const STORED_TEXT_MAX        = 1200;
	const STORED_MESSAGES_MAX    = 80;

	/**
	 * @return string
	 */
	public function createSessionId()
	{
		return wp_generate_uuid4();
	}

	/**
	 * @param string $sessionId
	 * @return array
	 */
	public function get($sessionId)
	{
		$sessionId = $this->sanitizeId($sessionId);
		if ($sessionId === '') {
			return array();
		}
		$data = get_transient(self::OPTION_PREFIX . $sessionId);

		return is_array($data) ? $data : array();
	}

	/**
	 * @param string $sessionId
	 * @param array  $data
	 */
	public function save($sessionId, array $data)
	{
		$sessionId = $this->sanitizeId($sessionId);
		if ($sessionId === '') {
			return;
		}
		$data['updated_at'] = time();
		set_transient(self::OPTION_PREFIX . $sessionId, $data, self::TTL);
	}

	/**
	 * @param string $sessionId
	 * @param array  $preview Preview payload from tool.
	 * @return string Preview id
	 */
	public function storePreview($sessionId, array $preview)
	{
		$data = $this->get($sessionId);
		$id   = wp_generate_uuid4();
		$data['pending_preview'] = array(
			'id'           => $id,
			'tool'         => isset($preview['tool']) ? $preview['tool'] : '',
			'arguments'    => isset($preview['arguments']) ? $preview['arguments'] : array(),
			'summary'      => isset($preview['summary']) ? $preview['summary'] : '',
			'proposed'     => isset($preview['proposed']) ? $preview['proposed'] : array(),
			'current'      => isset($preview['current']) ? $preview['current'] : array(),
			'tool_call_id' => isset($preview['tool_call_id']) ? (string) $preview['tool_call_id'] : '',
			'created_at'   => time(),
			'user_id'      => get_current_user_id(),
		);
		$this->save($sessionId, $data);

		return $id;
	}

	/**
	 * Replace the PENDING placeholder of a write tool call with its real outcome
	 * (applied / failed / cancelled / superseded) so the model sees it next turn,
	 * and optionally record the text the merchant was shown.
	 *
	 * @param string $sessionId
	 * @param string $toolCallId Empty for legacy (single-shot) previews.
	 * @param string $content
	 * @param bool   $isError
	 * @param string $assistantNote
	 */
	public function resolveToolResult($sessionId, $toolCallId, $content, $isError = false, $assistantNote = '')
	{
		$data = $this->get($sessionId);
		if (! $data) {
			return;
		}
		$messages = isset($data['messages']) && is_array($data['messages']) ? $data['messages'] : array();

		if ($toolCallId !== '') {
			for ($i = count($messages) - 1; $i >= 0; $i--) {
				if (isset($messages[ $i ]['role'], $messages[ $i ]['tool_call_id'])
					&& $messages[ $i ]['role'] === 'tool'
					&& $messages[ $i ]['tool_call_id'] === $toolCallId
				) {
					$messages[ $i ]['content']  = SLN_AI_MessageFormat::truncate($content, self::STORED_TOOL_RESULT_MAX);
					$messages[ $i ]['is_error'] = (bool) $isError;
					break;
				}
			}
		}

		if ($assistantNote !== '') {
			$messages[] = array(
				'role'    => 'assistant',
				'content' => SLN_AI_MessageFormat::truncate($assistantNote, self::STORED_TEXT_MAX),
				'at'      => time(),
			);
		}

		$data['messages'] = $messages;
		$this->save($sessionId, $data);
	}

	/**
	 * Append canonical messages of one turn, truncating tool results and long
	 * texts; caps the stored transcript at a whole-turn boundary.
	 *
	 * @param array $session
	 * @param array $messages
	 * @return array Updated session.
	 */
	public static function appendMessages(array $session, array $messages)
	{
		$stored = isset($session['messages']) && is_array($session['messages']) ? $session['messages'] : array();
		foreach ($messages as $msg) {
			if (! is_array($msg) || empty($msg['role'])) {
				continue;
			}
			$msg['at'] = time();
			if ($msg['role'] === 'tool') {
				$msg['content'] = SLN_AI_MessageFormat::truncate(isset($msg['content']) ? $msg['content'] : '', self::STORED_TOOL_RESULT_MAX);
			} elseif ($msg['role'] === 'assistant' && isset($msg['content'])) {
				$msg['content'] = SLN_AI_MessageFormat::truncate($msg['content'], self::STORED_TEXT_MAX * 3);
			}
			$stored[] = $msg;
		}

		if (count($stored) > self::STORED_MESSAGES_MAX) {
			$stored = array_slice($stored, -self::STORED_MESSAGES_MAX);
			while ($stored && ( ! isset($stored[0]['role']) || $stored[0]['role'] !== 'user' )) {
				array_shift($stored);
			}
		}
		$session['messages'] = $stored;

		return $session;
	}

	/**
	 * Canonical transcript for the model: the last N merchant turns, always cut
	 * at a user message (never an orphan tool result), within a char budget.
	 *
	 * @param array $messages Stored session messages.
	 * @param int   $userTurns
	 * @param int   $charBudget
	 * @return array
	 */
	public static function historyWindow(array $messages, $userTurns = 0, $charBudget = 0)
	{
		$userTurns  = $userTurns > 0 ? (int) $userTurns : (int) apply_filters('sln_ai_history_user_turns', self::HISTORY_USER_TURNS);
		$charBudget = $charBudget > 0 ? (int) $charBudget : self::HISTORY_CHAR_BUDGET;

		$clean = array();
		foreach ($messages as $msg) {
			if (! is_array($msg) || empty($msg['role'])) {
				continue;
			}
			unset($msg['at']);
			if ($msg['role'] !== 'tool' && isset($msg['content'])) {
				$msg['content'] = SLN_AI_MessageFormat::truncate((string) $msg['content'], self::STORED_TEXT_MAX);
			}
			$clean[] = $msg;
		}

		$userIdx = array();
		foreach ($clean as $i => $msg) {
			if ($msg['role'] === 'user') {
				$userIdx[] = $i;
			}
		}
		if (! $userIdx || $userTurns < 1) {
			return array();
		}

		$starts = array_slice($userIdx, -$userTurns);
		foreach ($starts as $start) {
			$window = array_slice($clean, $start);
			if (self::transcriptLength($window) <= $charBudget || $start === end($starts)) {
				return array_values($window);
			}
		}

		return array();
	}

	/**
	 * Text-only recent turns for the legacy single-shot path (mock fallback).
	 *
	 * @param array $messages
	 * @return array
	 */
	public static function legacyHistory(array $messages)
	{
		$text = array();
		foreach ($messages as $msg) {
			if (empty($msg['role']) || empty($msg['content']) || ! in_array($msg['role'], array('user', 'assistant'), true)) {
				continue;
			}
			$text[] = array(
				'role'    => $msg['role'],
				'content' => SLN_AI_MessageFormat::truncate((string) $msg['content'], self::STORED_TEXT_MAX),
			);
		}

		return array_slice($text, -6);
	}

	/**
	 * @param array $messages
	 * @return int
	 */
	private static function transcriptLength(array $messages)
	{
		$len = 0;
		foreach ($messages as $msg) {
			$len += strlen(isset($msg['content']) ? (string) $msg['content'] : '');
			if (! empty($msg['tool_calls'])) {
				$len += strlen((string) wp_json_encode($msg['tool_calls']));
			}
		}

		return $len;
	}

	/**
	 * @param string $sessionId
	 * @param string $previewId
	 * @return array|null Only the user who created the preview can retrieve
	 *                    (and therefore confirm) it.
	 */
	public function getPreview($sessionId, $previewId)
	{
		$data = $this->get($sessionId);
		if (empty($data['pending_preview']) || ! is_array($data['pending_preview'])) {
			return null;
		}
		if ($data['pending_preview']['id'] !== $previewId) {
			return null;
		}
		if (! $this->ownedByCurrentUser($data['pending_preview'])) {
			return null;
		}

		return $data['pending_preview'];
	}

	/**
	 * @param string $sessionId
	 */
	public function clearPreview($sessionId)
	{
		$data = $this->get($sessionId);
		unset($data['pending_preview']);
		$this->save($sessionId, $data);
	}

	/**
	 * @param string $tool
	 * @param array  $before
	 * @param array  $after
	 */
	public function setUndoSnapshot($tool, array $before, array $after)
	{
		update_option(
			self::UNDO_OPTION,
			array(
				'tool'       => $tool,
				'before'     => $before,
				'after'      => $after,
				'user_id'    => get_current_user_id(),
				'created_at' => time(),
			),
			false
		);
	}

	/**
	 * @return array|null Only the user who made the change can see and revert it.
	 */
	public function getUndoSnapshot()
	{
		$data = get_option(self::UNDO_OPTION, null);
		if (! is_array($data)) {
			return null;
		}
		if (! $this->ownedByCurrentUser($data)) {
			return null;
		}

		return $data;
	}

	/**
	 * @param array $record Record carrying a user_id field.
	 * @return bool
	 */
	private function ownedByCurrentUser(array $record)
	{
		$owner = isset($record['user_id']) ? (int) $record['user_id'] : 0;

		return $owner > 0 && $owner === (int) get_current_user_id();
	}

	public function clearUndoSnapshot()
	{
		delete_option(self::UNDO_OPTION);
	}

	/**
	 * @return bool
	 */
	public function canUndo()
	{
		$snap = $this->getUndoSnapshot();

		return is_array($snap) && ! empty($snap['tool']) && isset($snap['before']);
	}

	/**
	 * @param string $tool
	 * @param array  $meta
	 */
	public function appendAudit($tool, array $meta = array())
	{
		$log   = get_option(self::AUDIT_OPTION, array());
		if (! is_array($log)) {
			$log = array();
		}
		$log[] = array_merge(
			array(
				'tool'       => $tool,
				'user_id'    => get_current_user_id(),
				'created_at' => time(),
			),
			$meta
		);
		// Keep last 50 entries.
		if (count($log) > 50) {
			$log = array_slice($log, -50);
		}
		update_option(self::AUDIT_OPTION, $log, false);
	}

	/**
	 * @param string $id
	 * @return string
	 */
	private function sanitizeId($id)
	{
		$id = (string) $id;

		return preg_match('/^[a-f0-9\-]{36}$/i', $id) ? $id : '';
	}
}
