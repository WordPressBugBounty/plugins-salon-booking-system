<?php
use Google\Client as Google_Client;
use Google\Service\Calendar as Google_Service_Calendar;
use Google\Service\Calendar\Event as Google_Service_Calendar_Event;
use Google\Service\Calendar\EventDateTime as Google_Service_Calendar_EventDateTime;
use Google\Service\Calendar\EventAttendee as Google_Service_Calendar_EventAttendee;
if (!function_exists('\GuzzleHttp\choose_handler')) {
    require_once SLN_PLUGIN_DIR . '/src/SLN/Third/google-api-php-client/vendor/guzzlehttp/guzzle/src/functions.php';
    require_once SLN_PLUGIN_DIR . '/src/SLN/Third/google-api-php-client/vendor/guzzlehttp/guzzle/src/functions_include.php';
}
if (!function_exists('\GuzzleHttp\Promise\promise_for')) {
    require_once SLN_PLUGIN_DIR . '/src/SLN/Third/google-api-php-client/vendor/guzzlehttp/promises/src/functions_include.php';
}
if (!function_exists('\GuzzleHttp\choose_handler')) {
    require_once SLN_PLUGIN_DIR . '/src/SLN/Third/google-api-php-client/vendor/guzzlehttp/guzzle/src/functions_include.php';
}
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
function _pre($m) {
    echo "<pre>";
    print_r($m);
    echo "</pre>";
}

function sln_my_wp_log($message, $file = null, $level = 1) {
    // full path to log file
    if ($file == null) {
        $file = 'debug.log';
    }
    if(!SLN_Plugin::DEBUG_ENABLED){
        return false;
    }

    $file = SLN_PLUGIN_DIR . DIRECTORY_SEPARATOR . "src/SLN/Third/" . $file;

    /* backtrace */
    $bTrace = debug_backtrace(1, $level); // assoc array

    /* Build the string containing the complete log line. */
    $line = PHP_EOL . sprintf('[%s, <%s>, (%d)]==> %s', date("Y/m/d h:i:s" /* ,time() */), basename($bTrace[0]['file']), $bTrace[0]['line'], print_r($message, true));

    if ($level > 1) {
        $i = 0;
        $line.=PHP_EOL . sprintf('Call Stack : ');
        while (++$i < $level && isset($bTrace[$i])) {
            $line.=PHP_EOL . sprintf("\tfile: %s, function: %s, line: %d" . PHP_EOL . "\targs : %s", isset($bTrace[$i]['file']) ? basename($bTrace[$i]['file']) : '(same as previous)', isset($bTrace[$i]['function']) ? $bTrace[$i]['function'] : '(anonymous)', isset($bTrace[$i]['line']) ? $bTrace[$i]['line'] : 'UNKNOWN', print_r($bTrace[$i]['args'], true));
        }
        $line.=PHP_EOL . sprintf('End Call Stack') . PHP_EOL;
    }
    // log to file
    SLN_Plugin::addLog($line);
    return true;

    file_put_contents($file, $line, FILE_APPEND);

    return true;
}

//Add to htaccess RewriteRule ^wp-admin/salon-settings/(.*)/$ /wp-admin/admin.php?page=salon-settings&tab=$1 [L]
if(!class_exists('Google_Service_Calendar')){
   require SLN_PLUGIN_DIR . '/src/SLN/Third/google-api-php-client/vendor/autoload.php';
}

/**
 * Add '_sln_calendar_event_id' => '' in MetBox/Booking.php getFieldList
 */
class SLN_GoogleScope {

    public $date_offset = 0;
    public $client_id = '102246196260-so9c267umku08brmrgder71ige08t3nm.apps.googleusercontent.com'; //change this
    public $email_address = '102246196260-so9c267umku08brmrgder71ige08t3nm@developer.gserviceaccount.com'; //change this
    public $scopes = "https://www.googleapis.com/auth/calendar";
    public $key_file_location = 'prv.p12'; //change this
    public $outh2_client_id = "102246196260-hjpu1fs2rh5b9mesa9l5urelno396vc0.apps.googleusercontent.com";
    public $outh2_client_secret = "AJzLfWtRDz53JLT5fYp5gLqZ";
    public $outh2_redirect_uri;
    public $google_calendar_enabled = false;
    public $google_client_calendar;
    public $client;
    public $service;
    public $settings;

    /**
     * __construct
     */
    public function __construct() {
        if (is_admin()) {
            add_action('wp_ajax_googleoauth-callback', array($this, 'get_client'));
            add_action('wp_ajax_nopriv_googleoauth-callback', array($this, 'get_client'));
            add_action('wp_ajax_startsynch', array($this, 'start_synch'));
            add_action('wp_ajax_deleteallevents', array($this, 'delete_all_bookings_event'));
            add_action('admin_footer', array($this, 'add_script'));
        }
    }

