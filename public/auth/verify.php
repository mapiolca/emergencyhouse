<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

$token = GETPOST('verification', 'alphanohtml');
$errorKey = '';
$account = $token !== '' ? $emergencyhousePublicAuth->consumeToken($token, 'email_verification') : false;
if (!$account instanceof EmergencyHousePublicAccount) {
	$errorKey = 'ErrorTokenInvalid';
} elseif ($account->markEmailVerified() <= 0
	|| !$emergencyhousePublicAuth->createSession($account, $emergencyhousePublicIp, $emergencyhousePublicUserAgent)) {
	$errorKey = 'ErrorAccountVerification';
} else {
	header('Location: '.emergencyhousePublicUrl('account/index.php', array('verified' => 1)));
	exit;
}

emergencyhousePublicRenderHeader($langs->trans('VerifyEmail'), null, 'login');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('VerifyEmail').'</h1></div>';
emergencyhousePublicAlert($errorKey, 'error');
print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/resend.php')).'">'.$langs->trans('ResendVerificationEmail').'</a>';
print '</section>';
emergencyhousePublicRenderFooter();

