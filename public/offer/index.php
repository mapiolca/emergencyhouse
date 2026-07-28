<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');

$mine = GETPOSTINT('mine') > 0;
$page = max(0, GETPOSTINT('page'));
$limit = 24;
$campaignId = GETPOSTINT('campaign');
$town = trim(GETPOST('town', 'alphanohtml'));
$listingService = new EmergencyHouseListingService($db);
$campaigns = array();
$campaignOfferCounts = array();
$totalOffers = 0;
$totalPages = 1;

if ($mine) {
	$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
	$offers = $listingService->fetchOwnedOffers($account, $limit, $page * $limit);
	$pageTitle = $langs->trans('MyOffers');
} else {
	$account = $emergencyhousePublicAccount;
	$campaignRows = $listingService->fetchPublicOfferCampaigns($town);
	if (is_array($campaignRows)) {
		$campaigns = $campaignRows;
	}
	foreach ($campaigns as $campaign) {
		$currentCampaignId = isset($campaign['rowid']) ? (int) $campaign['rowid'] : 0;
		if ($currentCampaignId <= 0) {
			continue;
		}
		$campaignOfferCounts[$currentCampaignId] = isset($campaign['offer_count'])
			? max(0, (int) $campaign['offer_count'])
			: 0;
	}
	if ($campaignId > 0 && !isset($campaignOfferCounts[$campaignId])) {
		$campaignId = 0;
	}
	$totalOffers = $campaignId > 0
		? $campaignOfferCounts[$campaignId]
		: array_sum($campaignOfferCounts);
	$totalPages = max(1, (int) ceil($totalOffers / $limit));
	$page = min($page, $totalPages - 1);
	$offers = $listingService->fetchPublicOffers($campaignId, $limit, $page * $limit, $town);
	$pageTitle = $langs->trans('AvailableOffers');
}
if (!is_array($offers)) {
	$offers = array();
}

emergencyhousePublicRenderHeader(
	$pageTitle,
	$account,
	'offers',
	false,
	false,
	array(
		'description' => $langs->trans($mine ? 'MyOffersIntroduction' : 'PublicOffersIntroduction'),
		'robots' => $mine ? 'noindex,nofollow' : 'noindex,follow',
	)
);
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
		$currentCampaignId = isset($campaign['rowid']) ? (int) $campaign['rowid'] : 0;
		if ($currentCampaignId > 0) {
			$campaignOptions[$currentCampaignId] = isset($campaign['label']) ? (string) $campaign['label'] : '';
		}
	}
	if (!empty($campaignOptions)) {
		print '<form method="GET" action="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/index.php')).'" class="eh-toolbar">';
		print '<div class="eh-field"><label for="campaign">'.$langs->trans('Campaign').'</label>';
		print emergencyhousePublicSelect(
			'campaign',
			$campaignOptions,
			$campaignId,
			true,
			$langs->trans('AllCampaigns')
		);
		print '</div>';
		print '<div class="eh-field"><label for="town">'.$langs->trans('TownOrPublicZone').'</label>';
		print '<input type="search" id="town" name="town" maxlength="255" value="'.dol_escape_htmltag($town).'"></div>';
		print '<button class="eh-button eh-button-small" type="submit">'.$langs->trans('Search').'</button>';
		print '</form>';
	}

	if (!empty($campaigns)) {
		print '<section class="eh-campaign-overview eh-section-tight" aria-labelledby="campaign-overview-title">';
		print '<div class="eh-section-heading"><div><h2 id="campaign-overview-title">'.$langs->trans('CampaignsOverview').'</h2>';
		print '<p>'.$langs->trans('CampaignsOverviewHelp').'</p></div></div>';
		print '<div class="eh-campaign-overview-grid">';
		foreach ($campaigns as $campaign) {
			$currentCampaignId = isset($campaign['rowid']) ? (int) $campaign['rowid'] : 0;
			if ($currentCampaignId <= 0) {
				continue;
			}
			$summaryParams = array('campaign' => $currentCampaignId);
			if ($town !== '') {
				$summaryParams['town'] = $town;
			}
			$summaryUrl = emergencyhousePublicUrl('offer/index.php', $summaryParams);
			$summarySelected = $campaignId === $currentCampaignId;
			$summaryLabel = isset($campaign['label']) ? (string) $campaign['label'] : '';
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
			$campaignOfferCount = $campaignOfferCounts[$currentCampaignId];
			print '</p><span class="eh-badge">'.$langs->trans(
				$campaignOfferCount === 1 ? 'CampaignAvailableOfferCountOne' : 'CampaignAvailableOfferCount',
				$campaignOfferCount
			).'</span>';
			print '</article>';
		}
		print '</div></section>';
	}
}

