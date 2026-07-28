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
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/verificationservice.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'verification', 'write')) {
	accessforbidden();
}

$queueId = GETPOSTINT('queue_id');
if ($queueId <= 0) {
	accessforbidden();
}
$service = new EmergencyHouseVerificationService($db);
$queue = $service->fetchQueueItem($queueId);
if (!is_object($queue)
	|| !emergencyhouseVerificationCardEntityIsAccessible((int) $queue->entity, (string) $queue->object_type)) {
	accessforbidden();
}
if ($service->reconcileAssignments(array((int) $queue->entity)) < 0) {
	setEventMessages(emergencyhouseGetUserErrorMessage($service->error), null, 'errors');
}
$queue = emergencyhouseVerificationCardFetchTarget($db, $queueId);
if (!is_object($queue)) {
	accessforbidden();
}
$isAdmin = emergencyhouseUserIsFullAdmin($user);
if (!$isAdmin && (int) $queue->fk_assigned_user !== (int) $user->id) {
	accessforbidden($langs->trans('ErrorVerificationQueueAssignedToAnotherUser'));
}
if (!emergencyhouseVerificationCardTargetIsEligible($queue)) {
	accessforbidden($langs->trans('ErrorVerificationQueueUnavailable'));
}

$form = new Form($db);
$thresholds = emergencyhouseVerificationThresholds($db, (int) $queue->entity);
$expiration = null;
$action = GETPOST('action', 'aZ09');
if ($action === 'record_verification') {
	$verificationType = GETPOST('verification_type', 'aZ09');
	$levelId = GETPOSTINT('fk_verification_level');
	$status = GETPOSTINT('verification_status');
	$methodCode = GETPOST('method_code', 'aZ09');
	$privateNote = trim(GETPOST('private_note', 'restricthtml'));
	$expiration = emergencyhouseVerificationCardReadDate('date_expiration');
	$encryptedNote = null;
	if ($privateNote !== '') {
		$encryption = new EmergencyHouseEncryptionService();
		$context = 'emergencyhouse|verification|'.((int) $queue->entity);
		$context .= '|'.(string) $queue->object_type.'|'.((int) $queue->fk_object);
		$context .= '|'.$verificationType.'|'.((int) $levelId).'|'.$methodCode;
		$encrypted = $encryption->encrypt($privateNote, $context);
		if (is_string($encrypted)) {
			$encryptedNote = $encrypted;
		} else {
			setEventMessages(emergencyhouseGetUserErrorMessage($encryption->error), null, 'errors');
		}
	}
	if ($privateNote === '' || is_string($encryptedNote)) {
		$result = $service->recordQueueDecision(
			$queueId,
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
			header('Location: '.dol_buildpath('/emergencyhouse/verification/list.php', 1).'?view=queue');
			exit;
		}
		setEventMessages(emergencyhouseGetUserErrorMessage($service->error), null, 'errors');
		$refreshedQueue = emergencyhouseVerificationCardFetchTarget($db, $queueId);
		if (!is_object($refreshedQueue) || !emergencyhouseVerificationCardTargetIsEligible($refreshedQueue)) {
			header('Location: '.dol_buildpath('/emergencyhouse/verification/list.php', 1).'?view=queue');
			exit;
		}
		$queue = $refreshedQueue;
	}
}

