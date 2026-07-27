<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/emergencyhousecommonobject.class.php');

/**
 * Emergency campaign.
 */
class EmergencyHouseCampaign extends EmergencyHouseCommonObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_PUBLISHED = 1;
	public const STATUS_SUSPENDED = 2;
	public const STATUS_CLOSED = 3;
	public const STATUS_ARCHIVED = 4;

	/** @var string */
	public $element = 'campaign';
	/** @var string */
	public $table_element = 'emergencyhouse_campaign';
	/** @var string */
	public $picto = 'fontawesome_house-user';
	/** @var string */
	public $trigger_prefix = 'EMERGENCYHOUSE_CAMPAIGN';
	/** @var string */
	public $label = '';
	/** @var string */
	public $slug = '';
	/** @var int|null */
	public $fk_campaign_type;
	/** @var string|null */
	public $description_public;
	/** @var string|null */
	public $official_instructions;
	/** @var string */
	public $coordinator_name = '';
	/** @var string */
	public $official_phone = '';
	/** @var string|null */
	public $official_email;
	/** @var int|null */
	public $date_start;
	/** @var int|null */
	public $date_end;
	/** @var string */
	public $timezone = 'Europe/Paris';
	/** @var string */
	public $public_visibility_mode = 'offers';
	/** @var string */
	public $verification_policy = 'operator_validation';
	/** @var int */
	public $default_radius = 50;
	/** @var string|null */
	public $matching_config_snapshot;
	/** @var string */
	public $matching_config_version = '1';
	/** @var int */
	public $retention_days = 90;
	/** @var string */
	public $consent_version = '1';
	/** @var string|null */
	public $banner_text;
	/** @var string|null */
	public $eligibility_text;
	/** @var string */
	public $privacy_url = '';
	/** @var string */
	public $terms_url = '';
	/** @var int */
	public $robots_index = 0;
	/** @var int|null */
	public $date_publication;
	/** @var int|null */
	public $date_closure;
	/** @var int|null */
	public $date_archive;
	/** @var int|null */
	public $date_purge_planned;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->fields = array_merge(self::commonFields(), array(
			'label' => array('type' => 'varchar(255)', 'label' => 'CampaignLabel', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 20),
			'slug' => array('type' => 'varchar(191)', 'label' => 'CampaignSlug', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'index' => 1, 'position' => 30),
			'fk_campaign_type' => array('type' => 'integer', 'label' => 'CampaignType', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 40),
			'description_public' => array('type' => 'text', 'label' => 'CampaignDescription', 'enabled' => 1, 'visible' => 3, 'notnull' => 0, 'position' => 50),
			'official_instructions' => array('type' => 'text', 'label' => 'OfficialInstructions', 'enabled' => 1, 'visible' => 3, 'notnull' => 0, 'position' => 60),
			'coordinator_name' => array('type' => 'varchar(255)', 'label' => 'CoordinatorName', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 70),
			'official_phone' => array('type' => 'varchar(64)', 'label' => 'OfficialPhone', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 80),
			'official_email' => array('type' => 'varchar(255)', 'label' => 'OfficialEmail', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 90),
			'date_start' => array('type' => 'datetime', 'label' => 'DateStart', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 100),
			'date_end' => array('type' => 'datetime', 'label' => 'DateEnd', 'enabled' => 1, 'visible' => 1, 'notnull' => 0, 'position' => 110),
			'timezone' => array('type' => 'varchar(64)', 'label' => 'Timezone', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 120),
			'public_visibility_mode' => array('type' => 'varchar(32)', 'label' => 'PublicVisibilityMode', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 130),
			'verification_policy' => array('type' => 'varchar(32)', 'label' => 'VerificationPolicy', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 140),
			'default_radius' => array('type' => 'integer', 'label' => 'DefaultRadius', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 150),
			'matching_config_snapshot' => array('type' => 'text', 'label' => 'MatchingConfiguration', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 160),
			'matching_config_version' => array('type' => 'varchar(32)', 'label' => 'MatchingConfigurationVersion', 'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 170),
			'retention_days' => array('type' => 'integer', 'label' => 'RetentionDays', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 180),
			'consent_version' => array('type' => 'varchar(64)', 'label' => 'ConsentVersion', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 190),
			'banner_text' => array('type' => 'text', 'label' => 'BannerText', 'enabled' => 1, 'visible' => 3, 'notnull' => 0, 'position' => 200),
			'eligibility_text' => array('type' => 'text', 'label' => 'EligibilityText', 'enabled' => 1, 'visible' => 3, 'notnull' => 0, 'position' => 210),
			'privacy_url' => array('type' => 'varchar(1024)', 'label' => 'PrivacyUrl', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 220),
			'terms_url' => array('type' => 'varchar(1024)', 'label' => 'TermsUrl', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 230),
			'robots_index' => array('type' => 'boolean', 'label' => 'RobotsIndex', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 240),
			'date_publication' => array('type' => 'datetime', 'label' => 'DatePublication', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 250),
			'date_closure' => array('type' => 'datetime', 'label' => 'DateClosure', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 260),
			'date_archive' => array('type' => 'datetime', 'label' => 'DateArchive', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 270),
			'date_purge_planned' => array('type' => 'datetime', 'label' => 'DatePurgePlanned', 'enabled' => 1, 'visible' => -2, 'notnull' => 0, 'position' => 280),
		));
	}

	/**
	 * Create with a generated reference and a stable public slug.
	 *
	 * @param User $user User
	 * @param int $notrigger Disable trigger
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;
		if (empty($this->date_creation)) {
			$this->date_creation = dol_now();
		}
		if (empty($this->ref)) {
			$nextRef = $this->getNextNumRef();
			if (!is_string($nextRef) || $nextRef === '') {
				return -1;
			}
			$this->ref = $nextRef;
		}
		if (empty($this->slug)) {
			$this->slug = dol_sanitizeFileName(strtolower($this->ref));
		}

		return parent::create($user, $notrigger);
	}

	/**
	 * Change campaign status.
	 *
	 * @param int    $newStatus New status
	 * @param User   $user User
	 * @param string $reason Stable reason code
	 * @return int
	 */
	public function setStatus($newStatus, $user, $reason)
	{
		$allowed = array(
			self::STATUS_DRAFT => array(self::STATUS_PUBLISHED),
			self::STATUS_PUBLISHED => array(self::STATUS_SUSPENDED, self::STATUS_CLOSED),
			self::STATUS_SUSPENDED => array(self::STATUS_PUBLISHED, self::STATUS_CLOSED),
			self::STATUS_CLOSED => array(self::STATUS_ARCHIVED),
			self::STATUS_ARCHIVED => array(),
		);
		if (!isset($allowed[$this->status]) || !in_array($newStatus, $allowed[$this->status], true)) {
			$this->error = 'ErrorInvalidStatusTransition';
			return -1;
		}
		if ($newStatus === self::STATUS_PUBLISHED && !$this->canBePublished()) {
			$this->error = 'ErrorCampaignCannotBePublished';
			return -1;
		}

		$oldStatus = (int) $this->status;
		$this->status = $newStatus;
		$this->context['trigger_reason'] = $reason;
		$this->context['changed_fields'] = array('status');
		$this->context['old_status'] = $oldStatus;
		$this->context['new_status'] = $newStatus;
		if ($newStatus === self::STATUS_PUBLISHED) $this->date_publication = dol_now();
		if ($newStatus === self::STATUS_CLOSED) $this->date_closure = dol_now();
		if ($newStatus === self::STATUS_ARCHIVED) {
			$this->date_archive = dol_now();
			$this->date_purge_planned = dol_time_plus_duree(dol_now(), $this->retention_days, 'd');
		}

		return $this->update($user);
	}

	/**
	 * Validate publication requirements.
	 *
	 * @return bool
	 */
	public function canBePublished()
	{
		return !empty($this->label)
			&& !empty($this->coordinator_name)
			&& !empty($this->official_phone)
			&& !empty($this->privacy_url)
			&& !empty($this->terms_url)
			&& !empty($this->consent_version)
			&& !empty($this->date_start);
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
			self::STATUS_DRAFT => array('StatusDraft', 0),
			self::STATUS_PUBLISHED => array('StatusPublished', 4),
			self::STATUS_SUSPENDED => array('StatusSuspended', 5),
			self::STATUS_CLOSED => array('StatusClosed', 6),
			self::STATUS_ARCHIVED => array('StatusArchived', 9),
		);
		$definition = isset($labels[$status]) ? $labels[$status] : array('StatusUnknown', 0);
		return dolGetStatus($langs->trans($definition[0]), $langs->trans($definition[0]), '', $definition[1], $mode);
	}
}
