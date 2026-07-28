<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/emergencyhouse/class/listingservice.class.php');

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$mode = GETPOST('mode', 'alpha');
$campaignId = GETPOSTINT('campaign');
$listingService = new EmergencyHouseListingService($db);
$form = new Form($db);
$request = false;
$privateValues = array('pickup_location' => '', 'private_note' => '');
$housingPreferences = array();
$criteriaPreferences = array();
$errorKey = '';

if ($id > 0) {
	$request = $listingService->fetchOwnedRequest($account, $id);
	if (!$request instanceof EmergencyHouseRequest) {
		http_response_code(404);
		emergencyhousePublicRenderHeader($langs->trans('RequestNotFound'), $account, 'requests');
		print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('RequestNotFound').'</h1></div></section>';
		emergencyhousePublicRenderFooter();
		exit;
	}
	$campaignId = (int) $request->fk_campaign;
	$decrypted = $listingService->decryptOwnedRequest($account, $request);
	if (is_array($decrypted)) {
		$privateValues = $decrypted;
	} else {
		$errorKey = $listingService->error;
	}
	$relations = $listingService->fetchRequestHousingTypes($request);
	if (is_array($relations)) {
		foreach ($relations as $relation) {
			$housingPreferences[(int) $relation['id']] = (string) $relation['level'];
		}
	}
	$relations = $listingService->fetchRequestCriteria($request);
	if (is_array($relations)) {
		foreach ($relations as $relation) {
			$criteriaPreferences[(int) $relation['id']] = (string) $relation['level'];
		}
	}
}

$campaigns = emergencyhousePublicCampaignOptions($db);
if ($campaignId <= 0 && !empty($campaigns)) {
	$campaignId = (int) $campaigns[0]['id'];
}
$housingTypes = $listingService->fetchDictionary('housing_type', (int) $account->entity);
$features = $listingService->fetchDictionary('feature', (int) $account->entity);
$housingTypes = is_array($housingTypes) ? $housingTypes : array();
$features = is_array($features) ? $features : array();

$submittedData = array();
if ($action === 'save' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'request_save')) {
	if (!emergencyhousePublicConsumeRateLimit(
		$db,
		(int) $account->entity,
		'request_save',
		(string) $account->id.'|'.$emergencyhousePublicIp,
		30,
		3600
	)) {
		$errorKey = 'ErrorRateLimitExceeded';
	} else {
		$dateStart = emergencyhousePublicGetNativeDate('date_start');
		$dateEnd = emergencyhousePublicGetNativeDate('date_end', true);
		$submittedData = array(
			'fk_campaign' => GETPOSTINT('fk_campaign'),
			'adults_count' => GETPOSTINT('adults_count'),
			'children_infant_count' => GETPOSTINT('children_infant_count'),
			'children_young_count' => GETPOSTINT('children_young_count'),
			'children_teen_count' => GETPOSTINT('children_teen_count'),
			'group_divisible' => GETPOSTINT('group_divisible'),
			'minimum_group_size' => GETPOSTINT('minimum_group_size'),
			'date_start' => $dateStart,
			'date_end' => $dateEnd,
			'duration_unknown' => GETPOSTINT('duration_unknown'),
			'desired_zone' => trim(GETPOST('desired_zone', 'alphanohtml')),
			'desired_zip' => trim(GETPOST('desired_zip', 'alphanohtml')),
			'desired_town' => trim(GETPOST('desired_town', 'alphanohtml')),
			'search_radius' => GETPOSTINT('search_radius'),
			'pickup_location' => trim(GETPOST('pickup_location', 'restricthtml')),
			'transport_mode' => GETPOST('transport_mode', 'alpha'),
			'pickup_possible' => GETPOSTINT('pickup_possible'),
			'urgency_level' => GETPOSTINT('urgency_level'),
			'title' => trim(GETPOST('title', 'restricthtml')),
			'description_public' => trim(GETPOST('description_public', 'restricthtml')),
			'private_note' => trim(GETPOST('private_note', 'restricthtml')),
			'visibility' => GETPOST('visibility', 'alpha'),
		);
		$housingPreferences = emergencyhousePublicGetChoiceMap('housing_pref', array('required', 'wanted', 'indifferent'));
		$criteriaPreferences = emergencyhousePublicGetChoiceMap('criterion', array('required', 'preferred', 'indifferent'));
		$storedHousing = array_filter($housingPreferences, static function ($level) {
			return $level !== 'indifferent';
		});
		$storedCriteria = array_filter($criteriaPreferences, static function ($level) {
			return $level !== 'indifferent';
		});
		$submit = $mode === 'submit';
		$triggerUser = emergencyhousePublicTriggerUser($db);
		$saved = $request instanceof EmergencyHouseRequest
			? $listingService->updateOwnedRequest($account, (int) $request->id, $submittedData, $storedHousing, $storedCriteria, $triggerUser, $submit)
			: $listingService->createRequest($account, $submittedData, $storedHousing, $storedCriteria, $triggerUser, $submit);
		if ($saved instanceof EmergencyHouseRequest) {
			emergencyhousePublicAnalyticsEvent(
				$submit ? 'request_submitted' : 'request_draft_saved',
				$submit,
				'request_form',
				$account,
				(int) $saved->fk_campaign
			);
			header('Location: '.emergencyhousePublicUrl('request/view.php', array('uuid' => $saved->public_uuid, 'saved' => 1)));
			exit;
		}
		$errorKey = $listingService->error !== '' ? $listingService->error : 'ErrorRecordNotSaved';
	}
} elseif ($action === 'save') {
	$errorKey = 'ErrorInvalidCsrfToken';
}