    public function add_script() {
        ?>
        <script>
            jQuery(function () {
                jQuery('#sln_synch').on('click', function () {
                    var $button = jQuery(this);
                    $button.addClass('disabled').after('<div class="load-spinner"><img src="<?php echo get_site_url() . '/wp-admin/images/wpspin_light.gif'; ?>" /></div>');
                    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                    var data = <?php echo wp_json_encode(apply_filters('sln.google-scope.ajax-events.synch.data', array('action' => 'startsynch','nonce'=> wp_create_nonce('google_calendar')))) ?>;
                    jQuery.post(ajaxurl, data, function (response) {
                        if (response == 'OK') {
                            alert("<?php echo esc_html__('Operation completed!', 'salon-booking-system'); ?>");
                        } else {
                            var tmp = data.split('|');
                            if (tmp[1])
                                alert(tmp[1]);
                        }
                        $button.removeClass('disabled');
                        jQuery('.load-spinner').remove();
                    });
                });
                jQuery('#sln_del').on('click', function () {
                    var $button = jQuery(this);
                    $button.addClass('disabled').after('<div class="load-spinner"><img src="<?php echo get_site_url() . '/wp-admin/images/wpspin_light.gif'; ?>" /></div>');
                    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                    var data = <?php echo wp_json_encode(apply_filters('sln.google-scope.ajax-events.delete.data', array('action' => 'deleteallevents','nonce'=> wp_create_nonce('google_calendar')))) ?>;
                    jQuery.post(ajaxurl, data, function (response) {
                        if (response == 'OK') {
                            alert("<?php echo esc_html__('Operation completed!', 'salon-booking-system'); ?>");
                        } else {
                            var tmp = data.split('|');
                            if (tmp[1])
                                alert(tmp[1]);
                        }
                        $button.removeClass('disabled');
                        jQuery('.load-spinner').remove();
                    });
                });
            });
        </script>
        <?php
    }
    public function check_role(){
        $user = wp_get_current_user();

        // List of allowed roles
        $allowed_roles = ['administrator', 'sln_staff', 'sln_shop_manager'];

        if (array_intersect($allowed_roles, $user->roles) === []) {
            wp_send_json_error('Unauthorized', 403);
        }

        $nonce = $_POST['nonce'] ?? '';

        if ( ! wp_verify_nonce( $nonce, 'google_calendar' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
        }
    }
    public function start_synch() {
        $this->check_role();
        if (!$this->is_connected()) {
            echo "KO|" . esc_html__("Google Client is not connected!", 'salon-booking-system');
        }

        $now = new SLN_DateTime();
        $booking_handler = new SLN_Bookings_Handle($now);
        $bookings = $booking_handler->getBookings();
        foreach ($bookings as $k => $post) {
            $event_id = get_post_meta($post->ID, '_sln_calendar_event_id', true);
            if (!empty($event_id)) {
                $this->delete_event_from_booking($event_id);
            }
        }

        foreach ($bookings as $k => $post) {
            synch_a_booking($booking_handler->createBooking($post->ID), true);
        }
        echo "OK";
        die();
    }

    public function delete_all_bookings_event($bookings = "") {
        $this->check_role();
        $now = new SLN_DateTime();
        $booking_handler = new SLN_Bookings_Handle($now);
        $bookings = $booking_handler->getBookings();

        foreach ($bookings as $k => $post) {
            $event_id = get_post_meta($post->ID, '_sln_calendar_event_id', true);
            $this->delete_event_from_booking($event_id);
        }
        echo "OK";
        die;
    }

    /**
     * wp_init
     * @param type $plugin
     */
    public function wp_init() {

        $this->google_calendar_enabled = $this->settings->get('google_calendar_enabled');
        $this->google_client_calendar = $this->settings->get('google_client_calendar');

        if (isset($this->settings)) {
            $this->outh2_client_id = $this->settings->get('google_outh2_client_id');
            $this->outh2_client_secret = $this->settings->get('google_outh2_client_secret');
            $this->outh2_redirect_uri = $this->settings->get('google_outh2_redirect_uri');
            if (    (!empty($this->outh2_client_id) &&
                    !empty($this->outh2_client_secret) &&
                    !empty($this->outh2_redirect_uri))
            ) {
                if ($this->google_calendar_enabled)
                    $this->start_auth(false);
                else
                    $this->start_auth(true);
            }
        }
    }

    /**
     * start_auth
     */
    public function start_auth($force_revoke_token = false) {
        $access_token = '';
        if (!isset($access_token) || empty($access_token))
            $access_token = $this->settings->getGoogleAccessToken();

        if (isset($access_token) && !empty($access_token)) {
            if ($force_revoke_token || (isset($_GET['revoketoken']) && $_GET['revoketoken'] == 1)) {
                $res = wp_remote_get("https://accounts.google.com/o/oauth2/revoke?token={$access_token}");

                $this->save_tokens('', '');

                unset($_SESSION['access_token']);

                header("Location: " . $this->get_success_redirect_page_url());
            }

            $this->client = new Google_Client(array('retry' => array('retries' => 2)));
            $this->client->setClientId($this->outh2_client_id);
            $this->client->setClientSecret($this->outh2_client_secret);
            $this->client->setRedirectUri(isset($this->outh2_redirect_uri) ? $this->outh2_redirect_uri : admin_url('admin-ajax.php?action=googleoauth-callback'));
            $this->client->setAccessType('offline');
            $this->client->addScope($this->scopes);
            $this->client->setAccessToken($access_token);

            $this->service = $this->get_google_service();
        } else {
            if (!$force_revoke_token) {
                if ( isset($_GET['force_revoke_token']) ) {

                    $loginUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query(array(
                        'response_type'   => 'code',
                        'client_id'       => $this->outh2_client_id,
                        'redirect_uri'    => $this->outh2_redirect_uri,
                        'scope'           => $this->scopes,
                        'access_type'     => 'offline',
                        'approval_prompt' => 'force',
                        'state'           => $this->get_current_page_url(),
                    ));

                    header("Location: " . $loginUrl);
                }
            }
        }
    }

    protected function get_success_redirect_page_url() {
        return isset($_GET['state']) ? $_GET['state'] : $this->get_current_page_url();
    }

    protected function get_current_page_url() {
        return admin_url(
            (isset($_SERVER['REDIRECT_URL']) ? str_replace('wp-admin', '', trim($_SERVER['REDIRECT_URL'] , '/')) : 'admin.php').
            '?'.
            remove_query_arg(array('revoketoken', 'force_revoke_token'), $_SERVER['QUERY_STRING'])
        );
    }

    protected function get_error_redirect_page_url() {
        return $this->get_success_redirect_page_url().'&revoketoken=1';
    }

    /**
     * get_client
     */
    public function get_client() {
        if (isset($_GET['error'])) {
	        wp_safe_redirect($this->get_error_redirect_page_url());
        }

        $code = isset($_GET['code']) ? $_GET['code'] : null;

        if (isset($code)) {
            $oauth_result = wp_remote_post("https://accounts.google.com/o/oauth2/token", array(
                'body' => array(
                    'code' => $code,
                    'client_id' => $this->outh2_client_id,
                    'client_secret' => $this->outh2_client_secret,
                    'redirect_uri' => isset($this->outh2_redirect_uri) ? $this->outh2_redirect_uri : admin_url('admin-ajax.php?action=googleoauth-callback'),
                    'grant_type' => 'authorization_code'
                )
            ));

            if (!is_wp_error($oauth_result)) {
                $oauth_response = json_decode($oauth_result['body'], true);
            } else {
                _pre($oauth_result);
                die();
            }

            if (isset($oauth_response['access_token'])) {
                //refresh_token is present with only login setting approval_prompt=force
                $oauth_refresh_token = $oauth_response['refresh_token'];
                $oauth_token_type = $oauth_response['token_type'];
                $oauth_access_token = $oauth_response['access_token'];
                $oauth_expiry = $oauth_response['expires_in'] + time();
                $idtoken_validation_result = wp_remote_get('https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . $oauth_access_token);

                $_SESSION['access_token'] = $oauth_result['body']; //$oauth_access_token;
                $_SESSION['refresh_token'] = $oauth_refresh_token;

                $this->save_tokens($oauth_result['body'], $oauth_refresh_token);

                if (!is_wp_error($idtoken_validation_result)) {
                    $idtoken_response = json_decode($idtoken_validation_result['body'], true);
                    setcookie('google_oauth_id_token', $oauth_access_token, $oauth_expiry, COOKIEPATH, COOKIE_DOMAIN);
                } else {
                    _pre($idtoken_validation_result);
                    die();
                }
            } else {
                _pre($oauth_response);
                if (isset($oauth_response['error'])) {
	                wp_safe_redirect($this->get_error_redirect_page_url());
                }
                die();
            }
        } else {
            $this->start_auth();
        }
	    wp_safe_redirect($this->get_success_redirect_page_url());
        die();
    }

    /**
     * set_settings_by_plugin
     * @param type $plugin
     */
    public function set_settings_by_plugin($plugin) {
        $this->settings = $plugin->getSettings();
    }

    protected function save_tokens($access_token = '', $refresh_token = '')
    {
        $applied = apply_filters('sln.google-scope.get-client.update-tokens', false, array(
            'sln_access_token'  => $access_token,
            'sln_refresh_token' => $refresh_token,
        ));

        if (!$applied) {
            $this->settings->set('sln_access_token', $access_token);
            $this->settings->set('sln_refresh_token', $refresh_token);
            $this->settings->save();
        }
    }

    /**
     * get_google_service creates and return the google service
     * @return \Google_Service_Calendar
     */
    public function get_google_service() {
        return new Google_Service_Calendar($this->client);
    }

    /**
     * start_auth init the login to google services
     */
    public function start_auth_assertion() {
        $base = SLN_PLUGIN_DIR . "/src/SLN/Third/";
        $key = file_get_contents($base . $this->key_file_location);
        $cred = new Google_Auth_AssertionCredentials(
                $this->email_address, array($this->scopes), $key
        );
        $this->client->setAssertionCredentials($cred);
        if ($this->client->isAccessTokenExpired()) {
            $this->client->refreshTokenWithAssertion($cred);
        }
    }

    /**
     * is_connected
     * @return boolean
     */
    public function is_connected() {
        $ret = (isset($this->client) && !$this->client->isAccessTokenExpired());
        sln_my_wp_log("is connected " . $ret);
        sln_my_wp_log("client " . isset($this->client));

        if (!$ret) {
            $access_token = '';
            if (!isset($access_token) || empty($access_token)) {
                $access_token = $this->settings->getGoogleAccessToken();
            }
            sln_my_wp_log($access_token);

            $refresh_token = isset($_SESSION['refresh_token']) ? $_SESSION['refresh_token'] : "";
            if (!isset($refresh_token) || empty($refresh_token)) {
                $refresh_token = $this->settings->getGoogleRefreshToken();
            }
            sln_my_wp_log($refresh_token);

            try {
                if (isset($this->client)) {
                    sln_my_wp_log("Refreshed Token");
                    $this->client->refreshToken($refresh_token);
                    sln_my_wp_log($refresh_token);
                } else {
                    sln_my_wp_log("Not Refreshed Token");

                    $this->client = new Google_Client(array('retry' => array('retries' => 2)));
                    $this->client->setClientId($this->outh2_client_id);
                    $this->client->setClientSecret($this->outh2_client_secret);
                    $this->client->setRedirectUri(isset($this->outh2_redirect_uri) ? $this->outh2_redirect_uri : admin_url('admin-ajax.php?action=googleoauth-callback'));
                    $this->client->setAccessType('offline');
                    $this->client->addScope($this->scopes);
                    $this->client->setAccessToken($access_token);
                    $this->client->refreshToken($refresh_token);

                    $this->service = $this->get_google_service();

                    $access_token = $this->client->getAccessToken();
                    sln_my_wp_log("is connected2 :" . (isset($access_token['expires_in']) && !$this->client->isAccessTokenExpired()));
                }
                $access_token = $this->client->getAccessToken();
                return !isset($access_token['expires_in']) || !$this->client->isAccessTokenExpired();
            } catch (Exception $e) {
                sln_my_wp_log($e);
                return false;
            }
            return true;
        }
        return $ret;
    }

    /**
     * get_calendar_list
     * @return type
     */
    public function get_calendar_list(array $accessRoles = array()) {
        $cal_list = array();

        if (!$this->is_connected())
            return $cal_list;

        $calendarList = $this->service->calendarList->listCalendarList();
        $cal_list = array();
        $cal_list['']['id'] = 0;
        $cal_list['']['label'] = __("Choose among your Calendars", 'salon-booking-system');
        while (true) {
            foreach ($calendarList->getItems() as $calendarListEntry) {
                if (!empty($accessRoles) && !in_array($calendarListEntry->accessRole, $accessRoles)) {
                    continue;
                }
                if (!isset($cal_list[$calendarListEntry->getId()]))
                    $cal_list[$calendarListEntry->getId()] = array();
                $cal_list[$calendarListEntry->getId()]['id'] = $calendarListEntry->getId();
                $cal_list[$calendarListEntry->getId()]['label'] = $calendarListEntry->getSummary();
            }
            $pageToken = $calendarList->getNextPageToken();
            if ($pageToken) {
                $optParams = array('pageToken' => $pageToken);
                $calendarList = $this->service->calendarList->listCalendarList($optParams);
            } else {
                break;
            }
        }

        return $cal_list;
    }

    /**
     * create_event
     * @param type $params
      array(
      'email' => $email,
      'title' => $title,
      'location' => $location,
      'date_start' => $date_start,
      'time_start' => $time_start,
      'date_end' => $date_end,
      'time_end' => $time_end,
      );
     * @return type
     */
    public function create_event($params) {
        extract($params);

        $catId = isset($params['catId']) && !empty($params['catId']) ? $params['catId'] : "primary";

        $event = new Google_Service_Calendar_Event();
        $event->setSummary($title);
        $event->setLocation($location);
        $start = new Google_Service_Calendar_EventDateTime();
        $str_date = strtotime($date_start . " " . $time_start);
        $dateTimeS = self::date3339($str_date, $this->date_offset);

        $start->setDateTime($dateTimeS);
        $event->setStart($start);
        $end = new Google_Service_Calendar_EventDateTime();
        $str_date = strtotime($date_end . " " . $time_end);
        $dateTimeE = self::date3339($str_date, $this->date_offset);

        $end->setDateTime($dateTimeE);
        $event->setEnd($end);

        $attendee1 = new Google_Service_Calendar_EventAttendee();
        $attendee1->setEmail($email);
        $attendees = array($attendee1);

        $event->attendees = $attendees;
        $createdEvent = $this->service->events->insert($catId, $event);

        return $createdEvent->getId();
    }

    /**
     * delete_event
     * @param type $event_id
     * @return type
     */
    public function delete_event($event_id, $catId = 'primary') {
        return $this->service->events->delete($catId, $event_id);
    }

    /**
     *
     * @param type $params
      array(
      'email' => $email,
      'title' => $title,
      'location' => $location,
      'date_start' => $date_start,
      'time_start' => $time_start,
      'date_end' => $date_end,
      'time_end' => $time_end,
      );
     * @return type
     */
    public function update_event($params) {
        extract($params);

        $catId = isset($params['catId']) && !empty($params['catId']) ? $params['catId'] : "primary";

        $rule = $this->service->events->get($catId, $event_id);

        $event = new Google_Service_Calendar_Event();
        $event->setSummary($title);
        $event->setLocation($location);
        $start = new Google_Service_Calendar_EventDateTime();
        $str_date = strtotime($date_start . " " . $time_start);
        $dateTimeS = self::date3339($str_date, $this->date_offset);

        $start->setDateTime($dateTimeS);
        $event->setStart($start);
        $end = new Google_Service_Calendar_EventDateTime();
        $str_date = strtotime($date_end . " " . $time_end);
        $dateTimeE = self::date3339($str_date, $this->date_offset);

        $end->setDateTime($dateTimeE);
        $event->setEnd($end);

        $attendee1 = new Google_Service_Calendar_EventAttendee();
        $attendee1->setEmail($email); //change this
        $attendees = array($attendee1);
        $event->attendees = $attendees;

        $updatedRule = $this->service->events->update($catId, $rule->getId(), $event);
        return $updatedRule;
    }

    /**
     * get_list_event return all the event for primary calendar
     */
    public function get_list_event() {
        if (!$this->is_connected())
            return;

        $ret = array();
        $events = $this->service->events->listEvents($this->google_client_calendar);
        while (true) {
            foreach ($events->getItems() as $event) {
                $ret[$event->getId()] = $event->getSummary();
            }
            $pageToken = $events->getNextPageToken();
            if ($pageToken) {
                $optParams = array('pageToken' => $pageToken);
                $events = $this->service->events->listEvents($this->google_client_calendar, $optParams);
            } else {
                break;
            }
        }
        return $ret;
    }

    /**
     *
     * @param type $params
     *         $params = array(
      'height' => 600,
      'width' => 800,
      'wkst' => 1,
      'bgcolor' => '#FFFFFF',
      'color' => '#29527A',
      'src' => 'magikboo23@gmail.com',
      'ctz' => 'Europe/Rome'
      );
     */
    public function print_calendar_by_calendar_id($params) {
        $str = "?";
        $i = 0;
        foreach ($params as $k => $v) {
            if ($i <= 0)
                $str .= "$k=" . urlencode($v);
            else
                $str .= "&amp;$k=" . urlencode($v);
            $i++;
        }
        ?>
        <iframe src="https://www.google.com/calendar/embed?<?php echo $str; ?>" style=" border-width:0 " width="800" height="600" frameborder="0" scrolling="no"></iframe>
        <?php
    }

    /**
     * date3339 transform timestamp into google calendar date compliant
     * @param type $timestamp
     * @param type $offset
     * @return string
     */
    public static function date3339($timestamp = 0, $offset = 0) {
        $date = new SLN_DateTime();
        $date->setTimestamp($timestamp);
        return $date->format(DateTime::RFC3339);
    }

    /**
     * create_event_from_booking
     * @param type $booking
     * @return type
     */
    public function create_event_from_booking($booking, $cancel = false, $error = '') {
        if (!$this->is_connected())
            return;

        $gc_event = new SLN_GoogleCalendarEventFactory();
        $event = $gc_event->get_event($booking);

        if ($cancel)
            $event->setColorId("11");
        else {
	    if ( empty ( $error ) ) {
		$event->setColorId("10");
	    } else {
		$event->setColorId("6");
		$error = $event->getDescription()."\n\nWARNING: {$error}";
		$event->setDescription($error);
	    }
	}

        $attendee1 = new Google_Service_Calendar_EventAttendee();
        $attendee1->setEmail($this->google_client_calendar);
        $attendees = array($attendee1);

        $event->attendees = $attendees;
        $createdEvent = $this->service->events->insert($this->google_client_calendar, $event);

        return $createdEvent->getId();
    }

    /**
     * create_event_from_booking
     * @param type $booking
     * @return type
     */
    public function update_event_from_booking($booking, $b_event_id, $cancel = false, $error = '') {
        if (!$this->is_connected())
            return;

        $gc_event = new SLN_GoogleCalendarEventFactory();
        $event = $gc_event->get_event($booking);

        if ($cancel)
            $event->setColorId("11");
        else {
	    if ( empty ( $error ) ) {
		$event->setColorId("10");
	    } else {
		$event->setColorId("6");
		$error = $event->getDescription()."\n\nWARNING: {$error}";
		$event->setDescription($error);
	    }
	}

        $attendee1 = new Google_Service_Calendar_EventAttendee();
        $attendee1->setEmail($this->google_client_calendar); //change this

        $attendees = array($attendee1);
        $event->attendees = $attendees;

        sln_my_wp_log("event updating");
        sln_my_wp_log($event);

        $updatedRule = $this->service->events->update($this->google_client_calendar, $b_event_id, $event);

        $rule = $this->service->events->get($this->google_client_calendar, $b_event_id);

        return $updatedRule->getId();
    }

    /**
     * delete_event
     * @param type $event_id
     * @return type
     */
    public function delete_event_from_booking($event_id) {
        try {
            if(!$this->service) return;
            $this->service->events->delete($this->google_client_calendar, $event_id);
            sln_my_wp_log($event_id);
            sln_my_wp_log($this->google_client_calendar);
        } catch (Exception $e) {
            sln_my_wp_log($e);
        }
    }

    public function clear_calendar_events() {
        if (!$this->is_connected())
            return;

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . $this->google_client_calendar . '/clear';
        sln_my_wp_log($url);
        $ret = wp_remote_get($url);
        sln_my_wp_log($ret);
        return $ret;
    }

    public function validate_booking_services($booking)
    {
	$ah = SLN_Plugin::getInstance()->getAvailabilityHelper();

	$date = $booking->getStartsAt();

	$ah->setDate($date, $booking);

        $bookingServices = $booking->getBookingServices();

	$ah->addAttendantForServices($bookingServices);

        $settings               = SLN_Plugin::getInstance()->getSettings();
        $primaryServicesCount   = $settings->get('primary_services_count');
        $secondaryServicesCount = $settings->get( 'secondary_services_count' );
        $bookingOffsetEnabled   = $settings->get('reservation_interval_enabled');
        $bookingOffset          = $settings->get('minutes_between_reservation');
        $isMultipleAttSelection = $settings->get('m_attendant_enabled');

	    // $isServicesCountPrimaryServices = $settings->get('is_services_count_primary_services');

        if ($primaryServicesCount) {

            $_services = $bookingServices->getItems();

            // if ($isServicesCountPrimaryServices) {
            $_services = array_filter($_services, function ($bookingService) {
                return !$bookingService->getService()->isSecondary();
            });
            // }

            if (count($_services) >= $primaryServicesCount) {
                throw new SLN_Exception(
                    sprintf(
                        // translators: %s will be replaced by the primary services count
                        esc_html__('You can select up to %d items', 'salon-booking-system'), $primaryServicesCount)
                );
            }
        }

        if( $secondaryServicesCount ){
            $_services = array_filter( $bookingServices->getItems(), function( $bookingService ){
                return $bookingService->getService()->isSecondary();
            } );

            if(count($_services) >= $secondaryServicesCount) {
                throw new SLN_Exception(
                    sprintf(
                        // translators: %s will be replaced by the secondary services count
                        esc_html__('You can select up to %d items', 'salon-booking-system'), $secondaryServicesCount)
                );
            }
        }

        $firstSelectedAttendant = null;
        foreach ($bookingServices->getItems() as $bookingService) {

	    $serviceErrors = $ah->validateServiceFromOrder($bookingService->getService(), $bookingServices);

	    if (!empty($serviceErrors)) {
		throw new SLN_Exception(reset($serviceErrors));
	    }

	    if ($bookingServices->isLast($bookingService) && $bookingOffsetEnabled) {
		$offsetStart   = $bookingService->getEndsAt();
		$offsetEnd     = $bookingService->getEndsAt()->modify('+'.$bookingOffset.' minutes');
		$serviceErrors = $ah->validateTimePeriod($offsetStart, $offsetEnd);

		if (!empty($serviceErrors)) {
		    throw new SLN_Exception(reset($serviceErrors));
		}
	    }

	    $serviceErrors = $ah->validateBookingService($bookingService);
	    if (!empty($serviceErrors)) {
		throw new SLN_Exception(reset($serviceErrors));
	    }

	    if (!$isMultipleAttSelection && !is_array($bookingService->getAttendant())) {
            if (!$firstSelectedAttendant) {
                $firstSelectedAttendant = $bookingService->getAttendant() ?
                $bookingService->getAttendant()->getId() : false;
            }
            if ($bookingService->getAttendant() &&
                $bookingService->getAttendant()->getId() != $firstSelectedAttendant
            ) {
                throw new SLN_Exception(
                    esc_html__(
                    'Multiple attendants selection is disabled. You must select one attendant for all services.',
                    'salon-booking-system'
                )
                );
            }
	    }
	    if ($bookingService->getAttendant()) {
		$attendantErrors = $ah->validateAttendantService(
		    $bookingService->getAttendant(),
		    $bookingService->getService()
		);
		if (!empty($attendantErrors)) {
		    throw new SLN_Exception(reset($attendantErrors));
		}

		if(!is_array($bookingService->getAttendant())){
            $attendantErrors = $ah->validateBookingAttendant($bookingService, $bookingServices->isLast($bookingService));
        }else{
            $attendantErrors = $ah->validateBookingAttendants($bookingService, $bookingServices->isLast($bookingService));
        }
		if (!empty($attendantErrors)) {
		    throw new SLN_Exception(reset($attendantErrors));
		}
	    }
        }
    }

}

class SLN_GoogleCalendarEventFactory extends Google_Service_Calendar_Event {

    public function get_event($booking) {
        require_once SLN_PLUGIN_DIR . "/src/SLN/Enum/BookingStatus.php";
        $plugin = SLN_Plugin::getInstance();

        $desc = "";
        //Name and Phone
        $desc .= __('Customer name', 'salon-booking-system') . ": " . $booking->getDisplayName() . " - ";
        $desc .= $booking->getSmsPrefix(). ' '. $booking->getPhone() . " \n";

	$desc = apply_filters('sln.google_calendar_event_factory.get_event.description.before-services', $desc, $booking);

        //Services
        $desc .= "\n" . __('Booked services', 'salon-booking-system') . ":";
        foreach ($booking->getBookingServices()->getItems() as $bookingService) {
            $desc .= "\n";
            $serviceCategory = $bookingService->getService()->getServiceCategory();
            $desc .= (!empty($serviceCategory)? ($serviceCategory->getName() . '/') : '') . $bookingService->getService()->getName() . ': ' .
                     $plugin->format()->time($bookingService->getStartsAt()) . ' ➝ ' .
                     $plugin->format()->time($bookingService->getEndsAt());
            if($bookingService->getAttendant()){
                $attendants = $bookingService->getAttendant();
                if(!is_array($attendants)){
                    $desc .= ' - ' . $bookingService->getAttendant()->getName();
                }else{
                    $desc .= ' - ' . SLN_Wrapper_Attendant::implodeArrayAttendantsName(', ', $attendants);
                }
            }
        }
        $notes = $booking->getNote();
        $desc .= "\n\n" . __('Booking notes', 'salon-booking-system') . ":\n" . (empty($notes) ? __("None", 'salon-booking-system') : $notes);
        $desc .= "\n\n" . __('Booking status', 'salon-booking-system') . ": " . SLN_Enum_BookingStatus::getLabel($booking->getStatus());
        if(!$plugin->getSettings()->isPayEnabled()){
            $desc .= "\n\n" . __('Booking URL', 'salon-booking-system') . ": " . get_edit_post_link($booking->getId(), null);
        } else{
            $desc .= "\n\n" . __('Booking URL', 'salon-booking-system') . ": " . self::get_edit_post_link_dont_check_can($booking->getId(), null);
        }

	$netTotalAmount = $booking->getAmount();

	$discountAmount = $booking->getMeta('discount_amount');

	if ($discountAmount) {
	    $discountAmount = array_sum($discountAmount);
	}

	$totalAmount = (float)$netTotalAmount + (float)$discountAmount;

	$desc .= "\n\n" . __('Total amount', 'salon-booking-system') . ": " . $plugin->format()->moneyFormatted($totalAmount, true, true);
	$desc .= "\n\n" . __('Discount amount', 'salon-booking-system') . ": " . $plugin->format()->moneyFormatted($discountAmount, false, true);
	$desc .= "\n\n" . __('Net total amount', 'salon-booking-system') . ": " . $plugin->format()->moneyFormatted($netTotalAmount, true, true);

        $title = $booking->getDisplayName() . " - " . $plugin->format()->datetime($booking->getStartsAt());
        sln_my_wp_log($title);

        $event = new Google_Service_Calendar_Event();
        $event->setSummary($title);
        $event->setDescription($desc);
        $event->setLocation($booking->getAddress());

        $start = new Google_Service_Calendar_EventDateTime();
        $str_date = $booking->getStartsAt()->getTimestamp();
        $dateTimeS = SLN_GoogleScope::date3339($str_date);
        sln_my_wp_log("start_date");
        sln_my_wp_log($dateTimeS);
        $start->setDateTime($dateTimeS);
        $event->setStart($start);

        $end = new Google_Service_Calendar_EventDateTime();
        $str_date = $booking->getEndsAt()->getTimestamp();
        $dateTimeE = SLN_GoogleScope::date3339($str_date);
        sln_my_wp_log("end_date");
        sln_my_wp_log($dateTimeE);
        $end->setDateTime($dateTimeE);
        $event->setEnd($end);

        return apply_filters('sln.google_calendar_event_factory.get_event', $event, $booking);
    }

    static protected function get_edit_post_link_dont_check_can($post, $context){
        $post = get_post( $post );

        if ( ! $post ) {
            return;
        }

        if ( 'revision' === $post->post_type ) {
            $action = '';
        } elseif ( 'display' === $context ) {
            $action = '&amp;action=edit';
        } else {
            $action = '&action=edit';
        }

        $post_type_object = get_post_type_object( $post->post_type );

        if ( ! $post_type_object ) {
            return;
        }

        if ( $post_type_object->_edit_link ) {
            $link = admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) );
        } else {
            $link = '';
        }
        return $link;
    }

}

