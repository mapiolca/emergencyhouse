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

require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/emergencyhouse/class/publicanalyticsservice.class.php');
dol_include_once('/emergencyhouse/class/statisticsservice.class.php');
dol_include_once('/emergencyhouse/class/supervisionservice.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'statistics', 'read')) {
	accessforbidden();
}

$allowedTabs = array('overview', 'audience', 'contents', 'journeys', 'business');
$tab = GETPOST('tab', 'alpha');
if (!in_array($tab, $allowedTabs, true)) {
	$tab = 'overview';
}
$action = GETPOST('action', 'aZ09');
$period = GETPOSTINT('period');
if (!in_array($period, array(0, 7, 30, 90, 365), true)) {
	$period = 30;
}
$campaignId = GETPOSTINT('fk_campaign');
$pageCode = GETPOST('page_code', 'alpha');
$source = GETPOST('source', 'alpha');
$device = GETPOST('device', 'alpha');
$contentType = GETPOST('content_type', 'alpha');
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
}
$page = max(0, GETPOSTINT('page'));
$offset = $page * $limit;
$allowedSources = array('', 'direct', 'internal', 'search', 'social', 'external');
$allowedDevices = array('', 'desktop', 'mobile', 'tablet');
$allowedContentTypes = array('', 'campaign', 'offer', 'request');
if (!in_array($source, $allowedSources, true)) {
	$source = '';
}
if (!in_array($device, $allowedDevices, true)) {
	$device = '';
}
if (!in_array($contentType, $allowedContentTypes, true)) {
	$contentType = '';
}
if (!in_array($pageCode, EmergencyHousePublicAnalyticsService::allowedPageCodes(), true)) {
	$pageCode = '';
}

$entities = emergencyhouseEntityScope('campaign');
$showEnvironment = emergencyhouseEntityScopeIsShared($entities);
$searchEntitiesRaw = GETPOST('search_entity', 'array');
$searchEntities = $showEnvironment ? emergencyhouseEntitySelection($searchEntitiesRaw, $entities) : array();
$filteredEntities = !empty($searchEntities) ? $searchEntities : $entities;
$entityOptions = $showEnvironment ? emergencyhouseEntityOptionsForScope($db, $entities) : array();

$todayStart = dol_mktime(0, 0, 0, (int) dol_print_date(dol_now(), '%m'), (int) dol_print_date(dol_now(), '%d'), (int) dol_print_date(dol_now(), '%Y'));
$dateEndInput = emergencyhouseSupervisionReadDate('date_end');
$dateStartInput = emergencyhouseSupervisionReadDate('date_start');
if ($period > 0) {
	$dateEnd = dol_time_plus_duree($todayStart, 1, 'd');
	$dateStart = dol_time_plus_duree($dateEnd, -$period, 'd');
} else {
	$dateStart = $dateStartInput ?: dol_time_plus_duree($todayStart, -29, 'd');
	$dateEnd = $dateEndInput ? dol_time_plus_duree($dateEndInput, 1, 'd') : dol_time_plus_duree($todayStart, 1, 'd');
}
if ($dateStart >= $dateEnd) {
	$dateStart = dol_time_plus_duree($dateEnd, -30, 'd');
	setEventMessages($langs->trans('ErrorDateRange'), null, 'warnings');
}

if (GETPOST('button_removefilter', 'alpha') !== '') {
	$period = 30;
	$campaignId = 0;
	$pageCode = '';
	$source = '';
	$device = '';
	$contentType = '';
	$searchEntities = array();
	$filteredEntities = $entities;
	$page = 0;
	$offset = 0;
	$dateStartInput = null;
	$dateEndInput = null;
	$dateEnd = dol_time_plus_duree($todayStart, 1, 'd');
	$dateStart = dol_time_plus_duree($dateEnd, -30, 'd');
}

if ($action === 'rebuild') {
	$statistics = new EmergencyHouseStatisticsService($db);
	$result = $statistics->buildDaily((int) $conf->entity);
	if ($result >= 0) {
		setEventMessages($langs->trans('StatisticsRebuilt', $result), null, 'mesgs');
		header('Location: '.dol_buildpath('/emergencyhouse/supervision/index.php', 1).'?tab='.urlencode($tab));
		exit;
	}
	setEventMessages(emergencyhouseGetUserErrorMessage($statistics->error), null, 'errors');
}

