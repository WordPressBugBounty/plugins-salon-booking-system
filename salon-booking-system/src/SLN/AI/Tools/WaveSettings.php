<?php

class SLN_AI_Tools_SetSalonIdentity extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_salon_identity'; }
	protected function successMessage() { return __('Salon identity updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'gen_name'    => 'text',
			'gen_email'   => 'email',
			'gen_phone'   => 'text',
			'gen_address' => 'textarea',
		);
	}

	public function preview(array $arguments)
	{
		$bound = SLN_AI_Multishop::bindShop($arguments, true);
		if (is_wp_error($bound)) {
			return $bound;
		}
		if (! empty($bound['guidance'])) {
			return $bound;
		}
		$shop = isset($bound['shop']) ? $bound['shop'] : null;
		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$before = array();
		foreach (array_keys($mapped) as $key) {
			$before[ $key ] = $shop
				? SLN_AI_Multishop::getScopedSetting($shop, $key)
				: $this->settings()->get($key);
		}
		$label = $shop && method_exists($shop, 'getName')
			? sprintf(' (shop: %s)', $shop->getName())
			: '';

		return array(
			'ok'        => true,
			'summary'   => $this->formatKeyValueDiff($before, $mapped) . $label,
			'proposed'  => $mapped,
			'current'   => $before,
			'arguments' => array_merge(
				array(
					'values'  => $mapped,
					'summary' => isset($arguments['summary']) ? sanitize_text_field($arguments['summary']) : '',
				),
				$shop ? array('shop_id' => (int) $shop->getId()) : array()
			),
			'tool'      => $this->getName(),
			'tier'      => $this->getTier(),
		);
	}

	public function apply(array $arguments)
	{
		$bound = SLN_AI_Multishop::bindShop($arguments, true);
		if (is_wp_error($bound)) {
			return $bound;
		}
		if (! empty($bound['guidance'])) {
			return new WP_Error('sln_ai_shop', __('Specify which shop to update.', 'salon-booking-system'));
		}
		$shop   = isset($bound['shop']) ? $bound['shop'] : null;
		$mapped = $this->mapArguments($arguments);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		$before = array();
		foreach (array_keys($mapped) as $key) {
			$before[ $key ] = $shop
				? SLN_AI_Multishop::getScopedSetting($shop, $key)
				: $this->settings()->get($key);
		}
		if ($shop) {
			foreach ($mapped as $key => $value) {
				SLN_AI_Multishop::writeShopSetting($shop, $key, $value);
			}
		} else {
			$this->writeSettings($mapped);
		}

		return array(
			'ok'      => true,
			'before'  => SLN_AI_Multishop::wrapSnapshot($shop, $before),
			'after'   => $mapped,
			'message' => $this->successMessage(),
		);
	}

	public function restore($previous)
	{
		$unwrapped = SLN_AI_Multishop::unwrapSnapshot($previous);
		$shop      = isset($unwrapped['shop']) ? $unwrapped['shop'] : null;
		$data      = isset($unwrapped['data']) ? $unwrapped['data'] : $previous;
		if (! is_array($data)) {
			return new WP_Error('sln_ai_restore', __('Nothing to restore.', 'salon-booking-system'));
		}
		if ($shop) {
			foreach ($data as $key => $value) {
				SLN_AI_Multishop::writeShopSetting($shop, $key, $value);
			}
		} else {
			$this->writeSettings($data);
		}

		return array(
			'ok'      => true,
			'message' => __('Settings restored.', 'salon-booking-system'),
		);
	}
}

class SLN_AI_Tools_SetLocaleFormats extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_locale_formats'; }
	protected function successMessage() { return __('Date/time and calendar formats updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'date_format'   => 'text',
			'time_format'   => 'text',
			'week_start'    => 'int',
			'calendar_view' => 'text',
		);
	}
}

