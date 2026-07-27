<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/matchingservice.class.php');
dol_include_once('/emergencyhouse/class/notificationservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/retentionservice.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');
dol_include_once('/emergencyhouse/class/statisticsservice.class.php');

/**
 * Methods exposed to Dolibarr Scheduled jobs.
 */
class EmergencyHouseCron
{
	/** @var DoliDB */
	public $db;
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
	}

	/**
	 * Process public notification queue.
	 *
	 * @return int
	 */
	public function processNotificationQueue()
	{
		if (!$this->guardModule()) {
			return 0;
		}
		$service = new EmergencyHouseNotificationService($this->db);
		$result = $service->processQueue(getDolGlobalInt('EMERGENCYHOUSE_NOTIFICATION_BATCH_SIZE', 25));
		$this->error = $service->error;
		return $result;
	}

	/**
	 * Process matching jobs.
	 *
	 * @return int
	 */
	public function processMatchingQueue()
	{
		global $conf;
		if (!$this->guardModule()) {
			return 0;
		}
		$limit = max(1, min(100, getDolGlobalInt('EMERGENCYHOUSE_MATCH_BATCH_SIZE', 100)));
		$processed = 0;
		for ($i = 0; $i < $limit; $i++) {
			$job = $this->claimJob((int) $conf->entity, 'matching');
			if (!is_array($job)) {
				break;
			}
			$service = new EmergencyHouseMatchingService($this->db);
			$result = 0;
			if ($job['object_type'] === 'request') {
				$result = $service->recalculateForRequest((int) $job['fk_object']);
			} elseif ($job['object_type'] === 'offer') {
				$result = $service->recalculateForOffer((int) $job['fk_object']);
			} else {
				$result = $this->recalculateCampaign((int) $job['entity'], (int) $job['fk_campaign'], $service);
			}
			if ($result < 0) {
				$this->releaseJob($job, false, $service->error);
			} else {
				$this->releaseJob($job, true, '');
				$processed++;
			}
		}
		return $processed;
	}

	/**
	 * Expire offers, requests and solicitations.
	 *
	 * @return int
	 */
	public function expireRecords()
	{
		global $conf, $user;
		if (!$this->guardModule()) {
			return 0;
		}
		$count = 0;
		$definitions = array(
			array('emergencyhouse_offer', EmergencyHouseOffer::STATUS_PUBLISHED, EmergencyHouseOffer::STATUS_EXPIRED, 'EmergencyHouseOffer'),
			array('emergencyhouse_request', EmergencyHouseRequest::STATUS_ACTIVE, EmergencyHouseRequest::STATUS_EXPIRED, 'EmergencyHouseRequest'),
			array('emergencyhouse_solicitation', EmergencyHouseSolicitation::STATUS_PENDING, EmergencyHouseSolicitation::STATUS_EXPIRED, 'EmergencyHouseSolicitation'),
		);
		foreach ($definitions as $definition) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$definition[0];
			$sql .= ' WHERE entity = '.((int) $conf->entity).' AND status = '.((int) $definition[1]);
			$sql .= " AND date_expiration IS NOT NULL AND date_expiration < '".$this->db->idate(dol_now())."'";
			$sql .= $this->db->plimit(200);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			while (is_object($obj = $this->db->fetch_object($resql))) {
				$class = (string) $definition[3];
				$record = new $class($this->db);
				if ($record->fetch((int) $obj->rowid) > 0 && $record->setStatus((int) $definition[2], $user, 'automatic_expiration') > 0) {
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * Queue availability confirmations.
	 *
	 * @return int
	 */
	public function requestAvailabilityConfirmations()
	{
		global $conf;
		if (!$this->guardModule()) {
			return 0;
		}
		$intervalDays = max(1, getDolGlobalInt('EMERGENCYHOUSE_AVAILABILITY_CONFIRM_DAYS', 7));
		$cutoff = dol_time_plus_duree(dol_now(), -$intervalDays, 'd');
		$sql = 'SELECT rowid, fk_account, fk_campaign, ref FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND status = '.EmergencyHouseOffer::STATUS_PUBLISHED;
		$sql .= " AND (date_last_confirmation IS NULL OR date_last_confirmation < '".$this->db->idate($cutoff)."')";
		$sql .= $this->db->plimit(200);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$queue = new EmergencyHouseNotificationService($this->db);
		$count = 0;
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$account = new EmergencyHousePublicAccount($this->db);
			if ($account->fetch((int) $obj->fk_account) <= 0) {
				continue;
			}
			$payload = array('OFFER_REF' => (string) $obj->ref);
			if ($queue->queueForAccount($account, (int) $obj->fk_campaign, 'offer_confirmation_due', 'offer_confirmation_due', $payload, 'offer-'.$obj->rowid.'-'.dol_print_date(dol_now(), '%Y%m%d')) > 0) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Queue stay reminders.
	 *
	 * @return int
	 */
	public function sendStayReminders()
	{
		global $conf;
		if (!$this->guardModule()) {
			return 0;
		}
		$horizon = dol_time_plus_duree(dol_now(), 24, 'h');
		$sql = 'SELECT a.rowid, a.ref, a.fk_campaign, o.fk_account AS host_account, r.fk_account AS requester_account';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_allocation AS a';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = a.fk_offer AND o.entity = a.entity';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = a.fk_request AND r.entity = a.entity';
		$sql .= ' WHERE a.entity = '.((int) $conf->entity);
		$sql .= ' AND a.status IN ('.EmergencyHouseAllocation::STATUS_PROPOSED.','.EmergencyHouseAllocation::STATUS_CONFIRMED.')';
		$sql .= " AND a.date_start BETWEEN '".$this->db->idate(dol_now())."' AND '".$this->db->idate($horizon)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$count = 0;
		$queue = new EmergencyHouseNotificationService($this->db);
		while (is_object($obj = $this->db->fetch_object($resql))) {
			foreach (array((int) $obj->host_account, (int) $obj->requester_account) as $accountId) {
				$account = new EmergencyHousePublicAccount($this->db);
				if ($account->fetch($accountId) <= 0) {
					continue;
				}
				$payload = array('ALLOCATION_REF' => (string) $obj->ref);
				if ($queue->queueForAccount($account, (int) $obj->fk_campaign, 'stay_reminder', 'stay_reminder', $payload, 'allocation-'.$obj->rowid.'-'.$accountId.'-'.dol_print_date(dol_now(), '%Y%m%d')) > 0) {
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * Close allocations whose stay ended.
	 *
	 * @return int
	 */
	public function closeEndedAllocations()
	{
		global $conf, $user;
		if (!$this->guardModule()) {
			return 0;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_allocation';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND status IN ('.EmergencyHouseAllocation::STATUS_CONFIRMED.','.EmergencyHouseAllocation::STATUS_ACTIVE.')';
		$sql .= " AND COALESCE(actual_end, date_end) IS NOT NULL AND COALESCE(actual_end, date_end) < '".$this->db->idate(dol_now())."'";
		$sql .= $this->db->plimit(200);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$count = 0;
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$allocation = new EmergencyHouseAllocation($this->db);
			if ($allocation->fetch((int) $obj->rowid) > 0
				&& $allocation->setStatus(EmergencyHouseAllocation::STATUS_COMPLETED, $user, 'stay_end') > 0) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Build daily statistics.
	 *
	 * @return int
	 */
	public function buildDailyStatistics()
	{
		global $conf;
		if (!$this->guardModule()) {
			return 0;
		}
		$service = new EmergencyHouseStatisticsService($this->db);
		$result = $service->buildDaily((int) $conf->entity);
		$this->error = $service->error;
		return $result;
	}

	/**
	 * Apply retention rules.
	 *
	 * @return int
	 */
	public function applyRetention()
	{
		global $conf;
		if (!$this->guardModule()) {
			return 0;
		}
		$service = new EmergencyHouseRetentionService($this->db);
		$result = $service->apply((int) $conf->entity);
		$this->error = $service->error;
		return $result;
	}

	/**
	 * Provider health check without transmitting personal data.
	 *
	 * @return int
	 */
	public function checkProviders()
	{
		if (!$this->guardModule()) {
			return 0;
		}
		$encryption = new EmergencyHouseEncryptionService();
		if (!$encryption->isAvailable()) {
			$this->error = $encryption->error;
			return -1;
		}
		$provider = getDolGlobalString('EMERGENCYHOUSE_GEOCODING_PROVIDER', 'disabled');
		if ($provider === 'public_nominatim') {
			$this->error = 'ErrorPublicNominatimForbiddenForExactAddress';
			return -1;
		}
		if (!in_array($provider, array('disabled', 'geoplateforme'), true)) {
			$this->error = 'ErrorGeocodingProviderNotImplemented';
			return -1;
		}
		if ($provider !== 'disabled') {
			$defaultEndpoint = $provider === 'geoplateforme' ? 'https://data.geopf.fr/geocodage/search' : '';
			$endpoint = getDolGlobalString('EMERGENCYHOUSE_GEOCODING_ENDPOINT', $defaultEndpoint);
			if (filter_var($endpoint, FILTER_VALIDATE_URL) === false || stripos($endpoint, 'https://') !== 0) {
				$this->error = 'ErrorGeocodingEndpointInvalid';
				return -1;
			}
		}
		if (getDolGlobalString('EMERGENCYHOUSE_SMS_PROVIDER', 'disabled') !== 'disabled') {
			$this->error = 'ErrorSmsProviderNotImplemented';
			return -1;
		}
		return 1;
	}

	/**
	 * Guard module activation.
	 *
	 * @return bool
	 */
	private function guardModule()
	{
		if (!isModEnabled('emergencyhouse')) {
			$this->error = 'ErrorModuleDisabled';
			return false;
		}
		return true;
	}

	/**
	 * Claim one generic job.
	 *
	 * @param int    $entity Entity
	 * @param string $jobType Job type
	 * @return array<string, int|string|null>|false
	 */
	private function claimJob($entity, $jobType)
	{
		$lockToken = bin2hex(random_bytes(16));
		$this->db->begin();
		$sql = 'SELECT rowid, entity, object_type, fk_object, fk_campaign, attempt_count';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_job';
		$sql .= ' WHERE entity = '.((int) $entity)." AND job_type = '".$this->db->escape($jobType)."'";
		$sql .= ' AND status = 0';
		$sql .= " AND next_attempt <= '".$this->db->idate(dol_now())."'";
		$sql .= ' AND (locked_at IS NULL OR locked_at < \''.$this->db->idate(dol_time_plus_duree(dol_now(), -15, 'i')).'\')';
		$sql .= ' ORDER BY priority ASC, date_creation ASC'.$this->db->plimit(1).' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			$this->db->rollback();
			return false;
		}
		$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_job SET status = 1,';
		$sqlUpdate .= " locked_at = '".$this->db->idate(dol_now())."', lock_token = '".$this->db->escape($lockToken)."',";
		$sqlUpdate .= ' attempt_count = attempt_count + 1 WHERE rowid = '.((int) $obj->rowid);
		if (!$this->db->query($sqlUpdate)) {
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return array(
			'id' => (int) $obj->rowid,
			'entity' => (int) $obj->entity,
			'object_type' => (string) $obj->object_type,
			'fk_object' => empty($obj->fk_object) ? null : (int) $obj->fk_object,
			'fk_campaign' => empty($obj->fk_campaign) ? null : (int) $obj->fk_campaign,
			'attempt_count' => (int) $obj->attempt_count + 1,
			'lock_token' => $lockToken,
		);
	}

	/**
	 * Complete or retry one job.
	 *
	 * @param array<string, int|string|null> $job Job
	 * @param bool                          $success Success
	 * @param string                        $errorCode Error code
	 * @return void
	 */
	private function releaseJob(array $job, $success, $errorCode)
	{
		$attempt = (int) $job['attempt_count'];
		$terminal = !$success && $attempt >= max(1, getDolGlobalInt('EMERGENCYHOUSE_JOB_MAX_ATTEMPTS', 6));
		$status = $success ? 2 : ($terminal ? 3 : 0);
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_job SET status = '.$status.',';
		$sql .= ' locked_at = NULL, lock_token = NULL,';
		$sql .= $success ? " date_completed = '".$this->db->idate(dol_now())."', last_error_code = NULL" : " next_attempt = '".$this->db->idate(dol_now() + min(86400, 60 * (2 ** min(10, $attempt))))."', last_error_code = '".$this->db->escape($errorCode)."'";
		$sql .= ' WHERE rowid = '.((int) $job['id']);
		$sql .= " AND lock_token = '".$this->db->escape((string) $job['lock_token'])."'";
		if (!$this->db->query($sql)) {
			dol_syslog(__METHOD__.': '.$this->db->lasterror(), LOG_ERR);
		}
	}

	/**
	 * Recalculate a campaign in bounded chunks.
	 *
	 * @param int                           $entity Entity
	 * @param int                           $campaignId Campaign
	 * @param EmergencyHouseMatchingService $service Matching service
	 * @return int
	 */
	private function recalculateCampaign($entity, $campaignId, $service)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_request';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_campaign = '.((int) $campaignId);
		$sql .= ' AND status IN ('.EmergencyHouseRequest::STATUS_ACTIVE.','.EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED.')';
		$sql .= $this->db->plimit(200);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$count = 0;
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$result = $service->recalculateForRequest((int) $obj->rowid);
			if ($result < 0) {
				return -1;
			}
			$count += $result;
		}
		return $count;
	}
}
