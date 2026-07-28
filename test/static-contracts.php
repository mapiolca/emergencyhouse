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
	strpos($descriptor, "\$this->depends = array('modAdherent');") !== false
		&& strpos($descriptor, 'ensureMemberLinkSchema()') !== false
		&& strpos($descriptor, "'EMERGENCYHOUSE_ADHERENT_TYPE_ID' => array('0', 'chaine')") !== false,
	'Module Adhérents obligatoire, migration de liaison et type par entité'
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
$cronBlockStart = strpos($descriptor, '$this->cronjobs = array(');
$cronBlockEnd = $cronBlockStart === false ? false : strpos($descriptor, "\n\t\t);\n\n\t\t\$r = 0;", $cronBlockStart);
$cronBlock = $cronBlockStart !== false && $cronBlockEnd !== false
	? substr($descriptor, $cronBlockStart, $cronBlockEnd - $cronBlockStart)
	: '';
emergencyhouseContract(
	substr_count($cronBlock, "'status' => 1,") === 9
		&& substr_count($cronBlock, "'status' => 0,") === 0,
	'Neuf travaux planifiés actifs dès leur création'
);
emergencyhouseContract(
	strpos($descriptor, '$sql[] = $this->buildCronStatusUpdateSql(1, (int) $conf->entity);') !== false
		&& strpos($descriptor, '$this->buildCronStatusUpdateSql(0, (int) $conf->entity)') !== false
		&& strpos($descriptor, "UPDATE '.MAIN_DB_PREFIX.'cronjob") !== false
		&& strpos($descriptor, "WHERE module_name = 'emergencyhouse'") !== false
		&& strpos($descriptor, "classesname = '/emergencyhouse/class/emergencyhousecron.class.php'") !== false
		&& strpos($descriptor, "objectname = 'EmergencyHouseCron'") !== false,
	'Activation et désactivation synchronisées des travaux planifiés natifs'
);

$notificationActions = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'actions_emergencyhouse.class.php'
);
$installationData = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'data.sql'
);
preg_match_all(
	"/SELECT 'emergencyhouse', '(EMERGENCYHOUSE_(?:CAMPAIGN|OFFER|REQUEST|SOLICITATION|ALLOCATION|REPORT)_(?:CREATE|UPDATE|DELETE))'/",
	$installationData,
	$notificationSqlMatches
);
$notificationSqlCodes = isset($notificationSqlMatches[1])
	? array_values(array_unique($notificationSqlMatches[1]))
	: array();
emergencyhouseContract(
	count($notificationSqlCodes) === 18
		&& strpos($installationData, "@emergencyhouse', 'EMERGENCYHOUSE_") === false
		&& strpos($descriptor, '$this->buildNotificationTriggerModuleUpdateSql(),') !== false
		&& strpos($descriptor, "SET elementtype = 'emergencyhouse'") !== false
		&& strpos($descriptor, "LEFT(code, 15) = 'EMERGENCYHOUSE_'") !== false,
	'Dix-huit triggers natifs rattachés et migrés vers la clé de module Notifications'
);
foreach ($notificationSqlCodes as $notificationSqlCode) {
	emergencyhouseContract(
		strpos($notificationActions, "'".$notificationSqlCode."'") !== false,
		'Événement exposé par notifsupported : '.$notificationSqlCode
	);
}

$notificationService = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'notificationservice.class.php'
);
$listingService = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'listingservice.class.php'
);
$updateOwnedOfferStart = strpos($listingService, 'public function updateOwnedOffer(');
$updateOwnedOfferEnd = $updateOwnedOfferStart === false
	? false
	: strpos($listingService, "\n\tpublic function deleteOwnedOfferPhoto(", $updateOwnedOfferStart);
$updateOwnedOffer = $updateOwnedOfferStart !== false && $updateOwnedOfferEnd !== false
	? substr($listingService, $updateOwnedOfferStart, $updateOwnedOfferEnd - $updateOwnedOfferStart)
	: '';
$campaignValidationPosition = strpos(
	$updateOwnedOffer,
	'$campaign = $this->fetchPublishedCampaign((int) $account->entity, (int) ($data[\'fk_campaign\'] ?? 0));'
);
$campaignAssignmentPosition = strpos($updateOwnedOffer, '$offer->fk_campaign = (int) $campaign->id;');
$offerTransactionPosition = strpos($updateOwnedOffer, '$this->db->begin();');
emergencyhouseContract(
	$campaignValidationPosition !== false
		&& strpos($updateOwnedOffer, 'if (!$campaign instanceof EmergencyHouseCampaign)') !== false
		&& $campaignAssignmentPosition !== false
		&& $offerTransactionPosition !== false
		&& $campaignValidationPosition < $campaignAssignmentPosition
		&& $campaignAssignmentPosition < $offerTransactionPosition,
	'Changement de campagne validé et affecté avant la transaction de mise à jour publique'
);
$fetchPublishedCampaignStart = strpos($listingService, 'private function fetchPublishedCampaign(');
$fetchPublishedCampaignEnd = $fetchPublishedCampaignStart === false
	? false
	: strpos($listingService, "\n\tprivate function replaceOfferFeatures(", $fetchPublishedCampaignStart);
