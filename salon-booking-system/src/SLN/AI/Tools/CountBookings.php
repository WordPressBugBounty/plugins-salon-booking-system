<?php

/**
 * Guidance-only: count salon bookings (totals / date ranges), not a list lookup.
 */
class SLN_AI_Tools_CountBookings extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'count_bookings';
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
		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$lang    = $mapped['lang'];
		$total   = $this->countWithStatuses($mapped['statuses'], $mapped['date_from'], $mapped['date_to']);
		$byStatus = array();
		foreach ($mapped['statuses'] as $status) {
			$n = $this->countWithStatuses(array($status), $mapped['date_from'], $mapped['date_to']);
			if ($n > 0) {
				$byStatus[ $status ] = $n;
			}
		}
		arsort($byStatus);

		$period = $this->periodLabel($mapped['date_from'], $mapped['date_to'], $lang);
		$header = SLN_AI_Language::phrase(
			$lang,
			'count_bookings_total',
			__('You have %1$d bookings %2$s.', 'salon-booking-system'),
			array($total, $period)
		);

		$lines   = array();
		$lines[] = $header;
		if ($byStatus) {
			$lines[] = '';
			$lines[] = SLN_AI_Language::phrase(
				$lang,
				'count_bookings_by_status',
				__('By status:', 'salon-booking-system')
			);
			foreach ($byStatus as $status => $n) {
				$lines[] = sprintf('• %s: %d', $this->statusLabel($status, $lang), $n);
			}
		}

		$noteParts = array();
		if (empty($mapped['include_draft'])) {
			$noteParts[] = SLN_AI_Language::phrase(
				$lang,
				'count_bookings_excl_draft',
				__('Drafts are excluded.', 'salon-booking-system')
			);
		}
		if (empty($mapped['include_error'])) {
			$noteParts[] = SLN_AI_Language::phrase(
				$lang,
				'count_bookings_excl_error',
				__('ERROR bookings are excluded.', 'salon-booking-system')
			);
		}
		if ($noteParts) {
			$lines[] = '';
			$lines[] = implode(' ', $noteParts);
		}

		$calUrl = admin_url('admin.php?page=salon');
		$lines[] = '';
		$lines[] = '[' . SLN_AI_Language::phrase(
			$lang,
			'open_calendar',
			__('Open calendar', 'salon-booking-system')
		) . '](' . $calUrl . ')';

		return array(
			'ok'         => true,
			'guidance'   => true,
			'summary'    => implode("\n", $lines),
			'tool'       => $this->getName(),
			'tier'       => 'guidance',
			'total'      => $total,
			'by_status'  => $byStatus,
			'date_from'  => $mapped['date_from'],
			'date_to'    => $mapped['date_to'],
			'arguments'  => $mapped,
		);
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
	 * @return array|WP_Error
	 */
	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Booking count cannot be undone.', 'salon-booking-system')
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	private function mapArguments(array $arguments)
	{
		$userMessage = isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : '';
		$lang        = SLN_AI_Language::detect($userMessage !== '' ? $userMessage : 'en');
		$hint        = SLN_AI_Language::fold($userMessage . ' ' . (isset($arguments['query']) ? (string) $arguments['query'] : ''));

		$dateFrom = '';
		$dateTo   = '';
		if (! empty($arguments['date_from'])) {
			$dateFrom = sanitize_text_field((string) $arguments['date_from']);
		}
		if (! empty($arguments['date_to'])) {
			$dateTo = sanitize_text_field((string) $arguments['date_to']);
		}
		if (! empty($arguments['date']) && $dateFrom === '' && $dateTo === '') {
			$day = sanitize_text_field((string) $arguments['date']);
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
				$dateFrom = $day;
				$dateTo   = $day;
			}
		}

		try {
			$tz  = wp_timezone();
			$now = new DateTimeImmutable('now', $tz);
			$today = $now->format('Y-m-d');

			if (($dateFrom === '' || $dateTo === '') && preg_match(
				'/\b(this\s+month|questo\s+mese|este\s+mes|ce\s+mois|diesen\s+monat)\b/i',
				$hint
			)) {
				$dateFrom = $now->modify('first day of this month')->format('Y-m-d');
				$dateTo   = $now->modify('last day of this month')->format('Y-m-d');
			} elseif (($dateFrom === '' || $dateTo === '') && preg_match(
				'/\b(today|oggi|hoy|aujourd.?hui|heute|hoje)\b/i',
				$hint
			) && ! preg_match('/\b(fino\s+ad\s+oggi|until\s+today|up\s+to\s+today|to\s+date|hasta\s+hoy|jusqu.?a\s+aujourd)\b/i', $hint)) {
				$dateFrom = $today;
				$dateTo   = $today;
			} elseif ($dateTo === '' && preg_match(
				'/\b(fino\s+ad\s+oggi|until\s+today|up\s+to\s+(?:today|now)|to\s+date|hasta\s+hoy|jusqu.?a\s+aujourd|bis\s+heute|ate\s+hoje|collected|collezionat)\b/i',
				$hint
			)) {
				$dateTo = $today;
			} elseif ($dateFrom === '' && $dateTo === '') {
				// Bare “how many bookings?” → all history through today.
				$dateTo = $today;
			}
		} catch (Exception $e) {
			// keep empty dates
		}

		foreach (array('date_from' => &$dateFrom, 'date_to' => &$dateTo) as $label => &$value) {
			if ($value !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				return new WP_Error(
					'sln_ai_count_date',
					sprintf(
						/* translators: %s: field name */
						__('%s must be Y-m-d when provided.', 'salon-booking-system'),
						$label
					)
				);
			}
		}
		unset($value);

		$includeDraft = ! empty($arguments['include_draft']);
		$includeError = ! empty($arguments['include_error']);
		if (preg_match('/\b(draft|bozza|borrador|brouillon|entwurf|includ.*draft|incluse?\s+bozze)\b/i', $hint)) {
			$includeDraft = true;
		}
		if (preg_match('/\b(error|errore|inclue?.*error)\b/i', $hint)) {
			$includeError = true;
		}

		$status = '';
		if (! empty($arguments['status'])) {
			$status = $this->normalizeStatusFilter((string) $arguments['status']);
		}

		$statuses = $this->defaultStatuses($includeDraft, $includeError);
		if ($status !== '') {
			$statuses = array($status);
			if ($status === SLN_Enum_BookingStatus::DRAFT) {
				$statuses = array(SLN_Enum_BookingStatus::DRAFT, 'draft');
			}
		}

		return array(
			'date_from'     => $dateFrom,
			'date_to'       => $dateTo,
			'status'        => $status,
			'statuses'      => $statuses,
			'include_draft' => $includeDraft,
			'include_error' => $includeError,
			'lang'          => $lang,
		);
	}

	/**
	 * @param bool $includeDraft
	 * @param bool $includeError
	 * @return string[]
	 */
	private function defaultStatuses($includeDraft, $includeError)
	{
		$statuses = array_keys(SLN_Enum_BookingStatus::toArray());
		if ($includeDraft) {
			$statuses[] = SLN_Enum_BookingStatus::DRAFT;
			$statuses[] = 'draft';
		}
		if ($includeError) {
			$statuses[] = SLN_Enum_BookingStatus::ERROR;
		}

		return array_values(array_unique($statuses));
	}

	/**
	 * @param string $raw
	 * @return string
	 */
	private function normalizeStatusFilter($raw)
	{
		$v   = strtolower(trim($raw));
		$map = array(
			'draft'           => SLN_Enum_BookingStatus::DRAFT,
			'auto-draft'      => SLN_Enum_BookingStatus::DRAFT,
			'bozza'           => SLN_Enum_BookingStatus::DRAFT,
			'error'           => SLN_Enum_BookingStatus::ERROR,
			'errore'          => SLN_Enum_BookingStatus::ERROR,
			'sln-b-error'     => SLN_Enum_BookingStatus::ERROR,
			'pending'         => SLN_Enum_BookingStatus::PENDING,
			'confirmed'       => SLN_Enum_BookingStatus::CONFIRMED,
			'paid'            => SLN_Enum_BookingStatus::PAID,
			'canceled'        => SLN_Enum_BookingStatus::CANCELED,
			'cancelled'       => SLN_Enum_BookingStatus::CANCELED,
			'pendingpayment'  => SLN_Enum_BookingStatus::PENDING_PAYMENT,
			'pending_payment' => SLN_Enum_BookingStatus::PENDING_PAYMENT,
			'paylater'        => SLN_Enum_BookingStatus::PAY_LATER,
			'pay_later'       => SLN_Enum_BookingStatus::PAY_LATER,
		);
		if (isset($map[ $v ])) {
			return $map[ $v ];
		}
		$allowed = array_merge(array_keys(SLN_Enum_BookingStatus::toArray()), array(SLN_Enum_BookingStatus::DRAFT, SLN_Enum_BookingStatus::ERROR, 'draft'));
		if (in_array($raw, $allowed, true)) {
			return $raw;
		}

		return '';
	}

	/**
	 * @param string[] $statuses
	 * @param string   $dateFrom
	 * @param string   $dateTo
	 * @return int
	 */
	private function countWithStatuses(array $statuses, $dateFrom, $dateTo)
	{
		if (! $statuses) {
			return 0;
		}

		$args = array(
			'post_type'              => SLN_Plugin::POST_TYPE_BOOKING,
			'post_status'            => $statuses,
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$metaQuery = array('relation' => 'AND');
		if ($dateFrom !== '') {
			$metaQuery[] = array(
				'key'     => '_sln_booking_date',
				'value'   => $dateFrom,
				'compare' => '>=',
				'type'    => 'CHAR',
			);
		}
		if ($dateTo !== '') {
			$metaQuery[] = array(
				'key'     => '_sln_booking_date',
				'value'   => $dateTo,
				'compare' => '<=',
				'type'    => 'CHAR',
			);
		}
		if (count($metaQuery) > 1) {
			$args['meta_query'] = $metaQuery;
		}

		$query = new WP_Query($args);
		$count = (int) $query->found_posts;
		wp_reset_postdata();

		return $count;
	}

	/**
	 * @param string $dateFrom
	 * @param string $dateTo
	 * @param string $lang
	 * @return string
	 */
	private function periodLabel($dateFrom, $dateTo, $lang)
	{
		if ($dateFrom !== '' && $dateTo !== '' && $dateFrom === $dateTo) {
			return SLN_AI_Language::phrase(
				$lang,
				'count_bookings_on_day',
				__('on %s', 'salon-booking-system'),
				array($dateFrom)
			);
		}
		if ($dateFrom !== '' && $dateTo !== '') {
			return SLN_AI_Language::phrase(
				$lang,
				'count_bookings_between',
				__('from %1$s to %2$s', 'salon-booking-system'),
				array($dateFrom, $dateTo)
			);
		}
		if ($dateFrom === '' && $dateTo !== '') {
			return SLN_AI_Language::phrase(
				$lang,
				'count_bookings_until',
				__('up to %s', 'salon-booking-system'),
				array($dateTo)
			);
		}
		if ($dateFrom !== '' && $dateTo === '') {
			return SLN_AI_Language::phrase(
				$lang,
				'count_bookings_from',
				__('from %s onward', 'salon-booking-system'),
				array($dateFrom)
			);
		}

		return SLN_AI_Language::phrase(
			$lang,
			'count_bookings_all_time',
			__('in total', 'salon-booking-system')
		);
	}

	/**
	 * @param string $status
	 * @param string $lang
	 * @return string
	 */
	private function statusLabel($status, $lang = 'en')
	{
		if ($status === SLN_Enum_BookingStatus::DRAFT || $status === 'draft') {
			return SLN_AI_Language::phrase(
				$lang,
				'status_draft',
				__('Draft', 'salon-booking-system')
			);
		}
		if ($status === SLN_Enum_BookingStatus::ERROR) {
			return __('ERROR', 'salon-booking-system');
		}

		return SLN_Enum_BookingStatus::getLabel($status);
	}
}
