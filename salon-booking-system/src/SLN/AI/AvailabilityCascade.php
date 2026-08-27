<?php

/**
 * Compare salon / shop opening hours with per-assistant and per-service rules.
 *
 * Empty catalog rules inherit salon hours. Custom rules that do not cover a
 * proposed (or current) salon interval would keep those slots unbookable.
 */
class SLN_AI_AvailabilityCascade
{
	const ATTENDANT_META = '_sln_attendant_availabilities';
	const SERVICE_META   = '_sln_service_availabilities';

	/**
	 * @param mixed $availabilities
	 * @return bool
	 */
	/**
	 * Normalize LLM/tool args: closed-day rules (no intervals) become closed_days.
	 *
	 * @param array $arguments
	 * @return array{rules:array,closed_days:int[],preserve_unmentioned:bool}
	 */
	public static function normalizeToolRules(array $arguments)
	{
		$closed = array();
		if (! empty($arguments['closed_days']) && is_array($arguments['closed_days'])) {
			foreach ($arguments['closed_days'] as $day) {
				$day = self::normalizeDayToken($day);
				if ($day) {
					$closed[] = $day;
				}
			}
		}

		$rules = array();
		if (! empty($arguments['rules']) && is_array($arguments['rules'])) {
			foreach ($arguments['rules'] as $rule) {
				if (! is_array($rule)) {
					continue;
				}
				$days = array();
				$rawDays = isset($rule['days']) && is_array($rule['days']) ? $rule['days'] : array();
				foreach ($rawDays as $day) {
					$day = self::normalizeDayToken($day);
					if ($day) {
						$days[] = $day;
					}
				}
				$intervals = self::normalizeIntervalList($rule);
				$isClosed  = ! empty($rule['closed']) || ! empty($rule['is_closed']) || ! empty($rule['closed_day']);
				if ($isClosed || ( $days && ! $intervals )) {
					foreach ($days as $day) {
						$closed[] = $day;
					}
					continue;
				}
				if (! $days || ! $intervals) {
					continue;
				}
				$fromDate = isset($rule['from_date']) ? trim((string) $rule['from_date']) : '';
				$toDate   = isset($rule['to_date']) ? trim((string) $rule['to_date']) : '';
				$hasDates = $fromDate !== '' && $toDate !== '';
				// Cloud models often set always=false on weekly hours. Without dates that
				// is not a date-limited rule — treat it as recurring (toujours).
				$always = $hasDates ? false : true;
				$row    = array(
					'days'      => array_values(array_unique($days)),
					'intervals' => $intervals,
					'always'    => $always,
				);
				if ($hasDates) {
					$row['from_date'] = $fromDate;
					$row['to_date']   = $toDate;
				}
				$rules[] = $row;
			}
		}

		return array(
			'rules'                => $rules,
			'closed_days'          => array_values(array_unique($closed)),
			'preserve_unmentioned' => ! empty($arguments['preserve_unmentioned']),
		);
	}

	/**
	 * @param mixed $token int 1–7 or weekday name
	 * @return int|null
	 */
	public static function normalizeDayToken($token)
	{
		if (is_int($token) || ( is_string($token) && preg_match('/^\d+$/', $token) )) {
			$day = (int) $token;
			return ( $day >= 1 && $day <= 7 ) ? $day : null;
		}
		$key = preg_replace('/[^a-z]/', '', strtolower(SLN_AI_Language::fold((string) $token)));
		$map = array(
			'sun' => 1, 'sunday' => 1, 'sundays' => 1, 'dimanche' => 1, 'domenica' => 1, 'domingo' => 1, 'sonntag' => 1,
			'mon' => 2, 'monday' => 2, 'mondays' => 2, 'lundi' => 2, 'lunedi' => 2, 'lunes' => 2, 'montag' => 2,
			'tue' => 3, 'tues' => 3, 'tuesday' => 3, 'mardi' => 3, 'martedi' => 3, 'martes' => 3, 'dienstag' => 3,
			'wed' => 4, 'wednesday' => 4, 'mercredi' => 4, 'mercoledi' => 4, 'miercoles' => 4, 'mittwoch' => 4,
			'thu' => 5, 'thur' => 5, 'thurs' => 5, 'thursday' => 5, 'jeudi' => 5, 'giovedi' => 5, 'jueves' => 5, 'donnerstag' => 5,
			'fri' => 6, 'friday' => 6, 'vendredi' => 6, 'venerdi' => 6, 'viernes' => 6, 'freitag' => 6,
			'sat' => 7, 'saturday' => 7, 'samedi' => 7, 'sabato' => 7, 'sabado' => 7, 'samstag' => 7,
		);

		return isset($map[ $key ]) ? $map[ $key ] : null;
	}

