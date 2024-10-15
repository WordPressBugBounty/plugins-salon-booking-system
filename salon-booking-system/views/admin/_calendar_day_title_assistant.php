<?php
/** @var SLN_Wrapper_Booking $booking */
?>
<div class="day-event-item__calendar-day__header" >
    <span class="day-event-item__customer"><div class="day-event-item__customer-name">...<?php echo esc_html($booking->getDisplayName()) ?></div>
    <i class="sln-btn--icon sln-icon--checkmark <?php if (!$booking->getOnProcess()) {
            echo "hide";
    }
    ?>" ></i></span>

    <span class="day-event-item__booking_id"><?php echo esc_html($booking->getId()) ?></span>
</div>