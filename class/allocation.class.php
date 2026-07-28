<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/emergencyhousecommonobject.class.php');

/**
 * Capacity allocation and stay.
 */
class EmergencyHouseAllocation extends EmergencyHouseCommonObject
{
	public const STATUS_PROPOSED = 0;
	public const STATUS_CONFIRMED = 1;
	public const STATUS_ACTIVE = 2;
	public const STATUS_COMPLETED = 3;
	public const STATUS_CANCELLED = 4;
	public const STATUS_INCIDENT = 5;

	/** @var string */
	public $element = 'allocation';
	/** @var string */
	public $table_element = 'emergencyhouse_allocation';
	/** @var string */
	public $picto = 'calendar';
	/** @var string */
	public $trigger_prefix = 'EMERGENCYHOUSE_ALLOCATION';
	/** @var int */
	public $fk_campaign = 0;
	/** @var int */
	public $fk_offer = 0;
	/** @var int */
	public $fk_request = 0;
	/** @var int|null */
	public $fk_solicitation;
	/** @var int */
	public $quantity = 1;
	/** @var string|null */
	public $subgroup_code;
	/** @var int|null */
	public $date_start;
	/** @var int|null */
	public $date_end;
	/** @var int|null */
	public $actual_start;
	/** @var int|null */
	public $actual_end;
	/** @var int */
	public $host_confirmed = 0;
	/** @var int */
	public $requester_confirmed = 0;
	/** @var int|null */
	public $host_confirmation_date;
	/** @var int|null */
	public $requester_confirmation_date;
	/** @var int */
	public $address_share_authorized = 0;
	/** @var string|null */
	public $cancellation_reason;
	/** @var int */
	public $incident_open = 0;
	/** @var int|null */
	public $fk_operator;
	/** @var string */
	public $idempotency_key = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->fields = array_merge(self::commonFields(), array(
			'public_uuid' => array('type' => 'varchar(64)', 'label' => 'PublicUuid', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 12),
			'fk_campaign' => array('type' => 'integer:EmergencyHouseCampaign:emergencyhouse/class/campaign.class.php', 'label' => 'Campaign', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 20),
			'fk_offer' => array('type' => 'integer:EmergencyHouseOffer:emergencyhouse/class/offer.class.php', 'label' => 'Offer', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 30),
			'fk_request' => array('type' => 'integer:EmergencyHouseRequest:emergencyhouse/class/request.class.php', 'label' => 'Request', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 40),
			'fk_solicitation' => array('type' => 'integer:EmergencyHouseSolicitation:emergencyhouse/class/solicitation.class.php', 'label' => 'Solicitation', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 50),
			'quantity' => array('type' => 'integer', 'label' => 'Quantity', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 60),
			'subgroup_code' => array('type' => 'varchar(64)', 'label' => 'SubgroupCode', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 70),
			'date_start' => array('type' => 'datetime', 'label' => 'DateStart', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 80),
			'date_end' => array('type' => 'datetime', 'label' => 'DateEnd', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 90),
			'actual_start' => array('type' => 'datetime', 'label' => 'ActualStart', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 100),
			'actual_end' => array('type' => 'datetime', 'label' => 'ActualEnd', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 110),
			'host_confirmed' => array('type' => 'boolean', 'label' => 'HostConfirmed', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 120),
			'requester_confirmed' => array('type' => 'boolean', 'label' => 'RequesterConfirmed', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 130),
			'host_confirmation_date' => array('type' => 'datetime', 'label' => 'HostConfirmationDate', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 140),
			'requester_confirmation_date' => array('type' => 'datetime', 'label' => 'RequesterConfirmationDate', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 150),
			'address_share_authorized' => array('type' => 'boolean', 'label' => 'AddressShareAuthorized', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 160),
			'cancellation_reason' => array('type' => 'varchar(64)', 'label' => 'CancellationReason', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 170),
			'incident_open' => array('type' => 'boolean', 'label' => 'IncidentOpen', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 180),
			'fk_operator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Operator', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 190),
			'idempotency_key' => array('type' => 'varchar(128)', 'label' => 'IdempotencyKey', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 200),
		));
	}

	/**
	 * Validate and create.
	 *
	 * @param User $user User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		if (empty($this->context['capacity_lock_acquired'])) {
			$this->error = 'ErrorAllocationRequiresCapacityService';
			return -1;
		}
		if (!$this->validateBusinessRules()) {
			return -1;
		}
		if (empty($this->idempotency_key)) {
			$this->idempotency_key = hash('sha256', $this->entity.'|'.$this->fk_offer.'|'.$this->fk_request.'|'.$this->date_start.'|'.$this->date_end.'|'.$this->quantity.'|'.bin2hex(random_bytes(8)));
		}
		return parent::create($user, $notrigger);
	}

	/**
	 * Insert while the caller owns the capacity transaction.
	 *
	 * @param User $user User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function createInsideCapacityTransaction($user, $notrigger = 0)
	{
		$this->context['capacity_lock_acquired'] = true;
		if (!$this->validateBusinessRules()) {
			return -1;
		}
		if (empty($this->idempotency_key)) {
			$this->idempotency_key = hash('sha256', $this->entity.'|'.$this->fk_offer.'|'.$this->fk_request.'|'.$this->date_start.'|'.$this->date_end.'|'.$this->quantity.'|'.bin2hex(random_bytes(8)));
		}
		if (!$this->prepareCommonCreate($user)) {
			return -1;
		}
		return $this->insertPrepared($user, $notrigger);
	}

	/**
	 * Validate basic invariants.
	 *
	 * @return bool
	 */
	public function validateBusinessRules()
	{
		$this->errors = array();
		if ($this->fk_campaign <= 0 || $this->fk_offer <= 0 || $this->fk_request <= 0) {
			$this->errors[] = 'ErrorMissingRequiredLink';
		}
		if ($this->quantity <= 0) {
			$this->errors[] = 'ErrorInvalidQuantity';
		}
		if (empty($this->date_start) || (!empty($this->date_end) && $this->date_end < $this->date_start)) {
			$this->errors[] = 'ErrorInvalidPeriod';
		}
		if (!empty($this->errors)) {
			$this->error = $this->errors[0];
			return false;
		}
		return true;
	}

	/**
	 * Confirm one side of an allocation.
	 *
	 * @param string $side host or requester
	 * @param User   $user User
	 * @return int
	 */
	public function confirm($side, $user)
	{
		if (!in_array($side, array('host', 'requester'), true)) {
			$this->error = 'ErrorInvalidConfirmationSide';
			return -1;
		}
		if ($side === 'host') {
			$this->host_confirmed = 1;
			$this->host_confirmation_date = dol_now();
		} else {
			$this->requester_confirmed = 1;
			$this->requester_confirmation_date = dol_now();
		}
		$oldStatus = (int) $this->status;
		if ($this->host_confirmed && $this->requester_confirmed && $this->status === self::STATUS_PROPOSED) {
			$this->status = self::STATUS_CONFIRMED;
		}
		$this->context['trigger_reason'] = 'confirmation_change';
		$this->context['changed_fields'] = array($side.'_confirmed', 'status');
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = (int) $this->status;
		return parent::update($user);
	}

	/**
	 * Set lifecycle status.
	 *
	 * @param int         $newStatus New status
	 * @param User        $user User
	 * @param string      $reason Stable reason
	 * @param string|null $reasonCode Optional cancellation code
	 * @return int
	 */
	public function setStatus($newStatus, $user, $reason, $reasonCode = null)
	{
		$allowed = array(
			self::STATUS_PROPOSED => array(self::STATUS_CONFIRMED, self::STATUS_CANCELLED),
			self::STATUS_CONFIRMED => array(self::STATUS_ACTIVE, self::STATUS_CANCELLED, self::STATUS_INCIDENT),
			self::STATUS_ACTIVE => array(self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_INCIDENT),
			self::STATUS_INCIDENT => array(self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_CANCELLED),
			self::STATUS_COMPLETED => array(),
			self::STATUS_CANCELLED => array(),
		);
		if (!isset($allowed[$this->status]) || !in_array($newStatus, $allowed[$this->status], true)) {
			$this->error = 'ErrorInvalidStatusTransition';
			return -1;
		}
		if ($newStatus === self::STATUS_CANCELLED && empty($reasonCode)) {
			$this->error = 'ErrorCancellationReasonRequired';
			return -1;
		}

		$oldStatus = (int) $this->status;
		$this->status = $newStatus;
		if ($newStatus === self::STATUS_ACTIVE && empty($this->actual_start)) {
			$this->actual_start = dol_now();
		}
		if ($newStatus === self::STATUS_COMPLETED && empty($this->actual_end)) {
			$this->actual_end = dol_now();
		}
		if ($newStatus === self::STATUS_CANCELLED) {
			$this->cancellation_reason = $reasonCode;
		}
		$this->incident_open = $newStatus === self::STATUS_INCIDENT ? 1 : 0;
		$this->context['trigger_reason'] = $reason;
		$this->context['changed_fields'] = array('status');
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = $newStatus;
		return parent::update($user);
	}

	/**
	 * Generate the native accommodation agreement.
	 *
	 * @param string         $modele      Document model
	 * @param Translate      $outputlangs Output language
	 * @param int            $hidedetails Hide details
	 * @param int            $hidedesc    Hide description
	 * @param int            $hideref     Hide reference
	 * @param array<mixed>|null $moreparams Additional parameters
	 * @return int
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $langs, $user;

		dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');
		$langs->load('emergencyhouse@emergencyhouse');
		if (!is_object($user)
			|| !emergencyhouseCanDo($user, 'allocation', 'write', $this)
			|| !emergencyhouseCanDo($user, 'sensitive', 'contact', $this)
			|| !emergencyhouseCanDo($user, 'sensitive', 'address', $this)) {
			$this->error = 'ErrorForbidden';
			return -1;
		}
		if (!dol_strlen($modele)) {
			$modele = !empty($this->model_pdf)
				? (string) $this->model_pdf
				: getDolGlobalString('EMERGENCYHOUSE_ALLOCATION_DEFAULT_MODEL', 'emergencyhouse_agreement');
		}
		if ($modele !== 'emergencyhouse_agreement') {
			$this->error = 'ErrorUnknownDocumentModel';
			return -1;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'document_model';
		$sql .= " WHERE nom = '".$this->db->escape($modele)."'";
		$sql .= " AND type = 'emergencyhouse'";
		$sql .= ' AND entity IN (0, '.((int) $this->entity).')';
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) === 0) {
			$this->error = $resql ? 'ErrorDocumentModelDisabled' : $this->db->lasterror();
			return -1;
		}

		return $this->commonGenerateDocument(
			'core/modules/emergencyhouse/doc/',
			$modele,
			$outputlangs,
			$hidedetails,
			$hidedesc,
			$hideref,
			$moreparams
		);
	}

	/**
	 * Render status.
	 *
	 * @param int $status Status
	 * @param int $mode Mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$labels = array(
			self::STATUS_PROPOSED => array('StatusProposed', 'status1'),
			self::STATUS_CONFIRMED => array('StatusConfirmed', 'status4'),
			self::STATUS_ACTIVE => array('StatusActive', 'status4'),
			self::STATUS_COMPLETED => array('StatusCompleted', 'status6'),
			self::STATUS_CANCELLED => array('StatusCancelled', 'status6'),
			self::STATUS_INCIDENT => array('StatusIncident', 'status8'),
		);
		$definition = isset($labels[$status]) ? $labels[$status] : array('StatusUnknown', 'status0');
		return dolGetStatus($langs->trans($definition[0]), $langs->trans($definition[0]), '', $definition[1], $mode);
	}
}
