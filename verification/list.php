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
dol_include_once('/emergencyhouse/class/verificationservice.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'verification', 'write')) {
	accessforbidden();
}

$form = new Form($db);
$service = new EmergencyHouseVerificationService($db);
$isAdmin = emergencyhouseUserIsFullAdmin($user);
$view = GETPOST('view', 'aZ09') === 'history' ? 'history' : 'queue';
$scope = $isAdmin && GETPOST('scope', 'aZ09') === 'all' ? 'all' : 'mine';

$objectTypeOptions = array(
	'account' => $langs->trans('PublicAccount'),
	'offer' => $langs->trans('Offer'),
	'request' => $langs->trans('Request'),
);
$verificationStatusOptions = array(
	0 => $langs->trans('StatusPending'),
	1 => $langs->trans('StatusVerified'),
	2 => $langs->trans('StatusRefused'),
);
$urgencyOptions = array(
	'neutral' => $langs->trans('VerificationUrgencyNeutral'),
	'warning' => $langs->trans('VerificationUrgencyWarning'),
	'critical' => $langs->trans('VerificationUrgencyCritical'),
);

$offerEntities = emergencyhouseEntityScope('offer');
$requestEntities = emergencyhouseEntityScope('request');
$entities = array_values(array_unique(array_merge(array((int) $conf->entity), $offerEntities, $requestEntities)));
sort($entities, SORT_NUMERIC);
$showEnvironment = emergencyhouseEntityScopeIsShared($entities);
$entityOptions = $showEnvironment ? emergencyhouseEntityOptionsForScope($db, $entities) : array();

$thresholdsByEntity = array();
foreach ($entities as $entityId) {
	$thresholdsByEntity[(int) $entityId] = emergencyhouseVerificationThresholds($db, (int) $entityId);
}

if ($view === 'queue') {
	$reconciliation = $service->reconcileAssignments($entities);
	if ($reconciliation < 0) {
		setEventMessages(emergencyhouseGetUserErrorMessage($service->error), null, 'errors');
	}
}

$limit = GETPOSTINT('limit');
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
}
$page = max(0, GETPOSTINT('page'));
$offset = $page * $limit;
$searchObjectType = GETPOST('search_object_type', 'aZ09');
$searchEntitiesRaw = GETPOST('search_entity', 'array');
$searchEntities = $showEnvironment
	? emergencyhouseEntitySelection($searchEntitiesRaw, $entities)
	: array();
if (GETPOST('button_removefilter', 'alpha') !== '') {
	$searchObjectType = '';
	$searchEntities = array();
	$page = 0;
	$offset = 0;
}
$filteredEntities = empty($searchEntities) ? $entities : $searchEntities;
$typeEntityClauses = emergencyhouseVerificationTypeEntityClauses(
	$filteredEntities,
	$offerEntities,
	$requestEntities,
	(int) $conf->entity,
	'q'
);

$rows = array();
$totalnboflines = 0;
$hasNext = false;
$num = 0;
$arrayfields = array();
$sortfield = '';
$sortorder = '';
$searchAssignedUser = '';
$searchUrgency = '';
$searchStatus = '';
$assignedUserOptions = array();
$param = '&view='.urlencode($view);
foreach ($searchEntities as $searchEntity) {
	$param .= '&search_entity[]='.((int) $searchEntity);
}
$param .= '&search_object_type='.urlencode($searchObjectType);

