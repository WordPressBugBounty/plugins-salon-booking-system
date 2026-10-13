<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

require_once SLN_PLUGIN_DIR . '/src/SLN/Third/calendar/vendor/autoload.php';

use Spatie\CalendarLinks\Link;

class SLN_Helper_CalendarLink
{
    public static function getGoogleLink($booking) {
	return self::generateLink($booking)->google();
    }

    /**
     * Temp .ics paths created for the current request. Deleted after wp_mail.
     *
     * @var string[]
     */
    private static $tempFiles = array();

    public static function getICallLink($booking, $data) {

	$content = self::getIcsContent($booking);
	$filepath = self::writeTempIcs($content);
	if ($filepath) {
		self::$tempFiles[] = $filepath;
		if ($data instanceof ArrayAccess) {
			$attachments   = $data->offsetExists('attachments') ? $data['attachments'] : array();
			$attachments   = is_array($attachments) ? $attachments : array();
			$attachments[] = $filepath;
			$data['attachments'] = $attachments;
		} elseif (is_array($data)) {
			if (!isset($data['attachments']) || !is_array($data['attachments'])) {
				$data['attachments'] = array();
			}
			$data['attachments'][] = $filepath;
		}
	}

	// The email icon points at a tokenised download. Nothing is written under uploads/.
	return self::getDownloadUrl($booking);
    }

    /**
     * Remove temp .ics files created while rendering a booking email.
     */
    public static function deleteTempFiles() {
        foreach (self::$tempFiles as $filepath) {
            if (is_string($filepath) && is_file($filepath)) {
                unlink($filepath);
            }
        }
        self::$tempFiles = array();
    }

    /**
     * Stream an .ics for a booking the caller can prove they know about.
     * The token is the existing unguessable "{id}-{hash}" unique id.
     */
    public static function serveIcsDownload() {
        $token = isset($_REQUEST['booking']) ? sanitize_text_field(wp_unslash($_REQUEST['booking'])) : '';
        if (!preg_match('/^(\d+)-[a-f0-9]{32}$/', $token, $matches)
            || get_post_type((int) $matches[1]) !== SLN_Plugin::POST_TYPE_BOOKING
            || !get_post_meta((int) $matches[1], '_' . SLN_Plugin::POST_TYPE_BOOKING . '_uniqid', true)
        ) {
            status_header(404);
            exit;
        }

        try {
            $booking = SLN_Plugin::getInstance()->createBooking($token);
        } catch (Exception $e) {
            status_header(404);
            exit;
        }

        if (!$booking || !$booking->getId()) {
            status_header(404);
            exit;
        }

        $content = self::getIcsContent($booking);
        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="booking-' . intval($booking->getId()) . '.ics"');
        header('X-Robots-Tag: noindex, nofollow', true);
        echo $content;
        exit;
    }

    /**
     * Delete legacy public files (uploads/Mycal_*.ics) left by older versions.
     * Runs at most once a day.
     */
    public static function maybeSweepPublicIcsFiles() {
        if (get_transient('sln_ics_public_sweep')) {
            return;
        }
        set_transient('sln_ics_public_sweep', 1, DAY_IN_SECONDS);
        self::sweepPublicIcsFiles();
    }

    public static function sweepPublicIcsFiles() {
        $basedir = wp_upload_dir();
        $basedir = isset($basedir['basedir']) ? $basedir['basedir'] : '';
        if (!$basedir || !is_dir($basedir)) {
            return;
        }
        $files = glob(trailingslashit($basedir) . 'Mycal_*.ics');
        if (!is_array($files)) {
            return;
        }
        foreach ($files as $filepath) {
            if (is_file($filepath)) {
                unlink($filepath);
            }
        }
    }

    public static function getDownloadUrl($booking) {
        return add_query_arg(
            array(
                'action'  => 'sln_ics',
                'booking' => $booking->getUniqueId(),
            ),
            admin_url('admin-ajax.php')
        );
    }

