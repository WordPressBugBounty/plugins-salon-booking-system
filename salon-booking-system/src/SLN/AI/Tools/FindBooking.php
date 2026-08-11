<?php

/**
 * Guidance-only: look up bookings by id / customer fields, including draft and ERROR.
 */
class SLN_AI_Tools_FindBooking extends SLN_AI_Tools_Abstract
{
	const MAX_RESULTS = 15;

	public function getName()
	{
		return 'find_booking';
	}

	public function getTier()
	{
		return 'guidance';
	}

	/**
	 * Statuses AI search must include (broader than availability / front-end calendars).
	 *
	 * @return string[]
	 */
	public static function searchableStatuses()
	{
		$statuses = array_keys(SLN_Enum_BookingStatus::toArray());
		$statuses[] = SLN_Enum_BookingStatus::DRAFT; // auto-draft
		$statuses[] = 'draft';
		$statuses[] = SLN_Enum_BookingStatus::ERROR;

		return array_values(array_unique($statuses));
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

		$lang = isset($mapped['lang']) ? $mapped['lang'] : 'en';

		$hits = array();
		if (! empty($mapped['id'])) {
			$hit = $this->loadById((int) $mapped['id'], $lang);
			if ($hit) {
				$hits[] = $hit;
			}
		}

		if (! $hits && ($mapped['query'] !== '' || $mapped['date'] !== '' || $mapped['date_from'] !== '' || $mapped['date_to'] !== '')) {
			$hits = $this->searchByQuery($mapped, $lang);
		}

		if (! $hits && empty($mapped['id']) && $mapped['query'] === '' && ! empty($mapped['status'])) {
			$hits = $this->searchByQuery($mapped, $lang);
		}

		if (! $hits) {
			$hint = ! empty($mapped['id'])
				? SLN_AI_Language::phrase(
					$lang,
					'no_booking_id',
					__('No booking found with id %d (checked draft, ERROR, and all salon statuses).', 'salon-booking-system'),
					array((int) $mapped['id'])
				)
				: SLN_AI_Language::phrase(
					$lang,
					'no_bookings',
					__('No bookings matched. Try an id, customer name, email, or phone — draft and ERROR bookings are included.', 'salon-booking-system')
				);

			return array(
				'ok'       => true,
				'guidance' => true,
				'summary'  => $hint,
				'tool'     => $this->getName(),
				'tier'     => 'guidance',
				'bookings' => array(),
			);
		}

		$openLabel = SLN_AI_Language::phrase(
			$lang,
			'open_booking',
			__('Open booking', 'salon-booking-system')
		);
		$count     = count($hits);
		$header    = $count === 1
			? SLN_AI_Language::phrase(
				$lang,
				'found_bookings_one',
				__('Found 1 booking:', 'salon-booking-system')
			)
			: SLN_AI_Language::phrase(
				$lang,
				'found_bookings_many',
				__('Found %d bookings:', 'salon-booking-system'),
				array($count)
			);

		$lines   = array();
		$lines[] = $header;
		$lines[] = '';
		foreach ($hits as $row) {
			$lines[] = sprintf(
				'#%d — %s — %s — %s%s',
				$row['id'],
				$row['status_label'],
				$row['when'],
				$row['customer'],
				$row['services'] !== '' ? ' — ' . $row['services'] : ''
			);
			if (! empty($row['email']) || ! empty($row['phone'])) {
				$lines[] = '  ' . trim($row['email'] . ' ' . $row['phone']);
			}
			if (! empty($row['edit_url'])) {
				// Markdown so the UI shows a clear label with a correct href (incl. &action=edit).
				$lines[] = '  [' . $openLabel . '](' . $row['edit_url'] . ')';
			}
			$lines[] = '';
		}
		$lines[] = SLN_AI_Language::phrase(
			$lang,
			'bookings_include',
			__('Draft and ERROR statuses are included in this search.', 'salon-booking-system')
		);

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => trim(implode("\n", $lines)),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'bookings'  => $hits,
			'arguments' => $mapped,
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
			__('Booking lookup cannot be undone.', 'salon-booking-system')
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	private function mapArguments(array $arguments)
	{
		$userMessage = isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : '';
		$lang        = SLN_AI_Language::detect($userMessage !== '' ? $userMessage : (isset($arguments['query']) ? (string) $arguments['query'] : ''));

		$id    = isset($arguments['id']) ? absint($arguments['id']) : 0;
		$query = '';
		if (isset($arguments['query'])) {
			$query = sanitize_text_field((string) $arguments['query']);
		} elseif (isset($arguments['search'])) {
			$query = sanitize_text_field((string) $arguments['search']);
		}
		foreach (array('name', 'email', 'phone', 'customer') as $k) {
			if ($query === '' && ! empty($arguments[ $k ])) {
				$query = sanitize_text_field((string) $arguments[ $k ]);
			}
		}

		$status = '';
		if (! empty($arguments['status'])) {
			$status = $this->normalizeStatusFilter((string) $arguments['status']);
		}

		$date      = '';
		$dateFrom  = '';
		$dateTo    = '';
		if (! empty($arguments['date'])) {
			$date = sanitize_text_field((string) $arguments['date']);
			if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				return new WP_Error(
					'sln_ai_booking_date',
					__('date must be Y-m-d when provided.', 'salon-booking-system')
				);
			}
		}
		if (! empty($arguments['date_from'])) {
			$dateFrom = sanitize_text_field((string) $arguments['date_from']);
		}
		if (! empty($arguments['date_to'])) {
			$dateTo = sanitize_text_field((string) $arguments['date_to']);
		}

		// Natural “this month” / “questo mese” from user utterance or query.
		$monthHint = $userMessage . ' ' . $query;
		if (($dateFrom === '' || $dateTo === '') && preg_match(
			'/\b(this\s+month|questo\s+mese|este\s+mes|ce\s+mois|diesen\s+monat|este\s+mes)\b/i',
			SLN_AI_Language::fold($monthHint)
		)) {
			$tz       = wp_timezone();
			$now      = new DateTimeImmutable('now', $tz);
			$dateFrom = $now->modify('first day of this month')->format('Y-m-d');
			$dateTo   = $now->modify('last day of this month')->format('Y-m-d');
			// Drop non-identifying filler from query so meta LIKE doesn't filter everything out.
			$query = trim(preg_replace(
				'/\b(trovami|trova|cerca|find|search|any|qualsiasi|prenotazione|prenotazioni|booking|bookings|appointment|di|del|questo|mese|this|month|mostrar|reserva)\b/iu',
				' ',
				$query
			));
			$query = trim(preg_replace('/\s+/', ' ', $query));
		}

		if (! $id && $query === '' && $status === '' && $date === '' && $dateFrom === '' && $dateTo === '') {
			return new WP_Error(
				'sln_ai_booking_query',
				__('Pass booking id, or a search query (name/email/phone), and/or status=error|draft.', 'salon-booking-system')
			);
		}

		return array(
			'id'        => $id,
			'query'     => $query,
			'status'    => $status,
			'date'      => $date,
			'date_from' => $dateFrom,
			'date_to'   => $dateTo,
			'lang'      => $lang,
		);
	}

