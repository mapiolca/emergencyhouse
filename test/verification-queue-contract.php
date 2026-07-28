<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone contracts for the unified verification queue.
 */

/**
 * Dolibarr include stub: queue rotation tests use only pure static methods.
 *
 * @param string $path Module path
 * @return void
 */
function dol_include_once($path)
{
}

require_once dirname(__DIR__).'/class/verificationservice.class.php';
require_once dirname(__DIR__).'/lib/emergencyhouse.lib.php';

$root = dirname(__DIR__);
$failures = array();

/**
 * Register a contract.
 *
 * @param bool   $condition Result
 * @param string $message Contract
 * @return void
 */
function emergencyhouseVerificationContract($condition, $message)
{
	global $failures;

	if (!$condition) {
		$failures[] = $message;
	}
}

/**
 * Read one required source file.
 *
 * @param string $path Path
 * @return string
 */
function emergencyhouseVerificationRead($path)
{
	global $failures;

	$content = @file_get_contents($path);
	if (!is_string($content)) {
		$failures[] = 'Fichier illisible : '.$path;
		return '';
	}

	return $content;
}

$schema = emergencyhouseVerificationRead($root.'/sql/'.'llx'.'_emergencyhouse.sql');
$service = emergencyhouseVerificationRead($root.'/class/verificationservice.class.php');
$listing = emergencyhouseVerificationRead($root.'/class/listingservice.class.php');
$account = emergencyhouseVerificationRead($root.'/class/publicaccount.class.php');
$descriptor = emergencyhouseVerificationRead($root.'/core/modules/modEmergencyHouse.class.php');
$list = emergencyhouseVerificationRead($root.'/verification/list.php');
$card = emergencyhouseVerificationRead($root.'/verification/card.php');
$javascript = emergencyhouseVerificationRead($root.'/js/verification.js');

emergencyhouseVerificationContract(
	strpos($schema, 'CREATE TABLE IF NOT EXISTS '.'llx'.'_emergencyhouse_verification_queue') !== false
		&& strpos($schema, 'uk_emergencyhouse_verification_queue_object (entity, object_type, fk_object)') !== false
		&& strpos($schema, 'CREATE TABLE IF NOT EXISTS '.'llx'.'_emergencyhouse_verification_rotation') !== false
		&& strpos($schema, 'verification_status integer DEFAULT 0 NOT NULL') !== false,
	'Schéma normalisé de file, rotation et état des comptes publics.'
);
emergencyhouseVerificationContract(
	strpos($descriptor, 'ensureVerificationQueueSchema((int) $conf->entity)') !== false
		&& strpos($descriptor, 'manual_verification_level > 0') !== false
		&& strpos($descriptor, "WHERE object_type = 'account' AND status IN (1, 2)") !== false
		&& strpos($descriptor, 'foreach ($backfills as $backfill)') !== false
		&& strpos($descriptor, "\$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.\$queueTable.' AS queue';") !== false
		&& strpos($descriptor, "'EMERGENCYHOUSE_VERIFICATION_WARNING_MINUTES' => array('10', 'chaine')") !== false
		&& strpos($descriptor, "'EMERGENCYHOUSE_VERIFICATION_CRITICAL_MINUTES' => array('30', 'chaine')") !== false,
	'Migration conservatrice et seuils par défaut sans écrasement.'
);
emergencyhouseVerificationContract(
	strpos($service, 'FOR UPDATE') !== false
		&& strpos($service, 'lockSubmissionTarget(') !== false
		&& strpos($service, "INSERT IGNORE INTO '.MAIN_DB_PREFIX.'emergencyhouse_verification_rotation") !== false
		&& strpos($service, 'ON DUPLICATE KEY UPDATE queue_status') !== false
		&& strpos($service, 'user_rights AS direct_right') !== false
		&& strpos($service, 'usergroup_rights AS group_right') !== false
		&& strpos($service, 'user.statut = 1') !== false
		&& strpos($service, 'user.fk_soc IS NULL') !== false,
	'Verrouillage concurrent et éligibilité explicite par utilisateur ou groupe.'
);
emergencyhouseVerificationContract(
	EmergencyHouseVerificationService::selectNextUserId(array(30, 10, 20), 0) === 10
		&& EmergencyHouseVerificationService::selectNextUserId(array(30, 10, 20), 10) === 20
		&& EmergencyHouseVerificationService::selectNextUserId(array(30, 10, 20), 20) === 30
		&& EmergencyHouseVerificationService::selectNextUserId(array(30, 10, 20), 30) === 10
		&& EmergencyHouseVerificationService::selectNextUserId(array(10, 30), 20) === 30
		&& EmergencyHouseVerificationService::selectNextUserId(array(), 20) === 0,
	'Rotation stricte, circulaire et stable.'
);
emergencyhouseVerificationContract(
	substr_count($listing, 'enqueueTarget(') >= 4
		&& substr_count($listing, 'cancelTarget(') >= 3
		&& substr_count($listing, 'lockSubmissionTarget(') >= 3
		&& strpos($listing, '$request->verification_status = 0;') !== false
		&& strpos($account, "enqueueTarget(\n\t\t\t(int) \$this->entity,\n\t\t\t'account'") !== false,
	'Soumissions et retours en brouillon raccordés atomiquement à la file.'
);
emergencyhouseVerificationContract(
	strpos($service, 'recordQueueDecision(') !== false
		&& strpos($service, 'STATUS_VERIFIED, self::STATUS_REFUSED') !== false
		&& strpos($service, 'verification_status < 1') !== false
		&& strpos($service, 'affected_rows($resql) !== 1') !== false,
	'Décision finale verrouillée et refus des objets déjà traités.'
);
emergencyhouseVerificationContract(
	strpos($list, "GETPOST('scope', 'aZ09') === 'all'") !== false
		&& strpos($list, 'reconcileAssignments($entities)') !== false
		&& strpos($list, 'q.date_queued') !== false
		&& strpos($list, 'Form::multiSelectArrayWithCheckbox') !== false
		&& strpos($list, 'NoRecordFound') !== false
		&& strpos($card, "GETPOSTINT('queue_id')") !== false
		&& strpos($card, 'name="object_type"') === false
		&& strpos($card, 'name="fk_object"') === false,
	'Liste FIFO native et formulaire imposant la cible par identifiant de file.'
);
emergencyhouseVerificationContract(
	emergencyhouseVerificationUrgency(599, array('warning' => 10, 'critical' => 30)) === 'neutral'
		&& emergencyhouseVerificationUrgency(600, array('warning' => 10, 'critical' => 30)) === 'warning'
		&& emergencyhouseVerificationUrgency(1799, array('warning' => 10, 'critical' => 30)) === 'warning'
		&& emergencyhouseVerificationUrgency(1800, array('warning' => 10, 'critical' => 30)) === 'critical'
		&& emergencyhouseVerificationFormatDuration(90061) === '25:01:01'
		&& strpos($javascript, 'window.setInterval(refresh, 1000)') !== false,
	'Bornes d’urgence exactes, compteur supérieur à 24 heures et rafraîchissement chaque seconde.'
);

if (!empty($failures)) {
	foreach ($failures as $failure) {
		fwrite(STDERR, '[FAIL] '.$failure.PHP_EOL);
	}
	exit(1);
}

fwrite(STDOUT, "8 contrats de file de vérification validés.\n");
exit(0);
