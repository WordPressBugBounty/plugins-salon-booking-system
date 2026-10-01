<?php

/**
 * Guidance-only: hand the conversation to human support. Builds a prefilled
 * Help Scout Beacon message the merchant reviews and sends — nothing is sent here.
 */
class SLN_AI_Tools_ContactSupport extends SLN_AI_Tools_Abstract
{
	const SUPPORT_EMAIL = 'support@salonbookingsystem.com';
	const FORUM_URL     = 'https://wordpress.org/support/plugin/salon-booking-system/';
	const SUBJECT_MAX   = 150;
	const TEXT_MAX      = 4000;

	public function getName()
	{
		return 'contact_support';
	}

	public function getTier()
	{
		return 'guidance';
	}

	/**
	 * @param array $arguments
	 * @return array
	 */
	public function preview(array $arguments)
	{
		$userMessage = isset($arguments['_user_message']) ? (string) $arguments['_user_message'] : '';

		$subject = isset($arguments['subject']) ? sanitize_text_field((string) $arguments['subject']) : '';
		$problem = isset($arguments['summary']) ? sanitize_textarea_field((string) $arguments['summary']) : '';
		$tried   = isset($arguments['steps_tried']) ? sanitize_textarea_field((string) $arguments['steps_tried']) : '';

		// Short hand-off requests are hard to classify; the model's summary is in the same language.
		$lang = SLN_AI_Language::detect(trim($userMessage . ' ' . $subject . ' ' . $problem));

		if ($problem === '') {
			$problem = sanitize_textarea_field($userMessage);
		}
		if ($subject === '') {
			$subject = __('Help request from AI Setup', 'salon-booking-system');
		}
		$subject = self::cut($subject, self::SUBJECT_MAX);

		$text = $problem;
		if ($tried !== '') {
			$text .= "\n\n" . __('Already tried:', 'salon-booking-system') . "\n" . $tried;
		}
		$text  = self::cut($text, self::TEXT_MAX - 400);
		$text .= "\n\n---\n" . $this->environment();

		$isPro = SLN_AI_Edition::isPro();
		$user  = function_exists('wp_get_current_user') ? wp_get_current_user() : null;

		$support = array(
			'subject'        => $subject,
			'text'           => $text,
			'name'           => $user && ! empty($user->display_name) ? (string) $user->display_name : '',
			'email'          => $user && ! empty($user->user_email) ? (string) $user->user_email : '',
			'label'          => SLN_AI_Language::phrase($lang, 'support_button', __('Contact support', 'salon-booking-system')),
			'fallback_label' => $isPro
				? SLN_AI_Language::phrase($lang, 'support_email', __('Or send it by email', 'salon-booking-system'))
				: SLN_AI_Language::phrase($lang, 'support_forum', __('Or ask on the WordPress.org support forum', 'salon-booking-system')),
			'fallback'       => $isPro ? 'email' : 'forum',
			'fallback_url'   => $isPro
				? 'mailto:' . self::SUPPORT_EMAIL . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($text)
				: self::FORUM_URL,
		);

		return array(
			'ok'         => true,
			'guidance'   => true,
			'summary'    => SLN_AI_Language::phrase(
				$lang,
				'support_ready',
				__('I prepared a message for our support team with a summary of the problem and your site details. Click “Contact support” below to review it and send it — nothing is sent until you do.', 'salon-booking-system')
			),
			'arguments'  => array('subject' => $subject),
			'tool'       => $this->getName(),
			'tier'       => 'guidance',
			'support'    => $support,
			'model_data' => array(
				'status'   => 'button_shown',
				'note'     => 'Nothing has been sent. Tell the merchant to click "Contact support" below the reply, review the prefilled message and send it. Never claim the message was sent.',
				'channel'  => $isPro ? 'help_scout (fallback: email ' . self::SUPPORT_EMAIL . ')' : 'help_scout (fallback: WordPress.org forum)',
				'subject'  => $subject,
			),
		);
	}

	/**
	 * @param array $arguments
	 * @return array
	 */
	public function apply(array $arguments)
	{
		$preview = $this->preview($arguments);

		return array(
			'ok'       => true,
			'before'   => array(),
			'after'    => array(),
			'message'  => $preview['summary'],
			'guidance' => true,
		);
	}

	/**
	 * @param mixed $previous
	 * @return WP_Error
	 */
	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Support requests cannot be undone.', 'salon-booking-system')
		);
	}

	/**
	 * @return string
	 */
	private function environment()
	{
		global $wp_version;

		$lines = array(
			'Site: ' . home_url('/'),
			'Plugin: ' . ( defined('SLN_VERSION') ? SLN_VERSION : '?' ) . ' (' . SLN_AI_Edition::key() . ')',
			'WordPress: ' . ( ! empty($wp_version) ? $wp_version : '?' ),
			'PHP: ' . PHP_VERSION,
		);
		if (class_exists('SLN_AI_Multishop') && SLN_AI_Multishop::isActive()) {
			$lines[] = 'Multi-shop: active';
		}
		$lines[] = 'Sent from: AI Setup assistant';

		return implode("\n", $lines);
	}

	/**
	 * @param string $text
	 * @param int    $max
	 * @return string
	 */
	private static function cut($text, $max)
	{
		if (function_exists('mb_strlen') && mb_strlen($text) > $max) {
			return rtrim(mb_substr($text, 0, $max - 1)) . '…';
		}
		if (strlen($text) > $max) {
			return rtrim(substr($text, 0, $max - 1)) . '…';
		}

		return $text;
	}
}
