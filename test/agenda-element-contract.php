<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone contracts for native Agenda labels and object resolution.
 *
 * This script does not bootstrap Dolibarr. It exercises the hook mapping and
 * verifies the source-level trigger context required by the native Agenda.
 */

$root = dirname(__DIR__);
$descriptorPath = $root.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'modEmergencyHouse.class.php';
$hooksPath = $root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'actions_emergencyhouse.class.php';
$commonObjectPath = $root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'emergencyhousecommonobject.class.php';

$descriptorSource = @file_get_contents($descriptorPath);
$commonObjectSource = @file_get_contents($commonObjectPath);
if (!is_string($descriptorSource) || !is_string($commonObjectSource)) {
	fwrite(STDERR, 'Unable to read the Agenda contract sources.'.PHP_EOL);
	exit(1);
}

require_once $hooksPath;

$failures = array();

if (!class_exists('HookManager')) {
	/**
	 * Minimal standalone substitute for the hook signature.
	 */
	class HookManager
	{
	}
}

/**
 * Record a failed contract.
 *
 * @param bool   $condition Contract result
 * @param string $message Failure message
 * @return void
 */
function emergencyhouseAgendaContract($condition, $message)
{
	global $failures;

	if (!$condition) {
		$failures[] = $message;
	}
}

emergencyhouseAgendaContract(
	strpos($descriptorSource, "'elementproperties'") !== false
		&& strpos($descriptorSource, "'entity' => '0'") !== false,
	'The descriptor must expose the elementproperties hook in every entity.'
);

$expected = array(
	'campaign@emergencyhouse' => array('campaign', 'EmergencyHouseCampaign'),
	'offer@emergencyhouse' => array('offer', 'EmergencyHouseOffer'),
	'request@emergencyhouse' => array('request', 'EmergencyHouseRequest'),
	'solicitation@emergencyhouse' => array('solicitation', 'EmergencyHouseSolicitation'),
	'allocation@emergencyhouse' => array('allocation', 'EmergencyHouseAllocation'),
	'report@emergencyhouse' => array('report', 'EmergencyHouseReport'),
);

$hooksReflection = new ReflectionClass(ActionsEmergencyHouse::class);
/** @var ActionsEmergencyHouse $hooks */
$hooks = $hooksReflection->newInstanceWithoutConstructor();
$object = new stdClass();
$action = '';
$hookmanager = new HookManager();
foreach ($expected as $elementType => $definition) {
	$parameters = array('elementType' => $elementType);
	$result = $hooks->getElementProperties($parameters, $object, $action, $hookmanager);
	$properties = $hooks->results;
	$classPath = $root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.$definition[0].'.class.php';
	$classSource = @file_get_contents($classPath);

	emergencyhouseAgendaContract($result === 0, 'Unexpected hook result for '.$elementType.'.');
	emergencyhouseAgendaContract(
		isset($properties['module'], $properties['element'], $properties['table_element'], $properties['subelement'], $properties['classpath'], $properties['classfile'], $properties['classname'])
			&& $properties['module'] === 'emergencyhouse'
			&& $properties['element'] === $definition[0]
			&& $properties['table_element'] === 'emergencyhouse_'.$definition[0]
			&& $properties['subelement'] === $definition[0]
			&& $properties['classpath'] === 'emergencyhouse/class'
			&& $properties['classfile'] === $definition[0]
			&& $properties['classname'] === $definition[1],
		'Invalid native element properties for '.$elementType.'.'
	);
	emergencyhouseAgendaContract(
		is_string($classSource) && strpos($classSource, 'class '.$definition[1].' extends EmergencyHouseCommonObject') !== false,
		'The resolved class declaration is missing for '.$elementType.'.'
	);
}

foreach (array('campaign', 'offer', 'request', 'solicitation', 'allocation', 'report') as $unqualifiedType) {
	$parameters = array('elementType' => $unqualifiedType);
	$hooks->getElementProperties($parameters, $object, $action, $hookmanager);
	emergencyhouseAgendaContract(
		$hooks->results === array(),
		'Unqualified element type '.$unqualifiedType.' must not be claimed by Emergency House.'
	);
}

foreach (array('CREATE', 'UPDATE', 'DELETE') as $crudAction) {
	emergencyhouseAgendaContract(
		strpos($commonObjectSource, "'".$crudAction."' => 'EmergencyHouseAgendaObject") !== false,
		'Missing Agenda translation mapping for '.$crudAction.'.'
	);
}
emergencyhouseAgendaContract(
	substr_count($commonObjectSource, '$this->prepareAgendaTriggerMessages(') === 4,
	'Every common CRUD trigger path must prepare its Agenda labels.'
);
emergencyhouseAgendaContract(
	strpos($commonObjectSource, "empty(\$this->context['actionmsg'])") !== false
		&& strpos($commonObjectSource, "empty(\$this->context['actionmsg2'])") !== false,
	'Agenda defaults must preserve caller-supplied actionmsg and actionmsg2 values.'
);

foreach (array('fr_FR', 'en_US') as $locale) {
	$langPath = $root.DIRECTORY_SEPARATOR.'langs'.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.'emergencyhouse.lang';
	$lang = @file_get_contents($langPath);
	emergencyhouseAgendaContract(
		is_string($lang)
			&& strpos($lang, 'EmergencyHouseAgendaObjectCreated=') !== false
			&& strpos($lang, 'EmergencyHouseAgendaObjectUpdated=') !== false
			&& strpos($lang, 'EmergencyHouseAgendaObjectDeleted=') !== false,
		'Missing native Agenda labels for '.$locale.'.'
	);
}

if ($failures !== array()) {
	foreach ($failures as $failure) {
		fwrite(STDERR, '[FAIL] '.$failure.PHP_EOL);
	}
	exit(1);
}

print '[OK] Agenda element and label contracts'.PHP_EOL;
