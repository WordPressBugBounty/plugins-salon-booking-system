<?php if ($attendant->getId()): ?>
    <?php $bb = SLN_Plugin::getINstance()->getBookingBuilder(); ?>
        <?php foreach($bb->getServices() as $service): ?>
            <?php if ($service->getVariablePriceEnabled()): ?>
                <?php $servicePrice = $service->getVariablePrice($attendant->getId()) !== '' ? $service->getVariablePrice($attendant->getId()) : $service->getPrice() ?>
                <div class="sln-steps-price sln-list__item__price" data-price="<?php echo esc_html($servicePrice * $bb->getCountService($service->getId())) ?>">
                    <span class="sln-list__item__price--fw-light"><?php echo esc_html($service->getTitle())?>:</span>
                    <?php 
                    echo esc_html($plugin->format()->moneyFormatted($servicePrice));
                    if ($bb->getCountService($service->getId()) != 1) {
                        echo ' ('. esc_html($plugin->format()->moneyFormatted($servicePrice*$bb->getCountService($service->getId()))). ')';
                    }                    
                    ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
<?php endif; ?>