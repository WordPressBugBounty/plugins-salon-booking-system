<?php

/**
 * Guidance-only: list / look up discount coupons (Discount add-on).
 */
class SLN_AI_Tools_FindDiscount extends SLN_AI_Tools_Abstract
{
	const MAX_RESULTS = 25;

	public function getName()
	{
		return 'find_discount';
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

		if (! $this->settings()->get('enable_discount_system')) {
			$listUrl = admin_url('edit.php?post_type=sln_discount');
			$summary = SLN_AI_Language::phrase(
				$lang,
				'discount_system_off',
				__('The discount system is disabled. Enable it first (or ask me to turn on enable_discount_system), then open Discounts.', 'salon-booking-system')
			);
			$summary .= "\n\n[" . SLN_AI_Language::phrase(
				$lang,
				'open_discounts',
				__('Open Discounts', 'salon-booking-system')
			) . '](' . $listUrl . ')';

			return array(
				'ok'        => true,
				'guidance'  => true,
				'summary'   => $summary,
				'tool'      => $this->getName(),
				'tier'      => 'guidance',
				'discounts' => array(),
			);
		}

		if (! class_exists('SLB_Discount_Plugin') && ! post_type_exists('sln_discount')) {
			return array(
				'ok'       => true,
				'guidance' => true,
				'summary'  => SLN_AI_Language::phrase(
					$lang,
					'discount_addon_missing',
					__('The Discount add-on is not available on this site.', 'salon-booking-system')
				),
				'tool'     => $this->getName(),
				'tier'     => 'guidance',
				'discounts'=> array(),
			);
		}

		$mapped = $this->mapArguments($arguments);
		$hits   = array();

		if (! empty($mapped['id'])) {
			$row = $this->formatById((int) $mapped['id']);
			if ($row) {
				$hits[] = $row;
			}
		}

		if (! $hits) {
			$hits = $this->search($mapped);
		}

		if (! $hits) {
			$hint = ! empty($mapped['id'])
				? SLN_AI_Language::phrase(
					$lang,
					'no_discount_id',
					__('No discount found with id %d.', 'salon-booking-system'),
					array((int) $mapped['id'])
				)
				: SLN_AI_Language::phrase(
					$lang,
					'no_discounts',
					__('No discounts matched. Try an id, name, or coupon code — or list all with no filters.', 'salon-booking-system')
				);

			return array(
				'ok'        => true,
				'guidance'  => true,
				'summary'   => $hint,
				'tool'      => $this->getName(),
				'tier'      => 'guidance',
				'discounts' => array(),
			);
		}

		$openLabel = SLN_AI_Language::phrase(
			$lang,
			'open_discount',
			__('Open discount', 'salon-booking-system')
		);
		$count  = count($hits);
		$header = $count === 1
			? SLN_AI_Language::phrase(
				$lang,
				'found_discounts_one',
				__('Found 1 discount:', 'salon-booking-system')
			)
			: SLN_AI_Language::phrase(
				$lang,
				'found_discounts_many',
				__('Found %d discounts:', 'salon-booking-system'),
				array($count)
			);

		$lines   = array();
		$lines[] = $header;
		$lines[] = '';
		foreach ($hits as $row) {
			$lines[] = sprintf(
				'#%d — %s — %s%s',
				$row['id'],
				$row['name'],
				$row['amount_label'],
				$row['code'] !== '' ? ' — code: ' . $row['code'] : ''
			);
			if ($row['valid'] !== '') {
				$lines[] = '  ' . $row['valid'];
			}
			if ($row['services'] !== '') {
				$lines[] = '  ' . $row['services'];
			}
			if (! empty($row['edit_url'])) {
				$lines[] = '  [' . $openLabel . '](' . $row['edit_url'] . ')';
			}
			$lines[] = '';
		}

		$listUrl = admin_url('edit.php?post_type=sln_discount');
		$lines[] = '[' . SLN_AI_Language::phrase(
			$lang,
			'open_discounts',
			__('Open Discounts', 'salon-booking-system')
		) . '](' . $listUrl . ')';

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => trim(implode("\n", $lines)),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'discounts' => $hits,
			'arguments' => $mapped,
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		return new WP_Error(
			'sln_ai_guidance_only',
			__('find_discount is read-only guidance; nothing to apply.', 'salon-booking-system')
		);
	}

