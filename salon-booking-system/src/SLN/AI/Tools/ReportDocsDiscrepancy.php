<?php

/**
 * Internal guidance tool: the model records a docs-vs-code contradiction it
 * noticed. Never shown to the merchant; rejects unknown articles/settings so
 * hallucinated reports do not pollute the log.
 */
class SLN_AI_Tools_ReportDocsDiscrepancy extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'report_docs_discrepancy';
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
		$articleId  = isset($arguments['article_id']) ? sanitize_key((string) $arguments['article_id']) : '';
		$docsClaim  = isset($arguments['docs_claim']) ? trim((string) $arguments['docs_claim']) : '';
		$codeTruth  = isset($arguments['code_truth']) ? trim((string) $arguments['code_truth']) : '';
		$settingKey = isset($arguments['setting_key']) ? sanitize_key((string) $arguments['setting_key']) : '';

		if ($articleId === '' || $docsClaim === '' || $codeTruth === '') {
			return new WP_Error('sln_ai_discrepancy_args', 'article_id, docs_claim and code_truth are required.');
		}

		$article = SLN_AI_DocsCatalog::findById($articleId);
		if (! $article) {
			return new WP_Error('sln_ai_discrepancy_article', 'Unknown article_id: use an id returned by lookup_docs.');
		}

		$keyVerified = false;
		if ($settingKey !== '') {
			if (SLN_AI_Reference::isAvailable()) {
				if (! SLN_AI_Reference::findSetting($settingKey)) {
					return new WP_Error('sln_ai_discrepancy_key', 'Unknown setting_key: use a key returned by lookup_reference.');
				}
				$keyVerified = true;
			}
		}

		$entry = SLN_AI_DocsDiscrepancyLog::record(
			array(
				'article_id'    => $articleId,
				'article_title' => isset($article['title']) ? $article['title'] : '',
				'article_url'   => isset($article['url']) ? $article['url'] : '',
				'docs_claim'    => $docsClaim,
				'code_truth'    => $codeTruth,
				'setting_key'   => $settingKey,
				'key_verified'  => $keyVerified,
			)
		);

		return array(
			'ok'        => true,
			'guidance'  => true,
			'internal'  => true,
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'summary'   => 'Discrepancy logged for the documentation team (seen ' . (int) $entry['count'] . 'x). Do not mention this to the merchant.',
			'arguments' => array(
				'article_id'  => $articleId,
				'setting_key' => $settingKey,
			),
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		return new WP_Error('sln_ai_not_applicable', 'Discrepancy reports are recorded at lookup time.');
	}

	/**
	 * @param mixed $previous
	 * @return WP_Error
	 */
	public function restore($previous)
	{
		return new WP_Error('sln_ai_no_undo', 'Discrepancy reports cannot be undone.');
	}
}