$values = array(
	'fk_campaign' => isset($submittedData['fk_campaign']) ? (int) $submittedData['fk_campaign'] : ($request instanceof EmergencyHouseRequest ? (int) $request->fk_campaign : $campaignId),
	'adults_count' => isset($submittedData['adults_count']) ? (int) $submittedData['adults_count'] : ($request instanceof EmergencyHouseRequest ? (int) $request->adults_count : 1),
	'children_infant_count' => isset($submittedData['children_infant_count']) ? (int) $submittedData['children_infant_count'] : ($request instanceof EmergencyHouseRequest ? (int) $request->children_infant_count : 0),
	'children_young_count' => isset($submittedData['children_young_count']) ? (int) $submittedData['children_young_count'] : ($request instanceof EmergencyHouseRequest ? (int) $request->children_young_count : 0),
	'children_teen_count' => isset($submittedData['children_teen_count']) ? (int) $submittedData['children_teen_count'] : ($request instanceof EmergencyHouseRequest ? (int) $request->children_teen_count : 0),
	'group_divisible' => isset($submittedData['group_divisible']) ? (int) $submittedData['group_divisible'] : ($request instanceof EmergencyHouseRequest ? (int) $request->group_divisible : 0),
	'minimum_group_size' => isset($submittedData['minimum_group_size']) ? (int) $submittedData['minimum_group_size'] : ($request instanceof EmergencyHouseRequest ? (int) $request->minimum_group_size : 1),
	'date_start' => isset($submittedData['date_start']) ? (int) $submittedData['date_start'] : ($request instanceof EmergencyHouseRequest ? (int) $request->date_start : dol_now()),
	'date_end' => array_key_exists('date_end', $submittedData) ? ($submittedData['date_end'] === null ? null : (int) $submittedData['date_end']) : ($request instanceof EmergencyHouseRequest && !empty($request->date_end) ? (int) $request->date_end : null),
	'duration_unknown' => isset($submittedData['duration_unknown']) ? (int) $submittedData['duration_unknown'] : ($request instanceof EmergencyHouseRequest ? (int) $request->duration_unknown : 0),
	'desired_zone' => isset($submittedData['desired_zone']) ? (string) $submittedData['desired_zone'] : ($request instanceof EmergencyHouseRequest ? $request->desired_zone : ''),
	'desired_zip' => isset($submittedData['desired_zip']) ? (string) $submittedData['desired_zip'] : ($request instanceof EmergencyHouseRequest ? (string) $request->desired_zip : ''),
	'desired_town' => isset($submittedData['desired_town']) ? (string) $submittedData['desired_town'] : ($request instanceof EmergencyHouseRequest ? (string) $request->desired_town : ''),
	'search_radius' => isset($submittedData['search_radius']) ? (int) $submittedData['search_radius'] : ($request instanceof EmergencyHouseRequest ? (int) $request->search_radius : max(1, getDolGlobalInt('EMERGENCYHOUSE_MATCH_DEFAULT_RADIUS_KM', 50))),
	'pickup_location' => isset($submittedData['pickup_location']) ? (string) $submittedData['pickup_location'] : $privateValues['pickup_location'],
	'transport_mode' => isset($submittedData['transport_mode']) ? (string) $submittedData['transport_mode'] : ($request instanceof EmergencyHouseRequest ? (string) $request->transport_mode : ''),
	'pickup_possible' => isset($submittedData['pickup_possible']) ? (int) $submittedData['pickup_possible'] : ($request instanceof EmergencyHouseRequest ? (int) $request->pickup_possible : 0),
	'urgency_level' => isset($submittedData['urgency_level']) ? (int) $submittedData['urgency_level'] : ($request instanceof EmergencyHouseRequest ? (int) $request->urgency_level : 0),
	'title' => isset($submittedData['title']) ? (string) $submittedData['title'] : ($request instanceof EmergencyHouseRequest ? $request->title : ''),
	'description_public' => isset($submittedData['description_public']) ? (string) $submittedData['description_public'] : ($request instanceof EmergencyHouseRequest ? (string) $request->description_public : ''),
	'private_note' => isset($submittedData['private_note']) ? (string) $submittedData['private_note'] : $privateValues['private_note'],
	'visibility' => isset($submittedData['visibility']) ? (string) $submittedData['visibility'] : ($request instanceof EmergencyHouseRequest ? $request->visibility : getDolGlobalString('EMERGENCYHOUSE_PUBLIC_REQUEST_VISIBILITY', 'private')),
);

