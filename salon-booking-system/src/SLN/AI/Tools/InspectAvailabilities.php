<?php

/**
 * Read-only: salon / shop hours plus assistant and service custom rules.
 */
class SLN_AI_Tools_InspectAvailabilities extends SLN_AI_Tools_Abstract
{
	public function getName()
	{
		return 'inspect_availabilities';
	}

	public function getTier()
	{
		return 'guidance';
	}

	/**
	 * @param array $arguments
	 * @return array|WP_Error
	 */
	public function preview(array $arguments)
	{
		$bound = SLN_AI_Multishop::bindShop($arguments, false);
		if (is_wp_error($bound)) {
			return $bound;
		}
		$shop = ( ! empty($bound['ok']) && isset($bound['shop']) ) ? $bound['shop'] : null;

		$current = SLN_AI_Multishop::getScopedSetting($shop, 'availabilities');
		if (! is_array($current)) {
			$current = array();
		}

		$hours = new SLN_AI_Tools_SetAvailabilities($this->plugin);
		$scope = SLN_AI_Multishop::scopeLabel($shop);
		$shopArgs = SLN_AI_Multishop::argsFromShop($shop);

		$proposed  = null;
		$parseNote = '';
		$hasRules  = ! empty($arguments['rules']) && is_array($arguments['rules']);
		if ($hasRules) {
			$proposed = $hours->resolveProposed($arguments, $current);
			if (is_wp_error($proposed)) {
				$parseNote = $proposed->get_error_message();
				$proposed  = null;
				$hasRules  = false;
			}
		}

		$against     = $proposed ? $proposed : $current;
		$mode        = $proposed ? 'desired' : 'current';
		$salonIssues = array();
		$analysis    = SLN_AI_AvailabilityCascade::analyze($this->plugin, $against);
		$writes   = SLN_AI_AvailabilityCascade::alignmentWrites($analysis);

		$lines   = array();
		if ($scope !== '') {
			$lines[] = $scope;
			$lines[] = '';
		}
		if ($parseNote !== '') {
			$lines[] = sprintf(
				/* translators: %s: validation error */
				__('Could not use the desired timetable (%s). Showing current hours instead.', 'salon-booking-system'),
				$parseNote
			);
			$lines[] = '';
		}
		$lines[] = __('Current salon / shop opening hours:', 'salon-booking-system');
		$lines[] = $hours->formatAvailabilitiesSummary($current);
		if ($proposed) {
			$lines[] = '';
			$lines[] = __('Desired opening hours (what you asked to check against):', 'salon-booking-system');
			$lines[] = $hours->formatAvailabilitiesSummary($proposed);
			$salonIssues = SLN_AI_AvailabilityCascade::diffIssues(
				SLN_AI_AvailabilityCascade::weekMap($current),
				SLN_AI_AvailabilityCascade::weekMap($proposed)
			);
			$lines[] = '';
			if (! $salonIssues) {
				$lines[] = __('Salon hours already match the desired timetable.', 'salon-booking-system');
			} else {
				$lines[] = __('Salon hours do not match the desired timetable yet:', 'salon-booking-system');
				$lines[] = '  ' . SLN_AI_AvailabilityCascade::formatIssues($salonIssues);
			}
		}
		$lines[] = '';
		$lines[] = SLN_AI_AvailabilityCascade::formatAnalysisSummary($analysis, $mode);
		$lines[] = '';
		if ($proposed) {
			$lines[] = __(
				'This is a check only — nothing was saved. Entities without custom rules would inherit the desired salon hours. Custom rules listed above would still block those slots until they are aligned.',
				'salon-booking-system'
			);
			if ($writes['assistants'] || $writes['services'] || $salonIssues) {
				$lines[] = '';
				$lines[] = __(
					'If you want this timetable applied, ask me to change the opening hours — I will preview salon hours plus the assistant/service alignments.',
					'salon-booking-system'
				);
			}
		} else {
			$lines[] = __(
				'Entities without custom rules inherit salon hours automatically. Custom rules that do not cover salon hours (or stay open when the salon is closed) will block those slots until they are aligned.',
				'salon-booking-system'
			);
		}
		$lines[] = '';
		$lines[] = '[' . __('Open Booking rules', 'salon-booking-system') . '](' . admin_url('admin.php?page=salon-settings&tab=booking') . ')';
		$lines[] = '[' . __('Open Assistants', 'salon-booking-system') . '](' . admin_url('edit.php?post_type=' . SLN_Plugin::POST_TYPE_ATTENDANT) . ')';
		$lines[] = '[' . __('Open Services', 'salon-booking-system') . '](' . admin_url('edit.php?post_type=' . SLN_Plugin::POST_TYPE_SERVICE) . ')';

		$outArgs = $shopArgs;
		if ($hasRules) {
			$outArgs['rules']                = $arguments['rules'];
			$outArgs['closed_days']          = isset($arguments['closed_days']) ? $arguments['closed_days'] : array();
			$outArgs['preserve_unmentioned'] = ! empty($arguments['preserve_unmentioned']);
		}

		return array(
			'ok'        => true,
			'guidance'  => true,
			'summary'   => trim(implode("\n", $lines)),
			'tool'      => $this->getName(),
			'tier'      => 'guidance',
			'arguments' => $outArgs,
			'analysis'  => $analysis,
			'proposed'  => $proposed,
		);
	}

	public function apply(array $arguments)
	{
		return new WP_Error(
			'sln_ai_guidance_only',
			__('inspect_availabilities is read-only guidance; nothing to apply.', 'salon-booking-system')
		);
	}

	public function restore($previous)
	{
		return new WP_Error(
			'sln_ai_no_undo',
			__('Availability inspection cannot be undone.', 'salon-booking-system')
		);
	}
}
