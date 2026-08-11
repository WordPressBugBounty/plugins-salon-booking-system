<?php
/**
 * Copy to config.php on the store server (do not commit secrets).
 */

if (! defined('ABSPATH')) {
	exit;
}

/** Dodo API key (Test or Live). */
if (! defined('SLN_AI_DODO_API_KEY')) {
	define('SLN_AI_DODO_API_KEY', 'REPLACE_ME');
}

/** Dodo webhook signing secret. */
if (! defined('SLN_AI_DODO_WEBHOOK_SECRET')) {
	define('SLN_AI_DODO_WEBHOOK_SECRET', 'REPLACE_ME');
}

/** test_mode | live_mode */
if (! defined('SLN_AI_DODO_ENV')) {
	define('SLN_AI_DODO_ENV', 'test_mode');
}

/** One product + credit entitlement (Option A packs via checkout override). */
if (! defined('SLN_AI_DODO_PRODUCT_ID')) {
	define('SLN_AI_DODO_PRODUCT_ID', 'pdt_0Nl55oQx6bkFjvwc0qqs8');
}
if (! defined('SLN_AI_DODO_CREDIT_ID')) {
	define('SLN_AI_DODO_CREDIT_ID', 'cde_0Nl55oQxNO8qpo5ahLpF4');
}

/**
 * Optional: enable “Pay what you want” on the Dodo product so pack prices differ.
 * amount is in the smallest currency unit (EUR cents).
 */
if (! defined('SLN_AI_DODO_USE_AMOUNT_OVERRIDE')) {
	define('SLN_AI_DODO_USE_AMOUNT_OVERRIDE', true);
}

/**
 * LLM used by the store /chat handler (merchants never set these on their WP sites).
 * Copy to config.php on salonbookingsystem.com and set the real OpenRouter key.
 */
if (! defined('SLN_AI_LLM_PROVIDER')) {
	define('SLN_AI_LLM_PROVIDER', 'openrouter');
}
if (! defined('SLN_AI_LLM_MODEL')) {
	define('SLN_AI_LLM_MODEL', 'openai/gpt-4o-mini');
}
if (! defined('SLN_AI_LLM_BASE')) {
	define('SLN_AI_LLM_BASE', 'https://openrouter.ai/api/v1');
}
if (! defined('SLN_AI_LLM_API_KEY')) {
	define('SLN_AI_LLM_API_KEY', 'REPLACE_ME_OPENROUTER_KEY');
}
