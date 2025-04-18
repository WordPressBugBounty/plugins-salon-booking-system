<?php // algolplus
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Shortcode_SalonMyAccount
{
    const NAME = 'salon_booking_my_account';

    private $plugin;
    private $attrs;

    function __construct(SLN_Plugin $plugin, $attrs)
    {
        $this->plugin = $plugin;
        $this->attrs = $attrs;
    }

    public static function init(SLN_Plugin $plugin)
    {
        add_shortcode(self::NAME, array(__CLASS__, 'create'));        
    }

    public static function create($attrs)
    {
        SLN_TimeFunc::startRealTimezone();

        $obj = new self(SLN_Plugin::getInstance(), $attrs);

        $ret = $obj->execute();
        SLN_TimeFunc::endRealTimezone();
        return $ret;
    }

    public function execute()
    {
        if (!is_user_logged_in()) {
            add_filter('login_form_bottom', array($this, 'hook_login_form_bottom'), 10, 2);
            $content = '<div id="sln-salon" class="sln-bootstrap sln-loginform">' . wp_login_form(array('echo' => false)) . '<span class="help-block"><a href="' . wp_lostpassword_url() . '" class="tec-link">' . __('Forgot password?', 'salon-booking-system') . '</a></span></div>';
            remove_filter('login_form_bottom', array($this, 'hook_login_form_bottom'), 10);

            return $content;
        }

        $data = array();
        if(isset($_POST['action']) && $_POST['action'] === 'sln_update_profile' ) {

            $updater = new SLN_Shortcode_SalonMyAccount_ProfileUpdater($this->plugin);
            $data['sln_update_profile'] = $updater->dispatchForm();
            
        }
        
        $feedback_id = false;

        if( isset( $_GET[ 'feedback_id' ] ) ) {
            $feedback_id = intval( $_GET[ 'feedback_id' ] );
            $url = remove_query_arg( 'feedback_id' );
            
            $booking = new SLN_Wrapper_Booking( $feedback_id );
            if( $booking->getUserId() != get_current_user_id() || $booking->getRating() ) {
                wp_redirect( $url );
                exit();
            }
        }
        wp_add_inline_script( 'salon-my-account', 'sln_myAccount.feedback_id = ' . wp_json_encode( $feedback_id ) . ';' );

        return $this->render($data);
    }

    public function hook_login_form_bottom($content, $args) {

	if ( $this->plugin->getSettings()->get('enabled_fb_login') ) {
	    $content .= '<div class="sln-loginform--sociallogin"><a href="' . add_query_arg(array('referrer' => urlencode(get_permalink(SLN_Plugin::getInstance()->getSettings()->getBookingmyaccountPageId()))), SLN_Helper_FacebookLogin::getRedirectUri()) . '" class="sln-btn sln-btn--fullwidth sln-btn--borderonly sln-btn--medium" data-salon-target="page">' . __('log-in with Facebook', 'salon-booking-system') . '</a></div>';
	}

        return $content;
    }

    protected function render($data = array())
    {
        return $this->plugin->loadView('shortcode/salon_my_account/salon_my_account', compact('data'));
    }

}
