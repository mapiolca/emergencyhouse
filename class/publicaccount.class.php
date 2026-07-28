<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/languageservice.class.php');

/**
 * Public portal identity, deliberately separate from Dolibarr users.
 */
class EmergencyHousePublicAccount
{
	public const STATUS_PENDING = 0;
	public const STATUS_ACTIVE = 1;
	public const STATUS_SUSPENDED = 2;
	public const STATUS_ANONYMIZED = 3;

	/** @var DoliDB */
	public $db;
	/** @var int */
	public $id = 0;
	/** @var int */
	public $entity = 1;
	/** @var int */
	public $fk_member = 0;
	/** @var string */
	public $public_uuid = '';
	/** @var string */
	public $firstname_encrypted = '';
	/** @var string */
	public $lastname_encrypted = '';
	/** @var string */
	public $email_encrypted = '';
	/** @var string */
	public $email_hash = '';
	/** @var string|null */
	public $phone_encrypted;
	/** @var string|null */
	public $phone_hash;
	/** @var string|null */
	public $password_hash;
	/** @var string */
	public $lang = 'fr_FR';
	/** @var string */
	public $preferred_contact = 'email';
	/** @var string|null */
	public $contact_availability;
	/** @var int */
	public $email_verified = 0;
	/** @var int */
	public $phone_verification_level = 0;
	/** @var int */
	public $manual_verification_level = 0;
	/** @var int */
	public $verification_status = 0;
	/** @var int */
	public $adult_confirmed = 0;
	/** @var int */
	public $failed_login_count = 0;
	/** @var int|null */
	public $locked_until;
	/** @var int|null */
	public $last_login;
	/** @var int|null */
	public $last_activity;
	/** @var int */
	public $status = self::STATUS_PENDING;
	/** @var int|null */
	public $date_deletion_requested;
	/** @var int|null */
	public $date_anonymized;
	/** @var int|null */
	public $date_creation;
	/** @var int|null */
	public $tms;
	/** @var string */
	public $error = '';
	/** @var array<int, string> */
	public $errors = array();
	/** @var EmergencyHouseEncryptionService */
	private $encryption;

	/**
	 * Constructor.
	 *
	 * @param DoliDB                               $db Database handler
	 * @param EmergencyHouseEncryptionService|null $encryption Encryption service
	 */
	public function __construct($db, $encryption = null)
	{
		$this->db = $db;
		$this->encryption = $encryption instanceof EmergencyHouseEncryptionService ? $encryption : new EmergencyHouseEncryptionService();
	}

	/**
	 * Create a public identity.
	 *
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $email Email
	 * @param string $phone Phone
	 * @param string $password Password
	 * @return int
	 */
	public function create($firstname, $lastname, $email, $phone, $password)
	{
		global $conf;

		$email = EmergencyHouseEncryptionService::normalizeEmail($email);
		$phone = EmergencyHouseEncryptionService::normalizePhone($phone);
		if (!$this->validateInput($firstname, $lastname, $email, $password)) {
			return -1;
		}
		$this->entity = (int) $conf->entity;
		$this->public_uuid = bin2hex(random_bytes(16));
		$context = $this->encryptionContext();
		$firstnameEncrypted = $this->encryption->encrypt(trim($firstname), $context.'|firstname');
		$lastnameEncrypted = $this->encryption->encrypt(trim($lastname), $context.'|lastname');
		$emailEncrypted = $this->encryption->encrypt($email, $context.'|email');
		$emailHash = $this->encryption->hashLookup($email, 'account-email');
		$phoneEncrypted = $phone === '' ? null : $this->encryption->encrypt($phone, $context.'|phone');
		$phoneHash = $phone === '' ? null : $this->encryption->hashLookup($phone, 'account-phone');
		if (!is_string($firstnameEncrypted) || !is_string($lastnameEncrypted) || !is_string($emailEncrypted) || !is_string($emailHash)
			|| ($phone !== '' && (!is_string($phoneEncrypted) || !is_string($phoneHash)))) {
			$this->error = $this->encryption->error;
			return -1;
		}
		$duplicateSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$duplicateSql .= ' WHERE entity = '.((int) $this->entity);
		$duplicateSql .= " AND email_hash = '".$this->db->escape($emailHash)."'";
		$duplicateResult = $this->db->query($duplicateSql);
		if (!$duplicateResult) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($duplicateResult) > 0) {
			$this->error = 'ErrorAccountAlreadyExists';
			return -1;
		}

		$passwordHash = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
		if (!is_string($passwordHash)) {
			$this->error = 'ErrorPasswordHash';
			return -1;
		}

