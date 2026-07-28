<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/emergencyhousecommonobject.class.php');

/**
 * Accommodation request.
 */
class EmergencyHouseRequest extends EmergencyHouseCommonObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_ACTIVE = 1;
	public const STATUS_PARTIALLY_ALLOCATED = 2;
	public const STATUS_FULFILLED = 3;
	public const STATUS_SUSPENDED = 4;
	public const STATUS_EXPIRED = 5;
	public const STATUS_CLOSED = 6;

	/** @var string */
	public $element = 'request';
	/** @var string */
	public $table_element = 'emergencyhouse_request';
	/** @var string */
	public $picto = 'help';
	/** @var string */
	public $trigger_prefix = 'EMERGENCYHOUSE_REQUEST';
	/** @var int */
	public $fk_campaign = 0;
	/** @var int */
	public $fk_account = 0;
	/** @var int */
	public $adults_count = 1;
	/** @var int */
	public $children_infant_count = 0;
	/** @var int */
	public $children_young_count = 0;
	/** @var int */
	public $children_teen_count = 0;
	/** @var int */
	public $person_count = 1;
	/** @var int */
	public $remaining_count = 1;
	/** @var int */
	public $group_divisible = 0;
	/** @var int */
	public $minimum_group_size = 1;
	/** @var int|null */
	public $date_start;
	/** @var int|null */
	public $date_end;
	/** @var int */
	public $duration_unknown = 0;
	/** @var string */
	public $desired_zone = '';
	/** @var string|null */
	public $desired_zip;
	/** @var string|null */
	public $desired_town;
	/** @var int */
	public $search_radius = 50;
	/** @var string|null */
	public $geo_cell;
	/** @var string|null */
	public $pickup_location_encrypted;
	/** @var string|null */
	public $transport_mode;
	/** @var int */
	public $pickup_possible = 0;
	/** @var int */
	public $urgency_level = 0;
	/** @var string */
	public $title = '';
	/** @var string|null */
	public $description_public;
	/** @var string|null */
	public $private_note_encrypted;
	/** @var string */
	public $visibility = 'private';
	/** @var int */
	public $verification_status = 0;
	/** @var int|null */
	public $date_expiration;

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
			'fk_campaign' => array('type' => 'integer:EmergencyHouseCampaign:emergencyhouse/class/campaign.class.php', 'label' => 'Campaign', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'index' => 1, 'position' => 20),
			'fk_account' => array('type' => 'integer', 'label' => 'PublicAccount', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'index' => 1, 'position' => 30),
			'adults_count' => array('type' => 'integer', 'label' => 'AdultsCount', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 40),
			'children_infant_count' => array('type' => 'integer', 'label' => 'ChildrenInfantCount', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 50),
			'children_young_count' => array('type' => 'integer', 'label' => 'ChildrenYoungCount', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 60),
			'children_teen_count' => array('type' => 'integer', 'label' => 'ChildrenTeenCount', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 70),
			'person_count' => array('type' => 'integer', 'label' => 'PersonCount', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 80),
			'remaining_count' => array('type' => 'integer', 'label' => 'RemainingCount', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 90),
			'group_divisible' => array('type' => 'boolean', 'label' => 'GroupDivisible', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 100),
			'minimum_group_size' => array('type' => 'integer', 'label' => 'MinimumGroupSize', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 110),
			'date_start' => array('type' => 'datetime', 'label' => 'DateStart', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 120),
			'date_end' => array('type' => 'datetime', 'label' => 'DateEnd', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 130),
			'duration_unknown' => array('type' => 'boolean', 'label' => 'DurationUnknown', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 140),
			'desired_zone' => array('type' => 'varchar(255)', 'label' => 'DesiredZone', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 150),
			'desired_zip' => array('type' => 'varchar(25)', 'label' => 'Zip', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 160),
			'desired_town' => array('type' => 'varchar(255)', 'label' => 'Town', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 170),
			'search_radius' => array('type' => 'integer', 'label' => 'SearchRadius', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 180),
			'geo_cell' => array('type' => 'varchar(32)', 'label' => 'GeoCell', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 190),
			'pickup_location_encrypted' => array('type' => 'text', 'label' => 'PickupLocation', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 200),
			'transport_mode' => array('type' => 'varchar(32)', 'label' => 'TransportMode', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 210),
			'pickup_possible' => array('type' => 'boolean', 'label' => 'PickupPossible', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 220),
			'urgency_level' => array('type' => 'integer', 'label' => 'UrgencyLevel', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 230),
			'title' => array('type' => 'varchar(255)', 'label' => 'Title', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 240),
			'description_public' => array('type' => 'text', 'label' => 'PublicDescription', 'enabled' => 1, 'visible' => 3, 'notnull' => 0, 'position' => 250),
			'private_note_encrypted' => array('type' => 'text', 'label' => 'PrivateNote', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 260),
			'visibility' => array('type' => 'varchar(16)', 'label' => 'Visibility', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 270),
			'verification_status' => array('type' => 'integer', 'label' => 'VerificationStatus', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 280),
			'date_expiration' => array('type' => 'datetime', 'label' => 'DateExpiration', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 290),
		));
	}

	/**
	 * Create request.
	 *
	 * @param User $user User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		$this->person_count = $this->calculatePersonCount();
		$this->remaining_count = $this->person_count;
		if (!$this->validateBusinessRules()) {
			return -1;
		}
		return parent::create($user, $notrigger);
	}

	/**
	 * Insert while a service owns the current transaction.
	 *
	 * @param User $user User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function createInsideServiceTransaction($user, $notrigger = 0)
	{
		$this->person_count = $this->calculatePersonCount();
		$this->remaining_count = $this->person_count;
		if (!$this->validateBusinessRules()) {
			return -1;
		}
		if (!$this->prepareCommonCreate($user)) {
			return -1;
		}
		return $this->insertPrepared($user, $notrigger);
	}

	/**
	 * Update request.
	 *
	 * @param User $user User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function update($user, $notrigger = 0)
	{
		$previousTotal = (int) $this->person_count;
		$newTotal = $this->calculatePersonCount();
		$allocated = max(0, $previousTotal - (int) $this->remaining_count);
		$this->person_count = $newTotal;
		$this->remaining_count = max(0, $newTotal - $allocated);
		if (!$this->validateBusinessRules()) {
			return -1;
		}
		return parent::update($user, $notrigger);
	}

	/**
	 * Update while a service owns the current transaction.
	 *
	 * @param User $user User
	 * @param int $notrigger Disable trigger
	 * @return int
	 */
	public function updateInsideServiceTransaction($user, $notrigger = 0)
	{
		$previousTotal = (int) $this->person_count;
		$newTotal = $this->calculatePersonCount();
		$allocated = max(0, $previousTotal - (int) $this->remaining_count);
		$this->person_count = $newTotal;
		$this->remaining_count = max(0, $newTotal - $allocated);
		if (!$this->validateBusinessRules() || !$this->prepareCommonUpdate($user)) {
			return -1;
		}
		return $this->updatePrepared($user, $notrigger);
	}

	/**
	 * Change status.
	 *
	 * @param int    $newStatus New status
	 * @param User   $user User
	 * @param string $reason Stable reason code
	 * @return int
	 */
	public function setStatus($newStatus, $user, $reason)
	{
		$allowed = array(
			self::STATUS_DRAFT => array(self::STATUS_ACTIVE, self::STATUS_CLOSED),
			self::STATUS_ACTIVE => array(self::STATUS_PARTIALLY_ALLOCATED, self::STATUS_FULFILLED, self::STATUS_SUSPENDED, self::STATUS_EXPIRED, self::STATUS_CLOSED),
			self::STATUS_PARTIALLY_ALLOCATED => array(self::STATUS_ACTIVE, self::STATUS_FULFILLED, self::STATUS_SUSPENDED, self::STATUS_EXPIRED, self::STATUS_CLOSED),
			self::STATUS_FULFILLED => array(self::STATUS_ACTIVE, self::STATUS_CLOSED),
			self::STATUS_SUSPENDED => array(self::STATUS_ACTIVE, self::STATUS_CLOSED),
			self::STATUS_EXPIRED => array(self::STATUS_ACTIVE, self::STATUS_CLOSED),
			self::STATUS_CLOSED => array(),
		);
		if (!isset($allowed[$this->status]) || !in_array($newStatus, $allowed[$this->status], true)) {
			$this->error = 'ErrorInvalidStatusTransition';
			return -1;
		}
		if ($newStatus === self::STATUS_ACTIVE && !$this->validateBusinessRules()) {
			$this->error = 'ErrorRequestCannotBePublished';
			return -1;
		}

		$oldStatus = (int) $this->status;
		$this->status = $newStatus;
		if ($oldStatus === self::STATUS_DRAFT && $newStatus === self::STATUS_ACTIVE) {
			$this->verification_status = 0;
		}
		$this->context['trigger_reason'] = $reason;
		$this->context['changed_fields'] = $oldStatus === self::STATUS_DRAFT && $newStatus === self::STATUS_ACTIVE
			? array('status', 'verification_status')
			: array('status');
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = $newStatus;

		$this->db->begin();
		dol_include_once('/emergencyhouse/class/verificationservice.class.php');
		$verificationService = new EmergencyHouseVerificationService($this->db);
		if (!$verificationService->lockSubmissionTarget((int) $this->entity, 'request', (int) $this->id)
			|| $this->updateInsideServiceTransaction($user) <= 0) {
			$this->error = $verificationService->error !== '' ? $verificationService->error : $this->error;
			$this->db->rollback();
			return -1;
		}
		$queueResult = $oldStatus === self::STATUS_DRAFT && $newStatus === self::STATUS_ACTIVE
			? $verificationService->enqueueTarget((int) $this->entity, 'request', (int) $this->id, dol_now(), false)
			: $verificationService->cancelTarget((int) $this->entity, 'request', (int) $this->id, false);
		if ($queueResult <= 0) {
			$this->error = $verificationService->error;
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		return 1;
	}

	/**
	 * Calculate people total.
	 *
	 * @return int
	 */
	public function calculatePersonCount()
	{
		return max(0, (int) $this->adults_count)
			+ max(0, (int) $this->children_infant_count)
			+ max(0, (int) $this->children_young_count)
			+ max(0, (int) $this->children_teen_count);
	}

	/**
	 * Validate invariants.
	 *
	 * @return bool
	 */
	public function validateBusinessRules()
	{
		$this->errors = array();
		if ($this->fk_campaign <= 0 || $this->fk_account <= 0) {
			$this->errors[] = 'ErrorMissingRequiredLink';
		}
		if ($this->person_count <= 0 || $this->remaining_count < 0 || $this->remaining_count > $this->person_count) {
			$this->errors[] = 'ErrorInvalidPersonCount';
		}
		if ($this->minimum_group_size <= 0 || $this->minimum_group_size > $this->person_count) {
			$this->errors[] = 'ErrorInvalidMinimumGroupSize';
		}
		if (empty($this->date_start) || (!$this->duration_unknown && !empty($this->date_end) && $this->date_end < $this->date_start)) {
			$this->errors[] = 'ErrorInvalidPeriod';
		}
		if (empty($this->desired_zone) || $this->search_radius <= 0 || $this->search_radius > 1000) {
			$this->errors[] = 'ErrorInvalidSearchArea';
		}
		if (dol_strlen(trim($this->title)) < 5 || dol_strlen($this->title) > 255) {
			$this->errors[] = 'ErrorInvalidTitle';
		}
		if ($this->description_public !== null && dol_strlen($this->description_public) > 5000) {
			$this->errors[] = 'ErrorPublicDescriptionTooLong';
		}
		if (!in_array($this->visibility, array('private', 'public'), true)) {
			$this->errors[] = 'ErrorInvalidVisibility';
		}
		if (!empty($this->errors)) {
			$this->error = $this->errors[0];
			return false;
		}
		return true;
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
			self::STATUS_DRAFT => array('StatusDraft', 'status0'),
			self::STATUS_ACTIVE => array('StatusActive', 'status4'),
			self::STATUS_PARTIALLY_ALLOCATED => array('StatusPartiallyAllocated', 'status1'),
			self::STATUS_FULFILLED => array('StatusFulfilled', 'status4'),
			self::STATUS_SUSPENDED => array('StatusSuspended', 'status5'),
			self::STATUS_EXPIRED => array('StatusExpired', 'status6'),
			self::STATUS_CLOSED => array('StatusClosed', 'status6'),
		);
		$definition = isset($labels[$status]) ? $labels[$status] : array('StatusUnknown', 'status0');
		return dolGetStatus($langs->trans($definition[0]), $langs->trans($definition[0]), '', $definition[1], $mode);
	}
}
