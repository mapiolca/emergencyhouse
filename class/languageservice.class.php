<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Central registry and negotiation service for Emergency House languages.
 */
class EmergencyHouseLanguageService
{
	/**
	 * Return supported locales and their public metadata.
	 *
	 * @return array<string, array{label:string,tag:string,direction:'ltr'|'rtl'}>
	 */
	public static function getSupportedLocales()
	{
		return array(
			'fr_FR' => array('label' => 'Français', 'tag' => 'fr-FR', 'direction' => 'ltr'),
			'en_US' => array('label' => 'English', 'tag' => 'en-US', 'direction' => 'ltr'),
			'es_ES' => array('label' => 'Español', 'tag' => 'es-ES', 'direction' => 'ltr'),
			'de_DE' => array('label' => 'Deutsch', 'tag' => 'de-DE', 'direction' => 'ltr'),
			'it_IT' => array('label' => 'Italiano', 'tag' => 'it-IT', 'direction' => 'ltr'),
			'pt_PT' => array('label' => 'Português', 'tag' => 'pt-PT', 'direction' => 'ltr'),
			'nl_NL' => array('label' => 'Nederlands', 'tag' => 'nl-NL', 'direction' => 'ltr'),
			'pl_PL' => array('label' => 'Polski', 'tag' => 'pl-PL', 'direction' => 'ltr'),
			'ro_RO' => array('label' => 'Română', 'tag' => 'ro-RO', 'direction' => 'ltr'),
			'uk_UA' => array('label' => 'Українська', 'tag' => 'uk-UA', 'direction' => 'ltr'),
			'ru_RU' => array('label' => 'Русский', 'tag' => 'ru-RU', 'direction' => 'ltr'),
			'ar_SA' => array('label' => 'العربية', 'tag' => 'ar-SA', 'direction' => 'rtl'),
			'tr_TR' => array('label' => 'Türkçe', 'tag' => 'tr-TR', 'direction' => 'ltr'),
			'zh_CN' => array('label' => '简体中文', 'tag' => 'zh-CN', 'direction' => 'ltr'),
			'ja_JP' => array('label' => '日本語', 'tag' => 'ja-JP', 'direction' => 'ltr'),
		);
	}

	/**
	 * Normalize a locale or browser language tag to a supported locale.
	 *
	 * Traditional Chinese is intentionally not mapped to simplified Chinese.
	 *
	 * @param string $candidate Locale or BCP 47 tag
	 * @return string Supported locale or an empty string
	 */
	public static function normalizeLocale($candidate)
	{
		$candidate = trim(str_replace('-', '_', $candidate));
		if ($candidate === '' || !preg_match('/^[A-Za-z]{2,3}(?:_[A-Za-z]{2,8})?$/', $candidate)) {
			return '';
		}

		$supported = self::getSupportedLocales();
		foreach (array_keys($supported) as $locale) {
			if (strcasecmp($locale, $candidate) === 0) {
				return $locale;
			}
		}

		$normalizedLower = strtolower($candidate);
		if (strpos($normalizedLower, 'zh_hant') === 0 || strpos($normalizedLower, 'zh_tw') === 0 || strpos($normalizedLower, 'zh_hk') === 0) {
			return '';
		}

		$primary = substr($normalizedLower, 0, 2);
		$byPrimaryLanguage = array(
			'fr' => 'fr_FR',
			'en' => 'en_US',
			'es' => 'es_ES',
			'de' => 'de_DE',
			'it' => 'it_IT',
			'pt' => 'pt_PT',
			'nl' => 'nl_NL',
			'pl' => 'pl_PL',
			'ro' => 'ro_RO',
			'uk' => 'uk_UA',
			'ru' => 'ru_RU',
			'ar' => 'ar_SA',
			'tr' => 'tr_TR',
			'zh' => 'zh_CN',
			'ja' => 'ja_JP',
		);

		return isset($byPrimaryLanguage[$primary]) ? $byPrimaryLanguage[$primary] : '';
	}

	/**
	 * Negotiate the best supported locale from an Accept-Language header.
	 *
	 * @param string $header Accept-Language header
	 * @return string Supported locale or an empty string
	 */
	public static function negotiateAcceptLanguage($header)
	{
		$candidates = array();
		foreach (explode(',', substr($header, 0, 2048)) as $position => $part) {
			$segments = array_map('trim', explode(';', $part));
			$tag = isset($segments[0]) ? $segments[0] : '';
			$quality = 1.0;
			foreach (array_slice($segments, 1) as $parameter) {
				if (preg_match('/^q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?)$/i', $parameter, $matches)) {
					$quality = (float) $matches[1];
				}
			}
			$locale = self::normalizeLocale($tag);
			if ($locale !== '' && $quality > 0) {
				$candidates[] = array('locale' => $locale, 'quality' => $quality, 'position' => (int) $position);
			}
		}

		usort($candidates, static function ($left, $right) {
			if ($left['quality'] === $right['quality']) {
				return $left['position'] <=> $right['position'];
			}
			return $left['quality'] > $right['quality'] ? -1 : 1;
		});

		return isset($candidates[0]['locale']) ? (string) $candidates[0]['locale'] : '';
	}

	/**
	 * Return the configured public fallback locale.
	 *
	 * @param string $dolibarrLocale Current Dolibarr locale
	 * @return string
	 */
	public static function getDefaultLocale($dolibarrLocale = '')
	{
		$configured = self::normalizeLocale(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_DEFAULT_LANG', ''));
		if ($configured !== '') {
			return $configured;
		}
		$dolibarrLocale = self::normalizeLocale($dolibarrLocale);
		return $dolibarrLocale !== '' ? $dolibarrLocale : 'fr_FR';
	}

	/**
	 * Return the public metadata of a supported locale.
	 *
	 * @param string $locale Locale
	 * @return array{label:string,tag:string,direction:'ltr'|'rtl'}
	 */
	public static function getLocaleMetadata($locale)
	{
		$supported = self::getSupportedLocales();
		return isset($supported[$locale]) ? $supported[$locale] : $supported['fr_FR'];
	}

	/**
	 * Build a deterministic locale fallback chain without duplicates.
	 *
	 * @param string $locale Requested locale
	 * @param string $dolibarrLocale Current Dolibarr locale
	 * @return list<string>
	 */
	public static function getFallbackChain($locale, $dolibarrLocale = '')
	{
		$chain = array(
			self::normalizeLocale($locale),
			self::getDefaultLocale($dolibarrLocale),
			'en_US',
			'fr_FR',
		);
		$result = array();
		foreach ($chain as $candidate) {
			if ($candidate !== '' && !in_array($candidate, $result, true)) {
				$result[] = $candidate;
			}
		}
		return $result;
	}
}
