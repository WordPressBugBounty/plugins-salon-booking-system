<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/** @var SLN_Wrapper_Booking $booking */
$format = SLN_Plugin::getInstance()->format();
$duration = $booking->getDuration();
$hours = explode(" ", $duration);
$hoursParts = explode(":", $hours[1]);
$minutes = ($hoursParts[0] * 60) + $hoursParts[1];
?>
<div class="day-event-item__calendar-day__header" style="display: flex; padding-top: 3px;padding-bottom: 0px;padding-right: 0px;">
<span class="day-event-item__customer" >
    <div class="day-event-item__booking_id" style="margin-right: 0.5rem;"><?php echo $booking->getId() ?></div>
    <div class="day-event-item__customer-name"><?php echo $booking->getDisplayName() ?></div>
    <i class="sln-btn--icon sln-icon--checkmark <?php if (!$booking->getOnProcess()) {
	        echo "hide";
        }?>" >
    </i>
</span>
<span class="sln-event-header-more-icon sln-event-header-more-icon-vertical" style="margin-left: auto;" data-tooltip-id=<?php echo $booking->getId() ?> ></span>

</div>

<ul class='service_wrapper <?php echo 'duration-' . $minutes; ?>'>
    <?php
$sum = 0;
foreach ($booking->getBookingServices()->getItems() as $bookingService): ?>
        <?php if (SLN_Func::getMinutesFromDuration($bookingService->getService()->getDuration()) > 0): ?>
            <li>
                <span class='day-event-item__service'><?php echo $bookingService->getService()->getName() ?></span>
                <span class='day-event-item__attendant'><span class="day-event-item__attendant_name"><?php
                    echo ($attendant = $bookingService->getAttendant()) ?
                            (!is_array($attendant) ?
                                $bookingService->getAttendant()->getName() :
                                SLN_Wrapper_Attendant::implodeArrayAttendantsName(' ', $attendant))
                            . ': ' :
                            ''
                    ?></span>
                <span class='day-event-item__attendant_timing'><?php echo $format->time($bookingService->getStartsAt()) . ' &#8594; ' . $format->time($bookingService->getEndsAt()) ?></span> </span>
                <span class='day-event-item__resource'><?php echo $bookingService->getResource() ? $bookingService->getResource()->getTitle() : '' ?></span>
            </li>
        <?php else: ?>
            <li class="service-empty-duration">
                <span class='day-event-item__service'><?php echo $bookingService->getService()->getName() ?></span> -
                <span class='day-event-item__attendant'><span class="day-event-item__attendant_name"><?php
                    echo ($attendant = $bookingService->getAttendant()) ?
                            (!is_array($attendant) ?
                                $bookingService->getAttendant()->getName() :
                                SLN_Wrapper_Attendant::implodeArrayAttendantsName(' ', $attendant))
                            . ' - ' :
                            ''
                    ?></span>
                <span class='day-event-item__attendant_timing'><?php echo $format->time($bookingService->getEndsAt()) ?></span> </span>
                <span class='day-event-item__resource'><?php echo $bookingService->getResource() ? ' - ' . $bookingService->getResource()->getTitle() : '' ?></span>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>