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
$offer = false;
$privateValues = array('address' => '', 'private_instructions' => '');
$selectedFeatures = array();
$offerPhotos = array();
$photosEnabled = getDolGlobalInt('EMERGENCYHOUSE_PHOTOS_ENABLED', 0) > 0;
$photosAvailable = EmergencyHouseOfferPhotoService::isAvailable();
$photoDeleted = GETPOSTINT('photo_deleted') > 0;
$errorKey = '';

if ($id > 0) {
	$offer = $listingService->fetchOwnedOffer($account, $id);
	if (!$offer instanceof EmergencyHouseOffer) {
		http_response_code(404);
		emergencyhousePublicRenderHeader($langs->trans('OfferNotFound'), $account, 'offers');
		print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('OfferNotFound').'</h1></div></section>';
		emergencyhousePublicRenderFooter();
		exit;
	}
	$campaignId = (int) $offer->fk_campaign;
	$decrypted = $listingService->decryptOwnedOffer($account, $offer);
	if (is_array($decrypted)) {
		$privateValues = $decrypted;
	} else {
		$errorKey = $listingService->error;
	}
	$relations = $listingService->fetchOfferFeatures($offer);
	if (is_array($relations)) {
		foreach ($relations as $relation) {
			$selectedFeatures[(int) $relation['id']] = (int) $relation['id'];
		}
	}
	$photoService = new EmergencyHouseOfferPhotoService($db);
	$loadedPhotos = $photoService->fetchPhotos($offer, true);
	if (is_array($loadedPhotos)) {
		$offerPhotos = $loadedPhotos;
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
if (
	$action === 'delete_photo'
	&& $offer instanceof EmergencyHouseOffer
	&& emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'offer_photo_delete')
) {
	$photoId = GETPOSTINT('photo_id');
	$triggerUser = emergencyhousePublicTriggerUser($db);
	$updatedOffer = $listingService->deleteOwnedOfferPhoto($account, (int) $offer->id, $photoId, $triggerUser);
	if ($updatedOffer instanceof EmergencyHouseOffer) {
		header('Location: '.emergencyhousePublicUrl('offer/edit.php', array(
			'id' => (int) $offer->id,
			'photo_deleted' => 1,
		)));
		exit;
	}
	$errorKey = $listingService->error !== '' ? $listingService->error : 'ErrorOfferPhotoDelete';
} elseif ($action === 'delete_photo') {
	$errorKey = 'ErrorInvalidCsrfToken';
} elseif ($action === 'save' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'offer_save')) {
	if (!emergencyhousePublicConsumeRateLimit(
		$db,
		(int) $account->entity,
		'offer_save',
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
			'fk_housing_type' => GETPOSTINT('fk_housing_type'),
			'address' => trim(GETPOST('address', 'restricthtml')),
			'zip' => trim(GETPOST('zip', 'alphanohtml')),
			'town' => trim(GETPOST('town', 'alphanohtml')),
			'fk_pays' => GETPOSTINT('fk_pays'),
			'public_zone' => trim(GETPOST('public_zone', 'alphanohtml')),
			'public_location_precision' => GETPOST('public_location_precision', 'alpha'),
			'date_start' => $dateStart,
			'date_end' => $dateEnd,
			'capacity_total' => GETPOSTINT('capacity_total'),
			'max_adults' => GETPOSTINT('max_adults'),
			'max_children' => GETPOSTINT('max_children'),
			'room_count' => GETPOSTINT('room_count'),
			'bed_count' => GETPOSTINT('bed_count'),
			'extra_bed_count' => GETPOSTINT('extra_bed_count'),
			'tent_count' => GETPOSTINT('tent_count'),
			'title' => trim(GETPOST('title', 'restricthtml')),
			'description_public' => trim(GETPOST('description_public', 'restricthtml')),
			'private_instructions' => trim(GETPOST('private_instructions', 'restricthtml')),
			'minimum_stay_days' => GETPOSTINT('minimum_stay_days'),
			'maximum_stay_days' => GETPOSTINT('maximum_stay_days'),
			'arrival_window' => trim(GETPOST('arrival_window', 'alphanohtml')),
			'transport_available' => GETPOSTINT('transport_available'),
			'direct_solicitation_enabled' => GETPOSTINT('direct_solicitation_enabled'),
		);
		$selectedFeatures = array();
		$featureValues = array();
		foreach (emergencyhousePublicGetIntArray('feature_ids') as $featureId) {
			$selectedFeatures[$featureId] = $featureId;
			$featureValues[$featureId] = array('code' => 'yes', 'number' => null);
		}
		$submit = $mode === 'submit';
		$triggerUser = emergencyhousePublicTriggerUser($db);
		$uploadedPhotos = isset($_FILES['offer_photos']) && is_array($_FILES['offer_photos'])
			? $_FILES['offer_photos']
			: array();
		$saved = $offer instanceof EmergencyHouseOffer
			? $listingService->updateOwnedOffer(
				$account,
				(int) $offer->id,
				$submittedData,
				$featureValues,
				$triggerUser,
				$submit,
				$uploadedPhotos
			)
			: $listingService->createOffer(
				$account,
				$submittedData,
				$featureValues,
				$triggerUser,
				$submit,
				$uploadedPhotos
			);
		if ($saved instanceof EmergencyHouseOffer) {
			header('Location: '.emergencyhousePublicUrl('offer/view.php', array('uuid' => $saved->public_uuid, 'saved' => 1)));
			exit;
		}
		$errorKey = $listingService->error !== '' ? $listingService->error : 'ErrorRecordNotSaved';
	}
} elseif ($action === 'save') {
	$errorKey = 'ErrorInvalidCsrfToken';
}

