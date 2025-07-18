<?php

class SLN_UserRole_SalonStaff
{
    private $plugin;

    private $role;
    private $displayName;
    private $capabilities = array(
        'manage_salon' => true,
        'manage_salon_settings' => false,
        'edit_posts' => true,
        'export_reservations_csv_sln_calendar' => true,
        'delete_permanently_sln_booking'       => false,
        'create_sln_bookings' => true,
        'create_sln_attendants' => true,
    );

    public function __construct(SLN_Plugin $plugin, $role, $displayName)
    {
        $adminRole = get_role('administrator');

	if ($adminRole) {
	    $adminRole->add_cap('manage_salon');
	    $adminRole->add_cap('manage_salon_settings');
	    $adminRole->add_cap('export_reservations_csv_sln_calendar');
	    $adminRole->add_cap('delete_permanently_sln_booking');
        $adminRole->add_cap('create_sln_bookings');
        $adminRole->add_cap('create_sln_attendants');
        $adminRole->add_cap('create_sln_resources');
	}

        foreach (array(
                     SLN_Plugin::POST_TYPE_ATTENDANT,
                     SLN_Plugin::POST_TYPE_SERVICE,
                     SLN_Plugin::POST_TYPE_BOOKING,
                     SLB_Discount_Plugin::POST_TYPE_DISCOUNT,
                     SLN_Plugin::POST_TYPE_RESOURCE,
                 ) as $k) {
            $postTypeObj = get_post_type_object($k);
            if( !is_object($postTypeObj) ){
                continue;
            }
            foreach ($postTypeObj->cap as $v) {
                if (!isset($role->capabilities[$v]) && $adminRole) {
                    $adminRole->add_cap($v);
                }
                $this->capabilities[$v] = true;
            }
        }
        $this->plugin = $plugin;
        $this->role = $role;
        $this->displayName = $displayName;

        $roles = wp_roles();
        if ($roles->get_role($this->role)) {
            $roles->remove_role($this->role);
        }
        $roles->add_role($this->role, $this->displayName, $this->capabilities);
    }

    /**
     * @return SLN_Plugin
     */
    protected function getPlugin()
    {
        return $this->plugin;
    }

    public static function addCapabilitiesForRole($role) {
        self::changeCapabilitiesForRole($role, true);
    }

    public static function removeCapabilitiesFoRole($role) {
        self::changeCapabilitiesForRole($role, false);
    }

    private static function changeCapabilitiesForRole($role, $canManage) {
        $roleObj = get_role($role);
        if ($canManage) {
            $roleObj->add_cap('manage_salon');
	    $roleObj->add_cap('manage_salon_settings');
	    $roleObj->add_cap('export_reservations_csv_sln_calendar');
	    $roleObj->add_cap('delete_permanently_sln_booking');
        } else {
            $roleObj->remove_cap('manage_salon');
            $roleObj->remove_cap('manage_salon_settings');
	    $roleObj->remove_cap('export_reservations_csv_sln_calendar');
	    $roleObj->remove_cap('delete_permanently_sln_booking');
        }

        foreach (array(
            SLN_Plugin::POST_TYPE_ATTENDANT,
            SLN_Plugin::POST_TYPE_SERVICE,
            SLN_Plugin::POST_TYPE_BOOKING,
            SLN_Plugin::POST_TYPE_RESOURCE,
        ) as $k) {
            $postTypeObj = get_post_type_object($k);
            if (!is_object($postTypeObj)) {
                continue;
            }
            foreach ($postTypeObj->cap as $v) {
                if ($canManage) {
                    $roleObj->add_cap($v);
                }
                else {
                    if ($v !== 'read') {
                        $roleObj->remove_cap($v);
                    }
                }
            }
        }
    }

    public static function addCapabilities(array $capabilities) {

	$role = get_role(SLN_Plugin::USER_ROLE_STAFF);

	foreach ($capabilities as $cap) {
		$role->add_cap($cap, true);
	}
    }

    public static function removeCapabilities(array $capabilities) {

	$role = get_role(SLN_Plugin::USER_ROLE_STAFF);

	foreach ($capabilities as $cap) {
	    $role->remove_cap($cap);
	}
    }

    public static function changeCapabilitiesByPostType($post_types, $canManage) {
        $roleObj = get_role(SLN_Plugin::USER_ROLE_STAFF);
        foreach ($post_types as $k) {
            $postTypeObj = get_post_type_object($k);
            if (!is_object($postTypeObj)) {
                continue;
            }
            foreach ($postTypeObj->cap as $v) {
                if ($canManage) {
                    $roleObj->add_cap($v);
                }
                else {
                    if ($v !== 'read') {
                        $roleObj->remove_cap($v);
                    }
                }
            }
        }
    }
}