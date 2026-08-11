<?php

/**
 * Style / email / discount / checkout-fields tools (Wave 3–4).
 */
class SLN_AI_Tools_SetBookingStyleColors extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'set_booking_style_colors'; }
	public function getTier() { return 'confirm'; }

	public function preview(array $arguments)
	{
		$mapped = $this->map($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$before = $this->snapshotKeys(array_keys($mapped));

		return array(
			'ok'        => true,
			'summary'   => $this->formatKeyValueDiff($before, $mapped),
			'arguments' => array('values' => $mapped),
			'tool'      => $this->getName(),
			'current'   => $before,
			'proposed'  => $mapped,
		);
	}

	public function apply(array $arguments)
	{
		$mapped = $this->map($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$before = $this->snapshotKeys(array_keys($mapped));
		$this->writeSettings($mapped);
		if (class_exists('SLN_Helper_CustomColorsCss')) {
			$enabled = $this->settings()->get('style_colors_enabled');
			if ($enabled || ! empty($mapped['style_colors_enabled'])) {
				SLN_Helper_CustomColorsCss::regenerate($this->settings());
			}
		}

		return array(
			'ok'      => true,
			'before'  => $before,
			'after'   => $mapped,
			'message' => __('Booking style updated.', 'salon-booking-system'),
		);
	}

	public function restore($previous)
	{
		if (! is_array($previous)) {
			return new WP_Error('sln_ai_restore', __('Nothing to restore.', 'salon-booking-system'));
		}
		$this->writeSettings($previous);
		if (class_exists('SLN_Helper_CustomColorsCss') && $this->settings()->get('style_colors_enabled')) {
			SLN_Helper_CustomColorsCss::regenerate($this->settings());
		}

		return array('ok' => true, 'message' => __('Style restored.', 'salon-booking-system'));
	}

	private function map(array $arguments)
	{
		$out = array();
		if (isset($arguments['style_shortcode'])) {
			$out['style_shortcode'] = sanitize_text_field($arguments['style_shortcode']);
		}
		if (isset($arguments['style_colors_enabled'])) {
			$out['style_colors_enabled'] = $this->toFlag($arguments['style_colors_enabled']);
		}
		if (isset($arguments['style_colors']) && is_array($arguments['style_colors'])) {
			$colors = array();
			foreach ($arguments['style_colors'] as $k => $v) {
				$colors[ sanitize_key($k) ] = sanitize_hex_color($v) ? sanitize_hex_color($v) : sanitize_text_field($v);
			}
			$out['style_colors'] = $colors;
		}
		if (! $out) {
			return new WP_Error('sln_ai_style', __('No style values provided.', 'salon-booking-system'));
		}

		return $out;
	}
}

class SLN_AI_Tools_SetEmailNotificationTemplates extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_email_notification_templates'; }
	public function getTier() { return 'confirm'; }
	protected function successMessage() { return __('Email notification settings updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'email_subject'                 => 'text',
			'booking_update_message'        => 'textarea',
			'new_booking_message'           => 'textarea',
			'disable_new_user_welcome_email'=> 'flag',
			'follow_up_message'             => 'textarea',
			'feedback_message'              => 'textarea',
		);
	}
}

