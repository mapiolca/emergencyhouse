<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

dol_include_once('/emergencyhouse/class/campaign.class.php');

if (!headers_sent()) {
	header('Content-Type: text/markdown; charset=UTF-8');
	header('Cache-Control: public, max-age=300');
}

print '# '.$langs->trans('EmergencyHouse')."\n\n";
print '> '.$langs->trans('PublicHeroDescription')."\n\n";
print "## ".$langs->trans('ReferencePages')."\n\n";
print '- ['.$langs->trans('Home').']('.emergencyhousePublicAbsoluteUrl().")\n";
print '- ['.$langs->trans('ContactUs').']('.emergencyhousePublicAbsoluteUrl('contact.php').")\n";
print '- ['.$langs->trans('Accessibility').']('.emergencyhousePublicAbsoluteUrl('accessibility.php').")\n";
if (emergencyhousePublicLegalPageIsPublished('EMERGENCYHOUSE_PUBLIC_PRIVACY_ENABLED', 'EMERGENCYHOUSE_PUBLIC_PRIVACY_HTML')) {
	print '- ['.$langs->trans('PrivacyPolicy').']('.emergencyhousePublicAbsoluteUrl('privacy.php').")\n";
}
if (emergencyhousePublicLegalPageIsPublished('EMERGENCYHOUSE_PUBLIC_TERMS_ENABLED', 'EMERGENCYHOUSE_PUBLIC_TERMS_HTML')) {
	print '- ['.$langs->trans('TermsOfUse').']('.emergencyhousePublicAbsoluteUrl('terms.php').")\n";
}

$sql = 'SELECT slug, label, description_public, tms FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
$sql .= ' WHERE entity = '.((int) $conf->entity);
$sql .= ' AND status = '.EmergencyHouseCampaign::STATUS_PUBLISHED;
$sql .= ' AND robots_index = 1';
$sql .= " AND description_public IS NOT NULL AND TRIM(description_public) <> ''";
$sql .= " AND official_instructions IS NOT NULL AND TRIM(official_instructions) <> ''";
$sql .= " AND date_start <= '".$db->idate(dol_now())."'";
$sql .= " AND (date_end IS NULL OR date_end >= '".$db->idate(dol_now())."')";
$sql .= ' ORDER BY date_start DESC, rowid DESC';
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
	print "\n## ".$langs->trans('ActiveCampaigns')."\n\n";
	while (is_object($obj = $db->fetch_object($resql))) {
		$label = str_replace(array('[', ']'), '', (string) $obj->label);
		$description = emergencyhousePublicPlainText((string) $obj->description_public);
		$url = emergencyhousePublicAbsoluteUrl('campaign.php', array('slug' => (string) $obj->slug));
		print '- ['.$label.']('.$url.')';
		if ($description !== '') {
			print ' — '.dol_trunc($description, 240);
		}
		print "\n";
	}
} elseif (!$resql) {
	dol_syslog(__FILE__.': unable to build LLM index: '.$db->lasterror(), LOG_ERR);
}

print "\n## ".$langs->trans('ContentPolicy')."\n\n";
print $langs->trans('LlmIndexPrivacyNotice')."\n";
