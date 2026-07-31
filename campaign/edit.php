<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	exit;
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
dol_include_once('/emergencyhouse/class/campaign.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'campaign', 'write')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$object = new EmergencyHouseCampaign($db);
if ($id > 0) {
	if ($object->fetch($id) <= 0 || !emergencyhouseCanDo($user, 'campaign', 'write', $object)) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
}

if ($action === 'save') {
	$object->label = trim(GETPOST('label', 'restricthtml'));
	$object->fk_campaign_type = GETPOSTINT('fk_campaign_type') > 0 ? GETPOSTINT('fk_campaign_type') : null;
	$object->description_public = emergencyhouseNormalizeRichTextLineBreaks(GETPOST('description_public', 'restricthtml'));
	$object->official_instructions = emergencyhouseNormalizeRichTextLineBreaks(GETPOST('official_instructions', 'restricthtml'));
	$object->coordinator_name = trim(GETPOST('coordinator_name', 'restricthtml'));
	$object->official_phone = trim(GETPOST('official_phone', 'alphanohtml'));
	$object->official_email = trim(GETPOST('official_email', 'alphanohtml'));
	$object->date_start = emergencyhouseCampaignReadDate('date_start', true);
	$object->date_end = emergencyhouseCampaignReadDate('date_end', false);
	$object->timezone = GETPOST('timezone', 'alphanohtml');
	$object->public_visibility_mode = GETPOST('public_visibility_mode', 'aZ09');
	$object->verification_policy = GETPOST('verification_policy', 'aZ09');
	$object->default_radius = GETPOSTINT('default_radius');
	$object->retention_days = GETPOSTINT('retention_days');
	$object->consent_version = trim(GETPOST('consent_version', 'alphanohtml'));
	$object->banner_text = emergencyhouseNormalizeRichTextLineBreaks(GETPOST('banner_text', 'restricthtml'));
	$object->eligibility_text = emergencyhouseNormalizeRichTextLineBreaks(GETPOST('eligibility_text', 'restricthtml'));
	$object->privacy_url = trim(GETPOST('privacy_url', 'alphanohtml'));
	$object->terms_url = trim(GETPOST('terms_url', 'alphanohtml'));
	$object->robots_index = GETPOSTINT('robots_index') === 1 ? 1 : 0;
	$errors = array();
	if ($object->label === '' || $object->coordinator_name === '' || $object->official_phone === '' || empty($object->date_start)) {
		$errors[] = 'ErrorMissingRequiredField';
	}
	if ($object->official_email !== '' && filter_var($object->official_email, FILTER_VALIDATE_EMAIL) === false) {
		$errors[] = 'ErrorInvalidEmail';
	}
	if (!in_array($object->timezone, timezone_identifiers_list(), true)) {
		$errors[] = 'ErrorInvalidTimezone';
	}
	if (!in_array($object->public_visibility_mode, array('offers', 'offers_requests', 'private'), true)) {
		$errors[] = 'ErrorInvalidVisibility';
	}
	if (!in_array($object->verification_policy, array('operator_validation', 'email_verification', 'manual'), true)) {
		$errors[] = 'ErrorInvalidVerificationPolicy';
	}
	if ($object->default_radius <= 0 || $object->default_radius > 1000 || $object->retention_days < 1) {
		$errors[] = 'ErrorInvalidConfiguration';
	}
	if ((!emergencyhouseCampaignUrlIsAllowed($object->privacy_url))
		|| (!emergencyhouseCampaignUrlIsAllowed($object->terms_url))) {
		$errors[] = 'ErrorInvalidUrl';
	}
	if (!empty($object->date_end) && $object->date_end < $object->date_start) {
		$errors[] = 'ErrorInvalidPeriod';
	}
	if (empty($errors)) {
		if ($id > 0) {
			$object->context['trigger_reason'] = 'operator_edit';
			$result = $object->update($user);
		} else {
			$object->entity = (int) $conf->entity;
			$object->status = EmergencyHouseCampaign::STATUS_DRAFT;
			$result = $object->create($user);
		}
		if ($result > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.dol_buildpath('/emergencyhouse/campaign/card.php', 1).'?id='.((int) $object->id));
			exit;
		}
		setEventMessages(emergencyhouseGetUserErrorMessage((string) $object->error), null, 'errors');
	} else {
		foreach ($errors as $errorKey) {
			setEventMessages($langs->trans($errorKey), null, 'errors');
		}
	}
}

