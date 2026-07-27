<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');

/**
 * Public authentication independent from Dolibarr user accounts.
 */
class EmergencyHousePublicAuthService
{
	private const COOKIE_NAME = 'EMERGENCYHOUSESESSID';

	/** @var DoliDB */
	private $db;
	/** @var EmergencyHouseEncryptionService */
	private $encryption;
	/** @var string */
	public $error = '';
	/** @var int */
	private $sessionId = 0;
	/** @var string */
	private $sessionHash = '';
	/** @var string */
	private $csrfSecret = '';
	/** @var EmergencyHousePublicAccount|null */
	private $account;

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
	 * Authenticate credentials and establish a session.
	 *
	 * @param string $email Email
	 * @param string $password Password
	 * @param string $ipAddress Source IP
	 * @param string $userAgent User agent
	 * @return EmergencyHousePublicAccount|false
	 */
	public function login($email, $password, $ipAddress, $userAgent)
	{
		$account = new EmergencyHousePublicAccount($this->db, $this->encryption);
		$fetchResult = $account->fetchByEmail($email);
		if ($fetchResult <= 0 || !$account->verifyPassword($password)) {
			if ($fetchResult > 0) {
				$account->registerFailedLogin();
			}
			$this->error = 'ErrorAuthenticationFailed';
			return false;
		}
		if ($account->registerSuccessfulLogin() < 0) {
			$this->error = $account->error;
			return false;
		}
		if (!$this->createSession($account, $ipAddress, $userAgent)) {
			return false;
		}
		$this->account = $account;
		return $account;
	}

	/**
	 * Create a server-side session and secure cookie.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param string                      $ipAddress Source IP
	 * @param string                      $userAgent User agent
	 * @return bool
	 */
	public function createSession($account, $ipAddress, $userAgent)
	{
		$rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
		$sessionHash = hash('sha256', $rawToken);
		$csrfSecret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
		$encryptedCsrf = $this->encryption->encrypt($csrfSecret, $this->sessionContext((int) $account->entity, $sessionHash));
		$ipHash = $this->encryption->hashLookup($ipAddress, 'session-ip');
		$userAgentHash = $this->encryption->hashLookup($userAgent, 'session-user-agent');
		if (!is_string($encryptedCsrf) || !is_string($ipHash) || !is_string($userAgentHash)) {
			$this->error = $this->encryption->error;
			return false;
		}
		$durationMinutes = max(15, getDolGlobalInt('EMERGENCYHOUSE_SESSION_IDLE_MINUTES', 120));
		$now = dol_now();
		$expiresAt = dol_time_plus_duree($now, $durationMinutes, 'i');

		$this->db->begin();
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_public_session';
		$sql .= ' (entity, fk_account, session_hash, csrf_secret_encrypted, ip_hash, user_agent_hash, date_creation, last_activity, expires_at)';
		$sql .= ' VALUES ('.((int) $account->entity).', '.((int) $account->id).',';
		$sql .= " '".$this->db->escape($sessionHash)."', '".$this->db->escape($encryptedCsrf)."',";
		$sql .= " '".$this->db->escape($ipHash)."', '".$this->db->escape($userAgentHash)."',";
		$sql .= " '".$this->db->idate($now)."', '".$this->db->idate($now)."', '".$this->db->idate($expiresAt)."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}
		$this->sessionId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_public_session');
		$this->sessionHash = $sessionHash;
		$this->csrfSecret = $csrfSecret;
		$this->db->commit();

		$this->setSessionCookie($rawToken, $expiresAt);
		return true;
	}

