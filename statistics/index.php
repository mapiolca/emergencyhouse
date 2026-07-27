<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	exit;
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/emergencyhouse/class/campaign.class.php');
dol_include_once('/emergencyhouse/class/statisticsservice.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'statistics', 'read')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
if ($action === 'rebuild' && emergencyhouseCanDo($user, 'statistics', 'read')) {
	$service = new EmergencyHouseStatisticsService($db);
	$result = $service->buildDaily((int) $conf->entity);
	if ($result >= 0) {
		setEventMessages($langs->trans('StatisticsRebuilt', $result), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	setEventMessages(emergencyhouseGetUserErrorMessage($service->error), null, 'errors');
}

$campaignId = GETPOSTINT('fk_campaign');
$dateStart = emergencyhouseStatisticsReadDate('date_start');
$dateEnd = emergencyhouseStatisticsReadDate('date_end');
$download = GETPOSTINT('download') === 1;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$allowedSorts = array('s.entity', 'c.ref', 's.metric_date', 's.metric_code', 's.dimension_code', 's.metric_value');
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 's.metric_date';
}
$sortorder = strtoupper(GETPOST('sortorder', 'aZ09')) === 'ASC' ? 'ASC' : 'DESC';
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
}
$page = max(0, GETPOSTINT('page'));
$offset = $page * $limit;
$searchEntitiesRaw = GETPOST('search_entity', 'array');
if (GETPOST('button_removefilter', 'alpha') !== '') {
	$campaignId = 0;
	$dateStart = null;
	$dateEnd = null;
	$searchEntitiesRaw = array();
	$page = 0;
	$offset = 0;
}
$entities = emergencyhouseEntityScope('campaign');
$searchEntities = emergencyhouseEntitySelection($searchEntitiesRaw, $entities);
$filteredEntities = empty($searchEntities) ? $entities : $searchEntities;
$entityOptions = emergencyhouseEntityOptionsForScope($db, $entities);
$where = ' WHERE s.entity IN ('.implode(',', $filteredEntities).')';
if ($campaignId > 0) {
	$where .= ' AND s.fk_campaign = '.$campaignId;
}
if (!empty($dateStart)) {
	$where .= " AND s.metric_date >= '".$db->escape(dol_print_date($dateStart, '%Y-%m-%d'))."'";
}
if (!empty($dateEnd)) {
	$where .= " AND s.metric_date <= '".$db->escape(dol_print_date($dateEnd, '%Y-%m-%d'))."'";
}
$from = ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_stat_daily AS s';
$from .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c ON c.rowid = s.fk_campaign AND c.entity = s.entity';
$select = 'SELECT s.entity, s.fk_campaign, s.metric_date, s.metric_code, s.dimension_code, s.metric_value, c.ref AS campaign_ref';
$orderBy = ' ORDER BY '.$sortfield.' '.$sortorder.', c.ref, s.metric_code';
if ($download) {
	if (!emergencyhouseCanDo($user, 'export', 'anonymous')) {
		accessforbidden();
	}
	$resql = $db->query($select.$from.$where.$orderBy);
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="emergencyhouse-statistics-'.dol_print_date(dol_now(), '%Y%m%d').'.csv"');
	print "\xEF\xBB\xBF";
	$csvHeaders = array(
		$langs->trans('Environment'),
		$langs->trans('Campaign'),
		$langs->trans('Date'),
		$langs->trans('Metric'),
		$langs->trans('Dimension'),
		$langs->trans('Value'),
	);
	print implode(';', array_map('dol_escape_csv', $csvHeaders))."\n";
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$values = array(
				$entityOptions[(int) $row->entity] ?? (string) $row->entity,
				(string) $row->campaign_ref,
				(string) $row->metric_date,
				emergencyhouseStatisticsMetricLabel($langs, (string) $row->metric_code),
				emergencyhouseStatisticsDimensionLabel($langs, (string) $row->dimension_code),
				(string) $row->metric_value,
			);
			print implode(';', array_map('dol_escape_csv', $values))."\n";
		}
	}
	exit;
}
$sqlCount = 'SELECT COUNT(*) AS total'.$from.$where;
$resqlCount = $db->query($sqlCount);
$countRow = $resqlCount ? $db->fetch_object($resqlCount) : false;
$totalnboflines = is_object($countRow) ? (int) $countRow->total : 0;
$resql = $db->query($select.$from.$where.$orderBy.$db->plimit($limit + 1, $offset));
if (!$resql) {
	dol_print_error($db);
}
$rows = array();
if ($resql) {
	while (is_object($row = $db->fetch_object($resql))) {
		$rows[] = $row;
	}
}
$hasNext = count($rows) > $limit;
if ($hasNext) {
	array_pop($rows);
}
$num = count($rows);
$campaignOptions = array('' => '');
$sqlCampaigns = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
$sqlCampaigns .= ' WHERE entity IN ('.implode(',', $entities).') ORDER BY ref';
$resqlCampaigns = $db->query($sqlCampaigns);
if ($resqlCampaigns) {
	while (is_object($campaign = $db->fetch_object($resqlCampaigns))) {
		$campaignOptions[(int) $campaign->rowid] = (string) $campaign->ref.' - '.(string) $campaign->label;
	}
}
$form = new Form($db);
$downloadParam = '?download=1&fk_campaign='.$campaignId;
if (!empty($dateStart)) {
	$downloadParam .= '&date_startyear='.date('Y', $dateStart).'&date_startmonth='.date('n', $dateStart).'&date_startday='.date('j', $dateStart);
}
if (!empty($dateEnd)) {
	$downloadParam .= '&date_endyear='.date('Y', $dateEnd).'&date_endmonth='.date('n', $dateEnd).'&date_endday='.date('j', $dateEnd);
}
foreach ($searchEntities as $searchEntity) {
	$downloadParam .= '&search_entity[]='.((int) $searchEntity);
}
$downloadParam .= '&sortfield='.urlencode($sortfield).'&sortorder='.urlencode($sortorder);
$param = '&fk_campaign='.$campaignId;
if (!empty($dateStart)) {
	$param .= '&date_startyear='.date('Y', $dateStart).'&date_startmonth='.date('n', $dateStart).'&date_startday='.date('j', $dateStart);
}
if (!empty($dateEnd)) {
	$param .= '&date_endyear='.date('Y', $dateEnd).'&date_endmonth='.date('n', $dateEnd).'&date_endday='.date('j', $dateEnd);
}
foreach ($searchEntities as $searchEntity) {
	$param .= '&search_entity[]='.((int) $searchEntity);
}

