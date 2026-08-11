<?php

/**
 * Guidance-only: list / look up salon customers (WP users with sln_customer role).
 */
class SLN_AI_Tools_FindCustomer extends SLN_AI_Tools_Abstract
{
	const MAX_RESULTS = 25;

	public function getName()
	{
		return 'find_customer';
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
		$lang   = SLN_AI_Language::detect(
			isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : ''
		);
		$mapped = $this->mapArguments($arguments);
		$hits   = array();

		if (! empty($mapped['id'])) {
			$row = $this->formatById((int) $mapped['id']);
			if ($row) {
				$hits[] = $row;
			}
		}

		if (! $hits) {
			$hits = $this->search($mapped);
		}

		$listUrl = admin_url('admin.php?page=salon-customers');

		if (! $hits) {
			$hint = ! empty($mapped['id'])
				? SLN_AI_Language::phrase(
					$lang,
					'no_customer_id',
					__('No customer found with id %d.', 'salon-booking-system'),
					array((int) $mapped['id'])
				)
				: SLN_AI_Language::phrase(
					$lang,
					'no_customers',
					__('No customers matched. Try an id, name, email, or phone — or list with no filters.', 'salon-booking-system')
				);
			$hint .= "\n\n[" . SLN_AI_Language::phrase(
				$lang,
				'open_customers',
				__('Open Customers', 'salon-booking-system')
			) . '](' . $listUrl . ')';

			return array(
				'ok'        => true,
				'guidance'  => true,
				'summary'   => $hint,
				'tool'      => $this->getName(),
				'tier'      => 'guidance',
				'customers' => array(),
			);
		}

		$openLabel = SLN_AI_Language::phrase(
			$lang,
			'open_customer',
			__('Open customer', 'salon-booking-system')
		);
		$count  = count($hits);
		$header = $count === 1
			? SLN_AI_Language::phrase(
				$lang,
				'found_customers_one',
				__('Found 1 customer:', 'salon-booking-system')
			)
			: SLN_AI_Language::phrase(
				$lang,
				'found_customers_many',
				__('Found %d customers:', 'salon-booking-system'),
				array($count)
			);

		$lines   = array();
		$lines[] = $header;
		$lines[] = '';
		foreach ($hits as $row) {
			$lines[] = sprintf(
				'#%d — %s%s',
				$row['id'],
				$row['name'] !== '' ? $row['name'] : '(no name)',
				$row['email'] !== '' ? ' — ' . $row['email'] : ''
			);
			if ($row['phone'] !== '') {
				$lines[] = '  ' . $row['phone'];
			}
			if ($row['bookings_count'] !== null) {
				$lines[] = '  ' . sprintf(
					/* translators: %d: booking count */
					__('Bookings: %d', 'salon-booking-system'),
					(int) $row['bookings_count']
				);
			}
			if (! empty($row['edit_url'])) {
				$lines[] = '  [' . $openLabel . '](' . $row['edit_url'] . ')';
			}
			$lines[] = '';
		}

		$lines[] = '[' . SLN_AI_Language::phrase(
			$lang,
			'open_customers',
			__('Open Customers', 'salon-booking-system')
		) . '](' . $listUrl . ')';

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => trim(implode("\n", $lines)),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'customers' => $hits,
			'arguments' => $mapped,
		);
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function apply(array $arguments)
	{
		return new WP_Error(
			'sln_ai_guidance_only',
			__('find_customer is read-only guidance; nothing to apply.', 'salon-booking-system')
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
			__('Customer lookup cannot be undone.', 'salon-booking-system')
		);
	}

	/**
	 * Count only for ContextPack (no PII dump).
	 *
	 * @return int
	 */
	public static function countForContext()
	{
		$q = new WP_User_Query(
			array(
				'role'   => SLN_Plugin::USER_ROLE_CUSTOMER,
				'number' => 1,
				'fields' => 'ID',
				'count_total' => true,
			)
		);

		return (int) $q->get_total();
	}