$values = array(
	'fk_campaign' => isset($submittedData['fk_campaign']) ? (int) $submittedData['fk_campaign'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->fk_campaign : $campaignId),
	'fk_housing_type' => isset($submittedData['fk_housing_type']) ? (int) $submittedData['fk_housing_type'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->fk_housing_type : 0),
	'address' => isset($submittedData['address']) ? (string) $submittedData['address'] : $privateValues['address'],
	'zip' => isset($submittedData['zip']) ? (string) $submittedData['zip'] : ($offer instanceof EmergencyHouseOffer ? $offer->zip : ''),
	'town' => isset($submittedData['town']) ? (string) $submittedData['town'] : ($offer instanceof EmergencyHouseOffer ? $offer->town : ''),
	'fk_pays' => isset($submittedData['fk_pays']) ? (int) $submittedData['fk_pays'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->fk_pays : 0),
	'public_zone' => isset($submittedData['public_zone']) ? (string) $submittedData['public_zone'] : ($offer instanceof EmergencyHouseOffer ? $offer->public_zone : ''),
	'public_location_precision' => isset($submittedData['public_location_precision']) ? (string) $submittedData['public_location_precision'] : ($offer instanceof EmergencyHouseOffer ? $offer->public_location_precision : 'town'),
	'date_start' => isset($submittedData['date_start']) ? (int) $submittedData['date_start'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->date_start : dol_now()),
	'date_end' => array_key_exists('date_end', $submittedData) ? ($submittedData['date_end'] === null ? null : (int) $submittedData['date_end']) : ($offer instanceof EmergencyHouseOffer && !empty($offer->date_end) ? (int) $offer->date_end : null),
	'capacity_total' => isset($submittedData['capacity_total']) ? (int) $submittedData['capacity_total'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->capacity_total : 1),
	'max_adults' => isset($submittedData['max_adults']) ? (int) $submittedData['max_adults'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->max_adults : 1),
	'max_children' => isset($submittedData['max_children']) ? (int) $submittedData['max_children'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->max_children : 0),
	'room_count' => isset($submittedData['room_count']) ? (int) $submittedData['room_count'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->room_count : 0),
	'bed_count' => isset($submittedData['bed_count']) ? (int) $submittedData['bed_count'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->bed_count : 1),
	'extra_bed_count' => isset($submittedData['extra_bed_count']) ? (int) $submittedData['extra_bed_count'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->extra_bed_count : 0),
	'tent_count' => isset($submittedData['tent_count']) ? (int) $submittedData['tent_count'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->tent_count : 0),
	'title' => isset($submittedData['title']) ? (string) $submittedData['title'] : ($offer instanceof EmergencyHouseOffer ? $offer->title : ''),
	'description_public' => isset($submittedData['description_public']) ? (string) $submittedData['description_public'] : ($offer instanceof EmergencyHouseOffer ? (string) $offer->description_public : ''),
	'private_instructions' => isset($submittedData['private_instructions']) ? (string) $submittedData['private_instructions'] : $privateValues['private_instructions'],
	'minimum_stay_days' => isset($submittedData['minimum_stay_days']) ? (int) $submittedData['minimum_stay_days'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->minimum_stay_days : 0),
	'maximum_stay_days' => isset($submittedData['maximum_stay_days']) ? (int) $submittedData['maximum_stay_days'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->maximum_stay_days : 0),
	'arrival_window' => isset($submittedData['arrival_window']) ? (string) $submittedData['arrival_window'] : ($offer instanceof EmergencyHouseOffer ? (string) $offer->arrival_window : ''),
	'transport_available' => isset($submittedData['transport_available']) ? (int) $submittedData['transport_available'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->transport_available : 0),
	'direct_solicitation_enabled' => isset($submittedData['direct_solicitation_enabled']) ? (int) $submittedData['direct_solicitation_enabled'] : ($offer instanceof EmergencyHouseOffer ? (int) $offer->direct_solicitation_enabled : 1),
);

