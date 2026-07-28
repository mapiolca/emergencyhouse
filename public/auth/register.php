<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/consentservice.class.php');
dol_include_once('/emergencyhouse/class/notificationservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');

if ($emergencyhousePublicAccount instanceof EmergencyHousePublicAccount) {
	header('Location: '.emergencyhousePublicUrl('account/index.php'));
	exit;
}

$action = GETPOST('action', 'aZ09');
$errorKey = '';
$registered = GETPOSTINT('registered') > 0;
$dataPolicyEnabled = isModEnabled('datapolicy');
$termsEnabled = emergencyhousePublicHtmlHasContent(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_TERMS_HTML', ''));

if ($action === 'register' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$firstname = trim(GETPOST('firstname', 'restricthtml'));
	$lastname = trim(GETPOST('lastname', 'restricthtml'));
	$email = trim(GETPOST('email', 'restricthtml'));
	$phone = trim(GETPOST('phone', 'restricthtml'));
	$password = GETPOST('password', 'none');
	$adultConfirmed = GETPOSTINT('adult_confirmed') > 0;
	$termsAccepted = !$termsEnabled || GETPOSTINT('terms_accepted') > 0;
	$privacyAccepted = !$dataPolicyEnabled || GETPOSTINT('privacy_accepted') > 0;

	$identity = $emergencyhousePublicIp.'|'.EmergencyHouseEncryptionService::normalizeEmail($email);
	$rateLimitError = '';
	if (!emergencyhousePublicConsumeRateLimit($db, (int) $conf->entity, 'register', $identity, 5, 3600, $rateLimitError)) {
		$errorKey = $rateLimitError === 'ErrorRateLimitExceeded' ? 'ErrorRateLimitExceeded' : 'ErrorInternalError';
	} elseif (!$adultConfirmed || !$termsAccepted || !$privacyAccepted) {
		$errorKey = 'ErrorRequiredConsents';
	} else {
		$account = new EmergencyHousePublicAccount($db);
		$account->adult_confirmed = 1;
		$account->lang = in_array($langs->defaultlang, array('fr_FR', 'en_US'), true) ? $langs->defaultlang : 'fr_FR';
		$result = $account->create($firstname, $lastname, $email, $phone, $password);
		if ($result <= 0) {
			$errorKey = !empty($account->error) ? $account->error : 'ErrorAccountCreation';
		} else {
			$consent = new EmergencyHouseConsentService($db);
			$consentVersion = getDolGlobalString('EMERGENCYHOUSE_GLOBAL_CONSENT_VERSION', '1.0');
			$proof = hash('sha256', $account->public_uuid.'|'.$consentVersion.'|'.dol_now());
			$consentResult = 0;
			$requiredConsentCount = 0;
			if ($termsEnabled) {
				$consentResult += $consent->setConsent((int) $account->entity, (int) $account->id, null, 'terms', $consentVersion, true, $proof);
				$requiredConsentCount++;
			}
			if ($dataPolicyEnabled) {
				$consentResult += $consent->setConsent((int) $account->entity, (int) $account->id, null, 'privacy', $consentVersion, true, $proof);
				$requiredConsentCount++;
			}
			$verificationToken = $emergencyhousePublicAuth->issueToken(
				$account,
				'email_verification',
				max(15, getDolGlobalInt('EMERGENCYHOUSE_TOKEN_TTL_MINUTES', 30)) * 60
			);
			if ($consentResult < $requiredConsentCount || !is_string($verificationToken)) {
				$errorKey = 'ErrorAccountVerificationPreparation';
			} else {
				$notification = new EmergencyHouseNotificationService($db);
				$link = emergencyhousePublicAbsoluteUrl('auth/verify.php', array('verification' => $verificationToken));
				$queued = $notification->queueForAccount(
					$account,
					null,
					'account_verification',
					'account_verification',
					array(
						'FIRSTNAME' => $firstname,
						'VERIFY_URL' => $link,
						'SERVICE_NAME' => $langs->trans('EmergencyHouse'),
					),
					'account-verification|'.$account->id.'|'.$verificationToken,
					10,
					true
				);
				if ($queued <= 0) {
					$errorKey = 'ErrorVerificationEmailQueue';
				} else {
					header('Location: '.emergencyhousePublicUrl('auth/register.php', array('registered' => 1)));
					exit;
				}
			}
		}
	}
}

emergencyhousePublicRenderHeader($langs->trans('CreateAccount'), null, 'register');
print '<section class="eh-shell eh-section">';
print '<div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('PublicAccount').'</p><h1>'.$langs->trans('CreateYourAccount').'</h1>';
print '<p>'.$langs->trans('AccountCreationIntroduction').'</p></div>';
if ($registered) {
	emergencyhousePublicAlert('RegistrationVerificationSent', 'success');
}
if ($errorKey !== '') {
	emergencyhousePublicAlert($errorKey, 'error');
}
print '<form class="eh-form" method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/register.php')).'" data-disable-on-submit>';
print emergencyhousePublicCsrfFields();
print '<input type="hidden" name="action" value="register">';
print '<div class="eh-form-section"><h2>'.$langs->trans('YourIdentity').'</h2><p>'.$langs->trans('AdultIdentityOnlyHelp').'</p>';
print '<div class="eh-field-grid">';
print '<div class="eh-field"><label for="firstname">'.$langs->trans('Firstname').'</label><input id="firstname" name="firstname" required autocomplete="given-name" maxlength="80"></div>';
print '<div class="eh-field"><label for="lastname">'.$langs->trans('Lastname').'</label><input id="lastname" name="lastname" required autocomplete="family-name" maxlength="80"></div>';
print '<div class="eh-field"><label for="email">'.$langs->trans('Email').'</label><input id="email" type="email" name="email" required autocomplete="email" maxlength="255" inputmode="email"></div>';
print '<div class="eh-field"><label for="phone">'.$langs->trans('Phone').'</label><input id="phone" type="tel" name="phone" autocomplete="tel" maxlength="40" inputmode="tel"><span class="eh-help">'.$langs->trans('PhoneInternationalFormatHelp').'</span></div>';
print '<div class="eh-field eh-field-full"><label for="password">'.$langs->trans('Password').'</label><input id="password" type="password" name="password" required autocomplete="new-password" minlength="12" maxlength="4096"><span class="eh-help">'.$langs->trans('PasswordPolicyHelp').'</span></div>';
print '</div></div>';
print '<div class="eh-form-section"><h2>'.$langs->trans('YourCommitments').'</h2>';
print '<label class="eh-switch"><span>'.$langs->trans('ConfirmAdultAge').'</span><input type="checkbox" role="switch" name="adult_confirmed" value="1" required></label>';
if ($termsEnabled) {
	$termsLink = '<a href="'.dol_escape_htmltag(emergencyhousePublicUrl('terms.php')).'" target="_blank" rel="noopener noreferrer">'
		.$langs->trans('TermsOfUse').'</a>';
	print '<label class="eh-switch"><span>'.$langs->trans('AcceptTermsOfUsePrefix').' '.$termsLink.'.</span>';
	print '<input type="checkbox" role="switch" name="terms_accepted" value="1" required></label>';
}
if ($dataPolicyEnabled) {
	print '<label class="eh-switch"><span>'.$langs->trans('AcceptPrivacyPolicy').'</span><input type="checkbox" role="switch" name="privacy_accepted" value="1" required></label>';
}
print '</div>';
print '<div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('CreateAccount').'</button></div>';
print '</form>';
print '<p>'.$langs->trans('AlreadyRegistered').' <a href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/login.php')).'">'.$langs->trans('Login').'</a></p>';
print '</section>';
emergencyhousePublicRenderFooter();
