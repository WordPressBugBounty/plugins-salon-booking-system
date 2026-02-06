<?php

class SLN_Enum_BookingOrigin extends SLN_Enum_AbstractEnum
{
	const ORIGIN_MOBILE = 'Web app';
	const ORIGIN_ADMIN = 'Back-end';
	const ORIGIN_DIRECT = 'Front-end';

	/**
	 * Get display label for origin (converts old labels to new ones)
	 * 
	 * @param string $origin The origin value from database
	 * @return string The display label
	 */
	public static function getLabel($origin)
	{
		// Map old labels to new labels for backward compatibility
		$labelMap = array(
			'Mobile App' => 'Web app',
			'Web admin' => 'Back-end',
			'Direct' => 'Front-end',
			// Also map the new values to themselves
			'Web app' => 'Web app',
			'Back-end' => 'Back-end',
			'Front-end' => 'Front-end',
		);

		return isset($labelMap[$origin]) ? $labelMap[$origin] : $origin;
	}
}