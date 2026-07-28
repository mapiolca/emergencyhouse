<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/notificationservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');

if ($emergencyhousePublicAccount instanceof EmergencyHousePublicAccount) {
	header('Location: '.emergencyhousePublicUrl('account/index.php'));
	exit;
}

$action = GETPOST('action', 'aZ09');
$errorKey = '';
$noticeKey = '';
$next = emergencyhousePublicSafeReturnUrl(GETPOST('next', 'restricthtml'));

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('login', 'magic'), true)) {
	$email = trim(GETPOST('email', 'restricthtml'));
	$identity = $emergencyhousePublicIp.'|'.EmergencyHouseEncryptionService::normalizeEmail($email);
	$rateLimitError = '';
	if (!emergencyhousePublicConsumeRateLimit($db, (int) $conf->entity, 'login', $identity, 10, 3600, $rateLimitError)) {
		$errorKey = $rateLimitError === 'ErrorRateLimitExceeded' ? 'ErrorRateLimitExceeded' : 'ErrorInternalError';
	} elseif ($action === 'login') {
		$password = GETPOST('password', 'none');
		$account = $emergencyhousePublicAuth->login($email, $password, $emergencyhousePublicIp, $emergencyhousePublicUserAgent);
		if (!$account instanceof EmergencyHousePublicAccount) {
			$errorKey = $emergencyhousePublicAuth->error === 'ErrorAuthenticationFailed'
				? 'PublicAuthenticationFailed'
				: 'ErrorInternalError';
		} else {
			emergencyhousePublicAnalyticsEvent('login_success', true, 'login', $account);
			header('Location: '.($next !== '' ? $next : emergencyhousePublicUrl('account/index.php')));
			exit;
		}
	} else {
		$account = new EmergencyHousePublicAccount($db);
		if ($account->fetchByEmail($email) > 0 && $account->status === EmergencyHousePublicAccount::STATUS_ACTIVE && !empty($account->email_verified)) {
			$magicToken = $emergencyhousePublicAuth->issueToken($account, 'magic_login', 15 * 60);
			if (is_string($magicToken)) {
				$profile = $account->getDecryptedProfile();
				$notification = new EmergencyHouseNotificationService($db);
				$notification->sendForAccount(
					$account,
					null,
					'magic_login',
					array(
						'FIRSTNAME' => is_array($profile) ? $profile['firstname'] : '',
						'LOGIN_URL' => emergencyhousePublicAbsoluteUrl('auth/magic.php', array('login_token' => $magicToken)),
						'SERVICE_NAME' => $langs->trans('EmergencyHouse'),
					),
					'magic-login-'.$account->id
				);
			}
		}
		$noticeKey = 'MagicLinkGenericConfirmation';
	}
}

emergencyhousePublicRenderHeader($langs->trans('Login'), null, 'login');
print '<section class="eh-shell eh-section">';
print '<div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('PublicAccount').'</p><h1>'.$langs->trans('LoginToYourSpace').'</h1>';
print '<p>'.$langs->trans('LoginIntroduction').'</p></div>';
if ($errorKey !== '') emergencyhousePublicAlert($errorKey, 'error');
if ($noticeKey !== '') emergencyhousePublicAlert($noticeKey, 'success');
print '<form class="eh-form" method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/login.php')).'" data-disable-on-submit>';
print emergencyhousePublicCsrfFields();
print '<input type="hidden" name="action" value="login">';
print '<input type="hidden" name="next" value="'.dol_escape_htmltag($next).'">';
print '<div class="eh-field-grid">';
print '<div class="eh-field eh-field-full"><label for="email">'.$langs->trans('Email').'</label><input id="email" type="email" name="email" required autocomplete="email" inputmode="email"></div>';
print '<div class="eh-field eh-field-full"><label for="password">'.$langs->trans('Password').'</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>';
print '</div>';
print '<div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('Login').'</button></div>';
print '</form>';
print '<div class="eh-actions"><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/forgot.php')).'">'.$langs->trans('ForgotPassword').'</a>';
print '<a href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/resend.php')).'">'.$langs->trans('ResendVerificationEmail').'</a></div>';
print '<form class="eh-form eh-section-tight" method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/login.php')).'" data-disable-on-submit>';
print emergencyhousePublicCsrfFields();
print '<input type="hidden" name="action" value="magic">';
print '<h2>'.$langs->trans('PasswordlessLogin').'</h2><p>'.$langs->trans('PasswordlessLoginHelp').'</p>';
print '<div class="eh-field"><label for="magic_email">'.$langs->trans('Email').'</label><input id="magic_email" type="email" name="email" required autocomplete="email"></div>';
print '<div class="eh-form-actions"><button class="eh-button eh-button-secondary" type="submit">'.$langs->trans('SendLoginLink').'</button></div>';
print '</form>';
print '</section>';
emergencyhousePublicRenderFooter();
