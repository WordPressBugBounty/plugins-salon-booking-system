<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Metabox_Booking extends SLN_Metabox_Abstract
{
    /** @var  SLN_Wrapper_Booking */
    private $booking;
    /** @var string */
    private $prevStatus;

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
            'origin_source' => ''
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
        add_meta_box(
            $pt . '-notify',
            __('Customer notification', 'salon-booking-system'),
            array($this, 'notify_meta_box'),
            $pt,
            'side',
            'low'
        );
    }


    protected function init()
    {
        parent::init();
        add_action('load-post.php', array($this, 'hookLoadPost'));
        add_action('trashed_post', array($this, 'trashed_post'), 10, 1);
        add_filter('wp_untrash_post_status', array($this, 'wp_untrash_post_status'), 10, 3);
        add_action('admin_notices', array($this, 'show_booking_trashed_notice'));

	if (!isset($_GET['mode']) || $_GET['mode'] !== 'sln_editor') {
	    add_action('in_admin_header', array($this, 'in_admin_header'));
	}
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
                    error_log('SLN Booking: Failed to parse time parameter "' . $raw_time . '": ' . $e2->getMessage());
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
                'mode' => isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : '',
                'date' => isset($_GET['date']) ? new SLN_DateTime(sanitize_text_field(wp_unslash($_GET['date'])),SLN_TimeFunc::getWpTimezone()) : null,
                'time' => $time_param,
            )
        );
        do_action($this->getPostType().'_details_meta_box', $object, $box);
    }

    public function notify_meta_box($object, $box)
    {
        if(in_array($object->post_status,array('draft','auto-draft')))
            return '';
        ?>
        <input id="dont-notify-customer" name="_sln_booking_dont_notify_customer" type="checkbox" style="margin-right:5px" value="1" <?php
        $booking = new SLN_Wrapper_Booking($object);
        if(!$booking->getNotifyCustomer()) {
            echo 'checked';
        }
        ?>/><label for="dont-notify-customer"><span style="vertical-align:middle"><?php esc_html_e('Do not notify customer', 'salon-booking-system') ?></span></label>
        <?php
        do_action($this->getPostType() . '_notify_meta_box', $object, $box);
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

        $_POST['_sln_booking_services'] = $this->processServicesSubmission($_POST['_sln_booking']);
        $_POST['_sln_booking_services_resources'] = isset($_POST['_sln_booking']['services_resources']) ? $_POST['_sln_booking']['services_resources'] : array();
        if(count($_POST['_sln_booking_services']) == 0){
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
        if($h->isMetaNewForPost($post_id, $h->processRequest($this->getPostType(), $this->getFieldList())) &&
        $this->prevStatus != 'auto-draft') {
            $is_modified = true;
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
            return;
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

	    if ( $userdata ) {
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

	    // Only set origin on NEW bookings (preserve original booking channel)
	    // Origin tracks WHERE the booking was ORIGINALLY created, not who edited it
	    if (isset($_POST['_sln_booking_origin_source'])) {
		    // Use metadata_exists() for definitive check - more reliable than checking value
		    if (!metadata_exists('post', $post_id, '_sln_booking_origin_source')) {
			    $booking->setMeta('origin_source', sanitize_text_field($_POST['_sln_booking_origin_source']));
		    }
	    }
	    
	    // Track the WordPress user who created the booking (set once)
	    // This is different from post_author which is the customer
	    if (!$booking->getMeta('created_by_user_id')) {
		    $booking->setMeta('created_by_user_id', get_current_user_id());
	    }
	    
	    // Track the last editor and edit time (updates on every save)
	    $current_user_id = get_current_user_id();
	    if ($current_user_id) {
		    $booking->setMeta('last_edited_by_user_id', $current_user_id);
		    $booking->setMeta('last_edited_at', current_time('mysql'));
	    }

        if(($customer = $booking->getCustomer())){
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
        $services = array();
        $services_ids = array_map('intval',$data['services']);
        if($services_ids)
        foreach ($services_ids as $key => $serviceId) {
            if($serviceId == 0){
                continue;
            }
            $duration      = SLN_Func::convertToHoursMins($data['duration'][$serviceId]);
            $breakDuration = SLN_Func::convertToHoursMins($data['break_duration'][$serviceId]);

            if(isset($data['attendants'])){
                $attendant = $data['attendants'][$serviceId];
            }elseif(isset($data['attendant'])){
                $attendant = $data['attendant'];
            }
            $service = $this->getPlugin()->getInstance()->createService($serviceId);
            $service = apply_filters('sln.booking_services.buildService', $service);
            if($attendant == 0 && $this->getPlugin()->getSettings()->isAttendantsEnabled() && $service->isAttendantsEnabled()){
                continue;
            }
            $services[] = array(
                'service' => $serviceId,
                'attendant' => $attendant,
                'price' => $data['price'][$serviceId],
                'duration' => $duration,
                'break_duration' => $breakDuration,
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

