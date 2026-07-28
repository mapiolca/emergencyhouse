<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!function_exists('getDolGlobalString')) {
	/**
	 * @param string $key Constant
	 * @param string $default Default
	 * @return string
	 */
	function getDolGlobalString($key, $default = '')
	{
		return $default;
	}
}

require dirname(__DIR__).'/class/languageservice.class.php';

$failures = array();

/**
 * @param bool $condition Contract
 * @param string $message Failure
 * @return void
 */
function emergencyhouseLanguageServiceContract($condition, $message)
{
	global $failures;
	if (!$condition) {
		$failures[] = $message;
	}
}

$supported = EmergencyHouseLanguageService::getSupportedLocales();
emergencyhouseLanguageServiceContract(count($supported) === 15, 'Exactly 15 public locales are registered');
emergencyhouseLanguageServiceContract(
	EmergencyHouseLanguageService::normalizeLocale('pt-BR') === 'pt_PT',
	'Portuguese browser variants map to the supported Portuguese locale'
);
emergencyhouseLanguageServiceContract(
	EmergencyHouseLanguageService::normalizeLocale('zh-Hant') === '',
	'Traditional Chinese is not silently mapped to simplified Chinese'
);
emergencyhouseLanguageServiceContract(
	EmergencyHouseLanguageService::negotiateAcceptLanguage('de-DE;q=0.7, uk-UA;q=0.9, fr;q=0.8') === 'uk_UA',
	'Accept-Language quality weights are respected'
);
emergencyhouseLanguageServiceContract(
	EmergencyHouseLanguageService::negotiateAcceptLanguage('xx-YY, ar;q=0.5') === 'ar_SA',
	'Unsupported tags are skipped'
);
emergencyhouseLanguageServiceContract(
	EmergencyHouseLanguageService::getLocaleMetadata('ar_SA')['direction'] === 'rtl',
	'Arabic is registered as RTL'
);
emergencyhouseLanguageServiceContract(
	EmergencyHouseLanguageService::getDefaultLocale('ja_JP') === 'ja_JP',
	'Supported Dolibarr locale is used as fallback'
);

if (!empty($failures)) {
	foreach ($failures as $failure) {
		fwrite(STDERR, '[FAIL] '.$failure.PHP_EOL);
	}
	exit(1);
}
fwrite(STDOUT, '[OK] Language negotiation and registry contracts'.PHP_EOL);
