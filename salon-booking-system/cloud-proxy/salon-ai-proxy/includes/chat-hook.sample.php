<?php
/**
 * SUPERSEDED — the /chat endpoint is now implemented by the plugin itself in
 * includes/class-chat.php (SLN_AI_Proxy_Chat). No manual hook is needed:
 * deploy the salon-ai-proxy folder, activate the plugin, and fill config.php
 * (SLN_AI_LLM_API_KEY etc. — see config.sample.php).
 *
 * The handler verifies the per-site token (SLN_AI_Proxy_REST::verifyAuth),
 * consumes one ledger query, calls the configured OpenAI-compatible LLM and
 * returns {message, tool_call, draft, usage}. Failed LLM calls are refunded.
 */
