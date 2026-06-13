<?php
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

/**
 * Builds the runtime custom-colors stylesheet (uploads/sln-colors.css) by
 * substituting the {color-*} tokens in the compiled css/sln-colors--custom.css
 * with the salon's chosen palette.
 *
 * The generated file is a build artifact: historically it was only (re)written
 * when the Style settings tab was saved, so any plugin update that shipped new
 * token rules (e.g. the forecast/login step colors) would not take effect until
 * an admin manually re-saved Style settings on every site. maybeRegenerate()
 * removes that friction by rebuilding the file whenever it is missing or older
 * than its source template.
 */
class SLN_Helper_CustomColorsCss
{
	const SOURCE_RELATIVE = '/css/sln-colors--custom.css';
	const GENERATED_NAME  = 'sln-colors.css';

	public static function getSourcePath()
	{
		return SLN_PLUGIN_DIR . self::SOURCE_RELATIVE;
	}

	public static function getGeneratedPath()
	{
		$dir = wp_upload_dir();

		return rtrim($dir['basedir'], '/\\') . '/' . self::GENERATED_NAME;
	}

	/**
	 * Regenerate uploads/sln-colors.css from the token template + chosen colors.
	 *
	 * @return bool true on success, false when the source template is unreadable
	 *              or the file could not be written.
	 */
	public static function regenerate(SLN_Settings $settings)
	{
		$source = self::getSourcePath();
		if (!is_readable($source)) {
			return false;
		}

		$css    = file_get_contents($source);
		$colors = $settings->get('style_colors');
		if ($colors) {
			foreach ($colors as $k => $v) {
				$css = str_replace("{color-$k}", $v, $css);
			}
		}

		return false !== file_put_contents(self::getGeneratedPath(), $css);
	}

	/**
	 * The generated file is stale when it is missing or older than the compiled
	 * source template (e.g. right after a plugin update). filemtime is enough: a
	 * zip-based update extracts the new source with a fresh mtime while the
	 * previously generated file keeps its older save time.
	 */
	public static function isStale()
	{
		$generated = self::getGeneratedPath();
		if (!file_exists($generated)) {
			return true;
		}

		$source = self::getSourcePath();
		if (!file_exists($source)) {
			return false; // Nothing to rebuild from.
		}

		return filemtime($source) > filemtime($generated);
	}

	/**
	 * Rebuild the generated stylesheet only when custom colors are enabled and
	 * the file is stale. Safe to call on every front-end request: the work runs
	 * at most once after each update (afterwards the generated file is newer
	 * than its source, so isStale() short-circuits).
	 */
	public static function maybeRegenerate(SLN_Settings $settings)
	{
		if (!$settings->get('style_colors_enabled')) {
			return;
		}
		if (!self::isStale()) {
			return;
		}
		self::regenerate($settings);
	}
}
