<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/messageservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');

/**
 * Solicitation orchestration and anti-abuse rules.
 */
class EmergencyHouseSolicitationService
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
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->encryption = new EmergencyHouseEncryptionService();
	}

	/**
	 * Open one solicitation.
	 *
	 * @param int         $offerId Offer ID
	 * @param int         $requestId Request ID
	 * @param int|null    $matchId Match ID
	 * @param int|null    $initiatorAccount Public account
	 * @param int|null    $initiatorUser Dolibarr user
	 * @param string      $direction Direction
	 * @param string      $message Initial message
	 * @param User        $triggerUser User passed to object trigger
	 * @return EmergencyHouseSolicitation|false
	 */
	public function create($offerId, $requestId, $matchId, $initiatorAccount, $initiatorUser, $direction, $message, $triggerUser)
	{
		$offer = new EmergencyHouseOffer($this->db);
		$request = new EmergencyHouseRequest($this->db);
		if ($offer->fetch($offerId) <= 0 || $request->fetch($requestId) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		if ((int) $offer->entity !== (int) $request->entity || (int) $offer->fk_campaign !== (int) $request->fk_campaign) {
			$this->error = 'ErrorCampaignMismatch';
			return false;
		}
		if ((int) $offer->fk_account === (int) $request->fk_account) {
			$this->error = 'ErrorSelfSolicitation';
			return false;
		}
		if ($offer->status !== EmergencyHouseOffer::STATUS_PUBLISHED
			|| !in_array($request->status, array(EmergencyHouseRequest::STATUS_ACTIVE, EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED), true)) {
			$this->error = 'ErrorObjectNotSolicitable';
			return false;
		}
		if (!in_array($direction, array('request_to_offer', 'offer_to_request', 'operator'), true)) {
			$this->error = 'ErrorInvalidSolicitationDirection';
			return false;
		}
		if ($initiatorAccount !== null) {
			$authorized = ($direction === 'request_to_offer' && (int) $request->fk_account === $initiatorAccount)
				|| ($direction === 'offer_to_request' && (int) $offer->fk_account === $initiatorAccount);
			if (!$authorized || $initiatorUser !== null || $direction === 'operator') {
				$this->error = 'ErrorForbidden';
				return false;
			}
		} elseif ($initiatorUser === null || $direction !== 'operator') {
			$this->error = 'ErrorForbidden';
			return false;
		}
		if ($direction === 'request_to_offer' && !$offer->direct_solicitation_enabled && $matchId === null) {
			$this->error = 'ErrorDirectSolicitationDisabled';
			return false;
		}
		if ($direction === 'offer_to_request' && $request->visibility !== 'public' && $matchId === null) {
			$this->error = 'ErrorRequestNotPublic';
			return false;
		}
		if ($this->isBlocked((int) $offer->entity, (int) $offer->fk_account, (int) $request->fk_account)) {
			$this->error = 'ErrorSolicitationBlocked';
			return false;
		}
		if ($this->hasOpenSolicitation((int) $offer->entity, $offerId, $requestId)) {
			$this->error = 'ErrorSolicitationAlreadyExists';
			return false;
		}
		if ($initiatorAccount !== null && !$this->withinDailyLimit((int) $offer->entity, $initiatorAccount)) {
			$this->error = 'ErrorSolicitationDailyLimit';
			return false;
		}

		$solicitation = new EmergencyHouseSolicitation($this->db);
		$solicitation->entity = (int) $offer->entity;
		$solicitation->public_uuid = bin2hex(random_bytes(16));
		$solicitation->date_creation = dol_now();
		$nextRef = $solicitation->getNextNumRef();
		if (!is_string($nextRef) || $nextRef === '') {
			$this->error = !empty($solicitation->error) ? $solicitation->error : 'ErrorNumberingModel';
			return false;
		}
		$solicitation->ref = $nextRef;
		$solicitation->fk_campaign = (int) $offer->fk_campaign;
		$solicitation->fk_offer = (int) $offer->id;
		$solicitation->fk_request = (int) $request->id;
		$solicitation->fk_match = $matchId;
		$solicitation->fk_initiator_account = $initiatorAccount;
		$solicitation->fk_initiator_user = $initiatorUser;
		$solicitation->initiator_direction = $direction;
		if ($initiatorAccount !== null) {
			$solicitation->context['public_account_id'] = $initiatorAccount;
		}
		$solicitation->context['trigger_reason'] = 'solicitation_opened';
		$solicitation->date_expiration = dol_time_plus_duree(dol_now(), max(1, getDolGlobalInt('EMERGENCYHOUSE_SOLICITATION_EXPIRY_DAYS', 7)), 'd');
		$encrypted = $this->encryption->encrypt(
			trim($message),
			'emergencyhouse|solicitation|'.$solicitation->entity.'|'.$solicitation->public_uuid
		);
		if (!is_string($encrypted)) {
			$this->error = $this->encryption->error;
			return false;
		}
		$solicitation->initial_message_encrypted = $encrypted;

		$this->db->begin();
		if (!$this->lockPair((int) $offer->entity, (int) $offer->id, (int) $request->id)
			|| $this->hasOpenSolicitation((int) $offer->entity, $offerId, $requestId)) {
			if ($this->error === '') {
				$this->error = 'ErrorSolicitationAlreadyExists';
			}
			$this->db->rollback();
			return false;
		}
		$result = $solicitation->createInsideServiceTransaction($triggerUser);
		if ($result <= 0) {
			$this->error = $solicitation->error;
			$this->errors = $solicitation->errors;
			$this->db->rollback();
			return false;
		}
		$messageService = new EmergencyHouseMessageService($this->db);
		if ($messageService->createMessage($solicitation, $initiatorAccount, $initiatorUser, $message) <= 0) {
			$this->error = $messageService->error;
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return $solicitation;
	}

	/**
	 * Lock both sides of a pair to serialize duplicate checks.
	 *
	 * @param int $entity Entity
	 * @param int $offerId Offer ID
	 * @param int $requestId Request ID
	 * @return bool
	 */
	private function lockPair($entity, $offerId, $requestId)
	{
		$sql = 'SELECT o.rowid AS offer_id, r.rowid AS request_id';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= ' ON r.rowid = '.((int) $requestId).' AND r.entity = o.entity';
		$sql .= ' WHERE o.rowid = '.((int) $offerId).' AND o.entity = '.((int) $entity);
		$sql .= ' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) !== 1) {
			$this->error = $resql ? 'ErrorRecordNotFound' : $this->db->lasterror();
			return false;
		}
		return true;
	}

	/**
	 * Check account blocks in both directions.
	 *
	 * @param int $entity Entity
	 * @param int $accountA Account A
	 * @param int $accountB Account B
	 * @return bool
	 */
	private function isBlocked($entity, $accountA, $accountB)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_block';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= ' AND ((fk_account = '.((int) $accountA).' AND fk_blocked_account = '.((int) $accountB).')';
		$sql .= ' OR (fk_account = '.((int) $accountB).' AND fk_blocked_account = '.((int) $accountA).'))';
		$sql .= " AND (date_end IS NULL OR date_end > '".$this->db->idate(dol_now())."')";
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) > 0;
	}

	/**
	 * Check duplicate active solicitation.
	 *
	 * @param int $entity Entity
	 * @param int $offerId Offer ID
	 * @param int $requestId Request ID
	 * @return bool
	 */
	private function hasOpenSolicitation($entity, $offerId, $requestId)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_offer = '.((int) $offerId).' AND fk_request = '.((int) $requestId);
		$sql .= ' AND status IN ('.EmergencyHouseSolicitation::STATUS_PENDING.','.EmergencyHouseSolicitation::STATUS_ACCEPTED.')';
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) > 0;
	}

	/**
	 * Enforce daily solicitation quota.
	 *
	 * @param int $entity Entity
	 * @param int $accountId Account ID
	 * @return bool
	 */
	private function withinDailyLimit($entity, $accountId)
	{
		$limit = max(1, getDolGlobalInt('EMERGENCYHOUSE_SOLICITATION_DAILY_LIMIT', 20));
		$dayStart = dol_mktime(0, 0, 0, (int) dol_print_date(dol_now(), '%m'), (int) dol_print_date(dol_now(), '%d'), (int) dol_print_date(dol_now(), '%Y'));
		$sql = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_initiator_account = '.((int) $accountId);
		$sql .= " AND date_creation >= '".$this->db->idate($dayStart)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return !is_object($obj) || (int) $obj->total < $limit;
	}

}