$form = new Form($db);
$campaignTypes = array('' => '');
$sqlTypes = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_campaign_type';
$sqlTypes .= ' WHERE entity = '.((int) ($id > 0 ? $object->entity : $conf->entity)).' AND active = 1 ORDER BY position, label';
$resqlTypes = $db->query($sqlTypes);
if ($resqlTypes) {
	while (is_object($type = $db->fetch_object($resqlTypes))) {
		$campaignTypes[(int) $type->rowid] = $langs->trans((string) $type->label);
	}
}
$timezoneOptions = array();
foreach (timezone_identifiers_list() as $timezone) {
	$timezoneOptions[$timezone] = $timezone;
}
$visibilityOptions = array(
	'offers' => $langs->trans('VisibilityOffersOnly'),
	'offers_requests' => $langs->trans('VisibilityOffersAndRequests'),
	'private' => $langs->trans('VisibilityPrivate'),
);
$verificationOptions = array(
	'operator_validation' => $langs->trans('VerificationOperatorValidation'),
	'email_verification' => $langs->trans('VerificationEmailOnly'),
	'manual' => $langs->trans('VerificationManual'),
);
$yesNoOptions = array(0 => $langs->trans('Disabled'), 1 => $langs->trans('Enabled'));

llxHeader('', $langs->trans($id > 0 ? 'EditCampaign' : 'NewCampaign'));
print load_fiche_titre(
	$langs->trans($id > 0 ? 'EditCampaign' : 'NewCampaign'),
	'<a href="'.dol_buildpath('/emergencyhouse/campaign/list.php', 1).'">'.$langs->trans('BackToList').'</a>',
	'fontawesome_house-user'
);
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).($id > 0 ? '?id='.((int) $id) : '').'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="border centpercent">';
print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('CampaignLabel').'</td><td><input class="flat minwidth300" name="label" maxlength="255" required value="'.dol_escape_htmltag((string) $object->label).'"></td></tr>';
print '<tr><td>'.$langs->trans('CampaignType').'</td><td>'.$form->selectarray('fk_campaign_type', $campaignTypes, $object->fk_campaign_type, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('CampaignDescription').'</td><td>';
$descriptionEditor = new DolEditor('description_public', emergencyhouseNormalizeRichTextLineBreaks((string) $object->description_public), '', 220, 'dolibarr_notes', '', false, false, isModEnabled('fckeditor'), 10, '100%');
$descriptionEditor->Create();
print '</td></tr>';
print '<tr><td>'.$langs->trans('OfficialInstructions').'</td><td>';
$instructionsEditor = new DolEditor('official_instructions', emergencyhouseNormalizeRichTextLineBreaks((string) $object->official_instructions), '', 220, 'dolibarr_notes', '', false, false, isModEnabled('fckeditor'), 10, '100%');
$instructionsEditor->Create();
print '</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('CoordinatorName').'</td><td><input class="flat minwidth300" name="coordinator_name" required value="'.dol_escape_htmltag((string) $object->coordinator_name).'"></td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('OfficialPhone').'</td><td><input class="flat minwidth200" name="official_phone" required value="'.dol_escape_htmltag((string) $object->official_phone).'"></td></tr>';
print '<tr><td>'.$langs->trans('OfficialEmail').'</td><td><input class="flat minwidth300" type="email" name="official_email" value="'.dol_escape_htmltag((string) $object->official_email).'"></td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('DateStart').'</td><td>'.$form->selectDate($object->date_start ?: dol_now(), 'date_start', 1, 1, 0, '', 1, 0, 0, 0).'</td></tr>';
print '<tr><td>'.$langs->trans('DateEnd').'</td><td>'.$form->selectDate($object->date_end ?: -1, 'date_end', 1, 1, 1, '', 1, 0, 0, 0).'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('Timezone').'</td><td>'.$form->selectarray('timezone', $timezoneOptions, $object->timezone, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('PublicVisibilityMode').'</td><td>'.$form->selectarray('public_visibility_mode', $visibilityOptions, $object->public_visibility_mode, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('VerificationPolicy').'</td><td>'.$form->selectarray('verification_policy', $verificationOptions, $object->verification_policy, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('DefaultRadius').'</td><td><input class="flat maxwidth75" type="number" min="1" max="1000" name="default_radius" value="'.((int) $object->default_radius).'"> '.$langs->trans('Km').'</td></tr>';
print '<tr><td>'.$langs->trans('RetentionDays').'</td><td><input class="flat maxwidth75" type="number" min="1" name="retention_days" value="'.((int) $object->retention_days).'"></td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('ConsentVersion').'</td><td><input class="flat maxwidth150" name="consent_version" required value="'.dol_escape_htmltag((string) $object->consent_version).'"></td></tr>';
print '<tr><td>'.$langs->trans('BannerText').'</td><td>';
$bannerEditor = new DolEditor('banner_text', emergencyhouseNormalizeRichTextLineBreaks((string) $object->banner_text), '', 150, 'dolibarr_notes', '', false, false, isModEnabled('fckeditor'), 6, '100%');
$bannerEditor->Create();
print '</td></tr>';
print '<tr><td>'.$langs->trans('EligibilityText').'</td><td>';
$eligibilityEditor = new DolEditor('eligibility_text', emergencyhouseNormalizeRichTextLineBreaks((string) $object->eligibility_text), '', 150, 'dolibarr_notes', '', false, false, isModEnabled('fckeditor'), 6, '100%');
$eligibilityEditor->Create();
print '</td></tr>';
print '<tr><td>'.$langs->trans('PrivacyUrl').'</td><td><input class="flat centpercent" name="privacy_url" value="'.dol_escape_htmltag((string) $object->privacy_url).'">';
print '<div class="opacitymedium">'.$langs->trans('CampaignPrivacyUrlFallbackHelp').'</div></td></tr>';
print '<tr><td>'.$langs->trans('TermsUrl').'</td><td><input class="flat centpercent" name="terms_url" value="'.dol_escape_htmltag((string) $object->terms_url).'">';
print '<div class="opacitymedium">'.$langs->trans('CampaignTermsUrlFallbackHelp').'</div></td></tr>';
print '<tr><td>'.$langs->trans('RobotsIndex').'</td><td>'.$form->selectarray('robots_index', $yesNoOptions, (int) $object->robots_index, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150');
print '<div class="opacitymedium small">'.$langs->trans('RobotsIndexHelp').'</div></td></tr>';
print '</table>';
print '<div class="center"><button class="button button-save" type="submit">'.$langs->trans('Save').'</button> ';
print '<a class="button button-cancel" href="'.dol_buildpath('/emergencyhouse/campaign/list.php', 1).'">'.$langs->trans('Cancel').'</a></div>';
print '</form>';
foreach (array('fk_campaign_type', 'timezone', 'public_visibility_mode', 'verification_policy', 'robots_index') as $selectName) {
	print ajax_combobox($selectName);
}
llxFooter();
$db->close();

/**
 * Read a native Dolibarr date selector.
 *
 * @param string $prefix Field prefix
 * @param bool   $required Whether the field is required
 * @return int|null
 */
function emergencyhouseCampaignReadDate($prefix, $required)
{
	$year = GETPOSTINT($prefix.'year');
	if ($year <= 0) {
		return $required ? 0 : null;
	}
	return dol_mktime(
		GETPOSTINT($prefix.'hour'),
		GETPOSTINT($prefix.'min'),
		0,
		GETPOSTINT($prefix.'month'),
		GETPOSTINT($prefix.'day'),
		$year
	);
}

/**
 * Accept an empty inherited value, an absolute HTTP(S) URL or a local path.
 *
 * @param string $url URL
 * @return bool
 */
function emergencyhouseCampaignUrlIsAllowed($url)
{
	return $url === '' || filter_var($url, FILTER_VALIDATE_URL) !== false || strpos($url, '/') === 0;
}
