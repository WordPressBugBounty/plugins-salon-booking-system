<?php

/**
 * Extended non-secret settings tools (notifications, SMS toggles, payments behaviour, pages, GCal).
 */

class SLN_AI_Tools_SetNotificationSchedule extends SLN_AI_Tools_SimpleSettings
{
	public function getName()
	{
		return 'set_notification_schedule';
	}

	public function getTier()
	{
		return 'confirm';
	}

	protected function successMessage()
	{
		return __('Notification schedule updated.', 'salon-booking-system');
	}

	protected function allowedKeys()
	{
		return array(
			'email_remind'          => 'flag',
			'email_remind_interval' => 'text',
			'follow_up_email'       => 'flag',
			'follow_up_sms'         => 'flag',
			'follow_up_interval'    => 'text',
			'feedback_email'        => 'flag',
			'feedback_sms'          => 'flag',
			'custom_feedback_url'   => 'url',
		);
	}
}

class SLN_AI_Tools_SetSmsBehaviour extends SLN_AI_Tools_SimpleSettings
{
	public function getName()
	{
		return 'set_sms_behaviour';
	}

	public function getTier()
	{
		return 'confirm';
	}

	protected function successMessage()
	{
		return __('SMS behaviour updated.', 'salon-booking-system');
	}

	protected function allowedKeys()
	{
		return array(
			'sms_enabled'              => 'flag',
			'sms_new'                  => 'flag',
			'sms_modified'             => 'flag',
			'sms_canceled'             => 'flag',
			'sms_new_attendant'        => 'flag',
			'sms_modified_attendant'   => 'flag',
			'sms_canceled_attendant'   => 'flag',
			'sms_remind'               => 'flag',
			'sms_remind_interval'      => 'text',
			'whatsapp_enabled'         => 'flag',
		);
	}

	public function preview(array $arguments)
	{
		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		// Enabling SMS without credentials → still allow toggle, but note guidance.
		$notes = '';
		if (! empty($mapped['sms_enabled']) && $mapped['sms_enabled'] === '1') {
			$account = (string) $this->settings()->get('sms_account');
			if ($account === '') {
				$notes = "\n\n" . __(
					'Note: SMS provider credentials are not set. Use explain_setting topic=sms to open General → SMS and paste account/password there — AI never writes those secrets.',
					'salon-booking-system'
				);
			}
		}
		$base = parent::preview($arguments);
		if (is_wp_error($base) || empty($base['ok'])) {
			return $base;
		}
		$base['summary'] = $base['summary'] . $notes;

		return $base;
	}
}

class SLN_AI_Tools_SetPaymentBehaviour extends SLN_AI_Tools_SimpleSettings
{
	public function getName()
	{
		return 'set_payment_behaviour';
	}

	public function getTier()
	{
		return 'confirm';
	}

	protected function successMessage()
	{
		return __('Payment behaviour updated.', 'salon-booking-system');
	}

	protected function allowedKeys()
	{
		return array(
			'pay_enabled'               => 'flag',
			'pay_method'                => 'text',
			'pay_deposit'               => 'text',
			'pay_deposit_fixed_amount'  => 'text',
			'pay_tip_request'           => 'flag',
			'pay_minimum_order_amount'  => 'text',
			'pay_offset_enabled'        => 'flag',
			'pay_offset'                => 'int',
			'pay_cash'                  => 'flag',
			'pay_transaction_fee_amount'=> 'text',
			'pay_transaction_fee_type'  => 'text',
		);
	}

	public function preview(array $arguments)
	{
		$blocked = SLN_AI_Edition::blockIfFree('payments');
		if ($blocked) {
			return $blocked;
		}
		$check = $this->validatePayMethod($arguments);
		if (is_wp_error($check)) {
			return $check;
		}

		return parent::preview($arguments);
	}

	public function apply(array $arguments)
	{
		if (! SLN_AI_Edition::isPro()) {
			return new WP_Error('sln_ai_pro', __('Online payments require the PRO edition.', 'salon-booking-system'));
		}
		$check = $this->validatePayMethod($arguments);
		if (is_wp_error($check)) {
			return $check;
		}

		return parent::apply($arguments);
	}

