<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/offerphotoservice.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

/**
 * Unified FIFO queue and operator verification ledger.
 */
class EmergencyHouseVerificationService
{
	public const STATUS_PENDING = 0;
	public const STATUS_VERIFIED = 1;
	public const STATUS_REFUSED = 2;

	public const QUEUE_PENDING = 0;
	public const QUEUE_COMPLETED = 1;
	public const QUEUE_CANCELLED = 2;
	public const METHOD_EMAIL_CONFIRMATION = 'email_confirmation';

	/** @var array<int, string> */
	private const VERIFICATION_TYPES = array('identity', 'email', 'phone', 'address', 'housing');
	/** @var array<int, string> */
	private const METHOD_CODES = array('document', 'phone_call', 'video_call', 'onsite', 'operator_review');

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
	 * Create or reactivate a queue item and immediately assign it in rotation.
	 *
	 * @param int      $entity            Entity
	 * @param string   $objectType        account, offer or request
	 * @param int      $objectId          Object ID
	 * @param int|null $dateQueued        Submission timestamp
	 * @param bool     $manageTransaction Whether this method owns the transaction
	 * @return int Queue ID, 0 when the target is not eligible, -1 on error
	 */
	public function enqueueTarget($entity, $objectType, $objectId, $dateQueued = null, $manageTransaction = true)
	{
		$this->error = '';
		if (!$this->isValidTarget($entity, $objectType, $objectId)) {
			$this->error = 'ErrorInvalidVerificationTarget';
			return -1;
		}
		if ($manageTransaction) {
			$this->db->begin();
		}
		if (!$this->lockSubmissionTarget($entity, $objectType, $objectId)) {
			return $this->rollbackWithError($manageTransaction);
		}
		if (!$this->targetIsEligible($entity, $objectType, $objectId, true)) {
			if ($this->error !== '') {
				return $this->rollbackWithError($manageTransaction);
			}
			if ($manageTransaction) {
				$this->db->commit();
			}
			return 0;
		}

		$queuedAt = $dateQueued === null ? dol_now() : (int) $dateQueued;
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue';
		$sql .= ' (entity, object_type, fk_object, queue_status, fk_assigned_user,';
		$sql .= ' date_queued, date_assigned, date_completed, fk_verification)';
		$sql .= ' VALUES ('.((int) $entity).", '".$this->db->escape($objectType)."', ".((int) $objectId).',';
		$sql .= ' '.self::QUEUE_PENDING.", NULL, '".$this->db->idate($queuedAt)."', NULL, NULL, NULL)";
		$sql .= ' ON DUPLICATE KEY UPDATE queue_status = '.self::QUEUE_PENDING.',';
		$sql .= ' fk_assigned_user = NULL, date_queued = VALUES(date_queued),';
		$sql .= ' date_assigned = NULL, date_completed = NULL, fk_verification = NULL';
		if (!$this->db->query($sql)) {
			return $this->rollbackWithError($manageTransaction);
		}

		$queueId = $this->findQueueId($entity, $objectType, $objectId, true);
		if ($queueId <= 0 || !$this->assignQueueItem($queueId, $entity)) {
			return $this->rollbackWithError($manageTransaction);
		}
		if ($manageTransaction) {
			$this->db->commit();
		}

		return $queueId;
	}

	/**
	 * Lock the entity rotation and an existing target queue row before a source mutation.
	 *
	 * The caller must own a transaction. Keeping the order rotation, queue, source
	 * prevents concurrent resubmissions, reconciliation and decisions from taking
	 * the same locks in opposite directions.
	 *
	 * @param int    $entity     Entity
	 * @param string $objectType Object type
	 * @param int    $objectId   Object ID
	 * @return bool
	 */
	public function lockSubmissionTarget($entity, $objectType, $objectId)
	{
		$this->error = '';
		if (!$this->isValidTarget($entity, $objectType, $objectId)) {
			$this->error = 'ErrorInvalidVerificationTarget';
			return false;
		}
		if ($this->lockRotationCursor($entity) === null) {
			return false;
		}

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= " AND object_type = '".$this->db->escape($objectType)."'";
		$sql .= ' AND fk_object = '.((int) $objectId).' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return true;
	}

