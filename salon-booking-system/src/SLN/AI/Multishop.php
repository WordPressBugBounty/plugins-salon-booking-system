<?php

/**
 * Multi-shop awareness for AI Setup (context, prompts, shop-scoped tools).
 */
class SLN_AI_Multishop
{
	/**
	 * @return bool
	 */
	public static function isActive()
	{
		return class_exists('\SalonMultishop\Addon');
	}

	/**
	 * @return array<int,array{id:int,name:string}>
	 */
	public static function listShops()
	{
		if (! self::isActive()) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_SHOP,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$out = array();
		foreach ($posts as $post) {
			$out[] = array(
				'id'   => (int) $post->ID,
				'name' => (string) $post->post_title,
			);
		}

		return $out;
	}

	/**
	 * @return string
	 */
	public static function adminShopsUrl()
	{
		return admin_url('edit.php?post_type=sln_shop');
	}

	/**
	 * Fields for ContextPack when the add-on is present.
	 *
	 * @return array
	 */
	public static function contextFields()
	{
		$active = self::isActive();
		$shops  = $active ? self::listShops() : array();

		return array(
			'multishop_addon'  => $active,
			'multishop_active' => $active,
			'shops_count'      => count($shops),
			'shops'            => $shops,
		);
	}

	/**
	 * Compact Multi-shop map for the LLM system prompt.
	 *
	 * @return string
	 */
	public static function instructionsSnippet()
	{
		if (! self::isActive()) {
			return 'Multi-shop add-on: not active on this site.';
		}

		$shops = self::listShops();
		if (! $shops) {
			return 'Multi-shop add-on is active but no shops are published yet. '
				. 'Guide the user to create shops under Salon → Shops. '
				. 'Do not assume a single-location salon.';
		}

		$labels = array();
		foreach ($shops as $shop) {
			$labels[] = $shop['name'] . ' (id ' . $shop['id'] . ')';
		}

		return 'Multi-shop add-on is ACTIVE. Locations: ' . implode('; ', $labels) . '. '
			. 'Opening hours, holidays, salon identity, and availability diagnosis are per-shop when a shop is specified '
			. '(pass shop_id or shop_name on set_salon_availabilities, set_salon_holidays, set_salon_identity, explain_unavailable_slot). '
			. 'If the user does not name a shop and there is more than one, ask which location. '
			. 'With a single shop, use it automatically. '
			. 'Shop managers and shop-level Google Calendar OAuth stay guidance — use explain_setting topic=multishop.';
	}

	/**
	 * Resolve shop from tool args. Auto-picks the only shop when Multishop is active.
	 *
	 * @param array $arguments
	 * @param bool  $requireWhenMultiple When true and multiple shops exist without a hint, return guidance payload.
	 * @return array{ok:bool,shop?:object|null,guidance?:bool,summary?:string,tool?:string,tier?:string}|WP_Error
	 */
	public static function bindShop(array $arguments, $requireWhenMultiple = true)
	{
		if (! self::isActive()) {
			return array('ok' => true, 'shop' => null);
		}

		$shops = self::listShops();
		if (! $shops) {
			return array('ok' => true, 'shop' => null);
		}

		$hasHint = ! empty($arguments['shop_id']) || ! empty($arguments['shop_name']);
		if (! $hasHint) {
			if (count($shops) === 1) {
				$shop = self::loadShop((int) $shops[0]['id']);
				if (! $shop) {
					return array('ok' => true, 'shop' => null);
				}

				return array('ok' => true, 'shop' => $shop);
			}
			if ($requireWhenMultiple) {
				return array(
					'ok'       => false,
					'guidance' => true,
					'summary'  => self::askWhichShopMessage($shops),
					'tool'     => 'explain_setting',
					'tier'     => 'guidance',
				);
			}

			return array('ok' => true, 'shop' => null);
		}

		$shop = self::resolve(
			isset($arguments['shop_id']) ? (int) $arguments['shop_id'] : 0,
			isset($arguments['shop_name']) ? (string) $arguments['shop_name'] : ''
		);
		if (is_wp_error($shop)) {
			return $shop;
		}

		return array('ok' => true, 'shop' => $shop);
	}

	/**
	 * @param int    $id
	 * @param string $name
	 * @return object|WP_Error Shop wrapper
	 */
	public static function resolve($id, $name)
	{
		$id   = (int) $id;
		$name = trim((string) $name);

		if ($id > 0) {
			$shop = self::loadShop($id);
			if ($shop) {
				return $shop;
			}

			return new WP_Error(
				'sln_ai_shop_not_found',
				sprintf(
					/* translators: %d: shop id */
					__('No shop found with id %d.', 'salon-booking-system'),
					$id
				)
			);
		}

		if ($name === '') {
			return new WP_Error(
				'sln_ai_shop_missing',
				__('Please specify a shop (name or id).', 'salon-booking-system')
			);
		}

		foreach (self::listShops() as $row) {
			if (strcasecmp($row['name'], $name) === 0) {
				$shop = self::loadShop((int) $row['id']);
				if ($shop) {
					return $shop;
				}
			}
		}

		foreach (self::listShops() as $row) {
			if (stripos($row['name'], $name) !== false || stripos($name, $row['name']) !== false) {
				$shop = self::loadShop((int) $row['id']);
				if ($shop) {
					return $shop;
				}
			}
		}

		return new WP_Error(
			'sln_ai_shop_not_found',
			sprintf(
				/* translators: %s: shop name */
				__('No shop named “%s” was found.', 'salon-booking-system'),
				$name
			)
		);
	}

