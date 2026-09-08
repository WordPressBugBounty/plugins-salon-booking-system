<?php

/**
 * Official Help Scout knowledge-base index for AI Setup (second source after live tools).
 *
 * Compact catalog ships in the plugin; full article text is fetched on demand
 * (top hit only) and cached. The model must not invent how-to steps when this
 * catalog can be searched via lookup_docs.
 */
class SLN_AI_DocsCatalog
{
	const KB_BASE     = 'https://salonbookingsystem.helpscoutdocs.com';
	const CACHE_PREFIX = 'sln_ai_docs_excerpt_';
	const CACHE_TTL   = 86400;
	const EXCERPT_MAX = 1100;

	/**
	 * Curated Help Scout articles (id, path, title, category, excerpt, keywords).
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function all()
	{
		$articles = array(
			array(
				'id'       => 'first_install',
				'path'     => '/article/4-first-install',
				'title'    => 'First install',
				'category' => 'settings',
				'excerpt'  => 'Install Salon Booking System, complete the setup wizard, and create the booking / thank-you / my-account pages before taking live reservations.',
				'keywords' => 'first install prima installazione setup wizard onboarding installazione iniziale',
			),
			array(
				'id'       => 'sms',
				'path'     => '/article/50-sms-services',
				'title'    => 'SMS services',
				'category' => 'settings',
				'excerpt'  => 'Enable SMS reminders by connecting a supported provider (Twilio, Plivo, …) under Settings → General → SMS. Provider passwords stay in Settings — AI never writes them.',
				'keywords' => 'sms twilio plivo whatsapp reminders promemoria sms servizi sms',
			),
			array(
				'id'       => 'customers_per_session',
				'path'     => '/article/60-customers-per-session-and-average-session-duration',
				'title'    => 'Customers per session and average session duration',
				'category' => 'settings',
				'excerpt'  => 'Customers per session is salon-level overlapping capacity. Average session duration / interval controls which start times appear. Service “units per session” and assistant “multiple customers” also affect whether a slot is full.',
				'keywords' => 'customers per session average session duration interval units per session capacita clienti per sessione',
			),
			array(
				'id'       => 'timezone',
				'path'     => '/article/74-correct-wordpress-timezone',
				'title'    => 'Correct WordPress timezone',
				'category' => 'troubleshoot',
				'excerpt'  => 'Set the real city timezone in Settings → General (WordPress), not a UTC offset. A wrong timezone shifts every slot, reminder, and calendar display.',
				'keywords' => 'wordpress timezone fuso orario utc offset timezone sbagliato orario sbagliato',
			),
			array(
				'id'       => 'payments',
				'path'     => '/article/75-online-payments',
				'title'    => 'Online payments',
				'category' => 'settings',
				'excerpt'  => 'PRO online payments (Stripe, PayPal, and other gateways) are enabled under Settings → Payments. Paste gateway keys there; AI can toggle behaviour but never stores secrets.',
				'keywords' => 'online payments stripe paypal pagamenti online deposit acconto gateway',
			),
			array(
				'id'       => 'style',
				'path'     => '/article/76-style-of-the-booking-form',
				'title'    => 'Style of the booking form',
				'category' => 'settings',
				'excerpt'  => 'Choose a booking-form layout and colors under Settings → Style. Custom CSS and “Ajax steps” also live on that tab.',
				'keywords' => 'style booking form colors css ajax steps stile form colori',
			),
			array(
				'id'       => 'google_calendar',
				'path'     => '/article/77-google-calendar-synchronization',
				'title'    => 'Google Calendar synchronization',
				'category' => 'settings',
				'excerpt'  => 'Create a Google Cloud OAuth client, paste Client ID/secret in Settings → Google Calendar, then connect. One-way sync publishes salon bookings to Google.',
				'keywords' => 'google calendar gcal oauth calendar sync sincronizzazione google',
			),
			array(
				'id'       => 'gcal_two_way',
				'path'     => '/article/127-google-calendar-two-ways-synch',
				'title'    => 'Google Calendar two-way sync',
				'category' => 'settings',
				'excerpt'  => 'Two-way sync can also lock busy Google events so those slots are not bookable (PRO). Connect OAuth first, then enable the two-way / lock-busy option.',
				'keywords' => 'google calendar two way two-ways synch bidirezionale lock busy slots blocca occupati',
			),
			array(
				'id'       => 'gcal_assistants',
				'path'     => '/article/97-notify-reservations-on-assistants-google-calendar',
				'title'    => 'Notify reservations on assistants Google Calendar',
				'category' => 'settings',
				'excerpt'  => 'Each assistant can receive bookings on their own Google Calendar after salon-level OAuth is connected and the assistant calendar is linked.',
				'keywords' => 'assistant google calendar staff calendar notify reservations calendario assistente',
			),
			array(
				'id'       => 'services',
				'path'     => '/article/89-services-settings-and-options',
				'title'    => 'Services settings and options',
				'category' => 'settings',
				'excerpt'  => 'Service price, duration, units per session, assigned assistants, secondary services, and custom hours are edited on each service in Salon → Services.',
				'keywords' => 'services settings options durata prezzo units per session servizi opzioni',
			),
			array(
				'id'       => 'texts',
				'path'     => '/article/106-change-default-texts-strings',
				'title'    => 'Change default text strings',
				'category' => 'settings',
				'excerpt'  => 'Replace booking-form labels and messages from Settings (default texts) or with a translation plugin. Do not edit plugin PHP files.',
				'keywords' => 'change default texts strings labels traduci etichette testi form',
			),
			array(
				'id'       => 'calendar',
				'path'     => '/article/80-back-end-booking-calendar',
				'title'    => 'Back-end booking calendar',
				'category' => 'calendar',
				'excerpt'  => 'Salon → Calendar is the staff agenda (day/week/month). Create, edit, and review reservations there; the floating AI assistant is also available on that page.',
				'keywords' => 'back-end booking calendar admin calendar calendario backend',
			),
			array(
				'id'       => 'calendar_create',
				'path'     => '/article/82-create-a-new-reservation-from-back-end-calendar',
				'title'    => 'Create a reservation from the back-end calendar',
				'category' => 'calendar',
				'excerpt'  => 'Click an empty slot (or use Add) on Salon → Calendar, choose service/assistant/customer, and save. AI Setup can also create a booking from chat with a preview.',
				'keywords' => 'create reservation back-end calendar nuova prenotazione calendario',
			),
			array(
				'id'       => 'calendar_edit',
				'path'     => '/article/83-edit-an-existing-reservation',
				'title'    => 'Edit an existing reservation',
				'category' => 'calendar',
				'excerpt'  => 'Open a booking from the calendar or Bookings list to change time, service, assistant, or status. AI can update a booking after you confirm the preview.',
				'keywords' => 'edit existing reservation modifica prenotazione calendario',
			),
			array(
				'id'       => 'calendar_alerts',
				'path'     => '/article/84-how-to-deal-with-reservations-alerts',
				'title'    => 'How to deal with reservation alerts',
				'category' => 'calendar',
				'excerpt'  => 'Calendar alerts flag conflicts, pending payments, or bookings that need attention. Open the booking to resolve the highlighted issue.',
				'keywords' => 'reservation alerts warning conflict avvisi prenotazioni',
			),
			array(
				'id'       => 'calendar_daily',
				'path'     => '/article/153-search-and-manage-a-reservation-from-daily-view',
				'title'    => 'Search and manage a reservation from daily view',
				'category' => 'calendar',
				'excerpt'  => 'Daily view lists the day’s bookings; search and quick actions let you find and update a reservation without leaving the calendar.',
				'keywords' => 'daily view search manage reservation vista giornaliera cerca prenotazione',
			),
			array(
				'id'       => 'calendar_weekly',
				'path'     => '/article/191-back-end-calendar-weekly-view',
				'title'    => 'Back-end calendar weekly view',
				'category' => 'calendar',
				'excerpt'  => 'Weekly view shows assistants/columns across the week so you can spot gaps and move bookings between days.',
				'keywords' => 'weekly view calendar vista settimanale',
			),
			array(
				'id'       => 'calendar_assistant',
				'path'     => '/article/88-back-end-booking-calendar-assistant-view',
				'title'    => 'Back-end calendar assistant view',
				'category' => 'calendar',
				'excerpt'  => 'Assistant / daily assistant view columns the day by staff member so you can see who is free and who is booked.',
				'keywords' => 'assistant view daily view staff columns vista assistente',
			),
			array(
				'id'       => 'block_slot',
				'path'     => '/article/87-blocking-a-time-slot',
				'title'    => 'Blocking a time slot',
				'category' => 'calendar',
				'excerpt'  => 'Block a slot from the calendar when a chair is unavailable (break, private appointment). Blocked time is not offered on the front-end form.',
				'keywords' => 'blocking a time slot bloccare slot break unavailable block time',
			),
			array(
				'id'       => 'csv_export',
				'path'     => '/article/86-export-reservation-into-a-csv-file',
				'title'    => 'Export reservations to CSV',
				'category' => 'calendar',
				'excerpt'  => 'Export bookings to a CSV from the Bookings / calendar tools when you need a spreadsheet for accounting or another system.',
				'keywords' => 'export reservation csv file esporta prenotazioni csv excel',
			),
			array(
				'id'       => 'my_account',
				'path'     => '/article/131-booking-my-account',
				'title'    => 'Booking my-account',
				'category' => 'frontend',
				'excerpt'  => 'Customers manage upcoming bookings from the My Account page created during setup. Assign that page in Settings if the link is missing.',
				'keywords' => 'booking my account area clienti customer account pagina account',
			),
			array(
				'id'       => 'repeat_booking',
				'path'     => '/article/151-repeat-booking',
				'title'    => 'Repeat booking',
				'category' => 'frontend',
				'excerpt'  => 'Customers (and staff) can repeat a past booking to book the same service again. Cloning from the back-end calendar is the staff equivalent.',
				'keywords' => 'repeat booking clone recurring prenotazione ricorrente ripeti',
			),
			array(
				'id'       => 'reschedule',
				'path'     => '/article/152-reschedule-an-upcoming-reservation',
				'title'    => 'Reschedule an upcoming reservation',
				'category' => 'frontend',
				'excerpt'  => 'Customers can reschedule an upcoming booking from My Account when cancellation/reschedule rules allow it. Staff can also edit the booking in the calendar.',
				'keywords' => 'reschedule upcoming reservation spostare prenotazione riprogrammare',
			),
			array(
				'id'       => 'password',
				'path'     => '/article/157-how-does-salon-customers-can-recover-their-password',
				'title'    => 'How customers recover their password',
				'category' => 'troubleshoot',
				'excerpt'  => 'Customers use the standard WordPress lost-password flow on the My Account / login screen. Check that emails are sending if the reset mail never arrives.',
				'keywords' => 'recover password lost password recupera password clienti reset password',
			),
			array(
				'id'       => 'shortcode_services',
				'path'     => '/article/133-services-shortcode',
				'title'    => 'Services shortcode',
				'category' => 'frontend',
				'excerpt'  => 'Use the services shortcode to list services on any page. Booking still goes through the booking form / booking page.',
				'keywords' => 'services shortcode elenco servizi shortcode',
			),
			array(
				'id'       => 'shortcode_assistants',
				'path'     => '/article/135-assistants-shortcode',
				'title'    => 'Assistants shortcode',
				'category' => 'frontend',
				'excerpt'  => 'Use the assistants shortcode to list staff on a page. It does not replace the booking form.',
				'keywords' => 'assistants shortcode staff shortcode elenco assistenti',
			),
			array(
				'id'       => 'shortcode_calendar',
				'path'     => '/article/138-bookings-calendar-shortcode',
				'title'    => 'Bookings calendar shortcode',
				'category' => 'frontend',
				'excerpt'  => 'A public/staff calendar shortcode can display bookings on a page. Respect privacy — do not expose customer PII on a public page.',
				'keywords' => 'bookings calendar shortcode calendario shortcode',
			),
			array(
				'id'       => 'feedback',
				'path'     => '/article/136-customers-feedback-submission',
				'title'    => 'Customer feedback submission',
				'category' => 'frontend',
				'excerpt'  => 'Follow-up messages can invite customers to leave a review (Facebook / Google Business) after a visit.',
				'keywords' => 'customers feedback submission review recensioni feedback',
			),
			array(
				'id'       => 'feedback_shortcode',
				'path'     => '/article/177-customers-feedback-short-code',
				'title'    => 'Customer feedback shortcode',
				'category' => 'frontend',
				'excerpt'  => 'Display collected customer feedback with the feedback shortcode on any page.',
				'keywords' => 'customers feedback shortcode recensioni shortcode',
			),
			array(
				'id'       => 'custom_email',
				'path'     => '/article/176-custom-email-notification-template',
				'title'    => 'Custom email notification template',
				'category' => 'customizations',
				'excerpt'  => 'Override notification emails with a custom template (Customization add-on / email template settings). Keep merge tags intact so booking data still renders.',
				'keywords' => 'custom email notification template email personalizzata template mail',
			),
			array(
				'id'       => 'mobile_app',
				'path'     => '/article/125-mobile-app',
				'title'    => 'Mobile app (staff PWA)',
				'category' => 'extensions',
				'excerpt'  => 'The staff mobile web app (PWA) lets workers see the calendar, customers, and quick booking actions on a phone. Open it from the salon site while logged in as staff.',
				'keywords' => 'mobile app pwa staff app app assistenti web app',
			),
			array(
				'id'       => 'mobile_app_troubleshoot',
				'path'     => '/article/166-mobile-app-troubleshooting',
				'title'    => 'Mobile app troubleshooting',
				'category' => 'troubleshoot',
				'excerpt'  => 'If the staff PWA will not install or refresh, check HTTPS, login as a worker, update the plugin, and see the mobile-app troubleshooting steps.',
				'keywords' => 'mobile app troubleshooting pwa non funziona app staff problemi',
			),
			array(
				'id'       => 'zapier',
				'path'     => '/article/180-zapier-integration',
				'title'    => 'Zapier integration',
				'category' => 'extensions',
				'excerpt'  => 'Connect Zapier from Settings → General using the plugin API keys. AI never writes those keys — paste them in Settings.',
				'keywords' => 'zapier integration zaps api keys automazione',
			),
			array(
				'id'       => 'debug',
				'path'     => '/article/94-how-to-debug-issues',
				'title'    => 'How to debug issues',
				'category' => 'troubleshoot',
				'excerpt'  => 'Before contacting support: update the plugin, enable WP_DEBUG log, disable Ajax steps, then test for plugin/theme conflicts (other plugins off, default theme).',
				'keywords' => 'how to debug issues debug wordpress conflict theme plugin errori php log',
			),
			array(
				'id'       => 'upgrade_pro',
				'path'     => '/article/137-how-to-upgrate-to-the-pro-version',
				'title'    => 'How to upgrade to the PRO version',
				'category' => 'troubleshoot',
				'excerpt'  => 'Install PRO over Free (or from your account download). Settings and bookings are kept. Activate the license under Salon → Extensions.',
				'keywords' => 'upgrade to pro version passare a pro aggiornare pro licenza',
			),
			array(
				'id'       => 'email_problems',
				'path'     => '/article/126-email-notifications-problems',
				'title'    => 'Email notification problems',
				'category' => 'troubleshoot',
				'excerpt'  => 'If booking emails never arrive, test wp_mail (SMTP plugin), check spam, and confirm notification toggles. Theme/plugin conflicts can also swallow mail.',
				'keywords' => 'email notifications problems email non arrivano smtp mail not received',
			),
			array(
				'id'       => 'translate',
				'path'     => '/article/160-translate-missing-translation-text-stings',
				'title'    => 'Translate missing strings',
				'category' => 'troubleshoot',
				'excerpt'  => 'Use Loco Translate / WPML / Polylang on the salon-booking-system text domain. Do not edit plugin files. Some strings are settings “default texts”.',
				'keywords' => 'translate missing translation text strings loco wpml polylang stringhe non tradotte',
			),
			array(
				'id'       => 'speed',
				'path'     => '/article/145-salon-booking-system-speed-optimization',
				'title'    => 'Speed optimization',
				'category' => 'troubleshoot',
				'excerpt'  => 'Exclude booking pages from full-page cache, keep PHP updated, and avoid aggressive JS minify on booking assets if the form breaks after a cache plugin.',
				'keywords' => 'speed optimization cache performance ottimizzazione velocita lenti',
			),
			array(
				'id'       => 'support',
				'path'     => '/article/154-get-support',
				'title'    => 'Get support',
				'category' => 'troubleshoot',
				'excerpt'  => 'PRO: email support@salonbookingsystem.com after the debug checklist. Free: WordPress.org forum. Include plugin version, PHP version, and steps tried.',
				'keywords' => 'get support contatto supporto ticket help',
			),
			array(
				'id'       => 'update',
				'path'     => '/article/155-how-to-keep-salon-booking-system-updated',
				'title'    => 'How to keep Salon Booking System updated',
				'category' => 'troubleshoot',
				'excerpt'  => 'Free updates from wp-admin → Plugins. PRO from the site account or CodeCanyon. Always keep a backup; bookings are stored in WordPress and survive an update.',
				'keywords' => 'keep updated update plugin aggiornare plugin ultima versione',
			),
		);

		foreach ($articles as &$row) {
			$row['url']  = self::KB_BASE . $row['path'];
			$row['kind'] = 'docs';
		}
		unset($row);

		return apply_filters('sln_ai_docs_catalog', $articles);
	}

	/**
	 * @param string $id
	 * @return array<string,string>|null
	 */
	public static function findById($id)
	{
		$id = sanitize_key((string) $id);
		foreach (self::all() as $row) {
			if ($row['id'] === $id) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * @param string $query
	 * @param int    $limit
	 * @return array<int,array<string,mixed>>
	 */
	public static function search($query, $limit = 3)
	{
		$query = trim((string) $query);
		$limit = max(1, min(8, (int) $limit));
		$all   = self::all();

		if ($query === '' || $query === 'docs' || $query === 'documentation') {
			$browse = array();
			foreach ($all as $row) {
				if (in_array($row['id'], array('first_install', 'debug', 'google_calendar', 'payments', 'calendar'), true)) {
					$browse[] = $row;
				}
			}

			return array_slice($browse ? $browse : $all, 0, $limit);
		}

		$byId = self::findById($query);
		if ($byId) {
			return array($byId);
		}

		$needle = self::normalize($query);
		$tokens = preg_split('/\s+/', $needle);
		$tokens = array_values(
			array_filter(
				$tokens,
				function ($t) {
					return strlen($t) >= 3;
				}
			)
		);
		if (! $tokens) {
			$tokens = array($needle);
		}

		$scored = array();
		foreach ($all as $row) {
			$hay = self::normalize(
				$row['id'] . ' ' . $row['title'] . ' ' . $row['excerpt'] . ' ' . $row['keywords'] . ' ' . $row['category']
			);
			$score = 0;
			if ($hay === $needle || $row['id'] === sanitize_key($query)) {
				$score += 100;
			}
			if (strpos($hay, $needle) !== false) {
				$score += 40;
			}
			foreach ($tokens as $tok) {
				if (strpos($hay, $tok) !== false) {
					$score += 10;
				}
			}
			if ($score > 0) {
				$row['_score'] = $score;
				$scored[]      = $row;
			}
		}

		usort(
			$scored,
			function ($a, $b) {
				return $b['_score'] - $a['_score'];
			}
		);

		$scored = array_slice($scored, 0, $limit);
		foreach ($scored as &$row) {
			unset($row['_score']);
		}

		return $scored;
	}

	/**
	 * Attach a body excerpt (live fetch + cache, else curated).
	 *
	 * @param array $row
	 * @param bool  $fetchLive
	 * @return array
	 */
	public static function withExcerpt(array $row, $fetchLive = true)
	{
		$curated = isset($row['excerpt']) ? (string) $row['excerpt'] : '';
		$row['excerpt_source'] = 'catalog';
		if (! $fetchLive) {
			return $row;
		}

		$live = self::cachedExcerpt($row);
		if ($live !== '') {
			$row['excerpt']        = $live;
			$row['excerpt_source'] = 'article';
		} elseif ($curated !== '') {
			$row['excerpt'] = $curated;
		}

		return $row;
	}

	/**
	 * Intent → catalog query for mock/LLM routing. Null when this is not a docs question.
	 *
	 * Does not steal availability diagnosis, writes, or “where do I configure X?” admin deep-links.
	 *
	 * @param string $text
	 * @return string|null
	 */
	public static function detectIntentQuery($text)
	{
		$folded = self::normalize($text);

		if (preg_match(
			'/\b(documentation|documentazione|documentacion|official docs|docs ufficiali|knowledge base|helpscout|guida ufficiale|video tutorial)\b/',
			$folded
		)) {
			$rest = trim((string) preg_replace(
				'/\b(documentation|documentazione|documentacion|official docs|docs ufficiali|knowledge base|helpscout|guida ufficiale|video tutorial|official|ufficiale|oficial|the|la|il|i|gli|le|dei|delle|del)\b/',
				' ',
				$folded
			));
			$rest = trim((string) preg_replace('/\s+/', ' ', $rest));

			return $rest !== '' ? $rest : 'docs';
		}

		$map = array(
			'debug'                 => '/\b(how to debug|debug(gare| issues)?|fare debug|abilitare (il )?debug|wp.debug|error[ei] php)\b/',
			'timezone'              => '/\b(wordpress timezone|fuso orario( di)? wordpress|correct timezone|timezone (sbagliat|wrong|incorrect))\b/',
			'translate'             => '/\b(translate missing|stringhe (non )?tradott|missing translation|loco translate|testi non tradotti)\b/',
			'csv_export'            => '/\b(export\w*.{0,24}csv|esporta\w*.{0,24}csv|csv (file|export)|esportare le prenotaz)\b/',
			'block_slot'            => '/\b(block(ing)? (a )?time slot|bloccare (uno )?slot|slot bloccato)\b/',
			'password'              => '/\b(recover\w* (their |the |la )?password|recupera(re)? (la )?password|lost password|password dimenticata)\b/',
			'email_problems'        => '/\b(email notification problems|email (non|not|nunca) (arriv|coming|received|llegan)|email non arrivano|mail non arriv)\b/',
			'first_install'         => '/\b(first install|prima installazione|installazione iniziale)\b/',
			'speed'                 => '/\b(speed optimization|ottimizz\w*.{0,16}(velocita|performance)|plugin lento|booking form slow)\b/',
			'update'                => '/\b(keep.{0,20}updated|come (si )?aggiorna(re)? (il )?plugin|aggiornare salon booking)\b/',
			'custom_email'          => '/\b(custom email (notification )?template|template email personalizz)\b/',
			'gcal_two_way'          => '/\b(two[- ]ways? synch?|sincronizz\w* bidirezional|lock busy (slots?|events?))\b/',
			'shortcode_services'    => '/\b(services? shortcode|shortcode (dei )?servizi)\b/',
			'shortcode_assistants'  => '/\b(assistants? shortcode|shortcode (degli )?assistenti)\b/',
			'shortcode_calendar'    => '/\b(calendar shortcode|shortcode (del )?calendario)\b/',
			'repeat_booking'        => '/\b(repeat booking|ripeti(re)? (la )?prenotazione|prenotazione ricorrente)\b/',
			'reschedule'            => '/\b(reschedule (an )?upcoming|spostare (una )?prenotazione|riprogrammare (una )?prenotazione)\b/',
			'mobile_app_troubleshoot' => '/\b(mobile app troubleshooting|pwa non (si )?(install|aggiorn|funzion)|app staff non funziona)\b/',
			'support'               => '/\b(get support|come (si )?contatta(re)? il supporto|aprire un ticket)\b/',
			'upgrade_pro'           => '/\b(how to upgrade to (the )?pro|come (si )?passa(re)? a pro|aggiornare alla versione pro)\b/',
			'customers_per_session' => '/\b(customers per session|clienti per (ogni )?sessione|average session duration)\b/',
		);

		foreach ($map as $query => $pattern) {
			if (preg_match($pattern, $folded)) {
				return $query;
			}
		}

		return null;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private static function normalize($text)
	{
		$folded = strtolower(SLN_AI_Language::fold((string) $text));

		return (string) preg_replace('/[`\'"^~]/', '', $folded);
	}

	/**
	 * @param array $row
	 * @return string
	 */
	private static function cachedExcerpt(array $row)
	{
		$url = isset($row['url']) ? (string) $row['url'] : '';
		if ($url === '' || strpos($url, self::KB_BASE . '/') !== 0) {
			return '';
		}

		$allow = true;
		if (function_exists('apply_filters')) {
			$allow = (bool) apply_filters('sln_ai_docs_fetch_excerpt', true, $row);
		}
		if (! $allow) {
			return '';
		}

		$id        = isset($row['id']) ? sanitize_key($row['id']) : md5($url);
		$cacheKey  = self::CACHE_PREFIX . $id;
		$cached    = function_exists('get_transient') ? get_transient($cacheKey) : false;
		if (is_string($cached) && $cached !== '') {
			return $cached;
		}

		$live = self::fetchLiveExcerpt($url);
		if ($live !== '' && function_exists('set_transient')) {
			set_transient($cacheKey, $live, self::CACHE_TTL);
		}

		return $live;
	}

	/**
	 * Public Help Scout article page — no API key. Fail closed on any error.
	 *
	 * @param string $url
	 * @return string
	 */
	private static function fetchLiveExcerpt($url)
	{
		if (! function_exists('wp_remote_get')) {
			return '';
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'redirection'=> 3,
				'user-agent' => 'SalonBookingSystem-AISetup/1.0',
			)
		);
		if (is_wp_error($response)) {
			return '';
		}
		$code = (int) wp_remote_retrieve_response_code($response);
		$html = (string) wp_remote_retrieve_body($response);
		if ($code < 200 || $code >= 300 || $html === '') {
			return '';
		}

		$body = '';
		if (preg_match('/<article[^>]*id="fullArticle"[^>]*>(.*)<\/article>/is', $html, $m)) {
			$body = $m[1];
		} elseif (preg_match('/<article[^>]*>(.*)<\/article>/is', $html, $m)) {
			$body = $m[1];
		}
		if ($body === '') {
			return '';
		}

		$body = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $body);
		$body = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $body);
		$text = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($body) : trim(strip_tags($body));
		$text = preg_replace('/\s+/', ' ', (string) $text);
		$text = trim((string) $text);
		$text = preg_replace('/\s*Did this answer your question\?.*$/i', '', $text);
		$text = trim((string) $text);
		if ($text === '') {
			return '';
		}

		if (function_exists('mb_strlen') && mb_strlen($text) > self::EXCERPT_MAX) {
			return rtrim(mb_substr($text, 0, self::EXCERPT_MAX - 1)) . '…';
		}
		if (strlen($text) > self::EXCERPT_MAX) {
			return rtrim(substr($text, 0, self::EXCERPT_MAX - 1)) . '…';
		}

		return $text;
	}
}
