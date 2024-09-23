<?php

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

        echo $this->plugin->loadView(
            'admin/reports',
            array(
                'plugin' => $this->plugin,
            )
        );
    }

    public function enqueueAssets()
    {
        SLN_Admin_Reports_GoogleGraph::enqueue_scripts();
        parent::enqueueAssets();

        $event = 'Page views of back-end plugin pages';
        $data  = array(
            'page'   => 'reports',
            'report' => !empty($_GET['view']) ? $_GET['view'] : 'revenues',
        );

        SLN_Action_InitScripts::mixpanelTrack($event, $data);
    }
}
