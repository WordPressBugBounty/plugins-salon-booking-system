<?php

/**
 * CPT catalog tools: services, assistants, resources (Wave 2–3).
 */
class SLN_AI_Tools_UpsertService extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'upsert_service'; }

	public function preview(array $arguments)
	{
		$mapped = $this->map($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$existing = $this->findService($mapped);
		$lines    = array(
			sprintf('%s service "%s"', $existing ? 'Update' : 'Create', $mapped['name']),
			'price: ' . (isset($mapped['price']) ? $mapped['price'] : '(unchanged)'),
			'duration: ' . (isset($mapped['duration']) ? $mapped['duration'] : '(unchanged)'),
		);
		foreach (array('secondary', 'exclusive', 'hide_on_frontend', 'variable_duration', 'variable_price_enabled', 'break_duration_enabled', 'offset_for_service', 'lock_for_service') as $flag) {
			if (isset($mapped[ $flag ])) {
				$lines[] = $flag . ': ' . $mapped[ $flag ];
			}
		}
		if (isset($mapped['break_duration'])) {
			$lines[] = 'break_duration: ' . $mapped['break_duration'];
		}
		if (isset($mapped['offset_for_service_interval'])) {
			$lines[] = 'offset_interval: ' . $mapped['offset_for_service_interval'];
		}
		if (isset($mapped['lock_for_service_interval'])) {
			$lines[] = 'lock_interval: ' . $mapped['lock_for_service_interval'];
		}
		if (! empty($mapped['category_names'])) {
			$lines[] = 'categories: ' . implode(', ', $mapped['category_names']);
		}
		if (! empty($mapped['resource_ids'])) {
			$lines[] = 'resource_ids: ' . implode(', ', $mapped['resource_ids']);
		}

		return array(
			'ok'        => true,
			'summary'   => implode("\n", $lines),
			'arguments' => $mapped,
			'tool'      => $this->getName(),
			'current'   => $existing ? array('id' => $existing->ID, 'title' => $existing->post_title) : array(),
			'proposed'  => $mapped,
		);
	}

	public function apply(array $arguments)
	{
		$mapped = $this->map($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$existing = $this->findService($mapped);
		$before   = $existing
			? array('id' => $existing->ID, 'title' => $existing->post_title)
			: array('_created' => true);

		$postarr = array(
			'post_type'   => SLN_Plugin::POST_TYPE_SERVICE,
			'post_status' => 'publish',
			'post_title'  => $mapped['name'],
		);
		if (isset($mapped['description'])) {
			$postarr['post_content'] = $mapped['description'];
		}
		if ($existing) {
			$postarr['ID'] = $existing->ID;
			$id            = wp_update_post($postarr, true);
		} else {
			$id = wp_insert_post($postarr, true);
		}
		if (is_wp_error($id)) {
			return $id;
		}
		if (! empty($before['_created'])) {
			$before['id'] = (int) $id;
		}
		if (isset($mapped['price'])) {
			update_post_meta($id, '_sln_service_price', $mapped['price']);
		}
		if (! empty($mapped['duration'])) {
			update_post_meta($id, '_sln_service_duration', $mapped['duration']);
		}
		$flagKeys = array(
			'secondary'              => '_sln_service_secondary',
			'exclusive'              => '_sln_service_exclusive',
			'hide_on_frontend'       => '_sln_service_hide_on_frontend',
			'variable_duration'      => '_sln_service_variable_duration',
			'variable_price_enabled' => '_sln_service_variable_price_enabled',
			'offset_for_service'     => '_sln_service_offset_for_service',
			'lock_for_service'       => '_sln_service_lock_for_service',
		);
		foreach ($flagKeys as $arg => $meta) {
			if (isset($mapped[ $arg ])) {
				update_post_meta($id, $meta, $mapped[ $arg ]);
			}
		}
		if (isset($mapped['break_duration_enabled'])) {
			if ($mapped['break_duration_enabled'] === '0') {
				update_post_meta($id, '_sln_service_break_duration', '00:00');
			} elseif (isset($mapped['break_duration'])) {
				update_post_meta($id, '_sln_service_break_duration', $mapped['break_duration']);
			} elseif (! get_post_meta($id, '_sln_service_break_duration', true)) {
				update_post_meta($id, '_sln_service_break_duration', '00:15');
			}
		} elseif (isset($mapped['break_duration'])) {
			update_post_meta($id, '_sln_service_break_duration', $mapped['break_duration']);
		}
		if (isset($mapped['offset_for_service_interval'])) {
			update_post_meta($id, '_sln_service_offset_for_service_interval', $mapped['offset_for_service_interval']);
		}
		if (isset($mapped['lock_for_service_interval'])) {
			update_post_meta($id, '_sln_service_lock_for_service_interval', $mapped['lock_for_service_interval']);
		}
		if (! empty($mapped['category_names']) && taxonomy_exists(SLN_Plugin::TAXONOMY_SERVICE_CATEGORY)) {
			wp_set_object_terms($id, $mapped['category_names'], SLN_Plugin::TAXONOMY_SERVICE_CATEGORY, false);
		}
		if (isset($mapped['resource_ids'])) {
			// Resources store services[]; update each resource's services list.
			$this->syncServiceResources((int) $id, $mapped['resource_ids']);
		}
		$this->refreshBookingCaches();

		return array(
			'ok'      => true,
			'before'  => $before,
			'after'   => array('id' => (int) $id, 'title' => $mapped['name']),
			'message' => sprintf(
				/* translators: %s: service name */
				__('Service “%s” saved.', 'salon-booking-system'),
				$mapped['name']
			),
		);
	}

	public function restore($previous)
	{
		if (! is_array($previous) || empty($previous['id'])) {
			return new WP_Error('sln_ai_restore', __('Cannot restore service.', 'salon-booking-system'));
		}
		// Soft undo: trash created post if we only have create marker.
		if (! empty($previous['_created'])) {
			wp_trash_post((int) $previous['id']);
		}

		return array('ok' => true, 'message' => __('Service change undo attempted.', 'salon-booking-system'));
	}

	private function map(array $arguments)
	{
		$name = isset($arguments['name']) ? sanitize_text_field($arguments['name']) : '';
		if ($name === '') {
			return new WP_Error('sln_ai_service_name', __('Service name is required.', 'salon-booking-system'));
		}
		$out = array('name' => $name);
		if (isset($arguments['id'])) {
			$out['id'] = absint($arguments['id']);
		}
		if (isset($arguments['price'])) {
			$out['price'] = floatval($arguments['price']);
		}
		if (isset($arguments['duration'])) {
			$out['duration'] = $this->normalizeDuration($arguments['duration']);
		}
		if (isset($arguments['description'])) {
			$out['description'] = wp_kses_post((string) $arguments['description']);
		}
		foreach (array('secondary', 'exclusive', 'hide_on_frontend', 'variable_duration', 'variable_price_enabled', 'break_duration_enabled', 'offset_for_service', 'lock_for_service') as $flag) {
			if (array_key_exists($flag, $arguments)) {
				$out[ $flag ] = $this->toFlag($arguments[ $flag ]);
			}
		}
		if (isset($arguments['break_duration'])) {
			$out['break_duration'] = $this->normalizeDuration($arguments['break_duration']);
		}
		if (isset($arguments['offset_for_service_interval'])) {
			$out['offset_for_service_interval'] = absint($arguments['offset_for_service_interval']);
		}
		if (isset($arguments['lock_for_service_interval'])) {
			$out['lock_for_service_interval'] = absint($arguments['lock_for_service_interval']);
		}
		if (! empty($arguments['category_names']) && is_array($arguments['category_names'])) {
			$cats = array();
			foreach ($arguments['category_names'] as $c) {
				$c = sanitize_text_field((string) $c);
				if ($c !== '') {
					$cats[] = $c;
				}
			}
			$out['category_names'] = $cats;
		}
		if (isset($arguments['resource_ids']) && is_array($arguments['resource_ids'])) {
			$out['resource_ids'] = array_values(array_filter(array_map('absint', $arguments['resource_ids'])));
		} elseif (! empty($arguments['resource_names']) && is_array($arguments['resource_names'])) {
			$ids = array();
			foreach ($arguments['resource_names'] as $rn) {
				$p = $this->findPostByTitle(sanitize_text_field((string) $rn), SLN_Plugin::POST_TYPE_RESOURCE);
				if ($p) {
					$ids[] = (int) $p->ID;
				}
			}
			$out['resource_ids'] = $ids;
		}

		return $out;
	}

	/**
	 * @param mixed $duration
	 * @return string HH:MM
	 */
	private function normalizeDuration($duration)
	{
		$d = sanitize_text_field((string) $duration);
		if (ctype_digit($d)) {
			$mins = (int) $d;
			$d    = sprintf('%02d:%02d', floor($mins / 60), $mins % 60);
		}

		return $d;
	}

	/**
	 * Keep resource↔service links consistent with metabox storage on resources.
	 *
	 * @param int   $serviceId
	 * @param int[] $resourceIds
	 */
	private function syncServiceResources($serviceId, array $resourceIds)
	{
		$resourceIds = array_map('intval', $resourceIds);
		$all = get_posts(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_RESOURCE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ($all as $rid) {
			$services = get_post_meta($rid, '_sln_resource_services', true);
			if (! is_array($services)) {
				$services = array();
			}
			$services = array_map('intval', $services);
			$has      = in_array((int) $serviceId, $services, true);
			$want     = in_array((int) $rid, $resourceIds, true);
			if ($want && ! $has) {
				$services[] = (int) $serviceId;
				update_post_meta($rid, '_sln_resource_services', array_values(array_unique($services)));
			} elseif (! $want && $has) {
				$services = array_values(array_diff($services, array((int) $serviceId)));
				update_post_meta($rid, '_sln_resource_services', $services);
			}
		}
	}

	private function findService(array $mapped)
	{
		if (! empty($mapped['id'])) {
			$p = get_post((int) $mapped['id']);
			if ($p && $p->post_type === SLN_Plugin::POST_TYPE_SERVICE) {
				return $p;
			}
		}
		$found = $this->findPostByTitle($mapped['name'], SLN_Plugin::POST_TYPE_SERVICE);

		return $found ? $found : null;
	}
}

class SLN_AI_Tools_SetServiceAvailabilities extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'set_service_availabilities'; }
	public function getTier() { return 'confirm'; }

	public function preview(array $arguments)
	{
		$service = $this->resolveService($arguments);
		if (is_wp_error($service)) {
			return $service;
		}
		$hours  = new SLN_AI_Tools_SetAvailabilities($this->plugin);
		$mapped = $hours->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$current = get_post_meta($service->ID, '_sln_service_availabilities', true);
		if (! is_array($current)) {
			$current = array();
		}

		return array(
			'ok'        => true,
			'summary'   => sprintf(
				"Service #%d %s\n\nCurrent:\n%s\n\nProposed:\n%s",
				$service->ID,
				$service->post_title,
				$hours->formatAvailabilitiesSummary($current),
				$hours->formatAvailabilitiesSummary($mapped)
			),
			'arguments' => array_merge($arguments, array('service_id' => $service->ID)),
			'tool'      => $this->getName(),
			'current'   => $current,
			'proposed'  => $mapped,
		);
	}

	public function apply(array $arguments)
	{
		$service = $this->resolveService($arguments);
		if (is_wp_error($service)) {
			return $service;
		}
		$hours  = new SLN_AI_Tools_SetAvailabilities($this->plugin);
		$mapped = $hours->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$before = get_post_meta($service->ID, '_sln_service_availabilities', true);
		if (! is_array($before)) {
			$before = array();
		}
		$processed = SLN_Helper_AvailabilityItems::processSubmission($mapped);
		update_post_meta($service->ID, '_sln_service_availabilities', $processed);
		$this->refreshBookingCaches();

		return array(
			'ok'      => true,
			'before'  => array('service_id' => $service->ID, 'availabilities' => $before),
			'after'   => $processed,
			'message' => __('Service availability updated.', 'salon-booking-system'),
		);
	}

	public function restore($previous)
	{
		if (! is_array($previous) || empty($previous['service_id'])) {
			return new WP_Error('sln_ai_restore', __('Cannot restore service availability.', 'salon-booking-system'));
		}
		$av = isset($previous['availabilities']) && is_array($previous['availabilities']) ? $previous['availabilities'] : array();
		update_post_meta((int) $previous['service_id'], '_sln_service_availabilities', SLN_Helper_AvailabilityItems::processSubmission($av));
		$this->refreshBookingCaches();

		return array('ok' => true, 'message' => __('Service availability restored.', 'salon-booking-system'));
	}

	private function resolveService(array $arguments)
	{
		$id = isset($arguments['service_id']) ? absint($arguments['service_id']) : 0;
		if (! $id && ! empty($arguments['name'])) {
			$p  = $this->findPostByTitle(sanitize_text_field($arguments['name']), SLN_Plugin::POST_TYPE_SERVICE);
			$id = $p ? $p->ID : 0;
		}
		$p = $id ? get_post($id) : null;
		if (! $p || $p->post_type !== SLN_Plugin::POST_TYPE_SERVICE) {
			return new WP_Error('sln_ai_service', __('Service not found (pass service_id or name).', 'salon-booking-system'));
		}

		return $p;
	}
}

