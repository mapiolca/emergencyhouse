<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');

$slug = GETPOST('slug', 'aZ09');
$campaign = emergencyhousePublicFetchCampaign($db, 0, $slug);
if (!$campaign instanceof EmergencyHouseCampaign) {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('CampaignNotFound'), $emergencyhousePublicAccount, 'campaigns');
	print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('CampaignNotFound').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}

$statsSql = 'SELECT';
$statsSql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer WHERE entity = '.((int) $campaign->entity).' AND fk_campaign = '.((int) $campaign->id).' AND status = 2) AS offers,';
$statsSql .= ' (SELECT COALESCE(SUM(capacity_available), 0) FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer WHERE entity = '.((int) $campaign->entity).' AND fk_campaign = '.((int) $campaign->id).' AND status = 2) AS places,';
$statsSql .= ' (SELECT COALESCE(SUM(remaining_count), 0) FROM '.MAIN_DB_PREFIX.'emergencyhouse_request WHERE entity = '.((int) $campaign->entity).' AND fk_campaign = '.((int) $campaign->id).' AND status IN (1,2)) AS needs,';
$statsSql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'emergencyhouse_allocation WHERE entity = '.((int) $campaign->entity).' AND fk_campaign = '.((int) $campaign->id).' AND status IN (2,3,4,5)) AS allocations';
$statsResult = $db->query($statsSql);
$stats = $statsResult ? $db->fetch_object($statsResult) : false;

$listingService = new EmergencyHouseListingService($db);
$offers = $listingService->fetchPublicOffers((int) $campaign->id, 6, 0);
if (!is_array($offers)) {
	$offers = array();
}
$campaignIndexable = !empty($campaign->robots_index)
	&& emergencyhousePublicHtmlHasContent((string) $campaign->description_public)
	&& emergencyhousePublicHtmlHasContent((string) $campaign->official_instructions)
	&& (int) $campaign->date_start <= dol_now()
	&& (empty($campaign->date_end) || (int) $campaign->date_end >= dol_now());
$campaignCanonical = emergencyhousePublicAbsoluteUrl('campaign.php', array('slug' => (string) $campaign->slug));
/** @var array<string, mixed> Heterogeneous search metadata, including a recursive JSON-LD tree. */
$campaignSeo = array(
	'description' => (string) $campaign->description_public,
	'og_type' => 'article',
	'robots' => 'noindex,follow',
);
if ($campaignIndexable) {
	$campaignSeo['canonical'] = $campaignCanonical;
	$campaignSeo['structured_data'] = emergencyhousePublicCampaignStructuredData($campaign, $campaignCanonical);
}

emergencyhousePublicRenderHeader(
	(string) $campaign->label,
	$emergencyhousePublicAccount,
	'campaigns',
	$campaignIndexable,
	false,
	$campaignSeo
);
print '<section class="eh-hero"><div class="eh-shell eh-hero-grid"><div>';
print '<p class="eh-eyebrow">'.dol_escape_htmltag($campaign->coordinator_name).'</p>';
print '<h1>'.dol_escape_htmltag($campaign->label).'</h1>';
if (!empty($campaign->banner_text)) print '<p class="eh-lead">'.dol_escape_htmltag($campaign->banner_text).'</p>';
print '<div class="eh-actions">';
print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/edit.php', array('campaign' => (int) $campaign->id))).'">'.$langs->trans('NeedAccommodationCta').'</a>';
print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php', array('campaign' => (int) $campaign->id))).'">'.$langs->trans('OfferAccommodationCta').'</a>';
print '</div></div>';
print '<aside class="eh-card"><h2>'.$langs->trans('OfficialInformation').'</h2>';
print '<p>'.dol_escape_htmltag((string) $campaign->description_public).'</p>';
print '<p><strong>'.$langs->trans('CampaignPeriod').'</strong><br>';
print $langs->trans(
	'CampaignFromDate',
	emergencyhousePublicDatabaseDate($db, (int) $campaign->date_start)
);
if (!empty($campaign->date_end)) {
	print ' · '.$langs->trans(
		'UntilDate',
		emergencyhousePublicDatabaseDate($db, (int) $campaign->date_end)
	);
}
print '</p>';
print '<p><strong>'.$langs->trans('OfficialPhone').'</strong><br><a href="tel:'.dol_escape_htmltag((string) $campaign->official_phone).'">'.dol_escape_htmltag((string) $campaign->official_phone).'</a></p>';
print '<p class="eh-card-meta">'.$langs->trans('LastUpdateAt', dol_print_date((int) $campaign->tms, 'dayhour')).'</p>';
print '</aside></div></section>';

