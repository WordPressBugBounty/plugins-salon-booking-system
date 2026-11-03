<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * "Book" links of the weekly schedule: preselect service, assistant, date and
 * time in the visitor's BookingBuilder, then open the booking form.
 *
 * By default the form opens on the details step, skipping the choices already
 * made. The booking itself is still created only by the form's own steps, which
 * validate the slot again. The form opens earlier when there is something left
 * to choose (quantity, secondary services), and on the date step with a notice
 * when the class is no longer bookable (the schedule may be cached).
 *
 * Same approach as SLN_Shortcode_SalonServices::listen_book_now_redirect(), it
 * only touches the visitor's own booking session.
 */
class SLN_WeeklySchedule_BookRedirect
{
    const ACTION     = 'sln_ws_book';
    const NOTICE_KEY = 'sln_ws_notice';
    const ANCHOR     = 'sln-salon';

    const STEP_DETAILS = 'details';
    const STEP_DATE    = 'date';

    public static function init()
    {
        add_action('wp_loaded', array(__CLASS__, 'listen'));
        add_action('sln.view.shortcode/salon_date.before_template', array(__CLASS__, 'showNotice'));
    }

    public static function getUrl($bookingUrl, array $event, $bookStep = self::STEP_DETAILS)
    {
        $params = array(
            self::ACTION     => 1,
            'sln_ws_service' => $event['service_id'],
            'sln_ws_date'    => $event['date'],
            'sln_ws_time'    => $event['time'],
        );
        if (!empty($event['assistant_id'])) {
            $params['sln_ws_assistant'] = $event['assistant_id'];
        }
        if (self::STEP_DATE === $bookStep) {
            $params['sln_ws_step'] = self::STEP_DATE;
        }

        return add_query_arg($params, $bookingUrl);
    }

    public static function listen()
    {
        // No nonce: the link only preselects the visitor's own booking session
        // (like the services shortcode "Book now"), and nonces would expire in
        // page-cached schedules. Every value is validated in book().
        if (!filter_input(INPUT_GET, self::ACTION) || is_admin()) {
            return;
        }
        // Only a page the visitor opens: an image, script or frame embedded on
        // another site must not replace the booking they are making.
        $dest = isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_SEC_FETCH_DEST'])) : '';
        if ($dest !== '' && $dest !== 'document') {
            return;
        }
        $serviceId   = absint(filter_input(INPUT_GET, 'sln_ws_service', FILTER_SANITIZE_NUMBER_INT));
        $assistantId = absint(filter_input(INPUT_GET, 'sln_ws_assistant', FILTER_SANITIZE_NUMBER_INT));
        $date        = sanitize_text_field((string) filter_input(INPUT_GET, 'sln_ws_date'));
        $time        = sanitize_text_field((string) filter_input(INPUT_GET, 'sln_ws_time'));
        $bookStep    = self::STEP_DATE === filter_input(INPUT_GET, 'sln_ws_step') ? self::STEP_DATE : self::STEP_DETAILS;

        $target = remove_query_arg(array(self::ACTION, 'sln_ws_service', 'sln_ws_assistant', 'sln_ws_date', 'sln_ws_time', 'sln_ws_step', SLN_Shortcode_Salon::STEP_KEY));

        $step = self::book($serviceId, $assistantId, $date, $time, $bookStep);
        if ($step) {
            // Jump to the form, which may sit below the schedule on the same page.
            $target = add_query_arg(SLN_Shortcode_Salon::STEP_KEY, $step, $target) . '#' . self::ANCHOR;
        }

        wp_safe_redirect($target);
        exit;
    }

    /**
     * Preselect the class and choose the booking form step to open.
     *
     * @param string $bookStep "details" to skip ahead, "date" to always confirm the slot.
     *
     * @return string|null Step to open, null when the request doesn't describe a lesson.
     */
    public static function book($serviceId, $assistantId, $date, $time, $bookStep = self::STEP_DETAILS)
    {
        if (!self::preselect($serviceId, $assistantId, $date, $time)) {
            return null;
        }

        $plugin  = SLN_Plugin::getInstance();
        $service = $plugin->createService($serviceId);
        $status  = self::checkSlot($serviceId, $assistantId, $date, $time);
        if (SLN_WeeklySchedule_Builder::STATUS_AVAILABLE !== $status) {
            self::setNotice(SLN_WeeklySchedule_Builder::STATUS_FULL === $status
                ? __('Sorry, this class is now full. Please choose another time.', 'salon-booking-system')
                : __('Sorry, this class can no longer be booked. Please choose another time.', 'salon-booking-system'));

            return self::STEP_DATE;
        }
        $wizard = new SLN_Shortcode_Salon($plugin, array());
        $steps  = $wizard->getSteps();

        // Open the first step that still has a choice to make. A shop step
        // (Multi-Shops) comes first: without a shop the booking can't be created.
        if (in_array('shop', $steps, true) && !$plugin->getBookingBuilder()->get('shop')) {
            $candidates = array('shop');
        } elseif (self::STEP_DATE === $bookStep) {
            $candidates = array(self::STEP_DATE);
        } elseif ($service->isQuantityEnabled() || $service->isVariableDuration()) {
            $candidates = array('services', self::STEP_DATE);
        } elseif (self::hasSecondaryChoices()) {
            $candidates = array('secondary', self::STEP_DATE);
        } else {
            $candidates = array(self::STEP_DETAILS, self::STEP_DATE);
        }
        $candidates = apply_filters('sln_weekly_schedule_book_steps', $candidates, $service, $date, $time, $assistantId);

        foreach ($candidates as $step) {
            if (in_array($step, $steps, true)) {
                return $step;
            }
        }

        return self::STEP_DATE;
    }

