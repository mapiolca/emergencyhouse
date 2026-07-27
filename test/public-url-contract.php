<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone contract for the configured public-directory root URL.
 *
 * This test stubs only the two Dolibarr helpers required by the URL builder.
 */

$emergencyhouseTestPublicBaseUrl = 'https://emergencyhouse.example.org/';
define('DOL_URL_ROOT', '/dolibarr');

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

emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl() === 'https://emergencyhouse.example.org/',
	'La page d’accueil doit être la racine configurée.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl('offer/index.php') === 'https://emergencyhouse.example.org/offer/index.php',
	'Le chemin Dolibarr ne doit pas être ajouté à un lien d’offre.'
);
$emergencyhouseTestPublicBaseUrl = 'https://emergencyhouse.example.org/portal';
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl('offer/index.php') === 'https://emergencyhouse.example.org/portal/offer/index.php',
	'Une racine publique sans barre finale doit être normalisée.'
);
$emergencyhouseTestPublicBaseUrl = 'https://emergencyhouse.example.org/';
emergencyhousePublicUrlAssert(
	emergencyhousePublicAbsoluteUrl('allocation/view.php', array('id' => 42))
		=== 'https://emergencyhouse.example.org/allocation/view.php?id=42',
	'Les notifications doivent utiliser la racine configurée.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicSafeReturnUrl('https://emergencyhouse.example.org/request/view.php?uuid=abc')
		=== 'https://emergencyhouse.example.org/request/view.php?uuid=abc',
	'Une redirection interne sûre doit rester sur le domaine public.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicSafeReturnUrl('https://attacker.example/request/view.php?uuid=abc') === '',
	'Une redirection vers une autre origine doit être refusée.'
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
		&& strpos($renderedHeader, 'https://emergencyhouse.example.org/offer/index.php') !== false
		&& strpos($renderedHeader, 'https://emergencyhouse.example.org/assets/public.css.php') !== false
		&& strpos($renderedHeader, '/custom/emergencyhouse') === false,
	'La navigation et les ressources rendues doivent utiliser la racine publique.'
);

$emergencyhouseTestPublicBaseUrl = '';
emergencyhousePublicUrlAssert(
	emergencyhousePublicUrl('offer/index.php') === '/custom/emergencyhouse/public/offer/index.php',
	'Le fallback Dolibarr doit rester disponible sans URL publique configurée.'
);
emergencyhousePublicUrlAssert(
	emergencyhousePublicAbsoluteUrl('offer/index.php')
		=== 'https://dolibarr.example.org/custom/emergencyhouse/public/offer/index.php',
	'Le fallback absolu Dolibarr doit rester disponible pour les notifications.'
);

fwrite(STDOUT, "Contrat d’URL publique validé.".PHP_EOL);
exit(0);