if (is_object($stats)) {
	print '<section class="eh-shell eh-section-tight" aria-label="'.$langs->trans('CampaignKeyFigures').'"><div class="eh-stat-grid">';
	print emergencyhouseCampaignStat((int) $stats->offers, 'ActiveOffers');
	print emergencyhouseCampaignStat((int) $stats->places, 'AvailablePlaces');
	print emergencyhouseCampaignStat((int) $stats->needs, 'PeopleStillSeeking');
	print emergencyhouseCampaignStat((int) $stats->allocations, 'ConfirmedAccommodations');
	print '</div></section>';
}

print '<section class="eh-shell eh-section"><div class="eh-section-heading"><div><h2>'.$langs->trans('OfficialInstructions').'</h2></div></div>';
print '<div class="eh-card"><p>'.nl2br(dol_escape_htmltag((string) $campaign->official_instructions)).'</p>';
if (!empty($campaign->eligibility_text)) print '<h3>'.$langs->trans('EligibilityConditions').'</h3><p>'.nl2br(dol_escape_htmltag((string) $campaign->eligibility_text)).'</p>';
print '</div></section>';

print '<section class="eh-shell eh-section"><div class="eh-section-heading"><div><h2>'.$langs->trans('RecentlyAvailableOffers').'</h2><p>'.$langs->trans('ExactAddressesProtected').'</p></div>';
print '<a href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/index.php', array('campaign' => (int) $campaign->id))).'">'.$langs->trans('ViewAllOffers').'</a></div>';
if (empty($offers)) {
	print '<div class="eh-empty">'.$langs->trans('NoPublicOffer').'</div>';
} else {
	print '<div class="eh-card-grid">';
	foreach ($offers as $offer) {
		print '<article class="eh-card"><span class="eh-badge">'.$langs->trans((string) ($offer['housing_type_label'] ?? 'HousingTypeOther')).'</span>';
		print '<h3>'.dol_escape_htmltag((string) $offer['title']).'</h3>';
		print '<p>'.dol_escape_htmltag(dol_trunc((string) ($offer['description_public'] ?? ''), 180)).'</p>';
		print '<div class="eh-card-meta"><span>'.dol_escape_htmltag((string) $offer['public_zone']).'</span>';
		print '<span>'.$langs->trans('PlacesCount', (int) $offer['capacity_available']).'</span></div>';
		print '<div class="eh-card-footer"><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/view.php', array('uuid' => (string) $offer['public_uuid']))).'">'.$langs->trans('ViewOffer').'</a></div></article>';
	}
	print '</div>';
}
print '</section>';
emergencyhousePublicRenderFooter();

/**
 * Render one anonymized campaign statistic.
 *
 * @param int $value Value
 * @param string $labelKey Label
 * @return string
 */
function emergencyhouseCampaignStat($value, $labelKey)
{
	global $langs;
	$threshold = max(3, getDolGlobalInt('EMERGENCYHOUSE_PUBLIC_STAT_MIN_COUNT', 3));
	$display = $value > 0 && $value < $threshold ? $langs->trans('LessThanCount', $threshold) : (string) $value;
	return '<div class="eh-stat"><strong>'.dol_escape_htmltag($display).'</strong><span>'.$langs->trans($labelKey).'</span></div>';
}
