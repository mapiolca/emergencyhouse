<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone contract for the configured public-directory root URL.
 *
 * This test stubs only the two Dolibarr helpers required by the URL builder.
 */

$emergencyhouseTestPublicBaseUrl = 'https://emergencyhouse.example.org/';
$emergencyhouseTestRequestValues = array();
define('DOL_URL_ROOT', '/dolibarr');

class EmergencyHousePublicAccount
{
	/** @var string */
	public $lang = '';
}

/**
 * @param string $name Constant name
 * @param string $default Default
 * @return string
 */
function getDolGlobalString($name, $default = '')
{
	global $emergencyhouseTestPublicBaseUrl;

	return $name === 'EMERGENCYHOUSE_PUBLIC_BASE_URL' ? $emergencyhouseTestPublicBaseUrl : $default;
}

/**
 * @param string $name Input name
 * @param string $type Input filter
 * @return string
 */
function GETPOST($name, $type = '')
{
	global $emergencyhouseTestRequestValues;

	return isset($emergencyhouseTestRequestValues[$name])
		? (string) $emergencyhouseTestRequestValues[$name]
		: '';
}

/**
 * @param string $path Dolibarr path
 * @param int $type Link type
 * @return string
 */
function dol_buildpath($path, $type = 0)
{
	return $type === 2 ? 'https://dolibarr.example.org/custom'.$path : '/custom'.$path;
}

/**
 * @param string $value Value
 * @return string
 */
function dol_escape_htmltag($value)
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param string $value HTML
 * @return string
 */
function dol_string_nohtmltag($value)
{
	return strip_tags($value);
}

/**
 * @param string $value Value
 * @param int $length Length
 * @return string
 */
function dol_trunc($value, $length)
{
	return strlen($value) > $length ? substr($value, 0, $length - 3).'...' : $value;
}

/**
 * @return string Test CSRF token
 */
function newToken()
{
	return 'test-token';
}

require dirname(__DIR__).'/class/languageservice.class.php';
require dirname(__DIR__).'/lib/emergencyhouse_public.lib.php';

/**
 * @param bool $condition Contract result
 * @param string $message Error
 * @return void
 */
function emergencyhousePublicUrlAssert($condition, $message)
{
	if (!$condition) {
		fwrite(STDERR, '[FAIL] '.$message.PHP_EOL);
		exit(1);
	}
}

$languageAccount = new EmergencyHousePublicAccount();
$languageAccount->lang = 'uk_UA';
$_COOKIE['emergencyhouse_language'] = 'es_ES';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ja-JP,fr;q=0.8';
$emergencyhouseTestRequestValues['lang'] = 'de_DE';
emergencyhousePublicUrlAssert(
	emergencyhousePublicResolveLocale($languageAccount, 'fr_FR') === 'de_DE',
	'La langue explicite doit précéder toutes les préférences.'
);
unset($emergencyhouseTestRequestValues['lang']);
emergencyhousePublicUrlAssert(
	emergencyhousePublicResolveLocale($languageAccount, 'fr_FR') === 'uk_UA',
	'La préférence du compte doit précéder le cookie et le navigateur.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicResolveLocale(null, 'fr_FR') === 'es_ES',
	'Le cookie fonctionnel doit précéder la négociation navigateur.'
);
unset($_COOKIE['emergencyhouse_language']);
emergencyhousePublicUrlAssert(
	emergencyhousePublicResolveLocale(null, 'fr_FR') === 'ja_JP',
	'La langue du navigateur doit être utilisée en première visite.'
);

emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl() === 'https://emergencyhouse.example.org/?lang=fr_FR',
	'La page d’accueil doit être la racine configurée avec une langue explicite.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl('offer/index.php') === 'https://emergencyhouse.example.org/offer/index.php?lang=fr_FR',
	'Le chemin Dolibarr ne doit pas être ajouté à un lien d’offre localisé.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl('contact.php') === 'https://emergencyhouse.example.org/contact.php?lang=fr_FR',
	'La page de contact doit être publiée directement sous la racine configurée et localisée.'
);
$emergencyhouseTestPublicBaseUrl = 'https://emergencyhouse.example.org/portal';
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl('offer/index.php') === 'https://emergencyhouse.example.org/portal/offer/index.php?lang=fr_FR',
	'Une racine publique sans barre finale doit être normalisée.'
);
$emergencyhouseTestPublicBaseUrl = 'https://emergencyhouse.example.org/';
emergencyhousePublicUrlAssert(
	emergencyhousePublicAbsoluteUrl('allocation/view.php', array('id' => 42))
		=== 'https://emergencyhouse.example.org/allocation/view.php?id=42&lang=fr_FR',
	'Les notifications doivent utiliser la racine configurée.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl('assets/public.css.php') === 'https://emergencyhouse.example.org/assets/public.css.php',
	'Les ressources statiques ne doivent pas recevoir de paramètre de langue.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrlWithLocale('https://emergencyhouse.example.org/request/view.php?id=4&lang=fr_FR', 'de_DE')
		=== 'https://emergencyhouse.example.org/request/view.php?id=4&lang=de_DE',
	'Le sélecteur doit remplacer la langue sans perdre les autres paramètres.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicSafeReturnUrl('https://emergencyhouse.example.org/request/view.php?uuid=abc')
		=== 'https://emergencyhouse.example.org/request/view.php?uuid=abc&lang=fr_FR',
	'Une redirection interne sûre doit rester sur le domaine public et conserver la langue.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicSafeReturnUrl('https://attacker.example/request/view.php?uuid=abc') === '',
	'Une redirection vers une autre origine doit être refusée.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicSafeReturnUrl('https://emergencyhouse.example.org/contact.php')
		=== 'https://emergencyhouse.example.org/contact.php?lang=fr_FR',
	'La page de contact doit être une destination de retour interne sûre.'
);

$langs = new class {
	/** @var string */
	public $defaultlang = 'fr_FR';

	/**
	 * @param string $key Translation key
	 * @return string
	 */
	public function trans($key)
	{
		return $key;
	}
};
ob_start();
emergencyhousePublicRenderHeader('Test');
$renderedHeader = ob_get_clean();
emergencyhousePublicUrlAssert(
	is_string($renderedHeader)
		&& strpos($renderedHeader, 'https://emergencyhouse.example.org/offer/index.php?lang=fr_FR') !== false
		&& strpos($renderedHeader, 'https://emergencyhouse.example.org/contact.php?lang=fr_FR') !== false
		&& strpos($renderedHeader, 'https://emergencyhouse.example.org/assets/public.css.php') !== false
		&& strpos($renderedHeader, 'name="lang"') !== false
		&& strpos($renderedHeader, 'value="ar_SA"') !== false
		&& strpos($renderedHeader, 'content="noindex,nofollow"') !== false
		&& strpos($renderedHeader, '/custom/emergencyhouse') === false,
	'La navigation et les ressources rendues doivent utiliser la racine publique.'
);

ob_start();
emergencyhousePublicRenderHeader(
	'Campagne de test',
	null,
	'campaigns',
	true,
	false,
	array(
		'description' => 'Une campagne publique de test.',
		'canonical' => 'https://emergencyhouse.example.org/campaign.php?slug=test',
		'structured_data' => array('@context' => 'https://schema.org', '@type' => 'WebPage'),
	)
);
$seoHeader = ob_get_clean();
emergencyhousePublicUrlAssert(
	is_string($seoHeader)
		&& strpos($seoHeader, 'index,follow,max-image-preview:large') !== false
		&& strpos($seoHeader, '<meta name="description" content="Une campagne publique de test.">') !== false
		&& strpos($seoHeader, '<link rel="canonical" href="https://emergencyhouse.example.org/campaign.php?slug=test">') !== false
		&& strpos($seoHeader, '<meta property="og:title" content="Campagne de test">') !== false
		&& strpos($seoHeader, '<script type="application/ld+json">') !== false,
	'Les pages indexables doivent exposer leurs métadonnées de recherche et de partage.'
);

$emergencyhouseTestPublicBaseUrl = '';
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl('offer/index.php') === '/custom/emergencyhouse/public/offer/index.php?lang=fr_FR',
	'Le fallback Dolibarr doit rester disponible sans URL publique configurée.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicAbsoluteUrl('offer/index.php')
		=== 'https://dolibarr.example.org/custom/emergencyhouse/public/offer/index.php?lang=fr_FR',
	'Le fallback absolu Dolibarr doit rester disponible pour les notifications.'
);

fwrite(STDOUT, "Contrat d’URL publique validé.".PHP_EOL);
exit(0);
