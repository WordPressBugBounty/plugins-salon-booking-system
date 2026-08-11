<?php

/**
 * Plugin changelog awareness for AI Setup (from readme.txt).
 *
 * Compact highlights stay in every prompt; fuller history via explain_setting topic=changelog.
 */
class SLN_AI_Changelog
{
	const README_REL      = '/readme.txt';
	const CACHE_KEY       = 'sln_ai_changelog_parsed_v1';
	const PUBLIC_URL      = 'https://www.salonbookingsystem.com/category/changelog/';
	const ALWAYS_VERSIONS = 3;
	const ALWAYS_BULLETS  = 4;
	const DETAIL_VERSIONS = 8;

	/**
	 * Compact always-on line(s) for the LLM system prompt.
	 *
	 * @return string
	 */
	public static function instructionsSnippet()
	{
		return 'Changelog: use recent_changelog in context; detail → explain_setting topic=changelog; never invent features.';
	}

	/**
	 * Structured fields for ContextPack.
	 *
	 * @return array
	 */
	public static function contextFields()
	{
		$highlights = self::highlightBullets(self::ALWAYS_VERSIONS, self::ALWAYS_BULLETS);
		$releases   = self::recentReleases(self::ALWAYS_VERSIONS);

		return array(
			'plugin_version'     => self::installedVersion(),
			'changelog_url'      => self::PUBLIC_URL,
			'releases'           => $releases,
			'highlight_bullets'  => $highlights,
		);
	}

	/**
	 * Text block for ContextPack::formatForPrompt.
	 *
	 * @param array $context Optional prebuilt contextFields(); rebuilt if missing.
	 * @return string
	 */
	public static function formatForPrompt(array $context = array())
	{
		$version = isset($context['plugin_version'])
			? (string) $context['plugin_version']
			: self::installedVersion();
		$highlights = isset($context['highlight_bullets']) && is_array($context['highlight_bullets'])
			? $context['highlight_bullets']
			: self::highlightBullets(self::ALWAYS_VERSIONS, self::ALWAYS_BULLETS);
		$url = isset($context['changelog_url'])
			? (string) $context['changelog_url']
			: self::PUBLIC_URL;

		$lines   = array();
		$lines[] = sprintf(
			'Recent changelog (installed plugin version: %s). Source: plugin readme.txt. Full public archive: %s',
			$version !== '' ? $version : '(unknown)',
			$url
		);

		if (! $highlights) {
			$lines[] = '(No New/Improved highlights found in the latest releases — call explain_setting topic=changelog for the raw recent entries.)';

			return implode("\n", $lines);
		}

		$lines[] = 'Highlights (do not invent beyond this list):';
		foreach ($highlights as $bullet) {
			// Keep always-on prompt short — full text via explain_setting topic=changelog.
			$lines[] = '• ' . self::truncate($bullet, 140);
		}
		$lines[] = 'More → explain_setting topic=changelog.';

		return implode("\n", $lines);
	}

	/**
	 * @param string $text
	 * @param int    $max
	 * @return string
	 */
	private static function truncate($text, $max)
	{
		$text = trim((string) $text);
		$max  = max(20, (int) $max);
		if (function_exists('mb_strlen') && function_exists('mb_substr')) {
			if (mb_strlen($text) <= $max) {
				return $text;
			}

			return rtrim(mb_substr($text, 0, $max - 1)) . '…';
		}
		if (strlen($text) <= $max) {
			return $text;
		}

		return rtrim(substr($text, 0, $max - 1)) . '…';
	}

