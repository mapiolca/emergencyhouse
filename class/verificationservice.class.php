<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/offerphotoservice.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');

/**
 * Operator verification ledger.
 */
class EmergencyHouseVerificationService
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
	 * Record a verification decision.
	 *
	 * @param int         $entity Entity
	 * @param string      $objectType Object type
	 * @param int         $objectId Object ID
	 * @param string      $verificationType Verification type
	 * @param int         $levelId Level dictionary ID
	 * @param int         $status Status
	 * @param string      $methodCode Method code
	 * @param User        $user Operator
	 * @param string|null $encryptedNote Encrypted note
	 * @param int|null    $expiration Expiration
	 * @return int
	 */
	public function record($entity, $objectType, $objectId, $verificationType, $levelId, $status, $methodCode, $user, $encryptedNote = null, $expiration = null)
	{
		$operatorId = is_object($user) ? (int) $user->id : 0;
		if (!in_array($objectType, array('account', 'offer', 'request'), true)
			|| $objectId <= 0
			|| $levelId <= 0
			|| $operatorId <= 0
			|| !in_array($status, array(0, 1, 2), true)
			|| !preg_match('/^[a-z0-9_]{1,64}$/', $verificationType)
			|| !preg_match('/^[a-z0-9_]{1,64}$/', $methodCode)) {
			$this->error = 'ErrorInvalidVerification';
			return -1;
		}
		$levelSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_verification_level';
		$levelSql .= ' WHERE rowid = '.((int) $levelId).' AND entity = '.((int) $entity).' AND active = 1';
		$levelResult = $this->db->query($levelSql);
		if (!$levelResult || $this->db->num_rows($levelResult) === 0) {
			$this->error = $levelResult ? 'ErrorInvalidVerificationLevel' : $this->db->lasterror();
			return -1;
		}

		$linkedObject = null;
		if ($objectType === 'offer') {
			$linkedObject = new EmergencyHouseOffer($this->db);
		} elseif ($objectType === 'request') {
			$linkedObject = new EmergencyHouseRequest($this->db);
		}
		if (is_object($linkedObject)
			&& ($linkedObject->fetch($objectId) <= 0 || (int) $linkedObject->entity !== (int) $entity)) {
			$this->error = !empty($linkedObject->error) ? $linkedObject->error : 'ErrorRecordNotFound';
			return -1;
		}
		if ($objectType === 'account') {
			$accountSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
			$accountSql .= ' WHERE rowid = '.((int) $objectId).' AND entity = '.((int) $entity);
			$accountResult = $this->db->query($accountSql);
			if (!$accountResult || $this->db->num_rows($accountResult) === 0) {
				$this->error = $accountResult ? 'ErrorRecordNotFound' : $this->db->lasterror();
				return -1;
			}
		}

		$this->db->begin();
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_verification';
		$sql .= ' (entity, object_type, fk_object, verification_type, fk_verification_level, status, method_code, private_note_encrypted, fk_operator, date_creation, date_verified, date_expiration)';
		$sql .= ' VALUES ('.((int) $entity).", '".$this->db->escape($objectType)."', ".((int) $objectId).',';
		$sql .= " '".$this->db->escape($verificationType)."', ".((int) $levelId).', '.((int) $status).',';
		$sql .= " '".$this->db->escape($methodCode)."',";
		$sql .= $encryptedNote === null ? ' NULL,' : " '".$this->db->escape($encryptedNote)."',";
		$sql .= ' '.((int) $operatorId).", '".$this->db->idate(dol_now())."',";
		$sql .= $status > 0 ? " '".$this->db->idate(dol_now())."'," : ' NULL,';
		$sql .= $expiration === null ? ' NULL)' : " '".$this->db->idate($expiration)."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$verificationId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_verification');
		if ($objectType === 'offer') {
			/** @var EmergencyHouseOffer $linkedObject */
			$linkedObject->verification_status = $status;
			$linkedObject->context['trigger_reason'] = 'verification_change';
			$linkedObject->context['changed_fields'] = array('verification_status', 'photos');
			if ($linkedObject->updateInsideServiceTransaction($user) <= 0) {
				$this->error = $linkedObject->error;
				$this->db->rollback();
				return -1;
			}
			$photoService = new EmergencyHouseOfferPhotoService($this->db);
			if (!$photoService->updateStatuses($linkedObject, $status)) {
				$this->error = $photoService->error;
				$this->db->rollback();
				return -1;
			}
		}
		if ($objectType === 'request') {
			/** @var EmergencyHouseRequest $linkedObject */
			$linkedObject->verification_status = $status;
			$linkedObject->context['trigger_reason'] = 'verification_change';
			$linkedObject->context['changed_fields'] = array('verification_status');
			if ($linkedObject->updateInsideServiceTransaction($user) <= 0) {
				$this->error = $linkedObject->error;
				$this->db->rollback();
				return -1;
			}
		}
		if ($objectType === 'account') {
			$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
			$sqlUpdate .= ' SET manual_verification_level = '.($status === 1 ? (int) $levelId : 0);
			$sqlUpdate .= ' WHERE rowid = '.((int) $objectId).' AND entity = '.((int) $entity);
			if (!$this->db->query($sqlUpdate)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		}
		$this->db->commit();
		return $verificationId;
	}
}
