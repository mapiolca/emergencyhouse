<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Shared native list controller for Emergency House CommonObject records.
 *
 * @var string $emergencyhouseListType Configured by the object list entrypoint.
 */

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
dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/campaign.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/report.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));

if (!isModEnabled('emergencyhouse')) {
	accessforbidden();
}

/**
 * @var array<string, array{
 *     class:string,
 *     table:string,
 *     element:string,
 *     permission_object:string,
 *     permission_action:string,
 *     title:string,
 *     picto:string,
 *     label_field:string,
 *     joins:string,
 *     select:string,
 *     search_fields:array<int, string>,
 *     fields:array<string, array{label:string,checked:int,position:int}>
 * }> $configurations
 */
$configurations = array(
	'campaign' => array(
		'class' => 'EmergencyHouseCampaign',
		'table' => 'emergencyhouse_campaign',
		'element' => 'campaign',
		'permission_object' => 'campaign',
		'permission_action' => 'read',
		'title' => 'Campaigns',
		'picto' => 'emergencyhouse@emergencyhouse',
		'label_field' => 'label',
		'joins' => '',
		'select' => 't.label, t.date_start, t.date_end',
		'search_fields' => array('t.ref', 't.label'),
		'fields' => array(
			't.ref' => array('label' => 'Ref', 'checked' => 1, 'position' => 10),
			't.label' => array('label' => 'Label', 'checked' => 1, 'position' => 20),
			't.date_start' => array('label' => 'DateStart', 'checked' => 1, 'position' => 30),
			't.date_end' => array('label' => 'DateEnd', 'checked' => 1, 'position' => 40),
			't.status' => array('label' => 'Status', 'checked' => 1, 'position' => 50),
			't.entity' => array('label' => 'Environment', 'checked' => 1, 'position' => 60),
		),
	),
	'offer' => array(
		'class' => 'EmergencyHouseOffer',
		'table' => 'emergencyhouse_offer',
		'element' => 'offer',
		'permission_object' => 'listing',
		'permission_action' => 'read',
		'title' => 'Offers',
		'picto' => 'home',
		'label_field' => 'title',
		'joins' => ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c ON c.rowid = t.fk_campaign AND c.entity = t.entity',
		'select' => 't.title, t.capacity_available, t.verification_status, t.date_start, t.date_end, c.rowid AS campaign_id, c.ref AS campaign_ref',
		'search_fields' => array('t.ref', 't.title', 't.public_zone'),
		'fields' => array(
			't.ref' => array('label' => 'Ref', 'checked' => 1, 'position' => 10),
			't.title' => array('label' => 'Title', 'checked' => 1, 'position' => 20),
			'campaign' => array('label' => 'Campaign', 'checked' => 1, 'position' => 30),
			't.capacity_available' => array('label' => 'CapacityAvailable', 'checked' => 1, 'position' => 40),
			't.date_start' => array('label' => 'DateStart', 'checked' => 1, 'position' => 50),
			't.status' => array('label' => 'Status', 'checked' => 1, 'position' => 60),
			't.entity' => array('label' => 'Environment', 'checked' => 1, 'position' => 70),
		),
	),
	'request' => array(
		'class' => 'EmergencyHouseRequest',
		'table' => 'emergencyhouse_request',
		'element' => 'request',
		'permission_object' => 'listing',
		'permission_action' => 'read',
		'title' => 'Requests',
		'picto' => 'people-arrows',
		'label_field' => 'title',
		'joins' => ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c ON c.rowid = t.fk_campaign AND c.entity = t.entity',
		'select' => 't.title, t.person_count, t.remaining_count, t.urgency_level, t.date_start, t.date_end, c.rowid AS campaign_id, c.ref AS campaign_ref',
		'search_fields' => array('t.ref', 't.title', 't.desired_zone'),
		'fields' => array(
			't.ref' => array('label' => 'Ref', 'checked' => 1, 'position' => 10),
			't.title' => array('label' => 'Title', 'checked' => 1, 'position' => 20),
			'campaign' => array('label' => 'Campaign', 'checked' => 1, 'position' => 30),
			't.person_count' => array('label' => 'PeopleCount', 'checked' => 1, 'position' => 40),
			't.remaining_count' => array('label' => 'RemainingCount', 'checked' => 1, 'position' => 50),
			't.date_start' => array('label' => 'DateStart', 'checked' => 1, 'position' => 60),
			't.status' => array('label' => 'Status', 'checked' => 1, 'position' => 70),
			't.entity' => array('label' => 'Environment', 'checked' => 1, 'position' => 80),
		),
	),
	'solicitation' => array(
		'class' => 'EmergencyHouseSolicitation',
		'table' => 'emergencyhouse_solicitation',
		'element' => 'solicitation',
		'permission_object' => 'solicitation',
		'permission_action' => 'write',
		'title' => 'Solicitations',
		'picto' => 'comment',
		'label_field' => 'ref',
		'joins' => ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = t.fk_offer AND o.entity = t.entity'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = t.fk_request AND r.entity = t.entity',
		'select' => 't.date_response, t.date_expiration, o.rowid AS offer_id, o.ref AS offer_ref, r.rowid AS request_id, r.ref AS request_ref',
		'search_fields' => array('t.ref', 'o.ref', 'r.ref'),
		'fields' => array(
			't.ref' => array('label' => 'Ref', 'checked' => 1, 'position' => 10),
			'offer' => array('label' => 'Offer', 'checked' => 1, 'position' => 20),
			'request' => array('label' => 'Request', 'checked' => 1, 'position' => 30),
			't.date_creation' => array('label' => 'DateCreation', 'checked' => 1, 'position' => 40),
			't.date_expiration' => array('label' => 'ExpirationDate', 'checked' => 1, 'position' => 50),
			't.status' => array('label' => 'Status', 'checked' => 1, 'position' => 60),
			't.entity' => array('label' => 'Environment', 'checked' => 1, 'position' => 70),
		),
	),
	'allocation' => array(
		'class' => 'EmergencyHouseAllocation',
		'table' => 'emergencyhouse_allocation',
		'element' => 'allocation',
		'permission_object' => 'allocation',
		'permission_action' => 'write',
		'title' => 'AllocationsAndStays',
		'picto' => 'calendar-check',
		'label_field' => 'ref',
		'joins' => ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = t.fk_offer AND o.entity = t.entity'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = t.fk_request AND r.entity = t.entity',
		'select' => 't.quantity, t.date_start, t.date_end, t.host_confirmed, t.requester_confirmed, o.rowid AS offer_id, o.ref AS offer_ref, r.rowid AS request_id, r.ref AS request_ref',
		'search_fields' => array('t.ref', 'o.ref', 'r.ref'),
		'fields' => array(
			't.ref' => array('label' => 'Ref', 'checked' => 1, 'position' => 10),
			'offer' => array('label' => 'Offer', 'checked' => 1, 'position' => 20),
			'request' => array('label' => 'Request', 'checked' => 1, 'position' => 30),
			't.quantity' => array('label' => 'Quantity', 'checked' => 1, 'position' => 40),
			't.date_start' => array('label' => 'DateStart', 'checked' => 1, 'position' => 50),
			't.date_end' => array('label' => 'DateEnd', 'checked' => 1, 'position' => 60),
			't.status' => array('label' => 'Status', 'checked' => 1, 'position' => 70),
			't.entity' => array('label' => 'Environment', 'checked' => 1, 'position' => 80),
		),
	),
	'report' => array(
		'class' => 'EmergencyHouseReport',
		'table' => 'emergencyhouse_report',
		'element' => 'report',
		'permission_object' => 'report',
		'permission_action' => 'write',
		'title' => 'Reports',
		'picto' => 'warning',
		'label_field' => 'ref',
		'joins' => ' LEFT JOIN '.MAIN_DB_PREFIX.'c_emergencyhouse_report_reason AS reason ON reason.rowid = t.fk_report_reason AND reason.entity = t.entity',
		'select' => 't.object_type, t.fk_object, t.severity, t.fk_assigned_user, reason.label AS reason_label',
		'search_fields' => array('t.ref', 't.object_type'),
		'fields' => array(
			't.ref' => array('label' => 'Ref', 'checked' => 1, 'position' => 10),
			't.object_type' => array('label' => 'ObjectType', 'checked' => 1, 'position' => 20),
			't.fk_object' => array('label' => 'LinkedObject', 'checked' => 1, 'position' => 30),
			'reason' => array('label' => 'ReportReason', 'checked' => 1, 'position' => 40),
			't.severity' => array('label' => 'Severity', 'checked' => 1, 'position' => 50),
			't.date_creation' => array('label' => 'DateCreation', 'checked' => 1, 'position' => 60),
			't.status' => array('label' => 'Status', 'checked' => 1, 'position' => 70),
			't.entity' => array('label' => 'Environment', 'checked' => 1, 'position' => 80),
		),
	),
);

