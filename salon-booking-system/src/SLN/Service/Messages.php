<?php

class SLN_Service_Messages
{
    private $plugin;
    private $disabled = false;
    private $sendToAdmin = true;
    private $sendToCustomer = true;

    private static $statusForSummary = array(
        SLN_Enum_BookingStatus::PAID,
        SLN_Enum_BookingStatus::PAY_LATER,
        SLN_Enum_BookingStatus::PENDING,
    );

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        add_action('sln.booking.setStatus', array($this, 'sendConfirmedmation'), 10, 3);
    }

    public function setDisabled($bool)
    {
        $this->disabled = $bool;
    }

    /**
     * TEMP DIAGNOSTIC: trace "Do not notify customer" behaviour.
     * Logs the raw meta, the resolved getNotifyCustomer() gate, the disabled
     * flag and the current status so we can see exactly why a customer message
     * is (or isn't) being sent. Remove once the issue is confirmed fixed.
     */
    private function logDontNotifyDiag($where, $booking)
    {
        if (!($booking instanceof SLN_Wrapper_Booking)) {
            return;
        }
        $bookingId = $booking->getId();
        SLN_Plugin::addLog(sprintf(
            '[DONT_NOTIFY_DIAG] %s | booking #%s | disabled=%s | raw_meta(_sln_booking_dont_notify_customer)=%s | getNotifyCustomer=%s | sendToCustomer(prop)=%s | status=%s | phone=%s',
            $where,
            $bookingId,
            $this->disabled ? '1' : '0',
            var_export($bookingId ? get_post_meta($bookingId, '_sln_booking_dont_notify_customer', true) : null, true),
            $booking->getNotifyCustomer() ? 'true' : 'false',
            $this->sendToCustomer ? 'true' : 'false',
            $booking->getStatus(),
            $booking->getPhone()
        ));
    }

    public function setSendToAdmin($bool)
    {
        $this->sendToAdmin = $bool;
    }

    public function setSendToCustomer($bool)
    {
        $this->sendToCustomer = $bool;
    }

    public function sendByStatus(SLN_Wrapper_Booking $booking, $status)
    {
        $this->logDontNotifyDiag('sendByStatus() ENTER status=' . $status, $booking);
        if ($this->disabled) {
            SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] sendByStatus() ABORTED: messages service disabled | booking #' . $booking->getId());
            return;
        }

	if ($booking->getMeta('disable_status_change_email')) {
	    $booking->setMeta('disable_status_change_email', 0);
	    return;
	}

        do_action('sln.messages.before_booking_send_message', $booking);
        $p = $this->plugin;
        $sendToAdmin = $this->sendToAdmin;
        $sendToCustomer = $this->sendToCustomer;
        if ($status == SLN_Enum_BookingStatus::CONFIRMED) {
            $this->sendBookingConfirmed($booking, $sendToAdmin, $sendToCustomer);
        } elseif ($status == SLN_Enum_BookingStatus::CANCELED) {
            if($booking->getNotifyCustomer() && $sendToCustomer) {
                SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] >>> CUSTOMER EMAIL SENT (mail/status_canceled) | booking #' . $booking->getId());
                $p->sendMail('mail/status_canceled', compact('booking'));
            }
            $forAdmin = true;
            $p->sendMail('mail/status_canceled', compact('booking', 'forAdmin', 'sendToAdmin'));
            $this->sendSmsCanceledBooking($booking);
        } elseif ($status == SLN_Enum_BookingStatus::PENDING_PAYMENT && $booking->getNotifyCustomer() && $sendToCustomer) {
            $settings = $this->plugin->getSettings();
            if (!$settings->get('disable_first_pending_payment_email_to_customer')) {
                SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] >>> CUSTOMER EMAIL SENT (mail/status_pending_payment) | booking #' . $booking->getId());
                $p->sendMail('mail/status_pending_payment', compact('booking'));
            }
        } elseif (in_array($status, self::$statusForSummary)) {
            $this->sendSummaryMail($booking, $sendToAdmin, $sendToCustomer);
            $this->sendSmsBooking($booking, $sendToAdmin, $sendToCustomer);
        }
    }

    public function sendConfirmedmation($booking, $oldStatus, $newStatus){
        if($oldStatus == SLN_Enum_BookingStatus::PENDING && $newStatus == SLN_Enum_BookingStatus::CONFIRMED){
            if ($this->plugin->getSettings()->get('confirmation') && $booking->getMeta('origin_source') == 'Direct' && false) {
                $sendToAdmin = $this->sendToAdmin;
                $sendToCustomer = $this->sendToCustomer;
                $this->plugin->sendMail('mail/status_confirmed', compact('booking', 'sendToAdmin', 'sendToCustomer'));
                $this->sendSmsBooking($booking, $sendToAdmin, $sendToCustomer);
            }
        }
    }

    private function sendBookingConfirmed(SLN_Wrapper_Booking $booking, $sendToAdmin = true, $sendToCustomer = true)
    {
        if ($this->plugin->getSettings()->get('confirmation')) {
            if ($booking->getNotifyCustomer() && $sendToCustomer) {
                SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] >>> CUSTOMER EMAIL SENT (mail/status_confirmed) | booking #' . $booking->getId());
                $this->plugin->sendMail('mail/status_confirmed', compact('booking', 'sendToCustomer'));
            }
            if ($sendToAdmin) {
                $forAdmin = true;
                $this->plugin->sendMail('mail/status_confirmed', compact('booking', 'forAdmin', 'sendToAdmin'));
            }
        } else {
            $this->sendSummaryMail($booking, $sendToAdmin, $sendToCustomer);
        }
        $this->sendSmsBooking($booking, $sendToAdmin, $sendToCustomer);
    }

    public function sendBookingModified(SLN_Wrapper_Booking $booking) {
        $this->logDontNotifyDiag('sendBookingModified() ENTER', $booking);
        $this->sendSmsModifiedBooking($booking);
        $this->sendSummaryModifiedMail($booking);
    }

    public function sendSmsBooking($booking, $sendToAdmin = true, $sendToCustomer = true)
    {
        do_action('sln.messages.before_booking_send_message', $booking);

        $p   = $this->plugin;
        // Activate the booking's shop so per-shop settings (sms_new_attendant,
        // [SALON NAME], templates) resolve to the correct location.
        $shopState = $p->applyShopContextFromBooking($booking);
        try {
            $sms = $p->sms();
            $s   = $p->getSettings();

            if ($s->get('sms_new')) {

                $phone = $s->get('sms_new_number');
                if ($phone) {
                    $sms->send($phone, $p->loadView('sms/summary', compact('booking')));
                }

                $phone = $booking->getPhone();
                if ($phone && $booking->getNotifyCustomer() && $sendToCustomer) {
                    SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] >>> CUSTOMER SMS SENT (sms/summary or sms/pending) | booking #' . $booking->getId() . ' | phone=' . $phone);
                    if($booking->getStatus() == SLN_Enum_BookingStatus::PENDING && $s->get('confirmation')){
                        $sms->send($phone, $p->loadView('sms/pending', compact('booking')), $booking->getsmsPrefix());
                    }else{
                        $sms->send($phone, $p->loadView('sms/summary', compact('booking')), $booking->getSmsPrefix());
                    }
                }
            }

            $this->sendSmsToAttendants($booking, 'sms_new_attendant', 'sms/summary');

            do_action('sln.messages.booking_sms',$booking);
        } finally {
            $p->restoreShopContext($shopState);
        }
    }

    /**
     * Send an SMS to each attendant assigned to the booking, if the given setting
     * flag is enabled. Also logs why messages are (not) sent so the "therapists
     * receive no SMS" issue can be diagnosed from the plugin log.
     *
     * @param SLN_Wrapper_Booking $booking
     * @param string $settingKey e.g. sms_new_attendant / sms_modified_attendant / sms_canceled_attendant
     * @param string $view       SMS template to render
     * @return void
     */
    private function sendSmsToAttendants($booking, $settingKey, $view)
    {
        $p   = $this->plugin;
        $sms = $p->sms();
        $s   = $p->getSettings();

        if (!$s->get($settingKey)) {
            SLN_Plugin::addLog('[ATTENDANT_SMS_DIAG] ' . $settingKey . ' is OFF for this shop | booking #' . $booking->getId());
            return;
        }

        $tmpAttendants = $booking->getAttendants();
        $tmpAttendants = $tmpAttendants && is_array($tmpAttendants) ? $tmpAttendants : array();

        $attendants = array();

        foreach ($tmpAttendants as $a) {
            if (is_array($a)) {
                foreach ($a as $singleAttendant) {
                    $attendants[$singleAttendant->getId()] = $singleAttendant;
                }
            } else {
                $attendants[$a->getId()] = $a;
            }
        }

        if (empty($attendants)) {
            SLN_Plugin::addLog('[ATTENDANT_SMS_DIAG] no attendants assigned | booking #' . $booking->getId());
            return;
        }

        foreach ($attendants as $attendant) {

            $phone = $attendant->getPhone();

            if ($phone) {
                SLN_Plugin::addLog('[ATTENDANT_SMS_DIAG] sending ' . $view . ' to attendant #' . $attendant->getId() . ' | phone=' . $phone . ' | booking #' . $booking->getId());
                $sms->send($phone, $p->loadView($view, compact('booking')), $attendant->getSmsPrefix());
            } else {
                SLN_Plugin::addLog('[ATTENDANT_SMS_DIAG] attendant #' . $attendant->getId() . ' has NO phone number, skipped | booking #' . $booking->getId());
            }
        }
    }

    private function sendSmsModifiedBooking($booking) {
        do_action('sln.messages.before_booking_send_message', $booking);

        $p   = $this->plugin;
        $shopState = $p->applyShopContextFromBooking($booking);
        try {
            $sms = $p->sms();
            $s   = $p->getSettings();

            if ($s->get('sms_modified')) {

                $phone = $s->get('sms_new_number');
                if ($phone) {
                    $sms->send($phone, $p->loadView('sms/summary_modified', compact('booking')));
                }

                $phone = $booking->getPhone();
                if ($phone && $booking->getNotifyCustomer()) {
                    SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] >>> CUSTOMER SMS SENT (sms/summary_modified) | booking #' . $booking->getId() . ' | phone=' . $phone);
                    $sms->send($phone, $p->loadView('sms/summary_modified', compact('booking')), $booking->getSmsPrefix());
                }
            }

            $this->sendSmsToAttendants($booking, 'sms_modified_attendant', 'sms/summary_modified');

            do_action('sln.messages.modified_booking_sms',$booking);
        } finally {
            $p->restoreShopContext($shopState);
        }
    }

    private function sendSmsCanceledBooking($booking) {
        do_action('sln.messages.before_booking_send_message', $booking);

        $p   = $this->plugin;
        $shopState = $p->applyShopContextFromBooking($booking);
        try {
            $sms = $p->sms();
            $s   = $p->getSettings();

            if ($s->get('sms_canceled')) {

                $phone = $s->get('sms_new_number');
                if ($phone) {
                    $sms->send($phone, $p->loadView('sms/status_canceled', compact('booking')));
                }

                $phone = $booking->getPhone();
                if ($phone && $booking->getNotifyCustomer()) {
                    SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] >>> CUSTOMER SMS SENT (sms/status_canceled) | booking #' . $booking->getId() . ' | phone=' . $phone);
                    $sms->send($phone, $p->loadView('sms/status_canceled', compact('booking')), $booking->getSmsPrefix());
                }
            }

            $this->sendSmsToAttendants($booking, 'sms_canceled_attendant', 'sms/status_canceled');

            do_action('sln.messages.canceled_booking_sms',$booking);
        } finally {
            $p->restoreShopContext($shopState);
        }
    }

    public function sendRescheduledMail($booking)
    {
        do_action('sln.messages.before_booking_send_message', $booking);

	    $rescheduled = true;
        $updated = false;

        $p = $this->plugin;
        if($booking->getNotifyCustomer()) {
            $p->sendMail('mail/summary', compact('booking', 'rescheduled', 'updated'));
        }
        $p->sendMail('mail/summary_admin', compact('booking', 'rescheduled', 'updated'));
        
        // Send SMS notifications when customer reschedules their booking
        // This ensures customers receive SMS updates when they reschedule from "My Account" page
        // Respects sms_modified and sms_modified_attendant settings
        $this->sendSmsModifiedBooking($booking);
    }

    public function sendSummaryMail($booking, $sendToAdmin = true, $sendToCustomer = true)
    {
        do_action('sln.messages.before_booking_send_message', $booking);
        $updated = false;
        $rescheduled = false;

        $p = $this->plugin;
        if($booking->getNotifyCustomer() && $sendToCustomer) {
            SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] >>> CUSTOMER EMAIL SENT (mail/summary) | booking #' . $booking->getId());
            $p->sendMail('mail/summary', compact('booking', 'sendToCustomer', 'updated', 'rescheduled'));
        }
        $p->sendMail('mail/summary_admin', compact('booking', 'sendToAdmin', 'updated', 'rescheduled'));
        do_action('sln.messages.booking_summary_mail',$booking);
    }

    private function sendSummaryModifiedMail($booking) {
        do_action('sln.messages.before_booking_send_message', $booking);

        $p = $this->plugin;
        $updated = true;
        $rescheduled = false;
        $sendToAdmin = true;
        $sendToCustomer = $booking->getNotifyCustomer();
        if($booking->getNotifyCustomer()) {
            SLN_Plugin::addLog('[DONT_NOTIFY_DIAG] >>> CUSTOMER EMAIL SENT (mail/summary, modified) | booking #' . $booking->getId());
            $p->sendMail('mail/summary', compact('booking', 'sendToCustomer', 'updated', 'rescheduled'));
        }
        $p->sendMail('mail/summary_admin', compact('booking', 'sendToAdmin', 'updated', 'rescheduled'));
        do_action('sln.messages.booking_summary_modified_mail',$booking);
    }

    public function getStatusForSummary() {
        return self::$statusForSummary;
    }
}
