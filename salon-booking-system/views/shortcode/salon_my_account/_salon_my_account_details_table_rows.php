<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
 // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php
// Resolve rescheduling window in hours; migrate legacy day-based stored values.
$sln_reschedule_raw          = $plugin->getSettings()->get('days_before_rescheduling');
$sln_reschedule_legacy_hours = array('1' => 24, '2' => 48, '3' => 72, '7' => 168, '14' => 336);
$sln_reschedule_hours        = isset($sln_reschedule_legacy_hours[$sln_reschedule_raw])
	? $sln_reschedule_legacy_hours[$sln_reschedule_raw]
	: (int) $sln_reschedule_raw;

$sln_variant      = isset($data['table_data']['variant']) ? $data['table_data']['variant'] : 'compact';
$sln_status_class = array(
	SLN_Enum_BookingStatus::CONFIRMED       => 'confirmed',
	SLN_Enum_BookingStatus::PAID            => 'paid',
	SLN_Enum_BookingStatus::PAY_LATER       => 'paylater',
	SLN_Enum_BookingStatus::PENDING_PAYMENT => 'pendingpay',
	SLN_Enum_BookingStatus::PENDING         => 'pending',
);
?>
<?php foreach ($data['table_data']['items'] as $item): ?>
        <?php $booking = $plugin->createBooking($item['id']);
        // Add null check to prevent fatal error
        if (!$booking) {
            continue;
        }
        if(class_exists('\SalonMultishop\Addon')){
            \SalonMultishop\Addon::getInstance()->setCurrentShop($booking->getMeta('shop'));
            $_GET['shop'] = $booking->getMeta('shop');
        }
        $sln_status   = isset($sln_status_class[$item['status_code']]) ? $sln_status_class[$item['status_code']] : 'other';
        $sln_starts   = $plugin->getSettings()->isDisplaySlotsCustomerTimezone() && $data['customer_timezone']
            ? $booking->getStartsAt()->setTimezone(new DateTimezone($data['customer_timezone']))
            : $booking->getStartsAt();
        $sln_timezone = $sln_starts->getTimezone();
        $sln_relative = '';
        if ($data['table_data']['mode'] == 'new') {
            $sln_today     = new DateTime('today', $sln_timezone);
            $sln_day       = new DateTime($sln_starts->format('Y-m-d'), $sln_timezone);
            $sln_days_left = (int) $sln_today->diff($sln_day)->format('%r%a');
            if ($sln_days_left === 0) {
                $sln_relative = __('Today', 'salon-booking-system');
            } elseif ($sln_days_left === 1) {
                $sln_relative = __('Tomorrow', 'salon-booking-system');
            } elseif ($sln_days_left > 1) {
                // translators: %d: number of days until the appointment
                $sln_relative = sprintf(_n('In %d day', 'In %d days', $sln_days_left, 'salon-booking-system'), $sln_days_left);
            }
        }
        // "Haircut, Colour with Mark" when one assistant does everything, otherwise "Haircut with Mark, Colour with Luis".
        $sln_attendants = array_unique(array_filter(array_column($item['services'], 'attendant')));
        $sln_names      = array_column($item['services'], 'name');
        if (count($sln_attendants) === 1 && count(array_filter(array_column($item['services'], 'attendant'))) === count($item['services'])) {
            // translators: 1: list of services, 2: assistant name
            $sln_services_line = sprintf(__('%1$s with %2$s', 'salon-booking-system'), implode(', ', $sln_names), reset($sln_attendants));
        } else {
            $sln_services_parts = array();
            foreach ($item['services'] as $service) {
                // translators: 1: service name, 2: assistant name
                $sln_services_parts[] = isset($service['attendant']) ? sprintf(__('%1$s with %2$s', 'salon-booking-system'), $service['name'], $service['attendant']) : $service['name'];
            }
            $sln_services_line = implode(', ', $sln_services_parts);
        }
        $sln_discount_amount = (float) $booking->getMeta('discount_amount');
        ?>
        <article id="sln-account__booking--<?php echo (int) $item['id'] ?>" class="sln-account__booking sln-account__card sln-account__list__item sln-account__appt sln-account__appt--<?php echo esc_attr($sln_variant); ?> sln-account__appt--<?php echo esc_attr($sln_status); ?>">
        <?php ob_start(); ?>
            <span class="sln-account__chip sln-account__chip--<?php echo esc_attr($sln_status); ?>"><span class="sln-account__chip__dot" aria-hidden="true"></span><?php echo esc_html($item['status']); ?></span>
        <?php $sln_chip = ob_get_clean(); ?>
        <?php if ($sln_variant === 'next'): ?>
            <div class="sln-account__appt__head">
                <div class="sln-account__datebox" aria-hidden="true">
                    <span class="sln-account__datebox__wd"><?php echo esc_html(wp_date('D', $sln_starts->getTimestamp(), $sln_timezone)); ?></span>
                    <span class="sln-account__datebox__day"><?php echo esc_html(wp_date('d', $sln_starts->getTimestamp(), $sln_timezone)); ?></span>
                    <span class="sln-account__datebox__month"><?php echo esc_html(wp_date('M', $sln_starts->getTimestamp(), $sln_timezone)); ?></span>
                </div>
                <div class="sln-account__appt__when">
                    <h3 class="sln-account__booking__date sln-account__appt__time">
                        <span class="sln-booking-time"><?php echo esc_html($item['time']) ?></span>
                        <span class="sr-only">, <span class="sln-booking-date"><?php echo esc_html($item['date']) ?></span></span>
                    </h3>
                    <p class="sln-account__appt__shop sln-booking-shop"><?php echo esc_html($plugin->getSettings()->getSalonName()); ?></p>
                    <?php if ($sln_relative): ?>
                        <p class="sln-account__appt__relative"><?php echo esc_html($sln_relative); ?></p>
                    <?php endif; ?>
                </div>
                <?php echo wp_kses_post($sln_chip); ?>
            </div>
            <div class="sln-account__appt__body">
                <ul class="sln-account__services__list sln-account__appt__services">
                    <?php foreach($item['services'] as $service): ?>
                    <li class="sln-account__service">
                        <div>
                            <p class="sln-account__service__name"><?php echo esc_html($service['name']) ?></p>
                            <?php if(isset($service['attendant'])): ?>
                                <p class="sln-account__service__assistant">
                                    <?php
                                    // translators: %s: assistant name
                                    echo esc_html(sprintf(__('with %s', 'salon-booking-system'), $service['attendant']));
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if (!$data['hide_prices']): ?>
                            <span class="sln-account__service__price sln-account__num"><?php echo wp_kses_post($service['price']); ?></span>
                        <?php endif;?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if(!$data['hide_prices']): ?>
                    <?php if ($sln_discount_amount > 0): ?>
                        <div class="sln-account__appt__row sln-account__appt__discount">
                            <span><?php esc_html_e('Discount', 'salon-booking-system'); ?></span>
                            <span class="sln-account__num">&minus;<?php echo wp_kses_post($plugin->format()->moneyFormatted($sln_discount_amount)); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="sln-account__appt__row sln-account__card__total">
                        <span class="sln-account__appt__total-label"><?php esc_html_e('Total', 'salon-booking-system'); ?></span>
                        <span class="sln-account__appt__total sln-account__num"><?php echo wp_kses_post($item['total']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="sln-account__appt__head">
                <div class="sln-account__datebox sln-account__datebox--sm" aria-hidden="true">
                    <span class="sln-account__datebox__wd"><?php echo esc_html(wp_date('D', $sln_starts->getTimestamp(), $sln_timezone)); ?></span>
                    <span class="sln-account__datebox__day"><?php echo esc_html(wp_date('d', $sln_starts->getTimestamp(), $sln_timezone)); ?></span>
                    <span class="sln-account__datebox__month"><?php echo esc_html(wp_date('M', $sln_starts->getTimestamp(), $sln_timezone)); ?></span>
                </div>
                <div class="sln-account__appt__when">
                    <h3 class="sln-account__booking__date sln-account__appt__time">
                        <span class="sr-only"><span class="sln-booking-date"><?php echo esc_html($item['date']) ?></span>, </span>
                        <span class="sln-booking-time"><?php echo esc_html($item['time']) ?></span>
                        <span aria-hidden="true">&middot;</span>
                        <span class="sln-booking-shop"><?php echo esc_html($plugin->getSettings()->getSalonName()); ?></span>
                    </h3>
                    <p class="sln-account__appt__services-line"><?php echo esc_html($sln_services_line); ?></p>
                </div>
                <?php if (!$data['hide_prices']): ?>
                    <span class="sln-account__appt__total sln-account__num"><?php echo wp_kses_post($item['total']) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <footer class="sln-account__card__footer sln-account__card__actions sln-account__booking__actions sln-account__appt__footer">
            <div class="sln-account__appt__meta">
                <?php if ($sln_variant === 'next'): ?>
                    <span class="sln-account__appt__id">
                        <?php
                        // translators: %s: booking ID
                        echo esc_html(sprintf(__('Booking #%s', 'salon-booking-system'), $item['id']));
                        ?>
                    </span>
                <?php else: ?>
                    <?php echo wp_kses_post($sln_chip); ?>
                    <?php if ($data['table_data']['mode'] == 'new' && $sln_status === 'pendingpay'): ?>
                        <span class="sln-account__appt__note"><?php esc_html_e('· pay to confirm', 'salon-booking-system'); ?></span>
                    <?php elseif ($data['table_data']['mode'] == 'new' && $sln_status === 'pending'): ?>
                        <span class="sln-account__appt__note"><?php esc_html_e('Waiting for the salon to confirm', 'salon-booking-system'); ?></span>
                    <?php endif; ?>
                    <span class="sln-account__appt__id">#<?php echo (int) $item['id'] ?></span>
                <?php endif; ?>
            </div>
            <?php if($data['table_data']['mode'] == 'new'):
                    if($plugin->getSettings()->get('cancellation_enabled') && ($booking->getStartsAt()->getTimestamp() - time()) > $data['seconds_before_cancellation']): ?>
                        <button type="button" class="sln-account__btn sln-account__btn--text sln-cancel-booking--button sln-account__btn--cancel" onclick="sln_myAccount.cancelBooking(<?php echo (int) $item['id']; ?>);" data-message="<?php esc_html_e('Booking cancelled', 'salon-booking-system');?>"><?php esc_html_e('Cancel', 'salon-booking-system');?></button>
                    <?php endif; ?>

                    <?php if ($data['pay_enabled']) { ?>
                        <?php if ($item['status_code'] == SLN_Enum_BookingStatus::PENDING_PAYMENT && $booking->getToPayAmount(false)) { ?>
                            <a class="sln-account__btn sln-account__btn--primary sln-account__btn--wide sln-account__btn--pay" href="<?php echo esc_url($booking->getPayUrl()); ?>">
                                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19"/></svg>
                                <?php echo esc_html__('Pay', 'salon-booking-system') . ' ' . wp_kses_post($plugin->format()->moneyFormatted($booking->getToPayAmount(false))); ?>
                            </a>
                        <?php } ?>

                        <?php
                        $paymentMethod = $plugin->getSettings()->isPayEnabled() ? SLN_Enum_PaymentMethodProvider::getService($plugin->getSettings()->getPaymentMethod(), $plugin) : false;
                        if ($item['status_code'] == SLN_Enum_BookingStatus::PAID && $booking->getRemaingAmountAfterPay(false) && $paymentMethod) { ?>
                            <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-account__btn--pay sln-account__btn--pay-remaining">
                                <?php
                                $payUrl = $booking->getPayUrl(true) . "&mode={$paymentMethod->getMethodKey()}";
                                echo $paymentMethod->renderPayButton(array('booking' => $booking, 'paymentMethod' => $paymentMethod, 'payUrl' => $payUrl, 'payRemainingAmount' => 1));
                                ?>
                            </div>
                        <?php } ?>
                    <?php } ?>

                    <?php if(!$plugin->getSettings()->get('rescheduling_disabled') && ($booking->getStartsAt()->getTimestamp() - time()) >= ($sln_reschedule_hours * 3600) && in_array($item['status_code'], array(SLN_Enum_BookingStatus::CONFIRMED, SLN_Enum_BookingStatus::PAY_LATER, SLN_Enum_BookingStatus::PAID,))):
                        $date = $plugin->getSettings()->isDisplaySlotsCustomerTimezone() && $data['customer_timezone']
                            ? $booking->getStartsAt()->setTimezone(new DateTimezone($data['customer_timezone']))
                            : $booking->getStartsAt(); ?> 
                        <button type="button" class="sln-account__btn <?php echo $sln_variant === 'next' ? 'sln-account__btn--primary' : 'sln-account__btn--secondary'; ?> sln-reschedule-booking--button sln-account__btn--reschedule" data-message="<?php esc_html_e('Booking rescheduled', 'salon-booking-system');?>">
                            <?php esc_html_e('Reschedule', 'salon-booking-system'); ?>
                        </button>

                        <?php ob_start(); ?>
                        <?php SLN_Form::fieldJSDate('_sln_booking_date', $date, array('inline' => true)) ?>
                        <input name="_sln_booking_date" type="hidden" value="<?php echo $plugin->format()->date($date) ?>">
                        <?php $datepicker = ob_get_clean();
                        
                        ob_start(); ?>
                        <?php SLN_Form::fieldJSTime('_sln_booking_time', $date, array('inline' => true, 'interval' => $plugin->getSettings()->get('interval'),)); ?>
                        <input name="_sln_booking_time" type="hidden" value="<?php echo $plugin->format()->time($date); ?>">
                        <?php $timepicker = ob_get_clean(); ?>
                        
                        <form class="sln-reschedule-form hide">
                            <?php do_action('sln.salon_my_accout.reschedule-form', $booking);
                            SLN_Form::fieldText('_sln_booking_id', $item['id'], array('type'=> 'hidden'));
                            SLN_Form::fieldText('customer_timezone', $data['customer_timezone'], array('type' => 'hidden')); ?>
                            <input class="sln_booking_default_date" type="hidden" value="<?php echo $plugin->format()->date($date); ?>">
                            <input class="sln_booking_default_time" type="hidden" value="<?php echo $plugin->format()->time($date); ?>">
                            <?php
                            foreach($booking->getBookingServices()->getItems() as $bookingService){
                                $serviceId = $bookingService->getService()->getId();
                                SLN_Form::fieldText(
                                    "_sln_booking[services][$serviceId]",
                                    $bookingService->getAttendant() 
                                        ? (
                                            is_array($bookingService->getAttendant())
                                                ? SLN_Wrapper_Attendant::getArrayAttendantsValue('getId', $bookingService->getAttendant()) 
                                                : $bookingService->getAttendant()->getId()
                                        ) 
                                        : 0,
                                    array('type' => 'hidden', array('multiple' => ''))
                                );
                            } ?>
                            <div class="sln-box--main sln-account__reschedule">
                                <div class="sln-account__reschedule__header">
                                    <h3><?php esc_html_e('Reschedule', 'salon-booking-system'); ?></h3>
                                </div>
                                        <div class="sln-input sln-input--datepicker sln-input--datepicker--date">
                                            <?php echo $datepicker; ?>
                                        </div>
                                        <div class="sln-input sln-input--datepicker sln-input--datepicker--time" id="sln-booking-id-resch-<?php echo $booking->getId();?>">
                                            <?php echo $timepicker; ?>
                                        </div>
                                        <div class="sln-notifications"></div>
                                        <div class="sln-reschedule-selected-datetime" style="text-align: center; font-size: 16px; font-weight: 500; margin: 20px 0; color: #333;">
                                            <!-- Selected date and time will be displayed here by JavaScript -->
                                        </div>
                                        <div class="sln-reschedule-form__btnwrp">
                                            <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--borderonly sln-reschedule-form--cancel-button">
                                                <?php esc_html_e('Cancel', 'salon-booking-system'); ?>
                                            </div>
                                            <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-reschedule-form--save-button">
                                                <?php esc_html_e('Confirm', 'salon-booking-system'); ?>
                                            </div>
                                        </div>
                                    </div>
                        </form>
                    <?php endif ?>
            <?php elseif($data['table_data']['mode'] == 'history'): ?>
                    <?php if(empty($item['rating'])): ?>
                        <button type="button" class="sln-account__btn sln-account__btn--secondary" onclick="sln_myAccount.showRateForm(<?php echo (int) $item['id']; ?>);" data-message="<?php esc_html_e('Feedback submitted', 'salon-booking-system');?>">
                            <?php esc_html_e('Leave a feedback', 'salon-booking-system'); ?>
                        </button>
                    <?php else: ?>
                        <div class="sln-accout__feedback">
                            <div class="feedback sln-accout__feedback__text"><?php echo $item['feedback'] ?></div>
                            <input type="hidden" name="sln-rating" value="<?php echo $item['rating']; ?>">
                            <div class="rating sln-accout__feedback__rating" id="<?php echo $item['id']; ?>" style="display: none;"></div>
                        </div>
                    <?php endif; ?>
            <?php endif; ?>
        </footer>
        <div id="sln-account__card__action_notification_<?php echo $item['id']?>" class="sln-account__card__action_notification sln-account__card__action_notification--hide">
            <div class="sln-account__notification_action__text">
                <span class="sln-account__notification_action_name"></span>
            </div>
            <div class="sln_account__notification_action__icon"></div>
            <!-- <div class="sln_account__notification_action__close"></div> -->
        </div>
    </article>

<?php endforeach;?>
