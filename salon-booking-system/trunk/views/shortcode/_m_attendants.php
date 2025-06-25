<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin                        $plugin
 * @var string                            $formAction
 * @var string                            $submitName
 * @var SLN_Shortcode_Salon_AttendantStep $step
 * @var SLN_Wrapper_Attendant[]           $attendants
 */

$ah = $plugin->getAvailabilityHelper();
$ah->setDate($plugin->getBookingBuilder()->getDateTime());
$bookingServices = SLN_Wrapper_Booking_Services::build($bb->getAttendantsIds(), $bb->getDateTime(), 0, $bb->getCountServices());

$isSymbolLeft = $plugin->getSettings()->get('pay_currency_pos') == 'left';
$symbolLeft = $isSymbolLeft ? $plugin->getSettings()->getCurrencySymbol() : '';
$symbolRight = $isSymbolLeft ? '' : $plugin->getSettings()->getCurrencySymbol();
$decimalSeparator = $plugin->getSettings()->getDecimalSeparator();
$thousandSeparator = $plugin->getSettings()->getThousandSeparator();

$isChooseAttendantForMeDisabled = $plugin->getSettings()->isChooseAttendantForMeDisabled();

foreach ($bookingServices->getItems() as $bookingService) :
    $service = $bookingService->getService();
    if($service->getVariablePriceEnabled()){
        $sort_func = function($att1, $att2)use($service){
            $price1 = $service->getVariablePrice($att1->getId());
            $price2 = $service->getVariablePrice($att2->getId());
            if($price1 == $price2){
                return 0;
            }
            return $price1 < $price2 ? 1 : -1;
        };
        usort($attendants, $sort_func);
    }

    if ($service->isAttendantsEnabled()) {
        $tmp = '';
	$i = 0;
        foreach ($attendants as $attendant) {
            if(get_post_status($attendant->getId()) == 'draft'){
                continue;
            }
            if ($attendant->hasServices(array($service))) {
                $errors = SLN_Shortcode_Salon_AttendantHelper::validateItem(array($bookingService), $ah, $attendant);

                if($plugin->getSettings()->get('hide_invalid_attendants_enabled') && !empty($errors)){
                    continue;
                }

                if (!$i && $isChooseAttendantForMeDisabled) {
                    $tmp .= SLN_Shortcode_Salon_AttendantHelper::renderItem($size, $errors, $attendant, $service, true);
                } else {
                    $tmp .= SLN_Shortcode_Salon_AttendantHelper::renderItem($size, $errors, $attendant, $service);
                }

                $i++;
            }
        }
        if ($tmp && !$isChooseAttendantForMeDisabled) {
            $tmp = SLN_Shortcode_Salon_AttendantHelper::renderItem($size, null, null, $service, true).$tmp;
        }
    }


    ?>
    <div class="sln-attendant-list sln-attendant-list--multiple sln-list sln-list--multiple">
                <h3 class="sln-steps-name sln-service-name"><?php echo $service->getName() ?></h3>
        <?php if ($service->isAttendantsEnabled()) : ?>
            <?php if ($tmp) : ?>
                        <div class="sln-list__horscroller">
                            <div class="sln-list__horscroller__in">
                                <?php echo $tmp ?>
                            </div>
                        </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <p><?php echo apply_filters('sln.template.shortcode.attendant.emptyAttendantsList', __(
                            'No assistants available for the selected time/slot - please choose another one',
                            'salon-booking-system'
                        )) ?></p>
                </div>
            <?php endif ?>
        <?php else: ?>
            <div class="row sln-attendant">
                <?php SLN_Form::fieldText('sln[attendants]['.$service->getId().']', 0, array('type' => 'hidden')) ?>
                <p><?php echo esc_html__(
                        'The choice of assistant is not provided for this service',
                        'salon-booking-system'
                    ) ?></p>
            </div>
        <?php endif ?>
    </div>
<?php endforeach ?>