$campaignOptions = array();
foreach ($campaigns as $campaign) {
	$campaignOptions[(int) $campaign['id']] = (string) $campaign['label'];
}
$housingOptions = array();
foreach ($housingTypes as $housingType) {
	$housingOptions[(int) $housingType['id']] = $langs->trans((string) $housingType['label']);
}
$featureOptions = array();
foreach ($features as $feature) {
	$featureOptions[(int) $feature['id']] = $langs->trans((string) $feature['label']);
}

$pageTitle = $langs->trans($offer instanceof EmergencyHouseOffer ? 'EditOffer' : 'CreateOffer');
emergencyhousePublicRenderHeader($pageTitle, $account, 'offers');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('MyOffers').'</p>';
print '<h1>'.$pageTitle.'</h1><p>'.$langs->trans('OfferFormIntroduction').'</p></div>';
if ($errorKey !== '') {
	emergencyhousePublicAlert($errorKey, 'error');
}
if ($photoDeleted) {
	emergencyhousePublicAlert('OfferPhotoDeleted', 'success');
}
if (empty($campaignOptions)) {
	print '<div class="eh-empty"><h2>'.$langs->trans('NoActiveCampaign').'</h2><p>'.$langs->trans('NoActiveCampaignHelp').'</p></div>';
} else {
	if ($offer instanceof EmergencyHouseOffer && !empty($offerPhotos)) {
		print '<section class="eh-card eh-photo-manager" aria-labelledby="offer-existing-photos">';
		print '<div class="eh-section-heading"><div><h2 id="offer-existing-photos">'.$langs->trans('OfferPhotos').'</h2>';
		print '<p>'.$langs->trans('OfferExistingPhotosHelp').'</p></div>';
		print '<span class="eh-badge">'.$langs->trans('OfferPhotoCount', count($offerPhotos), EmergencyHouseOfferPhotoService::MAX_PHOTOS).'</span></div>';
		print '<div class="eh-photo-grid">';
		foreach ($offerPhotos as $photo) {
			$photoStatus = $langs->trans(EmergencyHouseOfferPhotoService::getStatusTranslationKey((int) $photo['status']));
			$photoUrl = emergencyhousePublicUrl('offer/photo.php', array(
				'offer' => $offer->public_uuid,
				'photo' => (int) $photo['id'],
			));
			print '<article class="eh-photo-card">';
			print '<img src="'.dol_escape_htmltag($photoUrl).'" alt="'.dol_escape_htmltag($langs->trans('OfferPhotoAlt', $offer->title)).'" loading="lazy">';
			print '<div class="eh-photo-card-footer"><span class="eh-badge">'.dol_escape_htmltag($photoStatus).'</span>';
			print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php', array('id' => (int) $offer->id))).'">';
			print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'offer_photo_delete');
			print '<input type="hidden" name="action" value="delete_photo">';
			print '<input type="hidden" name="photo_id" value="'.((int) $photo['id']).'">';
			print '<button class="eh-button eh-button-danger eh-button-small" type="submit">'.$langs->trans('DeletePhoto').'</button>';
			print '</form></div></article>';
		}
		print '</div></section>';
	}

	print '<form method="POST" enctype="multipart/form-data" action="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php', $id > 0 ? array('id' => $id) : array())).'" class="eh-form eh-form-wide" data-disable-on-submit>';
	print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'offer_save');
	print '<input type="hidden" name="action" value="save">';

	print '<section class="eh-form-section"><h2>'.$langs->trans('OfferContextSection').'</h2><p>'.$langs->trans('OfferContextSectionHelp').'</p><div class="eh-field-grid">';
	print '<div class="eh-field"><label for="fk_campaign">'.$langs->trans('Campaign').'</label>';
	print emergencyhousePublicSelect('fk_campaign', $campaignOptions, $values['fk_campaign'], false, '', ' required');
	print '</div><div class="eh-field"><label for="fk_housing_type">'.$langs->trans('HousingType').'</label>';
	print emergencyhousePublicSelect('fk_housing_type', $housingOptions, $values['fk_housing_type'], true, $langs->trans('SelectAnOption'), ' required');
	print '</div><div class="eh-field eh-field-full"><label for="title">'.$langs->trans('OfferTitle').'</label>';
	print '<input type="text" id="title" name="title" required minlength="5" maxlength="255" value="'.dol_escape_htmltag((string) $values['title']).'"></div>';
	print '<div class="eh-field eh-field-full"><label for="description_public">'.$langs->trans('PublicDescription').'</label>';
	print '<textarea id="description_public" name="description_public" maxlength="5000" data-character-count="offer-description-count">'.dol_escape_htmltag((string) $values['description_public']).'</textarea>';
	print '<small class="eh-help"><span id="offer-description-count">0</span>/5000 — '.$langs->trans('PublicDescriptionSafetyHelp').'</small></div></div></section>';

	print '<section class="eh-form-section"><h2>'.$langs->trans('ExactAddressSection').'</h2><p>'.$langs->trans('ExactAddressProtectedHelp').'</p><div class="eh-field-grid">';
	print '<div class="eh-field eh-field-full"><label for="address">'.$langs->trans('Address').'</label>';
	print '<input type="text" id="address" name="address" required maxlength="500" autocomplete="street-address" value="'.dol_escape_htmltag((string) $values['address']).'"></div>';
	print '<div class="eh-field"><label for="zip">'.$langs->trans('Zip').'</label><input type="text" id="zip" name="zip" required maxlength="25" autocomplete="postal-code" value="'.dol_escape_htmltag((string) $values['zip']).'"></div>';
	print '<div class="eh-field"><label for="town">'.$langs->trans('Town').'</label><input type="text" id="town" name="town" required maxlength="255" autocomplete="address-level2" value="'.dol_escape_htmltag((string) $values['town']).'"></div>';
	print '<div class="eh-field"><label for="fk_pays">'.$langs->trans('Country').'</label>';
	print emergencyhousePublicCountrySelector($form, (int) $values['fk_pays']);
	print '</div><div class="eh-field"><label for="public_zone">'.$langs->trans('PublicZone').'</label><input type="text" id="public_zone" name="public_zone" required maxlength="255" value="'.dol_escape_htmltag((string) $values['public_zone']).'">';
	print '<small class="eh-help">'.$langs->trans('PublicZoneHelp').'</small></div>';
	print '<div class="eh-field"><label for="public_location_precision">'.$langs->trans('PublicLocationPrecision').'</label>';
	print emergencyhousePublicSelect(
		'public_location_precision',
		array('town' => $langs->trans('LocationPrecisionTown'), 'district' => $langs->trans('LocationPrecisionDistrict'), 'radius' => $langs->trans('LocationPrecisionRadius')),
		$values['public_location_precision']
	);
	print '</div></div></section>';

	print '<section class="eh-form-section"><h2>'.$langs->trans('AvailabilityAndCapacity').'</h2><div class="eh-field-grid">';
	print '<fieldset class="eh-field eh-fieldset"><legend>'.$langs->trans('DateStart').'</legend>'.emergencyhousePublicNativeDateSelector($form, (int) $values['date_start'], 'date_start').'</fieldset>';
	print '<fieldset class="eh-field eh-fieldset"><legend>'.$langs->trans('DateEndOptional').'</legend>'.emergencyhousePublicNativeDateSelector($form, $values['date_end'] === null ? null : (int) $values['date_end'], 'date_end', true).'</fieldset>';
	print emergencyhouseOfferNumberField('capacity_total', 'CapacityTotal', (int) $values['capacity_total'], 1, 1000);
	print emergencyhouseOfferNumberField('max_adults', 'MaxAdults', (int) $values['max_adults'], 0, 1000);
	print emergencyhouseOfferNumberField('max_children', 'MaxChildren', (int) $values['max_children'], 0, 1000);
	print emergencyhouseOfferNumberField('room_count', 'RoomCount', (int) $values['room_count'], 0, 1000);
	print emergencyhouseOfferNumberField('bed_count', 'BedCount', (int) $values['bed_count'], 0, 1000);
	print emergencyhouseOfferNumberField('extra_bed_count', 'ExtraBedCount', (int) $values['extra_bed_count'], 0, 1000);
	print emergencyhouseOfferNumberField('tent_count', 'TentCount', (int) $values['tent_count'], 0, 1000);
	print emergencyhouseOfferNumberField('minimum_stay_days', 'MinimumStayDays', (int) $values['minimum_stay_days'], 0, 3650);
	print emergencyhouseOfferNumberField('maximum_stay_days', 'MaximumStayDaysOptional', (int) $values['maximum_stay_days'], 0, 3650);
	print '<div class="eh-field eh-field-full"><label for="arrival_window">'.$langs->trans('ArrivalWindow').'</label><input type="text" id="arrival_window" name="arrival_window" maxlength="255" value="'.dol_escape_htmltag((string) $values['arrival_window']).'"></div>';
	print '</div></section>';

	print '<section class="eh-form-section"><h2>'.$langs->trans('FeaturesAndRules').'</h2><div class="eh-field-grid">';
	print '<div class="eh-field eh-field-full"><label for="feature_ids">'.$langs->trans('AvailableFeatures').'</label>';
	print '<select class="eh-select2" id="feature_ids" name="feature_ids[]" multiple>';
	foreach ($featureOptions as $featureId => $featureLabel) {
		print '<option value="'.((int) $featureId).'"'.(isset($selectedFeatures[(int) $featureId]) ? ' selected' : '').'>'.dol_escape_htmltag($featureLabel).'</option>';
	}
	print '</select></div>';
	print '<div class="eh-field eh-field-full"><label for="private_instructions">'.$langs->trans('PrivateInstructions').'</label>';
	print '<textarea id="private_instructions" name="private_instructions" maxlength="5000">'.dol_escape_htmltag((string) $values['private_instructions']).'</textarea>';
	print '<small class="eh-help">'.$langs->trans('PrivateInstructionsHelp').'</small></div>';
	print emergencyhouseOfferSwitch('transport_available', 'TransportAvailable', (int) $values['transport_available']);
	print emergencyhouseOfferSwitch('direct_solicitation_enabled', 'DirectSolicitationEnabled', (int) $values['direct_solicitation_enabled']);
	print '</div></section>';

	if ($photosEnabled) {
		print '<section class="eh-form-section"><h2>'.$langs->trans('OfferPhotos').'</h2>';
		if ($photosAvailable) {
			print '<p>'.$langs->trans('OfferPhotosHelp', EmergencyHouseOfferPhotoService::MAX_PHOTOS).'</p>';
			print '<div class="eh-field-grid"><div class="eh-field eh-field-full">';
			print '<label for="offer_photos">'.$langs->trans('AddOfferPhotos').'</label>';
			print '<input id="offer_photos" type="file" name="offer_photos[]" accept="image/jpeg,image/png,image/webp" multiple>';
			print '<span class="eh-help">'.$langs->trans('OfferPhotoUploadHelp', EmergencyHouseOfferPhotoService::MAX_PHOTOS).'</span>';
			print '</div></div>';
		} else {
			print '<div class="eh-alert eh-alert-warning">'.$langs->trans('OfferPhotosUnavailable').'</div>';
		}
		print '</section>';
	}

	print '<div class="eh-form-actions">';
	print '<button class="eh-button eh-button-secondary" type="submit" name="mode" value="draft">'.$langs->trans('SaveDraft').'</button>';
	print '<button class="eh-button" type="submit" name="mode" value="submit">'.$langs->trans('SubmitForValidation').'</button>';
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
function emergencyhouseOfferNumberField($name, $labelKey, $value, $minimum, $maximum)
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
function emergencyhouseOfferSwitch($name, $labelKey, $value)
{
	global $langs;
	return '<label class="eh-switch" for="'.dol_escape_htmltag($name).'"><span>'.$langs->trans($labelKey).'</span>'
		.'<input type="checkbox" role="switch" id="'.dol_escape_htmltag($name).'" name="'.dol_escape_htmltag($name).'" value="1"'.($value > 0 ? ' checked' : '').'></label>';
}