	/**
	 * Longer summary for explain_setting topic=changelog.
	 *
	 * @return string
	 */
	public static function formatDetailSummary()
	{
		$releases = self::recentReleases(self::DETAIL_VERSIONS);
		$version  = self::installedVersion();
		$lines    = array(
			sprintf(
				/* translators: %s: plugin version */
				__('Recent Salon Booking System changelog (installed: %s)', 'salon-booking-system'),
				$version !== '' ? $version : __('unknown', 'salon-booking-system')
			),
			'',
			'[' . __('Open public changelog', 'salon-booking-system') . '](' . self::PUBLIC_URL . ')',
			'',
		);

		if (! $releases) {
			$lines[] = __('Could not read changelog entries from the plugin readme.', 'salon-booking-system');

			return implode("\n", $lines);
		}

		foreach ($releases as $release) {
			$head = isset($release['version']) ? (string) $release['version'] : '';
			if (! empty($release['date'])) {
				$head .= ' (' . $release['date'] . ')';
			}
			$lines[] = '### ' . $head;
			$bullets = isset($release['bullets']) && is_array($release['bullets']) ? $release['bullets'] : array();
			if (! $bullets) {
				$lines[] = '• ' . __('(no details listed)', 'salon-booking-system');
			} else {
				foreach ($bullets as $b) {
					$lines[] = '• ' . $b;
				}
			}
			$lines[] = '';
		}

		$lines[] = __('Answers about “what\'s new” should stick to these entries (and the compact highlights in chat context). Do not invent features.', 'salon-booking-system');

		return trim(implode("\n", $lines));
	}

	/**
	 * @return string
	 */
	public static function installedVersion()
	{
		if (defined('SLN_VERSION') && SLN_VERSION) {
			return (string) SLN_VERSION;
		}

		return '';
	}

	/**
	 * @param int $maxVersions
	 * @return array<int,array{version:string,date:string,bullets:array<int,string>}>
	 */
	public static function recentReleases($maxVersions = self::ALWAYS_VERSIONS)
	{
		$maxVersions = max(1, (int) $maxVersions);
		$all         = self::parse();
		if (! $all) {
			return array();
		}

		return array_slice($all, 0, $maxVersions);
	}

	/**
	 * Prefer New/Improved/Added bullets across recent versions (skip filler + pure security when possible).
	 *
	 * @param int $maxVersions
	 * @param int $maxBullets
	 * @return array<int,string>
	 */
	public static function highlightBullets($maxVersions = self::ALWAYS_VERSIONS, $maxBullets = self::ALWAYS_BULLETS)
	{
		$maxBullets = max(1, (int) $maxBullets);
		$out        = array();
		foreach (self::recentReleases($maxVersions) as $release) {
			$ver = isset($release['version']) ? (string) $release['version'] : '';
			foreach (isset($release['bullets']) ? $release['bullets'] : array() as $bullet) {
				$bullet = trim((string) $bullet);
				if ($bullet === '' || ! self::isHighlightBullet($bullet)) {
					continue;
				}
				$label = $ver !== '' ? '[' . $ver . '] ' . $bullet : $bullet;
				$out[] = $label;
				if (count($out) >= $maxBullets) {
					return $out;
				}
			}
		}

		// Fallback: first non-filler bullets if no New/Improved found.
		if (! $out) {
			foreach (self::recentReleases($maxVersions) as $release) {
				$ver = isset($release['version']) ? (string) $release['version'] : '';
				foreach (isset($release['bullets']) ? $release['bullets'] : array() as $bullet) {
					$bullet = trim((string) $bullet);
					if ($bullet === '' || self::isFillerBullet($bullet)) {
						continue;
					}
					$out[] = $ver !== '' ? '[' . $ver . '] ' . $bullet : $bullet;
					if (count($out) >= $maxBullets) {
						return $out;
					}
				}
			}
		}

		return $out;
	}

	/**
	 * @param string $bullet
	 * @return bool
	 */
	private static function isHighlightBullet($bullet)
	{
		if (self::isFillerBullet($bullet)) {
			return false;
		}
		// Prefer product-facing changes; skip security / hardening notes in the always-on blurb.
		if (preg_match(
			'/\b(security\s+fix|vulnerability|cve-\d|nonce|csrf|idor|unauthenticated|oauth state|capability check|broken access)\b/i',
			$bullet
		)) {
			return false;
		}

		return (bool) preg_match(
			'/^\s*(new\b|improved\b|added\b|implement(?:ed)?\b|restored\b)/i',
			$bullet
		);
	}