$filters = array(
	'campaign' => $campaignId,
	'page' => $pageCode,
	'source' => $source,
	'device' => $device,
	'content_type' => $contentType,
);
$detailRetentionDays = max(7, getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_DETAIL_RETENTION_DAYS', 90));
$detailCutoff = dol_time_plus_duree($todayStart, -$detailRetentionDays, 'd');
$exactPeriod = $dateStart >= $detailCutoff;
$supervision = new EmergencyHouseSupervisionService($db);
$overview = $exactPeriod
	? $supervision->getExactOverview($filteredEntities, $dateStart, $dateEnd, $filters)
	: $supervision->getAggregateOverview($filteredEntities, $dateStart, $dateEnd);
if ($overview === false) {
	setEventMessages(emergencyhouseGetUserErrorMessage($supervision->error), null, 'errors');
	$overview = emergencyhouseSupervisionEmptyOverview();
}
$dailySeries = $exactPeriod
	? $supervision->getExactDailySeries($filteredEntities, $dateStart, $dateEnd, $filters)
	: $supervision->getDailySeries($filteredEntities, $dateStart, $dateEnd);
$dailySeries = is_array($dailySeries) ? $dailySeries : array();

$campaignOptions = array('' => '');
$sqlCampaigns = 'SELECT rowid, ref, label FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
$sqlCampaigns .= ' WHERE entity IN ('.implode(',', array_map('intval', $entities)).') ORDER BY ref';
$resqlCampaigns = $db->query($sqlCampaigns);
if ($resqlCampaigns) {
	while (is_object($campaign = $db->fetch_object($resqlCampaigns))) {
		$campaignOptions[(int) $campaign->rowid] = (string) $campaign->ref.' - '.(string) $campaign->label;
	}
}
$pageOptions = array('' => '');
foreach (EmergencyHousePublicAnalyticsService::allowedPageCodes() as $allowedPageCode) {
	$pageOptions[$allowedPageCode] = emergencyhouseSupervisionDimensionLabel($langs, 'page', $allowedPageCode);
}
$sourceOptions = array(
	'' => '',
	'direct' => $langs->trans('AnalyticsSourceDirect'),
	'internal' => $langs->trans('AnalyticsSourceInternal'),
	'search' => $langs->trans('AnalyticsSourceSearch'),
	'social' => $langs->trans('AnalyticsSourceSocial'),
	'external' => $langs->trans('AnalyticsSourceExternal'),
);
$deviceOptions = array(
	'' => '',
	'desktop' => $langs->trans('AnalyticsDeviceDesktop'),
	'mobile' => $langs->trans('AnalyticsDeviceMobile'),
	'tablet' => $langs->trans('AnalyticsDeviceTablet'),
);
$contentOptions = array(
	'' => '',
	'campaign' => $langs->trans('Campaign'),
	'offer' => $langs->trans('Offer'),
	'request' => $langs->trans('Request'),
);
$periodOptions = array(
	7 => $langs->trans('LastDays', 7),
	30 => $langs->trans('LastDays', 30),
	90 => $langs->trans('LastDays', 90),
	365 => $langs->trans('LastDays', 365),
	0 => $langs->trans('CustomPeriod'),
);
$form = new Form($db);
$param = emergencyhouseSupervisionBuildParam($tab, $period, $dateStartInput, $dateEndInput, $campaignId, $pageCode, $source, $device, $contentType, $searchEntities);

if (GETPOSTINT('download') === 1) {
	if (!emergencyhouseCanDo($user, 'export', 'anonymous')) {
		accessforbidden();
	}
	emergencyhouseSupervisionExport($db, $langs, $filteredEntities, $dateStart, $dateEnd, $campaignId, $contentType);
}

llxHeader('', $langs->trans('Supervision'));
$buttons = '';
if (emergencyhouseCanDo($user, 'export', 'anonymous')) {
	$buttons = '<a class="butAction" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?download=1'.$param).'">'.$langs->trans('ExportAnonymousCsv').'</a>';
}
print load_fiche_titre($langs->trans('Supervision'), $buttons, 'chart-area');
$head = emergencyhouseSupervisionPrepareHead();
print dol_get_fiche_head($head, $tab, $langs->trans('Supervision'), -1, 'chart-area');

