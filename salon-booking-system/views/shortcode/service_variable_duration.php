<?php if ($service->isVariableDuration()): ?>
    <div class="sln-service-variable-duration" data-units-per-session="<?php echo esc_html($service->getMaxVariableDuration()) ?>">
        <div class="sln-service-variable-duration--counter">
            <span class="sln-service-variable-duration--counter--minus <?php echo $bb->getCountService($service->getId()) <= 0 ? 'sln-service-variable-duration--counter--button--disabled' : '' ?>"></span>
            <span class="sln-service-variable-duration--counter--value"><?php echo $bb->hasService($service) ? esc_html($bb->getCountService($service->getId())) : 0; ?></span>
            <span class="sln-service-variable-duration--counter--plus"></span>
            <input type="hidden" value="<?php echo  $bb->hasService($service) ? esc_html($bb->getCountService($service->getId())) : 0; ?>" name="sln[service_count][<?php echo esc_html($service->getId()) ?>]" class="sln-service-count-input">
        </div>
    </div>
<?php endif; ?>