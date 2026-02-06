<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API\Controller;

use WP_REST_Server;
use WP_Error;
use SLN_Plugin;
use SLN_Enum_DaysOfWeek;
use SLN_Enum_BookingStatus;
use WP_Query;

class Assistants_Controller extends REST_Controller
{
    const POST_TYPE = SLN_Plugin::POST_TYPE_ATTENDANT;

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'assistants';

    public function register_routes() {

        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
		'args'		      => apply_filters('sln_api_assistants_register_routes_get_items_args', array(
                    'order'      => array(
                        'description' => __('Order.', 'salon-booking-system'),
                        'type'        => 'string',
                        'enum'        => array('asc', 'desc'),
                        'default'     => 'asc',
                    ),
                    'orderby'      => array(
                        'description' => __('Order by.', 'salon-booking-system'),
                        'type'        => 'string',
                        'enum'        => array('id', 'name'),
                        'default'     => 'id',
                    ),
                    'per_page'      => array(
                        'description' => __('Per page.', 'salon-booking-system'),
                        'type'        => 'integer',
                        'default'     => 10,
                    ),
                    'page'      => array(
                        'description' => __('Page.', 'salon-booking-system'),
                        'type'        => 'integer',
                        'default'     => 1,
                    ),
                    'offset'      => array(
                        'description' => __('Offset.', 'salon-booking-system'),
                        'type'        => 'integer',
                    ),
                )),
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
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
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
                    'order_by' => array(
                        'description' => __( 'Order by.', 'salon-booking-system' ),
                        'type'        => 'string',
                        'enum'        => array('revenue', 'bookings', 'utilization'),
                        'default'     => 'revenue',
                    ),
                    'order' => array(
                        'description' => __( 'Order.', 'salon-booking-system' ),
                        'type'        => 'string',
                        'enum'        => array('asc', 'desc'),
                        'default'     => 'desc',
                    ),
                    'shop' => array(
                        'description' => __( 'Shop ID for multi-shop filtering (0 = all shops).', 'salon-booking-system' ),
                        'type'        => 'integer',
                        'default'     => 0,
                    ),
                ),
            ),
        ) );
    }

    public function get_items( $request )
    {
        $prepared_args          = array();
        $prepared_args['order'] = isset($request['order']) && in_array(strtolower($request['order']), array('asc', 'desc')) ? $request['order'] : 'asc';

        $prepared_args['posts_per_page'] = is_null($request['per_page']) ? 10 : $request['per_page'];

        $request['orderby'] = is_null($request['orderby']) ? 'id' : $request['orderby'];
        $request['page']    = is_null($request['page']) ? 1 : $request['page'];

        if ( ! empty( $request['offset'] ) ) {
            $prepared_args['offset'] = $request['offset'];
        } else {
            $prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['posts_per_page'];
        }

        $orderby_possibles = array(
            'id'   => 'ID',
            'name' => 'title',
        );

        $prepared_args['orderby']	= $orderby_possibles[ $request['orderby'] ];
        $prepared_args['post_type']	= self::POST_TYPE;
        $prepared_args['post_status']	= 'publish';

        $assistants = array();

	$prepared_args = apply_filters('sln_api_assistants_get_items_prepared_args', $prepared_args, $request);

        $query = new WP_Query( $prepared_args );

        foreach ( $query->posts as $assistant ) {
            $data         = $this->prepare_item_for_response( $assistant, $request );
            $assistants[] = $this->prepare_response_for_collection( $data );
        }

        $response = $this->success_response(array('items' => $assistants));

        // Store pagination values for headers then unset for count query.
        $per_page = (int) $prepared_args['posts_per_page'];
        $page     = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

	$prepared_args['fields'] = 'ID';

        $total_assistants = $query->found_posts;

        if ( $total_assistants < 1 ) {
            // Out-of-bounds, run the query again without LIMIT for total count.
            unset( $prepared_args['posts_per_page'] );
            unset( $prepared_args['offset'] );
            $count_query = new WP_Query( $prepared_args );
            $total_assistants = $count_query->found_posts;
        }

        $response->header( 'X-WP-Total', (int) $total_assistants );

        $max_pages = ceil( $total_assistants / $per_page );

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

    public function prepare_item_for_response( $assistant, $request )
    {
        return SLN_Plugin::getInstance()->createAttendant($assistant);
    }

    public function prepare_response_for_collection($attendant)
    {
        $availabilities = array();

        foreach ($attendant->getAvailabilityItems()->toArray() as $availability) {

            $data = $availability->getData();

            if (!$data) {
                continue;
            }

            $avDays = array();

	    for ($i = 1; $i <= 7; $i++) {
		$apiDayKey    = $i; //1-7 (Mon-Sun)
		$pluginDayKey = $i + 1 > 7 ? ($i + 1) % 7 : $i + 1; //1-7 (Sun-Sat)
		$avDays[$apiDayKey] = empty( $data['days'][$pluginDayKey] ) ? 0 : 1;
            }

            $availabilities[] = array(
                'days'      => $avDays,
                'from'      => (object)$data['from'],
                'to'        => (object)$data['to'],
                'always'    => $data['always'],
                'from_date' => $data['from_date'],
                'to_date'   => $data['to_date'],
            );
        }

        $holidays = array();

        foreach ($attendant->getHolidayItems()->toArray() as $holiday) {

            $data = $holiday->getData();

            if (!$data) {
                continue;
            }

            $holidays[] = array(
                'from_date' => $data['from_date'],
                'to_date'   => $data['to_date'],
                'from_time' => $data['from_time'],
                'to_time'   => $data['to_time'],
            );
        }

	$response = array(
            'id'             => $attendant->getId(),
            'name'           => $attendant->getName(),
            'services'       => $attendant->getServicesIds(),
            'email'          => $attendant->getEmail(),
            'phone_country_code' => $attendant->getSmsPrefix(),
            'phone'          => $attendant->getPhone(),
            'description'    => $attendant->getContent(),
            'availabilities' => $availabilities,
            'holidays'       => $holidays,
            'image_url'      => (string) wp_get_attachment_url(get_post_thumbnail_id($attendant->getId())),
        );

        return apply_filters('sln_api_assistants_prepare_response_for_collection', $response, $attendant);
    }

    public function create_item( $request )
    {
        if ($request->get_param('id')) {

            $query = new WP_Query(array(
                'post_type' => self::POST_TYPE,
                'p'         => $request->get_param('id'),
            ));

            if ( $query->posts ) {
                return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, resource already exists.', 'salon-booking-system' ), array( 'status' => 409 ) );
            }
        }

        try {
            $id = $this->save_item_post($request);
        } catch (\Exception $ex) {
            return new WP_Error( 'salon_rest_cannot_view', __( sprintf('Sorry, error on create (%s).', $ex->getMessage()), 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        $response = $this->success_response(array('id' => $id));

        $response->set_status(201);

        return $response;
    }

    public function get_item( $request )
    {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'p'         => $request->get_param('id'),
        ));

        if ( ! $query->posts ) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, resource not found.', 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        $attendant = $this->prepare_item_for_response(current($query->posts), $request);
        $assistant = $this->prepare_response_for_collection($attendant);

        return $this->success_response(array('items' => array($assistant)));
    }

    public function update_item( $request )
    {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'p'         => $request->get_param('id'),
        ));

        if ( ! $query->posts ) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, resource not found.', 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        $attendant = $this->prepare_item_for_response(current($query->posts), $request);
        $assistant = $this->prepare_response_for_collection($attendant);

        try {
            $cloned_request = clone $request;
            $cloned_request->set_default_params($assistant);
            $this->save_item_post($cloned_request, $request->get_param('id'));
        } catch (\Exception $ex) {
            return new WP_Error( 'salon_rest_cannot_view', __( sprintf('Sorry, error on update (%s).', $ex->getMessage()), 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        return $this->success_response();
    }

    public function delete_item( $request )
    {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'p'         => $request->get_param('id'),
        ));

        if ( ! $query->posts ) {
            return new WP_Error( 'salon_rest_cannot_delete', __( 'Sorry, resource not found.', 'salon-booking-system' ), array( 'status' => 404 ) );
        }

        wp_trash_post($request->get_param('id'));

        return $this->success_response();
    }

    protected function save_item_post($request, $id = 0)
    {
        $availabilities     = array();
        $tmp_availabilities = array_filter($request->get_param('availabilities'));

        foreach ($tmp_availabilities as $availability) {

            $avDays = array();

	    for ($i = 1; $i <= 7; $i++) {
		$apiDayKey    = $i; //1-7 (Mon-Sun)
		$pluginDayKey = $i + 1 > 7 ? ($i + 1) % 7 : $i + 1; //1-7 (Sun-Sat)
		if ( ! empty( $availability['days'][$apiDayKey] ) ) {
		    $avDays[$pluginDayKey] = 1;
		}
            }

	    $_availability = array(
		'days'      => $avDays,
		'from'      => array(),
		'to'        => array(),
                'always'    => empty($availability['always']) ? 0 : 1,
                'from_date' => empty($availability['from_date']) ? null : $availability['from_date'],
		'to_date'   => empty($availability['to_date']) ? null : $availability['to_date'],
	    );

	    if ( ! empty( $availability['from'][0] ) &&  ! empty( $availability['to'][0] ) ) {
		$_availability['from'][] = $availability['from'][0];
		$_availability['to'][]   = $availability['to'][0];
	    } else {
		$_availability['disable_second_shift'] = 1;
	    }

	    if ( ! empty( $availability['from'][1] ) &&  ! empty( $availability['to'][1] ) ) {
		$_availability['from'][] = $availability['from'][1];
		$_availability['to'][]   = $availability['to'][1];
	    } else {
		$_availability['disable_second_shift'] = 1;
	    }

            $availabilities[] = $_availability;
        }

        $holidays     = array();
        $tmp_holidays = array_filter($request->get_param('holidays'));

        foreach ($tmp_holidays as $holiday) {
            $holidays[] = array(
                'from_date' => isset($holiday['from_date']) ? $holiday['from_date'] : '',
                'to_date'   => isset($holiday['to_date']) ? $holiday['to_date'] : '',
                'from_time' => isset($holiday['from_time']) ? $holiday['from_time'] : '',
                'to_time'   => isset($holiday['to_time']) ? $holiday['to_time'] : '',
            );
        }

        $id = wp_insert_post(array(
            'ID'          => $id,
            'post_title'  => $request->get_param('name'),
            'post_excerpt'=> $request->get_param('description'),
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'meta_input'  => array(
                '_sln_attendant_email'          => $request->get_param('email'),
                '_sln_attendant_phone'          => $request->get_param('phone'),
                '_sln_attendant_services'       => $request->get_param('services'),
                '_sln_attendant_availabilities' => $availabilities,
                '_sln_attendant_holidays'       => $holidays,
            ),
        ));

        if ( is_wp_error($id) ) {
            throw new \Exception(esc_html__( 'Save post error.', 'salon-booking-system' ));
        }

        $this->save_item_image($request->get_param('image_url'), $id);

	do_action('sln_api_assistants_save_item_post', $id, $request);

        return $id;
    }

    public function get_item_schema()
    {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'assistant',
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
                'name' => array(
                    'description' => __( 'The name for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'required'          => true,
                    ),
                ),
                'services' => array(
                    'description' => __( 'The services ids for the resource.', 'salon-booking-system' ),
                    'type'        => 'array',
                    'items'       => array(
                        'type' => 'integer',
                    ),
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'default' => array(),
                    ),
                ),
                'email' => array(
                    'description' => __( 'The email address for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'format'      => 'email',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'required' => true,
                    ),
                ),
                'phone' => array(
                    'description' => __( 'The phone number for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'format'      => 'phone',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'default' => '',
                    ),
                ),
                'description' => array(
                    'description' => __( 'The description for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ),
                ),
                'availabilities' => array(
                    'description' => __( 'The availabilities for the resource.', 'salon-booking-system' ),
                    'type'        => 'array',
                    'context'     => array( 'view', 'edit' ),
                    'items'  => array(
                        'description' => __( 'The availability item.', 'salon-booking-system' ),
                        'type'        => 'object',
                        'context'     => array( 'view', 'edit' ),
                        'properties'  => array(
                            'days' => array(
                                'description' => __( 'The days.', 'salon-booking-system' ),
                                'type'        => 'object',
                                'properties'  => array(
                                    0 => array(
                                        'description' => __( 'The sunday.', 'salon-booking-system' ),
                                        'type'        => 'integer',
                                        'enum'        => array(0, 1),
                                    ),
                                    1 => array(
                                        'description' => __( 'The monday.', 'salon-booking-system' ),
                                        'type'        => 'integer',
                                        'enum'        => array(0, 1),
                                    ),
                                    2 => array(
                                        'description' => __( 'The tuesday.', 'salon-booking-system' ),
                                        'type'        => 'integer',
                                        'enum'        => array(0, 1),
                                    ),
                                    3 => array(
                                        'description' => __( 'The wednesday.', 'salon-booking-system' ),
                                        'type'        => 'integer',
                                        'enum'        => array(0, 1),
                                    ),
                                    4 => array(
                                        'description' => __( 'The thursday.', 'salon-booking-system' ),
                                        'type'        => 'integer',
                                        'enum'        => array(0, 1),
                                    ),
                                    5 => array(
                                        'description' => __( 'The friday.', 'salon-booking-system' ),
                                        'type'        => 'integer',
                                        'enum'        => array(0, 1),
                                    ),
                                    6 => array(
                                        'description' => __( 'The saturday.', 'salon-booking-system' ),
                                        'type'        => 'integer',
                                        'enum'        => array(0, 1),
                                    ),
                                ),
                                'context'     => array( 'view', 'edit' ),
                                'required'    => true,
                            ),
                            'from' => array(
                                'description' => __( 'The from time.', 'salon-booking-system' ),
                                'type'        => 'object',
                                'properties'  => array(
                                    0 => array(
                                        'type'   => 'string',
                                        'format' => 'HH:ii',
                                    ),
                                    1 => array(
                                        'type'   => 'string',
                                        'format' => 'HH:ii',
                                    ),
                                ),
                                'context'     => array( 'view', 'edit' ),
                                'required'    => true,
                            ),
                            'to' => array(
                                'description' => __( 'The to time.', 'salon-booking-system' ),
                                'type'        => 'object',
                                'properties'  => array(
                                    0 => array(
                                        'type'   => 'string',
                                        'format' => 'HH:ii',
                                    ),
                                    1 => array(
                                        'type'   => 'string',
                                        'format' => 'HH:ii',
                                    ),
                                ),
                                'context'     => array( 'view', 'edit' ),
                                'required'    => true,
                            ),
                            'always' => array(
                                'description' => __( 'The always.', 'salon-booking-system' ),
                                'type'        => 'integer',
                                'enum'        => array(0, 1),
                                'context'     => array( 'view', 'edit' ),
                            ),
                            'from_date' => array(
                                'description' => __( 'The from date.', 'salon-booking-system' ),
                                'type'        => 'string',
                                'format'      => 'YYYY-MM-DD',
                                'context'     => array( 'view', 'edit' ),
                            ),
                            'to_date' => array(
                                'description' => __( 'The to date.', 'salon-booking-system' ),
                                'type'        => 'string',
                                'format'      => 'YYYY-MM-DD',
                                'context'     => array( 'view', 'edit' ),
                            ),
                        ),
                    ),
                    'arg_options' => array(
                        'default'           => array(),
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                    ),
                ),
                'holidays' => array(
                    'description' => __( 'The holidays for the resource.', 'salon-booking-system' ),
                    'type'        => 'array',
                    'context'     => array( 'view', 'edit' ),
                    'items'  => array(
                        'description' => __( 'The holiday item.', 'salon-booking-system' ),
                        'type'        => 'object',
                        'context'     => array( 'view', 'edit' ),
                        'properties'  => array(
                            'from_date' => array(
                                'description' => __( 'The from date.', 'salon-booking-system' ),
                                'type'        => 'string',
                                'format'      => 'YYYY-MM-DD',
                                'context'     => array( 'view', 'edit' ),
                                'required'    => true,
                            ),
                            'to_date' => array(
                                'description' => __( 'The to date.', 'salon-booking-system' ),
                                'type'        => 'string',
                                'format'      => 'YYYY-MM-DD',
                                'context'     => array( 'view', 'edit' ),
                                'required'    => true,
                            ),
                            'from_time' => array(
                                'description' => __( 'The from time.', 'salon-booking-system' ),
                                'type'        => 'string',
                                'format'      => 'HH:ii',
                                'context'     => array( 'view', 'edit' ),
                                'required'    => true,
                            ),
                            'to_time' => array(
                                'description' => __( 'The to time.', 'salon-booking-system' ),
                                'type'        => 'string',
                                'format'      => 'HH:ii',
                                'context'     => array( 'view', 'edit' ),
                                'required'    => true,
                            ),
                        ),
                    ),
                    'arg_options' => array(
                        'validate_callback' => array($this, 'rest_validate_request_arg'),
                        'default'           => array(),
                    ),
                ),
                'image_url' => array(
                    'description' => __( 'The image url for the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                    'arg_options' => array(
                        'default' => '',
                    ),
                ),
            ),
        );

        return apply_filters('sln_api_assistants_get_item_schema', $schema);
    }

    /**
     * Get assistant performance statistics
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_stats( $request )
    {
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $order_by = $request->get_param('order_by');
        $order = $request->get_param('order');
        $shop_id = (int) $request->get_param('shop');

        // Get all assistants
        $assistants_query = new WP_Query(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ));

        $assistants_stats = array();

        // Build meta query for bookings
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
            return $this->success_response(array('assistants' => array()));
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

        // Initialize stats for each assistant
        foreach ($assistants_query->posts as $assistant_post) {
            $assistant = SLN_Plugin::getInstance()->createAttendant($assistant_post->ID);
            $assistants_stats[$assistant_post->ID] = array(
                'assistant_id'       => $assistant_post->ID,
                'assistant_name'     => $assistant->getName(),
                'bookings_count'     => 0,
                'total_revenue'      => 0.0,
                'avg_booking_value'  => 0.0,
                'total_hours_worked' => 0,
                'utilization_rate'   => 0.0,
            );
        }

        // Process bookings
        foreach ($bookings_query->posts as $booking_post) {
            $booking = SLN_Plugin::getInstance()->createBooking($booking_post->ID);
            
            $processed_assistants = array();
            
            foreach ($booking->getBookingServices()->getItems() as $bookingService) {
                $attendant = $bookingService->getAttendant();
                $service_duration = $bookingService->getDuration(); // Get service-specific duration
                
                if ($attendant) {
                    $service_assistants = is_array($attendant) ? $attendant : array($attendant);
                    $num_assistants = count($service_assistants);
                    
                    // ✅ FIX: Get service price with fallback for missing prices
                    $service_price = $bookingService->getPrice();
                    
                    // ✅ FALLBACK: If price is 0 or missing, calculate from service base price
                    // This handles older bookings where individual service prices weren't stored
                    if (empty($service_price) || $service_price == 0) {
                        $service = $bookingService->getService();
                        
                        // Get variable price by attendant if enabled, otherwise use base price
                        $attendant_id = is_array($attendant) ? (isset($attendant[0]) ? $attendant[0]->getId() : null) : $attendant->getId();
                        if ($service->getVariablePriceEnabled() && $attendant_id && $service->getVariablePrice($attendant_id) !== '') {
                            $service_price = floatval($service->getVariablePrice($attendant_id));
                        } else {
                            $service_price = floatval($service->getPrice());
                        }
                        
                        // Apply quantity multiplier for variable duration services
                        $variable_duration = get_post_meta($service->getId(), '_sln_service_variable_duration', true);
                        if ($variable_duration) {
                            $service_price = $service_price * $bookingService->getCountServices();
                        }
                    }
                    
                    // ✅ FIX: Handle negative prices from excessive discounts (clamp to 0)
                    // Negative prices occur when discounts exceed service price (e.g., 100%+ discounts)
                    $service_price = max(0, $service_price);
                    
                    // ✅ FIX: Split service price evenly among assistants for this service
                    $revenue_share = $num_assistants > 0 ? $service_price / $num_assistants : 0;
                    
                    // ✅ FIX: Split service count evenly among assistants
                    $service_count_share = $num_assistants > 0 ? $bookingService->getCountServices() / $num_assistants : 0;
                    
                    foreach ($service_assistants as $att) {
                        $att_id = $att->getId();
                        
                        if (isset($assistants_stats[$att_id])) {
                            // ✅ FIX: Count services, not bookings (to match RevenuesByAssistantsReport)
                            // Use service ID + assistant ID as unique key to avoid double-counting
                            $service_key = $att_id . '_' . $bookingService->getService()->getId();
                            if (!in_array($service_key, $processed_assistants)) {
                                $assistants_stats[$att_id]['bookings_count'] += $service_count_share;
                                $processed_assistants[] = $service_key;
                            }
                            
                            // ✅ FIX: Add revenue share (split service price, not booking amount)
                            $assistants_stats[$att_id]['total_revenue'] += $revenue_share;
                            
                            // Add hours (convert duration to hours using plugin helper)
                            if ($service_duration) {
                                $minutes = \SLN_Func::getMinutesFromDuration($service_duration);
                                if ($minutes > 0) {
                                    // ✅ FIX: Split hours evenly among assistants for this service
                                    $hours_share = $num_assistants > 0 ? ($minutes / 60) / $num_assistants : 0;
                                    $assistants_stats[$att_id]['total_hours_worked'] += $hours_share;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Calculate averages and utilization
        foreach ($assistants_stats as $assistant_id => $stats) {
            if ($stats['bookings_count'] > 0) {
                $assistants_stats[$assistant_id]['avg_booking_value'] = round($stats['total_revenue'] / $stats['bookings_count'], 2);
            }
            $assistants_stats[$assistant_id]['total_revenue'] = round($stats['total_revenue'], 2);
            $assistants_stats[$assistant_id]['total_hours_worked'] = round($stats['total_hours_worked'], 2);
            
            // Calculate utilization based on assistant's actual availability schedule and holidays
            $assistant = SLN_Plugin::getInstance()->createAttendant($assistant_id);
            $available_hours = $this->calculateAvailableHours($assistant, $start_date, $end_date);
            
            // Calculate utilization rate (avoid division by zero)
            if ($available_hours > 0) {
                $assistants_stats[$assistant_id]['utilization_rate'] = round(($stats['total_hours_worked'] / $available_hours) * 100, 2);
            } else {
                $assistants_stats[$assistant_id]['utilization_rate'] = 0;
            }
        }

        // Sort based on order_by
        usort($assistants_stats, function($a, $b) use ($order_by, $order) {
            $field_map = array(
                'revenue'     => 'total_revenue',
                'bookings'    => 'bookings_count',
                'utilization' => 'utilization_rate',
            );
            
            $field = $field_map[$order_by] ?? 'total_revenue';
            $comparison = $a[$field] <=> $b[$field];
            
            return $order === 'asc' ? $comparison : -$comparison;
        });

        return $this->success_response(array('items' => $assistants_stats));
    }

    /**
     * Calculate total available hours for an assistant in a date range,
     * considering their weekly schedule and holidays.
     * 
     * @param \SLN_Wrapper_Attendant $assistant
     * @param string $start_date YYYY-MM-DD
     * @param string $end_date YYYY-MM-DD
     * @return float Available hours
     */
    private function calculateAvailableHours($assistant, $start_date, $end_date) {
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        $end->setTime(23, 59, 59); // Include the end date
        
        // Get assistant's availability rules (or fall back to salon general availability)
        $availabilityItems = $assistant->getAvailabilityItems();
        $weekDayRules = $availabilityItems->getWeekDayRules();
        
        // If assistant has no schedule, use salon general availability
        if (empty($weekDayRules) || $this->isEmptySchedule($weekDayRules)) {
            $settings = SLN_Plugin::getInstance()->getSettings();
            $availabilityItems = $settings->getAvailabilityItems();
            $weekDayRules = $availabilityItems->getWeekDayRules();
        }
        
        // Get holiday rules for this assistant
        $holidayItems = $assistant->getHolidayItems();
        
        $totalAvailableHours = 0;
        $current = clone $start;
        
        // Iterate through each day in the range
        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('w'); // 0 (Sunday) - 6 (Saturday)
            
            // Check if this day is a holiday
            if ($this->isHoliday($current, $holidayItems)) {
                // Skip this day - no available hours
                $current->modify('+1 day');
                continue;
            }
            
            // Get scheduled hours for this day of week
            if (isset($weekDayRules[$dayOfWeek])) {
                $dayRules = $weekDayRules[$dayOfWeek];
                
                if (!empty($dayRules['from']) && !empty($dayRules['to'])) {
                    $fromCount = count($dayRules['from']);
                    
                    for ($i = 0; $i < $fromCount; $i++) {
                        if (isset($dayRules['from'][$i]) && isset($dayRules['to'][$i])) {
                            $from = \SLN_Func::getMinutesFromDuration($dayRules['from'][$i]);
                            $to = \SLN_Func::getMinutesFromDuration($dayRules['to'][$i]);
                            
                            if ($to > $from) {
                                $totalAvailableHours += ($to - $from) / 60;
                            }
                        }
                    }
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $totalAvailableHours;
    }
    
    /**
     * Check if a given date is a holiday for the assistant
     * 
     * @param \DateTime $date
     * @param \SLN_Helper_HolidayItems $holidayItems
     * @return bool
     */
    private function isHoliday($date, $holidayItems) {
        if (!$holidayItems) {
            return false;
        }
        
        foreach ($holidayItems->toArray() as $holiday) {
            $data = $holiday->getData();
            
            if (!$data || empty($data['from_date']) || empty($data['to_date'])) {
                continue;
            }
            
            $holidayStart = new \DateTime($data['from_date']);
            $holidayEnd = new \DateTime($data['to_date']);
            $holidayEnd->setTime(23, 59, 59);
            
            // Check if date falls within holiday range
            if ($date >= $holidayStart && $date <= $holidayEnd) {
                // Check if it's a full-day holiday or partial
                $fromTime = isset($data['from_time']) ? $data['from_time'] : '';
                $toTime = isset($data['to_time']) ? $data['to_time'] : '';
                
                // If no specific times set, it's a full-day holiday
                if (empty($fromTime) && empty($toTime)) {
                    return true;
                }
                
                // TODO: For partial day holidays, we'd need more complex logic
                // For now, treat any holiday entry as full-day
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a weekly schedule is empty (no working hours defined)
     * 
     * @param array $weekDayRules
     * @return bool
     */
    private function isEmptySchedule($weekDayRules) {
        foreach ($weekDayRules as $dayRules) {
            if (!empty($dayRules['from']) && !empty($dayRules['to'])) {
                foreach ($dayRules['from'] as $i => $from) {
                    if (!empty($from) && !empty($dayRules['to'][$i])) {
                        return false; // Found at least one time slot
                    }
                }
            }
        }
        return true; // No time slots found
    }

}