	/**
	 * @param array $arguments
	 * @return true|WP_Error
	 */
	private function validatePayMethod(array $arguments)
	{
		$values = ! empty($arguments['values']) && is_array($arguments['values'])
			? $arguments['values']
			: $arguments;
		if (! isset($values['pay_method'])) {
			return true;
		}
		$method = sanitize_text_field((string) $values['pay_method']);
		$allowed = array();
		try {
			$allowed = array_keys(SLN_Enum_PaymentMethodProvider::toArray());
		} catch (Exception $e) {
			$allowed = array();
		}
		if ($allowed && ! in_array($method, $allowed, true)) {
			return new WP_Error(
				'sln_ai_pay_method',
				sprintf(
					/* translators: %s: comma-separated method keys */
					__('Invalid pay_method. Installed methods: %s. Gateway API keys stay in Settings → Payments (explain_setting topic=payments).', 'salon-booking-system'),
					implode(', ', $allowed)
				)
			);
		}
		if (isset($values['pay_transaction_fee_type'])) {
			$type = sanitize_text_field((string) $values['pay_transaction_fee_type']);
			if (! in_array($type, array('fixed', 'percent', 'percentage', ''), true)) {
				return new WP_Error(
					'sln_ai_pay_fee_type',
					__('pay_transaction_fee_type must be fixed or percent.', 'salon-booking-system')
				);
			}
		}

		return true;
	}
}

class SLN_AI_Tools_SetProBookingFlags extends SLN_AI_Tools_SimpleSettings
{
	public function getName()
	{
		return 'set_pro_booking_flags';
	}

	public function getTier()
	{
		return 'confirm';
	}

	protected function successMessage()
	{
		return __('PRO booking flags updated.', 'salon-booking-system');
	}

	protected function allowedKeys()
	{
		return array(
			'enable_customer_fidelity_score'  => 'flag',
			'display_slots_customer_timezone' => 'flag',
			'enabled_one_click_booking'       => 'flag',
			'one_click_min_bookings'          => 'int',
		);
	}

	public function preview(array $arguments)
	{
		$gate = $this->gateProKeys($arguments);
		if ($gate) {
			return $gate;
		}

		return parent::preview($arguments);
	}

	public function apply(array $arguments)
	{
		$gate = $this->gateProKeys($arguments);
		if ($gate) {
			return new WP_Error('sln_ai_pro', __('This PRO booking flag requires the PRO edition.', 'salon-booking-system'));
		}

		return parent::apply($arguments);
	}

	/**
	 * @param array $arguments
	 * @return array|null
	 */
	private function gateProKeys(array $arguments)
	{
		$values = ! empty($arguments['values']) && is_array($arguments['values'])
			? $arguments['values']
			: $arguments;
		$map = array(
			'enable_customer_fidelity_score'  => 'fidelity_score',
			'display_slots_customer_timezone' => 'customer_timezone',
			'enabled_one_click_booking'       => 'fidelity_score',
			'one_click_min_bookings'          => 'fidelity_score',
		);
		$touches = false;
		foreach (array_keys($map) as $key) {
			if (array_key_exists($key, $values)) {
				$touches = true;
				break;
			}
		}
		if (! $touches) {
			return null;
		}
		if (! SLN_AI_Edition::isPro()) {
			foreach ($map as $key => $feature) {
				if (array_key_exists($key, $values)) {
					return SLN_AI_Edition::blockIfFree($feature);
				}
			}
		}

		return null;
	}
}

class SLN_AI_Tools_SetGcalendarBehaviour extends SLN_AI_Tools_SimpleSettings
{
	public function getName()
	{
		return 'set_gcalendar_behaviour';
	}

	public function getTier()
	{
		return 'confirm';
	}

	protected function needsCacheRefresh()
	{
		return true;
	}

	protected function successMessage()
	{
		return __('Google Calendar behaviour updated.', 'salon-booking-system');
	}

	protected function allowedKeys()
	{
		return array(
			'google_calendar_enabled'                  => 'flag',
			'google_calendar_publish_pending_payment'  => 'flag',
			'google_calendar_lock_slots'               => 'flag',
		);
	}

	public function preview(array $arguments)
	{
		if (! $this->isOauthConnected()) {
			$url = admin_url('admin.php?page=salon-settings&tab=gcalendar');

			return array(
				'ok'       => true,
				'guidance' => true,
				'summary'  => implode(
					"\n",
					array(
						__('Google Calendar OAuth is not connected yet.', 'salon-booking-system'),
						'',
						__('Open Settings → Google Calendar, paste the OAuth client ID/secret, and complete authorization. AI never writes those secrets.', 'salon-booking-system'),
						'',
						sprintf(
							/* translators: %s: admin URL */
							__('Open Google Calendar settings: %s', 'salon-booking-system'),
							$url
						),
					)
				),
				'url'      => $url,
				'tool'     => 'explain_setting',
				'tier'     => 'guidance',
			);
		}

		$values = ! empty($arguments['values']) && is_array($arguments['values'])
			? $arguments['values']
			: $arguments;
		if (array_key_exists('google_calendar_lock_slots', $values)) {
			$blocked = SLN_AI_Edition::blockIfFree('gcal_lock_slots');
			if ($blocked) {
				return $blocked;
			}
		}

		return parent::preview($arguments);
	}

