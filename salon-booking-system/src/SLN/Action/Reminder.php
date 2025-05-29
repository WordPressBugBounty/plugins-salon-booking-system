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
        $sms = $this->plugin->sms();
        $sms->clearError();
        if(!empty($booking->getPhone())){
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
    }

    private function sendEmail($booking){
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
}
