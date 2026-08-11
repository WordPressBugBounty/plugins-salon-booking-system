<?php

use Salon\Util\Date;

/**
 * Guidance-only: diagnose why a front-end booking slot may be unavailable.
 */
class SLN_AI_Tools_ExplainUnavailableSlot extends SLN_AI_Tools_Abstract
{
	/** @var string Reply language from the merchant message (en|it|es|fr|de|pt). */
	private $replyLang = 'en';

	/** @var string Asked start time H:i (for clearer capacity wording). */
	private $askedTime = '';

	public function getName()
	{
		return 'explain_unavailable_slot';
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
		$bound = SLN_AI_Multishop::bindShop($arguments, true);
		if (is_wp_error($bound)) {
			return $bound;
		}
		if (empty($bound['ok'])) {
			return $bound;
		}
		$shop = isset($bound['shop']) ? $bound['shop'] : null;

		return SLN_AI_Multishop::withShop(
			$shop,
			function ($shop) use ($arguments) {
				return $this->previewInScope($arguments, $shop);
			}
		);
	}

	/**
	 * @param array       $arguments
	 * @param object|null $shop
	 * @return array|WP_Error
	 */
	private function previewInScope(array $arguments, $shop)
	{
		$this->replyLang = SLN_AI_Language::detect(
			isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : ''
		);

		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$dateStr         = $mapped['date'];
		$timeStr         = $mapped['time'];
		$this->askedTime = $timeStr ? (string) $timeStr : '';
		$service         = $mapped['service'];
		$assistant       = $mapped['assistant'];
		$missing         = $mapped['missing'];
		$notFound        = $mapped['not_found'];
		$focus           = $mapped['focus'];
		$shopArgs        = SLN_AI_Multishop::argsFromShop($shop);

		// Not enough to diagnose usefully → ask clarifying questions first (no weak “nothing blocks” answer).
		if ($this->shouldAskClarificationFirst($mapped)) {
			return array(
				'ok'        => true,
				'guidance'  => true,
				'summary'   => $this->formatClarificationSummary($mapped, $shop),
				'arguments' => array(
					'date'           => $dateStr,
					'time'           => $timeStr,
					'service_id'     => $service ? (int) $service->getId() : 0,
					'assistant_id'   => $assistant ? (int) $assistant->getId() : 0,
					'service_name'   => $service ? $service->getName() : '',
					'assistant_name' => $assistant ? $assistant->getName() : '',
					'shop_id'        => $shopArgs['shop_id'],
					'shop_name'      => $shopArgs['shop_name'],
					'needs_clarification' => true,
				),
				'tool'      => $this->getName(),
				'tier'      => 'guidance',
				'checks'    => array(),
				'missing'   => $missing,
			);
		}

		$checks = array();
		$checks[] = $this->checkBookingEnabled();
		if ($focus !== 'service') {
			$checks[] = $this->checkAssistantSelectionMode();
		}

		$dt       = null;
		$duration = $this->defaultDuration();
		if ($service) {
			$duration = $service->getDuration();
			if (! $duration instanceof DateTime) {
				$duration = new DateTime('1970-01-01 ' . (string) $duration);
			}
		}

		if ($dateStr) {
			$useTime = $timeStr ? $timeStr : '12:00';
			$dt      = new SLN_DateTime($dateStr . ' ' . $useTime, SLN_DateTime::getWpTimezone());
			$checks[] = $this->checkHoursBefore($dt);
			$checks[] = $this->checkSalonHolidays($dt);
			if ($timeStr) {
				$checks[] = $this->checkSalonHours($dt, $duration);
				$checks[] = $this->checkOfferedTimes($dt, $timeStr);
			} else {
				$checks[] = $this->checkSalonDayOpen($dt);
				if ($focus !== 'assistant') {
					$checks[] = $this->checkOfferedTimesForDay($dt);
				}
			}
		}

		if ($service) {
			if ($dt) {
				if (! $timeStr) {
					$checks[] = $this->checkServiceDayOpen($service, $dt);
				}
				$checks[] = $this->checkServiceHours($service, $dt);
			}
			$checks[] = $this->checkServiceHasEligibleAssistants($service);
			if ($service && $dt && $timeStr && ! $assistant) {
				$checks[] = $this->checkEngineServiceOnly($service, $dt, $duration);
			}
		}
		if ($service && $assistant) {
			$checks[] = $this->checkAssistantAssigned($service, $assistant);
		}
		if ($assistant && $dt) {
			if (! $timeStr) {
				$checks[] = $this->checkAssistantDayOpen($assistant, $dt, $service);
			}
			$checks[] = $this->checkAssistantHours($assistant, $dt, $duration, $service);
			$checks[] = $this->checkAssistantHolidays($assistant, $dt);
		}
		if ($service && $assistant && $dt && $timeStr) {
			$checks[] = $this->checkEngine($service, $assistant, $dt, $duration);
		}

		$failed  = array();
		foreach ($checks as $c) {
			if (empty($c['pass'])) {
				$failed[] = $c;
			}
		}
		$primary = $this->primaryFailures($failed);
		$summary = $this->formatCompactSummary(
			array(
				'service'   => $service,
				'assistant' => $assistant,
				'date'      => $dateStr,
				'time'      => $timeStr,
				'focus'     => $focus,
				'shop'      => $shop,
				'checks'    => $checks,
				'failed'    => $primary,
				'missing'   => $missing,
				'not_found' => $notFound,
			)
		);

		$unitPerHour = ( $service && method_exists($service, 'getUnitPerHour') ) ? (int) $service->getUnitPerHour() : 0;
		$capacity    = $this->extractCapacityFromChecks($primary);
		if (! $capacity && $service) {
			$capacity = $this->buildCapacityContext($service, $assistant, '');
		}

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => $summary,
			'arguments' => array(
				'date'           => $dateStr,
				'time'           => $timeStr,
				'service_id'     => $service ? (int) $service->getId() : 0,
				'assistant_id'   => $assistant ? (int) $assistant->getId() : 0,
				'service_name'   => $service ? $service->getName() : '',
				'assistant_name' => $assistant ? $assistant->getName() : '',
				'shop_id'        => $shopArgs['shop_id'],
				'shop_name'      => $shopArgs['shop_name'],
			),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'checks'    => $checks,
			'missing'   => $missing,
			'diagnosis' => array(
				'kind'                 => $primary ? 'blocked' : 'clear',
				'failed_ids'           => array_values(
					array_filter(
						array_map(
							function ($c) {
								return isset($c['id']) ? $c['id'] : '';
							},
							$primary
						)
					)
				),
				'reasons'              => array_values(
					array_filter(
						array_map(
							array($this, 'formatFailureHuman'),
							$primary
						)
					)
				),
				'unit_per_hour'        => $unitPerHour,
				'units_per_session'    => $unitPerHour,
				'customers_per_session'=> (int) $this->plugin->getSettings()->get('parallels_hour'),
				'capacity'             => $capacity ? $capacity : array(),
				'summary'              => $summary,
			),
		);
	}

	/**
	 * True when a diagnosis would be too weak — ask the merchant first.
	 *
	 * @param array $mapped From mapArguments().
	 * @return bool
	 */
	private function shouldAskClarificationFirst(array $mapped)
	{
		$dateStr   = ! empty($mapped['date']);
		$timeStr   = ! empty($mapped['time']);
		$service   = ! empty($mapped['service']);
		$assistant = ! empty($mapped['assistant']);
		$notFound  = ! empty($mapped['not_found']);
		$focus     = isset($mapped['focus']) ? $mapped['focus'] : '';

		// Failed name resolution → ask to correct / restate.
		if ($notFound) {
			return true;
		}
		// Need a date for any availability diagnosis.
		if (! $dateStr) {
			return true;
		}
		// Need at least a service or an assistant to scope the check.
		if (! $service && ! $assistant) {
			return true;
		}
		// Vague “availability problem” with only a date → ask what to check.
		if (! $service && ! $assistant && ! $timeStr) {
			return true;
		}
		// “Why isn’t this start time bookable?” with no service/assistant and no time → ask.
		if (! $timeStr && ! $service && ! $assistant && $focus === 'slot') {
			return true;
		}

		return false;
	}

	/**
	 * Clarifying questions as the main reply (no speculative diagnosis).
	 *
	 * @param array       $mapped
	 * @param object|null $shop
	 * @return string
	 */
	private function formatClarificationSummary(array $mapped, $shop = null)
	{
		$parts     = array();
		$notFound  = isset($mapped['not_found']) && is_array($mapped['not_found']) ? $mapped['not_found'] : array();
		$missing   = isset($mapped['missing']) && is_array($mapped['missing']) ? $mapped['missing'] : array();
		$have      = array();

		if (! empty($mapped['date'])) {
			$have[] = sprintf(
				/* translators: %s: date */
				__('date: %s', 'salon-booking-system'),
				$mapped['date']
			);
		}
		if (! empty($mapped['time'])) {
			$have[] = sprintf(
				/* translators: %s: time */
				__('time: %s', 'salon-booking-system'),
				$mapped['time']
			);
		}
		if (! empty($mapped['service'])) {
			$have[] = sprintf(
				/* translators: %s: service name */
				__('service: %s', 'salon-booking-system'),
				$mapped['service']->getName()
			);
		}
		if (! empty($mapped['assistant'])) {
			$have[] = sprintf(
				/* translators: %s: assistant name */
				__('assistant: %s', 'salon-booking-system'),
				$mapped['assistant']->getName()
			);
		}
		if ($shop && method_exists($shop, 'getName')) {
			$have[] = sprintf(
				/* translators: %s: shop name */
				__('shop: %s', 'salon-booking-system'),
				$shop->getName()
			);
		}

		if ($notFound) {
			$parts[] = implode(' ', $notFound);
		}

		$parts[] = __('I can check this properly, but I still need a few details:', 'salon-booking-system');

		$questions = array();
		if (! $mapped['date']) {
			$questions[] = __('Which date? (Y-m-d or e.g. 27 August 2026)', 'salon-booking-system');
		}
		if (! $mapped['service'] && ! $mapped['assistant']) {
			$questions[] = __('Which service and/or assistant is affected?', 'salon-booking-system');
		} else {
			if (! $mapped['service']) {
				$questions[] = __('Which service? (name or id)', 'salon-booking-system');
			}
			if (! $mapped['assistant'] && ( empty($mapped['focus']) || $mapped['focus'] === 'slot' )) {
				$questions[] = __('Which assistant/operator? (if the issue is for a specific one)', 'salon-booking-system');
			}
		}
		if (! $mapped['time'] && ( empty($mapped['focus']) || $mapped['focus'] === 'slot' )) {
			$questions[] = __('Which start time did you expect to be available? (e.g. 14:30)', 'salon-booking-system');
		}
		// Fallback to mapped missing labels if questions empty.
		if (! $questions && $missing) {
			foreach ($missing as $field) {
				$questions[] = sprintf(
					/* translators: %s: field name */
					__('Please share the %s.', 'salon-booking-system'),
					$field
				);
			}
		}

		foreach ($questions as $i => $q) {
			$parts[] = sprintf('%d) %s', $i + 1, $q);
		}

		if ($have) {
			$parts[] = sprintf(
				/* translators: %s: already known facts */
				__('I already have: %s.', 'salon-booking-system'),
				implode(', ', $have)
			);
		}

		return implode("\n\n", $parts);
	}

	/**
	 * Keep root causes; drop cascading symptoms (empty times / engine) when schedule already fails.
	 *
	 * @param array $failed
	 * @return array
	 */
	private function primaryFailures(array $failed)
	{
		if (! $failed) {
			return array();
		}

		$ids = array();
		foreach ($failed as $c) {
			if (! empty($c['id'])) {
				$ids[ $c['id'] ] = true;
			}
		}

		$scheduleBlocked = ! empty($ids['hours_before'])
			|| ! empty($ids['salon_hours'])
			|| ! empty($ids['salon_day'])
			|| ! empty($ids['salon_holidays'])
			|| ! empty($ids['service_hours'])
			|| ! empty($ids['service_day'])
			|| ! empty($ids['assistant_hours'])
			|| ! empty($ids['assistant_day'])
			|| ! empty($ids['assistant_holidays']);

		$symptomIds = array(
			'offered_times'  => true,
			'offered_day'    => true,
			'engine'         => true,
			'engine_service' => true,
		);

		$out = array();
		foreach ($failed as $c) {
			$id = isset($c['id']) ? $c['id'] : '';
			if ($scheduleBlocked && isset($symptomIds[ $id ])) {
				continue;
			}
			$out[] = $c;
		}

		// Cap length: booking window + up to 2 more root causes is enough for chat.
		return array_slice($out, 0, 3);
	}

	/**
	 * Short merchant-facing answer in conversational prose.
	 *
	 * @param array $ctx
	 * @return string
	 */
	private function formatCompactSummary(array $ctx)
	{
		$service   = isset($ctx['service']) ? $ctx['service'] : null;
		$assistant = isset($ctx['assistant']) ? $ctx['assistant'] : null;
		$dateStr   = isset($ctx['date']) ? $ctx['date'] : '';
		$timeStr   = isset($ctx['time']) ? $ctx['time'] : '';
		$checks    = isset($ctx['checks']) && is_array($ctx['checks']) ? $ctx['checks'] : array();
		$failed    = isset($ctx['failed']) && is_array($ctx['failed']) ? $ctx['failed'] : array();
		$missing   = isset($ctx['missing']) && is_array($ctx['missing']) ? $ctx['missing'] : array();
		$notFound  = isset($ctx['not_found']) && is_array($ctx['not_found']) ? $ctx['not_found'] : array();
		$shop      = isset($ctx['shop']) ? $ctx['shop'] : null;

		$subject = $this->formatSubjectPhrase($service, $assistant, $dateStr, $timeStr, $shop);
		$parts   = array();

		if ($notFound) {
			$parts[] = implode(' ', $notFound);
		}

		$lang = $this->replyLang ? $this->replyLang : 'en';

		if (! $checks) {
			$parts[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_need_more',
				__('Could you share a bit more — for example the date, time, and service — so I can check properly?', 'salon-booking-system')
			);
		} elseif (! $failed) {
			$parts[] = $subject
				? SLN_AI_Language::phrase(
					$lang,
					'unavailable_ok_subject',
					__('I looked at %s, and nothing in your current rules clearly blocks it.', 'salon-booking-system'),
					array($subject)
				)
				: SLN_AI_Language::phrase(
					$lang,
					'unavailable_ok',
					__('Nothing in your current rules clearly blocks this with the details you gave.', 'salon-booking-system')
				);
			$parts[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_refresh',
				__('If customers still can’t see it on the booking form, try a refresh or update Settings once to clear the booking cache.', 'salon-booking-system')
			);
		} else {
			$reasons = array();
			foreach ($failed as $c) {
				$reason = $this->formatFailureHuman($c);
				if ($reason !== '') {
					$reasons[] = $reason;
				}
			}
			$count = count($reasons);
			if ($count === 1) {
				$parts[] = $subject
					? SLN_AI_Language::phrase(
						$lang,
						'unavailable_why_one',
						__('Here’s why %1$s isn’t available: %2$s', 'salon-booking-system'),
						array($subject, $reasons[0])
					)
					: SLN_AI_Language::phrase(
						$lang,
						'unavailable_why_one_bare',
						__('Here’s why that isn’t available: %s', 'salon-booking-system'),
						array($reasons[0])
					);
			} elseif ($count === 2) {
				$parts[] = $subject
					? SLN_AI_Language::phrase(
						$lang,
						'unavailable_why_two',
						__('Here’s why %1$s isn’t available. %2$s Also, %3$s', 'salon-booking-system'),
						array($subject, $reasons[0], lcfirst($reasons[1]))
					)
					: SLN_AI_Language::phrase(
						$lang,
						'unavailable_why_two_bare',
						__('Here’s why that isn’t available. %1$s Also, %2$s', 'salon-booking-system'),
						array($reasons[0], lcfirst($reasons[1]))
					);
			} else {
				$parts[] = $subject
					? SLN_AI_Language::phrase(
						$lang,
						'unavailable_why_many',
						__('Here’s why %s isn’t available:', 'salon-booking-system'),
						array($subject)
					)
					: SLN_AI_Language::phrase(
						$lang,
						'unavailable_why_many_bare',
						__('Here’s why that isn’t available:', 'salon-booking-system')
					);
				foreach ($reasons as $i => $reason) {
					$parts[] = sprintf('%d) %s', $i + 1, $reason);
				}
			}
		}

		if ($missing) {
			$parts[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_also_share',
				__('To confirm further, please also share: %s.', 'salon-booking-system'),
				array(implode(', ', $missing))
			);
		}

		return implode("\n\n", array_filter($parts));
	}

	/**
	 * Conversational subject, e.g. "Haircut on 25 December 2026 at 10:00 with Anna".
	 *
	 * @param object|null $service
	 * @param object|null $assistant
	 * @param string      $dateStr
	 * @param string      $timeStr
	 * @param object|null $shop
	 * @return string
	 */
	private function formatSubjectPhrase($service, $assistant, $dateStr, $timeStr, $shop)
	{
		$lang = $this->replyLang ? $this->replyLang : 'en';
		$when = $this->formatHumanWhen($dateStr, $timeStr);
		if ($service && $when && $assistant) {
			$phrase = SLN_AI_Language::phrase(
				$lang,
				'unavailable_subject_with',
				__('%1$s on %2$s with %3$s', 'salon-booking-system'),
				array($service->getName(), $when, $assistant->getName())
			);
		} elseif ($service && $when) {
			$phrase = SLN_AI_Language::phrase(
				$lang,
				'unavailable_subject_on',
				__('%1$s on %2$s', 'salon-booking-system'),
				array($service->getName(), $when)
			);
		} elseif ($assistant && $when) {
			$phrase = SLN_AI_Language::phrase(
				$lang,
				'unavailable_subject_on',
				__('%1$s on %2$s', 'salon-booking-system'),
				array($assistant->getName(), $when)
			);
		} elseif ($when) {
			$phrase = $when;
		} elseif ($service) {
			$phrase = $service->getName();
		} elseif ($assistant) {
			$phrase = $assistant->getName();
		} else {
			$phrase = '';
		}

		$shopLabel = SLN_AI_Multishop::scopeLabel($shop);
		if ($phrase !== '' && $shopLabel !== '') {
			$phrase .= ' (' . $shopLabel . ')';
		}

		return $phrase;
	}

	/**
	 * @param string $dateStr Y-m-d
	 * @param string $timeStr H:i
	 * @return string
	 */
	private function formatHumanWhen($dateStr, $timeStr)
	{
		if (! $dateStr) {
			return $timeStr ? $timeStr : '';
		}

		$lang = $this->replyLang ? $this->replyLang : 'en';
		$tz   = class_exists('SLN_DateTime') ? SLN_DateTime::getWpTimezone() : wp_timezone();

		try {
			$dt = $timeStr
				? new DateTimeImmutable($dateStr . ' ' . $timeStr, $tz)
				: new DateTimeImmutable($dateStr . ' 12:00:00', $tz);
		} catch (Exception $e) {
			return trim($dateStr . ' ' . $timeStr);
		}

		if ($timeStr) {
			$fmt = SLN_AI_Language::phrase(
				$lang,
				'unavailable_when_at',
				__('j F Y \a\t H:i', 'salon-booking-system')
			);

			return wp_date($fmt, $dt->getTimestamp(), $tz);
		}

		return wp_date(__('j F Y', 'salon-booking-system'), $dt->getTimestamp(), $tz);
	}

	/**
	 * @param array $checks
	 * @return array|null
	 */
	private function extractCapacityFromChecks(array $checks)
	{
		foreach ($checks as $c) {
			if (! empty($c['capacity']) && is_array($c['capacity'])) {
				return $c['capacity'];
			}
		}

		return null;
	}

	/**
	 * Map engine error text + live settings into merchant-facing capacity context.
	 *
	 * @param object|null $service
	 * @param object|null $assistant
	 * @param string      $errorText
	 * @return array
	 */
	private function buildCapacityContext($service, $assistant, $errorText)
	{
		$settings   = $this->plugin->getSettings();
		$parallels  = (int) $settings->get('parallels_hour');
		$units      = ( $service && method_exists($service, 'getUnitPerHour') ) ? (int) $service->getUnitPerHour() : 0;
		$multi      = null;
		if ($assistant && method_exists($assistant, 'canMultipleCustomers')) {
			$multi = (bool) $assistant->canMultipleCustomers();
		}

		$busyTime = null;
		if (preg_match('/\b(\d{1,2}):(\d{2})\b/', (string) $errorText, $m)) {
			$busyTime = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
		}

		$folded = strtolower(SLN_AI_Language::fold((string) $errorText));
		$kind   = 'capacity';
		if (preg_match('/\b(limit of parallels|parallels bookings|limite.*prenotazioni parallele)\b/i', $folded)) {
			$kind = 'customers_per_session';
		} elseif (preg_match('/\b(currently full|attualmente completo|service for)\b/i', $folded)) {
			$kind = 'units_per_session';
		} elseif (preg_match('/\b(assistant is full|this assistant is full|assistente.*pieno|assistente.*completo)\b/i', $folded)) {
			$kind = ( $multi === false ) ? 'assistant_single' : 'assistant_multi';
		} elseif ($assistant && $errorText === '') {
			$kind = ( $multi === false ) ? 'assistant_single' : 'assistant_multi';
		} elseif ($service) {
			$kind = 'units_per_session';
		}

		$serviceId = ( $service && method_exists($service, 'getId') ) ? (int) $service->getId() : 0;
		$asstId    = ( $assistant && method_exists($assistant, 'getId') ) ? (int) $assistant->getId() : 0;

		return array(
			'kind'                        => $kind,
			'asked_at'                    => $this->askedTime,
			'busy_at'                     => $busyTime,
			'units_per_session'           => $units,
			'customers_per_session'       => $parallels,
			'assistant_multiple_customers'=> $multi,
			'service_id'                  => $serviceId,
			'service_name'                => ( $service && method_exists($service, 'getName') ) ? (string) $service->getName() : '',
			'assistant_id'                => $asstId,
			'assistant_name'              => ( $assistant && method_exists($assistant, 'getName') ) ? (string) $assistant->getName() : '',
			'service_edit_url'            => $serviceId ? admin_url('post.php?post=' . $serviceId . '&action=edit') : '',
			'assistant_edit_url'          => $asstId ? admin_url('post.php?post=' . $asstId . '&action=edit') : '',
			'settings_url'                => admin_url('admin.php?page=salon-settings&tab=booking#sln-customers_per_session'),
		);
	}

	/**
	 * Rephrase raw booking-engine capacity errors with the settings that control them.
	 *
	 * @param array $check
	 * @return string
	 */
	private function formatEngineFailureHuman(array $check)
	{
		$lang   = $this->replyLang ? $this->replyLang : 'en';
		$detail = isset($check['detail']) ? trim((string) $check['detail']) : '';
		$parts  = $detail !== '' ? preg_split('/\R+/', $detail) : array();
		$first  = $parts ? trim((string) $parts[0]) : '';
		$cap    = ( isset($check['capacity']) && is_array($check['capacity']) ) ? $check['capacity'] : array();

		if (! $cap && $detail === '') {
			return '';
		}

		$kind     = isset($cap['kind']) ? $cap['kind'] : '';
		$asked    = ! empty($cap['asked_at']) ? $cap['asked_at'] : $this->askedTime;
		$busyTime = ! empty($cap['busy_at']) ? $cap['busy_at'] : null;
		if (! $busyTime && preg_match('/\b(\d{1,2}):(\d{2})\b/', $detail, $m)) {
			$busyTime = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
		}
		$units     = isset($cap['units_per_session']) ? (int) $cap['units_per_session'] : 0;
		$parallels = isset($cap['customers_per_session']) ? (int) $cap['customers_per_session'] : 0;
		$multi     = array_key_exists('assistant_multiple_customers', $cap) ? $cap['assistant_multiple_customers'] : null;
		$svcName   = isset($cap['service_name']) ? (string) $cap['service_name'] : '';
		$asstName  = isset($cap['assistant_name']) ? (string) $cap['assistant_name'] : '';

		// Infer kind from raw engine text when capacity meta is thin.
		if ($kind === '' || $kind === 'capacity') {
			$folded = strtolower(SLN_AI_Language::fold($detail));
			if (preg_match('/\b(limit of parallels|parallels bookings)\b/i', $folded)) {
				$kind = 'customers_per_session';
			} elseif (preg_match('/\b(assistant is full|this assistant is full)\b/i', $folded)) {
				$kind = ( $multi === false ) ? 'assistant_single' : 'assistant_multi';
			} elseif (preg_match('/\b(currently full|attualmente completo|service for)\b/i', $folded)) {
				$kind = 'units_per_session';
			}
		}

		$whenBits = array();
		if ($asked && $busyTime && $asked !== $busyTime) {
			$whenBits[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_capacity_overlap_lead',
				/* translators: 1: asked start, 2: busy interval inside duration */
				__('It can’t start at %1$s because during that appointment capacity is already full at %2$s.', 'salon-booking-system'),
				array($asked, $busyTime)
			);
		} elseif ($asked || $busyTime) {
			$whenBits[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_capacity_at',
				/* translators: %s: time */
				__('Capacity is already full at %s.', 'salon-booking-system'),
				array($asked ? $asked : $busyTime)
			);
		}

		$settingsLine = $this->formatCapacitySettingsLine($lang, $kind, $units, $parallels, $multi, $svcName, $asstName, $cap);
		$linkLine     = $this->formatCapacityLinksLine($lang, $kind, $cap);
		$out          = array_filter(array_merge($whenBits, array($settingsLine)));

		if (! $out) {
			return $first !== '' ? $first : SLN_AI_Language::phrase(
				$lang,
				'unavailable_engine_generic',
				__('The booking engine rejects this slot (capacity, duration, or a conflict).', 'salon-booking-system')
			);
		}

		$text = implode(' ', $out);
		if ($linkLine !== '') {
			$text .= "\n\n" . $linkLine;
		}

		return $text;
	}

	/**
	 * Markdown links to the setting screen that controls this capacity failure.
	 *
	 * @param string $lang
	 * @param string $kind
	 * @param array  $cap
	 * @return string
	 */
	private function formatCapacityLinksLine($lang, $kind, array $cap)
	{
		$links = array();

		switch ($kind) {
			case 'customers_per_session':
				if (! empty($cap['settings_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_customers_per_session',
						__('Open Customers per session', 'salon-booking-system')
					) . '](' . $cap['settings_url'] . ')';
				}
				break;

			case 'units_per_session':
				if (! empty($cap['service_edit_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_units_per_session',
						__('Open Units per session (service)', 'salon-booking-system')
					) . '](' . $cap['service_edit_url'] . ')';
				}
				break;

			case 'assistant_single':
				if (! empty($cap['assistant_edit_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_multiple_customers',
						__('Open Multiple Customers per Session (assistant)', 'salon-booking-system')
					) . '](' . $cap['assistant_edit_url'] . ')';
				}
				break;

			case 'assistant_multi':
				if (! empty($cap['service_edit_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_units_per_session',
						__('Open Units per session (service)', 'salon-booking-system')
					) . '](' . $cap['service_edit_url'] . ')';
				}
				if (! empty($cap['assistant_edit_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_assistant',
						__('Open assistant', 'salon-booking-system')
					) . '](' . $cap['assistant_edit_url'] . ')';
				}
				if (! empty($cap['settings_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_customers_per_session',
						__('Open Customers per session', 'salon-booking-system')
					) . '](' . $cap['settings_url'] . ')';
				}
				break;

			default:
				if (! empty($cap['service_edit_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_service',
						__('Open service', 'salon-booking-system')
					) . '](' . $cap['service_edit_url'] . ')';
				}
				if (! empty($cap['assistant_edit_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_assistant',
						__('Open assistant', 'salon-booking-system')
					) . '](' . $cap['assistant_edit_url'] . ')';
				}
				if (! empty($cap['settings_url'])) {
					$links[] = '[' . SLN_AI_Language::phrase(
						$lang,
						'open_customers_per_session',
						__('Open Customers per session', 'salon-booking-system')
					) . '](' . $cap['settings_url'] . ')';
				}
				break;
		}

		return $links ? implode(' · ', $links) : '';
	}

	/**
	 * @param string      $lang
	 * @param string      $kind
	 * @param int         $units
	 * @param int         $parallels
	 * @param bool|null   $multi
	 * @param string      $svcName
	 * @param string      $asstName
	 * @param array       $cap
	 * @return string
	 */
	private function formatCapacitySettingsLine($lang, $kind, $units, $parallels, $multi, $svcName, $asstName, array $cap)
	{
		$labelUnits = SLN_AI_Language::phrase($lang, 'setting_units_per_session', __('Units per session', 'salon-booking-system'));
		$labelCust  = SLN_AI_Language::phrase($lang, 'setting_customers_per_session', __('Customers per session', 'salon-booking-system'));
		$labelMulti = SLN_AI_Language::phrase($lang, 'setting_multiple_customers', __('Multiple Customers per Session', 'salon-booking-system'));

		switch ($kind) {
			case 'customers_per_session':
				return SLN_AI_Language::phrase(
					$lang,
					'unavailable_cap_setting_customers',
					/* translators: 1: setting label, 2: current value */
					__('This comes from Settings → Booking → “%1$s” (currently %2$d). Raise it if more overlapping bookings are allowed salon-wide.', 'salon-booking-system'),
					array($labelCust, $parallels > 0 ? $parallels : 1)
				);

			case 'units_per_session':
				return SLN_AI_Language::phrase(
					$lang,
					'unavailable_cap_setting_units',
					/* translators: 1: service name, 2: setting label, 3: current value */
					__('This comes from the service%1$s setting “%2$s” (currently %3$d) — concurrent capacity for that service. Open the service to raise it if more clients can book the same slot.', 'salon-booking-system'),
					array(
						$svcName !== '' ? ' “' . $svcName . '”' : '',
						$labelUnits,
						$units > 0 ? $units : 0,
					)
				);

			case 'assistant_single':
				return SLN_AI_Language::phrase(
					$lang,
					'unavailable_cap_setting_assistant_off',
					/* translators: 1: assistant name, 2: checkbox label */
					__('This comes from assistant%1$s: “%2$s” is off, so one booking occupies that assistant for the whole duration. Enable it only if they can serve multiple customers at once.', 'salon-booking-system'),
					array(
						$asstName !== '' ? ' “' . $asstName . '”' : '',
						$labelMulti,
					)
				);

			case 'assistant_multi':
				$limitLabel = ( $units > 0 ) ? $labelUnits : $labelCust;
				$limitVal   = ( $units > 0 ) ? $units : ( $parallels > 0 ? $parallels : 1 );

				return SLN_AI_Language::phrase(
					$lang,
					'unavailable_cap_setting_assistant_on',
					/* translators: 1: assistant, 2: multiple-customers label, 3: limit setting label, 4: limit value */
					__('Assistant%1$s has “%2$s” on, so the limit is “%3$s” (currently %4$d) on the service / booking settings — that many overlapping bookings already fill the slot.', 'salon-booking-system'),
					array(
						$asstName !== '' ? ' “' . $asstName . '”' : '',
						$labelMulti,
						$limitLabel,
						$limitVal,
					)
				);

			default:
				$bits = array();
				if ($svcName !== '' || $units > 0) {
					$bits[] = SLN_AI_Language::phrase(
						$lang,
						'unavailable_cap_setting_units_short',
						/* translators: 1: setting label, 2: value */
						__('Service “%1$s” = %2$d', 'salon-booking-system'),
						array($labelUnits, $units)
					);
				}
				$bits[] = SLN_AI_Language::phrase(
					$lang,
					'unavailable_cap_setting_customers_short',
					/* translators: 1: setting label, 2: value */
					__('Salon “%1$s” = %2$d', 'salon-booking-system'),
					array($labelCust, $parallels > 0 ? $parallels : 1)
				);
				if ($multi !== null) {
					$bits[] = SLN_AI_Language::phrase(
						$lang,
						'unavailable_cap_setting_multi_short',
						/* translators: 1: checkbox label, 2: on/off */
						__('Assistant “%1$s” = %2$s', 'salon-booking-system'),
						array(
							$labelMulti,
							$multi
								? SLN_AI_Language::phrase($lang, 'on', __('on', 'salon-booking-system'))
								: SLN_AI_Language::phrase($lang, 'off', __('off', 'salon-booking-system')),
						)
					);
				}

				return SLN_AI_Language::phrase(
					$lang,
					'unavailable_cap_setting_generic',
					/* translators: %s: settings summary */
					__('Relevant capacity settings: %s.', 'salon-booking-system'),
					array(implode('; ', $bits))
				);
		}
	}

	/**
	 * Longer plain-language explanation of a prior capacity diagnosis (follow-up).
	 *
	 * @param array  $guidance last_guidance payload
	 * @param string $lang
	 * @return string
	 */
	public static function explainCapacityFollowUp(array $guidance, $lang = 'en')
	{
		$args        = isset($guidance['arguments']) && is_array($guidance['arguments']) ? $guidance['arguments'] : array();
		$diagnosis   = isset($guidance['diagnosis']) && is_array($guidance['diagnosis']) ? $guidance['diagnosis'] : array();
		$cap         = isset($diagnosis['capacity']) && is_array($diagnosis['capacity']) ? $diagnosis['capacity'] : array();
		$serviceName = isset($args['service_name']) ? (string) $args['service_name'] : ( isset($cap['service_name']) ? (string) $cap['service_name'] : '' );
		$asstName    = isset($args['assistant_name']) ? (string) $args['assistant_name'] : ( isset($cap['assistant_name']) ? (string) $cap['assistant_name'] : '' );
		$date        = isset($args['date']) ? (string) $args['date'] : '';
		$time        = isset($args['time']) ? (string) $args['time'] : '';
		$units       = isset($cap['units_per_session'])
			? (int) $cap['units_per_session']
			: ( isset($diagnosis['units_per_session']) ? (int) $diagnosis['units_per_session'] : ( isset($diagnosis['unit_per_hour']) ? (int) $diagnosis['unit_per_hour'] : 0 ) );
		$parallels   = isset($cap['customers_per_session'])
			? (int) $cap['customers_per_session']
			: ( isset($diagnosis['customers_per_session']) ? (int) $diagnosis['customers_per_session'] : 0 );
		$multi       = array_key_exists('assistant_multiple_customers', $cap) ? $cap['assistant_multiple_customers'] : null;
		$kind        = isset($cap['kind']) ? (string) $cap['kind'] : '';
		$reasons     = isset($diagnosis['reasons']) && is_array($diagnosis['reasons']) ? $diagnosis['reasons'] : array();
		$reasonText  = $reasons ? $reasons[0] : ( isset($guidance['summary']) ? (string) $guidance['summary'] : '' );

		$labelUnits = SLN_AI_Language::phrase($lang, 'setting_units_per_session', __('Units per session', 'salon-booking-system'));
		$labelCust  = SLN_AI_Language::phrase($lang, 'setting_customers_per_session', __('Customers per session', 'salon-booking-system'));
		$labelMulti = SLN_AI_Language::phrase($lang, 'setting_multiple_customers', __('Multiple Customers per Session', 'salon-booking-system'));

		$parts   = array();
		$parts[] = SLN_AI_Language::phrase(
			$lang,
			'unavailable_full_means',
			__(
				'“Full” / “at capacity” means an overlapping-bookings limit was already reached for part of the appointment. That limit comes from service “Units per session”, salon “Customers per session”, and/or the assistant “Multiple Customers per Session” checkbox.',
				'salon-booking-system'
			)
		);

		if ($reasonText !== '') {
			$parts[] = $reasonText;
		}

		$parts[] = SLN_AI_Language::phrase($lang, 'unavailable_full_settings_intro', __('Settings that control this:', 'salon-booking-system'));

		$parts[] = '- ' . SLN_AI_Language::phrase(
			$lang,
			'unavailable_full_setting_units_line',
			/* translators: 1: setting label, 2: service name, 3: value */
			__('“%1$s” on the service%2$s → currently %3$d (concurrent bookings allowed for that service).', 'salon-booking-system'),
			array(
				$labelUnits,
				$serviceName !== '' ? ' “' . $serviceName . '”' : '',
				$units,
			)
		);

		$parts[] = '- ' . SLN_AI_Language::phrase(
			$lang,
			'unavailable_full_setting_customers_line',
			/* translators: 1: setting label, 2: value */
			__('“%1$s” in Settings → Booking → currently %2$d (salon-wide overlapping bookings per slot).', 'salon-booking-system'),
			array($labelCust, $parallels > 0 ? $parallels : 1)
		);

		if ($asstName !== '' || $multi !== null) {
			$onOff = ( $multi === true )
				? SLN_AI_Language::phrase($lang, 'on', __('on', 'salon-booking-system'))
				: ( ( $multi === false )
					? SLN_AI_Language::phrase($lang, 'off', __('off', 'salon-booking-system'))
					: '—' );
			$parts[] = '- ' . SLN_AI_Language::phrase(
				$lang,
				'unavailable_full_setting_multi_line',
				/* translators: 1: setting label, 2: assistant name, 3: on/off */
				__('“%1$s” on the assistant%2$s → %3$s. Off = one booking fills the assistant; On = limited by Units/Customers per session.', 'salon-booking-system'),
				array(
					$labelMulti,
					$asstName !== '' ? ' “' . $asstName . '”' : '',
					$onOff,
				)
			);
		}

		if ($kind === 'units_per_session') {
			$parts[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_full_hint_units',
				__('In this case the blocker is the service “Units per session”. Raise it on the service edit screen if more clients can take that service at the same time.', 'salon-booking-system')
			);
		} elseif ($kind === 'customers_per_session') {
			$parts[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_full_hint_customers',
				__('In this case the blocker is salon “Customers per session” (Settings → Booking).', 'salon-booking-system')
			);
		} elseif ($kind === 'assistant_single') {
			$parts[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_full_hint_assistant_off',
				__('In this case the blocker is the assistant: “Multiple Customers per Session” is off, so they are exclusive for that slot.', 'salon-booking-system')
			);
		} elseif ($kind === 'assistant_multi') {
			$parts[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_full_hint_assistant_on',
				__('In this case the assistant allows multiple customers, but the Units/Customers per session limit is already reached.', 'salon-booking-system')
			);
		}

		$links = array();
		if (! empty($cap['service_edit_url'])) {
			$links[] = '[' . SLN_AI_Language::phrase($lang, 'open_service', __('Open service', 'salon-booking-system')) . '](' . $cap['service_edit_url'] . ')';
		}
		if (! empty($cap['assistant_edit_url'])) {
			$links[] = '[' . SLN_AI_Language::phrase($lang, 'open_assistant', __('Open assistant', 'salon-booking-system')) . '](' . $cap['assistant_edit_url'] . ')';
		}
		if (! empty($cap['settings_url'])) {
			$links[] = '[' . SLN_AI_Language::phrase($lang, 'open_customers_per_session', __('Open Customers per session', 'salon-booking-system')) . '](' . $cap['settings_url'] . ')';
		}
		if ($links) {
			$parts[] = implode(' · ', $links);
		}

		$whereBits = array_filter(array($serviceName, $asstName, trim($date . ' ' . $time)));
		if ($whereBits) {
			$parts[] = SLN_AI_Language::phrase(
				$lang,
				'unavailable_full_check_calendar',
				/* translators: %s: service / assistant / date-time */
				__('Also check the calendar around %s for overlapping bookings that consume that capacity.', 'salon-booking-system'),
				array(implode(' · ', $whereBits))
			);
		}

		return implode("\n\n", $parts);
	}

	/**
	 * Human phrasing for a failed check.
	 *
	 * @param array $check
	 * @return string
	 */
	private function formatFailureHuman(array $check)
	{
		$id     = isset($check['id']) ? $check['id'] : '';
		$detail = isset($check['detail']) ? trim((string) $check['detail']) : '';
		$parts  = $detail !== '' ? preg_split('/\R+/', $detail) : array();
		$first  = $parts ? trim((string) $parts[0]) : '';
		$extra  = isset($parts[1]) ? trim((string) $parts[1]) : '';

		switch ($id) {
			case 'hours_before':
				if (stripos($first, 'Too far ahead') !== false || stripos($first, 'only until') !== false) {
					$until = $first;
					if (preg_match('/until\s+([0-9:\-\s]+)/i', $first, $m)) {
						$untilTs = strtotime(trim($m[1]));
						$until   = $untilTs
							? wp_date(__('j F Y', 'salon-booking-system'), $untilTs)
							: trim($m[1]);
						return sprintf(
							/* translators: %s: end of booking window */
							__('It’s outside your booking window — customers can only book up to %s.', 'salon-booking-system'),
							$until
						);
					}
				}
				if (stripos($first, 'Too soon') !== false || stripos($first, 'open from') !== false) {
					return __('It’s too soon to book — that time is still inside your “hours before” minimum.', 'salon-booking-system');
				}
				return $first !== '' ? $first : __('It’s outside your allowed booking window.', 'salon-booking-system');

			case 'salon_hours':
			case 'salon_day':
				$msg = __('Your salon opening hours don’t cover that day or time (including the service duration).', 'salon-booking-system');
				if ($extra !== '' && strlen($extra) <= 140) {
					$msg .= ' ' . sprintf(
						/* translators: %s: hours summary */
						__('Right now you’re set to: %s.', 'salon-booking-system'),
						$extra
					);
				}
				return $msg;

			case 'salon_holidays':
				return __('That date/time is blocked by a salon holiday or lock.', 'salon-booking-system');

			case 'service_hours':
			case 'service_day':
				return __('This service has its own availability rules that exclude that slot.', 'salon-booking-system');

			case 'assistant_hours':
			case 'assistant_day':
				return __('That assistant’s working hours don’t cover this date/time.', 'salon-booking-system');

			case 'assistant_holidays':
				return __('That assistant has a holiday or lock covering this date/time.', 'salon-booking-system');

			case 'assistant_assigned':
				return __('That assistant isn’t assigned to this service.', 'salon-booking-system');

			case 'offered_times':
			case 'offered_day':
				return __('The booking form isn’t offering that start time for that day with your current rules.', 'salon-booking-system');

			case 'engine':
			case 'engine_service':
				$engineMsg = $this->formatEngineFailureHuman($check);
				if ($engineMsg !== '') {
					return $engineMsg;
				}
				return SLN_AI_Language::phrase(
					$this->replyLang,
					'unavailable_engine_generic',
					__('The booking engine rejects this slot (capacity, duration, or a conflict).', 'salon-booking-system')
				);

			case 'booking_enabled':
				return __('Online booking is currently disabled in Settings.', 'salon-booking-system');

			case 'assistants_enabled':
				return __('Assistant selection is turned off in Settings, so picking a specific assistant may not apply.', 'salon-booking-system');

			default:
				return $first !== '' ? $first : ( isset($check['label']) ? (string) $check['label'] : '' );
		}
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);
		if (is_wp_error($preview)) {
			return $preview;
		}

		return array(
			'ok'       => true,
			'before'   => array(),
			'after'    => array(),
			'message'  => $preview['summary'],
			'guidance' => true,
		);
	}

	/**
	 * @param mixed $previous
	 * @return WP_Error
	 */
	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Diagnostic replies cannot be undone.', 'salon-booking-system')
		);
	}

	/**
	 * Flexible args: run whatever checks are possible; collect missing fields to ask later.
	 *
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	private function mapArguments(array $arguments)
	{
		$dateStr = ! empty($arguments['date']) ? $this->sanitizeDate($arguments['date']) : null;
		$timeStr = ! empty($arguments['time']) ? $this->sanitizeTime($arguments['time']) : null;

		$notFound = array();
		$service  = null;
		$hasServiceHint = ! empty($arguments['service_id']) || ! empty($arguments['service_name']) || ! empty($arguments['service']);
		if ($hasServiceHint) {
			$resolved = $this->resolveService($arguments);
			if (is_wp_error($resolved)) {
				$notFound[] = $resolved->get_error_message();
			} else {
				$service = $resolved;
			}
		}

		$assistant = null;
		$hasAssistantHint = ! empty($arguments['assistant_id']) || ! empty($arguments['assistant_name']) || ! empty($arguments['assistant']);
		if ($hasAssistantHint) {
			$resolved = $this->resolveAssistant($arguments);
			if (is_wp_error($resolved)) {
				$notFound[] = $resolved->get_error_message();
			} else {
				$assistant = $resolved;
			}
		}

		// Bare entity name: try service first, then assistant.
		if (! $service && ! $assistant && ! empty($arguments['entity_name'])) {
			$entityArgs = array('service_name' => $arguments['entity_name']);
			$resolved   = $this->resolveService($entityArgs);
			if (! is_wp_error($resolved)) {
				$service        = $resolved;
				$hasServiceHint = true;
				if (empty($arguments['focus'])) {
					$arguments['focus'] = 'service';
				}
			} else {
				$entityArgs = array('assistant_name' => $arguments['entity_name']);
				$resolved   = $this->resolveAssistant($entityArgs);
				if (! is_wp_error($resolved)) {
					$assistant        = $resolved;
					$hasAssistantHint = true;
					if (empty($arguments['focus'])) {
						$arguments['focus'] = 'assistant';
					}
				} else {
					$notFound[] = sprintf(
						/* translators: %s: name */
						__('No service or assistant named “%s” was found.', 'salon-booking-system'),
						sanitize_text_field($arguments['entity_name'])
					);
				}
			}
		}

		$focus = isset($arguments['focus']) ? sanitize_key($arguments['focus']) : '';
		if (! in_array($focus, array('assistant', 'service', 'slot'), true)) {
			$focus = '';
		}
		if ($focus === '' && $service && $dateStr && ! $hasAssistantHint) {
			$focus = 'service';
		}
		if ($focus === '' && $assistant && $dateStr && ! $hasServiceHint) {
			$focus = 'assistant';
		}

		$missing = array();
		// Only ask for fields never provided (not ones that failed to resolve).
		if (! $dateStr) {
			$missing[] = __('date', 'salon-booking-system');
		}
		if (! $timeStr && $focus !== 'service' && $focus !== 'assistant') {
			$missing[] = __('time', 'salon-booking-system');
		}
		if (! $service && ! $hasServiceHint) {
			$missing[] = __('service name', 'salon-booking-system');
		}
		if (! $assistant && ! $hasAssistantHint && $focus !== 'service') {
			$missing[] = __('assistant name', 'salon-booking-system');
		}

		return array(
			'date'      => $dateStr,
			'time'      => $timeStr,
			'service'   => $service,
			'assistant' => $assistant,
			'missing'   => $missing,
			'not_found' => $notFound,
			'focus'     => $focus,
		);
	}

	/**
	 * @return DateTime
	 */
	private function defaultDuration()
	{
		$mins = (int) $this->settings()->get('interval');
		if ($mins < 1) {
			$mins = 30;
		}

		return new DateTime('1970-01-01 ' . sprintf('%02d:%02d:00', (int) floor($mins / 60), $mins % 60));
	}

	/**
	 * @param SLN_Wrapper_ServiceInterface $service
	 * @param DateTimeInterface            $dt
	 */
	private function checkServiceDayOpen($service, $dt)
	{
		$items   = $service->getAvailabilityItems();
		$rules   = $items->toArray();
		$hasReal = $rules && ! ( isset($rules[0]) && $rules[0] instanceof SLN_Helper_AvailabilityItemNull );
		if (! $hasReal) {
			return array(
				'id'     => 'service_day',
				'label'  => __('Service follows salon hours on this date (no custom service schedule)', 'salon-booking-system'),
				'pass'   => true,
				'detail' => '',
			);
		}
		$pass = $items->isValidDate(Date::create($dt), $service);

		return array(
			'id'     => 'service_day',
			'label'  => __('Service availability rules include this date', 'salon-booking-system'),
			'pass'   => (bool) $pass,
			'detail' => $pass ? '' : __('This service’s own availability rules do not cover that date.', 'salon-booking-system'),
		);
	}

	/**
	 * @param SLN_Wrapper_ServiceInterface $service
	 */
	private function checkServiceHasEligibleAssistants($service)
	{
		if (! $this->settings()->get('attendant_enabled')) {
			return array(
				'id'     => 'service_assistants',
				'label'  => __('Service can be delivered (assistants disabled — not required)', 'salon-booking-system'),
				'pass'   => true,
				'detail' => '',
			);
		}

		$attendants = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT)->getAll();
		$eligible   = array();
		foreach ($attendants as $att) {
			if ($att->isEmpty()) {
				continue;
			}
			if (method_exists($att, 'getMeta') && $att->getMeta('hide_on_frontend')) {
				continue;
			}
			if ($att->hasAllServices() || $att->hasService($service)) {
				$eligible[] = $att->getName();
			}
		}

		return array(
			'id'     => 'service_assistants',
			'label'  => __('At least one assistant can perform this service', 'salon-booking-system'),
			'pass'   => ! empty($eligible),
			'detail' => empty($eligible)
				? __('No assistant is assigned to this service (or all are hidden on the front-end).', 'salon-booking-system')
				: sprintf(
					/* translators: %s: assistant names */
					__('Eligible assistants include: %s', 'salon-booking-system'),
					implode(', ', array_slice($eligible, 0, 8))
				),
		);
	}

	/**
	 * Service-only engine check when no assistant was specified.
	 *
	 * @param SLN_Wrapper_ServiceInterface $service
	 * @param DateTimeInterface            $dt
	 * @param DateTime                     $duration
	 */
	private function checkEngineServiceOnly($service, $dt, DateTime $duration)
	{
		$ah = $this->plugin->getAvailabilityHelper();
		$ah->setExcludeHiddenFromFrontend(true);
		$start = new SLN_DateTime($dt->format('Y-m-d H:i:s'), SLN_DateTime::getWpTimezone());
		$ah->setDate($start);
		$svcErr = $ah->validateService($service, $start, $duration);
		$detail = $svcErr ? $this->stringifyErrors($svcErr) : '';

		$out = array(
			'id'     => 'engine_service',
			'label'  => __('Passes booking engine service validation (capacity / duration)', 'salon-booking-system'),
			'pass'   => empty($svcErr),
			'detail' => $detail,
		);
		if ($svcErr) {
			$out['capacity'] = $this->buildCapacityContext($service, null, $detail);
		}

		return $out;
	}

	/**
	 * @param SLN_Wrapper_AttendantInterface $assistant
	 * @param DateTimeInterface              $dt
	 * @param SLN_Wrapper_ServiceInterface|null $service
	 */
	private function checkAssistantDayOpen($assistant, $dt, $service = null)
	{
		$items   = $assistant->getAvailabilityItems();
		$rules   = $items->toArray();
		$hasReal = $rules && ! ( isset($rules[0]) && $rules[0] instanceof SLN_Helper_AvailabilityItemNull );
		if (! $hasReal) {
			return array(
				'id'     => 'assistant_day',
				'label'  => __('Assistant follows salon hours on this date (no custom assistant schedule)', 'salon-booking-system'),
				'pass'   => true,
				'detail' => '',
			);
		}
		$pass = $items->isValidDate(Date::create($dt), $service);

		return array(
			'id'     => 'assistant_day',
			'label'  => __('Assistant working rules include this date', 'salon-booking-system'),
			'pass'   => (bool) $pass,
			'detail' => $pass ? '' : __('This assistant’s working-hour rules do not cover that date (weekday / period).', 'salon-booking-system'),
		);
	}

	/**
	 * Day-level salon open check when time is unknown.
	 *
	 * @param DateTimeInterface $dt
	 */
	private function checkSalonDayOpen($dt)
	{
		$items = $this->settings()->getAvailabilityItems();
		$pass  = $items->isValidDate(Date::create($dt));

		return array(
			'id'     => 'salon_day',
			'label'  => __('Salon is open on this date', 'salon-booking-system'),
			'pass'   => (bool) $pass,
			'detail' => $pass ? '' : __('Salon opening rules do not include this weekday/date.', 'salon-booking-system'),
		);
	}

	/**
	 * @param DateTimeInterface $dt
	 */
	private function checkOfferedTimesForDay($dt)
	{
		$ah = $this->plugin->getAvailabilityHelper();
		$ah->setExcludeHiddenFromFrontend(true);
		$ah->setDate(new SLN_DateTime($dt->format('Y-m-d') . ' 12:00:00', SLN_DateTime::getWpTimezone()));
		$times = $ah->getTimes(Date::create($dt));
		$keys  = is_array($times) ? array_keys($times) : array();
		$sample = array();
		foreach (array_slice($keys, 0, 12) as $k) {
			$sample[] = substr((string) $k, 0, 5);
		}

		return array(
			'id'     => 'offered_day',
			'label'  => __('Day has at least one bookable start time', 'salon-booking-system'),
			'pass'   => ! empty($keys),
			'detail' => empty($keys)
				? __('No bookable times were found for that date.', 'salon-booking-system')
				: sprintf(
					/* translators: %s: times */
					__('Example offered times: %s', 'salon-booking-system'),
					implode(', ', $sample)
				),
		);
	}

	private function checkBookingEnabled()
	{
		$disabled = (bool) $this->settings()->get('disabled');
		return array(
			'id'     => 'booking_enabled',
			'label'  => __('Online booking is enabled', 'salon-booking-system'),
			'pass'   => ! $disabled,
			'detail' => $disabled
				? (string) $this->settings()->get('disabled_message')
				: '',
		);
	}

	/**
	 * @param DateTimeInterface $dt
	 */
	private function checkHoursBefore($dt)
	{
		$hb   = $this->plugin->getAvailabilityHelper()->getHoursBeforeHelper();
		$from = $hb->isValidFrom($dt);
		$to   = $hb->isValidTo($dt);
		$pass = $from && $to;
		$detail = '';
		if (! $from) {
			$detail = sprintf(
				/* translators: %s: datetime */
				__('Too soon — bookings open from %s.', 'salon-booking-system'),
				$hb->getFromDate()->format('Y-m-d H:i')
			);
		} elseif (! $to) {
			$detail = sprintf(
				/* translators: %s: datetime */
				__('Too far ahead — bookings only until %s.', 'salon-booking-system'),
				$hb->getToDate()->format('Y-m-d H:i')
			);
		}

		return array(
			'id'     => 'hours_before',
			'label'  => __('Inside the bookable time window (hours before)', 'salon-booking-system'),
			'pass'   => $pass,
			'detail' => $detail,
		);
	}

	/**
	 * @param DateTimeInterface $dt
	 * @param DateTime          $duration
	 */
	private function checkSalonHours($dt, DateTime $duration)
	{
		$items   = $this->settings()->getAvailabilityItems();
		$pass    = $items->isValidDatetimeDuration($dt, $duration);
		$summary = '';
		if (class_exists('SLN_AI_Tools_SetAvailabilities')) {
			$tool    = new SLN_AI_Tools_SetAvailabilities($this->plugin);
			$raw     = $this->settings()->get('availabilities');
			$summary = $tool->formatAvailabilitiesSummary(is_array($raw) ? $raw : array());
		}

		return array(
			'id'     => 'salon_hours',
			'label'  => __('Covered by salon opening hours (including service duration)', 'salon-booking-system'),
			'pass'   => (bool) $pass,
			'detail' => $pass ? '' : __('Salon opening rules do not cover this date/time for the service duration.', 'salon-booking-system')
				. ( $summary ? "\n" . $summary : '' ),
		);
	}

	/**
	 * @param DateTimeInterface $dt
	 */
	private function checkSalonHolidays($dt)
	{
		$items = $this->settings()->getHolidayItems();
		$pass  = $items->isValidDatetime($dt);

		return array(
			'id'     => 'salon_holidays',
			'label'  => __('Not blocked by a salon holiday / lock', 'salon-booking-system'),
			'pass'   => (bool) $pass,
			'detail' => $pass ? '' : __('A salon holiday or lock rule covers this date/time.', 'salon-booking-system'),
		);
	}

	/**
	 * @param SLN_Wrapper_ServiceInterface $service
	 * @param DateTimeInterface            $dt
	 */
	private function checkServiceHours($service, $dt)
	{
		$slnDt       = new SLN_DateTime($dt->format('Y-m-d H:i:s'), SLN_DateTime::getWpTimezone());
		$unavailable = $service->isNotAvailableOnDate($slnDt);

		return array(
			'id'     => 'service_hours',
			'label'  => __('Service is allowed on this date/time', 'salon-booking-system'),
			'pass'   => ! $unavailable,
			'detail' => $unavailable
				? __('This service has its own availability rules that exclude this slot.', 'salon-booking-system')
				: '',
		);
	}

	private function checkAssistantSelectionMode()
	{
		$enabled = (bool) $this->settings()->get('attendant_enabled');

		return array(
			'id'     => 'assistants_enabled',
			'label'  => __('Assistants are enabled in settings', 'salon-booking-system'),
			'pass'   => $enabled,
			'detail' => $enabled ? '' : __('Assistant selection is disabled — the front-end may ignore a specific assistant choice.', 'salon-booking-system'),
		);
	}

	/**
	 * @param SLN_Wrapper_ServiceInterface   $service
	 * @param SLN_Wrapper_AttendantInterface $assistant
	 */
	private function checkAssistantAssigned($service, $assistant)
	{
		if ($assistant->hasAllServices() || $assistant->hasService($service)) {
			return array(
				'id'     => 'assistant_assigned',
				'label'  => __('Assistant can perform this service', 'salon-booking-system'),
				'pass'   => true,
				'detail' => '',
			);
		}

		return array(
			'id'     => 'assistant_assigned',
			'label'  => __('Assistant can perform this service', 'salon-booking-system'),
			'pass'   => false,
			'detail' => __('This assistant is not assigned to that service (services list on the assistant).', 'salon-booking-system'),
		);
	}

	/**
	 * @param SLN_Wrapper_AttendantInterface $assistant
	 * @param DateTimeInterface              $dt
	 * @param DateTime                       $duration
	 * @param SLN_Wrapper_ServiceInterface   $service
	 */
	private function checkAssistantHours($assistant, $dt, DateTime $duration, $service = null)
	{
		$items   = $assistant->getAvailabilityItems();
		$rules   = $items->toArray();
		$hasReal = $rules && ! ( isset($rules[0]) && $rules[0] instanceof SLN_Helper_AvailabilityItemNull );
		if (! $hasReal) {
			return array(
				'id'     => 'assistant_hours',
				'label'  => __('Covered by assistant working hours', 'salon-booking-system'),
				'pass'   => true,
				'detail' => __('No custom assistant hours — uses salon schedule.', 'salon-booking-system'),
			);
		}
		$pass = $items->isValidDatetimeDuration($dt, $duration, $service);

		return array(
			'id'     => 'assistant_hours',
			'label'  => __('Covered by assistant working hours', 'salon-booking-system'),
			'pass'   => (bool) $pass,
			'detail' => $pass ? '' : __('Assistant working hours do not cover this date/time for the service duration.', 'salon-booking-system'),
		);
	}

	/**
	 * @param SLN_Wrapper_AttendantInterface $assistant
	 * @param DateTimeInterface              $dt
	 */
	private function checkAssistantHolidays($assistant, $dt)
	{
		$pass = $assistant->getHolidayItems()->isValidDatetime($dt);

		return array(
			'id'     => 'assistant_holidays',
			'label'  => __('Not blocked by an assistant holiday / lock', 'salon-booking-system'),
			'pass'   => (bool) $pass,
			'detail' => $pass ? '' : __('An assistant holiday or lock rule covers this date/time.', 'salon-booking-system'),
		);
	}

	/**
	 * @param SLN_Wrapper_ServiceInterface   $service
	 * @param SLN_Wrapper_AttendantInterface $assistant
	 * @param DateTimeInterface              $dt
	 * @param DateTime                       $duration
	 */
	private function checkEngine($service, $assistant, $dt, DateTime $duration)
	{
		$ah = $this->plugin->getAvailabilityHelper();
		$ah->setExcludeHiddenFromFrontend(true);
		$start = new SLN_DateTime($dt->format('Y-m-d H:i:s'), SLN_DateTime::getWpTimezone());
		$ah->setDate($start);

		$svcErr = $ah->validateService($service, $start, $duration);
		$attErr = $ah->validateAttendant($assistant, $start, $duration, $service);

		$details = array();
		if ($svcErr) {
			$details[] = $this->stringifyErrors($svcErr);
		}
		if ($attErr) {
			$details[] = $this->stringifyErrors($attErr);
		}
		$detail = $details ? implode(' | ', $details) : '';
		$pass   = empty($svcErr) && empty($attErr);

		$out = array(
			'id'     => 'engine',
			'label'  => __('Passes booking engine validation (capacity, busy, duration, resources)', 'salon-booking-system'),
			'pass'   => $pass,
			'detail' => $detail,
		);
		if (! $pass) {
			// Prefer attendant-specific capacity context when the assistant check failed.
			$contextSrc = $attErr ? $this->stringifyErrors($attErr) : $detail;
			$out['capacity'] = $this->buildCapacityContext($service, $assistant, $contextSrc . ' ' . $detail);
		}

		return $out;
	}

	/**
	 * @param DateTimeInterface $dt
	 * @param string            $timeStr
	 */
	private function checkOfferedTimes($dt, $timeStr)
	{
		$ah = $this->plugin->getAvailabilityHelper();
		$ah->setExcludeHiddenFromFrontend(true);
		$ah->setDate(new SLN_DateTime($dt->format('Y-m-d H:i:s'), SLN_DateTime::getWpTimezone()));

		$times = $ah->getTimes(Date::create($dt));
		$keys  = array();
		if (is_array($times)) {
			foreach (array_keys($times) as $k) {
				$keys[] = substr((string) $k, 0, 5);
			}
		}
		$norm = substr($timeStr, 0, 5);
		$pass = in_array($norm, $keys, true);

		$sample = array_slice($keys, 0, 12);
		$detail = '';
		if (! $pass) {
			$detail = __('This start time is not in the bookable times list for that day (with current rules).', 'salon-booking-system');
			if ($sample) {
				$detail .= ' ' . sprintf(
					/* translators: %s: times list */
					__('Nearby offered times: %s', 'salon-booking-system'),
					implode(', ', $sample)
				);
			} else {
				$detail .= ' ' . __('No bookable times were found for that date.', 'salon-booking-system');
			}
		}

		return array(
			'id'     => 'offered_times',
			'label'  => __('Start time appears in the front-end offered times for that day', 'salon-booking-system'),
			'pass'   => $pass,
			'detail' => $detail,
		);
	}

	/**
	 * @param mixed $errors
	 * @return string
	 */
	private function stringifyErrors($errors)
	{
		if (is_string($errors)) {
			return wp_strip_all_tags($errors);
		}
		if (is_array($errors)) {
			$out = array();
			foreach ($errors as $e) {
				$out[] = wp_strip_all_tags(is_string($e) ? $e : (string) $e);
			}

			return implode(' ', $out);
		}

		return wp_strip_all_tags((string) $errors);
	}

	/**
	 * @param array $arguments
	 * @return SLN_Wrapper_Service|WP_Error
	 */
	private function resolveService(array $arguments)
	{
		if (! empty($arguments['service_id'])) {
			$id = (int) $arguments['service_id'];
			if ($this->isPostType($id, SLN_Plugin::POST_TYPE_SERVICE)) {
				$s = $this->plugin->createService($id);
				if ($s && ! $s->isEmpty()) {
					return $s;
				}
			} elseif (empty($arguments['service_name'])) {
				// Chat “servizio 2” is often a label/ordinal, not WP post ID 2.
				$arguments['service_name'] = (string) $id;
			}
		}
		$name = isset($arguments['service_name']) ? sanitize_text_field($arguments['service_name']) : '';
		if ($name === '' && isset($arguments['service'])) {
			$name = sanitize_text_field($arguments['service']);
		}
		if ($name !== '') {
			$p = $this->findCatalogPostByLabel($name, SLN_Plugin::POST_TYPE_SERVICE);
			if ($p) {
				$s = $this->plugin->createService($p->ID);
				if ($s && ! $s->isEmpty()) {
					return $s;
				}
			}

			$available = $this->listEntityNames(SLN_Plugin::POST_TYPE_SERVICE, 8);
			if ($available) {
				return new WP_Error(
					'sln_ai_service',
					sprintf(
						/* translators: 1: requested name, 2: available names */
						__('I couldn’t find service “%1$s”. Available: %2$s.', 'salon-booking-system'),
						$name,
						implode(', ', $available)
					)
				);
			}

			return new WP_Error(
				'sln_ai_service',
				sprintf(
					/* translators: %s: requested name */
					__('I couldn’t find service “%s”.', 'salon-booking-system'),
					$name
				)
			);
		}

		return new WP_Error('sln_ai_service', __('Service not found.', 'salon-booking-system'));
	}

	/**
	 * @param array $arguments
	 * @return SLN_Wrapper_Attendant|WP_Error
	 */
	private function resolveAssistant(array $arguments)
	{
		if (! empty($arguments['assistant_id'])) {
			$id = (int) $arguments['assistant_id'];
			if ($this->isPostType($id, SLN_Plugin::POST_TYPE_ATTENDANT)) {
				$a = $this->plugin->createAttendant($id);
				if ($a && ! $a->isEmpty()) {
					return $a;
				}
			} elseif (empty($arguments['assistant_name'])) {
				// Chat “assistente 2” must not resolve WP page #2 (often “Privacy Policy”).
				$arguments['assistant_name'] = (string) $id;
			}
		}
		$name = isset($arguments['assistant_name']) ? sanitize_text_field($arguments['assistant_name']) : '';
		if ($name === '' && isset($arguments['assistant'])) {
			$name = sanitize_text_field($arguments['assistant']);
		}
		if ($name !== '') {
			$p = $this->findCatalogPostByLabel($name, SLN_Plugin::POST_TYPE_ATTENDANT);
			if ($p) {
				$a = $this->plugin->createAttendant($p->ID);
				if ($a && ! $a->isEmpty()) {
					return $a;
				}
			}

			$available = $this->listEntityNames(SLN_Plugin::POST_TYPE_ATTENDANT, 8);
			if ($available) {
				return new WP_Error(
					'sln_ai_assistant',
					sprintf(
						/* translators: 1: requested name, 2: available names */
						__('I couldn’t find assistant “%1$s”. Available: %2$s.', 'salon-booking-system'),
						$name,
						implode(', ', $available)
					)
				);
			}

			return new WP_Error(
				'sln_ai_assistant',
				sprintf(
					/* translators: %s: requested name */
					__('I couldn’t find assistant “%s”, and no assistants are set up yet.', 'salon-booking-system'),
					$name
				)
			);
		}

		return new WP_Error('sln_ai_assistant', __('Assistant not found.', 'salon-booking-system'));
	}

	/**
	 * @param int    $postId
	 * @param string $postType
	 * @return bool
	 */
	private function isPostType($postId, $postType)
	{
		$postId = (int) $postId;
		if ($postId <= 0) {
			return false;
		}
		$post = get_post($postId);

		return $post && $post->post_type === $postType;
	}

	/**
	 * Resolve catalog CPT by title, common “Label N” patterns, or 1-based list ordinal.
	 *
	 * @param string $label
	 * @param string $postType
	 * @return WP_Post|null
	 */
	private function findCatalogPostByLabel($label, $postType)
	{
		$label = trim((string) $label);
		if ($label === '') {
			return null;
		}

		$p = $this->findPostByTitle($label, $postType);
		if ($p) {
			return $p;
		}
		$p = $this->findPostByTitleLoose($label, $postType);
		if ($p) {
			return $p;
		}

		$n = 0;
		if (preg_match('/^\d+$/', $label)) {
			$n = (int) $label;
		} elseif (preg_match('/\b(\d+)\s*$/', $label, $m)) {
			$n = (int) $m[1];
		}

		if ($n > 0) {
			$prefixes = array(
				'Assistente', 'Assistant', 'Attendant', 'Operatore', 'Asistente',
				'Servizio', 'Service', 'Servicio', 'Dienstleistung',
			);
			foreach ($prefixes as $prefix) {
				$p = $this->findPostByTitle($prefix . ' ' . $n, $postType);
				if ($p) {
					return $p;
				}
				$p = $this->findPostByTitleLoose($prefix . ' ' . $n, $postType);
				if ($p) {
					return $p;
				}
			}

			return $this->findCatalogPostByOrdinal($n, $postType);
		}

		return null;
	}

	/**
	 * @param int    $ordinal 1-based
	 * @param string $postType
	 * @return WP_Post|null
	 */
	private function findCatalogPostByOrdinal($ordinal, $postType)
	{
		$ordinal = (int) $ordinal;
		if ($ordinal < 1) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => $postType,
				'post_status'    => array('publish', 'draft', 'private'),
				'posts_per_page' => 100,
				'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
				'order'          => 'ASC',
			)
		);
		$idx = $ordinal - 1;
		if (isset($posts[ $idx ])) {
			return $posts[ $idx ];
		}

		return null;
	}

	/**
	 * Case-insensitive / partial title match.
	 *
	 * @param string $title
	 * @param string $postType
	 * @return WP_Post|null
	 */
	private function findPostByTitleLoose($title, $postType)
	{
		$title = trim((string) $title);
		if ($title === '') {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => $postType,
				'post_status'    => array('publish', 'draft', 'private'),
				'posts_per_page' => 50,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$needle = strtolower($title);
		$best   = null;
		foreach ($posts as $p) {
			$hay = strtolower($p->post_title);
			if ($hay === $needle) {
				return $p;
			}
			if (! $best && (strpos($hay, $needle) !== false || strpos($needle, $hay) !== false)) {
				$best = $p;
			}
		}

		return $best;
	}

	/**
	 * @param string $postType
	 * @param int    $limit
	 * @return string[]
	 */
	private function listEntityNames($postType, $limit = 8)
	{
		$posts = get_posts(
			array(
				'post_type'      => $postType,
				'post_status'    => array('publish', 'draft', 'private'),
				'posts_per_page' => (int) $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$names = array();
		foreach ($posts as $p) {
			$names[] = $p->post_title;
		}

		return $names;
	}

	/**
	 * @param string $date
	 * @return string|null
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
		$time = trim((string) $time);
		if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
			$h = (int) $m[1];
			$i = (int) $m[2];
			if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59) {
				return sprintf('%02d:%02d', $h, $i);
			}
		}
		$ts = strtotime('1970-01-01 ' . $time);
		if ($ts) {
			return gmdate('H:i', $ts);
		}

		return null;
	}
}
