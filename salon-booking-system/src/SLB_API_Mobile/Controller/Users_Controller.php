<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_API_Mobile\Controller;

use WP_Error;
use WP_REST_Server;
use WP_Session_Tokens;
use SLN_Plugin;

class Users_Controller extends REST_Controller
{
    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'users';

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'   => WP_REST_Server::EDITABLE,
                    'callback'  => array($this, 'update_item'),
                    'permission_callback' => '__return_true',
                    'args'      => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/current',
            array(
                array(
                    'methods'   => WP_REST_Server::READABLE,
                    'callback'  => array($this, 'get_current_user'),
                    'permission_callback' => array($this, 'check_permissions'),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/logout',
            array(
                array(
                    'methods'   => WP_REST_Server::CREATABLE,
                    'callback'  => array($this, 'logout_user'),
                    'permission_callback' => array($this, 'check_permissions'),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/active',
            array(
                array(
                    'methods'   => WP_REST_Server::READABLE,
                    'callback'  => array($this, 'get_active_users'),
                    'permission_callback' => array($this, 'check_permissions'),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/logout',
            array(
                array(
                    'methods'   => WP_REST_Server::CREATABLE,
                    'callback'  => array($this, 'logout_specific_user'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                    'args' => array(
                        'id' => array(
                            'required' => true,
                            'type' => 'integer',
                        ),
                    ),
                ),
            )
        );
    }

    public function get_current_user($request)
    {
        $user = wp_get_current_user();

        if (!$user || $user->ID === 0) {
            return new WP_Error('no_user', __('No user is currently logged in.', 'salon-booking-system'), array('status' => 401));
        }

        $roles_map = array(
            SLN_Plugin::USER_ROLE_STAFF => __('Salon staff', SLN_Plugin::TEXT_DOMAIN),
            SLN_Plugin::USER_ROLE_CUSTOMER => __('Salon customer', SLN_Plugin::TEXT_DOMAIN),
            SLN_Plugin::USER_ROLE_WORKER => __('Salon worker', SLN_Plugin::TEXT_DOMAIN),
        );

        $user_role = $user->roles[0] ?? 'guest';
        $readable_role = $roles_map[$user_role] ?? $user_role;

        return rest_ensure_response(array(
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'role'  => $readable_role,
        ));
    }

    public function logout_user($request)
    {
        wp_logout();

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('User logged out successfully.', 'salon-booking-system'),
        ));
    }

    public function get_active_users($request)
    {
        // Get current user info
        $current_user = wp_get_current_user();
        if (!$current_user || $current_user->ID === 0) {
            return new WP_Error('no_user', __('No user is currently logged in.', 'salon-booking-system'), array('status' => 401));
        }

        // Role mapping for display
        $roles_map = array(
            'administrator' => __('Administrator', 'salon-booking-system'),
            SLN_Plugin::USER_ROLE_WORKER => __('Salon Manager', 'salon-booking-system'),
            SLN_Plugin::USER_ROLE_STAFF => __('Salon Staff Member', 'salon-booking-system'),
        );

        // Get all relevant users (administrators, managers, and staff)
        $users = get_users(array(
            'role__in' => array('administrator', SLN_Plugin::USER_ROLE_WORKER, SLN_Plugin::USER_ROLE_STAFF),
        ));

        $active_users = array();
        $current_time = time();
        $activity_threshold = 15 * MINUTE_IN_SECONDS; // Consider active if session within last 15 minutes

        foreach ($users as $user) {
            // Get user sessions
            $sessions = get_user_meta($user->ID, 'session_tokens', true);
            
            if (empty($sessions) || !is_array($sessions)) {
                continue; // Skip users with no active sessions
            }

            // Check if any session is recent
            $has_active_session = false;
            foreach ($sessions as $token => $session) {
                if (isset($session['expiration']) && $session['expiration'] > $current_time) {
                    // Session is not expired
                    // Check if session has recent activity (login time)
                    if (isset($session['login']) && ($current_time - $session['login']) <= $activity_threshold) {
                        $has_active_session = true;
                        break;
                    }
                    // If no 'login' timestamp, consider any non-expired session as active
                    if (!isset($session['login'])) {
                        $has_active_session = true;
                        break;
                    }
                }
            }

            if ($has_active_session) {
                $user_role = !empty($user->roles) ? $user->roles[0] : '';
                $readable_role = isset($roles_map[$user_role]) ? $roles_map[$user_role] : ucfirst($user_role);

                $active_users[] = array(
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'role' => $readable_role,
                );
            }
        }

        return rest_ensure_response(array(
            'users' => $active_users,
        ));
    }

    public function logout_specific_user($request)
    {
        $user_id = (int) $request['id'];
        $current_user = wp_get_current_user();

        // Prevent users from logging themselves out via this endpoint
        if ($user_id === $current_user->ID) {
            return new WP_Error('cannot_logout_self', __('Cannot logout yourself using this endpoint.', 'salon-booking-system'), array('status' => 400));
        }

        // Verify the target user exists
        $target_user = get_user_by('id', $user_id);
        if (!$target_user) {
            return new WP_Error('user_not_found', __('User not found.', 'salon-booking-system'), array('status' => 404));
        }

        // Destroy all sessions for the target user
        $sessions = get_user_meta($user_id, 'session_tokens', true);
        if (!empty($sessions)) {
            WP_Session_Tokens::get_instance($user_id)->destroy_all();
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => sprintf(__('User %s has been logged out.', 'salon-booking-system'), $target_user->display_name),
        ));
    }

    public function check_permissions()
    {
        return is_user_logged_in();
    }

    public function check_admin_permissions()
    {
        $current_user = wp_get_current_user();
        
        // Allow administrators, salon managers, and salon staff
        return $current_user && (
            in_array('administrator', $current_user->roles) ||
            in_array(SLN_Plugin::USER_ROLE_WORKER, $current_user->roles) ||
            in_array(SLN_Plugin::USER_ROLE_STAFF, $current_user->roles)
        );
    }

    public function update_item( $request )
    {
	$user_id = get_current_user_id();

        try {
            $this->save_item_user($request, $user_id);
        } catch (\Exception $ex) {
            return new WP_Error( 'salon_rest_cannot_view', __( 'Sorry, error on update ('.$ex->getMessage().').', 'salon-booking-system' ), array( 'status' => 404 ) );
        }

	return $this->success_response(array(
	    'id'               => $user_id,
	    'sended_player_id' => $request->get_param('onesignal_player_id'),
	    'user_player_ids'  => get_user_meta($user_id, '_sln_onesignal_player_id', true),
	));
    }

    protected function save_item_user($request, $user_id = 0)
    {
	$meta = array();

	$player_id = $request->get_param('onesignal_player_id');

	if ( $player_id !== null ) {

	    $meta_value = get_user_meta($user_id, '_sln_onesignal_player_id', true);
	    $player_ids = is_array($meta_value) ? $meta_value : ($meta_value ? array($meta_value) : array());

	    if ( ! in_array( $player_id, $player_ids ) ) {
		$player_ids[] = $player_id;
	    }

	    $meta['_sln_onesignal_player_id'] = array_filter($player_ids);
	}

	foreach ($meta as $key => $value) {
	    update_user_meta($user_id, $key, $value);
	}

	return $user_id;
    }

    public function get_item_schema()
    {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'user',
            'type'       => 'object',
            'properties' => array(
                'onesignal_player_id' => array(
                    'description' => __( 'The notification push id the resource.', 'salon-booking-system' ),
                    'type'        => 'string',
                    'context'     => array( 'edit' ),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => null,
                    ),
                ),
            ),
        );

        return $schema;
    }

}