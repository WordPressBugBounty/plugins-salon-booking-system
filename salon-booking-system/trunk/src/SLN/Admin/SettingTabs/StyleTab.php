<?php
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
class SLN_Admin_SettingTabs_StyleTab extends SLN_Admin_SettingTabs_AbstractTab {
	protected $fields = array(
		'style_shortcode',
		'style_colors_enabled',
		'style_colors',
		'ajax_enabled',
		'no_bootstrap',
		'no_bootstrap_js',
		'replace_booking_modal_with_popup',
		'disable_google_fonts',
		'hide_service_duration',
	);

	protected function postProcess() {
		$this->settings->save();
		if ($this->settings->get('style_colors_enabled')) {
			$this->saveCustomCss();
		}
	}

	protected function saveCustomCss() {
		$css = file_get_contents(SLN_PLUGIN_DIR . '/css/sln-colors--custom.css');
		$colors = $this->settings->get('style_colors');

		if ($colors) {
			foreach ($colors as $k => $v) {
				$css = str_replace("{color-$k}", $v, $css);
			}
		}
		$dir = wp_upload_dir();
		$dir = $dir['basedir'];
		file_put_contents($dir . '/sln-colors.css', $css);
	}
}
?>