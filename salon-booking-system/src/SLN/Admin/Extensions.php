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
        add_action('load-' . $pagename, [$this, 'enqueueAssets']);
    }

    public function enqueueAssets()
    {
        wp_enqueue_script('salon-extensions', SLN_PLUGIN_URL . '/js/extensions.js', ['jquery'], SLN_Action_InitScripts::ASSETS_VERSION, true);
        wp_enqueue_style('salon-admin-css', SLN_PLUGIN_URL . '/css/admin.css', [], SLN_VERSION, 'all');
        wp_enqueue_style('salon-extensions', SLN_PLUGIN_URL . '/css/extensions.css', [], SLN_Action_InitScripts::ASSETS_VERSION, 'all');

        $s = SLN_Plugin::getInstance()->getSettings();
        $params = [
            'ajax_url' => admin_url('admin-ajax.php') . '?lang=' . $s->getLocale(),
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