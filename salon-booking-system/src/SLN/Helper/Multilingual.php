<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.MissingUnslash
// phpcs:ignoreFile WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

class SLN_Helper_Multilingual{

	static $implementation;

	/**
	 * Forced language code for the current message render (WPML/Polylang slug).
	 *
	 * @var string|null
	 */
	protected static $forcedLanguage;

	/**
	 * Forced locale for gettext / plugin_locale (e.g. en_US).
	 *
	 * @var string|null
	 */
	protected static $forcedLocale;

	static function getImplementation(){
		if(self::$implementation === null){
			self::setImplementation();
		}
		return self::$implementation;
	}

	static function setImplementation(){
		$implementation = 'wp';
		if(function_exists('pll_current_language')){
			$implementation = 'polylang';
		}elseif(defined('ICL_LANGUAGE_CODE')){
			$implementation = 'wpml';
		}
		self::$implementation = $implementation;
	}

	static function getCurrentLanguage(){
		if (self::$forcedLanguage) {
			return self::$forcedLanguage;
		}
		$i = self::getImplementation();
		$ret;
		switch ($i) {
			case 'wpml':
				$ret = ICL_LANGUAGE_CODE;
				break;
			case 'polylang':
				$ret = pll_current_language();
				break;
			default:
			   $ret = strtolower(substr(get_user_locale(), 0, 2));
		}
		return $ret;
	}

	static function getDefaultLanguage(){
		$i = self::getImplementation();
		$ret;
		switch ($i) {
			case 'wpml':
				$ret = apply_filters( 'wpml_default_language', NULL );
				break;
			case 'polylang':
				$ret = pll_default_language ();
				break;
			default:
			   $ret = strtolower(substr(get_user_locale(), 0, 2));
		}
		return $ret;
	}
	static function getObjectLanguage( $id ){
		$i = self::getImplementation();
		$ret;
		switch ($i) {
			case 'wpml':
				$ret = apply_filters( 'wpml_element_language_code', NULL, array('element_id' => $id, 'element_type' => get_post_type( $id ) ) );
				break;
			case 'polylang':
				$ret = pll_get_post_language($id);
				break;
			default:
			   $ret = strtolower(substr(get_user_locale(), 0, 2));
		}
		return $ret;
	}

	static function translateId( $id , $code = false, $return_original = true ){
		$i = self::getImplementation();
		if(!$code) $code = self::getDefaultLanguage();
		$ret;
		switch ($i) {
			case 'wpml':
				$ret = apply_filters( 'wpml_object_id', $id, get_post_type( $id ), $return_original, $code );;
				break;
			case 'polylang':
				$ret = pll_get_post($id, $code);
				if( empty($ret) && $return_original ) $ret = $id;
				break;
			default:
			   $ret = $id;
		}
		return $ret;
	}

	static function getTermLanguage( $id, $taxonomy  ){
		$i = self::getImplementation();
		$ret;
		switch ($i) {
			case 'wpml':
				$term = get_term($id,$taxonomy);
				$ret = apply_filters( 'wpml_element_language_code', NULL, array('element_id' => $term->term_taxonomy_id, 'element_type' => $taxonomy ) );
				break;
			case 'polylang':
				$ret = pll_get_term_language($id);
				break;
			default:
			   $ret = strtolower(substr(get_user_locale(), 0, 2));
		}
		return $ret;
	}

	static function translateTermId( $id, $taxonomy  , $code = false, $return_original = true ){
		$i = self::getImplementation();
		if(!$code) $code = self::getDefaultLanguage();
		$ret;
		switch ($i) {
			case 'wpml':
				$ret = apply_filters( 'wpml_object_id', $id, $taxonomy, $return_original, $code );
				break;
			case 'polylang':
				$ret = pll_get_term($id, $code);
				if( empty($ret) && $return_original ) $ret = $id;
				break;
			default:
			   $ret = $id;
		}
		return $ret;
	}

	static function getDateLocale(){
		if (self::$forcedLocale) {
			return self::$forcedLocale;
		}
		$implementation = self::getImplementation();
		$locale = get_user_locale();
		if($implementation === 'wpml'){
			$languages = apply_filters( 'wpml_active_languages', null);

			$language = isset( $_REQUEST['lang'] ) ? $_REQUEST['lang'] : ICL_LANGUAGE_CODE;

			if ( isset( $languages[$language] ) )
				$locale = $languages[$language]['default_locale'];
		}elseif($implementation === 'polylang'){
			$locale = pll_current_language('locale');
			if(! $locale ){
				$locale = get_user_locale();
			}
		}

		if( setlocale(LC_TIME,0) !== $locale  ){ setlocale(LC_TIME, $locale ); }

		return $locale;
	}

