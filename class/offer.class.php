<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/emergencyhousecommonobject.class.php');

/**
 * Housing offer published by a public account.
 */
class EmergencyHouseOffer extends EmergencyHouseCommonObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_PENDING = 1;
	public const STATUS_PUBLISHED = 2;
	public const STATUS_SUSPENDED = 3;
	public const STATUS_EXPIRED = 4;
	public const STATUS_CLOSED = 5;
	public const STATUS_REJECTED = 6;

	/** @var string */
	public $element = 'offer';
	/** @var string */
	public $table_element = 'emergencyhouse_offer';
	/** @var string */
	public $picto = 'home';
	/** @var string */
	public $trigger_prefix = 'EMERGENCYHOUSE_OFFER';
	/** @var int */
	public $fk_campaign = 0;
	/** @var int */
	public $fk_account = 0;
	/** @var int */
	public $fk_housing_type = 0;
	/** @var string */
	public $address_encrypted = '';
	/** @var string */
	public $zip = '';
	/** @var string */
	public $town = '';
	/** @var int|null */
	public $fk_pays;
	/** @var int|null */
	public $fk_departement;
	/** @var string */
	public $public_zone = '';
	/** @var string */
	public $public_location_precision = 'town';
	/** @var string|null */
	public $latitude_encrypted;
	/** @var string|null */
	public $longitude_encrypted;
	/** @var string|null */
	public $geo_cell;
	/** @var int|null */
	public $date_start;
	/** @var int|null */
	public $date_end;
	/** @var int */
	public $capacity_total = 1;
	/** @var int */
	public $capacity_available = 1;
	/** @var int|null */
	public $max_adults;
	/** @var int|null */
	public $max_children;
	/** @var int */
	public $room_count = 0;
	/** @var int */
	public $bed_count = 0;
	/** @var int */
	public $extra_bed_count = 0;
	/** @var int */
	public $tent_count = 0;
	/** @var string */
	public $title = '';
	/** @var string|null */
	public $description_public;
	/** @var string|null */
	public $private_instructions_encrypted;
	/** @var int */
	public $minimum_stay_days = 0;
	/** @var int|null */
	public $maximum_stay_days;
	/** @var string|null */
	public $arrival_window;
	/** @var int */
	public $transport_available = 0;
	/** @var int */
	public $direct_solicitation_enabled = 1;
	/** @var int */
	public $verification_status = 0;
	/** @var int|null */
	public $date_last_confirmation;
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
			'fk_account' => array('type' => 'integer', 'label' => 'DepositedBy', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'index' => 1, 'position' => 30),
			'fk_housing_type' => array('type' => 'integer', 'label' => 'HousingType', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 40),
			'address_encrypted' => array('type' => 'text', 'label' => 'Address', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 50),
			'zip' => array('type' => 'varchar(25)', 'label' => 'Zip', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 60),
			'town' => array('type' => 'varchar(255)', 'label' => 'Town', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 70),
			'fk_pays' => array('type' => 'integer', 'label' => 'Country', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 80),
			'fk_departement' => array('type' => 'integer', 'label' => 'State', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 90),
			'public_zone' => array('type' => 'varchar(255)', 'label' => 'PublicZone', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 100),
			'public_location_precision' => array('type' => 'varchar(16)', 'label' => 'PublicLocationPrecision', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 110),
			'latitude_encrypted' => array('type' => 'text', 'label' => 'Latitude', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 120),
			'longitude_encrypted' => array('type' => 'text', 'label' => 'Longitude', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 130),
			'geo_cell' => array('type' => 'varchar(32)', 'label' => 'GeoCell', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 140),
			'date_start' => array('type' => 'datetime', 'label' => 'DateStart', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 150),
			'date_end' => array('type' => 'datetime', 'label' => 'DateEnd', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 160),
			'capacity_total' => array('type' => 'integer', 'label' => 'CapacityTotal', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 170),
			'capacity_available' => array('type' => 'integer', 'label' => 'CapacityAvailable', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 180),
			'max_adults' => array('type' => 'integer', 'label' => 'MaxAdults', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 190),
			'max_children' => array('type' => 'integer', 'label' => 'MaxChildren', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 200),
			'room_count' => array('type' => 'integer', 'label' => 'RoomCount', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 210),
			'bed_count' => array('type' => 'integer', 'label' => 'BedCount', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 220),
			'extra_bed_count' => array('type' => 'integer', 'label' => 'ExtraBedCount', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 230),
			'tent_count' => array('type' => 'integer', 'label' => 'TentCount', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 240),
			'title' => array('type' => 'varchar(255)', 'label' => 'Title', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 250),
			'description_public' => array('type' => 'text', 'label' => 'PublicDescription', 'enabled' => 1, 'visible' => 3, 'notnull' => 0, 'position' => 260),
			'private_instructions_encrypted' => array('type' => 'text', 'label' => 'PrivateInstructions', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 270),
			'minimum_stay_days' => array('type' => 'integer', 'label' => 'MinimumStayDays', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 280),
			'maximum_stay_days' => array('type' => 'integer', 'label' => 'MaximumStayDays', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 290),
			'arrival_window' => array('type' => 'varchar(255)', 'label' => 'ArrivalWindow', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 300),
			'transport_available' => array('type' => 'boolean', 'label' => 'TransportAvailable', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 310),
			'direct_solicitation_enabled' => array('type' => 'boolean', 'label' => 'DirectSolicitationEnabled', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 320),
			'verification_status' => array('type' => 'integer', 'label' => 'VerificationStatus', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 330),
			'date_last_confirmation' => array('type' => 'datetime', 'label' => 'DateLastConfirmation', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 340),
			'date_expiration' => array('type' => 'datetime', 'label' => 'DateExpiration', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 350),
		));
	}

	/**
	 * Create offer.
	 *
	 * @param User $user User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		if (!$this->validateBusinessRules()) {
			return -1;
		}
		$this->capacity_available = (int) $this->capacity_total;
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
		if (!$this->validateBusinessRules()) {
			return -1;
		}
		$this->capacity_available = (int) $this->capacity_total;
		if (!$this->prepareCommonCreate($user)) {
			return -1;
		}
		return $this->insertPrepared($user, $notrigger);
	}

	/**
	 * Update offer.
	 *
	 * @param User $user User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function update($user, $notrigger = 0)
	{
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
		if (!$this->validateBusinessRules() || !$this->prepareCommonUpdate($user)) {
			return -1;
		}
		return $this->updatePrepared($user, $notrigger);
	}

	/**
	 * Change status while keeping one CRUD trigger.
	 *
	 * @param int    $newStatus New status
	 * @param User   $user User
	 * @param string $reason Stable reason code
	 * @return int
	 */
	public function setStatus($newStatus, $user, $reason)
	{
		$allowed = array(
			self::STATUS_DRAFT => array(self::STATUS_PENDING, self::STATUS_CLOSED),
			self::STATUS_PENDING => array(self::STATUS_PUBLISHED, self::STATUS_REJECTED, self::STATUS_CLOSED),
			self::STATUS_PUBLISHED => array(self::STATUS_SUSPENDED, self::STATUS_EXPIRED, self::STATUS_CLOSED),
			self::STATUS_SUSPENDED => array(self::STATUS_PUBLISHED, self::STATUS_CLOSED),
			self::STATUS_EXPIRED => array(self::STATUS_PUBLISHED, self::STATUS_CLOSED),
			self::STATUS_CLOSED => array(),
			self::STATUS_REJECTED => array(self::STATUS_DRAFT, self::STATUS_CLOSED),
		);
		if (!isset($allowed[$this->status]) || !in_array($newStatus, $allowed[$this->status], true)) {
			$this->error = 'ErrorInvalidStatusTransition';
			return -1;
		}
		if ($newStatus === self::STATUS_PUBLISHED && ($this->verification_status <= 0 || !$this->validateBusinessRules())) {
			$this->error = 'ErrorOfferCannotBePublished';
			return -1;
		}

		$oldStatus = (int) $this->status;
		$this->status = $newStatus;
		$this->context['trigger_reason'] = $reason;
		$this->context['changed_fields'] = array('status');
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = $newStatus;
		if ($newStatus === self::STATUS_PUBLISHED) {
			$this->date_last_confirmation = dol_now();
		}

		return parent::update($user);
	}

	/**
	 * Validate invariants.
	 *
	 * @return bool
	 */
	public function validateBusinessRules()
	{
		$this->errors = array();
		if ($this->fk_campaign <= 0 || $this->fk_account <= 0 || $this->fk_housing_type <= 0) {
			$this->errors[] = 'ErrorMissingRequiredLink';
		}
		if ($this->capacity_total <= 0 || $this->capacity_available < 0 || $this->capacity_available > $this->capacity_total) {
			$this->errors[] = 'ErrorInvalidCapacity';
		}
		if (empty($this->address_encrypted) || empty($this->zip) || empty($this->town) || empty($this->public_zone)) {
			$this->errors[] = 'ErrorInvalidAddress';
		}
		if (dol_strlen(trim($this->title)) < 5 || dol_strlen($this->title) > 255) {
			$this->errors[] = 'ErrorInvalidTitle';
		}
		if ($this->description_public !== null && dol_strlen($this->description_public) > 5000) {
			$this->errors[] = 'ErrorPublicDescriptionTooLong';
		}
		if (empty($this->date_start) || (!empty($this->date_end) && $this->date_end < $this->date_start)) {
			$this->errors[] = 'ErrorInvalidPeriod';
		}
		if ($this->minimum_stay_days < 0 || (!empty($this->maximum_stay_days) && $this->maximum_stay_days < $this->minimum_stay_days)) {
			$this->errors[] = 'ErrorInvalidStayDuration';
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
			self::STATUS_PENDING => array('StatusPendingValidation', 'status1'),
			self::STATUS_PUBLISHED => array('StatusPublished', 'status4'),
			self::STATUS_SUSPENDED => array('StatusSuspended', 'status5'),
			self::STATUS_EXPIRED => array('StatusExpired', 'status6'),
			self::STATUS_CLOSED => array('StatusClosed', 'status6'),
			self::STATUS_REJECTED => array('StatusRejected', 'status8'),
		);
		$definition = isset($labels[$status]) ? $labels[$status] : array('StatusUnknown', 'status0');
		return dolGetStatus($langs->trans($definition[0]), $langs->trans($definition[0]), '', $definition[1], $mode);
	}
}
