<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotValidated
namespace SLB_API_Mobile\Helper;

class RequestHelper {

	/**
	 * Get and sanitize GET parameter value
	 * 
	 * @param string $name Parameter name
	 * @param string $type Sanitization type: 'text', 'int', 'email', 'url', 'raw'
	 * @return mixed Sanitized value or null if not set
	 */
	public function getGetQueryValue($name, $type = 'text') {
		if (!isset($_GET[$name])) {
			return null;
		}
		$value = wp_unslash($_GET[$name]);
		return $this->sanitizeValue($value, $type);
	}

	/**
	 * Get and sanitize POST parameter value
	 * 
	 * @param string $name Parameter name
	 * @param string $type Sanitization type: 'text', 'int', 'email', 'url', 'raw'
	 * @return mixed Sanitized value or null if not set
	 */
	public function getPostQueryValue($name, $type = 'text') {
		if (!isset($_POST[$name])) {
			return null;
		}
		$value = wp_unslash($_POST[$name]);
		return $this->sanitizeValue($value, $type);
	}

	/**
	 * Sanitize value based on type
	 * 
	 * @param mixed $value Value to sanitize
	 * @param string $type Sanitization type
	 * @return mixed Sanitized value
	 */
	private function sanitizeValue($value, $type) {
		switch ($type) {
			case 'int':
				return intval($value);
			case 'float':
				return floatval($value);
			case 'email':
				return sanitize_email($value);
			case 'url':
				return esc_url_raw($value);
			case 'textarea':
				return sanitize_textarea_field($value);
			case 'raw':
				// Only use for values that will be further sanitized/validated
				return $value;
			case 'text':
			default:
				return sanitize_text_field($value);
		}
	}

	public function getAccessToken() {
		return $this->getHeaderValue('Access-Token');
	}

	public function getHeaderValue($name) {
		$headers = $this->getAllHeaders();

		return isset($headers[$name]) ? $headers[$name] : null;
	}

	/**
	 * Get and sanitize request body (JSON)
	 * 
	 * @return array|null Decoded and sanitized JSON data or null on failure
	 */
	public function getRequestBody() {
		$raw = file_get_contents('php://input');
		if (empty($raw)) {
			return null;
		}
		
		$args = json_decode($raw, true);
		
		// Validate JSON was parsed successfully
		if (json_last_error() !== JSON_ERROR_NONE) {
			return null;
		}
		
		// Recursively sanitize array values
		if (is_array($args)) {
			$args = $this->sanitizeArray($args);
		}
		
		return $args;
	}

	/**
	 * Recursively sanitize array values
	 * 
	 * @param array $data Data to sanitize
	 * @return array Sanitized data
	 */
	private function sanitizeArray($data) {
		$sanitized = array();
		foreach ($data as $key => $value) {
			$sanitized_key = sanitize_key($key);
			if (is_array($value)) {
				$sanitized[$sanitized_key] = $this->sanitizeArray($value);
			} elseif (is_string($value)) {
				$sanitized[$sanitized_key] = sanitize_text_field($value);
			} elseif (is_numeric($value)) {
				$sanitized[$sanitized_key] = $value;
			} else {
				$sanitized[$sanitized_key] = $value;
			}
		}
		return $sanitized;
	}

	public function getRequestMethod() {
		return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
	}

	public function getRequestScheme() {
		return isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
	}

	public function getHttpHost() {
		return isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
	}

	public function getRequestUri() {
		return isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
	}

	private function getAllHeaders()
	{
		$headers = array();
		foreach($_SERVER as $name => $value)
		{
			if(substr($name, 0, 5) == 'HTTP_')
			{
				$header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
				$headers[$header_name] = sanitize_text_field(wp_unslash($value));
			}
		}
		return $headers;
	}
}