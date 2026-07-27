<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/encryptionservice.class.php');

/**
 * Database-backed fixed-window rate limiter.
 */
class EmergencyHouseRateLimitService
{
	/** @var DoliDB */
	private $db;
	/** @var EmergencyHouseEncryptionService */
	private $encryption;
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
		$this->encryption = new EmergencyHouseEncryptionService();
	}

	/**
	 * Consume one hit.
	 *
	 * @param int    $entity Entity
	 * @param string $scope Scope
	 * @param string $identity Raw identity
	 * @param int    $limit Maximum hits
	 * @param int    $windowSeconds Window size
	 * @param int    $blockSeconds Block duration after overflow
	 * @return bool
	 */
	public function consume($entity, $scope, $identity, $limit, $windowSeconds, $blockSeconds = 0)
	{
		if ($limit <= 0 || $windowSeconds <= 0) {
			$this->error = 'ErrorInvalidRateLimit';
			return false;
		}
		$keyHash = $this->encryption->hashLookup($identity, 'rate-limit|'.$scope);
		if (!is_string($keyHash)) {
			$this->error = $this->encryption->error;
			return false;
		}
		$now = dol_now();
		$windowStart = $now - ($now % $windowSeconds);

		$this->db->begin();
		$sql = 'SELECT rowid, hit_count, blocked_until FROM '.MAIN_DB_PREFIX.'emergencyhouse_rate_limit';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= " AND key_hash = '".$this->db->escape($keyHash)."'";
		$sql .= " AND scope = '".$this->db->escape($scope)."'";
		$sql .= " AND window_start = '".$this->db->idate($windowStart)."' FOR UPDATE";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (is_object($obj)) {
			$blockedUntil = empty($obj->blocked_until) ? null : $this->db->jdate($obj->blocked_until);
			if ($blockedUntil !== null && $blockedUntil > $now) {
				$this->db->rollback();
				$this->error = 'ErrorRateLimitExceeded';
				return false;
			}
			$newCount = (int) $obj->hit_count + 1;
			$newBlockedUntil = $newCount > $limit && $blockSeconds > 0 ? $now + $blockSeconds : null;
			$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_rate_limit SET hit_count = '.$newCount;
			$sqlUpdate .= ', blocked_until = '.($newBlockedUntil === null ? 'NULL' : "'".$this->db->idate($newBlockedUntil)."'");
			$sqlUpdate .= ' WHERE rowid = '.((int) $obj->rowid);
			if (!$this->db->query($sqlUpdate)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return false;
			}
		} else {
			$sqlInsert = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_rate_limit';
			$sqlInsert .= ' (entity, key_hash, scope, window_start, window_seconds, hit_count, blocked_until)';
			$sqlInsert .= ' VALUES ('.((int) $entity).", '".$this->db->escape($keyHash)."', '".$this->db->escape($scope)."',";
			$sqlInsert .= " '".$this->db->idate($windowStart)."', ".((int) $windowSeconds).', 1, NULL)';
			if (!$this->db->query($sqlInsert)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return false;
			}
			$newCount = 1;
		}
		if ($newCount > $limit) {
			$this->db->commit();
			$this->error = 'ErrorRateLimitExceeded';
			return false;
		}
		$this->db->commit();
		return true;
	}
}