$fetchPublishedCampaign = $fetchPublishedCampaignStart !== false && $fetchPublishedCampaignEnd !== false
	? substr($listingService, $fetchPublishedCampaignStart, $fetchPublishedCampaignEnd - $fetchPublishedCampaignStart)
	: '';
emergencyhouseContract(
	strpos($fetchPublishedCampaign, '(int) $campaign->entity !== $entity') !== false
		&& strpos($fetchPublishedCampaign, 'EmergencyHouseCampaign::STATUS_PUBLISHED') !== false
		&& strpos($fetchPublishedCampaign, '(int) $campaign->date_end < dol_now()') !== false,
	'Campagne cible limitée à l’entité du compte et à une publication encore ouverte'
);
emergencyhouseContract(
	strpos($notificationService, "require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';") !== false
		&& strpos($notificationService, 'new CMailFile(') !== false
		&& strpos($notificationService, "getDolGlobalString('MAIN_MAIL_EMAIL_FROM'") !== false
		&& strpos($notificationService, "MAIN_MAIL_AUTOCOPY_TO permanent BCC") !== false
		&& strpos($notificationService, "'standard'") !== false,
	'Transport de courriel natif Dolibarr avec copie cachée permanente'
);
$publicAuthenticationMailControllers = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'register.php'
).emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'forgot.php'
).emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'resend.php'
).emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'login.php'
);
preg_match_all(
	'/sendForAccount\\(/',
	$publicAuthenticationMailControllers,
	$directMailMatches
);
emergencyhouseContract(
	isset($directMailMatches[0]) && count($directMailMatches[0]) === 4
		&& strpos($publicAuthenticationMailControllers, 'queueForAccount(') === false
		&& strpos($publicAuthenticationMailControllers, 'queueEmail(') === false,
	'Quatre courriels d’accès au compte envoyés sans file planifiée'
);
emergencyhouseContract(
	strpos($notificationService, 'public function sendForAccount(') !== false
		&& strpos($notificationService, 'private const SYNCHRONOUS_ACCESS_EMAILS') !== false
		&& strpos($notificationService, "'account_verification'") !== false
		&& strpos($notificationService, "'password_reset'") !== false
		&& strpos($notificationService, "'magic_login'") !== false
		&& strpos($notificationService, 'ErrorSynchronousAccessEmailRequired') !== false
		&& strpos($notificationService, 'private function discardLegacyQueuedAccessEmails(') !== false
		&& strpos($notificationService, 'event_code NOT IN (') !== false
		&& strpos($notificationService, 'template_code NOT IN (') !== false
		&& strpos($notificationService, 'private function sendPendingByIdempotencyKey(') === false
		&& strpos($notificationService, 'if ($sendImmediately)') === false
		&& strpos($notificationService, '$this->markFailure($record, $this->error') !== false
		&& strpos($notificationService, 'next_attempt = ') !== false
		&& strpos($descriptor, "'method' => 'processNotificationQueue'") !== false,
	'File planifiée réservée aux notifications métier différées'
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
$memberService = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'memberservice.class.php'
);
emergencyhouseContract(
	strpos($memberService, "require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';") !== false
		&& strpos($memberService, "isModEnabled('member')") !== false
		&& strpos($memberService, 'new Adherent($this->db)') !== false
		&& strpos($memberService, '$member->create($user)') !== false
		&& strpos($memberService, '$member->validate($user)') !== false
		&& strpos($memberService, 'call_trigger(') === false,
	'Création et validation des adhérents confiées exclusivement à l’objet natif'
);
emergencyhouseContract(
	strpos($memberService, "LOWER(TRIM(email))") !== false
		&& strpos($memberService, "AND entity = '.((int) \$entity)") !== false
		&& strpos($memberService, "'ErrorMemberMultipleMatches'") !== false
		&& strpos($memberService, 'Adherent::STATUS_RESILIATED') === false
		&& strpos($memberService, 'Adherent::STATUS_EXCLUDED') === false,
	'Rapprochement strict par e-mail et entité avec refus des statuts non admis'
);
emergencyhouseContract(
	strpos($memberService, "\$member->login = 'eh_'.\$account->public_uuid;") !== false
		&& strpos($memberService, '$member->pass =') === false
		&& strpos($memberService, 'new User(') === false
		&& strpos($memberService, 'new Societe(') === false
		&& strpos($memberService, 'new Subscription(') === false,
	'Identifiant membre opaque sans mot de passe, utilisateur, tiers ni cotisation'
);
emergencyhouseContract(
	strpos($setup, "'EMERGENCYHOUSE_ADHERENT_TYPE_ID' => array('type' => 'int', 'default' => '0')") !== false
		&& strpos($setup, "getAvailableMemberTypes((int) \$conf->entity)") !== false
		&& strpos($setup, "ajax_combobox(\$name)") !== false
		&& strpos($setup, "name=\"action\" value=\"reconcile_members\"") !== false
		&& strpos($setup, "name=\"token\" value=\"'.newToken().'\"") !== false
		&& strpos($setup, "'EMERGENCYHOUSE_ADHERENT_MODE' =>") === false,
	'Type d’adhérent en Select2 et reprise CSRF sans réutiliser l’ancien mode'
);

