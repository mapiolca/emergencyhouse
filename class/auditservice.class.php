<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Append-only audit trail for security-sensitive operations.
 */
class EmergencyHouseAuditService
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
	 * Append an audit event.
	 *
	 * @param int                       $entity Entity
	 * @param string                    $actorType Actor type
	 * @param int|null                  $actorId Actor ID
	 * @param string                    $actionCode Stable action code
	 * @param string                    $objectType Object type
	 * @param int                       $objectId Object ID
	 * @param int|null                  $campaignId Campaign ID
	 * @param string|null               $justification Justification code
	 * @param string|null               $ipHash Hashed source address
	 * @param array<string, scalar|null> $metadata Non-sensitive metadata
	 * @return int
	 */
	public function record($entity, $actorType, $actorId, $actionCode, $objectType, $objectId, $campaignId = null, $justification = null, $ipHash = null, array $metadata = array())
	{
		$metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($metadataJson)) {
			$metadataJson = '{}';
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_audit (';
		$sql .= 'entity, fk_campaign, actor_type, fk_actor, action_code, object_type, fk_object, justification_code, ip_hash, metadata_snapshot, date_creation';
		$sql .= ') VALUES (';
		$sql .= ((int) $entity).',';
		$sql .= $campaignId === null ? 'NULL,' : ((int) $campaignId).',';
		$sql .= "'".$this->db->escape($actorType)."',";
		$sql .= $actorId === null ? 'NULL,' : ((int) $actorId).',';
		$sql .= "'".$this->db->escape($actionCode)."',";
		$sql .= "'".$this->db->escape($objectType)."',";
		$sql .= ((int) $objectId).',';
		$sql .= $justification === null ? 'NULL,' : "'".$this->db->escape($justification)."',";
		$sql .= $ipHash === null ? 'NULL,' : "'".$this->db->escape($ipHash)."',";
		$sql .= "'".$this->db->escape($metadataJson)."',";
		$sql .= "'".$this->db->idate(dol_now())."')";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_audit');
	}
}

