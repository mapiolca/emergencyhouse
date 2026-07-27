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
dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/capacityservice.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'allocation', 'write')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$source = GETPOST('source', 'alphanohtml');
$quantity = max(1, GETPOSTINT('quantity'));
$dateStart = emergencyhouseAllocationReadDate('date_start', true);
$dateEnd = emergencyhouseAllocationReadDate('date_end', false);
$addressAuthorized = GETPOSTINT('address_share_authorized') === 1 ? 1 : 0;

if ($action === 'create') {
	$sourceData = emergencyhouseAllocationResolveSource($db, $source);
	if (!is_array($sourceData)) {
		setEventMessages($langs->trans('ErrorInvalidAllocationSource'), null, 'errors');
	} elseif (empty($dateStart) || (!empty($dateEnd) && $dateEnd < $dateStart)) {
		setEventMessages($langs->trans('ErrorInvalidPeriod'), null, 'errors');
	} elseif (!emergencyhouseEntityIsAccessibleForElement((int) $sourceData['entity'], 'offer')
		|| !emergencyhouseEntityIsAccessibleForElement((int) $sourceData['entity'], 'request')) {
		accessforbidden();
	} else {
		$allocation = new EmergencyHouseAllocation($db);
		$allocation->entity = (int) $sourceData['entity'];
		$allocation->fk_campaign = (int) $sourceData['fk_campaign'];
		$allocation->fk_offer = (int) $sourceData['fk_offer'];
		$allocation->fk_request = (int) $sourceData['fk_request'];
		$allocation->fk_solicitation = !empty($sourceData['fk_solicitation']) ? (int) $sourceData['fk_solicitation'] : null;
		$allocation->quantity = $quantity;
		$allocation->date_start = $dateStart;
		$allocation->date_end = $dateEnd;
		$allocation->address_share_authorized = $addressAuthorized;
		$allocation->fk_operator = (int) $user->id;
		$allocation->status = EmergencyHouseAllocation::STATUS_PROPOSED;
		$allocation->model_pdf = getDolGlobalString('EMERGENCYHOUSE_ALLOCATION_DEFAULT_MODEL', 'emergencyhouse_agreement');
		$allocation->context['trigger_reason'] = 'operator_allocation';
		$allocation->context['allocation_source'] = (string) $sourceData['source_type'];
		$capacity = new EmergencyHouseCapacityService($db);
		$result = $capacity->reserve($allocation, $user);
		if ($result > 0) {
			setEventMessages($langs->trans('AllocationCreated'), null, 'mesgs');
			header('Location: '.dol_buildpath('/emergencyhouse/allocation/card.php', 1).'?id='.((int) $allocation->id));
			exit;
		}
		setEventMessages(emergencyhouseGetUserErrorMessage($capacity->error), null, 'errors');
	}
}

$sourceOptions = emergencyhouseAllocationSourceOptions($db, $langs);
$yesNo = array(0 => $langs->trans('Disabled'), 1 => $langs->trans('Enabled'));
$form = new Form($db);

llxHeader('', $langs->trans('NewAllocation'));
print load_fiche_titre(
	$langs->trans('NewAllocation'),
	'<a href="'.dol_buildpath('/emergencyhouse/allocation/list.php', 1).'">'.$langs->trans('BackToList').'</a>',
	'calendar-check'
);
if (empty($sourceOptions)) {
	print '<div class="warning">'.$langs->trans('NoAllocatableSource').'</div>';
} else {
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="create">';
	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('AllocationSource').'</td><td>';
	print $form->selectarray('source', $sourceOptions, $source, 1, 0, 0, '', 0, 0, 0, '', 'minwidth500');
	print '</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Quantity').'</td><td><input class="flat maxwidth75" type="number" min="1" name="quantity" required value="'.((int) $quantity).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('DateStart').'</td><td>';
	print $form->selectDate($dateStart ?: dol_now(), 'date_start', 1, 1, 0, '', 1, 0, 0, 0);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('DateEnd').'</td><td>';
	print $form->selectDate($dateEnd ?: -1, 'date_end', 1, 1, 1, '', 1, 0, 0, 0);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('AddressShareAuthorized').'</td><td>';
	print $form->selectarray('address_share_authorized', $yesNo, $addressAuthorized, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150');
	print '</td></tr>';
	print '</table>';
	print '<div class="center"><button class="button button-save" type="submit">'.$langs->trans('Create').'</button> ';
	print '<a class="button button-cancel" href="'.dol_buildpath('/emergencyhouse/allocation/list.php', 1).'">'.$langs->trans('Cancel').'</a></div>';
	print '</form>';
	print ajax_combobox('source');
	print ajax_combobox('address_share_authorized');
}
llxFooter();
$db->close();