	/**
	 * @param string $raw
	 * @return string post_status or ''
	 */
	private function normalizeStatusFilter($raw)
	{
		$v = strtolower(trim($raw));
		$map = array(
			'draft'             => SLN_Enum_BookingStatus::DRAFT,
			'auto-draft'        => SLN_Enum_BookingStatus::DRAFT,
			'bozza'             => SLN_Enum_BookingStatus::DRAFT,
			'error'             => SLN_Enum_BookingStatus::ERROR,
			'errore'            => SLN_Enum_BookingStatus::ERROR,
			'sln-b-error'       => SLN_Enum_BookingStatus::ERROR,
			'pending'           => SLN_Enum_BookingStatus::PENDING,
			'confirmed'         => SLN_Enum_BookingStatus::CONFIRMED,
			'paid'              => SLN_Enum_BookingStatus::PAID,
			'canceled'          => SLN_Enum_BookingStatus::CANCELED,
			'cancelled'         => SLN_Enum_BookingStatus::CANCELED,
			'pendingpayment'    => SLN_Enum_BookingStatus::PENDING_PAYMENT,
			'pending_payment'   => SLN_Enum_BookingStatus::PENDING_PAYMENT,
			'paylater'          => SLN_Enum_BookingStatus::PAY_LATER,
			'pay_later'         => SLN_Enum_BookingStatus::PAY_LATER,
		);
		if (isset($map[ $v ])) {
			return $map[ $v ];
		}
		$allowed = self::searchableStatuses();
		if (in_array($raw, $allowed, true)) {
			return $raw;
		}

		return '';
	}

