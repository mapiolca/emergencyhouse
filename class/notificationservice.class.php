<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/languageservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_public.lib.php');

/**
 * Public email delivery and transactional business notification queue.
 *
 * Account access emails are sent synchronously. Deferred business messages use
 * the queue. Back-office user notifications remain exposed through Dolibarr's
 * native Notifications module and CRUD triggers.
 */
class EmergencyHouseNotificationService
{
	/**
	 * Email templates that must never enter or be sent from the scheduled queue.
	 *
	 * @var array<int, string>
	 */
	private const SYNCHRONOUS_ACCESS_EMAILS = array(
		'account_verification',
		'password_reset',
		'magic_login',
	);

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
	 * Send an account access email immediately through Dolibarr mail transport.
	 *
	 * Account verification, password reset and temporary sign-in links never
	 * enter the scheduled notification queue.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int|null                    $campaignId Campaign ID
	 * @param string                      $templateCode Template code
	 * @param array<string, scalar|null>   $payload Template values
	 * @param string                      $trackId Non-sensitive mail tracking ID
	 * @return int 1 on success, -1 on failure
	 */
	public function sendForAccount($account, $campaignId, $templateCode, array $payload, $trackId)
	{
		$profile = $account->getDecryptedProfile();
		if (!is_array($profile)) {
			$this->error = $account->error;
			return -1;
		}
		$payload = $this->localizePublicPayloadUrls($payload, (string) $account->lang);

		$result = $this->sendNativeEmail(
			(int) $account->entity,
			$campaignId,
			(string) $profile['email'],
			(string) $account->lang,
			$templateCode,
			$payload,
			$trackId
		);
		if ($result < 0) {
			dol_syslog(__METHOD__.': '.$this->error, LOG_ERR);
		}

		return $result;
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
		$payload = $this->localizePublicPayloadUrls($payload, (string) $account->lang);
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
		if (
			in_array($eventCode, self::SYNCHRONOUS_ACCESS_EMAILS, true)
			|| in_array($templateCode, self::SYNCHRONOUS_ACCESS_EMAILS, true)
		) {
			$this->error = 'ErrorSynchronousAccessEmailRequired';
			return -1;
		}
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
			'SOLICITATION_URL' => emergencyhousePublicAbsoluteUrl(
				'solicitation/view.php',
				array('id' => (int) $solicitation->id)
			),
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
			'ALLOCATION_URL' => emergencyhousePublicAbsoluteUrl(
				'allocation/view.php',
				array('id' => (int) $allocation->id)
			),
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
	 * Keep controlled public links aligned with the recipient language.
	 *
	 * @param array<string, scalar|null> $payload Template data
	 * @param string                     $locale Recipient locale
	 * @return array<string, scalar|null>
	 */
	private function localizePublicPayloadUrls(array $payload, $locale)
	{
		$urlKeys = array(
			'VERIFY_URL',
			'RESET_URL',
			'LOGIN_URL',
			'SOLICITATION_URL',
			'ALLOCATION_URL',
		);
		foreach ($urlKeys as $key) {
			if (!isset($payload[$key]) || !is_string($payload[$key])) {
				continue;
			}
			$localizedUrl = emergencyhousePublicUrlWithLocale($payload[$key], $locale);
			if ($localizedUrl !== '') {
				$payload[$key] = $localizedUrl;
			}
		}
		return $payload;
	}

	/**
	 * Process pending queue records.
	 *
	 * @param int $limit Batch limit
	 * @return int Number sent, -1 on failure
	 */
	public function processQueue($limit = 25)
	{
		global $conf;

		$sent = 0;
		$limit = min(100, max(1, $limit));
		if (!$this->discardLegacyQueuedAccessEmails((int) $conf->entity)) {
			return -1;
		}
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
	 * Invalidate access emails queued by an older module version.
	 *
	 * These rows are deliberately not sent: their tokens may be stale and access
	 * emails must only be delivered synchronously from the initiating request.
	 *
	 * @param int $entity Entity
	 * @return bool
	 */
	private function discardLegacyQueuedAccessEmails($entity)
	{
		$codes = array();
		foreach (self::SYNCHRONOUS_ACCESS_EMAILS as $code) {
			$codes[] = "'".$this->db->escape($code)."'";
		}
		$sqlCodes = implode(', ', $codes);
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_notification SET';
		$sql .= ' status = 3, locked_at = NULL, lock_token = NULL,';
		$sql .= " last_error_code = 'ErrorSynchronousAccessEmailRequired'";
		$sql .= ' WHERE entity = '.((int) $entity).' AND status = 0';
		$sql .= ' AND (event_code IN ('.$sqlCodes.') OR template_code IN ('.$sqlCodes.'))';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return true;
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
		$excludedCodes = array();
		foreach (self::SYNCHRONOUS_ACCESS_EMAILS as $code) {
			$excludedCodes[] = "'".$this->db->escape($code)."'";
		}
		$sqlExcludedCodes = implode(', ', $excludedCodes);
		$this->db->begin();
		$sql = 'SELECT rowid, fk_campaign, fk_account, event_code, template_code, recipient_encrypted, locale, payload_encrypted, idempotency_key, attempt_count';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_notification';
		$sql .= ' WHERE entity = '.((int) $entity).' AND status = 0';
		$sql .= ' AND event_code NOT IN ('.$sqlExcludedCodes.')';
		$sql .= ' AND template_code NOT IN ('.$sqlExcludedCodes.')';
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
		$sendResult = $this->sendNativeEmail(
			(int) $record['entity'],
			$record['fk_campaign'] === null ? null : (int) $record['fk_campaign'],
			$recipient,
			(string) $record['locale'],
			(string) $record['template_code'],
			$payload,
			'emergencyhouse-notification-'.((int) $record['id'])
		);
		if ($sendResult < 0) {
			$this->markFailure($record, $this->error !== '' ? $this->error : 'ErrorMailSend');
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
	 * Send one email with Dolibarr's standard mail configuration.
	 *
	 * @param int                       $entity Entity
	 * @param int|null                  $campaignId Campaign ID
	 * @param string                    $recipient Recipient email
	 * @param string                    $locale Locale
	 * @param string                    $templateCode Template code
	 * @param array<string, scalar|null> $payload Template values
	 * @param string                    $trackId Non-sensitive mail tracking ID
	 * @return int 1 on success, -1 on failure
	 */
	private function sendNativeEmail($entity, $campaignId, $recipient, $locale, $templateCode, array $payload, $trackId)
	{
		if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
			$this->error = 'ErrorInvalidEmail';
			return -1;
		}
		$template = $this->fetchTemplate($entity, $campaignId, $templateCode, $locale);
		if (!is_array($template)) {
			return -1;
		}
		$subject = $this->render((string) $template['subject'], $payload);
		$body = $this->render((string) $template['body'], $payload);
		$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', '');
		if ($from === '') {
			$from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', '');
		}
		if ($from === '') {
			$this->error = 'ErrorSenderEmailMissing';
			return -1;
		}
		$safeTrackId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $trackId);
		$safeTrackId = is_string($safeTrackId) && $safeTrackId !== ''
			? substr($safeTrackId, 0, 128)
			: 'emergencyhouse-public';

		// CMailFile applies the native SMTP/send mode, mail hooks, forced
		// recipients and MAIN_MAIL_AUTOCOPY_TO permanent BCC configuration.
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
			$safeTrackId,
			'',
			'standard'
		);
		if (!empty($mail->error) || !$mail->sendfile()) {
			$this->error = !empty($mail->error) ? 'ErrorMailTransport' : 'ErrorMailSend';
			return -1;
		}

		return 1;
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
		$locales = EmergencyHouseLanguageService::getFallbackChain($locale);
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
			$builtInTemplate = $this->fetchBuiltInTemplate($code, $candidateLocale);
			if (is_array($builtInTemplate)) {
				return $builtInTemplate;
			}
		}
		$this->error = 'ErrorNotificationTemplateMissing';
		return false;
	}