if (empty($emergencyhouseListType) || !isset($configurations[$emergencyhouseListType])) {
	accessforbidden();
}
$configuration = $configurations[$emergencyhouseListType];
if (!emergencyhouseCanDo($user, $configuration['permission_object'], $configuration['permission_action'])) {
	accessforbidden();
}

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = strtoupper(GETPOST('sortorder', 'aZ09')) === 'ASC' ? 'ASC' : 'DESC';
$allowedSorts = array_keys($configuration['fields']);
if (!in_array($sortfield, $allowedSorts, true) || in_array($sortfield, array('campaign', 'offer', 'request', 'reason'), true)) {
	$sortfield = 't.date_creation';
}
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
}
$page = max(0, GETPOSTINT('page'));
$offset = $limit * $page;
$searchText = trim(GETPOST('search_text', 'restricthtml'));
$searchStatus = GETPOST('search_status', 'alphanohtml');
$searchEntitiesRaw = GETPOST('search_entity', 'array');
$searchEntities = array();
if (is_array($searchEntitiesRaw)) {
	foreach ($searchEntitiesRaw as $entityId) {
		if ((int) $entityId > 0) {
			$searchEntities[(int) $entityId] = (int) $entityId;
		}
	}
}
if (GETPOST('button_removefilter', 'alpha') !== '') {
	$searchText = '';
	$searchStatus = '';
	$searchEntities = array();
	$page = 0;
	$offset = 0;
}

