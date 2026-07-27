<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Standalone repository contract checks.
 *
 * This script intentionally does not bootstrap Dolibarr. It validates source
 * invariants that must hold before integration tests are run on a real
 * Dolibarr instance.
 */

$root = dirname(__DIR__);
$failures = array();
$successes = array();

/**
 * Record one contract result.
 *
 * @param bool   $condition Result
 * @param string $message   Human-readable contract
 * @return void
 */
function emergencyhouseContract($condition, $message)
{
	global $failures, $successes;

	if ($condition) {
		$successes[] = $message;
	} else {
		$failures[] = $message;
	}
}

/**
 * Read a required UTF-8 text file.
 *
 * @param string $path File path
 * @return string
 */
function emergencyhouseReadRequired($path)
{
	global $failures;

	$content = @file_get_contents($path);
	if (!is_string($content)) {
		$failures[] = 'Fichier illisible : '.$path;
		return '';
	}

	return $content;
}

/**
 * Return all PHP files below the module root.
 *
 * @param string $root Module root
 * @return array<int, string>
 */
function emergencyhousePhpFiles($root)
{
	$files = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
			$files[] = $file->getPathname();
		}
	}
	sort($files);

	return $files;
}

/**
 * Parse a Dolibarr translation file and report duplicate keys.
 *
 * @param string $path Translation path
 * @return array<string, string>
 */
function emergencyhouseTranslationMap($path)
{
	global $failures;

	$map = array();
	$lines = @file($path, FILE_IGNORE_NEW_LINES);
	if (!is_array($lines)) {
		$failures[] = 'Fichier de langue illisible : '.$path;
		return $map;
	}
	foreach ($lines as $lineNumber => $line) {
		$line = trim($line);
		if ($line === '' || $line[0] === '#') {
			continue;
		}
		$position = strpos($line, '=');
		if ($position === false) {
			continue;
		}
		$key = trim(substr($line, 0, $position));
		$value = substr($line, $position + 1);
		if (isset($map[$key])) {
			$failures[] = 'Clé de traduction dupliquée '.$key.' dans '.$path.':'.($lineNumber + 1);
			continue;
		}
		$map[$key] = $value;
	}

	return $map;
}

$requiredRootFiles = array(
	'AGENT.md',
	'ChangeLog.md',
	'LICENSE',
	'README.md',
	'modulebuilder.txt',
);
foreach ($requiredRootFiles as $requiredFile) {
	emergencyhouseContract(is_file($root.DIRECTORY_SEPARATOR.$requiredFile), 'Présence de '.$requiredFile);
}
emergencyhouseContract(!is_dir($root.DIRECTORY_SEPARATOR.'emergencyhouse'), 'Absence de racine emergencyhouse imbriquée');

$descriptorPath = $root.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'modEmergencyHouse.class.php';
$descriptor = emergencyhouseReadRequired($descriptorPath);
emergencyhouseContract(
	strpos($descriptor, '$this->numero = 450201;') !== false,
	'Identifiant module 450201'
);
emergencyhouseContract(
	strpos($descriptor, "\$this->family = 'Les Métiers du Bâtiment';") !== false,
	'Famille Les Métiers du Bâtiment'
);
emergencyhouseContract(
	strpos($descriptor, "\$this->version = '1.0.0';") !== false,
	'Version du descripteur 1.0.0'
);
emergencyhouseContract(
	strpos($descriptor, "\$this->config_page_url = array('setup.php@emergencyhouse');") !== false,
	'Point de configuration unique setup.php'
);
emergencyhouseContract(
	strpos($descriptor, '$this->rights[$r][0] = $this->numero * 100 + $r;') !== false,
	'Formule native des identifiants de permissions'
);
preg_match_all('/\$this->addRight\(\+\+\$r,/', $descriptor, $rightMatches);
$rightCount = isset($rightMatches[0]) ? count($rightMatches[0]) : 0;
emergencyhouseContract($rightCount > 0 && $rightCount <= 99, 'Nombre de permissions compris entre 1 et 99');
emergencyhouseContract(
	strpos($descriptor, "'entity' => '0'") !== false
		&& strpos($descriptor, 'multicompanyexternalmodulesharing') !== false,
	'Hooks Multicompany déclarés pour toutes les entités'
);
emergencyhouseContract(
	strpos($descriptor, 'public function remove(') !== false
		&& strpos($descriptor, 'return $this->_remove($sql, $options);') !== false,
	'Désactivation non destructive'
);

