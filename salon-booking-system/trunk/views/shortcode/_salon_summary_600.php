<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div class="row sln-summary">
    <div class="col-xs-12">
        <div class="row sln-box--main sln-box--fixed_height">
            <div class="col-xs-12">
                <div class="sln-summary__recap sln-list">
                    <div class="sln-summary-row sln-list__item sln-list__item--db">
                        <div class="sln-data-val">
                            <strong><?php echo $plugin->format()->date($datetime); ?> / <?php echo $plugin->format()->time($datetime) ?></strong>
                        </div>
                        <div class="sln-data-desc">
                            <?php
                            $args = array(
                                'key'          => 'Date and time booked',
                                'label'        => __('Date and time booked', 'salon-booking-system'),
                                'tag'          => 'span',
                                'textClasses'  => 'text-min label',
                                'inputClasses' => 'input-min',
                                'tagClasses'   => 'label',
                            );
                            echo $plugin->loadView('shortcode/_editable_snippet', $args);
                            ?>
                        </div>
                    </div>
                    <div class="sln-summary-row sln-list__item sln-list__item--db">
                        <div class="sln-data-val">
                            <ul class="sln-summary__list <?php if(!$showPrices){echo ' sln-summary__list--2col';}?>">
                                <?php foreach ($bb->getBookingServices()->getItems() as $bookingService): ?>
                                    <?php $service = $bookingService->getService(); ?>
                                    <li class="sln-summary__list__item ">
                                                <?php $attendant = isset($bb->getAttendantsIds()[$service->getId()]) ? $plugin->createAttendant($bb->getAttendantsIds()[$service->getId()]) : null; ?>
                                        <?php if($showPrices){?>
                                            <div class="sln-summary__list__price">
                                                <?php $servicePrice = $bookingService->getPrice() ?>
                                                <?php echo $plugin->format()->moneyFormatted($servicePrice) ?>
                                            </div>
                                        <?php } ?>
                                        <div class="sln-summary__list__info">
                                            <?php
                                                echo ' <span class="sln-summary__list__name">' . $service->getName() . '</span>';
                                                if (!empty($service->getServiceCategory())) {
                                                    echo ' <span class="sln-summary__list__secondary">(' . $service->getServiceCategory()->getName() . ')</span>';
                                                }
                                            ?>
                                            <?php if (isset($bb->getCountServices()[$service->getId()]) && $bb->getCountServices()[$service->getId()] > 1): ?>
                                                 <?php echo ' <span class="sln-summary__list__secondary">x ' . $bb->getCountServices()[$service->getId()] . '</span>' ?>
                                            <?php endif; ?>
                                         </div>
                                        <?php if(!empty($attendant)): ?>
                                        <div class="sln-summary__list__attendant">
                                            <?php if(!is_array($attendant)): ?>
                                                <span><?php echo $attendant->getName() ?></span>
                                            <?php else:
                                                foreach($attendant as $att):?>
                                                    <span><?php echo $att->getName(). ' ' ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif ?>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                        </div>
                        <div class="sln-data-desc">
                            <?php
                            $args = array(
                                'key'          => 'Services & assistants',
                                'label'        => __('Services & assistants', 'salon-booking-system'),
                                'tag'          => 'span',
                                'textClasses'  => 'text-min label',
                                'inputClasses' => 'input-min',
                                'tagClasses'   => 'label',
                            );
                            echo $plugin->loadView('shortcode/_editable_snippet', $args);
                            ?>
                        </div>
                    </div>
                    <?php
                    if (class_exists('SalonPackages\Addon') && slnpackages_is_pro_version_salon() && slnpackages_is_license_active()) {
                        echo $plugin->templating()->loadView('shortcode/_salon_summary_credit', compact('plugin', 'bb', 'packages_credits'));
                    }
                    ?>
                    <!--
                    <?php if($attendants = $bb->getAttendants(true)) :  ?>
                        <div class="sln-summary-row sln-list__item sln-list__item--db">
                            <div class="sln-data-val">
                                <?php $names = array();
                                foreach($attendants as $att) {
                                    if(!is_array($att)){
                                        $names[] = $att->getName();
                                    }else{
                                        $names = array_merge($names, SLN_Wrapper_Attendant::getArrayAttendantsValue('getName', $att));
                                    }
                                }
                                echo implode(', ', $names);?>
                            </div>
                            <div class="sln-data-desc">
                                <?php
                                $args = array(
                                    'key'          => 'Assistants',
                                    'label'        => __('Assistants', 'salon-booking-system'),
                                    'tag'          => 'span',
                                    'textClasses'  => 'text-min label',
                                    'inputClasses' => 'input-min',
                                    'tagClasses'   => 'label',
                                );
                                echo $plugin->loadView('shortcode/_editable_snippet', $args);
                                ?>
                            </div>
                        </div>
                    <?php // IF ASSISTANT
                    endif ?>
                -->
                    <?php do_action('sln.template.summary.before_total_amount', $bb, $size); ?>
                    <?php if($settings->get('pay_transaction_fee_amount')): ?>
                        <div class="sln-summary-row sln-summary-row--transaction-fee sln-list__item sln-list__item--db">
                            <div class="sln-data-val">
                                <span id="sln_transaction_fee_value"><?php echo $plugin->format()->money(SLN_Helper_TransactionFee::getFee($bb->getAmount()), false, false, true); ?></span>
                            </div>
                            <div class="sln-data-desc">
                                <?php
                                $args = array(
                                    'key'          => 'Transaction fee',
                                    'label'        => __('Transaction fee', 'salon-booking-system'),
                                    'tag'          => 'span',
                                    'textClasses'  => 'text-min label',
                                    'inputClasses' => 'input-min',
                                    'tagClasses'   => 'label',
                                );
                                echo $plugin->loadView('shortcode/_editable_snippet', $args);
                                ?>
                            </div>
                        </div>
                    <?php endif ?>
                    <?php if ($isTipRequestEnabled): ?>
                        <?php include '_salon_summary_show_tips.php'; ?>
                    <?php endif; ?>
                    <?php
                         if($settings->get('enable_booking_tax_calculation')){
                        include '_salon_summary_show_tax.php';
                    } ?>
                    <?php if($showPrices){?>
                        <div class="sln-summary-row sln-list__item sln-list__item--db">
                            <div class="sln-data-val sln-total-price">
                                <?php echo $plugin->format()->moneyFormatted($bb->getAmount(true)) ?>
                            </div>
                            <div class="sln-data-desc">
                                <?php esc_html_e('Total amount', 'salon-booking-system') ?>
                            </div>
                        </div>
                    <?php }; ?>
                <!-- sln-box--main sln-box--fixed_height // END -->
                </div>
            </div>
        </div>
        <div class="sln-summary__tabs">
            <?php $enableDiscountSystem = $this->plugin->getSettings()->get('enable_discount_system'); ?>
            <ul class="sln-summary__tabs__nav" id="myTab" role="tablist">
                <li class="sln-summary__tabs__nav__item active" role="presentation">
                    <a href="#nogo" class="sln-summary__tabs__toggle" id="message-tab" data-toggle="tab" data-target="#message" type="button" role="tab" aria-controls="message" aria-selected="true">
                        <span><?php esc_html_e('Leave a message', 'salon-booking-system'); ?></span>
                    </a>
                </li>
                <?php if ($enableDiscountSystem): ?>
                <li class="sln-summary__tabs__nav__item" role="presentation">
                    <a href="#nogo" class="sln-summary__tabs__toggle" id="coupon-tab" data-toggle="tab" data-target="#coupon" type="button" role="tab" aria-controls="coupon" aria-selected="true">
                        <span><?php esc_html_e('Enter discount code', 'salon-booking-system'); ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($isTipRequestEnabled): ?>
                <li class="sln-summary__tabs__nav__item" role="presentation">
                    <a href="#nogo" class="sln-summary__tabs__toggle" id="tip-tab" data-toggle="tab" data-target="#tip" type="button" role="tab" aria-controls="tip" aria-selected="true">
                        <span><?php esc_html_e('Leave a tip', 'salon-booking-system'); ?></span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="tab-content sln-summary__tabs__content">
              <div class="tab-pane sln-summary__tabs__pane active" id="message" role="tabpanel" aria-labelledby="message-tab">
                <div class="sln-summary__tabs__pane__content sln-summary__message">
                    <?php
                    $args = array(
                        'key'          => 'Leave a message.',
                        'label'        => __('Leave a message.', 'salon-booking-system'),
                        'tag'          => 'label',
                        'textClasses'  => '',
                        'inputClasses' => '',
                        'tagClasses'   => '',
                    );
                    echo $plugin->loadView('shortcode/_editable_snippet', $args);
                    ?>
                    <?php SLN_Form::fieldTextarea(
                        'sln[note]',
                        $bb->getMeta('note'),
                        array('attrs' => array('placeholder' => __('Leave a message', 'salon-booking-system')))
                    ); ?>
                </div>
              </div>
              <?php if ($enableDiscountSystem): ?>
                <div class="tab-pane sln-summary__tabs__pane" id="coupon" role="tabpanel" aria-labelledby="coupon-tab">
                    <?php do_action('sln.template.summary.after_total_amount', $bb, $size); ?>
                </div>
              <?php endif; ?>
              <?php if ($isTipRequestEnabled): ?>
                <div class="tab-pane sln-summary__tabs__pane" id="tip" role="tabpanel" aria-labelledby="tip-tab">
                    <?php include '_salon_summary_add_tips.php'; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    
    <?php do_action('sln.template.summary.before_termssln.template.summary.before_terms', $bb, $size); ?>
    <div class="sln-summary__reminder sln-summary__reminder--m">
         <?php if(!empty($paymentMethod)){ ?>
            <h3><?php esc_html_e('Complete the payment to confirm your reservation', 'salon-booking-system'); ?></h3>
            <h5><?php esc_html_e('Choose your favourite payment method', 'salon-booking-system'); ?></h5>
        <?php } else {?>
            <h3><?php esc_html_e('Complete your reservation', 'salon-booking-system'); ?></h3>
            <h5><?php esc_html_e('Click on Next button', 'salon-booking-system'); ?></h5>
        <?php } ?>
    </div>
    <div class="sln-summary__terms">
         <h5><?php esc_html_e('Terms & Conditions','salon-booking-system')?></h5>
        <p><?php echo $plugin->getSettings()->get('gen_timetable')
            /*_e(
                'In case of delay of arrival. we will wait a maximum of 10 minutes from booking time. Then we will release your reservation',
                'salon-booking-system'
            )*/ ?></p>
    </div>
    <?php do_action('sln.template.summary.after_terms', $bb, $size); ?>
</div>
</div>

    