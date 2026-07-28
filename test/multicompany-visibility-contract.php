<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone contracts for conditional Multicompany UI visibility.
 */

$multicompanyEnabled = false;
$entityScopes = array();
$conf = (object) array('entity' => 1);
$langs = new class {
	/**
	 * @param string $domain Translation domain
	 * @return void
	 */
	public function load($domain)
	{
	}

	/**
	 * @param string $key Translation key
	 * @return string
	 */
	public function trans($key)
	{
		return $key;
	}
};

/**
 * Test stub for the native Dolibarr module check.
 *
 * @param string $module Module key
 * @return bool
 */
function isModEnabled($module)
{
	global $multicompanyEnabled;

	return $module === 'multicompany' && $multicompanyEnabled;
}

/**
 * Test stub for the native Dolibarr entity scope.
 *
 * @param string $element Object element
 * @return string
 */
function getEntity($element)
{
	global $entityScopes;

	return isset($entityScopes[$element]) ? $entityScopes[$element] : '1';
}

/**
 * Test stub for a module URL.
 *
 * @param string $path Relative path
 * @param int    $type URL type
 * @return string
 */
function dol_buildpath($path, $type)
{
	return $path;
}

require_once dirname(__DIR__).'/lib/emergencyhouse.lib.php';

$failures = array();

/**
 * Record a failed contract.
 *
 * @param bool   $condition Result
 * @param string $message   Contract description
 * @return void
 */
function emergencyhouseMulticompanyContract($condition, $message)
{
	global $failures;

	if (!$condition) {
		$failures[] = $message;
	}
}

$entityScopes = array('campaign' => '1,2');
emergencyhouseMulticompanyContract(
	!emergencyhouseHasConfiguredObjectSharing(),
	'L’interface reste masquée lorsque Multicompany est désactivé.'
);
emergencyhouseMulticompanyContract(
	!in_array('multicompany', array_column(emergencyhouseAdminPrepareHead(), 2), true),
	'L’onglet reste absent lorsque Multicompany est désactivé.'
);

$multicompanyEnabled = true;
$entityScopes = array();
emergencyhouseMulticompanyContract(
	!emergencyhouseHasConfiguredObjectSharing(),
	'L’interface reste masquée sans partage configuré.'
);
emergencyhouseMulticompanyContract(
	!in_array('multicompany', array_column(emergencyhouseAdminPrepareHead(), 2), true),
	'L’onglet reste absent sans partage configuré.'
);

$entityScopes = array('offer' => '1,2');
emergencyhouseMulticompanyContract(
	emergencyhouseHasConfiguredObjectSharing(),
	'L’interface devient visible avec un partage métier configuré.'
);
emergencyhouseMulticompanyContract(
	in_array('multicompany', array_column(emergencyhouseAdminPrepareHead(), 2), true),
	'L’onglet devient visible avec un partage métier configuré.'
);
emergencyhouseMulticompanyContract(
	emergencyhouseEntityScopeIsShared(array(0, 1, 1, 2)),
	'Un périmètre est partagé uniquement avec plusieurs entités distinctes.'
);
emergencyhouseMulticompanyContract(
	!emergencyhouseEntityScopeIsShared(array(1, 1)),
	'Une entité répétée ne constitue pas un partage.'
);

if (!empty($failures)) {
	foreach ($failures as $failure) {
		fwrite(STDERR, '[FAIL] '.$failure.PHP_EOL);
	}
	exit(1);
}

fwrite(STDOUT, "8 contrats de visibilité Multicompany validés.\n");
exit(0);
