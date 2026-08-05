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
        $data = $this->getViewData();

        // SECURITY: never disclose booking details when the visitor is not entitled
        // to this booking (see getViewData()). Redirect back to the booking form so an
        // attacker cannot read a booking by enumerating IDs via ?op=x-<id>.
        if (empty($data['booking'])) {
            SLN_Plugin::addLog('[ThankyouStep] No authorized booking to display — redirecting to booking form.');
            $payId = $this->getPlugin()->getSettings()->getPayPageId();
            $url   = $payId ? get_permalink($payId) : home_url('/');
            $this->redirect(add_query_arg(array('sln_step_page' => 'services'), $url));
            return '';
        }

        return $this->getPlugin()->loadView('shortcode/salon_' . $this->getStep(), $data);
    }

    protected function getViewData(){
        $ret = parent::getViewData();
        $plugin  = $this->getPlugin();
        $booking = $plugin->getBookingBuilder()->getLastBooking();

        // SECURITY: only load a booking from the request when the visitor is
        // authorized for it (valid secure token, own session, or own/managed
        // booking). A bare numeric ID is rejected to prevent IDOR disclosure.
        if (empty($booking) && isset($_GET['sln_booking_id'])) {
            $booking = SLN_Helper_BookingAccess::resolve($plugin, sanitize_text_field(wp_unslash($_GET['sln_booking_id'])));
        }
        if (empty($booking) && isset($_GET['op'])) {
            $booking = SLN_Helper_BookingAccess::resolveFromOp($plugin, sanitize_text_field(wp_unslash($_GET['op'])));
        }

        if (!empty($booking)) {
            $bb     = $plugin->getBookingBuilder();
            $origin = $bb->get( 'forecast_origin' )
                ? SLN_Enum_BookingOrigin::ORIGIN_FORECAST
                : SLN_Enum_BookingOrigin::ORIGIN_DIRECT;
            add_post_meta( $booking->getId(), '_' . SLN_Plugin::POST_TYPE_BOOKING . '_origin_source', $origin, true );
        }

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