$preview = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'public-preview.php');
emergencyhouseContract(
	strpos($preview, 'NOLOGIN') === false
		&& strpos($preview, "emergencyhouseCanDo(\$user, 'configuration', 'write')") !== false
		&& strpos($preview, 'PublicPreviewSampleDataHelp') !== false,
	'Aperçu public privé, authentifié et alimenté uniquement par des exemples'
);

$publicLibrary = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'emergencyhouse_public.lib.php');
$publicStyles = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'public.css.php');
$publicOfferEditor = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'offer'.DIRECTORY_SEPARATOR.'edit.php'
);
$publicRequestEditor = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'request'.DIRECTORY_SEPARATOR.'edit.php'
);
$publicRequestIndex = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'request'.DIRECTORY_SEPARATOR.'index.php'
);
$registerController = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'register.php'
);
emergencyhouseContract(
	strpos($publicRequestIndex, "\$campaign->public_visibility_mode === 'offers_requests'") !== false
		&& strpos($publicRequestIndex, "array('requests', 'both')") === false,
	'Les demandes publiques sont exposées pour le mode de campagne Offres et demandes'
);
emergencyhouseContract(
	strpos($registerController, '$db->begin();') !== false
		&& strpos($registerController, '$memberService->provisionForAccount($account, $triggerUser)') !== false
		&& strpos($registerController, '$db->commit();') !== false
		&& strpos($registerController, '$notification->sendForAccount(') > strpos($registerController, '$db->commit();')
		&& strpos($registerController, 'PublicMemberRegistrationUnavailable') !== false,
	'Inscription atomique avec adhérent validé avant l’envoi du courriel'
);
$termsController = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'terms.php'
);
$notificationService = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'notificationservice.class.php');
emergencyhouseContract(
	strpos($publicLibrary, "getDolGlobalString('EMERGENCYHOUSE_PUBLIC_BASE_URL', '')") !== false
		&& strpos($publicLibrary, "\$relativePath === 'index.php' ? '' : \$relativePath") !== false
		&& strpos($publicLibrary, "dol_buildpath('/emergencyhouse/public/'.ltrim") === false,
	'L’URL configurée est la racine directe du répertoire public'
);
emergencyhouseContract(
	strpos($publicLibrary, '<span class="eh-date-selector">') !== false
		&& strpos($publicLibrary, "array('Day', 'Month')") !== false
		&& strpos($publicLibrary, "'year' => \$langs->trans('Year')") !== false
		&& strpos($publicStyles, '.eh-date-selector {') !== false
		&& strpos($publicStyles, 'grid-template-columns: minmax(62px, .7fr)') !== false
		&& strpos($publicStyles, '.eh-date-selector:focus-within') !== false
		&& substr_count($publicOfferEditor, '<fieldset class="eh-field eh-fieldset"><legend>') >= 2
		&& substr_count($publicRequestEditor, '<fieldset class="eh-field eh-fieldset"><legend>') >= 2,
	'Sélecteurs de date natifs regroupés, compacts et accessibles sur le portail'
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
	strpos($setup, "require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';") !== false
		&& strpos($setup, "'EMERGENCYHOUSE_PUBLIC_TERMS_HTML' => array('type' => 'string', 'default' => '')") !== false
		&& strpos($setup, "isModEnabled('fckeditor')") !== false
		&& strpos($setup, '$editor = new DolEditor(') !== false
		&& strpos($descriptor, "'EMERGENCYHOUSE_PUBLIC_TERMS_HTML' => array('', 'chaine')") !== false,
	'CGU administrables avec l’éditeur WYSIWYG natif et repli textarea'
);
emergencyhouseContract(
	strpos($publicLibrary, "emergencyhousePublicUrl('terms.php')") !== false
		&& strpos($publicLibrary, 'emergencyhousePublicHtmlHasContent($termsHtml)') !== false
		&& strpos($registerController, '$termsAccepted = !$termsEnabled ||') !== false
		&& strpos($registerController, 'if ($termsEnabled) {') !== false
		&& strpos($termsController, 'http_response_code(404);') !== false
		&& strpos($termsController, 'dolPrintHTML($termsHtml)') !== false
		&& strpos($publicLibrary, 'EMERGENCYHOUSE_PUBLIC_TERMS_URL') === false,
	'Page et consentement CGU publiés uniquement lorsqu’un contenu HTML existe'
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
$publicRobots = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'robots.php');
$publicSitemap = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'sitemap.php');
$publicLlmIndex = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'llms.php');
$publicHomeSeo = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php');
emergencyhouseContract(
	strpos($publicRobots, 'OAI-SearchBot') !== false
		&& strpos($publicRobots, 'ChatGPT-User') !== false
		&& strpos($publicRobots, 'EMERGENCYHOUSE_PUBLIC_GPTBOT_ALLOWED') !== false
		&& strpos($descriptor, "'EMERGENCYHOUSE_PUBLIC_GPTBOT_ALLOWED' => array('0', 'yesno')") !== false,
	'Robots IA séparés entre recherche, navigation utilisateur et entraînement'
);
emergencyhouseContract(
	strpos($publicSitemap, 'robots_index = 1') !== false
		&& strpos($publicSitemap, 'description_public IS NOT NULL') !== false
		&& strpos($publicSitemap, "emergencyhousePublicAbsoluteUrl('campaign.php'") !== false
		&& strpos($publicLlmIndex, 'LlmIndexPrivacyNotice') !== false,
	'Sitemap et index LLM limités aux campagnes publiques complètes et autorisées'
);
emergencyhouseContract(
	strpos($publicLibrary, 'rel="canonical"') !== false
		&& strpos($publicLibrary, 'application/ld+json') !== false
		&& strpos($publicLibrary, 'og:description') !== false
		&& strpos($publicHomeSeo, 'emergencyhousePublicHomeStructuredData') !== false,
	'Métadonnées canoniques, sociales et structurées sur le portail public'
);
emergencyhouseContract(
	strpos($setup, 'ErrorPublicBaseUrlInvalid') !== false
		&& strpos($setup, "rtrim(\$publicBaseUrl, '/').'/'") !== false
		&& strpos($setup, "'EMERGENCYHOUSE_PUBLIC_BASE_URL' => 'HelpPublicBaseUrl'") !== false,
	'Validation et aide de l’URL racine publique'
);
$publicContact = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'contact.php'
);
$publicHome = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php'
);
$publicCaptcha = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'captcha.php'
);
$publicContactService = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'publiccontactservice.class.php'
);
emergencyhouseContract(
	strpos($setup, "'EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL' => array('type' => 'string', 'default' => '')") !== false
		&& strpos($setup, "'EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE' => array('type' => 'string', 'default' => '')") !== false
		&& strpos($setup, 'type="email" inputmode="email" autocomplete="email"') !== false
		&& strpos($setup, 'type="tel" inputmode="tel" autocomplete="tel"') !== false
		&& strpos($descriptor, "'EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL' => array('', 'chaine')") !== false
		&& strpos($descriptor, "'EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE' => array('', 'chaine')") !== false,
	'Coordonnées du support configurables par entité'
);
emergencyhouseContract(
	substr_count($publicLibrary, "emergencyhousePublicUrl('contact.php')") >= 2
		&& strpos($publicLibrary, "'contact', \$active") !== false
		&& strpos($publicHome, "emergencyhousePublicUrl('contact.php')") !== false
		&& strpos($preview, 'id="preview-contact"') !== false,
	'Page Nous contacter visible dans la navigation, l’accueil, le pied et l’aperçu'
);
emergencyhouseContract(
	strpos($publicContact, "getDolGlobalInt('MAIN_SECURITY_ENABLECAPTCHA'") !== false
		&& strpos($publicContact, "\$_SESSION['dol_antispam_value']") !== false
		&& strpos($publicContact, 'hash_equals(') !== false
		&& strpos($publicContact, 'emergencyhousePublicCsrfFields(') !== false
		&& strpos($publicContact, 'emergencyhousePublicConsumeRateLimit(') !== false
		&& strpos($publicCaptcha, 'core/antispamimage.php') !== false,
	'Formulaire protégé par CSRF, débit et captcha natif'
);
emergencyhouseContract(
	strpos($publicContact, 'enctype="multipart/form-data"') !== false
		&& strpos($publicContact, 'name="attachments[]"') !== false
		&& strpos($publicContactService, 'getMaxFileSizeArray()') !== false
		&& strpos($publicContactService, 'getimagesize($tmpName)') !== false
		&& strpos($publicContactService, 'dolCheckVirus($tmpName, $safeName)') !== false
		&& strpos($publicContactService, 'new CMailFile(') !== false
		&& strpos($publicContactService, 'MAIN_MAIL_AUTOCOPY_TO permanent BCC') !== false
		&& strpos($publicContactService, 'queueEmail(') === false
		&& strpos($publicContactService, 'dol_move_uploaded_file(') === false,
	'Images contrôlées, envoyées immédiatement et non conservées'
);
$publicOfferView = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'offer'.DIRECTORY_SEPARATOR.'view.php'
);
$publicOfferPhoto = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'offer'.DIRECTORY_SEPARATOR.'photo.php'
);
$operatorOfferPhoto = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'offer'.DIRECTORY_SEPARATOR.'photo.php'
);
$offerPhotoService = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'offerphotoservice.class.php'
);
$verificationService = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'verificationservice.class.php'
);
emergencyhouseContract(
	strpos($publicOfferEditor, 'enctype="multipart/form-data"') !== false
		&& strpos($publicOfferEditor, 'name="offer_photos[]"') !== false
		&& strpos($publicOfferEditor, "'offer_photo_delete'") !== false
		&& strpos($publicOfferEditor, "action\" value=\"delete_photo") !== false
		&& strpos($publicOfferEditor, "trans('OfferPhotoUploadHelp'") !== false,
	'Ajout et suppression CSRF des photos depuis le formulaire public d’offre'
);
emergencyhouseContract(
	strpos($offerPhotoService, "getDolGlobalInt('EMERGENCYHOUSE_PHOTOS_ENABLED'") !== false
		&& strpos($offerPhotoService, 'getMaxFileSizeArray()') !== false
		&& strpos($offerPhotoService, 'getimagesize($tmpName)') !== false
		&& strpos($offerPhotoService, 'dolCheckVirus($tmpName, $safeName)') !== false
		&& strpos($offerPhotoService, "getMultidirOutput(\$offer, 'emergencyhouse', 1)") !== false
		&& strpos($offerPhotoService, '@imagecreatefromjpeg(') !== false
		&& strpos($offerPhotoService, '@imagecreatefrompng(') !== false
		&& strpos($offerPhotoService, '@imagecreatefromwebp(') !== false
		&& strpos($offerPhotoService, '@imagejpeg(') !== false
		&& strpos($offerPhotoService, '@imagepng(') !== false
		&& strpos($offerPhotoService, '@imagewebp(') !== false,
	'Photos validées, réencodées sans métadonnées et stockées dans le répertoire Multicompany'
);
emergencyhouseContract(
	strpos($publicOfferPhoto, 'fetchViewableOffer(') !== false
		&& strpos($publicOfferPhoto, 'getPhotoFile($offer, $photoId, $isOwner)') !== false
		&& strpos($publicOfferPhoto, "getDolGlobalInt('EMERGENCYHOUSE_PHOTOS_ENABLED'") !== false
		&& strpos($publicOfferPhoto, '$publiclyCacheable = !$isOwner') !== false
		&& strpos($publicOfferPhoto, 'Cache-Control: private, no-store') !== false
		&& strpos($publicOfferPhoto, 'X-Content-Type-Options: nosniff') !== false
		&& strpos($operatorOfferPhoto, "emergencyhouseCanDo(\$user, 'listing', 'read', \$offer)") !== false
		&& strpos($operatorOfferPhoto, 'getPhotoFile($offer, $photoId, true)') !== false,
	'Diffusion des photos contrôlée séparément pour le public, le propriétaire et les opérateurs'
);
emergencyhouseContract(
	strpos($publicOfferView, '$photoService->fetchPhotos($offer)') !== false
		&& strpos($publicOfferView, '$photoService->fetchPhotos($offer, true)') !== false
		&& strpos($verificationService, '$photoService->updateStatuses($linkedObject, $status)') !== false,
	'Galerie publique limitée aux photos approuvées et statuts synchronisés avec la vérification'
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
$campaignEditor = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'campaign'.DIRECTORY_SEPARATOR.'edit.php');
$campaignClass = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'campaign.class.php');
emergencyhouseContract(
	strpos($campaignEditor, 'name="privacy_url" required') === false
		&& strpos($campaignEditor, 'name="terms_url" required') === false
		&& strpos($campaignEditor, "return \$url === '' ||") !== false
		&& strpos($campaignEditor, 'CampaignPrivacyUrlFallbackHelp') !== false
		&& strpos($campaignEditor, 'CampaignTermsUrlFallbackHelp') !== false,
	'URL juridiques de campagne facultatives avec héritage explicite'
);
emergencyhouseContract(
	strpos($campaignClass, "&& !empty(\$this->privacy_url)") === false
		&& strpos($campaignClass, "&& !empty(\$this->terms_url)") === false,
	'Publication de campagne indépendante des URL juridiques spécifiques'
);

