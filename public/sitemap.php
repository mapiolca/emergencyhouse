<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

dol_include_once('/emergencyhouse/class/campaign.class.php');

if (!headers_sent()) {
	header('Content-Type: application/xml; charset=UTF-8');
	header('Cache-Control: public, max-age=300');
	header('X-Robots-Tag: noindex', true);
}

/**
 * @var array<int, array{loc:string,lastmod?:string}> $urls
 */
$urls = array(
	array('loc' => emergencyhousePublicAbsoluteUrl()),
	array('loc' => emergencyhousePublicAbsoluteUrl('contact.php')),
	array('loc' => emergencyhousePublicAbsoluteUrl('accessibility.php')),
);
if (emergencyhousePublicLegalPageIsPublished('EMERGENCYHOUSE_PUBLIC_PRIVACY_ENABLED', 'EMERGENCYHOUSE_PUBLIC_PRIVACY_HTML')) {
	$urls[] = array('loc' => emergencyhousePublicAbsoluteUrl('privacy.php'));
}
if (emergencyhousePublicLegalPageIsPublished('EMERGENCYHOUSE_PUBLIC_TERMS_ENABLED', 'EMERGENCYHOUSE_PUBLIC_TERMS_HTML')) {
	$urls[] = array('loc' => emergencyhousePublicAbsoluteUrl('terms.php'));
}

$sql = 'SELECT slug, tms FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
$sql .= ' WHERE entity = '.((int) $conf->entity);
$sql .= ' AND status = '.EmergencyHouseCampaign::STATUS_PUBLISHED;
$sql .= ' AND robots_index = 1';
$sql .= " AND description_public IS NOT NULL AND TRIM(description_public) <> ''";
$sql .= " AND official_instructions IS NOT NULL AND TRIM(official_instructions) <> ''";
$sql .= " AND date_start <= '".$db->idate(dol_now())."'";
$sql .= " AND (date_end IS NULL OR date_end >= '".$db->idate(dol_now())."')";
$sql .= ' ORDER BY date_start DESC, rowid DESC';
$resql = $db->query($sql);
if ($resql) {
	while (is_object($obj = $db->fetch_object($resql))) {
		$entry = array(
			'loc' => emergencyhousePublicAbsoluteUrl('campaign.php', array('slug' => (string) $obj->slug)),
		);
		if (!empty($obj->tms)) {
			$entry['lastmod'] = gmdate('c', (int) $db->jdate($obj->tms));
		}
		$urls[] = $entry;
	}
} else {
	dol_syslog(__FILE__.': unable to build campaign sitemap: '.$db->lasterror(), LOG_ERR);
}

print '<?xml version="1.0" encoding="UTF-8"?>'."\n";
print '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
foreach ($urls as $url) {
	print "\t<url>\n";
	print "\t\t<loc>".htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8')."</loc>\n";
	if (isset($url['lastmod'])) {
		print "\t\t<lastmod>".htmlspecialchars($url['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8')."</lastmod>\n";
	}
	print "\t</url>\n";
}
print "</urlset>\n";
