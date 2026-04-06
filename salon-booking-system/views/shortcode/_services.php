<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var string $formAction
 * @var string $submitName
 * @var SLN_Shortcode_Salon_ServicesStep $step
 * @var SLN_Wrapper_Service[] $services
 */

$ah = $plugin->getAvailabilityHelper();
$bb = $plugin->getBookingBuilder();
$ah->setDate($bb->getDateTime());
$isSymbolLeft = $plugin->getSettings()->get('pay_currency_pos') == 'left';
$symbolLeft = $isSymbolLeft ? $plugin->getSettings()->getCurrencySymbol() : '';
$symbolRight = $isSymbolLeft ? '' : $plugin->getSettings()->getCurrencySymbol();
$decimalSeparator = $plugin->getSettings()->getDecimalSeparator();
$thousandSeparator = $plugin->getSettings()->getThousandSeparator();
$grouped = SLN_Repository_ServiceRepository::groupServicesByCategory($services);
$showPrices = ($plugin->getSettings()->get('hide_prices') != '1') ? true : false;

if ($plugin->getSettings()->isFormStepsAltOrder()) {
    $servicesErrors = array();
} else {
    $servicesErrors = $ah->checkEachOfNewServicesForExistOrder($bb->getServicesIds(), $services);
} 
    
$style = $step->getShortcode()->getStyleShortcode();
$size = SLN_Enum_ShortcodeStyle::getSize($style);
?>
<?php SLN_Form::fieldText('sln[date]', $bb->getDate(), array('type' => 'hidden')) ?>
<?php SLN_Form::fieldText('sln[time]', $bb->getTime(), array('type' => 'hidden')) ?>
<div class="sln-service-list sln-list <?php if ($size == '400') { echo 'sln-list--s'; } ?> <?php echo 'sln-list--' . $size; ?> clearfix"> 
    <?php foreach ($grouped as $group): ?>
        <?php if ($group['term'] !== false && count($group['services']) > 1): ?>
            <div class="row sln-panel">

        <?php

        $openGroup = false;

        foreach ($group['services'] as $service) {

            $openGroup = $bb->hasService($service);

            if ($openGroup) {
            break;
            }
        }
        ?>
        <a class="col-xs-12 sln-panel-heading <?php echo ($openGroup || count($group['services']) == 1) ? '' : ' collapsed ' ?>" role="button"
               data-toggle="collapse" href="#collapse<?php echo $group['term']->getId() ?>"
               aria-expanded="false" aria-controls="collapse<?php echo $group['term']->getId() ?>">
                <h2 class="sln-btn sln-btn--icon sln-btn--fullwidth">
                    <?php echo esc_html__(sprintf('%s', $group['term']->getName()),'salon-booking-system') ?></h2>
            </a>
            <div id="collapse<?php echo $group['term']->getId() ?>"
            class="col-xs-12 sln-panel-content panel-collapse collapse <?php echo ($openGroup || count($group['services']) == 1) ? ' in ' : '' ?>" role="tabpanel"
            aria-labelledby="collapse<?php echo $group['term']->getId() ?>Heading"
            aria-expanded="false" style="<?php echo ($openGroup || count($group['services']) == 1) ? '' : 'height: 0px;' ?>">
        <?php endif ?>
        <?php foreach ($group['services'] as $service) {
            $serviceErrors = isset($servicesErrors[$service->getId()]) ? $servicesErrors[$service->getId()] : array();
            $settings = array(
                'attrs' => array(
                    'data-price' => $service->getPrice(),
                    'data-duration' => SLN_Func::getMinutesFromDuration($service->getTotalDuration()),
                ),
            );
            if ($serviceErrors) {
                $settings['attrs']['disabled'] = 'disabled';
            }
            if ($size == '900') {
                include '_services_item_900.php';
            } elseif ($size == '600') {
                include '_services_item_600.php';
            } elseif ($size == '400') {
                include '_services_item_400.php';
            } else {
                throw new Exception('size not supported');
            }
        } ?>
        <?php if ($group['term'] !== false && count($group['services']) > 1): ?>
            <!-- panel END -->
            </div>
            </div>
            <!-- panel END -->
        <?php endif ?>
    <?php endforeach ?>
    <!-- .sln-service-list // END -->
</div>

