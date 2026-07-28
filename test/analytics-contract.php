<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Static contracts for privacy-preserving public audience measurement.
 */

$root = dirname(__DIR__);
$failures = 0;

/**
 * @param bool $condition Contract result
 * @param string $label Label
 * @return void
 */
function emergencyhouseAnalyticsContract($condition, $label)
{
	global $failures;
	if ($condition) {
		print '[OK] '.$label.PHP_EOL;
		return;
	}
	$failures++;
	print '[FAIL] '.$label.PHP_EOL;
}

$schema = (string) file_get_contents($root.'/sql/'.'llx'.'_emergencyhouse.sql');
$descriptor = (string) file_get_contents($root.'/core/modules/modEmergencyHouse.class.php');
$service = (string) file_get_contents($root.'/class/publicanalyticsservice.class.php');
$statistics = (string) file_get_contents($root.'/class/statisticsservice.class.php');
$retention = (string) file_get_contents($root.'/class/retentionservice.class.php');
$publicInit = (string) file_get_contents($root.'/public/_init.php');
$publicHeader = (string) file_get_contents($root.'/lib/emergencyhouse_public.lib.php');
$endpoint = (string) file_get_contents($root.'/public/analytics.php');
$preference = (string) file_get_contents($root.'/public/audience.php');
$javascript = (string) file_get_contents($root.'/js/public.js.php');
$supervision = (string) file_get_contents($root.'/supervision/index.php');
$supervisionService = (string) file_get_contents($root.'/class/supervisionservice.class.php');
$legacyStatistics = (string) file_get_contents($root.'/statistics/index.php');