	public function apply(array $arguments)
	{
		if (! $this->isOauthConnected()) {
			return new WP_Error(
				'sln_ai_gcal',
				__('Connect Google Calendar OAuth in Settings before changing sync behaviour.', 'salon-booking-system')
			);
		}
		$values = ! empty($arguments['values']) && is_array($arguments['values'])
			? $arguments['values']
			: $arguments;
		if (array_key_exists('google_calendar_lock_slots', $values) && ! SLN_AI_Edition::isPro()) {
			return new WP_Error('sln_ai_pro', __('Google Calendar lock slots requires PRO.', 'salon-booking-system'));
		}

		return parent::apply($arguments);
	}

	/**
	 * @return bool
	 */
	private function isOauthConnected()
	{
		$s = $this->settings();
		$token = '';
		if (method_exists($s, 'getGoogleAccessToken')) {
			$token = (string) $s->getGoogleAccessToken();
		}
		if ($token === '') {
			$token = (string) $s->get('sln_access_token');
		}
		$client = (string) $s->get('google_outh2_client_id');
		$secret = (string) $s->get('google_outh2_client_secret');

		return ($token !== '') || ($client !== '' && $secret !== '');
	}
}

class SLN_AI_Tools_SetBookingPages extends SLN_AI_Tools_SimpleSettings
{
	public function getName()
	{
		return 'set_booking_pages';
	}

	public function getTier()
	{
		return 'confirm';
	}

	protected function successMessage()
	{
		return __('Booking pages updated.', 'salon-booking-system');
	}

	protected function allowedKeys()
	{
		return array(
			'pay'              => 'int',
			'thankyou'         => 'int',
			'bookingmyaccount' => 'int',
		);
	}

	public function preview(array $arguments)
	{
		$check = $this->validatePages($arguments);
		if (is_wp_error($check)) {
			return $check;
		}

		return parent::preview($arguments);
	}

	public function apply(array $arguments)
	{
		$check = $this->validatePages($arguments);
		if (is_wp_error($check)) {
			return $check;
		}

		return parent::apply($arguments);
	}

	/**
	 * @param array $arguments
	 * @return true|WP_Error
	 */
	private function validatePages(array $arguments)
	{
		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		foreach ($mapped as $key => $pageId) {
			$pageId = (int) $pageId;
			if ($pageId <= 0) {
				return new WP_Error(
					'sln_ai_page',
					sprintf(
						/* translators: %s: setting key */
						__('Invalid page id for %s.', 'salon-booking-system'),
						$key
					)
				);
			}
			$post = get_post($pageId);
			if (! $post || $post->post_type !== 'page' || $post->post_status !== 'publish') {
				return new WP_Error(
					'sln_ai_page',
					sprintf(
						/* translators: 1: setting key, 2: page id */
						__('%1$s must be a published WordPress page (id %2$d not found/published).', 'salon-booking-system'),
						$key,
						$pageId
					)
				);
			}
		}

		return true;
	}
}

class SLN_AI_Tools_SetOnesignalEnabled extends SLN_AI_Tools_SimpleSettings
{
	public function getName()
	{
		return 'set_onesignal_enabled';
	}

	public function getTier()
	{
		return 'apply';
	}

	protected function successMessage()
	{
		return __('OneSignal notification toggle updated. App ID must still be set in Settings.', 'salon-booking-system');
	}

	protected function allowedKeys()
	{
		return array(
			'onesignal_new' => 'flag',
		);
	}

	public function preview(array $arguments)
	{
		$blocked = SLN_AI_Edition::blockIfFree('onesignal');
		if ($blocked) {
			return $blocked;
		}
		$base = parent::preview($arguments);
		if (is_wp_error($base) || empty($base['ok'])) {
			return $base;
		}
		$appId  = (string) $this->settings()->get('onesignal_app_id');
		$restKey = (string) $this->settings()->get('onesignal_rest_api_key');
		if ($appId === '' || $restKey === '') {
			$base['summary'] .= "\n\n" . __(
				'OneSignal App ID or REST API Key is empty — paste them under General settings (explain_setting topic=onesignal). AI never writes those keys.',
				'salon-booking-system'
			);
		}

		return $base;
	}

	public function apply(array $arguments)
	{
		if (! SLN_AI_Edition::isPro()) {
			return new WP_Error('sln_ai_pro', __('OneSignal requires the PRO edition.', 'salon-booking-system'));
		}

		return parent::apply($arguments);
	}
}