llxHeader('', $langs->trans('Statistics'));
$buttons = '<a class="butAction" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].$downloadParam).'">'.$langs->trans('ExportAnonymousCsv').'</a>';
print load_fiche_titre($langs->trans('Statistics'), $buttons, 'chart-area');
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'"><table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('Campaign').'</td><td>'.$form->selectarray('fk_campaign', $campaignOptions, $campaignId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('Environment').'</td><td>';
print Form::multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'minwidth300', 0, 0, '', '', $langs->trans('Environment'));
print '</td></tr>';
print '<tr><td>'.$langs->trans('DateStart').'</td><td>'.$form->selectDate($dateStart ?: -1, 'date_start', 0, 0, 1, '', 1, 0, 0, 0).'</td></tr>';
print '<tr><td>'.$langs->trans('DateEnd').'</td><td>'.$form->selectDate($dateEnd ?: -1, 'date_end', 0, 0, 1, '', 1, 0, 0, 0).'</td></tr>';
print '</table><div class="center"><button class="button" type="submit">'.$langs->trans('Filter').'</button> ';
print '<button class="button" type="submit" name="button_removefilter" value="x">'.$langs->trans('RemoveFilter').'</button></div>';
print ajax_combobox('fk_campaign');
print_barre_liste(
	$langs->trans('Statistics'),
	$page,
	$_SERVER['PHP_SELF'],
	$param,
	$sortfield,
	$sortorder,
	'',
	$num,
	$totalnboflines,
	'chart-area',
	0,
	'',
	'',
	$limit,
	$hasNext ? 0 : 1
);
print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('Environment'), $_SERVER['PHP_SELF'], 's.entity', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Campaign'), $_SERVER['PHP_SELF'], 'c.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Date'), $_SERVER['PHP_SELF'], 's.metric_date', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Metric'), $_SERVER['PHP_SELF'], 's.metric_code', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Dimension'), $_SERVER['PHP_SELF'], 's.dimension_code', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Value'), $_SERVER['PHP_SELF'], 's.metric_value', '', $param, '', $sortfield, $sortorder);
print '</tr>';
$campaignStatic = new EmergencyHouseCampaign($db);
foreach ($rows as $row) {
	print '<tr class="oddeven"><td class="center">'.emergencyhouseEntityBadge((int) $row->entity, $entityOptions).'</td><td>';
	print $campaignStatic->fetch((int) $row->fk_campaign) > 0 ? $campaignStatic->getNomUrl(1) : '<span class="opacitymedium">#'.((int) $row->fk_campaign).'</span>';
	print '</td><td>'.dol_escape_htmltag((string) $row->metric_date).'</td>';
	print '<td>'.dol_escape_htmltag(emergencyhouseStatisticsMetricLabel($langs, (string) $row->metric_code)).'</td>';
	print '<td>'.dol_escape_htmltag(emergencyhouseStatisticsDimensionLabel($langs, (string) $row->dimension_code)).'</td>';
	print '<td class="right">'.dol_escape_htmltag((string) $row->metric_value).'</td></tr>';
}
if ($num === 0) {
	print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div></form>';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="center">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="rebuild">';
print '<button class="button" type="submit">'.$langs->trans('RebuildCurrentEntityStatistics').'</button></form>';
llxFooter();
$db->close();

/**
 * Read an optional native date selector.
 *
 * @param string $prefix Prefix
 * @return int|null
 */
function emergencyhouseStatisticsReadDate($prefix)
{
	$year = GETPOSTINT($prefix.'year');
	if ($year <= 0) {
		return null;
	}
	return dol_mktime(0, 0, 0, GETPOSTINT($prefix.'month'), GETPOSTINT($prefix.'day'), $year);
}

/**
 * Translate a controlled statistics metric code.
 *
 * @param Translate $langs Translation service
 * @param string    $code  Metric code
 * @return string
 */
function emergencyhouseStatisticsMetricLabel($langs, $code)
{
	$labels = array(
		'active_offers' => 'ActiveOffers',
		'active_requests' => 'ActiveRequests',
		'active_solicitations' => 'ActiveSolicitations',
		'active_allocations' => 'ActiveAllocations',
		'open_reports' => 'OpenReports',
	);

	return isset($labels[$code]) ? $langs->trans($labels[$code]) : $langs->trans('UnknownMetric');
}

/**
 * Translate a controlled statistics dimension code.
 *
 * @param Translate $langs Translation service
 * @param string    $code  Dimension code
 * @return string
 */
function emergencyhouseStatisticsDimensionLabel($langs, $code)
{
	if ($code === '') {
		return $langs->trans('All');
	}

	return $langs->trans('UnknownDimension');
}