if (!getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_ENABLED', 0)) {
	print '<div class="warning">'.$langs->trans('AnalyticsCollectionDisabled').'</div>';
}
if (!$exactPeriod) {
	print '<div class="info">'.$langs->trans('AnalyticsAggregateOnlyNotice', $detailRetentionDays).'</div>';
}
if (!$exactPeriod && ($campaignId > 0 || $pageCode !== '' || $source !== '' || $device !== '' || $contentType !== '')) {
	print '<div class="warning">'.$langs->trans('AnalyticsAggregateFilterLimitation').'</div>';
}

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="tab" value="'.dol_escape_htmltag($tab).'">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('Period').'</td><td>';
print $form->selectarray('period', $periodOptions, $period, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200');
print '</td></tr>';
print '<tr><td>'.$langs->trans('DateStart').'</td><td>'.$form->selectDate($dateStartInput ?: $dateStart, 'date_start', 0, 0, 1, '', 1, 0, 0, 0).'</td></tr>';
print '<tr><td>'.$langs->trans('DateEnd').'</td><td>'.$form->selectDate($dateEndInput ?: dol_time_plus_duree($dateEnd, -1, 'd'), 'date_end', 0, 0, 1, '', 1, 0, 0, 0).'</td></tr>';
print '<tr><td>'.$langs->trans('Campaign').'</td><td>'.$form->selectarray('fk_campaign', $campaignOptions, $campaignId, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
print '<tr><td>'.$langs->trans('PageOrContent').'</td><td>';
print $form->selectarray('page_code', $pageOptions, $pageCode, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').' ';
print $form->selectarray('content_type', $contentOptions, $contentType, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150');
print '</td></tr>';
print '<tr><td>'.$langs->trans('Source').'</td><td>'.$form->selectarray('source', $sourceOptions, $source, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td>'.$langs->trans('Device').'</td><td>'.$form->selectarray('device', $deviceOptions, $device, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
if ($showEnvironment) {
	print '<tr><td>'.$langs->trans('Environment').'</td><td>';
	print Form::multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'minwidth300', 0, 0, '', '', $langs->trans('Environment'));
	print '</td></tr>';
}
print '</table>';
print '<div class="center"><button class="button" type="submit">'.$langs->trans('Filter').'</button> ';
print '<button class="button" type="submit" name="button_removefilter" value="x">'.$langs->trans('RemoveFilter').'</button></div>';
print ajax_combobox('period');
print ajax_combobox('fk_campaign');
print ajax_combobox('page_code');
print ajax_combobox('content_type');
print ajax_combobox('source');
print ajax_combobox('device');
print '</form>';

if ($tab === 'overview') {
	emergencyhouseSupervisionPrintKpis($langs, $overview, $exactPeriod);
	emergencyhouseSupervisionPrintLineGraph($langs->trans('AudienceTrend'), $dailySeries, 'emergencyhouse_audience_trend');
} elseif ($tab === 'audience') {
	emergencyhouseSupervisionPrintKpis($langs, $overview, $exactPeriod);
	$sources = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'source', 'visits', 20);
	$devices = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'device', 'visits', 20);
	$visitorTypes = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'visitor_type', 'unique_visitors', 20);
	$authContexts = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'authentication', 'visits', 20);
	$referrerDomains = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'referrer_domain', 'visits', 50);
	print '<div class="fichecenter"><div class="fichehalfleft">';
	emergencyhouseSupervisionPrintPieGraph($langs, $langs->trans('TrafficSources'), is_array($sources) ? $sources : array(), 'source', 'emergencyhouse_sources');
	emergencyhouseSupervisionPrintPieGraph($langs, $langs->trans('NewAndReturningVisitors'), is_array($visitorTypes) ? $visitorTypes : array(), 'visitor_type', 'emergencyhouse_visitors');
	print '</div><div class="fichehalfright">';
	emergencyhouseSupervisionPrintPieGraph($langs, $langs->trans('Devices'), is_array($devices) ? $devices : array(), 'device', 'emergencyhouse_devices');
	emergencyhouseSupervisionPrintPieGraph($langs, $langs->trans('AuthenticationContext'), is_array($authContexts) ? $authContexts : array(), 'authentication', 'emergencyhouse_auth');
	print '</div></div>';
	emergencyhouseSupervisionPrintBreakdownTable($langs, $langs->trans('ExternalReferrerDomains'), is_array($referrerDomains) ? $referrerDomains : array(), 'referrer_domain');
} elseif ($tab === 'contents') {
	$landingPages = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'landing_page', 'visits', 50);
	$exitPages = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'exit_page', 'visits', 50);
	$topPages = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'page', 'page_views', 100);
	$topContents = $exactPeriod
		? $supervision->getExactTopContents($filteredEntities, $dateStart, $dateEnd, $filters, $limit + 1, $offset)
		: $supervision->getTopContents($filteredEntities, $dateStart, $dateEnd, $campaignId, $contentType, $limit + 1, $offset);
	$totalContents = $exactPeriod
		? $supervision->countExactTopContents($filteredEntities, $dateStart, $dateEnd, $filters)
		: $supervision->countTopContents($filteredEntities, $dateStart, $dateEnd, $campaignId, $contentType);
	$topContents = is_array($topContents) ? $topContents : array();
	$hasNext = count($topContents) > $limit;
	if ($hasNext) {
		array_pop($topContents);
	}
	print '<div class="fichecenter"><div class="fichehalfleft">';
	emergencyhouseSupervisionPrintBreakdownTable($langs, $langs->trans('LandingPages'), is_array($landingPages) ? $landingPages : array(), 'landing_page');
	print '</div><div class="fichehalfright">';
	emergencyhouseSupervisionPrintBreakdownTable($langs, $langs->trans('ExitPages'), is_array($exitPages) ? $exitPages : array(), 'exit_page');
	print '</div></div>';
	emergencyhouseSupervisionPrintBreakdownTable($langs, $langs->trans('MostViewedPages'), is_array($topPages) ? $topPages : array(), 'page');
	emergencyhouseSupervisionPrintPaginationFormStart($tab, $period, $dateStartInput, $dateEndInput, $campaignId, $pageCode, $source, $device, $contentType, $searchEntities);
	print_barre_liste(
		$langs->trans('MostViewedContents'),
		$page,
		$_SERVER['PHP_SELF'],
		$param,
		'',
		'',
		'',
		count($topContents),
		is_int($totalContents) ? $totalContents : count($topContents),
		'chart-area',
		0,
		'',
		'',
		$limit,
		$hasNext ? 0 : 1
	);
	emergencyhouseSupervisionPrintContentTable($langs, $topContents, $showEnvironment, $entityOptions);
	print '</form>';
} elseif ($tab === 'journeys') {
	$funnel = $exactPeriod
		? $supervision->getExactFunnel($filteredEntities, $dateStart, $dateEnd, $filters)
		: $supervision->getFunnel($filteredEntities, $dateStart, $dateEnd);
	emergencyhouseSupervisionPrintFunnel($langs, is_array($funnel) ? $funnel : array(), $exactPeriod);
	$conversions = emergencyhouseSupervisionGetBreakdown($supervision, $exactPeriod, $filteredEntities, $dateStart, $dateEnd, $filters, 'conversion', 'conversions', 100);
	emergencyhouseSupervisionPrintBreakdownTable($langs, $langs->trans('SuccessfulConversions'), is_array($conversions) ? $conversions : array(), 'conversion');
} else {
	emergencyhouseSupervisionPrintBusinessActivity(
		$db,
		$langs,
		$filteredEntities,
		$dateStart,
		$dateEnd,
		$campaignId,
		$showEnvironment,
		$entityOptions,
		$page,
		$limit,
		$param,
		$tab,
		$period,
		$dateStartInput,
		$dateEndInput,
		$pageCode,
		$source,
		$device,
		$contentType,
		$searchEntities
	);
}

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="center">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="rebuild">';
print '<input type="hidden" name="tab" value="'.dol_escape_htmltag($tab).'">';
print '<button class="button" type="submit">'.$langs->trans('RebuildCurrentEntityStatistics').'</button></form>';

