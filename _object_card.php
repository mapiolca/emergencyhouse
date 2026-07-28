<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Shared native card controller for Emergency House CommonObject records.
 *
 * @var string $emergencyhouseCardType Configured by the object card entrypoint.
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/campaign.class.php');
dol_include_once('/emergencyhouse/class/capacityservice.class.php');
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/messageservice.class.php');
dol_include_once('/emergencyhouse/class/moderationservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');
dol_include_once('/emergencyhouse/class/report.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/sensitivedataservice.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse', 'other'));

/**
 * @var array<string, array{
 *     class:string,
 *     permission_object:string,
 *     permission_action:string,
 *     title:string,
 *     fields:array<string, string>
 * }> $configurations
 */
$configurations = array(
	'campaign' => array(
		'class' => 'EmergencyHouseCampaign',
		'permission_object' => 'campaign',
		'permission_action' => 'read',
		'title' => 'Campaign',
		'fields' => array(
			'label' => 'Label',
			'description_public' => 'PublicDescription',
			'coordinator_name' => 'CoordinatorName',
			'official_phone' => 'OfficialPhone',
			'official_email' => 'OfficialEmail',
			'date_start' => 'DateStart',
			'date_end' => 'DateEnd',
			'timezone' => 'Timezone',
			'public_visibility_mode' => 'PublicVisibilityMode',
			'verification_policy' => 'VerificationPolicy',
			'default_radius' => 'DefaultRadius',
			'retention_days' => 'RetentionDays',
			'consent_version' => 'ConsentVersion',
		),
	),
	'offer' => array(
		'class' => 'EmergencyHouseOffer',
		'permission_object' => 'listing',
		'permission_action' => 'read',
		'title' => 'Offer',
		'fields' => array(
			'title' => 'Title',
			'fk_campaign' => 'Campaign',
			'fk_account' => 'DepositedBy',
			'public_zone' => 'PublicZone',
			'zip' => 'Zip',
			'town' => 'Town',
			'date_start' => 'DateStart',
			'date_end' => 'DateEnd',
			'capacity_total' => 'CapacityTotal',
			'capacity_available' => 'CapacityAvailable',
			'room_count' => 'RoomCount',
			'bed_count' => 'BedCount',
			'verification_status' => 'VerificationStatus',
		),
	),
	'request' => array(
		'class' => 'EmergencyHouseRequest',
		'permission_object' => 'listing',
		'permission_action' => 'read',
		'title' => 'Request',
		'fields' => array(
			'title' => 'Title',
			'fk_campaign' => 'Campaign',
			'fk_account' => 'PublicAccount',
			'person_count' => 'PeopleCount',
			'remaining_count' => 'RemainingCount',
			'desired_zone' => 'DesiredZone',
			'search_radius' => 'SearchRadiusKm',
			'date_start' => 'DateStart',
			'date_end' => 'DateEnd',
			'urgency_level' => 'UrgencyLevel',
			'visibility' => 'Visibility',
			'verification_status' => 'VerificationStatus',
		),
	),
	'solicitation' => array(
		'class' => 'EmergencyHouseSolicitation',
		'permission_object' => 'solicitation',
		'permission_action' => 'write',
		'title' => 'Solicitation',
		'fields' => array(
			'fk_campaign' => 'Campaign',
			'fk_offer' => 'Offer',
			'fk_request' => 'Request',
			'fk_match' => 'Match',
			'initiator_direction' => 'Direction',
			'date_read' => 'DateRead',
			'date_response' => 'DateResponse',
			'date_expiration' => 'ExpirationDate',
			'initiator_contact_consent' => 'InitiatorContactConsent',
			'recipient_contact_consent' => 'RecipientContactConsent',
			'address_share_authorized' => 'AddressShareAuthorized',
		),
	),
	'allocation' => array(
		'class' => 'EmergencyHouseAllocation',
		'permission_object' => 'allocation',
		'permission_action' => 'write',
		'title' => 'Allocation',
		'fields' => array(
			'fk_campaign' => 'Campaign',
			'fk_offer' => 'Offer',
			'fk_request' => 'Request',
			'fk_solicitation' => 'Solicitation',
			'quantity' => 'Quantity',
			'date_start' => 'DateStart',
			'date_end' => 'DateEnd',
			'actual_start' => 'ActualStart',
			'actual_end' => 'ActualEnd',
			'host_confirmed' => 'HostConfirmed',
			'requester_confirmed' => 'RequesterConfirmed',
			'address_share_authorized' => 'AddressShareAuthorized',
			'cancellation_reason' => 'CancellationReason',
			'incident_open' => 'IncidentOpen',
			'fk_operator' => 'Operator',
		),
	),
	'report' => array(
		'class' => 'EmergencyHouseReport',
		'permission_object' => 'report',
		'permission_action' => 'write',
		'title' => 'Report',
		'fields' => array(
			'fk_campaign' => 'Campaign',
			'object_type' => 'ObjectType',
			'fk_object' => 'LinkedObject',
			'fk_report_reason' => 'ReportReason',
			'severity' => 'Severity',
			'fk_assigned_user' => 'AssignedTo',
			'retention_hold' => 'RetentionHold',
			'retention_hold_reason' => 'RetentionHoldReason',
			'date_closure' => 'DateClosure',
		),
	),
);