class SLN_AI_Tools_SetCheckoutFields extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'set_checkout_fields'; }
	public function getTier() { return 'confirm'; }

	/**
	 * Core fields the booking flow depends on: never hidden, never optional.
	 * (Account creation and notifications require email; bookings need a name.)
	 *
	 * @var array<string>
	 */
	private static $protectedFields = array('firstname', 'email');

	public function preview(array $arguments)
	{
		if (empty($arguments['checkout_fields']) || ! is_array($arguments['checkout_fields'])) {
			return new WP_Error('sln_ai_checkout_fields', __('checkout_fields object is required.', 'salon-booking-system'));
		}
		$current = $this->settings()->get('checkout_fields');
		if (! is_array($current)) {
			$current = array();
		}

		$merged = $this->mergeSanitized($current, $arguments['checkout_fields']);
		if (is_wp_error($merged)) {
			return $merged;
		}

		return array(
			'ok'        => true,
			'summary'   => sprintf(
				__("Checkout fields will be updated (%d keys in proposal).", 'salon-booking-system'),
				count($arguments['checkout_fields'])
			) . "\n" . wp_json_encode(array_keys($arguments['checkout_fields'])),
			'arguments' => array('checkout_fields' => $arguments['checkout_fields']),
			'tool'      => $this->getName(),
			'current'   => $current,
			'proposed'  => $merged,
		);
	}

	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);
		if (is_wp_error($preview)) {
			return $preview;
		}
		$before = is_array($preview['current']) ? $preview['current'] : array();
		$merged = $preview['proposed'];
		$this->settings()->set('checkout_fields', $merged);
		$this->settings()->save();
		if (class_exists('SLN_Enum_CheckoutFields') && method_exists('SLN_Enum_CheckoutFields', 'refresh')) {
			SLN_Enum_CheckoutFields::refresh();
		}

		return array(
			'ok'      => true,
			'before'  => $before,
			'after'   => $merged,
			'message' => __('Checkout fields updated.', 'salon-booking-system'),
		);
	}

	/**
	 * Merge an LLM-provided patch into the saved fields, treating it as
	 * untrusted input: whitelist attributes, cast types, merge per field so a
	 * partial patch cannot wipe existing attributes, and keep the fields the
	 * booking flow depends on visible and required.
	 *
	 * @param array $current
	 * @param array $patch
	 * @return array|WP_Error
	 */
	private function mergeSanitized(array $current, array $patch)
	{
		$merged  = $current;
		$applied = 0;

		foreach ($patch as $rawKey => $rawField) {
			$key = sanitize_key((string) $rawKey);
			if ($key === '' || ! is_array($rawField)) {
				continue;
			}

			$field = $this->sanitizeFieldAttrs($rawField);
			if (! $field) {
				continue;
			}

			$isNew = ! isset($merged[ $key ]) || ! is_array($merged[ $key ]);
			if ($isNew) {
				// New fields are always "additional" custom fields.
				$merged[ $key ] = array_merge($field, array('additional' => true));
			} else {
				$existing = $merged[ $key ];
				// Field identity flags are not patchable.
				unset($field['additional']);
				$merged[ $key ] = array_merge($existing, $field);
			}

			if (in_array($key, self::$protectedFields, true)) {
				$merged[ $key ]['required'] = true;
				$merged[ $key ]['hidden']   = false;
			}

			$applied++;
		}

		if (! $applied) {
			return new WP_Error(
				'sln_ai_checkout_fields',
				__('No valid checkout field values provided.', 'salon-booking-system')
			);
		}

		return $merged;
	}

	/**
	 * @param array $rawField
	 * @return array Whitelisted, sanitized attributes only.
	 */
	private function sanitizeFieldAttrs(array $rawField)
	{
		$out = array();

		if (isset($rawField['label'])) {
			$out['label'] = sanitize_text_field((string) $rawField['label']);
		}
		if (isset($rawField['type'])) {
			$type = sanitize_key((string) $rawField['type']);
			if (in_array($type, array('text', 'textarea', 'checkbox', 'select', 'file', 'html'), true)) {
				$out['type'] = $type;
			}
		}
		if (isset($rawField['width'])) {
			$width = (int) $rawField['width'];
			if (in_array($width, array(3, 6, 12), true)) {
				$out['width'] = $width;
			}
		}
		foreach (array('required', 'hidden', 'customer_profile', 'booking_hidden', 'export_csv', 'additional') as $flag) {
			if (isset($rawField[ $flag ])) {
				$out[ $flag ] = $this->toFlag($rawField[ $flag ]);
			}
		}
		if (isset($rawField['default_value']) && is_scalar($rawField['default_value'])) {
			$out['default_value'] = sanitize_text_field((string) $rawField['default_value']);
		}
		if (isset($rawField['options'])) {
			if (is_array($rawField['options'])) {
				$out['options'] = array_map('sanitize_text_field', array_map('strval', $rawField['options']));
			} elseif (is_string($rawField['options'])) {
				$out['options'] = sanitize_textarea_field($rawField['options']);
			}
		}
		if (isset($rawField['file_type']) && is_string($rawField['file_type'])) {
			$types = array_intersect(
				array_map('trim', explode(',', strtolower($rawField['file_type']))),
				array('jpg', 'gif', 'mp4', 'doc', 'pdf')
			);
			if ($types) {
				$out['file_type'] = implode(',', $types);
			}
		}

		return $out;
	}

	public function restore($previous)
	{
		if (! is_array($previous)) {
			return new WP_Error('sln_ai_restore', __('Nothing to restore.', 'salon-booking-system'));
		}
		$this->settings()->set('checkout_fields', $previous);
		$this->settings()->save();
		if (class_exists('SLN_Enum_CheckoutFields') && method_exists('SLN_Enum_CheckoutFields', 'refresh')) {
			SLN_Enum_CheckoutFields::refresh();
		}

		return array('ok' => true, 'message' => __('Checkout fields restored.', 'salon-booking-system'));
	}
}

