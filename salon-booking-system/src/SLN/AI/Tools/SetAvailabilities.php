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

		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$current = SLN_AI_Multishop::getScopedSetting($shop, 'availabilities');
		if (! is_array($current)) {
			$current = array();
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

		$shopArgs = SLN_AI_Multishop::argsFromShop($shop);

		return array(
			'ok'        => true,
			'summary'   => $diff,
			'proposed'  => $mapped,
			'current'   => $current,
			'arguments' => array(
				'mode'       => 'replace_all',
				'summary'    => isset($arguments['summary']) ? sanitize_text_field($arguments['summary']) : '',
				'rules'      => isset($arguments['rules']) ? $arguments['rules'] : array(),
				'shop_id'    => $shopArgs['shop_id'],
				'shop_name'  => $shopArgs['shop_name'],
			),
			'tool'      => $this->getName(),
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

		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$before = SLN_AI_Multishop::getScopedSetting($shop, 'availabilities');
		if (! is_array($before)) {
			$before = array();
		}

		$processed = SLN_Helper_AvailabilityItems::processSubmission($mapped);
		if ($shop) {
			SLN_AI_Multishop::writeShopSetting($shop, 'availabilities', $processed);
			SLN_AI_Multishop::refreshCaches($shop);
		} else {
			$this->settings()->set('availabilities', $processed);
			$this->settings()->save();
			$this->refreshBookingCaches();
		}

		$msg = __('Opening hours updated.', 'salon-booking-system');
		$scope = SLN_AI_Multishop::scopeLabel($shop);
		if ($scope !== '') {
			$msg = $scope . ' — ' . $msg;
		}

		return array(
			'ok'      => true,
			'before'  => SLN_AI_Multishop::wrapSnapshot($shop, $before),
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
		$processed = SLN_Helper_AvailabilityItems::processSubmission($data);
		if (! is_array($processed)) {
			$processed = array();
		}
		if ($shop) {
			SLN_AI_Multishop::writeShopSetting($shop, 'availabilities', $processed);
			SLN_AI_Multishop::refreshCaches($shop);
		} else {
			$this->settings()->set('availabilities', $processed);
			$this->settings()->save();
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

		$always = ! isset($rule['always']) || $rule['always'];
		$row    = array(
			'days'                 => $days,
			'from'                 => $from,
			'to'                   => $to,
			'always'               => $always ? true : false,
			// Second shift off unless the user explicitly provided two intervals.
			'disable_second_shift' => $i < 2,
		);

		if (! $always) {
			if (empty($rule['from_date']) || empty($rule['to_date'])) {
				return new WP_Error(
					'sln_ai_dates',
					__('Date-limited rules need from_date and to_date.', 'salon-booking-system')
				);
			}
			$row['from_date'] = sanitize_text_field($rule['from_date']);
			$row['to_date']   = sanitize_text_field($rule['to_date']);
		}

		return $row;
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
