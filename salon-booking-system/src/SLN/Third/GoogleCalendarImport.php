<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Third_GoogleCalendarImport
{
    const SUCCESS_COLOR_ID = '10';
    const WARNING_COLOR_ID = '6';
    const ERROR_COLOR_ID = '11';

    const EXCEPTION_CODE_FOR_EMPTY_CALENDAR_EVENT       = 1001;
    const EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT     = 1002;
    const EXCEPTION_CODE_FOR_CONFLICTING_CALENDAR_EVENT = 1003;

    const EVENT_DELETED_STATUS = 'cancelled';

    private static $googleClientCalendarSyncToken = 'salon_google_client_calendar_sync_token';

    private static $instance;
    /** @var SLN_GoogleScope */
    private $gScope;
    protected $timezone;

    public static function launch($gScope)
    {
        if (empty(self::$instance)) {
            self::$instance = new self($gScope);
        }
    }

    /**
     * SLN_Third_GoogleCalendarImport constructor.
     * @param $gScope
     */
    private function __construct($gScope)
    {
        $this->gScope = $gScope;

        if (defined('DOING_CRON') && isset($_GET['action']) && $_GET['action'] === 'sln_sync_from_google_calendar') {
            add_action('wp_loaded', array($this, 'syncFull'));
            add_action('wp_loaded', array($this, 'syncFullShops'));
            add_filter('user_has_cap', array($this, 'userHasCapCallback'), 10, 4);
        }
    }

    public function userHasCapCallback($allcaps, $caps, $args, $user)
    {
        if (in_array('edit_others_sln_bookings', $caps)) {
            $allcaps['edit_others_sln_bookings'] = true;
        }

        return $allcaps;
    }

    public function syncFull()
    {
        $gScope = $this->gScope;

        if ( ! $gScope->is_connected() ) {
            return;
        }

        $syncToken = self::getSyncToken();
        $this->printMsg(sprintf("Current token '%s'", $syncToken));

        $showDeletedParams = array(
           'showDeleted' => true,
        );

        $params = $showDeletedParams;

	    $now	 = new SLN_DateTime();
	    $timeMin = $now->sub(new DateInterval('P2M'))->format(DateTime::RFC3339);

	    $showDeletedParams['timeMin'] = $timeMin;

        if (!empty($syncToken)) {
            $params['syncToken'] = $syncToken;
        } else {
	        $params['timeMin'] = $timeMin;
	    }

        $nextPageToken = null;

        try {
            $timezone = $gScope->get_google_service()->settings->get('timezone')->value;
            $this->timezone = new DateTimeZone($timezone);
        }catch (\Exception $e) {
            $this->timezone = SLN_TimeFunc::getWpTimezone();
        }

        do {

            if ($nextPageToken) {
                $params['pageToken'] = $nextPageToken;
            }

            try {
                $gCalendarEvents = $gScope->get_google_service()->events->listEvents(
                    $gScope->google_client_calendar,
                    $params
                );
            } catch (Google_Service_Exception $e) {
		        $params = $showDeletedParams;
                $gCalendarEvents = $gScope->get_google_service()->events->listEvents($gScope->google_client_calendar, $params);
            }

            $gEvents = $gCalendarEvents->getItems();

            $this->printMsg(sprintf("Need to process %s events", count($gEvents)));

            $this->importBookingsFromGoogleCalendarEvents($gEvents);

            $nextPageToken = $gCalendarEvents->getNextPageToken();

            $this->printMsg(sprintf("Next page token '%s'", $nextPageToken));

        } while ($nextPageToken);

        $nextSyncToken = $gCalendarEvents->getNextSyncToken();

        $this->printMsg(sprintf("Next sync token '%s'", $syncToken));

        self::updateSyncToken($nextSyncToken);
    }
    public function syncFullShops()
    {
        global $wpdb;
        $meta_key = '_sln_shop_google_client_calendar';

        $shops = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $meta_key
            ),
            ARRAY_A
        );
        foreach ($shops as $shop){
        $gScope = $this->gScope;
        $gScope->google_client_calendar = $shop['meta_value'];
        if ( ! $gScope->is_connected() ) {
            return;
        }

        $syncToken = self::getSyncToken();
        $this->printMsg(sprintf("Current token '%s'", $syncToken));

        $showDeletedParams = array(
           'showDeleted' => true,
        );

        $params = $showDeletedParams;

	    $now	 = new SLN_DateTime();
	    $timeMin = $now->sub(new DateInterval('P2M'))->format(DateTime::RFC3339);

	    $showDeletedParams['timeMin'] = $timeMin;

        if (!empty($syncToken)) {
            //$params['syncToken'] = $syncToken;
        } else {
	        $params['timeMin'] = $timeMin;
	    }

        $nextPageToken = null;

        try {
            $timezone = $gScope->get_google_service()->settings->get('timezone')->value;
            $this->timezone = new DateTimeZone($timezone);
        }catch (\Exception $e) {
            $this->timezone = SLN_TimeFunc::getWpTimezone();
        }

        do {

            if ($nextPageToken) {
                $params['pageToken'] = $nextPageToken;
            }

            try {
                $gCalendarEvents = $gScope->get_google_service()->events->listEvents(
                    $gScope->google_client_calendar,
                    $params
                );
            } catch (Google_Service_Exception $e) {
		        $params = $showDeletedParams;
                $gCalendarEvents = $gScope->get_google_service()->events->listEvents($gScope->google_client_calendar, $params);
            }

            $gEvents = $gCalendarEvents->getItems();

            $this->printMsg(sprintf("Need to process %s events", count($gEvents)));

            $this->importBookingsFromGoogleCalendarEvents($gEvents, $shop['post_id']);

            $nextPageToken = $gCalendarEvents->getNextPageToken();

            $this->printMsg(sprintf("Next page token '%s'", $nextPageToken));

        } while ($nextPageToken);

        $nextSyncToken = $gCalendarEvents->getNextSyncToken();

        $this->printMsg(sprintf("Next sync token '%s'", $syncToken));

        self::updateSyncToken($nextSyncToken);
        }

    }

    private function importBookingsFromGoogleCalendarEvents($gEvents, $shop_id = false)
    {
        if (empty($gEvents)) {
            return;
        }

        foreach ($gEvents as $gEvent) {
            $this->eventError = '';
            $this->printMsg(str_repeat('*', 100));
            $this->importBookingFromGoogleCalendarEvent($gEvent, $shop_id);
            $this->printMsg(str_repeat('*', 100));
        }
    }

    /**
     * @param Google_Service_Calendar_Event $gEvent
     */
    private function importBookingFromGoogleCalendarEvent($gEvent, $shop_id = false)
    {
        $this->printMsg(sprintf("Start processing event '%s' with title '%s'", $gEvent->getId(), $gEvent->getSummary()));

        $bookingId = $this->getBookingIdFromEventId($gEvent->getId());

        if (!$bookingId && ( $gEvent->getStatus() === self::EVENT_DELETED_STATUS || preg_match('/(http)/im', $gEvent->getDescription(), $matches) ) ) {
            return;
        }

        if (!$bookingId) {
            try {
                $bookingDetails = $this->getBookingDetailsFromGoogleCalendarEvent($gEvent);
                if(empty($bookingDetails)){
                    return;
                }
                if (empty($bookingDetails['user_id'])) {
                    $this->printMsg("Start creating new user");
                    $bookingDetails['user_id'] = $this->createCustomer($bookingDetails);
                    $this->printMsg("User created");
                }

                $this->printMsg("Event parsed details:");
                $this->printMsg(print_r($bookingDetails, true));
                $this->printMsg("Start creating booking");
                if($shop_id){
                    $bookingDetails['shop_id'] = $shop_id;
                }
                $this->importNewBookingFromGoogleCalendarEvent($gEvent, $bookingDetails);

                $gEvent = $this->gScope->get_google_service()->events->get(
                    $this->gScope->google_client_calendar,
                    $gEvent->getId()
                );

                if (empty($this->eventError)) {
                    $this->makeGoogleCalendarEventSyncSuccessful($gEvent);
                } else {
                    $this->makeGoogleCalendarEventSyncWarning($gEvent, $this->eventError);
                }
            } catch (Exception $e) {
                switch ($e->getCode()) {
                    case self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT:
                        $this->makeGoogleCalendarEventSyncWrong($gEvent, $e->getMessage());
                        $this->printMsg(sprintf("ERROR: %s", $e->getMessage()));
                        break;
                    case self::EXCEPTION_CODE_FOR_EMPTY_CALENDAR_EVENT:
                        break;
                }
            }
        }
        else {

            $booking = new SLN_Wrapper_Booking($bookingId);

            if ($gEvent->getStatus() === self::EVENT_DELETED_STATUS && $booking->getStatus() !== SLN_Enum_BookingStatus::CANCELED) {

                $booking->setStatus(SLN_Enum_BookingStatus::CANCELED);

                $this->printMsg(sprintf("Booking for this event set status to Cancelled '%s'", $bookingId));
            } else {
                $localStartTime = new \DateTime($booking->getStartsAt());
                $googleStartTime = new \DateTime($gEvent->getStart()->getDateTime());

                $localUpdated = new \DateTime(get_post_modified_time('Y-m-d H:i:s', false, $booking->getId()));
                $googleUpdated = new \DateTime($gEvent->getUpdated());
                $googleUpdated->setTimezone(SLN_TimeFunc::getWpTimezone());

                $localUpdatedFormatted = $localUpdated->format('Y-m-d H:i:s');
                $googleUpdatedFormatted = $googleUpdated->format('Y-m-d H:i:s');

                if($localStartTime != $googleStartTime &&  $googleUpdatedFormatted  > $localUpdatedFormatted){

                    $this->updateBookingFromGoogleCalendarEvent($gEvent, $booking);
                    $this->printMsg(sprintf("Booking update id: '%s'", $booking->getId()));
                }

            }

            $this->printMsg(sprintf("Booking for this event already exist '%s'", $bookingId));
        }

        $this->printMsg(sprintf("End processing event '%s'", $gEvent->getId()));
    }


    /**
     * @param Google_Service_Calendar_Event $gEvent
     * @param SLN_Wrapper_Booking $booking
     * @return bool
     */
    private function updateBookingFromGoogleCalendarEvent($gEvent, $booking)
    {
        $googleStartTime = new \DateTime($gEvent->getStart()->getDateTime());

        wp_update_post(array(
            'ID' => $booking->getId(),
            'meta_input'  => array(
                '_sln_booking_date'      => $googleStartTime->format('Y-m-d'),
                '_sln_booking_time'      => $googleStartTime->format('H:i'),
            ),
        ));

        return true;
    }

    /**
     * @param Google_Service_Calendar_Event $gEvent
     * @param array $bookingDetails
     * @return bool
     */
    private function importNewBookingFromGoogleCalendarEvent($gEvent, $bookingDetails)
    {
        $date            = new SLN_DateTime($bookingDetails['date'].' '.$bookingDetails['time'], SLN_TimeFunc::getWpTimezone());
        $bookingServices = $this->prepareAndValidateBookingServices($bookingDetails);

        // create booking
        $user = get_userdata($bookingDetails['user_id']);

        $name       = trim($bookingDetails['first_name'].' '.$bookingDetails['last_name']);
        $dateString = SLN_Plugin::getInstance()->format()->datetime($date);

        $postArr = array(
            'post_author' => $bookingDetails['user_id'],
            'post_type'   => SLN_Plugin::POST_TYPE_BOOKING,
            'post_title'  => $name.' - '.$dateString,
            'meta_input'  => array(
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_date'      => $bookingDetails['date'],
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_time'      => $bookingDetails['time'],
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_firstname' => $bookingDetails['first_name'],
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_lastname'  => $bookingDetails['last_name'],
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_email'     => !empty($bookingDetails['email']) ? $bookingDetails['email'] : $user->user_email,
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_phone'     => $bookingDetails['phone'],
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_sms_prefix'=> $bookingDetails['sms_prefix'],
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_address'   => '',
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_note'      => $bookingDetails['note'],
                '_sln_calendar_event_id'                       => $gEvent->getId(),
                '_'.SLN_Plugin::POST_TYPE_BOOKING.'_services'  => $bookingServices->toArrayRecursive(),
            ),
        );

	    remove_action('save_post', 'synch_a_booking', 12);

	    $postId  = wp_insert_post($postArr);
	    if(isset($bookingDetails['shop_id'])){
	        add_post_meta($postId,'_sln_booking_shop', $bookingDetails['shop_id'], true);
        }
	    add_action('save_post', 'synch_a_booking', 12, 2);

        if ($postId instanceof WP_Error) {
            throw new ErrorException($postId->get_error_message(), self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT);
        }
        $this->printMsg(sprintf("Booking created '%s'", $postId));

        $booking = SLN_Plugin::getInstance()->createBooking($postId);
        $booking->getBookingServices();
        $booking->evalTotal();
        $booking->evalDuration();
        $booking->setStatus(SLN_Enum_BookingStatus::PAY_LATER);

        return true;
    }

    private function prepareAndValidateBookingServices($bookingDetails)
    {
        $date = new SLN_DateTime($bookingDetails['date'].' '.$bookingDetails['time'],SLN_TimeFunc::getWpTimezone());
        $this->validateBookingStartTime($date);

        $ah = SLN_Plugin::getInstance()->getAvailabilityHelper();
        $ah->setDate($date);

        $bookingServices = SLN_Wrapper_Booking_Services::build(
            array_fill_keys(
                $bookingDetails['services'],
                0
            ),
            $date
        );

        try {
            $ah->addAttendantForServices($bookingServices);

            $this->validateBookingServices($ah, $bookingServices);
        } catch (Exception $e) {
            $this->eventError = $e->getMessage();
            $this->printMsg(sprintf("WARNING: %s", $this->eventError));
        }

        return $bookingServices;
    }

    private function validateBookingStartTime($date) {
        $interval    = SLN_Plugin::getInstance()->getSettings()->getInterval();
        $startInMins = (int) SLN_Func::getMinutesFromDuration($date);
        if ($startInMins % $interval) {
            throw new ErrorException(sprintf("Event start time is not multiple of %s minutes", $interval), self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT);
        }

        return true;
    }

    /**
     * @param SLN_Helper_Availability $ah
     * @param SLN_Wrapper_Booking_Services $bookingServices
     * @throws SLN_Exception
     */
    private function validateBookingServices($ah, $bookingServices)
    {
        $settings               = SLN_Plugin::getInstance()->getSettings();
        $primaryServicesCount   = $settings->get('primary_services_count');
        $secondaryServicesCount = $settings->get( 'secondary_services_count' );
        $bookingOffsetEnabled   = $settings->get('reservation_interval_enabled');
        $bookingOffset          = $settings->get('minutes_between_reservation');
        $isMultipleAttSelection = $settings->get('m_attendant_enabled');

    	// $isServicesCountPrimaryServices = $settings->get('is_services_count_primary_services');

        if ($servicesCount) {

            $_services = $bookingServices->getItems();

            // if($isServicesCountPrimaryServices) {
            $_services = array_filter($_services, function ($bookingService) {
                return !$bookingService->getService()->isSecondary();
            });
            // }

            if (count($_services) >= $primaryServicesCount) {
            throw new SLN_Exception(
                sprintf(
                    // translators: %s will be replaced by the primary services count
                    esc_html__('You can select up to %d items', 'salon-booking-system'), $primaryServicesCount)
            );
            }
        }
        if( $secondaryServicesCount ){
            $_services = array_filter( $services, function( $bookingService ){
                return $bookingService->getService()->isSecondary();
            });
            
            if( count( $_services ) >= $secondaryServicesCount ){
                throw new SLN_Exception(
                    sprintf(
                        // translators: %s will be replaced by the secondary services count
                        esc_html__('You can select up to %d items', 'salon-booking-system'), $secondaryServicesCount)
                );
            }
        }

        $firstSelectedAttendant = null;
        foreach ($bookingServices->getItems() as $bookingService) {

	    $serviceErrors = $ah->validateServiceFromOrder($bookingService->getService(), $bookingServices);

	    if (!empty($serviceErrors)) {
		throw new SLN_Exception(reset($serviceErrors));
	    }

	    if ($bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
		$offsetStart   = $bookingService->getEndsAt();
		$offsetEnd     = $bookingService->getEndsAt()->modify('+'.$bookingOffset.' minutes');
		$serviceErrors = $ah->validateTimePeriod($offsetStart, $offsetEnd);

		if (!empty($serviceErrors)) {
		    throw new SLN_Exception(reset($serviceErrors));
		}
	    }

	    $serviceErrors = $ah->validateBookingService($bookingService);
	    if (!empty($serviceErrors)) {
		throw new SLN_Exception(reset($serviceErrors));
	    }

	    if (!$isMultipleAttSelection && !is_array($bookingService->getAttendant())) {
            if (!$firstSelectedAttendant) {
                $firstSelectedAttendant = $bookingService->getAttendant() ?
                $bookingService->getAttendant()->getId() : false;
            }
            if ($bookingService->getAttendant() &&
                $bookingService->getAttendant()->getId() != $firstSelectedAttendant
            ) {
                throw new SLN_Exception(
                esc_html__(
                    'Multiple attendants selection is disabled. You must select one attendant for all services.',
                    'salon-booking-system'
                )
                );
            }
	    }
	    if ($bookingService->getAttendant()) {
		$attendantErrors = $ah->validateAttendantService(
		    $bookingService->getAttendant(),
		    $bookingService->getService()
		);
		if (!empty($attendantErrors)) {
		    throw new SLN_Exception(reset($attendantErrors));
		}

		if(!is_array($bookingService->getAttendant())){
            $attendantErrors = $ah->validateBookingAttendant($bookingService, $bookingServices->isLast($bookingService));
        }else{
            $attendantErrors = $ah->validateBookingAttendants($bookingService, $bookingServices->isLast($bookingService));
        }
		if (!empty($attendantErrors)) {
		    throw new SLN_Exception(reset($attendantErrors));
		}
	    }
        }
    }


    /**
     * @param Google_Service_Calendar_Event $gEvent
     * @return array
     */
    private function getBookingDetailsFromGoogleCalendarEvent($gEvent)
    {
        $bookingDetails = array();

        if (null == $gEvent->getStart()) {
            throw new ErrorException('Event start datetime is null', self::EXCEPTION_CODE_FOR_EMPTY_CALENDAR_EVENT);
        }

        $eventDateTime = $gEvent->getStart()->getDateTime();
        $timezone = ($eventTimeZone = $gEvent->getStart()->getTimeZone()) ? new DateTimeZone($eventTimeZone) : $this->timezone;
        if (empty($eventDateTime)) {
            throw new ErrorException('Event start time is null', self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT);
        }

        $localDateTime       = SLN_DateTime::createFromFormat ( DateTime::RFC3339 , $eventDateTime, $timezone );
        $localDateTime->setTimezone(SLN_TimeFunc::getWpTimezone());
        $bookingDetails['date'] = $localDateTime->format('Y-m-d');
        $bookingDetails['time'] = $localDateTime->format('H:i');
        $_bookingDetails = $this->parseGoogleCalendarEventDescription($gEvent->getSummary());
        if(empty($_bookingDetails)){
            return null;
        }
        $bookingDetails = array_merge(
            $bookingDetails,
            $_bookingDetails
        );

        $bookingDetails['user_id'] = $this->getCustomerIdByName(
            $bookingDetails['first_name'],
            $bookingDetails['last_name']
        );

        if (empty($bookingDetails['user_id']) && empty($bookingDetails['email'])) {
            throw new ErrorException(
                sprintf(
                    "Invalid username '%s'",
                    trim($bookingDetails['first_name'].' '.$bookingDetails['last_name'])
                ),
                self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT
            );
        }

        foreach ($bookingDetails['services'] as $i => $name) {
            $bookingDetails['services'][$i] = $this->getServiceIdByName($name);
            if (empty($bookingDetails['services'][$i])) {
                throw new ErrorException(sprintf("Invalid service name '%s'", $name), self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT);
            }
        }

        return $bookingDetails;
    }

    private function parseGoogleCalendarEventDescription($text)
    {
        $details = array(
            'first_name' => '',
            'last_name'  => '',
            'services'   => array(),
            'email'      => '',
            'phone'      => '',
            'sms_prefix' => '',
            'note'       => '',
        );

        $items = explode(',', trim($text), 5);
        $items = array_map('trim', $items);

        if (count($items) < 2) {
            return null;
            // throw new ParseError("Invalid string. 'First_name last_name, service name' not found", self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT);
        }

        $details['services'] = array_filter(array_map('trim', explode('+', $items[1])));
        $details['email']    = isset($items[2]) ? $items[2] : '';
        if(isset($items[3])){
            $parse_phone = explode(' ', $items[3]);
            if(count($parse_phone) >= 2 && $parse_phone[0][0] == '+'){
                $details['sms_prefix'] = $parse_phone[0];
                $details['phone'] = implode(' ', array_slice($parse_phone, 1));
            }else{
                $details['phone'] = $items[3];
            }
        }
        $details['note']     = isset($items[4]) ? $items[4] : '';

        $details = array_merge($details, $this->parseCustomerName(trim($items[0])));

        return $details;
    }

    private function parseCustomerName($customerName)
    {
        $ret = array(
            'first_name' => '',
            'last_name'  => '',
        );

        $nameParts = explode(' ', $customerName);
        if (count($nameParts) > 1) {
            $ret['last_name'] = array_pop($nameParts);
        }
        $ret['first_name'] = implode(' ', $nameParts);

        return $ret;
    }

    private function getCustomerIdByName($firstName, $lastName)
    {
        $args  = array(
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => 'first_name',
                    'value'   => $firstName,
                    'compare' => '=',
                ),
                array(
                    'key'     => 'last_name',
                    'value'   => $lastName,
                    'compare' => '=',
                ),
            ),
        );
        $query = new WP_User_Query($args);

        if (!$query->get_total()) {
            return false;
        }

        $users = $query->get_results();
        $user  = reset($users);

        return $user->ID;
    }

    private function createCustomer($values) {
        if (email_exists($values['email'])) {
            throw new ErrorException(
                sprintf(
                    "E-mail '%s' exists",
                    $values['email']
                ),
                self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT
            );
        }

        if (empty($values['password'])) {
            $values['password'] = wp_generate_password();
        }

        $user_id = wp_create_user($values['email'], $values['password'], $values['email']);

        if (is_wp_error($user_id)) {
            throw new ErrorException(
                $user_id->get_error_message(),
                self::EXCEPTION_CODE_FOR_INVALID_CALENDAR_EVENT
            );
        }

        wp_update_user(
            array('ID' => $user_id, 'first_name' => $values['first_name'], 'last_name' => $values['last_name'], 'role' => SLN_Plugin::USER_ROLE_CUSTOMER)
        );
        add_user_meta($user_id, '_sln_phone', $values['phone']);
        add_user_meta($user_id, '_sln_sms_prefix', $value['sms_prefix']);
        add_user_meta($user_id, '_sln_address', isset($values['address']) ? $values['address'] : '');

        if (!SLN_Plugin::getInstance()->getSettings()->isDisableNewUserWelcomeEmail()) {
            wp_new_user_notification($user_id, null, 'both');
        }

        return $user_id;
    }

    private function getBookingIdFromEventId($gEventId)
    {
        $args  = array(
            'post_type'  => SLN_Plugin::POST_TYPE_BOOKING,
            'meta_query' => array(
                array(
                    'key'   => '_sln_calendar_event_id',
                    'value' => $gEventId,
                ),
            ),
        );
        $query = new WP_Query($args);

        $posts = $query->get_posts();
        wp_reset_query();
        $post = reset($posts);

        return (!empty($post) ? $post->ID : null);
    }

    private function getServiceIdByName($serviceName)
    {
        $serviceName = trim($serviceName);
        if (empty($serviceName)) {
            return false;
        }

        $args  = array(
            'title'     => $serviceName,
            'post_type' => SLN_Plugin::POST_TYPE_SERVICE,
        );
        $query = new WP_Query($args);

        if (!$query->post_count) {
            return false;
        }
        $posts = $query->get_posts();
        wp_reset_query();

        $post = reset($posts);

        return $post->ID;
    }

    /**
     * @param Google_Service_Calendar_Event $gEvent
     */
    private function makeGoogleCalendarEventSyncSuccessful($gEvent)
    {
        $gEvent->setColorId(self::SUCCESS_COLOR_ID);

        $updated = $this->gScope->get_google_service()->events->update(
            $this->gScope->google_client_calendar,
            $gEvent->getId(),
            $gEvent
        );
    }

    /**
     * @param Google_Service_Calendar_Event $gEvent
     * @param string|null $error
     */
    private function makeGoogleCalendarEventSyncWrong($gEvent, $error = null)
    {
        $gEvent->setColorId(self::ERROR_COLOR_ID);
        if (!empty($error)) {
            $gEvent->setDescription("ERROR: {$error}");
        }

        $updated = $this->gScope->get_google_service()->events->update(
            $this->gScope->google_client_calendar,
            $gEvent->getId(),
            $gEvent
        );
    }

    /**
     * @param Google_Service_Calendar_Event $gEvent
     * @param string|null $error
     */
    private function makeGoogleCalendarEventSyncWarning($gEvent, $error = null)
    {
        $gEvent->setColorId(self::WARNING_COLOR_ID);
        if (!empty($error)) {
            $error = $gEvent->getDescription()."\n\nWARNING: {$error}";
            $gEvent->setDescription($error);
        }

        $updated = $this->gScope->get_google_service()->events->update(
            $this->gScope->google_client_calendar,
            $gEvent->getId(),
            $gEvent
        );
    }

    private function printMsg($text) {
        echo "{$text}<br/>";
        error_log($text);
    }

    private static function updateSyncToken($syncToken)
    {
        update_option(self::$googleClientCalendarSyncToken, $syncToken);
    }

    private static function getSyncToken()
    {
        return get_option(self::$googleClientCalendarSyncToken);
    }
}