	/**
	 * @param int $id
	 * @return object|null
	 */
	public static function loadShop($id)
	{
		$id = (int) $id;
		if ($id <= 0 || ! self::isActive()) {
			return null;
		}

		try {
			$shop = SLN_Plugin::getInstance()->createFromPost($id);
			if ($shop && method_exists($shop, 'getPostType') && $shop->getPostType() === SLN_Plugin::POST_TYPE_SHOP) {
				return $shop;
			}
		} catch (Exception $e) {
			return null;
		}

		return null;
	}

	/**
	 * @param array<int,array{id:int,name:string}> $shops
	 * @return string
	 */
	public static function askWhichShopMessage(array $shops)
	{
		$lines = array(
			__('This site uses Multi-shop. Which location should I use?', 'salon-booking-system'),
			'',
		);
		foreach ($shops as $shop) {
			$lines[] = '• ' . $shop['name'];
		}
		$lines[] = '';
		$lines[] = __('Reply with the shop name (for example: “for shop Downtown”).', 'salon-booking-system');

		return implode("\n", $lines);
	}

	/**
	 * Run a callback with Multishop current-shop context (and $_GET['shop'] for settings filters).
	 *
	 * @param object|null $shop
	 * @param callable    $callback function ($shop)
	 * @return mixed
	 */
	public static function withShop($shop, callable $callback)
	{
		if (! $shop || ! self::isActive()) {
			return $callback(null);
		}

		$addon = \SalonMultishop\Addon::getInstance();
		$prev  = $addon->getCurrentShop();
		$hadGet = array_key_exists('shop', $_GET);
		$prevGet = $hadGet ? $_GET['shop'] : null;

		try {
			$addon->setCurrentShop($shop);
			$_GET['shop'] = $shop->getId();

			return $callback($shop);
		} finally {
			if ($prev) {
				$addon->setCurrentShop($prev);
			} else {
				$addon->setCurrentShop(null);
			}
			if ($hadGet) {
				$_GET['shop'] = $prevGet;
			} else {
				unset($_GET['shop']);
			}
		}
	}

	/**
	 * Read a setting from shop meta, falling back to global salon_settings.
	 *
	 * @param object|null $shop
	 * @param string      $key
	 * @return mixed
	 */
	public static function getScopedSetting($shop, $key)
	{
		if ($shop && method_exists($shop, 'getMeta')) {
			$val = $shop->getMeta($key);
			if ($val !== null && $val !== '' && $val !== array()) {
				return $val;
			}
		}

		return SLN_Plugin::getInstance()->getSettings()->get($key);
	}

	/**
	 * Persist a setting to shop meta only (shop-scoped override).
	 *
	 * @param object $shop
	 * @param string $key
	 * @param mixed  $value
	 */
	public static function writeShopSetting($shop, $key, $value)
	{
		if ($shop && method_exists($shop, 'setMeta')) {
			$shop->setMeta($key, $value);
		}
	}

	/**
	 * @param object|null $shop
	 */
	public static function refreshCaches($shop = null)
	{
		if (class_exists('SLN_Helper_Availability_Cache')) {
			SLN_Helper_Availability_Cache::clearCache();
		}

		$plugin = SLN_Plugin::getInstance();
		if ($shop && self::isActive()) {
			try {
				\SalonMultishop\Addon::getInstance()->getBookingCacheByShop($shop)->refreshAll();
			} catch (Exception $e) {
				SLN_Plugin::addLog('AI Multishop cache refresh failed: ' . $e->getMessage());
			}
		} else {
			$plugin->getBookingCache()->refreshAll();
			$plugin->getBookingCache()->save();
		}

		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sln_%'");
	}

	/**
	 * Wrap undo snapshot so restore can re-bind the shop.
	 *
	 * @param object|null $shop
	 * @param mixed       $data
	 * @return mixed
	 */
	public static function wrapSnapshot($shop, $data)
	{
		if (! $shop) {
			return $data;
		}

		return array(
			'__sln_ai_shop_id' => (int) $shop->getId(),
			'data'             => $data,
		);
	}

	/**
	 * @param mixed $previous
	 * @return array{shop:object|null,data:mixed}
	 */
	public static function unwrapSnapshot($previous)
	{
		if (is_array($previous) && isset($previous['__sln_ai_shop_id'])) {
			return array(
				'shop' => self::loadShop((int) $previous['__sln_ai_shop_id']),
				'data' => isset($previous['data']) ? $previous['data'] : array(),
			);
		}

		return array(
			'shop' => null,
			'data' => $previous,
		);
	}

	/**
	 * @param object|null $shop
	 * @return array{shop_id:int,shop_name:string}
	 */
	public static function argsFromShop($shop)
	{
		if (! $shop) {
			return array(
				'shop_id'   => 0,
				'shop_name' => '',
			);
		}

		$name = method_exists($shop, 'getName') ? (string) $shop->getName() : '';
		if ($name === '' && method_exists($shop, 'getTitle')) {
			$name = (string) $shop->getTitle();
		}

		return array(
			'shop_id'   => (int) $shop->getId(),
			'shop_name' => $name,
		);
	}

	/**
	 * Label for preview summaries.
	 *
	 * @param object|null $shop
	 * @return string
	 */
	public static function scopeLabel($shop)
	{
		if (! $shop) {
			return '';
		}
		$args = self::argsFromShop($shop);

		return sprintf(
			/* translators: %s: shop name */
			__('Shop: %s', 'salon-booking-system'),
			$args['shop_name'] !== '' ? $args['shop_name'] : '#' . $args['shop_id']
		);
	}
}
