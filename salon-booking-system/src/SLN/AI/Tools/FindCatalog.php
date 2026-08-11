<?php

/**
 * Guidance-only: list / look up services.
 */
class SLN_AI_Tools_FindService extends SLN_AI_Tools_Abstract
{
	const MAX_RESULTS = 25;

	public function getName()
	{
		return 'find_service';
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
		return SLN_AI_Tools_CatalogFindHelper::preview(
			$this->plugin,
			$arguments,
			SLN_Plugin::POST_TYPE_SERVICE,
			'find_service',
			'service'
		);
	}

	public function apply(array $arguments)
	{
		return new WP_Error(
			'sln_ai_guidance_only',
			__('find_service is read-only guidance; nothing to apply.', 'salon-booking-system')
		);
	}

	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Service lookup cannot be undone.', 'salon-booking-system')
		);
	}
}

/**
 * Guidance-only: list / look up assistants (attendants).
 */
class SLN_AI_Tools_FindAssistant extends SLN_AI_Tools_Abstract
{
	const MAX_RESULTS = 25;

	public function getName()
	{
		return 'find_assistant';
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
		return SLN_AI_Tools_CatalogFindHelper::preview(
			$this->plugin,
			$arguments,
			SLN_Plugin::POST_TYPE_ATTENDANT,
			'find_assistant',
			'assistant'
		);
	}

	public function apply(array $arguments)
	{
		return new WP_Error(
			'sln_ai_guidance_only',
			__('find_assistant is read-only guidance; nothing to apply.', 'salon-booking-system')
		);
	}

	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Assistant lookup cannot be undone.', 'salon-booking-system')
		);
	}
}

/**
 * Shared search/format for service & assistant find tools.
 */
class SLN_AI_Tools_CatalogFindHelper
{
	const MAX_RESULTS = 25;

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $arguments
	 * @param string     $postType
	 * @param string     $toolName
	 * @param string     $kind service|assistant
	 * @return array
	 */
	public static function preview(SLN_Plugin $plugin, array $arguments, $postType, $toolName, $kind)
	{
		$lang   = SLN_AI_Language::detect(
			isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : ''
		);
		$mapped = self::mapArguments($arguments);
		$hits   = array();

		if (! empty($mapped['id'])) {
			$row = self::formatById($plugin, (int) $mapped['id'], $postType, $kind);
			if ($row) {
				$hits[] = $row;
			}
		}

		if (! $hits) {
			$hits = self::search($plugin, $mapped, $postType, $kind);
		}

		$listUrl = admin_url('edit.php?post_type=' . $postType);
		$phrases = self::phraseKeys($kind);

		if (! $hits) {
			$hint = ! empty($mapped['id'])
				? SLN_AI_Language::phrase(
					$lang,
					$phrases['no_id'],
					$kind === 'service'
						? __('No service found with id %d.', 'salon-booking-system')
						: __('No assistant found with id %d.', 'salon-booking-system'),
					array((int) $mapped['id'])
				)
				: SLN_AI_Language::phrase(
					$lang,
					$phrases['none'],
					$kind === 'service'
						? __('No services matched. Try an id or name — or list with no filters.', 'salon-booking-system')
						: __('No assistants matched. Try an id or name — or list with no filters.', 'salon-booking-system')
				);
			$hint .= "\n\n[" . SLN_AI_Language::phrase(
				$lang,
				$phrases['open_list'],
				$kind === 'service'
					? __('Open Services', 'salon-booking-system')
					: __('Open Assistants', 'salon-booking-system')
			) . '](' . $listUrl . ')';

			$empty = array(
				'ok'       => true,
				'guidance' => true,
				'summary'  => $hint,
				'tool'     => $toolName,
				'tier'     => 'guidance',
			);
			$empty[ $kind === 'service' ? 'services' : 'assistants' ] = array();

			return $empty;
		}

		$openLabel = SLN_AI_Language::phrase(
			$lang,
			$phrases['open_one'],
			$kind === 'service'
				? __('Open service', 'salon-booking-system')
				: __('Open assistant', 'salon-booking-system')
		);
		$count  = count($hits);
		$header = $count === 1
			? SLN_AI_Language::phrase(
				$lang,
				$phrases['found_one'],
				$kind === 'service'
					? __('Found 1 service:', 'salon-booking-system')
					: __('Found 1 assistant:', 'salon-booking-system')
			)
			: SLN_AI_Language::phrase(
				$lang,
				$phrases['found_many'],
				$kind === 'service'
					? __('Found %d services:', 'salon-booking-system')
					: __('Found %d assistants:', 'salon-booking-system'),
				array($count)
			);

		$lines   = array();
		$lines[] = $header;
		$lines[] = '';
		foreach ($hits as $row) {
			$extra = array();
			if (! empty($row['price_label'])) {
				$extra[] = $row['price_label'];
			}
			if (! empty($row['duration'])) {
				$extra[] = $row['duration'];
			}
			if (! empty($row['email'])) {
				$extra[] = $row['email'];
			}
			if (! empty($row['phone'])) {
				$extra[] = $row['phone'];
			}
			$lines[] = sprintf(
				'#%d — %s%s%s',
				$row['id'],
				$row['name'],
				$row['status'] !== 'publish' ? ' [' . $row['status'] . ']' : '',
				$extra ? ' — ' . implode(' · ', $extra) : ''
			);
			if (! empty($row['edit_url'])) {
				$lines[] = '  [' . $openLabel . '](' . $row['edit_url'] . ')';
			}
			$lines[] = '';
		}

		$lines[] = '[' . SLN_AI_Language::phrase(
			$lang,
			$phrases['open_list'],
			$kind === 'service'
				? __('Open Services', 'salon-booking-system')
				: __('Open Assistants', 'salon-booking-system')
		) . '](' . $listUrl . ')';

		$result = array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => trim(implode("\n", $lines)),
			'tool'      => $toolName,
			'tier'      => 'guidance',
			'arguments' => $mapped,
		);
		$result[ $kind === 'service' ? 'services' : 'assistants' ] = $hits;