$entityScope = array_filter(array_map('intval', explode(',', (string) getEntity($configuration['element']))));
if (empty($entityScope)) {
	$entityScope = array((int) $conf->entity);
}
$entityScope = array_values(array_unique($entityScope));
$filteredEntities = empty($searchEntities)
	? $entityScope
	: array_values(array_intersect($entityScope, array_values($searchEntities)));
if (empty($filteredEntities)) {
	$filteredEntities = array(0);
}

$where = ' WHERE t.entity IN ('.implode(',', $filteredEntities).')';
if ($searchText !== '') {
	$searchClauses = array();
	foreach ($configuration['search_fields'] as $field) {
		$searchClauses[] = $field." LIKE '%".$db->escape($searchText)."%'";
	}
	$where .= ' AND ('.implode(' OR ', $searchClauses).')';
}
if ($searchStatus !== '' && preg_match('/^[0-9]+$/', $searchStatus)) {
	$where .= ' AND t.status = '.((int) $searchStatus);
}

$sqlCount = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$configuration['table'].' AS t';
$sqlCount .= $configuration['joins'].$where;
$resqlCount = $db->query($sqlCount);
$countObject = $resqlCount ? $db->fetch_object($resqlCount) : false;
if (!$resqlCount) {
	dol_print_error($db);
}
$totalnboflines = is_object($countObject) ? (int) $countObject->total : 0;

$sql = 'SELECT t.rowid, t.entity, t.ref, t.status, t.date_creation, '.$configuration['select'];
$sql .= ' FROM '.MAIN_DB_PREFIX.$configuration['table'].' AS t';
$sql .= $configuration['joins'].$where;
$sql .= ' ORDER BY '.$sortfield.' '.$sortorder;
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
}
$rows = array();
if ($resql) {
	while (is_object($obj = $db->fetch_object($resql))) {
		$rows[] = $obj;
	}
}
$hasNext = count($rows) > $limit;
if ($hasNext) {
	array_pop($rows);
}
$num = count($rows);

