<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Admin_Extensions extends SLN_Admin_AbstractPage
{
    const PAGE = 'salon-extensions';
    const PRIORITY = 14;

    public function __construct(SLN_Plugin $plugin)
    {
        parent::__construct($plugin);
        add_action('in_admin_header', [$this, 'in_admin_header']);
    }

    public function admin_menu()
    {
        $crown_icon = '<img src="' . esc_url( SLN_PLUGIN_URL . '/img/crown.svg' ) . '" '
            . 'style="width:14px;height:14px;vertical-align:middle;margin-right:4px;'
            . 'position:relative;top:-1px;opacity:0.85;" alt="">';

        $pagename = add_submenu_page(
            'salon',
            __('Salon Extensions', 'salon-booking-system'),
            $crown_icon . __('Extensions', 'salon-booking-system'),
            $this->getCapability(),
            static::PAGE,
            [$this, 'show']
        );
        add_action('load-' . $pagename, [$this, 'maybeClearCache']);
        add_action('load-' . $pagename, [$this, 'enqueueAssets']);
    }

    /**
     * Handle the "clear extensions cache" request.
     * Runs before output so it can redirect cleanly.
     */
    public function maybeClearCache()
    {
        if ( empty( $_GET['sln_clear_ext_cache'] ) ) {
            return;
        }
        check_admin_referer( 'sln_clear_ext_cache' );
        if ( ! current_user_can( $this->getCapability() ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'salon-booking-system' ) );
        }

        // Delete the products transient and stored option so the next page load
        // fetches a fresh list from salonbookingsystem.com.
        $slug = defined( 'SLN_ITEM_SLUG' ) ? SLN_ITEM_SLUG : 'salon-booking-wordpress-plugin';
        delete_transient( $slug . '_products_cache' );
        delete_option( $slug . '_products_data' );

        wp_safe_redirect( admin_url( 'admin.php?page=' . static::PAGE . '&sln_cache_cleared=1' ) );
        exit;
    }

    public function enqueueAssets()
    {
        wp_enqueue_script('salon-extensions', SLN_PLUGIN_URL . '/js/extensions.js', ['jquery'], SLN_Action_InitScripts::ASSETS_VERSION, true);
        wp_enqueue_style('salon-admin-css', SLN_PLUGIN_URL . '/css/admin.css', [], SLN_VERSION, 'all');
        wp_enqueue_style('salon-extensions', SLN_PLUGIN_URL . '/css/extensions.css', [], SLN_Action_InitScripts::ASSETS_VERSION, 'all');

        $s    = SLN_Plugin::getInstance()->getSettings();
        $slug = defined( 'SLN_ITEM_SLUG' ) ? SLN_ITEM_SLUG : 'salon-booking-wordpress-plugin';
        $params = [
            'ajax_url'        => admin_url('admin-ajax.php') . '?lang=' . $s->getLocale(),
            'clear_cache_url' => wp_nonce_url(
                admin_url( 'admin.php?page=' . static::PAGE . '&sln_clear_ext_cache=1' ),
                'sln_clear_ext_cache'
            ),
            'cache_cleared'   => ! empty( $_GET['sln_cache_cleared'] ) ? '1' : '',
        ];
        wp_localize_script('salon-extensions', 'salon', $params);

        // Suppress all admin notices on this page — the extensions page has its own
        // upgrade banner and notices would break the full-bleed layout.
        add_action('admin_notices',     function() { ob_start(); },       -99);
        add_action('admin_notices',     function() { ob_end_clean(); },  999);
        add_action('all_admin_notices', function() { ob_start(); },       -99);
        add_action('all_admin_notices', function() { ob_end_clean(); },  999);
    }

    public function show()
    {
        echo $this->plugin->loadView('admin/extensions', ['plugin' => $this->plugin]);
    }
}