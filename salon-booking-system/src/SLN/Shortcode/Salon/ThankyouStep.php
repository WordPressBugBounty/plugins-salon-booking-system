<?php

class SLN_Shortcode_Salon_ThankyouStep extends SLN_Shortcode_Salon_Step
{
    protected function dispatchForm(){
        return !$this->hasErrors();
    }

    public function getThankyou(){
        $id = $this->getPlugin()->getSettings()->getThankyouPageId();
        $args = array('sln_thankyou_layout' => $this->getShortcode()->getStyleShortcode());
        if($id){
            return add_query_arg($args, get_permalink($id));
        }else{
            return add_query_arg($args, home_url());
        }
    }

    public function render(){
        $plugin = $this->getPlugin();
        return $this->getPlugin()->loadView('shortcode/salon_' . $this->getStep(), $this->getViewData());
    }

    protected function getViewData(){
        $ret = parent::getViewData();
        $booking = $this->getPlugin()->getBookingBuilder()->getLastBooking();
        if(empty($booking) && isset($_GET['op'])){
            $booking = $this->getPlugin()->createBooking(explode('-', sanitize_text_field($_GET['op']))[1]);
        }
	    add_post_meta($booking->getId(), '_'.SLN_Plugin::POST_TYPE_BOOKING.'_origin_source', SLN_Enum_BookingOrigin::ORIGIN_DIRECT);
        $ret['booking'] = $booking;
        $ret['goToThankyou'] = $this->getThankyou();
        return $ret;
    }

    public function redirect($url)
    {
        if ($this->isAjax()) {
            throw new SLN_Action_Ajax_RedirectException($url);
        } else {
            wp_redirect($url);die();
        }
    }

    public function isAjax()
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    public function getTitleKey(){
        return 'Booking Confirmation';
    }

    public function getTitleLabel(){
        return __('Booking Confirmation', 'salon-booking-system');
    }
}
