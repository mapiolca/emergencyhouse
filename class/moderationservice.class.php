<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/auditservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/report.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');

/**
 * Moderation actions with explicit targets and auditability.
 */
class EmergencyHouseModerationService
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Apply a dictionary-backed moderation action.
	 *
	 * @param int         $entity Entity
	 * @param int         $reportId Report ID
	 * @param int         $actionId Dictionary action ID
	 * @param string      $targetType Target type
	 * @param int         $targetId Target ID
	 * @param User        $user Operator
	 * @param string|null $reasonCode Reason code
	 * @param string|null $encryptedNote Encrypted private note
	 * @param int|null    $dateEnd End
	 * @return int
	 */
	public function apply($entity, $reportId, $actionId, $targetType, $targetId, $user, $reasonCode = null, $encryptedNote = null, $dateEnd = null)
	{
		$operatorId = is_object($user) ? (int) $user->id : 0;
		if (!in_array($targetType, array('account', 'offer', 'request', 'solicitation', 'allocation', 'message'), true)
			|| $reportId <= 0 || $actionId <= 0 || $targetId <= 0 || $operatorId <= 0) {
			$this->error = 'ErrorInvalidModerationAction';
			return -1;
		}
		$report = new EmergencyHouseReport($this->db);
		if ($report->fetch($reportId) <= 0
			|| (int) $report->entity !== (int) $entity
			|| $report->object_type !== $targetType
			|| (int) $report->fk_object !== (int) $targetId) {
			$this->error = 'ErrorInvalidModerationTarget';
			return -1;
		}
		$sqlAction = 'SELECT code FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_moderation_action';
		$sqlAction .= ' WHERE rowid = '.((int) $actionId);
		$sqlAction .= ' AND entity = '.((int) $entity).' AND active = 1';
		$resqlAction = $this->db->query($sqlAction);
		$actionObject = $resqlAction ? $this->db->fetch_object($resqlAction) : false;
		$actionCode = is_object($actionObject) ? (string) $actionObject->code : '';
		if (!in_array($actionCode, array('warn', 'hide', 'suspend', 'close'), true)) {
			$this->error = $resqlAction ? 'ErrorInvalidModerationAction' : $this->db->lasterror();
			return -1;
		}

		$this->db->begin();
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_moderation_action';
		$sql .= ' (entity, fk_report, fk_moderation_action, target_type, fk_target, fk_operator, reason_code, private_note_encrypted, date_start, date_end)';
		$sql .= ' VALUES ('.((int) $entity).', '.((int) $reportId).', '.((int) $actionId).',';
		$sql .= " '".$this->db->escape($targetType)."', ".((int) $targetId).', '.((int) $operatorId).',';
		$sql .= $reasonCode === null ? ' NULL,' : " '".$this->db->escape($reasonCode)."',";
		$sql .= $encryptedNote === null ? ' NULL,' : " '".$this->db->escape($encryptedNote)."',";
		$sql .= " '".$this->db->idate(dol_now())."',";
		$sql .= $dateEnd === null ? ' NULL)' : " '".$this->db->idate($dateEnd)."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$moderationId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_moderation_action');

		if (in_array($actionCode, array('hide', 'suspend'), true)
			&& !$this->moderateTarget($entity, $targetType, $targetId, $actionCode, $user)) {
			$this->db->rollback();
			return -1;
		}
		$report->fk_assigned_user = $operatorId;
		$report->status = $actionCode === 'warn'
			? EmergencyHouseReport::STATUS_IN_REVIEW
			: EmergencyHouseReport::STATUS_RESOLVED;
		$report->date_closure = $report->status === EmergencyHouseReport::STATUS_RESOLVED ? dol_now() : null;
		$report->context['trigger_reason'] = 'moderation_action';
		$report->context['moderation_action'] = $actionCode;
		$report->context['changed_fields'] = array('status', 'fk_assigned_user');
		if ($report->updateInsideServiceTransaction($user) <= 0) {
			$this->error = $report->error;
			$this->db->rollback();
			return -1;
		}
		$audit = new EmergencyHouseAuditService($this->db);
		if ($audit->record(
			$entity,
			'dolibarr_user',
			$operatorId,
			'EMERGENCYHOUSE_MODERATION_'.strtoupper($actionCode),
			$targetType,
			$targetId,
			(int) $report->fk_campaign,
			$reasonCode,
			null,
			array('report_id' => $reportId, 'moderation_action_id' => $moderationId)
		) <= 0) {
			$this->error = $audit->error;
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return $moderationId;
	}

	/**
	 * Apply the target-side effect associated with a moderation action.
	 *
	 * @param int    $entity Entity
	 * @param string $targetType Type
	 * @param int    $targetId ID
	 * @param string $actionCode Moderation action
	 * @param User   $user Operator
	 * @return bool
	 */
	private function moderateTarget($entity, $targetType, $targetId, $actionCode, $user)
	{
		if ($targetType === 'account' || $targetType === 'message') {
			$table = $targetType === 'account' ? 'emergencyhouse_public_account' : 'emergencyhouse_message';
			$field = $targetType === 'account' ? 'status' : 'moderation_status';
			$value = $targetType === 'account' ? 2 : ($actionCode === 'hide' ? 1 : 2);
			$sql = 'UPDATE '.MAIN_DB_PREFIX.$table.' SET '.$field.' = '.((int) $value);
			$sql .= ' WHERE rowid = '.((int) $targetId).' AND entity = '.((int) $entity);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return false;
			}
			return true;
		}

		if ($targetType === 'offer') {
			$object = new EmergencyHouseOffer($this->db);
			$newStatus = EmergencyHouseOffer::STATUS_SUSPENDED;
		} elseif ($targetType === 'request') {
			$object = new EmergencyHouseRequest($this->db);
			$newStatus = EmergencyHouseRequest::STATUS_SUSPENDED;
		} elseif ($targetType === 'solicitation') {
			$object = new EmergencyHouseSolicitation($this->db);
			$newStatus = EmergencyHouseSolicitation::STATUS_CANCELLED;
		} else {
			$object = new EmergencyHouseAllocation($this->db);
			$newStatus = EmergencyHouseAllocation::STATUS_INCIDENT;
		}
		if ($object->fetch($targetId) <= 0 || (int) $object->entity !== (int) $entity) {
			$this->error = $object->error ?: 'ErrorRecordNotFound';
			return false;
		}
		$oldStatus = (int) $object->status;
		$object->status = $newStatus;
		if ($object instanceof EmergencyHouseSolicitation) {
			$object->cancellation_reason = 'moderation';
		}
		if ($object instanceof EmergencyHouseAllocation) {
			$object->incident_open = 1;
		}
		$object->context['trigger_reason'] = 'moderation_'.$actionCode;
		$object->context['changed_fields'] = array('status');
		$object->context['old_status'] = $oldStatus;
		$object->context['new_status'] = $newStatus;
		if ($object->updateInsideServiceTransaction($user) <= 0) {
			$this->error = $object->error;
			return false;
		}
		return true;
	}
}
