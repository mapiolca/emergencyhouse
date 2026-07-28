<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$root = dirname(__DIR__);
$locales = array(
	'fr_FR', 'en_US', 'es_ES', 'de_DE', 'it_IT', 'pt_PT', 'nl_NL', 'pl_PL',
	'ro_RO', 'uk_UA', 'ru_RU', 'ar_SA', 'tr_TR', 'zh_CN', 'ja_JP',
);
$failures = array();

/**
 * @param string $path Catalogue path
 * @return array<string, string>
 */
function emergencyhouseLanguageContractRead($path)
{
	global $failures;

	$lines = @file($path, FILE_IGNORE_NEW_LINES);
	if (!is_array($lines)) {
		$failures[] = 'Unreadable catalogue: '.$path;
		return array();
	}
	$translations = array();
	foreach ($lines as $lineNumber => $line) {
		if ($line === '' || $line[0] === '#') {
			continue;
		}
		$separator = strpos($line, '=');
		if ($separator === false || $separator === 0) {
			$failures[] = $path.':'.($lineNumber + 1).' invalid line';
			continue;
		}
		$key = substr($line, 0, $separator);
		if (isset($translations[$key])) {
			$failures[] = $path.':'.($lineNumber + 1).' duplicate key '.$key;
			continue;
		}
		$translations[$key] = substr($line, $separator + 1);
	}
	return $translations;
}

/**
 * @param string $value Translation
 * @return list<string>
 */
function emergencyhouseLanguageContractPlaceholders($value)
{
	preg_match_all(
		'~%(?:\d+\$)?[-+0-9.\' ]*[bcdeEfFgGosuxX]|__[A-Z0-9_]+__|\{[A-Za-z0-9_]+\}~u',
		$value,
		$matches
	);
	$placeholders = isset($matches[0]) ? $matches[0] : array();
	sort($placeholders);
	return array_values($placeholders);
}

$reference = emergencyhouseLanguageContractRead($root.'/langs/en_US/emergencyhouse.lang');
foreach ($locales as $locale) {
	$path = $root.'/langs/'.$locale.'/emergencyhouse.lang';
	$translations = emergencyhouseLanguageContractRead($path);
	$missing = array_diff_key($reference, $translations);
	$unexpected = array_diff_key($translations, $reference);
	if (!empty($missing)) {
		$failures[] = $locale.' missing keys: '.implode(', ', array_keys($missing));
	}
	if (!empty($unexpected)) {
		$failures[] = $locale.' unexpected keys: '.implode(', ', array_keys($unexpected));
	}
	foreach ($reference as $key => $referenceValue) {
		if (!isset($translations[$key])) {
			continue;
		}
		if (strpos($translations[$key], 'ZXPH') !== false) {
			$failures[] = $locale.' contains an unrestored placeholder token for '.$key;
		}
		if (
			emergencyhouseLanguageContractPlaceholders($referenceValue)
			!== emergencyhouseLanguageContractPlaceholders($translations[$key])
		) {
			$failures[] = $locale.' placeholder mismatch for '.$key;
		}
	}
}

if (!empty($failures)) {
	foreach ($failures as $failure) {
		fwrite(STDERR, '[FAIL] '.$failure.PHP_EOL);
	}
	exit(1);
}

fwrite(STDOUT, '[OK] '.count($locales).' complete language catalogues with '.count($reference).' keys'.PHP_EOL);
