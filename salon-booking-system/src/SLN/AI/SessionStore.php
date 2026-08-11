<?php

/**
 * AI Setup session state: pending previews, undo snapshot, light audit log.
 */
class SLN_AI_SessionStore
{
	const OPTION_PREFIX = 'sln_ai_setup_session_';
	const AUDIT_OPTION  = 'sln_ai_setup_audit';
	const UNDO_OPTION   = 'sln_ai_setup_undo';
	const TTL           = 86400; // 24h

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
			'id'         => $id,
			'tool'       => isset($preview['tool']) ? $preview['tool'] : '',
			'arguments'  => isset($preview['arguments']) ? $preview['arguments'] : array(),
			'summary'    => isset($preview['summary']) ? $preview['summary'] : '',
			'proposed'   => isset($preview['proposed']) ? $preview['proposed'] : array(),
			'current'    => isset($preview['current']) ? $preview['current'] : array(),
			'created_at' => time(),
			'user_id'    => get_current_user_id(),
		);
		$this->save($sessionId, $data);

		return $id;
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
