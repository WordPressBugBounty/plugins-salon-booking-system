<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Admin_Settings {

	const PAGE = 'salon-settings';

	protected $plugin;
	private $tabs = null;

	public function __construct(SLN_Plugin $plugin) {
		$this->plugin = $plugin;
		$this->tabs = apply_filters('sln.settings.tabs', array(
			'general' => __('General', 'salon-booking-system'),
			'booking' => __('Booking Rules', 'salon-booking-system'),
			'checkout' => __('Checkout', 'salon-booking-system'),
			'payments' => __('Payments', 'salon-booking-system'),
			'style' => __('Style', 'salon-booking-system'),
			'gcalendar' => __('Google Calendar', 'salon-booking-system'),
			'documentation' => __('Support', 'salon-booking-system'),
		), $plugin);
		add_action('admin_menu', array($this, 'admin_menu'), 12);
		if (defined("SLN_VERSION_PAY") && SLN_VERSION_PAY) {
			$this->addTabHooks();
		}

		add_filter('sln.settings.general.fields', array($this, 'initSmsServices'));
	}

	public function admin_menu() {
		$pagename = add_submenu_page(
			'salon',
			__('Salon Settings', 'salon-booking-system'),
			__('Settings', 'salon-booking-system'),
			apply_filters('salonviews/settings/capability', 'manage_salon_settings'),
			self::PAGE,
			array($this, 'show')
		);
		add_action('load-' . $pagename, array($this, 'enqueueAssets'));
	}

	public function show() {
		$current = $this->getCurrentTab();
		if (!in_array($current, array_keys($this->tabs))) {
			throw new Exception('Tab with slug ' . $current . ' not registered');
		}
		if(!apply_filters('sln.show_branding', true)){
		    unset($this->tabs['documentation']);
        }
		$class_name = 'SLN_Admin_SettingTabs_' . ucfirst($current) . 'Tab';
		if (!class_exists($class_name)) {
			throw new Exception('Class ' . $class_name . ' is not existent');
		}
		if (!is_subclass_of($class_name, 'SLN_Admin_SettingTabs_AbstractTab')) {
			throw new Exception('Class ' . $class_name . ' not implement SLN_Admin_SettingTabs_AbstractTab');
		}
		$tab = new $class_name($current, $this->tabs[$current], $this->plugin);
		?>
        <div id="sln-salon--admin" class="wrap sln-bootstrap sln-salon--settings <?php if (!defined("SLN_VERSION_PAY") || !SLN_VERSION_PAY) {echo " sln-salon--settings--free";}?>">
            <div class="row">
                <div class="col-xs-12">
                	<h1 class="sln-salon--admin__breadcrumbs">
                		<span><a href="#nogo"><?php esc_html_e('Salon Booking', 'salon-booking-system');?></a></span>
                		<span><a href="#nogo"><?php esc_html_e('Settings', 'salon-booking-system');?></a></span>
                		<span><?php echo $this->tabs[$current]; ?></span>
                	</h1>
                </div>
            </div>

            <?php settings_errors();?>
            <?php $this->showTabsBar();?>
            <form method="post" action="<?php admin_url('admin.php?page=' . self::PAGE);?>" enctype="multipart/form-data">
                <?php
					$tab->show();
					echo self::PAGE. $current;
					wp_nonce_field(self::PAGE . $current, self::PAGE. $current);
				?>
            </form>

        </div><!-- wrap -->
        <?php
}

	private function addTabHooks() {
		add_filter('sln.settings.payments.fields', array($this, 'initGateways'));
		add_filter('sln.settings.general.fields', array($this, 'initGeneralFields'));
		add_filter('sln.settings.checkout.fields', array($this, 'initCheckoutFields'));
		add_filter('sln.settings.booking.fields', array($this, 'initBookingFields'));
	}

	private function showTabsBar() {
		echo '<h2 id="sln-nav-tab-wrapper" class="sln-nav-tab-wrapper nav-tab-wrapper">';
		echo '<a href="#sln-nav-tab-wrapper" class="sln-inpage_navbar__currenttab sln-inpage_navbar__icon--close sln-nav-tab--close">
			<span class="sr-only">go to main tabs menu</span>
		</a>';
		$page = self::PAGE;
		$current = $this->getCurrentTab();
		foreach ($this->tabs as $tab => $name) {
			$class = ($tab == $current) ? ' nav-tab-active' : '';
			echo "<a class='nav-tab$class nav-tab-$tab' href='?page=$page&tab=$tab'><span>$name</span></a>";
		}

		echo $this->plugin->loadView('admin/help');

		echo '</h2>';
	}

	function getCurrentTab() {
		return isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
	}

	public function enqueueAssets() {
		SLN_Action_InitScripts::enqueueTwitterBootstrap(true);
		SLN_Action_InitScripts::enqueueSelect2();
		SLN_Action_InitScripts::enqueueAdmin();
		SLN_Action_InitScripts::enqueueSettingsNavigation();
		wp_enqueue_script(
			'salon-customSettings',
			SLN_PLUGIN_URL . '/js/admin/customSettings.js',
			array('jquery'),
			SLN_Action_InitScripts::ASSETS_VERSION,
			true
		);

        wp_enqueue_script(
            'salon-payDepositAdvancedRules',
            SLN_PLUGIN_URL . '/js/admin/payDepositAdvancedRules.js',
            array('jquery'),
            SLN_Action_InitScripts::ASSETS_VERSION,
            true
        );

		if (isset($_GET['tab']) && $_GET['tab'] == 'style') {
			SLN_Action_InitScripts::enqueueColorPicker();
			wp_enqueue_script(
				'salon-customColors',
				SLN_PLUGIN_URL . '/js/admin/customColors.js',
				array('jquery'),
				SLN_Action_InitScripts::ASSETS_VERSION,
				true
			);
		}

                $event = 'Page views of back-end plugin pages';
                $data  = array(
                    'page' => 'settings',
                    'tab'  => !empty($_GET['tab']) ? $_GET['tab'] : 'general',
                );

                SLN_Action_InitScripts::mixpanelTrack($event, $data);
	}

	public function initGateways($fields) {
		foreach (SLN_Enum_PaymentMethodProvider::toArray() as $k => $v) {
			$fields = array_merge(
				$fields,
				SLN_Enum_PaymentMethodProvider::getService($k, $this->plugin)->getFields()
			);
		}
		return $fields;
	}

	public function initSmsServices($fields) {
		foreach (SLN_Enum_SmsProvider::toArray() as $k => $v) {
			$fields = array_merge(
				$fields,
				SLN_Enum_SmsProvider::getService($k, $this->plugin)->getFields()
			);
		}
		return $fields;
	}

	public function initGeneralFields($fields) {
            $fields[] = 'display_slots_customer_timezone';
            return $fields;
	}

	public function initCheckoutFields($fields) {
            $fields[] = 'enable_customer_fidelity_score';
            return $fields;
	}

	public function initBookingFields($fields) {
            $fields[] = 'enable_resources';
            return $fields;
	}

}
