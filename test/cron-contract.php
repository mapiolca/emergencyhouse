<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone scheduled-job return-contract checks.
 *
 * This script does not bootstrap Dolibarr. It verifies that every method
 * exposed through modEmergencyHouse::$cronjobs follows the native contract:
 * 0 on success and -1 on failure, with business counters stored in output.
 */

$root = dirname(__DIR__);
$cronPath = $root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'emergencyhousecron.class.php';
$cronSource = @file_get_contents($cronPath);
if (!is_string($cronSource)) {
	fwrite(STDERR, 'Unable to read '.$cronPath.PHP_EOL);
	exit(1);
}

$failures = array();

/**
 * Extract one method body from PHP source.
 *
 * @param string $source PHP source
 * @param string $method Method name
 * @return string|null
 */
function emergencyhouseCronMethodBody($source, $method)
{
	$tokens = token_get_all($source);
	$waitingForName = false;
	$waitingForBody = false;
	$depth = 0;
	$body = '';

	foreach ($tokens as $token) {
		$text = is_array($token) ? $token[1] : $token;
		if ($depth > 0) {
			$body .= $text;
			if ($text === '{') {
				$depth++;
			} elseif ($text === '}') {
				$depth--;
				if ($depth === 0) {
					return $body;
				}
			}
			continue;
		}
		if (is_array($token) && $token[0] === T_FUNCTION) {
			$waitingForName = true;
			$waitingForBody = false;
			continue;
		}
		if ($waitingForName && is_array($token) && $token[0] === T_STRING) {
			$waitingForName = false;
			$waitingForBody = $token[1] === $method;
			continue;
		}
		if ($waitingForBody && $text === '{') {
			$depth = 1;
			$body = '{';
		}
	}

	return null;
}

/**
 * Record a failed contract.
 *
 * @param bool   $condition Contract result
 * @param string $message Failure message
 * @return void
 */
function emergencyhouseCronContract($condition, $message)
{
	global $failures;

	if (!$condition) {
		$failures[] = $message;
	}
}

emergencyhouseCronContract(
	strpos($cronSource, "public \$output = '';") !== false,
	'The cron class must expose the native output property.'
);

$countMethods = array(
	'processNotificationQueue',
	'processMatchingQueue',
	'expireRecords',
	'requestAvailabilityConfirmations',
	'sendStayReminders',
	'closeEndedAllocations',
	'buildDailyStatistics',
	'applyRetention',
);
foreach ($countMethods as $method) {
	$body = emergencyhouseCronMethodBody($cronSource, $method);
	emergencyhouseCronContract(is_string($body), 'Missing cron method '.$method.'.');
	if (!is_string($body)) {
		continue;
	}
	emergencyhouseCronContract(
		strpos($body, 'return $this->completeWithCount(') !== false,
		$method.' must convert its business count to the native success code.'
	);
	emergencyhouseCronContract(
		strpos($body, 'return $count;') === false
			&& strpos($body, 'return $processed;') === false
			&& strpos($body, 'return $result;') === false,
		$method.' must not return a business count to Dolibarr.'
	);
}

$providerBody = emergencyhouseCronMethodBody($cronSource, 'checkProviders');
emergencyhouseCronContract(is_string($providerBody), 'Missing cron method checkProviders.');
if (is_string($providerBody)) {
	emergencyhouseCronContract(
		strpos($providerBody, 'EmergencyHouseCronProvidersHealthy') !== false
			&& strpos($providerBody, 'return 0;') !== false
			&& strpos($providerBody, 'return 1;') === false,
		'checkProviders must report a valid configuration as a native success.'
	);
	emergencyhouseCronContract(
		strpos($providerBody, "getDolGlobalString('MAIN_MAIL_EMAIL_FROM'") !== false
			&& strpos($providerBody, "getDolGlobalString('MAIN_INFO_SOCIETE_MAIL'") !== false
			&& strpos($providerBody, 'ErrorSenderEmailMissing') !== false
			&& strpos($providerBody, 'ErrorSenderEmailInvalid') !== false,
		'checkProviders must reject a missing or invalid native sender address.'
	);
}

$completionBody = emergencyhouseCronMethodBody($cronSource, 'completeWithCount');
emergencyhouseCronContract(is_string($completionBody), 'Missing cron completion method.');
if (is_string($completionBody)) {
	emergencyhouseCronContract(
		strpos($completionBody, 'EmergencyHouseCronProcessedCount') !== false
			&& strpos($completionBody, 'return 0;') !== false,
		'Cron completion must write the count to output and return 0.'
	);
}

foreach (array('fr_FR', 'en_US') as $locale) {
	$langPath = $root.DIRECTORY_SEPARATOR.'langs'.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.'emergencyhouse.lang';
	$lang = @file_get_contents($langPath);
	emergencyhouseCronContract(
		is_string($lang)
			&& strpos($lang, 'EmergencyHouseCronProcessedCount=') !== false
			&& strpos($lang, 'EmergencyHouseCronProvidersHealthy=') !== false
			&& strpos($lang, 'ErrorCronProcessingFailed=') !== false,
		'Missing cron translations for '.$locale.'.'
	);
}

if ($failures !== array()) {
	foreach ($failures as $failure) {
		fwrite(STDERR, '[FAIL] '.$failure.PHP_EOL);
	}
	exit(1);
}

print '[OK] Scheduled-job return contracts'.PHP_EOL;