$entityOptions = array();
foreach ($entityScope as $entityId) {
	$entityOptions[$entityId] = $entityId === (int) $conf->entity
		? getDolGlobalString('MAIN_INFO_SOCIETE_NOM', (string) $entityId)
		: (string) $entityId;
}
if (isModEnabled('multicompany') && count($entityScope) > 1) {
	$sqlEntity = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.implode(',', $entityScope).')';
	$resqlEntity = $db->query($sqlEntity);
	if ($resqlEntity) {
		while (is_object($entityObject = $db->fetch_object($resqlEntity))) {
			$entityOptions[(int) $entityObject->rowid] = (string) $entityObject->label;
		}
	}
}

/** @var array<string, array{label:string,checked:int,position:int}> $arrayfields */
$arrayfields = $configuration['fields'];
$contextpage = 'emergencyhouse'.$configuration['element'].'list';
$selectedfields = Form::multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage);
$form = new Form($db);
$param = '&search_text='.urlencode($searchText).'&search_status='.urlencode($searchStatus);
foreach ($searchEntities as $entityId) {
	$param .= '&search_entity[]='.((int) $entityId);
}

$newButton = '';
if ($configuration['element'] === 'campaign' && emergencyhouseCanDo($user, 'campaign', 'write')) {
	$newButton = dolGetButtonTitle(
		$langs->trans('NewCampaign'),
		'',
		'fa fa-plus-circle',
		dol_buildpath('/emergencyhouse/campaign/edit.php', 1)
	);
}
if ($configuration['element'] === 'allocation' && emergencyhouseCanDo($user, 'allocation', 'write')) {
	$newButton = dolGetButtonTitle(
		$langs->trans('NewAllocation'),
		'',
		'fa fa-plus-circle',
		dol_buildpath('/emergencyhouse/allocation/create.php', 1)
	);
}

llxHeader('', $langs->trans($configuration['title']), '', '', 0, 0, array(), array(), '', 'mod-emergencyhouse page-list');
$hookmanager->initHooks(array($configuration['element'].'list', 'emergencyhouselist'));
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print_barre_liste(
	$langs->trans($configuration['title']),
	$page,
	$_SERVER['PHP_SELF'],
	$param,
	$sortfield,
	$sortorder,
	'',
	$num,
	$totalnboflines,
	$configuration['picto'],
	0,
	$newButton.$selectedfields,
	'',
	$limit,
	$hasNext ? 0 : 1
);

print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
$visibleColumns = 0;
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) {
		continue;
	}
	$visibleColumns++;
	print '<td>';
	if ($field === 't.ref' || $field === 't.label' || $field === 't.title') {
		print '<input class="flat maxwidth150" type="text" name="search_text" value="'.dol_escape_htmltag($searchText).'">';
	} elseif ($field === 't.status') {
		$statusOptions = array();
		/** @var EmergencyHouseCommonObject $statusRecord */
		$statusRecord = new $configuration['class']($db);
		for ($status = 0; $status <= 8; $status++) {
			$label = strip_tags($statusRecord->LibStatut($status, 1));
			if ($label !== '' && $label !== $langs->trans('StatusUnknown')) {
				$statusOptions[$status] = $label;
			}
		}
		print $form->selectarray('search_status', $statusOptions, $searchStatus, 1);
		print ajax_combobox('search_status');
	} elseif ($field === 't.entity') {
		print Form::multiselectarray('search_entity', $entityOptions, array_values($searchEntities), 0, 0, 'minwidth150', 0, 0, '', '', $langs->trans('Environment'));
	}
	print '</td>';
}
print '<td class="liste_titre center">';
print '<button class="liste_titre button_search" type="submit" name="button_search" value="1">'.img_picto($langs->trans('Search'), 'search').'</button>';
print '<button class="liste_titre button_removefilter" type="submit" name="button_removefilter" value="1">'.img_picto($langs->trans('RemoveFilter'), 'remove-filter').'</button>';
print '</td></tr>';

print '<tr class="liste_titre">';
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) {
		continue;
	}
	$isSortable = !in_array($field, array('campaign', 'offer', 'request', 'reason'), true);
	if ($isSortable) {
		print_liste_field_titre($langs->trans($definition['label']), $_SERVER['PHP_SELF'], $field, '', $param, '', $sortfield, $sortorder);
	} else {
		print '<th>'.$langs->trans($definition['label']).'</th>';
	}
}
print '<th></th></tr>';

