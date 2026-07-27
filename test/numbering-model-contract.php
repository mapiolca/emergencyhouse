<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone contract for object-specific numbering models.
 *
 * The test does not bootstrap Dolibarr or access a database. It validates that
 * each business object loads a distinct model with its own name, description,
 * example and activation scope.
 */

$root = dirname(__DIR__);
$failures = array();
$names = array();
$descriptions = array();
$examples = array();

if (!defined('DOL_VERSION')) {
	define('DOL_VERSION', '20.0.0');
}

/**
 * Minimal include bridge used by numbering model files.
 *
 * @param string $path Dolibarr module path
 * @return void
 */
function dol_include_once($path)
{
	global $root;

	$prefix = '/emergencyhouse/';
	if (strpos($path, $prefix) !== 0) {
		throw new RuntimeException('Unexpected include path: '.$path);
	}
	require_once $root.DIRECTORY_SEPARATOR.substr($path, strlen($prefix));
}

/**
 * Minimal translation stub.
 */
class EmergencyHouseNumberingTranslateStub
{
	/**
	 * Return the requested key.
	 *
	 * @param string $key Translation key
	 * @return string
	 */
	public function trans($key)
	{
		return $key;
	}
}

$definitions = array(
	'campaign' => array('prefix' => 'EHC', 'description' => 'EmergencyHouseCampaignNumberingModelDescription'),
	'offer' => array('prefix' => 'EHO', 'description' => 'EmergencyHouseOfferNumberingModelDescription'),
	'request' => array('prefix' => 'EHR', 'description' => 'EmergencyHouseRequestNumberingModelDescription'),
	'solicitation' => array('prefix' => 'EHS', 'description' => 'EmergencyHouseSolicitationNumberingModelDescription'),
	'allocation' => array('prefix' => 'EHA', 'description' => 'EmergencyHouseAllocationNumberingModelDescription'),
	'report' => array('prefix' => 'EHI', 'description' => 'EmergencyHouseReportNumberingModelDescription'),
);
$langs = new EmergencyHouseNumberingTranslateStub();

foreach ($definitions as $objectType => $definition) {
	$modelName = 'emergencyhouse_'.$objectType.'_standard';
	$modelFile = $root.'/core/modules/emergencyhouse/mod_'.$modelName.'.php';
	require_once $modelFile;
	$className = 'mod_'.$modelName;
	if (!class_exists($className)) {
		$failures[] = 'Missing class '.$className;
		continue;
	}

	/** @var mod_emergencyhouse_standard $model */
	$model = new $className();
	$name = (string) $model->name;
	$description = $model->info($langs);
	$example = $model->getExample();
	$names[] = $name;
	$descriptions[] = $description;
	$examples[] = $example;

	if ($name !== $modelName) {
		$failures[] = $objectType.': unexpected model name '.$name;
	}
	if ($description !== $definition['description']) {
		$failures[] = $objectType.': unexpected description key '.$description;
	}
	if (strpos($example, $definition['prefix'].'-') !== 0) {
		$failures[] = $objectType.': unexpected example '.$example;
	}
	if (!$model->canBeActivated((object) array('element' => $objectType))) {
		$failures[] = $objectType.': model cannot be activated for its object';
	}
	if ($model->canBeActivated((object) array('element' => 'unexpected'))) {
		$failures[] = $objectType.': model accepts an unrelated object';
	}
}

if (count(array_unique($names)) !== count($definitions)) {
	$failures[] = 'Model names are not unique';
}
if (count(array_unique($descriptions)) !== count($definitions)) {
	$failures[] = 'Model descriptions are not unique';
}
if (count(array_unique($examples)) !== count($definitions)) {
	$failures[] = 'Model examples are not unique';
}

if ($failures) {
	fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
	exit(1);
}

print 'Numbering model contract: '.count($definitions).' distinct object models validated.'.PHP_EOL;
