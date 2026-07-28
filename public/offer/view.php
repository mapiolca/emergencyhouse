<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');

$uuid = GETPOST('uuid', 'alphanohtml');
$saved = GETPOSTINT('saved') > 0;
$listingService = new EmergencyHouseListingService($db);
$offer = $listingService->fetchViewableOffer($emergencyhousePublicAccount, $uuid);
if (!$offer instanceof EmergencyHouseOffer) {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('OfferNotFound'), $emergencyhousePublicAccount, 'offers');
	print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('OfferNotFound').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}

$isOwner = $emergencyhousePublicAccount instanceof EmergencyHousePublicAccount
	&& (int) $emergencyhousePublicAccount->entity === (int) $offer->entity
	&& (int) $emergencyhousePublicAccount->id === (int) $offer->fk_account;
$privateValues = false;
if ($isOwner) {
	$privateValues = $listingService->decryptOwnedOffer($emergencyhousePublicAccount, $offer);
}
$features = $listingService->fetchOfferFeatures($offer);
$features = is_array($features) ? $features : array();

$campaign = new EmergencyHouseCampaign($db);
$campaignLoaded = $campaign->fetch((int) $offer->fk_campaign) > 0;
$housingLabel = '';
$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_housing_type';
$sql .= ' WHERE entity = '.((int) $offer->entity).' AND rowid = '.((int) $offer->fk_housing_type);
$resql = $db->query($sql);
$housing = $resql ? $db->fetch_object($resql) : false;
if (is_object($housing)) {
	$housingLabel = $langs->trans((string) $housing->label);
}

emergencyhousePublicRenderHeader(
	$offer->title,
	$emergencyhousePublicAccount,
	'offers',
	false,
	false,
	array(
		'description' => (string) $offer->description_public,
		'robots' => $isOwner ? 'noindex,nofollow' : 'noindex,follow',
		'og_type' => 'article',
	)
);
print '<section class="eh-shell eh-section"><div class="eh-page-title"><p class="eh-eyebrow">'.dol_escape_htmltag($housingLabel).'</p>';
print '<h1>'.dol_escape_htmltag($offer->title).'</h1><p>'.dol_escape_htmltag((string) $offer->description_public).'</p></div>';
if ($saved) {
	emergencyhousePublicAlert('OfferSaved', 'success');
}
if ($isOwner) {
	print '<div class="eh-alert eh-alert-info">'.$langs->trans('OwnerPreviewNotice').' '.emergencyhousePublicListingStatus('offer', (int) $offer->status).'</div>';
}

print '<div class="eh-dashboard-grid"><article class="eh-card">';
print '<h2>'.$langs->trans('OfferDetails').'</h2><dl class="eh-description-list">';
print '<div><dt>'.$langs->trans('Campaign').'</dt><dd>'.($campaignLoaded ? dol_escape_htmltag($campaign->label) : $langs->trans('Unavailable')).'</dd></div>';
print '<div><dt>'.$langs->trans('PublicZone').'</dt><dd>'.dol_escape_htmltag($offer->public_zone).'</dd></div>';
print '<div><dt>'.$langs->trans('Availability').'</dt><dd>'.$langs->trans('FromDate', dol_print_date((int) $offer->date_start, 'day'));
if (!empty($offer->date_end)) {
	print ' · '.$langs->trans('UntilDate', dol_print_date((int) $offer->date_end, 'day'));
}
print '</dd></div>';
print '<div><dt>'.$langs->trans('CapacityAvailable').'</dt><dd>'.$langs->trans('PlacesCount', (int) $offer->capacity_available).'</dd></div>';
print '<div><dt>'.$langs->trans('RoomsAndBeds').'</dt><dd>'.$langs->trans('RoomsBedsCount', (int) $offer->room_count, (int) $offer->bed_count).'</dd></div>';
if (!empty($offer->arrival_window)) {
	print '<div><dt>'.$langs->trans('ArrivalWindow').'</dt><dd>'.dol_escape_htmltag($offer->arrival_window).'</dd></div>';
}
print '</dl>';
if (!empty($features)) {
	print '<h3>'.$langs->trans('AvailableFeatures').'</h3><ul class="eh-tag-list">';
	foreach ($features as $feature) {
		print '<li class="eh-badge">'.dol_escape_htmltag($langs->trans($feature['label'])).'</li>';
	}
	print '</ul>';
}
print '</article><aside class="eh-card">';
print '<h2>'.$langs->trans($isOwner ? 'ManageOffer' : 'ContactWithoutExposure').'</h2>';
if ($isOwner) {
	print '<p>'.$langs->trans('OwnerExactAddressNotice').'</p>';
	if (is_array($privateValues)) {
		print '<p><strong>'.$langs->trans('Address').'</strong><br>'.nl2br(dol_escape_htmltag($privateValues['address'])).'</p>';
		if ($privateValues['private_instructions'] !== '') {
			print '<p><strong>'.$langs->trans('PrivateInstructions').'</strong><br>'.nl2br(dol_escape_htmltag($privateValues['private_instructions'])).'</p>';
		}
	}
	print '<div class="eh-actions"><a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php', array('id' => (int) $offer->id))).'">'.$langs->trans('EditOffer').'</a></div>';
} elseif (!($emergencyhousePublicAccount instanceof EmergencyHousePublicAccount)) {
	print '<p>'.$langs->trans('LoginToSolicitOffer').'</p>';
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/login.php', array('next' => emergencyhousePublicUrl('offer/view.php', array('uuid' => $offer->public_uuid))))).'">'.$langs->trans('Login').'</a>';
} elseif (!empty($offer->direct_solicitation_enabled)) {
	print '<p>'.$langs->trans('SolicitationConsentExplanation').'</p>';
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/create.php', array('offer' => (int) $offer->id))).'">'.$langs->trans('ContactHost').'</a>';
} else {
	print '<p>'.$langs->trans('DirectSolicitationUnavailable').'</p>';
}
print '<p class="eh-help">'.$langs->trans('ExactAddressProtected').'</p>';
print '</aside></div>';

print '<div class="eh-actions"><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('report/create.php', array('type' => 'offer', 'object' => (int) $offer->id))).'">'.$langs->trans('ReportThisOffer').'</a></div>';
print '</section>';
emergencyhousePublicRenderFooter();