	/**
	 * @param array $rule
	 * @return array<int,array{from:string,to:string}>
	 */
	public static function normalizeIntervalList(array $rule)
	{
		$raw = array();
		if (! empty($rule['intervals']) && is_array($rule['intervals'])) {
			$raw = $rule['intervals'];
		} elseif (isset($rule['from'], $rule['to'])) {
			if (is_array($rule['from'])) {
				foreach ($rule['from'] as $idx => $from) {
					$raw[] = array(
						'from' => $from,
						'to'   => is_array($rule['to']) && isset($rule['to'][ $idx ]) ? $rule['to'][ $idx ] : $rule['to'],
					);
				}
			} else {
				$raw[] = array('from' => $rule['from'], 'to' => $rule['to']);
			}
		}

		$out = array();
		foreach ($raw as $interval) {
			$parsed = self::normalizeOneInterval($interval);
			if ($parsed) {
				$out[] = $parsed;
			}
			if (count($out) >= 2) {
				break;
			}
		}

		return $out;
	}

	/**
	 * @param mixed $interval
	 * @return array{from:string,to:string}|null
	 */
	private static function normalizeOneInterval($interval)
	{
		$from = '';
		$to   = '';
		if (is_string($interval)) {
			if (preg_match('/(\d{1,2}(?:[:.h]\d{2})?)\s*[–\-—to]+\s*(\d{1,2}(?:[:.h]\d{2})?)/i', $interval, $m)) {
				$from = $m[1];
				$to   = $m[2];
			}
		} elseif (is_array($interval)) {
			$from = isset($interval['from']) ? $interval['from'] : ( isset($interval['start']) ? $interval['start'] : '' );
			$to   = isset($interval['to']) ? $interval['to'] : ( isset($interval['end']) ? $interval['end'] : '' );
		}
		$from = self::normalizeTimeToken($from);
		$to   = self::normalizeTimeToken($to);
		if (! $from || ! $to || ( $from === '00:00' && $to === '00:00' )) {
			return null;
		}

		return array('from' => $from, 'to' => $to);
	}

	/**
	 * @param mixed $time
	 * @return string|null
	 */
	private static function normalizeTimeToken($time)
	{
		$time = strtolower(trim(preg_replace('/\s+/', '', (string) $time)));
		$time = preg_replace('/(\d{1,2})h(\d{2})/', '$1:$2', $time);
		$time = preg_replace('/(\d{1,2})h$/', '$1:00', $time);
		$time = str_replace('.', ':', $time);
		if (! preg_match('/^(\d{1,2})(?::(\d{2}))?$/', $time, $m)) {
			return null;
		}
		$h = (int) $m[1];
		$i = isset($m[2]) ? (int) $m[2] : 0;
		if ($h < 0 || $h > 24 || $i < 0 || $i > 59) {
			return null;
		}

		return sprintf('%02d:%02d', $h, $i);
	}

