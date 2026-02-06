<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Admin_Reports extends SLN_Admin_AbstractPage
{

    const PAGE = 'salon-reports';
    const PRIORITY = 11;

    public function __construct(SLN_Plugin $plugin)
    {
        parent::__construct($plugin);
	add_action('in_admin_header', array($this, 'in_admin_header'));
    }

    public function admin_menu()
    {
        $this->classicAdminMenu(
            __('Salon Reports', 'salon-booking-system'),
            __('Reports', 'salon-booking-system')
        );
    }

    public function show()
    {
        // Check if user is a shop manager with no shops assigned
        $warning_message = '';
        $current_shop_name = '';
        $current_shop_id = 0;
        
        if (class_exists('\SalonMultishop\Addon')) {
            $user = wp_get_current_user();
            $is_shop_manager = in_array('shop_manager', (array) $user->roles) || 
                             in_array('sln_shop_manager', (array) $user->roles);
            
            // Only show warning for non-admin shop managers
            if ($is_shop_manager && !current_user_can('manage_options')) {
                // Get assigned shops (returns array directly from multi-shops addon)
                $shop_ids = get_user_meta(get_current_user_id(), 'sln_manager_shop_id');
                
                // Ensure it's an array and contains valid IDs
                if (!is_array($shop_ids)) {
                    $shop_ids = !empty($shop_ids) ? array($shop_ids) : array();
                }
                $shop_ids = array_filter(array_map('intval', $shop_ids));
                
                // Check if manager has no shops assigned
                if (empty($shop_ids)) {
                    $warning_message = __('You are currently not assigned to any shop', 'salon-booking-system');
                } else {
                    // Manager has assigned shops
                    // If only one shop, show its name. If multiple, show first or let them select
                    $current_shop_id = $shop_ids[0];
                    
                    try {
                        $shop_post = get_post($current_shop_id);
                        if ($shop_post && $shop_post->post_type === 'sln_shop') {
                            $current_shop_name = $shop_post->post_title;
                        }
                    } catch (\Exception $e) {
                        // Silently fail if shop cannot be retrieved
                    }
                }
            } else {
                // For non-managers (admins), get current shop from addon
                try {
                    $addon = \SalonMultishop\Addon::getInstance();
                    if ($addon && method_exists($addon, 'getCurrentShop')) {
                        $currentShop = $addon->getCurrentShop();
                        if ($currentShop && method_exists($currentShop, 'getId') && method_exists($currentShop, 'getName')) {
                            $current_shop_id = $currentShop->getId();
                            $current_shop_name = $currentShop->getName();
                        }
                    }
                } catch (\Exception $e) {
                    // Silently fail if shop cannot be retrieved
                }
            }
        }
        
        // Use new modern dashboard
        echo $this->plugin->loadView(
            'admin/reports-dashboard',
            array(
                'plugin' => $this->plugin,
                'warning_message' => $warning_message,
                'current_shop_name' => $current_shop_name,
                'current_shop_id' => $current_shop_id,
            )
        );
    }

    public function enqueueAssets()
    {
        // Enqueue Chart.js from CDN
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        // Enqueue new dashboard JavaScript
        wp_enqueue_script(
            'sln-reports-dashboard',
            SLN_PLUGIN_URL . '/js/admin/reports-dashboard.js',
            array('jquery', 'chart-js'),
            SLN_VERSION,
            true
        );
        
        // Set up translations for JavaScript
        wp_set_script_translations(
            'sln-reports-dashboard',
            'salon-booking-system',
            SLN_PLUGIN_DIR . '/languages'
        );
        
        // Enqueue debug script (optional - comment out for production)
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     wp_enqueue_script(
        //         'sln-reports-dashboard-debug',
        //         SLN_PLUGIN_URL . '/js/admin/reports-dashboard-debug.js',
        //         array('jquery', 'sln-reports-dashboard'),
        //         SLN_VERSION,
        //         true
        //     );
        // }

        // Get manager's assigned shop if applicable
        $manager_shop_id = 0;
        $manager_shop_ids = array();
        if (class_exists('\SalonMultishop\Addon')) {
            $user = wp_get_current_user();
            $is_shop_manager = in_array('shop_manager', (array) $user->roles) || 
                             in_array('sln_shop_manager', (array) $user->roles);
            
            if ($is_shop_manager && !current_user_can('manage_options')) {
                // Get assigned shops (returns array directly from multi-shops addon)
                $shop_ids = get_user_meta(get_current_user_id(), 'sln_manager_shop_id');
                
                // Ensure it's an array and contains valid IDs
                if (!is_array($shop_ids)) {
                    $shop_ids = !empty($shop_ids) ? array($shop_ids) : array();
                }
                $shop_ids = array_filter(array_map('intval', $shop_ids));
                
                if (!empty($shop_ids)) {
                    $manager_shop_id = $shop_ids[0]; // Default/first shop
                    $manager_shop_ids = $shop_ids;   // All assigned shops
                }
            }
        }
        
        // Pass configuration to JavaScript
        wp_localize_script('sln-reports-dashboard', 'salonDashboard', array(
            'nonce'           => wp_create_nonce('wp_rest'),
            'apiToken'        => '', // Will be populated if using API token auth
            'currency'        => $this->plugin->getSettings()->getCurrency(),
            'currencySymbol'  => SLN_Currency::getSymbolAsIs($this->plugin->getSettings()->getCurrency()),
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'restUrl'         => rest_url('salon/api/v1/'),
            'isDebug'         => defined('WP_DEBUG') && WP_DEBUG,
            'isPro'           => defined('SLN_VERSION_PAY') || defined('SLN_VERSION_CODECANYON'),
            'managerShopId'   => $manager_shop_id,   // Default shop ID for manager
            'managerShopIds'  => !empty($manager_shop_ids) ? $manager_shop_ids : array(), // All assigned shop IDs
        ));

        parent::enqueueAssets();
    }
}
