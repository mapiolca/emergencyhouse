<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');

$mine = GETPOSTINT('mine') > 0;
$page = max(0, GETPOSTINT('page'));
$limit = 24;
$offset = $page * $limit;
$campaignId = GETPOSTINT('campaign');
$town = trim(GETPOST('town', 'alphanohtml'));
$listingService = new EmergencyHouseListingService($db);
$campaigns = emergencyhousePublicCampaignOptions($db);

if ($campaignId <= 0 && !empty($campaigns)) {
	$campaignId = (int) $campaigns[0]['id'];
}

if ($mine) {
	$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
	$offers = $listingService->fetchOwnedOffers($account, $limit, $offset);
	$pageTitle = $langs->trans('MyOffers');
} else {
	$account = $emergencyhousePublicAccount;
	$offers = $campaignId > 0 ? $listingService->fetchPublicOffers($campaignId, $limit, $offset, $town) : array();
	$pageTitle = $langs->trans('AvailableOffers');
}
if (!is_array($offers)) {
	$offers = array();
}

emergencyhousePublicRenderHeader($pageTitle, $account, 'offers');
print '<section class="eh-shell eh-section">';
print '<div class="eh-section-heading"><div><p class="eh-eyebrow">'.$langs->trans($mine ? 'MySpace' : 'SolidarityAccommodation').'</p>';
print '<h1>'.$pageTitle.'</h1><p>'.$langs->trans($mine ? 'MyOffersIntroduction' : 'PublicOffersIntroduction').'</p></div>';
if ($account instanceof EmergencyHousePublicAccount) {
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php')).'">'.$langs->trans('CreateOffer').'</a>';
}
print '</div>';

if (!$mine) {
	$campaignOptions = array();
	foreach ($campaigns as $campaign) {
		$campaignOptions[(int) $campaign['id']] = (string) $campaign['label'];
	}
	print '<form method="GET" action="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/index.php')).'" class="eh-toolbar">';
	print '<div class="eh-field"><label for="campaign">'.$langs->trans('Campaign').'</label>';
	print emergencyhousePublicSelect('campaign', $campaignOptions, $campaignId, false);
	print '</div>';
	print '<div class="eh-field"><label for="town">'.$langs->trans('TownOrPublicZone').'</label>';
	print '<input type="search" id="town" name="town" maxlength="255" value="'.dol_escape_htmltag($town).'"></div>';
	print '<button class="eh-button eh-button-small" type="submit">'.$langs->trans('Search').'</button>';
	print '</form>';
}

if (empty($offers)) {
	print '<div class="eh-empty eh-section-tight"><h2>'.$langs->trans($mine ? 'NoOwnedOffer' : 'NoPublicOffer').'</h2>';
	print '<p>'.$langs->trans($mine ? 'NoOwnedOfferHelp' : 'NoPublicOfferHelp').'</p></div>';
} else {
	print '<div class="eh-card-grid eh-section-tight">';
	foreach ($offers as $offer) {
		$viewUrl = emergencyhousePublicUrl('offer/view.php', array('uuid' => (string) $offer['public_uuid']));
		print '<article class="eh-card">';
		print '<div class="eh-card-meta">';
		if ($mine) {
			print emergencyhousePublicListingStatus('offer', (int) $offer['status']);
			print '<span>'.dol_escape_htmltag((string) $offer['campaign_label']).'</span>';
		} else {
			print '<span>'.$langs->trans('AvailablePlacesCount', (int) $offer['capacity_available']).'</span>';
		}
		print '</div>';
		print '<h2><a class="eh-card-link" href="'.dol_escape_htmltag($viewUrl).'">'.dol_escape_htmltag((string) $offer['title']).'</a></h2>';
		print '<p class="eh-card-meta"><span>'.dol_escape_htmltag((string) $offer['public_zone']).'</span>';
		print '<span>'.$langs->trans(
			'AvailabilityFromDate',
			emergencyhousePublicDatabaseDate($db, $offer['date_start'])
		).'</span></p>';
		print '<div class="eh-card-footer"><a href="'.dol_escape_htmltag($viewUrl).'">'.$langs->trans('ViewOffer').'</a>';
		if ($mine) {
			print '<a href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php', array('id' => (int) $offer['rowid']))).'">'.$langs->trans('Edit').'</a>';
		}
		print '</div></article>';
	}
	print '</div>';
	if (count($offers) === $limit) {
		$params = array('page' => $page + 1);
		if ($mine) {
			$params['mine'] = 1;
		} else {
			$params['campaign'] = $campaignId;
			if ($town !== '') {
				$params['town'] = $town;
			}
		}
		print '<p class="eh-pagination"><a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/index.php', $params)).'">'.$langs->trans('NextPage').'</a></p>';
	}
}
print '</section>';
emergencyhousePublicRenderFooter();

