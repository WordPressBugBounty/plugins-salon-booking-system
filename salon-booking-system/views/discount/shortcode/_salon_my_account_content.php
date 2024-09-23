<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
?>
<div role="tabpanel" class="tab-pane sln-account__tabpanel sln-account__tabpanel--discounts" id="sln-account__discount__content">
    <?php foreach(SLN_Plugin::getInstance()->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT)->getAll() as $discount): ?>
        <?php
        if($discount->isHideFromAccount()){
            continue;
        }
        $discounted_booking = array();
        foreach($booking_history as $booking){
            if(get_post_meta($booking['id'], '_sln_booking_discount_'.$discount->getId(), true)){
                $discounted_booking[] = $booking;
            }
        }
        $errors = $discount->validateDiscountForMail((new DateTime())->getTimestamp(), $customer);
        if(count($discounted_booking) || empty($errors)): ?>
            
        <article class="sln-account__booking sln-account__card sln-account__list__item">
            <header class="sln-account__card__header">
            <?php if(empty($errors)): ?>
                <h4 class="sln-account__card__header__el">
                    <?php if($discount->getDiscountType() === SLB_Discount_Enum_DiscountType::DISCOUNT_AUTO){
	                    esc_html_e('Automatic discount', 'salon-booking-system');
                    }else{
                        echo esc_attr($discount->getCouponCode());  ?>
                        <small><?php esc_html_e('Coupon code', 'salon-booking-system');?></small>
                    <?php } ?>
                </h4>
                <h4 class="sln-account__card__header__el">
                    <?php echo $plugin->format()->date($discount->getEndsAt());  ?>
                    <small><?php esc_html_e('Expiration', 'salon-booking-system');?></small>
                </h4>
            <?php else: ?>
                <h4 class="sln-account__card__header__el sln-account__card__header__error">
                    <?php echo $errors[0]; ?>
                </h4>
            <?php endif;?> 
            </header>
            <h3 class="sln-account__card__title">
                <?php echo esc_attr($discount->getName()); ?>
            </h3>
            <section class="sln-account__card__body">
                <ul class="sln-account__services__list">
                    <?php foreach($discount->geAttendantsIds() as $attendant):
                        $attendant = $plugin->createFromPost($attendant); ?>
                        <li class="sln-account__service">
                            <span class="sln-account__service__assistant">(<?php echo esc_attr($attendant->getName()); ?>)</span>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach($discount->getServicesIds() as $service):
                        $service = $plugin->createFromPost($service); ?>
                        <li class="sln-account__service">                        
                            <span class="sln-account__service__name"><?php echo esc_attr($service->getName()) ?></span>
                            <?php if(!$plugin->getSettings()->isHidePrices()): ?>
                                <span class="sln-account__service__price" data-th="<?php esc_html_e('Price', 'salon-booking-system');?>">
                                    <?php echo $plugin->format()->moneyFormatted($service->getPrice()) ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="sln-account__card__total">
                <h5 class="sln-account__card__total__amount">Amount: <strong><?php echo esc_attr($discount->getAmountString()) ?></strong></h5>
            </section>
            <footer class="sln-account__card__footer sln-account__card__actions sln-account__booking__actions">
            <?php if (count($discounted_booking) > 0): ?>
                <div class="sln-account__card__showmore">
                    <h4 class="sln-account__card__info"><?php echo sprintf(
                            // translators: %s: the name of the discounted booking
                            __('Used <strong>%s</strong> times', 'salon-booking-system'), count($discounted_booking)); ?></h4>
                    <a class="sln-account__card__showmore__trigger collapsed" data-toggle="collapse" href="#sln-discount__used--<?php echo esc_attr($discount->getID());  ?>" role="button" aria-expanded="false" aria-controls="sln-discount__used--<?php echo esc_attr($discount->getID());  ?>">
                        <h3 class="sln-account__card__showmore__display"><?php esc_html_e('Display reservations', 'salon-booking-system');?></h3>
                        <h3 class="sln-account__card__showmore__hide"><?php esc_html_e('Hide reservations', 'salon-booking-system');?></h3>
                    </a>
                    <span class="sln-account__card__icon"><span class="sr-only"><?php echo sprintf(
                            // translators: %s: the name of the discounted booking
                            __('Used <strong>%s</strong> times', 'salon-booking-system'), count($discounted_booking)); ?></span></span>
                </div>
            <?php endif;?>
            <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth- sln-reschedule-booknow--button">
                <a class="" href="<?php echo add_query_arg(array('discount_id' => $discount->getID()), get_permalink($plugin->getSettings()->getPayPageId())) ?>">
                    <?php esc_html_e('Book now', 'salon-booking-system');?>
                </a> 
            </div> 
            </footer>
            <div class="collapse sln-account__discounted__bookings sln-account__card__showmore__history" id="sln-discount__used--<?php echo esc_attr($discount->getID());  ?>">
                    <?php foreach($discounted_booking as $booking): ?>
                        <div class="sln-account__discounted__booking sln-account__card__showmore__item">
                            <span class="sln-account__discounted__booking__id"><?php esc_html_e('ID', 'salon-booking-system');?> <strong><?php echo esc_attr($booking['id']); ?></strong></span> - <span class="sln-account__discounted__booking__date"><?php echo esc_attr($booking['date']); ?></span> - <span class="sln-account__discounted__booking__price"><?php echo esc_attr($booking['total']); ?></span>
                        </div>
                    <?php endforeach ;?>
                </div>
        </article>
        <?php endif; ?>
    <?php endforeach; ?>
</div>