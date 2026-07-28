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
dol_include_once('/emergencyhouse/class/matchingservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'match', 'read')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
if ($action === 'recalculate' && emergencyhouseCanDo($user, 'match', 'write')) {
	$sourceType = GETPOST('source_type', 'aZ09');
	$sourceId = GETPOSTINT('source_id');
	$matching = new EmergencyHouseMatchingService($db);
	$result = $sourceType === 'offer'
		? $matching->recalculateForOffer($sourceId)
		: ($sourceType === 'request' ? $matching->recalculateForRequest($sourceId) : -1);
	if ($result >= 0) {
		setEventMessages($langs->trans('MatchesRecalculated', $result), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	setEventMessages(
		emergencyhouseGetUserErrorMessage($matching->error, 'ErrorInvalidObjectType'),
		null,
		'errors'
	);
}

$entities = array_values(array_intersect(emergencyhouseEntityScope('offer'), emergencyhouseEntityScope('request')));
if (empty($entities)) {
	$entities = array((int) $conf->entity);
}
$showEnvironment = emergencyhouseEntityScopeIsShared($entities);

$sortfield = GETPOST('sortfield', 'aZ09comma');
$allowedSorts = array('m.score_total', 'm.score_class', 'm.date_calculation', 'o.ref', 'r.ref', 'm.status');
if ($showEnvironment) {
	$allowedSorts[] = 'm.entity';
}
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 'm.score_total';
}
$sortorder = strtoupper(GETPOST('sortorder', 'aZ09')) === 'ASC' ? 'ASC' : 'DESC';
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
}
$page = max(0, GETPOSTINT('page'));
$offset = $limit * $page;
$searchText = trim(GETPOST('search_text', 'restricthtml'));
$searchClass = GETPOST('search_class', 'aZ09');
$searchStatus = GETPOST('search_status', 'alphanohtml');
$minimumScore = GETPOST('minimum_score', 'alphanohtml');
$searchEntitiesRaw = GETPOST('search_entity', 'array');
if (GETPOST('button_removefilter', 'alpha') !== '') {
	$searchText = '';
	$searchClass = '';
	$searchStatus = '';
	$minimumScore = '';
	$searchEntitiesRaw = array();
	$page = 0;
	$offset = 0;
}
$searchEntities = $showEnvironment
	? emergencyhouseEntitySelection($searchEntitiesRaw, $entities)
	: array();
$filteredEntities = empty($searchEntities) ? $entities : $searchEntities;
$entityOptions = $showEnvironment
	? emergencyhouseEntityOptionsForScope($db, $entities)
	: array();
$where = ' WHERE m.entity IN ('.implode(',', $filteredEntities).')';
if ($searchText !== '') {
	$where .= " AND (o.ref LIKE '%".$db->escape($searchText)."%' OR r.ref LIKE '%".$db->escape($searchText)."%')";
}
if (in_array($searchClass, array('strong', 'medium', 'weak'), true)) {
	$where .= " AND m.score_class = '".$db->escape($searchClass)."'";
}
if ($searchStatus !== '' && in_array((int) $searchStatus, array(0, 1), true)) {
	$where .= ' AND m.status = '.((int) $searchStatus);
}
if ($minimumScore !== '' && is_numeric($minimumScore)) {
	$where .= ' AND m.score_total >= '.max(0.0, min(100.0, (float) $minimumScore));
}
$joins = ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = m.fk_offer AND o.entity = m.entity';
$joins .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = m.fk_request AND r.entity = m.entity';
$sqlCount = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'emergencyhouse_match AS m'.$joins.$where;
$resqlCount = $db->query($sqlCount);
$countRow = $resqlCount ? $db->fetch_object($resqlCount) : false;
$totalnboflines = is_object($countRow) ? (int) $countRow->total : 0;
$sql = 'SELECT m.*, o.ref AS offer_ref, r.ref AS request_ref';
$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_match AS m'.$joins.$where;
$sql .= ' ORDER BY '.$sortfield.' '.$sortorder;
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
$rows = array();
if ($resql) {
	while (is_object($row = $db->fetch_object($resql))) {
		$rows[] = $row;
	}
} else {
	dol_print_error($db);
}
$hasNext = count($rows) > $limit;
if ($hasNext) {
	array_pop($rows);
}
$num = count($rows);
$arrayfields = array(
	'o.ref' => array('label' => 'Offer', 'checked' => 1, 'position' => 10),
	'r.ref' => array('label' => 'Request', 'checked' => 1, 'position' => 20),
	'm.score_total' => array('label' => 'MatchScore', 'checked' => 1, 'position' => 30),
	'm.score_class' => array('label' => 'MatchClass', 'checked' => 1, 'position' => 40),
	'm.score_capacity' => array('label' => 'CapacityScore', 'checked' => 1, 'position' => 50),
	'm.score_dates' => array('label' => 'DateScore', 'checked' => 1, 'position' => 60),
	'm.date_calculation' => array('label' => 'DateCalculation', 'checked' => 1, 'position' => 70),
	'm.status' => array('label' => 'Status', 'checked' => 1, 'position' => 80),
);
if ($showEnvironment) {
	$arrayfields['m.entity'] = array('label' => 'Environment', 'checked' => 1, 'position' => 90);
}
$selectedfields = Form::multiSelectArrayWithCheckbox('selectedfields', $arrayfields, 'emergencyhousematchlist');
$form = new Form($db);
$param = '&search_text='.urlencode($searchText).'&search_class='.urlencode($searchClass);
$param .= '&search_status='.urlencode($searchStatus).'&minimum_score='.urlencode($minimumScore);
foreach ($searchEntities as $searchEntity) {
	$param .= '&search_entity[]='.((int) $searchEntity);
}

llxHeader('', $langs->trans('Matches'));
if (emergencyhouseCanDo($user, 'match', 'write')) {
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="marginbottomonly">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="recalculate">';
	print '<label for="source_type">'.$langs->trans('RecalculateMatches').'</label> ';
	print $form->selectarray('source_type', array('offer' => $langs->trans('Offer'), 'request' => $langs->trans('Request')), 'request', 0, 0, 0, '', 0, 0, 0, '', 'minwidth150');
	print ' <input class="flat maxwidth100" id="source_id" name="source_id" type="number" min="1" required placeholder="'.$langs->trans('TechnicalID').'"> ';
	print '<button class="button" type="submit">'.$langs->trans('Recalculate').'</button></form>';
	print ajax_combobox('source_type');
}
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print_barre_liste(
	$langs->trans('Matches'),
	$page,
	$_SERVER['PHP_SELF'],
	$param,
	$sortfield,
	$sortorder,
	'',
	$num,
	$totalnboflines,
	'compress',
	0,
	$selectedfields,
	'',
	$limit,
	$hasNext ? 0 : 1
);
print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) {
		continue;
	}
	print '<td>';
	if ($field === 'o.ref' || $field === 'r.ref') {
		print '<input class="flat maxwidth125" name="search_text" value="'.dol_escape_htmltag($searchText).'">';
	} elseif ($field === 'm.score_total') {
		print '<input class="flat maxwidth75" type="number" min="0" max="100" step="0.01" name="minimum_score" value="'.dol_escape_htmltag($minimumScore).'">';
	} elseif ($field === 'm.score_class') {
		print $form->selectarray('search_class', array(
			'' => '',
			'strong' => $langs->trans('MatchClassStrong'),
			'medium' => $langs->trans('MatchClassMedium'),
			'weak' => $langs->trans('MatchClassWeak'),
		), $searchClass, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth125');
	} elseif ($field === 'm.status') {
		print $form->selectarray('search_status', array('' => '', 1 => $langs->trans('StatusActive'), 0 => $langs->trans('StatusInactive')), $searchStatus, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth125');
	} elseif ($field === 'm.entity') {
		print Form::multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'minwidth150', 0, 0, '', '', $langs->trans('Environment'));
	}
	print '</td>';
}
print '<td class="liste_titre center">';
print '<button class="liste_titre button_search" type="submit" name="button_search" value="x">'.img_picto($langs->trans('Search'), 'search').'</button>';
print '<button class="liste_titre button_removefilter" type="submit" name="button_removefilter" value="x">'.img_picto($langs->trans('RemoveFilter'), 'searchclear').'</button>';
print '</td></tr>';
print '<tr class="liste_titre">';
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) {
		continue;
	}
	print_liste_field_titre($langs->trans($definition['label']), $_SERVER['PHP_SELF'], $field, '', $param, '', $sortfield, $sortorder);
}
print '<th></th></tr>';
$offerStatic = new EmergencyHouseOffer($db);
$requestStatic = new EmergencyHouseRequest($db);
foreach ($rows as $row) {
	print '<tr class="oddeven">';
	foreach ($arrayfields as $field => $definition) {
		if (empty($definition['checked'])) {
			continue;
		}
		print '<td>';
		if ($field === 'o.ref') {
			print $offerStatic->fetch((int) $row->fk_offer) > 0 ? $offerStatic->getNomUrl(1) : '<span class="opacitymedium">#'.((int) $row->fk_offer).'</span>';
		} elseif ($field === 'r.ref') {
			print $requestStatic->fetch((int) $row->fk_request) > 0 ? $requestStatic->getNomUrl(1) : '<span class="opacitymedium">#'.((int) $row->fk_request).'</span>';
		} elseif (in_array($field, array('m.score_total', 'm.score_capacity', 'm.score_dates'), true)) {
			$property = substr($field, 2);
			print dol_escape_htmltag(number_format((float) $row->{$property}, 2, '.', '')).'%';
		} elseif ($field === 'm.score_class') {
			$scoreClass = (string) $row->score_class;
			$statusCode = $scoreClass === 'strong' ? 4 : ($scoreClass === 'medium' ? 1 : 6);
			print dolGetStatus($langs->trans('MatchClass'.ucfirst($scoreClass)), '', '', $statusCode, 1);
		} elseif ($field === 'm.date_calculation') {
			print dol_print_date($db->jdate($row->date_calculation), 'dayhour');
		} elseif ($field === 'm.status') {
			print dolGetStatus($langs->trans((int) $row->status === 1 ? 'StatusActive' : 'StatusInactive'), '', '', (int) $row->status === 1 ? 4 : 6, 1);
		} elseif ($field === 'm.entity') {
			print emergencyhouseEntityBadge((int) $row->entity, $entityOptions);
		}
		print '</td>';
	}
	print '<td class="center"><a class="reposition" href="'.dol_buildpath('/emergencyhouse/match/card.php', 1).'?id='.((int) $row->rowid).'">'.img_picto($langs->trans('View'), 'view').'</a></td>';
	print '</tr>';
}
if ($num === 0) {
	$visibleFieldCount = 0;
	foreach ($arrayfields as $definition) {
		if (!empty($definition['checked'])) {
			$visibleFieldCount++;
		}
	}
	print '<tr class="oddeven"><td colspan="'.($visibleFieldCount + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div></form>';
print ajax_combobox('search_class');
print ajax_combobox('search_status');
llxFooter();
$db->close();
