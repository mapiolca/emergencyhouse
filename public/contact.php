<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

dol_include_once('/emergencyhouse/class/publiccontactservice.class.php');

$action = GETPOST('action', 'aZ09');
$supportEmail = trim(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL', ''));
$supportPhone = trim(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE', ''));
$captchaAvailable = getDolGlobalInt('MAIN_SECURITY_ENABLECAPTCHA', 0) > 0
	&& function_exists('imagecreate')
	&& function_exists('imagepng');
$supportEmailAvailable = filter_var($supportEmail, FILTER_VALIDATE_EMAIL) !== false;
$contactRateLimit = max(10, getDolGlobalInt('EMERGENCYHOUSE_RATE_LIMIT_HOUR', 20));
$errorKey = '';
$sent = false;

$name = trim(GETPOST('contact_name', 'restricthtml'));
$email = trim(GETPOST('contact_email', 'restricthtml'));
$phone = trim(GETPOST('contact_phone', 'restricthtml'));
$subject = trim(GETPOST('contact_subject', 'restricthtml'));
$message = trim(GETPOST('contact_message', 'restricthtml'));

if (
	$action === ''
	&& $emergencyhousePublicAccount instanceof EmergencyHousePublicAccount
) {
	$profile = $emergencyhousePublicAccount->getDecryptedProfile();
	if (is_array($profile)) {
		$name = trim($profile['firstname'].' '.$profile['lastname']);
		$email = (string) $profile['email'];
		$phone = (string) $profile['phone'];
	}
}

if ($action === 'send' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$publicCsrfValid = !($emergencyhousePublicAccount instanceof EmergencyHousePublicAccount)
		|| emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'contact_send');
	if (!$publicCsrfValid) {
		$errorKey = 'ErrorInvalidCsrfToken';
	} elseif (!$supportEmailAvailable) {
		$errorKey = 'ErrorSupportEmailNotConfigured';
	} elseif (!$captchaAvailable) {
		$errorKey = 'ErrorContactCaptchaUnavailable';
	} else {
		$captchaCode = trim(GETPOST('code', 'alphanohtml'));
		$expectedCaptcha = isset($_SESSION['dol_antispam_value']) && is_string($_SESSION['dol_antispam_value'])
			? $_SESSION['dol_antispam_value']
			: '';
		unset($_SESSION['dol_antispam_value']);
		if (
			$expectedCaptcha === ''
			|| $captchaCode === ''
			|| !hash_equals(strtolower($expectedCaptcha), strtolower($captchaCode))
		) {
			$errorKey = 'ErrorBadValueForCode';
		} else {
			$rateLimitError = '';
			if (!emergencyhousePublicConsumeRateLimit(
				$db,
				(int) $conf->entity,
				'public_contact',
				$emergencyhousePublicIp,
				$contactRateLimit,
				3600,
				$rateLimitError
			)) {
				$errorKey = $rateLimitError === 'ErrorRateLimitExceeded'
					? 'ErrorRateLimitExceeded'
					: 'ErrorInternalError';
			} else {
				$uploadedFiles = isset($_FILES['attachments']) && is_array($_FILES['attachments'])
					? $_FILES['attachments']
					: array();
				$contactService = new EmergencyHousePublicContactService();
				$result = $contactService->send($name, $email, $phone, $subject, $message, $uploadedFiles);
				if ($result > 0) {
					$sent = true;
					$name = '';
					$email = '';
					$phone = '';
					$subject = '';
					$message = '';
				} else {
					$errorKey = $contactService->error !== '' ? $contactService->error : 'ErrorMailSend';
				}
			}
		}
	}
} elseif ($action !== '') {
	$errorKey = 'ErrorInvalidAction';
}

emergencyhousePublicRenderHeader(
	$langs->trans('ContactUs'),
	$emergencyhousePublicAccount,
	'contact'
);
print '<section class="eh-shell eh-section">';
print '<div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('Support').'</p>';
print '<h1>'.$langs->trans('ContactUs').'</h1><p>'.$langs->trans('PublicContactIntroduction').'</p></div>';

print '<div class="eh-contact-layout"><aside class="eh-contact-details" aria-labelledby="contact-details-title">';
print '<div class="eh-card"><h2 id="contact-details-title">'.$langs->trans('SupportContactDetails').'</h2>';
if ($supportPhone !== '') {
	$phoneUri = preg_replace('/[^0-9+]/', '', $supportPhone);
	print '<p><strong>'.$langs->trans('Phone').'</strong><br>';
	print '<a class="eh-contact-link" href="tel:'.dol_escape_htmltag(is_string($phoneUri) ? $phoneUri : '').'">'
		.dol_escape_htmltag($supportPhone).'</a></p>';
}
if ($supportEmailAvailable) {
	print '<p><strong>'.$langs->trans('Email').'</strong><br>';
	print '<a class="eh-contact-link" href="mailto:'.dol_escape_htmltag($supportEmail).'">'
		.dol_escape_htmltag($supportEmail).'</a></p>';
}
if ($supportPhone === '' && !$supportEmailAvailable) {
	print '<p>'.$langs->trans('SupportContactDetailsUnavailable').'</p>';
}
print '<p class="eh-help">'.$langs->trans('EmergencyContactReminder').'</p></div></aside>';

print '<div>';
if ($sent) {
	emergencyhousePublicAlert('ContactRequestSent', 'success');
}
if ($errorKey !== '') {
	emergencyhousePublicAlert($errorKey, 'error');
}
if (!$supportEmailAvailable) {
	emergencyhousePublicAlert('ContactFormEmailUnavailable', 'warning');
} elseif (!$captchaAvailable) {
	emergencyhousePublicAlert('ContactFormCaptchaUnavailable', 'warning');
} elseif (!$sent) {
	print '<form class="eh-form eh-form-wide" method="POST" enctype="multipart/form-data"';
	print ' action="'.dol_escape_htmltag(emergencyhousePublicUrl('contact.php')).'" data-disable-on-submit>';
	print emergencyhousePublicCsrfFields(
		$emergencyhousePublicAccount instanceof EmergencyHousePublicAccount ? $emergencyhousePublicAuth : null,
		$emergencyhousePublicAccount instanceof EmergencyHousePublicAccount ? 'contact_send' : ''
	);
	print '<input type="hidden" name="action" value="send">';
	print '<div class="eh-field-grid">';
	print '<div class="eh-field"><label for="contact_name">'.$langs->trans('Name').'</label>';
	print '<input id="contact_name" name="contact_name" required maxlength="160" autocomplete="name" value="'.dol_escape_htmltag($name).'"></div>';
	print '<div class="eh-field"><label for="contact_email">'.$langs->trans('Email').'</label>';
	print '<input id="contact_email" type="email" name="contact_email" required maxlength="255" autocomplete="email" inputmode="email" value="'.dol_escape_htmltag($email).'"></div>';
	print '<div class="eh-field"><label for="contact_phone">'.$langs->trans('Phone').'</label>';
	print '<input id="contact_phone" type="tel" name="contact_phone" maxlength="40" autocomplete="tel" inputmode="tel" value="'.dol_escape_htmltag($phone).'"></div>';
	print '<div class="eh-field"><label for="contact_subject">'.$langs->trans('Subject').'</label>';
	print '<input id="contact_subject" name="contact_subject" required maxlength="180" value="'.dol_escape_htmltag($subject).'"></div>';
	print '<div class="eh-field eh-field-full"><label for="contact_message">'.$langs->trans('Message').'</label>';
	print '<textarea id="contact_message" name="contact_message" required minlength="20" maxlength="5000">'.dol_escape_htmltag($message).'</textarea>';
	print '<span class="eh-help">'.$langs->trans('PublicContactMessageHelp').'</span></div>';
	print '<div class="eh-field eh-field-full"><label for="attachments">'.$langs->trans('PhotosOrScreenshots').'</label>';
	print '<input id="attachments" type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple>';
	print '<span class="eh-help">'.$langs->trans('ContactAttachmentsHelp').'</span></div>';
	print '<div class="eh-field eh-field-full"><label for="securitycode">'.$langs->trans('SecurityCode').'</label>';
	print '<div class="eh-captcha">';
	print '<input id="securitycode" name="code" required minlength="5" maxlength="5" pattern="[A-Za-z0-9]{5}"';
	print ' autocomplete="off" autocapitalize="none" spellcheck="false" inputmode="text">';
	print '<img src="'.dol_escape_htmltag(emergencyhousePublicUrl('captcha.php', array('refresh' => dol_now()))).'"';
	print ' width="80" height="32" id="img_securitycode" alt="'.dol_escape_htmltag($langs->trans('SecurityCodeImageAlternative')).'">';
	print '<button class="eh-button eh-button-secondary eh-button-small" type="button" data-captcha-refresh';
	print ' data-captcha-url="'.dol_escape_htmltag(emergencyhousePublicUrl('captcha.php')).'">';
	print $langs->trans('RefreshSecurityCode').'</button></div></div>';
	print '</div>';
	print '<div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('SendMessage').'</button></div>';
	print '</form>';
}
print '</div></div></section>';
emergencyhousePublicRenderFooter();
