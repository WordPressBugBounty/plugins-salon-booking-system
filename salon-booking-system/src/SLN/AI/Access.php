<?php

/**
 * AI assistant access options (AI Setup → Access settings card).
 *
 * "Assistant users" are WordPress users with the sln_staff (Salon staff) role.
 */
class SLN_AI_Access
{
	const OPT_DISABLED               = 'ai_assistant_disabled';
	const OPT_DISABLED_ASSISTANTS       = 'ai_assistant_disabled_assistants';
	const OPT_RESTRICT_CUSTOMER_DATA = 'ai_assistant_restrict_customer_data';

	const SAVE_ACTION = 'sln_ai_setup_access';

	/** Tools that expose customer profiles. */
	private static $customerTools = array('find_customer');

	/**
	 * @return array option key => current bool value
	 */
	public static function options()
	{
		$settings = SLN_Plugin::getInstance()->getSettings();
		$out      = array();
		foreach (array(self::OPT_DISABLED, self::OPT_DISABLED_ASSISTANTS, self::OPT_RESTRICT_CUSTOMER_DATA) as $key) {
			$out[ $key ] = (bool) $settings->get($key);
		}

		return $out;
	}

	/**
	 * @return bool
	 */
	public static function isGloballyDisabled()
	{
		return (bool) SLN_Plugin::getInstance()->getSettings()->get(self::OPT_DISABLED);
	}

	/**
	 * @param WP_User|null $user
	 * @return bool
	 */
	public static function isAssistantUser($user = null)
	{
		$user = $user ? $user : wp_get_current_user();

		return $user && in_array(SLN_Plugin::USER_ROLE_STAFF, (array) $user->roles, true);
	}

	/**
	 * Whether the current user may manage the access options.
	 * Assistant users never can, even with settings rights, so they cannot lift their own restrictions.
	 *
	 * @return bool
	 */
	public static function canManage()
	{
		return ! self::isAssistantUser()
			&& current_user_can(apply_filters('salonviews/settings/capability', 'manage_salon_settings'));
	}

	/**
	 * Whether the chat (page, calendar widget, REST) is usable by the current user.
	 *
	 * @return bool
	 */
	public static function isEnabledForCurrentUser()
	{
		if (self::isGloballyDisabled()) {
			return false;
		}
		$settings = SLN_Plugin::getInstance()->getSettings();
		if (self::isAssistantUser() && $settings->get(self::OPT_DISABLED_ASSISTANTS)) {
			return false;
		}

		return true;
	}

	/**
	 * The AI Setup page stays reachable for managers even when the assistant is off,
	 * so the options can be switched back on.
	 *
	 * @return bool
	 */
	public static function canAccessPage()
	{
		return self::canManage() || self::isEnabledForCurrentUser();
	}

	/**
	 * @return bool
	 */
	public static function restrictsCustomerData()
	{
		return self::isAssistantUser()
			&& (bool) SLN_Plugin::getInstance()->getSettings()->get(self::OPT_RESTRICT_CUSTOMER_DATA);
	}

	/**
	 * Drop tools the current user is not allowed to call.
	 *
	 * @param array[] $catalog
	 * @return array[]
	 */
	public static function filterCatalog(array $catalog)
	{
		if (! self::restrictsCustomerData()) {
			return $catalog;
		}

		return array_values(
			array_filter(
				$catalog,
				function ($tool) {
					return ! in_array($tool['name'], self::$customerTools, true);
				}
			)
		);
	}

	/**
	 * admin-post handler for the access settings form.
	 */
	public static function handleSave()
	{
		if (! self::canManage()) {
			wp_die(esc_html__('You are not allowed to change these settings.', 'salon-booking-system'), 403);
		}
		check_admin_referer(self::SAVE_ACTION);

		$settings = SLN_Plugin::getInstance()->getSettings();
		foreach (array_keys(self::options()) as $key) {
			$settings->set($key, ! empty($_POST[ $key ]));
		}
		$settings->save();

		wp_safe_redirect(admin_url('admin.php?page=' . SLN_Admin_AISetup::PAGE . '&ai_access_saved=1'));
		exit;
	}
}
