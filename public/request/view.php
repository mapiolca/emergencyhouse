<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');

$uuid = GETPOST('uuid', 'alphanohtml');
$saved = GETPOSTINT('saved') > 0;
$listingService = new EmergencyHouseListingService($db);
$request = $listingService->fetchViewableRequest($emergencyhousePublicAccount, $uuid);
if (!$request instanceof EmergencyHouseRequest) {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('RequestNotFound'), $emergencyhousePublicAccount, 'requests');
	print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('RequestNotFound').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}

$isOwner = $emergencyhousePublicAccount instanceof EmergencyHousePublicAccount
	&& (int) $emergencyhousePublicAccount->entity === (int) $request->entity
	&& (int) $emergencyhousePublicAccount->id === (int) $request->fk_account;
$privateValues = false;
if ($isOwner) {
	$privateValues = $listingService->decryptOwnedRequest($emergencyhousePublicAccount, $request);
}
$housingTypes = $listingService->fetchRequestHousingTypes($request);
$criteria = $listingService->fetchRequestCriteria($request);
$housingTypes = is_array($housingTypes) ? $housingTypes : array();
$criteria = is_array($criteria) ? $criteria : array();
$campaign = new EmergencyHouseCampaign($db);
$campaignLoaded = $campaign->fetch((int) $request->fk_campaign) > 0;

emergencyhousePublicRenderHeader(
	$request->title,
	$emergencyhousePublicAccount,
	'requests',
	false,
	false,
	array(
			'description' => (string) $request->description_public,
			'robots' => $isOwner ? 'noindex,nofollow' : 'noindex,follow',
			'og_type' => 'article',
		),
		array(
			'campaign_id' => (int) $request->fk_campaign,
			'content_type' => 'request',
			'content_id' => (int) $request->id,
		)
	);
print '<section class="eh-shell eh-section"><div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('AccommodationRequest').'</p>';
print '<h1>'.dol_escape_htmltag($request->title).'</h1><p>'.dol_escape_htmltag((string) $request->description_public).'</p></div>';
if ($saved) {
	emergencyhousePublicAlert('RequestSaved', 'success');
}
if ($isOwner) {
	print '<div class="eh-alert eh-alert-info">'.$langs->trans('OwnerPreviewNotice').' '.emergencyhousePublicListingStatus('request', (int) $request->status).'</div>';
}

print '<div class="eh-dashboard-grid"><article class="eh-card"><h2>'.$langs->trans('RequestDetails').'</h2><dl class="eh-description-list">';
print '<div><dt>'.$langs->trans('Campaign').'</dt><dd>'.($campaignLoaded ? dol_escape_htmltag($campaign->label) : $langs->trans('Unavailable')).'</dd></div>';
print '<div><dt>'.$langs->trans('PeopleToAccommodate').'</dt><dd>'.$langs->trans('PeopleCount', (int) $request->remaining_count).'</dd></div>';
print '<div><dt>'.$langs->trans('DesiredZone').'</dt><dd>'.dol_escape_htmltag($request->desired_zone).'</dd></div>';
print '<div><dt>'.$langs->trans('SearchRadiusKm').'</dt><dd>'.((int) $request->search_radius).' km</dd></div>';
print '<div><dt>'.$langs->trans('NeededPeriod').'</dt><dd>'.$langs->trans(
	'NeedFromDate',
	emergencyhousePublicDatabaseDate($db, (int) $request->date_start)
);
if (!empty($request->duration_unknown)) {
	print ' · '.$langs->trans('DurationUnknown');
} elseif (!empty($request->date_end)) {
	print ' · '.$langs->trans(
		'UntilDate',
		emergencyhousePublicDatabaseDate($db, (int) $request->date_end)
	);
}
print '</dd></div>';
print '<div><dt>'.$langs->trans('UrgencyLevel').'</dt><dd>'.$langs->trans('UrgencyLevelValue'.((int) $request->urgency_level)).'</dd></div>';
print '</dl>';
if (!empty($housingTypes)) {
	print '<h3>'.$langs->trans('AccommodationPreferences').'</h3><ul class="eh-list">';
	foreach ($housingTypes as $housingType) {
		print '<li>'.dol_escape_htmltag($langs->trans($housingType['label'])).' — '.$langs->trans('Preference'.ucfirst($housingType['level'])).'</li>';
	}
	print '</ul>';
}
if (!empty($criteria)) {
	print '<h3>'.$langs->trans('FeatureCriteria').'</h3><ul class="eh-list">';
	foreach ($criteria as $criterion) {
		print '<li>'.dol_escape_htmltag($langs->trans($criterion['label'])).' — '.$langs->trans('Preference'.ucfirst($criterion['level'])).'</li>';
	}
	print '</ul>';
}
print '</article><aside class="eh-card"><h2>'.$langs->trans($isOwner ? 'ManageRequest' : 'OfferHelp').'</h2>';
if ($isOwner) {
	print '<p>'.$langs->trans('OwnerPrivateRequestNotice').'</p>';
	if (is_array($privateValues)) {
		if ($privateValues['pickup_location'] !== '') {
			print '<p><strong>'.$langs->trans('PickupLocation').'</strong><br>'.nl2br(dol_escape_htmltag($privateValues['pickup_location'])).'</p>';
		}
		if ($privateValues['private_note'] !== '') {
			print '<p><strong>'.$langs->trans('PrivateNote').'</strong><br>'.nl2br(dol_escape_htmltag($privateValues['private_note'])).'</p>';
		}
	}
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/edit.php', array('id' => (int) $request->id))).'">'.$langs->trans('EditRequest').'</a>';
} elseif (!($emergencyhousePublicAccount instanceof EmergencyHousePublicAccount)) {
	print '<p>'.$langs->trans('LoginToOfferHelp').'</p>';
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/login.php', array('next' => emergencyhousePublicUrl('request/view.php', array('uuid' => $request->public_uuid))))).'">'.$langs->trans('Login').'</a>';
} else {
	print '<p>'.$langs->trans('SolicitationConsentExplanation').'</p>';
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/create.php', array('request' => (int) $request->id))).'">'.$langs->trans('OfferAccommodationForRequest').'</a>';
}
print '<p class="eh-help">'.$langs->trans('RequestContactProtected').'</p></aside></div>';
print '<div class="eh-actions"><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('report/create.php', array('type' => 'request', 'object' => (int) $request->id))).'">'.$langs->trans('ReportThisRequest').'</a></div>';
print '</section>';
emergencyhousePublicRenderFooter();
