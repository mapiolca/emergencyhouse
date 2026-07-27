<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');

/**
 * Encrypted, bounded conversation service.
 */
class EmergencyHouseMessageService
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
	 * Add a message after checking thread membership.
	 *
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @param int|null                   $authorAccount Public account
	 * @param int|null                   $authorUser Dolibarr user
	 * @param string                     $body Message body
	 * @return int
	 */
	public function createMessage($solicitation, $authorAccount, $authorUser, $body)
	{
		$body = trim($body);
		$maxLength = max(200, getDolGlobalInt('EMERGENCYHOUSE_MESSAGE_MAX_LENGTH', 4000));
		if ($body === '' || dol_strlen($body) > $maxLength) {
			$this->error = 'ErrorInvalidMessageLength';
			return -1;
		}
		if (!in_array((int) $solicitation->status, array(
			EmergencyHouseSolicitation::STATUS_PENDING,
			EmergencyHouseSolicitation::STATUS_ACCEPTED,
		), true)) {
			$this->error = 'ErrorSolicitationClosed';
			return -1;
		}
		if ($authorAccount === null && $authorUser === null) {
			$this->error = 'ErrorMessageAuthorRequired';
			return -1;
		}
		if ($authorAccount !== null && !$this->accountBelongsToThread((int) $solicitation->entity, (int) $solicitation->id, $authorAccount)) {
			$this->error = 'ErrorForbidden';
			return -1;
		}

		$uuid = bin2hex(random_bytes(16));
		$context = 'emergencyhouse|message|'.$solicitation->entity.'|'.$uuid;
		$encrypted = $this->encryption->encrypt($body, $context);
		$fingerprint = $this->encryption->hashLookup($body, 'message-duplicate|'.$solicitation->id);
		if (!is_string($encrypted) || !is_string($fingerprint)) {
			$this->error = $this->encryption->error;
			return -1;
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_message';
		$sql .= ' (entity, public_uuid, fk_solicitation, fk_author_account, fk_author_user, message_type, body_encrypted, body_fingerprint, moderation_status, date_creation)';
		$sql .= ' VALUES ('.((int) $solicitation->entity).", '".$this->db->escape($uuid)."', ".((int) $solicitation->id).', ';
		$sql .= $authorAccount === null ? 'NULL, ' : ((int) $authorAccount).', ';
		$sql .= $authorUser === null ? 'NULL, ' : ((int) $authorUser).', ';
		$sql .= "'user', '".$this->db->escape($encrypted)."', '".$this->db->escape($fingerprint)."', 0,";
		$sql .= " '".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_message');
	}

	/**
	 * Fetch decrypted messages for a permitted participant or operator.
	 *
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @param int|null                   $accountId Public account
	 * @param bool                       $operatorAccess Operator has module permission
	 * @param int                        $limit Maximum messages
	 * @return array<int, array<string, int|string|null>>|false
	 */
	public function fetchMessages($solicitation, $accountId, $operatorAccess, $limit = 100)
	{
		if (!$operatorAccess && ($accountId === null || !$this->accountBelongsToThread((int) $solicitation->entity, (int) $solicitation->id, $accountId))) {
			$this->error = 'ErrorForbidden';
			return false;
		}
		$sql = 'SELECT rowid, public_uuid, fk_author_account, fk_author_user, message_type, body_encrypted, moderation_status, date_creation, date_read';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_message';
		$sql .= ' WHERE entity = '.((int) $solicitation->entity);
		$sql .= ' AND fk_solicitation = '.((int) $solicitation->id);
		$sql .= ' AND date_deleted IS NULL';
		$sql .= ' ORDER BY date_creation ASC';
		$sql .= $this->db->plimit(min(500, max(1, $limit)));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$messages = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$context = 'emergencyhouse|message|'.$solicitation->entity.'|'.$obj->public_uuid;
			$body = $this->encryption->decrypt((string) $obj->body_encrypted, $context);
			if (!is_string($body)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$messages[] = array(
				'id' => (int) $obj->rowid,
				'public_uuid' => (string) $obj->public_uuid,
				'fk_author_account' => empty($obj->fk_author_account) ? null : (int) $obj->fk_author_account,
				'fk_author_user' => empty($obj->fk_author_user) ? null : (int) $obj->fk_author_user,
				'message_type' => (string) $obj->message_type,
				'body' => $body,
				'moderation_status' => (int) $obj->moderation_status,
				'date_creation' => $this->db->jdate($obj->date_creation),
				'date_read' => empty($obj->date_read) ? null : $this->db->jdate($obj->date_read),
			);
		}
		return $messages;
	}

	/**
	 * Determine whether a public account owns either side of the thread.
	 *
	 * @param int $entity Entity
	 * @param int $solicitationId Solicitation ID
	 * @param int $accountId Account ID
	 * @return bool
	 */
	private function accountBelongsToThread($entity, $solicitationId, $accountId)
	{
		$sql = 'SELECT s.rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation AS s';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = s.fk_offer AND o.entity = s.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = s.fk_request AND r.entity = s.entity';
		$sql .= ' WHERE s.rowid = '.((int) $solicitationId).' AND s.entity = '.((int) $entity);
		$sql .= ' AND (o.fk_account = '.((int) $accountId).' OR r.fk_account = '.((int) $accountId).')';
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) > 0;
	}
}