	/**
	 * Cancel the pending queue row when an object returns to draft.
	 *
	 * @param int    $entity            Entity
	 * @param string $objectType        Object type
	 * @param int    $objectId          Object ID
	 * @param bool   $manageTransaction Whether this method owns the transaction
	 * @return int 1 on success, -1 on error
	 */
	public function cancelTarget($entity, $objectType, $objectId, $manageTransaction = true)
	{
		$this->error = '';
		if (!$this->isValidTarget($entity, $objectType, $objectId)) {
			$this->error = 'ErrorInvalidVerificationTarget';
			return -1;
		}
		if ($manageTransaction) {
			$this->db->begin();
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue';
		$sql .= ' SET queue_status = '.self::QUEUE_CANCELLED.', fk_assigned_user = NULL,';
		$sql .= " date_assigned = NULL, date_completed = '".$this->db->idate(dol_now())."', fk_verification = NULL";
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= " AND object_type = '".$this->db->escape($objectType)."'";
		$sql .= ' AND fk_object = '.((int) $objectId);
		$sql .= ' AND queue_status = '.self::QUEUE_PENDING;
		if (!$this->db->query($sql)) {
			return $this->rollbackWithError($manageTransaction);
		}
		if ($manageTransaction) {
			$this->db->commit();
		}

		return 1;
	}

	/**
	 * Reassign invalid or unassigned pending rows without changing their age.
	 *
	 * @param array<int, int> $entities Entity IDs
	 * @return int Number of changed queue rows, -1 on error
	 */
	public function reconcileAssignments($entities)
	{
		$this->error = '';
		$entities = array_values(array_unique(array_filter(array_map('intval', $entities))));
		$changed = 0;
		foreach ($entities as $entity) {
			if ($entity <= 0) {
				continue;
			}
			$this->db->begin();
			$lastUserId = $this->lockRotationCursor($entity);
			if ($lastUserId === null) {
				return $this->rollbackWithError(true);
			}
			$eligibleUsers = $this->getEligibleUserIds($entity);
			if ($eligibleUsers === null) {
				return $this->rollbackWithError(true);
			}

			$sql = 'SELECT rowid, object_type, fk_object, fk_assigned_user';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue';
			$sql .= ' WHERE entity = '.((int) $entity);
			$sql .= ' AND queue_status = '.self::QUEUE_PENDING;
			$sql .= ' ORDER BY date_queued ASC, rowid ASC FOR UPDATE';
			$resql = $this->db->query($sql);
			if (!$resql) {
				return $this->rollbackWithError(true);
			}
			while (is_object($queue = $this->db->fetch_object($resql))) {
				$queueId = (int) $queue->rowid;
				$objectType = (string) $queue->object_type;
				$objectId = (int) $queue->fk_object;
				$assignedUserId = isset($queue->fk_assigned_user) ? (int) $queue->fk_assigned_user : 0;
				if (!$this->targetIsEligible($entity, $objectType, $objectId, true)) {
					if ($this->error !== '') {
						return $this->rollbackWithError(true);
					}
					if (!$this->setQueueCancelled($queueId)) {
						return $this->rollbackWithError(true);
					}
					$changed++;
					continue;
				}
				if ($assignedUserId > 0 && in_array($assignedUserId, $eligibleUsers, true)) {
					continue;
				}
				if ($assignedUserId <= 0 && empty($eligibleUsers)) {
					continue;
				}
				$nextUserId = self::selectNextUserId($eligibleUsers, $lastUserId);
				if (!$this->setQueueAssignment($queueId, $nextUserId)) {
					return $this->rollbackWithError(true);
				}
				if ($nextUserId > 0) {
					$lastUserId = $nextUserId;
				}
				$changed++;
			}
			if (!$this->setRotationLastUser($entity, $lastUserId)) {
				return $this->rollbackWithError(true);
			}
			$this->db->commit();
		}

		return $changed;
	}

	/**
	 * Return the next user in a stable circular sequence.
	 *
	 * @param array<int, int> $eligibleUsers Sorted or unsorted eligible user IDs
	 * @param int             $lastUserId    Last assigned user
	 * @return int User ID, or 0 when nobody is eligible
	 */
	public static function selectNextUserId($eligibleUsers, $lastUserId)
	{
		$eligibleUsers = array_values(array_unique(array_filter(array_map('intval', $eligibleUsers))));
		sort($eligibleUsers, SORT_NUMERIC);
		if (empty($eligibleUsers)) {
			return 0;
		}
		foreach ($eligibleUsers as $userId) {
			if ($userId > $lastUserId) {
				return $userId;
			}
		}

		return (int) $eligibleUsers[0];
	}

	/**
	 * Record a final decision from a locked queue row.
	 *
	 * @param int         $queueId         Queue ID
	 * @param string      $verificationType Verification type
	 * @param int         $levelId         Verification level
	 * @param int         $status          Verified or refused
	 * @param string      $methodCode      Method
	 * @param User        $user            Operator
	 * @param string|null $encryptedNote   Encrypted private note
	 * @param int|null    $expiration      Expiration timestamp
	 * @return int Verification ID, -1 on error
	 */
	public function recordQueueDecision($queueId, $verificationType, $levelId, $status, $methodCode, $user, $encryptedNote = null, $expiration = null)
	{
		$this->error = '';
		$operatorId = is_object($user) ? (int) $user->id : 0;
		if ($queueId <= 0
			|| $levelId <= 0
			|| $operatorId <= 0
			|| !in_array($status, array(self::STATUS_VERIFIED, self::STATUS_REFUSED), true)
			|| !in_array($verificationType, self::VERIFICATION_TYPES, true)
			|| !in_array($methodCode, self::METHOD_CODES, true)
			|| !emergencyhouseCanDo($user, 'verification', 'write')) {
			$this->error = 'ErrorInvalidVerification';
			return -1;
		}

		$queueSnapshot = $this->fetchQueueItem($queueId);
		if (!is_object($queueSnapshot)) {
			if ($this->error === '') {
				$this->error = 'ErrorVerificationQueueUnavailable';
			}
			return -1;
		}
		if ((int) $queueSnapshot->queue_status !== self::QUEUE_PENDING
			|| !$this->queueEntityIsAccessible((int) $queueSnapshot->entity, (string) $queueSnapshot->object_type)) {
			$this->error = 'ErrorVerificationQueueUnavailable';
			return -1;
		}
		if ($this->reconcileAssignments(array((int) $queueSnapshot->entity)) < 0) {
			return -1;
		}

		$this->db->begin();
		$queue = $this->fetchQueueItem($queueId, true);
		if (!is_object($queue)) {
			if ($this->error === '') {
				$this->error = 'ErrorVerificationQueueUnavailable';
			}
			return $this->rollbackWithError(true);
		}
		$entity = (int) $queue->entity;
		$objectType = (string) $queue->object_type;
		$objectId = (int) $queue->fk_object;
		if ((int) $queue->queue_status !== self::QUEUE_PENDING
			|| !$this->queueEntityIsAccessible($entity, $objectType)) {
			$this->error = 'ErrorVerificationQueueUnavailable';
			return $this->rollbackWithError(true);
		}
		$assignedUserId = isset($queue->fk_assigned_user) ? (int) $queue->fk_assigned_user : 0;
		if (!emergencyhouseUserIsFullAdmin($user) && $assignedUserId !== $operatorId) {
			$this->error = 'ErrorVerificationQueueAssignedToAnotherUser';
			return $this->rollbackWithError(true);
		}
		if (!$this->targetIsEligible($entity, $objectType, $objectId, true)) {
			if ($this->error === '') {
				$this->error = 'ErrorVerificationAlreadyProcessed';
			}
			return $this->rollbackWithError(true);
		}
		if (!$this->verificationLevelIsActive($entity, $levelId)) {
			$this->error = 'ErrorInvalidVerificationLevel';
			return $this->rollbackWithError(true);
		}

		$verificationId = $this->insertVerification(
			$entity,
			$objectType,
			$objectId,
			$verificationType,
			$levelId,
			$status,
			$methodCode,
			$operatorId,
			$encryptedNote,
			$expiration
		);
		if ($verificationId <= 0 || !$this->updateVerifiedTarget($entity, $objectType, $objectId, $levelId, $status, $user)) {
			return $this->rollbackWithError(true);
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue';
		$sql .= ' SET queue_status = '.self::QUEUE_COMPLETED.',';
		$sql .= " date_completed = '".$this->db->idate(dol_now())."',";
		$sql .= ' fk_verification = '.((int) $verificationId);
		$sql .= ' WHERE rowid = '.((int) $queueId);
		$sql .= ' AND queue_status = '.self::QUEUE_PENDING;
		$resql = $this->db->query($sql);
		if (!$resql || (int) $this->db->affected_rows($resql) !== 1) {
			$this->error = $resql ? 'ErrorVerificationAlreadyProcessed' : $this->db->lasterror();
			return $this->rollbackWithError(true);
		}
		$this->db->commit();

		return $verificationId;
	}

	/**
	 * Backward-compatible entry point that still requires an active queue row.
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
		$queueId = $this->findQueueId($entity, $objectType, $objectId, true);
		if ($queueId <= 0) {
			$this->error = 'ErrorVerificationQueueUnavailable';
			return -1;
		}

		return $this->recordQueueDecision(
			$queueId,
			$verificationType,
			$levelId,
			$status,
			$methodCode,
			$user,
			$encryptedNote,
			$expiration
		);
	}

	/**
	 * Record an email confirmation as an automatic, operator-free proof.
	 *
	 * @param int      $entity            Entity
	 * @param int      $accountId         Public account ID
	 * @param int|null $confirmedAt       Confirmation timestamp
	 * @param bool     $manageTransaction Whether this method owns the transaction
	 * @return int Verification ID, -1 on error
	 */
	public function recordEmailConfirmation($entity, $accountId, $confirmedAt = null, $manageTransaction = true)
	{
		$this->error = '';
		if ($entity <= 0 || $accountId <= 0) {
			$this->error = 'ErrorInvalidVerificationTarget';
			return -1;
		}
		$confirmationAt = $confirmedAt !== null && (int) $confirmedAt > 0
			? (int) $confirmedAt
			: dol_now();
		if ($manageTransaction) {
			$this->db->begin();
		}
		if (!$this->lockSubmissionTarget($entity, 'account', $accountId)) {
			return $this->rollbackWithError($manageTransaction);
		}

		$sql = 'SELECT rowid, status FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= ' WHERE rowid = '.((int) $accountId).' AND entity = '.((int) $entity).' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->rollbackWithError($manageTransaction);
		}
		$account = $this->db->fetch_object($resql);
		if (!is_object($account) || !in_array((int) $account->status, array(0, 1), true)) {
			$this->error = 'ErrorInvalidVerificationTarget';
			return $this->rollbackWithError($manageTransaction);
		}

		$levelId = $this->findVerificationLevelIdByCode($entity, 'email');
		if ($levelId <= 0) {
			return $this->rollbackWithError($manageTransaction);
		}
		$verificationId = $this->findEmailConfirmationVerificationId($entity, $accountId);
		if ($verificationId <= 0 && $this->error !== '') {
			return $this->rollbackWithError($manageTransaction);
		}
		if ($verificationId <= 0) {
			$verificationId = $this->insertVerification(
				$entity,
				'account',
				$accountId,
				'email',
				$levelId,
				self::STATUS_VERIFIED,
				self::METHOD_EMAIL_CONFIRMATION,
				null,
				null,
				null,
				$confirmationAt
			);
		}
		if ($verificationId <= 0) {
			return $this->rollbackWithError($manageTransaction);
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= ' SET email_verified = 1, verification_status = '.self::STATUS_VERIFIED.', status = 1';
		$sql .= ' WHERE rowid = '.((int) $accountId).' AND entity = '.((int) $entity);
		if (!$this->db->query($sql)) {
			return $this->rollbackWithError($manageTransaction);
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue';
		$sql .= ' SET queue_status = '.self::QUEUE_COMPLETED.', fk_assigned_user = NULL,';
		$sql .= " date_assigned = NULL, date_completed = '".$this->db->idate($confirmationAt)."',";
		$sql .= ' fk_verification = '.((int) $verificationId);
		$sql .= ' WHERE entity = '.((int) $entity)." AND object_type = 'account'";
		$sql .= ' AND fk_object = '.((int) $accountId).' AND queue_status = '.self::QUEUE_PENDING;
		if (!$this->db->query($sql)) {
			return $this->rollbackWithError($manageTransaction);
		}
		if ($manageTransaction) {
			$this->db->commit();
		}

		return $verificationId;
	}

	/**
	 * Fetch a queue item.
	 *
	 * @param int  $queueId Queue ID
	 * @param bool $forUpdate Lock row
	 * @return stdClass|null
	 */
	public function fetchQueueItem($queueId, $forUpdate = false)
	{
		$sql = 'SELECT queue.rowid, queue.entity, queue.object_type, queue.fk_object,';
		$sql .= ' queue.queue_status, queue.fk_assigned_user, queue.date_queued,';
		$sql .= ' queue.date_assigned, queue.date_completed, queue.fk_verification';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue AS queue';
		$sql .= ' WHERE queue.rowid = '.((int) $queueId);
		if ($forUpdate) {
			$sql .= ' FOR UPDATE';
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$queue = $this->db->fetch_object($resql);

		return is_object($queue) ? $queue : null;
	}

	/**
	 * Find the queue row for a target.
	 *
	 * @param int    $entity      Entity
	 * @param string $objectType  Object type
	 * @param int    $objectId    Object ID
	 * @param bool   $pendingOnly Require pending status
	 * @return int Queue ID or 0
	 */
	public function findQueueId($entity, $objectType, $objectId, $pendingOnly = false)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= " AND object_type = '".$this->db->escape($objectType)."'";
		$sql .= ' AND fk_object = '.((int) $objectId);
		if ($pendingOnly) {
			$sql .= ' AND queue_status = '.self::QUEUE_PENDING;
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return 0;
		}
		$queue = $this->db->fetch_object($resql);

		return is_object($queue) ? (int) $queue->rowid : 0;
	}

	/**
	 * Assign one freshly submitted row while locking the entity rotation.
	 *
	 * @param int $queueId Queue ID
	 * @param int $entity  Entity
	 * @return bool
	 */
	private function assignQueueItem($queueId, $entity)
	{
		$lastUserId = $this->lockRotationCursor($entity);
		if ($lastUserId === null) {
			return false;
		}
		$eligibleUsers = $this->getEligibleUserIds($entity);
		if ($eligibleUsers === null) {
			return false;
		}
		$nextUserId = self::selectNextUserId($eligibleUsers, $lastUserId);
		if (!$this->setQueueAssignment($queueId, $nextUserId)) {
			return false;
		}

		return $nextUserId <= 0 || $this->setRotationLastUser($entity, $nextUserId);
	}

	/**
	 * Create and lock the per-entity rotation cursor.
	 *
	 * @param int $entity Entity
	 * @return int|null Last user ID, null on error
	 */
	private function lockRotationCursor($entity)
	{
		$sql = 'INSERT IGNORE INTO '.MAIN_DB_PREFIX.'emergencyhouse_verification_rotation';
		$sql .= ' (entity, fk_last_user) VALUES ('.((int) $entity).', NULL)';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$sql = 'SELECT fk_last_user FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification_rotation';
		$sql .= ' WHERE entity = '.((int) $entity).' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql || !is_object($rotation = $this->db->fetch_object($resql))) {
			$this->error = $resql ? 'ErrorVerificationRotationUnavailable' : $this->db->lasterror();
			return null;
		}

		return isset($rotation->fk_last_user) ? (int) $rotation->fk_last_user : 0;
	}

	/**
	 * Return active internal users explicitly granted the verification right.
	 *
	 * Effective grants include direct and group permissions. Administrator
	 * elevation is intentionally not used for rotation eligibility.
	 *
	 * @param int $entity Entity
	 * @return array<int, int>|null User IDs, null on SQL error
	 */
	private function getEligibleUserIds($entity)
	{
		$sql = 'SELECT rights.id';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'rights_def AS rights';
		$sql .= " WHERE rights.module = 'emergencyhouse'";
		$sql .= " AND rights.perms = 'verification' AND rights.subperms = 'write'";
		$sql .= ' AND rights.entity IN (0, '.((int) $entity).')';
		$sql .= ' ORDER BY rights.entity DESC LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$right = $this->db->fetch_object($resql);
		if (!is_object($right)) {
			return array();
		}
		$rightId = (int) $right->id;

		$sql = 'SELECT DISTINCT user.rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'user AS user';
		$sql .= ' WHERE user.statut = 1 AND (user.fk_soc IS NULL OR user.fk_soc = 0)';
		$sql .= ' AND (EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'user_rights AS direct_right';
		$sql .= ' WHERE direct_right.fk_user = user.rowid AND direct_right.fk_id = '.((int) $rightId);
		$sql .= ' AND direct_right.entity = '.((int) $entity).')';
		$sql .= ' OR EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'usergroup_user AS group_member';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'usergroup_rights AS group_right';
		$sql .= ' ON group_right.fk_usergroup = group_member.fk_usergroup';
		$sql .= ' AND group_right.entity = group_member.entity';
		$sql .= ' WHERE group_member.fk_user = user.rowid';
		$sql .= ' AND group_member.entity = '.((int) $entity);
		$sql .= ' AND group_right.fk_id = '.((int) $rightId).'))';
		$sql .= ' ORDER BY user.rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}
		$userIds = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$userIds[] = (int) $obj->rowid;
		}

		return $userIds;
	}

	/**
	 * Set an assignee without altering queue age.
	 *
	 * @param int $queueId Queue ID
	 * @param int $userId  User ID or zero
	 * @return bool
	 */
	private function setQueueAssignment($queueId, $userId)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue SET';
		if ($userId > 0) {
			$sql .= ' fk_assigned_user = '.((int) $userId);
			$sql .= ", date_assigned = '".$this->db->idate(dol_now())."'";
		} else {
			$sql .= ' fk_assigned_user = NULL, date_assigned = NULL';
		}
		$sql .= ' WHERE rowid = '.((int) $queueId);
		$sql .= ' AND queue_status = '.self::QUEUE_PENDING;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return true;
	}

