<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res && file_exists('../../../../main.inc.php')) {
	$res = include '../../../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	exit;
}

dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_public.lib.php');

$langs->loadLangs(array('main', 'emergencyhouse@emergencyhouse'));

if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'configuration', 'write')) {
	accessforbidden();
}

emergencyhousePublicSendSecurityHeaders();
emergencyhousePublicRenderHeader($langs->trans('PublicPreview'), null, 'campaigns', false, true);

$settingsUrl = dol_buildpath('/emergencyhouse/admin/setup.php', 1).'?tab=portal';
print '<section class="eh-shell eh-section-tight">';
print '<div class="eh-alert eh-alert-info" role="status"><strong>'.$langs->trans('PrivatePreviewMode').'</strong>';
print '<p>'.$langs->trans('PrivatePreviewModeDescription').'</p>';
print '<a class="eh-button eh-button-secondary eh-button-small" href="'.dol_escape_htmltag($settingsUrl).'">'.$langs->trans('BackToSettings').'</a>';
print '</div></section>';

print '<section class="eh-hero"><div class="eh-shell eh-hero-grid"><div>';
print '<p class="eh-eyebrow">'.$langs->trans('SolidarityAccommodation').'</p>';
print '<h1>'.$langs->trans('PublicHeroTitle').'</h1>';
print '<p class="eh-lead">'.$langs->trans('PublicHeroDescription').'</p>';
print '<div class="eh-actions">';
print '<a class="eh-button" href="#preview-requests">'.$langs->trans('NeedAccommodationCta').'</a>';
print '<a class="eh-button eh-button-secondary" href="#preview-offers">'.$langs->trans('OfferAccommodationCta').'</a>';
print '</div></div>';
print '<aside class="eh-reassurance" aria-label="'.$langs->trans('ServiceCommitments').'">';
print emergencyhousePreviewReassurance('1', 'MutualConsentTitle', 'MutualConsentDescription');
print emergencyhousePreviewReassurance('2', 'ProtectedAddressTitle', 'ProtectedAddressDescription');
print emergencyhousePreviewReassurance('3', 'HumanSupportTitle', 'HumanSupportDescription');
print '</aside></div></section>';

print '<section id="preview-campaigns" class="eh-shell eh-section" aria-labelledby="preview-campaigns-title">';
print '<div class="eh-section-heading"><div><p class="eh-eyebrow">'.$langs->trans('CurrentEmergencies').'</p>';
print '<h2 id="preview-campaigns-title">'.$langs->trans('ActiveCampaigns').'</h2>';
print '<p>'.$langs->trans('PublicPreviewSampleDataHelp').'</p></div></div>';
print '<div class="eh-card-grid">';
for ($campaignIndex = 1; $campaignIndex <= 3; $campaignIndex++) {
	print '<article class="eh-card">';
	print '<p class="eh-eyebrow">'.$langs->trans('PreviewCoordinator').'</p>';
	print '<h3><a class="eh-card-link" href="#preview-offers">'.$langs->trans('PreviewCampaignTitle'.$campaignIndex).'</a></h3>';
	print '<p>'.$langs->trans('PreviewCampaignDescription'.$campaignIndex).'</p>';
	print '<div class="eh-card-meta"><span>'.$langs->trans('PreviewCampaignPeriod').'</span></div>';
	print '<div class="eh-card-footer"><span class="eh-badge">'.$langs->trans('PreviewAvailablePlaces').'</span>';
	print '<a href="#preview-offers">'.$langs->trans('ViewCampaign').'</a></div></article>';
}
print '</div></section>';

print '<section id="preview-offers" class="eh-shell eh-section" aria-labelledby="preview-offers-title">';
print '<div class="eh-section-heading"><div><h2 id="preview-offers-title">'.$langs->trans('Offers').'</h2>';
print '<p>'.$langs->trans('PreviewOffersDescription').'</p></div></div>';
print '<div class="eh-list-cards">';
print emergencyhousePreviewListing('PreviewOfferTitle1', 'PreviewOfferDescription1', 'PreviewOfferMeta1', 'Available');
print emergencyhousePreviewListing('PreviewOfferTitle2', 'PreviewOfferDescription2', 'PreviewOfferMeta2', 'Available');
print '</div></section>';

print '<section id="preview-requests" class="eh-shell eh-section" aria-labelledby="preview-requests-title">';
print '<div class="eh-section-heading"><div><h2 id="preview-requests-title">'.$langs->trans('Requests').'</h2>';
print '<p>'.$langs->trans('PreviewRequestsDescription').'</p></div></div>';
print '<div class="eh-list-cards">';
print emergencyhousePreviewListing('PreviewRequestTitle1', 'PreviewRequestDescription1', 'PreviewRequestMeta1', 'Urgent');
print emergencyhousePreviewListing('PreviewRequestTitle2', 'PreviewRequestDescription2', 'PreviewRequestMeta2', 'Pending');
print '</div></section>';

print '<section id="preview-account" class="eh-shell eh-section"><div class="eh-empty">';
print '<h2>'.$langs->trans('PreviewAccountTitle').'</h2><p>'.$langs->trans('PreviewAccountDescription').'</p>';
print '</div></section>';

emergencyhousePublicRenderFooter(true);
$db->close();

/**
 * Render one reassurance row in the private preview.
 *
 * @param string $number Number
 * @param string $titleKey Title translation key
 * @param string $descriptionKey Description translation key
 * @return string
 */
function emergencyhousePreviewReassurance($number, $titleKey, $descriptionKey)
{
	global $langs;

	return '<div class="eh-reassurance-item"><span class="eh-reassurance-icon" aria-hidden="true">'
		.dol_escape_htmltag($number).'</span><div><strong>'.$langs->trans($titleKey).'</strong><p>'
		.$langs->trans($descriptionKey).'</p></div></div>';
}

/**
 * Render one sample listing card.
 *
 * @param string $titleKey Title translation key
 * @param string $descriptionKey Description translation key
 * @param string $metaKey Metadata translation key
 * @param string $statusKey Status translation key
 * @return string
 */
function emergencyhousePreviewListing($titleKey, $descriptionKey, $metaKey, $statusKey)
{
	global $langs;

	$html = '<article class="eh-card eh-list-card"><div><h3>'.$langs->trans($titleKey).'</h3>';
	$html .= '<p>'.$langs->trans($descriptionKey).'</p>';
	$html .= '<div class="eh-card-meta"><span>'.$langs->trans($metaKey).'</span></div></div>';
	$html .= '<span class="eh-badge">'.$langs->trans($statusKey).'</span></article>';
	return $html;
}
