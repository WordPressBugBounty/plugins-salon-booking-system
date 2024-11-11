<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$isMulti = $plugin->getSettings()->isMultipleAttendantsEnabled();
?>
<label for="<?php echo esc_html($elemId) ?>" class="sln-list__item <?php if ($isMulti) { echo 'sln-list--multiple__item'; } ?> sln-attendant">
    <?php if ($thumb): ?>
        <div class="sln-list__item__thumb">
            <?php echo $thumb; ?>
        </div>
    <?php endif ?>
    <div class="sln-list__item__content">
        <h3 class="sln-steps-name sln-attendant-name sln-list__item__name"><?php echo esc_html($attendant->getName()); ?></h3>
        <?php
            $string = $attendant->getContent();
            $incipit = array_shift(explode("\n", wordwrap($string, 50)));
            $more = str_replace($incipit, '', $string) ;
        ?>
        <p class="sln-steps-description sln-attendant-description sln-list__item__description <?php if (!empty($more)) { echo "sln-list__item__description__toggle"; } ?>">
            <?php
            echo esc_html($incipit);
            if (!empty($more) && !$isMulti) {
                echo ' <span class="sln-list__item__description__breakdots">...</span> <span class="sln-list__item__description__more">' . esc_html($more) . '</span>';
            } else if (!empty($more) && $isMulti)  {
                echo ' <span class="sln-list__item__description__breakdots">...</span>';
            }
            ?>
        </p>
        <div class="sln-service__info sln-list__item__info">
            <div>
            <?php
            if($plugin->getSettings()->get('hide_prices') != '1'){
                include 'service_variable_price.php';
            }
            ?>
            </div>
        </div>
    </div>
    <div class="sln-service__action sln-list__item__action">
        <div class="sln-radiobox sln-steps-check sln-attendant-check <?php $isChecked  ? 'is-checked' : '' ?>">
            <?php SLN_Form::fieldRadioboxForGroup($field, $field, $attendant->getId(), $isChecked, $settings) ?>
        </div>
    </div>
        <?php if ($isMulti) : ?>
        <span class="sln-list__item__content--add">
            <p class="sln-steps-description sln-attendant-description sln-list__item__description sln-list__item__description__toggle">
                <?php echo esc_html($string); ?>
            </p>
        </span>
        <?php endif ?>
    <?php echo $tplErrors; ?>
</label>