class SLN_AI_Tools_SetSlotTiming extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_slot_timing'; }
	public function getTier() { return 'confirm'; }
	protected function needsCacheRefresh() { return true; }
	protected function successMessage() { return __('Slot timing updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'interval'                       => 'int',
			'hours_before_from'              => 'int',
			'hours_before_to'                => 'int',
			'auto_align_slots'               => 'flag',
			'parallels_hour'                 => 'int',
			'parallels_day'                  => 'int',
			'reservation_interval_enabled'   => 'flag',
			'minutes_between_reservation'    => 'int',
		);
	}
}

class SLN_AI_Tools_SetBookingStatus extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_booking_status'; }
	protected function successMessage() { return __('Booking status options updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'confirmation'     => 'flag',
			'disabled'         => 'flag',
			'disabled_message' => 'textarea',
		);
	}
}

class SLN_AI_Tools_SetCancellationPolicy extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_cancellation_policy'; }
	protected function successMessage() { return __('Cancellation policy updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'cancellation_enabled'       => 'flag',
			'hours_before_cancellation'  => 'int',
			'auto_trash_cancelled'       => 'flag',
		);
	}
}

class SLN_AI_Tools_SetReschedulingPolicy extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_rescheduling_policy'; }
	protected function successMessage() { return __('Rescheduling policy updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'rescheduling_disabled'      => 'flag',
			'days_before_rescheduling'   => 'int',
		);
	}
}

class SLN_AI_Tools_SetSocialLinks extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_social_links'; }
	protected function successMessage() { return __('Social links updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'soc_facebook' => 'url',
			'soc_twitter'  => 'url',
			'soc_google'   => 'url',
		);
	}
}

class SLN_AI_Tools_SetAssistantSelectionMode extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_assistant_selection_mode'; }
	protected function successMessage() { return __('Assistant selection mode updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'attendant_enabled'                   => 'flag',
			'm_attendant_enabled'                 => 'flag',
			'skip_attendants_enabled'             => 'flag',
			'choose_attendant_for_me_disabled'    => 'flag',
			'hide_invalid_attendants_enabled'     => 'flag',
			'only_from_backend_attendant_enabled' => 'flag',
			'attendant_email'                     => 'flag',
		);
	}
}

class SLN_AI_Tools_SetAvailabilityMode extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_availability_mode'; }
	public function getTier() { return 'confirm'; }
	protected function needsCacheRefresh() { return true; }
	protected function successMessage() { return __('Availability mode updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'availability_mode'                 => 'text',
			'do_not_nest_same_booking_services' => 'flag',
			'nested_bookings_enabled'           => 'flag',
		);
	}

	public function preview(array $arguments)
	{
		$check = $this->guardNestedOnFree($arguments);
		if ($check) {
			return $check;
		}

		return parent::preview($arguments);
	}

	public function apply(array $arguments)
	{
		$check = $this->guardNestedOnFree($arguments);
		if ($check) {
			return new WP_Error('sln_ai_pro', __('Nested bookings require the PRO edition.', 'salon-booking-system'));
		}

		return parent::apply($arguments);
	}

	/**
	 * @param array $arguments
	 * @return array|null
	 */
	private function guardNestedOnFree(array $arguments)
	{
		$values = array();
		if (! empty($arguments['values']) && is_array($arguments['values'])) {
			$values = $arguments['values'];
		} else {
			$values = $arguments;
		}
		$touchesNested = isset($values['nested_bookings_enabled'])
			|| isset($values['do_not_nest_same_booking_services']);
		if (! $touchesNested) {
			return null;
		}

		return SLN_AI_Edition::blockIfFree('nested_bookings');
	}
}

class SLN_AI_Tools_SetBookingFormFlow extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_booking_form_flow'; }
	public function getTier() { return 'confirm'; }
	protected function successMessage() { return __('Booking form flow updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'form_steps_alt_order'              => 'flag',
			'multiple_customers_for_assistant'  => 'flag',
		);
	}
}

