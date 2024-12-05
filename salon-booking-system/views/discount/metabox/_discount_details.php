<?php
/**
 * @var SLN_Plugin $plugin
 * @var SLN_Settings $settings
 * @var SLN_Metabox_Helper $helper
 * @var SLB_Discount_Wrapper_Discount $discount
 * @var string $postType
 *
 */
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
$helper->showNonce($postType);
$isShopEnabled = false;
$isShopEnabled = apply_filters('sln_is_shops_enabled',$isShopEnabled);
?>

<div class="row">
<!-- default settings -->
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-input--simple">
        <label><?php echo esc_html__('Amount', 'salon-booking-system') ?></label>
        <?php SLN_Form::fieldText($helper->getFieldName($postType, 'amount'), $discount->getAmount()); ?>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-select">
        <label><?php esc_html_e('Type', 'salon-booking-system'); ?></label>
        <?php SLN_Form::fieldSelect(
            $helper->getFieldName($postType, 'amount_type'),
            array(
                'fixed'      => $settings->getCurrency() . ' (' . $settings->getCurrencySymbol() . ')',
                'percentage' => __('%', 'salon-booking-system'),
            ),
            $discount->getAmountType(),
            array(),
            true
        ); ?>
        <p><?php esc_html_e('Type the amount of this discount','salon-booking-system'); ?></p>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-input--simple">
        <label><?php esc_html_e('Maximum uses limit', 'salon-booking-system'); ?></label>
        <?php SLN_Form::fieldText($helper->getFieldName($postType, 'usages_limit_total'), $discount->getTotalUsagesLimit()); ?>
        <p><?php esc_html_e('Leave it blank for an unlimited times of usage','salon-booking-system'); ?></p>
    </div>
    <div class="sln-clear"></div>
</div>
<div class="row">
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-slider-wrapper">
        <label><?php echo esc_html__('Valid from', 'salon-booking-system') ?></label>
        <div class="sln_datepicker">
            <?php SLN_Form::fieldJSDate(
                $helper->getFieldName($postType, 'from'),
                $discount->getStartsAt()
            ) ?>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-slider-wrapper">
        <label><?php esc_html_e('To', 'salon-booking-system'); ?></label>
        <div class="sln_datepicker">
            <?php SLN_Form::fieldJSDate(
                $helper->getFieldName($postType, 'to'),
                $discount->getEndsAt()
            ) ?>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-4 form-group sln-input--simple">
        <label><?php esc_html_e('Per single user limit', 'salon-booking-system'); ?></label>
        <?php SLN_Form::fieldText($helper->getFieldName($postType, 'usages_limit'), $discount->getUsagesLimit()); ?>
        <p><?php esc_html_e('Leave it blank for an unlimited times of usage','salon-booking-system'); ?></p>
    </div>
    <div class="sln-clear"></div>
</div>
<div class="row">
    <div class="col-xs-12 col-md-8 form-group sln-select">
        <label><?php echo esc_html__('Limit this discount to the following services', 'salon-booking-system') ?></label>
        <?php
        /** @var SLN_Wrapper_Service[] $services */
        $services = $plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE)->getAll();
        $items    = array();
        foreach($services as $s) {
            $items[$s->getId()] = $s->getName();
        }
        SLN_Form::fieldSelect(
            $helper->getFieldName($postType, 'services[]'),
            $items,
            (array)$discount->getMeta('services'),
            array('attrs' => array('multiple' => true, 'data-containerCssClass' => 'sln-select-wrapper-no-search')),
            true
        ); ?>
        <p><?php esc_html_e('Leave it blank if you want to be applied to all services','salon-booking-system'); ?></p>
    </div>
    <?php if($settings->get('attendant_enabled') === '1') : ?>
        <div class="col-xs-12 col-md-8 form-group sln-select">
            <label><?php echo esc_html__('Limit this discount to the assistants', 'salon-booking-system') ?></label>
            <?php
            /** @var SLN_Wrapper_Attendant[] $attendants */
            $attendants = $plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT)->getAll();
            $items    = array();
            foreach($attendants as $s) {
                $items[$s->getId()] = $s->getName();
            }
            SLN_Form::fieldSelect(
                $helper->getFieldName($postType, 'attendants[]'),
                $items,
                (array)$discount->getMeta('attendants'),
                array('attrs' => array('multiple' => true, 'data-containerCssClass' => 'sln-select-wrapper-no-search')),
                true
            ); ?>
            <p><?php esc_html_e('Leave it blank if you want to be applied to all assistants','salon-booking-system'); ?></p>
        </div>
    <?php endif;?>
    <?php if($isShopEnabled) : ?>
        <div class="col-xs-12 col-md-8 form-group sln-select">
            <label><?php echo esc_html__('Limit this discount to the following shops', 'salon-booking-system') ?></label>
            <?php
            /** @var SLN_Wrapper_Attendant[] $attendants */
            $shops = $plugin->getRepository(SLN_Plugin::POST_TYPE_SHOP)->getAll();
            $items    = array();
            foreach($shops as $s) {
                $items[$s->getId()] = $s->getTitle();
            }
            SLN_Form::fieldSelect(
                $helper->getFieldName($postType, 'shops[]'),
                $items,
                (array)$discount->getMeta('shops'),
                array('attrs' => array('multiple' => true, 'data-containerCssClass' => 'sln-select-wrapper-no-search')),
                true
            ); ?>
            <p><?php esc_html_e('Leave it blank if you want to be applied to all shops','salon-booking-system'); ?></p>
        </div>
    <?php endif;?>
    <div class="col-xs-12 col-md-4 form-group sln-checkbox">
        <?php SLN_Form::fieldCheckboxButton($helper->getFieldName($postType, 'email_notify'), $discount->getMeta('email_notify'), __('Notify this discout by email.', 'salon-booking-system')); ?>
    </div>
    <div class="sln-clear"></div>
</div>
<div class="row">
    <div class="col-xs-12 col-md-4 from-group sln-checkbox">
        <?php SLN_Form::fieldCheckboxButton($helper->getFieldName($postType, 'hide_from_account'), $discount->getMeta('hide_from_account'), __('Hide from Booking My Account', 'salon-booking-system')); ?>
    </div>
</div>


<div class="sln-clear"></div>
<?php do_action('sln.template.discount_details.metabox', $discount); ?>