if ($view === 'queue') {
	$searchAssignedUser = $isAdmin && $scope === 'all' ? GETPOST('search_assigned_user', 'alphanohtml') : '';
	$searchUrgency = GETPOST('search_urgency', 'aZ09');
	if (!isset($urgencyOptions[$searchUrgency])) {
		$searchUrgency = '';
	}
	if (GETPOST('button_removefilter', 'alpha') !== '') {
		$searchAssignedUser = '';
		$searchUrgency = '';
	}
	$allowedSorts = array('q.date_queued', 'q.object_type', 'q.fk_assigned_user');
	if ($showEnvironment) {
		$allowedSorts[] = 'q.entity';
	}
	$sortfield = GETPOST('sortfield', 'aZ09comma');
	if (!in_array($sortfield, $allowedSorts, true)) {
		$sortfield = 'q.date_queued';
	}
	$sortorder = strtoupper(GETPOST('sortorder', 'aZ09')) === 'DESC' ? 'DESC' : 'ASC';

	$where = empty($typeEntityClauses) ? ' WHERE 1 = 0' : ' WHERE ('.implode(' OR ', $typeEntityClauses).')';
	$where .= ' AND q.queue_status = '.EmergencyHouseVerificationService::QUEUE_PENDING;
	$where .= " AND ((q.object_type = 'account' AND account.status = 1";
	$where .= ' AND account.email_verified = 1 AND account.verification_status < 1)';
	$where .= " OR (q.object_type = 'offer' AND offer.status = 1 AND offer.verification_status < 1)";
	$where .= " OR (q.object_type = 'request' AND request.status = 1 AND request.verification_status < 1))";
	if ($scope === 'mine') {
		$where .= ' AND q.fk_assigned_user = '.((int) $user->id);
	}
	if (isset($objectTypeOptions[$searchObjectType])) {
		$where .= " AND q.object_type = '".$db->escape($searchObjectType)."'";
	}
	if ($isAdmin && $scope === 'all' && $searchAssignedUser !== '') {
		$where .= (int) $searchAssignedUser > 0
			? ' AND q.fk_assigned_user = '.((int) $searchAssignedUser)
			: ' AND q.fk_assigned_user IS NULL';
	}
	if ($searchUrgency !== '') {
		$urgencyClauses = emergencyhouseVerificationUrgencyClauses(
			$db,
			$filteredEntities,
			$thresholdsByEntity,
			$searchUrgency,
			dol_now()
		);
		$where .= empty($urgencyClauses) ? ' AND 1 = 0' : ' AND ('.implode(' OR ', $urgencyClauses).')';
	}

	$joins = " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_public_account AS account";
	$joins .= " ON account.rowid = q.fk_object AND account.entity = q.entity AND q.object_type = 'account'";
	$joins .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_offer AS offer";
	$joins .= " ON offer.rowid = q.fk_object AND offer.entity = q.entity AND q.object_type = 'offer'";
	$joins .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_request AS request";
	$joins .= " ON request.rowid = q.fk_object AND request.entity = q.entity AND q.object_type = 'request'";
	$joins .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS assigned_user ON assigned_user.rowid = q.fk_assigned_user';
	$sqlCount = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue AS q'.$joins.$where;
	$resqlCount = $db->query($sqlCount);
	$countRow = $resqlCount ? $db->fetch_object($resqlCount) : false;
	$totalnboflines = is_object($countRow) ? (int) $countRow->total : 0;

	$sql = 'SELECT q.rowid, q.entity, q.object_type, q.fk_object, q.fk_assigned_user, q.date_queued,';
	$sql .= ' account.public_uuid AS account_uuid, offer.ref AS offer_ref, offer.title AS offer_title,';
	$sql .= ' request.ref AS request_ref, request.title AS request_title,';
	$sql .= ' assigned_user.login AS assigned_login, assigned_user.firstname AS assigned_firstname,';
	$sql .= ' assigned_user.lastname AS assigned_lastname';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue AS q'.$joins.$where;
	$sql .= ' ORDER BY '.$sortfield.' '.$sortorder.', q.rowid ASC';
	$sql .= $db->plimit($limit + 1, $offset);
	$resql = $db->query($sql);
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$rows[] = $row;
		}
	} else {
		setEventMessages($langs->trans('ErrorDatabaseQuery'), null, 'errors');
	}
	$hasNext = count($rows) > $limit;
	if ($hasNext) {
		array_pop($rows);
	}
	$num = count($rows);

	$arrayfields = array(
		'q.object_type' => array('label' => 'ObjectType', 'checked' => 1, 'position' => 10),
		'q.fk_object' => array('label' => 'ObjectToVerify', 'checked' => 1, 'position' => 20),
		'q.fk_assigned_user' => array('label' => 'AssignedTo', 'checked' => 1, 'position' => 30),
		'q.date_queued' => array('label' => 'VerificationAge', 'checked' => 1, 'position' => 40),
	);
	if ($showEnvironment) {
		$arrayfields['q.entity'] = array('label' => 'Environment', 'checked' => 1, 'position' => 50);
	}
	$selectedfields = Form::multiSelectArrayWithCheckbox(
		'selectedfields',
		$arrayfields,
		'emergencyhouseverificationqueue'
	);
	$param .= '&scope='.urlencode($scope);
	$param .= '&search_assigned_user='.urlencode($searchAssignedUser);
	$param .= '&search_urgency='.urlencode($searchUrgency);

	$assignedUserOptions = array(0 => $langs->trans('VerificationUnassigned'));
	if ($isAdmin && $scope === 'all') {
		$sqlUsers = 'SELECT DISTINCT assigned_user.rowid, assigned_user.login, assigned_user.firstname, assigned_user.lastname';
		$sqlUsers .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue AS assigned_queue';
		$sqlUsers .= ' INNER JOIN '.MAIN_DB_PREFIX.'user AS assigned_user ON assigned_user.rowid = assigned_queue.fk_assigned_user';
		$sqlUsers .= ' WHERE assigned_queue.entity IN ('.implode(',', $entities).')';
		$sqlUsers .= ' ORDER BY assigned_user.lastname, assigned_user.firstname, assigned_user.login';
		$resqlUsers = $db->query($sqlUsers);
		if ($resqlUsers) {
			while (is_object($assigned = $db->fetch_object($resqlUsers))) {
				$assignedUserOptions[(int) $assigned->rowid] = emergencyhouseVerificationUserLabel($assigned, '');
			}
		}
	}
} else {
	$searchStatus = GETPOST('search_status', 'alphanohtml');
	if (GETPOST('button_removefilter', 'alpha') !== '') {
		$searchStatus = '';
	}
	$allowedSorts = array('v.date_creation', 'v.status', 'v.object_type', 'v.verification_type', 'v.date_expiration');
	if ($showEnvironment) {
		$allowedSorts[] = 'v.entity';
	}
	$sortfield = GETPOST('sortfield', 'aZ09comma');
	if (!in_array($sortfield, $allowedSorts, true)) {
		$sortfield = 'v.date_creation';
	}
	$sortorder = strtoupper(GETPOST('sortorder', 'aZ09')) === 'ASC' ? 'ASC' : 'DESC';
	$historyTypeClauses = emergencyhouseVerificationTypeEntityClauses(
		$filteredEntities,
		$offerEntities,
		$requestEntities,
		(int) $conf->entity,
		'v'
	);
	$where = empty($historyTypeClauses) ? ' WHERE 1 = 0' : ' WHERE ('.implode(' OR ', $historyTypeClauses).')';
	if (isset($objectTypeOptions[$searchObjectType])) {
		$where .= " AND v.object_type = '".$db->escape($searchObjectType)."'";
	}
	if ($searchStatus !== '' && in_array((int) $searchStatus, array(0, 1, 2), true)) {
		$where .= ' AND v.status = '.((int) $searchStatus);
	}
	$joins = ' LEFT JOIN '.MAIN_DB_PREFIX.'c_emergencyhouse_verification_level AS level';
	$joins .= ' ON level.rowid = v.fk_verification_level AND level.entity = v.entity';
	$joins .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS operator ON operator.rowid = v.fk_operator';
	$joins .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_public_account AS account";
	$joins .= " ON account.rowid = v.fk_object AND account.entity = v.entity AND v.object_type = 'account'";
	$joins .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_offer AS offer";
	$joins .= " ON offer.rowid = v.fk_object AND offer.entity = v.entity AND v.object_type = 'offer'";
	$joins .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_request AS request";
	$joins .= " ON request.rowid = v.fk_object AND request.entity = v.entity AND v.object_type = 'request'";
	$sqlCount = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification AS v'.$where;
	$resqlCount = $db->query($sqlCount);
	$countRow = $resqlCount ? $db->fetch_object($resqlCount) : false;
	$totalnboflines = is_object($countRow) ? (int) $countRow->total : 0;

	$sql = 'SELECT v.*, level.label AS level_label, account.public_uuid AS account_uuid,';
	$sql .= ' offer.ref AS offer_ref, offer.title AS offer_title,';
	$sql .= ' request.ref AS request_ref, request.title AS request_title,';
	$sql .= ' operator.login AS operator_login, operator.firstname AS operator_firstname,';
	$sql .= ' operator.lastname AS operator_lastname';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification AS v'.$joins.$where;
	$sql .= ' ORDER BY '.$sortfield.' '.$sortorder.', v.rowid DESC';
	$sql .= $db->plimit($limit + 1, $offset);
	$resql = $db->query($sql);
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$rows[] = $row;
		}
	} else {
		setEventMessages($langs->trans('ErrorDatabaseQuery'), null, 'errors');
	}
	$hasNext = count($rows) > $limit;
	if ($hasNext) {
		array_pop($rows);
	}
	$num = count($rows);
	$arrayfields = array(
		'v.object_type' => array('label' => 'ObjectType', 'checked' => 1, 'position' => 10),
		'v.fk_object' => array('label' => 'LinkedObject', 'checked' => 1, 'position' => 20),
		'v.verification_type' => array('label' => 'VerificationType', 'checked' => 1, 'position' => 30),
		'v.fk_verification_level' => array('label' => 'VerificationLevel', 'checked' => 1, 'position' => 40),
		'v.status' => array('label' => 'Status', 'checked' => 1, 'position' => 50),
		'v.fk_operator' => array('label' => 'Operator', 'checked' => 1, 'position' => 60),
		'v.date_creation' => array('label' => 'DateCreation', 'checked' => 1, 'position' => 70),
		'v.date_expiration' => array('label' => 'ExpirationDate', 'checked' => 1, 'position' => 80),
	);
	if ($showEnvironment) {
		$arrayfields['v.entity'] = array('label' => 'Environment', 'checked' => 1, 'position' => 90);
	}
	$selectedfields = Form::multiSelectArrayWithCheckbox(
		'selectedfields',
		$arrayfields,
		'emergencyhouseverificationhistory'
	);
	$param .= '&search_status='.urlencode($searchStatus);
}

