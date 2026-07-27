<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

$action = GETPOST('action', 'aZ09');
$token = GETPOST('reset_token', 'alphanohtml');
$errorKey = '';
$success = false;
if ($action === 'reset' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$password = GETPOST('password', 'none');
	$confirmation = GETPOST('password_confirmation', 'none');
	if ($password !== $confirmation) {
		$errorKey = 'ErrorPasswordConfirmation';
	} elseif (strlen($password) < 12 || strlen($password) > 4096 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
		$errorKey = 'ErrorPasswordPolicy';
	} else {
		$account = $emergencyhousePublicAuth->consumeToken($token, 'password_reset');
		if (!$account instanceof EmergencyHousePublicAccount || $account->setPassword($password) <= 0) {
			$errorKey = 'ErrorTokenInvalid';
		} else {
			$emergencyhousePublicAuth->revokeAllSessions($account);
			$success = true;
		}
	}
}

emergencyhousePublicRenderHeader($langs->trans('ResetPassword'), null, 'login');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('ResetPassword').'</h1></div>';
if ($success) {
	emergencyhousePublicAlert('PasswordResetSuccess', 'success');
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/login.php')).'">'.$langs->trans('Login').'</a>';
} else {
	if ($errorKey !== '') emergencyhousePublicAlert($errorKey, 'error');
	print '<form class="eh-form" method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/reset.php')).'" data-disable-on-submit>';
	print emergencyhousePublicCsrfFields().'<input type="hidden" name="action" value="reset">';
	print '<input type="hidden" name="reset_token" value="'.dol_escape_htmltag($token).'">';
	print '<div class="eh-field-grid">';
	print '<div class="eh-field eh-field-full"><label for="password">'.$langs->trans('NewPassword').'</label><input id="password" type="password" name="password" required minlength="12" maxlength="4096" autocomplete="new-password"></div>';
	print '<div class="eh-field eh-field-full"><label for="password_confirmation">'.$langs->trans('ConfirmPassword').'</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="12" maxlength="4096" autocomplete="new-password"></div>';
	print '</div><div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('ResetPassword').'</button></div></form>';
}
print '</section>';
emergencyhousePublicRenderFooter();
