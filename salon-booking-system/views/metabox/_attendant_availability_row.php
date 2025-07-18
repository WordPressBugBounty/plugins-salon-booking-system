<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$alert = __(
    'This rule represents your open and close days, your open and close shift. Set carefully as it will affect your reservation system.',
    'salon-booking-system'
);

if (empty($row) || !isset($row['from'])) {
    $row = array('from' => array('9:00', '14:00'), 'to' => array('13:00', '19:00'));
}
if (empty($rulenumber)) {
    $rulenumber = 'New';
}
$dateFrom = new SLN_DateTime(isset($row['from_date']) ? $row['from_date'] : null);
$dateTo = new SLN_DateTime(isset($row['to_date']) ? $row['to_date'] : null);
$row['always'] = isset($row['always']) ? ($row['always'] ? true : false) : true;
?>
<div class="col-xs-12 sln-box--sub sln-booking-rule" data-n="<?php echo esc_attr($rulenumber) ?>">
    <h2 class="sln-box-title"><?php esc_html_e('Rule', 'salon-booking-system'); ?> <strong><?php echo esc_attr($rulenumber); ?></strong> <span class="block"><?php echo esc_attr($alert) ?></span>
    </h2>
    <div class="sln-title-wrapper">
        <h3 class="sln-box-title--sec "><?php esc_html_e('Available days', 'salon-booking-system'); ?>
            <span class="block"><?php esc_html_e('Available days checked and green.', 'salon-booking-system'); ?></span>
        </h3>
        <?php if ($show_specific_dates): ?>
            <div class="sln-select-specific-dates sln-profeature <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-variable-price--disabled sln-profeature--disabled sln-profeature__tooltip-wrapper' : '' ?>">
                <?php echo $plugin->loadView(
                    'metabox/_pro_feature_tooltip',
                    array(
                        // 'cta_url' => 'https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO',
                        'additional_classes' => 'sln-profeature--switch--bare',
                        'trigger' => 'sln-select-specific-dates',
                    )
                ); ?>
                <?php $attrs = !defined("SLN_VERSION_PAY") ? array('disabled' => 'disabled') : array() ?>
                <div class="sln-switch sln-switch--bare <?php echo !defined("SLN_VERSION_PAY") ? 'sln-disabled' : '' ?>">
                    <?php SLN_Form::fieldCheckboxSwitch(
                        $prefix . '[select_specific_dates]',
                        isset($row['select_specific_dates']) ? $row['select_specific_dates'] : false,
                        __('Select specific dates', 'salon-booking-system'),
                        __('Select specific dates', 'salon-booking-system'),
                        array('attrs' => $attrs)
                    ); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="sln-checkbutton-group <?php echo $show_specific_dates && isset($row['select_specific_dates']) && $row['select_specific_dates'] ? 'hide' : '' ?>">
        <?php foreach (SLN_Func::getDays() as $k => $day): ?>
            <div class="sln-checkbutton">
                <?php SLN_Form::fieldCheckboxButton(
                    $prefix . "[days][{$k}]",
                    (isset($row['days'][$k]) ? 1 : null),
                    $label = substr($day, 0, 3)
                ) ?>
            </div>
        <?php endforeach ?>
        <div class="clearfix"></div>
    </div>
    <?php if ($show_specific_dates): ?>
        <div class="sln-select-specific-dates-calendar <?php echo isset($row['select_specific_dates']) && $row['select_specific_dates'] ? '' : 'hide' ?>">
            <div class="sln-specific-dates-wrapper">
                <div class="sln-datepicker-section">
                    <?php SLN_Form::fieldJSDate($prefix . '[specific_dates]', '', array('inline' => true)) ?>
                    <?php SLN_Form::fieldText($prefix . '[specific_dates]', isset($row['specific_dates']) ? $row['specific_dates'] : '', array('attrs' => array_merge(array('hidden' => ''), $attrs))) ?>

                    <div class="sln-selected-dates-panel">
                        <div class="sln-selected-dates-header">
                            <h4><?php esc_html_e('Selected Dates', 'salon-booking-system'); ?>:</h4>
                            <span class="sln-selected-count">0</span>
                        </div>
                        <div class="sln-selected-dates-list">
                            <div class="sln-selected-dates-empty"><?php esc_html_e('No dates selected', 'salon-booking-system'); ?></div>
                        </div>
                        <button type="button" class="sln-clear-all-dates sln-btn sln-btn--light sln-btn--small">
                            <?php esc_html_e('Clear All Dates', 'salon-booking-system'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    <?php endif; ?>
    <div class="row">
        <div class="col-xs-12">
            <div class="row">
                <div class="col-xs-12 col-md-6 sln-slider-wrapper sln-slider-wrapper-first-shift">
                    <div class="sln-slider ">
                        <h2 class="sln-box-title"><?php esc_html_e('First shift', 'salon-booking-system'); ?></h2>
                        <div class="sln-slider__inner">
                            <div class="col col-time">
                                <h2 class="sln-slider--title col-time-title"><em><strong class="slider-time-from">9:00</strong>
                                        to <strong class="slider-time-to">16:00</strong></em></h2>
                                <input type="text" name="<?php echo esc_attr($prefix) ?>[from][0]" id=""
                                    value="<?php echo $row['from'][0] ? esc_attr($row['from'][0]) : "9:00" ?>"
                                    class="slider-time-input-from hidden">
                                <input type="text" name="<?php echo esc_attr($prefix) ?>[to][0]" id=""
                                    value="<?php echo $row['to'][0] ? esc_attr($row['to'][0]) : "13:00" ?>"
                                    class="slider-time-input-to hidden">
                            </div>
                            <div class="sliders_step1 col col-slider">
                                <div class="slider-range"></div>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12 col-md-6 sln-slider-wrapper sln-slider-wrapper-second-shift" <?php if (isset($row['disable_second_shift']) && $row['disable_second_shift']) {
                                                                                                        echo 'hidden';
                                                                                                    } ?>>
                    <div class="sln-slider sln-second-shift">
                        <h2 class="sln-box-title"><?php esc_html_e('Second shift', 'salon-booking-system'); ?></h2>
                        <div class="sln-slider__inner">
                            <div class="col col-time">
                                <h2 class="sln-slider--title col-time-title" <?php if (isset($row['disable_second_shift']) && $row['disable_second_shift']) {
                                                                                    echo 'hidden';
                                                                                } ?>>
                                    <em><strong class="slider-time-from">9:00</strong> to <strong class="slider-time-to">16:00</strong></em>
                                </h2>
                                <input type="text" name="<?php echo esc_attr($prefix) ?>[from][1]" id=""
                                    value="<?php echo isset($row['from'][1]) && $row['from'][1] ? esc_attr($row['from'][1]) : "14:00" ?>"
                                    class="slider-time-input-from hidden" <?php if (isset($row['disable_second_shift']) && $row['disable_second_shift']) {
                                                                                echo 'disabled="disabled"';
                                                                            } ?>>
                                <input type="text" name="<?php echo esc_attr($prefix) ?>[to][1]" id=""
                                    value="<?php echo isset($row['to'][1]) && $row['to'][1] ? esc_attr($row['to'][1]) : "19:00" ?>"
                                    class="slider-time-input-to hidden" <?php if (isset($row['disable_second_shift']) && $row['disable_second_shift']) {
                                                                            echo 'disabled="disabled"';
                                                                        } ?>>
                            </div>
                            <div class="sliders_step1 col col-slider">
                                <div class="slider-range"></div>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                    <div class="sln-switch sln-switch--inverted sln-switch--bare sln-disable-second-shift">
                        <?php SLN_Form::fieldCheckboxSwitch(
                            $prefix . '[disable_second_shift]',
                            isset($row['disable_second_shift']) ? $row['disable_second_shift'] : false,
                            __('Shift enabled', 'salon-booking-system'),
                            __('Shift disabled', 'salon-booking-system')
                        ); ?>
                    </div>
                </div>

            </div>
        </div>
        <div class="col-xs-12 col-md-4 col-md-push-6">

        </div>
        <div class="clearfix"></div>
        <div class="row col-xs-12 sln-select-specific-dates-calendar <?php echo isset($row['select_specific_dates']) && $row['select_specific_dates'] ? '' : 'hide' ?>">
            <?php if ($show_specific_dates && $plugin->getSettings()->isResourcesEnabled()): ?>
                <div class="col-xs-12 col-md-6 form-group sln-select">
                    <label> <?php echo esc_html__('Select resource', 'salon-booking-system'); ?></label>
                    <div class="sln_attendant_day_resource closed-">
                        <select class="sln-select select2-hidden-accessible" data-palceholder="<?php esc_html_e('Select a resource', 'salon-booking-system') ?>"
                            name="<?php echo esc_attr($prefix); ?>[day_specific_resource]" id="<?php echo SLN_Form::makeID($prefix . '[day_specific_resource]'); ?>" tabindex="-1" aria-hidden="true">
                            <option value='0'><?php esc_html_e('Select a resource'); ?></option>
                            <?php
                            foreach ($plugin->getRepository(SLN_Plugin::POST_TYPE_RESOURCE)->getAll() as $resource): ?>
                                <option class="red" value="<?php echo esc_attr($resource->getId()) ?>"
                                    <?php echo (isset($row['day_specific_resource']) && $row['day_specific_resource'] == $resource->getId()) ? 'selected=selected' : ''; ?>><?php echo esc_attr($resource->getTitle()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif;
            if ($show_specific_dates): ?>
                <div class="col-xs-12 col-md-6 form-group sln-select">
                    <label> <?php echo esc_html__('Select service', 'salon-booking-system'); ?></label>
                    <div class="sln_attendant_day_service closed-">
                        <select id="<?php echo SLN_Form::makeID($prefix . '[day_specific_service]'); ?>"
                            class="sln-select select2-hidden-accessible"
                            data-palceholder="<?php esc_html_e('Select a service', 'salon-booking-system') ?>"
                            name="<?php echo esc_attr($prefix); ?>[day_specific_service]" tabindex="-1" aria-hidden="true">
                            <option value='0' class="red"><?php esc_html_e('Select a service'); ?></option>
                            <?php
                            $services = array();
                            $services = $plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE)->getAll();
                            foreach ($services as $service):
                                $resources = $service->getResources();
                                $has_resource = false; ?>
                                <option class="red" value="<?php echo esc_attr($service->getId()) ?>"
                                    data-resources="<?php foreach ($resources as $resource) {
                                                        echo esc_attr($resource->getId()) . ',';
                                                        $has_resource |= isset($row['day_specific_resource']) && $row['day_specific_resource'] == $resource->getId();
                                                    } ?>"
                                    <?php echo !$has_resource && isset($row['day_specific_resource']) ? 'disabled="disabled"' : ''; ?>
                                    <?php echo (isset($row['day_specific_service']) && $row['day_specific_service'] == $service->getId()) ? 'selected=selected' : ''; ?>><?php echo esc_attr($service->getName()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="sln-always-valid-section <?php echo $show_specific_dates && isset($row['select_specific_dates']) && $row['select_specific_dates'] ? 'hide' : '' ?>">
            <div class="col-xs-12 col-md-4 form-group sln-switch">
                <?php SLN_Form::fieldCheckboxSwitch(
                    $prefix . '[always]',
                    $row['always'],
                    __('This rule is always Enabled', 'salon-booking-system'),
                    __('Not always Enabled', 'salon-booking-system'),
                    array('attrs' => array(
                        'data-unhide' => '#' . SLN_Form::makeID($prefix . '[always]' . 'unhide'),
                    ))
                ); ?>
            </div>
            <div id="<?php echo SLN_Form::makeID($prefix . '[always]' . 'unhide') ?>" class="col-xs-12">
                <div class="row sln-box--tertiary">
                    <div class="col-xs-12">
                        <h3 class="sln-box-title--sec ">
                            <?php esc_html_e('Set a time range for this rule', 'salon-booking-system') ?>:
                        </h3>
                    </div>
                    <div class="col-xs-12 col-md-4 sln-input--simple sln-input--datepicker">
                        <label><?php esc_html_e('Apply from', 'salon-booking-system') ?></label>
                        <?php SLN_Form::fieldJSDate($prefix . "[from_date]", $dateFrom) ?>
                    </div>
                    <div class="col-xs-12 col-md-4 sln-input--simple sln-input--datepicker">
                        <label><?php esc_html_e('Until', 'salon-booking-system') ?></label>
                        <div class="sln_datepicker"><?php SLN_Form::fieldJSDate($prefix . "[to_date]", $dateTo) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div id="<?php echo SLN_Form::makeID($prefix . '[always]' . 'unhide') ?>" class="col-xs-12">
            <div class="row sln-box--tertiary">
                <div class="col-xs-12">
                    <h3 class="sln-box-title--sec ">
                        <?php esc_html_e('Set a time range for this rule', 'salon-booking-system') ?>:
                    </h3>
                </div>
                <div class="col-xs-12 col-md-4 sln-input--simple sln-input--datepicker">
                    <label><?php esc_html_e('Apply from', 'salon-booking-system') ?></label>
                    <?php SLN_Form::fieldJSDate($prefix . "[from_date]", $dateFrom) ?>
                </div>
                <div class="col-xs-12 col-md-4 sln-input--simple sln-input--datepicker">
                    <label><?php esc_html_e('Until', 'salon-booking-system') ?></label>
                    <div class="sln_datepicker"><?php SLN_Form::fieldJSDate($prefix . "[to_date]", $dateTo) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xs-12 sln-booking-rules__actions">
            <button class="sln-btn sln-btn--problem sln-btn--big sln-btn--icon sln-icon--trash"
                data-collection="remove"><?php echo esc_html__('Remove this rule', 'salon-booking-system') ?></button>
        </div>
        <div class="clearfix"></div>
    </div>
</div>