if (!isModEnabled('emergencyhouse')
	|| empty($emergencyhouseCardType)
	|| !isset($configurations[$emergencyhouseCardType])) {
	accessforbidden();
}
$configuration = $configurations[$emergencyhouseCardType];
$id = GETPOSTINT('id');
$tab = GETPOST('tab', 'aZ09');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
if ($tab === '') {
	$tab = 'card';
}

/** @var EmergencyHouseCommonObject $object */
$object = new $configuration['class']($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
if (!emergencyhouseCanDo($user, $configuration['permission_object'], $configuration['permission_action'], $object)) {
	accessforbidden();
}
$permissionToWrite = emergencyhouseCanDo($user, $configuration['permission_object'], 'write', $object);
$documentsTabRequested = $tab === 'documents' && property_exists($object, 'model_pdf');
$documentStorageReady = false;
$upload_dir = '';
$relativepathwithnofile = '';
$permissiontoadd = $permissionToWrite ? 1 : 0;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (getDolGlobalString('MAIN_DOC_SORT_FIELD') !== '') {
	$sortfield = getDolGlobalString('MAIN_DOC_SORT_FIELD');
}
if (getDolGlobalString('MAIN_DOC_SORT_ORDER') !== '') {
	$sortorder = getDolGlobalString('MAIN_DOC_SORT_ORDER');
}
if ($sortfield === '') {
	$sortfield = 'name';
}
if ($sortorder === '') {
	$sortorder = 'ASC';
}
if ($documentsTabRequested) {
	$resolvedUploadDir = getMultidirOutput($object, 'emergencyhouse', 1);
	$resolvedRelativePath = getMultidirOutput($object, 'emergencyhouse', 1, 'outputrel');
	if (is_string($resolvedUploadDir)
		&& $resolvedUploadDir !== ''
		&& strpos($resolvedUploadDir, 'error-') !== 0
		&& is_string($resolvedRelativePath)
		&& strpos($resolvedRelativePath, 'error-') !== 0) {
		$upload_dir = $resolvedUploadDir;
		$relativepathwithnofile = trim($resolvedRelativePath, '/\\');
		if ($relativepathwithnofile !== '') {
			$relativepathwithnofile .= '/';
		}
		$documentStorageReady = true;
	}
}

if ($action === 'set_status' && $permissionToWrite) {
	$newStatus = GETPOSTINT('new_status');
	$allowedStatuses = emergencyhouseCardAllowedStatuses($object);
	if (!isset($allowedStatuses[$newStatus])) {
		setEventMessages($langs->trans('ErrorInvalidStatusTransition'), null, 'errors');
	} else {
		$result = $object->setStatus($newStatus, $user, 'operator_status_change');
		if ($result > 0) {
			setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		}
		setEventMessages(emergencyhouseGetUserErrorMessage((string) $object->error), null, 'errors');
	}
} elseif ($action === 'cancel_allocation'
	&& $permissionToWrite
	&& $object instanceof EmergencyHouseAllocation) {
	$reasonCode = GETPOST('cancellation_reason', 'aZ09');
	$capacity = new EmergencyHouseCapacityService($db);
	$result = $capacity->cancel($object, $user, $reasonCode);
	if ($result > 0) {
		setEventMessages($langs->trans('AllocationCancelled'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
		exit;
	}
	setEventMessages(emergencyhouseGetUserErrorMessage($capacity->error), null, 'errors');
} elseif ($action === 'builddoc'
	&& $permissionToWrite
	&& emergencyhouseCanDo($user, 'sensitive', 'contact', $object)
	&& emergencyhouseCanDo($user, 'sensitive', 'address', $object)
	&& $object instanceof EmergencyHouseAllocation) {
	$model = GETPOST('model', 'alpha');
	if ($model === '') {
		$model = getDolGlobalString('EMERGENCYHOUSE_ALLOCATION_DEFAULT_MODEL', 'emergencyhouse_agreement');
	}
	$result = $object->generateDocument($model, $langs);
	if ($result > 0) {
		setEventMessages($langs->trans('DocumentGenerated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&tab=documents');
		exit;
	}
	setEventMessages(emergencyhouseGetUserErrorMessage((string) $object->error), null, 'errors');
} elseif ($action === 'moderate_report'
	&& $object instanceof EmergencyHouseReport
	&& emergencyhouseCanDo($user, 'moderation', 'write', $object)) {
	$moderationActionId = GETPOSTINT('fk_moderation_action');
	$reasonCode = GETPOST('reason_code', 'aZ09');
	$privateNote = trim(GETPOST('private_note', 'restricthtml'));
	$dateEnd = null;
	if (GETPOSTINT('date_endyear') > 0) {
		$dateEnd = dol_mktime(
			23,
			59,
			59,
			GETPOSTINT('date_endmonth'),
			GETPOSTINT('date_endday'),
			GETPOSTINT('date_endyear')
		);
	}
	$encryptedNote = null;
	if ($privateNote !== '') {
		$encryption = new EmergencyHouseEncryptionService();
		$encrypted = $encryption->encrypt(
			$privateNote,
			'emergencyhouse|moderation|'.$object->entity.'|'.$object->id.'|'.$moderationActionId.'|'.$object->object_type.'|'.$object->fk_object
		);
		if (is_string($encrypted)) {
			$encryptedNote = $encrypted;
		} else {
			setEventMessages(emergencyhouseGetUserErrorMessage($encryption->error), null, 'errors');
		}
	}
	if ($privateNote === '' || is_string($encryptedNote)) {
		$moderation = new EmergencyHouseModerationService($db);
		$result = $moderation->apply(
			(int) $object->entity,
			(int) $object->id,
			$moderationActionId,
			(string) $object->object_type,
			(int) $object->fk_object,
			$user,
			$reasonCode !== '' ? $reasonCode : null,
			$encryptedNote,
			$dateEnd
		);
		if ($result > 0) {
			setEventMessages($langs->trans('ModerationActionApplied'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		}
		setEventMessages(emergencyhouseGetUserErrorMessage($moderation->error), null, 'errors');
	}
}

$permissionnote = $permissionToWrite && property_exists($object, 'note_public');
if ($tab === 'notes' && $permissionnote) {
	include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';
}
if ($documentsTabRequested && $documentStorageReady) {
	$documentMutationRequested = GETPOST('sendit', 'alpha') !== ''
		|| GETPOST('linkit', 'restricthtml') !== ''
		|| in_array($action, array('confirm_deletefile', 'confirm_updateline', 'renamefile'), true);
	if ($documentMutationRequested && !$permissiontoadd) {
		accessforbidden();
	}
	$backtopage = $_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&tab=documents';
	include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
}

$revealedAddress = false;
if ($action === 'reveal_address' && $object instanceof EmergencyHouseOffer) {
	$justification = trim(GETPOST('justification', 'restricthtml'));
	$sensitiveService = new EmergencyHouseSensitiveDataService($db);
	$revealedAddress = $sensitiveService->revealAddressForOperator($object, $user, $justification);
	if (!is_string($revealedAddress)) {
		setEventMessages(emergencyhouseGetUserErrorMessage($sensitiveService->error), null, 'errors');
	}
}

llxHeader('', $langs->trans($configuration['title']).' '.$object->ref, '', '', 0, 0, array(), array(), '', 'mod-emergencyhouse page-card');
$head = emergencyhouseObjectPrepareHead($object);
print dol_get_fiche_head($head, $tab, $langs->trans($configuration['title']), -1, $object->picto);
print dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref');

$ficheHeadClosed = false;
if ($tab === 'notes' && property_exists($object, 'note_public')) {
	$form = new Form($db);
	$cssclass = 'titlefield';
	$moreparam = '&tab=notes';
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';
	print '</div>';
	print '<div class="clearboth"></div>';
} elseif ($tab === 'documents' && property_exists($object, 'model_pdf')) {
	if (!$documentStorageReady) {
		print '<div class="error">'.$langs->trans('DocumentStorageUnavailable').'</div>';
	} else {
		$form = new Form($db);
		$formfile = new FormFile($db);
		$filearray = dol_dir_list(
			$upload_dir,
			'files',
			0,
			'',
			'(\.meta|_preview.*\.png)$',
			$sortfield,
			strtolower($sortorder) === 'desc' ? SORT_DESC : SORT_ASC,
			1
		);
		if (!is_array($filearray)) {
			$filearray = array();
		}
		$totalSize = 0;
		foreach ($filearray as $file) {
			if (isset($file['size'])) {
				$totalSize += (int) $file['size'];
			}
		}

		print '<div class="fichecenter">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border tableforfield centpercent">';
		print '<tr><td class="titlefield">'.$langs->trans('NbOfAttachedFiles').'</td>';
		print '<td colspan="3">'.count($filearray).'</td></tr>';
		print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td>';
		print '<td colspan="3">'.dol_print_size($totalSize, 1, 1).'</td></tr>';
		print '</table>';
		print '</div>';

		print dol_get_fiche_end();
		$ficheHeadClosed = true;

		$canGenerateAgreement = $object instanceof EmergencyHouseAllocation
			&& $permissionToWrite
			&& emergencyhouseCanDo($user, 'sensitive', 'contact', $object)
			&& emergencyhouseCanDo($user, 'sensitive', 'address', $object);
		if ($canGenerateAgreement) {
			$urlSource = $_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&tab=documents';
			$models = array('emergencyhouse_agreement' => $langs->trans('EmergencyHouseAgreement'));
			print $formfile->showdocuments(
				'emergencyhouse',
				$relativepathwithnofile,
				'',
				$urlSource,
				$models,
				0,
				(string) $object->model_pdf,
				1,
				0,
				0,
				0,
				0,
				'',
				'',
				'',
				'',
				'',
				$object
			);
		}

		$modulepart = 'emergencyhouse';
		$permission = $permissiontoadd;
		$permtoedit = $permissiontoadd;
		$param = '&id='.((int) $object->id).'&tab=documents&entity='.((int) $object->entity);
		$moreparam = '&tab=documents';
		include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';
	}
} elseif ($tab === 'agenda') {
	if (isModEnabled('agenda')) {
		$formActions = new FormActions($db);
		$formActions->showactions($object, $object->element.'@emergencyhouse', 0, 1, 'listactions', 100);
	} else {
		print '<div class="warning">'.$langs->trans('AgendaModuleDisabled').'</div>';
	}
} elseif ($tab === 'audit') {
	emergencyhouseCardRenderAudit($db, $object);
} else {
	print '<div class="fichecenter"><div class="fichehalfleft"><table class="border centpercent">';
	foreach ($configuration['fields'] as $field => $label) {
		print '<tr><td class="titlefield">'.$langs->trans($label).'</td><td>';
		print emergencyhouseCardRenderField($db, $object, $field);
		print '</td></tr>';
	}
	if ($object instanceof EmergencyHouseReport && !empty($object->description_encrypted)) {
		$encryption = new EmergencyHouseEncryptionService();
		$description = $encryption->decrypt(
			(string) $object->description_encrypted,
			'emergencyhouse|report|'.$object->entity.'|'.$object->public_uuid.'|description'
		);
		print '<tr><td>'.$langs->trans('Description').'</td><td>'.(is_string($description) ? nl2br(dol_escape_htmltag($description)) : $langs->trans('EncryptedValueUnavailable')).'</td></tr>';
	}
	print '</table></div><div class="fichehalfright">';
	if ($object instanceof EmergencyHouseSolicitation) {
		$messageService = new EmergencyHouseMessageService($db);
		$messages = $messageService->fetchMessages($object, null, true, 100);
		print load_fiche_titre($langs->trans('Messages'));
		print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Message').'</th></tr>';
		if (is_array($messages) && !empty($messages)) {
			foreach ($messages as $message) {
				print '<tr class="oddeven"><td>'.dol_print_date((int) $message['date_creation'], 'dayhour').'</td>';
				print '<td>'.nl2br(dol_escape_htmltag((string) $message['body'])).'</td></tr>';
			}
		} else {
			print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		}
		print '</table>';
	}
	if ($object instanceof EmergencyHouseOffer && emergencyhouseCanDo($user, 'sensitive', 'address', $object)) {
		print load_fiche_titre($langs->trans('SensitiveData'));
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.((int) $object->id).'">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="reveal_address">';
		print '<label for="justification">'.$langs->trans('DisclosureJustification').'</label> ';
		$justificationOptions = array();
		foreach (EmergencyHouseSensitiveDataService::getOperatorJustificationTranslationKeys() as $code => $translationKey) {
			$justificationOptions[$code] = $langs->trans($translationKey);
		}
		$formSensitive = new Form($db);
		print $formSensitive->selectarray('justification', $justificationOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth300');
		print ajax_combobox('justification');
		print '<button class="button" type="submit">'.$langs->trans('RevealExactAddress').'</button></form>';
		if (is_string($revealedAddress)) {
			print '<div class="info">'.nl2br(dol_escape_htmltag($revealedAddress)).'</div>';
		}
	}
	if ($object instanceof EmergencyHouseAllocation
		&& $permissionToWrite
		&& in_array((int) $object->status, array(
			EmergencyHouseAllocation::STATUS_PROPOSED,
			EmergencyHouseAllocation::STATUS_CONFIRMED,
			EmergencyHouseAllocation::STATUS_ACTIVE,
			EmergencyHouseAllocation::STATUS_INCIDENT,
		), true)) {
		$cancellationOptions = array();
		$sqlCancellation = 'SELECT code, label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_cancellation_reason';
		$sqlCancellation .= ' WHERE entity = '.((int) $object->entity).' AND active = 1 ORDER BY position, label';
		$resqlCancellation = $db->query($sqlCancellation);
		if ($resqlCancellation) {
			while (is_object($cancellationOption = $db->fetch_object($resqlCancellation))) {
				$cancellationOptions[(string) $cancellationOption->code] = $langs->trans((string) $cancellationOption->label);
			}
		}
		$formCancellation = new Form($db);
		print load_fiche_titre($langs->trans('CancelAllocation'));
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.((int) $object->id).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="cancel_allocation">';
		print '<label for="cancellation_reason">'.$langs->trans('CancellationReason').'</label> ';
		print $formCancellation->selectarray('cancellation_reason', $cancellationOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth250');
		if (!empty($cancellationOptions)) {
			print ' <button class="button button-delete" type="submit">'.$langs->trans('CancelAllocation').'</button>';
		}
		print '</form>';
		print ajax_combobox('cancellation_reason');
	}
	if ($object instanceof EmergencyHouseReport && emergencyhouseCanDo($user, 'moderation', 'write', $object)) {
		$actionOptions = array();
		$sqlActions = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_moderation_action';
		$sqlActions .= ' WHERE entity = '.((int) $object->entity).' AND active = 1 ORDER BY position, label';
		$resqlActions = $db->query($sqlActions);
		if ($resqlActions) {
			while (is_object($actionOption = $db->fetch_object($resqlActions))) {
				$actionOptions[(int) $actionOption->rowid] = $langs->trans((string) $actionOption->label);
			}
		}
		$reasonOptions = array('' => '');
		$sqlReasons = 'SELECT code, label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_report_reason';
		$sqlReasons .= ' WHERE entity = '.((int) $object->entity).' AND active = 1 ORDER BY position, label';
		$resqlReasons = $db->query($sqlReasons);
		if ($resqlReasons) {
			while (is_object($reasonOption = $db->fetch_object($resqlReasons))) {
				$reasonOptions[(string) $reasonOption->code] = $langs->trans((string) $reasonOption->label);
			}
		}
		$formModeration = new Form($db);
		print load_fiche_titre($langs->trans('Moderation'));
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.((int) $object->id).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="moderate_report">';
		print '<table class="border centpercent">';
		print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('ModerationAction').'</td><td>';
		print $formModeration->selectarray('fk_moderation_action', $actionOptions, '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		print '</td></tr>';
		print '<tr><td>'.$langs->trans('Reason').'</td><td>';
		print $formModeration->selectarray('reason_code', $reasonOptions, '', 0, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		print '</td></tr>';
		print '<tr><td>'.$langs->trans('PrivateNote').'</td><td><textarea class="flat centpercent" name="private_note" rows="4"></textarea></td></tr>';
		print '<tr><td>'.$langs->trans('EndDate').'</td><td>';
		print $formModeration->selectDate(-1, 'date_end', 0, 0, 1, '', 1, 0, 0, 0);
		print '</td></tr></table>';
		if (!empty($actionOptions)) {
			print '<div class="center"><button class="button button-save" type="submit">'.$langs->trans('Apply').'</button></div>';
		}
		print '</form>';
		print ajax_combobox('fk_moderation_action');
		print ajax_combobox('reason_code');
	}
	print '</div></div><div class="clearboth"></div>';
}

if (!$ficheHeadClosed) {
	print dol_get_fiche_end();
}
if ($tab === 'card' && $permissionToWrite) {
	$allowedStatuses = emergencyhouseCardAllowedStatuses($object);
	if (!empty($allowedStatuses)
		|| $object instanceof EmergencyHouseCampaign
		|| ($object instanceof EmergencyHouseOffer && (int) $object->verification_status <= 0)) {
		print '<div class="tabsAction">';
		if ($object instanceof EmergencyHouseCampaign) {
			print dolGetButtonAction(
				'',
				$langs->trans('Modify'),
				'default',
				dol_buildpath('/emergencyhouse/campaign/edit.php', 1).'?id='.((int) $object->id),
				''
			);
		}
		if ($object instanceof EmergencyHouseOffer && (int) $object->verification_status <= 0) {
			print dolGetButtonAction(
				'',
				$langs->trans('RecordVerification'),
				'default',
				dol_buildpath('/emergencyhouse/verification/list.php', 1).'?object_type=offer&amp;fk_object='.((int) $object->id),
				''
			);
		}
		foreach ($allowedStatuses as $status => $label) {
			$statusUrl = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
			$statusUrl .= '&amp;action=set_status&amp;new_status='.((int) $status).'&amp;token='.newToken();
			print dolGetButtonAction('', $langs->trans($label), 'default', $statusUrl, '');
		}
		print '</div>';
	}
}
llxFooter();
$db->close();

/**
 * Return allowed operator transitions and their action labels.
 *
 * @param EmergencyHouseCommonObject $object Object
 * @return array<int, string>
 */
function emergencyhouseCardAllowedStatuses($object)
{
	if ($object instanceof EmergencyHouseCampaign) {
		$map = array(
			0 => array(1 => 'Publish'),
			1 => array(2 => 'Suspend', 3 => 'Close'),
			2 => array(1 => 'Reopen', 3 => 'Close'),
			3 => array(4 => 'Archive'),
		);
	} elseif ($object instanceof EmergencyHouseOffer) {
		$map = array(
			0 => array(1 => 'SubmitForValidation', 5 => 'Close'),
			1 => array(2 => 'ValidateAndPublish', 6 => 'Reject', 5 => 'Close'),
			2 => array(3 => 'Suspend', 4 => 'Expire', 5 => 'Close'),
			3 => array(2 => 'Reopen', 5 => 'Close'),
			4 => array(2 => 'Reopen', 5 => 'Close'),
			6 => array(0 => 'SetToDraft', 5 => 'Close'),
		);
	} elseif ($object instanceof EmergencyHouseRequest) {
		$map = array(
			0 => array(1 => 'Publish'),
			1 => array(4 => 'Suspend', 5 => 'Expire', 6 => 'Close'),
			2 => array(1 => 'Reopen', 3 => 'MarkFulfilled', 4 => 'Suspend', 5 => 'Expire', 6 => 'Close'),
			3 => array(1 => 'Reopen', 6 => 'Close'),
			4 => array(1 => 'Reopen', 6 => 'Close'),
			5 => array(1 => 'Reopen', 6 => 'Close'),
		);
	} elseif ($object instanceof EmergencyHouseSolicitation) {
		$map = array(
			0 => array(4 => 'Expire'),
			1 => array(5 => 'Close'),
			2 => array(5 => 'Close'),
			3 => array(5 => 'Close'),
			4 => array(5 => 'Close'),
		);
	} elseif ($object instanceof EmergencyHouseAllocation) {
		$map = array(
			1 => array(2 => 'StartStay', 5 => 'OpenIncident'),
			2 => array(3 => 'CompleteStay', 5 => 'OpenIncident'),
			5 => array(2 => 'ResolveIncident', 3 => 'CompleteStay'),
		);
	} elseif ($object instanceof EmergencyHouseReport) {
		$map = array(
			0 => array(1 => 'StartReview', 2 => 'Resolve', 3 => 'Dismiss'),
			1 => array(0 => 'Reopen', 2 => 'Resolve', 3 => 'Dismiss'),
			2 => array(1 => 'ReopenReview'),
			3 => array(1 => 'ReopenReview'),
		);
	} else {
		return array();
	}
	return isset($map[(int) $object->status]) ? $map[(int) $object->status] : array();
}

/**
 * Render one safe card field.
 *
 * @param DoliDB $db Database
 * @param EmergencyHouseCommonObject $object Object
 * @param string $field Field
 * @return string
 */
function emergencyhouseCardRenderField($db, $object, $field)
{
	global $langs;

	$value = property_exists($object, $field) ? $object->{$field} : null;
	if (in_array($field, array('date_start', 'date_end', 'actual_start', 'actual_end', 'date_read', 'date_response', 'date_expiration', 'date_closure'), true)) {
		return empty($value) ? '' : dol_print_date((int) $value, 'dayhour');
	}
	if ($field === 'fk_account') {
		return emergencyhouseCardRenderPublicAccount($db, $object, (int) $value);
	}
	$linkedTypes = array(
		'fk_campaign' => array('EmergencyHouseCampaign', 'campaign'),
		'fk_offer' => array('EmergencyHouseOffer', 'offer'),
		'fk_request' => array('EmergencyHouseRequest', 'request'),
		'fk_solicitation' => array('EmergencyHouseSolicitation', 'solicitation'),
	);
	if (isset($linkedTypes[$field]) && (int) $value > 0) {
		/** @var EmergencyHouseCommonObject $linked */
		$linked = new $linkedTypes[$field][0]($db);
		return $linked->fetch((int) $value) > 0
			? $linked->getNomUrl(1)
			: '<span class="opacitymedium">#'.((int) $value).'</span>';
	}
	if ($field === 'fk_assigned_user' || $field === 'fk_operator') {
		if ((int) $value <= 0) {
			return '';
		}
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$userStatic = new User($db);
		return $userStatic->fetch((int) $value) > 0
			? $userStatic->getNomUrl(-1)
			: '<span class="opacitymedium">#'.((int) $value).'</span>';
	}
	if ($field === 'fk_report_reason' && (int) $value > 0) {
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_report_reason';
		$sql .= ' WHERE rowid = '.((int) $value).' AND entity = '.((int) $object->entity);
		$resql = $db->query($sql);
		$reason = $resql ? $db->fetch_object($resql) : false;
		return is_object($reason) ? dol_escape_htmltag($langs->trans((string) $reason->label)) : '';
	}
	if ($field === 'cancellation_reason' && is_string($value) && $value !== '') {
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_cancellation_reason';
		$sql .= " WHERE code = '".$db->escape($value)."' AND entity = ".((int) $object->entity);
		$resql = $db->query($sql);
		$reason = $resql ? $db->fetch_object($resql) : false;
		return is_object($reason) ? dol_escape_htmltag($langs->trans((string) $reason->label)) : dol_escape_htmltag($langs->trans('StatusUnknown'));
	}
	if ($field === 'fk_object' && $object instanceof EmergencyHouseReport) {
		return emergencyhouseCardRenderReportTarget($db, (string) $object->object_type, (int) $value);
	}
	if (in_array($field, array('host_confirmed', 'requester_confirmed', 'address_share_authorized', 'incident_open', 'retention_hold', 'initiator_contact_consent', 'recipient_contact_consent'), true)) {
		return $langs->trans(!empty($value) ? 'Yes' : 'No');
	}
	if ($field === 'object_type') {
		return $langs->trans('ObjectType'.ucfirst((string) $value));
	}
	if ($field === 'visibility') {
		return $langs->trans((string) $value === 'public' ? 'VisibilityPublic' : 'VisibilityPrivate');
	}
	if ($field === 'verification_status') {
		return $langs->trans((int) $value > 0 ? 'StatusVerified' : 'StatusUnverified');
	}
	if ($field === 'urgency_level') {
		return $langs->trans('UrgencyLevelValue'.((int) $value));
	}
	return nl2br(dol_escape_htmltag((string) $value));
}

/**
 * Render the public account owning a record without exposing its technical ID.
 *
 * @param DoliDB                     $db Database
 * @param EmergencyHouseCommonObject $object Context object
 * @param int                        $accountId Public account ID
 * @return string
 */
function emergencyhouseCardRenderPublicAccount($db, $object, $accountId)
{
	global $langs, $user;

	if ($accountId <= 0) {
		return '<span class="opacitymedium">'.$langs->trans('PublicAccountUnavailable').'</span>';
	}
	if (!emergencyhouseCanDo($user, 'sensitive', 'contact', $object)) {
		return '<span class="opacitymedium">'.$langs->trans('PublicIdentityProtected').'</span>';
	}

	$account = new EmergencyHousePublicAccount($db);
	if ($account->fetch($accountId, (int) $object->entity) <= 0) {
		return '<span class="opacitymedium">'.$langs->trans('PublicAccountUnavailable').'</span>';
	}
	if ((int) $account->status === EmergencyHousePublicAccount::STATUS_ANONYMIZED) {
		return '<span class="opacitymedium">'.$langs->trans('PublicAccountAnonymized').'</span>';
	}

	$identity = $account->getDecryptedIdentity();
	if (!is_array($identity)) {
		return '<span class="opacitymedium">'.$langs->trans('EncryptedValueUnavailable').'</span>';
	}
	$displayName = trim(dolGetFirstLastname($identity['firstname'], $identity['lastname']));
	if ($displayName === '') {
		return '<span class="opacitymedium">'.$langs->trans('PublicAccountUnavailable').'</span>';
	}

	return img_picto('', 'user', 'class="pictofixedwidth"').dol_escape_htmltag($displayName);
}

/**
 * Render a report target through its object when possible.
 *
 * @param DoliDB $db Database
 * @param string $type Type
 * @param int $id ID
 * @return string
 */
function emergencyhouseCardRenderReportTarget($db, $type, $id)
{
	$classes = array(
		'campaign' => 'EmergencyHouseCampaign',
		'offer' => 'EmergencyHouseOffer',
		'request' => 'EmergencyHouseRequest',
		'solicitation' => 'EmergencyHouseSolicitation',
		'allocation' => 'EmergencyHouseAllocation',
		'report' => 'EmergencyHouseReport',
	);
	if (!isset($classes[$type]) || $id <= 0) {
		return '<span class="opacitymedium">#'.((int) $id).'</span>';
	}
	/** @var EmergencyHouseCommonObject $target */
	$target = new $classes[$type]($db);
	return $target->fetch($id) > 0 ? $target->getNomUrl(1) : '<span class="opacitymedium">#'.((int) $id).'</span>';
}

/**
 * Render entity-scoped audit records.
 *
 * @param DoliDB $db Database
 * @param EmergencyHouseCommonObject $object Object
 * @return void
 */
function emergencyhouseCardRenderAudit($db, $object)
{
	global $langs;

	$sql = 'SELECT actor_type, fk_actor, action_code, justification_code, date_creation';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_audit';
	$sql .= ' WHERE entity = '.((int) $object->entity);
	$sql .= " AND object_type = '".$db->escape((string) $object->element)."'";
	$sql .= ' AND fk_object = '.((int) $object->id);
	$sql .= ' ORDER BY date_creation DESC'.$db->plimit(200);
	$resql = $db->query($sql);
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Action').'</th><th>'.$langs->trans('Reason').'</th><th>'.$langs->trans('Actor').'</th></tr>';
	$count = 0;
	if ($resql) {
		while (is_object($row = $db->fetch_object($resql))) {
			$count++;
			print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($row->date_creation), 'dayhour').'</td>';
			print '<td>'.dol_escape_htmltag($langs->trans(emergencyhouseCardCodeTranslationKey('AuditAction', (string) $row->action_code))).'</td>';
			print '<td>'.dol_escape_htmltag($langs->trans(emergencyhouseCardCodeTranslationKey('AuditReason', (string) $row->justification_code))).'</td><td>';
			if ((string) $row->actor_type === 'dolibarr_user' && (int) $row->fk_actor > 0) {
				require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
				$userStatic = new User($db);
				print $userStatic->fetch((int) $row->fk_actor) > 0 ? $userStatic->getNomUrl(-1) : $langs->trans('Unknown');
			} else {
				print $langs->trans('PublicAccount');
			}
			print '</td></tr>';
		}
	}
	if ($count === 0) {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
}

/**
 * Build a stable translation key from a stored technical code.
 *
 * @param string $prefix Translation key prefix
 * @param string $code Technical code
 * @return string
 */
function emergencyhouseCardCodeTranslationKey($prefix, $code)
{
	$normalized = preg_replace('/^EMERGENCYHOUSE_/', '', strtoupper(trim($code)));
	if (!is_string($normalized) || $normalized === '') {
		return $prefix.'Unknown';
	}
	$parts = preg_split('/[^A-Z0-9]+/', strtolower($normalized), -1, PREG_SPLIT_NO_EMPTY);
	if (!is_array($parts) || count($parts) === 0) {
		return $prefix.'Unknown';
	}
	return $prefix.implode('', array_map('ucfirst', $parts));
}
