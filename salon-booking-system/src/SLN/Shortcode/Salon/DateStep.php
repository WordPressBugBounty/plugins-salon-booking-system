<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Shortcode_Salon_DateStep extends SLN_Shortcode_Salon_Step
{

    protected function getViewData(){
        $step = $this->getStep();
        $rescheduledErrors = SLN_Action_RescheduleBooking::getErrors();
        SLN_Action_RescheduleBooking::clearErrors();
        
        SLN_TimeFunc::startRealTimezone();
        $plugin = $this->getPlugin();
        $bb = $plugin->getBookingBuilder();
        $intervals = $plugin->getIntervals($bb->getDateTime());
        $date = $intervals->getSuggestedDate();
        $customerTimezone = $plugin->getSettings()->isDisplaySlotsCustomerTimezone() ? $bb->get('customer_timezone') : '';
        
        if($plugin->getSettings()->isFormStepsAltOrder()){
            $obj = new SLN_Action_Ajax_CheckDateAlt($plugin);
            $obj->setDate(SLN_Func::filter($date, 'date'))->setTime(SLN_Func::filter($date, 'time'));
            $intervalsArray = $obj->getintervalsArray($customerTimezone);
            $date = new SLN_DateTime($intervalsArray['suggestedYear'].'-'.$intervalsArray['suggestedMonth'].'-'.$intervalsArray['suggestedDay'].' '.$intervalsArray['suggestedTime']);
            $dateTime = $customerTimezone ? (new SLN_DateTime($date, SLN_Func::createDateTimeZone($customerTimezone)))->setTimezone(SLN_DateTime::getWpTimezone()) : $date;
            $this->addErrors($obj->checkDateTimeServicesAndAttendants($bb->getAttendantsIds(), $dateTime));
        }else{
            $intervalsArray = $intervals->toArray($customerTimezone);
        }
        if(!$plugin->getSettings()->isFormStepsAltOrder() && !$intervalsArray['times']){
            $hb = $plugin->getAvailabilityHelper()->getHoursBeforeHelper()->getToDate();
            $this->addError(__('No more slots available until', 'salon-booking-system'). ' '. $plugin->format()->datetime($hb));
        }
        return array(
            'formAction' => add_query_arg(array('sln_step_page' => $this->getStep())),
            'backUrl'           => apply_filters('sln_shortcode_step_view_data_back_url', add_query_arg(array('sln_step_page' => $this->getShortcode()->getPrevStep())), $this->getStep()),
            'submitName'        => 'submit_' . $step,
            'step'              => $this,
            'errors'            => $this->getErrors(),
            'additional_errors' => array_merge($this->getAddtitionalErrors(), $rescheduledErrors),
            'settings'          => $plugin->getSettings(),
            'intervalsArray' => $intervalsArray,
            'date' => $date,
        );
    }

    protected function dispatchForm()
    {
        $bb     = $this->getPlugin()->getBookingBuilder();
        if(isset($_POST['sln'])){
                $date   = SLN_Func::filter(sanitize_text_field( wp_unslash( $_POST['sln']['date']  ) ), 'date');
                $time   = SLN_Func::filter(sanitize_text_field( wp_unslash( $_POST['sln']['time']  ) ), 'time');
                $timezone = SLN_Func::filter(sanitize_text_field( wp_unslash( $_POST['sln']['customer_timezone']  ) ), '');
        }
        if ($this->getPlugin()->getSettings()->isDisplaySlotsCustomerTimezone() && $timezone) {
            $dateTime = (new SLN_DateTime(SLN_Func::filter($date, 'date') . ' ' . SLN_Func::filter($time, 'time'.':00'), SLN_Func::createDateTimeZone($timezone)))->setTimezone(SLN_DateTime::getWpTimezone());
            $date = SLN_Func::filter($this->getPlugin()->format()->date($dateTime), 'date');
            $time = SLN_Func::filter($this->getPlugin()->format()->time($dateTime), 'time');
        }
        $bb
            ->removeLastId()
            ->setDate($date)
            ->setTime($time)
            ->setCustomerTimezone($timezone);
        $obj = new SLN_Action_Ajax_CheckDate($this->getPlugin());
        $obj
            ->setDate($date)
            ->setTime($time)
            ->execute();
        foreach ($obj->getErrors() as $err) {
            $this->addError($err);
        }
        if (!$this->getErrors()) {
            $bb->save();

            return true;
        }
    }

    public function getTitleKey(){
        return 'When do you want to come?';
    }

    public function getTitleLabel(){
        return __('When do you want to come?', 'salon-booking-system');
    }
}