print dol_get_fiche_end();
llxFooter();
$db->close();

/**
 * Read one native date selector.
 *
 * @param string $prefix Field prefix
 * @return int|null
 */
function emergencyhouseSupervisionReadDate($prefix)
{
	$year = GETPOSTINT($prefix.'year');
	if ($year <= 0) {
		return null;
	}
	return dol_mktime(0, 0, 0, GETPOSTINT($prefix.'month'), GETPOSTINT($prefix.'day'), $year);
}

/**
 * @return array<string, float>
 */
function emergencyhouseSupervisionEmptyOverview()
{
	return array(
		'page_views' => 0.0,
		'visits' => 0.0,
		'unique_visitors' => 0.0,
		'bounce_rate' => 0.0,
		'average_duration' => 0.0,
		'pages_per_visit' => 0.0,
		'conversion_rate' => 0.0,
	);
}

/**
 * Resolve a breakdown from exact details or anonymized daily aggregates.
 *
 * @param EmergencyHouseSupervisionService $service Supervision service
 * @param bool $exact Exact retained-detail period
 * @param array<int, int> $entities Entity scope
 * @param int $dateStart Inclusive timestamp
 * @param int $dateEnd Exclusive timestamp
 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
 * @param string $dimension Dimension
 * @param string $metric Metric
 * @param int $limit Limit
 * @return array<int, array{code:string,value:float}>|false
 */
function emergencyhouseSupervisionGetBreakdown($service, $exact, $entities, $dateStart, $dateEnd, $filters, $dimension, $metric, $limit)
{
	if ($exact) {
		return $service->getExactBreakdown($entities, $dateStart, $dateEnd, $filters, $dimension, $metric, $limit);
	}
	return $service->getBreakdown($entities, $dateStart, $dateEnd, $dimension, $metric, $limit);
}

/**
 * Print native KPI cells.
 *
 * @param Translate $langs Translation service
 * @param array<string, float> $overview Metrics
 * @param bool $exact Exact visitor calculation
 * @return void
 */