		$this->db->begin();
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_public_account (';
		$sql .= 'entity, public_uuid, firstname_encrypted, lastname_encrypted, email_encrypted, email_hash, phone_encrypted, phone_hash, password_hash, lang, preferred_contact, email_verified, verification_status, adult_confirmed, status, date_creation, last_activity';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).',';
		$sql .= "'".$this->db->escape($this->public_uuid)."',";
		$sql .= "'".$this->db->escape($firstnameEncrypted)."',";
		$sql .= "'".$this->db->escape($lastnameEncrypted)."',";
		$sql .= "'".$this->db->escape($emailEncrypted)."',";
		$sql .= "'".$this->db->escape($emailHash)."',";
		$sql .= $phoneEncrypted === null ? 'NULL,' : "'".$this->db->escape($phoneEncrypted)."',";
		$sql .= $phoneHash === null ? 'NULL,' : "'".$this->db->escape($phoneHash)."',";
		$sql .= "'".$this->db->escape($passwordHash)."',";
		$sql .= "'".$this->db->escape($this->lang)."',";
		$sql .= "'".$this->db->escape($this->preferred_contact)."',";
		$sql .= '0,0,'.((int) $this->adult_confirmed).','.self::STATUS_PENDING.',';
		$sql .= "'".$this->db->idate(dol_now())."',";
		$sql .= "'".$this->db->idate(dol_now())."')";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_public_account');
		$this->firstname_encrypted = $firstnameEncrypted;
		$this->lastname_encrypted = $lastnameEncrypted;
		$this->email_encrypted = $emailEncrypted;
		$this->email_hash = $emailHash;
		$this->phone_encrypted = $phoneEncrypted;
		$this->phone_hash = $phoneHash;
		$this->password_hash = $passwordHash;
		$this->status = self::STATUS_PENDING;
		$this->date_creation = dol_now();
		$this->last_activity = $this->date_creation;
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load account by ID.
	 *
	 * @param int      $id     Account ID
	 * @param int|null $entity Entity owning the account, current entity by default
	 * @return int
	 */
	public function fetch($id, $entity = null)
	{
		global $conf;

		$targetEntity = $entity === null ? (int) $conf->entity : (int) $entity;
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= ' WHERE rowid = '.((int) $id);
		$sql .= ' AND entity = '.$targetEntity;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			return 0;
		}
		$this->hydrate($obj);
		return 1;
	}

	/**
	 * Load by normalized email without revealing whether it exists publicly.
	 *
	 * @param string $email Email
	 * @return int
	 */
	public function fetchByEmail($email)
	{
		global $conf;

		$hash = $this->encryption->hashLookup(EmergencyHouseEncryptionService::normalizeEmail($email), 'account-email');
		if (!is_string($hash)) {
			$this->error = $this->encryption->error;
			return -1;
		}
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= " WHERE email_hash = '".$this->db->escape($hash)."'";
		$sql .= ' AND entity = '.((int) $conf->entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			return 0;
		}
		$this->hydrate($obj);
		return 1;
	}

	/**
	 * Verify a password, including lock state.
	 *
	 * @param string $password Password
	 * @return bool
	 */
	public function verifyPassword($password)
	{
		if ($this->status !== self::STATUS_ACTIVE || empty($this->email_verified)) {
			return false;
		}
		if (!empty($this->locked_until) && $this->locked_until > dol_now()) {
			return false;
		}
		return is_string($this->password_hash) && password_verify($password, $this->password_hash);
	}

	/**
	 * Register failed login and apply a bounded lock.
	 *
	 * @return int
	 */
	public function registerFailedLogin()
	{
		$this->failed_login_count++;
		$maximumAttempts = max(3, getDolGlobalInt('EMERGENCYHOUSE_MAX_LOGIN_ATTEMPTS', 5));
		$lockUntil = $this->failed_login_count >= $maximumAttempts
			? dol_time_plus_duree(dol_now(), min(60, $this->failed_login_count * 5), 'i')
			: null;
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account SET';
		$sql .= ' failed_login_count = '.((int) $this->failed_login_count).',';
		$sql .= ' locked_until = '.($lockUntil === null ? 'NULL' : "'".$this->db->idate($lockUntil)."'");
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->locked_until = $lockUntil;
		return 1;
	}

	/**
	 * Register successful login.
	 *
	 * @return int
	 */
	public function registerSuccessfulLogin()
	{
		$now = dol_now();
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account SET';
		$sql .= ' failed_login_count = 0, locked_until = NULL,';
		$sql .= " last_login = '".$this->db->idate($now)."',";
		$sql .= " last_activity = '".$this->db->idate($now)."'";
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->failed_login_count = 0;
		$this->locked_until = null;
		$this->last_login = $now;
		$this->last_activity = $now;
		return 1;
	}

	/**
	 * Mark email verified and activate the account.
	 *
	 * @return int
	 */
	public function markEmailVerified()
	{
		dol_include_once('/emergencyhouse/class/verificationservice.class.php');

		$this->db->begin();
		$verificationService = new EmergencyHouseVerificationService($this->db);
		if (!$verificationService->lockSubmissionTarget((int) $this->entity, 'account', (int) $this->id)) {
			$this->error = $verificationService->error;
			$this->db->rollback();
			return -1;
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= ' SET email_verified = 1, verification_status = 0, status = '.self::STATUS_ACTIVE;
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		if ($verificationService->enqueueTarget(
			(int) $this->entity,
			'account',
			(int) $this->id,
			dol_now(),
			false
		) <= 0) {
			$this->error = $verificationService->error;
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		$this->email_verified = 1;
		$this->verification_status = 0;
		$this->status = self::STATUS_ACTIVE;
		return 1;
	}

	/**
	 * Replace the account password after a validated reset token.
	 *
	 * @param string $password Plain password
	 * @return int
	 */
	public function setPassword($password)
	{
		if (!$this->validatePassword($password)) {
			return -1;
		}
		$passwordHash = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
		if (!is_string($passwordHash)) {
			$this->error = 'ErrorPasswordHash';
			return -1;
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account SET';
		$sql .= " password_hash = '".$this->db->escape($passwordHash)."',";
		$sql .= ' failed_login_count = 0, locked_until = NULL';
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->password_hash = $passwordHash;
		$this->failed_login_count = 0;
		$this->locked_until = null;
		return 1;
	}

	/**
	 * Request account deletion without immediately erasing active records.
	 *
	 * @return int
	 */
	public function requestDeletion()
	{
		$now = dol_now();
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= " SET date_deletion_requested = '".$this->db->idate($now)."'";
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->date_deletion_requested = $now;
		return 1;
	}

	/**
	 * Persist a supported interface and notification language.
	 *
	 * @param string $locale Supported locale
	 * @return int
	 */
	public function updateLanguage($locale)
	{
		$locale = EmergencyHouseLanguageService::normalizeLocale($locale);
		if ($this->id <= 0 || $this->entity <= 0 || $locale === '') {
			$this->error = 'ErrorInvalidLanguage';
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= " SET lang = '".$this->db->escape($locale)."'";
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->lang = $locale;
		return 1;
	}

	/**
	 * Link this public account to one native Dolibarr member.
	 *
	 * @param int $memberId Native member ID
	 * @return int
	 */
	public function linkMember($memberId)
	{
		if ($this->id <= 0 || $this->entity <= 0 || $memberId <= 0) {
			$this->error = 'ErrorMemberLinkInvalid';
			return -1;
		}

		$conflictSql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$conflictSql .= ' WHERE entity = '.((int) $this->entity);
		$conflictSql .= ' AND fk_member = '.((int) $memberId);
		$conflictSql .= ' AND rowid <> '.((int) $this->id);
		$conflictResult = $this->db->query($conflictSql);
		if (!$conflictResult) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($conflictResult) > 0) {
			$this->error = 'ErrorMemberLinkConflict';
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= ' SET fk_member = '.((int) $memberId);
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		$sql .= ' AND (fk_member IS NULL OR fk_member = '.((int) $memberId).')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$updateError = $this->db->lasterror();
			$conflictResult = $this->db->query($conflictSql);
			$this->error = $conflictResult && $this->db->num_rows($conflictResult) > 0
				? 'ErrorMemberLinkConflict'
				: $updateError;
			return -1;
		}

		if ((int) $this->db->affected_rows($resql) === 0) {
			$checkSql = 'SELECT fk_member FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
			$checkSql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
			$checkResult = $this->db->query($checkSql);
			$check = $checkResult ? $this->db->fetch_object($checkResult) : false;
			if (!is_object($check) || (int) $check->fk_member !== $memberId) {
				$this->error = 'ErrorMemberLinkConflict';
				return -1;
			}
		}

		$this->fk_member = $memberId;
		return 1;
	}

	/**
	 * Return only the decrypted identity fields.
	 *
	 * @return array{firstname:string, lastname:string}|false
	 */
	public function getDecryptedIdentity()
	{
		$context = $this->encryptionContext();
		$firstname = $this->encryption->decrypt($this->firstname_encrypted, $context.'|firstname');
		$lastname = $this->encryption->decrypt($this->lastname_encrypted, $context.'|lastname');
		if (!is_string($firstname) || !is_string($lastname)) {
			$this->error = $this->encryption->error;
			return false;
		}

		return array('firstname' => $firstname, 'lastname' => $lastname);
	}

	/**
	 * Return decrypted profile fields.
	 *
	 * @return array{firstname:string, lastname:string, email:string, phone:string}|false
	 */
	public function getDecryptedProfile()
	{
		$identity = $this->getDecryptedIdentity();
		if (!is_array($identity)) {
			return false;
		}
		$context = $this->encryptionContext();
		$email = $this->encryption->decrypt($this->email_encrypted, $context.'|email');
		$phone = '';
		if (is_string($this->phone_encrypted) && $this->phone_encrypted !== '') {
			$decryptedPhone = $this->encryption->decrypt($this->phone_encrypted, $context.'|phone');
			if (!is_string($decryptedPhone)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$phone = $decryptedPhone;
		}
		if (!is_string($email)) {
			$this->error = $this->encryption->error;
			return false;
		}
		return array(
			'firstname' => $identity['firstname'],
			'lastname' => $identity['lastname'],
			'email' => $email,
			'phone' => $phone,
		);
	}

	/**
	 * Anonymize personal data after retention checks have been performed.
	 *
	 * @return int
	 */
	public function anonymize()
	{
		$marker = 'anonymized-'.$this->public_uuid;
		$context = $this->encryptionContext();
		$encryptedMarker = $this->encryption->encrypt($marker, $context.'|firstname');
		$encryptedLast = $this->encryption->encrypt($marker, $context.'|lastname');
		$encryptedEmail = $this->encryption->encrypt($marker.'@invalid.local', $context.'|email');
		if (!is_string($encryptedMarker) || !is_string($encryptedLast) || !is_string($encryptedEmail)) {
			$this->error = $this->encryption->error;
			return -1;
		}
		$randomHash = hash('sha256', random_bytes(32));
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_account SET';
		$sql .= " firstname_encrypted = '".$this->db->escape($encryptedMarker)."',";
		$sql .= " lastname_encrypted = '".$this->db->escape($encryptedLast)."',";
		$sql .= " email_encrypted = '".$this->db->escape($encryptedEmail)."',";
		$sql .= " email_hash = '".$this->db->escape($randomHash)."',";
		$sql .= ' phone_encrypted = NULL, phone_hash = NULL, password_hash = NULL, fk_member = NULL,';
		$sql .= ' status = '.self::STATUS_ANONYMIZED.',';
		$sql .= " date_anonymized = '".$this->db->idate(dol_now())."'";
		$sql .= ' WHERE rowid = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->status = self::STATUS_ANONYMIZED;
		$this->fk_member = 0;
		return 1;
	}

	/**
	 * Validate account creation input.
	 *
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $email Email
	 * @param string $password Password
	 * @return bool
	 */
	private function validateInput($firstname, $lastname, $email, $password)
	{
		$this->errors = array();
		if (trim($firstname) === '' || trim($lastname) === '') {
			$this->errors[] = 'ErrorNameRequired';
		}
		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			$this->errors[] = 'ErrorInvalidEmail';
		}
		if (!$this->validatePassword($password)) {
			$this->errors[] = 'ErrorPasswordPolicy';
		}
		if (!$this->encryption->isAvailable()) {
			$this->errors[] = 'ErrorEncryptionKeyUnavailable';
		}
		if (!empty($this->errors)) {
			$this->error = $this->errors[0];
			return false;
		}
		return true;
	}

	/**
	 * Validate the public password policy.
	 *
	 * @param string $password Password
	 * @return bool
	 */
	private function validatePassword($password)
	{
		if (strlen($password) < 12 || strlen($password) > 4096) {
			$this->error = 'ErrorPasswordPolicy';
			return false;
		}
		if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
			$this->error = 'ErrorPasswordPolicy';
			return false;
		}
		return true;
	}

	/**
	 * Hydrate properties from a SQL row.
	 *
	 * @param object $obj SQL row
	 * @return void
	 */
	private function hydrate($obj)
	{
		foreach (get_object_vars($obj) as $property => $value) {
			if ($property === 'rowid') {
				$this->id = (int) $value;
			} elseif (property_exists($this, $property)) {
				if (in_array($property, array(
					'entity', 'fk_member', 'email_verified', 'phone_verification_level', 'manual_verification_level',
					'verification_status',
					'adult_confirmed', 'failed_login_count', 'status',
				), true)) {
					$this->{$property} = (int) $value;
				} elseif (in_array($property, array(
					'locked_until', 'last_login', 'last_activity', 'date_deletion_requested',
					'date_anonymized', 'date_creation', 'tms',
				), true)) {
					$this->{$property} = empty($value) ? null : $this->db->jdate($value);
				} else {
					$this->{$property} = $value;
				}
			}
		}
	}

	/**
	 * Associated context for per-account encryption.
	 *
	 * @return string
	 */
	private function encryptionContext()
	{
		return 'emergencyhouse|account|'.$this->entity.'|'.$this->public_uuid;
	}
}
