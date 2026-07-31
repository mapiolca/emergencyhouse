<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone behavior contract for public legal-page publication.
 */

$emergencyhouseTestIntegers = array();
$emergencyhouseTestStrings = array();

/**
 * @param string $name Constant name
 * @param int $default Default
 * @return int
 */
function getDolGlobalInt($name, $default = 0)
{
	global $emergencyhouseTestIntegers;

	return isset($emergencyhouseTestIntegers[$name])
		? (int) $emergencyhouseTestIntegers[$name]
		: (int) $default;
}

/**
 * @param string $name Constant name
 * @param string $default Default
 * @return string
 */
function getDolGlobalString($name, $default = '')
{
	global $emergencyhouseTestStrings;

	return isset($emergencyhouseTestStrings[$name])
		? (string) $emergencyhouseTestStrings[$name]
		: (string) $default;
}

/**
 * @param string $value Rich HTML
 * @return string
 */
function dol_string_nohtmltag($value)
{
	return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

require dirname(__DIR__).'/class/languageservice.class.php';
require dirname(__DIR__).'/lib/emergencyhouse.lib.php';
require dirname(__DIR__).'/lib/emergencyhouse_public.lib.php';

/**
 * @param bool $condition Contract result
 * @param string $message Message
 * @return void
 */
function emergencyhouseLegalPublicationAssert($condition, $message)
{
	if (!$condition) {
		fwrite(STDERR, '[FAIL] '.$message.PHP_EOL);
		exit(1);
	}
}

$enabledConstant = 'EMERGENCYHOUSE_PUBLIC_TERMS_ENABLED';
$htmlConstant = 'EMERGENCYHOUSE_PUBLIC_TERMS_HTML';

$emergencyhouseTestIntegers[$enabledConstant] = 0;
$emergencyhouseTestStrings[$htmlConstant] = '<p>Conditions publiables</p>';
emergencyhouseLegalPublicationAssert(
	!emergencyhousePublicLegalPageIsPublished($enabledConstant, $htmlConstant),
	'Un contenu ne doit pas être publié lorsque son interrupteur est désactivé.'
);

$emergencyhouseTestIntegers[$enabledConstant] = 1;
$emergencyhouseTestStrings[$htmlConstant] = '';
emergencyhouseLegalPublicationAssert(
	!emergencyhousePublicLegalPageIsPublished($enabledConstant, $htmlConstant),
	'Un interrupteur actif ne doit pas publier un contenu vide.'
);

$emergencyhouseTestStrings[$htmlConstant] = '<p><br></p>';
emergencyhouseLegalPublicationAssert(
	!emergencyhousePublicLegalPageIsPublished($enabledConstant, $htmlConstant),
	'Un balisage sans texte visible doit rester non publié.'
);

$emergencyhouseTestStrings[$htmlConstant] = '<p>&nbsp;</p>';
emergencyhouseLegalPublicationAssert(
	!emergencyhousePublicLegalPageIsPublished($enabledConstant, $htmlConstant),
	'Une espace insécable ne doit pas être considérée comme du contenu.'
);

$emergencyhouseTestStrings[$htmlConstant] = '<h2>Conditions publiées</h2><p>Contenu utile.</p>';
emergencyhouseLegalPublicationAssert(
	emergencyhousePublicLegalPageIsPublished($enabledConstant, $htmlConstant),
	'Un interrupteur actif et un contenu visible doivent publier la page.'
);

$emergencyhouseTestStrings[$htmlConstant.'_FR_FR'] = '<h2>Conditions françaises</h2>';
emergencyhouseLegalPublicationAssert(
	emergencyhousePublicLocalizedHtml($htmlConstant, 'fr_FR') === '<h2>Conditions françaises</h2>',
	'Le contenu localisé exact doit être prioritaire.'
);

$emergencyhouseTestStrings[$htmlConstant.'_FR_FR'] = '';
$emergencyhouseTestStrings[$htmlConstant.'_EN_US'] = '<h2>English terms</h2>';
emergencyhouseLegalPublicationAssert(
	emergencyhousePublicLocalizedHtml($htmlConstant, 'ja_JP') === '<h2>Conditions publiées</h2><p>Contenu utile.</p>',
	'Une langue juridique absente doit utiliser le contenu historique de la langue publique par défaut.'
);

fwrite(STDOUT, "Contrat de publication des pages légales validé.".PHP_EOL);
exit(0);
