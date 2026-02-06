<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var SLN_Wrapper_Booking $booking
 */
$recipients = array();

$adminEmail           = $plugin->getSettings()->getSalonEmail();
$attendantEmailOption = $plugin->getSettings()->get('attendant_email');
if(isset($updated) && $updated) {
    if ($attendantEmailOption) {
        $bookingAttendants = $booking->getAttendants();
        if (!empty($bookingAttendants)) {
            foreach($bookingAttendants as $attendant) {
                if(!is_array($attendant)){
                    $recipients[] = $attendant->getEmail();
                }else{
                    $recipients = array_merge($recipients, SLN_Wrapper_Attendant::getArrayAttendantsValue('getEmail', $attendant));
                }
            }
        }
    }
    $recipients = array_unique(array_filter($recipients));

    if ($sendToAdmin) {
        $recipients[] = $adminEmail;
    }
    $data['to'] = implode(',', $recipients);
    $data['subject'] = __('Reservation has been modified ','salon-booking-system')
                       . $plugin->format()->date($booking->getDate())
                       . ' - ' . $plugin->format()->time($booking->getTime());
} elseif(isset($rescheduled) && $rescheduled) {
    // Always start with admin email for rescheduled bookings
    $recipients = array();
    if (!empty($adminEmail)) {
        $recipients[] = $adminEmail;
    }
    
    // Add attendant emails if enabled
    if ($attendantEmailOption && ($attendants = $booking->getAttendants(true))) {
        foreach ($attendants as $attendant) {
            if(!is_array($attendant)){
                if (($email = $attendant->getEmail())){
                    $recipients[] = $email;
                }
            }else{
                foreach($attendant as $att){
                    if(($email = $att->getEmail())){
                        $recipients[] = $email;
                    }
                }
            }
        }
    }
    
    // Set final recipient list
    $recipients = array_unique(array_filter($recipients));
    $data['to'] = !empty($recipients) ? implode(',', $recipients) : $adminEmail;
    $current_user = wp_get_current_user();
    $data['subject'] = sprintf(
        // translators: %1$s will be replaced by the booking ID, %2$s will be replaced by the username
        __('Booking #%1$s has been re-scheduled by %2$s', 'salon-booking-system'),
        $booking->getId(),
        implode(' ', array_filter(array($current_user->user_firstname, $current_user->user_lastname)))
    );
} else {
    // Always start with admin email if configured
    $recipients = array();
    if ($sendToAdmin && !empty($adminEmail)) {
        $recipients[] = $adminEmail;
    }
    
    // Add attendant emails if enabled
    if ($attendantEmailOption && ($attendants = $booking->getAttendants(true))) {
        foreach ($attendants as $attendant) {
            if(!is_array($attendant)){
                if (($email = $attendant->getEmail())){
                    $recipients[] = $email;
                }
            }else{
                foreach($attendant as $att){
                    if(($email = $att->getEmail())){
                        $recipients[] = $email;
                    }
                }
            }
        }
    }
    
    // Set final recipient list (remove duplicates and empty values)
    $recipients = array_unique(array_filter($recipients));
    $data['to'] = !empty($recipients) ? implode(',', $recipients) : $adminEmail;
    
    $data['subject'] = __('New booking for ','salon-booking-system')
                       . $plugin->format()->date($booking->getDate())
                       . ' - ' . $plugin->format()->time($booking->getTime());

    $data['subject'] = apply_filters('sln.new_booking.notifications.email.subject', $data['subject'], $booking);

    $data['headers'] = array(
        'Reply-To: '. $booking->getDisplayName() .' <'. $booking->getEmail() .'>',
    );
}
$forAdmin = true;

$contentTemplate = '_summary_content';

echo $plugin->loadView('mail/template', compact('booking', 'plugin', 'data', 'updated', 'rescheduled', 'forAdmin', 'contentTemplate'));
