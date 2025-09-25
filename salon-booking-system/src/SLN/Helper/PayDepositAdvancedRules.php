<?php

class SLN_Helper_PayDepositAdvancedRules
{
    /**
     * Calculate deposit based on whether advanced rules are enabled.
     *
     * @param float $amount The full amount to calculate the deposit from.
     * @param SLN_Settings $settings Plugin settings instance.
     * @return float The calculated deposit.
     */
    public static function getDeposit(float $amount, SLN_Settings $settings): float
    {
        if ($settings->isPaymentDepositAdvancedRules()) {
            return self::getDepositWithFee(self::getAdvancedDeposit($amount, $settings));
        } else {
            return self::getDepositWithFee(self::getSimpleDeposit($amount, $settings));
        }
    }

    /**
     * Calculate the deposit amount including transaction fees.
     *
     * @param float $amount The base deposit amount before fees.
     * @return float The total deposit amount including applicable transaction fees.
     */
    public static function getDepositWithFee(float $amount): float
    {
        $plugin		      = SLN_Plugin::getInstance();

        $fee = SLN_Helper_TransactionFee::getFee($amount);
        $transactionFeeType   = $plugin->getSettings()->get('pay_transaction_fee_type');

        if ($transactionFeeType === 'fixed') {
            return $amount + $fee;
        }
        return $amount;
    }

    /**
     * Calculate deposit using advanced rule logic.
     *
     * @param float $amount The full amount to calculate the deposit from.
     * @param SLN_Settings $settings Plugin settings instance.
     * @return float The calculated deposit.
     */
    public static function getAdvancedDeposit(float $amount, SLN_Settings $settings): float
    {
        $deposit = self::getSimpleDeposit($amount, $settings); // fallback if no matching rule
        $active_advanced_rules = self::getActiveAdvancedRules($settings);

        foreach ($active_advanced_rules as $rule) {
            if (self::advancedRuleMatches($rule, $amount)) {
                $deposit = ($amount / 100) * $rule['deposit'];
                break; // apply only the first matching rule
            }
        }

        return $deposit;
    }

    /**
     * Calculate deposit using a simple fixed or percentage-based setting.
     *
     * @param float $amount The full amount to calculate the deposit from.
     * @param SLN_Settings $settings Plugin settings instance.
     * @return float The calculated deposit.
     */
    public static function getSimpleDeposit(float $amount, SLN_Settings $settings): float
    {
        $depositAmount = $settings->getPaymentDepositAmount();

        if ($settings->isPaymentDepositFixedAmount()) {
            $deposit = min($amount, $depositAmount);
        } else {
            $deposit = ($amount / 100) * $depositAmount;
        }

        return $deposit;
    }

    /**
     * Retrieve all active advanced rules based on date range.
     *
     * @param SLN_Settings $settings Plugin settings instance.
     * @return array[] Array of active rules.
     */
    protected static function getActiveAdvancedRules(SLN_Settings $settings): array
    {
        $advanced_rules = $settings->getPaymentDepositAdvancedRules();
        $active_advanced_rules = [];

        // Get current date based on WordPress timezone settings
        $current_date = current_time('Y-m-d');

        foreach ($advanced_rules as $advanced_rule) {
            $valid_from = $advanced_rule['valid_from'];
            $valid_to = $advanced_rule['valid_to'];

            // Skip rule if date is invalid
            if (!$valid_from || !$valid_to) {
                continue;
            }

            // Ensure all dates use Y-m-d format
            if ($current_date >= $valid_from && $current_date <= $valid_to) {
                $active_advanced_rules[] = $advanced_rule;
            }
        }

        return $active_advanced_rules;
    }

    /**
     * Check if the given amount matches the rule condition ('less', 'equal', 'more').
     *
     * @param array $rule The rule definition including 'condition' and 'amount'.
     * @param float $amount The amount to test against the rule.
     * @return bool True if the rule matches the amount, otherwise false.
     */
    protected static function advancedRuleMatches(array $rule, float $amount): bool
    {
        $rule_condition = $rule['condition'];
        $rule_amount = $rule['amount'];

        return ($rule_condition === 'less' && $amount < $rule_amount) ||
            ($rule_condition === 'equal' && $amount == $rule_amount) ||
            ($rule_condition === 'more' && $amount > $rule_amount);
    }
}
