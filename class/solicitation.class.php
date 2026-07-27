<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/emergencyhousecommonobject.class.php');

/**
 * Contact solicitation between a housing offer and an accommodation request.
 */
class EmergencyHouseSolicitation extends EmergencyHouseCommonObject
{
	public const STATUS_PENDING = 0;
	public const STATUS_ACCEPTED = 1;
	public const STATUS_REFUSED = 2;
	public const STATUS_CANCELLED = 3;
	public const STATUS_EXPIRED = 4;
	public const STATUS_CLOSED = 5;

	/** @var string */
	public $element = 'solicitation';
	/** @var string */
	public $table_element = 'emergencyhouse_solicitation';
	/** @var string */
	public $picto = 'comment';
	/** @var string */
	public $trigger_prefix = 'EMERGENCYHOUSE_SOLICITATION';
	/** @var int */
	public $fk_campaign = 0;
	/** @var int */
	public $fk_offer = 0;
	/** @var int */
	public $fk_request = 0;
	/** @var int|null */
	public $fk_match;
	/** @var int|null */
	public $fk_initiator_account;
	/** @var int|null */
	public $fk_initiator_user;
	/** @var string */
	public $initiator_direction = 'request_to_offer';
	/** @var string|null */
	public $initial_message_encrypted;
	/** @var string|null */
	public $confirmed_gaps_snapshot;
	/** @var string|null */
	public $refusal_reason;
	/** @var string|null */
	public $cancellation_reason;
	/** @var int */
	public $initiator_contact_consent = 1;
	/** @var int */
	public $recipient_contact_consent = 0;
	/** @var int */
	public $address_share_authorized = 0;
	/** @var int|null */
	public $date_read;
	/** @var int|null */
	public $date_response;
	/** @var int|null */
	public $date_contact_revealed;
	/** @var int|null */
	public $date_address_revealed;
	/** @var int|null */
	public $date_closure;
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
		$common = self::commonFields();
		foreach (array('import_key', 'note_public', 'note_private', 'model_pdf', 'last_main_doc') as $unusedField) {
			unset($common[$unusedField]);
		}
		$this->fields = array_merge($common, array(
			'public_uuid' => array('type' => 'varchar(64)', 'label' => 'PublicUuid', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 12),
			'fk_campaign' => array('type' => 'integer:EmergencyHouseCampaign:emergencyhouse/class/campaign.class.php', 'label' => 'Campaign', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'index' => 1, 'position' => 20),
			'fk_offer' => array('type' => 'integer:EmergencyHouseOffer:emergencyhouse/class/offer.class.php', 'label' => 'Offer', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'index' => 1, 'position' => 30),
			'fk_request' => array('type' => 'integer:EmergencyHouseRequest:emergencyhouse/class/request.class.php', 'label' => 'Request', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'index' => 1, 'position' => 40),
			'fk_match' => array('type' => 'integer', 'label' => 'Match', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 50),
			'fk_initiator_account' => array('type' => 'integer', 'label' => 'PublicAccount', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 60),
			'fk_initiator_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 70),
			'initiator_direction' => array('type' => 'varchar(16)', 'label' => 'SolicitationDirection', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 80),
			'initial_message_encrypted' => array('type' => 'text', 'label' => 'InitialMessage', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 90),
			'confirmed_gaps_snapshot' => array('type' => 'text', 'label' => 'ConfirmedGaps', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 100),
			'refusal_reason' => array('type' => 'varchar(64)', 'label' => 'RefusalReason', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 110),
			'cancellation_reason' => array('type' => 'varchar(64)', 'label' => 'CancellationReason', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 120),
			'initiator_contact_consent' => array('type' => 'boolean', 'label' => 'InitiatorContactConsent', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 130),
			'recipient_contact_consent' => array('type' => 'boolean', 'label' => 'RecipientContactConsent', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 140),
			'address_share_authorized' => array('type' => 'boolean', 'label' => 'AddressShareAuthorized', 'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 150),
			'date_read' => array('type' => 'datetime', 'label' => 'DateRead', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 160),
			'date_response' => array('type' => 'datetime', 'label' => 'DateResponse', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 170),
			'date_contact_revealed' => array('type' => 'datetime', 'label' => 'DateContactRevealed', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 180),
			'date_address_revealed' => array('type' => 'datetime', 'label' => 'DateAddressRevealed', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 190),
			'date_closure' => array('type' => 'datetime', 'label' => 'DateClosure', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 200),
			'date_expiration' => array('type' => 'datetime', 'label' => 'DateExpiration', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 210),
		));
	}

