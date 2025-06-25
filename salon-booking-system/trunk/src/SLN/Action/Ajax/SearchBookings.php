<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

class SLN_Action_Ajax_SearchBookings extends SLN_Action_Ajax_Abstract
{
	function execute(){
		if( !current_user_can( 'manage_salon' ) ){
			return array( 'status' => '403' );
		}
		$search = sanitize_text_field( isset($_POST['search']) ? wp_unslash($_POST['search']) : '' );
		$day = sanitize_text_field( isset($_POST['day']) ? wp_unslash($_POST['day']) : '' );
		try {
			$timestamp = new SLN_DateTime($day);
		} catch (Exception $e) {
			return [];
		}
		$repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING);
		$bookings = $repo->getForMonthSearch($search, $timestamp);
		return $this->plugin->loadView('admin/_calendar_search_result', array('bookings' => $bookings));
	}
}