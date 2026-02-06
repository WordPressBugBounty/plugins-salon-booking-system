<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_TimeFunc
{
    protected static $wp_locale;

    public static function startRealTimezone()
    {
    }

    public static function endRealTimezone()
    {
    }

    public static function getWpTimezone() {
        static $static_wp_timezone;
        if(null === $static_wp_timezone ){
            if(!self::getTimezoneWpSettingsOption() && function_exists('wp_timezone')){
                $static_wp_timezone = wp_timezone();
                return $static_wp_timezone;
            }
            $static_wp_timezone = new DateTimeZone( self::getWpTimezoneString() );
        }
        return $static_wp_timezone;
    }

    public static function getWpTimezoneString() {
        static $static_timezone_string;

        if(null === $static_timezone_string ){

            $timezone_string = self::getTimezoneWpSettingsOption();

            if ( $timezone_string ) {
                $static_timezone_string = $timezone_string;
                return $static_timezone_string;
            }

            if(function_exists('wp_timezone_string')){
                $static_timezone_string = wp_timezone_string();
                return $static_timezone_string;
            }

            $offset  = (float) get_option( 'gmt_offset' );
            $hours   = (int) $offset;
            $minutes = ( $offset - $hours );

            $sign      = ( $offset < 0 ) ? '-' : '+';
            $abs_hour  = abs( $hours );
            $abs_mins  = abs( $minutes * 60 );
            $tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

            $static_timezone_string = $tz_offset;
        }
        return $static_timezone_string;
    }

    public static function translateDate($format,$timestamp = null, $timezone = null){
        if(function_exists('wp_date')) {
	    global $wp_locale;
	    $wp_locale_tmp  = $wp_locale;
	    $wp_locale	    = self::getWpLocale();
	    $date	= wp_date($format,$timestamp, $timezone);
	    $wp_locale	= $wp_locale_tmp;
	    return $date;
        }

        if ( ! is_numeric( $timestamp ) ) {
            $timestamp = time();
        }
        if(date_default_timezone_get () === 'UTC'){
            $datetime = new DateTime;
            $datetime->setTimestamp($timestamp);
            if(!$timezone) $timezone = self::getWpTimezone();
            $datetime->setTimezone($timezone);
            $timestamp = $timestamp + $datetime->getOffset();
        }

        return 'U' === $format ? $timestamp : date_i18n( $format,$timestamp  );
    }

    public static function currentDateTime(){
        if(function_exists('current_datetime')){
            return current_datetime();
        }
        return new DateTimeImmutable( 'now', self::getWpTimezone() );
    }

    public static function getCurrentTimestamp(){
        $datetime = self::currentDateTime();
        return $datetime->getTimestamp();
    }

    public static function getPostDateTime($post = null,$field = 'date', $source = 'local'){
        if(function_exists('get_post_datetime')){
            return get_post_datetime($post,$field, $source);
        }

        $post = get_post( $post );

        if ( ! $post ) {
            return false;
        }

        $wp_timezone = self::getWpTimezone();

        if ( 'gmt' === $source ) {
            $time     = ( 'modified' === $field ) ? $post->post_modified_gmt : $post->post_date_gmt;
            $timezone = new DateTimeZone( 'UTC' );
        } else {
            $time     = ( 'modified' === $field ) ? $post->post_modified : $post->post_date;
            $timezone = $wp_timezone;
        }

        if ( empty( $time ) || '0000-00-00 00:00:00' === $time ) {
            return false;
        }

        $datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', $time, $timezone );

        if ( false === $datetime ) {
            return false;
        }

        return $datetime->setTimezone( $wp_timezone );
    }

    public static function getPostTimestamp($post = null,$field = 'date'){
        if(function_exists('get_post_timestamp')){
            return get_post_timestamp($post, $field);
        }
        $datetime = self::getPostDateTime( $post, $field );

        if ( false === $datetime ) {
            return false;
        }

        return $datetime->getTimestamp();
    }

    public static function evalPickedDate($date)
    {
        // Handle empty or null dates
        if (empty($date)) {
            throw new Exception('Empty date provided to evalPickedDate');
        }
        
        if (strpos($date, '-'))
            return $date;
        $initial = $date;
        $f = SLN_Plugin::getInstance()->getSettings()->getDateFormat();
        if ($f == SLN_Enum_DateFormat::_DEFAULT) {
            if(!strpos($date, ' ')) throw new Exception('bad date format, date ' . $initial . ' format: ' . $f);
            $date = explode(' ', $date);
            
            // Validate date array has required parts
            if (count($date) < 2) {
                throw new Exception('Invalid date format: ' . $initial . '. Expected format: "day month year" or "day monthname year"');
            }
            
            // Handle case where day and month are concatenated (e.g., "30Oct" instead of "30 Oct")
            if (count($date) == 2) {
                // Split first part into day and month (e.g., "30Oct" -> "30" + "Oct")
                if (preg_match('/^(\d+)([A-Za-z]+)$/', $date[0], $matches)) {
                    $date = array($matches[1], $matches[2], $date[1]); // [day, month, year]
                } else {
                    // If can't split, the date format is invalid
                    throw new Exception('Invalid date format: ' . $initial . '. Expected "day month year" but got only 2 parts.');
                }
            }
            
            // Validate we have at least 3 parts now (day, month, year)
            if (count($date) < 3) {
                throw new Exception('Invalid date format: ' . $initial . '. Expected format: "day month year" with 3 parts.');
            }
            
            switch(count(explode(' ', SLN_Func::getMonths()[1]))){
                case 1:
                    $k = self::guessMonthNum($date[1]);
                    $ret = $date[2] . '-' . ($k < 10 ? '0' . $k : $k) . '-' . $date[0];
                    break;
                case 2:
                    $k = self::guessMonthNum(implode(' ', array($date[1], $date[2])));
                    $ret = $date[3] . '-'. ($k < 10 ? '0' . $k : $k) . '-' . $date[0];
                    break;
                default:
                    throw new Exception('bad number of slashes, date ' . $initial . ' format: ' . $f);
            }
            return $ret;
        } elseif ($f == SLN_Enum_DateFormat::_SHORT) {
            $date = explode('/', $date);
            if (count($date) == 3)
                return sprintf('%04d-%02d-%02d', $date[2], $date[1], $date[0]);
            else
                throw new Exception('bad number of slashes, date ' . $initial . ' format: ' . $f);
        }elseif ($f == SLN_Enum_DateFormat::_SHORT_COMMA) {
            $date = explode('-', $date);
            if (count($date) == 3)
                return sprintf('%04d-%02d-%02d', $date[2], $date[1], $date[0]);
            else
                throw new Exception('bad number of commas, date ' . $initial . ' format: ' . $f);
        }else {
            return (new SLN_DateTime($date))->format('Y-m-d');
        }
        throw new Exception('wrong date ' . $initial . ' format: ' . $f);
    }

    public static function guessMonthNum($monthName)
    {
        // Check if the monthName is actually a year (4 digits) - indicates malformed date
        if (preg_match('/^\d{4}$/', $monthName)) {
            throw new \Exception(sprintf('Invalid date format: received year "%s" where month name was expected. Original date may be missing the month value.', $monthName));
        }
        
        $months = SLN_Func::getMonths();
        foreach ($months as $k => $v) {
            if ($monthName == $v) {
                return $k;
            }
        }
        foreach ($months as $k => $v) {
            if(SLN_Func::removeAccents($monthName) == SLN_Func::removeAccents($v)) {
                return $k;
            }
        }
        foreach ($months as $k => $v) {
            if (substr($monthName,0,3) == substr($v,0,3)) {
                return $k;
            }
        }
        foreach ($months as $k => $v) {
            if (substr(SLN_Func::removeAccents($monthName),0,3) == substr(SLN_Func::removeAccents($v),0,3)) {
                return $k;
            }
        }

        throw new \Exception(sprintf('month %s not found in months %s', $monthName, implode(', ', $months)));
    }

    public static function evalPickedTime($val){
        if ($val instanceof DateTime || $val instanceof DateTimeImmutable ) {
            $val = $val->format('H:i');
        }
        if (empty($val)) {
            return null;
        }
        
        // FIRST: Handle 12-hour format (am/pm) - convert to 24-hour format
        // This must be done BEFORE any other processing to avoid stripping am/pm indicators
        $val = trim(strtolower($val));
        $isPM = false;
        $isAM = false;
        
        if (strpos($val, 'pm') !== false) {
            $isPM = true;
            $val = str_replace(['pm', ' '], '', $val);
        } elseif (strpos($val, 'am') !== false) {
            $isAM = true;
            $val = str_replace(['am', ' '], '', $val);
        }
        
        // Handle time strings without colon (e.g., '1100', '11')
        if (strpos($val, ':') === false) {
            if(strlen($val) == 2){
                $val .= ':00';
            }else{
                $val = intval(substr($val, 0, 2)) . ':'. intval(substr($val, strlen($val)-2, 2));
            }
        }
        
        // Now handle time strings with colon (e.g., '11:', '11:5', '09:301')
        $parts = explode(':', $val);
        $hours = isset($parts[0]) ? intval($parts[0]) : 0;
        $minutesStr = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : '0';
        
        // Fix malformed minutes: if more than 2 digits, take only first 2 (09:301 -> 30)
        if (strlen($minutesStr) > 2) {
            $minutesStr = substr($minutesStr, 0, 2);
        }
        
        $minutes = intval($minutesStr);
        
        // Apply 12-hour to 24-hour conversion
        if ($isPM && $hours !== 12) {
            $hours += 12;
        } elseif ($isAM && $hours === 12) {
            $hours = 0;
        }
        
        // Validate hours and minutes
        $hours = max(0, min(23, $hours));
        $minutes = max(0, min(59, $minutes));
        
        // Reconstruct as properly formatted time
        $val = sprintf('%02d:%02d', $hours, $minutes);
        
        try {
            return (new SLN_DateTime('1970-01-01 ' . sanitize_text_field($val)))->format('H:i');
        } catch (Exception $e) {
            // If time parsing still fails, log and return default
            SLN_Plugin::addLog('ERROR: Invalid time format "' . $val . '": ' . $e->getMessage());
            return '00:00';
        }
    }

    public static function getTimezoneWpSettingsOption() {
       return apply_filters('sln.date_time.get_timezone_wp_settings_option', get_option('timezone_string'));
    }

    public static function strtotime($val){
        return (new SLN_DateTime($val))->getTimestamp();
    }

    public static function date($format,$timestamp = null){
        $timestamp = $timestamp === null ? time() : $timestamp;
        return (new SLN_DateTime)->setTimestamp($timestamp)->format($format);
    }

    public static function getWpLocale() {

	if (static::$wp_locale) {
	    return static::$wp_locale;
	}

	load_default_textdomain(SLN_Plugin::getInstance()->getSettings()->getDateLocale());

	static::$wp_locale = new WP_Locale();

	load_default_textdomain();

	return static::$wp_locale;
    }

    public static function wpLocale2DatepickerLocale($wp_locale){
        $settings = SLN_Plugin::getInstance()->getSettings();
        $locale = $settings->getDateLocale() ?? get_user_locale();
        $weekday_first_short = array($wp_locale->get_weekday_abbrev($wp_locale->weekday[0]));
        return array(
            'locale' => $locale,
            'locale_data' => array(
                'days' => array_merge($wp_locale->weekday, array($wp_locale->weekday[0])),
                'daysShort' => array_merge(array_values($wp_locale->weekday_abbrev), $weekday_first_short),
                'daysMin' => array_merge(array_values($wp_locale->weekday_abbrev), $weekday_first_short),
                'months' => array_map('ucfirst', array_values($wp_locale->month)),
                'monthsShort' => array_map('ucfirst', array_values($wp_locale->month_abbrev)),
                'meridiem' => array(
                    $wp_locale->meridiem['am'],
                    $wp_locale->meridiem['pm']
                ),
                'today' => __('Today'),
                'suffix' => array()
            )
        );
    }

    public static function wpLocale2CalendarLocale($locale){
        $settings = SLN_Plugin::getInstance()->getSettings();
        $locale_name = $settings->getDateLocale() ?? get_user_locale();
        $locale_name = str_replace('_', '-', $locale_name);
        $wpLang     = array('el', 'fi', 'hr', 'ja', 'nb-NO', 'sl-SI');
        $calLang    = array('el-GR', 'fi-FI', 'hr-HR', 'ja-JP', 'no-NO', 'sl-SL');
        $locale_name     = str_replace($wpLang, $calLang, $locale_name);
        return array(
            'locale' => $locale_name,
            'locale_data' => array(
                'error_noview' => sprintf(__('Calendar: View %s not found', 'salon-booking-system'), '{0}'),
                'error_dateformat' => sprintf(__('Calendar: Wrong date format %s. Should be either "now" or "yyyy-mm-dd"', 'salon-booking-system'), '{0}'),
                'error_loadurl' => __('Calendar: Event URL is not set', 'salon-booking-system'),
                'error_where' => sprintf(__('Calendar: Wrong navigation direction %s. Can be only "next" or "prev" or "today"', 'salon-booking-system'), '{0}'),
                'error_timedevide' => __('Calendar: Time split parameter should divide 60 without decimals. Something like 10, 15, 30', 'salon-booking-system'),

                'no_events_in_day' => __('No events in this day.', 'salon-booking-system'),

                // {0} will be replaced with the year (example: 2013)
                'title_year' => '{0}',
                // {0} will be replaced with the month name (example: September)
                // {1} will be replaced with the year (example: 2013)
                'title_month' => '{0} {1}',
                // {0} will be replaced with the week number (example: 37)
                // {1} will be replaced with the year (example: 2013)
                'title_week' => sprintf(__('week %s of %s', 'salon-booking-system'), '{0}', '{1}'),
                // {0} will be replaced with the weekday name (example: Thursday)
                // {1} will be replaced with the day of the month (example: 12)
                // {2} will be replaced with the month name (example: September)
                // {3} will be replaced with the year (example: 2013)
                'title_day' => '{0} {1} {2}, {3}',

                'week' => sprintf(__('Week %s', 'salon-booking-system'), '{0}'),
                'all_day' => __('All day', 'salon-booking-system'),
                'time' => __('Time', 'salon-booking-system'),
                'events' => __('Events', 'salon-booking-system'),
                'before_time' => __('Ends before timeline', 'salon-booking-system'),
                'after_time' => __('Starts after timeline', 'salon-booking-system'),

                'm0' => ucfirst($locale->get_month(1)),
                'm1' => ucfirst($locale->get_month(2)),
                'm2' => ucfirst($locale->get_month(3)),
                'm3' => ucfirst($locale->get_month(4)),
                'm4' => ucfirst($locale->get_month(5)),
                'm5' => ucfirst($locale->get_month(6)),
                'm6' => ucfirst($locale->get_month(7)),
                'm7' => ucfirst($locale->get_month(8)),
                'm8' => ucfirst($locale->get_month(9)),
                'm9' => ucfirst($locale->get_month(10)),
                'm10' => ucfirst($locale->get_month(11)),
                'm11' => ucfirst($locale->get_month(12)),

                'ms0' => ucfirst($locale->get_month_abbrev($locale->get_month(1))),
                'ms1' => ucfirst($locale->get_month_abbrev($locale->get_month(2))),
                'ms2' => ucfirst($locale->get_month_abbrev($locale->get_month(3))),
                'ms3' => ucfirst($locale->get_month_abbrev($locale->get_month(4))),
                'ms4' => ucfirst($locale->get_month_abbrev($locale->get_month(5))),
                'ms5' => ucfirst($locale->get_month_abbrev($locale->get_month(6))),
                'ms6' => ucfirst($locale->get_month_abbrev($locale->get_month(7))),
                'ms7' => ucfirst($locale->get_month_abbrev($locale->get_month(8))),
                'ms8' => ucfirst($locale->get_month_abbrev($locale->get_month(9))),
                'ms9' => ucfirst($locale->get_month_abbrev($locale->get_month(10))),
                'ms10' => ucfirst($locale->get_month_abbrev($locale->get_month(11))),
                'ms11' => ucfirst($locale->get_month_abbrev($locale->get_month(12))),

                'd0' => $locale->weekday[0],
                'd1' => $locale->weekday[1],
                'd2' => $locale->weekday[2],
                'd3' => $locale->weekday[3],
                'd4' => $locale->weekday[4],
                'd5' => $locale->weekday[5],
                'd6' => $locale->weekday[6],

                // Which is the first day of the week (2 for sunday, 1 for monday)
                'first_day' => $settings->get('week_start'),

                'holidays' =>array(),
            )
        );
    }

}
