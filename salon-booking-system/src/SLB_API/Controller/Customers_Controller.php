<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API\Controller;

use WP_REST_Server;
use WP_Error;
use SLN_Plugin;
use SLN_Enum_BookingStatus;
use WP_User_Query;
use SLN_Wrapper_Customer;
use WP_Query;

class Customers_Controller extends REST_Controller
{
    const ROLE = SLN_Plugin::USER_ROLE_CUSTOMER;

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'customers';

    public function register_routes() {

        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
		'permission_callback' => '__return_true',
                'args' => array(
                    'search' => array(
                        'description' => __( 'Search string.', 'salon-booking-system' ),
                        'type'        => 'string',
                        'default'     => '',
                    ),
                    'search_type' => array(
                        'description' => __( 'Search type.', 'salon-booking-system' ),
                        'type'        => 'string',
                        'enum'        => array('start_with', 'end_with', 'contains'),
                        'default'     => 'contains',
                    ),
                    'search_field' => array(
                        'description' => __( 'Search field.', 'salon-booking-system' ),
                        'type'        => 'string',
                        'enum'        => array('all', 'first_name', 'last_name', 'phone'),
                        'default'     => 'all',
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            'args' => array(
                'id' => array(
                    'description' => __( 'Unique identifier for the resource.', 'salon-booking-system' ),
                    'type'        => 'integer',
                    'required'    => true,
                ),
            ),
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
		'permission_callback' => '__return_true',
                'args'                => array(
                    'context' => $this->get_context_param( array( 'default' => 'view' ) ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/retention', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_retention' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args' => array(
                    'start_date' => array(
                        'description'       => __('Start date.', 'salon-booking-system'),
                        'type'              => 'string',
                        'format'            => 'YYYY-MM-DD',
                        'required'          => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                    'end_date' => array(
                        'description'       => __('End date.', 'salon-booking-system'),
                        'type'              => 'string',
                        'format'            => 'YYYY-MM-DD',
                        'required'          => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                    'rebooking_window' => array(
                        'description' => __( 'Rebooking window in days (e.g., 30, 60, 90).', 'salon-booking-system' ),
                        'type'        => 'integer',
                        'default'     => 60,
                    ),
                    'at_risk_limit' => array(
                        'description' => __( 'Limit of at-risk customers to return.', 'salon-booking-system' ),
                        'type'        => 'integer',
                        'default'     => 10,
                    ),
                    'shop' => array(
                        'description' => __( 'Shop ID for multi-shop filtering (0 = all shops).', 'salon-booking-system' ),
                        'type'        => 'integer',
                        'default'     => 0,
                    ),
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/frequency-clv', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_frequency_clv' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args' => array(
                    'start_date' => array(
                        'description'       => __('Start date.', 'salon-booking-system'),
                        'type'              => 'string',
                        'format'            => 'YYYY-MM-DD',
                        'required'          => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                    'end_date' => array(
                        'description'       => __('End date.', 'salon-booking-system'),
                        'type'              => 'string',
                        'format'            => 'YYYY-MM-DD',
                        'required'          => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                    'shop' => array(
                        'description' => __( 'Shop ID for multi-shop filtering (0 = all shops).', 'salon-booking-system' ),
                        'type'        => 'integer',
                        'default'     => 0,
                    ),
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/stats', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_stats' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args' => array(
                    'start_date' => array(
                        'description'       => __('Start date.', 'salon-booking-system'),
                        'type'              => 'string',
                        'format'            => 'YYYY-MM-DD',
                        'required'          => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                    'end_date' => array(
                        'description'       => __('End date.', 'salon-booking-system'),
                        'type'              => 'string',
                        'format'            => 'YYYY-MM-DD',
                        'required'          => true,
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                    'segment' => array(
                        'description' => __( 'Customer segment.', 'salon-booking-system' ),
                        'type'        => 'string',
                        'enum'        => array('all', 'new', 'returning'),
                        'default'     => 'all',
                    ),
                    'limit' => array(
                        'description' => __( 'Limit top customers.', 'salon-booking-system' ),
                        'type'        => 'integer',
                        'default'     => 10,
                    ),
                    'shop' => array(
                        'description' => __( 'Shop ID for multi-shop filtering (0 = all shops).', 'salon-booking-system' ),
                        'type'        => 'integer',
                        'default'     => 0,
                    ),
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/no-shows', array(
            'args' => array(
                'id' => array(
                    'description' => __( 'Customer ID.', 'salon-booking-system' ),
                    'type'        => 'integer',
                    'required'    => true,
                ),
            ),
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_customer_no_shows' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args' => array(
                    'limit' => array(
                        'description' => __( 'Limit number of no-show bookings to return.', 'salon-booking-system' ),
                        'type'        => 'integer',
                        'default'     => 10,
                    ),
                ),
            ),
        ) );
    }

    public function permissions_check($capability, $object_id = 0)
    {
        // Administrators have full access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Shop managers can read customer data (for reports/stats)
        if ($capability === 'read' && $this->is_shop_manager()) {
            return true;
        }
        
        $capabilities = array(
            'create' => 'add_users',
            'edit'   => 'edit_users',
            'delete' => 'delete_users',
        );

	return current_user_can( isset($capabilities[$capability]) ? $capabilities[$capability] : '', $object_id );
    }

    public function create_item_permissions_check( $request ) {

        if ( ! $this->permissions_check( 'create' ) ) {
            return new WP_Error( 'salon_rest_cannot_create', __( 'Sorry, you cannot create resource.', 'salon-booking-system' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function update_item_permissions_check( $request ) {

        if ( ! $this->permissions_check( 'edit' ) ) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, you cannot update resource.', 'salon-booking-system' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function delete_item_permissions_check( $request ) {

        if ( ! $this->permissions_check( 'delete' )) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, you cannot delete resource.', 'salon-booking-system' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function get_items( $request )
    {
        if( !current_user_can( 'manage_salon' ) ){
            return rest_ensure_response( array(
                'status' => '403',
                ) );
        }
        $prepared_args          = array();
        $prepared_args['order'] = isset($request['order']) && in_array(strtolower($request['order']), array('asc', 'desc')) ? $request['order'] : 'asc';

        $prepared_args['number'] = is_null($request['per_page']) ? -1 : $request['per_page'];

        $request['orderby'] = is_null($request['orderby']) ? 'id' : $request['orderby'];
        $request['page']    = is_null($request['page']) ? 1 : $request['page'];

        if ( ! empty( $request['offset'] ) ) {
            $prepared_args['offset'] = $request['offset'];
        } else {
            $prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['number'];
        }

        $orderby_possibles = array(
            'id'           => array('orderby' => 'ID'),
            'display_name' => array('orderby' => 'display_name'),
            'first_name_last_name' => array(
		'meta_query' => array(
		    'first_name' => array(
			'key'	  => 'first_name',
			'compare' => 'EXISTS',
		    ),
		    'last_name' => array(
			'key'	  => 'last_name',
			'compare' => 'EXISTS',
		    ),
		),
		'orderby' => 'first_name last_name',
            ),
            'last_name_first_name' => array(
		'meta_query' => array(
		    'first_name' => array(
			'key'	  => 'first_name',
			'compare' => 'EXISTS',
		    ),
		    'last_name' => array(
			'key'	  => 'last_name',
			'compare' => 'EXISTS',
		    ),
		),
		'orderby' => 'last_name first_name',
            ),
        );

        $prepared_args            = array_merge($prepared_args, $orderby_possibles[ $request['orderby'] ]);
        $prepared_args['role']    = array(self::ROLE);

        $s = $request->get_param('search');

        if ($s !== '') {

            $include = array();

            if ($request->get_param('search_type') === 'contains') {

                $search_params_main_fields = array_merge($prepared_args, array(
                    'search'         => '*' . $s . '*',
                    'search_columns' => array('user_nicename', 'user_email'),
                    'fields'         => 'ID',
                ));

                $include = array_merge($include, (new WP_User_Query($search_params_main_fields))->results);

                $search_params_meta_fields = $prepared_args;

                if ( ! isset( $search_params_meta_fields['meta_query'] ) ) {
                    $search_params_meta_fields['meta_query'] = array();
                }

                $search_params_meta_fields['fields'] = 'ID';

                $search_params_meta_fields['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'first_name',
                        'value'   => $s,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'last_name',
                        'value'   => $s,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => '_sln_phone',
                        'value'   => $s,
                        'compare' => 'LIKE',
                    ),
                );

                $include = array_merge($include, (new WP_User_Query($search_params_meta_fields))->results);
            }

            if ($request->get_param('search_type') === 'start_with') {

                $search_params_meta_fields = $prepared_args;

                if ( ! isset( $search_params_meta_fields['meta_query'] ) ) {
                    $search_params_meta_fields['meta_query'] = array();
                }

                $search_params_meta_fields['fields'] = 'ID';

                if ($request->get_param('search_field') === 'first_name') {
                    $search_params_meta_fields['meta_query'][] = array(
                        array(
                            'key'     => 'first_name',
                            'value'   => sprintf("^(%s)", implode('|', array_map('trim', explode('|', $s)))),
                            'compare' => 'REGEXP',
                        ),
                    );
                }

                if ($request->get_param('search_field') === 'last_name') {
                    $search_params_meta_fields['meta_query'][] = array(
                        array(
                            'key'     => 'last_name',
                            'value'   => sprintf("^(%s)", implode('|', array_map('trim', explode('|', $s)))),
                            'compare' => 'REGEXP',
                        ),
                    );
                }

                $include = array_merge($include, (new WP_User_Query($search_params_meta_fields))->results);
            }

            $prepared_args['include'] = $include ? $include : array(-1);
        }

        $customers = array();

        $query = new WP_User_Query( $prepared_args );

        try {
            foreach ( $query->results as $customer ) {
                $data        = $this->prepare_item_for_response( $customer, $request );
                $customers[] = $this->prepare_response_for_collection( $data );
            }
        } catch (\Exception $ex) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, resource list error ('.sprintf('%s', $ex->getMessage()).').', 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        $response = $this->success_response(array('items' => $customers));

        // Store pagination values for headers then unset for count query.
        $per_page = (int) $prepared_args['number'];
        $page     = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

	$prepared_args['fields'] = 'ID';

        $total = $query->get_total();

        if ( $total < 1 ) {
            // Out-of-bounds, run the query again without LIMIT for total count.
            unset( $prepared_args['number'] );
            unset( $prepared_args['offset'] );
            $count_query = new WP_User_Query( $prepared_args );
            $total = $count_query->get_total();
        }

        $response->header( 'X-WP-Total', (int) $total );

        $max_pages = ceil( $total / $per_page );

        $response->header( 'X-WP-TotalPages', (int) $max_pages );

        $base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

        if ( $page > 1 ) {
            $prev_page = $page - 1;
            if ( $prev_page > $max_pages ) {
                $prev_page = $max_pages;
            }
            $prev_link = add_query_arg( 'page', $prev_page, $base );
            $response->link_header( 'prev', $prev_link );
        }

        if ( $max_pages > $page ) {
            $next_page = $page + 1;
            $next_link = add_query_arg( 'page', $next_page, $base );
            $response->link_header( 'next', $next_link );
        }

        return $response;
    }

    public function prepare_item_for_response( $customer, $request )
    {
        return new SLN_Wrapper_Customer($customer);
    }

    public function prepare_response_for_collection($customer)
    {
        $query = new WP_Query(array(
            'author'    => $customer->getId(),
            'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
            'fields'    => 'ids',
        ));

        if (is_wp_error($query)) {
            throw new \Exception(esc_html__( 'Get bookings ids error.', 'salon-booking-system' ));
        }

        $bookings = $query->posts;

        return array(
            'id'         => $customer->getId(),
            'first_name' => $customer->get('first_name'),
            'last_name'  => $customer->get('last_name'),
            'email'      => $customer->get('user_email'),
            'phone_country_code' => $customer->getMeta('sms_prefix'),
            'phone'      => $customer->getMeta('phone'),
            'address'    => $customer->getMeta('address'),
            'note'       => $customer->getMeta('personal_note'),
            'bookings'   => $bookings,
            'total_amount_reservations' => $customer->getAmountOfReservations(),
        );
    }

    public function create_item( $request )
    {
        if ($request->get_param('id')) {

            $query = new WP_User_Query(array(
                'role'           => array(self::ROLE),
                'search'         => $request->get_param('id'),
                'search_columns' => array('ID'),
            ));

            if ( $query->results ) {
                return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, resource already exists.', 'salon-booking-system' ), array( 'status' => 409 ) );
            }
        }

        try {
            $id = $this->save_item_user($request);
        } catch (\Exception $ex) {
            return new WP_Error( 'salon_rest_cannot_view', __( sprintf('Sorry, error on create (%s).', $ex->getMessage()), 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        $response = $this->success_response(array('id' => $id));

        $response->set_status(201);

        return $response;
    }

    public function get_item( $request )
    {
        // SECURITY FIX: Implement proper authorization check
        $requested_customer_id = intval($request->get_param('id'));
        $current_user_id = get_current_user_id();
        
        // Admin and staff can view any customer
        $can_manage_salon = current_user_can( 'manage_salon' );
        
        // Customers can only view their own data
        if (!$can_manage_salon && $current_user_id !== $requested_customer_id) {
            return new WP_Error(
                'salon_rest_forbidden',
                __( 'Sorry, you are not authorized to view this customer data.', 'salon-booking-system' ),
                array( 'status' => 403 )
            );
        }

        $query = new WP_User_Query(array(
            'role'           => array(self::ROLE),
            'search'         => $requested_customer_id,
            'search_columns' => array('ID'),
        ));

        if ( ! $query->results ) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, resource not found.', 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        try {
            $customer = $this->prepare_item_for_response(current($query->results), $request);
            $customer = $this->prepare_response_for_collection($customer);
        } catch (\Exception $ex) {
            return new WP_Error( 'salon_rest_cannot_view', __( sprintf('Sorry, get resource error (%s).', $ex->getMessage()), 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        return $this->success_response(array('items' => array($customer)));
    }

    public function update_item( $request )
    {
        $query = new WP_User_Query(array(
            'role'           => array(self::ROLE),
            'search'         => $request->get_param('id'),
            'search_columns' => array('ID'),
        ));

        if ( ! $query->results ) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, resource not found.', 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        try {
            $customer = $this->prepare_item_for_response(current($query->results), $request);
            $customer = $this->prepare_response_for_collection($customer);
        } catch (\Exception $ex) {
            return new WP_Error( 'salon_rest_cannot_view', __( sprintf('Sorry, get resource error (%s).', $ex->getMessage()), 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        try {
            $cloned_request = clone $request;
            $cloned_request->set_default_params($customer);
            $this->save_item_user($cloned_request, $request->get_param('id'));
        } catch (\Exception $ex) {
            return new WP_Error( 'salon_rest_cannot_view', __( sprintf('Sorry, error on update (%s).', $ex->getMessage()), 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        return $this->success_response();
    }

    public function delete_item( $request )
    {
        $query = new WP_User_Query(array(
            'role'           => array(self::ROLE),
            'search'         => $request->get_param('id'),
            'search_columns' => array('ID'),
        ));

        if ( ! $query->results ) {
            return new WP_Error( 'salon_rest_cannot_delete', __( 'Sorry, resource not found.', 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        wp_delete_user($request->get_param('id'));

        return $this->success_response();
    }

    protected function save_item_user($request, $id = 0)
    {
        if (!$id) {

            $id = wp_create_user($request->get_param('email'), wp_generate_password(), $request->get_param('email'));

            if ( is_wp_error($id) ) {
                throw new \Exception(esc_html__( 'Save customer error.', 'salon-booking-system' ));
            }
        }

        $id = wp_update_user(array(
            'ID'         => $id,
            'user_email' => $request->get_param('email'),
            'first_name' => $request->get_param('first_name'),
            'last_name'  => $request->get_param('last_name'),
            'role'       => SLN_Plugin::USER_ROLE_CUSTOMER,
        ));

        if ( is_wp_error($id) ) {
            throw new \Exception(esc_html__( 'Save customer error.', 'salon-booking-system' ));
        }

        $meta = array(
            '_sln_phone'         => $request->get_param('phone'),
            '_sln_address'       => $request->get_param('address'),
            '_sln_personal_note' => $request->get_param('note'),
        );

        foreach ($meta as $key => $value) {
            update_user_meta($id, $key, $value);
        }

        return $id;
    }

    public function get_item_schema()
    {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'customer',
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __( 'Unique identifier for the resource.', 'salon-booking-system' ),
                    'type'        => 'integer',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'readonly'    => true,
                    ),
                ),
                'first_name' => array(
                    'description' => __( 'The first name for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'validate_callback' => array($this, 'rest_validate_not_empty_string'),
                        'sanitize_callback' => 'sanitize_text_field',
                        'required'          => true,
                    ),
                ),
                'last_name' => array(
                    'description' => __( 'The last name for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'validate_callback' => array($this, 'rest_validate_not_empty_string'),
                        'sanitize_callback' => 'sanitize_text_field',
                        'required'          => true,
                    ),
                ),
                'email' => array(
                    'description' => __( 'The email for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'format'      => 'email',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'required' => true,
                    ),
                ),
                'phone' => array(
                    'description' => __( 'The phone for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'format'      => 'phone',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'default' => '',
                    ),
                ),
                'address' => array(
                    'description' => __( 'The address for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ),
                ),
                'note' => array(
                    'description' => __( 'The note for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ),
                ),
                'bookings' => array(
                    'description' => __( 'The bookings ids for the resource.', 'salon-booking-system' ),
                    'type'        => 'array',
                    'items'       => array(
                        'type' => 'integer',
                    ),
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'readonly'=> true,
                    ),
                ),
                'total_amount_reservations' => array(
                    'description' => __( 'The total amount of reservations.', 'salon-booking-system' ),
                    'type'        => 'number',
                    'context'     => array( 'view' ),
                    'arg_options' => array(
                        'readonly'=> true,
                    ),
                ),
            ),
        );

        return $schema;
    }

    /**
     * Get customer analytics and statistics
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_stats( $request )
    {
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $segment = $request->get_param('segment');
        $limit = $request->get_param('limit');
        $shop_id = (int) $request->get_param('shop');

        // Build meta query
        $meta_query = array(
            array(
                'key'     => '_sln_booking_date',
                'value'   => array($start_date, $end_date),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        );

        // Add shop filter for Multi-Shop support
        // Apply shop manager restrictions if user is a manager
        $shop_id = $this->apply_shop_manager_filter($request);
        
        if ($shop_id === -1) {
            // Manager has no shops assigned or no access to requested shop
            // Will return empty data - handled by calling method
            $meta_query[] = array(
                'key'     => '_sln_booking_shop',
                'value'   => -9999, // Non-existent shop ID to return no results
                'compare' => '=',
            );
        } elseif ($shop_id > 0 && class_exists('\SalonMultishop\Addon')) {
            $meta_query[] = array(
                'key'     => '_sln_booking_shop',
                'value'   => $shop_id,
                'compare' => '=',
            );
        } elseif ($shop_id === 0 && $this->is_shop_manager() && class_exists('\SalonMultishop\Addon')) {
            // Manager with multiple shops - filter by all assigned shops
            $assigned_shops = $this->get_shop_manager_filter_ids();
            if ($assigned_shops) {
                $meta_query[] = array(
                    'key'     => '_sln_booking_shop',
                    'value'   => $assigned_shops,
                    'compare' => 'IN',
                );
            }
        }

        // Exclude no-show bookings from performance calculations
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => 'no_show',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => 'no_show',
                'value'   => '1',
                'compare' => '!=',
            ),
        );

        // Query all bookings in period
        $bookings_query = new WP_Query(array(
            'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
            'post_status'    => array(
                SLN_Enum_BookingStatus::PAID,
                SLN_Enum_BookingStatus::PAY_LATER,
                SLN_Enum_BookingStatus::CONFIRMED,
            ),
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
        ));

        // Get customers from bookings
        $customer_stats = array();
        $unique_customers = array();
        $new_customers = array();
        
        foreach ($bookings_query->posts as $booking_post) {
            $booking = SLN_Plugin::getInstance()->createBooking($booking_post->ID);
            $customer_id = $booking->getUserId();
            
            if (!$customer_id) continue;
            
            $unique_customers[$customer_id] = true;
            
            // Initialize customer stats
            if (!isset($customer_stats[$customer_id])) {
                $user = get_userdata($customer_id);
                if (!$user) continue;
                
                $customer_stats[$customer_id] = array(
                    'customer_id'       => $customer_id,
                    'customer_name'     => $user->display_name,
                    'bookings_count'    => 0,
                    'total_spent'       => 0.0,
                    'avg_booking_value' => 0.0,
                    'last_booking_date' => null,
                );
                
                // Check if this is a new customer (first booking in period)
                $prev_bookings = new WP_Query(array(
                    'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
                    'post_status'    => array(
                        SLN_Enum_BookingStatus::PAID,
                        SLN_Enum_BookingStatus::PAY_LATER,
                        SLN_Enum_BookingStatus::CONFIRMED,
                    ),
                    'author'         => $customer_id,
                    'posts_per_page' => 1,
                    'meta_query'     => array(
                        'relation' => 'AND',
                        array(
                            'key'     => '_sln_booking_date',
                            'value'   => $start_date,
                            'compare' => '<',
                            'type'    => 'DATE',
                        ),
                        // Exclude no-show bookings from new customer detection
                        array(
                            'relation' => 'OR',
                            array(
                                'key'     => 'no_show',
                                'compare' => 'NOT EXISTS',
                            ),
                            array(
                                'key'     => 'no_show',
                                'value'   => '1',
                                'compare' => '!=',
                            ),
                        ),
                    ),
                ));
                
                if ($prev_bookings->post_count == 0) {
                    $new_customers[$customer_id] = true;
                }
            }
            
            // Update stats
            $customer_stats[$customer_id]['bookings_count']++;
            $customer_stats[$customer_id]['total_spent'] += $booking->getAmount();
            
            $booking_date = $booking->getDate()->format('Y-m-d');
            if (!$customer_stats[$customer_id]['last_booking_date'] || 
                $booking_date > $customer_stats[$customer_id]['last_booking_date']) {
                $customer_stats[$customer_id]['last_booking_date'] = $booking_date;
            }
        }

        // Calculate averages
        foreach ($customer_stats as $customer_id => $stats) {
            if ($stats['bookings_count'] > 0) {
                $customer_stats[$customer_id]['avg_booking_value'] = round($stats['total_spent'] / $stats['bookings_count'], 2);
            }
            $customer_stats[$customer_id]['total_spent'] = round($stats['total_spent'], 2);
        }

        // Sort by total spent (descending)
        usort($customer_stats, function($a, $b) {
            return $b['total_spent'] <=> $a['total_spent'];
        });

        // Calculate retention rate
        $total_customers = count($unique_customers);
        $new_customers_count = count($new_customers);
        $returning_customers_count = $total_customers - $new_customers_count;
        $retention_rate = $total_customers > 0 ? round(($returning_customers_count / $total_customers) * 100, 2) : 0.0;

        // Filter top customers
        $top_customers = array_slice($customer_stats, 0, $limit);

        return $this->success_response(array(
            'summary' => array(
                'total_customers'       => $total_customers,
                'new_customers'         => $new_customers_count,
                'returning_customers'   => $returning_customers_count,
                'retention_rate'        => $retention_rate,
            ),
            'top_customers' => $top_customers,
        ));
    }

    /**
     * Get customer retention metrics
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_retention($request)
    {
        $start_date = new \SLN_DateTime($request->get_param('start_date'));
        $end_date = new \SLN_DateTime($request->get_param('end_date'));
        $rebooking_window = (int) $request->get_param('rebooking_window');
        $at_risk_limit = (int) $request->get_param('at_risk_limit');
        $shop_id = (int) $request->get_param('shop');

        // Build meta query
        $meta_query = array(
            array(
                'key'     => '_sln_booking_date',
                'value'   => array($start_date->format('Y-m-d'), $end_date->format('Y-m-d')),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        );

        // Add shop filter for Multi-Shop support
        // Apply shop manager restrictions if user is a manager
        $shop_id = $this->apply_shop_manager_filter($request);
        
        if ($shop_id === -1) {
            // Manager has no shops assigned or no access to requested shop
            // Will return empty data - handled by calling method
            $meta_query[] = array(
                'key'     => '_sln_booking_shop',
                'value'   => -9999, // Non-existent shop ID to return no results
                'compare' => '=',
            );
        } elseif ($shop_id > 0 && class_exists('\SalonMultishop\Addon')) {
            $meta_query[] = array(
                'key'     => '_sln_booking_shop',
                'value'   => $shop_id,
                'compare' => '=',
            );
        } elseif ($shop_id === 0 && $this->is_shop_manager() && class_exists('\SalonMultishop\Addon')) {
            // Manager with multiple shops - filter by all assigned shops
            $assigned_shops = $this->get_shop_manager_filter_ids();
            if ($assigned_shops) {
                $meta_query[] = array(
                    'key'     => '_sln_booking_shop',
                    'value'   => $assigned_shops,
                    'compare' => 'IN',
                );
            }
        }

        // Query all bookings in the period
        $query_args = array(
            'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
            'post_status'    => array(
                \SLN_Enum_BookingStatus::PAID,
                \SLN_Enum_BookingStatus::PAY_LATER,
                \SLN_Enum_BookingStatus::CONFIRMED,
            ),
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
        );

        $bookings_query = new WP_Query($query_args);
        $bookings = $bookings_query->posts;

        // Track customers and their bookings
        $customer_bookings = array();
        
        foreach ($bookings as $post) {
            $booking = \SLN_Plugin::getInstance()->createBooking($post->ID);
            $customer_id = $booking->getUserId();
            
            if (!$customer_id) continue;
            
            $booking_date = $booking->getDate();
            if (!$booking_date) continue;
            
            if (!isset($customer_bookings[$customer_id])) {
                $customer_bookings[$customer_id] = array(
                    'dates' => array(),
                    'total_spent' => 0,
                    'name' => $booking->getDisplayName(),
                    'email' => $booking->getEmail(),
                );
            }
            
            $customer_bookings[$customer_id]['dates'][] = $booking_date;
            $customer_bookings[$customer_id]['total_spent'] += $booking->getAmount();
        }

        // Calculate rebooking metrics
        $customers_with_rebooking = 0;
        $total_customers_measured = 0;
        $at_risk_customers = array();
        $now = new \DateTime();

        foreach ($customer_bookings as $customer_id => $data) {
            // Sort dates
            usort($data['dates'], function($a, $b) {
                return $a->getTimestamp() - $b->getTimestamp();
            });
            
            $first_visit = $data['dates'][0];
            $last_visit = end($data['dates']);
            
            // Only count customers whose first visit was more than rebooking_window days ago
            // This gives them a chance to rebook
            $days_since_first = $now->diff($first_visit)->days;
            
            if ($days_since_first < $rebooking_window) {
                continue; // Too early to measure rebooking
            }
            
            $total_customers_measured++;
            
            // Check if they have more than one booking (rebooked)
            if (count($data['dates']) > 1) {
                $customers_with_rebooking++;
            }
            
            // Check if customer is at risk (last visit > rebooking_window days ago and no recent booking)
            $days_since_last = $now->diff($last_visit)->days;
            
            if ($days_since_last > $rebooking_window) {
                $expected_return_date = clone $last_visit;
                $expected_return_date->modify("+{$rebooking_window} days");
                
                $at_risk_customers[] = array(
                    'customer_id' => $customer_id,
                    'customer_name' => $data['name'],
                    'customer_email' => $data['email'],
                    'last_visit_date' => $last_visit->format('Y-m-d'),
                    'days_since_last_visit' => $days_since_last,
                    'expected_return_date' => $expected_return_date->format('Y-m-d'),
                    'total_spent' => round($data['total_spent'], 2),
                    'total_bookings' => count($data['dates']),
                );
            }
        }

        // Sort at-risk customers by total spent (descending) - prioritize high-value customers
        usort($at_risk_customers, function($a, $b) {
            return $b['total_spent'] <=> $a['total_spent'];
        });

        // Limit at-risk list
        $at_risk_customers = array_slice($at_risk_customers, 0, $at_risk_limit);

        // Calculate rebooking rate
        $rebooking_rate = $total_customers_measured > 0 
            ? round(($customers_with_rebooking / $total_customers_measured) * 100, 1) 
            : 0.0;

        return $this->success_response(array(
            'rebooking_rate' => $rebooking_rate,
            'rebooking_window_days' => $rebooking_window,
            'total_customers_measured' => $total_customers_measured,
            'customers_with_rebooking' => $customers_with_rebooking,
            'at_risk_count' => count($at_risk_customers),
            'at_risk_customers' => $at_risk_customers,
        ));
    }

    /**
     * Get service frequency and customer lifetime value metrics
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_frequency_clv($request)
    {
        $start_date = new \SLN_DateTime($request->get_param('start_date'));
        $end_date = new \SLN_DateTime($request->get_param('end_date'));
        $shop_id = (int) $request->get_param('shop');

        // Build meta query
        $meta_query = array(
            array(
                'key'     => '_sln_booking_date',
                'value'   => array($start_date->format('Y-m-d'), $end_date->format('Y-m-d')),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        );

        // Add shop filter for Multi-Shop support
        // Apply shop manager restrictions if user is a manager
        $shop_id = $this->apply_shop_manager_filter($request);
        
        if ($shop_id === -1) {
            // Manager has no shops assigned or no access to requested shop
            // Will return empty data - handled by calling method
            $meta_query[] = array(
                'key'     => '_sln_booking_shop',
                'value'   => -9999, // Non-existent shop ID to return no results
                'compare' => '=',
            );
        } elseif ($shop_id > 0 && class_exists('\SalonMultishop\Addon')) {
            $meta_query[] = array(
                'key'     => '_sln_booking_shop',
                'value'   => $shop_id,
                'compare' => '=',
            );
        } elseif ($shop_id === 0 && $this->is_shop_manager() && class_exists('\SalonMultishop\Addon')) {
            // Manager with multiple shops - filter by all assigned shops
            $assigned_shops = $this->get_shop_manager_filter_ids();
            if ($assigned_shops) {
                $meta_query[] = array(
                    'key'     => '_sln_booking_shop',
                    'value'   => $assigned_shops,
                    'compare' => 'IN',
                );
            }
        }

        // Query all bookings in the period
        $query_args = array(
            'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
            'post_status'    => array(
                \SLN_Enum_BookingStatus::PAID,
                \SLN_Enum_BookingStatus::PAY_LATER,
                \SLN_Enum_BookingStatus::CONFIRMED,
            ),
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
        );

        $bookings_query = new WP_Query($query_args);
        $bookings = $bookings_query->posts;

        // Calculate period length in days
        $period_days = $end_date->diff($start_date)->days + 1;

        // Track customers and their bookings
        $customer_data = array();
        $service_frequency = array(); // Track service types
        
        foreach ($bookings as $post) {
            $booking = \SLN_Plugin::getInstance()->createBooking($post->ID);
            $customer_id = $booking->getUserId();
            
            if (!$customer_id) continue;
            
            $booking_date = $booking->getDate();
            if (!$booking_date) continue;
            
            $amount = $booking->getAmount();
            
            if (!isset($customer_data[$customer_id])) {
                $customer_data[$customer_id] = array(
                    'dates' => array(),
                    'total_spent' => 0,
                    'name' => $booking->getDisplayName(),
                );
            }
            
            $customer_data[$customer_id]['dates'][] = $booking_date;
            $customer_data[$customer_id]['total_spent'] += $amount;
            
            // Track services
            foreach ($booking->getBookingServices()->getItems() as $bookingService) {
                $service = $bookingService->getService();
                if ($service) {
                    $service_name = $service->getName();
                    if (!isset($service_frequency[$service_name])) {
                        $service_frequency[$service_name] = 0;
                    }
                    $service_frequency[$service_name]++;
                }
            }
        }

        // Calculate metrics
        $total_customers = count($customer_data);
        $total_bookings = count($bookings);
        $total_revenue = 0;
        $visit_intervals = array();
        $clv_distribution = array(
            '0-100' => 0,
            '100-250' => 0,
            '250-500' => 0,
            '500-1000' => 0,
            '1000+' => 0,
        );

        foreach ($customer_data as $customer_id => $data) {
            $total_revenue += $data['total_spent'];
            
            // CLV distribution
            if ($data['total_spent'] >= 1000) {
                $clv_distribution['1000+']++;
            } elseif ($data['total_spent'] >= 500) {
                $clv_distribution['500-1000']++;
            } elseif ($data['total_spent'] >= 250) {
                $clv_distribution['250-500']++;
            } elseif ($data['total_spent'] >= 100) {
                $clv_distribution['100-250']++;
            } else {
                $clv_distribution['0-100']++;
            }
            
            // Calculate visit intervals (days between visits)
            if (count($data['dates']) > 1) {
                usort($data['dates'], function($a, $b) {
                    return $a->getTimestamp() - $b->getTimestamp();
                });
                
                for ($i = 1; $i < count($data['dates']); $i++) {
                    $interval_days = $data['dates'][$i]->diff($data['dates'][$i - 1])->days;
                    $visit_intervals[] = $interval_days;
                }
            }
        }

        // Calculate average metrics
        $avg_clv = $total_customers > 0 ? $total_revenue / $total_customers : 0;
        $avg_visits_per_customer = $total_customers > 0 ? $total_bookings / $total_customers : 0;
        $avg_visit_interval = count($visit_intervals) > 0 
            ? array_sum($visit_intervals) / count($visit_intervals) 
            : 0;

        // Calculate projected annual frequency
        // If we have data for less than a year, project it
        $visits_per_year = $period_days > 0 
            ? ($avg_visits_per_customer / $period_days) * 365 
            : 0;

        // Sort service frequency
        arsort($service_frequency);
        $top_services = array_slice($service_frequency, 0, 10, true);

        return $this->success_response(array(
            'avg_customer_lifetime_value' => round($avg_clv, 2),
            'avg_visits_per_customer' => round($avg_visits_per_customer, 2),
            'avg_days_between_visits' => round($avg_visit_interval, 1),
            'projected_annual_visits' => round($visits_per_year, 1),
            'clv_distribution' => $clv_distribution,
            'top_services_by_frequency' => $top_services,
            'total_customers' => $total_customers,
            'total_bookings' => $total_bookings,
            'period_days' => $period_days,
        ));
    }

    /**
     * Get customer no-show history
     */
    public function get_customer_no_shows($request)
    {
        $customer_id = (int) $request['id'];
        $limit = (int) $request->get_param('limit');

        // Validate customer exists
        $customer = new SLN_Wrapper_Customer(new \WP_User($customer_id));
        if ($customer->isEmpty()) {
            return new \WP_Error(
                'customer_not_found',
                __('Customer not found', 'salon-booking-system'),
                array('status' => 404)
            );
        }

        // Get no-show bookings for this customer
        $args = array(
            'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
            'posts_per_page' => $limit,
            'author' => $customer_id,
            'post_status' => array('publish', 'pending', 'trash'),
            'meta_query' => array(
                array(
                    'key' => 'no_show',
                    'value' => '1',
                ),
            ),
            'orderby' => 'meta_value',
            'meta_key' => 'no_show_marked_at',
            'order' => 'DESC',
        );

        $no_show_posts = get_posts($args);
        $no_shows = array();

        foreach ($no_show_posts as $post) {
            $booking = SLN_Plugin::getInstance()->createBooking($post->ID);
            $marked_at = get_post_meta($post->ID, 'no_show_marked_at', true);
            $marked_by = get_post_meta($post->ID, 'no_show_marked_by', true);
            
            $marked_by_name = '';
            if ($marked_by) {
                $user = get_user_by('id', $marked_by);
                if ($user) {
                    $marked_by_name = $user->display_name;
                }
            }

            // Get services
            $services = array();
            foreach ($booking->getBookingServices()->getItems() as $booking_service) {
                $services[] = $booking_service->getService()->getName();
            }

            $no_shows[] = array(
                'id' => $post->ID,
                'booking_date' => $booking->getDate()->format('Y-m-d H:i:s'),
                'marked_at' => $marked_at,
                'marked_by' => $marked_by,
                'marked_by_name' => $marked_by_name,
                'services' => $services,
                'status' => $booking->getStatus(),
            );
        }

        // Calculate customer statistics
        $total_bookings = $customer->getCountOfReservations();
        $no_show_count = count(get_posts(array(
            'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
            'posts_per_page' => -1,
            'author' => $customer_id,
            'post_status' => array('publish', 'pending', 'trash'),
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'no_show',
                    'value' => '1',
                ),
            ),
        )));

        $no_show_rate = $total_bookings > 0 ? round(($no_show_count / $total_bookings) * 100, 2) : 0;

        return rest_ensure_response(array(
            'customer_id' => $customer_id,
            'customer_name' => $customer->getDisplayName(),
            'no_shows' => $no_shows,
            'statistics' => array(
                'total_no_shows' => $no_show_count,
                'total_bookings' => $total_bookings,
                'no_show_rate' => $no_show_rate,
            ),
        ));
    }

}