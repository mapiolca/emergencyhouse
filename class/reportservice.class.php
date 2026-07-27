<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');
dol_include_once('/emergencyhouse/class/report.class.php');

/**
 * Secure public report boundary.
 */
class EmergencyHouseReportService
{
	/** @var DoliDB */
	private $db;
	/** @var EmergencyHouseEncryptionService */
	private $encryption;
	/** @var string */
	public $error = '';
	/** @var array<int, string> */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->encryption = new EmergencyHouseEncryptionService();
	}

	/**
	 * Return active public report reasons.
	 *
	 * @param int $entity Entity
	 * @return array<int, string>|false Translation key by row ID
	 */
	public function fetchReasons($entity)
	{
		$sql = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_report_reason';
		$sql .= ' WHERE entity = '.((int) $entity).' AND active = 1';
		$sql .= ' ORDER BY position ASC, label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$reasons = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$reasons[(int) $obj->rowid] = (string) $obj->label;
		}
		return $reasons;
	}

	/**
	 * Resolve a reportable target visible to the public account.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param string $objectType Target type
	 * @param int $objectId Target ID
	 * @return array{campaign_id:int,label:string}|false
	 */
	public function resolveTarget($account, $objectType, $objectId)
	{
		if ($objectId <= 0 || !in_array($objectType, array('offer', 'request', 'solicitation', 'allocation', 'message'), true)) {
			$this->error = 'ErrorInvalidReportTarget';
			return false;
		}

		if ($objectType === 'offer') {
			$sql = 'SELECT fk_campaign, title AS target_label FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer';
			$sql .= ' WHERE rowid = '.((int) $objectId).' AND entity = '.((int) $account->entity);
			$sql .= ' AND (fk_account = '.((int) $account->id).' OR status = 2)';
		} elseif ($objectType === 'request') {
			$sql = 'SELECT fk_campaign, title AS target_label FROM '.MAIN_DB_PREFIX.'emergencyhouse_request';
			$sql .= ' WHERE rowid = '.((int) $objectId).' AND entity = '.((int) $account->entity);
			$sql .= " AND (fk_account = ".((int) $account->id)." OR (status IN (1, 2) AND visibility = 'public'))";
		} elseif ($objectType === 'solicitation') {
			$sql = 'SELECT s.fk_campaign, s.ref AS target_label';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation AS s';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = s.fk_offer AND o.entity = s.entity';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = s.fk_request AND r.entity = s.entity';
			$sql .= ' WHERE s.rowid = '.((int) $objectId).' AND s.entity = '.((int) $account->entity);
			$sql .= ' AND (o.fk_account = '.((int) $account->id).' OR r.fk_account = '.((int) $account->id).')';
		} elseif ($objectType === 'allocation') {
			$sql = 'SELECT a.fk_campaign, a.ref AS target_label';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_allocation AS a';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = a.fk_offer AND o.entity = a.entity';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = a.fk_request AND r.entity = a.entity';
			$sql .= ' WHERE a.rowid = '.((int) $objectId).' AND a.entity = '.((int) $account->entity);
			$sql .= ' AND (o.fk_account = '.((int) $account->id).' OR r.fk_account = '.((int) $account->id).')';
		} else {
			$sql = 'SELECT s.fk_campaign, s.ref AS target_label';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_message AS m';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_solicitation AS s ON s.rowid = m.fk_solicitation AND s.entity = m.entity';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = s.fk_offer AND o.entity = s.entity';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = s.fk_request AND r.entity = s.entity';
			$sql .= ' WHERE m.rowid = '.((int) $objectId).' AND m.entity = '.((int) $account->entity);
			$sql .= ' AND m.date_deleted IS NULL';
			$sql .= ' AND (o.fk_account = '.((int) $account->id).' OR r.fk_account = '.((int) $account->id).')';
		}

		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($obj)) {
			$this->error = $resql ? 'ErrorRecordNotFound' : $this->db->lasterror();
			return false;
		}
		return array(
			'campaign_id' => (int) $obj->fk_campaign,
			'label' => (string) $obj->target_label,
		);
	}

	/**
	 * Create an encrypted public report.
	 *
	 * @param EmergencyHousePublicAccount $account Reporter
	 * @param string $objectType Target type
	 * @param int $objectId Target ID
	 * @param int $reasonId Dictionary reason
	 * @param int $severity Severity 1..5
	 * @param string $description Description
	 * @param User $triggerUser Trigger user
	 * @return EmergencyHouseReport|false
	 */
	public function createPublicReport($account, $objectType, $objectId, $reasonId, $severity, $description, $triggerUser)
	{
		$target = $this->resolveTarget($account, $objectType, $objectId);
		if (!is_array($target)) {
			return false;
		}
		if (!$this->reasonExists((int) $account->entity, $reasonId)) {
			$this->error = 'ErrorInvalidReportReason';
			return false;
		}
		$description = trim($description);
		if (dol_strlen($description) < 20 || dol_strlen($description) > 5000) {
			$this->error = 'ErrorInvalidReportDescription';
			return false;
		}
		if ($severity < 1 || $severity > 5) {
			$this->error = 'ErrorInvalidSeverity';
			return false;
		}

		$report = new EmergencyHouseReport($this->db);
		$report->entity = (int) $account->entity;
		$report->public_uuid = bin2hex(random_bytes(16));
		$report->fk_campaign = $target['campaign_id'];
		$report->object_type = $objectType;
		$report->fk_object = $objectId;
		$report->fk_reporter_account = (int) $account->id;
		$report->fk_report_reason = $reasonId;
		$report->severity = $severity;
		$report->status = EmergencyHouseReport::STATUS_OPEN;
		$report->context['public_account_id'] = (int) $account->id;
		$report->context['trigger_reason'] = 'public_safety_report';
		$encrypted = $this->encryption->encrypt(
			$description,
			'emergencyhouse|report|'.$report->entity.'|'.$report->public_uuid.'|description'
		);
		if (!is_string($encrypted)) {
			$this->error = $this->encryption->error;
			return false;
		}
		$report->description_encrypted = $encrypted;
		$result = $report->create($triggerUser);
		if ($result <= 0) {
			$this->error = $report->error;
			$this->errors = $report->errors;
			return false;
		}
		return $report;
	}

	/**
	 * Verify an active reason.
	 *
	 * @param int $entity Entity
	 * @param int $reasonId Reason ID
	 * @return bool
	 */
	private function reasonExists($entity, $reasonId)
	{
		if ($reasonId <= 0) {
			return false;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_report_reason';
		$sql .= ' WHERE rowid = '.((int) $reasonId).' AND entity = '.((int) $entity).' AND active = 1';
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) === 1;
	}
}
