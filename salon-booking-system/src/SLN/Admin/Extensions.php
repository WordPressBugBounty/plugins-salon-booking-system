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
        if(apply_filters('sln.show_branding', true) ){
            $pagename = add_submenu_page(
                'salon',
                __('Salon Extensions', 'salon-booking-system'),
                __('Extensions', 'salon-booking-system'),
                $this->getCapability(),
                static::PAGE,
                [$this, 'show']
            );
        add_action('load-' . $pagename, [$this, 'enqueueAssets']);
        }
    }

    public function enqueueAssets()
    {
        wp_enqueue_script('salon-slick', SLN_PLUGIN_URL . '/js/slick.min.js', [], SLN_Action_InitScripts::ASSETS_VERSION, true);
        wp_enqueue_script('salon-extensions', SLN_PLUGIN_URL . '/js/extensions.js', [], SLN_Action_InitScripts::ASSETS_VERSION, true);
        wp_enqueue_style('salon-admin-css', SLN_PLUGIN_URL . '/css/admin.css', [], SLN_VERSION, 'all');
        wp_enqueue_style('salon-slick', SLN_PLUGIN_URL . '/css/slick.css', [], SLN_Action_InitScripts::ASSETS_VERSION, 'all');
        wp_enqueue_style('salon-extensions', SLN_PLUGIN_URL . '/css/extensions.css', [], SLN_Action_InitScripts::ASSETS_VERSION, 'all');

        $s = SLN_Plugin::getInstance()->getSettings();
        $params = [
            'ajax_url' => admin_url('admin-ajax.php') . '?lang=' . $s->getLocale(),
        ];
        wp_localize_script('salon-extensions', 'salon', $params);
    }

    public function show()
    {
        echo $this->plugin->loadView('admin/extensions', ['plugin' => $this->plugin]);
    }
}