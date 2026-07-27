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
	strpos($descriptor, "\$this->picto = 'fontawesome_house-user';") !== false,
	'Pictogramme natif house-user déclaré pour le module'
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
	substr_count($descriptor, "'perms' => '\$user->hasRight(") === 2
		&& strpos($descriptor, "'perms' => 'emergencyhouseCanDo(") === false
		&& strpos(
			$descriptor,
			"\$this->addLeftMenu(\$r, 'EmergencyHouseDashboard', '/emergencyhouse/index.php', 'campaign', 'read', 10);"
		) !== false,
	'Permissions déclaratives des menus basées sur User::hasRight()'
);
emergencyhouseContract(
	strpos($descriptor, 'public function remove(') !== false
		&& strpos($descriptor, 'return $this->_remove($sql, $options);') !== false,
	'Désactivation non destructive'
);

$setupPath = $root.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'setup.php';
$setup = emergencyhouseReadRequired($setupPath);
emergencyhouseContract(
	strpos($setup, "require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';") !== false
		&& substr_count($setup, 'base64_encode(getRandomPassword(true, null, 48))') === 2
		&& substr_count($descriptor, 'base64_encode(getRandomPassword(true, null, 48))') === 2,
	'Initialisation et récupération natives de deux clés encodées en base64'
);
emergencyhouseContract(
	strpos($setup, 'GeneratedEnvironmentKeys') === false
		&& strpos($setup, 'GeneratedValue') === false
		&& strpos($setup, '<pre class="small">export ') === false
		&& strpos($setup, 'autocomplete="off" spellcheck="false"') === false,
	'Aucune valeur de clé affichée ou demandée dans les réglages'
);
emergencyhouseContract(
	strpos($setup, 'SecurityConfigurationGuide') !== false
		&& strpos($setup, 'getConfigurationStatus()') !== false
		&& strpos($setup, 'ProviderConfigurationGuide') !== false,
	'Guides intégrés pour la sécurité et les fournisseurs'
);
emergencyhouseContract(
	strpos($setup, "'EMERGENCYHOUSE_SMS_PROVIDER' => array('type' => 'string', 'default' => 'disabled')") !== false
		&& strpos($setup, "\$options = array('disabled' => \$langs->trans('Disabled'));") !== false,
	'SMS non activable sans connecteur implémenté'
);

$preview = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'public-preview.php');
emergencyhouseContract(
	strpos($preview, 'NOLOGIN') === false
		&& strpos($preview, "emergencyhouseCanDo(\$user, 'configuration', 'write')") !== false
		&& strpos($preview, 'PublicPreviewSampleDataHelp') !== false,
	'Aperçu public privé, authentifié et alimenté uniquement par des exemples'
);

$publicLibrary = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'emergencyhouse_public.lib.php');
$registerController = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'register.php'
);
$notificationService = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'notificationservice.class.php');
emergencyhouseContract(
	strpos($publicLibrary, "getDolGlobalString('EMERGENCYHOUSE_PUBLIC_BASE_URL', '')") !== false
		&& strpos($publicLibrary, "\$relativePath === 'index.php' ? '' : \$relativePath") !== false
		&& strpos($publicLibrary, "dol_buildpath('/emergencyhouse/public/'.ltrim") === false,
	'L’URL configurée est la racine directe du répertoire public'
);
emergencyhouseContract(
	strpos($registerController, "\$dataPolicyEnabled = isModEnabled('datapolicy');") !== false
		&& strpos($registerController, "\$privacyAccepted = !\$dataPolicyEnabled || GETPOSTINT('privacy_accepted') > 0;") !== false
		&& strpos($registerController, 'if ($dataPolicyEnabled) {') !== false
		&& strpos($registerController, '$consentResult < $requiredConsentCount') !== false
		&& strpos($publicLibrary, "\$dataPolicyEnabled = isModEnabled('datapolicy');") !== false
		&& strpos($publicLibrary, '$dataPolicyEnabled && $privacyUrl !==') !== false,
	'Politique de confidentialité masquée et non exigée lorsque Data Policy est désactivé'
);
emergencyhouseContract(
	substr_count($notificationService, 'emergencyhousePublicAbsoluteUrl(') === 2
		&& strpos($notificationService, "dol_buildpath('/emergencyhouse/public/") === false,
	'Les notifications réutilisent le constructeur canonique d’URL publique'
);
foreach (array('public.css.php', 'public.js.php', 'emergencyhouse.svg.php') as $publicAsset) {
	emergencyhouseContract(
		is_file($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$publicAsset),
		'Ressource autonome du portail : '.$publicAsset
	);
}
emergencyhouseContract(
	strpos($setup, 'ErrorPublicBaseUrlInvalid') !== false
		&& strpos($setup, "rtrim(\$publicBaseUrl, '/').'/'") !== false
		&& strpos($setup, "'EMERGENCYHOUSE_PUBLIC_BASE_URL' => 'HelpPublicBaseUrl'") !== false,
	'Validation et aide de l’URL racine publique'
);
$publicControllers = '';
foreach (emergencyhousePhpFiles($root.DIRECTORY_SEPARATOR.'public') as $publicPhpFile) {
	$publicControllers .= "\n".emergencyhouseReadRequired($publicPhpFile);
}
emergencyhouseContract(
	strpos($publicControllers, "\$_SERVER['PHP_SELF']") === false
		&& substr_count($publicControllers, 'emergencyhousePublicUrl(') > 40,
	'Les actions et liens du portail utilisent le constructeur d’URL publique'
);