	/**
	 * @param string $bullet
	 * @return bool
	 */
	private static function isFillerBullet($bullet)
	{
		return (bool) preg_match(
			'/^\s*(minor\s+(improvements?|fixes?|issues?)|misc\.?|typo)\b/i',
			$bullet
		);
	}

	/**
	 * Parse == Changelog == from readme.txt (cached by filemtime).
	 *
	 * @return array<int,array{version:string,date:string,bullets:array<int,string>}>
	 */
	public static function parse()
	{
		$path = self::readmePath();
		if ($path === '' || ! is_readable($path)) {
			return array();
		}

		$mtime = (int) filemtime($path);
		$cached = get_transient(self::CACHE_KEY);
		if (
			is_array($cached)
			&& isset($cached['mtime'], $cached['releases'])
			&& (int) $cached['mtime'] === $mtime
			&& is_array($cached['releases'])
		) {
			return $cached['releases'];
		}

		$raw = file_get_contents($path);
		if (! is_string($raw) || $raw === '') {
			return array();
		}

		$releases = self::parseReadmeContent($raw);
		set_transient(
			self::CACHE_KEY,
			array(
				'mtime'    => $mtime,
				'releases' => $releases,
			),
			DAY_IN_SECONDS
		);

		return $releases;
	}

	/**
	 * @param string $content
	 * @return array<int,array{version:string,date:string,bullets:array<int,string>}>
	 */
	public static function parseReadmeContent($content)
	{
		$pos = stripos($content, '== Changelog ==');
		if ($pos === false) {
			return array();
		}
		$section = substr($content, $pos + strlen('== Changelog =='));
		// Stop at next top-level readme section if present.
		if (preg_match('/\n==\s+[^=].*?==\s*\n/', $section, $m, PREG_OFFSET_CAPTURE)) {
			$section = substr($section, 0, $m[0][1]);
		}

		$lines    = preg_split('/\R/', $section);
		$releases = array();
		$current  = null;

		foreach ($lines as $line) {
			$trim = trim($line);
			if ($trim === '') {
				continue;
			}

			if (preg_match(
				'/^(\d{1,2}[.\/-]\d{1,2}[.\/-]\d{4})\s*-?\s*(\d+\.\d+(?:\.\d+)?)\s*$/',
				$trim,
				$m
			)) {
				if ($current) {
					$releases[] = $current;
				}
				$current = array(
					'date'    => self::normalizeDate($m[1]),
					'version' => self::normalizeVersion($m[2]),
					'bullets' => array(),
				);
				continue;
			}

			if ($current === null) {
				continue;
			}

			if (preg_match('/^\*\s+(.+)$/', $trim, $bm)) {
				$bullet = trim($bm[1]);
				if ($bullet !== '') {
					$current['bullets'][] = $bullet;
				}
			}
		}

		if ($current) {
			$releases[] = $current;
		}

		return $releases;
	}

	/**
	 * @return string Absolute path or empty.
	 */
	private static function readmePath()
	{
		if (! defined('SLN_PLUGIN_DIR')) {
			return '';
		}
		$path = SLN_PLUGIN_DIR . self::README_REL;

		return file_exists($path) ? $path : '';
	}

	/**
	 * @param string $version
	 * @return string
	 */
	private static function normalizeVersion($version)
	{
		$version = trim((string) $version);
		$parts   = explode('.', $version);
		if (count($parts) === 2) {
			return $version . '.0';
		}

		return $version;
	}

	/**
	 * @param string $dateStr d.m.Y / d-m-Y / d/m/Y
	 * @return string Y-m-d or original trim
	 */
	private static function normalizeDate($dateStr)
	{
		$dateStr = trim((string) $dateStr);
		$parts   = preg_split('/[.\/-]/', $dateStr);
		if (! is_array($parts) || count($parts) !== 3) {
			return $dateStr;
		}
		$day   = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
		$month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
		$year  = $parts[2];

		return $year . '-' . $month . '-' . $day;
	}
}
