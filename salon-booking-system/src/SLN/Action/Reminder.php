<?php

class SLN_Action_Reminder
{
    const EMAIL = 'email';
    const SMS = 'sms';

    /** @var SLN_Plugin */
    private $plugin;
    private $mode;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        add_action('wp_mail_failed', array($this, 'sendEmailError'));
    }

    public function executeSms()
    {
        $this->mode = self::SMS;

        return $this->execute();
    }

    public function executeEmail()
    {
        $this->mode = self::EMAIL;

        return $this->execute();
    }

    private function execute()
    {
        SLN_TimeFunc::startRealTimezone();

        $type = $this->mode;
        $p = $this->plugin;
        $remind = $p->getSettings()->get($type.'_remind');
        if($remind){
            $p->addLog($type.'reminder execution');
            if (self::SMS === $type && SLN_Enum_CheckoutFields::getField('phone')->isHiddenOrNotRequired()) {
                $p->addLog($type.' phone field is hidden or not required');
                foreach ($this->getBookings() as $booking) {
                    $booking->setMeta($type.'_remind', false);
                    $booking->setMeta($type.'_remind_error', $type.' phone field is hidden or not required');
                }
            }else{
                foreach($this->getBookings() as $booking){
                    $booking->setMeta($type.'_remind', true);
                    $booking->setMeta($type. '_remind_utc_time', (new SLN_DateTime())->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
                    try{
                        switch($type){
                            case self::EMAIL: $this->sendEmail($booking); break;
                            case self::SMS: $this->sendSms($booking); break;
                        }
                    }catch(Exception $ex){
                        $booking->setMeta($type. '_remind', false);
                        $booking->setMeta($type. '_remind_utc_time', false);
                        $booking->setMeta($type.'_remind_error', $ex->getMessage());
                    }
                }
            }
            $p->addLog($type.'reminder execution ended');
        }
        SLN_TimeFunc::endRealTimezone();
    }

    private function sendSms($booking){
        // Activate the booking's shop so [SALON NAME] and other per-shop values
        // resolve correctly. The reminder cron has no "current shop" otherwise.
        $shopState = $this->plugin->applyShopContextFromBooking($booking);
        try {
            $sms = $this->plugin->sms();
            $sms->clearError();
            if(!empty($booking->getPhone()) && $booking->getNotifyCustomer()){
                $sms->send(
                    $booking->getPhone(),
                    $this->plugin->loadView('sms/remind', compact('booking')),
                    $booking->getMeta('sms_prefix')
                );
            } elseif(!empty($this->plugin->getSettings()->get('sms_new_number'))) {
                $sms->send(
                    $this->plugin->getSettings()->get('sms_new_number'),
                    $this->plugin->loadView('sms/remind', compact('booking')),
                    $booking->getMeta('sms_prefix')
                );
            }
            if($sms->hasError()){
                throw new Exception(esc_html($sms->getError()));
            }
        } finally {
            $this->plugin->restoreShopContext($shopState);
        }
    }

    private function sendEmail($booking){
        if ( ! $booking->getNotifyCustomer() ) return;
        $this->plugin->addLog('email reminder started to be sent to '.$booking->getId());
        $args = array('booking' => $booking, 'remind' => true);
        $booking->setMeta('email_remind', true);

        $this->plugin->sendMail('mail/summary', $args);
    }

    public function sendEmailError(WP_Error $error){
        $data = $error->get_error_data();
        $headers = $data['headers'];
        if($headers['remind']){
            $bookingId = intval($headers['booking-id']);
            $booking = $this->plugin->createBooking($bookingId);
            $this->plugin->addLog('email reminder started to be sent to '. $bookingId);
            $booking->setMeta('email_remind', false);
            $booking->setMeta('email_remind_utc_time', false);
            $booking->setMeta('email_remind_error', $error->get_error_message());
        }
    }

    /**
     * @return SLN_Wrapper_Booking[]
     * @throws Exception
     */
    private function getBookings()
    {
        $min = $this->getMin();
        $max = $this->getMax();

        $statuses = array(SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED, SLN_Enum_BookingStatus::PAY_LATER);

        /** @var SLN_Repository_BookingRepository $repo */
        $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING);
        $tmp = $repo->get(
            array(
                'post_status' => $statuses,
                'day@min'     => $min,
                'day@max'     => $max
            )
        );
        $ret = array();
        foreach ($tmp as $booking) {
            $d = $booking->getStartsAt();
            $done = $booking->getMeta($this->mode.'_remind');
            if ($d >= $min && $d <= $max && !$done) {
                $ret[] = $booking;
            }
        }

        return $ret;
    }


    /**
     * @return DateTime
     */
    private function getMin()
    {
        return new SLN_DateTime();
    }

    /**
     * @return DateTime
     */
    private function getMax()
    {
        $interval = $this->plugin->getSettings()->get($this->mode.'_remind_interval');
        $date = new SLN_DateTime();
        $date->modify($interval);

        return $date;
    }

    /**
     * Return the bookings the SMS reminder cron would consider for the current
     * reminder window. Same selection as getBookings() for SMS, but exposed for
     * diagnostics and able to include bookings whose reminder was already sent.
     *
     * @param bool $includeAlreadySent
     * @return SLN_Wrapper_Booking[]
     * @throws Exception
     */
    public function getSmsReminderCandidates($includeAlreadySent = false)
    {
        $this->mode = self::SMS;

        $min = $this->getMin();
        $max = $this->getMax();

        $statuses = array(SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED, SLN_Enum_BookingStatus::PAY_LATER);

        /** @var SLN_Repository_BookingRepository $repo */
        $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING);
        $tmp  = $repo->get(
            array(
                'post_status' => $statuses,
                'day@min'     => $min,
                'day@max'     => $max,
            )
        );

        $ret = array();
        foreach ($tmp as $booking) {
            $d    = $booking->getStartsAt();
            $done = $booking->getMeta('sms_remind');
            if ($d >= $min && $d <= $max && ($includeAlreadySent || !$done)) {
                $ret[] = $booking;
            }
        }

        return $ret;
    }

    /**
     * Dry-run diagnostic (SENDS NOTHING). For each upcoming SMS-reminder booking,
     * capture exactly how the location ([SALON NAME]) resolves BEFORE and AFTER the
     * booking's Multi-Shops shop context is applied, and render the reminder text.
     * This pinpoints where a wrong location comes from:
     *   - meta_shop empty              -> booking never stored its shop
     *   - salon_name_after still wrong -> setCurrentShop() isn't driving gen_name
     *   - salon_name_after correct but rendered_sms wrong -> template/other issue
     *
     * @param bool $includeAlreadySent
     * @return array[] one row per booking
     * @throws Exception
     */
    public function diagnoseSmsLocations($includeAlreadySent = true)
    {
        SLN_TimeFunc::startRealTimezone();

        $p    = $this->plugin;
        $s    = $p->getSettings();
        $rows = array();

        foreach ($this->getSmsReminderCandidates($includeAlreadySent) as $booking) {
            $id       = $booking->getId();
            $rawShop  = get_post_meta($id, '_sln_booking_shop', true);
            $metaShop = $booking->getMeta('shop');

            $genNameBefore   = $s->get('gen_name');
            $salonNameBefore = $s->getSalonName($booking);

            $state = $p->applyShopContextFromBooking($booking);

            $genNameAfter   = $s->get('gen_name');
            $salonNameAfter = $s->getSalonName($booking);

            try {
                $rendered = trim($p->loadView('sms/remind', compact('booking')));
            } catch (Exception $e) {
                $rendered = 'RENDER ERROR: ' . $e->getMessage();
            }

            $p->restoreShopContext($state);

            $row = array(
                'booking_id'           => $id,
                'starts_at'            => $booking->getStartsAt()->format('Y-m-d H:i'),
                'status'               => $booking->getStatus(),
                'raw_shop_meta'        => is_scalar($rawShop) ? (string) $rawShop : wp_json_encode($rawShop),
                'meta_shop'            => is_scalar($metaShop) ? (string) $metaShop : wp_json_encode($metaShop),
                'shop_context_applied' => !empty($state['set']) ? 'yes' : 'no',
                'gen_name_before'      => $genNameBefore,
                'gen_name_after'       => $genNameAfter,
                'salon_name_before'    => $salonNameBefore,
                'salon_name_after'     => $salonNameAfter,
                'rendered_sms'         => $rendered,
            );

            $rows[] = $row;
            SLN_Plugin::addLog('[SMS_LOC_DIAG] ' . wp_json_encode($row));
        }

        SLN_TimeFunc::endRealTimezone();

        return $rows;
    }
}
