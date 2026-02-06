<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/** @var SLN_Wrapper_Booking $booking */
$format = SLN_Plugin::getInstance()->format();
$customer = empty($customer) ? $booking->getCustomer() : new SLN_Wrapper_Customer($customer, false);
$booking_phone = esc_html($booking->getPhone());
?><strong>
</strong>
<div class='sln-tooltip-header'>
    <div class='left-sln-tooltip-header'>
        <?php foreach (apply_filters('sln.action.ajaxcalendar._calendar_title.header', array(), $booking) as $headerRow): ?>
            <div class='sln-booking-id-header--row'><?php echo $headerRow; ?></div>
        <?php endforeach; ?>
        <div class='sln-booking-id-tooltip'>ID <?php echo esc_attr($booking->getId()); ?></div>
        <?php if ($booking_phone): ?>
            <div class='sln-booking-id-phone' style='margin-left:0.7rem; margin-top:0.5rem;'>
                <a style='text-decoration:none;' target='_blank' href='https://wa.me/<?php echo $booking_phone; ?>'>Tel. <?php
                                                                                                                            echo (mb_strlen($booking_phone) > 10 ? mb_substr($booking_phone, 0, 10) . '...' : $booking_phone);
                                                                                                                            ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class='right-sln-tooltip-header'>
        <!-- <button class='sln-tooltip-dismiss'>DISMISS</button> -->
    </div>
</div>
<div class='sln-value-tooltip sln-booking-status-tooltip' style='margin-top:0.5rem; margin-left:0.7rem;'>
    <div class='head-info-tooltip'><?php echo \SLN_Enum_BookingStatus::getLabel($booking->getStatus()); ?></div>
    <div class='title-info-tooltip'><?php esc_html_e('Status', 'salon-booking-system'); ?></div>
</div>
<?php if (defined('SLN_VERSION_PAY') && SLN_VERSION_PAY && $plugin->getSettings()->get('enable_customer_fidelity_score') && !empty($customer)): ?>
    <div class='sln-value-tooltip sln-booking-status-tooltip' style='margin-top:0.5rem; margin-left:0.7rem;'>
        <div class='head-info-tooltip-cs'><?php echo $customer->getFidelityScore(); ?></div>
        <div class='title-info-tooltip-cs'><?php esc_html_e('Customer score', 'salon-booking-system'); ?></div>
    </div>
<?php endif; ?>
<?php if ($plugin->getSettings()->get('enable_discount_system')): ?>
    <div id='data-disc-sys' data-disc-sys='true' style='display:none;'></div>
<?php endif; ?>
<div class='sln-name-tooltip'><?php echo esc_attr($booking->getDisplayName()); ?></div>
<?php if ($booking_phone): ?>
    <div class='sln-name-tooltip'>
        <a class='sln-booking-title-phone' target='_blanck' href='https://wa.me/<?php echo $booking_phone; ?>'>
            Tel. <?php echo (mb_strlen($booking_phone) > 10 ? mb_substr($booking_phone, 0, 10) . '...' : $booking_phone); ?>
        </a>
    </div>
<?php endif; ?>
<div class='sln-name-tooltip'><?php echo $format->time($booking->getStartsAt()); ?>&#8594;<?php echo $format->time($booking->getEndsAt()); ?></div>
<?php $comments = get_comments("post_id=" . $booking->getId() . "&type=sln_review");
echo (isset($comments[0]) ? $comments[0]->comment_content : ''); ?>


<div class='sln-services-tooltip'>
    <?php foreach ($booking->getBookingServices()->getItems() as $bookingService): ?>
        <?php
        echo esc_attr($bookingService->getService()->getName()) . '<br /><span>' .
            (($attendant = $bookingService->getAttendant()) ?
                (!is_array($attendant) ?
                    esc_attr($attendant->getName()) :
                    SLN_Wrapper_Attendant::implodeArrayAttendantsName(' ', $attendant))
                . '&nbsp;' :
                '') .
            $format->time($bookingService->getStartsAt()) . ' &#8594; ' .
            $format->time($bookingService->getEndsAt()) . '<br /></span>';
        echo !empty($bookingService->getResource()) && !$bookingService->getResource()->isEmpty() ? $bookingService->getResource() . '<br />' : '';

        ?>
    <?php endforeach ?>
</div>

<?php if ($booking->getNote()): ?>
    <br />
    <?php echo esc_attr($booking->getNote()) ?>
<?php endif ?>

<?php if ($booking->getAdminNote()): ?>
    <br />
    <?php echo esc_attr($booking->getAdminNote()) ?>
<?php endif ?>