<?php

class SLN_Action_Ajax_ImportCustomers extends SLN_Action_Ajax_AbstractImport
{
    protected $fields = array(
        'first_name',
        'last_name',
        'email',
        'mobile_phone',
        'address',
        'personal_note',
        'administration_note',
    );

    protected $required = array(
        'first_name',
        'email',
    );

    /**
     * SLN_Action_Ajax_ImportCustomers constructor.
     *
     * @param SLN_Plugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);

        $this->type = $plugin::USER_ROLE_CUSTOMER;
    }

    protected function getImportOptions($form)
    {
        return array(
            'update_existing' => ! empty($form['import_customers_update_existing']),
        );
    }

    protected function processRow($data)
    {
        if (empty($data['email'])) {
            return true;
        }

        $existing = get_user_by('email', $data['email']);
        if ($existing) {
            if ( ! empty($this->options['update_existing'])) {
                $this->updateExistingCustomer($existing, $data);
            }
            return true;
        }

        $errors = wp_create_user($data['email'], wp_generate_password( 8, false ), $data['email']);
        if (is_wp_error($errors)) {
            return true;
        }

        $errors = wp_update_user(
            array(
                'ID'         => $errors,
                'first_name' => (string)$data['first_name'],
                'last_name'  => (string)$data['last_name'],
                'role'       => SLN_Plugin::USER_ROLE_CUSTOMER,
            )
        );
        if (is_wp_error($errors)) {
            return true;
        }

        add_user_meta($errors, '_sln_phone', $data['mobile_phone']);
        add_user_meta($errors, '_sln_address', $data['address']);
        add_user_meta($errors, '_sln_personal_note', sanitize_textarea_field($data['personal_note']));
        add_user_meta($errors, '_sln_administration_note', sanitize_textarea_field($data['administration_note']));

	    wp_send_new_user_notifications($errors, 'user');

        return true;
    }

    /**
     * Update contact details of a customer that already exists, matched by email.
     * Only salon customers are touched: an email belonging to an admin or staff
     * member is left alone. Bookings keep their own copy of the customer details
     * and are not changed.
     *
     * @param WP_User $user
     * @param array   $data
     */
    protected function updateExistingCustomer($user, $data)
    {
        if ( ! in_array(SLN_Plugin::USER_ROLE_CUSTOMER, (array) $user->roles, true)) {
            return;
        }

        $phone = trim((string) $data['mobile_phone']);
        if ($phone !== '') {
            update_user_meta($user->ID, '_sln_phone', $phone);
        }

        $address = trim((string) $data['address']);
        if ($address !== '') {
            update_user_meta($user->ID, '_sln_address', $address);
        }

        // Notes may have been written by staff on the site: only fill them when empty.
        foreach (array('personal_note', 'administration_note') as $field) {
            $value = sanitize_textarea_field((string) $data[$field]);
            if ($value !== '' && (string) get_user_meta($user->ID, '_sln_' . $field, true) === '') {
                update_user_meta($user->ID, '_sln_' . $field, $value);
            }
        }
    }

}