function emergencyhouseSupervisionPrintKpis($langs, $overview, $exact)
{
	$metrics = array(
		'page_views' => array('PageViews', price((float) $overview['page_views'], 0, $langs)),
		'visits' => array('Visits', price((float) $overview['visits'], 0, $langs)),
		'unique_visitors' => array($exact ? 'UniqueVisitors' : 'DailyUniqueVisitors', price((float) $overview['unique_visitors'], 0, $langs)),
		'bounce_rate' => array('BounceRate', price((float) $overview['bounce_rate'], 1, $langs).'%'),
		'average_duration' => array('AverageActiveDuration', emergencyhouseSupervisionDuration((int) round($overview['average_duration']))),
		'pages_per_visit' => array('PagesPerVisit', price((float) $overview['pages_per_visit'], 2, $langs)),
		'conversion_rate' => array('ConversionRate', price((float) $overview['conversion_rate'], 1, $langs).'%'),
	);
	print '<table class="noborder centpercent"><tr class="liste_titre">';
	foreach ($metrics as $metric) {
		print '<th class="center">'.dol_escape_htmltag($langs->trans($metric[0])).'</th>';
	}
	print '</tr><tr class="oddeven">';
	foreach ($metrics as $metric) {
		print '<td class="center"><strong class="wordbreak">'.dol_escape_htmltag((string) $metric[1]).'</strong></td>';
	}
	print '</tr></table>';
}

/**
 * @param Translate $langs Translation service
 * @param array<int, array{0:string,1:float,2:float}> $data Graph data
 * @param string $graphId DOM ID
 * @return void
 */
function emergencyhouseSupervisionPrintLineGraph($langs, $data, $graphId)
{
	$graph = new DolGraph();
	$graph->setWidth('100%');
	$graph->setHeight('280');
	$graph->setShowLegend(1);
	print '<table class="noborder nohover centpercent"><tr class="liste_titre"><th>'.$langs->trans('AudienceTrend').'</th></tr>';
	print '<tr class="oddeven"><td class="center">';
	if (!empty($data)) {
		$graph->SetData($data);
		$graph->SetLegend(array($langs->trans('PageViews'), $langs->trans('Visits')));
		$graph->SetType(array('lines', 'lines'));
		$graph->draw($graphId);
		print $graph->show();
	} else {
		print '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
	}
	print '</td></tr></table>';
}

/**
 * @param Translate $langs Translation service
 * @param string $title Title
 * @param array<int, array{code:string,value:float}> $rows Data
 * @param string $dimension Dimension
 * @param string $graphId DOM ID
 * @return void
 */
function emergencyhouseSupervisionPrintPieGraph($langs, $title, $rows, $dimension, $graphId)
{
	$data = array();
	foreach ($rows as $row) {
		$data[] = array(emergencyhouseSupervisionDimensionLabel($langs, $dimension, $row['code']), $row['value']);
	}
	$graph = new DolGraph();
	$graph->setWidth('100%');
	$graph->setHeight('260');
	$graph->setShowLegend(1);
	print '<table class="noborder nohover centpercent"><tr class="liste_titre"><th>'.dol_escape_htmltag($title).'</th></tr>';
	print '<tr class="oddeven"><td class="center">';
	if (!empty($data)) {
		$graph->SetData($data);
		$graph->SetType(array('pie'));
		$graph->draw($graphId);
		print $graph->show();
	} else {
		print '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
	}
	print '</td></tr></table>';
}

/**
 * @param Translate $langs Translation service
 * @param string $title Title
 * @param array<int, array{code:string,value:float}> $rows Rows
 * @param string $dimension Dimension
 * @return void
 */
function emergencyhouseSupervisionPrintBreakdownTable($langs, $title, $rows, $dimension)
{
	print '<table class="noborder centpercent"><tr class="liste_titre">';
	print '<th>'.dol_escape_htmltag($title).'</th><th class="right">'.$langs->trans('Value').'</th></tr>';
	foreach ($rows as $row) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag(emergencyhouseSupervisionDimensionLabel($langs, $dimension, $row['code'])).'</td>';
		print '<td class="right">'.dol_escape_htmltag(price($row['value'], 0, $langs)).'</td></tr>';
	}
	if (empty($rows)) {
		print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
}

/**
 * @param Translate $langs Translation service
 * @param array<int, array{entity:int,campaign_ref:string,content_type:string,content_id:int,content_label:string,value:float}> $rows Rows
 * @param bool $showEnvironment Environment column
 * @param array<int, string> $entityOptions Entity labels
 * @return void
 */
