<?php

/**
 * Whitelisted AI Setup tools, schemas, and risk tiers.
 */
class SLN_AI_ToolRegistry
{
	/** @var bool */
	private static $loaded = false;

	/**
	 * Load multi-class tool files (not 1:1 autoload).
	 */
	public static function bootstrap()
	{
		if (self::$loaded) {
			return;
		}
		$dir = SLN_PLUGIN_DIR . '/src/SLN/AI/Tools/';
		require_once $dir . 'Abstract.php';
		require_once $dir . 'SimpleSettings.php';
		require_once $dir . 'ExplainSetting.php';
		require_once $dir . 'SuggestCapability.php';
		require_once $dir . 'ExplainUnavailableSlot.php';
		require_once $dir . 'FindBooking.php';
		require_once $dir . 'CountBookings.php';
		require_once $dir . 'FindDiscount.php';
		require_once $dir . 'FindCustomer.php';
		require_once $dir . 'FindCatalog.php';
		require_once $dir . 'BookingWrite.php';
		require_once $dir . 'WaveSettings.php';
		require_once $dir . 'ExtendedSettings.php';
		require_once $dir . 'Catalog.php';
		require_once $dir . 'StyleNotify.php';
		self::$loaded = true;
	}

	/**
	 * @return array[] name, tier, class, description, parameters
	 */
	public function getCatalog()
	{
		self::bootstrap();

		$simpleObject = array(
			'type'       => 'object',
			'properties' => array(
				'values'  => array(
					'type'        => 'object',
					'description' => 'Map of setting keys to values',
				),
				'summary' => array('type' => 'string'),
			),
		);
		$simpleObjectShop = $simpleObject;
		$simpleObjectShop['properties']['shop_id']   = array('type' => 'integer', 'description' => 'Multi-shop location id');
		$simpleObjectShop['properties']['shop_name'] = array('type' => 'string', 'description' => 'Multi-shop location name');

		return array(
			array(
				'name'        => 'set_salon_availabilities',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetAvailabilities',
				'description' => 'Replace salon-level opening hours. Day keys 1=Sun…7=Sat. One interval by default; second only for explicit two shifts. On Multi-shop sites pass shop_id or shop_name.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('rules'),
					'properties' => array(
						'mode'       => array('type' => 'string', 'enum' => array('replace_all')),
						'summary'    => array('type' => 'string'),
						'shop_id'    => array('type' => 'integer', 'description' => 'Multi-shop location id'),
						'shop_name'  => array('type' => 'string', 'description' => 'Multi-shop location name'),
						'rules'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array('days', 'intervals'),
								'properties' => array(
									'days'      => array('type' => 'array', 'items' => array('type' => 'integer')),
									'intervals' => array(
										'type'     => 'array',
										'minItems' => 1,
										'maxItems' => 2,
										'items'    => array(
											'type'       => 'object',
											'required'   => array('from', 'to'),
											'properties' => array(
												'from' => array('type' => 'string'),
												'to'   => array('type' => 'string'),
											),
										),
									),
									'always' => array('type' => 'boolean'),
								),
							),
						),
					),
				),
			),
			array(
				'name'        => 'set_salon_holidays',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetHolidays',
				'description' => 'Add or replace salon holidays. Prefer append; replace_all clears/rewrites. full_day default true. On Multi-shop sites pass shop_id or shop_name.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('rules'),
					'properties' => array(
						'mode'       => array('type' => 'string', 'enum' => array('append', 'replace_all')),
						'summary'    => array('type' => 'string'),
						'shop_id'    => array('type' => 'integer'),
						'shop_name'  => array('type' => 'string'),
						'rules'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array('from_date', 'to_date'),
								'properties' => array(
									'from_date' => array('type' => 'string'),
									'to_date'   => array('type' => 'string'),
									'full_day'  => array('type' => 'boolean'),
									'from_time' => array('type' => 'string'),
									'to_time'   => array('type' => 'string'),
								),
							),
						),
					),
				),
			),
			array(
				'name'        => 'explain_setting',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_ExplainSetting',
				'description' => 'Explain where to configure a topic and return an admin URL. Use for payments, SMS, OAuth, reCAPTCHA, license, Free vs PRO, changelog / what\'s new, add-ons (waitlist, kiosk, communicator, migrator, woo_checkout, pwa, addons), and any unsupported domain. Never write secrets.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('topic'),
					'properties' => array(
						'topic' => array(
							'type' => 'string',
							'enum' => array_keys(SLN_AI_Tools_ExplainSetting::topics()),
						),
					),
				),
			),
			array(
				'name'        => 'suggest_capability',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_SuggestCapability',
				'description' => 'Proactively suggest core features or official add-ons that match the merchant’s goal (waitlist/no-shows, kiosk/walk-in, multi-shop, payments, discounts, PWA, communicator, migrator, “what can the plugin do”). Returns short blurbs + admin/Extensions links. Prefer this over generic help when the question is about capabilities or which product to use. Guidance only.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'query'  => array(
							'type'        => 'string',
							'description' => 'Goal keywords, e.g. waitlist, kiosk, payments, multi-shop, capabilities',
						),
						'intent' => array(
							'type'        => 'string',
							'description' => 'Alias of query',
						),
						'limit'  => array(
							'type'        => 'integer',
							'description' => 'Max suggestions (default 5)',
						),
					),
				),
			),
			array(
				'name'        => 'explain_unavailable_slot',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_ExplainUnavailableSlot',
				'description' => 'Diagnose why a booking slot, service, or assistant may be unavailable. Prefer date + service and/or assistant + expected start time (H:i). If critical details are missing, the tool returns clarifying questions — do not invent them. Optional focus=service|assistant|slot. Multi-shop: shop_id/shop_name. Read-only.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'date'           => array('type' => 'string', 'description' => 'Y-m-d (optional)'),
						'time'           => array('type' => 'string', 'description' => 'H:i (optional)'),
						'service_id'     => array('type' => 'integer'),
						'service_name'   => array('type' => 'string'),
						'assistant_id'   => array('type' => 'integer'),
						'assistant_name' => array('type' => 'string'),
						'entity_name'    => array('type' => 'string', 'description' => 'Ambiguous name to resolve as service or assistant'),
						'shop_id'        => array('type' => 'integer'),
						'shop_name'      => array('type' => 'string'),
						'focus'          => array('type' => 'string', 'enum' => array('assistant', 'service', 'slot')),
					),
				),
			),
			array(
				'name'        => 'find_booking',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_FindBooking',
				'description' => 'Look up existing bookings by id, customer name/email/phone, optional date (Y-m-d) or status. INCLUDES draft (auto-draft) and ERROR (sln-b-error) — not only confirmed/paid. Read-only; returns edit links. Use when the user asks to find/show/locate a booking or appointment. Do NOT use for totals/how-many questions — use count_bookings.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array('type' => 'integer', 'description' => 'Booking post id'),
						'query'  => array('type' => 'string', 'description' => 'Name, email, phone, or free-text search'),
						'search' => array('type' => 'string', 'description' => 'Alias of query'),
						'name'   => array('type' => 'string'),
						'email'  => array('type' => 'string'),
						'phone'  => array('type' => 'string'),
						'date'      => array('type' => 'string', 'description' => 'Optional booking date Y-m-d'),
						'date_from' => array('type' => 'string', 'description' => 'Optional range start Y-m-d (e.g. this month)'),
						'date_to'   => array('type' => 'string', 'description' => 'Optional range end Y-m-d'),
						'status'    => array('type' => 'string', 'description' => 'Optional filter: draft, error, pending, confirmed, paid, canceled, …'),
					),
				),
			),
			array(
				'name'        => 'count_bookings',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_CountBookings',
				'description' => 'Count how many bookings exist (total or in a date range). Use for “how many bookings…”, “quante prenotazioni…”, “fino ad oggi”, this month, today. Returns a total (and by-status breakdown). Draft/ERROR excluded by default. Read-only — not for looking up a specific booking (use find_booking).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'date'          => array('type' => 'string', 'description' => 'Single day Y-m-d (sets from=to)'),
						'date_from'     => array('type' => 'string', 'description' => 'Range start Y-m-d'),
						'date_to'       => array('type' => 'string', 'description' => 'Range end Y-m-d (use today for “up to today”)'),
						'status'        => array('type' => 'string', 'description' => 'Optional single status filter'),
						'include_draft' => array('type' => 'boolean', 'description' => 'Include draft/auto-draft (default false)'),
						'include_error' => array('type' => 'boolean', 'description' => 'Include ERROR bookings (default false)'),
					),
				),
			),
			array(
				'name'        => 'create_booking',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_CreateBooking',
				'description' => 'Create a new reservation (confirm-first). Requires date Y-m-d, time H:i, at least one service (id or name), and the customer fields the salon marks required in checkout settings (first name and email by default, phone if configured). Ask the merchant for missing required customer data — never invent it or leave it blank. Optional assistant (if assistant selection is enabled and none is named, an available assistant is auto-assigned with the same rules as the booking form). Optional status (default confirmed), notes.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('date', 'time'),
					'properties' => array(
						'date'                => array('type' => 'string', 'description' => 'Y-m-d'),
						'time'                => array('type' => 'string', 'description' => 'H:i'),
						'service_id'          => array('type' => 'integer'),
						'service_name'        => array('type' => 'string'),
						'assistant_id'        => array('type' => 'integer'),
						'assistant_name'      => array('type' => 'string'),
						'services'            => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'service_id'     => array('type' => 'integer'),
									'service_name'   => array('type' => 'string'),
									'assistant_id'   => array('type' => 'integer'),
									'assistant_name' => array('type' => 'string'),
								),
							),
						),
						'customer_name'       => array('type' => 'string', 'description' => 'Full name; split into first/last if needed'),
						'customer_first_name' => array('type' => 'string'),
						'customer_last_name'  => array('type' => 'string'),
						'customer_email'      => array('type' => 'string'),
						'customer_phone'      => array('type' => 'string'),
						'status'              => array('type' => 'string', 'description' => 'confirmed|pending|paid|canceled|…'),
						'note'                => array('type' => 'string'),
						'admin_note'          => array('type' => 'string'),
					),
				),
			),
			array(
				'name'        => 'create_bookings',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_CreateBookings',
				'description' => 'Create 2–10 reservations at once (confirm-first). Same customer/service/time for every date; assistants are auto-assigned per date when selection is enabled (or use a named assistant). Pass dates[] (Y-m-d) OR recurrence { weekday: monday|1–7 ISO Mon=1…Sun=7, count: N, optional from: Y-m-d }. Single booking → create_booking.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('time'),
					'properties' => array(
						'dates'               => array(
							'type'        => 'array',
							'items'       => array('type' => 'string'),
							'description' => 'Explicit Y-m-d dates (2–10)',
						),
						'recurrence'          => array(
							'type'       => 'object',
							'description'=> 'Expand next N weekdays from today (or from)',
							'properties' => array(
								'weekday' => array('type' => 'string', 'description' => 'monday…sunday or ISO 1–7'),
								'count'   => array('type' => 'integer', 'description' => 'How many occurrences (max 10)'),
								'from'    => array('type' => 'string', 'description' => 'Optional start Y-m-d'),
							),
						),
						'date'                => array('type' => 'string', 'description' => 'Optional single Y-m-d (prefer dates[] / recurrence)'),
						'time'                => array('type' => 'string', 'description' => 'H:i shared by all'),
						'service_id'          => array('type' => 'integer'),
						'service_name'        => array('type' => 'string'),
						'assistant_id'        => array('type' => 'integer'),
						'assistant_name'      => array('type' => 'string'),
						'services'            => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'service_id'     => array('type' => 'integer'),
									'service_name'   => array('type' => 'string'),
									'assistant_id'   => array('type' => 'integer'),
									'assistant_name' => array('type' => 'string'),
								),
							),
						),
						'customer_name'       => array('type' => 'string'),
						'customer_first_name' => array('type' => 'string'),
						'customer_last_name'  => array('type' => 'string'),
						'customer_email'      => array('type' => 'string'),
						'customer_phone'      => array('type' => 'string'),
						'status'              => array('type' => 'string'),
						'note'                => array('type' => 'string'),
						'admin_note'          => array('type' => 'string'),
					),
				),
			),
			array(
				'name'        => 'update_booking',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_UpdateBooking',
				'description' => 'Edit an existing reservation by id (confirm-first): date, time, services/assistant, customer fields, status. Prefer find_booking first when the id is unknown. Only send fields that change.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('id'),
					'properties' => array(
						'id'                  => array('type' => 'integer', 'description' => 'Booking post id'),
						'date'                => array('type' => 'string', 'description' => 'Y-m-d'),
						'time'                => array('type' => 'string', 'description' => 'H:i'),
						'service_id'          => array('type' => 'integer'),
						'service_name'        => array('type' => 'string'),
						'assistant_id'        => array('type' => 'integer'),
						'assistant_name'      => array('type' => 'string'),
						'services'            => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'service_id'     => array('type' => 'integer'),
									'service_name'   => array('type' => 'string'),
									'assistant_id'   => array('type' => 'integer'),
									'assistant_name' => array('type' => 'string'),
								),
							),
						),
						'customer_name'       => array('type' => 'string'),
						'customer_first_name' => array('type' => 'string'),
						'customer_last_name'  => array('type' => 'string'),
						'customer_email'      => array('type' => 'string'),
						'customer_phone'      => array('type' => 'string'),
						'status'              => array('type' => 'string'),
						'note'                => array('type' => 'string'),
						'admin_note'          => array('type' => 'string'),
					),
				),
			),
			$this->simple('set_salon_identity', 'SLN_AI_Tools_SetSalonIdentity', 'Set salon name, email, phone, address (not logo upload). On Multi-shop pass shop_id/shop_name for that location.', $simpleObjectShop),
			$this->simple('set_locale_formats', 'SLN_AI_Tools_SetLocaleFormats', 'Set date_format, time_format, week_start, calendar_view.', $simpleObject),
			$this->simple('set_slot_timing', 'SLN_AI_Tools_SetSlotTiming', 'Set interval, hours_before_*, parallels_*, auto_align_slots.', $simpleObject, 'confirm'),
			$this->simple('set_booking_status', 'SLN_AI_Tools_SetBookingStatus', 'Set confirmation, disabled, disabled_message.', $simpleObject),
			$this->simple('set_cancellation_policy', 'SLN_AI_Tools_SetCancellationPolicy', 'Set cancellation_enabled, hours_before_cancellation, auto_trash_cancelled.', $simpleObject),
			$this->simple('set_rescheduling_policy', 'SLN_AI_Tools_SetReschedulingPolicy', 'Set rescheduling_disabled, days_before_rescheduling.', $simpleObject),
			$this->simple('set_social_links', 'SLN_AI_Tools_SetSocialLinks', 'Set soc_facebook, soc_twitter, soc_google URLs.', $simpleObject),
			$this->simple('set_assistant_selection_mode', 'SLN_AI_Tools_SetAssistantSelectionMode', 'Toggle attendant_enabled and related assistant UX flags.', $simpleObject),
			$this->simple('set_availability_mode', 'SLN_AI_Tools_SetAvailabilityMode', 'Set availability_mode and nesting flags.', $simpleObject, 'confirm'),
			$this->simple('set_booking_form_flow', 'SLN_AI_Tools_SetBookingFormFlow', 'Set form_steps_alt_order and multiple_customers_for_assistant.', $simpleObject, 'confirm'),
			$this->simple('set_resources_enabled', 'SLN_AI_Tools_SetResourcesEnabled', 'Toggle enable_resources (PRO).', $simpleObject),
			$this->simple('set_guest_checkout', 'SLN_AI_Tools_SetGuestCheckout', 'Guest checkout flags.', $simpleObject),
			$this->simple('set_service_selection_limits', 'SLN_AI_Tools_SetServiceSelectionLimits', 'Primary/secondary service selection counts.', $simpleObject),
			$this->simple('set_discount_system_enabled', 'SLN_AI_Tools_SetDiscountSystemEnabled', 'Toggle enable_discount_system.', $simpleObject),
			$this->simple('set_checkout_copy', 'SLN_AI_Tools_SetCheckoutCopy', 'Checkout copy: gen_timetable, last_step_note.', $simpleObject),
			$this->simple('set_currency_display', 'SLN_AI_Tools_SetCurrencyDisplay', 'Currency display and hide_prices. Does NOT configure payment gateways.', $simpleObject),
			$this->simple('set_frontend_asset_flags', 'SLN_AI_Tools_SetFrontendAssetFlags', 'Frontend asset toggles (bootstrap, ajax, fonts).', $simpleObject, 'confirm'),
			$this->simple('set_email_notification_templates', 'SLN_AI_Tools_SetEmailNotificationTemplates', 'Email template/subject fields (not SMS).', $simpleObject, 'confirm'),
			$this->simple('set_notification_schedule', 'SLN_AI_Tools_SetNotificationSchedule', 'Email/SMS reminder, follow-up, and feedback schedule toggles/intervals (not templates, not SMS credentials).', $simpleObject, 'confirm'),
			$this->simple('set_sms_behaviour', 'SLN_AI_Tools_SetSmsBehaviour', 'SMS enable and non-credential notify/remind flags. Credentials → explain_setting topic=sms.', $simpleObject, 'confirm'),
			$this->simple('set_payment_behaviour', 'SLN_AI_Tools_SetPaymentBehaviour', 'PRO payment behaviour: pay_enabled, pay_method (installed methods only), deposit, tips, min order, unpaid offset. Never gateway secrets.', $simpleObject, 'confirm'),
			$this->simple('set_pro_booking_flags', 'SLN_AI_Tools_SetProBookingFlags', 'PRO flags: fidelity score, customer timezone slots, one-click booking + min bookings.', $simpleObject, 'confirm'),
			$this->simple('set_gcalendar_behaviour', 'SLN_AI_Tools_SetGcalendarBehaviour', 'Google Calendar enable/publish-pending/lock-slots after OAuth is connected. Secrets → explain_setting.', $simpleObject, 'confirm'),
			$this->simple('set_booking_pages', 'SLN_AI_Tools_SetBookingPages', 'Map pay (booking), thankyou, bookingmyaccount page IDs (published pages only).', $simpleObject, 'confirm'),
			$this->simple('set_onesignal_enabled', 'SLN_AI_Tools_SetOnesignalEnabled', 'Toggle onesignal_new only (PRO). App ID stays guidance.', $simpleObject, 'apply'),
			$this->simple('set_debug_and_worker_role', 'SLN_AI_Tools_SetDebugAndWorkerRole', 'Debug logging and worker role flags.', $simpleObject, 'confirm'),
			array(
				'name'        => 'upsert_service',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_UpsertService',
				'description' => 'Create/update a service: name, price, duration, flags (secondary/exclusive/hide/variable/break/offset/lock), categories, resource links.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('name'),
					'properties' => array(
						'name'                       => array('type' => 'string'),
						'id'                         => array('type' => 'integer'),
						'price'                      => array('type' => 'number'),
						'duration'                   => array('type' => 'string'),
						'description'                => array('type' => 'string'),
						'secondary'                  => array('type' => 'boolean'),
						'exclusive'                  => array('type' => 'boolean'),
						'hide_on_frontend'           => array('type' => 'boolean'),
						'variable_duration'          => array('type' => 'boolean'),
						'variable_price_enabled'     => array('type' => 'boolean'),
						'break_duration_enabled'     => array('type' => 'boolean'),
						'break_duration'             => array('type' => 'string'),
						'offset_for_service'         => array('type' => 'boolean'),
						'offset_for_service_interval'=> array('type' => 'integer'),
						'lock_for_service'           => array('type' => 'boolean'),
						'lock_for_service_interval'  => array('type' => 'integer'),
						'category_names'             => array('type' => 'array', 'items' => array('type' => 'string')),
						'resource_ids'               => array('type' => 'array', 'items' => array('type' => 'integer')),
						'resource_names'             => array('type' => 'array', 'items' => array('type' => 'string')),
					),
				),
			),
			array(
				'name'        => 'set_service_pricing',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetServicePricing',
				'description' => 'Alias of upsert_service for price/duration updates.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('name'),
					'properties' => array(
						'name'     => array('type' => 'string'),
						'id'       => array('type' => 'integer'),
						'price'    => array('type' => 'number'),
						'duration' => array('type' => 'string'),
					),
				),
			),
			array(
				'name'        => 'set_service_availabilities',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetServiceAvailabilities',
				'description' => 'Set per-service opening hours. Pass service_id or name plus rules like set_salon_availabilities.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('rules'),
					'properties' => array(
						'service_id' => array('type' => 'integer'),
						'name'       => array('type' => 'string'),
						'rules'      => array('type' => 'array'),
					),
				),
			),
			array(
				'name'        => 'set_service_assistants',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetServiceAssistants',
				'description' => 'Assign assistants to a service by service_id/name and assistant_ids/names.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'service_id'      => array('type' => 'integer'),
						'service_name'    => array('type' => 'string'),
						'assistant_ids'   => array('type' => 'array', 'items' => array('type' => 'integer')),
						'assistant_names' => array('type' => 'array', 'items' => array('type' => 'string')),
					),
				),
			),
			array(
				'name'        => 'upsert_assistant',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_UpsertAssistant',
				'description' => 'Create/update an assistant (name, email/phone, description, hide_on_frontend, multiple_customers; google_calendar id only if GCal connected).',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('name'),
					'properties' => array(
						'name'                                      => array('type' => 'string'),
						'id'                                        => array('type' => 'integer'),
						'email'                                     => array('type' => 'string'),
						'phone'                                     => array('type' => 'string'),
						'description'                               => array('type' => 'string'),
						'hide_on_frontend'                          => array('type' => 'boolean'),
						'multiple_customers'                        => array('type' => 'boolean'),
						'display_phone_inside_booking_notification' => array('type' => 'boolean'),
						'google_calendar'                           => array('type' => 'string'),
					),
				),
			),
			array(
				'name'        => 'set_assistant_availabilities',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetAssistantAvailabilities',
				'description' => 'Set per-assistant opening hours. Pass assistant_id or name plus rules like set_salon_availabilities.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('rules'),
					'properties' => array(
						'assistant_id' => array('type' => 'integer'),
						'name'         => array('type' => 'string'),
						'rules'        => array('type' => 'array'),
						'mode'         => array('type' => 'string', 'enum' => array('replace_all')),
					),
				),
			),
			array(
				'name'        => 'set_assistant_holidays',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetAssistantHolidays',
				'description' => 'Set per-assistant holidays. Pass assistant_id or name plus holiday rules.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('rules'),
					'properties' => array(
						'assistant_id' => array('type' => 'integer'),
						'name'         => array('type' => 'string'),
						'mode'         => array('type' => 'string', 'enum' => array('append', 'replace_all')),
						'rules'        => array('type' => 'array'),
					),
				),
			),
			array(
				'name'        => 'upsert_resource',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_UpsertResource',
				'description' => 'Create/update a resource (PRO): name, units, enabled, linked service_ids/names.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('name'),
					'properties' => array(
						'name'          => array('type' => 'string'),
						'unit'          => array('type' => 'integer'),
						'id'            => array('type' => 'integer'),
						'enabled'       => array('type' => 'boolean'),
						'service_ids'   => array('type' => 'array', 'items' => array('type' => 'integer')),
						'service_names' => array('type' => 'array', 'items' => array('type' => 'string')),
					),
				),
			),
			array(
				'name'        => 'set_booking_style_colors',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetBookingStyleColors',
				'description' => 'Update style_shortcode / style_colors_enabled / style_colors map.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'style_shortcode'       => array('type' => 'string'),
						'style_colors_enabled'  => array('type' => 'boolean'),
						'style_colors'          => array('type' => 'object'),
					),
				),
			),
			array(
				'name'        => 'set_checkout_fields',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_SetCheckoutFields',
				'description' => 'Patch checkout_fields object (shallow merge). Do not send secrets.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('checkout_fields'),
					'properties' => array(
						'checkout_fields' => array('type' => 'object'),
					),
				),
			),
			array(
				'name'        => 'find_discount',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_FindDiscount',
				'description' => 'List or look up discount coupons by id, name, or code (read-only). Use when the user asks which discounts/coupons exist, to check a code, or open the Discounts section. Requires discount system + Discount add-on.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array('type' => 'integer', 'description' => 'Discount post id'),
						'query'  => array('type' => 'string', 'description' => 'Name or coupon code search; omit to list all'),
						'search' => array('type' => 'string', 'description' => 'Alias of query'),
						'name'   => array('type' => 'string'),
						'code'   => array('type' => 'string', 'description' => 'Coupon code'),
					),
				),
			),
			array(
				'name'        => 'find_customer',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_FindCustomer',
				'description' => 'List or look up salon customers (WP users with customer role) by id, name, email, or phone. Read-only; returns edit links to Salon → Customers. Use when the user asks which customers exist or to find a client profile (not the same as find_booking).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array('type' => 'integer', 'description' => 'WordPress user id'),
						'query'  => array('type' => 'string', 'description' => 'Name, email, or phone; omit to list recent/all (capped)'),
						'search' => array('type' => 'string', 'description' => 'Alias of query'),
						'name'   => array('type' => 'string'),
						'email'  => array('type' => 'string'),
						'phone'  => array('type' => 'string'),
					),
				),
			),
			array(
				'name'        => 'find_service',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_FindService',
				'description' => 'List or look up services by id or name (read-only). Returns price, duration, edit links. Prefer this before upsert_service when the user asks which services exist or to open one.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array('type' => 'integer'),
						'query'  => array('type' => 'string', 'description' => 'Service name search; omit to list'),
						'search' => array('type' => 'string'),
						'name'   => array('type' => 'string'),
					),
				),
			),
			array(
				'name'        => 'find_assistant',
				'tier'        => 'guidance',
				'class'       => 'SLN_AI_Tools_FindAssistant',
				'description' => 'List or look up assistants/staff (attendants) by id or name (read-only). Returns contact fields when set, edit links. Prefer before upsert_assistant when listing or opening staff.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array('type' => 'integer'),
						'query'  => array('type' => 'string', 'description' => 'Assistant name search; omit to list'),
						'search' => array('type' => 'string'),
						'name'   => array('type' => 'string'),
					),
				),
			),
			array(
				'name'        => 'upsert_discount',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_UpsertDiscount',
				'description' => 'Create/update discount: name, amount, amount_type, code, from/to dates, usage limits, service scope. Requires discount system enabled. Prefer find_discount first when updating an existing coupon.',
				'parameters'  => array(
					'type'       => 'object',
					'required'   => array('name'),
					'properties' => array(
						'name'               => array('type' => 'string'),
						'amount'             => array('type' => 'number'),
						'amount_type'        => array('type' => 'string'),
						'code'               => array('type' => 'string'),
						'id'                 => array('type' => 'integer'),
						'from'               => array('type' => 'string', 'description' => 'Start date Y-m-d'),
						'to'                 => array('type' => 'string', 'description' => 'End date Y-m-d'),
						'usages_limit'       => array('type' => 'integer'),
						'usages_limit_total' => array('type' => 'integer'),
						'service_ids'        => array('type' => 'array', 'items' => array('type' => 'integer')),
						'service_names'      => array('type' => 'array', 'items' => array('type' => 'string')),
					),
				),
			),
			array(
				'name'        => 'complete_onboarding',
				'tier'        => 'confirm',
				'class'       => 'SLN_AI_Tools_CompleteOnboarding',
				'description' => 'Mark the setup wizard as completed.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'confirm' => array('type' => 'boolean'),
					),
				),
			),
		);
	}

	/**
	 * @param string $name
	 * @param string $class
	 * @param string $description
	 * @param array  $parameters
	 * @param string $tier apply|confirm|guidance
	 * @return array
	 */
	private function simple($name, $class, $description, array $parameters, $tier = 'apply')
	{
		return array(
			'name'        => $name,
			'tier'        => $tier,
			'class'       => $class,
			'description' => $description,
			'parameters'  => $parameters,
		);
	}

	/**
	 * Definitions for LLM (name/description/parameters only).
	 *
	 * @return array[]
	 */
	public function getToolDefinitions()
	{
		$out = array();
		foreach ($this->getCatalog() as $tool) {
			$out[] = array(
				'name'        => $tool['name'],
				'description' => $tool['description'],
				'parameters'  => $tool['parameters'],
			);
		}

		return $out;
	}

	/**
	 * Compact tool definitions for LLM/proxy payloads (smaller prompt → faster TTFT).
	 * Keeps names, types, enums, required; drops verbose nested descriptions.
	 *
	 * @return array[]
	 */
	public function getToolDefinitionsForLlm()
	{
		$out = array();
		foreach ($this->getToolDefinitions() as $tool) {
			$desc = isset($tool['description']) ? (string) $tool['description'] : '';
			$out[] = array(
				'name'        => isset($tool['name']) ? $tool['name'] : '',
				'description' => self::truncateForLlm($desc, 140),
				'parameters'  => self::compactJsonSchema(
					isset($tool['parameters']) && is_array($tool['parameters']) ? $tool['parameters'] : array()
				),
			);
		}

		return $out;
	}

	/**
	 * @param string $text
	 * @param int    $max
	 * @return string
	 */
	private static function truncateForLlm($text, $max)
	{
		$text = trim((string) $text);
		$max  = max(40, (int) $max);
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($text) <= $max) {
				return $text;
			}

			return rtrim(mb_substr($text, 0, $max - 1)) . '…';
		}
		if (strlen($text) <= $max) {
			return $text;
		}

		return rtrim(substr($text, 0, $max - 1)) . '…';
	}

	/**
	 * @param array $schema
	 * @return array
	 */
	private static function compactJsonSchema(array $schema)
	{
		unset($schema['description'], $schema['title'], $schema['examples'], $schema['$comment']);
		foreach ($schema as $key => $value) {
			if (! is_array($value)) {
				continue;
			}
			if ($key === 'properties' || $key === '$defs' || $key === 'definitions') {
				foreach ($value as $propName => $propSchema) {
					if (is_array($propSchema)) {
						$schema[ $key ][ $propName ] = self::compactJsonSchema($propSchema);
					}
				}
				continue;
			}
			if ($key === 'items' || $key === 'additionalProperties') {
				$schema[ $key ] = self::compactJsonSchema($value);
				continue;
			}
			if (isset($value[0]) || array_keys($value) === range(0, count($value) - 1)) {
				// list (e.g. anyOf / oneOf)
				$schema[ $key ] = array_map(
					function ($item) {
						return is_array($item) ? self::compactJsonSchema($item) : $item;
					},
					$value
				);
			}
		}

		return $schema;
	}

	/**
	 * @param string $name
	 * @return array|null
	 */
	public function getToolMeta($name)
	{
		foreach ($this->getCatalog() as $tool) {
			if ($tool['name'] === $name) {
				return $tool;
			}
		}

		return null;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function has($name)
	{
		return (bool) $this->getToolMeta($name);
	}

	/**
	 * @param string     $name
	 * @param SLN_Plugin $plugin
	 * @return SLN_AI_Tools_Abstract|object|WP_Error
	 */
	public function instantiate($name, SLN_Plugin $plugin)
	{
		self::bootstrap();
		$meta = $this->getToolMeta($name);
		if (! $meta) {
			return new WP_Error('sln_ai_unknown_tool', __('Unknown AI tool.', 'salon-booking-system'));
		}
		$class = $meta['class'];
		if (! class_exists($class)) {
			return new WP_Error('sln_ai_missing_class', __('Tool class missing.', 'salon-booking-system'));
		}

		return new $class($plugin);
	}

	/**
	 * @param string     $name
	 * @param array      $arguments
	 * @param SLN_Plugin $plugin
	 * @return array|WP_Error
	 */
	public function buildPreview($name, array $arguments, SLN_Plugin $plugin)
	{
		$tool = $this->instantiate($name, $plugin);
		if (is_wp_error($tool)) {
			return $tool;
		}

		return $tool->preview($arguments);
	}

	/**
	 * @param string     $name
	 * @param array      $arguments
	 * @param SLN_Plugin $plugin
	 * @return array|WP_Error
	 */
	public function apply($name, array $arguments, SLN_Plugin $plugin)
	{
		$tool = $this->instantiate($name, $plugin);
		if (is_wp_error($tool)) {
			return $tool;
		}

		return $tool->apply($arguments);
	}

	/**
	 * @param string     $name
	 * @param mixed      $previous
	 * @param SLN_Plugin $plugin
	 * @return array|WP_Error
	 */
	public function restore($name, $previous, SLN_Plugin $plugin)
	{
		$tool = $this->instantiate($name, $plugin);
		if (is_wp_error($tool)) {
			return $tool;
		}

		return $tool->restore($previous);
	}

	/**
	 * System instructions listing active tools.
	 *
	 * @return string
	 */
	public function getSystemInstructions()
	{
		// Keep this compact: full tool schemas are sent separately; avoid duplicating the tool name list.
		return 'You help configure Salon Booking System via chat. Keep replies short. '
			. 'Always reply in the same language as the latest user message. '
			. 'Tool args: dates Y-m-d, times H:i, day keys 1=Sunday…7=Saturday, English enums/tool names. '
			. 'If a request is incomplete for a good answer, ask short clarifying questions first — do not invent missing dates, times, services, assistants, or customer details. '
			. 'Availability problems (“why / come mai / non prenotabile / not bookable / unavailable”): NEVER upsert_service or upsert_assistant — call explain_unavailable_slot with date, time, service, and assistant when present (it may return questions). '
			. 'If the user asks to explain a prior availability answer (“what does full mean?”, “spiega meglio”), clarify capacity/units-per-hour in their language using that context — do not switch to generic setup help. '
			. 'Call whitelisted tools; never invent payment/SMS/OAuth/Maps/reCAPTCHA/OneSignal App ID/Zapier/license secrets — explain_setting. '
			. 'If the requested ACTION has no tool (e.g. marking attendance/no-show, bulk status updates, refunds), say plainly it is not supported and point to the right admin screen — NEVER substitute a lookup tool like find_booking, which would return a misleading “no results”. '
			. 'Writable (confirm-first when bookability/money/notifications change): hours, holidays, identity, locale, booking rules, '
			. 'notification schedule, SMS toggles, PRO payment behaviour (no keys), PRO flags, GCal behaviour (if connected), pages, '
			. 'OneSignal enable, style/checkout copy, email templates, catalog CPTs. '
			. 'Hours: one interval by default. Holidays: prefer append; full_day true; dates Y-m-d. '
			. 'Unavailable slot → explain_unavailable_slot (use its summary; if it asks for details, relay those questions). '
			. 'Find booking → find_booking (includes draft/ERROR). Totals → count_bookings. '
			. 'Create → create_booking / create_bookings (max 10); collect the customer fields required by checkout settings (email by default) before calling — ask, never invent emails/phones. Edit → update_booking. '
			. 'List discounts/customers/services/assistants → find_*; create/update coupons → upsert_discount. '
			. 'Calendar UI → explain_setting topic=calendar. Changelog/what\'s new → recent_changelog context or topic=changelog; never invent features. '
			. 'When the merchant describes a goal a product feature/add-on solves (no-shows→waitlist, walk-in tablet→kiosk, second location→multishop, email campaigns→communicator, import bookings→migrator, “what can I do”→capabilities), call suggest_capability; for a known topic deep-link use explain_setting. '
			. 'Add-ons are guidance-only (never invent install/license steps that write secrets). '
			. SLN_AI_Edition::instructionsSnippet() . ' '
			. SLN_AI_Multishop::instructionsSnippet();
	}
}
