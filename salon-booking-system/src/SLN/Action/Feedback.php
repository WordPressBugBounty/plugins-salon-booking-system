<?php

class SLN_Action_Feedback
{
    /** @var SLN_Plugin */
    private $plugin;
    private $mode;
    private $interval = '+1 days';

    public function __construct(SLN_Plugin $plugin) {
        $this->plugin = $plugin;
    }
    
    public function execute() {
        SLN_TimeFunc::startRealTimezone();

        $type = $this->mode;
        $p = $this->plugin;
        $feedback_reminder_mail = $p->getSettings()->get( 'feedback_email' );
        $feedback_reminder_sms = $p->getSettings()->get( 'feedback_sms' );
        if ($feedback_reminder_mail || $feedback_reminder_sms) {
            $p->addLog( 'feedback reminder execution' );
            foreach ( $this->getBookings() as $booking ) {
                if($feedback_reminder_mail) $this->sendMail( $booking );
                if($feedback_reminder_sms) $this->sendSms( $booking );
                $p->addLog( 'feedback reminder sent to ' . $booking->getId() );
                $booking->setMeta('feedback', true);
            }

            $p->addLog( 'feedback reminder execution ended' );
        }

        SLN_TimeFunc::endRealTimezone();
    }

    /**
     * @return SLN_Wrapper_Booking[]
     * @throws Exception
     */
    private function getBookings() {
        // Look at bookings from the last 7 days to catch any missed by cron failures
        // This provides a retry mechanism if the cron doesn't run on a particular day
        $start_day = new SLN_DateTime( '-7 days' );
        $end_day = new SLN_DateTime( '-1 day' );

        $statuses = array( SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED, SLN_Enum_BookingStatus::PAY_LATER );

        /** @var SLN_Repository_BookingRepository $repo */
        $repo = $this->plugin->getRepository( SLN_Plugin::POST_TYPE_BOOKING );
        $tmp = $repo->get(
            array(
                'post_status' => $statuses,
                'day@min'     => $start_day,
                'day@max'     => $end_day,
            )
        );
        $ret = array();
        $now = new SLN_DateTime('now');
        
        $feedback_reminder_mail = $this->plugin->getSettings()->get( 'feedback_email' );
        $feedback_reminder_sms = $this->plugin->getSettings()->get( 'feedback_sms' );
        $custom_feedback_url = $this->plugin->getSettings()->get( 'custom_feedback_url' );
        
        foreach ( $tmp as $booking ) {
            $done = $booking->getMeta('feedback');
            
            // Skip if feedback already sent
            if ($done) {
                continue;
            }
            
            // Check if booking was at least 1 day ago
            $booking_date = $booking->getDate();
            $days_since_booking = $now->diff($booking_date)->days;
            
            if ($days_since_booking < 1) {
                continue; // Too recent
            }

            $isRegisteredCustomer = SLN_Wrapper_Customer::isCustomer( $booking->getUserId() );
            
            // Registered customers can always receive feedback (they can use customer login hash)
            if ( $isRegisteredCustomer ) {
                $ret[] = $booking;
            }
            // Guest bookings can only receive feedback if custom URL is configured
            // (otherwise they have no way to submit feedback without a login hash)
            else if ( !empty($custom_feedback_url) ) {
                $hasEmail = !empty($booking->getEmail());
                $hasPhone = !empty($booking->getPhone());
                
                // Include guest if they have email (for email) or phone (for SMS)
                if ( ($feedback_reminder_mail && $hasEmail) || 
                     ($feedback_reminder_sms && $hasPhone) ) {
                    $ret[] = $booking;
                }
            }
            // Otherwise skip guest bookings (no way for them to submit feedback)
        }
        return $ret;
    }

    private function sendSms( $booking ) {
        $p = $this->plugin;
        $p->sms()->send(
            $booking->getPhone(),
            $p->loadView('sms/feedback', compact('booking'))
        );
    }

    private function sendMail( $booking ) {
        $p = $this->plugin;
        $p->sendMail('mail/feedback', compact('booking'));
    }
}