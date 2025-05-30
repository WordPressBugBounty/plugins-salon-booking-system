<?php
// phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
use SLB_API_Mobile\Helper\UserRoleHelper;


if (!class_exists('WP_Users_List_Table')) {
	_get_list_table('WP_Users_List_Table');
}

use SLN_Plugin;
class SLN_Admin_Customers_List extends WP_Users_List_Table {

    public $hide_email;
    public $hide_phone;
	/**
	 * SLN_Admin_Customers_List constructor.
	 */
	public function __construct($args = array()) {
		parent::__construct($args);
        $user_role_helper = new UserRoleHelper();
        $this->hide_email = $user_role_helper->is_hide_customer_phone();
        $this->hide_phone = $user_role_helper->is_hide_customer_email();
		add_filter('manage_users_custom_column', array($this, 'manage_users_custom_column'), 10, 3);
	}

	public function get_columns() {
		$columns = array(
			'cb'             => '<input type="checkbox" />',
			'ID'             => __('Customer ID', 'salon-booking-system'),
			'first_name'     => __('First Name', 'salon-booking-system'),
			'last_name'      => __('Last Name', 'salon-booking-system'),
			'user_email'     => __('E-mail', 'salon-booking-system'),
			'_sln_phone'     => __('Telephone', 'salon-booking-system'),
			'total_bookings' => __('Total Reservations', 'salon-booking-system'),
			'total_amount'   => __('Customer Value', 'salon-booking-system'),
		);

        return apply_filters('sln.admin_customers_list.columns', $columns);
	}

	protected function get_sortable_columns() {
		$c = array(
			'user_email' => 'user_email',
		);

		return $c;
	}

	public function manage_users_custom_column($empty, $column_name, $user_id) {

		$user_object = get_userdata((int) $user_id);
		$customer_object = new SLN_Wrapper_Customer($user_object);
		switch ($column_name) {
			case 'total_bookings':
				$html = $customer_object->getCountOfReservations();
				break;
			case 'total_amount':
				$html = SLN_Plugin::getInstance()->format()->money($customer_object->getAmountOfReservations(), false);
				break;
            case 'user_email':
                if($this->hide_email){
                    $html = '*******';
                } else {
                    $html = esc_html($user_object->get($column_name));
                }
                break;
            case '_sln_phone':
                if($this->hide_phone){
                    $html = '*******';
                } else {
                    $html = esc_html($user_object->get($column_name));
                }
                break;
			case 'first_name':
			case 'last_name':

            case 'ID':
				$link = esc_url( add_query_arg( 'wp_http_referer', urlencode( esc_url(wp_unslash( $_SERVER['REQUEST_URI']) ) ), SLN_Admin_Customers::get_edit_customer_link($user_id) ) );
				$html = '<strong><a href="' . $link . '">' . esc_html($user_object->get($column_name)) . '</a></strong><br />';
				break;
			default:
				$html = esc_html($user_object->get($column_name));
		}

		return $html;
	}