$statusObjects = array('campaign', 'offer', 'request', 'solicitation', 'allocation', 'report');
foreach ($statusObjects as $statusObject) {
	$statusClass = emergencyhouseReadRequired(
		$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.$statusObject.'.class.php'
	);
	emergencyhouseContract(
		strpos($statusClass, "'status0'") !== false
			|| strpos($statusClass, "'status1'") !== false,
		'Type de badge Dolibarr déclaré pour '.$statusObject
	);
	emergencyhouseContract(
		preg_match("/array\\('Status[^']+',\\s*[0-9]+\\)/", $statusClass) !== 1,
		'Aucun type de statut numérique non natif pour '.$statusObject
	);
}
$objectList = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'_object_list.php');
$matchList = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'match'.DIRECTORY_SEPARATOR.'list.php');
$verificationList = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'verification'.DIRECTORY_SEPARATOR.'list.php');
emergencyhouseContract(
	strpos($objectList, '$record->getLibStatut(5)') !== false
		&& strpos($matchList, '$statusCode, 5)') !== false
		&& strpos($matchList, "(int) \$row->status === 1 ? 'status4' : 'status6'") !== false
		&& strpos($verificationList, "EmergencyHouseVerificationService::STATUS_VERIFIED ? 'status4'") !== false
		&& strpos($verificationList, "EmergencyHouseVerificationService::STATUS_REFUSED ? 'status6' : 'status1'") !== false
		&& strpos($verificationList, "\t\t\t\t5\n") !== false,
	'Listes du back-office rendues avec les badges de statut natifs'
);
emergencyhouseContract(
	strpos($verificationList, 'type="number" min="1" name="fk_object"') === false
		&& strpos($verificationList, "GETPOSTINT('fk_object')") === false
		&& strpos($verificationList, "'q.fk_object' => array('label' => 'ObjectToVerify'") !== false
		&& strpos($verificationList, "'v.date_expiration' => array('label' => 'DateExpiration'") !== false,
	'Cible imposée par la file et libellés traduits sur les vérifications'
);
$objectCard = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'_object_card.php');
$matchCard = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'match'.DIRECTORY_SEPARATOR.'card.php');
$offerClass = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'offer.class.php');
$publicAccountClass = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'publicaccount.class.php'
);
emergencyhouseContract(
	strpos($objectCard, "'fk_account' => 'DepositedBy'") !== false
		&& strpos($offerClass, "'fk_account' => array('type' => 'integer', 'label' => 'DepositedBy'") !== false
		&& strpos($objectCard, "emergencyhouseCanDo(\$user, 'sensitive', 'contact', \$object)") !== false
		&& strpos($objectCard, '$account->fetch($accountId, (int) $object->entity)') !== false
		&& strpos($objectCard, '$account->getDecryptedIdentity()') !== false
		&& strpos($objectCard, "trans('PublicIdentityProtected')") !== false
		&& strpos($publicAccountClass, 'public function getDecryptedIdentity()') !== false,
	'Propriétaire de l’offre résolu sans identifiant brut et soumis au droit sensible'
);
emergencyhouseContract(
	strpos($objectCard, "print '</div></div><div class=\"clearboth\"></div>';") !== false
		&& substr_count($objectCard, 'dolGetButtonAction(') >= 3
		&& strpos($objectCard, '<form class="inline-block"') === false
		&& strpos($matchCard, "print '</table></div></div><div class=\"clearboth\"></div>';") !== false
		&& strpos($matchCard, 'dolGetButtonAction(') !== false,
	'Actions de fiche placées dans la barre native après dégagement des colonnes'
);
emergencyhouseContract(
	strpos(
		$objectCard,
		"\$listUrl = dol_buildpath('/emergencyhouse/'.\$object->element.'/list.php', 1).'?restore_lastsearch_values=1';"
	) !== false
		&& strpos($objectCard, "\$langs->trans('BackToList')") !== false
		&& strpos($objectCard, "dol_banner_tab(\$object, 'id', \$linkback, 1, 'rowid', 'ref');") !== false,
	'Bannières avec retour liste natif et navigation précédent/suivant basée sur rowid'
);
emergencyhouseContract(
	strpos($objectCard, "'offers_requests' => 'VisibilityOffersAndRequests'") !== false
		&& strpos($objectCard, "'email_verification' => 'VerificationEmailOnly'") !== false
		&& strpos($objectCard, "'request_to_offer' => 'SolicitationDirectionRequestToOffer'") !== false
		&& strpos($objectCard, "'date_expiration' => 'DateExpiration'") !== false
		&& strpos($objectList, "'label' => 'DateExpiration'") !== false
		&& strpos($objectCard, "trans('SeverityLevel'.\$severity)") !== false
		&& strpos($objectList, "trans('SeverityLevel'.\$severity)") !== false,
	'Valeurs techniques des fiches et gravités de liste rendues par des traductions bilingues'
);
emergencyhouseContract(
	strpos($objectCard, "include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';") !== false
		&& strpos($objectCard, "include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';") !== false
		&& strpos($objectCard, "\$moreparam = '&tab=notes';") !== false
		&& strpos($objectCard, "action\" value=\"save_notes") === false
		&& strpos($commonObject, 'public function update_note(') !== false
		&& strpos($commonObject, "\$this->context['trigger_reason'] = 'notes_update';") !== false
		&& strpos($commonObject, "SET '.\$field.' = ") !== false
		&& strpos($commonObject, "' AND entity = '.((int) \$this->entity)") !== false
		&& strpos($commonObject, "\$this->call_trigger(\$this->trigger_prefix.'_UPDATE', \$user)") !== false,
	'Notes rendues et enregistrées avec la structure native Dolibarr'
);
emergencyhouseContract(
	strpos($objectCard, "require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';") !== false
		&& strpos($objectCard, "include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';") !== false
		&& strpos($objectCard, "include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';") !== false
		&& strpos($objectCard, "getMultidirOutput(\$object, 'emergencyhouse', 1)") !== false
		&& strpos($objectCard, "getMultidirOutput(\$object, 'emergencyhouse', 1, 'outputrel')") !== false
		&& strpos($objectCard, "\$relativepathwithnofile .= '/';") !== false
		&& strpos($objectCard, "\$moreparam = '&tab=documents';") !== false
		&& strpos($objectCard, "trans('NbOfAttachedFiles')") !== false
		&& strpos($objectCard, "trans('TotalSizeOfAttachedFiles')") !== false
		&& strpos($objectCard, "\$permissiontoadd = \$permissionToWrite ? 1 : 0;") !== false,
	'Fichiers joints gérés par le contrôleur et le modèle documentaires natifs'
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
	if (strpos($content, 'dol_time_plus_duree(') !== false) {
		emergencyhouseContract(
			strpos($content, "require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';") !== false,
			'Dépendance date.lib.php déclarée dans '.substr($phpFile, strlen($root) + 1)
		);
	}
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
emergencyhouseContract(count($tableMatches) === 45, 'Quarante-cinq tables InnoDB déclarées');
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
emergencyhouseContract(
	strpos($schemaSql, 'fk_member integer NULL') !== false
		&& strpos($schemaSql, 'UNIQUE KEY uk_emergencyhouse_account_member (entity, fk_member)') !== false,
	'Liaison membre nullable et unique par entité sur les comptes publics'
);
$publicAccountClass = emergencyhouseReadRequired(
	$root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'publicaccount.class.php'
);
emergencyhouseContract(
	strpos($publicAccountClass, 'public $fk_member = 0;') !== false
		&& strpos($publicAccountClass, 'public function linkMember($memberId)') !== false
		&& strpos($publicAccountClass, 'password_hash = NULL, fk_member = NULL') !== false,
	'Liaison membre persistée et détachée sans supprimer l’adhérent lors de l’anonymisation'
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

$publicInit = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'_init.php');
emergencyhouseContract(
	strpos($publicInit, "\$langs->loadLangs(array('main', 'companies', 'other', 'emergencyhouse@emergencyhouse'));") !== false,
	'Domaines natifs companies et other chargés sur le portail public'
);

$publicUserInterfacePhp = $publicControllers."\n".$publicLibrary;
$publicDateTranslationPlaceholders = array(
	'CampaignFromDate' => 1,
	'AvailabilityFromDate' => 1,
	'NeedFromDate' => 1,
	'UntilDate' => 1,
	'AllocationSummary' => 2,
	'OfferPhotosHelp' => 1,
	'OfferPhotoUploadHelp' => 1,
	'OfferPhotoCount' => 2,
	'OfferPhotoAlt' => 1,
);
foreach ($publicDateTranslationPlaceholders as $translationKey => $placeholderCount) {
	emergencyhouseContract(
		isset($frTranslations[$translationKey], $enTranslations[$translationKey])
			&& substr_count($frTranslations[$translationKey], '%s') === $placeholderCount
			&& substr_count($enTranslations[$translationKey], '%s') === $placeholderCount,
		'Paramètres de date publics traduits : '.$translationKey
	);
}
emergencyhouseContract(
	strpos($publicControllers, "trans('FromDate',") === false
		&& strpos($publicControllers, "trans(\n\t\t\t'CampaignFromDate',") !== false
		&& strpos($publicControllers, "trans('CampaignPeriod')") !== false
		&& strpos($publicControllers, 'emergencyhousePublicDatabaseDate($db, $campaign->date_start)') !== false,
	'Dates de campagne rendues avec des traductions paramétrées sur les pages publiques'
);
preg_match_all(
	"/(?:trans|transnoentitiesnoconv)\\(\\s*['\"]([A-Za-z][A-Za-z0-9_]*)['\"]/",
	$publicUserInterfacePhp,
	$publicTranslationMatches
);
$publicTranslationKeys = isset($publicTranslationMatches[1])
	? array_values(array_unique($publicTranslationMatches[1]))
	: array();
$publicDolibarrMentions = array();
foreach ($publicTranslationKeys as $publicTranslationKey) {
	if (
		isset($frTranslations[$publicTranslationKey])
		&& stripos($frTranslations[$publicTranslationKey], 'Dolibarr') !== false
	) {
		$publicDolibarrMentions[] = 'fr_FR:'.$publicTranslationKey;
	}
	if (
		isset($enTranslations[$publicTranslationKey])
		&& stripos($enTranslations[$publicTranslationKey], 'Dolibarr') !== false
	) {
		$publicDolibarrMentions[] = 'en_US:'.$publicTranslationKey;
	}
}
emergencyhouseContract(
	empty($publicDolibarrMentions) && stripos($publicControllers, 'Dolibarr') === false,
	'Aucune mention de Dolibarr dans l’interface publique'
);
$publicLogin = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'login.php');
$publicAuthService = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'class'.DIRECTORY_SEPARATOR.'publicauthservice.class.php');
emergencyhouseContract(
	strpos($publicLogin, "'PublicAuthenticationFailed'") !== false
		&& strpos($publicLogin, "\$emergencyhousePublicAuth->error === 'ErrorAuthenticationFailed'") !== false,
	'Échec de connexion public distinct d’une erreur technique'
);
emergencyhouseContract(
	strpos($publicAuthService, 'if ($fetchResult < 0)') !== false
		&& strpos($publicAuthService, 'account lookup failed') !== false
		&& strpos($publicAuthService, 'session creation failed') !== false
		&& strpos($publicAuthService, 'session insert failed') !== false,
	'Journalisation des erreurs techniques de connexion publique'
);
$publicRateLimitCallers = $publicLogin
	.emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'register.php')
	.emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'forgot.php')
	.emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'resend.php');