class SLN_AI_Tools_SetServicePricing extends SLN_AI_Tools_UpsertService
{
	public function getName() { return 'set_service_pricing'; }
}

class SLN_AI_Tools_UpsertAssistant extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'upsert_assistant'; }

	public function preview(array $arguments)
	{
		$mapped = $this->map($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$existing = $this->find($mapped);
		$parts    = array(
			sprintf('%s assistant "%s"', $existing ? 'Update' : 'Create', $mapped['name']),
		);
		if (! empty($mapped['email'])) {
			$parts[] = 'email: ' . $mapped['email'];
		}
		if (! empty($mapped['phone'])) {
			$parts[] = 'phone: ' . $mapped['phone'];
		}
		if (isset($mapped['hide_on_frontend'])) {
			$parts[] = 'hide_on_frontend: ' . $mapped['hide_on_frontend'];
		}
		if (isset($mapped['multiple_customers'])) {
			$parts[] = 'multiple_customers: ' . $mapped['multiple_customers'];
		}
		if (! empty($mapped['google_calendar'])) {
			$parts[] = 'google_calendar: ' . $mapped['google_calendar'];
		}

		return array(
			'ok'        => true,
			'summary'   => implode("\n", $parts),
			'arguments' => $mapped,
			'tool'      => $this->getName(),
			'current'   => $existing ? array('id' => $existing->ID) : array(),
			'proposed'  => $mapped,
		);
	}

	public function apply(array $arguments)
	{
		$mapped = $this->map($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$existing = $this->find($mapped);
		$before   = $existing ? array('id' => $existing->ID, 'title' => $existing->post_title) : array();
		$postarr  = array(
			'post_type'   => SLN_Plugin::POST_TYPE_ATTENDANT,
			'post_status' => 'publish',
			'post_title'  => $mapped['name'],
		);
		if (isset($mapped['description'])) {
			$postarr['post_content'] = $mapped['description'];
		}
		if ($existing) {
			$postarr['ID'] = $existing->ID;
			$id            = wp_update_post($postarr, true);
		} else {
			$id = wp_insert_post($postarr, true);
			$before['_created'] = true;
			$before['id']       = is_wp_error($id) ? 0 : (int) $id;
		}
		if (is_wp_error($id)) {
			return $id;
		}
		if (! empty($mapped['email'])) {
			update_post_meta($id, '_sln_attendant_email', $mapped['email']);
		}
		if (! empty($mapped['phone'])) {
			update_post_meta($id, '_sln_attendant_phone', $mapped['phone']);
		}
		if (isset($mapped['hide_on_frontend'])) {
			update_post_meta($id, '_sln_attendant_hide_on_frontend', $mapped['hide_on_frontend']);
		}
		if (isset($mapped['multiple_customers'])) {
			update_post_meta($id, '_sln_attendant_multiple_customers', $mapped['multiple_customers']);
		}
		if (isset($mapped['display_phone_inside_booking_notification'])) {
			update_post_meta($id, '_sln_attendant_display_phone_inside_booking_notification', $mapped['display_phone_inside_booking_notification']);
		}
		if (isset($mapped['google_calendar'])) {
			$gcalOn = (bool) $this->settings()->get('google_calendar_enabled');
			$token  = method_exists($this->settings(), 'getGoogleAccessToken')
				? (string) $this->settings()->getGoogleAccessToken()
				: (string) $this->settings()->get('sln_access_token');
			if ($gcalOn && $token !== '') {
				update_post_meta($id, '_sln_attendant_google_calendar', sanitize_text_field($mapped['google_calendar']));
			}
		}
		$this->refreshBookingCaches();

		return array(
			'ok'      => true,
			'before'  => $before,
			'after'   => array('id' => (int) $id, 'title' => $mapped['name']),
			'message' => sprintf(
				/* translators: %s: assistant name */
				__('Assistant “%s” saved.', 'salon-booking-system'),
				$mapped['name']
			),
		);
	}

	public function restore($previous)
	{
		if (is_array($previous) && ! empty($previous['_created']) && ! empty($previous['id'])) {
			wp_trash_post((int) $previous['id']);
		}

		return array('ok' => true, 'message' => __('Assistant change undo attempted.', 'salon-booking-system'));
	}

	private function map(array $arguments)
	{
		$name = isset($arguments['name']) ? sanitize_text_field($arguments['name']) : '';
		if ($name === '') {
			return new WP_Error('sln_ai_assistant_name', __('Assistant name is required.', 'salon-booking-system'));
		}
		$out = array('name' => $name);
		if (isset($arguments['id'])) {
			$out['id'] = absint($arguments['id']);
		}
		if (isset($arguments['email'])) {
			$out['email'] = sanitize_email($arguments['email']);
		}
		if (isset($arguments['phone'])) {
			$out['phone'] = sanitize_text_field($arguments['phone']);
		}
		if (isset($arguments['description'])) {
			$out['description'] = wp_kses_post((string) $arguments['description']);
		}
		foreach (array('hide_on_frontend', 'multiple_customers', 'display_phone_inside_booking_notification') as $flag) {
			if (array_key_exists($flag, $arguments)) {
				$out[ $flag ] = $this->toFlag($arguments[ $flag ]);
			}
		}
		if (isset($arguments['google_calendar'])) {
			$out['google_calendar'] = sanitize_text_field((string) $arguments['google_calendar']);
		}

		return $out;
	}

	private function find(array $mapped)
	{
		if (! empty($mapped['id'])) {
			$p = get_post((int) $mapped['id']);
			if ($p && $p->post_type === SLN_Plugin::POST_TYPE_ATTENDANT) {
				return $p;
			}
		}
		$found = $this->findPostByTitle($mapped['name'], SLN_Plugin::POST_TYPE_ATTENDANT);

		return $found ? $found : null;
	}
}

class SLN_AI_Tools_SetAssistantAvailabilities extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'set_assistant_availabilities'; }
	public function getTier() { return 'confirm'; }

	public function preview(array $arguments)
	{
		$assistant = $this->resolveAssistant($arguments);
		if (is_wp_error($assistant)) {
			return $assistant;
		}
		$hours = new SLN_AI_Tools_SetAvailabilities($this->plugin);
		$mapped = $hours->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$current = get_post_meta($assistant->ID, '_sln_attendant_availabilities', true);
		if (! is_array($current)) {
			$current = array();
		}

		return array(
			'ok'        => true,
			'summary'   => sprintf(
				"Assistant #%d %s\n\nCurrent:\n%s\n\nProposed:\n%s",
				$assistant->ID,
				$assistant->post_title,
				$hours->formatAvailabilitiesSummary($current),
				$hours->formatAvailabilitiesSummary($mapped)
			),
			'arguments' => array_merge($arguments, array('assistant_id' => $assistant->ID, 'rules' => isset($arguments['rules']) ? $arguments['rules'] : array())),
			'tool'      => $this->getName(),
			'current'   => $current,
			'proposed'  => $mapped,
		);
	}

	public function apply(array $arguments)
	{
		$assistant = $this->resolveAssistant($arguments);
		if (is_wp_error($assistant)) {
			return $assistant;
		}
		$hours  = new SLN_AI_Tools_SetAvailabilities($this->plugin);
		$mapped = $hours->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$before = get_post_meta($assistant->ID, '_sln_attendant_availabilities', true);
		if (! is_array($before)) {
			$before = array();
		}
		$processed = SLN_Helper_AvailabilityItems::processSubmission($mapped);
		update_post_meta($assistant->ID, '_sln_attendant_availabilities', $processed);
		$this->refreshBookingCaches();

		return array(
			'ok'      => true,
			'before'  => array('assistant_id' => $assistant->ID, 'availabilities' => $before),
			'after'   => $processed,
			'message' => __('Assistant opening hours updated.', 'salon-booking-system'),
		);
	}

	public function restore($previous)
	{
		if (! is_array($previous) || empty($previous['assistant_id'])) {
			return new WP_Error('sln_ai_restore', __('Cannot restore assistant hours.', 'salon-booking-system'));
		}
		$av = isset($previous['availabilities']) && is_array($previous['availabilities']) ? $previous['availabilities'] : array();
		update_post_meta((int) $previous['assistant_id'], '_sln_attendant_availabilities', SLN_Helper_AvailabilityItems::processSubmission($av));
		$this->refreshBookingCaches();

		return array('ok' => true, 'message' => __('Assistant hours restored.', 'salon-booking-system'));
	}

	private function resolveAssistant(array $arguments)
	{
		$id = isset($arguments['assistant_id']) ? absint($arguments['assistant_id']) : 0;
		if (! $id && ! empty($arguments['name'])) {
			$p  = $this->findPostByTitle(sanitize_text_field($arguments['name']), SLN_Plugin::POST_TYPE_ATTENDANT);
			$id = $p ? $p->ID : 0;
		}
		$p = $id ? get_post($id) : null;
		if (! $p || $p->post_type !== SLN_Plugin::POST_TYPE_ATTENDANT) {
			return new WP_Error('sln_ai_assistant', __('Assistant not found (pass assistant_id or name).', 'salon-booking-system'));
		}

		return $p;
	}
}

