<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
$helper->showNonce($postType);
?>
<div class="sln-box sln-box--main sln-box--haspanel sln-box--haspanel--open">
    <div class="collapse in sln-box__panelcollapse">
        <div class="row">
<!-- default settings -->
            <div class="col-xs-12 col-sm-3 col-md-3 col-lg-3 form-group sln-select">
                <label><?php esc_html_e('Units per session', 'salon-booking-system');?></label>
                <?php SLN_Form::fieldNumeric($helper->getFieldName($postType, 'unit'), $resource->getUnitPerHour(), array('max' => 100));?>
                <p><?php esc_html_e('How many reservations for the same date/time slot ?', 'salon-booking-system');?></p>
            </div>
            <div class="col-xs-12 col-sm-4 col-md-6 col-lg-4">
                <div class="sln-switch">
                    <?php SLN_Form::fieldCheckboxSwitch($helper->getFieldName($postType, 'enabled'), $resource->getEnabled(), __('Enable', 'salon-booking-system'), __('Disable', 'salon-booking-system')) ?>
                </div>
                <p><?php esc_html_e('Use it to temporarily disable this resource', 'salon-booking-system');?></p>
            </div>
            <div class="sln-clear"></div>
        </div>
        <div class="row">
            <div class="col-xs-12 form-group sln-select sln-select--multiple sln-select--multiple--search">
                <label><?php esc_html_e('Assigned services', 'salon-booking-system');?></label>
                <div class="sln-select--inwrapper has_no_choices">
                <?php
                    /** @var SLN_Wrapper_Service[] $services */
                    $services = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_SERVICE)->getAll();
                    $items = array();
                    foreach ($services as $s) {
                        $items[$s->getId()] = $s->getName();
                    }
                    SLN_Form::fieldSelect(
                        $helper->getFieldName($postType, 'services[]'),
                        $items,
                        (array)$resource->getMeta('services'),
                        array('attrs' => array('multiple' => true, 'placeholder' => __('select one or more services', 'salon-booking-system'), 'data-containerCssClass' => 'sln-select-wrapper-multi-search')),
                        true
                    );
                ?>
                </div>
                <p><?php esc_html_e('Select the services to be assigned to this resource', 'salon-booking-system');?></p>
            </div>
        </div>
        <div class="sln-clear"></div>
    </div>
</div>

<?php do_action('sln.template.resource.metabox', $resource);?>