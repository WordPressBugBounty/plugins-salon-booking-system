<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

use Salon\Util\Date;
use Salon\Util\Time;


class SLN_Action_Ajax_CheckDate extends SLN_Action_Ajax_Abstract
{
    protected $date;
    protected $time;
    protected $errors = array();
    protected $duration;
    protected $booking;

    public function setDuration(Time $duration){
        $this->duration = $duration;
        return $this;
    }

    public function execute()
    {
        if (!isset($this->date)) {
            if(isset($_POST['sln'])){
                $date = isset($_POST['sln']['date']) ? sanitize_text_field(wp_unslash($_POST['sln']['date'])) : '';
                $time = isset($_POST['sln']['time']) ? sanitize_text_field(wp_unslash($_POST['sln']['time'])) : '';
                
                // Only set if not empty to prevent errors
                if (!empty($date)) {
                    $this->date = $date;
                }
                if (!empty($time)) {
                    $this->time = $time;
                }
                
                $settings = SLN_Plugin::getInstance()->getSettings();
                $settings->set( 'debug', $_POST['sln']['debug'] ?? false );
                $settings->save();
            }
            if(isset($_POST['_sln_booking_date'])) {
                $date = sanitize_text_field(wp_unslash($_POST['_sln_booking_date']));
                $time = isset($_POST['_sln_booking_time']) ? sanitize_text_field(wp_unslash($_POST['_sln_booking_time'])) : '';
                
                // Only set if not empty to prevent errors
                if (!empty($date)) {
                    $this->date = $date;
                }
                if (!empty($time)) {
                    $this->time = $time;
                }
            }
            $timezone   = $this->plugin->getSettings()->isDisplaySlotsCustomerTimezone() ? sanitize_text_field(wp_unslash($_POST['sln']['customer_timezone'])) : '';
            if (!empty($timezone) && isset($this->date) && isset($this->time)) {
                $dateTime = (new SLN_DateTime(SLN_Func::filter($this->date, 'date') . ' ' . SLN_Func::filter($this->time, 'time'.':00'), SLN_Func::createDateTimeZone($timezone)))->setTimezone(SLN_DateTime::getWpTimezone());
                $this->date = $this->plugin->format()->date($dateTime);
                $this->time = $this->plugin->format()->time($dateTime);
            }
        }

        $this->checkDateTime();
        if ($errors = $this->getErrors()) {
            $ret = compact('errors');
        } else {
            $ret = array('success' => 1);
        }
        
        // Check if current user is administrator or salon staff
        $currentUser = wp_get_current_user();
        $isAdminOrStaff = current_user_can('administrator') || 
                          in_array(SLN_Plugin::USER_ROLE_STAFF, $currentUser->roles);
        
        // Send flag to frontend indicating user can override validation
        $ret['can_override_validation'] = $isAdminOrStaff;

        // FIX RISCHIO #1: Sempre ritornare intervals, anche senza timezone
        // Problema precedente: se !isset($timezone), ritornava array vuoto
        // Questo causava AJAX refresh a fallire silently, usando dati stale dall'HTML
        // Soluzione: Sempre chiamare getIntervalsArray(), passando stringa vuota se no timezone
        $ret['intervals'] = $this->getIntervalsArray(isset($timezone) ? $timezone : '');

        $isFromAdmin = isset($_POST['_sln_booking_date']);
        if (!$isFromAdmin) {
            $suggestedDate = isset($ret['intervals']['suggestedDate']) ? $ret['intervals']['suggestedDate'] : null;
            $suggestedTime = isset($ret['intervals']['suggestedTime']) ? $ret['intervals']['suggestedTime'] : null;
        
            if ($suggestedDate !== $this->date || $suggestedTime !== $this->time) {
                unset($ret['errors']);
                $ret['success'] = 1;
            }
        }

        if ( true == SLN_Plugin::getInstance()->getSettings()->get( 'debug' ) && current_user_can( 'administrator' ) ){
            $ret['debug']['times'] = SLN_Helper_Availability_AdminRuleLog::getInstance()->getLog();
            $ret['debug']['dates'] = SLN_Helper_Availability_AdminRuleLog::getInstance()->getDateLog();
            SLN_Helper_Availability_AdminRuleLog::getInstance()->clear();
        }

        return $ret;
    }

    public function getIntervals() {
        return $this->plugin->getIntervals($this->getDateTime(), $this->duration);
    }

    public function getIntervalsArray($timezone = '') {
        return $this->getIntervals()->toArray($timezone);
    }

    public function checkDateTime()
    {

        $plugin = $this->plugin;
        $date   = $this->getDateTime();
        $ah   = $plugin->getAvailabilityHelper();
        $hb   = $ah->getHoursBeforeHelper();
        $from = $hb->getFromDate();
        $to   = $hb->getToDate();
        if (!$hb->isValidFrom($date)) {
            $txt = $plugin->format()->datetime($from);
            $this->addError(sprintf(__('The date is too near, the minimum allowed is:', 'salon-booking-system') . '<br /><strong>%s</strong>', $txt));
        } elseif (!$hb->isValidTo($date)) {
            $txt = $plugin->format()->datetime($to);
            $this->addError(sprintf(__('The date is too far, the maximum allowed is:', 'salon-booking-system') . '<br /><strong>%s</strong>', $txt));
        } elseif (!$ah->getItems()->isValidDatetime($date) || !$ah->getHolidaysItems()->isValidDatetime($date)) {
            $txt = $plugin->format()->datetime($date);
            $this->addError(sprintf(__('We are unavailable at:', 'salon-booking-system') . '<br /><strong>%s</strong>', $txt));
        } else {
            $ah->setDate($date, $this->booking);
            if (!$ah->isValidDate( Date::create($date))) {
                $this->addError(
                    __(
                        'There are no time slots available today - Please select a different day',
                        'salon-booking-system'
                    )
                );
            } elseif (!$ah->isValidTime($this->getDateTime())) {
                $this->addError(
                    __(
                        'There are no time slots available for this period - Please select a  different hour',
                        'salon-booking-system'
                    )
                );
            }
        }
    }

    protected function addError($err)
    {
        $this->errors[] = $err;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param mixed $date
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @param mixed $time
     * @return $this
     */
    public function setTime($time)
    {
        $this->time = $time;

        return $this;
    }

    protected function getDateTime()
    {
        $date = isset($this->date) ? $this->date : null;
        $time = isset($this->time) ? $this->time : null;
        
        // Validate date is not empty
        if (empty($date)) {
            throw new Exception(
                'Missing date in request. Date: "' . ($date ?? 'null') . '". Please select a date before proceeding.'
            );
        }
        
        // If time is empty, use a default placeholder time
        // This allows checking date availability without requiring a specific time
        if (empty($time)) {
            $time = '00:00';
        }
        
        $ret = new SLN_DateTime(
            SLN_Func::filter($date, 'date') . ' ' . SLN_Func::filter($time, 'time')
        );
        return $ret;
    }

    public function setBooking(SLN_Wrapper_Booking $booking){
        $this->booking = $booking;
        return $this;
    }

}
