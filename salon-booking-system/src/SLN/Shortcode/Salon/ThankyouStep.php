<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
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
        // Always render the booking confirmation step so the customer sees the
        // booking number/status and the salonBookingComplete analytics event fires.
        // The "Disable countdown on booking completion" option only suppresses the
        // auto-redirect countdown (see getViewData()/salon_thankyou views); it must
        // NOT skip this step, otherwise the customer was bounced straight to the
        // Thank You page — or, when no Thank You page is configured, back to the
        // booking page's first (forecast) step.
        return $this->getPlugin()->loadView('shortcode/salon_' . $this->getStep(), $this->getViewData());
    }

    protected function getViewData(){
        $ret = parent::getViewData();
        $booking = $this->getPlugin()->getBookingBuilder()->getLastBooking();
        if(empty($booking) && isset($_GET['op'])){
            $booking = $this->getPlugin()->createBooking(explode('-', sanitize_text_field($_GET['op']))[1]);
        }
		$bb     = $this->getPlugin()->getBookingBuilder();
		$origin = $bb->get( 'forecast_origin' )
			? SLN_Enum_BookingOrigin::ORIGIN_FORECAST
			: SLN_Enum_BookingOrigin::ORIGIN_DIRECT;
	    add_post_meta( $booking->getId(), '_' . SLN_Plugin::POST_TYPE_BOOKING . '_origin_source', $origin, true );
        $ret['booking'] = $booking;
        $ret['goToThankyou'] = $this->getThankyou();
        $ret['disableCountdown'] = (bool) $this->getPlugin()->getSettings()->get('disable_summary_skip_countdown');
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