	/**
	 * Create solicitation.
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
		return parent::create($user, $notrigger);
	}

	/**
	 * Insert while the solicitation service owns the transaction.
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
		if (!$this->prepareCommonCreate($user)) {
			return -1;
		}
		return $this->insertPrepared($user, $notrigger);
	}

	/**
	 * Validate solicitation invariants.
	 *
	 * @return bool
	 */
	private function validateBusinessRules()
	{
		if ($this->fk_campaign <= 0 || $this->fk_offer <= 0 || $this->fk_request <= 0) {
			$this->error = 'ErrorMissingRequiredLink';
			return false;
		}
		if (!in_array($this->initiator_direction, array('request_to_offer', 'offer_to_request', 'operator'), true)) {
			$this->error = 'ErrorInvalidSolicitationDirection';
			return false;
		}
		return true;
	}

	/**
	 * Apply an explicit transition.
	 *
	 * @param int         $newStatus New status
	 * @param User        $user User
	 * @param string      $reason Stable reason
	 * @param string|null $reasonCode Dictionary code
	 * @return int
	 */
	public function setStatus($newStatus, $user, $reason, $reasonCode = null)
	{
		$allowed = array(
			self::STATUS_PENDING => array(self::STATUS_ACCEPTED, self::STATUS_REFUSED, self::STATUS_CANCELLED, self::STATUS_EXPIRED),
			self::STATUS_ACCEPTED => array(self::STATUS_CANCELLED, self::STATUS_CLOSED),
			self::STATUS_REFUSED => array(self::STATUS_CLOSED),
			self::STATUS_CANCELLED => array(self::STATUS_CLOSED),
			self::STATUS_EXPIRED => array(self::STATUS_CLOSED),
			self::STATUS_CLOSED => array(),
		);
		if (!isset($allowed[$this->status]) || !in_array($newStatus, $allowed[$this->status], true)) {
			$this->error = 'ErrorInvalidStatusTransition';
			return -1;
		}
		if ($newStatus === self::STATUS_REFUSED && empty($reasonCode)) {
			$this->error = 'ErrorRefusalReasonRequired';
			return -1;
		}
		if ($newStatus === self::STATUS_CANCELLED && empty($reasonCode)) {
			$this->error = 'ErrorCancellationReasonRequired';
			return -1;
		}

		$oldStatus = (int) $this->status;
		$this->status = $newStatus;
		$this->date_response = dol_now();
		if ($newStatus === self::STATUS_ACCEPTED) {
			$this->recipient_contact_consent = 1;
		}
		if ($newStatus === self::STATUS_REFUSED) {
			$this->refusal_reason = $reasonCode;
		}
		if ($newStatus === self::STATUS_CANCELLED) {
			$this->cancellation_reason = $reasonCode;
		}
		if (in_array($newStatus, array(self::STATUS_REFUSED, self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_CLOSED), true)) {
			$this->date_closure = dol_now();
		}
		$this->context['trigger_reason'] = $reason;
		$this->context['changed_fields'] = array('status');
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = $newStatus;
		return parent::update($user);
	}

	/**
	 * Contact data may be revealed only after mutual consent.
	 *
	 * @return bool
	 */
	public function canRevealContact()
	{
		return $this->status === self::STATUS_ACCEPTED
			&& !empty($this->initiator_contact_consent)
			&& !empty($this->recipient_contact_consent);
	}

	/**
	 * Exact address additionally needs a dedicated authorization.
	 *
	 * @return bool
	 */
	public function canRevealAddress()
	{
		return $this->canRevealContact() && !empty($this->address_share_authorized);
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
			self::STATUS_PENDING => array('StatusPending', 1),
			self::STATUS_ACCEPTED => array('StatusAccepted', 4),
			self::STATUS_REFUSED => array('StatusRefused', 8),
			self::STATUS_CANCELLED => array('StatusCancelled', 6),
			self::STATUS_EXPIRED => array('StatusExpired', 6),
			self::STATUS_CLOSED => array('StatusClosed', 6),
		);
		$definition = isset($labels[$status]) ? $labels[$status] : array('StatusUnknown', 0);
		return dolGetStatus($langs->trans($definition[0]), $langs->trans($definition[0]), '', $definition[1], $mode);
	}
}
