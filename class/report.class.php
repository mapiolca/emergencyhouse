<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/emergencyhousecommonobject.class.php');

/**
 * Safety or moderation report.
 */
class EmergencyHouseReport extends EmergencyHouseCommonObject
{
	public const STATUS_OPEN = 0;
	public const STATUS_IN_REVIEW = 1;
	public const STATUS_RESOLVED = 2;
	public const STATUS_DISMISSED = 3;

	/** @var string */
	public $element = 'report';
	/** @var string */
	public $table_element = 'emergencyhouse_report';
	/** @var string */
	public $picto = 'warning';
	/** @var string */
	public $trigger_prefix = 'EMERGENCYHOUSE_REPORT';
	/** @var int */
	public $fk_campaign = 0;
	/** @var string */
	public $object_type = '';
	/** @var int */
	public $fk_object = 0;
	/** @var int|null */
	public $fk_reporter_account;
	/** @var int|null */
	public $fk_reporter_user;
	/** @var int */
	public $fk_report_reason = 0;
	/** @var int */
	public $severity = 1;
	/** @var string|null */
	public $description_encrypted;
	/** @var string|null */
	public $private_notes_encrypted;
	/** @var int|null */
	public $fk_assigned_user;
	/** @var int */
	public $retention_hold = 0;
	/** @var string|null */
	public $retention_hold_reason;
	/** @var int|null */
	public $date_closure;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$common = self::commonFields();
		foreach (array('fk_user_creat', 'fk_user_modif', 'import_key', 'note_public', 'note_private', 'model_pdf', 'last_main_doc') as $unusedField) {
			unset($common[$unusedField]);
		}
		$this->fields = array_merge($common, array(
			'public_uuid' => array('type' => 'varchar(64)', 'label' => 'PublicUuid', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 12),
			'fk_campaign' => array('type' => 'integer:EmergencyHouseCampaign:emergencyhouse/class/campaign.class.php', 'label' => 'Campaign', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 20),
			'object_type' => array('type' => 'varchar(64)', 'label' => 'ObjectType', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 30),
			'fk_object' => array('type' => 'integer', 'label' => 'LinkedObject', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 40),
			'fk_reporter_account' => array('type' => 'integer', 'label' => 'PublicAccount', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 50),
			'fk_reporter_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 60),
			'fk_report_reason' => array('type' => 'integer', 'label' => 'ReportReason', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 70),
			'severity' => array('type' => 'integer', 'label' => 'Severity', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 80),
			'description_encrypted' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 90),
			'private_notes_encrypted' => array('type' => 'text', 'label' => 'PrivateNote', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 100),
			'fk_assigned_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AssignedTo', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 110),
			'retention_hold' => array('type' => 'boolean', 'label' => 'RetentionHold', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 120),
			'retention_hold_reason' => array('type' => 'varchar(255)', 'label' => 'RetentionHoldReason', 'enabled' => 1, 'visible' => -1, 'notnull' => 0, 'position' => 130),
			'date_closure' => array('type' => 'datetime', 'label' => 'DateClosure', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 140),
		));
	}

	/**
	 * Create report.
	 *
	 * @param User $user User
	 * @param int  $notrigger Disable trigger
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		if ($this->fk_campaign <= 0 || $this->fk_object <= 0 || $this->fk_report_reason <= 0 || empty($this->object_type)) {
			$this->error = 'ErrorMissingRequiredLink';
			return -1;
		}
		if ($this->severity < 1 || $this->severity > 5) {
			$this->error = 'ErrorInvalidSeverity';
			return -1;
		}
		return parent::create($user, $notrigger);
	}

	/**
	 * Change moderation state.
	 *
	 * @param int    $newStatus New status
	 * @param User   $user User
	 * @param string $reason Stable reason
	 * @return int
	 */
	public function setStatus($newStatus, $user, $reason)
	{
		$allowed = array(
			self::STATUS_OPEN => array(self::STATUS_IN_REVIEW, self::STATUS_RESOLVED, self::STATUS_DISMISSED),
			self::STATUS_IN_REVIEW => array(self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_DISMISSED),
			self::STATUS_RESOLVED => array(self::STATUS_IN_REVIEW),
			self::STATUS_DISMISSED => array(self::STATUS_IN_REVIEW),
		);
		if (!isset($allowed[$this->status]) || !in_array($newStatus, $allowed[$this->status], true)) {
			$this->error = 'ErrorInvalidStatusTransition';
			return -1;
		}

		$oldStatus = (int) $this->status;
		$this->status = $newStatus;
		$this->date_closure = in_array($newStatus, array(self::STATUS_RESOLVED, self::STATUS_DISMISSED), true) ? dol_now() : null;
		$this->context['trigger_reason'] = $reason;
		$this->context['changed_fields'] = array('status');
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = $newStatus;
		return parent::update($user);
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
			self::STATUS_OPEN => array('StatusOpen', 'status1'),
			self::STATUS_IN_REVIEW => array('StatusInReview', 'status3'),
			self::STATUS_RESOLVED => array('StatusResolved', 'status4'),
			self::STATUS_DISMISSED => array('StatusDismissed', 'status6'),
		);
		$definition = isset($labels[$status]) ? $labels[$status] : array('StatusUnknown', 'status0');
		return dolGetStatus($langs->trans($definition[0]), $langs->trans($definition[0]), '', $definition[1], $mode);
	}
}
