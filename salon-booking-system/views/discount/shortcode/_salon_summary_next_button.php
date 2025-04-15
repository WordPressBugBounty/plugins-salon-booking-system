<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<button
    <?php if($plugin->getSettings()->isAjaxEnabled()): ?>
        data-salon-data="<?php echo "sln_step_page=summary&submit_summary=next" ?>" data-salon-toggle="next"
    <?php endif?>
    id="sln-step-submit" type="submit" name="submit_summary" value="next">
    <?php echo esc_html__('Next step', 'salon-booking-system'); ?> <i class="glyphicon glyphicon-chevron-right"></i>
</button>
<button
        id="sln-step-submit-complete" value="next hidden">
    <?php echo esc_html__('Complete', 'salon-booking-system'); ?> <i class="glyphicon glyphicon-chevron-right"></i>
</button>