<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) {
	$res = include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	exit;
}

dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'campaign', 'read')) {
	accessforbidden();
}

$definitions = array(
	'campaign' => array('table' => 'emergencyhouse_campaign', 'condition' => 'status IN (0, 1, 2)', 'label' => 'Campaigns', 'url' => '/emergencyhouse/campaign/list.php', 'right' => array('campaign', 'read'), 'picto' => 'emergencyhouse@emergencyhouse'),
	'offer' => array('table' => 'emergencyhouse_offer', 'condition' => 'status = 2', 'label' => 'ActiveOffers', 'url' => '/emergencyhouse/offer/list.php', 'right' => array('listing', 'read'), 'picto' => 'home'),
	'request' => array('table' => 'emergencyhouse_request', 'condition' => 'status IN (1, 2)', 'label' => 'ActiveRequests', 'url' => '/emergencyhouse/request/list.php', 'right' => array('listing', 'read'), 'picto' => 'people-arrows'),
	'solicitation' => array('table' => 'emergencyhouse_solicitation', 'condition' => 'status IN (0, 1)', 'label' => 'ActiveSolicitations', 'url' => '/emergencyhouse/solicitation/list.php', 'right' => array('solicitation', 'write'), 'picto' => 'comment'),
	'allocation' => array('table' => 'emergencyhouse_allocation', 'condition' => 'status IN (0, 1, 2, 5)', 'label' => 'ActiveAllocations', 'url' => '/emergencyhouse/allocation/list.php', 'right' => array('allocation', 'write'), 'picto' => 'calendar-check'),
	'report' => array('table' => 'emergencyhouse_report', 'condition' => 'status IN (0, 1)', 'label' => 'OpenReports', 'url' => '/emergencyhouse/report/list.php', 'right' => array('report', 'write'), 'picto' => 'warning'),
);
$counts = array();
foreach ($definitions as $code => $definition) {
	if (!emergencyhouseCanDo($user, $definition['right'][0], $definition['right'][1])) {
		continue;
	}
	$scope = implode(',', emergencyhouseEntityScope($code));
	$sql = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$definition['table'];
	$sql .= ' WHERE entity IN ('.$scope.') AND '.$definition['condition'];
	$resql = $db->query($sql);
	$row = $resql ? $db->fetch_object($resql) : false;
	$counts[$code] = is_object($row) ? (int) $row->total : 0;
}

$reportScope = implode(',', emergencyhouseEntityScope('report'));
$sqlRecent = 'SELECT r.rowid, r.ref, r.severity, r.date_creation';
$sqlRecent .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_report AS r';
$sqlRecent .= ' WHERE r.entity IN ('.$reportScope.') AND r.status IN (0, 1)';
$sqlRecent .= ' ORDER BY r.severity DESC, r.date_creation DESC';
$sqlRecent .= $db->plimit(10);
$recentReports = emergencyhouseCanDo($user, 'report', 'write') ? $db->query($sqlRecent) : false;

llxHeader('', $langs->trans('EmergencyHouseDashboard'));
print load_fiche_titre($langs->trans('EmergencyHouseDashboard'), '', 'emergencyhouse@emergencyhouse');
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<div class="box flexcontainer flexwrap">';
foreach ($counts as $code => $count) {
	$definition = $definitions[$code];
	print '<div class="boxstatsborder boxstats">';
	print '<a href="'.dol_buildpath($definition['url'], 1).'">';
	print '<span class="boxstatsindicator thumbstatistic">'.img_picto('', $definition['picto']).'</span>';
	print '<span class="boxstatstext">'.$langs->trans($definition['label']).'</span>';
	print '<span class="boxstatsvalue">'.((int) $count).'</span></a></div>';
}
print '</div>';
print '</div><div class="fichehalfright">';
print load_fiche_titre($langs->trans('OperationalReadiness'));
print '<table class="noborder centpercent">';
$encryptionEnvironmentValue = getenv(getDolGlobalString('EMERGENCYHOUSE_ENCRYPTION_KEY_ENV', 'EMERGENCYHOUSE_ENCRYPTION_KEY'));
$readiness = array(
	'PublicPortal' => getDolGlobalInt('EMERGENCYHOUSE_PUBLIC_PORTAL_ENABLED') === 1,
	'Agenda' => isModEnabled('agenda'),
	'Notifications' => isModEnabled('notification'),
	'ScheduledJobs' => isModEnabled('cron'),
	'Encryption' => is_string($encryptionEnvironmentValue) && $encryptionEnvironmentValue !== '',
);
foreach ($readiness as $label => $ready) {
	print '<tr class="oddeven"><td>'.$langs->trans($label).'</td><td class="right">';
	print dolGetStatus($langs->trans($ready ? 'StatusAvailable' : 'StatusUnavailable'), '', '', $ready ? 4 : 6, 1);
	print '</td></tr>';
}
print '</table></div></div>';
if ($recentReports) {
	print load_fiche_titre($langs->trans('RecentReports'));
	print '<div class="div-table-responsive"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Severity').'</th><th>'.$langs->trans('DateCreation').'</th></tr>';
	$hasRows = false;
	while (is_object($report = $db->fetch_object($recentReports))) {
		$hasRows = true;
		print '<tr class="oddeven"><td><a href="'.dol_buildpath('/emergencyhouse/report/card.php', 1).'?id='.((int) $report->rowid).'">'.dol_escape_htmltag((string) $report->ref).'</a></td>';
		print '<td>'.$langs->trans('SeverityLevel'.((int) $report->severity)).'</td><td>'.dol_print_date($db->jdate($report->date_creation), 'dayhour').'</td></tr>';
	}
	if (!$hasRows) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table></div>';
}
llxFooter();
$db->close();
