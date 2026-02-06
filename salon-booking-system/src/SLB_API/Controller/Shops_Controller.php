<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API\Controller;

use WP_REST_Server;
use WP_Query;

class Shops_Controller extends REST_Controller
{
    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'shops';

    /**
     * Register routes
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
            ),
        ));
    }

    /**
     * Get list of shops
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_items($request)
    {
        // Return empty array if Multi-Shop addon is not active
        if (!class_exists('\SalonMultishop\Addon')) {
            return $this->success_response(array('items' => array()));
        }

        // Query shops (stored as custom post type)
        $args = array(
            'post_type'      => 'sln_shop',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        $shops_query = new WP_Query($args);
        $shops = array();

        foreach ($shops_query->posts as $shop_post) {
            $shops[] = array(
                'id'      => $shop_post->ID,
                'name'    => $shop_post->post_title,
                'address' => get_post_meta($shop_post->ID, '_sln_shop_address', true),
            );
        }

        return $this->success_response(array('items' => $shops));
    }

    /**
     * Check if user can read shops
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function get_items_permissions_check($request)
    {
        // Admins have full access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Shop managers can view shops list
        if ($this->is_shop_manager()) {
            return true;
        }
        
        return false;
    }
}