	public static function hasRealRules($availabilities)
	{
		if (! is_array($availabilities) || ! $availabilities) {
			return false;
		}
		foreach ($availabilities as $row) {
			if (self::rowIntervals($row)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Weekday 1=Sun…7=Sat → list of {from,to}.
	 *
	 * @param mixed $availabilities Mapped rows or stored settings rows.
	 * @return array<int,array<int,array{from:string,to:string}>>
	 */
	public static function weekMap($availabilities)
	{
		$map = array(
			1 => array(),
			2 => array(),
			3 => array(),
			4 => array(),
			5 => array(),
			6 => array(),
			7 => array(),
		);
		if (! is_array($availabilities)) {
			return $map;
		}
		foreach ($availabilities as $row) {
			if (! is_array($row) || empty($row['days']) || ! is_array($row['days'])) {
				continue;
			}
			$intervals = self::rowIntervals($row);
			if (! $intervals) {
				continue;
			}
			foreach ($row['days'] as $day => $on) {
				$day = (int) $day;
				if (! $on || $day < 1 || $day > 7) {
					continue;
				}
				foreach ($intervals as $interval) {
					$map[ $day ][] = $interval;
				}
			}
		}

		return $map;
	}

	/**
	 * Merge proposed day rules with current hours for unmentioned weekdays.
	 *
	 * @param array $current  Current salon availabilities.
	 * @param array $proposed Mapped proposed rules.
	 * @param int[] $closedDays Explicitly closed weekdays.
	 * @param bool  $preserveUnmentioned
	 * @return array
	 */
	public static function mergeProposed(array $current, array $proposed, array $closedDays, $preserveUnmentioned)
	{
		$closed = array();
		foreach ($closedDays as $day) {
			$day = (int) $day;
			if ($day >= 1 && $day <= 7) {
				$closed[ $day ] = true;
			}
		}
		if (! $preserveUnmentioned && ! $closed) {
			return $proposed;
		}

		$currentMap  = self::weekMap($current);
		$proposedMap = self::weekMap($proposed);
		$mentioned   = array();
		foreach ($proposedMap as $day => $intervals) {
			if ($intervals) {
				$mentioned[ $day ] = true;
			}
		}

		$final = array();
		for ($day = 1; $day <= 7; $day++) {
			if (! empty($closed[ $day ])) {
				continue;
			}
			if (! empty($mentioned[ $day ])) {
				$final[ $day ] = $proposedMap[ $day ];
			} elseif ($preserveUnmentioned && ! empty($currentMap[ $day ])) {
				$final[ $day ] = $currentMap[ $day ];
			}
		}

		$merged = self::rulesFromWeekMap($final);

		return $merged ? $merged : $proposed;
	}

	/**
	 * @param array<int,array<int,array{from:string,to:string}>> $weekMap
	 * @return array
	 */
	public static function rulesFromWeekMap(array $weekMap)
	{
		$groups = array();
		foreach ($weekMap as $day => $intervals) {
			if (! $intervals) {
				continue;
			}
			$key = self::intervalsKey($intervals);
			if (! isset($groups[ $key ])) {
				$groups[ $key ] = array(
					'days'      => array(),
					'intervals' => $intervals,
				);
			}
			$groups[ $key ]['days'][ (int) $day ] = 1;
		}

		$out = array();
		$n   = 0;
		foreach ($groups as $group) {
			$n++;
			$from = array('00:00', '00:00');
			$to   = array('00:00', '00:00');
			$i    = 0;
			foreach (array_slice($group['intervals'], 0, 2) as $interval) {
				$from[ $i ] = $interval['from'];
				$to[ $i ]   = $interval['to'];
				$i++;
			}
			$out[ $n ] = array(
				'days'                 => $group['days'],
				'from'                 => $from,
				'to'                   => $to,
				'always'               => true,
				'disable_second_shift' => $i < 2,
			);
		}

		return $out;
	}

	/**
	 * Tool-style rules[] from mapped/stored availability rows.
	 *
	 * @param array $mapped
	 * @return array
	 */
	public static function toToolRules($mapped)
	{
		$rules = array();
		if (! is_array($mapped)) {
			return $rules;
		}
		foreach ($mapped as $row) {
			if (! is_array($row)) {
				continue;
			}
			$days = array();
			if (! empty($row['days']) && is_array($row['days'])) {
				foreach ($row['days'] as $day => $on) {
					if ($on) {
						$days[] = (int) $day;
					}
				}
			}
			$intervals = self::rowIntervals($row);
			if (! $days || ! $intervals) {
				continue;
			}
			$rules[] = array(
				'days'      => $days,
				'intervals' => $intervals,
				'always'    => ! isset($row['always']) || $row['always'],
			);
		}

		return $rules;
	}

	/**
	 * @param array $row
	 * @return array<int,array{from:string,to:string}>
	 */
	public static function rowIntervals($row)
	{
		if (! is_array($row)) {
			return array();
		}
		$out = array();
		if (! empty($row['intervals']) && is_array($row['intervals'])) {
			foreach ($row['intervals'] as $interval) {
				if (! is_array($interval) || empty($interval['from']) || empty($interval['to'])) {
					continue;
				}
				if ($interval['from'] === '00:00' && $interval['to'] === '00:00') {
					continue;
				}
				$out[] = array(
					'from' => (string) $interval['from'],
					'to'   => (string) $interval['to'],
				);
			}

			return $out;
		}
		if (empty($row['from']) || ! is_array($row['from'])) {
			return array();
		}
		foreach ($row['from'] as $idx => $from) {
			$to = isset($row['to'][ $idx ]) ? $row['to'][ $idx ] : '';
			if ($from === '00:00' && $to === '00:00') {
				continue;
			}
			if ($from === '' || $to === '') {
				continue;
			}
			$out[] = array(
				'from' => (string) $from,
				'to'   => (string) $to,
			);
		}

		return $out;
	}

	/**
	 * Issues when entity custom hours are compared to salon hours.
	 *
	 * @param array $entityMap
	 * @param array $salonMap
	 * @return string[] missing_day:N, missing_interval:N, extra_day:N
	 */
	public static function diffIssues(array $entityMap, array $salonMap)
	{
		$issues = array();
		for ($day = 1; $day <= 7; $day++) {
			$salon = isset($salonMap[ $day ]) ? $salonMap[ $day ] : array();
			$ent   = isset($entityMap[ $day ]) ? $entityMap[ $day ] : array();
			if ($salon && ! $ent) {
				$issues[] = 'missing_day:' . $day;
				continue;
			}
			if ($salon && $ent) {
				foreach ($salon as $need) {
					if (! self::intervalCovered($need, $ent)) {
						$issues[] = 'missing_interval:' . $day;
						break;
					}
				}
			}
			if ($ent && ! $salon) {
				$issues[] = 'extra_day:' . $day;
			}
		}

		return $issues;
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $salonMapped Proposed or current salon hours.
	 * @return array
	 */
	public static function analyze(SLN_Plugin $plugin, array $salonMapped)
	{
		$hours     = new SLN_AI_Tools_SetAvailabilities($plugin);
		$salonMap  = self::weekMap($salonMapped);
		$assistant = self::analyzeKind(
			$plugin,
			SLN_Plugin::POST_TYPE_ATTENDANT,
			self::ATTENDANT_META,
			$salonMap,
			$salonMapped,
			$hours
		);
		$service   = self::analyzeKind(
			$plugin,
			SLN_Plugin::POST_TYPE_SERVICE,
			self::SERVICE_META,
			$salonMap,
			$salonMapped,
			$hours
		);

		return array(
			'assistants' => $assistant,
			'services'   => $service,
		);
	}

	/**
	 * @param array  $analysis From analyze().
	 * @param string $mode     current|desired|apply
	 * @return string
	 */
	public static function formatAnalysisSummary(array $analysis, $mode = 'apply')
	{
		$lines   = array();
		$lines[] = ( $mode === 'desired' )
			? __('Compared with the desired opening hours:', 'salon-booking-system')
			: __('Shop / salon, assistant, and service hours:', 'salon-booking-system');
		$lines[] = self::formatKindBlock(
			__('Assistants', 'salon-booking-system'),
			isset($analysis['assistants']) ? $analysis['assistants'] : array(),
			$mode
		);
		$lines[] = '';
		$lines[] = self::formatKindBlock(
			__('Services', 'salon-booking-system'),
			isset($analysis['services']) ? $analysis['services'] : array(),
			$mode
		);

		return trim(implode("\n", $lines));
	}

	/**
	 * Catalog updates needed so the proposed salon hours are actually bookable.
	 *
	 * @param array $analysis
	 * @return array{assistants:array,services:array}
	 */
	public static function alignmentWrites(array $analysis)
	{
		return array(
			'assistants' => isset($analysis['assistants']['align']) ? $analysis['assistants']['align'] : array(),
			'services'   => isset($analysis['services']['align']) ? $analysis['services']['align'] : array(),
		);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param string     $postType
	 * @param string     $metaKey
	 * @param array      $salonMap
	 * @param array      $salonMapped
	 * @param SLN_AI_Tools_SetAvailabilities $hours
	 * @return array
	 */
	private static function analyzeKind(
		SLN_Plugin $plugin,
		$postType,
		$metaKey,
		array $salonMap,
		array $salonMapped,
		SLN_AI_Tools_SetAvailabilities $hours
	) {
		$inherit = 0;
		$ok      = 0;
		$align   = array();
		$listed  = array();

		$posts = get_posts(
			array(
				'post_type'      => $postType,
				'post_status'    => array('publish', 'private'),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ($posts as $post) {
			$raw = get_post_meta($post->ID, $metaKey, true);
			if (! is_array($raw)) {
				$raw = array();
			}
			$row = array(
				'id'              => (int) $post->ID,
				'name'            => (string) $post->post_title,
				'current_summary' => $hours->formatAvailabilitiesSummary($raw),
				'has_rules'       => self::hasRealRules($raw),
			);
			if (! $row['has_rules']) {
				$inherit++;
				$row['status'] = 'inherit';
				$listed[]      = $row;
				continue;
			}
			$issues = self::diffIssues(self::weekMap($raw), $salonMap);
			if (! $issues) {
				$ok++;
				$row['status'] = 'ok';
				$listed[]      = $row;
				continue;
			}
			$row['status'] = 'align';
			$row['issues'] = $issues;
			$row['rules']  = self::toToolRules($salonMapped);
			$align[]       = $row;
			$listed[]      = $row;
		}

		return array(
			'inherit' => $inherit,
			'ok'      => $ok,
			'align'   => $align,
			'listed'  => $listed,
		);
	}

	/**
	 * @param string $label
	 * @param array  $kind
	 * @return string
	 */
	private static function formatKindBlock($label, array $kind, $mode = 'apply')
	{
		$listed  = isset($kind['listed']) && is_array($kind['listed']) ? $kind['listed'] : array();
		$inherit = isset($kind['inherit']) ? (int) $kind['inherit'] : 0;
		$ok      = isset($kind['ok']) ? (int) $kind['ok'] : 0;
		$align   = isset($kind['align']) && is_array($kind['align']) ? $kind['align'] : array();
		$desired = ( $mode === 'desired' );

		$lines   = array();
		$lines[] = $label . ':';
		if (! $listed) {
			$lines[] = '  ' . __('(none)', 'salon-booking-system');

			return implode("\n", $lines);
		}
		if ($inherit) {
			$lines[] = '  ' . sprintf(
				/* translators: %d: count */
				$desired
					? _n(
						'%d has no custom rules — would inherit the desired salon hours.',
						'%d have no custom rules — would inherit the desired salon hours.',
						$inherit,
						'salon-booking-system'
					)
					: _n(
						'%d inherits salon hours (no custom rules).',
						'%d inherit salon hours (no custom rules).',
						$inherit,
						'salon-booking-system'
					),
				$inherit
			);
		}
		if ($ok) {
			$lines[] = '  ' . sprintf(
				/* translators: %d: count */
				$desired
					? _n(
						'%d custom schedule already covers the desired hours.',
						'%d custom schedules already cover the desired hours.',
						$ok,
						'salon-booking-system'
					)
					: _n(
						'%d custom schedule already covers these salon hours.',
						'%d custom schedules already cover these salon hours.',
						$ok,
						'salon-booking-system'
					),
				$ok
			);
		}
		if ($align) {
			$lines[] = '  ' . (
				$desired
					? __(
						'These custom rules do not match the desired opening hours (they would block those slots):',
						'salon-booking-system'
					)
					: __(
						'These custom rules would block or contradict the salon hours — they will be aligned to the same timetable:',
						'salon-booking-system'
					)
			);
			foreach ($align as $row) {
				$lines[] = sprintf(
					'  • #%d %s — %s',
					isset($row['id']) ? (int) $row['id'] : 0,
					isset($row['name']) ? $row['name'] : '',
					self::formatIssues(
						isset($row['issues']) && is_array($row['issues']) ? $row['issues'] : array()
					)
				);
				if (! empty($row['current_summary'])) {
					$lines[] = '    ' . __('Current:', 'salon-booking-system') . ' ' . str_replace("\n", ' | ', (string) $row['current_summary']);
				}
			}
		}

		return implode("\n", $lines);
	}

	/**
	 * @param string[] $issues
	 * @return string
	 */
	public static function formatIssues(array $issues)
	{
		$dayNames = array(
			1 => __('Sunday', 'salon-booking-system'),
			2 => __('Monday', 'salon-booking-system'),
			3 => __('Tuesday', 'salon-booking-system'),
			4 => __('Wednesday', 'salon-booking-system'),
			5 => __('Thursday', 'salon-booking-system'),
			6 => __('Friday', 'salon-booking-system'),
			7 => __('Saturday', 'salon-booking-system'),
		);
		$bits = array();
		foreach ($issues as $issue) {
			$parts = explode(':', (string) $issue, 2);
			$code  = $parts[0];
			$day   = isset($parts[1]) ? (int) $parts[1] : 0;
			$label = isset($dayNames[ $day ]) ? $dayNames[ $day ] : (string) $day;
			if ($code === 'missing_day') {
				$bits[] = sprintf(
					/* translators: %s: weekday */
					__('closed on %s while the salon is open', 'salon-booking-system'),
					$label
				);
			} elseif ($code === 'missing_interval') {
				$bits[] = sprintf(
					/* translators: %s: weekday */
					__('hours on %s do not cover the salon intervals', 'salon-booking-system'),
					$label
				);
			} elseif ($code === 'extra_day') {
				$bits[] = sprintf(
					/* translators: %s: weekday */
					__('still open on %s while the salon is closed', 'salon-booking-system'),
					$label
				);
			}
		}

		return $bits ? implode('; ', $bits) : __('needs alignment', 'salon-booking-system');
	}

	/**
	 * Compact lines for the LLM context pack.
	 *
	 * @param SLN_Plugin $plugin
	 * @param string     $postType
	 * @param string     $metaKey
	 * @param int        $limit
	 * @return string[]
	 */
	public static function contextLines(SLN_Plugin $plugin, $postType, $metaKey, $limit = 12)
	{
		$hours = new SLN_AI_Tools_SetAvailabilities($plugin);
		$posts = get_posts(
			array(
				'post_type'      => $postType,
				'post_status'    => array('publish', 'private'),
				'posts_per_page' => $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ($posts as $post) {
			$raw = get_post_meta($post->ID, $metaKey, true);
			if (! self::hasRealRules($raw)) {
				continue;
			}
			$out[] = sprintf(
				'#%d %s: %s',
				(int) $post->ID,
				$post->post_title,
				str_replace("\n", ' ; ', $hours->formatAvailabilitiesSummary($raw))
			);
		}

		return $out;
	}

	/**
	 * @param array{from:string,to:string}             $need
	 * @param array<int,array{from:string,to:string}> $haystack
	 * @return bool
	 */
	private static function intervalCovered(array $need, array $haystack)
	{
		$from = self::toMinutes($need['from']);
		$to   = self::toMinutes($need['to']);
		if ($from === null || $to === null) {
			return false;
		}
		foreach ($haystack as $interval) {
			$hFrom = self::toMinutes($interval['from']);
			$hTo   = self::toMinutes($interval['to']);
			if ($hFrom === null || $hTo === null) {
				continue;
			}
			if ($hFrom <= $from && $hTo >= $to) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int,array{from:string,to:string}> $intervals
	 * @return string
	 */
	private static function intervalsKey(array $intervals)
	{
		$bits = array();
		foreach ($intervals as $interval) {
			$bits[] = $interval['from'] . '-' . $interval['to'];
		}

		return implode('|', $bits);
	}

	/**
	 * @param string $time
	 * @return int|null
	 */
	private static function toMinutes($time)
	{
		if (! preg_match('/^(\d{1,2}):(\d{2})$/', (string) $time, $m)) {
			return null;
		}

		return ( (int) $m[1] * 60 ) + (int) $m[2];
	}
}