emergencyhouseContract(
	substr_count($publicRateLimitCallers, "\$rateLimitError === 'ErrorRateLimitExceeded'") === 4
		&& strpos($publicLibrary, '&$errorCode = null') !== false
		&& strpos($publicLibrary, "\$errorCode !== 'ErrorRateLimitExceeded'") !== false,
	'Distinction entre limitation de débit et panne technique'
);

preg_match_all(
	"/emergencyhousePublicAlert\\(\\s*'([A-Za-z][A-Za-z0-9_]*)'/",
	$publicControllers,
	$publicAlertMatches
);
$publicAlertKeys = isset($publicAlertMatches[1]) ? array_values(array_unique($publicAlertMatches[1])) : array();
preg_match_all(
	"/\\$(?:errorKey|noticeKey)\\s*=\\s*'([A-Za-z][A-Za-z0-9_]*)'/",
	$publicControllers,
	$publicAssignedAlertMatches
);
if (isset($publicAssignedAlertMatches[1])) {
	$publicAlertKeys = array_values(array_unique(array_merge($publicAlertKeys, $publicAssignedAlertMatches[1])));
}
sort($publicAlertKeys);
foreach ($publicAlertKeys as $publicAlertKey) {
	emergencyhouseContract(
		isset($frTranslations[$publicAlertKey]) && isset($enTranslations[$publicAlertKey]),
		'Message public traduit : '.$publicAlertKey
	);
}

