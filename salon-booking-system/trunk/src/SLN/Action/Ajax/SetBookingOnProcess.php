<?php // algolplus
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

class SLN_Action_Ajax_SetBookingOnProcess extends SLN_Action_Ajax_Abstract
{
	public function execute()
	{
        try {
            $booking = SLN_Plugin::getInstance()->createBooking(intval(isset($_REQUEST['id']) ? wp_unslash($_REQUEST['id']) : ''));
            $on_process = $booking->getOnProcess();

            $booking->setOnProcess(!$on_process);
        } catch(Exception $e) {
            $errors[] = $e->getMessage();
            return compact('errors');
        }

		return array('success' => 1, 'on_process' => !$on_process);
    }
}