	/**
	 * @param mixed $previous
	 * @return array|WP_Error
	 */
	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Discount lookup cannot be undone.', 'salon-booking-system')
		);
	}

	/**
	 * Compact rows for ContextPack.
	 *
	 * @param SLN_Plugin $plugin
	 * @param int        $limit
	 * @return array<int,array{id:int,name:string,code:string,amount:string}>
	 */
	public static function catalogForContext(SLN_Plugin $plugin, $limit = 25)
	{
		if (! $plugin->getSettings()->get('enable_discount_system')) {
			return array();
		}
		if (! class_exists('SLB_Discount_Plugin') && ! post_type_exists('sln_discount')) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'sln_discount',
				'post_status'    => array('publish', 'draft', 'private'),
				'posts_per_page' => (int) $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ($posts as $p) {
			$row = self::formatPost($plugin, $p);
			if ($row) {
				$out[] = array(
					'id'     => $row['id'],
					'name'   => $row['name'],
					'code'   => $row['code'],
					'amount' => $row['amount_label'],
				);
			}
		}

		return $out;
	}

	/**
	 * @param array $arguments
	 * @return array
	 */
	private function mapArguments(array $arguments)
	{
		$query = '';
		if (isset($arguments['query'])) {
			$query = sanitize_text_field((string) $arguments['query']);
		} elseif (isset($arguments['search'])) {
			$query = sanitize_text_field((string) $arguments['search']);
		}
		foreach (array('name', 'code') as $k) {
			if ($query === '' && ! empty($arguments[ $k ])) {
				$query = sanitize_text_field((string) $arguments[ $k ]);
			}
		}

		return array(
			'id'    => isset($arguments['id']) ? absint($arguments['id']) : 0,
			'query' => $query,
			'code'  => isset($arguments['code']) ? sanitize_text_field((string) $arguments['code']) : '',
			'name'  => isset($arguments['name']) ? sanitize_text_field((string) $arguments['name']) : '',
		);
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	private function formatById($id)
	{
		$post = get_post($id);
		if (! $post || $post->post_type !== 'sln_discount') {
			return null;
		}

		return self::formatPost($this->plugin, $post);
	}

	/**
	 * @param array $mapped
	 * @return array[]
	 */
	private function search(array $mapped)
	{
		$args = array(
			'post_type'      => 'sln_discount',
			'post_status'    => array('publish', 'draft', 'private'),
			'posts_per_page' => self::MAX_RESULTS,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$code  = $mapped['code'] !== '' ? $mapped['code'] : '';
		$query = $mapped['query'];
		$name  = $mapped['name'];

		if ($code !== '') {
			$args['meta_query'] = array(
				array(
					'key'     => '_sln_discount_code',
					'value'   => $code,
					'compare' => 'LIKE',
				),
			);
		} elseif ($query !== '') {
			// Prefer code meta match when query looks like a coupon; also title search.
			$byCode = get_posts(
				array(
					'post_type'      => 'sln_discount',
					'post_status'    => array('publish', 'draft', 'private'),
					'posts_per_page' => self::MAX_RESULTS,
					'meta_query'     => array(
						array(
							'key'     => '_sln_discount_code',
							'value'   => $query,
							'compare' => 'LIKE',
						),
					),
				)
			);
			$byTitle = get_posts(
				array(
					'post_type'      => 'sln_discount',
					'post_status'    => array('publish', 'draft', 'private'),
					'posts_per_page' => self::MAX_RESULTS,
					's'              => $query,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			$merged = array();
			$seen   = array();
			foreach (array_merge($byCode, $byTitle) as $p) {
				if (isset($seen[ $p->ID ])) {
					continue;
				}
				$seen[ $p->ID ] = true;
				$merged[]       = $p;
			}
			$out = array();
			foreach ($merged as $p) {
				$row = self::formatPost($this->plugin, $p);
				if ($row) {
					$out[] = $row;
				}
				if (count($out) >= self::MAX_RESULTS) {
					break;
				}
			}

			return $out;
		} elseif ($name !== '') {
			$args['s'] = $name;
		}

		$posts = get_posts($args);
		$out   = array();
		foreach ($posts as $p) {
			$row = self::formatPost($this->plugin, $p);
			if ($row) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param WP_Post    $post
	 * @return array|null
	 */
	private static function formatPost(SLN_Plugin $plugin, $post)
	{
		if (! $post || $post->post_type !== 'sln_discount') {
			return null;
		}

		$id   = (int) $post->ID;
		$code = (string) get_post_meta($id, '_sln_discount_code', true);
		$from = (string) get_post_meta($id, '_sln_discount_from', true);
		$to   = (string) get_post_meta($id, '_sln_discount_to', true);
		$amountLabel = '';

		if (class_exists('SLB_Discount_Wrapper_Discount')) {
			try {
				$d = new SLB_Discount_Wrapper_Discount($post);
				$amountLabel = (string) $d->getAmountString();
				if ($code === '' && method_exists($d, 'getCouponCode')) {
					$code = (string) $d->getCouponCode();
				}
			} catch (Exception $e) {
				$amountLabel = '';
			}
		}
		if ($amountLabel === '') {
			$amount = get_post_meta($id, '_sln_discount_amount', true);
			$type   = get_post_meta($id, '_sln_discount_amount_type', true);
			$amountLabel = $type === 'percentage'
				? (string) $amount . '%'
				: (string) $amount;
		}

		$valid = '';
		if ($from !== '' || $to !== '') {
			$valid = sprintf(
				'%s → %s',
				$from !== '' ? $from : '(open)',
				$to !== '' ? $to : '(open)'
			);
		}

		$serviceNames = array();
		$serviceIds   = get_post_meta($id, '_sln_discount_services', true);
		if (is_array($serviceIds)) {
			foreach ($serviceIds as $sid) {
				$sid = (int) $sid;
				if (! $sid) {
					continue;
				}
				$title = get_the_title($sid);
				if ($title) {
					$serviceNames[] = $title;
				}
			}
		}

		return array(
			'id'           => $id,
			'name'         => (string) $post->post_title,
			'code'         => $code,
			'amount_label' => $amountLabel,
			'valid'        => $valid,
			'services'     => $serviceNames ? implode(', ', $serviceNames) : '',
			'status'       => (string) $post->post_status,
			'edit_url'     => admin_url('post.php?post=' . $id . '&action=edit'),
		);
	}
}
