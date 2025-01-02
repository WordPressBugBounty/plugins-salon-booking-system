<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<label for="<?php echo !$service->isVariableDuration() ? SLN_Form::makeID('sln[services][' . $service->getId() . ']') : '' ?>" class="sln-list__item sln-service sln-service--<?php echo $service->getId(); ?>">
    
        <?php
            $thumb = has_post_thumbnail($service->getId()) ? get_the_post_thumbnail(
            $service->getId(),
            'thumbnail'
            ) : '';
        ?>
         <?php if ($thumb): ?>
        <div class="sln-list__item__thumb">
            <?php echo $thumb ?>
        </div>
    <?php endif ?>
<div class="sln-list__item__content">
        <h3 class="sln-steps-name sln-service-name sln-list__item__name"><?php echo $service->getName(); ?></h3>
        <?php
            $string = $service->getContent();
            $incipit = explode("\n", wordwrap($string, 50));
            $incipit = array_shift($incipit);
            $more = str_replace($incipit, '', $string) ;
        ?>
        <p class="sln-service-description sln-list__item__description <?php if (!empty($more)) { echo "sln-list__item__description__toggle"; } ?>">
            <?php
            echo $incipit;
            if (!empty($more)) {
                echo ' <span class="sln-list__item__description__breakdots">...</span> <span class="sln-list__item__description__more">' . $more . '</span>';
            }
            ?>
        </p>
        <div class="sln-service__info sln-list__item__info">
               <?php if($showPrices): ?>
                <h3 class="sln-steps-price sln-service-price sln-list__item__price">
                    <?php if ($service->getVariablePriceEnabled()): ?>
                        <?php esc_html_e('from', 'salon-booking-system') ?>
                    <?php endif; ?>
                    <span class="sln-service-price-value sln-list__item__proce__value"><?php echo $plugin->format()->moneyFormatted($service->getPrice())?></span>
                    <!-- .sln-service-price // END -->
                </h3>
            <?php endif ?>
            <?php if ($service->getDuration()->format('H:i') != '00:00' && !$plugin->getSettings()->get('hide_service_duration')):
                        $duration = $service->getTotalDuration(); ?>
                   <h3><span class="sln-steps-duration sln-service-duration sln-list__item__duration">
                        <?php echo $duration->format('H:i'); ?>
                    </span></h3>
                <?php endif ?>
        </div>
    </div>
        
<div class="sln-service__action sln-list__item__action">
    <div class="sln-checkbox <?php echo $service->isvariableDuration() ? 'hide' : ''; ?>">
        <?php SLN_Form::fieldCheckbox(
            'sln[services][' . $service->getId() . ']',
            $bb->hasService($service),
            $settings
        ) ?>
        <label for="<?php echo SLN_Form::makeID('sln[services][' . $service->getId() . ']') ?>"></label>
    </div>
     <?php include 'service_variable_duration.php'; ?>   
</div>
<span class="sln-service__errors sln-list__item__errors errors-area" data-class="sln-alert sln-alert-medium sln-alert--problem"><?php if ($serviceErrors) foreach ($serviceErrors as $error): ?><div class="sln-alert sln-alert-medium sln-alert--problem"><?php echo $error ?></div><?php endforeach ?></span>
    <div class="sln-alert sln-alert-medium sln-alert--problem" style="display: none" id="availabilityerror"><?php esc_html_e('Not enough time for this service','salon-booking-system') ?></div>
    <div class="sln-list__item__fkbkg"></div>
</label>
