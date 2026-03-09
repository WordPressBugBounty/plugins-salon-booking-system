<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin                $plugin
 * @var SLN_Wrapper_Booking       $booking
 */
if (!isset($data['to'])) {
    if (!empty($forAdmin)) {
        $data['to'] = $sendToAdmin ? implode(',', $plugin->getSettings()->getAdminNotificationEmails()) : '';
    } elseif ($booking->getNotifyCustomer() && isset($sendToCustomer) && $sendToCustomer) {
        $data['to'] = $booking->getEmail();
    }
}
if ($plugin->getSettings()->get('attendant_email')
    && ($attendants = $booking->getAttendants(true))
    && !empty($forAdmin)
) {
	foreach ($attendants as $attendant) {
		if(!is_array($attendant)){
			if (($email = $attendant->getEmail())){
				if(!is_array($data['to'])) $data['to'] = array_filter(array(isset($data['to']) ? $data['to'] : '', $email));
				else $data['to'][] = $email;
			}
		}else{
			foreach($attendant as $att){
				if(($email = $att->getEmail())){
					if(!is_array($data['to'])) $data['to'] = array_filter(array(isset($data['to']) ? $data['to'] : '', $email));
					else $data['to'][] = $email;
				}
			}
		}
	}
}

$data['subject'] = __('Booking confirmed','salon-booking-system')
    . ' ' . $plugin->format()->date($booking->getDate())
    . ' - ' . $plugin->format()->time($booking->getTime());

$data['subject'] = apply_filters('sln.new_booking.notifications.email.subject', $data['subject'], $booking);

$contentTemplate = '_summary_content';
$forAdmin = !empty($forAdmin) ? $forAdmin : null;

echo $plugin->loadView('mail/template', compact('booking', 'plugin', 'data', 'forAdmin', 'contentTemplate'));
