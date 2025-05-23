<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Admin_SettingTabs_PaymentsTab extends SLN_Admin_SettingTabs_AbstractTab {
	protected $fields = array(
		'hide_prices',
		'pay_method',
		'pay_currency',
		'pay_currency_pos',
		'pay_decimal_separator',
		'pay_thousand_separator',
		'pay_paypal_email',
		'pay_paypal_test',
		'pay_cash',
		'pay_offset_enabled',
		'pay_offset',
		'pay_enabled',
		'pay_deposit',
		'pay_deposit_fixed_amount',
        'pay_deposit_advanced_rules',
        'enable_pay_deposit_advanced_rules',
		'disable_first_pending_payment_email_to_customer',
		'pay_tip_request',
		'pay_transaction_fee_amount',
		'pay_transaction_fee_type',
		'pay_minimum_order_amount',
		'create_booking_after_pay',
		'enable_booking_tax_calculation',
		'enter_tax_price',
		'tax_value',
	);

	protected function validate() {
		if (isset($this->submitted['hide_prices'])) {
			$this->settings->set('pay_enabled', '');
		}

		if(!isset($this->submitted['enter_tax_price']) && isset($this->submitted['enable_booking_tax_calculation']) && $this->submitted['enable_booking_tax_calculation']){
			$this->submitted['enter_tax_price'] = 'inclusive';
		}

        // transform dates to Y-m-d format
        if (isset($this->submitted['pay_deposit_advanced_rules'])) {
            foreach ($this->submitted['pay_deposit_advanced_rules'] as $i => $pay_deposit_advanced_rule) {
                try {
                    $valid_from = SLN_TimeFunc::evalPickedDate(sanitize_text_field($pay_deposit_advanced_rule['valid_from']));
                    $valid_to = SLN_TimeFunc::evalPickedDate(sanitize_text_field($pay_deposit_advanced_rule['valid_to']));
                    $this->submitted['pay_deposit_advanced_rules'][$i]['valid_from'] = $valid_from;
                    $this->submitted['pay_deposit_advanced_rules'][$i]['valid_to'] = $valid_to;
                } catch (Exception $e) {
                    error_log("Error parsing dates in pay_deposit_advanced_rule #$i: " . $e->getMessage());
                    $this->submitted['pay_deposit_advanced_rules'][$i]['valid_from'] = '';
                    $this->submitted['pay_deposit_advanced_rules'][$i]['valid_to'] = '';
                }
            }
        }
	}

	protected function postProcess() {
		wp_clear_scheduled_hook('sln_cancel_bookings');
		if (isset($this->submitted['pay_offset_enabled']) && $this->submitted['pay_offset_enabled']) {
			wp_schedule_event(time(), 'hourly', 'sln_cancel_bookings');
			wp_schedule_event(time() + 1800, 'hourly', 'sln_cancel_bookings');
		}
	}

	public function show() {
		include $this->plugin->getViewFile('admin/utilities/settings-sidebar');
		echo '<div class="sln-tab" id="sln-tab-' . $this->slug . '">';
		include $this->plugin->getViewFile('settings/tab_' . $this->slug . (defined("SLN_VERSION_PAY") && SLN_VERSION_PAY ? '_pro' : ''));
		do_action('sln.view.settings.' . $this->slug . '.additional_fields', $this);
		echo '<div class="sln-tab__curtain"></div></div>
        <div class="clearfix"></div>';
	}
}
?>