<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');

$mine = GETPOSTINT('mine') > 0;
$page = max(0, GETPOSTINT('page'));
$limit = 24;
$offset = $page * $limit;
$campaignId = GETPOSTINT('campaign');
$listingService = new EmergencyHouseListingService($db);
$campaigns = emergencyhousePublicCampaignOptions($db);

if ($campaignId <= 0 && !empty($campaigns)) {
	$campaignId = (int) $campaigns[0]['id'];
}

if ($mine) {
	$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
	$requests = $listingService->fetchOwnedRequests($account, $limit, $offset);
	$pageTitle = $langs->trans('MyRequests');
} else {
	$account = $emergencyhousePublicAccount;
	$campaign = $campaignId > 0 ? emergencyhousePublicFetchCampaign($db, $campaignId) : false;
	$canExposeRequests = $campaign instanceof EmergencyHouseCampaign
		&& in_array($campaign->public_visibility_mode, array('requests', 'both'), true);
	$requests = $canExposeRequests
		? $listingService->fetchPublicRequests($campaignId, $limit, $offset)
		: array();
	$pageTitle = $langs->trans('VisibleRequests');
}
if (!is_array($requests)) {
	$requests = array();
}

emergencyhousePublicRenderHeader($pageTitle, $account, 'requests');
print '<section class="eh-shell eh-section">';
print '<div class="eh-section-heading"><div><p class="eh-eyebrow">'.$langs->trans($mine ? 'MySpace' : 'SolidarityAccommodation').'</p>';
print '<h1>'.$pageTitle.'</h1><p>'.$langs->trans($mine ? 'MyRequestsIntroduction' : 'PublicRequestsIntroduction').'</p></div>';
if ($account instanceof EmergencyHousePublicAccount) {
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/edit.php')).'">'.$langs->trans('CreateRequest').'</a>';
}
print '</div>';

if (!$mine) {
	$campaignOptions = array();
	foreach ($campaigns as $campaignOption) {
		$campaignOptions[(int) $campaignOption['id']] = (string) $campaignOption['label'];
	}
	print '<form method="GET" action="'.dol_escape_htmltag(emergencyhousePublicUrl('request/index.php')).'" class="eh-toolbar">';
	print '<div class="eh-field"><label for="campaign">'.$langs->trans('Campaign').'</label>';
	print emergencyhousePublicSelect('campaign', $campaignOptions, $campaignId, false);
	print '</div><button class="eh-button eh-button-small" type="submit">'.$langs->trans('ApplyFilter').'</button></form>';
}

if (empty($requests)) {
	print '<div class="eh-empty eh-section-tight"><h2>'.$langs->trans($mine ? 'NoOwnedRequest' : 'NoPublicRequest').'</h2>';
	print '<p>'.$langs->trans($mine ? 'NoOwnedRequestHelp' : 'NoPublicRequestHelp').'</p></div>';
} else {
	print '<div class="eh-card-grid eh-section-tight">';
	foreach ($requests as $request) {
		$viewUrl = emergencyhousePublicUrl('request/view.php', array('uuid' => (string) $request['public_uuid']));
		print '<article class="eh-card">';
		print '<div class="eh-card-meta">';
		if ($mine) {
			print emergencyhousePublicListingStatus('request', (int) $request['status']);
			print '<span>'.dol_escape_htmltag((string) $request['campaign_label']).'</span>';
		} else {
			if ((int) $request['urgency_level'] >= 2) {
				print '<span class="eh-badge eh-badge-urgent">'.$langs->trans('UrgentNeed').'</span>';
			}
			print '<span>'.$langs->trans('PeopleCount', (int) $request['remaining_count']).'</span>';
		}
		print '</div>';
		print '<h2><a class="eh-card-link" href="'.dol_escape_htmltag($viewUrl).'">'.dol_escape_htmltag((string) $request['title']).'</a></h2>';
		print '<p class="eh-card-meta"><span>'.dol_escape_htmltag((string) $request['desired_zone']).'</span>';
		print '<span>'.$langs->trans(
			'NeedFromDate',
			emergencyhousePublicDatabaseDate($db, $request['date_start'])
		).'</span></p>';
		print '<div class="eh-card-footer"><a href="'.dol_escape_htmltag($viewUrl).'">'.$langs->trans('ViewRequest').'</a>';
		if ($mine) {
			print '<a href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/edit.php', array('id' => (int) $request['rowid']))).'">'.$langs->trans('Edit').'</a>';
		}
		print '</div></article>';
	}
	print '</div>';
	if (count($requests) === $limit) {
		$params = array('page' => $page + 1);
		if ($mine) {
			$params['mine'] = 1;
		} else {
			$params['campaign'] = $campaignId;
		}
		print '<p class="eh-pagination"><a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/index.php', $params)).'">'.$langs->trans('NextPage').'</a></p>';
	}
}
print '</section>';
emergencyhousePublicRenderFooter();