	static function isMultiLingual(){
		return self::getImplementation() !== 'wp';
	}

	static function registerString($string){
		$implementation = self::getImplementation();
		if($implementation === 'wpml'){
			icl_register_string( 'salon-booking-system', '', $string );
		}elseif($implementation === 'polylang'){
			pll_register_string( 'salon-booking-system', $string);
		}
	}

	/**
	 * Active WPML/Polylang languages for the PWA language control.
	 *
	 * @return array<int, array{code:string,name:string,locale:string}>
	 */
	static function getAvailableLanguages(){
		$out = array();
		$i   = self::getImplementation();

		if ( $i === 'wpml' ) {
			$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
			if ( is_array( $languages ) ) {
				foreach ( $languages as $lang ) {
					$code = isset( $lang['code'] ) ? $lang['code'] : '';
					if ( ! $code ) {
						continue;
					}
					$name = '';
					if ( ! empty( $lang['native_name'] ) ) {
						$name = $lang['native_name'];
					} elseif ( ! empty( $lang['translated_name'] ) ) {
						$name = $lang['translated_name'];
					}
					$out[] = array(
						'code'   => $code,
						'name'   => $name ? $name : $code,
						'locale' => isset( $lang['default_locale'] ) ? $lang['default_locale'] : '',
					);
				}
			}
		} elseif ( $i === 'polylang' && function_exists( 'pll_languages_list' ) ) {
			$codes   = pll_languages_list( array( 'fields' => 'slug' ) );
			$names   = pll_languages_list( array( 'fields' => 'name' ) );
			$locales = pll_languages_list( array( 'fields' => 'locale' ) );
			if ( is_array( $codes ) ) {
				foreach ( $codes as $idx => $code ) {
					$out[] = array(
						'code'   => $code,
						'name'   => isset( $names[ $idx ] ) && $names[ $idx ] ? $names[ $idx ] : $code,
						'locale' => isset( $locales[ $idx ] ) ? $locales[ $idx ] : '',
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Accept only a known WPML/Polylang code, or a short language tag when no multilingual plugin is active.
	 *
	 * @param string $code
	 * @return string
	 */
	static function sanitizeLanguageCode( $code ){
		$code = strtolower( str_replace( '_', '-', trim( (string) $code ) ) );
		if ( $code === '' ) {
			return '';
		}

		$available = self::getAvailableLanguages();
		if ( $available ) {
			foreach ( $available as $lang ) {
				$lang_code = strtolower( (string) $lang['code'] );
				if ( $lang_code === $code ) {
					return $lang['code'];
				}
			}
			foreach ( $available as $lang ) {
				$locale_prefix = strtolower( substr( (string) $lang['locale'], 0, 2 ) );
				if ( $locale_prefix && $locale_prefix === substr( $code, 0, 2 ) && strlen( $code ) === 2 ) {
					return $lang['code'];
				}
			}
			return '';
		}

		if ( preg_match( '/^[a-z]{2}(?:-[a-z0-9]+)?$/', $code ) ) {
			return $code;
		}

		return '';
	}

	/**
	 * @param string $code
	 * @return string
	 */
	static function languageCodeToLocale( $code ){
		$code = self::sanitizeLanguageCode( $code );
		if ( ! $code ) {
			return '';
		}
		foreach ( self::getAvailableLanguages() as $lang ) {
			if ( $lang['code'] === $code && ! empty( $lang['locale'] ) ) {
				return $lang['locale'];
			}
		}
		if ( $code === 'en' ) {
			return 'en_US';
		}
		if ( strlen( $code ) === 2 ) {
			return $code . '_' . strtoupper( $code );
		}
		return str_replace( '-', '_', $code );
	}

	/**
	 * Language stored on the booking, then the customer, then empty.
	 *
	 * @param SLN_Wrapper_Booking|mixed $booking
	 * @return string
	 */
	static function getBookingLanguage( $booking ){
		if ( ! ( $booking instanceof SLN_Wrapper_Booking ) ) {
			return '';
		}
		$code = self::sanitizeLanguageCode( $booking->getMeta( 'language' ) );
		if ( $code ) {
			return $code;
		}
		$customer = $booking->getCustomer();
		if ( $customer && ! $customer->isEmpty() ) {
			return self::sanitizeLanguageCode( $customer->getMeta( 'language' ) );
		}
		return '';
	}

	/**
	 * Switch WPML/Polylang + WordPress locale for customer message rendering.
	 *
	 * @param string $code
	 * @return array
	 */
	static function applyLanguage( $code ){
		$code   = self::sanitizeLanguageCode( $code );
		$locale = $code ? self::languageCodeToLocale( $code ) : '';
		$state  = array(
			'forced_language' => self::$forcedLanguage,
			'forced_locale'   => self::$forcedLocale,
			'request_lang'    => isset( $_REQUEST['lang'] ) ? $_REQUEST['lang'] : null,
			'wpml_lang'       => null,
			'pll_lang'        => null,
			'switched_locale' => false,
		);

		if ( ! $code ) {
			return $state;
		}

		self::$forcedLanguage = $code;
		self::$forcedLocale   = $locale ? $locale : $code;
		$_REQUEST['lang']     = $code;

		if ( self::getImplementation() === 'wpml' ) {
			global $sitepress;
			if ( $sitepress && method_exists( $sitepress, 'get_current_language' ) && method_exists( $sitepress, 'switch_lang' ) ) {
				$state['wpml_lang'] = $sitepress->get_current_language();
				$sitepress->switch_lang( $code, true );
			} else {
				do_action( 'wpml_switch_language', $code );
			}
		} elseif ( self::getImplementation() === 'polylang' && function_exists( 'PLL' ) ) {
			$pll = PLL();
			if ( $pll && isset( $pll->curlang ) ) {
				$state['pll_lang'] = $pll->curlang;
			}
			if ( $pll && isset( $pll->model ) && method_exists( $pll->model, 'get_language' ) ) {
				$language = $pll->model->get_language( $code );
				if ( $language ) {
					$pll->curlang = $language;
				}
			}
		}

		if ( $locale && function_exists( 'switch_to_locale' ) ) {
			$switched = switch_to_locale( $locale );
			$state['switched_locale'] = (bool) $switched;
		}

		self::reloadPluginTextdomain();

		return $state;
	}

	/**
	 * @param array $state State returned by applyLanguage().
	 * @return void
	 */
	static function restoreLanguage( $state ){
		if ( ! is_array( $state ) ) {
			return;
		}

		self::$forcedLanguage = $state['forced_language'];
		self::$forcedLocale   = $state['forced_locale'];

		if ( array_key_exists( 'request_lang', $state ) ) {
			if ( $state['request_lang'] !== null ) {
				$_REQUEST['lang'] = $state['request_lang'];
			} else {
				unset( $_REQUEST['lang'] );
			}
		}

		if ( self::getImplementation() === 'wpml' && $state['wpml_lang'] !== null ) {
			global $sitepress;
			if ( $sitepress && method_exists( $sitepress, 'switch_lang' ) ) {
				$sitepress->switch_lang( $state['wpml_lang'], true );
			} else {
				do_action( 'wpml_switch_language', $state['wpml_lang'] );
			}
		} elseif ( self::getImplementation() === 'polylang' && $state['pll_lang'] !== null && function_exists( 'PLL' ) ) {
			$pll = PLL();
			if ( $pll ) {
				$pll->curlang = $state['pll_lang'];
			}
		}

		if ( ! empty( $state['switched_locale'] ) && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}

		self::reloadPluginTextdomain();
	}

	/**
	 * Reload plugin gettext for the current locale.
	 * load_plugin_textdomain() is not enough after a mid-request switch: WordPress 6.1+
	 * may mark the domain unloaded, so JIT never loads the other .mo.
	 */
	private static function reloadPluginTextdomain(){
		global $l10n_unloaded;

		if ( is_array( $l10n_unloaded ) ) {
			unset( $l10n_unloaded['salon-booking-system'] );
		}
		if ( function_exists( 'unload_textdomain' ) ) {
			unload_textdomain( 'salon-booking-system', true );
		}
		if ( class_exists( 'WP_Translation_Controller' ) ) {
			WP_Translation_Controller::get_instance()->unload_textdomain( 'salon-booking-system' );
		}

		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$mofile = ( defined( 'SLN_PLUGIN_DIR' ) ? SLN_PLUGIN_DIR : '' ) . '/languages/salon-booking-system-' . $locale . '.mo';
		if ( $locale && is_readable( $mofile ) ) {
			load_textdomain( 'salon-booking-system', $mofile );
		}
	}

	/**
	 * @param int    $booking_id
	 * @param string $code
	 * @param int    $customer_id
	 * @param bool   $update_customer When true, overwrite the customer's stored language.
	 * @return string Sanitized code that was stored, or empty.
	 */
	static function persistLanguage( $booking_id, $code, $customer_id = 0, $update_customer = true ){
		$code = self::sanitizeLanguageCode( $code );
		if ( ! $code ) {
			return '';
		}
		if ( $booking_id ) {
			update_post_meta( (int) $booking_id, '_sln_booking_language', $code );
		}
		if ( $update_customer && $customer_id ) {
			update_user_meta( (int) $customer_id, '_sln_language', $code );
		}
		return $code;
	}
}