/**
 * Read a native Dolibarr date selector.
 *
 * @param string $prefix Prefix
 * @param bool   $required Required
 * @return int|null
 */
function emergencyhouseAllocationReadDate($prefix, $required)
{
	$year = GETPOSTINT($prefix.'year');
	if ($year <= 0) {
		return $required ? 0 : null;
	}
	return dol_mktime(
		GETPOSTINT($prefix.'hour'),
		GETPOSTINT($prefix.'min'),
		0,
		GETPOSTINT($prefix.'month'),
		GETPOSTINT($prefix.'day'),
		$year
	);
}

/**
 * List accepted solicitations and active matches visible to the operator.
 *
 * @param DoliDB    $db Database handler
 * @param Translate $langs Translations
 * @return array<string, string>
 */
function emergencyhouseAllocationSourceOptions($db, $langs)
{
	$entities = array_filter(array_map('intval', explode(',', (string) getEntity('offer'))));
	if (empty($entities)) {
		return array();
	}
	$options = array();
	$sql = 'SELECT s.rowid, s.ref, o.ref AS offer_ref, r.ref AS request_ref';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation AS s';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = s.fk_offer AND o.entity = s.entity';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = s.fk_request AND r.entity = s.entity';
	$sql .= ' WHERE s.entity IN ('.implode(',', $entities).')';
	$sql .= ' AND s.status = '.EmergencyHouseSolicitation::STATUS_ACCEPTED;
	$sql .= ' ORDER BY s.date_response DESC';
	$sql .= $db->plimit(250);
	$resql = $db->query($sql);
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$options['s:'.((int) $row->rowid)] = $langs->trans(
				'AllocationSourceSolicitation',
				(string) $row->ref,
				(string) $row->offer_ref,
				(string) $row->request_ref
			);
		}
	}
	$sql = 'SELECT m.rowid, m.score_total, o.ref AS offer_ref, r.ref AS request_ref';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_match AS m';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = m.fk_offer AND o.entity = m.entity';
	$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = m.fk_request AND r.entity = m.entity';
	$sql .= ' WHERE m.entity IN ('.implode(',', $entities).') AND m.status = 1';
	$sql .= ' ORDER BY m.score_total DESC, m.date_calculation DESC';
	$sql .= $db->plimit(250);
	$resql = $db->query($sql);
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$options['m:'.((int) $row->rowid)] = $langs->trans(
				'AllocationSourceMatch',
				(string) $row->offer_ref,
				(string) $row->request_ref,
				(string) $row->score_total
			);
		}
	}
	return $options;
}

/**
 * Resolve and validate a selected source.
 *
 * @param DoliDB $db Database handler
 * @param string $source Source code
 * @return array{source_type:string,entity:int,fk_campaign:int,fk_offer:int,fk_request:int,fk_solicitation:int|null}|false
 */
function emergencyhouseAllocationResolveSource($db, $source)
{
	if (!preg_match('/^([sm]):([1-9][0-9]*)$/', $source, $matches)) {
		return false;
	}
	$id = (int) $matches[2];
	if ($matches[1] === 's') {
		$sql = 'SELECT entity, fk_campaign, fk_offer, fk_request, rowid AS fk_solicitation';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation';
		$sql .= ' WHERE rowid = '.$id.' AND status = '.EmergencyHouseSolicitation::STATUS_ACCEPTED;
		$sourceType = 'solicitation';
	} else {
		$sql = 'SELECT entity, fk_campaign, fk_offer, fk_request, NULL AS fk_solicitation';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_match';
		$sql .= ' WHERE rowid = '.$id.' AND status = 1';
		$sourceType = 'match';
	}
	$resql = $db->query($sql);
	$row = $resql ? $db->fetch_object($resql) : false;
	if (!is_object($row)) {
		return false;
	}
	return array(
		'source_type' => $sourceType,
		'entity' => (int) $row->entity,
		'fk_campaign' => (int) $row->fk_campaign,
		'fk_offer' => (int) $row->fk_offer,
		'fk_request' => (int) $row->fk_request,
		'fk_solicitation' => empty($row->fk_solicitation) ? null : (int) $row->fk_solicitation,
	);
}