class SLN_AI_Tools_UpsertDiscount extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'upsert_discount'; }
	public function getTier() { return 'confirm'; }

	public function preview(array $arguments)
	{
		if (! $this->settings()->get('enable_discount_system')) {
			return new WP_Error(
				'sln_ai_discount_off',
				__('Enable the discount system first (set_discount_system_enabled).', 'salon-booking-system')
			);
		}
		if (! class_exists('SLB_Discount_Plugin') && ! post_type_exists('sln_discount')) {
			return new WP_Error(
				'sln_ai_discount_addon',
				__('The Discount addon is required to create discounts.', 'salon-booking-system')
			);
		}
		$name = isset($arguments['name']) ? sanitize_text_field($arguments['name']) : '';
		if ($name === '') {
			return new WP_Error('sln_ai_discount', __('Discount name is required.', 'salon-booking-system'));
		}
		$amount = isset($arguments['amount']) ? floatval($arguments['amount']) : 0;
		$type   = isset($arguments['amount_type']) ? sanitize_text_field($arguments['amount_type']) : 'fixed';
		if (! in_array($type, array('fixed', 'percentage'), true)) {
			$type = 'fixed';
		}
		$serviceIds = array();
		if (! empty($arguments['service_ids']) && is_array($arguments['service_ids'])) {
			$serviceIds = array_map('absint', $arguments['service_ids']);
		}
		if (! empty($arguments['service_names']) && is_array($arguments['service_names'])) {
			foreach ($arguments['service_names'] as $sn) {
				$p = $this->findPostByTitle(sanitize_text_field((string) $sn), SLN_Plugin::POST_TYPE_SERVICE);
				if ($p) {
					$serviceIds[] = (int) $p->ID;
				}
			}
		}
		$serviceIds = array_values(array_unique(array_filter($serviceIds)));
		$from       = ! empty($arguments['from']) ? sanitize_text_field((string) $arguments['from']) : '';
		$to         = ! empty($arguments['to']) ? sanitize_text_field((string) $arguments['to']) : '';
		$summary    = sprintf('Upsert discount "%s": %s %s', $name, $amount, $type);
		if ($from || $to) {
			$summary .= sprintf("\nvalid: %s → %s", $from ? $from : '(open)', $to ? $to : '(open)');
		}
		if (isset($arguments['usages_limit']) || isset($arguments['usages_limit_total'])) {
			$summary .= sprintf(
				"\nusages_limit: %s | usages_limit_total: %s",
				isset($arguments['usages_limit']) ? absint($arguments['usages_limit']) : '(unchanged)',
				isset($arguments['usages_limit_total']) ? absint($arguments['usages_limit_total']) : '(unchanged)'
			);
		}
		if ($serviceIds) {
			$summary .= "\nservices: " . implode(', ', $serviceIds);
		}

		return array(
			'ok'        => true,
			'summary'   => $summary,
			'arguments' => array(
				'name'              => $name,
				'amount'            => $amount,
				'amount_type'       => $type,
				'code'              => isset($arguments['code']) ? sanitize_text_field($arguments['code']) : '',
				'id'                => isset($arguments['id']) ? absint($arguments['id']) : 0,
				'from'              => $from,
				'to'                => $to,
				'usages_limit'      => array_key_exists('usages_limit', $arguments) ? absint($arguments['usages_limit']) : null,
				'usages_limit_total'=> array_key_exists('usages_limit_total', $arguments) ? absint($arguments['usages_limit_total']) : null,
				'service_ids'       => $serviceIds,
			),
			'tool'      => $this->getName(),
		);
	}

	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);
		if (is_wp_error($preview)) {
			return $preview;
		}
		$args     = $preview['arguments'];
		$postType = class_exists('SLB_Discount_Plugin') ? SLB_Discount_Plugin::POST_TYPE_DISCOUNT : 'sln_discount';
		$postarr  = array(
			'post_type'   => $postType,
			'post_status' => 'publish',
			'post_title'  => $args['name'],
		);
		if (! empty($args['id'])) {
			$postarr['ID'] = $args['id'];
			$id            = wp_update_post($postarr, true);
		} else {
			$id = wp_insert_post($postarr, true);
		}
		if (is_wp_error($id)) {
			return $id;
		}
		update_post_meta($id, '_sln_discount_amount', $args['amount']);
		update_post_meta($id, '_sln_discount_amount_type', $args['amount_type']);
		if ($args['code'] !== '') {
			update_post_meta($id, '_sln_discount_code', $args['code']);
		}
		if ($args['from'] !== '') {
			update_post_meta($id, '_sln_discount_from', $args['from']);
		}
		if ($args['to'] !== '') {
			update_post_meta($id, '_sln_discount_to', $args['to']);
		}
		if ($args['usages_limit'] !== null) {
			update_post_meta($id, '_sln_discount_usages_limit', $args['usages_limit']);
		}
		if ($args['usages_limit_total'] !== null) {
			update_post_meta($id, '_sln_discount_usages_limit_total', $args['usages_limit_total']);
		}
		if (! empty($args['service_ids'])) {
			update_post_meta($id, '_sln_discount_services', array_map('intval', $args['service_ids']));
		}

		return array(
			'ok'      => true,
			'before'  => array(),
			'after'   => array('id' => (int) $id),
			'message' => __('Discount saved.', 'salon-booking-system'),
		);
	}

	public function restore($previous)
	{
		return array('ok' => true, 'message' => __('Discount undo not fully supported.', 'salon-booking-system'));
	}
}