function emergencyhouseSupervisionPrintContentTable($langs, $rows, $showEnvironment, $entityOptions)
{
	print '<table class="noborder centpercent"><tr class="liste_titre">';
	if ($showEnvironment) {
		print '<th class="center">'.$langs->trans('Environment').'</th>';
	}
	print '<th>'.$langs->trans('Type').'</th><th>'.$langs->trans('Campaign').'</th>';
	print '<th>'.$langs->trans('Content').'</th><th class="right">'.$langs->trans('PageViews').'</th></tr>';
	foreach ($rows as $row) {
		print '<tr class="oddeven">';
		if ($showEnvironment) {
			print '<td class="center">'.emergencyhouseEntityBadge($row['entity'], $entityOptions).'</td>';
		}
		print '<td>'.dol_escape_htmltag($langs->trans(ucfirst($row['content_type']))).'</td>';
		print '<td>'.dol_escape_htmltag($row['campaign_ref']).'</td>';
		print '<td>'.dol_escape_htmltag($row['content_label']).'</td>';
		print '<td class="right">'.dol_escape_htmltag(price($row['value'], 0, $langs)).'</td></tr>';
	}
	if (empty($rows)) {
		print '<tr class="oddeven"><td colspan="'.($showEnvironment ? 5 : 4).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
}

/**
 * @param Translate $langs Translation service
 * @param array<string, float> $funnel Funnel values
 * @param bool $exact Exact ordered paths
 * @return void
 */
function emergencyhouseSupervisionPrintFunnel($langs, $funnel, $exact)
{
	$stages = array(
		'home' => 'FunnelHome',
		'consultation' => 'FunnelConsultation',
		'form' => 'FunnelForm',
		'submission' => 'FunnelSubmission',
		'conversion' => 'FunnelConversion',
	);
	$previous = 0.0;
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Stage').'</th>';
	print '<th class="right">'.$langs->trans('Volume').'</th><th class="right">'.$langs->trans('ProgressionRate').'</th></tr>';
	foreach ($stages as $code => $label) {
		$value = isset($funnel[$code]) ? (float) $funnel[$code] : 0.0;
		$rate = $previous > 0 ? ($value * 100 / $previous) : 0.0;
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($langs->trans($label)).'</td>';
		print '<td class="right">'.dol_escape_htmltag(price($value, 0, $langs)).'</td>';
		print '<td class="right">'.dol_escape_htmltag($exact && $previous > 0 ? price($rate, 1, $langs).'%' : '—').'</td></tr>';
		$previous = $value;
	}
	print '</table>';
}

/**
 * Print existing daily business statistics.
 *
 * @param DoliDB $db Database handler
 * @param Translate $langs Translation service
 * @param array<int, int> $entities Entity scope
 * @param int $dateStart Inclusive timestamp
 * @param int $dateEnd Exclusive timestamp
 * @param int $campaignId Campaign filter
 * @param bool $showEnvironment Environment column
 * @param array<int, string> $entityOptions Entity labels
 * @param int $page Current page
 * @param int $limit Page size
 * @param string $param Preserved parameters
 * @param string $tab Active tab
 * @param int $period Quick period
 * @param int|null $dateStartInput Custom start
 * @param int|null $dateEndInput Custom end
 * @param string $pageCode Page filter
 * @param string $source Source filter
 * @param string $device Device filter
 * @param string $contentType Content filter
 * @param array<int, int> $searchEntities Selected entities
 * @return void
 */
function emergencyhouseSupervisionPrintBusinessActivity(
	$db,
	$langs,
	$entities,
	$dateStart,
	$dateEnd,
	$campaignId,
	$showEnvironment,
	$entityOptions,
	$page,
	$limit,
	$param,
	$tab,
	$period,
	$dateStartInput,
	$dateEndInput,
	$pageCode,
	$source,
	$device,
	$contentType,
	$searchEntities
) {
	$offset = $page * $limit;
	$from = ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_stat_daily AS s';
	$from .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c ON c.rowid = s.fk_campaign AND c.entity = s.entity';
	$where = ' WHERE s.entity IN ('.implode(',', array_map('intval', $entities)).')';
	$where .= " AND s.metric_date >= '".$db->escape(dol_print_date($dateStart, '%Y-%m-%d'))."'";
	$where .= " AND s.metric_date < '".$db->escape(dol_print_date($dateEnd, '%Y-%m-%d'))."'";
	if ($campaignId > 0) {
		$where .= ' AND s.fk_campaign = '.((int) $campaignId);
	}
	$resqlCount = $db->query('SELECT COUNT(*) AS total'.$from.$where);
	$countRow = $resqlCount ? $db->fetch_object($resqlCount) : false;
	$total = is_object($countRow) ? (int) $countRow->total : 0;
	$sql = 'SELECT s.entity, s.metric_date, s.metric_code, s.dimension_code, s.metric_value, c.ref AS campaign_ref';
	$sql .= $from.$where;
	$sql .= ' ORDER BY s.metric_date DESC, c.ref, s.metric_code'.$db->plimit($limit + 1, $offset);
	$resql = $db->query($sql);
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
	emergencyhouseSupervisionPrintPaginationFormStart(
		$tab,
		$period,
		$dateStartInput,
		$dateEndInput,
		$campaignId,
		$pageCode,
		$source,
		$device,
		$contentType,
		$searchEntities
	);
	print_barre_liste(
		$langs->trans('BusinessActivity'),
		$page,
		$_SERVER['PHP_SELF'],
		$param,
		'',
		'',
		'',
		count($rows),
		$total,
		'chart-area',
		0,
		'',
		'',
		$limit,
		$hasNext ? 0 : 1
	);
	print '<table class="noborder centpercent"><tr class="liste_titre">';
	if ($showEnvironment) {
		print '<th class="center">'.$langs->trans('Environment').'</th>';
	}
	print '<th>'.$langs->trans('Campaign').'</th><th>'.$langs->trans('Date').'</th>';
	print '<th>'.$langs->trans('Metric').'</th><th>'.$langs->trans('Dimension').'</th><th class="right">'.$langs->trans('Value').'</th></tr>';
	$count = 0;
	foreach ($rows as $row) {
		$count++;
		print '<tr class="oddeven">';
		if ($showEnvironment) {
			print '<td class="center">'.emergencyhouseEntityBadge((int) $row->entity, $entityOptions).'</td>';
		}
		print '<td>'.dol_escape_htmltag((string) $row->campaign_ref).'</td>';
		print '<td>'.dol_escape_htmltag((string) $row->metric_date).'</td>';
		print '<td>'.dol_escape_htmltag(emergencyhouseSupervisionBusinessMetricLabel($langs, (string) $row->metric_code)).'</td>';
		$dimensionCode = (string) $row->dimension_code;
		print '<td>'.dol_escape_htmltag($dimensionCode !== '' ? $dimensionCode : $langs->trans('All')).'</td>';
		print '<td class="right">'.dol_escape_htmltag(price((float) $row->metric_value, 0, $langs)).'</td></tr>';
	}
	if ($count === 0) {
		print '<tr class="oddeven"><td colspan="'.($showEnvironment ? 6 : 5).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table></form>';
}

/**
 * Open a native pagination form and preserve dashboard filters.
 *
 * @param string $tab Active tab
 * @param int $period Quick period
 * @param int|null $dateStart Custom start
 * @param int|null $dateEnd Custom end
 * @param int $campaignId Campaign
 * @param string $pageCode Page
 * @param string $source Source
 * @param string $device Device
 * @param string $contentType Content
 * @param array<int, int> $entities Entities
 * @return void
 */
function emergencyhouseSupervisionPrintPaginationFormStart($tab, $period, $dateStart, $dateEnd, $campaignId, $pageCode, $source, $device, $contentType, $entities)
{
	print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="tab" value="'.dol_escape_htmltag($tab).'">';
	print '<input type="hidden" name="period" value="'.((int) $period).'">';
	print '<input type="hidden" name="fk_campaign" value="'.((int) $campaignId).'">';
	print '<input type="hidden" name="page_code" value="'.dol_escape_htmltag($pageCode).'">';
	print '<input type="hidden" name="source" value="'.dol_escape_htmltag($source).'">';
	print '<input type="hidden" name="device" value="'.dol_escape_htmltag($device).'">';
	print '<input type="hidden" name="content_type" value="'.dol_escape_htmltag($contentType).'">';
	if ($period === 0 && $dateStart !== null) {
		print '<input type="hidden" name="date_startyear" value="'.date('Y', $dateStart).'">';
		print '<input type="hidden" name="date_startmonth" value="'.date('n', $dateStart).'">';
		print '<input type="hidden" name="date_startday" value="'.date('j', $dateStart).'">';
	}
	if ($period === 0 && $dateEnd !== null) {
		print '<input type="hidden" name="date_endyear" value="'.date('Y', $dateEnd).'">';
		print '<input type="hidden" name="date_endmonth" value="'.date('n', $dateEnd).'">';
		print '<input type="hidden" name="date_endday" value="'.date('j', $dateEnd).'">';
	}
	foreach ($entities as $entity) {
		print '<input type="hidden" name="search_entity[]" value="'.((int) $entity).'">';
	}
}

/**
 * @param Translate $langs Translation service
 * @param string $code Metric code
 * @return string
 */
function emergencyhouseSupervisionBusinessMetricLabel($langs, $code)
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
 * @param Translate $langs Translation service
 * @param string $dimension Dimension family
 * @param string $code Controlled code
 * @return string
 */
function emergencyhouseSupervisionDimensionLabel($langs, $dimension, $code)
{
	$known = array(
		'source' => array(
			'direct' => 'AnalyticsSourceDirect', 'internal' => 'AnalyticsSourceInternal',
			'search' => 'AnalyticsSourceSearch', 'social' => 'AnalyticsSourceSocial',
			'external' => 'AnalyticsSourceExternal',
		),
		'device' => array(
			'desktop' => 'AnalyticsDeviceDesktop', 'mobile' => 'AnalyticsDeviceMobile', 'tablet' => 'AnalyticsDeviceTablet',
		),
		'authentication' => array('anonymous' => 'Anonymous', 'authenticated' => 'Authenticated'),
		'visitor_type' => array('new' => 'NewVisitor', 'returning' => 'ReturningVisitor'),
		'conversion' => array(
			'registration_completed' => 'ConversionRegistration',
			'email_verified' => 'ConversionEmailVerification',
			'login_success' => 'ConversionLogin',
			'contact_sent' => 'ConversionContact',
			'offer_submitted' => 'ConversionOfferSubmission',
			'request_submitted' => 'ConversionRequestSubmission',
			'solicitation_created' => 'ConversionSolicitation',
			'report_created' => 'ConversionReport',
		),
	);
	if (isset($known[$dimension][$code])) {
		return $langs->trans($known[$dimension][$code]);
	}
	if (in_array($dimension, array('page', 'landing_page', 'exit_page'), true)) {
		$key = 'AnalyticsPage'.str_replace(' ', '', ucwords(str_replace('_', ' ', $code)));
		$translated = $langs->trans($key);
		return $translated !== $key ? $translated : ucwords(str_replace('_', ' ', $code));
	}
	return $code !== '' ? $code : $langs->trans('Unknown');
}

/**
 * @param int $seconds Duration
 * @return string
 */
function emergencyhouseSupervisionDuration($seconds)
{
	$seconds = max(0, $seconds);
	return sprintf('%02d:%02d:%02d', (int) floor($seconds / 3600), (int) floor(($seconds % 3600) / 60), $seconds % 60);
}

/**
 * Build filter parameters.
 *
 * @param string $tab Active tab
 * @param int $period Quick period
 * @param int|null $dateStart Start
 * @param int|null $dateEnd End
 * @param int $campaignId Campaign
 * @param string $page Page
 * @param string $source Source
 * @param string $device Device
 * @param string $contentType Content
 * @param array<int, int> $entities Entities
 * @return string
 */
function emergencyhouseSupervisionBuildParam($tab, $period, $dateStart, $dateEnd, $campaignId, $page, $source, $device, $contentType, $entities)
{
	$param = '&tab='.urlencode($tab).'&period='.((int) $period).'&fk_campaign='.((int) $campaignId);
	$param .= '&page_code='.urlencode($page).'&source='.urlencode($source).'&device='.urlencode($device);
	$param .= '&content_type='.urlencode($contentType);
	if ($period === 0 && $dateStart !== null) {
		$param .= '&date_startyear='.date('Y', $dateStart).'&date_startmonth='.date('n', $dateStart).'&date_startday='.date('j', $dateStart);
	}
	if ($period === 0 && $dateEnd !== null) {
		$param .= '&date_endyear='.date('Y', $dateEnd).'&date_endmonth='.date('n', $dateEnd).'&date_endday='.date('j', $dateEnd);
	}
	foreach ($entities as $entity) {
		$param .= '&search_entity[]='.((int) $entity);
	}
	return $param;
}

/**
 * Export anonymous daily aggregates.
 *
 * @param DoliDB $db Database handler
 * @param Translate $langs Translation service
 * @param array<int, int> $entities Entity scope
 * @param int $dateStart Inclusive timestamp
 * @param int $dateEnd Exclusive timestamp
 * @param int $campaignId Campaign filter
 * @param string $contentType Content filter
 * @return never
 */
function emergencyhouseSupervisionExport($db, $langs, $entities, $dateStart, $dateEnd, $campaignId, $contentType)
{
	$sql = 'SELECT entity, metric_date, metric_code, dimension_type, dimension_code, fk_campaign, content_type, fk_content, metric_value';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_daily';
	$sql .= ' WHERE entity IN ('.implode(',', array_map('intval', $entities)).')';
	$sql .= " AND metric_date >= '".$db->escape(dol_print_date($dateStart, '%Y-%m-%d'))."'";
	$sql .= " AND metric_date < '".$db->escape(dol_print_date($dateEnd, '%Y-%m-%d'))."'";
	if ($campaignId > 0) {
		$sql .= ' AND fk_campaign = '.((int) $campaignId);
	}
	if ($contentType !== '') {
		$sql .= " AND content_type = '".$db->escape($contentType)."'";
	}
	$sql .= ' ORDER BY metric_date, entity, metric_code, dimension_type, dimension_code';
	$resql = $db->query($sql);
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="emergencyhouse-supervision-'.dol_print_date(dol_now(), '%Y%m%d').'.csv"');
	print "\xEF\xBB\xBF";
	print implode(';', array_map('dol_escape_csv', array(
		$langs->trans('Environment'), $langs->trans('Date'), $langs->trans('Metric'),
		$langs->trans('Dimension'), $langs->trans('Code'), $langs->trans('Campaign'),
		$langs->trans('Type'), $langs->trans('Content'), $langs->trans('Value'),
	)))."\n";
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$values = array(
				(string) $row->entity, (string) $row->metric_date, (string) $row->metric_code,
				(string) $row->dimension_type, (string) $row->dimension_code, (string) $row->fk_campaign,
				(string) $row->content_type, (string) $row->fk_content, (string) $row->metric_value,
			);
			print implode(';', array_map('dol_escape_csv', $values))."\n";
		}
	}
	exit;
}
