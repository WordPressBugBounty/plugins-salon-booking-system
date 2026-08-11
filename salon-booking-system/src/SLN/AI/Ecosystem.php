<?php

/**
 * Curated Salon Booking System product/ecosystem knowledge for AI Setup prompts.
 *
 * Sourced from the product site (https://www.salonbookingsystem.com/) and in-plugin
 * Free/PRO reality — keep short; do not scrape or dump marketing pages.
 */
class SLN_AI_Ecosystem
{
	const SITE_URL     = 'https://www.salonbookingsystem.com/';
	const DOCS_URL     = 'https://www.salonbookingsystem.com/docs/';
	const PRICING_URL  = 'https://www.salonbookingsystem.com/pricing/';
	const ADDONS_URL   = 'https://www.salonbookingsystem.com/salon-booking-system-add-ons/';
	const SUPPORT_URL  = 'https://www.salonbookingsystem.com/get-in-touch/';

	/**
	 * Compact always-on product map for the LLM system prompt.
	 *
	 * @return string
	 */
	public static function instructionsSnippet()
	{
		$addons = array();
		foreach (self::officialAddons() as $row) {
			$addons[] = $row['name'] . ' — ' . $row['blurb'];
		}

		return 'Product: Salon Booking System — WordPress appointment scheduling for salons, spas, '
			. 'barbers, therapists, trainers, clinics (site: ' . self::SITE_URL . '). '
			. 'Core capabilities: online booking form, staff calendar / mobile web app for workers, '
			. 'services & assistants, reminders (email/SMS/WhatsApp where configured), discounts/coupons, '
			. 'payments (PRO), resources (PRO), REST API, multi-language. '
			. 'Editions: Free (core booking) vs PRO (payments, resources, nested bookings, fidelity score, '
			. 'customer timezone slots, advanced GCal lock, etc.). Upgrade path: Salon → Extensions; '
			. 'pricing overview: ' . self::PRICING_URL . '. '
			. 'Official add-ons (separate products; configure in their own settings, not as secrets in AI Setup): '
			. implode('; ', $addons) . '. '
			. 'Docs: ' . self::DOCS_URL . '. Support: ' . self::SUPPORT_URL . '. '
			. 'When the merchant asks what the product can do, or describes a problem a feature/add-on solves, '
			. 'call suggest_capability (or explain_setting for a known topic); deep-link to Settings or Extensions; '
			. 'never invent payment gateway keys or license steps that write secrets.';
	}

	/**
	 * Structured ecosystem facts for ContextPack (machine-readable).
	 *
	 * @return array
	 */
	public static function contextFields()
	{
		return array(
			'product_name'    => 'Salon Booking System',
			'product_site'    => self::SITE_URL,
			'docs_url'        => self::DOCS_URL,
			'pricing_url'     => self::PRICING_URL,
			'addons_url'      => self::ADDONS_URL,
			'support_url'     => self::SUPPORT_URL,
			'audiences'       => array(
				'Hairdressers & barbers',
				'Beauticians',
				'Therapists',
				'Personal trainers',
				'Doctors / clinics',
				'SPA',
			),
			'core_modules'    => array(
				'Online booking form (responsive)',
				'Staff mobile web app (calendar, customers, quick booking actions)',
				'Services, assistants, discounts',
				'Reminders & notifications (email / SMS / WhatsApp when gateways configured)',
				'REST API (bookings, services, assistants, availability, holidays, shops)',
				'Integrations ecosystem (payments, SMS, calendar, etc.)',
			),
			'official_addons' => self::officialAddons(),
		);
	}

	/**
	 * First-party add-ons highlighted on the product site.
	 *
	 * @return array<int,array{id:string,name:string,blurb:string,keywords:string,topic:string,url?:string}>
	 */
	public static function officialAddons()
	{
		$ext = SLN_AI_Edition::extensionsUrl();

		return array(
			array(
				'id'       => 'multishop',
				'name'     => 'Multi-Shops',
				'blurb'    => 'multiple locations on one site',
				'keywords' => 'multi shop multishop multiple locations sedi seconda sede shops',
				'topic'    => 'multishop',
				'url'      => class_exists('\SalonMultishop\Addon')
					? admin_url('edit.php?post_type=sln_shop')
					: $ext,
			),
			array(
				'id'       => 'waitlist',
				'name'     => 'Smart Waitlist',
				'blurb'    => 'recover cancelled/empty slots',
				'keywords' => 'waitlist lista attesa no-show cancellations empty slots recover last minute',
				'topic'    => 'waitlist',
				'url'      => $ext,
			),
			array(
				'id'       => 'kiosk',
				'name'     => 'Walk-In Totem / Kiosk',
				'blurb'    => 'in-salon walk-in booking',
				'keywords' => 'kiosk totem walk-in walkin reception tablet self service',
				'topic'    => 'kiosk',
				'url'      => $ext,
			),
			array(
				'id'       => 'communicator',
				'name'     => 'Communicator',
				'blurb'    => 'AI-powered email marketing to customers',
				'keywords' => 'communicator email marketing newsletter campaign campagne',
				'topic'    => 'communicator',
				'url'      => $ext,
			),
			array(
				'id'       => 'woo_checkout',
				'name'     => 'WooCommerce Checkout',
				'blurb'    => 'process booking payments via WooCommerce',
				'keywords' => 'woocommerce woo checkout cart',
				'topic'    => 'woo_checkout',
				'url'      => $ext,
			),
			array(
				'id'       => 'migrator',
				'name'     => 'Migrator',
				'blurb'    => 'import from other booking platforms',
				'keywords' => 'migrator migrate import switch from other booking platform',
				'topic'    => 'migrator',
				'url'      => $ext,
			),
		);
	}
}
