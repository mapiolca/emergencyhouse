<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/emergencyhouse/class/campaign.class.php');

$action = GETPOST('action', 'aZ09');
$submitted = GETPOSTINT('submitted') === 1;
$captchaAvailable = getDolGlobalInt('MAIN_SECURITY_ENABLECAPTCHA', 0) > 0
	&& function_exists('imagecreate')
	&& function_exists('imagepng');
$rateLimit = max(1, getDolGlobalInt('EMERGENCYHOUSE_RATE_LIMIT_HOUR', 20));
$errors = array();

$requesterName = trim(GETPOST('requester_name', 'restricthtml'));
$requesterEmail = trim(GETPOST('requester_email', 'restricthtml'));
$requesterPhone = trim(GETPOST('requester_phone', 'restricthtml'));
$organization = trim(GETPOST('organization', 'restricthtml'));
$campaignLabel = trim(GETPOST('campaign_label', 'restricthtml'));
$campaignTypeId = GETPOSTINT('fk_campaign_type');
$affectedArea = trim(GETPOST('affected_area', 'restricthtml'));
$estimatedPeople = GETPOSTINT('estimated_people');
$description = trim(GETPOST('description_public', 'restricthtml'));
$dateStart = emergencyhousePublicGetNativeDate('date_start');
$dateEnd = emergencyhousePublicGetNativeDate('date_end', true);

if ($action === '' && $emergencyhousePublicAccount instanceof EmergencyHousePublicAccount) {
	$profile = $emergencyhousePublicAccount->getDecryptedProfile();
	if (is_array($profile)) {
		$requesterName = trim($profile['firstname'].' '.$profile['lastname']);
		$requesterEmail = (string) $profile['email'];
		$requesterPhone = (string) $profile['phone'];
	}
}
if ($action === '') {
	$dateStart = dol_now();
	$dateEnd = null;
}

$campaignTypes = array();
$sqlTypes = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_campaign_type';
$sqlTypes .= ' WHERE entity = '.((int) $conf->entity).' AND active = 1 ORDER BY position, label';
$resqlTypes = $db->query($sqlTypes);
if ($resqlTypes) {
	while (is_object($campaignType = $db->fetch_object($resqlTypes))) {
		$campaignTypes[(int) $campaignType->rowid] = $langs->trans((string) $campaignType->label);
	}
} else {
	$errors[] = 'ErrorInternalError';
	dol_syslog(__FILE__.': unable to load campaign types: '.$db->lasterror(), LOG_ERR);
}