$levelOptions = array();
$sql = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_verification_level';
$sql .= ' WHERE entity = '.((int) $queue->entity).' AND active = 1 ORDER BY position, label';
$resql = $db->query($sql);
if ($resql) {
	while (is_object($level = $db->fetch_object($resql))) {
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
	EmergencyHouseVerificationService::STATUS_VERIFIED => $langs->trans('StatusVerified'),
	EmergencyHouseVerificationService::STATUS_REFUSED => $langs->trans('StatusRefused'),
);

$elapsed = max(0, dol_now() - $db->jdate($queue->date_queued));
$urgency = emergencyhouseVerificationUrgency($elapsed, $thresholds);
$urgencyClass = $urgency === 'neutral' ? '' : ' emergencyhouse-verification-age--'.$urgency;
$objectTypeOptions = array(
	'account' => $langs->trans('PublicAccount'),
	'offer' => $langs->trans('Offer'),
	'request' => $langs->trans('Request'),
);
$objectLabel = emergencyhouseVerificationCardObjectLabel($langs, $queue);
$assignedLabel = emergencyhouseVerificationCardUserLabel($queue);
$entityOptions = emergencyhouseEntityOptionsForScope($db, array((int) $queue->entity));

llxHeader(
	'',
	$langs->trans('RecordVerification'),
	'',
	'',
	0,
	0,
	array('/emergencyhouse/js/verification.js'),
	array('/emergencyhouse/css/verification.css'),
	'',
	'mod-emergencyhouse page-card'
);
$head = emergencyhouseVerificationPrepareHead();
print dol_get_fiche_head($head, 'queue', $langs->trans('RecordVerification'), -1, 'check');
print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('ObjectType').'</td><td>';
print dol_escape_htmltag($objectTypeOptions[(string) $queue->object_type] ?? $langs->trans('Object')).'</td></tr>';
print '<tr><td>'.$langs->trans('ObjectToVerify').'</td><td><strong>'.dol_escape_htmltag($objectLabel).'</strong></td></tr>';
print '<tr><td>'.$langs->trans('Environment').'</td><td>';
print emergencyhouseEntityBadge((int) $queue->entity, $entityOptions).'</td></tr>';
print '<tr><td>'.$langs->trans('AssignedTo').'</td><td>';
print $assignedLabel !== ''
	? dol_escape_htmltag($assignedLabel)
	: '<span class="opacitymedium">'.$langs->trans('VerificationUnassigned').'</span>';
print '</td></tr>';
print '<tr><td>'.$langs->trans('VerificationAge').'</td><td>';
print '<span class="emergencyhouse-verification-age'.$urgencyClass.'"';
print ' data-elapsed="'.((int) $elapsed).'"';
print ' data-warning="'.((int) ($thresholds['warning'] * 60)).'"';
print ' data-critical="'.((int) ($thresholds['critical'] * 60)).'">';
print '<span class="fa fa-exclamation-triangle emergencyhouse-verification-age__icon" aria-hidden="true"></span>';
print '<span>'.$langs->trans('VerificationSince').' </span>';
print '<span class="emergencyhouse-verification-age__value">';
print emergencyhouseVerificationFormatDuration($elapsed).'</span></span>';
print '</td></tr>';
if (!empty($queue->target_detail)) {
	print '<tr><td>'.$langs->trans('Description').'</td><td>'.dol_escape_htmltag((string) $queue->target_detail).'</td></tr>';
}
print '</table></div>';

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="record_verification">';
print '<input type="hidden" name="queue_id" value="'.((int) $queueId).'">';
print '<table class="border centpercent">';
print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('VerificationType').'</td><td>';
print $form->selectarray(
	'verification_type',
	$verificationTypeOptions,
	GETPOST('verification_type', 'aZ09') ?: 'identity',
	0,
	0,
	0,
	'',
	0,
	0,
	0,
	'',
	'minwidth250'
).'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('VerificationLevel').'</td><td>';
print $form->selectarray(
	'fk_verification_level',
	$levelOptions,
	GETPOSTINT('fk_verification_level'),
	1,
	0,
	0,
	'',
	0,
	0,
	0,
	'',
	'minwidth250'
).'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('Status').'</td><td>';
print $form->selectarray(
	'verification_status',
	$verificationStatusOptions,
	GETPOSTINT('verification_status') ?: EmergencyHouseVerificationService::STATUS_VERIFIED,
	0,
	0,
	0,
	'',
	0,
	0,
	0,
	'',
	'minwidth250'
).'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('VerificationMethod').'</td><td>';
print $form->selectarray(
	'method_code',
	$methodOptions,
	GETPOST('method_code', 'aZ09') ?: 'operator_review',
	0,
	0,
	0,
	'',
	0,
	0,
	0,
	'',
	'minwidth250'
).'</td></tr>';
print '<tr><td>'.$langs->trans('PrivateNote').'</td><td>';
print '<textarea class="flat centpercent" name="private_note" rows="4">';
print dol_escape_htmltag(GETPOST('private_note', 'restricthtml')).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('ExpirationDate').'</td><td>';
print $form->selectDate($expiration === null ? -1 : $expiration, 'date_expiration', 0, 0, 1, '', 1, 0, 0, 0);
print '</td></tr>';
print '</table>';
print '<div class="center"><button class="button button-save" type="submit">';
print $langs->trans('RecordVerification').'</button></div></form>';
foreach (array('verification_type', 'fk_verification_level', 'verification_status', 'method_code') as $selectName) {
	print ajax_combobox($selectName);
}
print dol_get_fiche_end();
llxFooter();
$db->close();

/**
 * Load a queue row and its safe target summary in one query.
 *
 * @param DoliDB $db Database handler
 * @param int    $queueId Queue ID
 * @return stdClass|null
 */
function emergencyhouseVerificationCardFetchTarget($db, $queueId)
{
	$sql = 'SELECT queue.rowid, queue.entity, queue.object_type, queue.fk_object,';
	$sql .= ' queue.queue_status, queue.fk_assigned_user, queue.date_queued,';
	$sql .= ' account.public_uuid AS account_uuid, account.status AS account_status,';
	$sql .= ' account.email_verified AS account_email_verified, account.verification_status AS account_verification_status,';
	$sql .= ' offer.ref AS offer_ref, offer.title AS offer_title, offer.public_zone AS offer_zone,';
	$sql .= ' offer.status AS offer_status, offer.verification_status AS offer_verification_status,';
	$sql .= ' request.ref AS request_ref, request.title AS request_title, request.desired_zone AS request_zone,';
	$sql .= ' request.status AS request_status, request.verification_status AS request_verification_status,';
	$sql .= ' assigned_user.login AS assigned_login, assigned_user.firstname AS assigned_firstname,';
	$sql .= ' assigned_user.lastname AS assigned_lastname,';
	$sql .= " CASE WHEN queue.object_type = 'offer' THEN offer.public_zone";
	$sql .= " WHEN queue.object_type = 'request' THEN request.desired_zone ELSE NULL END AS target_detail";
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue AS queue';
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_public_account AS account";
	$sql .= " ON account.rowid = queue.fk_object AND account.entity = queue.entity AND queue.object_type = 'account'";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_offer AS offer";
	$sql .= " ON offer.rowid = queue.fk_object AND offer.entity = queue.entity AND queue.object_type = 'offer'";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."emergencyhouse_request AS request";
	$sql .= " ON request.rowid = queue.fk_object AND request.entity = queue.entity AND queue.object_type = 'request'";
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user AS assigned_user ON assigned_user.rowid = queue.fk_assigned_user';
	$sql .= ' WHERE queue.rowid = '.((int) $queueId);
	$resql = $db->query($sql);
	$row = $resql ? $db->fetch_object($resql) : false;

	return is_object($row) ? $row : null;
}

/**
 * Check target state before displaying the decision form.
 *
 * @param object $queue Queue row
 * @return bool
 */
function emergencyhouseVerificationCardTargetIsEligible($queue)
{
	if ((int) $queue->queue_status !== EmergencyHouseVerificationService::QUEUE_PENDING) {
		return false;
	}
	if ((string) $queue->object_type === 'account') {
		return (int) $queue->account_status === 1
			&& (int) $queue->account_email_verified === 1
			&& (int) $queue->account_verification_status < 1;
	}
	if ((string) $queue->object_type === 'offer') {
		return (int) $queue->offer_status === 1 && (int) $queue->offer_verification_status < 1;
	}
	if ((string) $queue->object_type === 'request') {
		return (int) $queue->request_status === 1 && (int) $queue->request_verification_status < 1;
	}

	return false;
}

/**
 * Check target-specific Multicompany scope.
 *
 * @param int    $entity Entity
 * @param string $objectType Object type
 * @return bool
 */
function emergencyhouseVerificationCardEntityIsAccessible($entity, $objectType)
{
	global $conf;

	if ($objectType === 'account') {
		return $entity === (int) $conf->entity;
	}

	return in_array($objectType, array('offer', 'request'), true)
		&& emergencyhouseEntityIsAccessibleForElement($entity, $objectType);
}

/**
 * Build a fixed target label.
 *
 * @param Translate $langs Languages
 * @param object    $queue Queue row
 * @return string
 */
function emergencyhouseVerificationCardObjectLabel($langs, $queue)
{
	if ((string) $queue->object_type === 'offer') {
		$ref = trim((string) $queue->offer_ref);
		$title = trim((string) $queue->offer_title);

		return $ref !== '' && $title !== '' ? $ref.' — '.$title : ($ref !== '' ? $ref : $title);
	}
	if ((string) $queue->object_type === 'request') {
		$ref = trim((string) $queue->request_ref);
		$title = trim((string) $queue->request_title);

		return $ref !== '' && $title !== '' ? $ref.' — '.$title : ($ref !== '' ? $ref : $title);
	}
	if ((string) $queue->object_type === 'account' && !empty($queue->account_uuid)) {
		return $langs->trans('PublicAccount').' '.substr((string) $queue->account_uuid, 0, 8);
	}

	return $langs->trans('Object').' #'.((int) $queue->fk_object);
}

/**
 * Build the assigned-user label.
 *
 * @param object $queue Queue row
 * @return string
 */
function emergencyhouseVerificationCardUserLabel($queue)
{
	$label = trim((string) $queue->assigned_firstname.' '.(string) $queue->assigned_lastname);

	return $label !== '' ? $label : (string) $queue->assigned_login;
}

/**
 * Read an optional native date selector.
 *
 * @param string $prefix Prefix
 * @return int|null
 */
function emergencyhouseVerificationCardReadDate($prefix)
{
	$year = GETPOSTINT($prefix.'year');
	if ($year <= 0) {
		return null;
	}

	return dol_mktime(23, 59, 59, GETPOSTINT($prefix.'month'), GETPOSTINT($prefix.'day'), $year);
}