if (!$mine && empty($campaigns)) {
	print '<div class="eh-empty eh-section-tight"><h2>'.$langs->trans('NoActiveCampaign').'</h2>';
	print '<p>'.$langs->trans('NoActiveCampaignHelp').'</p></div>';
} elseif (empty($offers)) {
	print '<div class="eh-empty eh-section-tight"><h2>'.$langs->trans($mine ? 'NoOwnedOffer' : 'NoPublicOffer').'</h2>';
	print '<p>'.$langs->trans($mine ? 'NoOwnedOfferHelp' : 'NoPublicOfferHelp').'</p></div>';
} elseif ($mine) {
	print '<div class="eh-card-grid eh-section-tight">';
	foreach ($offers as $offer) {
		$viewUrl = emergencyhousePublicUrl('offer/view.php', array('uuid' => (string) $offer['public_uuid']));
		print '<article class="eh-card">';
		print '<div class="eh-card-meta">';
		print emergencyhousePublicListingStatus('offer', (int) $offer['status']);
		print '<span>'.dol_escape_htmltag((string) $offer['campaign_label']).'</span>';
		print '</div>';
		print '<h2><a class="eh-card-link" href="'.dol_escape_htmltag($viewUrl).'">'.dol_escape_htmltag((string) $offer['title']).'</a></h2>';
		print '<p class="eh-card-meta"><span>'.dol_escape_htmltag((string) $offer['public_zone']).'</span>';
		print '<span>'.$langs->trans(
			'AvailabilityFromDate',
			emergencyhousePublicDatabaseDate($db, $offer['date_start'])
		).'</span></p>';
		print '<div class="eh-card-footer"><a href="'.dol_escape_htmltag($viewUrl).'">'.$langs->trans('ViewOffer').'</a>';
		print '<a href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php', array('id' => (int) $offer['rowid']))).'">'.$langs->trans('Edit').'</a>';
		print '</div></article>';
	}
	print '</div>';
	if (count($offers) === $limit) {
		$params = array('page' => $page + 1, 'mine' => 1);
		print '<p class="eh-pagination"><a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/index.php', $params)).'">'.$langs->trans('Next').'</a></p>';
	}
} else {
	/** @var array<int, array{label:string,slug:string,offers:array<int, array<string, int|string|null>>}> $offerGroups */
	$offerGroups = array();
	foreach ($offers as $offer) {
		$offerCampaignId = isset($offer['fk_campaign']) ? (int) $offer['fk_campaign'] : 0;
		if ($offerCampaignId <= 0) {
			continue;
		}
		if (!isset($offerGroups[$offerCampaignId])) {
			$offerGroups[$offerCampaignId] = array(
				'label' => isset($offer['campaign_label']) ? (string) $offer['campaign_label'] : '',
				'slug' => isset($offer['campaign_slug']) ? (string) $offer['campaign_slug'] : '',
				'offers' => array(),
			);
		}
		$offerGroups[$offerCampaignId]['offers'][] = $offer;
	}
	print '<section class="eh-listing-results eh-section-tight" aria-labelledby="offer-campaigns-title">';
	print '<div class="eh-section-heading"><div><h2 id="offer-campaigns-title">'.$langs->trans('Campaigns').'</h2></div></div>';
	print '<div class="eh-listing-groups">';
	foreach ($offerGroups as $offerCampaignId => $offerGroup) {
		$campaignPageUrl = emergencyhousePublicUrl('campaign.php', array('slug' => $offerGroup['slug']));
		$campaignOfferCount = $campaignOfferCounts[$offerCampaignId] ?? count($offerGroup['offers']);
		print '<section class="eh-listing-campaign-group" aria-labelledby="offer-campaign-'.$offerCampaignId.'">';
		print '<div class="eh-listing-campaign-heading"><div>';
		print '<h3 id="offer-campaign-'.$offerCampaignId.'">'.dol_escape_htmltag($offerGroup['label']).'</h3></div>';
		print '<div class="eh-listing-campaign-actions"><span class="eh-badge">'.$langs->trans(
			$campaignOfferCount === 1 ? 'CampaignAvailableOfferCountOne' : 'CampaignAvailableOfferCount',
			$campaignOfferCount
		).'</span>';
		print '<a href="'.dol_escape_htmltag($campaignPageUrl).'">'.$langs->trans('ViewCampaign').'</a></div></div>';
		print '<div class="eh-card-grid">';
		foreach ($offerGroup['offers'] as $offer) {
			$viewUrl = emergencyhousePublicUrl('offer/view.php', array('uuid' => (string) $offer['public_uuid']));
			print '<article class="eh-card">';
			print '<div class="eh-card-meta"><span>'.$langs->trans(
				'AvailablePlacesCount',
				(int) $offer['capacity_available']
			).'</span></div>';
			print '<h4><a class="eh-card-link" href="'.dol_escape_htmltag($viewUrl).'">';
			print dol_escape_htmltag((string) $offer['title']).'</a></h4>';
			print '<p class="eh-card-meta"><span>'.dol_escape_htmltag((string) $offer['public_zone']).'</span>';
			print '<span>'.$langs->trans(
				'AvailabilityFromDate',
				emergencyhousePublicDatabaseDate($db, $offer['date_start'])
			).'</span></p>';
			print '<div class="eh-card-footer"><a href="'.dol_escape_htmltag($viewUrl).'">'.$langs->trans('ViewOffer').'</a></div>';
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
		if ($town !== '') {
			$paginationParams['town'] = $town;
		}
		print '<nav class="eh-pagination" aria-label="'.$langs->trans('Pagination').'">';
		if ($page > 0) {
			$previousParams = $paginationParams;
			$previousParams['page'] = $page - 1;
			print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(
				emergencyhousePublicUrl('offer/index.php', $previousParams)
			).'">'.$langs->trans('Previous').'</a>';
		}
		print '<span class="eh-pagination-status">'.$langs->trans('PaginationPageOf', $page + 1, $totalPages).'</span>';
		if ($page + 1 < $totalPages) {
			$nextParams = $paginationParams;
			$nextParams['page'] = $page + 1;
			print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(
				emergencyhousePublicUrl('offer/index.php', $nextParams)
			).'">'.$langs->trans('Next').'</a>';
		}
		print '</nav>';
	}
}
print '</section>';
emergencyhousePublicRenderFooter();