$js = $view === 'queue' ? array('/emergencyhouse/js/verification.js') : array();
$css = $view === 'queue' ? array('/emergencyhouse/css/verification.css') : array();
llxHeader('', $langs->trans('Verifications'), '', '', 0, 0, $js, $css, '', 'mod-emergencyhouse page-list');
$head = emergencyhouseVerificationPrepareHead();
print dol_get_fiche_head($head, $view, $langs->trans('Verifications'), -1, 'check');

if ($view === 'queue' && $isAdmin) {
	print '<div class="tabsAction">';
	print '<a class="butAction'.($scope === 'mine' ? 'Refused' : '').'"';
	print ' href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?view=queue&amp;scope=mine">'.$langs->trans('MyVerificationQueue').'</a>';
	print '<a class="butAction'.($scope === 'all' ? 'Refused' : '').'"';
	print ' href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?view=queue&amp;scope=all">'.$langs->trans('GlobalVerificationQueue').'</a>';
	print '</div>';
}

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="view" value="'.dol_escape_htmltag($view).'">';
if ($view === 'queue') {
	print '<input type="hidden" name="scope" value="'.dol_escape_htmltag($scope).'">';
}
print_barre_liste(
	$langs->trans($view === 'queue' ? 'VerificationQueue' : 'VerificationHistory'),
	$page,
	$_SERVER['PHP_SELF'],
	$param,
	$sortfield,
	$sortorder,
	'',
	$num,
	$totalnboflines,
	'check',
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
	if (in_array($field, array('q.object_type', 'v.object_type'), true)) {
		print $form->selectarray(
			'search_object_type',
			array('' => '') + $objectTypeOptions,
			$searchObjectType,
			0,
			0,
			0,
			'',
			0,
			0,
			0,
			'',
			'maxwidth150'
		);
	} elseif ($field === 'q.fk_assigned_user' && $isAdmin && $scope === 'all') {
		print $form->selectarray(
			'search_assigned_user',
			array('' => '') + $assignedUserOptions,
			$searchAssignedUser,
			0,
			0,
			0,
			'',
			0,
			0,
			0,
			'',
			'maxwidth200'
		);
	} elseif ($field === 'q.date_queued') {
		print $form->selectarray(
			'search_urgency',
			array('' => '') + $urgencyOptions,
			$searchUrgency,
			0,
			0,
			0,
			'',
			0,
			0,
			0,
			'',
			'maxwidth150'
		);
	} elseif ($field === 'v.status') {
		print $form->selectarray(
			'search_status',
			array('' => '') + $verificationStatusOptions,
			$searchStatus,
			0,
			0,
			0,
			'',
			0,
			0,
			0,
			'',
			'maxwidth150'
		);
	} elseif (in_array($field, array('q.entity', 'v.entity'), true)) {
		print Form::multiselectarray(
			'search_entity',
			$entityOptions,
			$searchEntities,
			0,
			0,
			'minwidth150',
			0,
			0,
			'',
			'',
			$langs->trans('Environment')
		);
	}
	print '</td>';
}
print '<td class="liste_titre center">';
print '<button class="liste_titre button_search" type="submit" name="button_search" value="x">';
print img_picto($langs->trans('Search'), 'search').'</button>';
print '<button class="liste_titre button_removefilter" type="submit" name="button_removefilter" value="x">';
print img_picto($langs->trans('RemoveFilter'), 'searchclear').'</button>';
print '</td></tr>';
print '<tr class="liste_titre">';
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) {
		continue;
	}
	$sortableField = $field;
	if (in_array($field, array('q.fk_object', 'v.fk_object', 'v.fk_verification_level', 'v.fk_operator'), true)) {
		$sortableField = '';
	}
	print_liste_field_titre(
		$langs->trans($definition['label']),
		$_SERVER['PHP_SELF'],
		$sortableField,
		'',
		$param,
		'',
		$sortfield,
		$sortorder
	);
}
print '<th></th></tr>';

