<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API_Mobile\Controller;

use WP_REST_Server;
use WP_Error;
use SLN_Plugin;
use SLN_Enum_DaysOfWeek;
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

    protected function get_primary_language() {
        // WPML Implementation - with proper initialization check
        if (defined('ICL_LANGUAGE_CODE') && function_exists('icl_object_id')) {
            global $sitepress;
            if ($sitepress && method_exists($sitepress, 'get_default_language')) {
                return $sitepress->get_default_language();
            }
        }

        // Polylang Integration
        if (function_exists('pll_default_language')) {
            return pll_default_language();
        }

        // WordPress core Fallback
        $locale = get_option('WPLANG');
        return !empty($locale) ? substr($locale, 0, 2) : 'en';
    }

    protected function apply_language_filters(&$prepared_args) {
        if (defined('ICL_LANGUAGE_CODE') && function_exists('icl_object_id')) {
            global $sitepress;
            if ($sitepress && method_exists($sitepress, 'get_default_language')) {
                $prepared_args['suppress_filters'] = false;
                $primary_language = $sitepress->get_default_language();
                $sitepress->switch_lang($primary_language);
                return;
            }
        }

        if (function_exists('pll_languages_list')) {
            $primary_language = $this->get_primary_language();
            $prepared_args['lang'] = $primary_language;
            return;
        }
    }

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
                        'default'     => -1,
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
                    'shop'      => array(
                        'description' => __('Shop ID.', 'salon-booking-system'),
                        'type'        => 'integer',
                        'default'     => null,
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
    }

    public function get_items( $request )
    {
        $prepared_args          = array();
        $prepared_args['order'] = isset($request['order']) && in_array(strtolower($request['order']), array('asc', 'desc')) ? $request['order'] : 'asc';

        $prepared_args['posts_per_page'] = is_null($request['per_page']) ? -1 : $request['per_page'];

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

        $this->apply_language_filters($prepared_args);

        $prepared_args = apply_filters('sln_api_assistants_get_items_prepared_args', $prepared_args, $request);

        $query = new WP_Query( $prepared_args );

        $assistants = array();
        foreach ( $query->posts as $assistant ) {
            $data = $this->prepare_item_for_response( $assistant, $request );

            $shop_id = $request->get_param('shop');
            if ($shop_id && class_exists('\SalonMultishop\Addon')) {
                $attendant = SLN_Plugin::getInstance()->createAttendant($assistant->ID);
                $attendant_shops = $attendant->getMeta('shops');
                if (!is_array($attendant_shops) || !in_array($shop_id, $attendant_shops)) {
                    continue;
                }
            }

            $assistants[] = $this->prepare_response_for_collection( $data, $request );
        }

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

        $response = $this->success_response(array('items' => $assistants));
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

    public function prepare_response_for_collection($attendant, $request = null)
    {
        $shop_id = $request ? $request->get_param('shop') : null;

        $current_attendant = $attendant;
        $shop_attendant = null;

        if ($shop_id && class_exists('\SalonMultishop\Addon')) {
            try {
                $plugin = SLN_Plugin::getInstance();
                $shop = $plugin->createFromPost($shop_id);
                $shop_attendant = $shop->getAttendantWrapper($attendant);
                $current_attendant = $shop_attendant;
            } catch (\Exception $e) {

            }
        }


        $availabilities = array();
        foreach ($current_attendant->getAvailabilityItems()->toArray() as $availability) {
            $data = $availability->getData();
            if (!$data) continue;

            $avDays = array();
            for ($i = 1; $i <= 7; $i++) {
                $apiDayKey = $i; // 1-7 (Mon-Sun)
                $pluginDayKey = $i + 1 > 7 ? ($i + 1) % 7 : $i + 1; // 1-7 (Sun-Sat)
                $avDays[$apiDayKey] = empty($data['days'][$pluginDayKey]) ? 0 : 1;
            }

            $availabilityItem = array(
                'days'                  => $avDays,
                'from'                  => isset($data['from']) && is_array($data['from']) ? $data['from'] : [],
                'to'                    => isset($data['to']) && is_array($data['to']) ? $data['to'] : [],
                'always'                => !empty($data['always']),
                'from_date'             => !empty($data['from_date']) ? $data['from_date'] : null,
                'to_date'               => !empty($data['to_date']) ? $data['to_date'] : null,
                'disable_second_shift'  => !empty($data['disable_second_shift']),
                'day_specific_service'  => !empty($data['day_specific_service']) ? $data['day_specific_service'] : '0'
            );

            if (!empty($data['select_specific_dates'])) {
                $availabilityItem['select_specific_dates'] = true;
                $availabilityItem['specific_dates'] = !empty($data['specific_dates']) ? $data['specific_dates'] : '';
                if ($availabilityItem['always'] && empty($availabilityItem['from']) && empty($availabilityItem['to'])) {
                    $settings = SLN_Plugin::getInstance()->getSettings();
                    $defaultAvailabilities = $settings->get('availabilities') ?: [];
                    if (!empty($defaultAvailabilities)) {
                        $availabilityItem['from'] = $defaultAvailabilities[0]['from'];
                        $availabilityItem['to'] = $defaultAvailabilities[0]['to'];
                    }
                }
            }

            $availabilities[] = $availabilityItem;
        }

        $holidays = array();
        foreach ($current_attendant->getHolidayItems()->toArray() as $holiday) {
            $data = $holiday->getData();
            if (!$data) continue;

            $holidays[] = array(
                'from_date' => $data['from_date'],
                'to_date'   => $data['to_date'],
                'from_time' => $data['from_time'],
                'to_time'   => $data['to_time'],
                'is_manual' => !empty($data['is_manual'])
            );
        }

        $response = array(
            'id'                    => $attendant->getId(),
            'name'                  => $attendant->getName(),
            'services'              => $current_attendant->getServicesIds(),
            'email'                 => $current_attendant->getEmail(),
            'phone_country_code'    => $attendant->getSmsPrefix(),
            'phone'                 => $current_attendant->getPhone(),
            'description'           => $attendant->getContent(),
            'availabilities'        => $availabilities,
            'holidays'              => $holidays,
            'image_url'             => (string) wp_get_attachment_url(get_post_thumbnail_id($attendant->getId())),
            'currency'              => SLN_Plugin::getInstance()->getSettings()->getCurrencySymbol(),

            'shops'                 => $shop_id ? array($shop_id) : $attendant->getMeta('shops'),
            'current_shop_id'       => $shop_id,
            'is_shop_specific'      => !is_null($shop_attendant),
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
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, error on create ('.$ex->getMessage().').', 'salon-booking-system' ), array( 'status' => 404 ) );
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
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, error on update ('.$ex->getMessage().').', 'salon-booking-system' ), array( 'status' => 404 ) );
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

}