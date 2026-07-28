<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');

$mine = GETPOSTINT('mine') > 0;
$page = max(0, GETPOSTINT('page'));
$limit = 24;
$campaignId = GETPOSTINT('campaign');
$zone = trim(GETPOST('zone', 'alphanohtml'));
$listingService = new EmergencyHouseListingService($db);
$campaigns = array();
$campaignRequestCounts = array();
$totalRequests = 0;
$totalPages = 1;

if ($mine) {
	$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
	$requests = $listingService->fetchOwnedRequests($account, $limit, $page * $limit);
	$pageTitle = $langs->trans('MyRequests');
} else {
	$account = $emergencyhousePublicAccount;
	$campaignRows = $listingService->fetchPublicRequestCampaigns($zone);
	if (is_array($campaignRows)) {
		$campaigns = $campaignRows;
	}
	foreach ($campaigns as $campaign) {
		$currentCampaignId = isset($campaign['rowid']) ? (int) $campaign['rowid'] : 0;
		if ($currentCampaignId <= 0) {
			continue;
		}
		$campaignRequestCounts[$currentCampaignId] = isset($campaign['request_count'])
			? max(0, (int) $campaign['request_count'])
			: 0;
	}
	if ($campaignId > 0 && !isset($campaignRequestCounts[$campaignId])) {
		$campaignId = 0;
	}
	$totalRequests = $campaignId > 0
		? $campaignRequestCounts[$campaignId]
		: array_sum($campaignRequestCounts);
	$totalPages = max(1, (int) ceil($totalRequests / $limit));
	$page = min($page, $totalPages - 1);
	$requests = $listingService->fetchPublicRequests($campaignId, $limit, $page * $limit, $zone);
	$pageTitle = $langs->trans('VisibleRequests');
}
if (!is_array($requests)) {
	$requests = array();
}

emergencyhousePublicRenderHeader(
	$pageTitle,
	$account,
	'requests',
	false,
	false,
	array(
		'description' => $langs->trans($mine ? 'MyRequestsIntroduction' : 'PublicRequestsIntroduction'),
		'robots' => $mine ? 'noindex,nofollow' : 'noindex,follow',
	)
);
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
		$currentCampaignId = isset($campaignOption['rowid']) ? (int) $campaignOption['rowid'] : 0;
		if ($currentCampaignId > 0) {
			$campaignOptions[$currentCampaignId] = isset($campaignOption['label']) ? (string) $campaignOption['label'] : '';
		}
	}
	if (!empty($campaignOptions)) {
		print '<form method="GET" action="'.dol_escape_htmltag(emergencyhousePublicUrl('request/index.php')).'" class="eh-toolbar">';
		print '<div class="eh-field"><label for="campaign">'.$langs->trans('Campaign').'</label>';
		print emergencyhousePublicSelect(
			'campaign',
			$campaignOptions,
			$campaignId,
			true,
			$langs->trans('AllCampaigns')
		);
		print '</div>';
		print '<div class="eh-field"><label for="zone">'.$langs->trans('DesiredZone').'</label>';
		print '<input type="search" id="zone" name="zone" maxlength="255" value="'.dol_escape_htmltag($zone).'"></div>';
		print '<button class="eh-button eh-button-small" type="submit">'.$langs->trans('Search').'</button>';
		print '</form>';
	}

	if (!empty($campaigns)) {
		print '<section class="eh-campaign-overview eh-section-tight" aria-labelledby="request-campaign-overview-title">';
		print '<div class="eh-section-heading"><div><h2 id="request-campaign-overview-title">'.$langs->trans('CampaignsOverview').'</h2>';
		print '<p>'.$langs->trans('RequestCampaignsOverviewHelp').'</p></div></div>';
		print '<div class="eh-campaign-overview-grid">';
		foreach ($campaigns as $campaign) {
			$currentCampaignId = isset($campaign['rowid']) ? (int) $campaign['rowid'] : 0;
			if ($currentCampaignId <= 0) {
				continue;
			}
			$summaryParams = array('campaign' => $currentCampaignId);
			if ($zone !== '') {
				$summaryParams['zone'] = $zone;
			}
			$summaryUrl = emergencyhousePublicUrl('request/index.php', $summaryParams);
			$summarySelected = $campaignId === $currentCampaignId;
			$summaryLabel = isset($campaign['label']) ? (string) $campaign['label'] : '';
			$requestCount = $campaignRequestCounts[$currentCampaignId];
			print '<article class="eh-campaign-summary'.($summarySelected ? ' is-selected' : '').'">';
			print '<h3><a href="'.dol_escape_htmltag($summaryUrl).'"'.($summarySelected ? ' aria-current="true"' : '').'>';
			print dol_escape_htmltag($summaryLabel).'</a></h3>';
			print '<p class="eh-card-meta"><span>'.$langs->trans(
				'CampaignFromDate',
				emergencyhousePublicDatabaseDate($db, $campaign['date_start'] ?? null)
			).'</span>';
			if (!empty($campaign['date_end'])) {
				print '<span>'.$langs->trans(
					'UntilDate',
					emergencyhousePublicDatabaseDate($db, $campaign['date_end'])
				).'</span>';
			}
			print '</p><span class="eh-badge">'.$langs->trans(
				$requestCount === 1 ? 'CampaignVisibleRequestCountOne' : 'CampaignVisibleRequestCount',
				$requestCount
			).'</span>';
			print '</article>';
		}
		print '</div></section>';
	}
}

