<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API_Mobile\Controller;

use WP_REST_Server;
use WP_Error;
use SLN_Plugin;
use WP_Query;

class Resources_Controller extends REST_Controller
{
    const POST_TYPE = SLN_Plugin::POST_TYPE_RESOURCE;

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'resources';

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => apply_filters('sln_api_resources_register_routes_get_items_args', array(
                    'order' => array(
                        'description' => __('Order.', 'salon-booking-system'),
                        'type' => 'string',
                        'enum' => array('asc', 'desc'),
                        'default' => 'asc',
                    ),
                    'orderby' => array(
                        'description' => __('Order by.', 'salon-booking-system'),
                        'type' => 'string',
                        'enum' => array('id', 'name'),
                        'default' => 'id',
                    ),
                    'per_page' => array(
                        'description' => __('Per page.', 'salon-booking-system'),
                        'type' => 'integer',
                        'default' => -1,
                    ),
                    'page' => array(
                        'description' => __('Page.', 'salon-booking-system'),
                        'type' => 'integer',
                        'default' => 1,
                    ),
                    'offset' => array(
                        'description' => __('Offset.', 'salon-booking-system'),
                        'type' => 'integer',
                    ),
                )),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            'args' => array(
                'id' => array(
                    'description' => __('Unique identifier for the resource.', 'salon-booking-system'),
                    'type' => 'integer',
                    'required' => true,
                ),
            ),
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_item'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
                'args' => array(
                    'context' => $this->get_context_param(array('default' => 'view')),
                ),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_item'),
                'permission_callback' => array($this, 'update_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_item'),
                'permission_callback' => array($this, 'delete_item_permissions_check'),
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));
    }

    public function get_items($request)
    {
        $prepared_args = array();
        $prepared_args['order'] = isset($request['order']) && in_array(strtolower($request['order']),
            array('asc', 'desc')) ? $request['order'] : 'asc';

        $prepared_args['posts_per_page'] = is_null($request['per_page']) ? -1 : $request['per_page'];

        $request['orderby'] = is_null($request['orderby']) ? 'id' : $request['orderby'];
        $request['page'] = is_null($request['page']) ? 1 : $request['page'];

        if (!empty($request['offset'])) {
            $prepared_args['offset'] = $request['offset'];
        } else {
            $prepared_args['offset'] = ($request['page'] - 1) * $prepared_args['posts_per_page'];
        }

        $orderby_possibles = array(
            'id' => 'ID',
            'name' => 'title',
        );

        $prepared_args['orderby'] = $orderby_possibles[$request['orderby']];
        $prepared_args['post_type'] = self::POST_TYPE;
        $prepared_args['post_status'] = 'any';

        $resources = array();

        $prepared_args = apply_filters('sln_api_resources_get_items_prepared_args', $prepared_args, $request);

        $query = new WP_Query($prepared_args);

        foreach ($query->posts as $resource) {
            $data = $this->prepare_item_for_response($resource, $request);
            $resources[] = $this->prepare_response_for_collection($data);
        }

        $response = $this->success_response(array('items' => $resources));

        // Store pagination values for headers then unset for count query.
        $per_page = (int)$prepared_args['posts_per_page'];
        $page = ceil((((int)$prepared_args['offset']) / $per_page) + 1);

        $prepared_args['fields'] = 'ID';

        $total_resources = $query->found_posts;

        if ($total_resources < 1) {
            // Out-of-bounds, run the query again without LIMIT for total count.
            unset($prepared_args['posts_per_page']);
            unset($prepared_args['offset']);
            $count_query = new WP_Query($prepared_args);
            $total_resources = $count_query->found_posts;
        }

        $response->header('X-WP-Total', (int)$total_resources);

        $max_pages = ceil($total_resources / $per_page);

        $response->header('X-WP-TotalPages', (int)$max_pages);

        $base = add_query_arg($request->get_query_params(),
            rest_url(sprintf('/%s/%s', $this->namespace, $this->rest_base)));

        if ($page > 1) {
            $prev_page = $page - 1;
            if ($prev_page > $max_pages) {
                $prev_page = $max_pages;
            }
            $prev_link = add_query_arg('page', $prev_page, $base);
            $response->link_header('prev', $prev_link);
        }

        if ($max_pages > $page) {
            $next_page = $page + 1;
            $next_link = add_query_arg('page', $next_page, $base);
            $response->link_header('next', $next_link);
        }

        return $response;
    }

    public function prepare_item_for_response($resource, $request)
    {
        return SLN_Plugin::getInstance()->createResource($resource);
    }

    public function prepare_response_for_collection($resource)
    {
        $response = array(
            'id' => $resource->getId(),
            'name' => $resource->getTitle(),
            'services' => $resource->getServices(),
            'enabled' => $resource->getEnabled(),
            'unit' => $resource->getUnitPerHour()
        );

        return apply_filters('sln_api_resources_prepare_response_for_collection', $response, $resource);
    }

    public function create_item($request)
    {
        if ($request->get_param('id')) {
            $query = new WP_Query(array(
                'post_type' => self::POST_TYPE,
                'p' => $request->get_param('id'),
            ));

            if ($query->posts) {
                return new WP_Error('salon_rest_cannot_view',
                    __('Sorry, resource already exists.', 'salon-booking-system'), array('status' => 409));
            }
        }

        try {
            $id = $this->save_item_post($request);
        } catch (\Exception $ex) {
            return new WP_Error('salon_rest_cannot_view',
                __('Sorry, error on create (' . $ex->getMessage() . ').', 'salon-booking-system'),
                array('status' => 404));
        }

        $response = $this->success_response(array('id' => $id));

        $response->set_status(201);

        return $response;
    }

    public function get_item($request)
    {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'p' => $request->get_param('id'),
        ));

        if (!$query->posts) {
            return new WP_Error('salon_rest_cannot_view', __('Sorry, resource not found.', 'salon-booking-system'),
                array('status' => 404));
        }

        $resource = $this->prepare_item_for_response(current($query->posts), $request);
        $assistant = $this->prepare_response_for_collection($resource);

        return $this->success_response(array('items' => array($resource)));
    }

    public function update_item($request)
    {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'p' => $request->get_param('id'),
        ));

        if (!$query->posts) {
            return new WP_Error('salon_rest_cannot_view', __('Sorry, resource not found.', 'salon-booking-system'),
                array('status' => 404));
        }

        $resourceObj = $this->prepare_item_for_response(current($query->posts), $request);
        $resource = $this->prepare_response_for_collection($resourceObj);

        try {
            $cloned_request = clone $request;
            $cloned_request->set_default_params($resource);
            $this->save_item_post($cloned_request, $request->get_param('id'));
        } catch (\Exception $ex) {
            return new WP_Error('salon_rest_cannot_view',
                __('Sorry, error on update (' . $ex->getMessage() . ').', 'salon-booking-system'),
                array('status' => 404));
        }

        return $this->success_response();
    }

    public function delete_item($request)
    {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'p' => $request->get_param('id'),
        ));

        if (!$query->posts) {
            return new WP_Error('salon_rest_cannot_delete', __('Sorry, resource not found.', 'salon-booking-system'),
                array('status' => 404));
        }

        wp_trash_post($request->get_param('id'));

        return $this->success_response();
    }

    protected function save_item_post($request, $id = 0)
    {
        $id = wp_insert_post(array(
            'ID' => $id,
            'post_title' => $request->get_param('name'),
            'post_excerpt' => $request->get_param('description'),
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => array(
                '_sln_resource_services' => $request->get_param('services'),
                '_sln_resource_enabled' => $request->get_param('enabled'),
                '_sln_resource_unit' => $request->get_param('unit'),
            ),
        ));

        if (is_wp_error($id)) {
            throw new \Exception(esc_html__('Save post error.', 'salon-booking-system'));
        }

        do_action('sln_api_resources_save_item_post', $id, $request);

        return $id;
    }

    public function get_item_schema()
    {
        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'resource',
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('Unique identifier for the resource.', 'salon-booking-system'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'arg_options' => array(
                        'readonly' => true,
                    ),
                ),
                'name' => array(
                    'description' => __('The name for the resource.', 'salon-booking-system'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'required' => true,
                    ),
                ),
                'services' => array(
                    'description' => __('The services ids for the resource.', 'salon-booking-system'),
                    'type' => 'array',
                    'items' => array(
                        'type' => 'integer',
                    ),
                    'context' => array('view', 'edit'),
                    'arg_options' => array(
                        'default' => array(),
                    ),
                ),
                'enabled' => array(
                    'description' => __('The enabled for the resource.', 'salon-booking-system'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'enum' => array(0, 1),
                    'arg_options' => array(
                        'default' => 1,
                    ),
                ),
                'unit' => array(
                    'description' => __('The unit for the resource.', 'salon-booking-system'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'enum' => range(1, 20),
                    'arg_options' => array(
                        'default' => 1,
                    ),
                ),
            ),
        );

        return apply_filters('sln_api_resources_get_item_schema', $schema);
    }

}