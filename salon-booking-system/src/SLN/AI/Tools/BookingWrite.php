<?php

/**
 * Confirm-first AI tools to create and update reservations (bookings).
 */
class SLN_AI_Tools_CreateBooking extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'create_booking';
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
		$mapped = SLN_AI_Tools_BookingWriteHelper::mapCreate($this->plugin, $arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$lang    = $mapped['lang'];
		$summary = SLN_AI_Tools_BookingWriteHelper::formatCreateSummary($this->plugin, $mapped, $lang);
		$warn    = SLN_AI_Tools_BookingWriteHelper::availabilityWarning($this->plugin, $mapped);
		if ($warn !== '') {
			$summary .= "\n\n" . $warn;
		}

		return array(
			'ok'        => true,
			'summary'   => $summary,
			'arguments' => $mapped,
			'tool'      => $this->getName(),
			'proposed'  => $mapped,
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		$mapped = SLN_AI_Tools_BookingWriteHelper::mapCreate($this->plugin, $arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		try {
			$bb = new SLN_Wrapper_Booking_Builder($this->plugin);
			$bb->emptyData();
			$bb->setDate($mapped['date']);
			$bb->setTime($mapped['time']);
			$bb->set('firstname', $mapped['customer_first_name']);
			$bb->set('lastname', $mapped['customer_last_name']);
			$bb->set('email', $mapped['customer_email']);
			$bb->set('phone', $mapped['customer_phone']);
			if ($mapped['note'] !== '') {
				$bb->set('note', $mapped['note']);
			}
			if ($mapped['admin_note'] !== '') {
				$bb->set('admin_note', $mapped['admin_note']);
			}
			$bb->setServicesAndAttendants($mapped['services_map']);

			$status = $mapped['status'] !== ''
				? $mapped['status']
				: SLN_Enum_BookingStatus::CONFIRMED;
			$bb->create($status);
			$booking = $bb->getLastBooking();
			if (! $booking) {
				return new WP_Error(
					'sln_ai_booking_create',
					__('Could not create the booking.', 'salon-booking-system')
				);
			}

			$this->refreshBookingCaches();
			$lang = $mapped['lang'];
			$id   = (int) $booking->getId();

			return array(
				'ok'      => true,
				'before'  => array('_created' => true, 'id' => $id),
				'after'   => array('id' => $id),
				'message' => SLN_AI_Language::phrase(
					$lang,
					'booking_created',
					__('Booking #%d created.', 'salon-booking-system'),
					array($id)
				) . ' [' . SLN_AI_Language::phrase(
					$lang,
					'open_booking',
					__('Open booking', 'salon-booking-system')
				) . '](' . admin_url('post.php?post=' . $id . '&action=edit') . ')',
			);
		} catch (Exception $e) {
			return new WP_Error('sln_ai_booking_create', $e->getMessage());
		}
	}

	/**
	 * @param mixed $previous
	 * @return array|WP_Error
	 */
	public function restore($previous)
	{
		if (! is_array($previous) || empty($previous['id'])) {
			return new WP_Error(
				'sln_ai_restore',
				__('Cannot undo booking create.', 'salon-booking-system')
			);
		}
		if (! empty($previous['_created'])) {
			wp_trash_post((int) $previous['id']);
			$this->refreshBookingCaches();
		}

		return array(
			'ok'      => true,
			'message' => __('Created booking moved to trash.', 'salon-booking-system'),
		);
	}
}

/**
 * Confirm-first: create several reservations (explicit dates[] or weekday recurrence).
 */
class SLN_AI_Tools_CreateBookings extends SLN_AI_Tools_Abstract
{
	const MAX_BATCH = 10;

	public function getName()
	{
		return 'create_bookings';
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
		$mapped = SLN_AI_Tools_BookingWriteHelper::mapCreateBatch($this->plugin, $arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$lang    = $mapped['lang'];
		$summary = SLN_AI_Tools_BookingWriteHelper::formatCreateBatchSummary($this->plugin, $mapped, $lang);
		$warns   = array();
		foreach ($mapped['dates'] as $date) {
			$check = $mapped;
			$check['date'] = $date;
			$w     = SLN_AI_Tools_BookingWriteHelper::availabilityWarning($this->plugin, $check);
			if ($w !== '' && ! in_array($w, $warns, true)) {
				$warns[] = $w;
			}
		}
		if ($warns) {
			$summary .= "\n\n" . implode("\n", $warns);
		}

		return array(
			'ok'        => true,
			'summary'   => $summary,
			'arguments' => $mapped,
			'tool'      => $this->getName(),
			'proposed'  => $mapped,
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		$mapped = SLN_AI_Tools_BookingWriteHelper::mapCreateBatch($this->plugin, $arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$ids    = array();
		$errors = array();
		$status = $mapped['status'] !== ''
			? $mapped['status']
			: SLN_Enum_BookingStatus::CONFIRMED;

		foreach ($mapped['dates'] as $date) {
			try {
				// Re-assign per date from the requested map (explicit assistants kept; empty ones filled).
				$baseMap  = ! empty($mapped['services_map_requested']) && is_array($mapped['services_map_requested'])
					? $mapped['services_map_requested']
					: $mapped['services_map'];
				$assigned = SLN_AI_Tools_BookingWriteHelper::assignAvailableAssistants(
					$this->plugin,
					$date,
					$mapped['time'],
					$baseMap
				);
				if (is_wp_error($assigned)) {
					$errors[] = $date . ' (' . $assigned->get_error_message() . ')';
					continue;
				}
				$servicesMap = $assigned['map'];

				$bb = new SLN_Wrapper_Booking_Builder($this->plugin);
				$bb->emptyData();
				$bb->setDate($date);
				$bb->setTime($mapped['time']);
				$bb->set('firstname', $mapped['customer_first_name']);
				$bb->set('lastname', $mapped['customer_last_name']);
				$bb->set('email', $mapped['customer_email']);
				$bb->set('phone', $mapped['customer_phone']);
				if ($mapped['note'] !== '') {
					$bb->set('note', $mapped['note']);
				}
				if ($mapped['admin_note'] !== '') {
					$bb->set('admin_note', $mapped['admin_note']);
				}
				$bb->setServicesAndAttendants($servicesMap);
				$bb->create($status);
				$booking = $bb->getLastBooking();
				if ($booking) {
					$ids[] = (int) $booking->getId();
				} else {
					$errors[] = $date;
				}
			} catch (Exception $e) {
				$errors[] = $date . ' (' . $e->getMessage() . ')';
			}
		}

		$this->refreshBookingCaches();
		$lang = $mapped['lang'];

		if (! $ids) {
			return new WP_Error(
				'sln_ai_bookings_create',
				__('Could not create any of the bookings.', 'salon-booking-system')
			);
		}

		$msg = SLN_AI_Language::phrase(
			$lang,
			'bookings_created',
			__('Created %d bookings: #%s.', 'salon-booking-system'),
			array(count($ids), implode(', #', $ids))
		);
		if ($errors) {
			$msg .= ' ' . SLN_AI_Language::phrase(
				$lang,
				'bookings_create_partial',
				__('Some dates failed: %s.', 'salon-booking-system'),
				array(implode(', ', $errors))
			);
		}

		return array(
			'ok'      => true,
			'before'  => array('_created_batch' => true, 'ids' => $ids),
			'after'   => array('ids' => $ids),
			'message' => $msg,
		);
	}

	/**
	 * @param mixed $previous
	 * @return array|WP_Error
	 */
	public function restore($previous)
	{
		if (! is_array($previous) || empty($previous['ids']) || ! is_array($previous['ids'])) {
			return new WP_Error(
				'sln_ai_restore',
				__('Cannot undo batch booking create.', 'salon-booking-system')
			);
		}
		if (! empty($previous['_created_batch'])) {
			foreach ($previous['ids'] as $id) {
				wp_trash_post((int) $id);
			}
			$this->refreshBookingCaches();
		}

		return array(
			'ok'      => true,
			'message' => __('Created bookings moved to trash.', 'salon-booking-system'),
		);
	}
}

/**
 * Confirm-first: patch an existing reservation.
 */
class SLN_AI_Tools_UpdateBooking extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'update_booking';
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
		$mapped = SLN_AI_Tools_BookingWriteHelper::mapUpdate($this->plugin, $arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$lang    = $mapped['lang'];
		$summary = SLN_AI_Tools_BookingWriteHelper::formatUpdateSummary($this->plugin, $mapped, $lang);
		$check   = array(
			'date'         => $mapped['date'] !== '' ? $mapped['date'] : $mapped['current']['date'],
			'time'         => $mapped['time'] !== '' ? $mapped['time'] : $mapped['current']['time'],
			'services_map' => ! empty($mapped['services_map'])
				? $mapped['services_map']
				: $mapped['current']['services_map'],
		);
		$warn = SLN_AI_Tools_BookingWriteHelper::availabilityWarning($this->plugin, $check);
		if ($warn !== '') {
			$summary .= "\n\n" . $warn;
		}

		return array(
			'ok'        => true,
			'summary'   => $summary,
			'arguments' => $mapped,
			'tool'      => $this->getName(),
			'current'   => $mapped['current'],
			'proposed'  => $mapped,
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		$mapped = SLN_AI_Tools_BookingWriteHelper::mapUpdate($this->plugin, $arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$id      = (int) $mapped['id'];
		$booking = $this->plugin->createBooking($id);
		$before  = SLN_AI_Tools_BookingWriteHelper::snapshotBooking($booking);

		$date = $mapped['date'] !== '' ? $mapped['date'] : $before['date'];
		$time = $mapped['time'] !== '' ? $mapped['time'] : $before['time'];
		$time = SLN_Func::filter($time, 'time');

		$first = $mapped['customer_first_name'] !== ''
			? $mapped['customer_first_name']
			: $before['customer_first_name'];
		$last  = $mapped['customer_last_name'] !== ''
			? $mapped['customer_last_name']
			: $before['customer_last_name'];
		$email = $mapped['customer_email'] !== ''
			? $mapped['customer_email']
			: $before['customer_email'];
		$phone = $mapped['customer_phone'] !== ''
			? $mapped['customer_phone']
			: $before['customer_phone'];

		$servicesMap = ! empty($mapped['services_map'])
			? $mapped['services_map']
			: $before['services_map'];

		try {
			$bb = new SLN_Wrapper_Booking_Builder($this->plugin);
			$bb->emptyData();
			$bb->setDate($date);
			$bb->setTime($time);
			$bb->setServicesAndAttendants($servicesMap);
			$servicesMeta = $bb->getBookingServices()->toArrayRecursive();

			$name     = trim($first . ' ' . $last);
			$datetime = $this->plugin->format()->datetime($bb->getDateTime());
			$args     = array(
				'ID'         => $id,
				'post_title' => $name . ' - ' . $datetime,
				'meta_input' => array(
					'_sln_booking_date'      => $date,
					'_sln_booking_time'      => $time,
					'_sln_booking_firstname' => $first,
					'_sln_booking_lastname'  => $last,
					'_sln_booking_email'     => $email,
					'_sln_booking_phone'     => $phone,
					'_sln_booking_services'  => $servicesMeta,
				),
			);
			if ($mapped['note'] !== '') {
				$args['meta_input']['_sln_booking_note'] = $mapped['note'];
			}
			if ($mapped['admin_note'] !== '') {
				$args['meta_input']['_sln_booking_admin_note'] = $mapped['admin_note'];
			}

			$updated = wp_update_post($args, true);
			if (is_wp_error($updated)) {
				return $updated;
			}

			clean_post_cache($id);
			$booking = $this->plugin->createBooking($id);
			$booking->evalBookingServices();
			$booking->evalTotal();
			$booking->evalDuration();

			if ($mapped['status'] !== '' && $mapped['status'] !== $before['status']) {
				$booking->setStatus($mapped['status']);
			}

			$this->refreshBookingCaches();
			$lang = $mapped['lang'];

			return array(
				'ok'      => true,
				'before'  => $before,
				'after'   => SLN_AI_Tools_BookingWriteHelper::snapshotBooking($booking),
				'message' => SLN_AI_Language::phrase(
					$lang,
					'booking_updated',
					__('Booking #%d updated.', 'salon-booking-system'),
					array($id)
				),
			);
		} catch (Exception $e) {
			return new WP_Error('sln_ai_booking_update', $e->getMessage());
		}
	}

	/**
	 * @param mixed $previous
	 * @return array|WP_Error
	 */
	public function restore($previous)
	{
		if (! is_array($previous) || empty($previous['id'])) {
			return new WP_Error(
				'sln_ai_restore',
				__('Cannot undo booking update.', 'salon-booking-system')
			);
		}

		$id = (int) $previous['id'];
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => isset($previous['post_title']) ? $previous['post_title'] : get_the_title($id),
				'meta_input' => array(
					'_sln_booking_date'      => isset($previous['date']) ? $previous['date'] : '',
					'_sln_booking_time'      => isset($previous['time']) ? $previous['time'] : '',
					'_sln_booking_firstname' => isset($previous['customer_first_name']) ? $previous['customer_first_name'] : '',
					'_sln_booking_lastname'  => isset($previous['customer_last_name']) ? $previous['customer_last_name'] : '',
					'_sln_booking_email'     => isset($previous['customer_email']) ? $previous['customer_email'] : '',
					'_sln_booking_phone'     => isset($previous['customer_phone']) ? $previous['customer_phone'] : '',
					'_sln_booking_services'  => isset($previous['services_meta']) ? $previous['services_meta'] : array(),
					'_sln_booking_note'      => isset($previous['note']) ? $previous['note'] : '',
					'_sln_booking_admin_note'=> isset($previous['admin_note']) ? $previous['admin_note'] : '',
				),
			),
			true
		);

		if (! empty($previous['status'])) {
			$booking = $this->plugin->createBooking($id);
			$booking->setStatus($previous['status']);
		}

		$this->refreshBookingCaches();

		return array(
			'ok'      => true,
			'message' => __('Booking update undone.', 'salon-booking-system'),
		);
	}
}

/**
 * Shared mapping / formatting for create_booking and update_booking.
 */
class SLN_AI_Tools_BookingWriteHelper
{
	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $arguments
	 * @return array|WP_Error
	 */
	public static function mapCreate(SLN_Plugin $plugin, array $arguments)
	{
		$lang = SLN_AI_Language::detect(
			isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : ''
		);

		$date = isset($arguments['date']) ? sanitize_text_field((string) $arguments['date']) : '';
		$time = isset($arguments['time']) ? sanitize_text_field((string) $arguments['time']) : '';
		if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return new WP_Error(
				'sln_ai_booking_date',
				__('Booking date is required (Y-m-d).', 'salon-booking-system')
			);
		}
		if ($time === '') {
			return new WP_Error(
				'sln_ai_booking_time',
				__('Booking time is required (H:i).', 'salon-booking-system')
			);
		}
		$time = self::normalizeTime($time);
		if ($time === '') {
			return new WP_Error(
				'sln_ai_booking_time',
				__('Booking time must look like 10:00 or 10:00 AM.', 'salon-booking-system')
			);
		}

		$services = self::resolveServices($plugin, $arguments);
		if (is_wp_error($services)) {
			return $services;
		}
		if (! $services['map']) {
			return new WP_Error(
				'sln_ai_booking_services',
				__('At least one service is required (service_id / service_name).', 'salon-booking-system')
			);
		}

		// Keep pre-assign map so batch create can re-pick per date (explicit assistants stay).
		$requestedMap = $services['map'];
		$assigned     = self::assignAvailableAssistants($plugin, $date, $time, $requestedMap);
		if (is_wp_error($assigned)) {
			return $assigned;
		}

		$customer = self::mapCustomer($arguments, true);
		if (is_wp_error($customer)) {
			return $customer;
		}

		// Enforce the salon's required checkout fields (email is required by
		// default): ask the merchant for missing data instead of creating a
		// booking with blanks the admin form itself would reject.
		$missingFields = self::missingRequiredCustomerFields($customer);
		if ($missingFields) {
			return new WP_Error(
				'sln_ai_booking_customer_fields',
				SLN_AI_Language::phrase(
					$lang,
					'booking_need_fields',
					__('To create the booking I also need: %s. Could you provide it?', 'salon-booking-system'),
					array(self::describeCustomerFields($missingFields, $lang))
				)
			);
		}

		$status = self::normalizeStatus(
			isset($arguments['status']) ? (string) $arguments['status'] : ''
		);

		return array_merge(
			$customer,
			array(
				'lang'                   => $lang,
				'date'                   => $date,
				'time'                   => $time,
				'services_map'           => $assigned['map'],
				'services_map_requested' => $requestedMap,
				'services_label'         => $assigned['label'] !== ''
					? $assigned['label']
					: $services['label'],
				'status'                 => $status,
				'note'                   => isset($arguments['note'])
					? sanitize_textarea_field((string) $arguments['note'])
					: '',
				'admin_note'             => isset($arguments['admin_note'])
					? sanitize_textarea_field((string) $arguments['admin_note'])
					: '',
			)
		);
	}

	/**
	 * Map batch create: dates[] and/or recurrence { weekday, count }.
	 *
	 * @param SLN_Plugin $plugin
	 * @param array      $arguments
	 * @return array|WP_Error
	 */
	public static function mapCreateBatch(SLN_Plugin $plugin, array $arguments)
	{
		$base = $arguments;
		// Seed mapCreate with a placeholder date; we replace dates afterward.
		if (empty($base['date'])) {
			$base['date'] = wp_date('Y-m-d');
		}
		$mapped = self::mapCreate($plugin, $base);
		if (is_wp_error($mapped)) {
			return $mapped;
		}

		$dates = self::resolveBatchDates($arguments);
		if (is_wp_error($dates)) {
			return $dates;
		}

		$mapped['dates']      = $dates;
		$mapped['date']       = $dates[0];
		$mapped['recurrence'] = isset($arguments['recurrence']) && is_array($arguments['recurrence'])
			? $arguments['recurrence']
			: null;

		return $mapped;
	}

	/**
	 * @param array $arguments
	 * @return string[]|WP_Error Y-m-d list
	 */
	public static function resolveBatchDates(array $arguments)
	{
		$dates = array();
		if (! empty($arguments['dates']) && is_array($arguments['dates'])) {
			foreach ($arguments['dates'] as $d) {
				$d = sanitize_text_field((string) $d);
				if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
					$dates[] = $d;
				}
			}
		}

		if (! $dates && ! empty($arguments['recurrence']) && is_array($arguments['recurrence'])) {
			$rec   = $arguments['recurrence'];
			$count = isset($rec['count']) ? absint($rec['count']) : 0;
			$wd    = isset($rec['weekday']) ? $rec['weekday'] : (isset($rec['day']) ? $rec['day'] : '');
			$from  = ! empty($rec['from']) ? sanitize_text_field((string) $rec['from']) : '';
			$iso   = self::normalizeWeekdayToIso($wd);
			if (! $iso || $count < 1) {
				return new WP_Error(
					'sln_ai_booking_recurrence',
					__('Recurrence needs weekday (monday…sunday or 1–7) and count.', 'salon-booking-system')
				);
			}
			if ($count > SLN_AI_Tools_CreateBookings::MAX_BATCH) {
				$count = SLN_AI_Tools_CreateBookings::MAX_BATCH;
			}
			$dates = self::expandNextWeekdays($iso, $count, $from);
		}

		// Single date still allowed via date=.
		if (! $dates && ! empty($arguments['date'])) {
			$d = sanitize_text_field((string) $arguments['date']);
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
				$dates[] = $d;
			}
		}

		$dates = array_values(array_unique($dates));
		if (count($dates) < 2) {
			return new WP_Error(
				'sln_ai_booking_batch',
				__('create_bookings needs at least 2 dates (dates[] or recurrence). For one booking use create_booking.', 'salon-booking-system')
			);
		}
		if (count($dates) > SLN_AI_Tools_CreateBookings::MAX_BATCH) {
			$dates = array_slice($dates, 0, SLN_AI_Tools_CreateBookings::MAX_BATCH);
		}

		return $dates;
	}

	/**
	 * Next $count occurrences of ISO weekday (1=Mon … 7=Sun), including today if it matches.
	 *
	 * @param int    $isoWeekday
	 * @param int    $count
	 * @param string $from Y-m-d or empty
	 * @return string[]
	 */
	public static function expandNextWeekdays($isoWeekday, $count, $from = '')
	{
		try {
			$tz = wp_timezone();
			$d  = $from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
				? new DateTimeImmutable($from, $tz)
				: new DateTimeImmutable('today', $tz);
		} catch (Exception $e) {
			$d = new DateTimeImmutable('today');
		}

		$isoWeekday = (int) $isoWeekday;
		$out        = array();
		$guard      = 0;
		while (count($out) < $count && $guard < 400) {
			if ((int) $d->format('N') === $isoWeekday) {
				$out[] = $d->format('Y-m-d');
			}
			$d = $d->modify('+1 day');
			$guard++;
		}

		return $out;
	}

	/**
	 * @param mixed $weekday name, alias, or 1–7 (ISO Mon–Sun)
	 * @return int 0 if unknown
	 */
	public static function normalizeWeekdayToIso($weekday)
	{
		if (is_numeric($weekday)) {
			$n = (int) $weekday;
			// Accept 1–7 ISO; also map WP day keys 0/1=Sun… if someone passes 0.
			if ($n >= 1 && $n <= 7) {
				return $n;
			}

			return 0;
		}
		$w = SLN_AI_Language::fold(strtolower(trim((string) $weekday)));
		$map = array(
			'monday'    => 1,
			'mon'       => 1,
			'lunedi'    => 1,
			'lunes'     => 1,
			'lundi'     => 1,
			'montag'    => 1,
			'segunda'   => 1,
			'tuesday'   => 2,
			'tue'       => 2,
			'martedi'   => 2,
			'martes'    => 2,
			'mardi'     => 2,
			'dienstag'  => 2,
			'terca'     => 2,
			'wednesday' => 3,
			'wed'       => 3,
			'mercoledi' => 3,
			'miercoles' => 3,
			'mercredi'  => 3,
			'mittwoch'  => 3,
			'quarta'    => 3,
			'thursday'  => 4,
			'thu'       => 4,
			'giovedi'   => 4,
			'jueves'    => 4,
			'jeudi'     => 4,
			'donnerstag'=> 4,
			'quinta'    => 4,
			'friday'    => 5,
			'fri'       => 5,
			'venerdi'   => 5,
			'viernes'   => 5,
			'vendredi'  => 5,
			'freitag'   => 5,
			'sexta'     => 5,
			'saturday'  => 6,
			'sat'       => 6,
			'sabato'    => 6,
			'sabado'    => 6,
			'samedi'    => 6,
			'samstag'   => 6,
			'sunday'    => 7,
			'sun'       => 7,
			'domenica'  => 7,
			'domingo'   => 7,
			'dimanche'  => 7,
			'sonntag'   => 7,
		);

		return isset($map[ $w ]) ? $map[ $w ] : 0;
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $mapped
	 * @param string     $lang
	 * @return string
	 */
	public static function formatCreateBatchSummary(SLN_Plugin $plugin, array $mapped, $lang)
	{
		$header = SLN_AI_Language::phrase(
			$lang,
			'create_bookings_summary',
			__('Create %d bookings:', 'salon-booking-system'),
			array(count($mapped['dates']))
		);
		$customer = trim($mapped['customer_first_name'] . ' ' . $mapped['customer_last_name']);
		$status   = $mapped['status'] !== ''
			? SLN_Enum_BookingStatus::getLabel($mapped['status'])
			: SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::CONFIRMED);

		$lines   = array();
		$lines[] = $header;
		$lines[] = '';
		$lines[] = $customer;
		if ($mapped['customer_email'] !== '') {
			$lines[] = $mapped['customer_email'];
		}
		$lines[] = $mapped['services_label'];
		$lines[] = $mapped['time'];
		$lines[] = $status;
		$lines[] = '';
		$lines[] = SLN_AI_Language::phrase($lang, 'booking_dates', __('Dates:', 'salon-booking-system'));
		foreach ($mapped['dates'] as $date) {
			$lines[] = '  • ' . self::lineWhen($plugin, $date, $mapped['time']);
		}

		return implode("\n", $lines);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $arguments
	 * @return array|WP_Error
	 */
	public static function mapUpdate(SLN_Plugin $plugin, array $arguments)
	{
		$lang = SLN_AI_Language::detect(
			isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : ''
		);

		$id = isset($arguments['id']) ? absint($arguments['id']) : 0;
		if (! $id) {
			return new WP_Error(
				'sln_ai_booking_id',
				__('Booking id is required to update a reservation.', 'salon-booking-system')
			);
		}

		$post = get_post($id);
		if (! $post || $post->post_type !== SLN_Plugin::POST_TYPE_BOOKING) {
			return new WP_Error(
				'sln_ai_booking_missing',
				sprintf(
					/* translators: %d: booking id */
					__('No booking with id %d.', 'salon-booking-system'),
					$id
				)
			);
		}

		$booking = $plugin->createBooking($id);
		$current = self::snapshotBooking($booking);

		$date = '';
		if (! empty($arguments['date'])) {
			$date = sanitize_text_field((string) $arguments['date']);
			if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				return new WP_Error(
					'sln_ai_booking_date',
					__('Booking date must be Y-m-d.', 'salon-booking-system')
				);
			}
		}

		$time = '';
		if (! empty($arguments['time'])) {
			$time = self::normalizeTime((string) $arguments['time']);
			if ($time === '') {
				return new WP_Error(
					'sln_ai_booking_time',
					__('Booking time must look like 10:00 or 10:00 AM.', 'salon-booking-system')
				);
			}
		}

		$servicesMap   = array();
		$servicesLabel = '';
		if (! empty($arguments['services']) || ! empty($arguments['service_id']) || ! empty($arguments['service_name'])) {
			$services = self::resolveServices($plugin, $arguments);
			if (is_wp_error($services)) {
				return $services;
			}
			$servicesMap   = $services['map'];
			$servicesLabel = $services['label'];
		}

		$customer = self::mapCustomer($arguments, false);
		if (is_wp_error($customer)) {
			return $customer;
		}

		$status = self::normalizeStatus(
			isset($arguments['status']) ? (string) $arguments['status'] : ''
		);

		$hasChange = $date !== '' || $time !== '' || $servicesMap || $status !== ''
			|| $customer['customer_first_name'] !== ''
			|| $customer['customer_last_name'] !== ''
			|| $customer['customer_email'] !== ''
			|| $customer['customer_phone'] !== ''
			|| (isset($arguments['note']) && (string) $arguments['note'] !== '')
			|| (isset($arguments['admin_note']) && (string) $arguments['admin_note'] !== '');

		if (! $hasChange) {
			return new WP_Error(
				'sln_ai_booking_noop',
				__('Nothing to update — provide date, time, services, customer fields, or status.', 'salon-booking-system')
			);
		}

		return array_merge(
			$customer,
			array(
				'lang'           => $lang,
				'id'             => $id,
				'date'           => $date,
				'time'           => $time,
				'services_map'   => $servicesMap,
				'services_label' => $servicesLabel,
				'status'         => $status,
				'note'           => isset($arguments['note'])
					? sanitize_textarea_field((string) $arguments['note'])
					: '',
				'admin_note'     => isset($arguments['admin_note'])
					? sanitize_textarea_field((string) $arguments['admin_note'])
					: '',
				'current'        => $current,
			)
		);
	}

	/**
	 * @param SLN_Wrapper_Booking $booking
	 * @return array
	 */
	public static function snapshotBooking(SLN_Wrapper_Booking $booking)
	{
		$id   = (int) $booking->getId();
		$date = (string) $booking->getMeta('date');
		$time = (string) $booking->getMeta('time');
		if ($time !== '') {
			$time = SLN_Func::filter($time, 'time');
		}

		$map   = array();
		$label = array();
		try {
			foreach ($booking->getBookingServices()->getItems() as $item) {
				$svc = $item->getService();
				if (! $svc) {
					continue;
				}
				$sid = (int) $svc->getId();
				$aid = 0;
				$att = $item->getAttendant();
				if ($att && ! is_array($att)) {
					$aid = (int) $att->getId();
				}
				$map[ $sid ] = $aid;
				$label[]     = $svc->getName() . ($aid ? ' (#' . $aid . ')' : '');
			}
		} catch (Exception $e) {
			$map = array();
		}

		$servicesMeta = $booking->getMeta('services');
		if (! is_array($servicesMeta)) {
			$servicesMeta = array();
		}

		return array(
			'id'                   => $id,
			'post_title'           => get_the_title($id),
			'status'               => (string) $booking->getStatus(),
			'date'                 => $date,
			'time'                 => $time,
			'customer_first_name'  => (string) $booking->getFirstname(),
			'customer_last_name'   => (string) $booking->getLastname(),
			'customer_email'       => (string) $booking->getEmail(),
			'customer_phone'       => (string) $booking->getPhone(),
			'services_map'         => $map,
			'services_label'       => implode(', ', $label),
			'services_meta'        => $servicesMeta,
			'note'                 => (string) $booking->getMeta('note'),
			'admin_note'           => (string) $booking->getMeta('admin_note'),
			'customer'             => (string) $booking->getDisplayName(),
		);
	}

	/**
	 * Soft availability check — warning text, never blocks confirm.
	 *
	 * @param SLN_Plugin $plugin
	 * @param array      $mapped
	 * @return string
	 */
	public static function availabilityWarning(SLN_Plugin $plugin, array $mapped)
	{
		if (empty($mapped['date']) || empty($mapped['time']) || empty($mapped['services_map'])) {
			return '';
		}
		try {
			$bb = new SLN_Wrapper_Booking_Builder($plugin);
			$bb->emptyData();
			$bb->setDate($mapped['date']);
			$bb->setTime($mapped['time']);
			$bb->setServicesAndAttendants($mapped['services_map']);
			if (! $bb->isValid()) {
				return __('Warning: this slot may be unavailable with current rules. You can still confirm as an admin override.', 'salon-booking-system');
			}
		} catch (Exception $e) {
			return '';
		}

		return '';
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $mapped
	 * @param string     $lang
	 * @return string
	 */
	public static function formatCreateSummary(SLN_Plugin $plugin, array $mapped, $lang)
	{
		$when = $plugin->format()->datetime(
			new SLN_DateTime($mapped['date'] . ' ' . $mapped['time'], SLN_DateTime::getWpTimezone())
		);
		$status = $mapped['status'] !== ''
			? SLN_Enum_BookingStatus::getLabel($mapped['status'])
			: SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::CONFIRMED);
		$customer = trim($mapped['customer_first_name'] . ' ' . $mapped['customer_last_name']);

		$header = SLN_AI_Language::phrase(
			$lang,
			'create_booking_summary',
			__('Create booking:', 'salon-booking-system')
		);

		$lines   = array();
		$lines[] = $header;
		$lines[] = '';
		$lines[] = $when;
		$lines[] = $customer;
		if ($mapped['customer_email'] !== '') {
			$lines[] = $mapped['customer_email'];
		}
		if ($mapped['customer_phone'] !== '') {
			$lines[] = $mapped['customer_phone'];
		}
		$lines[] = $mapped['services_label'];
		$lines[] = $status;

		return implode("\n", $lines);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $mapped
	 * @param string     $lang
	 * @return string
	 */
	public static function formatUpdateSummary(SLN_Plugin $plugin, array $mapped, $lang)
	{
		$cur    = $mapped['current'];
		$header = SLN_AI_Language::phrase(
			$lang,
			'update_booking_summary',
			__('Update booking #%d:', 'salon-booking-system'),
			array((int) $mapped['id'])
		);

		$lines   = array();
		$lines[] = $header;
		$lines[] = '';
		$lines[] = SLN_AI_Language::phrase($lang, 'booking_current', __('Current:', 'salon-booking-system'));
		$lines[] = self::lineWhen($plugin, $cur['date'], $cur['time']);
		$lines[] = $cur['customer'];
		$lines[] = $cur['services_label'];
		$lines[] = SLN_Enum_BookingStatus::getLabel($cur['status']);
		$lines[] = '';
		$lines[] = SLN_AI_Language::phrase($lang, 'booking_proposed', __('Proposed:', 'salon-booking-system'));

		$date = $mapped['date'] !== '' ? $mapped['date'] : $cur['date'];
		$time = $mapped['time'] !== '' ? $mapped['time'] : $cur['time'];
		$lines[] = self::lineWhen($plugin, $date, $time);

		$first = $mapped['customer_first_name'] !== '' ? $mapped['customer_first_name'] : $cur['customer_first_name'];
		$last  = $mapped['customer_last_name'] !== '' ? $mapped['customer_last_name'] : $cur['customer_last_name'];
		$lines[] = trim($first . ' ' . $last);

		if ($mapped['services_label'] !== '') {
			$lines[] = $mapped['services_label'];
		} else {
			$lines[] = $cur['services_label'];
		}

		if ($mapped['status'] !== '') {
			$lines[] = SLN_Enum_BookingStatus::getLabel($mapped['status']);
		}

		return implode("\n", $lines);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param string     $date
	 * @param string     $time
	 * @return string
	 */
	private static function lineWhen(SLN_Plugin $plugin, $date, $time)
	{
		if ($date === '' || $time === '') {
			return trim($date . ' ' . $time);
		}
		try {
			return $plugin->format()->datetime(
				new SLN_DateTime($date . ' ' . $time, SLN_DateTime::getWpTimezone())
			);
		} catch (Exception $e) {
			return trim($date . ' ' . $time);
		}
	}

	/**
	 * Checkout-field keys the salon marks required but the request left blank.
	 *
	 * Mirrors the admin booking metabox validation (e.g. email required by
	 * default via SLN_Enum_CheckoutFields) for the fields the AI schema carries.
	 *
	 * @param array $customer Output of mapCustomer().
	 * @return string[] Checkout field keys (firstname|lastname|email|phone).
	 */
	private static function missingRequiredCustomerFields(array $customer)
	{
		if (! class_exists('SLN_Enum_CheckoutFields')) {
			return array();
		}

		$map = array(
			'firstname' => 'customer_first_name',
			'lastname'  => 'customer_last_name',
			'email'     => 'customer_email',
			'phone'     => 'customer_phone',
		);

		$missing = array();
		foreach ($map as $fieldKey => $argKey) {
			$field = SLN_Enum_CheckoutFields::getField($fieldKey);
			if (! $field || ! $field->isRequiredNotHidden()) {
				continue;
			}
			if (! isset($customer[ $argKey ]) || $customer[ $argKey ] === '') {
				$missing[] = $fieldKey;
			}
		}

		return $missing;
	}

	/**
	 * Human list of checkout fields in the merchant's language.
	 *
	 * @param string[] $fieldKeys
	 * @param string   $lang
	 * @return string
	 */
	private static function describeCustomerFields(array $fieldKeys, $lang)
	{
		$labels = array(
			'firstname' => array('field_first_name', __('the customer\'s first name', 'salon-booking-system')),
			'lastname'  => array('field_last_name', __('the customer\'s last name', 'salon-booking-system')),
			'email'     => array('field_email', __('the customer\'s email address', 'salon-booking-system')),
			'phone'     => array('field_phone', __('the customer\'s mobile phone', 'salon-booking-system')),
		);

		$parts = array();
		foreach ($fieldKeys as $key) {
			if (! isset($labels[ $key ])) {
				continue;
			}
			list($phraseKey, $fallback) = $labels[ $key ];
			$parts[] = SLN_AI_Language::phrase($lang, $phraseKey, $fallback);
		}

		return implode(', ', $parts);
	}

	/**
	 * @param array $arguments
	 * @param bool  $required
	 * @return array|WP_Error
	 */
	private static function mapCustomer(array $arguments, $required)
	{
		$first = isset($arguments['customer_first_name'])
			? sanitize_text_field((string) $arguments['customer_first_name'])
			: '';
		$last  = isset($arguments['customer_last_name'])
			? sanitize_text_field((string) $arguments['customer_last_name'])
			: '';
		$email = isset($arguments['customer_email'])
			? sanitize_email((string) $arguments['customer_email'])
			: '';
		$phone = isset($arguments['customer_phone'])
			? sanitize_text_field((string) $arguments['customer_phone'])
			: '';

		if ($first === '' && ! empty($arguments['customer_name'])) {
			$parts = preg_split('/\s+/', trim((string) $arguments['customer_name']), 2);
			$first = sanitize_text_field($parts[0]);
			if (isset($parts[1])) {
				$last = sanitize_text_field($parts[1]);
			}
		}

		if ($required && $first === '' && $email === '') {
			return new WP_Error(
				'sln_ai_booking_customer',
				__('Customer first name or email is required.', 'salon-booking-system')
			);
		}

		return array(
			'customer_first_name' => $first,
			'customer_last_name'  => $last,
			'customer_email'      => $email,
			'customer_phone'      => $phone,
		);
	}

	/**
	 * When assistant selection is enabled, fill missing attendants using the same
	 * Availability::addAttendantForServices() rules as frontend auto-assign / Google import.
	 *
	 * @param SLN_Plugin $plugin
	 * @param string     $date Y-m-d
	 * @param string     $time H:i
	 * @param array      $servicesMap service_id => attendant_id|attendant_ids[]
	 * @return array{map:array,label:string}|WP_Error
	 */
	public static function assignAvailableAssistants(SLN_Plugin $plugin, $date, $time, array $servicesMap)
	{
		$label = self::servicesLabelFromMap($plugin, $servicesMap);

		if (! $plugin->getSettings()->isAttendantsEnabled() || ! $servicesMap) {
			return array(
				'map'   => $servicesMap,
				'label' => $label,
			);
		}

		$needsAssign = false;
		foreach ($servicesMap as $sid => $aid) {
			$service = $plugin->createService((int) $sid);
			if (! $service || $service->isEmpty() || ! $service->isAttendantsEnabled()) {
				continue;
			}
			if (self::isAttendantSlotEmpty($aid)) {
				$needsAssign = true;
				break;
			}
			if (
				$service->isMultipleAttendantsForServiceEnabled()
				&& is_array($aid)
				&& count($aid) < (int) $service->getCountMultipleAttendants()
			) {
				$needsAssign = true;
				break;
			}
		}

		if (! $needsAssign) {
			return array(
				'map'   => $servicesMap,
				'label' => $label,
			);
		}

		try {
			$startsAt = new SLN_DateTime(
				$date . ' ' . $time,
				SLN_DateTime::getWpTimezone()
			);
			$bookingServices = SLN_Wrapper_Booking_Services::build($servicesMap, $startsAt);
			$ah              = $plugin->getAvailabilityHelper();
			// AI Setup is admin-side: keep assistants hidden from frontend eligible (like admin calendar).
			$ah->setExcludeHiddenFromFrontend(false);
			$ah->setDate($startsAt);
			$ah->addAttendantForServices($bookingServices);

			$newMap = array();
			foreach ($bookingServices->getItems() as $bookingService) {
				$service = $bookingService->getService();
				if (! $service) {
					continue;
				}
				$sid = (int) $service->getId();
				$att = $bookingService->getAttendant();
				if (is_array($att)) {
					$ids = array();
					foreach ($att as $a) {
						if ($a && method_exists($a, 'getId')) {
							$ids[] = (int) $a->getId();
						}
					}
					$newMap[ $sid ] = $ids;
				} elseif ($att && method_exists($att, 'getId')) {
					$newMap[ $sid ] = (int) $att->getId();
				} else {
					$newMap[ $sid ] = 0;
				}

				if ($service->isAttendantsEnabled() && self::isAttendantSlotEmpty($newMap[ $sid ])) {
					return new WP_Error(
						'sln_ai_booking_assistant',
						sprintf(
							/* translators: %s: service name */
							__('No assistants available for %s at that time. Choose another slot or name an available assistant.', 'salon-booking-system'),
							$service->getName()
						)
					);
				}
			}

			return array(
				'map'   => $newMap,
				'label' => self::servicesLabelFromMap($plugin, $newMap),
			);
		} catch (Exception $e) {
			return new WP_Error(
				'sln_ai_booking_assistant',
				$e->getMessage()
			);
		}
	}

	/**
	 * @param mixed $aid
	 * @return bool
	 */
	private static function isAttendantSlotEmpty($aid)
	{
		if ($aid === false || $aid === null || $aid === '' || $aid === 0 || $aid === '0') {
			return true;
		}
		if (is_array($aid)) {
			return count(array_filter($aid)) === 0;
		}

		return false;
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $servicesMap
	 * @return string
	 */
	private static function servicesLabelFromMap(SLN_Plugin $plugin, array $servicesMap)
	{
		$parts = array();
		foreach ($servicesMap as $sid => $aid) {
			$service = $plugin->createService((int) $sid);
			if (! $service || $service->isEmpty()) {
				continue;
			}
			$name = $service->getName();
			if (is_array($aid)) {
				$names = array();
				foreach ($aid as $id) {
					$att = $plugin->createAttendant((int) $id);
					if ($att && ! $att->isEmpty()) {
						$names[] = $att->getName();
					}
				}
				if ($names) {
					$name .= ' · ' . implode(', ', $names);
				}
			} elseif (! self::isAttendantSlotEmpty($aid)) {
				$att = $plugin->createAttendant((int) $aid);
				if ($att && ! $att->isEmpty()) {
					$name .= ' · ' . $att->getName();
				}
			}
			$parts[] = $name;
		}

		return implode(', ', $parts);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $arguments
	 * @return array|WP_Error {map: int=>int, label: string}
	 */
	private static function resolveServices(SLN_Plugin $plugin, array $arguments)
	{
		// Preview stores a resolved map; apply must reuse it (names/ids are not re-sent).
		if (! empty($arguments['services_map']) && is_array($arguments['services_map'])) {
			$map = array();
			foreach ($arguments['services_map'] as $sid => $aid) {
				$sid = absint($sid);
				if (! $sid) {
					continue;
				}
				if (is_array($aid)) {
					$map[ $sid ] = array_values(array_filter(array_map('absint', $aid)));
				} else {
					$map[ $sid ] = absint($aid);
				}
			}
			if ($map) {
				return array(
					'map'   => $map,
					'label' => isset($arguments['services_label'])
						? sanitize_text_field((string) $arguments['services_label'])
						: '',
				);
			}
		}

		$rows = array();
		if (! empty($arguments['services']) && is_array($arguments['services'])) {
			$rows = $arguments['services'];
		} else {
			$row = array();
			if (! empty($arguments['service_id'])) {
				$row['service_id'] = absint($arguments['service_id']);
			}
			if (! empty($arguments['service_name'])) {
				$row['service_name'] = sanitize_text_field((string) $arguments['service_name']);
			}
			if (! empty($arguments['assistant_id']) || ! empty($arguments['attendant_id'])) {
				$row['assistant_id'] = absint(
					! empty($arguments['assistant_id'])
						? $arguments['assistant_id']
						: $arguments['attendant_id']
				);
			}
			if (! empty($arguments['assistant_name']) || ! empty($arguments['attendant_name'])) {
				$row['assistant_name'] = sanitize_text_field(
					(string) (! empty($arguments['assistant_name'])
						? $arguments['assistant_name']
						: $arguments['attendant_name'])
				);
			}
			if ($row) {
				$rows[] = $row;
			}
		}

		$map   = array();
		$label = array();
		foreach ($rows as $row) {
			if (! is_array($row)) {
				continue;
			}
			$service = self::resolveService($plugin, $row);
			if (is_wp_error($service)) {
				return $service;
			}
			$sid       = (int) $service->getId();
			$assistant = self::resolveAssistant($plugin, $row);
			if (is_wp_error($assistant)) {
				return $assistant;
			}
			$aid         = $assistant ? (int) $assistant->getId() : 0;
			$map[ $sid ] = $aid;
			$label[]     = $service->getName() . ($assistant ? ' · ' . $assistant->getName() : '');
		}

		return array(
			'map'   => $map,
			'label' => implode(', ', $label),
		);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $row
	 * @return SLN_Wrapper_ServiceInterface|WP_Error
	 */
	private static function resolveService(SLN_Plugin $plugin, array $row)
	{
		if (! empty($row['service_id'])) {
			$svc = $plugin->createService((int) $row['service_id']);
			if ($svc && ! $svc->isEmpty()) {
				return $svc;
			}
		}
		$name = '';
		if (! empty($row['service_name'])) {
			$name = (string) $row['service_name'];
		} elseif (! empty($row['name'])) {
			$name = (string) $row['name'];
		}
		if ($name === '') {
			return new WP_Error(
				'sln_ai_booking_service',
				__('Service id or name is required.', 'salon-booking-system')
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_SERVICE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				's'              => $name,
			)
		);
		$needle = mb_strtolower(trim($name), 'UTF-8');
		foreach ($posts as $p) {
			if (mb_strtolower($p->post_title, 'UTF-8') === $needle) {
				return $plugin->createService((int) $p->ID);
			}
		}
		foreach ($posts as $p) {
			if (strpos(mb_strtolower($p->post_title, 'UTF-8'), $needle) !== false) {
				return $plugin->createService((int) $p->ID);
			}
		}

		return new WP_Error(
			'sln_ai_booking_service',
			sprintf(
				/* translators: %s: service name */
				__('Service “%s” not found.', 'salon-booking-system'),
				$name
			)
		);
	}

	/**
	 * @param SLN_Plugin $plugin
	 * @param array      $row
	 * @return SLN_Wrapper_AttendantInterface|null|WP_Error
	 */
	private static function resolveAssistant(SLN_Plugin $plugin, array $row)
	{
		$aid = 0;
		if (! empty($row['assistant_id'])) {
			$aid = absint($row['assistant_id']);
		} elseif (! empty($row['attendant_id'])) {
			$aid = absint($row['attendant_id']);
		}
		if ($aid) {
			$att = $plugin->createAttendant($aid);
			if ($att && ! $att->isEmpty()) {
				return $att;
			}

			return new WP_Error(
				'sln_ai_booking_assistant',
				sprintf(
					/* translators: %d: assistant id */
					__('Assistant #%d not found.', 'salon-booking-system'),
					$aid
				)
			);
		}

		$name = '';
		if (! empty($row['assistant_name'])) {
			$name = (string) $row['assistant_name'];
		} elseif (! empty($row['attendant_name'])) {
			$name = (string) $row['attendant_name'];
		}
		if ($name === '') {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_ATTENDANT,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				's'              => $name,
			)
		);
		$needle = mb_strtolower(trim($name), 'UTF-8');
		foreach ($posts as $p) {
			if (mb_strtolower($p->post_title, 'UTF-8') === $needle) {
				return $plugin->createAttendant((int) $p->ID);
			}
		}
		foreach ($posts as $p) {
			if (strpos(mb_strtolower($p->post_title, 'UTF-8'), $needle) !== false) {
				return $plugin->createAttendant((int) $p->ID);
			}
		}

		return new WP_Error(
			'sln_ai_booking_assistant',
			sprintf(
				/* translators: %s: assistant name */
				__('Assistant “%s” not found.', 'salon-booking-system'),
				$name
			)
		);
	}

	/**
	 * @param string $time
	 * @return string H:i or empty
	 */
	private static function normalizeTime($time)
	{
		$time = trim((string) $time);
		if (preg_match('/^(\d{1,2}):(\d{2})\s*(am|pm)?$/i', $time, $m)) {
			$h = (int) $m[1];
			$i = (int) $m[2];
			if (! empty($m[3])) {
				$ap = strtolower($m[3]);
				if ($ap === 'pm' && $h < 12) {
					$h += 12;
				}
				if ($ap === 'am' && $h === 12) {
					$h = 0;
				}
			}
			if ($h > 23 || $i > 59) {
				return '';
			}

			return sprintf('%02d:%02d', $h, $i);
		}

		return '';
	}

	/**
	 * @param string $status
	 * @return string normalized status or ''
	 */
	private static function normalizeStatus($status)
	{
		$status = strtolower(trim((string) $status));
		if ($status === '') {
			return '';
		}
		$map = array(
			'confirmed'       => SLN_Enum_BookingStatus::CONFIRMED,
			'confirm'         => SLN_Enum_BookingStatus::CONFIRMED,
			'pending'         => SLN_Enum_BookingStatus::PENDING,
			'paid'            => SLN_Enum_BookingStatus::PAID,
			'canceled'        => SLN_Enum_BookingStatus::CANCELED,
			'cancelled'       => SLN_Enum_BookingStatus::CANCELED,
			'pay_later'       => SLN_Enum_BookingStatus::PAY_LATER,
			'paylater'        => SLN_Enum_BookingStatus::PAY_LATER,
			'pending_payment' => SLN_Enum_BookingStatus::PENDING_PAYMENT,
			'error'           => SLN_Enum_BookingStatus::ERROR,
			'sln-b-confirmed' => SLN_Enum_BookingStatus::CONFIRMED,
			'sln-b-pending'   => SLN_Enum_BookingStatus::PENDING,
			'sln-b-paid'      => SLN_Enum_BookingStatus::PAID,
			'sln-b-canceled'  => SLN_Enum_BookingStatus::CANCELED,
			'sln-b-paylater'  => SLN_Enum_BookingStatus::PAY_LATER,
			'sln-b-pendingpayment' => SLN_Enum_BookingStatus::PENDING_PAYMENT,
			'sln-b-error'     => SLN_Enum_BookingStatus::ERROR,
		);
		if (isset($map[ $status ])) {
			return $map[ $status ];
		}
		$labels = SLN_Enum_BookingStatus::toArray();
		if (isset($labels[ $status ])) {
			return $status;
		}

		return '';
	}
}