	/**
	 * Persist the rotation cursor.
	 *
	 * @param int $entity    Entity
	 * @param int $lastUserId Last user ID
	 * @return bool
	 */
	private function setRotationLastUser($entity, $lastUserId)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_verification_rotation SET fk_last_user = ';
		$sql .= $lastUserId > 0 ? (string) ((int) $lastUserId) : 'NULL';
		$sql .= ' WHERE entity = '.((int) $entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return true;
	}

	/**
	 * Mark a queue row cancelled.
	 *
	 * @param int $queueId Queue ID
	 * @return bool
	 */
	private function setQueueCancelled($queueId)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_verification_queue';
		$sql .= ' SET queue_status = '.self::QUEUE_CANCELLED.', fk_assigned_user = NULL,';
		$sql .= " date_assigned = NULL, date_completed = '".$this->db->idate(dol_now())."', fk_verification = NULL";
		$sql .= ' WHERE rowid = '.((int) $queueId);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return true;
	}

	/**
	 * Test whether a source object still belongs in the queue.
	 *
	 * @param int    $entity     Entity
	 * @param string $objectType Object type
	 * @param int    $objectId   Object ID
	 * @param bool   $forUpdate  Lock the source row
	 * @return bool
	 */
	private function targetIsEligible($entity, $objectType, $objectId, $forUpdate = false)
	{
		$table = '';
		$condition = '';
		if ($objectType === 'account') {
			return false;
		} elseif ($objectType === 'offer') {
			$table = 'emergencyhouse_offer';
			$condition = 'status = 1 AND verification_status < 1';
		} elseif ($objectType === 'request') {
			$table = 'emergencyhouse_request';
			$condition = 'status IN ('.EmergencyHouseRequest::STATUS_ACTIVE.','.EmergencyHouseRequest::STATUS_PENDING.')';
			$condition .= ' AND verification_status < 1';
		} else {
			return false;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$table;
		$sql .= ' WHERE rowid = '.((int) $objectId).' AND entity = '.((int) $entity);
		$sql .= ' AND '.$condition;
		if ($forUpdate) {
			$sql .= ' FOR UPDATE';
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return $this->db->num_rows($resql) > 0;
	}

	/**
	 * Validate target identifiers.
	 *
	 * @param int    $entity Entity
	 * @param string $type   Type
	 * @param int    $id     ID
	 * @return bool
	 */
	private function isValidTarget($entity, $type, $id)
	{
		return $entity > 0 && $id > 0 && in_array($type, array('account', 'offer', 'request'), true);
	}

	/**
	 * Check queue entity scope.
	 *
	 * @param int    $entity Entity
	 * @param string $type   Object type
	 * @return bool
	 */
	private function queueEntityIsAccessible($entity, $type)
	{
		global $conf;

		if ($type === 'account') {
			return $entity === (int) $conf->entity;
		}

		return emergencyhouseEntityIsAccessibleForElement($entity, $type);
	}

	/**
	 * Check an active verification level.
	 *
	 * @param int $entity Entity
	 * @param int $levelId Level ID
	 * @return bool
	 */
	private function verificationLevelIsActive($entity, $levelId)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_verification_level';
		$sql .= ' WHERE rowid = '.((int) $levelId).' AND entity = '.((int) $entity).' AND active = 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return $this->db->num_rows($resql) > 0;
	}

	/**
	 * Resolve a verification level by stable code.
	 *
	 * @param int    $entity Entity
	 * @param string $code   Level code
	 * @return int Level ID or 0
	 */
	private function findVerificationLevelIdByCode($entity, $code)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_emergencyhouse_verification_level';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= " AND code = '".$this->db->escape($code)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return 0;
		}
		$level = $this->db->fetch_object($resql);
		if (!is_object($level)) {
			$this->error = 'ErrorInvalidVerificationLevel';
			return 0;
		}

		return (int) $level->rowid;
	}

	/**
	 * Find an existing automatic email-confirmation proof.
	 *
	 * @param int $entity    Entity
	 * @param int $accountId Public account ID
	 * @return int Verification ID or 0
	 */
	private function findEmailConfirmationVerificationId($entity, $accountId)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_verification';
		$sql .= ' WHERE entity = '.((int) $entity)." AND object_type = 'account'";
		$sql .= ' AND fk_object = '.((int) $accountId)." AND verification_type = 'email'";
		$sql .= ' AND status = '.self::STATUS_VERIFIED;
		$sql .= " AND method_code = '".self::METHOD_EMAIL_CONFIRMATION."'";
		$sql .= ' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return 0;
		}
		$verification = $this->db->fetch_object($resql);

		return is_object($verification) ? (int) $verification->rowid : 0;
	}

	/**
	 * Insert the immutable verification ledger row.
	 *
	 * @param int         $entity Entity
	 * @param string      $objectType Object type
	 * @param int         $objectId Object ID
	 * @param string      $verificationType Verification type
	 * @param int         $levelId Level ID
	 * @param int         $status Final status
	 * @param string      $methodCode Method
	 * @param int|null    $operatorId Operator ID
	 * @param string|null $encryptedNote Encrypted note
	 * @param int|null    $expiration Expiration
	 * @param int|null    $verifiedAt Verification timestamp
	 * @return int
	 */
	private function insertVerification($entity, $objectType, $objectId, $verificationType, $levelId, $status, $methodCode, $operatorId, $encryptedNote, $expiration, $verifiedAt = null)
	{
		$verificationDate = $verifiedAt === null ? dol_now() : (int) $verifiedAt;
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_verification';
		$sql .= ' (entity, object_type, fk_object, verification_type, fk_verification_level, status,';
		$sql .= ' method_code, private_note_encrypted, fk_operator, date_creation, date_verified, date_expiration)';
		$sql .= ' VALUES ('.((int) $entity).", '".$this->db->escape($objectType)."', ".((int) $objectId).',';
		$sql .= " '".$this->db->escape($verificationType)."', ".((int) $levelId).', '.((int) $status).',';
		$sql .= " '".$this->db->escape($methodCode)."',";
		$sql .= $encryptedNote === null ? ' NULL,' : " '".$this->db->escape($encryptedNote)."',";
		$sql .= $operatorId === null ? ' NULL,' : ' '.((int) $operatorId).',';
		$sql .= " '".$this->db->idate($verificationDate)."',";
		$sql .= " '".$this->db->idate($verificationDate)."',";
		$sql .= $expiration === null ? ' NULL)' : " '".$this->db->idate($expiration)."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_verification');
	}

	/**
	 * Apply the final state to the verified source object.
	 *
	 * @param int     $entity Entity
	 * @param string  $objectType Object type
	 * @param int     $objectId Object ID
	 * @param int     $levelId Level ID
	 * @param int     $status Final status
	 * @param User    $user Operator
	 * @return bool
	 */
	private function updateVerifiedTarget($entity, $objectType, $objectId, $levelId, $status, $user)
	{
		if ($objectType === 'account') {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
			$sql .= ' SET verification_status = '.((int) $status).',';
			$sql .= ' manual_verification_level = '.($status === self::STATUS_VERIFIED ? (int) $levelId : 0);
			$sql .= ' WHERE rowid = '.((int) $objectId).' AND entity = '.((int) $entity);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return false;
			}

			return true;
		}

		$linkedObject = $objectType === 'offer'
			? new EmergencyHouseOffer($this->db)
			: new EmergencyHouseRequest($this->db);
		if ($linkedObject->fetch($objectId) <= 0 || (int) $linkedObject->entity !== $entity) {
			$this->error = !empty($linkedObject->error) ? $linkedObject->error : 'ErrorRecordNotFound';
			return false;
		}
		$linkedObject->verification_status = $status;
		$linkedObject->context['trigger_reason'] = 'verification_change';
		$linkedObject->context['changed_fields'] = $objectType === 'offer'
			? array('verification_status', 'photos')
			: array('verification_status');
		if ($linkedObject->updateInsideServiceTransaction($user) <= 0) {
			$this->error = $linkedObject->error;
			return false;
		}
		if ($objectType === 'offer') {
			$photoService = new EmergencyHouseOfferPhotoService($this->db);
			if (!$photoService->updateStatuses($linkedObject, $status)) {
				$this->error = $photoService->error;
				return false;
			}
		}

		return true;
	}

	/**
	 * Roll back an owned transaction and return a stable error.
	 *
	 * @param bool $manageTransaction Whether the method owns the transaction
	 * @return int
	 */
	private function rollbackWithError($manageTransaction)
	{
		if ($this->error === '') {
			$this->error = $this->db->lasterror();
		}
		if ($manageTransaction) {
			$this->db->rollback();
		}

		return -1;
	}
}
