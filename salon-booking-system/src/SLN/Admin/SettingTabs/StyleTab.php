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
		'disable_forecast_screen',
	);

	protected function postProcess() {
		$this->settings->save();
		if ($this->settings->get('style_colors_enabled')) {
			$this->saveCustomCss();
		}
	}

	protected function saveCustomCss() {
		// Single source of truth for generating uploads/sln-colors.css.
		// SLN_Helper_CustomColorsCss also rebuilds it automatically when the
		// shipped template changes, so a manual Style re-save is no longer the
		// only way to pick up new token rules after a plugin update.
		SLN_Helper_CustomColorsCss::regenerate($this->settings);
	}
}
?>