	/**
	 * Load a translated default template from the module catalogue.
	 *
	 * @param string $code Template code
	 * @param string $locale Supported locale
	 * @return array{subject:string, body:string}|false
	 */
	private function fetchBuiltInTemplate($code, $locale)
	{
		global $conf;

		$keys = array(
			'account_verification' => array('EmergencyHouseEmailAccountVerificationSubject', 'EmergencyHouseEmailAccountVerificationBody'),
			'password_reset' => array('EmergencyHouseEmailPasswordResetSubject', 'EmergencyHouseEmailPasswordResetBody'),
			'magic_login' => array('EmergencyHouseEmailMagicLoginSubject', 'EmergencyHouseEmailMagicLoginBody'),
			'solicitation_create' => array('EmergencyHouseEmailSolicitationCreateSubject', 'EmergencyHouseEmailSolicitationCreateBody'),
			'solicitation_update' => array('EmergencyHouseEmailSolicitationUpdateSubject', 'EmergencyHouseEmailSolicitationUpdateBody'),
			'message_created' => array('EmergencyHouseEmailMessageCreatedSubject', 'EmergencyHouseEmailMessageCreatedBody'),
			'allocation_create' => array('EmergencyHouseEmailAllocationCreateSubject', 'EmergencyHouseEmailAllocationCreateBody'),
			'allocation_update' => array('EmergencyHouseEmailAllocationUpdateSubject', 'EmergencyHouseEmailAllocationUpdateBody'),
			'offer_confirmation_due' => array('EmergencyHouseEmailOfferConfirmationDueSubject', 'EmergencyHouseEmailOfferConfirmationDueBody'),
			'stay_reminder' => array('EmergencyHouseEmailStayReminderSubject', 'EmergencyHouseEmailStayReminderBody'),
		);
		if (!isset($keys[$code])) {
			return false;
		}

		$outputlangs = new Translate('', $conf);
		$outputlangs->setDefaultLang($locale);
		$outputlangs->load('emergencyhouse@emergencyhouse');
		$subject = $outputlangs->trans($keys[$code][0]);
		$body = $outputlangs->trans($keys[$code][1]);
		if ($subject === $keys[$code][0] || $body === $keys[$code][1]) {
			return false;
		}
		return array('subject' => $subject, 'body' => $body);
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
		$this->error = $errorCode;
		dol_syslog(__METHOD__.': '.$errorCode.' for notification row '.((int) $record['id']), LOG_ERR);
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