foreach ($rows as $row) {
	/** @var EmergencyHouseCommonObject $record */
	$record = new $configuration['class']($db);
	$record->id = (int) $row->rowid;
	$record->ref = (string) $row->ref;
	$record->entity = (int) $row->entity;
	$record->status = (int) $row->status;
	print '<tr class="oddeven">';
	foreach ($arrayfields as $field => $definition) {
		if (empty($definition['checked'])) {
			continue;
		}
		print '<td>';
		if ($field === 't.ref') {
			print $record->getNomUrl(1);
		} elseif ($field === 't.label') {
			print dol_escape_htmltag((string) $row->label);
		} elseif ($field === 't.title') {
			print dol_escape_htmltag((string) $row->title);
		} elseif ($field === 'campaign') {
			print emergencyhouseListLinkedObject($db, 'campaign', (int) $row->campaign_id, (string) $row->campaign_ref);
		} elseif ($field === 'offer') {
			print emergencyhouseListLinkedObject($db, 'offer', (int) $row->offer_id, (string) $row->offer_ref);
		} elseif ($field === 'request') {
			print emergencyhouseListLinkedObject($db, 'request', (int) $row->request_id, (string) $row->request_ref);
		} elseif ($field === 'reason') {
			print dol_escape_htmltag($langs->trans((string) $row->reason_label));
		} elseif ($field === 't.status') {
			print $record->LibStatut((int) $row->status, 5);
		} elseif ($field === 't.entity') {
			$entityLabel = isset($entityOptions[(int) $row->entity]) ? $entityOptions[(int) $row->entity] : (string) $row->entity;
			print '<div class="refidno multicompany-entity-card-container center">';
			print '<span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div>';
		} elseif (in_array($field, array('t.date_start', 't.date_end', 't.date_creation', 't.date_expiration'), true)) {
			$property = substr($field, 2);
			$value = isset($row->{$property}) ? $row->{$property} : null;
			print $value === null ? '' : dol_print_date($db->jdate($value), 'day');
		} elseif ($field === 't.object_type') {
			print dol_escape_htmltag($langs->trans('ObjectType'.ucfirst((string) $row->object_type)));
		} elseif ($field === 't.fk_object') {
			print emergencyhouseListLinkedObject($db, (string) $row->object_type, (int) $row->fk_object, '');
		} else {
			$property = substr($field, 2);
			print isset($row->{$property}) ? dol_escape_htmltag((string) $row->{$property}) : '';
		}
		print '</td>';
	}
	print '<td class="nowrap right"><a href="'.dol_escape_htmltag(dol_buildpath('/emergencyhouse/'.$configuration['element'].'/card.php', 1).'?id='.((int) $row->rowid)).'">';
	print img_picto($langs->trans('View'), 'view').'</a></td></tr>';
}
if ($num === 0) {
	print '<tr class="oddeven"><td colspan="'.($visibleColumns + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div></form>';
llxFooter();
$db->close();

/**
 * Render a linked module object through its native-style getNomUrl().
 *
 * @param DoliDB $db Database
 * @param string $type Object type
 * @param int $id ID
 * @param string $ref Reference when already joined
 * @return string
 */
function emergencyhouseListLinkedObject($db, $type, $id, $ref)
{
	$classes = array(
		'campaign' => 'EmergencyHouseCampaign',
		'offer' => 'EmergencyHouseOffer',
		'request' => 'EmergencyHouseRequest',
		'solicitation' => 'EmergencyHouseSolicitation',
		'allocation' => 'EmergencyHouseAllocation',
		'report' => 'EmergencyHouseReport',
	);
	if ($id <= 0 || !isset($classes[$type])) {
		return '<span class="opacitymedium">#'.((int) $id).'</span>';
	}
	/** @var EmergencyHouseCommonObject $object */
	$object = new $classes[$type]($db);
	if ($ref === '') {
		if ($object->fetch($id) <= 0) {
			return '<span class="opacitymedium">#'.((int) $id).'</span>';
		}
	} else {
		$object->id = $id;
		$object->ref = $ref;
	}
	return $object->getNomUrl(1);
}
