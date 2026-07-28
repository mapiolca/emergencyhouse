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
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/verificationservice.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'verification', 'write')) {
	accessforbidden();
}

$objectTypeOptions = array(
	'account' => $langs->trans('PublicAccount'),
	'offer' => $langs->trans('Offer'),
	'request' => $langs->trans('Request'),
);
$action = GETPOST('action', 'aZ09');
if ($action === 'record_verification') {
	$objectType = GETPOST('object_type', 'aZ09');
	$objectId = isset($objectTypeOptions[$objectType]) ? GETPOSTINT('fk_object') : 0;
	$targetMode = GETPOST('target_mode', 'alpha');
	$verificationType = GETPOST('verification_type', 'aZ09');
	$levelId = GETPOSTINT('fk_verification_level');
	$status = GETPOSTINT('verification_status');
	$methodCode = GETPOST('method_code', 'aZ09');
	$privateNote = trim(GETPOST('private_note', 'restricthtml'));
	$expiration = emergencyhouseVerificationReadDate('date_expiration');
	$entity = emergencyhouseVerificationResolveEntity($db, $objectType, $objectId);
	if ($entity <= 0 || !emergencyhouseVerificationEntityIsAccessible($entity, $objectType)) {
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	} else {
		$encryptedNote = null;
		if ($privateNote !== '') {
			$encryption = new EmergencyHouseEncryptionService();
			$context = 'emergencyhouse|verification|'.$entity.'|'.$objectType.'|'.$objectId.'|'.$verificationType.'|'.$levelId.'|'.$methodCode;
			$encrypted = $encryption->encrypt($privateNote, $context);
			if (is_string($encrypted)) {
				$encryptedNote = $encrypted;
			} else {
				setEventMessages(emergencyhouseGetUserErrorMessage($encryption->error), null, 'errors');
			}
		}
		if ($privateNote === '' || is_string($encryptedNote)) {
			$service = new EmergencyHouseVerificationService($db);
			$result = $service->record(
				$entity,
				$objectType,
				$objectId,
				$verificationType,
				$levelId,
				$status,
				$methodCode,
				$user,
				$encryptedNote,
				$expiration
			);
			if ($result > 0) {
				setEventMessages($langs->trans('VerificationRecorded'), null, 'mesgs');
				$redirectUrl = $_SERVER['PHP_SELF'];
				if ($targetMode === 'locked') {
					$redirectUrl .= '?object_type='.urlencode($objectType).'&fk_object='.((int) $objectId);
				}
				header('Location: '.$redirectUrl);
				exit;
			}
			setEventMessages(emergencyhouseGetUserErrorMessage($service->error), null, 'errors');
		}
	}
}

$formObjectType = GETPOST('object_type', 'aZ09');
if (!isset($objectTypeOptions[$formObjectType])) {
	$formObjectType = 'offer';
}
$formObjectId = GETPOSTINT('fk_object');
$formTargetEntity = emergencyhouseVerificationResolveEntity($db, $formObjectType, $formObjectId);
$formTargetAccessible = emergencyhouseVerificationEntityIsAccessible($formTargetEntity, $formObjectType);
if (!$formTargetAccessible) {
	$formObjectId = 0;
	$formTargetEntity = (int) $conf->entity;
}
$formTargetMode = GETPOST('target_mode', 'alpha');
$formTargetLocked = $formTargetMode !== 'selector' && $formObjectId > 0 && $formTargetAccessible;
$targetOptions = $formTargetLocked
	? array()
	: emergencyhouseVerificationBuildTargetOptions($db, $formObjectType);
$form = new Form($db);
$levelOptions = array();
$sqlLevels = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_verification_level';
$sqlLevels .= ' WHERE entity = '.$formTargetEntity.' AND active = 1 ORDER BY position, label';
$resqlLevels = $db->query($sqlLevels);
if ($resqlLevels) {
	while (is_object($level = $db->fetch_object($resqlLevels))) {
		$levelOptions[(int) $level->rowid] = $langs->trans((string) $level->label);
	}
}
$verificationTypeOptions = array(
	'identity' => $langs->trans('VerificationIdentity'),
	'email' => $langs->trans('VerificationEmail'),
	'phone' => $langs->trans('VerificationPhone'),
	'address' => $langs->trans('VerificationAddress'),
	'housing' => $langs->trans('VerificationHousing'),
);
$methodOptions = array(
	'document' => $langs->trans('VerificationMethodDocument'),
	'phone_call' => $langs->trans('VerificationMethodPhoneCall'),
	'video_call' => $langs->trans('VerificationMethodVideoCall'),
	'onsite' => $langs->trans('VerificationMethodOnsite'),
	'operator_review' => $langs->trans('VerificationMethodOperatorReview'),
);
$verificationStatusOptions = array(
	0 => $langs->trans('StatusPending'),
	1 => $langs->trans('StatusVerified'),
	2 => $langs->trans('StatusRefused'),
);