class SLN_AI_Tools_SetAssistantHolidays extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'set_assistant_holidays'; }
	public function getTier() { return 'confirm'; }

	public function preview(array $arguments)
	{
		$assistant = $this->resolveAssistant($arguments);
		if (is_wp_error($assistant)) {
			return $assistant;
		}
		$tool   = new SLN_AI_Tools_SetHolidays($this->plugin);
		$mapped = $tool->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$mode    = isset($arguments['mode']) ? $arguments['mode'] : 'append';
		$current = get_post_meta($assistant->ID, '_sln_attendant_holidays', true);
		if (! is_array($current)) {
			$current = array();
		}

		if ($mode === 'append') {
			$parts = $tool->partitionAgainstExisting($mapped, $current);
			if ($mapped && ! $parts['novel'] && $parts['duplicates']) {
				return array(
					'ok'       => true,
					'guidance' => true,
					'summary'  => implode(
						"\n",
						array(
							sprintf(
								/* translators: %s: assistant name */
								__('Those holiday rules already exist for %s — nothing new to add.', 'salon-booking-system'),
								$assistant->post_title
							),
							'',
							__('Already configured:', 'salon-booking-system'),
							$tool->formatHolidaysSummary($parts['duplicates']),
						)
					),
					'tool'     => $this->getName(),
				);
			}
			$mapped   = $parts['novel'];
			$proposed = array_merge(array_values($current), $mapped);
		} else {
			$proposed = array_values($mapped);
		}

		$summary = sprintf(
			"Assistant #%d %s — holidays (%s)\n\nCurrent:\n%s\n\nProposed:\n%s",
			$assistant->ID,
			$assistant->post_title,
			$mode,
			$tool->formatHolidaysSummary($current),
			$tool->formatHolidaysSummary($proposed)
		);

		$rules = array();
		foreach ($mapped as $row) {
			$rules[] = array(
				'from_date' => $row['from_date'],
				'to_date'   => $row['to_date'],
				'from_time' => $row['from_time'],
				'to_time'   => $row['to_time'],
				'full_day'  => ($row['from_time'] === '00:00' && ($row['to_time'] === '24:00' || $row['to_time'] === '00:00')),
			);
		}

		return array(
			'ok'        => true,
			'summary'   => $summary,
			'arguments' => array(
				'mode'         => $mode,
				'assistant_id' => $assistant->ID,
				'rules'        => $rules,
			),
			'tool'      => $this->getName(),
			'current'   => $current,
			'proposed'  => $proposed,
		);
	}

	public function apply(array $arguments)
	{
		$assistant = $this->resolveAssistant($arguments);
		if (is_wp_error($assistant)) {
			return $assistant;
		}
		$tool   = new SLN_AI_Tools_SetHolidays($this->plugin);
		$mapped = $tool->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$mode   = isset($arguments['mode']) ? $arguments['mode'] : 'append';
		$before = get_post_meta($assistant->ID, '_sln_attendant_holidays', true);
		if (! is_array($before)) {
			$before = array();
		}
		if ($mode === 'append') {
			$parts  = $tool->partitionAgainstExisting($mapped, $before);
			$mapped = $parts['novel'];
			if (! $mapped) {
				return array(
					'ok'      => true,
					'before'  => array('assistant_id' => $assistant->ID, 'holidays' => $before),
					'after'   => $before,
					'message' => __('No holiday changes applied — those rules already exist.', 'salon-booking-system'),
				);
			}
			$merged = array_merge(array_values($before), array_values($mapped));
		} else {
			$merged = array_values($mapped);
		}
		$processed = SLN_Helper_HolidayItems::processSubmission($merged);
		update_post_meta($assistant->ID, '_sln_attendant_holidays', $processed);
		$this->refreshBookingCaches();

		return array(
			'ok'      => true,
			'before'  => array('assistant_id' => $assistant->ID, 'holidays' => $before),
			'after'   => $processed,
			'message' => __('Assistant holidays updated.', 'salon-booking-system'),
		);
	}

	public function restore($previous)
	{
		if (! is_array($previous) || empty($previous['assistant_id'])) {
			return new WP_Error('sln_ai_restore', __('Cannot restore assistant holidays.', 'salon-booking-system'));
		}
		$h = isset($previous['holidays']) && is_array($previous['holidays']) ? $previous['holidays'] : array();
		update_post_meta((int) $previous['assistant_id'], '_sln_attendant_holidays', SLN_Helper_HolidayItems::processSubmission($h));
		$this->refreshBookingCaches();

		return array('ok' => true, 'message' => __('Assistant holidays restored.', 'salon-booking-system'));
	}

	private function resolveAssistant(array $arguments)
	{
		$id = isset($arguments['assistant_id']) ? absint($arguments['assistant_id']) : 0;
		if (! $id && ! empty($arguments['name'])) {
			$p  = $this->findPostByTitle(sanitize_text_field($arguments['name']), SLN_Plugin::POST_TYPE_ATTENDANT);
			$id = $p ? $p->ID : 0;
		}
		$p = $id ? get_post($id) : null;
		if (! $p || $p->post_type !== SLN_Plugin::POST_TYPE_ATTENDANT) {
			return new WP_Error('sln_ai_assistant', __('Assistant not found.', 'salon-booking-system'));
		}

		return $p;
	}
}