if ($action === 'request_campaign' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$publicCsrfValid = !($emergencyhousePublicAccount instanceof EmergencyHousePublicAccount)
		|| emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'request_campaign');
	if (!$publicCsrfValid) {
		$errors[] = 'ErrorInvalidCsrfToken';
	} elseif (!$captchaAvailable) {
		$errors[] = 'ErrorCampaignRequestCaptchaUnavailable';
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
			$errors[] = 'ErrorBadValueForCode';
		}
	}

	if ($requesterName === '' || dol_strlen($requesterName) > 255) {
		$errors[] = 'ErrorCampaignRequestInvalid';
	}
	if (filter_var($requesterEmail, FILTER_VALIDATE_EMAIL) === false || dol_strlen($requesterEmail) > 255) {
		$errors[] = 'ErrorInvalidEmail';
	}
	if ($requesterPhone === '' || dol_strlen($requesterPhone) > 64) {
		$errors[] = 'ErrorCampaignRequestInvalid';
	}
	if (dol_strlen($organization) > 255) {
		$errors[] = 'ErrorCampaignRequestInvalid';
	}
	if ($campaignLabel === '' || dol_strlen($campaignLabel) > 255) {
		$errors[] = 'ErrorCampaignRequestInvalid';
	}
	if ($campaignTypeId <= 0 || !isset($campaignTypes[$campaignTypeId])) {
		$errors[] = 'ErrorCampaignRequestInvalid';
	}
	if ($affectedArea === '' || dol_strlen($affectedArea) > 255) {
		$errors[] = 'ErrorCampaignRequestInvalid';
	}
	if ($estimatedPeople < 0 || $estimatedPeople > 10000000) {
		$errors[] = 'ErrorCampaignRequestInvalid';
	}
	if (dol_strlen($description) < 20 || dol_strlen($description) > 5000) {
		$errors[] = 'ErrorCampaignRequestInvalid';
	}
	if (
		!is_int($dateStart)
		|| $dateStart <= 0
		|| ($dateEnd !== null && (!is_int($dateEnd) || $dateEnd <= 0 || $dateEnd < $dateStart))
	) {
		$errors[] = 'ErrorInvalidPeriod';
	}

	if (empty($errors)) {
		$rateLimitError = '';
		if (!emergencyhousePublicConsumeRateLimit(
			$db,
			(int) $conf->entity,
			'public_campaign_request',
			$emergencyhousePublicIp,
			$rateLimit,
			3600,
			$rateLimitError
		)) {
			$errors[] = $rateLimitError === 'ErrorRateLimitExceeded'
				? 'ErrorRateLimitExceeded'
				: 'ErrorInternalError';
		} else {
			$timezone = getDolGlobalString('EMERGENCYHOUSE_DEFAULT_TIMEZONE', date_default_timezone_get());
			if (!in_array($timezone, timezone_identifiers_list(), true)) {
				$timezone = date_default_timezone_get();
			}
			$campaign = new EmergencyHouseCampaign($db);
			$campaign->entity = (int) $conf->entity;
			$campaign->label = $campaignLabel;
			$campaign->fk_campaign_type = $campaignTypeId;
			$campaign->description_public = $description;
			$campaign->official_instructions = '';
			$campaign->coordinator_name = $requesterName;
			$campaign->official_phone = $requesterPhone;
			$campaign->official_email = $requesterEmail;
			$campaign->date_start = $dateStart;
			$campaign->date_end = $dateEnd;
			$campaign->timezone = $timezone;
			$campaign->public_visibility_mode = 'private';
			$campaign->verification_policy = 'operator_validation';
			$campaign->default_radius = max(1, getDolGlobalInt('EMERGENCYHOUSE_MATCH_DEFAULT_RADIUS_KM', 50));
			$campaign->matching_config_version = '1';
			$campaign->retention_days = max(1, getDolGlobalInt('EMERGENCYHOUSE_RETENTION_OPERATIONAL_DAYS', 90));
			$campaign->consent_version = getDolGlobalString('EMERGENCYHOUSE_GLOBAL_CONSENT_VERSION', '1.0');
			$campaign->privacy_url = emergencyhousePublicLegalPageIsPublished(
				'EMERGENCYHOUSE_PUBLIC_PRIVACY_ENABLED',
				'EMERGENCYHOUSE_PUBLIC_PRIVACY_HTML'
			) ? emergencyhousePublicUrl('privacy.php') : '';
			$campaign->terms_url = emergencyhousePublicLegalPageIsPublished(
				'EMERGENCYHOUSE_PUBLIC_TERMS_ENABLED',
				'EMERGENCYHOUSE_PUBLIC_TERMS_HTML'
			) ? emergencyhousePublicUrl('terms.php') : '';
			$campaign->robots_index = 0;
			$campaign->status = EmergencyHouseCampaign::STATUS_DRAFT;
			$noteLines = array(
				$langs->trans('CampaignRequestOriginNote'),
				$langs->trans('CampaignRequestAffectedArea').': '.$affectedArea,
			);
			if ($organization !== '') {
				$noteLines[] = $langs->trans('CampaignRequestOrganization').': '.$organization;
			}
			if ($estimatedPeople > 0) {
				$noteLines[] = $langs->trans('CampaignRequestEstimatedPeople').': '.$estimatedPeople;
			}
			$campaign->note_private = implode("\n", $noteLines);
			$campaign->context['trigger_reason'] = 'public_creation_request';
			$campaign->context['request_origin'] = 'public_portal';

			$triggerUser = emergencyhousePublicTriggerUser($db);
			$result = $campaign->create($triggerUser);
			if ($result > 0) {
				emergencyhousePublicAnalyticsEvent('campaign_requested',
					true,
					'campaign_request',
					$emergencyhousePublicAccount
				);
				header('Location: '.emergencyhousePublicUrl('campaign-request.php', array('submitted' => 1)));
				exit;
			}
			$errors[] = 'ErrorInternalError';
			dol_syslog(__FILE__.': campaign request creation failed: '.$campaign->error, LOG_ERR);
		}
	}
} elseif ($action !== '') {
	$errors[] = 'ErrorInvalidAction';
}

$form = new Form($db);
emergencyhousePublicRenderHeader(
	$langs->trans('RequestCampaignCreation'),
	$emergencyhousePublicAccount,
	'campaign_request',
	false,
	false,
	array('description' => $langs->trans('CampaignRequestIntroduction'))
);
print '<section class="eh-shell eh-section">';
print '<div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('CampaignRequestEyebrow').'</p>';
print '<h1>'.$langs->trans('RequestCampaignCreation').'</h1>';
print '<p>'.$langs->trans('CampaignRequestIntroduction').'</p></div>';

