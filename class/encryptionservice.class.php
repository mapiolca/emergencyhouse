<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Authenticated encryption and deterministic lookup hashes.
 *
 * The master key is read from an environment variable. It is never persisted
 * by the module.
 */
class EmergencyHouseEncryptionService
{
	private const PAYLOAD_VERSION = 'v1';

	/** @var string|null */
	private $key;
	/** @var string|null */
	private $lookupKey;
	/** @var bool */
	private $sodiumAvailable = false;
	/** @var bool */
	private $encryptionKeyConfigured = false;
	/** @var bool */
	private $hmacKeyConfigured = false;
	/** @var bool */
	private $keysDistinct = false;
	/** @var string */
	public $error = '';

	/**
	 * Constructor.
	 *
	 * @param string|null $environmentVariable Environment variable containing the key
	 */
	public function __construct($environmentVariable = null)
	{
		$this->key = null;
		$this->lookupKey = null;
		$this->sodiumAvailable = extension_loaded('sodium');
		if (!$this->sodiumAvailable) {
			$this->error = 'ErrorSodiumUnavailable';
			return;
		}

		$variable = $environmentVariable;
		if (empty($variable)) {
			$variable = getDolGlobalString('EMERGENCYHOUSE_ENCRYPTION_KEY_ENV', 'EMERGENCYHOUSE_ENCRYPTION_KEY');
		}
		$material = null;
		$value = getenv($variable);
		if (!is_string($value) || $value === '') {
			$this->error = 'ErrorEncryptionKeyUnavailable';
		} else {
			$material = $this->decodeKeyMaterial($value);
		}
		if (is_string($material) && strlen($material) < 32) {
			$this->error = 'ErrorEncryptionKeyTooShort';
			$material = null;
		} elseif (is_string($material)) {
			$this->encryptionKeyConfigured = true;
		}

		$lookupVariable = getDolGlobalString('EMERGENCYHOUSE_HMAC_KEY_ENV', 'EMERGENCYHOUSE_HMAC_KEY');
		$lookupMaterial = null;
		$lookupValue = getenv($lookupVariable);
		if (!is_string($lookupValue) || $lookupValue === '') {
			if ($this->error === '') {
				$this->error = 'ErrorHmacKeyUnavailable';
			}
		} else {
			$lookupMaterial = $this->decodeKeyMaterial($lookupValue);
		}
		if (is_string($lookupMaterial) && strlen($lookupMaterial) < 32) {
			if ($this->error === '') {
				$this->error = 'ErrorHmacKeyTooShort';
			}
			$lookupMaterial = null;
		} elseif (is_string($lookupMaterial)) {
			$this->hmacKeyConfigured = true;
		}
		if (!is_string($material) || !is_string($lookupMaterial)) {
			return;
		}
		if (hash_equals($material, $lookupMaterial)) {
			$this->error = 'ErrorEncryptionAndHmacKeysMustDiffer';
			return;
		}
		$this->keysDistinct = true;

		$this->key = sodium_crypto_generichash(
			$material,
			'',
			SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES
		);
		$this->lookupKey = sodium_crypto_generichash($lookupMaterial, '', 32);
	}

	/**
	 * Check whether encryption is usable.
	 *
	 * @return bool
	 */
	public function isAvailable()
	{
		return $this->sodiumAvailable
			&& is_string($this->key)
			&& strlen($this->key) === SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES
			&& is_string($this->lookupKey)
			&& strlen($this->lookupKey) === 32;
	}

	/**
	 * Return a non-sensitive configuration diagnostic for the setup page.
	 *
	 * @return array{sodium:bool,encryption_key:bool,hmac_key:bool,distinct:bool,available:bool,error:string}
	 */
	public function getConfigurationStatus()
	{
		return array(
			'sodium' => $this->sodiumAvailable,
			'encryption_key' => $this->encryptionKeyConfigured,
			'hmac_key' => $this->hmacKeyConfigured,
			'distinct' => $this->keysDistinct,
			'available' => $this->isAvailable(),
			'error' => $this->error,
		);
	}

	/**
	 * Encrypt a value with authenticated associated context.
	 *
	 * @param string $plaintext Plaintext
	 * @param string $context Associated context
	 * @return string|false
	 */
	public function encrypt($plaintext, $context)
	{
		if (!$this->isAvailable()) {
			if ($this->error === '') {
				$this->error = 'ErrorEncryptionKeyUnavailable';
			}
			return false;
		}
		$nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
			$plaintext,
			$context,
			$nonce,
			$this->key
		);
		return self::PAYLOAD_VERSION.'.'.base64_encode($nonce.$ciphertext);
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $payload Encrypted payload
	 * @param string $context Associated context
	 * @return string|false
	 */
	public function decrypt($payload, $context)
	{
		if (!$this->isAvailable()) {
			if ($this->error === '') {
				$this->error = 'ErrorEncryptionKeyUnavailable';
			}
			return false;
		}
		$parts = explode('.', $payload, 2);
		if (count($parts) !== 2 || $parts[0] !== self::PAYLOAD_VERSION) {
			$this->error = 'ErrorEncryptedPayloadInvalid';
			return false;
		}
		$binary = base64_decode($parts[1], true);
		if (!is_string($binary) || strlen($binary) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
			$this->error = 'ErrorEncryptedPayloadInvalid';
			return false;
		}
		$nonce = substr($binary, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
		$ciphertext = substr($binary, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
		$plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			$ciphertext,
			$context,
			$nonce,
			$this->key
		);
		if (!is_string($plaintext)) {
			$this->error = 'ErrorEncryptedPayloadAuthentication';
			return false;
		}
		return $plaintext;
	}

	/**
	 * Build a keyed deterministic lookup hash.
	 *
	 * @param string $normalizedValue Normalized value
	 * @param string $purpose Purpose domain
	 * @return string|false
	 */
	public function hashLookup($normalizedValue, $purpose)
	{
		if (!$this->isAvailable()) {
			if ($this->error === '') {
				$this->error = 'ErrorEncryptionKeyUnavailable';
			}
			return false;
		}
		return sodium_bin2hex(sodium_crypto_generichash($purpose."\0".$normalizedValue, $this->lookupKey, 32));
	}

	/**
	 * Decode canonical base64 values and otherwise keep the raw key material.
	 *
	 * @param string $value Environment value
	 * @return string
	 */
	private function decodeKeyMaterial($value)
	{
		$decoded = base64_decode($value, true);
		if (!is_string($decoded)) {
			return $value;
		}
		$canonicalInput = rtrim($value, '=');
		$canonicalDecoded = rtrim(base64_encode($decoded), '=');
		return hash_equals($canonicalInput, $canonicalDecoded) ? $decoded : $value;
	}

	/**
	 * Normalize an email used for lookup.
	 *
	 * @param string $email Email
	 * @return string
	 */
	public static function normalizeEmail($email)
	{
		$email = trim($email);
		return function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
	}

	/**
	 * Normalize a telephone number used for lookup.
	 *
	 * @param string $phone Phone
	 * @return string
	 */
	public static function normalizePhone($phone)
	{
		$normalized = preg_replace('/[^0-9+]/', '', trim($phone));
		return is_string($normalized) ? $normalized : '';
	}
}