    /**
     * Fill the visitor's booking session with the class.
     *
     * @return bool false when the request doesn't describe a bookable service.
     */
    public static function preselect($serviceId, $assistantId, $date, $time)
    {
        if (!$serviceId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            return false;
        }

        $plugin  = SLN_Plugin::getInstance();
        $service = $plugin->createService($serviceId);
        if (!$service || !$service->getId() || get_post_type($serviceId) !== SLN_Plugin::POST_TYPE_SERVICE
            || $service->isHideOnFrontend() || $service->isSecondary()) {
            return false;
        }

        $bb = $plugin->getBookingBuilder();
        $bb->removeServices();
        $bb->addService($service);

        if ($assistantId && in_array($assistantId, array_map('intval', $service->getAttendantsIds()), true)) {
            $assistant = $plugin->createAttendant($assistantId);
            if ($assistant && !$assistant->isHideOnFrontend()) {
                $bb->setAttendant($assistant, $service);
            }
        }

        // Multi-Shops: a class offered by a single shop needs no shop choice.
        // Picking the shop later would empty the selected services.
        $shops = array_filter(array_map('intval', (array) $service->getMeta('shops')));
        if (count($shops) === 1 && !$bb->get('shop')) {
            $bb->set('shop', reset($shops));
        }

        $bb->removeLastId()
            ->setDate($date)
            ->setTime($time);
        $bb->set(self::NOTICE_KEY, null);
        $bb->save();

        do_action('sln_weekly_schedule_book_preselected', $bb, $service, $date, $time, $assistantId);

        return true;
    }

    /**
     * Current status of the class, computed now (not from the schedule cache)
     * with the same rules as the schedule.
     *
     * @return string|null A SLN_WeeklySchedule_Builder::STATUS_* value, null
     *                     when there is no such class.
     */
    public static function checkSlot($serviceId, $assistantId, $date, $time)
    {
        SLN_TimeFunc::startRealTimezone();
        $args = SLN_WeeklySchedule_Args::normalize(array(
            'week'            => $date,
            'services'        => (string) $serviceId,
            'assistants'      => $assistantId ? (string) $assistantId : '',
            'show_empty_days' => 'yes',
            'weeks_ahead'     => SLN_WeeklySchedule_Args::MAX_WEEKS_AHEAD,
        ));
        $builder  = new SLN_WeeklySchedule_Builder(SLN_Plugin::getInstance(), $args);
        $schedule = $builder->build();
        SLN_TimeFunc::endRealTimezone();

        foreach ($schedule['days'] as $day) {
            if ($day['date'] !== $date) {
                continue;
            }
            foreach ($day['events'] as $event) {
                if ($event['time'] === $time && (!$assistantId || (int) $event['assistant_id'] === (int) $assistantId)) {
                    return $event['status'];
                }
            }
        }

        return null;
    }

    /**
     * Same rule as the secondary step of the booking form: secondary services
     * that can be added to the class just preselected.
     */
    private static function hasSecondaryChoices()
    {
        $plugin = SLN_Plugin::getInstance();
        $bb     = $plugin->getBookingBuilder();
        $ah     = $plugin->getAvailabilityHelper();
        $ah->setDate($bb->getDateTime());
        $bookingServices = $bb->getBookingServices();

        $services = array();
        foreach ($plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE)->getAllSecondary() as $service) {
            if (!$service->isHideOnFrontend() && !$ah->validateServiceFromOrder($service, $bookingServices)) {
                $services[] = $service;
            }
        }

        return (bool) apply_filters('sln.shortcode.salon.SecondaryStep.getServices', $services);
    }

    private static function setNotice($message)
    {
        $bb = SLN_Plugin::getInstance()->getBookingBuilder();
        $bb->set(self::NOTICE_KEY, array(
            'message' => $message,
            'date'    => $bb->getDate(),
            'time'    => $bb->getTime(),
            'expires' => time() + 5 * MINUTE_IN_SECONDS,
        ));
        $bb->save();
    }

    /**
     * Show the "class no longer bookable" notice on the date step.
     *
     * The booking form renders the step more than once (page load, then its
     * own AJAX refresh), so the notice stays until the visitor picks another
     * date or time, or for a few minutes at most. It is not a .sln-alert: the
     * date step script removes every .sln-alert when it checks availability.
     */
    public static function showNotice()
    {
        $bb     = SLN_Plugin::getInstance()->getBookingBuilder();
        $notice = $bb->get(self::NOTICE_KEY);
        if (!is_array($notice)) {
            return;
        }
        if ($notice['expires'] < time() || $notice['date'] !== $bb->getDate() || $notice['time'] !== $bb->getTime()) {
            $bb->set(self::NOTICE_KEY, null);
            $bb->save();

            return;
        }
        echo '<p class="sln-weekly-schedule-notice" role="alert"><strong>' . esc_html($notice['message']) . '</strong></p>';
    }
}