	/**
	 * @param int    $id
	 * @param string $lang
	 * @return array|null
	 */
	private function loadById($id, $lang = 'en')
	{
		$post = get_post($id);
		if (! $post || $post->post_type !== SLN_Plugin::POST_TYPE_BOOKING) {
			return null;
		}
		if ($post->post_status === 'trash') {
			return null;
		}

		return $this->formatBooking(new SLN_Wrapper_Booking($post), $lang);
	}

	/**
	 * @param array  $mapped
	 * @param string $lang
	 * @return array[]
	 */
	private function searchByQuery(array $mapped, $lang = 'en')
	{
		$statuses = self::searchableStatuses();
		if (! empty($mapped['status'])) {
			$statuses = array($mapped['status']);
			if ($mapped['status'] === SLN_Enum_BookingStatus::DRAFT) {
				$statuses = array(SLN_Enum_BookingStatus::DRAFT, 'draft');
			}
		}

		$args = array(
			'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
			'post_status'    => $statuses,
			'posts_per_page' => self::MAX_RESULTS,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$metaQuery = array('relation' => 'AND');
		if ($mapped['date'] !== '') {
			$metaQuery[] = array(
				'key'     => '_sln_booking_date',
				'value'   => $mapped['date'],
				'compare' => '=',
			);
		} else {
			if ($mapped['date_from'] !== '') {
				$metaQuery[] = array(
					'key'     => '_sln_booking_date',
					'value'   => $mapped['date_from'],
					'compare' => '>=',
				);
			}
			if ($mapped['date_to'] !== '') {
				$metaQuery[] = array(
					'key'     => '_sln_booking_date',
					'value'   => $mapped['date_to'],
					'compare' => '<=',
				);
			}
		}

		if ($mapped['query'] !== '') {
			$parts = preg_split('/\s+/', $mapped['query']);
			$parts = array_values(array_filter(array_map('sanitize_text_field', $parts)));
			foreach ($parts as $part) {
				$or = array('relation' => 'OR');
				foreach (array('_sln_booking_email', '_sln_booking_firstname', '_sln_booking_lastname', '_sln_booking_phone') as $key) {
					$or[] = array(
						'key'     => $key,
						'value'   => $part,
						'compare' => 'LIKE',
					);
				}
				$metaQuery[] = $or;
			}
		}

		if (count($metaQuery) > 1) {
			$args['meta_query'] = $metaQuery;
		}

		$query = new WP_Query($args);
		$out   = array();
		foreach ($query->posts as $post) {
			$out[] = $this->formatBooking(new SLN_Wrapper_Booking($post), $lang);
		}
		wp_reset_postdata();

		if (! $out && $mapped['query'] !== '' && ctype_digit($mapped['query'])) {
			$byId = $this->loadById((int) $mapped['query'], $lang);
			if ($byId) {
				$out[] = $byId;
			}
		}

		return $out;
	}

	/**
	 * @param SLN_Wrapper_Booking $booking
	 * @param string              $lang
	 * @return array
	 */
	private function formatBooking(SLN_Wrapper_Booking $booking, $lang = 'en')
	{
		$id     = (int) $booking->getId();
		$status = (string) $booking->getStatus();
		$when   = SLN_AI_Language::phrase(
			$lang,
			'no_date',
			__('(no date)', 'salon-booking-system')
		);
		try {
			$starts = $booking->getStartsAt();
			if ($starts) {
				$when = $this->plugin->format()->datetime($starts);
			}
		} catch (Exception $e) {
			// Incomplete draft/error bookings may lack date/time meta.
		}

		$services = array();
		try {
			foreach ($booking->getBookingServices()->getItems() as $item) {
				$svc = $item->getService();
				if ($svc) {
					$services[] = $svc->getName();
				}
			}
		} catch (Exception $e) {
			$services = array();
		}

		$email = '';
		$phone = '';
		try {
			$email = (string) $booking->getEmail();
			$phone = (string) $booking->getPhone();
		} catch (Exception $e) {
			// ignore
		}

		return array(
			'id'           => $id,
			'status'       => $status,
			'status_label' => $this->statusLabel($status, $lang),
			'when'         => $when,
			'customer'     => (string) $booking->getDisplayName(),
			'email'        => $email,
			'phone'        => $phone,
			'services'     => implode(', ', $services),
			// Prefer admin_url so the host matches the current WordPress site (Local, staging, prod).
			'edit_url'     => admin_url('post.php?post=' . $id . '&action=edit'),
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