$allPhp = '';
foreach (emergencyhousePhpFiles($root) as $phpFile) {
	if (realpath($phpFile) === realpath(__FILE__)) {
		continue;
	}
	$content = emergencyhouseReadRequired($phpFile);
	$allPhp .= "\n".$content;

	emergencyhouseContract(
		!preg_match('/\$_(?:GET|POST|REQUEST)\b/', $content),
		'Pas de superglobale HTTP brute dans '.substr($phpFile, strlen($root) + 1)
	);
	emergencyhouseContract(
		strpos($content, '$dolibarr_nocsrfcheck') === false
			&& strpos($content, 'MAIN_SECURITY_CSRF_WITH_TOKEN') === false,
		'Pas de contournement CSRF dans '.substr($phpFile, strlen($root) + 1)
	);

	$contentWithoutSqlFilename = str_replace('/emergencyhouse/sql/llx_', '/emergencyhouse/sql/prefix_', $content);
	emergencyhouseContract(
		strpos($contentWithoutSqlFilename, 'llx_') === false,
		'Pas de préfixe SQL llx_ codé en dur dans '.substr($phpFile, strlen($root) + 1)
	);
}

emergencyhouseContract(
	!preg_match(
		'/call_trigger\s*\([^;\n]*(?:_VALIDATE|_CANCEL|_REOPEN|_CLOSE|_SIGN|_UNSIGN|_CLASSIFY_BILLED|_GENERATE_DOCUMENT|_SIGNATURE_STATUS|_SERVICE_STATUS)/i',
		$allPhp
	),
	'Triggers custom limités aux mutations CRUD'
);
emergencyhouseContract(
	!preg_match('/round\s*\(\s*\$(?:amount|unitprice|total|price|vat|tva)[^,]*,\s*[0-9]+\s*\)/i', $allPhp),
	'Pas d’arrondi financier codé en dur'
);
emergencyhouseContract(
	strpos($allPhp, '@phpstan-ignore') === false,
	'Pas de suppression locale PHPStan'
);
emergencyhouseContract(
	!preg_match('/input\s+[^>]*type=["\']date["\']/i', $allPhp),
	'Pas de champ date HTML brut'
);

$dataSql = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'data.sql');
preg_match_all(
	"/SELECT\\s+'[^']+'\\s*,\\s*'(EMERGENCYHOUSE_[A-Z0-9_]+)'[^;]+llx_c_action_trigger/si",
	$dataSql,
	$triggerMatches
);
$triggerCodes = isset($triggerMatches[1]) ? array_values(array_unique($triggerMatches[1])) : array();
emergencyhouseContract(count($triggerCodes) === 18, 'Dix-huit événements CRUD déclarés dans c_action_trigger');
foreach ($triggerCodes as $triggerCode) {
	emergencyhouseContract(
		(bool) preg_match('/_(?:CREATE|UPDATE|DELETE)$/', $triggerCode),
		'Événement CRUD valide : '.$triggerCode
	);
}