		return $result;
	}

	/**
	 * @param string $kind
	 * @return array
	 */
	private static function phraseKeys($kind)
	{
		if ($kind === 'assistant') {
			return array(
				'no_id'      => 'no_assistant_id',
				'none'       => 'no_assistants',
				'open_one'   => 'open_assistant',
				'open_list'  => 'open_assistants',
				'found_one'  => 'found_assistants_one',
				'found_many' => 'found_assistants_many',
			);
		}

		return array(
			'no_id'      => 'no_service_id',
			'none'       => 'no_services',
			'open_one'   => 'open_service',
			'open_list'  => 'open_services',
			'found_one'  => 'found_services_one',
			'found_many' => 'found_services_many',
		);
	}

	/**
	 * @param array $arguments
	 * @return array
	 */
	private static function mapArguments(array $arguments)
	{
		$query = '';
		if (isset($arguments['query'])) {
			$query = sanitize_text_field((string) $arguments['query']);
		} elseif (isset($arguments['search'])) {
			$query = sanitize_text_field((string) $arguments['search']);
		}
		if ($query === '' && ! empty($arguments['name'])) {
			$query = sanitize_text_field((string) $arguments['name']);
		}

		return array(
			'id'    => isset($arguments['id']) ? absint($arguments['id']) : 0,
			'query' => $query,
			'name'  => isset($arguments['name']) ? sanitize_text_field((string) $arguments['name']) : '',
		);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param int        $id
	 * @param string     $postType
	 * @param string     $kind
	 * @return array|null
	 */
	private static function formatById(SLN_Plugin $plugin, $id, $postType, $kind)
	{
		$post = get_post($id);
		if (! $post || $post->post_type !== $postType) {
			return null;
		}

		return self::formatPost($plugin, $post, $kind);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $mapped
	 * @param string     $postType
	 * @param string     $kind
	 * @return array[]
	 */
	private static function search(SLN_Plugin $plugin, array $mapped, $postType, $kind)
	{
		$args = array(
			'post_type'      => $postType,
			'post_status'    => array('publish', 'draft', 'private'),
			'posts_per_page' => self::MAX_RESULTS,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		$q = $mapped['query'] !== '' ? $mapped['query'] : $mapped['name'];
		if ($q !== '') {
			$args['s'] = $q;
		}

		$posts = get_posts($args);
		$out   = array();
		foreach ($posts as $p) {
			$row = self::formatPost($plugin, $p, $kind);
			if ($row) {
				$out[] = $row;
			}
		}

		// Exact title preference when search returns noise.
		if ($q !== '' && $out) {
			$needle = mb_strtolower(trim($q), 'UTF-8');
			usort(
				$out,
				function ($a, $b) use ($needle) {
					$ae = mb_strtolower($a['name'], 'UTF-8') === $needle ? 0 : 1;
					$be = mb_strtolower($b['name'], 'UTF-8') === $needle ? 0 : 1;
					if ($ae !== $be) {
						return $ae - $be;
					}

					return strcasecmp($a['name'], $b['name']);
				}
			);
		}

		return $out;
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param WP_Post    $post
	 * @param string     $kind
	 * @return array|null
	 */
	private static function formatPost(SLN_Plugin $plugin, $post, $kind)
	{
		if (! $post) {
			return null;
		}
		$id = (int) $post->ID;
		$row = array(
			'id'       => $id,
			'name'     => (string) $post->post_title,
			'status'   => (string) $post->post_status,
			'edit_url' => admin_url('post.php?post=' . $id . '&action=edit'),
		);

		if ($kind === 'service') {
			$svc = $plugin->createService($id);
			if ($svc && ! $svc->isEmpty()) {
				$row['name'] = (string) $svc->getName();
				try {
					$row['price_label'] = $plugin->format()->money($svc->getPrice(), false);
				} catch (Exception $e) {
					$row['price_label'] = (string) $svc->getPrice();
				}
				$duration = $svc->getDuration();
				if ($duration instanceof DateTimeInterface) {
					$row['duration'] = $duration->format('H:i');
				} elseif ($duration) {
					$row['duration'] = (string) $duration;
				}
			}
		} else {
			$att = $plugin->createAttendant($id);
			if ($att && ! $att->isEmpty()) {
				$row['name'] = (string) $att->getName();
				if (method_exists($att, 'getEmail')) {
					$row['email'] = (string) $att->getEmail();
				}
				if (method_exists($att, 'getPhone')) {
					$row['phone'] = (string) $att->getPhone();
				}
			}
		}

		return $row;
	}
}