$offerEntities = emergencyhouseEntityScope('offer');
$requestEntities = emergencyhouseEntityScope('request');
$entities = array_values(array_unique(array_merge(array((int) $conf->entity), $offerEntities, $requestEntities)));
$showEnvironment = emergencyhouseEntityScopeIsShared($entities);

$sortfield = GETPOST('sortfield', 'aZ09comma');
$allowedSorts = array('v.date_creation', 'v.status', 'v.object_type', 'v.verification_type', 'v.date_expiration');
if ($showEnvironment) {
	$allowedSorts[] = 'v.entity';
}
if (!in_array($sortfield, $allowedSorts, true)) {
	$sortfield = 'v.date_creation';
}
$sortorder = strtoupper(GETPOST('sortorder', 'aZ09')) === 'ASC' ? 'ASC' : 'DESC';
$limit = GETPOSTINT('limit');
if ($limit <= 0) {
	$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 25);
}
$page = max(0, GETPOSTINT('page'));
$offset = $page * $limit;
$searchObjectType = GETPOST('search_object_type', 'aZ09');
$searchStatus = GETPOST('search_status', 'alphanohtml');
$searchEntitiesRaw = GETPOST('search_entity', 'array');
if (GETPOST('button_removefilter', 'alpha') !== '') {
	$searchObjectType = '';
	$searchStatus = '';
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
$typeEntityClauses = array();
if (in_array((int) $conf->entity, $filteredEntities, true)) {
	$typeEntityClauses[] = "(v.object_type = 'account' AND v.entity = ".((int) $conf->entity).')';
}
$filteredOfferEntities = array_values(array_intersect($offerEntities, $filteredEntities));
if (!empty($filteredOfferEntities)) {
	$typeEntityClauses[] = "(v.object_type = 'offer' AND v.entity IN (".implode(',', $filteredOfferEntities).'))';
}
$filteredRequestEntities = array_values(array_intersect($requestEntities, $filteredEntities));
if (!empty($filteredRequestEntities)) {
	$typeEntityClauses[] = "(v.object_type = 'request' AND v.entity IN (".implode(',', $filteredRequestEntities).'))';
}
$where = empty($typeEntityClauses) ? ' WHERE 1 = 0' : ' WHERE ('.implode(' OR ', $typeEntityClauses).')';
if (isset($objectTypeOptions[$searchObjectType])) {
	$where .= " AND v.object_type = '".$db->escape($searchObjectType)."'";
}
if ($searchStatus !== '' && in_array((int) $searchStatus, array(0, 1, 2), true)) {
	$where .= ' AND v.status = '.((int) $searchStatus);
}
$joins = ' LEFT JOIN '.MAIN_DB_PREFIX.'c_emergencyhouse_verification_level AS l ON l.rowid = v.fk_verification_level AND l.entity = v.entity';
$joins .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_public_account AS pa ON pa.rowid = v.fk_object AND pa.entity = v.entity AND v.object_type = 'account'";
$sqlCount = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification AS v'.$where;
$resqlCount = $db->query($sqlCount);
$countRow = $resqlCount ? $db->fetch_object($resqlCount) : false;
$totalnboflines = is_object($countRow) ? (int) $countRow->total : 0;
$sql = 'SELECT v.*, l.label AS level_label, pa.public_uuid AS account_uuid';
$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification AS v'.$joins.$where;
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
$param = '&search_object_type='.urlencode($searchObjectType).'&search_status='.urlencode($searchStatus);
foreach ($searchEntities as $searchEntity) {
	$param .= '&search_entity[]='.((int) $searchEntity);
}

llxHeader(
	'',
	$langs->trans('Verifications'),
	'',
	'',
	0,
	0,
	array('/emergencyhouse/js/verification.js'),
	array(),
	'',
	'mod-emergencyhouse page-verification'
);
print load_fiche_titre($langs->trans('RecordVerification'), '', 'check');
print '<form id="emergencyhouse-verification-form" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'"';
print ' data-refresh-url="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" data-target-locked="'.($formTargetLocked ? '1' : '0').'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="record_verification">';
print '<table class="border centpercent">';
if ($formTargetLocked) {
	print '<input type="hidden" name="target_mode" value="locked">';
	print '<input type="hidden" name="object_type" value="'.dol_escape_htmltag($formObjectType).'">';
	print '<input type="hidden" name="fk_object" value="'.((int) $formObjectId).'">';
	print '<tr><td class="titlefieldcreate">'.$langs->trans('ObjectType').'</td><td>';
	print dol_escape_htmltag($objectTypeOptions[$formObjectType]);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('ObjectToVerify').'</td><td>';
	print emergencyhouseVerificationRenderTarget($db, $formObjectType, $formObjectId, $formTargetEntity);
	print '</td></tr>';
} else {
	print '<input type="hidden" name="target_mode" value="selector">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('ObjectType').'</td><td>';
	print $form->selectarray('object_type', $objectTypeOptions, $formObjectType, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200');
	print '</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('ObjectToVerify').'</td><td>';
	print $form->selectarray('fk_object', $targetOptions, $formObjectId, 1, 0, 0, '', 0, 0, 0, '', 'minwidth400');
	print '</td></tr>';
}
print '<tr><td class="fieldrequired">'.$langs->trans('VerificationType').'</td><td>'.$form->selectarray('verification_type', $verificationTypeOptions, 'identity', 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('VerificationLevel').'</td><td>'.$form->selectarray('fk_verification_level', $levelOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('Status').'</td><td>'.$form->selectarray('verification_status', $verificationStatusOptions, 1, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('VerificationMethod').'</td><td>'.$form->selectarray('method_code', $methodOptions, 'operator_review', 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr><td>'.$langs->trans('PrivateNote').'</td><td><textarea class="flat centpercent" name="private_note" rows="4"></textarea></td></tr>';
print '<tr><td>'.$langs->trans('DateExpiration').'</td><td>'.$form->selectDate(-1, 'date_expiration', 0, 0, 1, '', 1, 0, 0, 0).'</td></tr>';
print '</table><div class="center"><button class="button button-save" type="submit">'.$langs->trans('Save').'</button></div></form>';
foreach (array('object_type', 'verification_type', 'fk_verification_level', 'verification_status', 'method_code') as $selectName) {
	if ($formTargetLocked && $selectName === 'object_type') {
		continue;
	}
	print ajax_combobox($selectName);
}
if (!$formTargetLocked) {
	print ajax_combobox('fk_object');
}

$listFields = array(
	'v.object_type' => 'ObjectType',
	'v.fk_object' => 'VerifiedObject',
	'v.verification_type' => 'VerificationType',
	'v.fk_verification_level' => 'VerificationLevel',
	'v.status' => 'Status',
	'v.fk_operator' => 'Operator',
	'v.date_creation' => 'DateCreation',
	'v.date_expiration' => 'DateExpiration',
);
if ($showEnvironment) {
	$listFields['v.entity'] = 'Environment';
}
print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print_barre_liste($langs->trans('Verifications'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $totalnboflines, 'check', 0, '', '', $limit, $hasNext ? 0 : 1);
print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td>'.$form->selectarray('search_object_type', array('' => '') + $objectTypeOptions, $searchObjectType, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td></td><td></td><td></td><td>';
print $form->selectarray('search_status', array('' => '') + $verificationStatusOptions, $searchStatus, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150');
print '</td><td></td><td></td><td></td>';
if ($showEnvironment) {
	print '<td>';
	print Form::multiselectarray('search_entity', $entityOptions, $searchEntities, 0, 0, 'minwidth150', 0, 0, '', '', $langs->trans('Environment'));
	print '</td>';
}
print '<td class="center">';
print '<button class="liste_titre button_search" name="button_search" value="x">'.img_picto($langs->trans('Search'), 'search').'</button>';
print '<button class="liste_titre button_removefilter" name="button_removefilter" value="x">'.img_picto($langs->trans('RemoveFilter'), 'searchclear').'</button>';
print '</td></tr>';
print '<tr class="liste_titre">';
foreach ($listFields as $field => $label) {
	print_liste_field_titre($langs->trans($label), $_SERVER['PHP_SELF'], in_array($field, $allowedSorts, true) ? $field : '', '', $param, '', $sortfield, $sortorder);
}
print '<th></th></tr>';
$offerStatic = new EmergencyHouseOffer($db);
$requestStatic = new EmergencyHouseRequest($db);
$userStatic = new User($db);
foreach ($rows as $row) {
	$objectTypeLabel = isset($objectTypeOptions[(string) $row->object_type])
		? $objectTypeOptions[(string) $row->object_type]
		: $langs->trans('Object');
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($objectTypeLabel).'</td><td>';
	if ((string) $row->object_type === 'offer' && $offerStatic->fetch((int) $row->fk_object) > 0) {
		print $offerStatic->getNomUrl(1);
	} elseif ((string) $row->object_type === 'request' && $requestStatic->fetch((int) $row->fk_object) > 0) {
		print $requestStatic->getNomUrl(1);
	} elseif ((string) $row->object_type === 'account' && !empty($row->account_uuid)) {
		print $langs->trans('PublicAccount').' '.dol_escape_htmltag(substr((string) $row->account_uuid, 0, 8));
	} else {
		print '<span class="opacitymedium">#'.((int) $row->fk_object).'</span>';
	}
	print '</td><td>'.$langs->trans('Verification'.ucfirst((string) $row->verification_type)).'</td>';
	print '<td>'.$langs->trans((string) $row->level_label).'</td>';
	$verificationStatusType = (int) $row->status === 1 ? 'status4' : ((int) $row->status === 2 ? 'status6' : 'status1');
	print '<td>'.dolGetStatus(
		$verificationStatusOptions[(int) $row->status] ?? $langs->trans('StatusUnknown'),
		'',
		'',
		$verificationStatusType,
		5
	).'</td>';
	print '<td>'.($userStatic->fetch((int) $row->fk_operator) > 0 ? $userStatic->getNomUrl(-1) : '<span class="opacitymedium">#'.((int) $row->fk_operator).'</span>').'</td>';
	print '<td>'.dol_print_date($db->jdate($row->date_creation), 'dayhour').'</td>';
	print '<td>'.(!empty($row->date_expiration) ? dol_print_date($db->jdate($row->date_expiration), 'day') : '').'</td>';
	if ($showEnvironment) {
		print '<td class="center">'.emergencyhouseEntityBadge((int) $row->entity, $entityOptions).'</td>';
	}
	print '<td></td></tr>';
}
if ($num === 0) {
	print '<tr class="oddeven"><td colspan="'.(count($listFields) + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div></form>';
print ajax_combobox('search_object_type');
print ajax_combobox('search_status');
llxFooter();
$db->close();

/**
 * Resolve the entity that owns a verification target.
 *
 * @param DoliDB $db Database handler
 * @param string $objectType Type
 * @param int $objectId ID
 * @return int
 */
function emergencyhouseVerificationResolveEntity($db, $objectType, $objectId)
{
	$tables = array(
		'account' => 'emergencyhouse_public_account',
		'offer' => 'emergencyhouse_offer',
		'request' => 'emergencyhouse_request',
	);
	if (!isset($tables[$objectType]) || $objectId <= 0) {
		return 0;
	}
	$sql = 'SELECT entity FROM '.MAIN_DB_PREFIX.$tables[$objectType].' WHERE rowid = '.((int) $objectId);
	$resql = $db->query($sql);
	$row = $resql ? $db->fetch_object($resql) : false;
	return is_object($row) ? (int) $row->entity : 0;
}

/**
 * Check the owner entity against the target-specific sharing scope.
 *
 * Public accounts are intentionally not shared between entities.
 *
 * @param int    $entity     Entity
 * @param string $objectType Target type
 * @return bool
 */
function emergencyhouseVerificationEntityIsAccessible($entity, $objectType)
{
	global $conf;

	if ($objectType === 'account') {
		return $entity > 0 && $entity === (int) $conf->entity;
	}
	if (!in_array($objectType, array('offer', 'request'), true)) {
		return false;
	}

	return emergencyhouseEntityIsAccessibleForElement($entity, $objectType);
}

/**
 * Build native selector options for the requested verification target type.
 *
 * @param DoliDB $db Database handler
 * @param string $objectType Target type
 * @return array<int, string>
 */
function emergencyhouseVerificationBuildTargetOptions($db, $objectType)
{
	global $conf, $langs, $user;

	$options = array();
	if ($objectType === 'account') {
		$sql = 'SELECT rowid, entity, public_uuid, firstname_encrypted, lastname_encrypted, status';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' ORDER BY date_creation DESC, rowid DESC';
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.': '.$db->lasterror(), LOG_ERR);
			return $options;
		}

		$canRevealIdentity = emergencyhouseCanDo($user, 'sensitive', 'contact');
		while (is_object($row = $db->fetch_object($resql))) {
			$uuid = (string) $row->public_uuid;
			$shortIdentifier = substr($uuid, 0, 8);
			$label = $langs->trans('PublicAccount').' '.$shortIdentifier;
			if ((int) $row->status === EmergencyHousePublicAccount::STATUS_ANONYMIZED) {
				$label = $langs->trans('PublicAccountAnonymized').' '.$shortIdentifier;
			} elseif ($canRevealIdentity) {
				$account = new EmergencyHousePublicAccount($db);
				$account->id = (int) $row->rowid;
				$account->entity = (int) $row->entity;
				$account->public_uuid = $uuid;
				$account->firstname_encrypted = (string) $row->firstname_encrypted;
				$account->lastname_encrypted = (string) $row->lastname_encrypted;
				$identity = $account->getDecryptedIdentity();
				if (is_array($identity)) {
					$displayName = trim(dolGetFirstLastname($identity['firstname'], $identity['lastname']));
					if ($displayName !== '') {
						$label = $displayName.' — '.$langs->trans('PublicAccount').' '.$shortIdentifier;
					}
				}
			}
			$options[(int) $row->rowid] = $label;
		}

		return $options;
	}

	if (!in_array($objectType, array('offer', 'request'), true)) {
		return $options;
	}

	$scope = emergencyhouseEntityScope($objectType);
	if (empty($scope)) {
		return $options;
	}
	$entityOptions = emergencyhouseEntityOptionsForScope($db, $scope);
	$showEnvironment = emergencyhouseEntityScopeIsShared($scope);
	$sql = 'SELECT rowid, entity, ref, title';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_'.$objectType;
	$sql .= ' WHERE entity IN ('.implode(',', array_map('intval', $scope)).')';
	$sql .= ' ORDER BY ref DESC, rowid DESC';
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.': '.$db->lasterror(), LOG_ERR);
		return $options;
	}
	while (is_object($row = $db->fetch_object($resql))) {
		$label = (string) $row->ref;
		if ((string) $row->title !== '' && (string) $row->title !== $label) {
			$label .= ' — '.(string) $row->title;
		}
		if ($showEnvironment) {
			$entityId = (int) $row->entity;
			$label .= ' — ['.($entityOptions[$entityId] ?? (string) $entityId).']';
		}
		$options[(int) $row->rowid] = $label;
	}

	return $options;
}

/**
 * Render a validated verification target without exposing its technical ID.
 *
 * @param DoliDB $db Database handler
 * @param string $objectType Target type
 * @param int $objectId Target ID
 * @param int $entity Owner entity
 * @return string
 */
function emergencyhouseVerificationRenderTarget($db, $objectType, $objectId, $entity)
{
	global $langs, $user;

	if ($objectType === 'offer') {
		$object = new EmergencyHouseOffer($db);
		if ($object->fetch($objectId) > 0 && (int) $object->entity === $entity) {
			return $object->getNomUrl(1);
		}
	} elseif ($objectType === 'request') {
		$object = new EmergencyHouseRequest($db);
		if ($object->fetch($objectId) > 0 && (int) $object->entity === $entity) {
			return $object->getNomUrl(1);
		}
	} elseif ($objectType === 'account') {
		$account = new EmergencyHousePublicAccount($db);
		if ($account->fetch($objectId, $entity) > 0) {
			$shortIdentifier = substr((string) $account->public_uuid, 0, 8);
			$label = $langs->trans('PublicAccount').' '.$shortIdentifier;
			if ((int) $account->status === EmergencyHousePublicAccount::STATUS_ANONYMIZED) {
				$label = $langs->trans('PublicAccountAnonymized').' '.$shortIdentifier;
			} elseif (emergencyhouseCanDo($user, 'sensitive', 'contact')) {
				$identity = $account->getDecryptedIdentity();
				if (is_array($identity)) {
					$displayName = trim(dolGetFirstLastname($identity['firstname'], $identity['lastname']));
					if ($displayName !== '') {
						$label = $displayName.' — '.$langs->trans('PublicAccount').' '.$shortIdentifier;
					}
				}
			}

			return img_picto('', 'user', 'class="pictofixedwidth"').dol_escape_htmltag($label);
		}
	}

	return '<span class="opacitymedium">'.$langs->trans('ErrorRecordNotFound').'</span>';
}

/**
 * Read optional native date selector.
 *
 * @param string $prefix Prefix
 * @return int|null
 */
function emergencyhouseVerificationReadDate($prefix)
{
	$year = GETPOSTINT($prefix.'year');
	if ($year <= 0) {
		return null;
	}
	return dol_mktime(23, 59, 59, GETPOSTINT($prefix.'month'), GETPOSTINT($prefix.'day'), $year);
}