if ($submitted) {
	emergencyhousePublicAlert('CampaignRequestSent', 'success');
	print '<p><a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl()).'">'
		.$langs->trans('Home').'</a></p>';
} else {
	foreach (array_unique($errors) as $errorKey) {
		emergencyhousePublicAlert($errorKey, 'error');
	}
	if (!$captchaAvailable) {
		emergencyhousePublicAlert('CampaignRequestCaptchaUnavailable', 'warning');
	} else {
		print '<form class="eh-form eh-form-wide" method="POST" action="'
			.dol_escape_htmltag(emergencyhousePublicUrl('campaign-request.php')).'" data-disable-on-submit>';
		print emergencyhousePublicCsrfFields(
			$emergencyhousePublicAccount instanceof EmergencyHousePublicAccount ? $emergencyhousePublicAuth : null,
			$emergencyhousePublicAccount instanceof EmergencyHousePublicAccount ? 'request_campaign' : ''
		);
		print '<input type="hidden" name="action" value="request_campaign">';

		print '<section class="eh-form-section" aria-labelledby="campaign-request-contact">';
		print '<h2 id="campaign-request-contact">'.$langs->trans('CampaignRequestContactSection').'</h2>';
		print '<p>'.$langs->trans('CampaignRequestContactHelp').'</p><div class="eh-field-grid">';
		print '<div class="eh-field"><label for="requester_name">'.$langs->trans('Name').'</label>';
		print '<input id="requester_name" name="requester_name" required maxlength="255" autocomplete="name" value="'
			.dol_escape_htmltag($requesterName).'"></div>';
		print '<div class="eh-field"><label for="organization">'.$langs->trans('CampaignRequestOrganization').'</label>';
		print '<input id="organization" name="organization" maxlength="255" autocomplete="organization" value="'
			.dol_escape_htmltag($organization).'"></div>';
		print '<div class="eh-field"><label for="requester_email">'.$langs->trans('Email').'</label>';
		print '<input id="requester_email" type="email" name="requester_email" required maxlength="255" autocomplete="email" value="'
			.dol_escape_htmltag($requesterEmail).'"></div>';
		print '<div class="eh-field"><label for="requester_phone">'.$langs->trans('Phone').'</label>';
		print '<input id="requester_phone" type="tel" name="requester_phone" required maxlength="64" autocomplete="tel" value="'
			.dol_escape_htmltag($requesterPhone).'"></div></div></section>';

		print '<section class="eh-form-section" aria-labelledby="campaign-request-details">';
		print '<h2 id="campaign-request-details">'.$langs->trans('CampaignRequestDetailsSection').'</h2>';
		print '<p>'.$langs->trans('CampaignRequestReviewNotice').'</p><div class="eh-field-grid">';
		print '<div class="eh-field eh-field-full"><label for="campaign_label">'.$langs->trans('CampaignLabel').'</label>';
		print '<input id="campaign_label" name="campaign_label" required maxlength="255" value="'
			.dol_escape_htmltag($campaignLabel).'"></div>';
		print '<div class="eh-field"><label for="fk_campaign_type">'.$langs->trans('CampaignType').'</label>';
		print '<select class="eh-select2" id="fk_campaign_type" name="fk_campaign_type" required>';
		print '<option value="">'.$langs->trans('SelectAnOption').'</option>';
		foreach ($campaignTypes as $typeId => $typeLabel) {
			print '<option value="'.((int) $typeId).'"'.($campaignTypeId === $typeId ? ' selected' : '').'>'
				.dol_escape_htmltag($typeLabel).'</option>';
		}
		print '</select></div>';
		print '<div class="eh-field"><label for="affected_area">'.$langs->trans('CampaignRequestAffectedArea').'</label>';
		print '<input id="affected_area" name="affected_area" required maxlength="255" value="'
			.dol_escape_htmltag($affectedArea).'"></div>';
		print '<div class="eh-field"><label for="estimated_people">'.$langs->trans('CampaignRequestEstimatedPeople').'</label>';
		print '<input id="estimated_people" type="number" name="estimated_people" min="0" max="10000000" value="'
			.($estimatedPeople > 0 ? ((int) $estimatedPeople) : '').'"></div>';
		print '<div class="eh-field"><label>'.$langs->trans('DateStart').'</label>'
			.emergencyhousePublicNativeDateSelector($form, $dateStart, 'date_start').'</div>';
		print '<div class="eh-field"><label>'.$langs->trans('DateEnd').'</label>'
			.emergencyhousePublicNativeDateSelector($form, $dateEnd, 'date_end', true).'</div>';
		print '<div class="eh-field eh-field-full"><label for="description_public">'.$langs->trans('CampaignDescription').'</label>';
		print '<textarea id="description_public" name="description_public" required minlength="20" maxlength="5000">'
			.dol_escape_htmltag($description).'</textarea>';
		print '<span class="eh-help">'.$langs->trans('CampaignRequestDescriptionHelp').'</span></div>';
		print '<div class="eh-field eh-field-full"><label for="securitycode">'.$langs->trans('SecurityCode').'</label>';
		print '<div class="eh-captcha"><input id="securitycode" name="code" required minlength="5" maxlength="5"';
		print ' pattern="[A-Za-z0-9]{5}" autocomplete="off" autocapitalize="none" spellcheck="false">';
		print '<img src="'.dol_escape_htmltag(emergencyhousePublicUrl('captcha.php', array('refresh' => dol_now()))).'"';
		print ' width="80" height="32" id="img_securitycode" alt="'
			.dol_escape_htmltag($langs->trans('SecurityCodeImageAlternative')).'">';
		print '<button class="eh-button eh-button-secondary eh-button-small" type="button" data-captcha-refresh';
		print ' data-captcha-url="'.dol_escape_htmltag(emergencyhousePublicUrl('captcha.php')).'">';
		print $langs->trans('RefreshSecurityCode').'</button></div></div></div></section>';
		print '<div class="eh-form-actions"><button class="eh-button" type="submit">'
			.$langs->trans('CampaignRequestSubmit').'</button></div></form>';
	}
}
print '</section>';
emergencyhousePublicRenderFooter();
