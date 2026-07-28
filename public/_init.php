<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res && file_exists('../../../../main.inc.php')) {
	$res = include '../../../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	exit;
}

dol_include_once('/emergencyhouse/class/publicauthservice.class.php');
dol_include_once('/emergencyhouse/class/publicanalyticsservice.class.php');
dol_include_once('/emergencyhouse/class/languageservice.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_public.lib.php');

$emergencyhouseApplicationLocale = (string) $langs->defaultlang;
$emergencyhousePublicDefaultLocale = EmergencyHouseLanguageService::getDefaultLocale($emergencyhouseApplicationLocale);
$emergencyhousePublicLocale = emergencyhousePublicResolveLocale(null, $emergencyhouseApplicationLocale);
$langs->setDefaultLang($emergencyhousePublicLocale);
$langs->loadLangs(array('main', 'companies', 'other', 'emergencyhouse@emergencyhouse'));

if (!isModEnabled('emergencyhouse') || !getDolGlobalInt('EMERGENCYHOUSE_PUBLIC_PORTAL_ENABLED', 0)) {
	http_response_code(503);
	emergencyhousePublicRenderUnavailable();
	exit;
}

emergencyhousePublicSendSecurityHeaders();
if (!emergencyhousePublicIsSecureTransport()) {
	http_response_code(403);
	emergencyhousePublicRenderUnavailable('PublicHttpsRequired');
	exit;
}

$emergencyhousePublicIp = defined('EMERGENCYHOUSE_PUBLIC_SKIP_ACCOUNT_AUTH')
	? ''
	: emergencyhousePublicRemoteAddress();
$emergencyhousePublicUserAgent = emergencyhousePublicUserAgent();
$emergencyhousePublicAuth = null;
$emergencyhousePublicAccount = null;
if (!defined('EMERGENCYHOUSE_PUBLIC_SKIP_ACCOUNT_AUTH')) {
	$emergencyhousePublicAuth = new EmergencyHousePublicAuthService($db);
	$authenticatedAccount = $emergencyhousePublicAuth->authenticateFromCookie(
		$emergencyhousePublicIp,
		$emergencyhousePublicUserAgent
	);
	$emergencyhousePublicAccount = $authenticatedAccount instanceof EmergencyHousePublicAccount
		? $authenticatedAccount
		: null;
}
$emergencyhousePublicLocale = emergencyhousePublicResolveLocale(
	$emergencyhousePublicAccount,
	$emergencyhouseApplicationLocale
);
if ((string) $langs->defaultlang !== $emergencyhousePublicLocale) {
	$langs->setDefaultLang($emergencyhousePublicLocale);
	$langs->loadLangs(array('main', 'companies', 'other', 'emergencyhouse@emergencyhouse'));
}
if (!headers_sent()) {
	header('Content-Language: '.EmergencyHouseLanguageService::getLocaleMetadata($emergencyhousePublicLocale)['tag']);
	header('Vary: Accept-Language, Cookie', false);
}
$emergencyhousePublicReferrer = isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])
	? $_SERVER['HTTP_REFERER']
	: '';
$emergencyhousePublicHost = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])
	? $_SERVER['HTTP_HOST']
	: '';
$emergencyhousePublicAnalytics = new EmergencyHousePublicAnalyticsService(
	$db,
	(int) $conf->entity,
	$emergencyhousePublicUserAgent,
	$emergencyhousePublicReferrer,
	$emergencyhousePublicHost
);