if (!$mine && empty($campaigns)) {
	print '<div class="eh-empty eh-section-tight"><h2>'.$langs->trans('NoActiveCampaign').'</h2>';
	print '<p>'.$langs->trans('NoActiveCampaignHelp').'</p></div>';
} elseif (empty($requests)) {
	print '<div class="eh-empty eh-section-tight"><h2>'.$langs->trans($mine ? 'NoOwnedRequest' : 'NoPublicRequest').'</h2>';
	print '<p>'.$langs->trans($mine ? 'NoOwnedRequestHelp' : 'NoPublicRequestHelp').'</p></div>';
} elseif ($mine) {
	print '<div class="eh-card-grid eh-section-tight">';
	foreach ($requests as $request) {
		$viewUrl = emergencyhousePublicUrl('request/view.php', array('uuid' => (string) $request['public_uuid']));
		print '<article class="eh-card">';
		print '<div class="eh-card-meta">';
		print emergencyhousePublicListingStatus('request', (int) $request['status']);
		print '<span>'.dol_escape_htmltag((string) $request['campaign_label']).'</span>';
		print '</div>';
		print '<h2><a class="eh-card-link" href="'.dol_escape_htmltag($viewUrl).'">'.dol_escape_htmltag((string) $request['title']).'</a></h2>';
		print '<p class="eh-card-meta"><span>'.dol_escape_htmltag((string) $request['desired_zone']).'</span>';
		print '<span>'.$langs->trans(
			'NeedFromDate',
			emergencyhousePublicDatabaseDate($db, $request['date_start'])
		).'</span></p>';
		print '<div class="eh-card-footer"><a href="'.dol_escape_htmltag($viewUrl).'">'.$langs->trans('ViewRequest').'</a>';
		print '<a href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/edit.php', array('id' => (int) $request['rowid']))).'">'.$langs->trans('Edit').'</a>';
		print '</div></article>';
	}
	print '</div>';
	if (count($requests) === $limit) {
		$params = array('page' => $page + 1, 'mine' => 1);
		print '<p class="eh-pagination"><a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/index.php', $params)).'">'.$langs->trans('Next').'</a></p>';
	}
} else {
	/** @var array<int, array{label:string,slug:string,requests:array<int, array<string, int|string|null>>}> $requestGroups */
	$requestGroups = array();
	foreach ($requests as $request) {
		$requestCampaignId = isset($request['fk_campaign']) ? (int) $request['fk_campaign'] : 0;
		if ($requestCampaignId <= 0) {
			continue;
		}
		if (!isset($requestGroups[$requestCampaignId])) {
			$requestGroups[$requestCampaignId] = array(
				'label' => isset($request['campaign_label']) ? (string) $request['campaign_label'] : '',
				'slug' => isset($request['campaign_slug']) ? (string) $request['campaign_slug'] : '',
				'requests' => array(),
			);
		}
		$requestGroups[$requestCampaignId]['requests'][] = $request;
	}
	print '<section class="eh-listing-results eh-section-tight" aria-labelledby="request-campaigns-title">';
	print '<div class="eh-section-heading"><div><h2 id="request-campaigns-title">'.$langs->trans('Campaigns').'</h2></div></div>';
	print '<div class="eh-listing-groups">';
	foreach ($requestGroups as $requestCampaignId => $requestGroup) {
		$campaignPageUrl = emergencyhousePublicUrl('campaign.php', array('slug' => $requestGroup['slug']));
		$requestCount = $campaignRequestCounts[$requestCampaignId] ?? count($requestGroup['requests']);
		print '<section class="eh-listing-campaign-group" aria-labelledby="request-campaign-'.$requestCampaignId.'">';
		print '<div class="eh-listing-campaign-heading"><div>';
		print '<h3 id="request-campaign-'.$requestCampaignId.'">'.dol_escape_htmltag($requestGroup['label']).'</h3></div>';
		print '<div class="eh-listing-campaign-actions"><span class="eh-badge">'.$langs->trans(
			$requestCount === 1 ? 'CampaignVisibleRequestCountOne' : 'CampaignVisibleRequestCount',
			$requestCount
		).'</span>';
		print '<a href="'.dol_escape_htmltag($campaignPageUrl).'">'.$langs->trans('ViewCampaign').'</a></div></div>';
		print '<div class="eh-card-grid">';
		foreach ($requestGroup['requests'] as $request) {
			$viewUrl = emergencyhousePublicUrl('request/view.php', array('uuid' => (string) $request['public_uuid']));
			print '<article class="eh-card"><div class="eh-card-meta">';
			if ((int) $request['urgency_level'] >= 2) {
				print '<span class="eh-badge eh-badge-urgent">'.$langs->trans('UrgentNeed').'</span>';
			}
			print '<span>'.$langs->trans('PeopleCount', (int) $request['remaining_count']).'</span></div>';
			print '<h4><a class="eh-card-link" href="'.dol_escape_htmltag($viewUrl).'">';
			print dol_escape_htmltag((string) $request['title']).'</a></h4>';
			print '<p class="eh-card-meta"><span>'.dol_escape_htmltag((string) $request['desired_zone']).'</span>';
			print '<span>'.$langs->trans(
				'NeedFromDate',
				emergencyhousePublicDatabaseDate($db, $request['date_start'])
			).'</span></p>';
			print '<div class="eh-card-footer"><a href="'.dol_escape_htmltag($viewUrl).'">'.$langs->trans('ViewRequest').'</a></div>';
			print '</article>';
		}
		print '</div></section>';
	}
	print '</div></section>';

	if ($totalPages > 1) {
		$paginationParams = array();
		if ($campaignId > 0) {
			$paginationParams['campaign'] = $campaignId;
		}
		if ($zone !== '') {
			$paginationParams['zone'] = $zone;
		}
		print '<nav class="eh-pagination" aria-label="'.$langs->trans('Pagination').'">';
		if ($page > 0) {
			$previousParams = $paginationParams;
			$previousParams['page'] = $page - 1;
			print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(
				emergencyhousePublicUrl('request/index.php', $previousParams)
			).'">'.$langs->trans('Previous').'</a>';
		}
		print '<span class="eh-pagination-status">'.$langs->trans('PaginationPageOf', $page + 1, $totalPages).'</span>';
		if ($page + 1 < $totalPages) {
			$nextParams = $paginationParams;
			$nextParams['page'] = $page + 1;
			print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(
				emergencyhousePublicUrl('request/index.php', $nextParams)
			).'">'.$langs->trans('Next').'</a>';
		}
		print '</nav>';
	}
}
print '</section>';
emergencyhousePublicRenderFooter();
