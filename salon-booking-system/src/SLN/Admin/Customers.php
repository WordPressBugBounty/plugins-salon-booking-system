<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Admin_Customers extends SLN_Admin_AbstractPage {

	const PAGE = 'salon-customers';
    const PRIORITY = 10;

    public function __construct(SLN_Plugin $plugin)
    {
        parent::__construct($plugin);
	add_action('in_admin_header', array($this, 'in_admin_header'));
    }

    public function admin_menu()
    {
        $this->classicAdminMenu(__('Salon Customers', 'salon-booking-system'), __('Customers', 'salon-booking-system'));
        if ( ! isset($_REQUEST['id'])) {
            add_filter(
                'manage_'.get_plugin_page_hookname('salon-customers', 'salon').'_columns',
                array($this, 'users_columns')
            );
        }
    }

	public function show() {
		if (isset($_REQUEST['id'])) {
			$this->show_customer_page(intval($_REQUEST['id']));
		}
		else {
			$this->show_customers();
		}
	}

	public function show_customer_page($user_id) {

            if (!self::current_user_can_manage_customer($user_id)) {
                wp_die(esc_html__('Sorry, you are not allowed to edit this user.', 'salon-booking-system'), 403);
            }

            $customer = new SLN_Wrapper_Customer(new WP_User($user_id));

            if (!empty($user_id) && $customer->isEmpty()) {
                wp_redirect(get_edit_user_link($user_id));
                exit;
            }

            if (isset($_POST['save'])) {
                $error = $this->save_customer($user_id);
            }

            $customer = new SLN_Wrapper_Customer(new WP_User($user_id));

            echo $this->plugin->loadView(
                'admin/_customer',
                array(
                    'customer' => $customer,
                    'new_link' => self::get_edit_customer_link(0),
                    'error'    => isset($error) ? $error : null,
                )
            );
        }

    private function check_email($email){
        if (email_exists($email)) {
                $error = new WP_Error();
                $error->add('email_exists', __('<strong>ERROR</strong>: This email is already registered, please choose another one.', 'salon-booking-system'));
                return $error;
        }
        return false;
    }

	private function save_customer($user_id) {

        check_admin_referer('sln_update_user_'.$user_id);
		$customer = [];
        $email = isset($_POST['sln_customer_meta']['_sln_email']) ? sanitize_email( wp_unslash($_POST['sln_customer_meta']['_sln_email']) ) : false;
        if(!$email){
            $error = new WP_Error();
            $error->add('missing_email', __('<strong>ERROR</strong>: This email is empty.', 'salon-booking-system'));
            return $error;
        }
        $is_new = empty($_POST['id']);
        if ($is_new) {
            if(($error = $this->check_email($email))) return $error;
            $user_id = wp_create_user($email, wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                return $user_id;
            }
            $customer['user_email'] = $email;
        }
        else {
            $user_id = intval($_POST['id']);
            if (!self::current_user_can_manage_customer($user_id)) {
                wp_die(esc_html__('Sorry, you are not allowed to edit this user.', 'salon-booking-system'), 403);
            }
            $user_info = get_userdata($user_id);
            $old_mail = $user_info->user_email;
            if($email !== $old_mail && !array_intersect(['administrator'],$user_info->roles) ){
                if(($error = $this->check_email($email))) return $error;
                $customer['user_email'] = $email;
            }
        }


        $customer['ID'] = $user_id;
        // Existing accounts keep their roles: replacing them here is what let a
        // salon user strip administrators down to the customer role.
        if ($is_new) {
            $customer['role'] = SLN_Plugin::USER_ROLE_CUSTOMER;
        }
        if(isset($_POST['sln_customer_meta']['_sln_firstname'])) $customer['first_name'] = sanitize_text_field(wp_unslash( $_POST['sln_customer_meta']['_sln_firstname'] ));
        if(isset($_POST['sln_customer_meta']['_sln_lastname'])) $customer['last_name'] = sanitize_text_field(wp_unslash( $_POST['sln_customer_meta']['_sln_lastname'] ));
        wp_update_user($customer);

        if (!$is_new && self::is_plain_customer(get_userdata($user_id))) {
            $wp_user = new WP_User($user_id);
            if (!in_array(SLN_Plugin::USER_ROLE_CUSTOMER, (array) $wp_user->roles, true)) {
                $wp_user->add_role(SLN_Plugin::USER_ROLE_CUSTOMER);
            }
        }

        $allowed_keys = self::get_allowed_customer_meta_keys();
        $posted_meta  = isset($_POST['sln_customer_meta']) && is_array($_POST['sln_customer_meta']) ? $_POST['sln_customer_meta'] : array();
        foreach ($posted_meta as $k => $value) {
            if (!is_string($k) || !in_array($k, $allowed_keys, true)) {
                continue;
            }
            $value = is_array($value) ? array_map('sanitize_textarea_field', $value) : sanitize_textarea_field($value);
            update_user_meta($user_id, $k, $value);
        }

        wp_redirect(self::get_edit_customer_link($user_id));
        exit;
    }

	public function show_customers() {
		if (empty($_REQUEST)) {
			$referer = '<input type="hidden" name="wp_http_referer" value="'. esc_attr(esc_url(wp_unslash($_SERVER['REQUEST_URI']))) . '" />';
		} elseif (isset($_REQUEST['wp_http_referer'])) {
			$redirect = remove_query_arg(array('wp_http_referer', 'updated', 'delete_count'), esc_url(wp_unslash($_REQUEST['wp_http_referer'])));
			$referer = '<input type="hidden" name="wp_http_referer" value="' . esc_attr($redirect) . '" />';
		} else {
			$redirect = 'admin.php';
			$referer = '';
		}

		$table = new SLN_Admin_Customers_List();

		switch ($table->current_action()) {
                    case 'delete':
                        if (!is_multisite() && !empty($_REQUEST['users']) && is_array($_REQUEST['users'])) {
                            check_admin_referer('bulk-users');
                            $users =  array_map('intval',$_REQUEST['users']);
                            foreach($users as $userId) {
                                if ($userId === get_current_user_id() || !self::current_user_can_manage_customer($userId)) {
                                    continue;
                                }
                                wp_delete_user( $userId );
                            }

                            wp_redirect(admin_url('admin.php?page='.self::PAGE));
                            exit;

                        }
                        break;
		}

		$table->prepare_items();

		echo $this->plugin->loadView(
                    'admin/customers',
                    array(
                        'new_link' => self::get_edit_customer_link(0),
                        'table'    => $table,
                    )
		);
	}

	public function users_columns($users_columns) {
		$table = new SLN_Admin_Customers_List();

		return $table->get_columns();
	}



	/**
	 * Salon users may manage plain customer accounts. Any account with more than a
	 * customer role (administrators, salon staff, editors…) is only manageable by
	 * someone WordPress itself allows to edit that user.
	 *
	 * @param int $user_id 0 for a new customer.
	 * @return bool
	 */
	public static function current_user_can_manage_customer($user_id) {
		$user_id = (int) $user_id;
		if (!$user_id) {
			return current_user_can('manage_salon');
		}
		$user = get_userdata($user_id);
		if (!$user) {
			return false;
		}
		if (current_user_can('edit_user', $user_id)) {
			return true;
		}

		return current_user_can('manage_salon') && self::is_plain_customer($user);
	}

	/**
	 * @param WP_User|false $user
	 * @return bool
	 */
	public static function is_plain_customer($user) {
		if (!$user instanceof WP_User || empty($user->roles)) {
			return false;
		}
		$customer_roles = apply_filters(
			'sln.admin_customers.customer_roles',
			array(SLN_Plugin::USER_ROLE_CUSTOMER, 'subscriber', 'customer')
		);
		if (array_diff((array) $user->roles, $customer_roles)) {
			return false;
		}

		return !user_can($user, 'manage_salon') && !user_can($user, 'edit_posts') && !user_can($user, 'list_users');
	}

	/**
	 * User-meta keys the customer form may write.
	 *
	 * @return string[]
	 */
	public static function get_allowed_customer_meta_keys() {
		$keys = array('_sln_personal_note', '_sln_administration_note', '_sln_sms_prefix');
		foreach (SLN_Enum_CheckoutFields::forCustomer()->appendSmsPrefix() as $key => $field) {
			if (in_array($field['type'], array('html', 'file'), true)) {
				continue;
			}
			$keys[] = '_sln_' . $key;
		}

		return array_values(array_unique(apply_filters('sln.admin_customers.allowed_meta_keys', $keys)));
	}

	public static function get_edit_customer_link($user_id) {

		return get_admin_url() . "admin.php?page=salon-customers&id=$user_id";
	}

        public function enqueueAssets()
        {
            parent::enqueueAssets();

            $event = 'Page views of back-end plugin pages';
        }
}