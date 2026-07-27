<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/notificationservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');

$action = GETPOST('action', 'aZ09');
$sent = false;
$errorKey = '';
if ($action === 'resend' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim(GETPOST('email', 'restricthtml'));
	$identity = $emergencyhousePublicIp.'|'.EmergencyHouseEncryptionService::normalizeEmail($email);
	$rateLimitError = '';
	if (!emergencyhousePublicConsumeRateLimit($db, (int) $conf->entity, 'verification-resend', $identity, 4, 3600, $rateLimitError)) {
		$errorKey = $rateLimitError === 'ErrorRateLimitExceeded' ? 'ErrorRateLimitExceeded' : 'ErrorInternalError';
	} else {
		$account = new EmergencyHousePublicAccount($db);
		if ($account->fetchByEmail($email) > 0 && empty($account->email_verified) && $account->status === EmergencyHousePublicAccount::STATUS_PENDING) {
			$verificationToken = $emergencyhousePublicAuth->issueToken($account, 'email_verification', 30 * 60);
			if (is_string($verificationToken)) {
				$profile = $account->getDecryptedProfile();
				$notification = new EmergencyHouseNotificationService($db);
				$notification->queueForAccount(
					$account,
					null,
					'account_verification',
					'account_verification',
					array(
						'FIRSTNAME' => is_array($profile) ? $profile['firstname'] : '',
						'VERIFY_URL' => emergencyhousePublicAbsoluteUrl('auth/verify.php', array('verification' => $verificationToken)),
						'SERVICE_NAME' => $langs->trans('EmergencyHouse'),
					),
					'account-verification-resend|'.$account->id.'|'.$verificationToken,
					10
				);
			}
		}
		$sent = true;
	}
}

emergencyhousePublicRenderHeader($langs->trans('ResendVerificationEmail'), null, 'login');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('ResendVerificationEmail').'</h1></div>';
if ($sent) emergencyhousePublicAlert('VerificationResendGenericConfirmation', 'success');
if ($errorKey !== '') emergencyhousePublicAlert($errorKey, 'error');
print '<form class="eh-form" method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/resend.php')).'" data-disable-on-submit>';
print emergencyhousePublicCsrfFields().'<input type="hidden" name="action" value="resend">';
print '<div class="eh-field"><label for="email">'.$langs->trans('Email').'</label><input id="email" type="email" name="email" required autocomplete="email"></div>';
print '<div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('ResendVerificationEmail').'</button></div></form>';
print '</section>';
emergencyhousePublicRenderFooter();
