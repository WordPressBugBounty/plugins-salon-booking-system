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
        $isCreateAfterPay = $plugin->getSettings()->get('create_booking_after_pay') && $plugin->getSettings()->isPayEnabled();
        if(isset($_GET['sln_booking_id']) && intval($_GET['sln_booking_id'])){
            $bb = $plugin->createBooking(intval(sanitize_text_field($_GET['sln_booking_id'])));
        }

        if(empty($bb) && isset($_GET['op'])){
            $bb = $plugin->createBooking(explode('-', sanitize_text_field($_GET['op']))[1]);
        }
        $bb->setMeta('note', SLN_Func::filter($value));
        $plugin->getBookingBuilder()->clear($bb->getId());
        $paymentMethod = $plugin->getSettings()->isPayEnabled() ? SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) : false;
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : null;
        $isConfirmation = $plugin->getSettings()->get('confirmation') && in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::DRAFT, SLN_Enum_BookingStatus::PENDING));

        if($mode == 'confirm' || empty($paymentMethod) || $bb->getAmount() <= 0.0){
            $status = $plugin->getSettings()->get('confirmation') ?
            SLN_Enum_BookingStatus::PENDING
            : SLN_Enum_BookingStatus::CONFIRMED;

	        $status = apply_filters('sln.booking_builder.getCreateStatus', $status);
            $bb->setPrepaidServices();
            $bb->setStatus($status);
            
            return !$this->hasErrors();
        } elseif($mode == 'later'){
            if(in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::PENDING_PAYMENT, SLN_Enum_BookingStatus::DRAFT))){
                if($bb->getAmount() > 0.0){
                    $bb->setStatus(SLN_Enum_BookingStatus::PAY_LATER);
                }else{
                    $bb->setPrepaidServices();
                    $bb->setStatus(SLN_Enum_BookingStatus::CONFIRMED);
                }
            }
            
            return !$this->hasErrors();
        }elseif(isset($_GET['op']) || $mode){
            if($error = $paymentMethod->dispatchThankyou($this, $bb)){
                if(!$plugin->getSettings()->get('create_booking_after_pay')){
                    $bb->setStatus($isConfirmation ? SLN_Enum_BookingStatus::PENDING : SLN_Enum_BookingStatus::PENDING_PAYMENT);
                }
                $this->addError($error);
            }else{
                return !$this->hasErrors();
            }
        }
        if(!empty($paymentMethod) && in_array($bb->getStatus(), array(SLN_Enum_BookingStatus::PAY_LATER, SLN_Enum_BookingStatus::PENDING_PAYMENT))){
            return false;
        }
        if(!$plugin->getSettings()->get('create_booking_after_pay')){
            $bb->setStatus($isConfirmation ? SLN_Enum_BookingStatus::PENDING : SLN_Enum_BookingStatus::PENDING_PAYMENT);

            return !$this->hasErrors();
        }

        return !$this->hasErrors();
    }

    public function render()
    {
        $bb = $this->getPlugin()->getBookingBuilder();
        if($bb->get('services') && $bb->isValid()){
            $data = $this->getViewData();
            do_action('sln.shortcode.summary.dispatchForm.before_booking_creation', $this, $bb);
            if(!$this->hasErrors()){
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
