<?php

/**
 * Guidance-only: search official Help Scout docs (second source after live tools).
 */
class SLN_AI_Tools_LookupDocs extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'lookup_docs';
	}

	public function getTier()
	{
		return 'guidance';
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function preview(array $arguments)
	{
		$lang = SLN_AI_Language::detect(
			isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : ''
		);

		$query = '';
		if (! empty($arguments['query'])) {
			$query = sanitize_text_field((string) $arguments['query']);
		} elseif (! empty($arguments['article_id'])) {
			$query = sanitize_key((string) $arguments['article_id']);
		}

		$limit = isset($arguments['limit']) ? absint($arguments['limit']) : 3;
		if ($limit < 1) {
			$limit = 3;
		}

		$hits = array();
		if (! empty($arguments['article_id'])) {
			$row = SLN_AI_DocsCatalog::findById($arguments['article_id']);
			if ($row) {
				$hits = array($row);
			}
		}
		if (! $hits) {
			$hits = SLN_AI_DocsCatalog::search($query, $limit);
		}

		if (! $hits) {
			return array(
				'ok'        => true,
				'guidance'  => true,
				'summary'   => SLN_AI_Language::phrase(
					$lang,
					'docs_none',
					__(
						'I could not find a matching official article. Try naming the feature (Google Calendar, SMS, debug, CSV export), or browse the knowledge base.',
						'salon-booking-system'
					)
				) . "\n\n[" . SLN_AI_Language::phrase(
					$lang,
					'docs_open',
					__('Open documentation', 'salon-booking-system')
				) . '](' . SLN_AI_DocsCatalog::KB_BASE . '/)',
				'arguments' => array('query' => $query),
				'tool'      => $this->getName(),
				'tier'      => 'guidance',
				'hits'      => array(),
			);
		}

		$fetchTop = count($hits) === 1 || ! empty($arguments['article_id']);
		foreach ($hits as $i => $row) {
			$hits[ $i ] = SLN_AI_DocsCatalog::withExcerpt($row, $fetchTop && $i === 0);
		}

		$isBrowse = ( $query === '' || $query === 'docs' || $query === 'documentation' );
		$parts    = array();
		$parts[]  = $isBrowse
			? SLN_AI_Language::phrase(
				$lang,
				'docs_browse',
				__('Here are useful official documentation articles:', 'salon-booking-system')
			)
			: SLN_AI_Language::phrase(
				$lang,
				'docs_found',
				__('Official documentation (second source after this site’s live settings):', 'salon-booking-system')
			);

		foreach ($hits as $row) {
			$line = '• ' . $row['title'];
			if (! empty($row['category'])) {
				$line .= ' — ' . $row['category'];
			}
			$parts[] = $line;
			if (! empty($row['excerpt'])) {
				$parts[] = '  ' . $row['excerpt'];
			}
			if (! empty($row['url'])) {
				$parts[] = '  [' . SLN_AI_Language::phrase(
					$lang,
					'docs_open',
					__('Open documentation', 'salon-booking-system')
				) . '](' . $row['url'] . ')';
			}
		}

		$parts[] = '';
		$parts[] = SLN_AI_Language::phrase(
			$lang,
			'docs_policy',
			__(
				'If this site’s live settings or a tool result disagree with the article, trust the live result and mention that the docs may be outdated.',
				'salon-booking-system'
			)
		);

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => implode("\n", $parts),
			'arguments' => array('query' => $query, 'limit' => $limit),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'hits'      => $hits,
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);
		if (is_wp_error($preview)) {
			return $preview;
		}

		return array(
			'ok'       => true,
			'before'   => array(),
			'after'    => array(),
			'message'  => $preview['summary'],
			'guidance' => true,
		);
	}

	/**
	 * @param mixed $previous
	 * @return WP_Error
	 */
	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Documentation lookups cannot be undone.', 'salon-booking-system')
		);
	}
}
