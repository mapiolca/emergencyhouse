<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/capacityservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');

/**
 * Public participant boundary for solicitations and allocations.
 */
class EmergencyHouseParticipantService
{
	/** @var DoliDB */
	private $db;
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
	}

	/**
	 * List solicitations involving a public account.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $limit Limit
	 * @param int $offset Offset
	 * @return array<int, array<string, int|string|null>>|false
	 */
	public function fetchSolicitations($account, $limit = 100, $offset = 0)
	{
		$sql = 'SELECT s.rowid, s.public_uuid, s.ref, s.fk_campaign, s.fk_offer, s.fk_request, s.status,';
		$sql .= ' s.fk_initiator_account, s.initiator_direction, s.date_creation, s.date_response, s.date_expiration,';
		$sql .= ' o.title AS offer_title, o.fk_account AS offer_account, r.title AS request_title, r.fk_account AS request_account,';
		$sql .= ' c.label AS campaign_label,';
		$sql .= ' (SELECT MAX(m.date_creation) FROM '.MAIN_DB_PREFIX.'emergencyhouse_message AS m';
		$sql .= ' WHERE m.entity = s.entity AND m.fk_solicitation = s.rowid AND m.date_deleted IS NULL) AS last_message_date';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation AS s';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = s.fk_offer AND o.entity = s.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = s.fk_request AND r.entity = s.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c ON c.rowid = s.fk_campaign AND c.entity = s.entity';
		$sql .= ' WHERE s.entity = '.((int) $account->entity);
		$sql .= ' AND (o.fk_account = '.((int) $account->id).' OR r.fk_account = '.((int) $account->id).')';
		$sql .= ' ORDER BY COALESCE(last_message_date, s.date_creation) DESC, s.rowid DESC';
		$sql .= $this->db->plimit(min(100, max(1, $limit)), max(0, $offset));
		return $this->fetchRows($sql);
	}

	/**
	 * Fetch a solicitation and identify the participant role.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $id Solicitation ID
	 * @return array{solicitation:EmergencyHouseSolicitation,role:string,offer_account:int,request_account:int,other_account:int}|false
	 */
	public function fetchSolicitation($account, $id)
	{
		$sql = 'SELECT o.fk_account AS offer_account, r.fk_account AS request_account';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation AS s';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = s.fk_offer AND o.entity = s.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = s.fk_request AND r.entity = s.entity';
		$sql .= ' WHERE s.rowid = '.((int) $id).' AND s.entity = '.((int) $account->entity);
		$sql .= ' AND (o.fk_account = '.((int) $account->id).' OR r.fk_account = '.((int) $account->id).')';
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($obj)) {
			$this->error = $resql ? 'ErrorRecordNotFound' : $this->db->lasterror();
			return false;
		}
		$solicitation = new EmergencyHouseSolicitation($this->db);
		if ($solicitation->fetch($id) <= 0) {
			$this->error = $solicitation->error;
			return false;
		}
		$offerAccount = (int) $obj->offer_account;
		$requestAccount = (int) $obj->request_account;
		$role = (int) $account->id === $offerAccount ? 'host' : 'requester';
		return array(
			'solicitation' => $solicitation,
			'role' => $role,
			'offer_account' => $offerAccount,
			'request_account' => $requestAccount,
			'other_account' => $role === 'host' ? $requestAccount : $offerAccount,
		);
	}

	/**
	 * Apply a participant response.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @param string $response accept, refuse or cancel
	 * @param string $reasonCode Controlled reason code
	 * @param User $triggerUser Trigger user
	 * @return int
	 */
	public function respond($account, $solicitation, $response, $reasonCode, $triggerUser)
	{
		$context = $this->fetchSolicitation($account, (int) $solicitation->id);
		if (!is_array($context)) {
			return -1;
		}
		$isInitiator = !empty($solicitation->fk_initiator_account)
			&& (int) $solicitation->fk_initiator_account === (int) $account->id;
		$solicitation->context['public_account_id'] = (int) $account->id;
		if ($response === 'accept' || $response === 'refuse') {
			if ($isInitiator || (int) $solicitation->status !== EmergencyHouseSolicitation::STATUS_PENDING) {
				$this->error = 'ErrorForbidden';
				return -1;
			}
			$newStatus = $response === 'accept'
				? EmergencyHouseSolicitation::STATUS_ACCEPTED
				: EmergencyHouseSolicitation::STATUS_REFUSED;
			if ($response === 'refuse' && !$this->reasonExists(
				'c_emergencyhouse_refusal_reason',
				(int) $account->entity,
				$reasonCode
			)) {
				$this->error = 'ErrorInvalidRefusalReason';
				return -1;
			}
			$result = $solicitation->setStatus(
				$newStatus,
				$triggerUser,
				$response === 'accept' ? 'participant_acceptance' : 'participant_refusal',
				$response === 'refuse' ? $reasonCode : null
			);
		} elseif ($response === 'cancel') {
			if (!$isInitiator || !in_array(
				(int) $solicitation->status,
				array(EmergencyHouseSolicitation::STATUS_PENDING, EmergencyHouseSolicitation::STATUS_ACCEPTED),
				true
			)) {
				$this->error = 'ErrorForbidden';
				return -1;
			}
			if (!$this->reasonExists('c_emergencyhouse_cancellation_reason', (int) $account->entity, $reasonCode)) {
				$this->error = 'ErrorInvalidCancellationReason';
				return -1;
			}
			$result = $solicitation->setStatus(
				EmergencyHouseSolicitation::STATUS_CANCELLED,
				$triggerUser,
				'participant_cancellation',
				$reasonCode
			);
		} else {
			$this->error = 'ErrorInvalidAction';
			return -1;
		}
		if ($result <= 0) {
			$this->error = $solicitation->error;
			$this->errors = $solicitation->errors;
		}
		return $result;
	}

	/**
	 * Let the host grant or withdraw exact-address access.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @param bool $authorized Authorization
	 * @param User $triggerUser Trigger user
	 * @return int
	 */
	public function setAddressAuthorization($account, $solicitation, $authorized, $triggerUser)
	{
		$context = $this->fetchSolicitation($account, (int) $solicitation->id);
		if (!is_array($context)
			|| $context['role'] !== 'host'
			|| (int) $solicitation->status !== EmergencyHouseSolicitation::STATUS_ACCEPTED) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		$oldValue = (int) $solicitation->address_share_authorized;
		$solicitation->address_share_authorized = $authorized ? 1 : 0;
		$solicitation->context['public_account_id'] = (int) $account->id;
		$solicitation->context['trigger_reason'] = 'address_authorization_change';
		$solicitation->context['changed_fields'] = array('address_share_authorized');
		$solicitation->context['old_address_share_authorized'] = $oldValue;
		$solicitation->context['new_address_share_authorized'] = (int) $solicitation->address_share_authorized;
		$result = $solicitation->update($triggerUser);
		if ($result <= 0) {
			$this->error = $solicitation->error;
			$this->errors = $solicitation->errors;
		}
		return $result;
	}

	/**
	 * Mark a pending solicitation read by its recipient.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @param User $triggerUser Trigger user
	 * @return int
	 */
	public function markRead($account, $solicitation, $triggerUser)
	{
		if (!empty($solicitation->date_read)
			|| empty($solicitation->fk_initiator_account)
			|| (int) $solicitation->fk_initiator_account === (int) $account->id) {
			return 0;
		}
		$context = $this->fetchSolicitation($account, (int) $solicitation->id);
		if (!is_array($context)) {
			return -1;
		}
		$solicitation->date_read = dol_now();
		$solicitation->context['public_account_id'] = (int) $account->id;
		$solicitation->context['trigger_reason'] = 'recipient_read';
		$solicitation->context['changed_fields'] = array('date_read');
		$result = $solicitation->update($triggerUser);
		if ($result <= 0) {
			$this->error = $solicitation->error;
		}
		return $result;
	}

	/**
	 * List allocations involving a public account.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $limit Limit
	 * @param int $offset Offset
	 * @return array<int, array<string, int|string|null>>|false
	 */
	public function fetchAllocations($account, $limit = 100, $offset = 0)
	{
		$sql = 'SELECT a.rowid, a.public_uuid, a.ref, a.fk_campaign, a.fk_offer, a.fk_request, a.fk_solicitation,';
		$sql .= ' a.quantity, a.date_start, a.date_end, a.status, a.host_confirmed, a.requester_confirmed, a.incident_open,';
		$sql .= ' o.title AS offer_title, o.fk_account AS offer_account, r.title AS request_title, r.fk_account AS request_account,';
		$sql .= ' c.label AS campaign_label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_allocation AS a';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = a.fk_offer AND o.entity = a.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = a.fk_request AND r.entity = a.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c ON c.rowid = a.fk_campaign AND c.entity = a.entity';
		$sql .= ' WHERE a.entity = '.((int) $account->entity);
		$sql .= ' AND (o.fk_account = '.((int) $account->id).' OR r.fk_account = '.((int) $account->id).')';
		$sql .= ' ORDER BY a.date_start DESC, a.rowid DESC';
		$sql .= $this->db->plimit(min(100, max(1, $limit)), max(0, $offset));
		return $this->fetchRows($sql);
	}

	/**
	 * Fetch an allocation and participant role.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $id Allocation ID
	 * @return array{allocation:EmergencyHouseAllocation,role:string,offer_account:int,request_account:int}|false
	 */
	public function fetchAllocation($account, $id)
	{
		$sql = 'SELECT o.fk_account AS offer_account, r.fk_account AS request_account';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_allocation AS a';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = a.fk_offer AND o.entity = a.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = a.fk_request AND r.entity = a.entity';
		$sql .= ' WHERE a.rowid = '.((int) $id).' AND a.entity = '.((int) $account->entity);
		$sql .= ' AND (o.fk_account = '.((int) $account->id).' OR r.fk_account = '.((int) $account->id).')';
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($obj)) {
			$this->error = $resql ? 'ErrorRecordNotFound' : $this->db->lasterror();
			return false;
		}
		$allocation = new EmergencyHouseAllocation($this->db);
		if ($allocation->fetch($id) <= 0) {
			$this->error = $allocation->error;
			return false;
		}
		$offerAccount = (int) $obj->offer_account;
		$requestAccount = (int) $obj->request_account;
		return array(
			'allocation' => $allocation,
			'role' => (int) $account->id === $offerAccount ? 'host' : 'requester',
			'offer_account' => $offerAccount,
			'request_account' => $requestAccount,
		);
	}

	/**
	 * Confirm an allocation for the current participant side.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param EmergencyHouseAllocation $allocation Allocation
	 * @param User $triggerUser Trigger user
	 * @return int
	 */
	public function confirmAllocation($account, $allocation, $triggerUser)
	{
		$context = $this->fetchAllocation($account, (int) $allocation->id);
		if (!is_array($context) || !in_array(
			(int) $allocation->status,
			array(EmergencyHouseAllocation::STATUS_PROPOSED, EmergencyHouseAllocation::STATUS_CONFIRMED),
			true
		)) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		$side = $context['role'] === 'host' ? 'host' : 'requester';
		if (($side === 'host' && !empty($allocation->host_confirmed))
			|| ($side === 'requester' && !empty($allocation->requester_confirmed))) {
			return 0;
		}
		$allocation->context['public_account_id'] = (int) $account->id;
		$result = $allocation->confirm($side, $triggerUser);
		if ($result <= 0) {
			$this->error = $allocation->error;
			$this->errors = $allocation->errors;
		}
		return $result;
	}

	/**
	 * Cancel an allocation and release its reserved capacity.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param EmergencyHouseAllocation $allocation Allocation
	 * @param string $reasonCode Controlled reason
	 * @param User $triggerUser Trigger user
	 * @return int
	 */
	public function cancelAllocation($account, $allocation, $reasonCode, $triggerUser)
	{
		$context = $this->fetchAllocation($account, (int) $allocation->id);
		if (!is_array($context)
			|| !in_array(
				(int) $allocation->status,
				array(
					EmergencyHouseAllocation::STATUS_PROPOSED,
					EmergencyHouseAllocation::STATUS_CONFIRMED,
					EmergencyHouseAllocation::STATUS_ACTIVE,
					EmergencyHouseAllocation::STATUS_INCIDENT,
				),
				true
			)) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		if (!$this->reasonExists('c_emergencyhouse_cancellation_reason', (int) $account->entity, $reasonCode)) {
			$this->error = 'ErrorInvalidCancellationReason';
			return -1;
		}

		$allocation->context['public_account_id'] = (int) $account->id;
		$service = new EmergencyHouseCapacityService($this->db);
		$result = $service->cancel($allocation, $triggerUser, $reasonCode);
		if ($result <= 0) {
			$this->error = $service->error;
			$this->errors = $service->errors;
		}
		return $result;
	}

	/**
	 * Return active dictionary codes and translated labels.
	 *
	 * @param string $dictionary refusal or cancellation
	 * @param int $entity Entity
	 * @return array<string, string>|false
	 */
	public function fetchReasonDictionary($dictionary, $entity)
	{
		$tables = array(
			'refusal' => 'c_emergencyhouse_refusal_reason',
			'cancellation' => 'c_emergencyhouse_cancellation_reason',
		);
		if (!isset($tables[$dictionary])) {
			$this->error = 'ErrorInvalidDictionary';
			return false;
		}
		$sql = 'SELECT code, label FROM '.MAIN_DB_PREFIX.$tables[$dictionary];
		$sql .= ' WHERE entity = '.((int) $entity).' AND active = 1 ORDER BY position ASC, label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[(string) $obj->code] = (string) $obj->label;
		}
		return $rows;
	}

	/**
	 * Validate a dictionary reason code.
	 *
	 * @param string $table Table without prefix
	 * @param int $entity Entity
	 * @param string $code Code
	 * @return bool
	 */
	private function reasonExists($table, $entity, $code)
	{
		if ($code === '' || !preg_match('/^[a-z0-9_]{1,64}$/', $code)) {
			return false;
		}
		$allowed = array('c_emergencyhouse_refusal_reason', 'c_emergencyhouse_cancellation_reason');
		if (!in_array($table, $allowed, true)) {
			return false;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$table;
		$sql .= ' WHERE entity = '.((int) $entity)." AND code = '".$this->db->escape($code)."' AND active = 1";
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) === 1;
	}

	/**
	 * Fetch safe scalar rows.
	 *
	 * @param string $sql SQL query
	 * @return array<int, array<string, int|string|null>>|false
	 */
	private function fetchRows($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$row = array();
			foreach (get_object_vars($obj) as $key => $value) {
				if (is_int($value) || is_string($value) || $value === null) {
					$row[$key] = $value;
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}
}
