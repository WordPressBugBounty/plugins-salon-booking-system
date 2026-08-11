<?php
/**
 * Admin-only, read-only diagnostic for "AI chat falls back to the local
 * assistant" (cloud proxy unreachable) reports.
 *
 * An administrator visits, while logged in:
 *
 *     /wp-admin/?sln_ai_proxy_diag=1
 *
 * and gets a report showing which backend the plugin would pick, the
 * wp-config constants that influence it, and the live result of a real
 * request to the cloud proxy /usage endpoint — enough to tell apart
 * "mock forced by constant", "outgoing HTTP blocked" and "proxy rejected us"
 * without shell access to the server.
 *
 * Consumes no AI queries (/usage is billing-neutral).
 */
class SLN_AI_ProxyDiagnostic
{
	public function __construct()
	{
		add_action('admin_init', array($this, 'maybeRun'));
	}

	public function maybeRun()
	{
		if (! isset($_GET['sln_ai_proxy_diag'])) {
			return;
		}

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Permission denied.', 'salon-booking-system'));
		}

		$rows = $this->collect();

		header('Content-Type: text/html; charset=utf-8');
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Salon AI Proxy Diagnostic</title>';
		echo '<style>body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#222;}
			table{border-collapse:collapse;min-width:640px;}
			td,th{border:1px solid #ccc;padding:6px 10px;text-align:left;vertical-align:top;}
			th{background:#f2f2f2;}
			code{background:#f6f6f6;padding:1px 4px;}
			.ok{color:#1a7f37;font-weight:bold;}
			.ko{color:#b32d2e;font-weight:bold;}</style></head><body>';
		echo '<h1>Salon AI Proxy Diagnostic</h1><table>';
		foreach ($rows as $label => $value) {
			echo '<tr><th>' . esc_html($label) . '</th><td>' . wp_kses_post($value) . '</td></tr>';
		}
		echo '</table></body></html>';
		exit;
	}

	/**
	 * @return array<string,string>
	 */
	private function collect()
	{
		$proxy = new SLN_AI_ProxyClient();
		$rows  = array();

		$rows['Plugin version'] = defined('SLN_VERSION') ? SLN_VERSION : 'n/a';
		$rows['Backend that would be used'] = '<code>' . esc_html($proxy->getBackend()) . '</code>';
		$rows['SLN_AI_PROXY_MOCK'] = defined('SLN_AI_PROXY_MOCK')
			? '<span class="ko">defined: ' . esc_html(var_export(SLN_AI_PROXY_MOCK, true)) . '</span> — forces the local assistant when true'
			: 'not defined';
		$rows['SLN_AI_PROXY_URL'] = defined('SLN_AI_PROXY_URL')
			? '<span class="ko">defined: ' . esc_html((string) SLN_AI_PROXY_URL) . '</span>'
			: 'not defined (default)';
		$rows['Proxy base URL in use'] = '<code>' . esc_html($proxy->getBaseUrl()) . '</code>';
		$rows['SLN_AI_LLM_API_KEY (staging direct LLM)'] = defined('SLN_AI_LLM_API_KEY') && SLN_AI_LLM_API_KEY
			? '<span class="ko">defined (value hidden)</span> — direct LLM is preferred as fallback'
			: 'not defined';
		$rows['WP_HTTP_BLOCK_EXTERNAL'] = defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL
			? '<span class="ko">TRUE — WordPress blocks outgoing HTTP</span>'
			: 'not set';
		$rows['WP_ACCESSIBLE_HOSTS'] = defined('WP_ACCESSIBLE_HOSTS')
			? '<code>' . esc_html((string) WP_ACCESSIBLE_HOSTS) . '</code>'
			: 'not set';

		$token = get_option('sln_ai_site_token', '');
		$rows['Site token'] = (is_string($token) && strlen($token) >= 32)
			? '<span class="ok">present (' . (int) strlen($token) . ' chars)</span>'
			: '<span class="ko">missing — will be generated on first proxy call</span>';

		// Live call to the proxy /usage endpoint with the real auth payload.
		$started = microtime(true);
		$result  = $proxy->fetchUsage();
		$elapsed = round((microtime(true) - $started) * 1000);

		if (is_wp_error($result)) {
			$rows['Live /usage call'] = '<span class="ko">FAILED after ' . $elapsed . 'ms</span> — <code>'
				. esc_html($result->get_error_code()) . '</code>: '
				. esc_html($result->get_error_message());
		} else {
			$rows['Live /usage call'] = '<span class="ok">OK in ' . $elapsed . 'ms</span> — edition: <code>'
				. esc_html(isset($result['edition']) ? (string) $result['edition'] : '?')
				. '</code>, included: <code>'
				. esc_html(isset($result['included_used']) ? (string) $result['included_used'] : '?') . '/'
				. esc_html(isset($result['included_limit']) ? (string) $result['included_limit'] : '?')
				. '</code>, total remaining: <code>'
				. esc_html(isset($result['total_remaining']) ? (string) $result['total_remaining'] : '?') . '</code>';
		}

		// Raw reachability of the store host, bypassing the plugin logic.
		$raw = wp_remote_get('https://www.salonbookingsystem.com/wp-json/', array('timeout' => 8));
		$rows['Raw HTTPS to salonbookingsystem.com'] = is_wp_error($raw)
			? '<span class="ko">FAILED</span> — ' . esc_html($raw->get_error_message())
			: '<span class="ok">HTTP ' . (int) wp_remote_retrieve_response_code($raw) . '</span>';

		// What IP does THIS server resolve for the store host? A private/local IP
		// (10.x, 172.16-31.x, 192.168.x, 127.x) means a hosts-file or internal-DNS
		// override is hijacking the domain.
		$ip = gethostbyname('www.salonbookingsystem.com');
		$isPrivate = ($ip === 'www.salonbookingsystem.com')
			? null
			: (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
		if ($isPrivate === null) {
			$rows['DNS resolution of salonbookingsystem.com'] = '<span class="ko">FAILED — hostname does not resolve</span>';
		} else {
			$rows['DNS resolution of salonbookingsystem.com'] = '<code>' . esc_html($ip) . '</code>'
				. ($isPrivate ? ' <span class="ko">(PRIVATE/LOCAL IP — hosts file or internal DNS override)</span>' : ' (public IP)');
		}

		// Neutral host: distinguishes "all outbound HTTPS blocked" from
		// "only the store host is unreachable".
		$neutral = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/', array('timeout' => 8));
		$rows['Raw HTTPS to api.wordpress.org (neutral host)'] = is_wp_error($neutral)
			? '<span class="ko">FAILED</span> — ' . esc_html($neutral->get_error_message())
			: '<span class="ok">HTTP ' . (int) wp_remote_retrieve_response_code($neutral) . '</span>';

		return $rows;
	}
}