$requiredTranslations = array(
	'EmergencyHouseModuleDescription',
	'EmergencyHouseModuleDescriptionLong',
	'EmergencyHouseRightApi',
	'OperationalComponent',
	'OperationalStatus',
	'ScheduledJobs',
	'SelectAnOption',
	'ContactSubject',
	'AvailablePlaces',
	'PeopleStillSeeking',
	'ConfirmedAccommodations',
	'OfferPhotos',
	'OfferPhotoUploadHelp',
	'OfferPhotoStatusPending',
	'OfferPhotoStatusApproved',
	'OfferPhotoStatusRejected',
	'MaximumStayDaysOptional',
	'SeverityLevel4',
	'SeverityLevel5',
	'SolicitationDirectionRequestToOffer',
	'SolicitationDirectionOfferToRequest',
	'SolicitationDirectionOperator',
	'UnknownValue',
	'ObjectToVerify',
	'VerifiedObject',
	'CompatibilityOfferPhotos',
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
emergencyhouseContract(
	isset($frTranslations['OfferPhotoUploadHelp'], $enTranslations['OfferPhotoUploadHelp'])
		&& strpos($frTranslations['OfferPhotoUploadHelp'], 'EXIF') !== false
		&& strpos($frTranslations['OfferPhotoUploadHelp'], 'GPS') !== false
		&& strpos($enTranslations['OfferPhotoUploadHelp'], 'EXIF') !== false
		&& strpos($enTranslations['OfferPhotoUploadHelp'], 'GPS') !== false,
	'Suppression des métadonnées EXIF et GPS explicitement annoncée aux utilisateurs'
);
emergencyhouseContract(
	strpos($publicControllers, "trans('Direction')") === false
		&& strpos($publicControllers, "trans('NextPage')") === false
		&& strpos($publicControllers, "trans('Subject')") === false,
	'Clés publiques non résolues remplacées par leurs traductions natives ou bilingues'
);

$dashboard = emergencyhouseReadRequired($root.DIRECTORY_SEPARATOR.'index.php');
emergencyhouseContract(
	strpos($dashboard, '<tr class="liste_titre">') !== false
		&& strpos($dashboard, "trans('OperationalComponent')") !== false
		&& strpos($dashboard, "trans('OperationalStatus')") !== false
		&& strpos($dashboard, "\$ready ? 'status4' : 'status6', 2") !== false,
	'Tableau d’état opérationnel avec en-têtes et badges Dolibarr'
);

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