class SLN_AI_Tools_UpsertResource extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'upsert_resource'; }

	public function preview(array $arguments)
	{
		$blocked = SLN_AI_Edition::blockIfFree('resources');
		if ($blocked) {
			return $blocked;
		}
		$name = isset($arguments['name']) ? sanitize_text_field($arguments['name']) : '';
		if ($name === '') {
			return new WP_Error('sln_ai_resource', __('Resource name is required.', 'salon-booking-system'));
		}

		$serviceIds = $this->resolveServiceIds($arguments);
		$summary    = sprintf('Upsert resource "%s" (units: %s)', $name, isset($arguments['unit']) ? absint($arguments['unit']) : 1);
		if ($serviceIds) {
			$summary .= "\nservices: " . implode(', ', $serviceIds);
		}
		if (isset($arguments['enabled'])) {
			$summary .= "\nenabled: " . $this->toFlag($arguments['enabled']);
		}

		return array(
			'ok'        => true,
			'summary'   => $summary,
			'arguments' => array(
				'name'        => $name,
				'unit'        => isset($arguments['unit']) ? absint($arguments['unit']) : 1,
				'id'          => isset($arguments['id']) ? absint($arguments['id']) : 0,
				'service_ids' => $serviceIds,
				'enabled'     => array_key_exists('enabled', $arguments) ? $this->toFlag($arguments['enabled']) : null,
			),
			'tool'      => $this->getName(),
		);
	}

	public function apply(array $arguments)
	{
		if (! SLN_AI_Edition::isPro()) {
			return new WP_Error('sln_ai_pro', __('Resources require the PRO edition.', 'salon-booking-system'));
		}
		$preview = $this->preview($arguments);
		if (is_wp_error($preview)) {
			return $preview;
		}
		$args = $preview['arguments'];
		$postarr = array(
			'post_type'   => SLN_Plugin::POST_TYPE_RESOURCE,
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
		update_post_meta($id, '_sln_resource_unit', $args['unit']);
		$enabled = $args['enabled'] !== null ? $args['enabled'] : '1';
		update_post_meta($id, '_sln_resource_enabled', $enabled);
		if (! empty($args['service_ids'])) {
			update_post_meta($id, '_sln_resource_services', array_map('intval', $args['service_ids']));
		}

		return array(
			'ok'      => true,
			'before'  => array(),
			'after'   => array('id' => (int) $id),
			'message' => __('Resource saved.', 'salon-booking-system'),
		);
	}

	/**
	 * @param array $arguments
	 * @return int[]
	 */
	private function resolveServiceIds(array $arguments)
	{
		$ids = array();
		if (! empty($arguments['service_ids']) && is_array($arguments['service_ids'])) {
			$ids = array_map('absint', $arguments['service_ids']);
		}
		if (! empty($arguments['service_names']) && is_array($arguments['service_names'])) {
			foreach ($arguments['service_names'] as $sn) {
				$p = $this->findPostByTitle(sanitize_text_field((string) $sn), SLN_Plugin::POST_TYPE_SERVICE);
				if ($p) {
					$ids[] = (int) $p->ID;
				}
			}
		}

		return array_values(array_unique(array_filter($ids)));
	}

	public function restore($previous)
	{
		return array('ok' => true, 'message' => __('Resource undo not fully supported.', 'salon-booking-system'));
	}
}

