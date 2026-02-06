<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API\Controller;

use WP_REST_Controller;
use WP_Error;
use SLB_API\Plugin;

abstract class REST_Controller extends WP_REST_Controller
{
    protected $namespace = Plugin::BASE_API;

    public function permissions_check($capability, $object_id = 0)
    {
        // Administrators have full access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Shop managers can read data (for reports/stats)
        if ($capability === 'read' && $this->is_shop_manager()) {
            return true;
        }
        
        // Try to get POST_TYPE constant - some controllers don't have it
        $post_type = null;
        try {
            $reflection = new \ReflectionClass(get_class($this));
            if ($reflection->hasConstant('POST_TYPE')) {
                $post_type = $reflection->getConstant('POST_TYPE');
            }
        } catch (\Exception $e) {
            // Ignore reflection errors
        }
        
        // If no POST_TYPE (like Shops, Customers controllers)
        if (!$post_type) {
            // Shop managers can read
            if ($capability === 'read' && $this->is_shop_manager()) {
                return true;
            }
            // Otherwise, require manage_salon capability
            return current_user_can('manage_salon');
        }

        // Standard post type capability check
        $object       = get_post_type_object($post_type);
        $capabilities = is_null($object) ? array() : (array)$object->cap;

        return current_user_can( isset($capabilities[$capability]) ? $capabilities[$capability] : '', $object_id );
    }

