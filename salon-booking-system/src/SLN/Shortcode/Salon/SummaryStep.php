<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Shortcode_Salon_SummaryStep extends SLN_Shortcode_Salon_Step
{
    const SLOT_UNAVAILABLE    = 'slotunavailable';
    const SERVICES_DATA_EMPTY = 'servicesdataempty';

    private $op;

    protected function dispatchForm()
    {
        $bb     = $this->getPlugin()->getBookingBuilder()->getLastBooking();
        $value = isset($_POST['sln']) && isset($_POST['sln']['note']) ? sanitize_text_field(wp_unslash($_POST['sln']['note'])) : '';
        $plugin = $this->getPlugin();
        $isCreateAfterPay = /*$plugin->getSettings()->get('create_booking_after_pay') &&*/ $plugin->getSettings()->isPayEnabled();
        if(isset($_GET['sln_booking_id']) && intval($_GET['sln_booking_id'])){
            $bb = $plugin->createBooking(intval(sanitize_text_field($_GET['sln_booking_id'])));
        }

        if(empty($bb) && isset($_GET['op'])){
            $bb = $plugin->createBooking(explode('-', sanitize_text_field($_GET['op']))[1]);
        }
        if(!empty($value)){
            $bb->setMeta('note', SLN_Func::filter($value));
        }
        $handler = new SLN_Action_Ajax_CheckDateAlt( $plugin );

        $plugin->getBookingBuilder()->clear($bb->getId());
        $paymentMethod = $plugin->getSettings()->isPayEnabled() ? SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) : false;
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : null;

        if($mode == 'confirm' || empty($paymentMethod) || $bb->getAmount() <= 0.0){
            $errors = $handler->checkDateTimeServicesAndAttendants($bb->getAttendantsIds(), $bb->getStartsAt());
            if(!empty($errors)){
                $this->addError(self::SLOT_UNAVAILABLE);
                return false;
            }
            if($bb->getStatus() == SLN_Enum_BookingStatus::DRAFT){
                ///$bb->setStatus($bb->getCreateStatus()); // SLN_Wrapper_Booking::getCreateStatus
                if($bb->getAmount() <= 0.0 && !SLN_Plugin::getInstance()->getSettings()->get('confirmation')){
                    $bb->setStatus(SLN_Enum_BookingStatus::CONFIRMED);
                } else if(SLN_Plugin::getInstance()->getSettings()->get('confirmation')) {
                    $bb->setStatus(SLN_Enum_BookingStatus::PENDING);
                } else if(empty($paymentMethod)) {
                    $bb->setStatus(SLN_Enum_BookingStatus::CONFIRMED);
                }  else {
                    $bb->setStatus(SLN_Enum_BookingStatus::PAID);
                }
            }
            $bb->setPrepaidServices();
            return !$this->hasErrors();
        } elseif($mode == 'later'){
            $errors = $handler->checkDateTimeServicesAndAttendants($bb->getAttendantsIds(), $bb->getStartsAt());
            if(!empty($errors)){
                $this->addError(self::SLOT_UNAVAILABLE);
                return false;
            }
            if(in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::PENDING_PAYMENT, SLN_Enum_BookingStatus::DRAFT))){
                if($bb->getAmount() > 0.0){
                    $bb->setStatus(SLN_Enum_BookingStatus::PAY_LATER);
                }else{
                    $bb->setPrepaidServices();
                    $bb->setStatus($bb->getCreateStatus()); // SLN_Wrapper_Booking::getCreateStatus
                }
            }
            
            return !$this->hasErrors();
        }elseif(isset($_GET['op']) || $mode){
            if($error = $paymentMethod->dispatchThankyou($this, $bb)){
                $this->addError($error);
            }
        }
        if(!empty($paymentMethod) && in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::PAY_LATER, SLN_Enum_BookingStatus::PENDING_PAYMENT))){
            return false;
        }
        if($bb->getStatus() == SLN_Enum_BookingStatus::DRAFT){
            $bb->setStatus($bb->getCreateStatus());
        }

        return !$this->hasErrors();
    }

    public function render()
    {
        $bb = $this->getPlugin()->getBookingBuilder();
        if($bb->get('services') && $bb->isValid()){
            $data = $this->getViewData();
            $service_ids   = implode('-', $bb->getServicesIds());
            $attendant_ids = implode('-', array_values($bb->getAttendantsIds()));
            $start_time    = $bb->getDateTime()->format('Y-m-d H:i:s');

            $lock_key = 'booking_lock_' . md5($service_ids . '_' . $attendant_ids . '_' . $start_time);

            if ( get_transient($lock_key) ) {
                $this->addError(self::SLOT_UNAVAILABLE);
                $bb->create(SLN_Enum_BookingStatus::DRAFT);
                return parent::render();
            }

            set_transient($lock_key, 1, 15);

            do_action('sln.shortcode.summary.dispatchForm.before_booking_creation', $this, $bb);
            if ( ! $this->hasErrors() ) {
                $bb->create(SLN_Enum_BookingStatus::DRAFT);
            }
            do_action('sln.shortcode.summary.dispatchForm.after_booking_creation', $bb);
            return parent::render();
        }elseif($bb->getLastBooking()){
            $data = $this->getViewData();
            $bb = $bb->getLastBooking();
            $custom_url = apply_filters('sln.shortcode.render.custom_url', false, $this->getStep(), $this->getShortcode(), $bb);
            if ($custom_url) {
                $this->redirect($custom_url);
                wp_die();
            }
            return parent::render();
        }else{
            if(empty($bb->get('services'))){
                $this->addError(self::SERVICES_DATA_EMPTY);
                $this->redirect(add_query_arg(array('sln_step_page' => 'services')));
            }else{
                $this->addError(self::SLOT_UNAVAILABLE);
                return parent::render();
            }
        }
    }

    public function setOp($op){
        $this->op = $op;
    }

    public function getViewData(){
        $ret = parent::getViewData();
        $formAction = $ret['formAction'];

        $laterUrl = add_query_arg(
            array(
                'mode' => 'later',
                'submit_'. $this->getStep() => 'next',
            ),
            $formAction
        );
        $confirmUrl = add_query_arg(
            array(
                'mode' => 'confirm',
                'submit_'. $this->getStep() => 'next',
            ),
            $formAction
        );
        $confirmUrl = apply_filters('sln.booking.thankyou-step.get-confirm-url', $confirmUrl);
        

        $data = array(
            'booking' => $this->getPlugin()->getBookingBuilder()->getLastBooking(),
            'confirmUrl' => $confirmUrl,
            'laterUrl' => $laterUrl,
        );
        
        if($this->getPlugin()->getSettings()->isPayEnabled()){
            $payUrl = $this->getPlugin()->getSettings()->getPayPageId()? get_permalink($this->getPlugin()->getSettings()->getPayPageId()) : SLN_Func::currPageUrl();
            $payUrl = add_query_arg(
                array_merge($_GET, array(
                    'mode' => $this->getPlugin()->getSettings()->getPaymentMethod(),
                    'submit_'. $this->getStep() => 'next',
                    'sln_step_page' => $this->getStep(),
                )),
                $payUrl
            );
            $payUrl = apply_filters('sln.booking.thankyou-step.get-pay-url', $payUrl);
            $data['payUrl'] = $payUrl;
            $data['payOp'] = $this->op;
        }
        return array_merge($ret, $data);
    }

    public function redirect($url)
    {
        if ($this->isAjax()) {
            throw new SLN_Action_Ajax_RedirectException($url);
        } else {
            wp_redirect($url);die;
        }
    }

    public function isAjax()
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    public function getTitleKey(){
        return 'Booking summary';
    }

    public function getTitleLabel(){
        return __('Booking summary', 'salon-booking-system');
    }
}