	/**
	 * @param array $arguments
	 * @return array
	 */
	private function mapArguments(array $arguments)
	{
		$query = '';
		if (isset($arguments['query'])) {
			$query = sanitize_text_field((string) $arguments['query']);
		} elseif (isset($arguments['search'])) {
			$query = sanitize_text_field((string) $arguments['search']);
		}
		foreach (array('name', 'email', 'phone') as $k) {
			if ($query === '' && ! empty($arguments[ $k ])) {
				$query = sanitize_text_field((string) $arguments[ $k ]);
			}
		}

		return array(
			'id'    => isset($arguments['id']) ? absint($arguments['id']) : 0,
			'query' => $query,
			'name'  => isset($arguments['name']) ? sanitize_text_field((string) $arguments['name']) : '',
			'email' => isset($arguments['email']) ? sanitize_email((string) $arguments['email']) : '',
			'phone' => isset($arguments['phone']) ? sanitize_text_field((string) $arguments['phone']) : '',
		);
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	private function formatById($id)
	{
		$user = get_userdata($id);
		if (! $user) {
			return null;
		}
		if (! in_array(SLN_Plugin::USER_ROLE_CUSTOMER, (array) $user->roles, true)
			&& ! user_can($user, 'manage_salon')
		) {
			// Still show if they have booking author history as guest-linked user.
			$customer = new SLN_Wrapper_Customer($user, false);
			if ($customer->isEmpty()) {
				return null;
			}
		}

		return $this->formatUser($user);
	}

	/**
	 * @param array $mapped
	 * @return array[]
	 */
	private function search(array $mapped)
	{
		$email = $mapped['email'];
		$phone = $mapped['phone'];
		$query = $mapped['query'];
		$name  = $mapped['name'];

		if ($email !== '') {
			$user = get_user_by('email', $email);
			if ($user) {
				$row = $this->formatUser($user);
				return $row ? array($row) : array();
			}
		}

		if ($phone !== '' || ($query !== '' && preg_match('/^\+?[\d\s\-()]{6,}$/', $query))) {
			$phoneQ = $phone !== '' ? $phone : preg_replace('/\s+/', '', $query);
			$users  = get_users(
				array(
					'role'       => SLN_Plugin::USER_ROLE_CUSTOMER,
					'number'     => self::MAX_RESULTS,
					'meta_query' => array(
						array(
							'key'     => '_sln_phone',
							'value'   => $phoneQ,
							'compare' => 'LIKE',
						),
					),
				)
			);
			$out = array();
			foreach ($users as $u) {
				$row = $this->formatUser($u);
				if ($row) {
					$out[] = $row;
				}
			}
			if ($out) {
				return $out;
			}
		}

		$args = array(
			'role'   => SLN_Plugin::USER_ROLE_CUSTOMER,
			'number' => self::MAX_RESULTS,
			'orderby'=> 'display_name',
			'order'  => 'ASC',
		);

		$search = $query !== '' ? $query : $name;
		if ($search !== '') {
			$args['search']         = '*' . esc_attr($search) . '*';
			$args['search_columns'] = array('user_login', 'user_email', 'display_name', 'user_nicename');
		}

		$users = get_users($args);
		$out   = array();
		$seen  = array();

		foreach ($users as $u) {
			$seen[ $u->ID ] = true;
			$row            = $this->formatUser($u);
			if ($row) {
				$out[] = $row;
			}
		}

		// Also match first/last name meta when WP search misses.
		if ($search !== '' && count($out) < self::MAX_RESULTS) {
			$byMeta = get_users(
				array(
					'role'       => SLN_Plugin::USER_ROLE_CUSTOMER,
					'number'     => self::MAX_RESULTS,
					'meta_query' => array(
						'relation' => 'OR',
						array(
							'key'     => 'first_name',
							'value'   => $search,
							'compare' => 'LIKE',
						),
						array(
							'key'     => 'last_name',
							'value'   => $search,
							'compare' => 'LIKE',
						),
					),
				)
			);
			foreach ($byMeta as $u) {
				if (isset($seen[ $u->ID ])) {
					continue;
				}
				$seen[ $u->ID ] = true;
				$row            = $this->formatUser($u);
				if ($row) {
					$out[] = $row;
				}
				if (count($out) >= self::MAX_RESULTS) {
					break;
				}
			}
		}

		return $out;
	}

	/**
	 * @param WP_User $user
	 * @return array|null
	 */
	private function formatUser($user)
	{
		if (! $user || ! $user->ID) {
			return null;
		}

		$customer = new SLN_Wrapper_Customer($user, false);
		$name     = '';
		$count    = null;
		if (! $customer->isEmpty()) {
			$name  = (string) $customer->getName();
			$count = (int) $customer->getCountOfReservations();
		}
		if ($name === '') {
			$name = trim($user->first_name . ' ' . $user->last_name);
		}
		if ($name === '') {
			$name = (string) $user->display_name;
		}

		$phone = (string) get_user_meta($user->ID, '_sln_phone', true);
		$email = (string) $user->user_email;

		return array(
			'id'             => (int) $user->ID,
			'name'           => $name,
			'email'          => $email,
			'phone'          => $phone,
			'bookings_count' => $count,
			'edit_url'       => admin_url('admin.php?page=salon-customers&id=' . (int) $user->ID),
		);
	}
}