class SLN_AI_Tools_SetResourcesEnabled extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_resources_enabled'; }
	protected function successMessage() { return __('Resources setting updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array('enable_resources' => 'flag');
	}

	public function preview(array $arguments)
	{
		$blocked = SLN_AI_Edition::blockIfFree('resources');
		if ($blocked) {
			return $blocked;
		}

		return parent::preview($arguments);
	}

	public function apply(array $arguments)
	{
		$blocked = SLN_AI_Edition::blockIfFree('resources');
		if ($blocked) {
			return new WP_Error(
				'sln_ai_pro',
				__('Resources require the PRO edition.', 'salon-booking-system')
			);
		}

		return parent::apply($arguments);
	}
}

class SLN_AI_Tools_SetGuestCheckout extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_guest_checkout'; }
	protected function successMessage() { return __('Guest checkout options updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'enabled_guest_checkout'       => 'flag',
			'enabled_force_guest_checkout' => 'flag',
			'skip_checkout_if_logged_in'   => 'flag',
		);
	}
}

class SLN_AI_Tools_SetServiceSelectionLimits extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_service_selection_limits'; }
	protected function successMessage() { return __('Service selection limits updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'primary_services_count'                     => 'int',
			'secondary_services_count'                   => 'int',
			'is_secondary_services_selection_required'   => 'flag',
		);
	}
}

class SLN_AI_Tools_SetDiscountSystemEnabled extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_discount_system_enabled'; }
	protected function successMessage() { return __('Discount system toggle updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array('enable_discount_system' => 'flag');
	}
}

class SLN_AI_Tools_SetCheckoutCopy extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_checkout_copy'; }
	protected function successMessage() { return __('Checkout copy updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'gen_timetable'                   => 'textarea',
			'last_step_note'                  => 'textarea',
			'disable_summary_skip_countdown'  => 'flag',
		);
	}

	public function preview(array $arguments)
	{
		$values = ! empty($arguments['values']) && is_array($arguments['values']) ? $arguments['values'] : $arguments;
		if (isset($values['disable_summary_skip_countdown'])) {
			$blocked = SLN_AI_Edition::blockIfFree('summary_countdown');
			if ($blocked) {
				return $blocked;
			}
		}

		return parent::preview($arguments);
	}
}

class SLN_AI_Tools_SetCurrencyDisplay extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_currency_display'; }
	protected function successMessage() { return __('Currency display updated (payment gateways unchanged).', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'hide_prices'            => 'flag',
			'pay_currency'           => 'text',
			'pay_currency_pos'       => 'text',
			'pay_decimal_separator'  => 'text',
			'pay_thousand_separator' => 'text',
		);
	}
}

class SLN_AI_Tools_SetFrontendAssetFlags extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_frontend_asset_flags'; }
	public function getTier() { return 'confirm'; }
	protected function successMessage() { return __('Frontend asset flags updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'ajax_enabled'                      => 'flag',
			'no_bootstrap'                      => 'flag',
			'no_bootstrap_js'                   => 'flag',
			'disable_google_fonts'              => 'flag',
			'hide_service_duration'             => 'flag',
			'replace_booking_modal_with_popup'  => 'flag',
		);
	}
}

class SLN_AI_Tools_SetDebugAndWorkerRole extends SLN_AI_Tools_SimpleSettings
{
	public function getName() { return 'set_debug_and_worker_role'; }
	public function getTier() { return 'confirm'; }
	protected function successMessage() { return __('Debug / worker settings updated.', 'salon-booking-system'); }
	protected function allowedKeys()
	{
		return array(
			'debug'                    => 'flag',
			'enable_debug_logs'        => 'flag',
			'enable_sln_worker_role'   => 'flag',
		);
	}

	public function apply(array $arguments)
	{
		$result = parent::apply($arguments);
		if (is_wp_error($result)) {
			return $result;
		}
		// Keep companion option in sync when debug toggled.
		if (isset($arguments['values']['debug']) || isset($arguments['debug'])) {
			$on = $this->settings()->get('debug');
			update_option('sln_debug_enabled', $on ? '1' : '0');
		}

		return $result;
	}
}
