<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$sln_discount_cards = array();
foreach (SLN_Plugin::getInstance()->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT)->getAll() as $discount) {
    if ($discount->isHideFromAccount()) {
        continue;
    }
    $discounted_booking = array();
    foreach ($booking_history as $booking) {
        if (get_post_meta($booking['id'], '_sln_booking_discount_' . $discount->getId(), true)) {
            $discounted_booking[] = $booking;
        }
    }
    $errors = $discount->validateDiscountForMail((new DateTime())->getTimestamp(), $customer);
    if (count($discounted_booking) || empty($errors)) {
        $sln_discount_cards[] = compact('discount', 'discounted_booking', 'errors');
    }
}
$sln_available_count = count(array_filter($sln_discount_cards, function ($card) {
    return empty($card['errors']);
}));
?>
<div role="tabpanel" class="tab-pane sln-account__tabpanel sln-account__tabpanel--discounts" id="sln-account__discount__content">
    <?php if ($sln_discount_cards): ?>
        <p class="sln-account__summary">
            <?php
            echo wp_kses(
                sprintf(
                    // translators: %s: number of discounts, e.g. "3 discounts"
                    __('%s available on your next booking', 'salon-booking-system'),
                    '<strong>' . esc_html(sprintf(
                        // translators: %d: number of discounts
                        _n('%d discount', '%d discounts', $sln_available_count, 'salon-booking-system'),
                        $sln_available_count
                    )) . '</strong>'
                ),
                array('strong' => array())
            );
            ?>
        </p>
    <?php else: ?>
        <p class="sln-account__empty"><?php esc_html_e('No discounts available', 'salon-booking-system'); ?></p>
    <?php endif; ?>

    <?php foreach ($sln_discount_cards as $sln_card):
        $discount           = $sln_card['discount'];
        $discounted_booking = $sln_card['discounted_booking'];
        $errors             = $sln_card['errors'];
        $sln_is_auto        = $discount->getDiscountType() === SLB_Discount_Enum_DiscountType::DISCOUNT_AUTO;
        $sln_history_id     = 'sln-discount__used--' . $discount->getId();
        $sln_ends_at        = $discount->getEndsAt();
        $sln_days_left      = null;
        if ($sln_ends_at) {
            $sln_today     = new DateTime('today', $sln_ends_at->getTimezone());
            $sln_days_left = (int) $sln_today->diff(new DateTime($sln_ends_at->format('Y-m-d'), $sln_ends_at->getTimezone()))->format('%r%a');
        }
        ?>
        <article class="sln-account__booking sln-account__card sln-account__list__item sln-account__coupon<?php echo empty($errors) ? '' : ' sln-account__coupon--invalid'; ?>">
            <div class="sln-account__coupon__head">
                <div class="sln-account__coupon__titles">
                    <p class="sln-account__coupon__amount">
                        <?php
                        // translators: %s: discount amount, e.g. "£5" or "10%"
                        echo esc_html(sprintf(__('%s off', 'salon-booking-system'), html_entity_decode(wp_strip_all_tags($discount->getAmountString()), ENT_QUOTES, 'UTF-8')));
                        ?>
                    </p>
                    <h3 class="sln-account__card__title sln-account__coupon__name"><?php echo esc_html($discount->getName()); ?></h3>
                </div>
                <?php if ($sln_is_auto && empty($errors)): ?>
                    <span class="sln-account__chip sln-account__chip--paid sln-account__coupon__auto">
                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
                        <?php esc_html_e('Applied automatically', 'salon-booking-system'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($discount->getServicesIds() || $discount->geAttendantsIds()): ?>
                <p class="sln-account__coupon__label"><?php esc_html_e('Valid on', 'salon-booking-system'); ?></p>
                <ul class="sln-account__services__list sln-account__coupon__chips">
                    <?php foreach ($discount->getServicesIds() as $service):
                        $service = $plugin->createFromPost($service); ?>
                        <li class="sln-account__service sln-account__coupon__chip">
                            <span class="sln-account__service__name"><?php echo esc_html($service->getName()); ?></span>
                            <?php if (!$plugin->getSettings()->isHidePrices()): ?>
                                <span aria-hidden="true">&middot;</span>
                                <span class="sln-account__service__price"><?php echo wp_kses_post($plugin->format()->moneyFormatted($service->getPrice())); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($discount->geAttendantsIds() as $attendant):
                        $attendant = $plugin->createFromPost($attendant); ?>
                        <li class="sln-account__service sln-account__coupon__chip">
                            <span class="sln-account__service__assistant">
                                <?php
                                // translators: %s: assistant name
                                echo esc_html(sprintf(__('with %s', 'salon-booking-system'), $attendant->getName()));
                                ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="sln-account__coupon__label"><?php esc_html_e('On any service', 'salon-booking-system'); ?></p>
            <?php endif; ?>

            <?php if (!$sln_is_auto): ?>
                <div class="sln-account__coupon__code">
                    <div class="sln-account__coupon__code__text">
                        <p class="sln-account__coupon__code__label"><?php esc_html_e('Coupon code', 'salon-booking-system'); ?></p>
                        <p class="sln-account__coupon__code__value"><?php echo esc_html($discount->getCouponCode()); ?></p>
                    </div>
                    <button type="button" class="sln-account__btn sln-account__coupon__copy" data-code="<?php echo esc_attr($discount->getCouponCode()); ?>" data-copied="<?php esc_attr_e('Copied', 'salon-booking-system'); ?>">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>
                        <span class="sln-account__coupon__copy__label"><?php esc_html_e('Copy', 'salon-booking-system'); ?></span>
                    </button>
                </div>
            <?php endif; ?>

            <p class="sln-account__coupon__validity<?php echo empty($errors) ? '' : ' sln-account__card__header__error'; ?>">
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span>
                    <?php if (!empty($errors)): ?>
                        <?php echo esc_html($errors[0]); ?>
                    <?php elseif ($sln_ends_at): ?>
                        <?php
                        // translators: %s: expiration date
                        echo esc_html(sprintf(__('Valid until %s', 'salon-booking-system'), $plugin->format()->date($sln_ends_at)));
                        ?>
                        <?php if ($sln_days_left !== null && $sln_days_left >= 0 && $sln_days_left <= 45): ?>
                            <span class="sln-account__coupon__days-left">
                                &middot;
                                <?php
                                // translators: %d: number of days before the discount expires
                                echo esc_html(sprintf(_n('%d day left', '%d days left', $sln_days_left, 'salon-booking-system'), $sln_days_left));
                                ?>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php esc_html_e('No expiration date', 'salon-booking-system'); ?>
                    <?php endif; ?>
                </span>
            </p>

            <div class="sln-account__ticket-divider" aria-hidden="true"></div>

            <footer class="sln-account__card__footer sln-account__coupon__footer">
                <?php if (count($discounted_booking) > 0): ?>
                    <div class="sln-account__usage">
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>
                        <span class="sln-account__usage__count">
                            <?php
                            echo wp_kses(
                                sprintf(
                                    // translators: %s: "1 time" / "2 times", wrapped in <strong>
                                    __('Used %s', 'salon-booking-system'),
                                    '<strong>' . esc_html(sprintf(
                                        // translators: %d: number of uses
                                        _n('%d time', '%d times', count($discounted_booking), 'salon-booking-system'),
                                        count($discounted_booking)
                                    )) . '</strong>'
                                ),
                                array('strong' => array())
                            );
                            ?>
                        </span>
                        <button type="button" class="sln-account__btn sln-account__btn--text sln-account__usage__toggle sln-account__card__showmore__trigger collapsed" data-toggle="collapse" data-target="#<?php echo esc_attr($sln_history_id); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr($sln_history_id); ?>">
                            <span class="sln-account__card__showmore__display"><?php esc_html_e('Show history', 'salon-booking-system'); ?></span>
                            <span class="sln-account__card__showmore__hide"><?php esc_html_e('Hide history', 'salon-booking-system'); ?></span>
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                    </div>
                    <div class="collapse sln-account__discounted__bookings sln-account__usage__history" id="<?php echo esc_attr($sln_history_id); ?>">
                        <ul class="sln-account__usage__list">
                            <?php foreach ($discounted_booking as $booking): ?>
                                <li class="sln-account__discounted__booking">
                                    <span>
                                        <?php
                                        // translators: 1: booking ID, 2: booking date
                                        echo esc_html(sprintf(__('Booking #%1$s · %2$s', 'salon-booking-system'), $booking['id'], $booking['date']));
                                        ?>
                                    </span>
                                    <span class="sln-account__discounted__booking__price sln-account__num"><?php echo wp_kses_post($booking['total']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <a class="sln-account__btn sln-account__btn--primary sln-account__btn--wide sln-reschedule-booknow--button" href="<?php echo esc_url(add_query_arg(array('discount_id' => $discount->getID()), get_permalink($plugin->getSettings()->getPayPageId()))); ?>">
                    <?php esc_html_e('Book now', 'salon-booking-system'); ?>
                </a>
            </footer>
        </article>
    <?php endforeach; ?>
</div>