$schemaSql = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'llx_emergencyhouse.sql');
preg_match_all(
	'/CREATE TABLE IF NOT EXISTS llx_([a-z0-9_]+)\s*\((.*?)\)\s*ENGINE=innodb;/si',
	$schemaSql,
	$tableMatches,
	PREG_SET_ORDER
);
emergencyhouseContract(count($tableMatches) === 43, 'Quarante-trois tables InnoDB déclarées');
foreach ($tableMatches as $tableMatch) {
	$tableName = $tableMatch[1];
	$tableBody = $tableMatch[2];
	if ($tableName !== 'emergencyhouse_schema') {
		emergencyhouseContract(
			(bool) preg_match('/\bentity\s+integer\s+DEFAULT\s+1\s+NOT\s+NULL\b/i', $tableBody),
			'Colonne entity obligatoire dans '.$tableName
		);
	}
}
emergencyhouseContract(stripos($schemaSql, 'ON DELETE CASCADE') === false, 'Pas de cascade SQL métier');
emergencyhouseContract(!preg_match('/\bCREATE\s+TRIGGER\b/i', $schemaSql), 'Pas de trigger SQL');

$frPath = $root.DIRECTORY_SEPARATOR.'langs'.DIRECTORY_SEPARATOR.'fr_FR'.DIRECTORY_SEPARATOR.'emergencyhouse.lang';
$enPath = $root.DIRECTORY_SEPARATOR.'langs'.DIRECTORY_SEPARATOR.'en_US'.DIRECTORY_SEPARATOR.'emergencyhouse.lang';
$frTranslations = emergencyhouseTranslationMap($frPath);
$enTranslations = emergencyhouseTranslationMap($enPath);
$missingInEnglish = array_values(array_diff(array_keys($frTranslations), array_keys($enTranslations)));
$missingInFrench = array_values(array_diff(array_keys($enTranslations), array_keys($frTranslations)));
emergencyhouseContract(empty($missingInEnglish), 'Toutes les clés françaises existent en anglais');
emergencyhouseContract(empty($missingInFrench), 'Toutes les clés anglaises existent en français');
emergencyhouseContract(count($frTranslations) >= 1000, 'Catalogue français complet');
emergencyhouseContract(count($enTranslations) >= 1000, 'Catalogue anglais complet');

$requiredTranslations = array(
	'EmergencyHouseModuleDescription',
	'EmergencyHouseModuleDescriptionLong',
	'EmergencyHouseRightApi',
	'Notify_EMERGENCYHOUSE_CAMPAIGN_CREATE',
	'Notify_EMERGENCYHOUSE_OFFER_UPDATE',
	'Notify_EMERGENCYHOUSE_REQUEST_DELETE',
	'Notify_EMERGENCYHOUSE_SOLICITATION_UPDATE',
	'Notify_EMERGENCYHOUSE_ALLOCATION_CREATE',
	'Notify_EMERGENCYHOUSE_REPORT_DELETE',
);
foreach ($requiredTranslations as $translationKey) {
	emergencyhouseContract(
		isset($frTranslations[$translationKey]) && isset($enTranslations[$translationKey]),
		'Traduction bilingue : '.$translationKey
	);
}

$readme = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'README.md');
$changeLog = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'ChangeLog.md');
$about = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'about.php');
$combinedProjectText = $descriptor."\n".$readme."\n".$changeLog."\n".$about;
emergencyhouseContract(strpos($combinedProjectText, 'emergecyhouse') === false, 'Faute emergecyhouse absente');
emergencyhouseContract(strpos($readme, 'https://github.com/mapiolca/emergencyhouse') !== false, 'URL GitHub canonique');
emergencyhouseContract(strpos($changeLog, '## 1.0.0 — 2026-07-26') !== false, 'ChangeLog aligné sur la version 1.0.0');

foreach ($successes as $success) {
	fwrite(STDOUT, "[OK] ".$success.PHP_EOL);
}
if (!empty($failures)) {
	foreach ($failures as $failure) {
		fwrite(STDERR, "[FAIL] ".$failure.PHP_EOL);
	}
	fwrite(STDERR, count($failures)." contrat(s) en échec.".PHP_EOL);
	exit(1);
}

fwrite(STDOUT, count($successes)." contrats validés.".PHP_EOL);
exit(0);
