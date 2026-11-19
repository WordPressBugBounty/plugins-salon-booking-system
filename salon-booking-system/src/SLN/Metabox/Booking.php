<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Metabox_Booking extends SLN_Metabox_Abstract
{
    /**
     * Editor field carrying the signature of the availability violations the user
     * confirmed in the "Save anyway" modal (see SLN_Helper_AvailabilityViolations).
     */
    const AVAILABILITY_OVERRIDE_FIELD = '_sln_availability_override';

    /** @var  SLN_Wrapper_Booking */
    private $booking;
    /** @var string */
    private $prevStatus;
    /** @var array Services of the current save, as built by processServicesSubmission(). */
    private $submittedServices = array();

    protected $fields = array(
            'amount' => 'float',
            'deposit' => 'float',
            'date' => 'date',
            'time' => 'time',
            'services' => 'nofilter',
            'note' => '',
            'admin_note' => '',
            '_sln_calendar_event_id' => '',
            'dont_notify_customer' => 'bool',
            'sms_prefix' => '',
            'tips' => '',
            'services_resources' => 'nofilter',
            'soap_notes' => '',
        );

    public function add_meta_boxes()
    {
        $pt = $this->getPostType();
        add_meta_box(
            $pt.'-details',
            __('Booking details', 'salon-booking-system'),
            array($this, 'details_meta_box'),
            $pt,
            'normal',
            'high'
        );
    }


    protected function init()
    {
        parent::init();
        add_action('load-post.php', array($this, 'hookLoadPost'));
        add_action('trashed_post', array($this, 'trashed_post'), 10, 1);
        add_filter('wp_untrash_post_status', array($this, 'wp_untrash_post_status'), 10, 3);
        add_action('admin_notices', array($this, 'show_booking_trashed_notice'));
        add_filter('redirect_post_location', array($this, 'redirect_post_location'), 10, 2);

	if (!isset($_GET['mode']) || $_GET['mode'] !== 'sln_editor') {
	    add_action('in_admin_header', array($this, 'in_admin_header'));
	}

        // Headers are hidden in admin CSS, so a saved "closed" postbox cannot be reopened.
        $pt = $this->getPostType();
        add_filter('postbox_classes_' . $pt . '_' . $pt . '-details', array($this, 'keep_booking_metabox_open'));
        add_filter('postbox_classes_' . $pt . '_' . $pt . '-actions', array($this, 'keep_booking_metabox_open'));
    }

    /**
     * Booking metabox headers are not shown, so never render them collapsed.
     *
     * @param string[] $classes
     * @return string[]
     */
    public function keep_booking_metabox_open($classes)
    {
        return array_values(array_diff((array) $classes, array('closed')));
    }

    public function hookLoadPost()
    {
        if (
            (isset($_GET['post_type']) && $_GET['post_type'] == $this->getPostType())
            || (isset($_POST['post_type']) && $_POST['post_type'] == $this->getPostType())
        ) {
            $this->getPlugin()->messages()->setDisabled(true);
            if (isset($_GET['post'])) {
                $this->booking = $this->getPlugin()->createFromPost(intval($_GET['post']));
                $this->prevStatus = $this->booking->getStatus();
            }
            if (isset($_POST['post_ID'])) {
                $this->booking = $this->getPlugin()->createFromPost(intval($_POST['post_ID']));
                $this->prevStatus = $this->booking->getStatus();
            }

            return;
        }
    }

    public function details_meta_box($object, $box)
    {
        if(isset($_GET['action']) && isset($_GET['post']) && $_GET['action'] === 'duplicate'){
            $object = get_post($_GET['post']);
        }
        
        // Parse time parameter with fallback handling
        $time_param = null;
        if (isset($_GET['time'])) {
            $raw_time = sanitize_text_field(wp_unslash($_GET['time']));
            
            try {
                // Try to parse the time parameter
                $time_param = new SLN_DateTime($raw_time, SLN_TimeFunc::getWpTimezone());
            } catch (Exception $e) {
                // Try alternative: prepend today's date
                try {
                    $today = date('Y-m-d');
                    $time_param = new SLN_DateTime($today . ' ' . $raw_time, SLN_TimeFunc::getWpTimezone());
                } catch (Exception $e2) {
                    // If parsing still fails, log error and continue without time
                    SLN_Plugin::addLog('SLN Booking: Failed to parse time parameter "' . $raw_time . '": ' . $e2->getMessage());
                    $time_param = null;
                }
            }
        }
        
        echo $this->getPlugin()->loadView(
            'metabox/booking',
            array(
                'metabox' => $this,
                'settings' => $this->getPlugin()->getSettings(),
                'booking' => $this->getPlugin()->createBooking($object),
                'postType' => $this->getPostType(),
                'helper' => new SLN_Metabox_Helper(),
                // Only sln_editor is a valid booking-metabox mode. Do not pass arbitrary GET values into the view.
                'mode' => (isset($_GET['mode']) && 'sln_editor' === sanitize_text_field(wp_unslash($_GET['mode']))) ? 'sln_editor' : '',
                'date' => isset($_GET['date']) ? new SLN_DateTime(sanitize_text_field(wp_unslash($_GET['date'])),SLN_TimeFunc::getWpTimezone()) : null,
                'time' => $time_param,
            )
        );
        do_action($this->getPostType().'_details_meta_box', $object, $box);
    }

    protected function getFieldList()
    {
        $additional = array();

        foreach (SLN_Enum_CheckoutFields::forBooking()->keys() as $key) {
            $additional[$key] = '';
        }

        return apply_filters('sln.metabox.booking.getFieldList', array_merge($this->fields, $additional));
    }

    private $disabledSavePost = false;

    public function save_post($post_id, $post)
    {
        if (
            get_post_field('post_type', $post_id) !== SLN_Plugin::POST_TYPE_BOOKING
            || $this->disabledSavePost
        ) {
            return;
        }

        if (preg_match('/post\-new\.php/i', $_SERVER['REQUEST_URI'])) {

            $postnew = array(
            'ID'	      => $post_id,
            'post_author' => 0,
            );

            $this->disabledSavePost = true;

            wp_update_post($postnew);

            $this->disabledSavePost = false;
        }

        if (
            get_post_field('post_type', $post_id) !== SLN_Plugin::POST_TYPE_BOOKING
            || $this->disabledSavePost
            || !isset($_POST['_sln_booking_status'])
        ) {
            return;
        }

        $_POST['_sln_booking_services'] = $this->processServicesSubmission(isset($_POST['_sln_booking']) ? $_POST['_sln_booking'] : array());
        $_POST['_sln_booking_services_resources'] = isset($_POST['_sln_booking']['services_resources']) ? $_POST['_sln_booking']['services_resources'] : array();
        if(count($_POST['_sln_booking_services']) == 0){
            // A booking that already stores services is never deleted here: an empty
            // payload means the submission lost the service rows (JS failure, truncated
            // POST, interrupted save), and force-deleting bypasses the trash, so the
            // booking cannot be recovered. Refuse the save instead — removing a real
            // booking goes through Trash or the cancelled status.
            $storedServices = get_post_meta($post_id, '_sln_booking_services', true);
            if (!empty($storedServices)) {
                SLN_Plugin::addLog(sprintf(
                    '[Metabox_Booking] save_post received no services for booking #%d (status %s) - save refused, booking kept',
                    $post_id,
                    get_post_status($post_id)
                ));
                $this->addError(
                    __(
                        'No service was submitted for this booking, so your changes were not saved. Select at least one service, or move the booking to the trash to remove it.',
                        'salon-booking-system'
                    )
                );
                $this->restoreStatusAfterRefusedSave($post_id);

                return;
            }

            wp_delete_post($post_id, true);
            if(preg_match('/post\-new\.php/i', $_SERVER['REQUEST_URI'])){
                wp_redirect(add_query_arg(array('post_type' => 'sln_booking'), admin_url('post-new.php')));
            }else{
                wp_redirect(add_query_arg(array('post' => $post_id ,'action' => 'edit'), admin_url('post.php')));
            }
            die();
        }
        $h = new SLN_Metabox_Helper();
        $is_modified = false;
        $this->normalizeBookingTimeInRequest();
        if($h->isMetaNewForPost($post_id, $h->processRequest($this->getPostType(), $this->getFieldList())) &&
        $this->prevStatus != 'auto-draft') {
            $is_modified = true;
        }

        // Availability rules never stop the salon staff: the editor shows the broken
        // rules in a modal before submitting and the user confirms them ("Save anyway").
        // Here the save goes through for users who can force it, and the forced rules
        // are recorded on the booking. Other users are refused with the same list.
        // Only for the booking editor form: without its nonce the metabox fields are
        // not saved at all (SLN_Metabox_Helper::isValidRequest).
        $editorNonceField = $this->getPostType() . '_details_meta_nonce';
        $isEditorRequest  = isset($_POST[$editorNonceField]) && wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST[$editorNonceField])),
            $this->getPostType()
        );
        // Lock the start time until the booking is saved: a front-end checkout (or a PWA
        // save) for the same slot waits and then sees this booking, and this check sees
        // any booking saved for the slot while the editor was open.
        if ($isEditorRequest && !SLN_Helper_SlotLock::acquire(SLN_Helper_SlotLock::key(self::getEditorStartsAt($_POST)))) {
            $this->addError(__('Another booking for this time is being saved right now, so your changes were not saved. Please try again.', 'salon-booking-system'));
            $this->refuseAvailabilitySave($post_id);

            return;
        }
        $availabilityViolations = $isEditorRequest
            ? self::getEditorAvailabilityViolations($post_id, $this->submittedServices, $_POST)
            : null;
        if (!empty($availabilityViolations)) {
            if (!SLN_Helper_AvailabilityViolations::currentUserCanForce()) {
                $this->addError(__('This booking breaks these availability rules and you can\'t save it anyway:', 'salon-booking-system'));
                foreach ($availabilityViolations as $violation) {
                    $this->addError($violation['title'].': '.$violation['message']);
                }
                $this->refuseAvailabilitySave($post_id);

                return;
            }
            $token = isset($_POST[self::AVAILABILITY_OVERRIDE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::AVAILABILITY_OVERRIDE_FIELD])) : '';
            if (!SLN_Helper_AvailabilityViolations::isConfirmed($availabilityViolations, $token)) {
                // The rules broken now are not the ones the user confirmed (or the user saw
                // none): availability changed after the pre-save check, e.g. a customer booked
                // the last free spot in the meantime. Never force a booking over rules the
                // user didn't see: refuse, list them, and let the user confirm on the next save.
                SLN_Plugin::addLog(sprintf(
                    '[Metabox_Booking] Booking #%d not saved: availability changed after the pre-save check (%s)',
                    $post_id,
                    implode(', ', wp_list_pluck($availabilityViolations, 'code'))
                ));
                $this->addError(__('Availability changed while you were editing this booking, so your changes were not saved. It now breaks these rules:', 'salon-booking-system'));
                foreach ($availabilityViolations as $violation) {
                    $this->addError($violation['title'].': '.$violation['message']);
                }
                $this->addError(__('Make your changes again and save: you will be asked to confirm these rules before the booking is saved.', 'salon-booking-system'));
                $this->refuseAvailabilitySave($post_id);

                return;
            }
        }

        $old_booking = $this->getPlugin()->createFromPost($post_id);
        $old_booking_services = $old_booking->getMeta('services');
        parent::save_post($post_id, $post);
        $this->validate($_POST);

        delete_post_meta($post_id, "_{$old_booking->getPostType()}_password");
        delete_post_meta($post_id, "_{$old_booking->getPostType()}_password_confirm");

        /** @var SLN_Wrapper_Booking $booking */
        $booking = $this->getPlugin()->createFromPost($post_id);
        do_action('sln.metabox.booking.pre_eval',$booking,$post_id);
        $booking->evalBookingServices();
        $booking->evalDuration();

        if(!$is_modified) {
            if($this->prevStatus != 'auto-draft' &&
            $old_booking_services != $booking->getMeta('services')) {
                $is_modified = true;
            }
        }

        $this->disabledSavePost = true;
        $booking->setStatus($booking->getStatus());
        $this->disabledSavePost = false;
        $s = $booking->getStatus();
        $new = sanitize_text_field(wp_unslash($_POST['_sln_booking_status']));
        if (strpos($new, 'sln-b-') !== 0) {
            $new = SLN_ENUM_BookingStatus::PENDING_PAYMENT;
        }
        $postnew = array(
            'ID' => $post_id,
	    'post_author' => (int)$_REQUEST['post_author'], //save guest customer id 0
            'post_status' => SLN_Enum_BookingStatus::getForceStatus($new),
        );
        if (isset($_SESSION['_sln_booking_user_errors'])) {
            $this->restoreStatusAfterRefusedSave($post_id);
            return;
        }
        if (is_array($availabilityViolations)) {
            SLN_Helper_AvailabilityViolations::recordOverride($post_id, $availabilityViolations);
        }
        $createUser = isset($_POST['_sln_booking_createuser'])  ? boolval($_POST['_sln_booking_createuser']) : false;
        if ($createUser) {
            $userid = $this->registration($_POST);
            if ($userid instanceof WP_Error) {
                return;
            }
            $postnew = array_merge(
                $postnew,
                array(
                    'ID' => $post_id,
                    'post_author' => $userid,
                )
            );
        }else{
            $mods = false;
            $user_id = (int) $_REQUEST['post_author'];
            $userdata = get_userdata( $user_id );

	    if ( $userdata && ! SLN_Wrapper_Customer::isStaff( $userdata ) && SLN_Admin_Customers::current_user_can_manage_customer( $user_id ) ) {
		foreach (['email'=>'user_email','firstname'=>'first_name','lastname'=>'last_name'] as $field => $wp_field) {
		    $value = sanitize_text_field($_POST['_sln_booking_'.$field]);
		    if($field === 'email' ){
			if ( !empty($value) && !filter_var(
				$value,
				FILTER_VALIDATE_EMAIL
			    )
			) {
			    $this->addError(__('e-mail is not valid', 'salon-booking-system'));
			    return;
			}
		    }
		    $original_value = $userdata->$wp_field;
		    if( $value !== $original_value){
			if($field === 'email' && array_intersect(['administrator'],$userdata->roles)){
			    continue;
			}
			$userdata->$wp_field = $value;
			$mods = true;
		    }
		}
		if($mods){
		    wp_update_user($userdata);
		}
	    }
        }

        if (!empty($postnew)) {
            $this->disabledSavePost = true;
            $booking->setStatus($new);
            wp_update_post($postnew);
            $this->disabledSavePost = false;
        }

	    // Only set origin when creating a new booking from the back-end.
	    // On edit pages the hidden field is never submitted (view guard above),
	    // but as a belt-and-braces defence we also reject the value here if the
	    // request is an edit (action=edit in POST/REQUEST) so nothing can slip
	    // through even if the view guard is bypassed.
	    $is_admin_edit = (isset($_REQUEST['action']) && $_REQUEST['action'] === 'edit')
	                   || (isset($_POST['original_post_status']) && $_POST['original_post_status'] !== 'auto-draft');
	    if (!$is_admin_edit && isset($_POST['_sln_booking_origin_source'])) {
		    $booking->setMeta('origin_source', sanitize_text_field($_POST['_sln_booking_origin_source']));
	    }
	    
	    // Track the WordPress user who created the booking (set once)
	    // This is different from post_author which is the customer
	    if (!$booking->getMeta('created_by_user_id')) {
		    $booking->setMeta('created_by_user_id', get_current_user_id());
	    }

	    if (!$booking->getMeta('language')) {
		    $booking_lang = SLN_Helper_Multilingual::sanitizeLanguageCode(SLN_Helper_Multilingual::getCurrentLanguage());
		    if ($booking_lang) {
			    $booking->setMeta('language', $booking_lang);
		    }
	    }
	    
	    // Track the last editor and edit time (updates on every save)
	    $current_user_id = get_current_user_id();
	    if ($current_user_id) {
		    $booking->setMeta('last_edited_by_user_id', $current_user_id);
		    $booking->setMeta('last_edited_at', current_time('mysql'));
	    }

        if(($customer = $booking->getCustomer()) && !SLN_Wrapper_Customer::isStaff($booking->getUserId())){
            $fields = SLN_Enum_CheckoutFields::forBookingAndCustomer()->keys();
            if($fields){
                foreach ($fields as $field) {

                    if(isset($_POST['_sln_'.$field])) $customer->setMeta($field,$_POST['_sln_'.$field]);
                }
            }

        }
        $this->addCustomerRole($booking);
        $booking->reload();
        $m = $this->getPlugin()->messages();
        $status_changed = ($this->prevStatus != $booking->getStatus());

        // TEMP DIAGNOSTIC: trace "Do not notify customer" on admin save.
        // Shows what was POSTed, what got persisted, and the resolved gate so we
        // can tell whether the meta is being saved and honoured. Remove once fixed.
        SLN_Plugin::addLog(sprintf(
            '[DONT_NOTIFY_DIAG] Metabox save_post | booking #%d | POST(_sln_booking_dont_notify_customer)=%s | raw_meta=%s | getNotifyCustomer=%s | prevStatus=%s | newStatus=%s | status_changed=%s | is_modified=%s',
            $post_id,
            isset($_POST['_sln_booking_dont_notify_customer']) ? var_export($_POST['_sln_booking_dont_notify_customer'], true) : 'NOT_SET',
            var_export(get_post_meta($post_id, '_sln_booking_dont_notify_customer', true), true),
            $booking->getNotifyCustomer() ? 'true' : 'false',
            $this->prevStatus,
            $booking->getStatus(),
            $status_changed ? 'true' : 'false',
            $is_modified ? 'true' : 'false'
        ));

        if ($status_changed) {
            if($this->prevStatus != 'auto-draft' && in_array($booking->getStatus(), $m->getStatusForSummary())) {
                $is_modified = true; //if booking status was changed to PAID or PAY_LATER from backend, send booking modified notification
            } else {
                $m->setDisabled(false);
                $m->sendByStatus($booking, $booking->getStatus());
                $is_modified = false; //status changed email was sent, no need to send booking modified email
            }
        }
        
        // Send modified notification if:
        // 1. Booking data was modified (date, time, services, etc.) OR
        // 2. Status was changed to PAID/PAY_LATER (handled above)
        // This ensures SMS is sent even when only booking details change without status change
        if($is_modified) {
            $m->setDisabled(false);
            $m->sendBookingModified($booking);
        }
        $this->getPlugin()
            ->getBookingCache()
            ->processBooking($booking, false);

        // Auto-trash if status was changed to CANCELED
        if ($this->prevStatus != $booking->getStatus() && $booking->getStatus() === SLN_Enum_BookingStatus::CANCELED) {
            // Disable save_post hook to prevent recursion when trashing
            $this->disabledSavePost = true;
            
            $trashAction = new SLN_Action_TrashCancelledBooking($this->getPlugin());
            $wasTrashSuccessful = $trashAction->execute($booking);
            
            // Re-enable save_post hook
            $this->disabledSavePost = false;
            
            // Redirect to bookings list with success message
            if ($wasTrashSuccessful) {
                // Use WordPress transient to show admin notice after redirect
                set_transient('sln_booking_trashed_' . get_current_user_id(), array(
                    'booking_id' => $post_id,
                    'booking_title' => $booking->getDisplayName(),
                ), 30);
                
                // Redirect to bookings list
                $redirect_url = admin_url('edit.php?post_type=' . SLN_Plugin::POST_TYPE_BOOKING);
                wp_redirect($redirect_url);
                exit;
            }
        }

        $source = isset($_POST['sln_action_source']) ? $_POST['sln_action_source'] : '';
        
        // Handle no-show status from metabox
        // Note: Hidden field is updated by AJAX toggle, so we trust its value
        $currentNoShow = get_post_meta($post_id, 'no_show', true);
        
        if (isset($_POST['_sln_booking_no_show']) && $_POST['_sln_booking_no_show'] == '1') {
            $newNoShow = 1;
        } else {
            $newNoShow = 0;
        }
        
        // Always update the no-show status to match the form value
        update_post_meta($post_id, 'no_show', $newNoShow);
        
        // Only update timestamps and trigger hooks if the state actually changed
        if ($newNoShow != $currentNoShow) {
            $currentUserId = get_current_user_id();
            $currentTime = current_time('mysql');
            
            if ($newNoShow == 1) {
                // Only update marked_at if not already set (AJAX might have set it)
                if (!get_post_meta($post_id, 'no_show_marked_at', true)) {
                    update_post_meta($post_id, 'no_show_marked_at', $currentTime);
                    update_post_meta($post_id, 'no_show_marked_by', $currentUserId);
                }
                
                $this->getPlugin()->addLog(sprintf(
                    'Booking #%d marked as no-show by user #%d (%s) from booking details page',
                    $post_id,
                    $currentUserId,
                    wp_get_current_user()->display_name
                ));
                
                do_action('sln.booking.marked_no_show', $post_id, $currentUserId);
            } else {
                update_post_meta($post_id, 'no_show_unmarked_at', $currentTime);
                
                $this->getPlugin()->addLog(sprintf(
                    'Booking #%d unmarked as no-show by user #%d (%s) from booking details page',
                    $post_id,
                    $currentUserId,
                    wp_get_current_user()->display_name
                ));
                
                do_action('sln.booking.unmarked_no_show', $post_id, $currentUserId);
            }
        }
        
        do_action('sln.booking_builder.create.booking_created', $booking);
    }

    public function trashed_post($post_id)
    {
        $pt = $this->getPostType();
        $post = get_post($post_id);
        if (is_admin() && $pt == $post->post_type) {
            /** @var SLN_Wrapper_Booking $booking */
            $booking = $this->getPlugin()->createFromPost($post_id);
            $this->getPlugin()->getBookingCache()->processBooking($booking);
        }
    }

    private function addCustomerRole($booking)
    {
        $user = new WP_User($booking->getUserId());
        $isNotAdmin = array_search('administrator', $user->roles) === false;
        $isNotSubscriber = array_search('subscriber', $user->roles) !== false;
        if ($isNotAdmin && $isNotSubscriber) {
            wp_update_user(
                array(
                    'ID' => $booking->getUserId(),
                    'role' => SLN_Plugin::USER_ROLE_CUSTOMER,
                )
            );
        }
    }

    private function processServicesSubmission($data)
    {
        $services = self::buildServicesFromSubmission($data);
        $this->submittedServices = $services;
        return $services;
    }

    /**
     * Service lines posted by the booking editor (_sln_booking), in the format
     * SLN_Wrapper_Booking_Services::build() expects.
     *
     * @param array $data
     *
     * @return array
     */
    public static function buildServicesFromSubmission($data)
    {
        $plugin   = SLN_Plugin::getInstance();
        $services = array();
        $data     = is_array($data) ? $data : array();
        $services_ids = array_map('intval', isset($data['services']) ? (array) $data['services'] : array());
        if($services_ids)
        foreach ($services_ids as $key => $serviceId) {
            if($serviceId == 0){
                continue;
            }
            $duration      = SLN_Func::convertToHoursMins($data['duration'][$serviceId]);
            $breakDuration = SLN_Func::convertToHoursMins($data['break_duration'][$serviceId]);

            $attendant = 0;
            if(isset($data['attendants'])){
                $attendant = $data['attendants'][$serviceId];
            }elseif(isset($data['attendant'])){
                $attendant = $data['attendant'];
            }
            $service = $plugin->createService($serviceId);
            $service = apply_filters('sln.booking_services.buildService', $service);
            if($attendant == 0 && $plugin->getSettings()->isAttendantsEnabled() && $service->isAttendantsEnabled()){
                continue;
            }
            $services[] = array(
                'service' => $serviceId,
                'attendant' => $attendant,
                'price' => $data['price'][$serviceId],
                'duration' => $duration,
                'break_duration' => $breakDuration,
                'break_duration_data' => $service->getBreakDurationData(),
                'resource' => isset($data['services_resources'][$serviceId]) ? $data['services_resources'][$serviceId] : '',
            );
        }
        return $services;
    }

    protected function registration($data)
    {
        $errors = wp_create_user($data['_sln_booking_email'], wp_generate_password(), $data['_sln_booking_email']);
        if (!is_wp_error($errors)) {
            wp_update_user(
                array(
                    'ID' => $errors,
                    'first_name' => $data['_sln_booking_firstname'],
                    'last_name' => $data['_sln_booking_lastname'],
                    'role' => SLN_Plugin::USER_ROLE_CUSTOMER,
                )
            );
            add_user_meta($errors, '_sln_phone', $data['_sln_booking_phone']);
            add_user_meta($errors, '_sln_address', $data['_sln_booking_address']);
            foreach(SLN_Enum_CheckoutFields::forCustomer()->keys() as $customer_key){
                if(in_array($customer_key, array('first_name', 'last_name', 'phone', 'addres'))){
                    continue;
                }
                if(isset($data["_sln_{$customer_key}"])){
                    update_user_meta($errors, "_sln_{$customer_key}", $data["_sln_{$customer_key}"]);
                }
            }

            if (!$this->getPlugin()->getSettings()->isDisableNewUserWelcomeEmail()) {
                wp_new_user_notification($errors, null, 'both');
            }
        } else {
            $this->addError($errors->get_error_message());
        }

        return $errors;
    }

    private function validate($values)
    {
        if (SLN_Enum_CheckoutFields::getField('firstname')->isRequired() && empty($values['_sln_booking_firstname'])) {
            $this->addError(__('First name can\'t be empty', 'salon-booking-system'));
        }
        if (SLN_Enum_CheckoutFields::getField('lastname')->isRequired() && empty($values['_sln_booking_lastname'])) {
            $this->addError(__('Last name can\'t be empty', 'salon-booking-system'));
        }
        if (isset($_POST['_sln_booking_createuser']) && boolval($_POST['_sln_booking_createuser'])) {
            if (SLN_Enum_CheckoutFields::getField('email')->isRequired() && empty($values['_sln_booking_email'])) {
                $this->addError(__('e-mail can\'t be empty', 'salon-booking-system'));
            }
        }

        if ( !empty($values['_sln_booking_email']) && !filter_var(
                $values['_sln_booking_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $this->addError(__('e-mail is not valid', 'salon-booking-system'));
        }
    }

    protected function addError($message)
    {
        $_SESSION['_sln_booking_user_errors'][] = $message;
    }

    /**
     * Drop WordPress's "Booking updated/published" notice when the save was refused,
     * so only the error is shown.
     */
    public function redirect_post_location($location, $post_id)
    {
        if (get_post_type($post_id) === $this->getPostType() && !empty($_SESSION['_sln_booking_user_errors'])) {
            $location = remove_query_arg('message', $location);
        }

        return $location;
    }

    /**
     * Refused save of the editor form: keep a new booking out of the calendar until
     * it is saved for real, put the previous status back on an existing one.
     *
     * @param int $post_id
     */
    private function refuseAvailabilitySave($post_id)
    {
        if (empty($this->prevStatus) || $this->prevStatus === 'auto-draft') {
            $this->disabledSavePost = true;
            wp_update_post(array('ID' => $post_id, 'post_status' => 'auto-draft'));
            $this->disabledSavePost = false;

            return;
        }
        $this->restoreStatusAfterRefusedSave($post_id);
    }

    /**
     * WordPress has already written the editor's post_status ("publish") when
     * save_post runs; the booking status is only applied at the end of a full save.
     * When the save is refused, put the previous booking status back, otherwise the
     * booking drops out of the calendar and availability.
     *
     * @param int $post_id
     */
    private function restoreStatusAfterRefusedSave($post_id)
    {
        if (empty($this->prevStatus) || strpos($this->prevStatus, 'sln-b-') !== 0 || get_post_status($post_id) === $this->prevStatus) {
            return;
        }

        $this->disabledSavePost = true;
        wp_update_post(array(
            'ID'          => $post_id,
            'post_status' => $this->prevStatus,
        ));
        $this->disabledSavePost = false;
    }

    /**
     * Align admin-submitted booking time to the configured interval before
     * availability checks and persistence (matches fieldJSTime rounding).
     *
     * Skipped for staff saving from the booking editor, who are allowed to enter a
     * time off the interval grid; availability still validates the slot normally.
     */
    private function normalizeBookingTimeInRequest()
    {
        if (empty($_POST['_sln_booking']['time'])) {
            return;
        }

        if (SLN_Func::requestAllowsUnalignedBookingTime()) {
            return;
        }

        $normalized = SLN_Func::alignTimeToInterval(
            sanitize_text_field(wp_unslash($_POST['_sln_booking']['time']))
        );

        if (empty($normalized)) {
            return;
        }

        $_POST['_sln_booking']['time'] = $normalized;
        $_POST[SLN_Metabox_Helper::getFieldName($this->getPostType(), 'time')] = $normalized;
    }

    /**
     * Start of the booking posted by the editor (_sln_booking_date / _sln_booking_time,
     * e.g. "13 Nov 2026", "10:20"), in the WordPress timezone.
     *
     * @param array $request The editor form ($_POST).
     *
     * @return SLN_DateTime|null
     */
    public static function getEditorStartsAt(array $request)
    {
        $postType  = SLN_Plugin::POST_TYPE_BOOKING;
        $dateField = SLN_Metabox_Helper::getFieldName($postType, 'date');
        $timeField = SLN_Metabox_Helper::getFieldName($postType, 'time');
        if (empty($request[$dateField]) || empty($request[$timeField])) {
            return null;
        }

        try {
            $day  = SLN_Func::filter(sanitize_text_field(wp_unslash($request[$dateField])), 'date');
            $time = SLN_Func::filter(sanitize_text_field(wp_unslash($request[$timeField])), 'time');

            return new SLN_DateTime($day . ' ' . $time, SLN_TimeFunc::getWpTimezone());
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Availability rules broken by the booking the editor is saving, or null when
     * there is nothing to check: no services, a status without a time (cancelled...),
     * or an existing booking whose date, time and services didn't change (editing
     * the notes or the status of a booking doesn't ask again).
     *
     * Used by save_post() and by the editor's pre-save check
     * (SLN_Action_Ajax_CheckBookingAvailability), so both see the same list.
     *
     * @param int   $post_id      The booking post ID.
     * @param array $servicesData Services as built by buildServicesFromSubmission().
     * @param array $request      The editor form ($_POST).
     *
     * @return array[]|null
     */
    public static function getEditorAvailabilityViolations($post_id, array $servicesData, array $request)
    {
        $plugin   = SLN_Plugin::getInstance();
        $postType = SLN_Plugin::POST_TYPE_BOOKING;

        if (empty($servicesData)) {
            return null;
        }
        $status = isset($request['_sln_booking_status']) ? sanitize_text_field(wp_unslash($request['_sln_booking_status'])) : '';
        if ($status !== '' && in_array($status, SLN_Enum_BookingStatus::$noTimeStatuses, true)) {
            return null;
        }

        $date = self::getEditorStartsAt($request);
        if (!$date) {
            return null;
        }
        $day  = $date->format('Y-m-d');
        $time = $date->format('H:i');

        // When editing an existing booking, pass it so that its own slots are left out.
        $currentBooking = $plugin->createBooking((int) $post_id);

        $storedServices = array();
        foreach ((array) $currentBooking->getMeta('services') as $stored) {
            $storedServices[] = array(
                'service'   => isset($stored['service']) ? (int) $stored['service'] : 0,
                'attendant' => isset($stored['attendant']) ? array_map('intval', (array) $stored['attendant']) : array(),
            );
        }
        $submittedServices = array();
        foreach ($servicesData as $submitted) {
            $submittedServices[] = array(
                'service'   => (int) $submitted['service'],
                'attendant' => array_map('intval', (array) $submitted['attendant']),
            );
        }
        if (
            $currentBooking->getMeta('date') === $day
            && $currentBooking->getMeta('time') === $time
            && $storedServices == $submittedServices
        ) {
            return null;
        }

        $bookingServices = SLN_Wrapper_Booking_Services::build(
            $servicesData,
            $date,
            0,
            $currentBooking->getCountServices()
        );

        $checker = new SLN_Helper_AvailabilityViolations($plugin);

        return $checker->check($bookingServices, $currentBooking);
    }

    protected function enqueueAssets()
    {
        parent::enqueueAssets();
        wp_enqueue_script(
            'salon-customBookingUser',
            SLN_PLUGIN_URL.'/js/admin/customBookingUser.js',
            array('jquery'),
            SLN_Action_InitScripts::ASSETS_VERSION,
            true
        );

	wp_localize_script(
            'salon-customBookingUser',
            'salonCustomBookingUser',
            array(
		'resend_notification_params' => apply_filters('sln_booking_resend_notification_params', array()),
		'resend_payment_params'	     => apply_filters('sln_booking_resend_payment_params', array()),
		'availability_override'	     => array(
		    'field'       => self::AVAILABILITY_OVERRIDE_FIELD,
		    'title_one'   => __('This booking breaks 1 availability rule', 'salon-booking-system'),
		    // translators: %d number of broken rules
		    'title_many'  => __('This booking breaks %d availability rules', 'salon-booking-system'),
		    'history'     => __('If you save, these exceptions are recorded in the booking history with your name.', 'salon-booking-system'),
		    'cannot_force'=> __('You can\'t save a booking that breaks these rules. Change the booking or ask an administrator.', 'salon-booking-system'),
		    'back'        => __('Go back and edit', 'salon-booking-system'),
		    'save'        => __('Save anyway', 'salon-booking-system'),
		    'close'       => __('Close', 'salon-booking-system'),
		),
	    )
        );
    }

    public function wp_untrash_post_status($new_status, $post_id, $previous_status)
    {
        $pt = $this->getPostType();
        $post = get_post($post_id);
        if (is_admin() && $pt == $post->post_type) {
            return $previous_status;
        }
        return $new_status;
    }

    /**
     * Show admin notice after booking is auto-trashed
     */
    public function show_booking_trashed_notice()
    {
        // Check if we're on the bookings list page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-' . SLN_Plugin::POST_TYPE_BOOKING) {
            return;
        }

        // Check for transient
        $transient_key = 'sln_booking_trashed_' . get_current_user_id();
        $notice_data = get_transient($transient_key);

        if ($notice_data) {
            // Delete transient so it only shows once
            delete_transient($transient_key);

            $booking_title = isset($notice_data['booking_title']) ? $notice_data['booking_title'] : '';
            $booking_id = isset($notice_data['booking_id']) ? $notice_data['booking_id'] : '';

            // Restore link
            $restore_url = wp_nonce_url(
                admin_url('post.php?post=' . $booking_id . '&action=untrash'),
                'untrash-post_' . $booking_id
            );

            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>';
            printf(
                // translators: %s is the booking customer name
                esc_html__('Booking for %s has been moved to trash.', 'salon-booking-system'),
                '<strong>' . esc_html($booking_title) . '</strong>'
            );
            echo ' ';
            echo '<a href="' . esc_url($restore_url) . '">' . esc_html__('Restore booking', 'salon-booking-system') . '</a>';
            echo '</p>';
            echo '</div>';
        }
    }
}

