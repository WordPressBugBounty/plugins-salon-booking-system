<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin           $plugin
 * @var SLN_Wrapper_Customer $customer
 */

$msg = $plugin->getSettings()->get('follow_up_message') . "\r\n" . home_url() . '?sln_customer_login=' . $customer->getHash();
$msg = str_replace(array('[NAME]', '[SALON NAME]'), array($customer->getName(), $plugin->getSettings()->getSalonName()), $msg);
echo $plugin->getSettings()->get('sms_ascii_mode') ? remove_accents($msg) : $msg;