$numberingObjects = array('campaign', 'offer', 'request', 'solicitation', 'allocation', 'report');
foreach ($numberingObjects as $numberingObject) {
	$modelName = 'emergencyhouse_'.$numberingObject.'_standard';
	$modelPath = $root.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'emergencyhouse';
	$modelPath .= DIRECTORY_SEPARATOR.'mod_'.$modelName.'.php';
	$model = emergencyhouseReadRequired($modelPath);
	emergencyhouseContract(
		strpos($model, "public \$name = '".$modelName."';") !== false
			&& strpos($descriptor, "'EMERGENCYHOUSE_".strtoupper($numberingObject)."_ADDON' => array('".$modelName."'") !== false,
		'Modèle de numérotation dédié pour '.$numberingObject
	);
}
emergencyhouseContract(
	strpos($setup, "\$configuredModel === 'emergencyhouse_standard'") !== false
		&& strpos($descriptor, "AND value = 'emergencyhouse_standard'") !== false,
	'Reprise immédiate et migration persistante du modèle de numérotation historique'
);
$commonObject = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'emergencyhousecommonobject.class.php'
);
emergencyhouseContract(
	strpos($commonObject, "if (\$modelName === 'emergencyhouse_standard')") !== false
		&& strpos($commonObject, '$modelName = $defaultModel;') !== false,
	'Routage des anciennes constantes vers le modèle propre à chaque objet'
);

$encryptionService = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'encryptionservice.class.php');
emergencyhouseContract(
	strpos($encryptionService, 'strlen($material) < 32') !== false
		&& strpos($encryptionService, 'strlen($lookupMaterial) < 32') !== false
		&& strpos($encryptionService, 'ErrorEncryptionAndHmacKeysMustDiffer') !== false,
	'Clés de sécurité distinctes et longues d’au moins 32 octets'
);
emergencyhouseContract(
	strpos($encryptionService, "public const ENCRYPTION_KEY_NAME = 'EMERGENCYHOUSE_ENCRYPTION_KEY';") !== false
		&& strpos($encryptionService, "public const HMAC_KEY_NAME = 'EMERGENCYHOUSE_HMAC_KEY';") !== false
		&& strpos($encryptionService, 'getDolGlobalString(self::ENCRYPTION_KEY_NAME)') !== false
		&& strpos($encryptionService, 'getDolGlobalString(self::HMAC_KEY_NAME)') !== false
		&& strpos($encryptionService, 'getenv(self::ENCRYPTION_KEY_NAME)') !== false
		&& strpos($encryptionService, 'getenv(self::HMAC_KEY_NAME)') !== false
		&& strpos($descriptor, 'private function ensureSecurityKeys()') !== false
		&& strpos($descriptor, "dolibarr_set_const(\$this->db, \$encryptionName, \$encryptionValue, 'chaine', 0, '', 0)") !== false
		&& strpos($descriptor, "dolibarr_set_const(\$this->db, \$hmacName, \$hmacValue, 'chaine', 0, '', 0)") !== false
		&& strpos($setup, "name=\"action\" value=\"generate_security_keys\"") !== false
		&& strpos($setup, 'EMERGENCYHOUSE_ENCRYPTION_KEY_ENV') === false
		&& strpos($setup, 'EMERGENCYHOUSE_HMAC_KEY_ENV') === false
		&& strpos($descriptor, 'EMERGENCYHOUSE_ENCRYPTION_KEY_ENV') === false
		&& strpos($descriptor, 'EMERGENCYHOUSE_HMAC_KEY_ENV') === false,
	'Clés fixes générées et enregistrées comme constantes sensibles globales Dolibarr'
);

$geocodingService = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'geocodingservice.class.php');
$listingService = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'listingservice.class.php');
emergencyhouseContract(
	strpos($geocodingService, "strtolower(\$parts['host']) !== 'data.geopf.fr'") !== false
		&& strpos($geocodingService, 'CURLOPT_FOLLOWLOCATION => false') !== false
		&& strpos($geocodingService, '$response = getURLContent(') === false
		&& substr_count($listingService, '$this->geocodeOffer(') >= 2
		&& substr_count($listingService, '$this->geocodeRequest(') >= 2,
	'Géocodage Géoplateforme épinglé sans journalisation native de l’adresse exacte'
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
	strpos($allPhp, 'EMERGENCYHOUSE_MASTER_KEY') === false,
	'Nom par défaut de la clé de chiffrement cohérent dans tout le module'
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
emergencyhouseContract(
	strpos($schemaSql, 'UNIQUE KEY uk_emergencyhouse_sequence (entity, object_type, period_code)') !== false,
	'Compteurs de numérotation isolés par entité, objet et période'
);
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
