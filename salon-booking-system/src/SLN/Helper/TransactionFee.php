<?php

class SLN_Helper_TransactionFee {

    public static function getFee($amount) {

	$plugin		      = SLN_Plugin::getInstance();
	$transactionFeeAmount = $plugin->getSettings()->get('pay_transaction_fee_amount');
	$transactionFeeType   = $plugin->getSettings()->get('pay_transaction_fee_type');

	$fee = 0;

	if ($transactionFeeType === 'fixed') {
	    $fee = SLN_Func::filter($transactionFeeAmount, 'float');
	}

	if ($transactionFeeType === 'percent') {
	    $fee = $amount * SLN_Func::filter($transactionFeeAmount, 'float') / 100;
	}
    if($amount == 0){
        $fee = 0;
    }
	return round(abs($fee), 2);
    }

}