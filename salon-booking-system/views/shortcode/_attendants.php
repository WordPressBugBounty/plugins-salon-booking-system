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

$hasAttendants = false;
$style         = $step->getShortcode()->getStyleShortcode();
$size          = SLN_Enum_ShortcodeStyle::getSize($style);


$services = $bb->getServices();
foreach ($services as $k => $service) {
    if (!$service->isAttendantsEnabled()) {
        unset($services[$k]);
    }
}

$isChooseAttendantForMeDisabled = $plugin->getSettings()->isChooseAttendantForMeDisabled();
$serviceVariablePriceEnabled = false;
$services_price = array();
foreach($services as $service){
    foreach($attendants as $attendant){
        $attendantId = $attendant->getId();
        
        if (!isset($services_price[$attendantId])) {
            $services_price[$attendantId] = 0;
        }
        $serviceVariablePriceEnabled |= $service->getVariablePriceEnabled();
        $service_price = $service->getVariablePriceEnabled() ? $service->getVariablePrice($attendantId) : $service->getPrice();

        $services_price[$attendantId] += floatval($service_price) * intval($bb->getCountService($service->getId()));
    }
}
if($serviceVariablePriceEnabled){
    $sort_func = function($att1, $att2)use($services_price){
        $price1 = $services_price[$att1->getId()];
        $price2 = $services_price[$att2->getId()];
        if($price1 == $price2){
            return 0;
        }
        return $price1 < $price2 ? 1 : -1;
    };
    usort($attendants, $sort_func);
}

$tmp = '';
$i = 0;
foreach ($attendants as $attendant) {
    if(get_post_status($attendant->getId()) == 'draft'){
        continue;
    }
    if(get_post_status($attendant->getId()) == 'private'){
        continue;
    }
    if(class_exists('\SalonMultishop\Addon')){
        if(get_user_meta($attendant->getId(),'_sln_booking_shop', true) == 'private'){
            continue;
        }
    }
    if ($attendant->hasServices($services)) {
        $errors = SLN_Shortcode_Salon_AttendantHelper::validateItem($bookingServices->getItems(), $ah, $attendant);
    if($plugin->getSettings()->get('hide_invalid_attendants_enabled') && !empty($errors)){
        continue;
    }

	if (!$i && $isChooseAttendantForMeDisabled) {
	    $tmp .= SLN_Shortcode_Salon_AttendantHelper::renderItem($size, $errors, $attendant, null, true, $services);
	} else {
	    $tmp .= SLN_Shortcode_Salon_AttendantHelper::renderItem($size, $errors, $attendant, null, false, $services);
	}

    $hasAttendants = true;
    if(is_null($errors)){
	    $i++;
    }
    }
}
if ($tmp && !$isChooseAttendantForMeDisabled) {
    $tmp = SLN_Shortcode_Salon_AttendantHelper::renderItem($size, null, null, null, true).$tmp; // add when attendant is null "Choose an assistant for me"
}

$isSymbolLeft = $plugin->getSettings()->get('pay_currency_pos') == 'left';
$symbolLeft = $isSymbolLeft ? $plugin->getSettings()->getCurrencySymbol() : '';
$symbolRight = $isSymbolLeft ? '' : $plugin->getSettings()->getCurrencySymbol();
$decimalSeparator = $plugin->getSettings()->getDecimalSeparator();
$thousandSeparator = $plugin->getSettings()->getThousandSeparator();

?>
<div class="sln-attendant-list sln-list sln-list--<?php echo $size; ?>">
    <?php if($i > 1): ?>
    <div class="sln-list__item sln-list__item--icons">
        <?php $icons = $step->renderSortIcon();
        echo $icons[0], $icons[1]; ?>
    </div>
    <?php endif ?>
    <?php if ($tmp) : ?>
        <?php echo $tmp ?>
    <?php else: ?>
        <div class="alert alert-warning">
            <p><?php echo apply_filters('sln.template.shortcode.attendant.emptyAttendantsList', __(
                    'No assistants available for the selected time/slot - please choose another one',
                    'salon-booking-system'
                )) ?></p>
        </div>
    <?php endif ?>
</div>
