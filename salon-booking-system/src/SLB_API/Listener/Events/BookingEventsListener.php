<?php
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query
namespace SLB_API\Listener\Events;

use SLN_Plugin;
use SLN_Enum_BookingStatus;
use WP_User_Query;
use SLB_API\Third\OnesignalAPI;

class BookingEventsListener
{
    /**
     * Shared with SLB_API_Mobile: sln.booking_builder.create.booking_created also fires on status
     * transitions and on every admin save, so without this marker the same booking is announced
     * several times. Keeping the same key means only one push goes out even if both listeners run.
     */
    const NOTIFIED_META = '_sln_booking_onesignal_notified';

    public function __construct()
    {
	add_action('sln.booking_builder.create.booking_created', array($this, 'event_created'), 10, 1);
    }

    /**
     * Statuses that mean the customer finished booking. With payments enabled the builder inserts
     * the booking as auto-draft to hold the slot while the summary page is open (Builder::getCreateStatus)
     * and fires the create hook right there, so staff must not be alerted for auto-draft or for a
     * booking still awaiting payment.
     */
    protected function notifiableStatuses()
    {
	return apply_filters('sln_onesignal_notification_statuses', array(
	    SLN_Enum_BookingStatus::PENDING,
	    SLN_Enum_BookingStatus::PAID,
	    SLN_Enum_BookingStatus::PAY_LATER,
	    SLN_Enum_BookingStatus::CONFIRMED,
	));
    }

    public function event_created( $booking ) {

	$plugin   = SLN_Plugin::getInstance();
	$settings = $plugin->getSettings();

	if ( ! $settings->get('onesignal_new') || ! $booking ) {
	    return;
	}

	$booking_id = $booking->getId();

	if ( ! $booking_id ) {
	    return;
	}

	$status = $booking->getStatus();

	if ( ! in_array($status, $this->notifiableStatuses(), true) ) {
	    SLN_Plugin::addLog('[OneSignal] Skipped booking #' . $booking_id . ': status "' . $status . '" is not a completed booking');
	    return;
	}

	if ( get_post_meta($booking_id, self::NOTIFIED_META, true) ) {
	    return;
	}

	$query = new WP_User_Query(array(
	    'meta_query' => array(
		array(
		    'key'     => '_sln_onesignal_player_id',
		    'value'   => '',
		    'compare' => '!=',
		),
	    )
        ));

	$player_ids = array();

	foreach ($query->results as $user) {

	    $user_player_ids = $user->get('_sln_onesignal_player_id');

	    if ( ! is_array( $user_player_ids ) ) {
		$user_player_ids = array($user_player_ids);
	    }

	    $player_ids = array_merge($player_ids, $user_player_ids);
	}

	$player_ids = array_values(array_unique(array_filter($player_ids)));
	$app_id     = $settings->get('onesignal_app_id');
	$rest_key   = $settings->get('onesignal_rest_api_key');

	if ( ! $player_ids && ! $rest_key ) {
	    SLN_Plugin::addLog('[OneSignal] Skipped booking #' . $booking_id . ': no staff player IDs and no REST API Key');
	    return;
	}

	if ( ! $app_id ) {
	    SLN_Plugin::addLog('[OneSignal] Skipped booking #' . $booking_id . ': App ID is empty');
	    return;
	}

	$message = $plugin->loadView('onesignal/notify', compact('booking'));

	try {
	    $outcome = OnesignalAPI::notify($app_id, $player_ids, $message, $rest_key);
	    update_post_meta($booking_id, self::NOTIFIED_META, current_time('mysql'));
	    SLN_Plugin::addLog('[OneSignal] Sent booking #' . $booking_id . ' to ' . $outcome);
	} catch (\Exception $ex) {
	    SLN_Plugin::addLog('[OneSignal] Failed booking #' . $booking_id . ': ' . $ex->getMessage());
	}
    }

}