foreach (array('analytics_visit', 'analytics_event', 'analytics_daily') as $table) {
	emergencyhouseAnalyticsContract(
		strpos($schema, 'CREATE TABLE IF NOT EXISTS '.'llx'.'_emergencyhouse_'.$table) !== false,
		'Table multientité déclarée : '.$table
	);
}
emergencyhouseAnalyticsContract(
	!preg_match('/analytics_(?:visit|event|daily)[\\s\\S]{0,1800}\\b(?:ip|ip_address|user_agent|url|query_string|fk_account|fk_user)\\b/i', $schema),
	'Aucune IP, URL, identité ou User-Agent dans les tables d’audience'
);
emergencyhouseAnalyticsContract(
	strpos($descriptor, "'EMERGENCYHOUSE_ANALYTICS_ENABLED' => array('0', 'yesno')") !== false,
	'Collecte désactivée par défaut'
);
foreach (array(
	'EMERGENCYHOUSE_ANALYTICS_SESSION_MINUTES' => '30',
	'EMERGENCYHOUSE_ANALYTICS_ENGAGEMENT_SECONDS' => '10',
	'EMERGENCYHOUSE_ANALYTICS_DETAIL_RETENTION_DAYS' => '90',
	'EMERGENCYHOUSE_ANALYTICS_AGGREGATE_RETENTION_MONTHS' => '25',
) as $constant => $default) {
	emergencyhouseAnalyticsContract(
		strpos($descriptor, "'".$constant."' => array('".$default."', 'chaine')") !== false,
		'Valeur par défaut conservatrice : '.$constant
	);
}
emergencyhouseAnalyticsContract(
	strpos($service, "'httponly' => true") !== false
	&& strpos($service, "'samesite' => 'Lax'") !== false
	&& strpos($service, "'secure' => \$this->isSecureTransport()") !== false
	&& strpos($service, "strtotime('+13 months'") !== false,
	'Cookie Secure HttpOnly SameSite=Lax à échéance fixe de treize mois'
);
emergencyhouseAnalyticsContract(
	strpos($service, "hashLookup(\$payload, 'analytics-cookie-'.\$this->entity)") !== false
	&& strpos($service, "hashLookup(\$cookie, 'analytics-visitor-'.\$this->entity)") !== false,
	'Signature et empreinte cloisonnées par entité'
);
emergencyhouseAnalyticsContract(
	strpos($service, 'GET_LOCK') !== false
	&& strpos($service, 'FOR UPDATE') !== false
	&& strpos($service, 'GREATEST(active_seconds') !== false
	&& strpos($service, 'analytics-engagement-event') !== false
	&& strpos($schema, 'active_seconds integer DEFAULT 0 NOT NULL') !== false,
	'Concurrence et engagement idempotent protégés'
);
emergencyhouseAnalyticsContract(
	strpos($service, 'allowedPageCodes') !== false
	&& strpos($service, 'allowedEventCodes') !== false
	&& strpos($service, 'normalizePublicContent') !== false,
	'Codes contrôlés et validation des contenus publics'
);
emergencyhouseAnalyticsContract(
	strpos($publicInit, 'EmergencyHousePublicAnalyticsService') !== false
	&& strpos($publicHeader, 'recordPageView') !== false
	&& strpos($publicHeader, 'if (!$preview') !== false,
	'Collecte explicite sur les pages HTML et exclusion de l’aperçu privé'
);
emergencyhouseAnalyticsContract(
	strpos($endpoint, 'isSameOriginPost') !== false
	&& strpos($endpoint, 'GETPOSTINT') !== false
	&& strpos($endpoint, 'public_analytics_engagement') !== false
	&& strpos($endpoint, 'hasValidVisitorCookie') !== false
	&& strpos($endpoint, 'strlen($engagementToken) > 160') !== false
	&& strpos($endpoint, 'EMERGENCYHOUSE_PUBLIC_SKIP_ACCOUNT_AUTH') !== false,
	'Signal d’engagement POST de même origine, borné et limité en débit'
);
emergencyhouseAnalyticsContract(
	strpos($javascript, 'document.visibilityState') !== false
	&& strpos($javascript, 'navigator.sendBeacon') !== false,
	'Engagement fondé sur l’activité visible'
);
emergencyhouseAnalyticsContract(
	strpos($preference, 'setOptOut(!$measurementAllowed)') !== false
	&& strpos($service, 'deleteCookie(self::VISITOR_COOKIE)') !== false,
	'Opposition immédiate et suppression du cookie d’audience'
);
emergencyhouseAnalyticsContract(
	strpos($statistics, 'buildAnalyticsDaily') !== false
	&& strpos($retention, 'emergencyhouse_analytics_event') !== false
	&& strpos($retention, 'emergencyhouse_analytics_daily') !== false,
	'Agrégation et purge étendent les traitements existants'
);
emergencyhouseAnalyticsContract(
	strpos($descriptor, "'Supervision', '/emergencyhouse/supervision/index.php', 'statistics', 'read'") !== false
	&& strpos($supervision, 'new DolGraph') !== false
	&& strpos($legacyStatistics, '../supervision/index.php?tab=business') !== false,
	'Menu, droit stable, graphiques natifs et redirection historique'
);
emergencyhouseAnalyticsContract(
	strpos($supervisionService, 'getExactBreakdown') !== false
	&& strpos($supervisionService, 'getExactTopContents') !== false
	&& strpos($supervisionService, 'getExactFunnel') !== false
	&& strpos($supervisionService, 'c.rowid > h.rowid') !== false
	&& strpos($supervisionService, 'f.rowid > c.rowid') !== false
	&& strpos($supervision, 'emergencyhouseSupervisionGetBreakdown') !== false,
	'Filtres croisés et parcours ordonné appliqués aux détails conservés'
);
foreach (array(
	'registration_completed' => 'public/auth/register.php',
	'email_verified' => 'public/auth/verify.php',
	'login_success' => 'public/auth/login.php',
	'contact_sent' => 'public/contact.php',
	'offer_submitted' => 'public/offer/edit.php',
	'request_submitted' => 'public/request/edit.php',
	'solicitation_created' => 'public/solicitation/create.php',
	'message_sent' => 'public/solicitation/view.php',
	'report_created' => 'public/report/create.php',
	'allocation_updated' => 'public/allocation/view.php',
) as $event => $file) {
	$source = (string) file_get_contents($root.'/'.$file);
	emergencyhouseAnalyticsContract(
		strpos($source, "emergencyhousePublicAnalyticsEvent('".$event."'") !== false
			|| strpos($source, "? '".$event."'") !== false,
		'Conversion ou action raccordée après succès : '.$event
	);
}

if ($failures > 0) {
	fwrite(STDERR, $failures." contrat(s) d’audience en échec.\n");
	exit(1);
}

print "Tous les contrats d’audience sont satisfaits.\n";