function synch_a_booking($booking, $sync = false) {
    if (!$sync)
        remove_action('save_post', 'test_booking', 12, 2);

    if(is_int($booking)){
        $booking = new SLN_Wrapper_Booking($booking);
    }
    sln_my_wp_log("############################################################################");
    $statusForPublish = array(
        SLN_Enum_BookingStatus::PAID,
        SLN_Enum_BookingStatus::CONFIRMED,
        SLN_Enum_BookingStatus::PAY_LATER,
    );

    if (SLN_Plugin::getInstance()->getSettings()->get('google_calendar_publish_pending_payment')) {
        $statusForPublish[] = SLN_Enum_BookingStatus::PENDING_PAYMENT;
    }
    sln_my_wp_log($booking);
    sln_my_wp_log($booking->getStartsAt());
    sln_my_wp_log($booking->getEndsAt());

    $event_id = "";
    $b_event_id = get_post_meta($booking->getId(), '_sln_calendar_event_id', true);
    sln_my_wp_log($b_event_id);
    sln_my_wp_log($booking->getStatus());

    if (!in_array($booking->getStatus(), $statusForPublish)) {
        //If cancelled and i have a event i need to delete it or do return
        if (isset($b_event_id) && !empty($b_event_id)) {
            sln_my_wp_log("delete");
            try {
                $GLOBALS['sln_googlescope']->delete_event_from_booking($b_event_id);
                update_post_meta($booking->getId(), '_sln_calendar_event_id', '');
            } catch (Exception $e) {
                sln_my_wp_log($e);
            }
        } else {
            sln_my_wp_log("do nothing");
        }
    } else {
		$error = '';
		try {
		    $GLOBALS['sln_googlescope']->validate_booking_services($booking);
		} catch (Exception $ex) {
		    $error = $ex->getMessage();
		}
        if (isset($b_event_id) && !empty($b_event_id)) {
            sln_my_wp_log("update");
            if(get_post_meta($booking->getId(),'_sln_booking_shop',true)){
                $shop_id = get_post_meta($booking->getId(),'_sln_booking_shop',true);
                $shop_calendar = get_post_meta($shop_id,'_sln_shop_google_client_calendar',true);
                $GLOBALS['sln_googlescope']->google_client_calendar = $shop_calendar;
            }
            try {
			    $event_id = $GLOBALS['sln_googlescope']->update_event_from_booking($booking, $b_event_id, false, $error);
            } catch (Exception $e) {
                $b_event_id = "";
                update_post_meta($booking->getId(), '_sln_calendar_event_id', '');
            }
        }
        if (!(isset($b_event_id) && !empty($b_event_id))) {
            sln_my_wp_log("create");
            if(get_post_meta($booking->getId(),'_sln_booking_shop',true)){
                $shop_id = get_post_meta($booking->getId(),'_sln_booking_shop',true);
                $shop_calendar = get_post_meta($shop_id,'_sln_shop_google_client_calendar',true);
                $GLOBALS['sln_googlescope']->google_client_calendar = $shop_calendar;
            }
            try {
                $event_id = $GLOBALS['sln_googlescope']->create_event_from_booking($booking, false, $error);
                update_post_meta($booking->getId(), '_sln_calendar_event_id', $event_id);
            } catch (Exception $e) {
                sln_my_wp_log($e);
            }
        }
                sln_my_wp_log($event_id);
    }

    $main_calendar = $GLOBALS['sln_googlescope']->google_client_calendar;

    $events = get_post_meta($booking->getId(), '_sln_calendar_attendants_events_id', true);
    if (!is_array($events)) {
        $events = array();
    }
    $booking_attendants_ids = $booking->getAttendantsIds();
    $all_attendants = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT)->getAll();
    /** @var SLN_Wrapper_Attendant $attendant */
    foreach($all_attendants as $attendant) {
        $att_google_calendar = $attendant->getGoogleCalendar();
        if (empty($att_google_calendar)) {
            continue;
        }
        $GLOBALS['sln_googlescope']->google_client_calendar = $att_google_calendar;

        $att_id = $attendant->getId();
        if (!in_array($booking->getStatus(), $statusForPublish)) {
            if (isset($events[$att_id]) && !empty($events[$att_id])) {
                sln_my_wp_log("delete");
                try {
                    $GLOBALS['sln_googlescope']->delete_event_from_booking($events[$att_id]);
                    unset($events[$att_id]);
                } catch (Exception $e) {
                    sln_my_wp_log($e);
                }
            }
        } else {
            if (!in_array($att_id, $booking_attendants_ids)) {
                if (isset($events[$att_id]) && !empty($events[$att_id])) {
                    sln_my_wp_log("delete");
                    try {
                        $GLOBALS['sln_googlescope']->delete_event_from_booking($events[$att_id]);
                        unset($events[$att_id]);
                    } catch (Exception $e) {
                        sln_my_wp_log($e);
                    }
                }
            } else {
                $error = '';
                try {
                    $GLOBALS['sln_googlescope']->validate_booking_services($booking);
                } catch (Exception $ex) {
                    $error = $ex->getMessage();
                }
                if (isset($events[$att_id]) && !empty($events[$att_id])) {
                    sln_my_wp_log("update");
                    try {
                        $events[$att_id] = $GLOBALS['sln_googlescope']->update_event_from_booking($booking, $events[$att_id], false, $error);
                    } catch (Exception $e) {
                        $events[$att_id] = "";
                    }
                }
                if (!(isset($events[$att_id]) && !empty($events[$att_id]))) {
                    sln_my_wp_log("create");
                    try {
                        $events[$att_id] = $GLOBALS['sln_googlescope']->create_event_from_booking($booking, false, $error);
                    } catch (Exception $e) {
                        sln_my_wp_log($e);
                    }
                }
                sln_my_wp_log($events[$att_id]);
                $events = update_post_meta($booking->getId(), '_sln_calendar_attendants_events_id', $events);

            }
        }
    }



    if (SLN_Plugin::getInstance()->getSettings()->get('google_calendar_publish_pending_payment') || $booking->getStatus() == SLN_Enum_BookingStatus::PAID) {
        do_action('sln.google_calendar.synch_a_booking', $booking, $statusForPublish);
    }
    $GLOBALS['sln_googlescope']->google_client_calendar = $main_calendar;
}

