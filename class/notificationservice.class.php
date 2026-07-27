<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');

/**
 * Transactional notification queue for public identities.
 *
 * Back-office user notifications remain exposed through Dolibarr's native
 * Notifications module and CRUD triggers.
 */
class EmergencyHouseNotificationService
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
	 * Queue an email to a public account.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int|null                    $campaignId Campaign ID
	 * @param string                      $eventCode Event code
	 * @param string                      $templateCode Template code
	 * @param array<string, scalar|null>   $payload Template values
	 * @param string                      $idempotencyKey Idempotency source
	 * @param int                         $priority Priority
	 * @return int
	 */
	public function queueForAccount($account, $campaignId, $eventCode, $templateCode, array $payload, $idempotencyKey, $priority = 50)
	{
		$profile = $account->getDecryptedProfile();
		if (!is_array($profile)) {
			$this->error = $account->error;
			return -1;
		}
		return $this->queueEmail(
			(int) $account->entity,
			$campaignId,
			(int) $account->id,
			(string) $profile['email'],
			(string) $account->lang,
			$eventCode,
			$templateCode,
			$payload,
			$idempotencyKey,
			$priority
		);
	}

	/**
	 * Queue one email.
	 *
	 * @param int                       $entity Entity
	 * @param int|null                  $campaignId Campaign ID
	 * @param int|null                  $accountId Account ID
	 * @param string                    $recipient Recipient email
	 * @param string                    $locale Locale
	 * @param string                    $eventCode Event code
	 * @param string                    $templateCode Template code
	 * @param array<string, scalar|null> $payload Template values
	 * @param string                    $idempotencySource Idempotency source
	 * @param int                       $priority Priority
	 * @return int
	 */
	public function queueEmail($entity, $campaignId, $accountId, $recipient, $locale, $eventCode, $templateCode, array $payload, $idempotencySource, $priority = 50)
	{
		if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
			$this->error = 'ErrorInvalidEmail';
			return -1;
		}
		$idempotencyKey = hash('sha256', $entity.'|'.$eventCode.'|'.$templateCode.'|'.$idempotencySource);
		$context = 'emergencyhouse|notification|'.$entity.'|'.$idempotencyKey;
		$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($payloadJson)) {
			$this->error = 'ErrorJsonEncoding';
			return -1;
		}
		$recipientEncrypted = $this->encryption->encrypt($recipient, $context.'|recipient');
		$payloadEncrypted = $this->encryption->encrypt($payloadJson, $context.'|payload');
		if (!is_string($recipientEncrypted) || !is_string($payloadEncrypted)) {
			$this->error = $this->encryption->error;
			return -1;
		}
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_notification';
		$sql .= ' (entity, fk_campaign, fk_account, channel, event_code, template_code, recipient_encrypted, locale, payload_encrypted, idempotency_key, priority, status, attempt_count, next_attempt, date_creation)';
		$sql .= ' VALUES ('.((int) $entity).', ';
		$sql .= $campaignId === null ? 'NULL, ' : ((int) $campaignId).', ';
		$sql .= $accountId === null ? 'NULL, ' : ((int) $accountId).', ';
		$sql .= "'email', '".$this->db->escape($eventCode)."', '".$this->db->escape($templateCode)."',";
		$sql .= " '".$this->db->escape($recipientEncrypted)."', '".$this->db->escape($locale)."',";
		$sql .= " '".$this->db->escape($payloadEncrypted)."', '".$this->db->escape($idempotencyKey)."',";
		$sql .= ' '.((int) $priority).", 0, 0, '".$this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."')";
		$sql .= ' ON DUPLICATE KEY UPDATE idempotency_key = idempotency_key';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Queue a solicitation event for the other participant, or both for an operator event.
	 *
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @param int|null $actorAccountId Public actor, null for an operator or cron
	 * @param string $eventCode Event code
	 * @param string $templateCode Template code
	 * @param string $idempotencySource Idempotency source
	 * @return int Number queued, or -1
	 */
	public function queueSolicitationParticipants($solicitation, $actorAccountId, $eventCode, $templateCode, $idempotencySource)
	{
		$sql = 'SELECT o.fk_account AS host_account, r.fk_account AS requester_account';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= ' ON r.rowid = '.((int) $solicitation->fk_request).' AND r.entity = o.entity';
		$sql .= ' WHERE o.rowid = '.((int) $solicitation->fk_offer);
		$sql .= ' AND o.entity = '.((int) $solicitation->entity);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($obj)) {
			$this->error = $resql ? 'ErrorRecordNotFound' : $this->db->lasterror();
			return -1;
		}
		$payload = array(
			'SOLICITATION_REF' => (string) $solicitation->ref,
			'SOLICITATION_URL' => dol_buildpath('/emergencyhouse/public/solicitation/view.php', 2).'?id='.((int) $solicitation->id),
		);
		return $this->queueParticipantAccounts(
			(int) $solicitation->entity,
			(int) $solicitation->fk_campaign,
			array((int) $obj->host_account, (int) $obj->requester_account),
			$actorAccountId,
			$eventCode,
			$templateCode,
			$payload,
			$idempotencySource
		);
	}

	/**
	 * Queue an allocation event for the other participant, or both for an operator event.
	 *
	 * @param EmergencyHouseAllocation $allocation Allocation
	 * @param int|null $actorAccountId Public actor, null for an operator or cron
	 * @param string $eventCode Event code
	 * @param string $templateCode Template code
	 * @param string $idempotencySource Idempotency source
	 * @return int Number queued, or -1
	 */
	public function queueAllocationParticipants($allocation, $actorAccountId, $eventCode, $templateCode, $idempotencySource)
	{
		$sql = 'SELECT o.fk_account AS host_account, r.fk_account AS requester_account';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= ' ON r.rowid = '.((int) $allocation->fk_request).' AND r.entity = o.entity';
		$sql .= ' WHERE o.rowid = '.((int) $allocation->fk_offer);
		$sql .= ' AND o.entity = '.((int) $allocation->entity);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($obj)) {
			$this->error = $resql ? 'ErrorRecordNotFound' : $this->db->lasterror();
			return -1;
		}
		$payload = array(
			'ALLOCATION_REF' => (string) $allocation->ref,
			'ALLOCATION_URL' => dol_buildpath('/emergencyhouse/public/allocation/view.php', 2).'?id='.((int) $allocation->id),
		);
		return $this->queueParticipantAccounts(
			(int) $allocation->entity,
			(int) $allocation->fk_campaign,
			array((int) $obj->host_account, (int) $obj->requester_account),
			$actorAccountId,
			$eventCode,
			$templateCode,
			$payload,
			$idempotencySource
		);
	}

	/**
	 * Queue one event for unique public participant accounts.
	 *
	 * @param int $entity Entity
	 * @param int $campaignId Campaign
	 * @param array<int, int> $accountIds Participant account IDs
	 * @param int|null $actorAccountId Actor to exclude
	 * @param string $eventCode Event
	 * @param string $templateCode Template
	 * @param array<string, scalar|null> $payload Template data
	 * @param string $idempotencySource Source
	 * @return int Number queued, or -1
	 */
	private function queueParticipantAccounts($entity, $campaignId, array $accountIds, $actorAccountId, $eventCode, $templateCode, array $payload, $idempotencySource)
	{
		$count = 0;
		foreach (array_values(array_unique($accountIds)) as $accountId) {
			if ($accountId <= 0 || ($actorAccountId !== null && $accountId === $actorAccountId)) {
				continue;
			}
			$account = new EmergencyHousePublicAccount($this->db);
			if ($account->fetch($accountId) <= 0 || (int) $account->entity !== $entity) {
				$this->error = !empty($account->error) ? $account->error : 'ErrorRecordNotFound';
				return -1;
			}
			$result = $this->queueForAccount(
				$account,
				$campaignId,
				$eventCode,
				$templateCode,
				$payload,
				$idempotencySource.'|account-'.$accountId
			);
			if ($result < 0) {
				return -1;
			}
			$count += $result;
		}
		return $count;
	}

	/**
	 * Process pending queue records.
	 *
	 * @param int $limit Batch limit
	 * @return int Number sent
	 */
	public function processQueue($limit = 25)
	{
		global $conf;

		$sent = 0;
		$limit = min(100, max(1, $limit));
		for ($i = 0; $i < $limit; $i++) {
			$record = $this->claimNext((int) $conf->entity);
			if (!is_array($record)) {
				break;
			}
			if ($this->sendClaimed($record)) {
				$sent++;
			}
		}
		return $sent;
	}

	/**
	 * Claim one queue row.
	 *
	 * @param int $entity Entity
	 * @return array<string, int|string|null>|false
	 */
	private function claimNext($entity)
	{
		$lockToken = bin2hex(random_bytes(16));
		$this->db->begin();
		$sql = 'SELECT rowid, fk_campaign, fk_account, event_code, template_code, recipient_encrypted, locale, payload_encrypted, idempotency_key, attempt_count';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_notification';
		$sql .= ' WHERE entity = '.((int) $entity).' AND status = 0';
		$sql .= " AND next_attempt <= '".$this->db->idate(dol_now())."'";
		$sql .= ' AND (locked_at IS NULL OR locked_at < \''.$this->db->idate(dol_time_plus_duree(dol_now(), -15, 'i')).'\')';
		$sql .= ' ORDER BY priority ASC, date_creation ASC';
		$sql .= $this->db->plimit(1);
		$sql .= ' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			$this->db->rollback();
			return false;
		}
		$sqlLock = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_notification SET';
		$sqlLock .= " locked_at = '".$this->db->idate(dol_now())."', lock_token = '".$this->db->escape($lockToken)."',";
		$sqlLock .= ' attempt_count = attempt_count + 1';
		$sqlLock .= ' WHERE rowid = '.((int) $obj->rowid).' AND status = 0';
		if (!$this->db->query($sqlLock)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return array(
			'id' => (int) $obj->rowid,
			'entity' => $entity,
			'fk_campaign' => empty($obj->fk_campaign) ? null : (int) $obj->fk_campaign,
			'fk_account' => empty($obj->fk_account) ? null : (int) $obj->fk_account,
			'event_code' => (string) $obj->event_code,
			'template_code' => (string) $obj->template_code,
			'recipient_encrypted' => (string) $obj->recipient_encrypted,
			'locale' => (string) $obj->locale,
			'payload_encrypted' => (string) $obj->payload_encrypted,
			'idempotency_key' => (string) $obj->idempotency_key,
			'attempt_count' => (int) $obj->attempt_count + 1,
			'lock_token' => $lockToken,
		);
	}

	/**
	 * Send one claimed email.
	 *
	 * @param array<string, int|string|null> $record Queue record
	 * @return bool
	 */
	private function sendClaimed(array $record)
	{
		$context = 'emergencyhouse|notification|'.$record['entity'].'|'.$record['idempotency_key'];
		$recipient = $this->encryption->decrypt((string) $record['recipient_encrypted'], $context.'|recipient');
		$payloadJson = $this->encryption->decrypt((string) $record['payload_encrypted'], $context.'|payload');
		if (!is_string($recipient) || !is_string($payloadJson)) {
			$this->markFailure($record, $this->encryption->error);
			return false;
		}
		$payload = json_decode($payloadJson, true);
		if (!is_array($payload)) {
			$this->markFailure($record, 'ErrorJsonDecoding');
			return false;
		}
		$template = $this->fetchTemplate(
			(int) $record['entity'],
			$record['fk_campaign'] === null ? null : (int) $record['fk_campaign'],
			(string) $record['template_code'],
			(string) $record['locale']
		);
		if (!is_array($template)) {
			$this->markFailure($record, $this->error);
			return false;
		}
		$subject = $this->render((string) $template['subject'], $payload);
		$body = $this->render((string) $template['body'], $payload);
		$from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', '');
		if ($from === '') {
			$this->markFailure($record, 'ErrorSenderEmailMissing');
			return false;
		}

		$mail = new CMailFile(
			$subject,
			$recipient,
			$from,
			$body,
			array(),
			array(),
			array(),
			'',
			'',
			0,
			-1,
			'',
			'',
			'',
			'',
			'emergencyhouse'
		);
		if (!empty($mail->error) || !$mail->sendfile()) {
			$errorCode = !empty($mail->error) ? 'ErrorMailTransport' : 'ErrorMailSend';
			$this->markFailure($record, $errorCode);
			return false;
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_notification SET';
		$sql .= " status = 2, date_sent = '".$this->db->idate(dol_now())."',";
		$sql .= ' locked_at = NULL, lock_token = NULL, last_error_code = NULL';
		$sql .= ' WHERE rowid = '.((int) $record['id']);
		$sql .= " AND lock_token = '".$this->db->escape((string) $record['lock_token'])."'";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}

	/**
	 * Fetch campaign, entity or fallback template.
	 *
	 * @param int      $entity Entity
	 * @param int|null $campaignId Campaign ID
	 * @param string   $code Template code
	 * @param string   $locale Locale
	 * @return array{subject:string, body:string}|false
	 */
	private function fetchTemplate($entity, $campaignId, $code, $locale)
	{
		$locales = array($locale);
		if ($locale !== 'fr_FR') {
			$locales[] = 'fr_FR';
		}
		foreach ($locales as $candidateLocale) {
			$sql = 'SELECT subject_template, body_template FROM '.MAIN_DB_PREFIX.'emergencyhouse_notification_template';
			$sql .= ' WHERE entity = '.((int) $entity);
			$sql .= " AND template_code = '".$this->db->escape($code)."' AND channel = 'email'";
			$sql .= " AND lang = '".$this->db->escape($candidateLocale)."' AND status = 1";
			if ($campaignId === null) {
				$sql .= ' AND fk_campaign = 0';
			} else {
				$sql .= ' AND fk_campaign IN ('.((int) $campaignId).', 0)';
				$sql .= ' ORDER BY fk_campaign DESC';
			}
			$sql .= $this->db->plimit(1);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return false;
			}
			$obj = $this->db->fetch_object($resql);
			if (is_object($obj)) {
				return array('subject' => (string) $obj->subject_template, 'body' => (string) $obj->body_template);
			}
		}
		$this->error = 'ErrorNotificationTemplateMissing';
		return false;
	}

	/**
	 * Replace explicit scalar placeholders only.
	 *
	 * @param string                    $template Template
	 * @param array<string, scalar|null> $payload Values
	 * @return string
	 */
	private function render($template, array $payload)
	{
		$replacements = array();
		foreach ($payload as $key => $value) {
			if (!preg_match('/^[A-Z0-9_]+$/', $key) || (!is_scalar($value) && $value !== null)) {
				continue;
			}
			$replacements['__'.$key.'__'] = (string) $value;
		}
		return strtr($template, $replacements);
	}

	/**
	 * Release failed row with exponential retry.
	 *
	 * @param array<string, int|string|null> $record Record
	 * @param string                        $errorCode Stable error code
	 * @return void
	 */
	private function markFailure(array $record, $errorCode)
	{
		$attempt = max(1, (int) $record['attempt_count']);
		$maxAttempts = max(1, getDolGlobalInt('EMERGENCYHOUSE_NOTIFICATION_MAX_ATTEMPTS', 6));
		$status = $attempt >= $maxAttempts ? 3 : 0;
		$delay = min(86400, 60 * (2 ** min(10, $attempt)));
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_notification SET';
		$sql .= ' status = '.$status.',';
		$sql .= " next_attempt = '".$this->db->idate(dol_now() + $delay)."',";
		$sql .= " last_error_code = '".$this->db->escape($errorCode)."',";
		$sql .= ' locked_at = NULL, lock_token = NULL';
		$sql .= ' WHERE rowid = '.((int) $record['id']);
		$sql .= " AND lock_token = '".$this->db->escape((string) $record['lock_token'])."'";
		if (!$this->db->query($sql)) {
			dol_syslog(__METHOD__.': '.$this->db->lasterror(), LOG_ERR);
		}
	}
}
