<?php

class SLN_Action_CleanUpDatabase
{
    /** @var SLN_Plugin */
    private $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function execute()
    {
	$now	    = new SLN_DateTime();
	$settings   = $this->plugin->getSettings();

	$holidays_rules  = $settings->get('holidays_daily') ?: array();
	$_holidays_rules = array();

	foreach ($holidays_rules as $holiday_rule) {
	    if (($now->getTimestamp() - (new SLN_DateTime($holiday_rule['to_date'].' '.$holiday_rule['to_time']))->getTimestamp()) / (24 * 3600) < 7) {
		$_holidays_rules[] = $holiday_rule;
	    }
	}

	$settings->set('holidays_daily', $_holidays_rules);

	$holidays  = $settings->get('holidays');
	$_holidays = array();

	foreach ($holidays as $holiday) {
	    if ($now->getTimestamp() - (new SLN_DateTime($holiday['to_date'].' '.$holiday['to_time']))->getTimestamp() < 0) {
		$_holidays[] = $holiday;
	    }
	}

	$settings->set('holidays', $_holidays);

	$settings->save();

        $attendants = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT)->getAll();

        foreach ($attendants as $attendant) {
            $holidays_rules  = $attendant->getMeta('holidays_daily') ?: array();
            $_holidays_rules = array();

            foreach ($holidays_rules as $holiday_rule) {
                if (($now->getTimestamp() - (new SLN_DateTime($holiday_rule['to_date'].' '.$holiday_rule['to_time']))->getTimestamp()) / (24 * 3600) < 30) {
                    $_holidays_rules[] = $holiday_rule;
                }
            }

            $attendant->setMeta('holidays_daily', $_holidays_rules);
        }

        $this->cleanUpAbandonedDraftBookings();
    }

    /**
     * Remove abandoned online-payment booking drafts.
     *
     * When online payment is enabled a booking is first inserted as an auto-draft and is
     * only promoted to a real status once the payment is completed (see the payment
     * integrity guard in SLN_Shortcode_Salon_SummaryStep). A customer who abandons,
     * cancels, or fails the payment therefore leaves an orphan auto-draft behind. These
     * never become appointments, so we prune the old ones to keep the database clean.
     *
     * The age threshold is deliberately conservative (default 72 hours): Stripe can retry
     * the checkout.session.completed webhook for up to ~3 days, and that webhook needs the
     * draft to still exist in order to promote a late-confirmed payment to PAID. Deleting
     * drafts sooner would risk discarding a booking whose payment actually succeeded but
     * whose confirmation arrived late.
     */
    private function cleanUpAbandonedDraftBookings()
    {
        $maxAgeHours = (int) apply_filters('sln.cleanup.draft_booking_max_age_hours', 72);

        // A non-positive threshold disables the cleanup entirely.
        if ($maxAgeHours <= 0) {
            return;
        }

        // Cap how many we delete per run so a large backlog can never turn the daily cron
        // into a long-running / timing-out process; the remainder is handled next run.
        $batchLimit = (int) apply_filters('sln.cleanup.draft_booking_batch_limit', 200);
        if ($batchLimit <= 0) {
            $batchLimit = 200;
        }

        global $wpdb;

        // post_date is stored in site-local time (current_time('mysql')), so build the
        // threshold in local time too for an apples-to-apples comparison.
        $thresholdLocal = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($maxAgeHours * HOUR_IN_SECONDS));

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_status = %s
                   AND post_date < %s
                 ORDER BY post_date ASC
                 LIMIT %d",
                SLN_Plugin::POST_TYPE_BOOKING,
                SLN_Enum_BookingStatus::DRAFT,
                $thresholdLocal,
                $batchLimit
            )
        );

        if (empty($ids)) {
            return;
        }

        $deleted = 0;
        foreach ($ids as $bookingId) {
            // Force delete (bypass trash) — abandoned drafts carry no value worth retaining.
            if (wp_delete_post((int) $bookingId, true)) {
                $deleted++;
            }
        }

        $this->plugin->addLog(sprintf(
            'CleanUpDatabase: removed %d abandoned auto-draft booking(s) older than %d hours (batch limit %d).',
            $deleted,
            $maxAgeHours,
            $batchLimit
        ));
    }

}