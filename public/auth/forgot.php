<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/notificationservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');

$action = GETPOST('action', 'aZ09');
$sent = false;
$errorKey = '';
if ($action === 'request' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim(GETPOST('email', 'restricthtml'));
	$identity = $emergencyhousePublicIp.'|'.EmergencyHouseEncryptionService::normalizeEmail($email);
	$rateLimitError = '';
	if (!emergencyhousePublicConsumeRateLimit($db, (int) $conf->entity, 'password-reset', $identity, 5, 3600, $rateLimitError)) {
		$errorKey = $rateLimitError === 'ErrorRateLimitExceeded' ? 'ErrorRateLimitExceeded' : 'ErrorInternalError';
	} else {
		$account = new EmergencyHousePublicAccount($db);
		if ($account->fetchByEmail($email) > 0 && $account->status === EmergencyHousePublicAccount::STATUS_ACTIVE) {
			$resetToken = $emergencyhousePublicAuth->issueToken($account, 'password_reset', 30 * 60);
			if (is_string($resetToken)) {
				$profile = $account->getDecryptedProfile();
				$notification = new EmergencyHouseNotificationService($db);
				$notification->sendForAccount(
					$account,
					null,
					'password_reset',
					array(
						'FIRSTNAME' => is_array($profile) ? $profile['firstname'] : '',
						'RESET_URL' => emergencyhousePublicAbsoluteUrl('auth/reset.php', array('reset_token' => $resetToken)),
						'SERVICE_NAME' => $langs->trans('EmergencyHouse'),
					),
					'password-reset-'.$account->id
				);
			}
		}
		$sent = true;
	}
}

emergencyhousePublicRenderHeader($langs->trans('ForgotPassword'), null, 'login');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('ForgotPassword').'</h1><p>'.$langs->trans('ForgotPasswordHelp').'</p></div>';
if ($sent) emergencyhousePublicAlert('PasswordResetGenericConfirmation', 'success');
if ($errorKey !== '') emergencyhousePublicAlert($errorKey, 'error');
print '<form class="eh-form" method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/forgot.php')).'" data-disable-on-submit>';
print emergencyhousePublicCsrfFields().'<input type="hidden" name="action" value="request">';
print '<div class="eh-field"><label for="email">'.$langs->trans('Email').'</label><input id="email" type="email" name="email" required autocomplete="email"></div>';
print '<div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('SendResetLink').'</button></div></form>';
print '</section>';
emergencyhousePublicRenderFooter();
