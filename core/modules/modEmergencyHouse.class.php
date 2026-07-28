<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        core/modules/modEmergencyHouse.class.php
 * \ingroup     emergencyhouse
 * \brief       Descriptor of the Emergency House module.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');

/**
 * Module descriptor.
 */
class modEmergencyHouse extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;
		$this->numero = 450201;
		$this->rights_class = 'emergencyhouse';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = 500;
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'EmergencyHouseModuleDescription';
		$this->descriptionlong = 'EmergencyHouseModuleDescriptionLong';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fontawesome_house-user';
		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = 'https://lesmetiersdubatiment.fr';
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);
		$this->langfiles = array('emergencyhouse@emergencyhouse');
		$this->depends = array('modAdherent');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->config_page_url = array('setup.php@emergencyhouse');
		$this->hidden = false;
		$this->always_enabled = false;
		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array(
				'data' => array(
					'notification',
					'multicompanyexternalmodulesharing',
					'multicompanyexternalmodules',
					'multicompanysharingoptions',
				),
				'entity' => '0',
			),
			'substitutions' => 1,
			'models' => 1,
		);
		$this->dirs = array('/emergencyhouse/temp');
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->const = array();

		$this->cronjobs = array(
			array(
				'label' => 'EmergencyHouseCronNotifications',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'processNotificationQueue',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronNotificationsDescription',
				'frequency' => 5,
				'unitfrequency' => 60,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 40,
			),
			array(
				'label' => 'EmergencyHouseCronMatching',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'processMatchingQueue',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronMatchingDescription',
				'frequency' => 5,
				'unitfrequency' => 60,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 45,
			),
			array(
				'label' => 'EmergencyHouseCronExpiry',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'expireRecords',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronExpiryDescription',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 50,
			),
			array(
				'label' => 'EmergencyHouseCronAvailability',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'requestAvailabilityConfirmations',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronAvailabilityDescription',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 55,
			),
			array(
				'label' => 'EmergencyHouseCronStayReminders',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'sendStayReminders',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronStayRemindersDescription',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 60,
			),
			array(
				'label' => 'EmergencyHouseCronCloseAllocations',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'closeEndedAllocations',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronCloseAllocationsDescription',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 65,
			),
			array(
				'label' => 'EmergencyHouseCronStatistics',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'buildDailyStatistics',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronStatisticsDescription',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 70,
			),
			array(
				'label' => 'EmergencyHouseCronRetention',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'applyRetention',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronRetentionDescription',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 75,
			),
			array(
				'label' => 'EmergencyHouseCronProviderHealth',
				'jobtype' => 'method',
				'class' => '/emergencyhouse/class/emergencyhousecron.class.php',
				'objectname' => 'EmergencyHouseCron',
				'method' => 'checkProviders',
				'parameters' => '',
				'comment' => 'EmergencyHouseCronProviderHealthDescription',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 1,
				'test' => 'isModEnabled("emergencyhouse")',
				'priority' => 80,
			),
		);

		$r = 0;
		$this->addRight(++$r, 'EmergencyHouseRightCampaignRead', 'campaign', 'read', true);
		$this->addRight(++$r, 'EmergencyHouseRightCampaignWrite', 'campaign', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightCampaignPublish', 'campaign', 'publish');
		$this->addRight(++$r, 'EmergencyHouseRightListingRead', 'listing', 'read', true);
		$this->addRight(++$r, 'EmergencyHouseRightListingWrite', 'listing', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightVerification', 'verification', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightMatchRead', 'match', 'read');
		$this->addRight(++$r, 'EmergencyHouseRightMatchWrite', 'match', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightSolicitation', 'solicitation', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightAllocation', 'allocation', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightRevealContact', 'sensitive', 'contact');
		$this->addRight(++$r, 'EmergencyHouseRightRevealAddress', 'sensitive', 'address');
		$this->addRight(++$r, 'EmergencyHouseRightModeration', 'moderation', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightReport', 'report', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightStatistics', 'statistics', 'read');
		$this->addRight(++$r, 'EmergencyHouseRightExportAnonymous', 'export', 'anonymous');
		$this->addRight(++$r, 'EmergencyHouseRightExportPersonal', 'export', 'personal');
		$this->addRight(++$r, 'EmergencyHouseRightAudit', 'audit', 'read');
		$this->addRight(++$r, 'EmergencyHouseRightConfigure', 'configuration', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightDictionaries', 'dictionary', 'write');
		$this->addRight(++$r, 'EmergencyHouseRightApi', 'api', 'use');

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'EmergencyHouse',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'emergencyhouse',
			'leftmenu' => '',
			'url' => '/emergencyhouse/index.php',
			'langs' => 'emergencyhouse@emergencyhouse',
			'position' => 1000,
			'enabled' => 'isModEnabled("emergencyhouse")',
			'perms' => '$user->hasRight(\'emergencyhouse\', \'campaign\', \'read\')',
			'target' => '',
			'user' => 2,
		);
		$this->addLeftMenu($r, 'EmergencyHouseDashboard', '/emergencyhouse/index.php', 'campaign', 'read', 10);
		$this->addLeftMenu($r, 'Campaigns', '/emergencyhouse/campaign/list.php', 'campaign', 'read', 20);
		$this->addLeftMenu($r, 'Offers', '/emergencyhouse/offer/list.php', 'listing', 'read', 30);
		$this->addLeftMenu($r, 'Requests', '/emergencyhouse/request/list.php', 'listing', 'read', 40);
		$this->addLeftMenu($r, 'Matches', '/emergencyhouse/match/list.php', 'match', 'read', 50);
		$this->addLeftMenu($r, 'Solicitations', '/emergencyhouse/solicitation/list.php', 'solicitation', 'write', 60);
		$this->addLeftMenu($r, 'AllocationsAndStays', '/emergencyhouse/allocation/list.php', 'allocation', 'write', 70);
		$this->addLeftMenu($r, 'Verifications', '/emergencyhouse/verification/list.php', 'verification', 'write', 80);
		$this->addLeftMenu($r, 'Reports', '/emergencyhouse/report/list.php', 'report', 'write', 90);
		$this->addLeftMenu($r, 'Statistics', '/emergencyhouse/statistics/index.php', 'statistics', 'read', 100);
		$this->addLeftMenu($r, 'EmergencyHouseConfiguration', '/emergencyhouse/admin/setup.php', 'configuration', 'write', 110);
	}

	/**
	 * Add a module right with the mandatory stable formula.
	 *
	 * @param int    $r          Right offset
	 * @param string $label      Translation key
	 * @param string $object     Right object
	 * @param string $action     Right action
	 * @param bool   $default    Granted by default
	 * @return void
	 */
	private function addRight($r, $label, $object, $action, $default = false)
	{
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = $label;
		$this->rights[$r][3] = $default ? 1 : 0;
		$this->rights[$r][4] = $object;
		$this->rights[$r][5] = $action;
	}

	/**
	 * Add a left menu.
	 *
	 * @param int    $r        Menu counter
	 * @param string $title    Translation key
	 * @param string $url      URL
	 * @param string $object   Right object
	 * @param string $action   Right action
	 * @param int    $position Position
	 * @return void
	 */
	private function addLeftMenu(&$r, $title, $url, $object, $action, $position)
	{
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=emergencyhouse',
			'type' => 'left',
			'titre' => $title,
			'mainmenu' => 'emergencyhouse',
			'leftmenu' => 'emergencyhouse_'.$object,
			'url' => $url,
			'langs' => 'emergencyhouse@emergencyhouse',
			'position' => $position,
			'enabled' => 'isModEnabled("emergencyhouse")',
			'perms' => '$user->hasRight(\'emergencyhouse\', \''.$object.'\', \''.$action.'\')',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Enable module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		global $conf;

		$result = $this->_load_tables('/emergencyhouse/sql/');
		if ($result < 0) {
			return -1;
		}

		if (!$this->ensureSecurityKeys()) {
			return -1;
		}
		if (!$this->ensureMemberLinkSchema()) {
			return -1;
		}
		$this->ensureDefaultConstants((int) $conf->entity);
		if (!$this->migrateLegacyNumberingModels((int) $conf->entity)) {
			return -1;
		}
		if (!$this->ensureEntityDefaults((int) $conf->entity)) {
			return -1;
		}
		$this->mergeMulticompanyDefinition((int) $conf->entity);

		$sql = array(
			"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity)"
				." SELECT 'emergencyhouse_agreement', 'emergencyhouse', ".((int) $conf->entity)
				." WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."document_model"
				." WHERE nom = 'emergencyhouse_agreement' AND type = 'emergencyhouse'"
				." AND entity = ".((int) $conf->entity).")",
			$this->buildNotificationTriggerModuleUpdateSql(),
		);
		$sql[] = $this->buildCronStatusUpdateSql(1, (int) $conf->entity);

		return $this->_init($sql, $options);
	}

	/**
	 * Create the two global security keys once.
	 *
	 * dolibarr_set_const() encrypts values whose names end in "_KEY" with the
	 * Dolibarr instance key. Existing values and legacy environment-based
	 * deployments are preserved.
	 *
	 * @return bool
	 */
	private function ensureSecurityKeys()
	{
		$encryptionName = EmergencyHouseEncryptionService::ENCRYPTION_KEY_NAME;
		$hmacName = EmergencyHouseEncryptionService::HMAC_KEY_NAME;
		$encryptionExists = $this->constantExists($encryptionName, 0);
		$hmacExists = $this->constantExists($hmacName, 0);

		if ($encryptionExists && $hmacExists) {
			return true;
		}
		if (!$encryptionExists && !$hmacExists) {
			$environmentEncryption = getenv($encryptionName);
			$environmentHmac = getenv($hmacName);
			if (
				is_string($environmentEncryption) && $environmentEncryption !== ''
				&& is_string($environmentHmac) && $environmentHmac !== ''
			) {
				return true;
			}
		}

		$encryptionValue = $encryptionExists ? dolibarr_get_const($this->db, $encryptionName, 0) : '';
		$hmacValue = $hmacExists ? dolibarr_get_const($this->db, $hmacName, 0) : '';
		if (!$encryptionExists) {
			$encryptionValue = base64_encode(getRandomPassword(true, null, 48));
			if (dolibarr_set_const($this->db, $encryptionName, $encryptionValue, 'chaine', 0, '', 0) <= 0) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}
		if (!$hmacExists) {
			do {
				$hmacValue = base64_encode(getRandomPassword(true, null, 48));
			} while (hash_equals($encryptionValue, $hmacValue));
			if (dolibarr_set_const($this->db, $hmacName, $hmacValue, 'chaine', 0, '', 0) <= 0) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}

		return true;
	}

	/**
	 * Disable module without deleting configuration or data.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		global $conf;

		$sql = array(
			$this->buildCronStatusUpdateSql(0, (int) $conf->entity),
		);

		return $this->_remove($sql, $options);
	}

	/**
	 * Build the scoped status update for the native scheduled jobs.
	 *
	 * The native rows are kept so their schedule and execution history survive
	 * module disable/enable cycles.
	 *
	 * @param int $status Enabled (1) or disabled (0)
	 * @param int $entity Entity
	 * @return string
	 */
	private function buildCronStatusUpdateSql($status, $entity)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'cronjob';
		$sql .= ' SET status = '.($status > 0 ? 1 : 0);
		$sql .= " WHERE module_name = 'emergencyhouse'";
		$sql .= ' AND entity = '.((int) $entity);
		$sql .= " AND classesname = '/emergencyhouse/class/emergencyhousecron.class.php'";
		$sql .= " AND objectname = 'EmergencyHouseCron'";

		return $sql;
	}

	/**
	 * Normalize native notification trigger ownership for existing installs.
	 *
	 * Dolibarr 20 to 23 checks c_action_trigger.elementtype directly against
	 * the enabled module key on the Notifications setup page. Agenda accepts
	 * both an object element and a module key, while Notifications requires
	 * the latter for external modules.
	 *
	 * @return string SQL update
	 */
	private function buildNotificationTriggerModuleUpdateSql()
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'c_action_trigger';
		$sql .= " SET elementtype = 'emergencyhouse'";
		$sql .= " WHERE LEFT(code, 15) = 'EMERGENCYHOUSE_'";
		$sql .= " AND elementtype <> 'emergencyhouse'";

		return $sql;
	}

	/**
	 * Create missing defaults without overwriting configured values.
	 *
	 * @param int $entity Entity
	 * @return void
	 */
	private function ensureDefaultConstants($entity)
	{
		$defaults = array(
			'EMERGENCYHOUSE_PUBLIC_PORTAL_ENABLED' => array('0', 'yesno'),
			'EMERGENCYHOUSE_PUBLIC_REQUEST_VISIBILITY' => array('private', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_BASE_URL' => array('', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_ORGANISATION_NAME' => array('', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_OFFICIAL_PHONE' => array('', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL' => array('', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE' => array('', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_PRIVACY_URL' => array('', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_SOCIAL_IMAGE_URL' => array('', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_TERMS_HTML' => array('', 'chaine'),
			'EMERGENCYHOUSE_PUBLIC_GPTBOT_ALLOWED' => array('0', 'yesno'),
			'EMERGENCYHOUSE_OFFER_PUBLICATION_POLICY' => array('operator_validation', 'chaine'),
			'EMERGENCYHOUSE_OSM_TILES_ENABLED' => array('1', 'yesno'),
			'EMERGENCYHOUSE_OSM_TILE_URL' => array('https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'chaine'),
			'EMERGENCYHOUSE_GEOCODING_PROVIDER' => array('disabled', 'chaine'),
			'EMERGENCYHOUSE_GEOCODING_ENDPOINT' => array('https://data.geopf.fr/geocodage/search', 'chaine'),
			'EMERGENCYHOUSE_SMS_PROVIDER' => array('disabled', 'chaine'),
			'EMERGENCYHOUSE_API_ENABLED' => array('0', 'yesno'),
			'EMERGENCYHOUSE_PHOTOS_ENABLED' => array('0', 'yesno'),
			'EMERGENCYHOUSE_ADHERENT_TYPE_ID' => array('0', 'chaine'),
			'EMERGENCYHOUSE_SESSION_IDLE_MINUTES' => array('120', 'chaine'),
			'EMERGENCYHOUSE_TOKEN_TTL_MINUTES' => array('30', 'chaine'),
			'EMERGENCYHOUSE_MAX_LOGIN_ATTEMPTS' => array('5', 'chaine'),
			'EMERGENCYHOUSE_MESSAGE_MAX_LENGTH' => array('4000', 'chaine'),
			'EMERGENCYHOUSE_SOLICITATION_EXPIRY_DAYS' => array('7', 'chaine'),
			'EMERGENCYHOUSE_SOLICITATION_DAILY_LIMIT' => array('20', 'chaine'),
			'EMERGENCYHOUSE_FREE_TEXT' => array('', 'chaine'),
			'EMERGENCYHOUSE_AVAILABILITY_CONFIRM_DAYS' => array('7', 'chaine'),
			'EMERGENCYHOUSE_NOTIFICATION_BATCH_SIZE' => array('100', 'chaine'),
			'EMERGENCYHOUSE_NOTIFICATION_MAX_ATTEMPTS' => array('5', 'chaine'),
			'EMERGENCYHOUSE_MATCH_BATCH_SIZE' => array('100', 'chaine'),
			'EMERGENCYHOUSE_JOB_BATCH_SIZE' => array('100', 'chaine'),
			'EMERGENCYHOUSE_RETENTION_SESSION_DAYS' => array('7', 'chaine'),
			'EMERGENCYHOUSE_RETENTION_TOKEN_DAYS' => array('7', 'chaine'),
			'EMERGENCYHOUSE_RETENTION_ACCOUNT_DAYS' => array('90', 'chaine'),
			'EMERGENCYHOUSE_RETENTION_ADDRESS_DAYS' => array('30', 'chaine'),
			'EMERGENCYHOUSE_RETENTION_MESSAGE_DAYS' => array('90', 'chaine'),
			'EMERGENCYHOUSE_RETENTION_OPERATIONAL_DAYS' => array('90', 'chaine'),
			'EMERGENCYHOUSE_RETENTION_AUDIT_DAYS' => array('365', 'chaine'),
			'EMERGENCYHOUSE_MATCH_DISTANCE_WEIGHT' => array('30', 'chaine'),
			'EMERGENCYHOUSE_MATCH_CAPACITY_WEIGHT' => array('25', 'chaine'),
			'EMERGENCYHOUSE_MATCH_DATES_WEIGHT' => array('20', 'chaine'),
			'EMERGENCYHOUSE_MATCH_TYPE_WEIGHT' => array('15', 'chaine'),
			'EMERGENCYHOUSE_MATCH_FEATURES_WEIGHT' => array('10', 'chaine'),
			'EMERGENCYHOUSE_CAMPAIGN_ADDON' => array('emergencyhouse_campaign_standard', 'chaine'),
			'EMERGENCYHOUSE_OFFER_ADDON' => array('emergencyhouse_offer_standard', 'chaine'),
			'EMERGENCYHOUSE_REQUEST_ADDON' => array('emergencyhouse_request_standard', 'chaine'),
			'EMERGENCYHOUSE_SOLICITATION_ADDON' => array('emergencyhouse_solicitation_standard', 'chaine'),
			'EMERGENCYHOUSE_ALLOCATION_ADDON' => array('emergencyhouse_allocation_standard', 'chaine'),
			'EMERGENCYHOUSE_REPORT_ADDON' => array('emergencyhouse_report_standard', 'chaine'),
			'EMERGENCYHOUSE_ALLOCATION_DEFAULT_MODEL' => array('emergencyhouse_agreement', 'chaine'),
		);

		foreach ($defaults as $name => $definition) {
			if (!$this->constantExists($name, $entity)) {
				dolibarr_set_const($this->db, $name, $definition[0], $definition[1], 0, '', $entity);
			}
		}
	}

	/**
	 * Add the public-account/member relation on existing installations.
	 *
	 * @return bool
	 */
	private function ensureMemberLinkSchema()
	{
		$table = MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql = "SHOW COLUMNS FROM ".$table." LIKE 'fk_member'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		if ($this->db->num_rows($resql) === 0) {
			if (!$this->db->query('ALTER TABLE '.$table.' ADD COLUMN fk_member integer NULL AFTER entity')) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}

		$indexFound = false;
		$resql = $this->db->query('SHOW INDEX FROM '.$table);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		while (is_object($obj = $this->db->fetch_object($resql))) {
			if (isset($obj->Key_name) && (string) $obj->Key_name === 'uk_emergencyhouse_account_member') {
				$indexFound = true;
				break;
			}
		}
		if (!$indexFound) {
			$sql = 'ALTER TABLE '.$table;
			$sql .= ' ADD UNIQUE KEY uk_emergencyhouse_account_member (entity, fk_member)';
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}

		return true;
	}

	/**
	 * Replace the former generic model only when it is still selected.
	 *
	 * Existing custom models and generated references are left untouched.
	 *
	 * @param int $entity Entity
	 * @return bool
	 */
	private function migrateLegacyNumberingModels($entity)
	{
		$models = array(
			'EMERGENCYHOUSE_CAMPAIGN_ADDON' => 'emergencyhouse_campaign_standard',
			'EMERGENCYHOUSE_OFFER_ADDON' => 'emergencyhouse_offer_standard',
			'EMERGENCYHOUSE_REQUEST_ADDON' => 'emergencyhouse_request_standard',
			'EMERGENCYHOUSE_SOLICITATION_ADDON' => 'emergencyhouse_solicitation_standard',
			'EMERGENCYHOUSE_ALLOCATION_ADDON' => 'emergencyhouse_allocation_standard',
			'EMERGENCYHOUSE_REPORT_ADDON' => 'emergencyhouse_report_standard',
		);
		foreach ($models as $constant => $model) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'const';
			$sql .= " SET value = '".$this->db->escape($model)."'";
			$sql .= " WHERE name = '".$this->db->escape($constant)."'";
			$sql .= " AND value = 'emergencyhouse_standard'";
			$sql .= ' AND entity = '.((int) $entity);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}
		return true;
	}

	/**
	 * Check whether a constant exists for an entity.
	 *
	 * @param string $name   Constant name
	 * @param int    $entity Entity
	 * @return bool
	 */
	private function constantExists($name, $entity)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'const';
		$sql .= " WHERE name = '".$this->db->escape($name)."'";
		$sql .= ' AND entity = '.((int) $entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}

		return $this->db->num_rows($resql) > 0;
	}

	/**
	 * Copy immutable default dictionaries and templates into the active entity.
	 *
	 * Existing rows are never overwritten, so administrators keep their choices.
	 *
	 * @param int $entity Entity
	 * @return bool
	 */
	private function ensureEntityDefaults($entity)
	{
		$dictionaryColumns = array(
			'c_emergencyhouse_campaign_type' => 'code, label, position, active',
			'c_emergencyhouse_housing_type' => 'code, label, position, active',
			'c_emergencyhouse_feature' => 'code, label, feature_group, value_type, position, active',
			'c_emergencyhouse_animal_type' => 'code, label, position, active',
			'c_emergencyhouse_verification_level' => 'code, label, position, active',
			'c_emergencyhouse_refusal_reason' => 'code, label, position, active',
			'c_emergencyhouse_cancellation_reason' => 'code, label, position, active',
			'c_emergencyhouse_report_reason' => 'code, label, position, active',
			'c_emergencyhouse_moderation_action' => 'code, label, position, active',
		);
		foreach ($dictionaryColumns as $table => $columns) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$table.' (entity, '.$columns.')';
			$sql .= ' SELECT '.((int) $entity).', '.$columns;
			$sql .= ' FROM '.MAIN_DB_PREFIX.$table.' AS source';
			$sql .= ' WHERE source.entity = 1';
			$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.$table.' AS target';
			$sql .= ' WHERE target.entity = '.((int) $entity).' AND target.code = source.code)';
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_notification_template';
		$sql .= ' (entity, fk_campaign, template_code, channel, lang, subject_template, body_template, is_mandatory, status, date_creation)';
		$sql .= ' SELECT '.((int) $entity).', 0, source.template_code, source.channel, source.lang,';
		$sql .= ' source.subject_template, source.body_template, source.is_mandatory, source.status,';
		$sql .= " '".$this->db->idate(dol_now())."'";
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_notification_template AS source';
		$sql .= ' WHERE source.entity = 1 AND source.fk_campaign = 0';
		$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'emergencyhouse_notification_template AS target';
		$sql .= ' WHERE target.entity = '.((int) $entity).' AND target.fk_campaign = 0';
		$sql .= ' AND target.template_code = source.template_code';
		$sql .= ' AND target.channel = source.channel AND target.lang = source.lang)';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}

	/**
	 * Merge the module definition into Multicompany configuration.
	 *
	 * @param int $entity Entity
	 * @return void
	 */
	private function mergeMulticompanyDefinition($entity)
	{
		dol_include_once('/emergencyhouse/class/actions_emergencyhouse.class.php');
		if (!class_exists('ActionsEmergencyHouse')) {
			return;
		}

		$current = json_decode(getDolGlobalString('MULTICOMPANY_EXTERNAL_MODULES_SHARING', '{}'), true);
		if (!is_array($current)) {
			$current = array();
		}

		$definition = ActionsEmergencyHouse::getMulticompanySharingDefinition();
		$merged = array_replace_recursive($current, $definition);
		$json = json_encode($merged);
		if (is_string($json)) {
			dolibarr_set_const(
				$this->db,
				'MULTICOMPANY_EXTERNAL_MODULES_SHARING',
				$json,
				'chaine',
				0,
				'',
				$entity
			);
		}
	}
}