class SLN_AI_Tools_CompleteOnboarding extends SLN_AI_Tools_Abstract
{
	public function getName() { return 'complete_onboarding'; }
	public function getTier() { return 'confirm'; }

	public function preview(array $arguments)
	{
		$done = get_option('_sln_onboarding_completed');

		return array(
			'ok'        => true,
			'summary'   => $done
				? __('Onboarding is already marked complete.', 'salon-booking-system')
				: __('Mark the setup wizard as completed so Salon admin redirects stop.', 'salon-booking-system'),
			'arguments' => array('confirm' => true),
			'tool'      => $this->getName(),
			'current'   => array('completed' => (bool) $done),
			'proposed'  => array('completed' => true),
		);
	}

	public function apply(array $arguments)
	{
		$before = (bool) get_option('_sln_onboarding_completed');
		update_option('_sln_onboarding_completed', '1');
		delete_transient('sln_redirect_to_onboarding');

		return array(
			'ok'      => true,
			'before'  => array('completed' => $before),
			'after'   => array('completed' => true),
			'message' => __('Onboarding marked as complete.', 'salon-booking-system'),
		);
	}

	public function restore($previous)
	{
		if (is_array($previous) && empty($previous['completed'])) {
			delete_option('_sln_onboarding_completed');
		}

		return array('ok' => true, 'message' => __('Onboarding flag restored.', 'salon-booking-system'));
	}
}