add_action('sln.booking_builder.create.booking_created', 'synch_a_booking', 12, 2);

class SLN_Bookings_Handle {

    private $from;

    public function __construct($from) {
        $this->from = $from;
    }

    public function getBookings() {
        return $this->getResults();
    }

    private function getResults() {
        $bookings = $this->buildBookings();
        $ret = array();
        foreach ($bookings as $b) {
            $ret[] = $b;
        }
        return $ret;
    }

    private function buildBookings() {
        $args = array(
            'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
            'nopaging' => true,
            'meta_query' => $this->getCriteria()
        );

        $args = apply_filters('sln.google-scope.build-bookings.processCriteria', $args);

        $query = new WP_Query($args);
        $ret = array();
        foreach ($query->get_posts() as $p) {
            $ret[] = $p;
        }
        wp_reset_query();
        wp_reset_postdata();

        return $ret;
    }

    public function createBooking($booking) {
        if (is_int($booking)) {
            $booking = get_post($booking);
        }

        return new SLN_Wrapper_Booking($booking);
    }

    private function getCriteria() {
        $from = $this->from->format('Y-m-d');
        $criteria = array(
            array(
                'key' => '_sln_booking_date',
                'value' => $from,
                'compare' => '>=',
            )
        );
        return $criteria;
    }

    private function getTitle($booking) {
        return $booking->getTitle();
    }

    private function getEventHtml($booking) {
        return $booking->getDisplayName();
    }

}
?>
