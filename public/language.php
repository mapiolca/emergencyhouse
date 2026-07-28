<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

dol_include_once('/emergencyhouse/class/memberservice.class.php');

$action = GETPOST('action', 'aZ09');
$locale = EmergencyHouseLanguageService::normalizeLocale(GETPOST('selected_locale', 'alphanohtml'));
$returnTo = emergencyhousePublicSafeReturnUrl(GETPOST('return_to', 'restricthtml'));

if (
	$action !== 'change_language'
	|| !isset($_SERVER['REQUEST_METHOD'])
	|| $_SERVER['REQUEST_METHOD'] !== 'POST'
	|| $locale === ''
) {
	http_response_code(400);
	emergencyhousePublicRenderHeader($langs->trans('ErrorInvalidLanguage'), $emergencyhousePublicAccount);
	print '<section class="eh-shell eh-section"><div class="eh-alert eh-alert-error" role="alert">';
	print '<h1>'.$langs->trans('ErrorInvalidLanguage').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}

if (
	$emergencyhousePublicAccount instanceof EmergencyHousePublicAccount
	&& !emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'change_language')
) {
	http_response_code(403);
	emergencyhousePublicRenderHeader($langs->trans('ErrorInvalidCsrfToken'), $emergencyhousePublicAccount);
	print '<section class="eh-shell eh-section"><div class="eh-alert eh-alert-error" role="alert">';
	print '<h1>'.$langs->trans('ErrorInvalidCsrfToken').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}

if ($emergencyhousePublicAccount instanceof EmergencyHousePublicAccount) {
	$db->begin();
	if ($emergencyhousePublicAccount->updateLanguage($locale) <= 0) {
		$db->rollback();
		http_response_code(500);
		emergencyhousePublicRenderHeader($langs->trans('ErrorLanguageUpdate'), $emergencyhousePublicAccount);
		print '<section class="eh-shell eh-section"><div class="eh-alert eh-alert-error" role="alert">';
		print '<h1>'.$langs->trans('ErrorLanguageUpdate').'</h1></div></section>';
		emergencyhousePublicRenderFooter();
		exit;
	}
	$memberService = new EmergencyHouseMemberService($db);
	$triggerUser = emergencyhousePublicTriggerUser($db);
	if ($memberService->updateLanguage($emergencyhousePublicAccount, $triggerUser) < 0) {
		$db->rollback();
		http_response_code(500);
		emergencyhousePublicRenderHeader($langs->trans('ErrorLanguageUpdate'), $emergencyhousePublicAccount);
		print '<section class="eh-shell eh-section"><div class="eh-alert eh-alert-error" role="alert">';
		print '<h1>'.$langs->trans('ErrorLanguageUpdate').'</h1></div></section>';
		emergencyhousePublicRenderFooter();
		exit;
	}
	$db->commit();
}

emergencyhousePublicSetLanguageCookie($locale);
$destination = $returnTo !== '' ? emergencyhousePublicUrlWithLocale($returnTo, $locale) : '';
if ($destination === '') {
	$destination = emergencyhousePublicUrl('index.php', array('lang' => $locale));
}
header('Location: '.$destination, true, 303);
exit;
