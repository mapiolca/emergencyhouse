<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/publicaccount.class.php');

/**
 * Conservative retention and anonymization.
 */
class EmergencyHouseRetentionService
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
	 * Apply technical and personal-data retention for one entity.
	 *
	 * @param int $entity Entity
	 * @return int Number of affected rows
	 */
	public function apply($entity)
	{
		$affected = 0;
		$sessionCutoff = dol_time_plus_duree(dol_now(), -max(1, getDolGlobalInt('EMERGENCYHOUSE_RETENTION_SESSION_DAYS', 7)), 'd');
		$tokenCutoff = dol_time_plus_duree(dol_now(), -max(1, getDolGlobalInt('EMERGENCYHOUSE_RETENTION_TOKEN_DAYS', 7)), 'd');
		$rateCutoff = dol_time_plus_duree(dol_now(), -7, 'd');

		$queries = array(
			'DELETE FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_session WHERE entity = '.((int) $entity)
				." AND expires_at < '".$this->db->idate($sessionCutoff)."'",
			'DELETE FROM '.MAIN_DB_PREFIX.'emergencyhouse_token WHERE entity = '.((int) $entity)
				." AND expires_at < '".$this->db->idate($tokenCutoff)."'",
			'DELETE FROM '.MAIN_DB_PREFIX.'emergencyhouse_rate_limit WHERE entity = '.((int) $entity)
				." AND window_start < '".$this->db->idate($rateCutoff)."'",
			'DELETE FROM '.MAIN_DB_PREFIX.'emergencyhouse_geo_cache WHERE entity = '.((int) $entity)
				." AND expires_at < '".$this->db->idate(dol_now())."'",
		);
		foreach ($queries as $sql) {
			$result = $this->db->query($sql);
			if (!$result) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			$affected += max(0, (int) $this->db->affected_rows($result));
		}

		$accountDays = max(1, getDolGlobalInt('EMERGENCYHOUSE_RETENTION_ACCOUNT_DAYS', 90));
		$accountCutoff = dol_time_plus_duree(dol_now(), -$accountDays, 'd');
		$sql = 'SELECT a.rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account AS a';
		$sql .= ' WHERE a.entity = '.((int) $entity);
		$sql .= ' AND a.status IN ('.EmergencyHousePublicAccount::STATUS_PENDING.','.EmergencyHousePublicAccount::STATUS_ACTIVE.','.EmergencyHousePublicAccount::STATUS_SUSPENDED.')';
		$sql .= " AND ((a.date_deletion_requested IS NOT NULL AND a.date_deletion_requested < '".$this->db->idate($accountCutoff)."')";
		$sql .= " OR (a.last_activity IS NOT NULL AND a.last_activity < '".$this->db->idate($accountCutoff)."'))";
		$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer o';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_allocation al ON al.fk_offer = o.rowid AND al.entity = o.entity';
		$sql .= ' WHERE o.fk_account = a.rowid AND o.entity = a.entity AND al.status IN (0,1,2,5))';
		$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'emergencyhouse_request r';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_allocation al2 ON al2.fk_request = r.rowid AND al2.entity = r.entity';
		$sql .= ' WHERE r.fk_account = a.rowid AND r.entity = a.entity AND al2.status IN (0,1,2,5))';
		$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'emergencyhouse_report rp';
		$sql .= ' WHERE rp.entity = a.entity AND rp.retention_hold = 1';
		$sql .= ' AND ((rp.fk_reporter_account = a.rowid) OR (rp.object_type = \'account\' AND rp.fk_object = a.rowid)))';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$account = new EmergencyHousePublicAccount($this->db);
			if ($account->fetch((int) $obj->rowid) > 0 && $account->anonymize() > 0) {
				$affected++;
			}
		}
		return $affected;
	}
}
