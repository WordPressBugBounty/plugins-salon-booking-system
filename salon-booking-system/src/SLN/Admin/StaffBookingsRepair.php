<?php
// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Finds bookings tied to a staff account instead of their customer and moves
 * them to the right one. Only the booking's owner changes: its details, the
 * staff profiles and the notifications already sent stay as they are.
 */
class SLN_Admin_StaffBookingsRepair
{
	const NONCE_ACTION = 'sln_staff_bookings_repair';
	const MAX_ROWS     = 200;

	const ACTION_REASSIGN = 'reassign';
	const ACTION_GUEST    = 'guest';
	const ACTION_REVIEW   = 'review';

	/**
	 * Run what the Tools page form asked for.
	 *
	 * @return array{report: array|null, done: int|null, error: string}
	 */
	public static function handleRequest()
	{
		$ret = array('report' => null, 'done' => null, 'error' => '');

		if (!isset($_POST['sln_staff_repair_scan']) && !isset($_POST['sln_staff_repair_apply'])) {
			return $ret;
		}

		$nonce = isset($_POST['sln_staff_repair_nonce']) ? sanitize_text_field(wp_unslash($_POST['sln_staff_repair_nonce'])) : '';
		if (!current_user_can('manage_salon_settings')) {
			$ret['error'] = __('You do not have permission to run this tool.', 'salon-booking-system');
		} elseif (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			$ret['error'] = __('Security check failed. Submit the form again.', 'salon-booking-system');
		} else {
			if (isset($_POST['sln_staff_repair_apply'])) {
				$ids         = isset($_POST['sln_staff_repair_ids']) ? array_map('absint', (array) wp_unslash($_POST['sln_staff_repair_ids'])) : array();
				$ret['done'] = self::repair($ids);
			}
			$ret['report'] = self::scan();
		}

		return $ret;
	}

	/**
	 * @return array{rows: array[], total: int}
	 */
	public static function scan()
	{
		$rows = array();
		foreach (self::getCandidates() as $candidate) {
			$row = self::classify($candidate);
			if ($row) {
				$rows[] = $row;
			}
		}

		return array(
			'rows'  => array_slice($rows, 0, self::MAX_ROWS),
			'total' => count($rows),
		);
	}

	/**
	 * The classification is repeated here so the submitted IDs are never trusted.
	 *
	 * @param int[] $booking_ids
	 * @return int Bookings repaired.
	 */
	public static function repair(array $booking_ids)
	{
		$repaired    = 0;
		$booking_ids = array_map('absint', $booking_ids);
		foreach (self::getCandidates() as $candidate) {
			if (!in_array((int) $candidate['id'], $booking_ids, true)) {
				continue;
			}

			$row = self::classify($candidate);
			if (!$row || self::ACTION_REVIEW === $row['action']) {
				continue;
			}

			$result = wp_update_post(array('ID' => $row['id'], 'post_author' => $row['customer_id']), true);
			if (is_wp_error($result)) {
				SLN_Plugin::addLog(sprintf('[Staff bookings repair] booking #%d failed: %s', $row['id'], $result->get_error_message()));
				continue;
			}

			update_post_meta($row['id'], '_sln_booking_repaired_from_user_id', $row['staff_id']);
			SLN_Plugin::getInstance()->createBooking($row['id'])->evalCustomerDetails();
			SLN_Plugin::addLog(sprintf('[Staff bookings repair] booking #%d moved from user #%d to #%d', $row['id'], $row['staff_id'], $row['customer_id']));
			$repaired++;
		}

		return $repaired;
	}

	/**
	 * Bookings owned by a staff account, or guest bookings carrying a staff email.
	 *
	 * @return array[]
	 */
	private static function getCandidates()
	{
		global $wpdb;

		$staff_ids    = self::getStaffIds();
		$staff_emails = array();
		foreach ($staff_ids as $staff_id) {
			$staff_emails[] = get_userdata($staff_id)->user_email;
		}
		if (!$staff_ids) {
			return array();
		}

		$type = SLN_Plugin::POST_TYPE_BOOKING;
		$args = array_merge(
			array("_{$type}_email", "_{$type}_firstname", "_{$type}_lastname", "_{$type}_date", $type),
			$staff_ids,
			$staff_emails
		);

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_author AS author, email.meta_value AS email,
					first.meta_value AS firstname, last.meta_value AS lastname, day.meta_value AS date
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} email ON email.post_id = p.ID AND email.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} first ON first.post_id = p.ID AND first.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} last ON last.post_id = p.ID AND last.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} day ON day.post_id = p.ID AND day.meta_key = %s
				WHERE p.post_type = %s
					AND p.post_status NOT IN ('auto-draft', 'trash')
					AND (
						p.post_author IN (" . implode(',', array_fill(0, count($staff_ids), '%d')) . ")
						OR (p.post_author = 0 AND email.meta_value IN (" . implode(',', array_fill(0, count($staff_emails), '%s')) . "))
					)
				ORDER BY day.meta_value DESC, p.ID DESC",
				$args
			),
			ARRAY_A
		);
	}

	/**
	 * @return int[]
	 */
	private static function getStaffIds()
	{
		global $wpdb;

		$ids     = get_users(array('capability' => 'manage_salon', 'fields' => 'ID'));
		$authors = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = %s AND post_author > 0",
				SLN_Plugin::POST_TYPE_BOOKING
			)
		);

		$ids = array_unique(array_map('intval', array_merge($ids, $authors)));

		return array_values(array_filter($ids, array('SLN_Wrapper_Customer', 'isStaff')));
	}

	/**
	 * @param array $candidate
	 * @return array|null Null when the booking really is the staff member's own.
	 */
	private static function classify(array $candidate)
	{
		$email = trim((string) $candidate['email']);
		$name  = trim($candidate['firstname'] . ' ' . $candidate['lastname']);
		$staff = $candidate['author'] ? get_userdata($candidate['author']) : get_user_by('email', $email);

		if (!$staff) {
			return null;
		}

		$row = array(
			'id'          => (int) $candidate['id'],
			'date'        => $candidate['date'],
			'name'        => $name,
			'email'       => $email,
			'staff_id'    => (int) $staff->ID,
			'staff_name'  => $staff->display_name,
			'customer_id' => 0,
			'customer'    => '',
			'action'      => self::ACTION_REVIEW,
		);

		$is_staff_email = '' === $email || 0 === strcasecmp($email, $staff->user_email);

		if ($is_staff_email) {
			$staff_name = trim($staff->first_name . ' ' . $staff->last_name);

			return 0 === strcasecmp($name, $staff_name) ? null : $row;
		}

		// Only owned bookings reach this point, guests matched on the staff email.
		$customer = get_user_by('email', $email);
		if ($customer && !SLN_Wrapper_Customer::isStaff($customer)) {
			$row['action']      = self::ACTION_REASSIGN;
			$row['customer_id'] = (int) $customer->ID;
			$row['customer']    = trim($customer->first_name . ' ' . $customer->last_name);
		} else {
			$row['action'] = self::ACTION_GUEST;
		}

		return $row;
	}
}
