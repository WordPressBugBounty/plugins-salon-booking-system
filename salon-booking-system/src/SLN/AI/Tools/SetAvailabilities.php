<?php

/**
 * Apply salon-level availabilities via validated tool args.
 */
class SLN_AI_Tools_SetAvailabilities extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'set_salon_availabilities';
	}

	public function getTier()
	{
		return 'confirm';
	}

	/**
	 * Validate args and build a preview payload (no write).
	 *
	 * @param array $arguments
	 * @return array{ok:bool,error?:string,summary?:string,proposed?:array,current?:array,arguments?:array}|WP_Error
	 */
	public function preview(array $arguments)
	{
		$bound = SLN_AI_Multishop::bindShop($arguments, true);
		if (is_wp_error($bound)) {
			return $bound;
		}
		if (empty($bound['ok'])) {
			return $bound;
		}
		$shop = isset($bound['shop']) ? $bound['shop'] : null;

		$current = SLN_AI_Multishop::getScopedSetting($shop, 'availabilities');
		if (! is_array($current)) {
			$current = array();
		}

		$mapped = $this->resolveProposed($arguments, $current);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$analysis = SLN_AI_AvailabilityCascade::analyze($this->plugin, $mapped);
		$writes   = SLN_AI_AvailabilityCascade::alignmentWrites($analysis);
		if ($this->shouldSkipCatalogAlign()) {
			$cascade = array('assistants' => array(), 'services' => array());
		} else {
			$cascade = $this->cascadeWriteArgs($writes);
		}

		$diff = sprintf(
			/* translators: 1: current hours summary, 2: proposed hours summary */
			__("Current:\n%s\n\nProposed (replaces all salon opening rules):\n%s", 'salon-booking-system'),
			$this->formatAvailabilitiesSummary($current),
			$this->formatAvailabilitiesSummary($mapped)
		);
		$scope = SLN_AI_Multishop::scopeLabel($shop);
		if ($scope !== '') {
			$diff = $scope . "\n\n" . $diff;
		}
		$preserved = $this->preservedDaysNote($current, $mapped, $arguments);
		if ($preserved !== '') {
			$diff .= "\n\n" . $preserved;
		}
		$diff .= "\n\n" . SLN_AI_AvailabilityCascade::formatAnalysisSummary($analysis);
		if ($this->shouldSkipCatalogAlign() && ( $writes['assistants'] || $writes['services'] )) {
			$diff .= "\n\n" . __(
				'Multi-shop is active with more than one location. Salon hours will update for this shop only; assistant and service custom rules are shared and will not be auto-aligned. Review the conflicts above and update those schedules separately if needed.',
				'salon-booking-system'
			);
		}

		$shopArgs = SLN_AI_Multishop::argsFromShop($shop);

		return array(
			'ok'        => true,
			'summary'   => $diff,
			'proposed'  => $mapped,
			'current'   => $current,
			'arguments' => array(
				'mode'                  => 'replace_all',
				'summary'               => isset($arguments['summary']) ? sanitize_text_field($arguments['summary']) : '',
				'rules'                 => SLN_AI_AvailabilityCascade::toToolRules($mapped),
				'closed_days'           => $this->sanitizeClosedDays($arguments),
				'preserve_unmentioned'  => empty($arguments['preserve_unmentioned']) ? false : true,
				'shop_id'               => $shopArgs['shop_id'],
				'shop_name'             => $shopArgs['shop_name'],
				'cascade_assistants'    => $cascade['assistants'],
				'cascade_services'      => $cascade['services'],
			),
			'tool'      => $this->getName(),
			'analysis'  => $analysis,
		);
	}

	/**
	 * Persist mapped availabilities and refresh caches.
	 *
	 * @param array $arguments Original tool arguments (or preview arguments).
	 * @return array{ok:bool,before:array,after:array,message:string}|WP_Error
	 */
	public function apply(array $arguments)
	{
		$bound = SLN_AI_Multishop::bindShop($arguments, true);
		if (is_wp_error($bound)) {
			return $bound;
		}
		if (empty($bound['ok'])) {
			return new WP_Error('sln_ai_shop_required', isset($bound['summary']) ? $bound['summary'] : __('Shop required.', 'salon-booking-system'));
		}
		$shop = isset($bound['shop']) ? $bound['shop'] : null;

		$beforeSalon = SLN_AI_Multishop::getScopedSetting($shop, 'availabilities');
		if (! is_array($beforeSalon)) {
			$beforeSalon = array();
		}

		$mapped = $this->resolveProposed($arguments, $beforeSalon);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$cascade = $this->normalizeCascadeArgs($arguments);
		if ($this->shouldSkipCatalogAlign()) {
			$cascade = array('assistants' => array(), 'services' => array());
		} elseif (! $cascade['assistants'] && ! $cascade['services']) {
			$analysis = SLN_AI_AvailabilityCascade::analyze($this->plugin, $mapped);
			$cascade  = $this->cascadeWriteArgs(SLN_AI_AvailabilityCascade::alignmentWrites($analysis));
		}

		$beforeAssistants = $this->snapshotCatalogMeta($cascade['assistants'], SLN_AI_AvailabilityCascade::ATTENDANT_META);
		$beforeServices   = $this->snapshotCatalogMeta($cascade['services'], SLN_AI_AvailabilityCascade::SERVICE_META);

		$processed = SLN_Helper_AvailabilityItems::processSubmission($mapped);
		if ($shop) {
			SLN_AI_Multishop::writeShopSetting($shop, 'availabilities', $processed);
		} else {
			$this->settings()->set('availabilities', $processed);
			$this->settings()->save();
		}

		$this->applyCatalogHours($cascade['assistants'], SLN_AI_AvailabilityCascade::ATTENDANT_META);
		$this->applyCatalogHours($cascade['services'], SLN_AI_AvailabilityCascade::SERVICE_META);

		if ($shop) {
			SLN_AI_Multishop::refreshCaches($shop);
		} else {
			$this->refreshBookingCaches();
		}

		$msg = __('Opening hours updated.', 'salon-booking-system');
		$scope = SLN_AI_Multishop::scopeLabel($shop);
		if ($scope !== '') {
			$msg = $scope . ' — ' . $msg;
		}
		$aligned = count($cascade['assistants']) + count($cascade['services']);
		if ($aligned) {
			$msg .= ' ' . sprintf(
				/* translators: %d: number of assistants/services updated */
				_n(
					'%d custom assistant/service schedule was aligned so the new hours are bookable.',
					'%d custom assistant/service schedules were aligned so the new hours are bookable.',
					$aligned,
					'salon-booking-system'
				),
				$aligned
			);
		}

		return array(
			'ok'      => true,
			'before'  => SLN_AI_Multishop::wrapSnapshot(
				$shop,
				array(
					'salon'      => $beforeSalon,
					'assistants' => $beforeAssistants,
					'services'   => $beforeServices,
				)
			),
			'after'   => $processed,
			'message' => $msg,
		);
	}

	/**
	 * Restore a previous availabilities snapshot (undo).
	 *
	 * @param mixed $previous
	 * @return array|WP_Error
	 */
	public function restore($previous)
	{
		$unwrapped = SLN_AI_Multishop::unwrapSnapshot($previous);
		$shop      = $unwrapped['shop'];
		$data      = $unwrapped['data'];
		if (! is_array($data)) {
			$data = array();
		}

		$salon      = $data;
		$assistants = array();
		$services   = array();
		if (isset($data['salon'])) {
			$salon      = isset($data['salon']) && is_array($data['salon']) ? $data['salon'] : array();
			$assistants = isset($data['assistants']) && is_array($data['assistants']) ? $data['assistants'] : array();
			$services   = isset($data['services']) && is_array($data['services']) ? $data['services'] : array();
		}

		$processed = SLN_Helper_AvailabilityItems::processSubmission($salon);
		if (! is_array($processed)) {
			$processed = array();
		}
		if ($shop) {
			SLN_AI_Multishop::writeShopSetting($shop, 'availabilities', $processed);
		} else {
			$this->settings()->set('availabilities', $processed);
			$this->settings()->save();
		}
		$this->restoreCatalogMeta($assistants, SLN_AI_AvailabilityCascade::ATTENDANT_META);
		$this->restoreCatalogMeta($services, SLN_AI_AvailabilityCascade::SERVICE_META);
		if ($shop) {
			SLN_AI_Multishop::refreshCaches($shop);
		} else {
			$this->refreshBookingCaches();
		}

		return array(
			'ok'      => true,
			'message' => __('Opening hours restored.', 'salon-booking-system'),
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error Availability rows keyed like settings form (1-based).
	 */
	public function mapArguments(array $arguments)
	{
		$mode = isset($arguments['mode']) ? $arguments['mode'] : 'replace_all';
		if ($mode !== 'replace_all') {
			return new WP_Error(
				'sln_ai_mode',
				__('Only replace_all mode is supported in this version.', 'salon-booking-system')
			);
		}

		$arguments = array_merge($arguments, SLN_AI_AvailabilityCascade::normalizeToolRules($arguments));
		if (empty($arguments['rules']) || ! is_array($arguments['rules'])) {
			return new WP_Error(
				'sln_ai_empty_rules',
				__('No availability rules provided.', 'salon-booking-system')
			);
		}

		$out = array();
		$n   = 0;
		foreach ($arguments['rules'] as $rule) {
			if (! is_array($rule)) {
				continue;
			}
			$row = $this->mapRule($rule);
			if (is_wp_error($row)) {
				// Closed / empty-interval rules are already stripped; skip leftovers.
				if ($row->get_error_code() === 'sln_ai_intervals') {
					continue;
				}
				return $row;
			}
			$n++;
			$out[ $n ] = $row;
		}

		if (! $out) {
			return new WP_Error(
				'sln_ai_empty_rules',
				__('No valid availability rules provided.', 'salon-booking-system')
			);
		}

		return $out;
	}

	/**
	 * @param array $rule
	 * @return array|WP_Error
	 */
	private function mapRule(array $rule)
	{
		$daysIn = isset($rule['days']) && is_array($rule['days']) ? $rule['days'] : array();
		$days   = array();
		foreach ($daysIn as $d) {
			$d = (int) $d;
			if ($d >= 1 && $d <= 7) {
				$days[ $d ] = 1;
			}
		}
		if (! $days) {
			return new WP_Error(
				'sln_ai_days',
				__('Each rule needs at least one weekday (1=Sunday … 7=Saturday).', 'salon-booking-system')
			);
		}

		$intervals = isset($rule['intervals']) && is_array($rule['intervals']) ? $rule['intervals'] : array();
		if (! $intervals) {
			return new WP_Error(
				'sln_ai_intervals',
				__('Each rule needs at least one time interval.', 'salon-booking-system')
			);
		}

		$from = array('00:00', '00:00');
		$to   = array('00:00', '00:00');
		$i    = 0;
		foreach ($intervals as $interval) {
			if ($i > 1) {
				break;
			}
			if (! is_array($interval) || empty($interval['from']) || empty($interval['to'])) {
				return new WP_Error(
					'sln_ai_interval',
					__('Invalid time interval.', 'salon-booking-system')
				);
			}
			$fromT = $this->sanitizeTime($interval['from']);
			$toT   = $this->sanitizeTime($interval['to']);
			if (! $fromT || ! $toT) {
				return new WP_Error(
					'sln_ai_interval',
					__('Times must look like 09:00.', 'salon-booking-system')
				);
			}
			// Skip empty/disabled placeholder intervals.
			if ($fromT === '00:00' && $toT === '00:00') {
				continue;
			}
			$from[ $i ] = $fromT;
			$to[ $i ]   = $toT;
			$i++;
		}

		if ($i < 1) {
			return new WP_Error(
				'sln_ai_intervals',
				__('Each rule needs at least one time interval.', 'salon-booking-system')
			);
		}

		$fromDate = isset($rule['from_date']) ? sanitize_text_field($rule['from_date']) : '';
		$toDate   = isset($rule['to_date']) ? sanitize_text_field($rule['to_date']) : '';
		$hasDates = $fromDate !== '' && $toDate !== '';
		// Weekly opening hours are recurring. always=false without dates is an LLM
		// artifact (optional boolean), not a date-limited rule.
		$always = $hasDates ? false : true;
		$row    = array(
			'days'                 => $days,
			'from'                 => $from,
			'to'                   => $to,
			'always'               => $always,
			// Second shift off unless the user explicitly provided two intervals.
			'disable_second_shift' => $i < 2,
		);

		if ($hasDates) {
			$row['from_date'] = $fromDate;
			$row['to_date']   = $toDate;
		}

		return $row;
	}

	/**
	 * Map tool args, then merge unmentioned weekdays from current salon hours.
	 *
	 * @param array $arguments
	 * @param array $current
	 * @return array|WP_Error
	 */
	public function resolveProposed(array $arguments, array $current)
	{
		$normalized = SLN_AI_AvailabilityCascade::normalizeToolRules($arguments);
		$arguments  = array_merge($arguments, $normalized);

		if (empty($arguments['rules']) && ! empty($normalized['closed_days'])) {
			return SLN_AI_AvailabilityCascade::mergeProposed(
				$current,
				array(),
				$normalized['closed_days'],
				true
			);
		}

		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$closed    = $this->sanitizeClosedDays($arguments);
		$preserve  = ! empty($arguments['preserve_unmentioned']);
		if (! $closed && ! $preserve) {
			return $mapped;
		}

		return SLN_AI_AvailabilityCascade::mergeProposed($current, $mapped, $closed, $preserve);
	}

	/**
	 * Shared assistant/service hours must not be rewritten from one shop’s timetable.
	 *
	 * @return bool
	 */
	private function shouldSkipCatalogAlign()
	{
		return SLN_AI_Multishop::isActive() && count(SLN_AI_Multishop::listShops()) > 1;
	}

	/**
	 * @param array $arguments
	 * @return int[]
	 */
	private function sanitizeClosedDays(array $arguments)
	{
		$out = array();
		if (empty($arguments['closed_days']) || ! is_array($arguments['closed_days'])) {
			return $out;
		}
		foreach ($arguments['closed_days'] as $day) {
			$day = (int) $day;
			if ($day >= 1 && $day <= 7) {
				$out[] = $day;
			}
		}

		return array_values(array_unique($out));
	}

	/**
	 * @param array $current
	 * @param array $mapped
	 * @param array $arguments
	 * @return string
	 */
	private function preservedDaysNote(array $current, array $mapped, array $arguments)
	{
		if (empty($arguments['preserve_unmentioned'])) {
			return '';
		}
		$before = SLN_AI_AvailabilityCascade::weekMap($current);
		$after  = SLN_AI_AvailabilityCascade::weekMap($mapped);
		$closed = array();
		foreach ($this->sanitizeClosedDays($arguments) as $day) {
			$closed[ $day ] = true;
		}
		$kept = array();
		$names = array(
			1 => __('Sunday', 'salon-booking-system'),
			2 => __('Monday', 'salon-booking-system'),
			3 => __('Tuesday', 'salon-booking-system'),
			4 => __('Wednesday', 'salon-booking-system'),
			5 => __('Thursday', 'salon-booking-system'),
			6 => __('Friday', 'salon-booking-system'),
			7 => __('Saturday', 'salon-booking-system'),
		);
		for ($day = 1; $day <= 7; $day++) {
			if (! empty($closed[ $day ]) || empty($before[ $day ]) || empty($after[ $day ])) {
				continue;
			}
			if (SLN_AI_AvailabilityCascade::diffIssues(array($day => $before[ $day ]), array($day => $after[ $day ]))) {
				continue;
			}
			// Day kept from current and still present after merge.
			$proposedDays = array();
			if (! empty($arguments['rules']) && is_array($arguments['rules'])) {
				foreach ($arguments['rules'] as $rule) {
					if (! empty($rule['days']) && is_array($rule['days'])) {
						foreach ($rule['days'] as $d) {
							$proposedDays[ (int) $d ] = true;
						}
					}
				}
			}
			if (empty($proposedDays[ $day ]) && isset($names[ $day ])) {
				$kept[] = $names[ $day ];
			}
		}
		if (! $kept) {
			return '';
		}

		return sprintf(
			/* translators: %s: weekday list */
			__('Unmentioned days kept from the current timetable: %s.', 'salon-booking-system'),
			implode(', ', $kept)
		);
	}

	/**
	 * @param array $writes
	 * @return array{assistants:array,services:array}
	 */
	private function cascadeWriteArgs(array $writes)
	{
		$out = array(
			'assistants' => array(),
			'services'   => array(),
		);
		foreach (array('assistants', 'services') as $kind) {
			if (empty($writes[ $kind ]) || ! is_array($writes[ $kind ])) {
				continue;
			}
			foreach ($writes[ $kind ] as $row) {
				if (empty($row['id']) || empty($row['rules'])) {
					continue;
				}
				$out[ $kind ][] = array(
					'id'    => (int) $row['id'],
					'name'  => isset($row['name']) ? sanitize_text_field($row['name']) : '',
					'rules' => $row['rules'],
				);
			}
		}

		return $out;
	}

	/**
	 * @param array $arguments
	 * @return array{assistants:array,services:array}
	 */
	private function normalizeCascadeArgs(array $arguments)
	{
		$out = array(
			'assistants' => array(),
			'services'   => array(),
		);
		foreach (array('assistants' => 'cascade_assistants', 'services' => 'cascade_services') as $kind => $key) {
			if (empty($arguments[ $key ]) || ! is_array($arguments[ $key ])) {
				continue;
			}
			foreach ($arguments[ $key ] as $row) {
				if (! is_array($row) || empty($row['id']) || empty($row['rules'])) {
					continue;
				}
				$out[ $kind ][] = array(
					'id'    => (int) $row['id'],
					'rules' => $row['rules'],
				);
			}
		}

		return $out;
	}

	/**
	 * @param array  $rows
	 * @param string $metaKey
	 * @return array
	 */
	private function snapshotCatalogMeta(array $rows, $metaKey)
	{
		$out = array();
		foreach ($rows as $row) {
			$id = isset($row['id']) ? (int) $row['id'] : 0;
			if (! $id) {
				continue;
			}
			$raw = get_post_meta($id, $metaKey, true);
			$out[] = array(
				'id'             => $id,
				'availabilities' => is_array($raw) ? $raw : array(),
			);
		}

		return $out;
	}

	/**
	 * @param array  $rows
	 * @param string $metaKey
	 */
	private function applyCatalogHours(array $rows, $metaKey)
	{
		foreach ($rows as $row) {
			$id = isset($row['id']) ? (int) $row['id'] : 0;
			if (! $id || empty($row['rules']) || ! is_array($row['rules'])) {
				continue;
			}
			$mapped = $this->mapArguments(
				array(
					'mode'  => 'replace_all',
					'rules' => $row['rules'],
				)
			);
			if (is_wp_error($mapped)) {
				continue;
			}
			update_post_meta($id, $metaKey, SLN_Helper_AvailabilityItems::processSubmission($mapped));
		}
	}

	/**
	 * @param array  $rows
	 * @param string $metaKey
	 */
	private function restoreCatalogMeta(array $rows, $metaKey)
	{
		foreach ($rows as $row) {
			$id = isset($row['id']) ? (int) $row['id'] : 0;
			if (! $id) {
				continue;
			}
			$av = isset($row['availabilities']) && is_array($row['availabilities']) ? $row['availabilities'] : array();
			update_post_meta($id, $metaKey, SLN_Helper_AvailabilityItems::processSubmission($av));
		}
	}

	/**
	 * @param string $time
	 * @return string|null
	 */
	private function sanitizeTime($time)
	{
		$time = preg_replace('/\s+/', '', (string) $time);
		if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
			return null;
		}
		$h = (int) $m[1];
		$i = (int) $m[2];
		if ($h < 0 || $h > 24 || $i < 0 || $i > 59) {
			return null;
		}
		if ($h === 24 && $i !== 0) {
			return null;
		}

		return sprintf('%02d:%02d', $h, $i);
	}

	/**
	 * @param array $availabilities
	 * @return string
	 */
	public function formatAvailabilitiesSummary($availabilities)
	{
		if (! is_array($availabilities) || ! $availabilities) {
			return __('(none)', 'salon-booking-system');
		}

		$dayNames = SLN_Func::getDays();
		$lines    = array();
		foreach ($availabilities as $row) {
			if (! is_array($row)) {
				continue;
			}
			$days = array();
			if (! empty($row['days']) && is_array($row['days'])) {
				foreach ($row['days'] as $k => $v) {
					if ($v && isset($dayNames[ $k ])) {
						$days[] = $dayNames[ $k ];
					}
				}
			}
			$shifts = array();
			if (! empty($row['from']) && is_array($row['from'])) {
				foreach ($row['from'] as $idx => $from) {
					$to = isset($row['to'][ $idx ]) ? $row['to'][ $idx ] : '';
					if ($from === '00:00' && $to === '00:00') {
						continue;
					}
					$shifts[] = $from . '–' . $to;
				}
			}
			$period = ! empty($row['always'])
				? __('always', 'salon-booking-system')
				: trim((isset($row['from_date']) ? $row['from_date'] : '') . ' → ' . (isset($row['to_date']) ? $row['to_date'] : ''));

			$lines[] = sprintf(
				'%s | %s | %s',
				$days ? implode(', ', $days) : __('(no days)', 'salon-booking-system'),
				$shifts ? implode(', ', $shifts) : __('(no hours)', 'salon-booking-system'),
				$period
			);
		}

		return $lines ? implode("\n", $lines) : __('(none)', 'salon-booking-system');
	}
}
