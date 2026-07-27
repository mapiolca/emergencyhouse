<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Versioned consent ledger.
 */
class EmergencyHouseConsentService
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
	 * Record or withdraw consent idempotently.
	 *
	 * @param int         $entity Entity
	 * @param int         $accountId Account ID
	 * @param int|null    $campaignId Campaign ID
	 * @param string      $type Consent type
	 * @param string      $version Consent version
	 * @param bool        $granted Granted
	 * @param string|null $proofHash Proof hash
	 * @return int
	 */
	public function setConsent($entity, $accountId, $campaignId, $type, $version, $granted, $proofHash = null)
	{
		$now = dol_now();
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_consent';
		$sql .= ' (entity, fk_account, fk_campaign, consent_type, consent_version, is_granted, proof_hash, date_granted, date_withdrawn, date_creation)';
		$sql .= ' VALUES ('.((int) $entity).', '.((int) $accountId).', ';
		$sql .= $campaignId === null ? 'NULL, ' : ((int) $campaignId).', ';
		$sql .= "'".$this->db->escape($type)."', '".$this->db->escape($version)."', ".($granted ? '1' : '0').', ';
		$sql .= $proofHash === null ? 'NULL, ' : "'".$this->db->escape($proofHash)."', ";
		$sql .= $granted ? "'".$this->db->idate($now)."', NULL, " : "NULL, '".$this->db->idate($now)."', ";
		$sql .= "'".$this->db->idate($now)."')";
		$sql .= ' ON DUPLICATE KEY UPDATE';
		$sql .= ' is_granted = VALUES(is_granted), proof_hash = VALUES(proof_hash),';
		$sql .= ' date_granted = VALUES(date_granted), date_withdrawn = VALUES(date_withdrawn)';

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Check current consent.
	 *
	 * @param int      $entity Entity
	 * @param int      $accountId Account ID
	 * @param int|null $campaignId Campaign ID
	 * @param string   $type Consent type
	 * @param string   $version Consent version
	 * @return bool
	 */
	public function isGranted($entity, $accountId, $campaignId, $type, $version)
	{
		$sql = 'SELECT is_granted FROM '.MAIN_DB_PREFIX.'emergencyhouse_consent';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_account = '.((int) $accountId);
		$sql .= $campaignId === null ? ' AND fk_campaign IS NULL' : ' AND fk_campaign = '.((int) $campaignId);
		$sql .= " AND consent_type = '".$this->db->escape($type)."'";
		$sql .= " AND consent_version = '".$this->db->escape($version)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return is_object($obj) && !empty($obj->is_granted);
	}
}

