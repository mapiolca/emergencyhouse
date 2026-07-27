<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

$token = GETPOST('login_token', 'alphanohtml');
$account = $token !== '' ? $emergencyhousePublicAuth->consumeToken($token, 'magic_login') : false;
if ($account instanceof EmergencyHousePublicAccount
	&& $emergencyhousePublicAuth->createSession($account, $emergencyhousePublicIp, $emergencyhousePublicUserAgent)) {
	header('Location: '.emergencyhousePublicUrl('account/index.php'));
	exit;
}

emergencyhousePublicRenderHeader($langs->trans('PasswordlessLogin'), null, 'login');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('PasswordlessLogin').'</h1></div>';
emergencyhousePublicAlert('ErrorTokenInvalid', 'error');
print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/login.php')).'">'.$langs->trans('BackToLogin').'</a>';
print '</section>';
emergencyhousePublicRenderFooter();

