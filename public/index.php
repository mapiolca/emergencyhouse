<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

$sql = 'SELECT c.rowid, c.slug, c.label, c.description_public, c.banner_text, c.date_start, c.date_end, c.coordinator_name, c.official_phone,';
$sql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
$sql .= ' WHERE o.entity = c.entity AND o.fk_campaign = c.rowid AND o.status = 2 AND o.capacity_available > 0) AS offer_count,';
$sql .= ' (SELECT COALESCE(SUM(o2.capacity_available), 0) FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o2';
$sql .= ' WHERE o2.entity = c.entity AND o2.fk_campaign = c.rowid AND o2.status = 2) AS place_count,';
$sql .= ' (SELECT COALESCE(SUM(r.remaining_count), 0) FROM '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
$sql .= ' WHERE r.entity = c.entity AND r.fk_campaign = c.rowid AND r.status IN (1,2)) AS need_count';
$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c';
$sql .= ' WHERE c.entity = '.((int) $conf->entity).' AND c.status = 1';
$sql .= " AND c.date_start <= '".$db->idate(dol_now())."'";
$sql .= " AND (c.date_end IS NULL OR c.date_end >= '".$db->idate(dol_now())."')";
$sql .= ' ORDER BY c.date_start DESC, c.rowid DESC';
$resql = $db->query($sql);
$campaigns = array();
if ($resql) {
	while (is_object($obj = $db->fetch_object($resql))) {
		$campaigns[] = $obj;
	}
}

$homeCanonical = emergencyhousePublicAbsoluteUrl();
emergencyhousePublicRenderHeader(
	$langs->trans('EmergencyHousePublicHome'),
	$emergencyhousePublicAccount,
	'campaigns',
	true,
	false,
	array(
		'description' => $langs->trans('PublicHeroDescription'),
		'canonical' => $homeCanonical,
		'structured_data' => emergencyhousePublicHomeStructuredData($homeCanonical),
	)
);
print '<section class="eh-hero"><div class="eh-shell eh-hero-grid"><div>';
print '<p class="eh-eyebrow">'.$langs->trans('SolidarityAccommodation').'</p>';
print '<h1>'.$langs->trans('PublicHeroTitle').'</h1>';
print '<p class="eh-lead">'.$langs->trans('PublicHeroDescription').'</p>';
print '<div class="eh-actions">';
print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/edit.php')).'">'.$langs->trans('NeedAccommodationCta').'</a>';
print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php')).'">'.$langs->trans('OfferAccommodationCta').'</a>';
print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('contact.php')).'">'.$langs->trans('ContactUs').'</a>';
print '</div></div>';
print '<aside class="eh-reassurance" aria-label="'.$langs->trans('ServiceCommitments').'">';
print emergencyhousePublicReassurance('1', 'MutualConsentTitle', 'MutualConsentDescription');
print emergencyhousePublicReassurance('2', 'ProtectedAddressTitle', 'ProtectedAddressDescription');
print emergencyhousePublicReassurance('3', 'HumanSupportTitle', 'HumanSupportDescription');
print '</aside></div></section>';

print '<section class="eh-shell eh-section" aria-labelledby="campaigns-title">';
print '<div class="eh-section-heading"><div><p class="eh-eyebrow">'.$langs->trans('CurrentEmergencies').'</p>';
print '<h2 id="campaigns-title">'.$langs->trans('ActiveCampaigns').'</h2><p>'.$langs->trans('ChooseCampaignHelp').'</p></div></div>';
if (empty($campaigns)) {
	print '<div class="eh-empty"><h3>'.$langs->trans('NoActiveCampaign').'</h3><p>'.$langs->trans('NoActiveCampaignHelp').'</p></div>';
} else {
	print '<div class="eh-card-grid">';
	foreach ($campaigns as $campaign) {
		$url = emergencyhousePublicUrl('campaign.php', array('slug' => (string) $campaign->slug));
		print '<article class="eh-card">';
		print '<p class="eh-eyebrow">'.dol_escape_htmltag((string) $campaign->coordinator_name).'</p>';
		print '<h3><a class="eh-card-link" href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag((string) $campaign->label).'</a></h3>';
		if (!empty($campaign->description_public)) {
			print '<p>'.dol_escape_htmltag(dol_trunc((string) $campaign->description_public, 240)).'</p>';
		}
		print '<div class="eh-card-meta"><span>'.$langs->trans('FromDate', dol_print_date($db->jdate($campaign->date_start), 'day')).'</span>';
		if (!empty($campaign->date_end)) {
			print '<span>'.$langs->trans('UntilDate', dol_print_date($db->jdate($campaign->date_end), 'day')).'</span>';
		}
		print '</div><div class="eh-card-footer">';
		print '<span class="eh-badge">'.$langs->trans('AvailablePlacesCount', emergencyhousePublicApproximateCount((int) $campaign->place_count)).'</span>';
		print '<a href="'.dol_escape_htmltag($url).'">'.$langs->trans('ViewCampaign').'</a></div></article>';
	}
	print '</div>';
}
print '</section>';

print '<section class="eh-shell eh-section"><div class="eh-card">';
print '<div class="eh-section-heading"><div><h2>'.$langs->trans('EmergencyNumbersTitle').'</h2><p>'.$langs->trans('EmergencyNumbersDescription').'</p></div></div>';
print '<p><strong>'.$langs->trans('EmergencyCallReminder').'</strong></p>';
print '</div></section>';
emergencyhousePublicRenderFooter();

/**
 * Render a reassurance row.
 *
 * @param string $number Number
 * @param string $titleKey Title key
 * @param string $descriptionKey Description key
 * @return string
 */
function emergencyhousePublicReassurance($number, $titleKey, $descriptionKey)
{
	global $langs;
	return '<div class="eh-reassurance-item"><span class="eh-reassurance-icon" aria-hidden="true">'
		.dol_escape_htmltag($number).'</span><div><strong>'.$langs->trans($titleKey).'</strong><p>'
		.$langs->trans($descriptionKey).'</p></div></div>';
}

/**
 * Protect small public counts from re-identification.
 *
 * @param int $count Count
 * @return string
 */
function emergencyhousePublicApproximateCount($count)
{
	global $langs;
	$threshold = max(3, getDolGlobalInt('EMERGENCYHOUSE_PUBLIC_STAT_MIN_COUNT', 3));
	return $count > 0 && $count < $threshold ? $langs->trans('LessThanCount', $threshold) : (string) $count;
}