foreach ($rows as $row) {
	print '<tr class="oddeven">';
	foreach ($arrayfields as $field => $definition) {
		if (empty($definition['checked'])) {
			continue;
		}
		$centerClass = in_array($field, array('q.entity', 'v.entity'), true) ? ' class="center"' : '';
		print '<td'.$centerClass.'>';
		if (in_array($field, array('q.object_type', 'v.object_type'), true)) {
			print dol_escape_htmltag($objectTypeOptions[(string) $row->object_type] ?? $langs->trans('Object'));
		} elseif (in_array($field, array('q.fk_object', 'v.fk_object'), true)) {
			$objectLabel = emergencyhouseVerificationObjectLabel($langs, $row);
			if ($view === 'queue') {
				print '<a href="'.dol_buildpath('/emergencyhouse/verification/card.php', 1);
				print '?queue_id='.((int) $row->rowid).'">'.dol_escape_htmltag($objectLabel).'</a>';
			} else {
				print dol_escape_htmltag($objectLabel);
			}
		} elseif ($field === 'q.fk_assigned_user') {
			print (int) $row->fk_assigned_user > 0
				? dol_escape_htmltag(emergencyhouseVerificationUserLabel($row))
				: '<span class="opacitymedium">'.$langs->trans('VerificationUnassigned').'</span>';
		} elseif ($field === 'q.date_queued') {
			$elapsed = max(0, dol_now() - $db->jdate($row->date_queued));
			$thresholds = $thresholdsByEntity[(int) $row->entity];
			$urgency = emergencyhouseVerificationUrgency($elapsed, $thresholds);
			$urgencyClass = $urgency === 'neutral' ? '' : ' emergencyhouse-verification-age--'.$urgency;
			print '<span class="emergencyhouse-verification-age'.$urgencyClass.'"';
			print ' data-elapsed="'.((int) $elapsed).'"';
			print ' data-warning="'.((int) ($thresholds['warning'] * 60)).'"';
			print ' data-critical="'.((int) ($thresholds['critical'] * 60)).'">';
			print '<span class="fa fa-exclamation-triangle emergencyhouse-verification-age__icon" aria-hidden="true"></span>';
			print '<span>'.$langs->trans('VerificationSince').' </span>';
			print '<span class="emergencyhouse-verification-age__value">';
			print emergencyhouseVerificationFormatDuration($elapsed).'</span></span>';
		} elseif ($field === 'v.verification_type') {
			print $langs->trans('Verification'.ucfirst((string) $row->verification_type));
		} elseif ($field === 'v.fk_verification_level') {
			print $langs->trans((string) $row->level_label);
		} elseif ($field === 'v.status') {
			$status = (int) $row->status;
			print dolGetStatus(
				$verificationStatusOptions[$status] ?? $langs->trans('StatusUnknown'),
				'',
				'',
				$status === EmergencyHouseVerificationService::STATUS_VERIFIED ? 'status4' : ($status === EmergencyHouseVerificationService::STATUS_REFUSED ? 'status6' : 'status1'),
				5
			);
		} elseif ($field === 'v.fk_operator') {
			print (int) $row->fk_operator > 0
				? dol_escape_htmltag(emergencyhouseVerificationUserLabel($row, 'operator_'))
				: '<span class="opacitymedium">#'.((int) $row->fk_operator).'</span>';
		} elseif ($field === 'v.date_creation') {
			print dol_print_date($db->jdate($row->date_creation), 'dayhour');
		} elseif ($field === 'v.date_expiration') {
			print !empty($row->date_expiration) ? dol_print_date($db->jdate($row->date_expiration), 'day') : '';
		} elseif (in_array($field, array('q.entity', 'v.entity'), true)) {
			print emergencyhouseEntityBadge((int) $row->entity, $entityOptions);
		}
		print '</td>';
	}
	print '<td class="center">';
	if ($view === 'queue') {
		print '<a class="reposition" href="'.dol_buildpath('/emergencyhouse/verification/card.php', 1);
		print '?queue_id='.((int) $row->rowid).'">';
		print img_picto($langs->trans('Verify'), 'check').' '.$langs->trans('Verify').'</a>';
	}
	print '</td></tr>';
}
if ($num === 0) {
	$visibleFieldCount = 0;
	foreach ($arrayfields as $definition) {
		if (!empty($definition['checked'])) {
			$visibleFieldCount++;
		}
	}
	print '<tr class="oddeven"><td colspan="'.($visibleFieldCount + 1).'">';
	print '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div></form>';
print ajax_combobox('search_object_type');
if ($view === 'queue') {
	print ajax_combobox('search_urgency');
	if ($isAdmin && $scope === 'all') {
		print ajax_combobox('search_assigned_user');
	}
} else {
	print ajax_combobox('search_status');
}
print dol_get_fiche_end();
llxFooter();
$db->close();

/**
 * Build target-specific entity clauses.
 *
 * @param array<int, int> $filteredEntities Selected entities
 * @param array<int, int> $offerEntities Offer scope
 * @param array<int, int> $requestEntities Request scope
 * @param int             $currentEntity Current entity
 * @param string          $alias SQL alias
 * @return array<int, string>
 */
function emergencyhouseVerificationTypeEntityClauses($filteredEntities, $offerEntities, $requestEntities, $currentEntity, $alias)
{
	$clauses = array();
	if (in_array($currentEntity, $filteredEntities, true)) {
		$clauses[] = "(".$alias.".object_type = 'account' AND ".$alias.'.entity = '.((int) $currentEntity).')';
	}
	$offers = array_values(array_intersect($offerEntities, $filteredEntities));
	if (!empty($offers)) {
		$clauses[] = "(".$alias.".object_type = 'offer' AND ".$alias.'.entity IN ('.implode(',', $offers).'))';
	}
	$requests = array_values(array_intersect($requestEntities, $filteredEntities));
	if (!empty($requests)) {
		$clauses[] = "(".$alias.".object_type = 'request' AND ".$alias.'.entity IN ('.implode(',', $requests).'))';
	}

	return $clauses;
}

/**
 * Build exact entity-specific urgency filters.
 *
 * @param DoliDB                                    $db Database handler
 * @param array<int, int>                           $entities Entity IDs
 * @param array<int, array{warning:int,critical:int}> $thresholds Thresholds
 * @param string                                    $urgency Urgency
 * @param int                                       $now Current timestamp
 * @return array<int, string>
 */
function emergencyhouseVerificationUrgencyClauses($db, $entities, $thresholds, $urgency, $now)
{
	$clauses = array();
	foreach ($entities as $entityId) {
		if (!isset($thresholds[$entityId])) {
			continue;
		}
		$warningDate = $db->idate($now - ($thresholds[$entityId]['warning'] * 60));
		$criticalDate = $db->idate($now - ($thresholds[$entityId]['critical'] * 60));
		$clause = '(q.entity = '.((int) $entityId);
		if ($urgency === 'critical') {
			$clause .= " AND q.date_queued <= '".$db->escape($criticalDate)."'";
		} elseif ($urgency === 'warning') {
			$clause .= " AND q.date_queued <= '".$db->escape($warningDate)."'";
			$clause .= " AND q.date_queued > '".$db->escape($criticalDate)."'";
		} else {
			$clause .= " AND q.date_queued > '".$db->escape($warningDate)."'";
		}
		$clauses[] = $clause.')';
	}

	return $clauses;
}

/**
 * Build a queue or history object label without another SQL query.
 *
 * @param Translate $langs Languages
 * @param object    $row   SQL row
 * @return string
 */
function emergencyhouseVerificationObjectLabel($langs, $row)
{
	if ((string) $row->object_type === 'offer') {
		$ref = trim((string) $row->offer_ref);
		$title = trim((string) $row->offer_title);

		return $ref !== '' && $title !== '' ? $ref.' — '.$title : ($ref !== '' ? $ref : $title);
	}
	if ((string) $row->object_type === 'request') {
		$ref = trim((string) $row->request_ref);
		$title = trim((string) $row->request_title);

		return $ref !== '' && $title !== '' ? $ref.' — '.$title : ($ref !== '' ? $ref : $title);
	}
	if ((string) $row->object_type === 'account' && !empty($row->account_uuid)) {
		return $langs->trans('PublicAccount').' '.substr((string) $row->account_uuid, 0, 8);
	}

	return $langs->trans('Object').' #'.((int) $row->fk_object);
}

/**
 * Build a readable user label from joined columns.
 *
 * @param object $row    SQL row
 * @param string $prefix Column prefix
 * @return string
 */
function emergencyhouseVerificationUserLabel($row, $prefix = 'assigned_')
{
	$firstnameProperty = $prefix.'firstname';
	$lastnameProperty = $prefix.'lastname';
	$loginProperty = $prefix.'login';
	$firstname = isset($row->{$firstnameProperty}) ? trim((string) $row->{$firstnameProperty}) : '';
	$lastname = isset($row->{$lastnameProperty}) ? trim((string) $row->{$lastnameProperty}) : '';
	$login = isset($row->{$loginProperty}) ? trim((string) $row->{$loginProperty}) : '';
	$label = trim($firstname.' '.$lastname);

	return $label !== '' ? $label : $login;
}