$campaignOptions = array();
foreach ($campaigns as $campaign) {
	$campaignOptions[(int) $campaign['id']] = (string) $campaign['label'];
}
$preferenceOptions = array(
	'indifferent' => $langs->trans('PreferenceIndifferent'),
	'wanted' => $langs->trans('PreferenceWanted'),
	'required' => $langs->trans('PreferenceRequired'),
);
$criterionOptions = array(
	'indifferent' => $langs->trans('PreferenceIndifferent'),
	'preferred' => $langs->trans('PreferencePreferred'),
	'required' => $langs->trans('PreferenceRequired'),
);

$pageTitle = $langs->trans($request instanceof EmergencyHouseRequest ? 'EditRequest' : 'CreateRequest');
emergencyhousePublicRenderHeader($pageTitle, $account, 'requests');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('MyRequests').'</p>';
print '<h1>'.$pageTitle.'</h1><p>'.$langs->trans('RequestFormIntroduction').'</p></div>';
if ($errorKey !== '') {
	emergencyhousePublicAlert($errorKey, 'error');
}
if (empty($campaignOptions)) {
	print '<div class="eh-empty"><h2>'.$langs->trans('NoActiveCampaign').'</h2><p>'.$langs->trans('NoActiveCampaignHelp').'</p></div>';
} else {
	print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('request/edit.php', $id > 0 ? array('id' => $id) : array())).'" class="eh-form eh-form-wide" data-disable-on-submit>';
	print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'request_save');
	print '<input type="hidden" name="action" value="save">';

	print '<section class="eh-form-section"><h2>'.$langs->trans('RequestContextSection').'</h2><p>'.$langs->trans('RequestContextSectionHelp').'</p><div class="eh-field-grid">';
	print '<div class="eh-field"><label for="fk_campaign">'.$langs->trans('Campaign').'</label>'.emergencyhousePublicSelect('fk_campaign', $campaignOptions, $values['fk_campaign'], false, '', ' required').'</div>';
	print '<div class="eh-field"><label for="visibility">'.$langs->trans('Visibility').'</label>'.emergencyhousePublicSelect(
		'visibility',
		array('private' => $langs->trans('VisibilityPrivate'), 'public' => $langs->trans('VisibilityPublic')),
		$values['visibility']
	).'<small class="eh-help">'.$langs->trans('RequestVisibilityHelp').'</small></div>';
	print '<div class="eh-field eh-field-full"><label for="title">'.$langs->trans('RequestTitle').'</label><input type="text" id="title" name="title" required minlength="5" maxlength="255" value="'.dol_escape_htmltag((string) $values['title']).'"></div>';
	print '<div class="eh-field eh-field-full"><label for="description_public">'.$langs->trans('PublicDescription').'</label><textarea id="description_public" name="description_public" maxlength="5000" data-character-count="request-description-count">'.dol_escape_htmltag((string) $values['description_public']).'</textarea>';
	print '<small class="eh-help"><span id="request-description-count">0</span>/5000 — '.$langs->trans('PublicDescriptionSafetyHelp').'</small></div></div></section>';

	print '<section class="eh-form-section"><h2>'.$langs->trans('HouseholdComposition').'</h2><div class="eh-field-grid">';
	print emergencyhouseRequestNumberField('adults_count', 'AdultsCount', (int) $values['adults_count'], 0, 1000);
	print emergencyhouseRequestNumberField('children_infant_count', 'ChildrenInfantCount', (int) $values['children_infant_count'], 0, 1000);
	print emergencyhouseRequestNumberField('children_young_count', 'ChildrenYoungCount', (int) $values['children_young_count'], 0, 1000);
	print emergencyhouseRequestNumberField('children_teen_count', 'ChildrenTeenCount', (int) $values['children_teen_count'], 0, 1000);
	print emergencyhouseRequestSwitch('group_divisible', 'GroupDivisible', (int) $values['group_divisible']);
	print emergencyhouseRequestNumberField('minimum_group_size', 'MinimumGroupSize', (int) $values['minimum_group_size'], 1, 1000);
	print '</div></section>';

	print '<section class="eh-form-section"><h2>'.$langs->trans('DatesAndSearchArea').'</h2><div class="eh-field-grid">';
	print '<fieldset class="eh-field eh-fieldset"><legend>'.$langs->trans('DateStart').'</legend>'.emergencyhousePublicNativeDateSelector($form, (int) $values['date_start'], 'date_start').'</fieldset>';
	print '<fieldset class="eh-field eh-fieldset"><legend>'.$langs->trans('DateEndOptional').'</legend>'.emergencyhousePublicNativeDateSelector($form, $values['date_end'] === null ? null : (int) $values['date_end'], 'date_end', true).'</fieldset>';
	print emergencyhouseRequestSwitch('duration_unknown', 'DurationUnknown', (int) $values['duration_unknown']);
	print '<div class="eh-field eh-field-full"><label for="desired_zone">'.$langs->trans('DesiredZone').'</label><input type="text" id="desired_zone" name="desired_zone" required maxlength="255" value="'.dol_escape_htmltag((string) $values['desired_zone']).'"></div>';
	print '<div class="eh-field"><label for="desired_zip">'.$langs->trans('DesiredZipOptional').'</label><input type="text" id="desired_zip" name="desired_zip" maxlength="25" value="'.dol_escape_htmltag((string) $values['desired_zip']).'"></div>';
	print '<div class="eh-field"><label for="desired_town">'.$langs->trans('DesiredTownOptional').'</label><input type="text" id="desired_town" name="desired_town" maxlength="255" value="'.dol_escape_htmltag((string) $values['desired_town']).'"></div>';
	print emergencyhouseRequestNumberField('search_radius', 'SearchRadiusKm', (int) $values['search_radius'], 1, 1000);
	print '<div class="eh-field"><label for="urgency_level">'.$langs->trans('UrgencyLevel').'</label>'.emergencyhousePublicSelect(
		'urgency_level',
		array(0 => $langs->trans('UrgencyNormal'), 1 => $langs->trans('UrgencySoon'), 2 => $langs->trans('UrgencyHigh'), 3 => $langs->trans('UrgencyImmediate')),
		$values['urgency_level']
	).'</div></div></section>';

	print '<section class="eh-form-section"><h2>'.$langs->trans('AccommodationPreferences').'</h2><p>'.$langs->trans('AccommodationPreferencesHelp').'</p><div class="eh-preference-grid">';
	foreach ($housingTypes as $housingType) {
		$housingId = (int) $housingType['id'];
		$current = isset($housingPreferences[$housingId]) ? $housingPreferences[$housingId] : 'indifferent';
		print '<div class="eh-field"><label for="housing_pref_'.$housingId.'">'.$langs->trans((string) $housingType['label']).'</label>';
		print '<select class="eh-select2" id="housing_pref_'.$housingId.'" name="housing_pref['.$housingId.']">';
		foreach ($preferenceOptions as $level => $label) {
			print '<option value="'.dol_escape_htmltag($level).'"'.($current === $level ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
		}
		print '</select></div>';
	}
	print '</div></section>';

	print '<section class="eh-form-section"><h2>'.$langs->trans('FeatureCriteria').'</h2><p>'.$langs->trans('FeatureCriteriaHelp').'</p><div class="eh-preference-grid">';
	foreach ($features as $feature) {
		$featureId = (int) $feature['id'];
		$current = isset($criteriaPreferences[$featureId]) ? $criteriaPreferences[$featureId] : 'indifferent';
		print '<div class="eh-field"><label for="criterion_'.$featureId.'">'.$langs->trans((string) $feature['label']).'</label>';
		print '<select class="eh-select2" id="criterion_'.$featureId.'" name="criterion['.$featureId.']">';
		foreach ($criterionOptions as $level => $label) {
			print '<option value="'.dol_escape_htmltag($level).'"'.($current === $level ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
		}
		print '</select></div>';
	}
	print '</div></section>';

	print '<section class="eh-form-section"><h2>'.$langs->trans('TransportAndPrivateDetails').'</h2><div class="eh-field-grid">';
	print '<div class="eh-field"><label for="transport_mode">'.$langs->trans('TransportMode').'</label>'.emergencyhousePublicSelect(
		'transport_mode',
		array('none' => $langs->trans('TransportNone'), 'car' => $langs->trans('TransportCar'), 'public' => $langs->trans('TransportPublic'), 'assistance' => $langs->trans('TransportAssistance')),
		$values['transport_mode'],
		true,
		$langs->trans('SelectAnOption')
	).'</div>';
	print emergencyhouseRequestSwitch('pickup_possible', 'PickupPossible', (int) $values['pickup_possible']);
	print '<div class="eh-field eh-field-full"><label for="pickup_location">'.$langs->trans('PickupLocation').'</label><input type="text" id="pickup_location" name="pickup_location" maxlength="500" value="'.dol_escape_htmltag((string) $values['pickup_location']).'"><small class="eh-help">'.$langs->trans('PickupLocationPrivateHelp').'</small></div>';
	print '<div class="eh-field eh-field-full"><label for="private_note">'.$langs->trans('PrivateNote').'</label><textarea id="private_note" name="private_note" maxlength="5000">'.dol_escape_htmltag((string) $values['private_note']).'</textarea><small class="eh-help">'.$langs->trans('PrivateNoteHelp').'</small></div>';
	print '</div></section>';

	print '<div class="eh-form-actions">';
	print '<button class="eh-button eh-button-secondary" type="submit" name="mode" value="draft">'.$langs->trans('SaveDraft').'</button>';
	print '<button class="eh-button" type="submit" name="mode" value="submit">'.$langs->trans('PublishRequest').'</button>';
	print '</div></form>';
}
print '</section>';
emergencyhousePublicRenderFooter();

/**
 * Render a bounded integer field.
 *
 * @param string $name Field name
 * @param string $labelKey Label key
 * @param int $value Value
 * @param int $minimum Minimum
 * @param int $maximum Maximum
 * @return string
 */
function emergencyhouseRequestNumberField($name, $labelKey, $value, $minimum, $maximum)
{
	global $langs;
	return '<div class="eh-field"><label for="'.dol_escape_htmltag($name).'">'.$langs->trans($labelKey).'</label>'
		.'<input type="number" id="'.dol_escape_htmltag($name).'" name="'.dol_escape_htmltag($name).'" min="'.((int) $minimum).'" max="'.((int) $maximum).'" value="'.((int) $value).'"></div>';
}

/**
 * Render a public boolean as a switch.
 *
 * @param string $name Field name
 * @param string $labelKey Label key
 * @param int $value Value
 * @return string
 */
function emergencyhouseRequestSwitch($name, $labelKey, $value)
{
	global $langs;
	return '<label class="eh-switch" for="'.dol_escape_htmltag($name).'"><span>'.$langs->trans($labelKey).'</span>'
		.'<input type="checkbox" role="switch" id="'.dol_escape_htmltag($name).'" name="'.dol_escape_htmltag($name).'" value="1"'.($value > 0 ? ' checked' : '').'></label>';
}