	public function prepare_items() {
		global $role, $usersearch, $wpdb;

		$args = array();

		$role   = '%'.SLN_Plugin::USER_ROLE_CUSTOMER.'%';
		$args[] = $role;

		$join   = '';
		$where  = '';

		$usersearch = !empty($_REQUEST['s']) ? '%'.wp_unslash(trim(sanitize_text_field(wp_unslash($_REQUEST['s'])))).'%' : '';
		if (!empty($usersearch)) {
			$join = "INNER JOIN {$wpdb->usermeta} AS usermeta2 ON ( users.ID = usermeta2.user_id )";

			$where = "AND
				    (
				        usermeta2.meta_key = 'first_name' AND usermeta2.meta_value LIKE %s
				        OR
				        usermeta2.meta_key = 'last_name' AND usermeta2.meta_value LIKE %s
						OR
						usermeta2.meta_key = '_sln_phone' AND usermeta2.meta_value LIKE %s
				        OR
				        users.user_email LIKE %s";

			$args[] = $usersearch;
			$args[] = $usersearch;
			$args[] = $usersearch;
			$args[] = $usersearch;
		}

		$orderby = !empty($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : '';
		$order   = !empty($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'ASC';

		$order = in_array(strtolower($order), array('asc', 'desc')) ? $order : 'ASC';
		$orderby_possibles = array(
			'id' => 'ID',
			'email' => 'user_email',
			'user_email' => 'user_email',
			'display_name' => 'display_name',
			'name' => 'display_name',
		);
		$orderby = $orderby_possibles[$orderby];

		if (!$orderby) {
		    $join .= " INNER JOIN {$wpdb->usermeta} AS usermeta3 ON ( users.ID = usermeta3.user_id ) AND usermeta3.meta_key = 'first_name' ";
		    $join .= " INNER JOIN {$wpdb->usermeta} AS usermeta4 ON ( users.ID = usermeta4.user_id ) AND usermeta4.meta_key = 'last_name' ";
			$where .= " OR CONCAT(usermeta3.meta_value, usermeta4.meta_value) LIKE %s";
			$args[] = str_replace(' ', '%', $usersearch);
		    $orderby = 'CONCAT(usermeta3.meta_value, usermeta4.meta_value)';
		}
		$where .= !empty($usersearch) ? ')' : '';
		$per_page       = ($this->is_site_users) ? 'site_users_network_per_page' : 'users_per_page';
		$users_per_page = $this->get_items_per_page($per_page);
		$paged  = $this->get_pagenum();
		$limit  = $users_per_page;
		$offset = ($paged-1) * $users_per_page;

		$args[] = $offset;
		$args[] = $limit;

		$sqlSelect = "SELECT DISTINCT ID

FROM {$wpdb->users} AS users
INNER JOIN {$wpdb->usermeta} AS usermeta1 ON ( users.ID = usermeta1.user_id ) AND usermeta1.meta_key = '{$wpdb->prefix}capabilities'
$join

WHERE
    ( usermeta1.meta_value LIKE %s )
    $where
ORDER BY ".$wpdb->_real_escape($orderby)." ".$wpdb->_real_escape($order)." LIMIT %d, %d";

		$querySelect = $wpdb->prepare(
				$sqlSelect,
				$args
		);
		$users = $wpdb->get_results($querySelect);
		$items = array();

		foreach($users as $user) {
			$items[$user->ID] = get_user_by('ID', $user->ID);
		}

		$shops_user = get_user_meta(get_current_user_id(), 'sln_manager_shop_id');
		if(!empty($shops_user)) {
			$filtered_items = array();
			foreach($items as $user_id => $item) {
				$customer_obj = new SLN_Wrapper_Customer($item);
				$shops_customer = array();
				foreach ($customer_obj->getBookings() as $obj) {
					try {
						$shop_id = $obj->getMeta('shop');
						if ($shop_id) {
							$shop = SLN_Plugin::getInstance()->createFromPost($shop_id);
							if (! array_key_exists($shop->getId(), $shops_customer)) {
								$shops_customer[] = $shop->getId();
							}
						}
					} catch (\Exception $ex) {

					}
				}
				if(!empty(array_intersect($shops_user, $shops_customer))) {
					$filtered_items[$user_id] = $item;
				}
			}
			$items = $filtered_items;
		}

		$this->items = $items;

		$sqlCount = str_replace(array('DISTINCT ID', 'LIMIT %d, %d'), array('COUNT(DISTINCT ID)', ''), $sqlSelect);
		$args =array_slice($args, 0, -2);
		$queryCount = $wpdb->prepare(
				$sqlCount,
				count($args)===1 ?$args[0]:$args
		);

		$this->set_pagination_args( array(
			'total_items' => $wpdb->get_var($queryCount),
			'per_page'    => $users_per_page,
		) );
	}

	protected function row_actions($actions, $always_visible = false) {

		if (isset($actions['edit'])) {

			if (preg_match('/user_id=(\d+)\&/s', $actions['edit'], $matches)) {
				$user_id = isset($matches[1]) ? $matches[1] : '';
				$edit_link = esc_url(SLN_Admin_Customers::get_edit_customer_link($user_id)) ;
				$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit', 'salon-booking-system') . '</a>';
			}
		}

		return parent::row_actions($actions, $always_visible);
	}

	protected function extra_tablenav($which) {
		if ($which === 'top') {
			?>
			<div class="alignleft actions">
				<?php $this->search_box(__('Search customers', 'salon-booking-system'), 'customer'); ?>
			</div>
			<?php
		}
	}
}