    protected function success_response(array $response = array())
    {
        return rest_ensure_response(array_merge(array(
            'status' => 'OK',
        ), $response));
    }
    protected function is_image($filename) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, $allowedExtensions);
    }

    protected function save_item_image($image_url = '', $id = 0)
    {
        if (!$image_url) {
            delete_post_thumbnail($id);
            return;
        }

        $filename  = basename($image_url);

        $uploaddir  = wp_upload_dir();
        $uploadfile = $uploaddir['path'] . '/' . $filename;
        $wp_check_image = $this->is_image(basename($filename));

        if(!$wp_check_image){
            throw new \Exception(esc_html__( 'Upload image error.', 'salon-booking-system' ));
        }
        $contents = file_get_contents($image_url);
        $savefile = fopen($uploadfile, 'w');

        fwrite($savefile, $contents);
        fclose($savefile);

        $wp_filetype = wp_check_filetype(basename($filename), null);

        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => $filename,
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $uploadfile, $id, true);

        if (is_wp_error($attach_id)) {
            throw new \Exception(esc_html__( 'Upload image error.', 'salon-booking-system' ));
        }

        $imagenew     = get_post($attach_id);
        $fullsizepath = get_attached_file($imagenew->ID);

        if (!function_exists('wp_generate_attachment_metadata')) {
            include_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attach_data  = wp_generate_attachment_metadata($attach_id, $fullsizepath);
        wp_update_attachment_metadata($attach_id, $attach_data);

        set_post_thumbnail($id, $attach_id);
    }

    public function rest_validate_not_empty_string($value, $request, $param)
    {
        $result = rest_validate_request_arg($value, $request, $param);

        if ($result !== true) {
            return $result;
        }

        if (trim($value) === '') {
            return new WP_Error( 'rest_invalid_param', sprintf(
                // translators: %1$s will be replaced by the rest validate parameter
                __( '%1$s is empty.' ), $param ) );
        }

        return true;
    }

    public function rest_validate_request_arg($value, $request, $param)
    {
        $result = rest_validate_request_arg($value, $request, $param);

        if ($result !== true) {
            return $result;
        }

        $attributes = $request->get_attributes();

	if ( ! isset( $attributes['args'][ $param ] ) || ! is_array( $attributes['args'][ $param ] ) ) {
            return true;
	}

        return $this->rest_validate_value_from_schema($value, $attributes['args'][ $param ], $param);
    }

    protected function rest_validate_value_from_schema( $value, $args, $param = '' )
    {
        if ( 'array' === $args['type'] ) {
            foreach ( $value as $index => $v ) {
                $is_valid = $this->rest_validate_value_from_schema( $v, $args['items'], $param . '[' . $index . ']' );
                if ( is_wp_error( $is_valid ) ) {
                        return $is_valid;
                }
            }
	}

        if ( 'object' === $args['type'] ) {

            if ( $value instanceof stdClass ) {
                $value = (array) $value;
            }

            foreach ( $value as $property => $v ) {
                if ( isset( $args['properties'][ $property ] ) ) {
                    $is_valid = $this->rest_validate_value_from_schema( $v, $args['properties'][ $property ], $param . '[' . $property . ']' );
                    if ( is_wp_error( $is_valid ) ) {
                        return $is_valid;
                    }
                }
            }
	}

        if ( isset( $args['format'] ) && $value ) {
            switch ( $args['format'] ) {
                case 'YYYY-MM-DD' :
                    if ( !  preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! strtotime($value) ) {
                        return new WP_Error( 'rest_invalid_date', __( sprintf('%s is invalid date.', $param), 'salon-booking-system' ) );
                    }
                    break;
                case 'HH:ii' :
                    if ( ! preg_match('/^\d{2}:\d{2}$/', $value) || ! strtotime($value) ) {
                        return new WP_Error( 'rest_invalid_time', __( sprintf('%s is invalid time.', $param), 'salon-booking-system' ) );
                    }
                    break;
                case 'YYYY-MM-DD HH:ii:ss' :
                    if ( !  preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) || ! strtotime($value) ) {
                        return new WP_Error( 'rest_invalid_date_time', __( sprintf('%s is invalid date/time.', $param), 'salon-booking-system' ) );
                    }
                    break;
            }
	}

        return true;
    }

    public function get_items_permissions_check( $request )
    {
        if ( ! $this->permissions_check( 'read' ) ) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'salon-booking-system' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function create_item_permissions_check( $request )
    {
        if ( ! $this->permissions_check( 'create_posts' ) ) {
            return new WP_Error( 'salon_rest_cannot_create', __( 'Sorry, you cannot create resource.', 'salon-booking-system' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function get_item_permissions_check( $request )
    {
        if ( ! $this->permissions_check( 'read' ) ) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, you cannot view resource.', 'salon-booking-system' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function update_item_permissions_check( $request )
    {
        if ( ! $this->permissions_check( 'edit_posts' ) ) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, you cannot update resource.', 'salon-booking-system' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function delete_item_permissions_check( $request )
    {
        if ( ! $this->permissions_check( 'delete_posts' )) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, you cannot delete resource.', 'salon-booking-system' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function rest_validate_empty_string($value, $request, $param)
    {
        if ($value === '') {
            return true;
        }

        return rest_validate_request_arg($value, $request, $param);
    }

    /**
     * Check if current user is a shop manager
     * 
     * @return bool
     */
    protected function is_shop_manager()
    {
        $user = wp_get_current_user();
        
        if (!$user || !$user->ID) {
            return false;
        }
        
        // Check if user has shop_manager or sln_shop_manager role
        return in_array('shop_manager', (array) $user->roles) || 
               in_array('sln_shop_manager', (array) $user->roles);
    }

    /**
     * Get shop IDs assigned to current shop manager
     * 
     * @return array Array of shop IDs (empty if not a manager or no shops assigned)
     */
    protected function get_shop_manager_assigned_shops()
    {
        if (!$this->is_shop_manager()) {
            return array();
        }
        
        $shops = get_user_meta(get_current_user_id(), 'sln_manager_shop_id', false);
        
        if (empty($shops) || !is_array($shops)) {
            return array();
        }
        
        // Flatten the array (get_user_meta returns array of arrays)
        $shop_ids = array();
        foreach ($shops as $shop) {
            if (is_array($shop)) {
                $shop_ids = array_merge($shop_ids, $shop);
            } else {
                $shop_ids[] = $shop;
            }
        }
        
        // Convert to integers and remove duplicates
        $shop_ids = array_unique(array_map('intval', $shop_ids));
        
        // Remove zero/invalid IDs
        return array_filter($shop_ids);
    }

    /**
     * Apply shop manager filter to WP_REST_Request
     * If current user is a shop manager, this will override the 'shop' parameter
     * to only include shops they're assigned to
     * 
     * @param \WP_REST_Request $request
     * @return int Shop ID to filter by (0 if no filtering needed)
     */
    protected function apply_shop_manager_filter($request)
    {
        // Only apply if Multi-Shop addon is active
        if (!class_exists('\SalonMultishop\Addon')) {
            return 0;
        }
        
        // Administrators can see all shops
        if (current_user_can('manage_options')) {
            // Use the shop parameter from request if provided
            return (int) $request->get_param('shop');
        }
        
        // Check if user is a shop manager
        if (!$this->is_shop_manager()) {
            // Not a manager, use shop parameter as-is
            return (int) $request->get_param('shop');
        }
        
        // Get manager's assigned shops
        $assigned_shops = $this->get_shop_manager_assigned_shops();
        
        if (empty($assigned_shops)) {
            // Manager has no shops assigned - return special value -1
            // This will be used to show "no data" message
            return -1;
        }
        
        $requested_shop = (int) $request->get_param('shop');
        
        // If a specific shop was requested, verify manager has access to it
        if ($requested_shop > 0) {
            if (in_array($requested_shop, $assigned_shops)) {
                return $requested_shop;
            } else {
                // Manager doesn't have access to this shop - return -1
                return -1;
            }
        }
        
        // If manager has only one shop, automatically filter by it
        if (count($assigned_shops) === 1) {
            return $assigned_shops[0];
        }
        
        // Manager has multiple shops - return 0 to indicate "all assigned shops"
        // The calling function will need to handle this case with IN query
        return 0;
    }

    /**
     * Get all assigned shop IDs for filtering queries (for managers with multiple shops)
     * 
     * @return array|null Array of shop IDs for IN query, or null if not applicable
     */
    protected function get_shop_manager_filter_ids()
    {
        if (!$this->is_shop_manager() || !class_exists('\SalonMultishop\Addon')) {
            return null;
        }
        
        $assigned_shops = $this->get_shop_manager_assigned_shops();
        
        return empty($assigned_shops) ? null : $assigned_shops;
    }

}