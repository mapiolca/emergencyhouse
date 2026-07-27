<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone contract for Dolibarr-managed security keys.
 */

if (!extension_loaded('sodium')) {
	fwrite(STDERR, 'Sodium extension is required.'.PHP_EOL);
	exit(1);
}

$GLOBALS['emergencyhouseSecurityTestConstants'] = array();

/**
 * Minimal Dolibarr constant reader used by this standalone contract.
 *
 * @param string $name    Constant name
 * @param string $default Default value
 * @return string
 */
function getDolGlobalString($name, $default = '')
{
	$values = $GLOBALS['emergencyhouseSecurityTestConstants'];
	return isset($values[$name]) && is_string($values[$name]) ? $values[$name] : $default;
}

require_once dirname(__DIR__).'/class/encryptionservice.class.php';

$encryptionValue = base64_encode(str_repeat('E', 48));
$hmacValue = base64_encode(str_repeat('H', 48));
$GLOBALS['emergencyhouseSecurityTestConstants'] = array(
	EmergencyHouseEncryptionService::ENCRYPTION_KEY_NAME => $encryptionValue,
	EmergencyHouseEncryptionService::HMAC_KEY_NAME => $hmacValue,
);

$service = new EmergencyHouseEncryptionService();
$status = $service->getConfigurationStatus();
if (!$status['available'] || !$status['distinct'] || $status['source'] !== 'dolibarr') {
	fwrite(STDERR, 'The service did not load the two Dolibarr-managed keys.'.PHP_EOL);
	exit(1);
}

$payload = $service->encrypt('contract-value', 'security-test');
if (!is_string($payload) || $service->decrypt($payload, 'security-test') !== 'contract-value') {
	fwrite(STDERR, 'Authenticated encryption round trip failed.'.PHP_EOL);
	exit(1);
}

$firstHash = $service->hashLookup('normalized-value', 'security-test');
$secondHash = $service->hashLookup('normalized-value', 'security-test');
if (!is_string($firstHash) || !hash_equals($firstHash, (string) $secondHash)) {
	fwrite(STDERR, 'Deterministic lookup hash failed.'.PHP_EOL);
	exit(1);
}

$GLOBALS['emergencyhouseSecurityTestConstants'][EmergencyHouseEncryptionService::HMAC_KEY_NAME] = $encryptionValue;
$invalidService = new EmergencyHouseEncryptionService();
$invalidStatus = $invalidService->getConfigurationStatus();
if ($invalidStatus['available'] || $invalidStatus['distinct']) {
	fwrite(STDERR, 'Identical managed values were not rejected.'.PHP_EOL);
	exit(1);
}

$GLOBALS['emergencyhouseSecurityTestConstants'] = array();
putenv(EmergencyHouseEncryptionService::ENCRYPTION_KEY_NAME.'='.$encryptionValue);
putenv(EmergencyHouseEncryptionService::HMAC_KEY_NAME.'='.$hmacValue);
$legacyService = new EmergencyHouseEncryptionService();
$legacyStatus = $legacyService->getConfigurationStatus();
if (!$legacyStatus['available'] || $legacyStatus['source'] !== 'environment') {
	fwrite(STDERR, 'The legacy environment fallback is not available.'.PHP_EOL);
	exit(1);
}

putenv(EmergencyHouseEncryptionService::ENCRYPTION_KEY_NAME);
putenv(EmergencyHouseEncryptionService::HMAC_KEY_NAME);

print 'Security key contract: Dolibarr constants, legacy fallback, encryption and separation validated.'.PHP_EOL;