	/**
	 * Authenticate the current public cookie.
	 *
	 * @param string $ipAddress Source IP
	 * @param string $userAgent User agent
	 * @return EmergencyHousePublicAccount|false
	 */
	public function authenticateFromCookie($ipAddress, $userAgent)
	{
		global $conf;

		$rawToken = isset($_COOKIE[self::COOKIE_NAME]) && is_string($_COOKIE[self::COOKIE_NAME])
			? $_COOKIE[self::COOKIE_NAME]
			: '';
		if ($rawToken === '' || !preg_match('/^[A-Za-z0-9_-]{40,128}$/', $rawToken)) {
			$this->error = 'ErrorAuthenticationRequired';
			return false;
		}
		$sessionHash = hash('sha256', $rawToken);
		$sql = 'SELECT rowid, fk_account, csrf_secret_encrypted, ip_hash, user_agent_hash, expires_at';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_session';
		$sql .= " WHERE session_hash = '".$this->db->escape($sessionHash)."'";
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' AND revoked_at IS NULL';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj) || $this->db->jdate($obj->expires_at) <= dol_now()) {
			$this->error = 'ErrorAuthenticationRequired';
			return false;
		}
		$expectedIpHash = $this->encryption->hashLookup($ipAddress, 'session-ip');
		$expectedUserAgentHash = $this->encryption->hashLookup($userAgent, 'session-user-agent');
		if (!is_string($expectedIpHash) || !is_string($expectedUserAgentHash)
			|| !hash_equals((string) $obj->user_agent_hash, $expectedUserAgentHash)) {
			$this->error = 'ErrorSessionFingerprintMismatch';
			return false;
		}
		$csrfSecret = $this->encryption->decrypt(
			(string) $obj->csrf_secret_encrypted,
			$this->sessionContext((int) $conf->entity, $sessionHash)
		);
		if (!is_string($csrfSecret)) {
			$this->error = $this->encryption->error;
			return false;
		}
		$account = new EmergencyHousePublicAccount($this->db, $this->encryption);
		if ($account->fetch((int) $obj->fk_account) <= 0 || $account->status !== EmergencyHousePublicAccount::STATUS_ACTIVE) {
			$this->error = 'ErrorAuthenticationRequired';
			return false;
		}

		$this->sessionId = (int) $obj->rowid;
		$this->sessionHash = $sessionHash;
		$this->csrfSecret = $csrfSecret;
		$this->account = $account;
		$this->touchSession($rawToken, $expectedIpHash);
		return $account;
	}

	/**
	 * Return a session-bound CSRF token for a stable action.
	 *
	 * @param string $action Action name
	 * @return string
	 */
	public function csrfToken($action)
	{
		if ($this->sessionHash === '' || $this->csrfSecret === '') {
			return '';
		}
		return hash_hmac('sha256', $action.'|'.$this->sessionHash, $this->csrfSecret);
	}

	/**
	 * Verify a session-bound CSRF token.
	 *
	 * @param string $action Action name
	 * @param string $token Submitted token
	 * @return bool
	 */
	public function verifyCsrfToken($action, $token)
	{
		$expected = $this->csrfToken($action);
		return $expected !== '' && $token !== '' && hash_equals($expected, $token);
	}

	/**
	 * Create a single-use account token.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param string                      $type Token type
	 * @param int                         $ttlSeconds Lifetime
	 * @return string|false
	 */
	public function issueToken($account, $type, $ttlSeconds)
	{
		if (!in_array($type, array('email_verification', 'password_reset', 'email_change', 'magic_login'), true)) {
			$this->error = 'ErrorInvalidTokenType';
			return false;
		}
		$rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
		$tokenHash = hash('sha256', $rawToken);
		$now = dol_now();
		$expiresAt = $now + max(300, $ttlSeconds);
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_token';
		$sql .= ' (entity, fk_account, token_type, token_hash, date_creation, expires_at, attempt_count)';
		$sql .= ' VALUES ('.((int) $account->entity).', '.((int) $account->id).',';
		$sql .= " '".$this->db->escape($type)."', '".$this->db->escape($tokenHash)."',";
		$sql .= " '".$this->db->idate($now)."', '".$this->db->idate($expiresAt)."', 0)";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return $rawToken;
	}

	/**
	 * Consume a token atomically.
	 *
	 * @param string $rawToken Raw token
	 * @param string $type Expected type
	 * @return EmergencyHousePublicAccount|false
	 */
	public function consumeToken($rawToken, $type)
	{
		global $conf;

		if (!preg_match('/^[A-Za-z0-9_-]{40,128}$/', $rawToken)) {
			$this->error = 'ErrorTokenInvalid';
			return false;
		}
		$tokenHash = hash('sha256', $rawToken);
		$this->db->begin();
		$sql = 'SELECT rowid, fk_account, expires_at, used_at, attempt_count';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_token';
		$sql .= " WHERE token_hash = '".$this->db->escape($tokenHash)."'";
		$sql .= " AND token_type = '".$this->db->escape($type)."'";
		$sql .= ' AND entity = '.((int) $conf->entity).' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj) || !empty($obj->used_at) || $this->db->jdate($obj->expires_at) <= dol_now() || (int) $obj->attempt_count >= 5) {
			$this->error = 'ErrorTokenInvalid';
			$this->db->rollback();
			return false;
		}
		$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_token';
		$sqlUpdate .= " SET used_at = '".$this->db->idate(dol_now())."', attempt_count = attempt_count + 1";
		$sqlUpdate .= ' WHERE rowid = '.((int) $obj->rowid);
		if (!$this->db->query($sqlUpdate)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}
		$account = new EmergencyHousePublicAccount($this->db, $this->encryption);
		if ($account->fetch((int) $obj->fk_account) <= 0) {
			$this->error = 'ErrorTokenInvalid';
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return $account;
	}

	/**
	 * Revoke current session and clear its cookie.
	 *
	 * @return int
	 */
	public function logout()
	{
		$result = 1;
		if ($this->sessionId > 0) {
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_session';
			$sql .= " SET revoked_at = '".$this->db->idate(dol_now())."'";
			$sql .= ' WHERE rowid = '.((int) $this->sessionId);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$result = -1;
			}
		}
		$this->setSessionCookie('', dol_now() - 3600);
		$this->sessionId = 0;
		$this->sessionHash = '';
		$this->csrfSecret = '';
		$this->account = null;
		return $result;
	}

	/**
	 * Revoke every public session for an account.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @return int
	 */
	public function revokeAllSessions($account)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_session';
		$sql .= " SET revoked_at = '".$this->db->idate(dol_now())."'";
		$sql .= ' WHERE entity = '.((int) $account->entity);
		$sql .= ' AND fk_account = '.((int) $account->id);
		$sql .= ' AND revoked_at IS NULL';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Return the authenticated account.
	 *
	 * @return EmergencyHousePublicAccount|null
	 */
	public function getAccount()
	{
		return $this->account;
	}

	/**
	 * Refresh activity and the configured idle expiration.
	 *
	 * @param string $rawToken Raw session token
	 * @param string $ipHash Current network fingerprint
	 * @return void
	 */
	private function touchSession($rawToken, $ipHash)
	{
		if ($this->sessionId <= 0) {
			return;
		}
		$expiresAt = dol_time_plus_duree(
			dol_now(),
			max(15, getDolGlobalInt('EMERGENCYHOUSE_SESSION_IDLE_MINUTES', 120)),
			'i'
		);
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_public_session';
		$sql .= " SET last_activity = '".$this->db->idate(dol_now())."',";
		$sql .= " expires_at = '".$this->db->idate($expiresAt)."',";
		$sql .= " ip_hash = '".$this->db->escape($ipHash)."'";
		$sql .= ' WHERE rowid = '.((int) $this->sessionId);
		if (!$this->db->query($sql)) {
			dol_syslog(__METHOD__.': '.$this->db->lasterror(), LOG_WARNING);
			return;
		}
		$this->setSessionCookie($rawToken, $expiresAt);
	}

	/**
	 * Set or delete session cookie.
	 *
	 * @param string $value Cookie value
	 * @param int    $expires Expiration timestamp
	 * @return void
	 */
	private function setSessionCookie($value, $expires)
	{
		$https = isset($_SERVER['HTTPS']) && is_string($_SERVER['HTTPS'])
			? strtolower($_SERVER['HTTPS'])
			: '';
		$forwardedProtocol = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && is_string($_SERVER['HTTP_X_FORWARDED_PROTO'])
			? strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]))
			: '';
		$secure = ($https !== '' && $https !== 'off' && $https !== '0') || $forwardedProtocol === 'https';
		$options = array(
			'expires' => $expires,
			'path' => '/',
			'secure' => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		);
		setcookie(self::COOKIE_NAME, $value, $options);
	}

	/**
	 * Encryption associated data for session secrets.
	 *
	 * @param int    $entity Entity
	 * @param string $sessionHash Session hash
	 * @return string
	 */
	private function sessionContext($entity, $sessionHash)
	{
		return 'emergencyhouse|session|'.$entity.'|'.$sessionHash;
	}
}
