<?php

/**
 * Apply salon-level holiday rules via validated tool args.
 */
class SLN_AI_Tools_SetHolidays extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'set_salon_holidays';
	}

	public function getTier()
	{
		return 'confirm';
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
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

		$mode = isset($arguments['mode']) ? $arguments['mode'] : 'append';
		if (! in_array($mode, array('append', 'replace_all'), true)) {
			return new WP_Error(
				'sln_ai_holiday_mode',
				__('Holiday mode must be append or replace_all.', 'salon-booking-system')
			);
		}

		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$current = SLN_AI_Multishop::getScopedSetting($shop, 'holidays');
		if (! is_array($current)) {
			$current = array();
		}

		$parts = array(
			'duplicates' => array(),
			'novel'      => array_values($mapped),
		);
		if ($mode === 'append') {
			$parts = $this->partitionAgainstExisting($mapped, $current);
			if ($mapped && ! $parts['novel'] && $parts['duplicates']) {
				$msg = array(
					__('Those holiday rules already exist — nothing new to add.', 'salon-booking-system'),
					'',
					__('Already configured:', 'salon-booking-system'),
					$this->formatHolidaysSummary($parts['duplicates']),
					'',
					__('Current holidays:', 'salon-booking-system'),
					$this->formatHolidaysSummary($current),
				);

				return array(
					'ok'       => true,
					'guidance' => true,
					'summary'  => implode("\n", $msg),
					'tool'     => $this->getName(),
					'current'  => $current,
					'proposed' => $current,
				);
			}
			$mapped   = $parts['novel'];
			$proposed = array_merge(array_values($current), $mapped);
		} else {
			$proposed = array_values($mapped);
		}

		$diff = sprintf(
			/* translators: 1: mode label, 2: current holidays, 3: proposed holidays */
			__("Mode: %1\$s\n\nCurrent holidays:\n%2\$s\n\nProposed holidays:\n%3\$s", 'salon-booking-system'),
			$mode === 'append'
				? __('add to existing', 'salon-booking-system')
				: __('replace all', 'salon-booking-system'),
			$this->formatHolidaysSummary($current),
			$this->formatHolidaysSummary($proposed)
		);

		if ($mode === 'append' && ! empty($parts['duplicates'])) {
			$diff = __(
				'Note: some dates were skipped because matching holiday rules already exist.',
				'salon-booking-system'
			) . "\n" . $this->formatHolidaysSummary($parts['duplicates']) . "\n\n" . $diff;
		}

		$scope = SLN_AI_Multishop::scopeLabel($shop);
		if ($scope !== '') {
			$diff = $scope . "\n\n" . $diff;
		}

		$rulesForApply = ($mode === 'append') ? $mapped : (isset($arguments['rules']) ? $arguments['rules'] : array());
		// Rebuild rules payload from mapped rows for append (novel only).
		if ($mode === 'append') {
			$rulesForApply = array();
			foreach ($mapped as $row) {
				$rulesForApply[] = array(
					'from_date' => $row['from_date'],
					'to_date'   => $row['to_date'],
					'from_time' => $row['from_time'],
					'to_time'   => $row['to_time'],
					'full_day'  => ($row['from_time'] === '00:00' && ($row['to_time'] === '24:00' || $row['to_time'] === '00:00')),
				);
			}
		}

		$shopArgs = SLN_AI_Multishop::argsFromShop($shop);

		return array(
			'ok'        => true,
			'summary'   => $diff,
			'proposed'  => $proposed,
			'current'   => $current,
			'arguments' => array(
				'mode'      => $mode,
				'summary'   => isset($arguments['summary']) ? sanitize_text_field($arguments['summary']) : '',
				'rules'     => $rulesForApply,
				'shop_id'   => $shopArgs['shop_id'],
				'shop_name' => $shopArgs['shop_name'],
			),
			'tool'      => $this->getName(),
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
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

		$mode   = isset($arguments['mode']) ? $arguments['mode'] : 'append';
		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$before = SLN_AI_Multishop::getScopedSetting($shop, 'holidays');
		if (! is_array($before)) {
			$before = array();
		}

		if ($mode === 'append') {
			$parts  = $this->partitionAgainstExisting($mapped, $before);
			$mapped = $parts['novel'];
			if (! $mapped) {
				return array(
					'ok'      => true,
					'before'  => SLN_AI_Multishop::wrapSnapshot($shop, $before),
					'after'   => $before,
					'message' => __('No holiday changes applied — those rules already exist.', 'salon-booking-system'),
				);
			}
			$merged = array_merge(array_values($before), array_values($mapped));
		} else {
			$merged = array_values($mapped);
		}

		$processed = SLN_Helper_HolidayItems::processSubmission($merged);
		if (! is_array($processed)) {
			$processed = array();
		}

		if ($shop) {
			SLN_AI_Multishop::writeShopSetting($shop, 'holidays', $processed);
			SLN_AI_Multishop::refreshCaches($shop);
		} else {
			$this->settings()->set('holidays', $processed);
			$this->settings()->save();
			$this->refreshBookingCaches();
		}

		$msg = __('Holiday rules updated.', 'salon-booking-system');
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
		$processed = SLN_Helper_HolidayItems::processSubmission($data);
		if (! is_array($processed)) {
			$processed = array();
		}
		if ($shop) {
			SLN_AI_Multishop::writeShopSetting($shop, 'holidays', $processed);
			SLN_AI_Multishop::refreshCaches($shop);
		} else {
			$this->settings()->set('holidays', $processed);
			$this->settings()->save();
			$this->refreshBookingCaches();
		}

		return array(
			'ok'      => true,
			'message' => __('Holiday rules restored.', 'salon-booking-system'),
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error Indexed list of holiday rows.
	 */
	public function mapArguments(array $arguments)
	{
		$mode = isset($arguments['mode']) ? $arguments['mode'] : 'append';

		// replace_all with empty rules = clear all holidays.
		if ($mode === 'replace_all' && empty($arguments['rules'])) {
			return array();
		}

		if (empty($arguments['rules']) || ! is_array($arguments['rules'])) {
			return new WP_Error(
				'sln_ai_holiday_empty',
				__('No holiday rules provided.', 'salon-booking-system')
			);
		}

		$out = array();
		foreach ($arguments['rules'] as $rule) {
			if (! is_array($rule)) {
				continue;
			}
			$row = $this->mapRule($rule);
			if (is_wp_error($row)) {
				return $row;
			}
			$out[] = $row;
		}

		if (! $out && $mode !== 'replace_all') {
			return new WP_Error(
				'sln_ai_holiday_empty',
				__('No valid holiday rules provided.', 'salon-booking-system')
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
		$fromDate = isset($rule['from_date']) ? $this->sanitizeDate($rule['from_date']) : null;
		$toDate   = isset($rule['to_date']) ? $this->sanitizeDate($rule['to_date']) : $fromDate;
		if (! $fromDate || ! $toDate) {
			return new WP_Error(
				'sln_ai_holiday_dates',
				__('Each holiday needs from_date and to_date (Y-m-d).', 'salon-booking-system')
			);
		}
		if ($toDate < $fromDate) {
			return new WP_Error(
				'sln_ai_holiday_range',
				__('Holiday end date must be on or after the start date.', 'salon-booking-system')
			);
		}

		$fullDay  = ! empty($rule['full_day']);
		$fromTime = isset($rule['from_time']) ? $this->sanitizeTime($rule['from_time']) : null;
		$toTime   = isset($rule['to_time']) ? $this->sanitizeTime($rule['to_time']) : null;

		if ($fullDay || (! $fromTime && ! $toTime)) {
			$fromTime = '00:00';
			$toTime   = '24:00';
		} else {
			if (! $fromTime || ! $toTime) {
				return new WP_Error(
					'sln_ai_holiday_times',
					__('Partial-day holidays need from_time and to_time.', 'salon-booking-system')
				);
			}
		}

		return array(
			'from_date' => $fromDate,
			'to_date'   => $toDate,
			'from_time' => $fromTime,
			'to_time'   => $toTime,
		);
	}

	/**
	 * @param string $date
	 * @return string|null Y-m-d
	 */
	private function sanitizeDate($date)
	{
		$date = trim((string) $date);
		$dt   = date_create($date);
		if (! $dt) {
			return null;
		}

		return $dt->format('Y-m-d');
	}

	/**
	 * @param string $time
	 * @return string|null
	 */
	private function sanitizeTime($time)
	{
		$time = preg_replace('/\s+/', '', (string) $time);
		if ($time === '24:00') {
			return '24:00';
		}
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
	 * @param array $holidays
	 * @return string
	 */
	public function formatHolidaysSummary($holidays)
	{
		if (! is_array($holidays) || ! $holidays) {
			return __('(none)', 'salon-booking-system');
		}

		$lines = array();
		foreach ($holidays as $row) {
			if (! is_array($row)) {
				continue;
			}
			$fromD = isset($row['from_date']) ? $row['from_date'] : '?';
			$toD   = isset($row['to_date']) ? $row['to_date'] : '?';
			$fromT = isset($row['from_time']) ? $row['from_time'] : '00:00';
			$toT   = isset($row['to_time']) ? $row['to_time'] : '24:00';
			$full  = $this->isFullDayish($row);
			if ($fromD === $toD) {
				$lines[] = $full
					? sprintf(__('%s (all day)', 'salon-booking-system'), $fromD)
					: sprintf('%s %s–%s', $fromD, $fromT, $toT);
			} else {
				$lines[] = $full
					? sprintf(__('%s → %s (all day)', 'salon-booking-system'), $fromD, $toD)
					: sprintf('%s %s → %s %s', $fromD, $fromT, $toD, $toT);
			}
		}

		return $lines ? implode("\n", $lines) : __('(none)', 'salon-booking-system');
	}

	/**
	 * Split incoming holiday rows into already-existing vs new (append safety).
	 *
	 * Treats same/covered date ranges as duplicates even when times differ
	 * slightly (e.g. stored 00:00–23:00 vs new “all day” 00:00–24:00).
	 *
	 * @param array $incoming
	 * @param array $existing
	 * @return array{duplicates: array, novel: array}
	 */
	public function partitionAgainstExisting(array $incoming, array $existing)
	{
		$existingRows = array();
		foreach ($existing as $row) {
			if (is_array($row)) {
				$existingRows[] = $row;
			}
		}

		$duplicates   = array();
		$novel        = array();
		$acceptedNovel = array();

		foreach ($incoming as $row) {
			if (! is_array($row)) {
				continue;
			}
			$matched = null;
			foreach ($existingRows as $ex) {
				if ($this->isRedundantHoliday($ex, $row)) {
					$matched = $ex;
					break;
				}
			}
			if (! $matched) {
				foreach ($acceptedNovel as $ex) {
					if ($this->isRedundantHoliday($ex, $row)) {
						$matched = $ex;
						break;
					}
				}
			}
			if ($matched) {
				$duplicates[] = $matched;
				continue;
			}
			$acceptedNovel[] = $row;
			$novel[]         = $row;
		}

		return array(
			'duplicates' => $duplicates,
			'novel'      => $novel,
		);
	}

	/**
	 * True when $incoming should not be appended because $existing already covers it.
	 *
	 * @param array $existing
	 * @param array $incoming
	 * @return bool
	 */
	public function isRedundantHoliday(array $existing, array $incoming)
	{
		if ($this->ruleSignature($existing) === $this->ruleSignature($incoming)) {
			return true;
		}

		if (! $this->dateRangeContained($incoming, $existing)) {
			return false;
		}

		// Existing full-day (or near full-day) already closes those dates.
		if ($this->isFullDayish($existing)) {
			return true;
		}

		// Both partial windows with identical normalized times.
		return $this->normalizedTimes($existing) === $this->normalizedTimes($incoming);
	}

	/**
	 * @param array $row
	 * @return string
	 */
	public function ruleSignature(array $row)
	{
		$fromDate = isset($row['from_date']) ? (string) $row['from_date'] : '';
		$toDate   = isset($row['to_date']) ? (string) $row['to_date'] : $fromDate;
		$times    = $this->normalizedTimes($row);

		return strtolower($fromDate . '|' . $toDate . '|' . $times[0] . '|' . $times[1]);
	}

	/**
	 * @param array $inner Must be fully inside $outer dates.
	 * @param array $outer
	 * @return bool
	 */
	private function dateRangeContained(array $inner, array $outer)
	{
		$iFrom = isset($inner['from_date']) ? (string) $inner['from_date'] : '';
		$iTo   = isset($inner['to_date']) ? (string) $inner['to_date'] : $iFrom;
		$oFrom = isset($outer['from_date']) ? (string) $outer['from_date'] : '';
		$oTo   = isset($outer['to_date']) ? (string) $outer['to_date'] : $oFrom;
		if ($iFrom === '' || $oFrom === '') {
			return false;
		}

		return $iFrom >= $oFrom && $iTo <= $oTo;
	}

	/**
	 * @param array $row
	 * @return bool
	 */
	private function isFullDayish(array $row)
	{
		$times = $this->normalizedTimes($row);

		return $times[0] === '00:00' && $times[1] === '24:00';
	}

	/**
	 * @param array $row
	 * @return array{0:string,1:string}
	 */
	private function normalizedTimes(array $row)
	{
		$fromTime = isset($row['from_time']) ? (string) $row['from_time'] : '00:00';
		$toTime   = isset($row['to_time']) ? (string) $row['to_time'] : '24:00';
		if ($toTime === '00:00') {
			$toTime = '24:00';
		}
		// Salon UI often stores “all day” as 00:00–23:00 / 23:59.
		if ($fromTime === '00:00' && in_array($toTime, array('23:00', '23:59', '24:00'), true)) {
			return array('00:00', '24:00');
		}

		return array($fromTime, $toTime);
	}
}
