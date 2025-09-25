<?php
$settings = SLN_Plugin::getInstance()->getSettings();
$meta = 'salon_settings';
$field = 'pay_deposit_advanced_rules';
$rules = $settings->getPaymentDepositAdvancedRules();
?>

<div id="sln-pay_deposit_advanced_rules"
     class="sln-box sln-box--main sln-box--haspanel <?php echo !$settings->isPaymentDepositAdvancedRules() ? 'hide' : '' ?>"
>
    <h2 class="sln-box-title sln-box__paneltitle">
        <?php esc_html_e('Advanced rules', 'salon-booking-system'); ?>
        <span class="block" style="font-size: 16px;">
            <?php esc_html_e('Set one or more conditions to define the amount of the upfront payment', 'salon-booking-system'); ?>
        </span>
    </h2>

    <div class="collapse sln-box__panelcollapse">
        <div class="ar-container">
            <div class="ar-rules">
                <?php
                $rules['__template__'] = [
                    'name' => '',
                    'condition' => 'more',
                    'amount' => 0,
                    'deposit' => 0,
                    'valid_from' => '',
                    'valid_to' => '',
                ];
                ?>
                <?php foreach ($rules as $i => $rule) { ?>
                    <?php $rule_prefix = "{$meta}[$field][$i]"; ?>
                    <div class="ar-rule<?php echo $i === '__template__' ? ' hide' : ''; ?>"
                         data-id="<?php echo esc_html($i); ?>">
                        <div class="row">
                            <div class="col-xs-12 col-lg-4">
                                <div class="ar-rule-field sln-input--simple">
                                    <label for="<?php echo esc_html(SLN_Form::makeID("{$rule_prefix}[name]")) ?>">
                                        <?php esc_html_e('Name', 'salon-booking-system') ?>
                                    </label>

                                    <?php SLN_Form::fieldText(
                                        "{$rule_prefix}[name]",
                                        $rule['name'],
                                        ['attrs' => ['data-type' => 'ar-rule-name']]
                                    ); ?>
                                </div>
                            </div>

                            <div class="col-xs-12 col-lg-4">
                                <div class="ar-rule-field sln-select<?php echo $i === '__template__' ? '-template' : ''; ?>">
                                    <label for="<?php echo esc_html(SLN_Form::makeID("{$rule_prefix}[condition]")) ?>">
                                        <?php esc_html_e('Condition (booking total amount is)', 'salon-booking-system') ?>
                                    </label>

                                    <?php
                                    SLN_Form::fieldSelect(
                                        "{$rule_prefix}[condition]",
                                        [
                                            'less' => __('less than', 'salon-booking-system'),
                                            'equal' => __('equal to', 'salon-booking-system'),
                                            'more' => __('more than', 'salon-booking-system'),
                                        ],
                                        $rule['condition'],
                                        ['attrs' => ['data-type' => 'ar-rule-condition']],
                                        true
                                    );
                                    ?>
                                </div>
                            </div>

                            <div class="col-xs-12 col-lg-2">
                                <div class="ar-rule-field sln-input--simple">
                                    <label for="<?php echo esc_html(SLN_Form::makeID("{$rule_prefix}[amount]")) ?>">
                                        <?php esc_html_e('Amount', 'salon-booking-system') ?>
                                        (<?php echo esc_html($settings->getCurrencySymbol()) ?>)
                                    </label>

                                    <?php SLN_Form::fieldText(
                                        "{$rule_prefix}[amount]",
                                        $rule['amount'],
                                        ['attrs' => ['data-type' => 'ar-rule-amount']]
                                    ); ?>
                                </div>
                            </div>

                            <div class="col-xs-12 col-lg-2">
                                <div class="ar-rule-field sln-input--simple">
                                    <label for="<?php echo esc_html(SLN_Form::makeID("{$rule_prefix}[deposit]")) ?>">
                                        <?php esc_html_e('Deposit', 'salon-booking-system') ?>
                                        (%)
                                    </label>

                                    <?php SLN_Form::fieldText(
                                        "{$rule_prefix}[deposit]",
                                        $rule['deposit'],
                                        ['attrs' => ['data-type' => 'ar-rule-deposit']]
                                    ); ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xs-12 col-lg-4">
                                <div class="ar-rule-field sln-input--simple">
                                    <label for="<?php echo esc_html(SLN_Form::makeID("{$rule_prefix}[valid_from]")) ?>">
                                        <?php esc_html_e('Valid from', 'salon-booking-system') ?>
                                    </label>

                                    <?php SLN_Form::fieldJSDate(
                                        "{$rule_prefix}[valid_from]",
                                        $rule['valid_from'],
                                        ['attrs' => ['data-type' => 'ar-rule-valid_from']]
                                    ); ?>
                                </div>
                            </div>

                            <div class="col-xs-12 col-lg-4">
                                <div class="ar-rule-field sln-input--simple">
                                    <label for="<?php echo esc_html(SLN_Form::makeID("{$rule_prefix}[valid_to]")) ?>">
                                        <?php esc_html_e('Valid to', 'salon-booking-system') ?>
                                    </label>

                                    <?php SLN_Form::fieldJSDate(
                                        "{$rule_prefix}[valid_to]",
                                        $rule['valid_to'],
                                        ['attrs' => ['data-type' => 'ar-rule-valid_to']]
                                    ); ?>
                                </div>
                            </div>

                            <div class="col-xs-12 col-lg-4">
                                <div class="ar-rule-field sln-input--simple">
                                    <label class="hidden-md">&nbsp;</label>
                                    <button type="button"
                                            class="sln-btn sln-btn--problem sln-btn--big"
                                            data-action="ar-rule-remove">
                                        <?php esc_html_e('Remove rule', 'salon-booking-system') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="ar-rule-add">
                <button type="button"
                        class="sln-btn sln-btn--main sln-btn--big"
                        data-action="ar-rule-add">
                    <?php esc_html_e('Add rule', 'salon-booking-system') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .ar-container {
        margin-bottom: 1.66rem;
    }

    .ar-rules {
        display: flex;
        flex-direction: column;
        gap: 30px;
        margin-bottom: 30px;
    }

    .ar-rule {
        padding: 30px 30px 0 30px;
        border: 1px solid rgb(199, 223, 243);
    }
</style>