    public static function getIcsContent($booking) {
        return base64_decode(str_replace(
            array('data:text/calendar;charset=utf8;base64,', '%0d%0a'),
            array('', "\r\n"),
            self::generateLink($booking)->ics()
        ));
    }

    private static function writeTempIcs($content) {
        if (!is_string($content) || $content === '') {
            return '';
        }
        $filepath = trailingslashit(get_temp_dir()) . 'sln-ics-' . wp_generate_password(16, false) . '.ics';
        if (file_put_contents($filepath, $content) === false) {
            return '';
        }
        return $filepath;
    }

    public static function cronUnlinkCall($calName = ''){
        if (!$calName || !is_string($calName)) {
            return;
        }
        // Only the legacy public filenames. Reject anything that could leave uploads/.
        if (!preg_match('/^Mycal_\d+\.ics$/', $calName)) {
            wp_clear_scheduled_hook('sln.helper.calendar_link.remove', $calName);
            return;
        }

        $basedir = wp_upload_dir();
        $basedir = isset($basedir['basedir']) ? $basedir['basedir'] : '';
        $filepath = $basedir ? trailingslashit($basedir) . $calName : '';
        if ($filepath && is_file($filepath)) {
            unlink($filepath);
        }
        wp_clear_scheduled_hook('sln.helper.calendar_link.remove', $calName);
    }

    public static function getOutlookLink($booking) {
	return self::generateLink($booking)->webOutlook();
    }

    protected static function generateLink($booking) {

	$plugin   = SLN_Plugin::getInstance();
	$settings = $plugin->getSettings();

	$from = $booking->getStartsAt($booking->getCustomerTimezone());
	$to   = $booking->getEndsAt($booking->getCustomerTimezone());

	$title    = $settings->getSalonName() . " - " . $plugin->format()->datetime($booking->getStartsAt());
	$location = $settings->get('gen_address');

	$desc = "";
        $desc .= __('Salon name', 'salon-booking-system') . ": " . $settings->getSalonName() . " \n\n";
        $desc .= __('Salon address', 'salon-booking-system') . ": " . $settings->get('gen_address') . " \n\n";
        $desc .= __('Salon phone', 'salon-booking-system') . ": " . $settings->get('gen_phone') . " \n\n";
        $desc .= __('Salon email', 'salon-booking-system') . ": " . $settings->getSalonEmail() . " \n";
        //Services
        $desc .= "\n" . __('Booked services', 'salon-booking-system') . ":";
        foreach ($booking->getBookingServices()->getItems() as $bookingService) {
            $desc .= "\n";
            $serviceCategory = $bookingService->getService()->getServiceCategory();
            $desc .= (!empty($serviceCategory)? ($serviceCategory->getName(). '/') : '') . $bookingService->getService()->getName() . ': ' .
                     $plugin->format()->time($bookingService->getStartsAt()) . ' ➝ ' .
                     $plugin->format()->time($bookingService->getEndsAt());
            if($attendant = $bookingService->getAttendant()){
                $desc .= ' - ' . (!is_array($attendant) ? $bookingService->getAttendant()->getName() : SLN_Wrapper_Attendant::implodeArrayAttendantsName(' ', $attendant));
            }
        }
        $notes = $booking->getNote();
        $desc .= "\n\n" . __('Booking notes', 'salon-booking-system') . ":\n" . (empty($notes) ? __("None", 'salon-booking-system') : $notes);
        $desc .= "\n\n" . __('Customer name', 'salon-booking-system') . ":\n" . $booking->getFirstname() .' '.$booking->getLastname();
        $desc .= "\n\n" . __('Booking status', 'salon-booking-system') . ": " . SLN_Enum_BookingStatus::getLabel($booking->getStatus());

	$link = Link::create($title, $from, $to)
		    ->description($desc)
		    ->address($location ? $location : '');

	return $link;
    }

}