class SLN_AI_Tools_SetServiceAssistants extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'set_service_assistants'; }

	public function preview(array $arguments)
	{
		$serviceId = $this->resolveServiceId($arguments);
		if (is_wp_error($serviceId)) {
			return $serviceId;
		}
		$ids = $this->resolveAssistantIds($arguments);
		if (is_wp_error($ids)) {
			return $ids;
		}

		return array(
			'ok'        => true,
			'summary'   => sprintf(
				/* translators: 1: service id, 2: assistant ids */
				__('Link service #%1$d to assistants [%2$s] (updates each assistant’s services list).', 'salon-booking-system'),
				$serviceId,
				implode(', ', $ids)
			),
			'arguments' => array('service_id' => $serviceId, 'assistant_ids' => $ids),
			'tool'      => $this->getName(),
			'proposed'  => $ids,
		);
	}

	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);
		if (is_wp_error($preview)) {
			return $preview;
		}
		$serviceId = (int) $preview['arguments']['service_id'];
		$targetIds = array_map('intval', $preview['arguments']['assistant_ids']);
		$before    = array();

		$all = get_posts(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_ATTENDANT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ($all as $attId) {
			$attId    = (int) $attId;
			$services = get_post_meta($attId, '_sln_attendant_services', true);
			if (! is_array($services)) {
				$services = array();
			}
			$before[ $attId ] = $services;

			$inTarget = in_array($attId, $targetIds, true);
			if ($inTarget) {
				if ($services) {
					$services[] = $serviceId;
					$services   = array_values(array_unique(array_map('intval', $services)));
					update_post_meta($attId, '_sln_attendant_services', $services);
				}
				// Empty list = all services; leave as-is.
			} elseif ($services && in_array($serviceId, array_map('intval', $services), true)) {
				$services = array_values(
					array_filter(
						array_map('intval', $services),
						function ($id) use ($serviceId) {
							return $id !== $serviceId;
						}
					)
				);
				update_post_meta($attId, '_sln_attendant_services', $services);
			}
		}

		$this->refreshBookingCaches();

		return array(
			'ok'      => true,
			'before'  => array('service_id' => $serviceId, 'attendants' => $before),
			'after'   => $targetIds,
			'message' => __('Service assistants updated.', 'salon-booking-system'),
		);
	}

	public function restore($previous)
	{
		if (! is_array($previous) || empty($previous['attendants']) || ! is_array($previous['attendants'])) {
			return new WP_Error('sln_ai_restore', __('Cannot restore.', 'salon-booking-system'));
		}
		foreach ($previous['attendants'] as $attId => $services) {
			update_post_meta((int) $attId, '_sln_attendant_services', is_array($services) ? $services : array());
		}
		$this->refreshBookingCaches();

		return array('ok' => true, 'message' => __('Service assistants restored.', 'salon-booking-system'));
	}

	private function resolveServiceId(array $arguments)
	{
		if (! empty($arguments['service_id'])) {
			return absint($arguments['service_id']);
		}
		if (! empty($arguments['service_name'])) {
			$p = $this->findPostByTitle(sanitize_text_field($arguments['service_name']), SLN_Plugin::POST_TYPE_SERVICE);
			if ($p) {
				return $p->ID;
			}
		}

		return new WP_Error('sln_ai_service', __('Pass service_id or service_name.', 'salon-booking-system'));
	}

	private function resolveAssistantIds(array $arguments)
	{
		$ids = array();
		if (! empty($arguments['assistant_ids']) && is_array($arguments['assistant_ids'])) {
			foreach ($arguments['assistant_ids'] as $id) {
				$ids[] = absint($id);
			}
		}
		if (! empty($arguments['assistant_names']) && is_array($arguments['assistant_names'])) {
			foreach ($arguments['assistant_names'] as $name) {
				$p = $this->findPostByTitle(sanitize_text_field($name), SLN_Plugin::POST_TYPE_ATTENDANT);
				if ($p) {
					$ids[] = $p->ID;
				}
			}
		}
		$ids = array_values(array_unique(array_filter($ids)));
		if (! $ids) {
			return new WP_Error('sln_ai_assistants', __('Pass assistant_ids or assistant_names.', 'salon-booking-system'));
		}

		return $ids;
	}
}
