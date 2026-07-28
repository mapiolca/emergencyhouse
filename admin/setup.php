<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res && file_exists('../../../../main.inc.php')) {
	$res = include '../../../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	exit;
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/emergencyhouse/core/modules/emergencyhouse/doc/pdf_emergencyhouse_agreement.modules.php');
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_public.lib.php');

$langs->loadLangs(array('admin', 'emergencyhouse@emergencyhouse'));

if (!isModEnabled('emergencyhouse')) {
	accessforbidden();
}
if (!emergencyhouseCanDo($user, 'configuration', 'write')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$tab = GETPOST('tab', 'aZ09');
if (empty($tab)) {
	$tab = 'general';
}

/**
 * @var array<string, array{label:string,model:string}>
 */
$numberingConstants = array(
	'EMERGENCYHOUSE_CAMPAIGN_ADDON' => array('label' => 'Campaign', 'model' => 'emergencyhouse_campaign_standard'),
	'EMERGENCYHOUSE_OFFER_ADDON' => array('label' => 'Offer', 'model' => 'emergencyhouse_offer_standard'),
	'EMERGENCYHOUSE_REQUEST_ADDON' => array('label' => 'Request', 'model' => 'emergencyhouse_request_standard'),
	'EMERGENCYHOUSE_SOLICITATION_ADDON' => array('label' => 'Solicitation', 'model' => 'emergencyhouse_solicitation_standard'),
	'EMERGENCYHOUSE_ALLOCATION_ADDON' => array('label' => 'Allocation', 'model' => 'emergencyhouse_allocation_standard'),
	'EMERGENCYHOUSE_REPORT_ADDON' => array('label' => 'Report', 'model' => 'emergencyhouse_report_standard'),
);

/**
 * @var array<string, array<string, array{type:string,default:string}>> $settingsByTab
 */
$settingsByTab = array(
	'general' => array(
		'EMERGENCYHOUSE_DEFAULT_TIMEZONE' => array('type' => 'string', 'default' => 'Europe/Paris'),
		'EMERGENCYHOUSE_DEFAULT_LANGUAGE' => array('type' => 'string', 'default' => 'fr_FR'),
		'EMERGENCYHOUSE_FREE_TEXT' => array('type' => 'string', 'default' => ''),
	),
	'portal' => array(
		'EMERGENCYHOUSE_PUBLIC_REQUEST_VISIBILITY' => array('type' => 'string', 'default' => 'private'),
		'EMERGENCYHOUSE_PUBLIC_BASE_URL' => array('type' => 'string', 'default' => ''),
		'EMERGENCYHOUSE_PUBLIC_ORGANISATION_NAME' => array('type' => 'string', 'default' => ''),
		'EMERGENCYHOUSE_PUBLIC_OFFICIAL_PHONE' => array('type' => 'string', 'default' => ''),
		'EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL' => array('type' => 'string', 'default' => ''),
		'EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE' => array('type' => 'string', 'default' => ''),
		'EMERGENCYHOUSE_PUBLIC_PRIVACY_URL' => array('type' => 'string', 'default' => ''),
		'EMERGENCYHOUSE_PUBLIC_TERMS_HTML' => array('type' => 'string', 'default' => ''),
	),
	'authentication' => array(
		'EMERGENCYHOUSE_SESSION_IDLE_MINUTES' => array('type' => 'int', 'default' => '120'),
		'EMERGENCYHOUSE_TOKEN_TTL_MINUTES' => array('type' => 'int', 'default' => '30'),
		'EMERGENCYHOUSE_MAX_LOGIN_ATTEMPTS' => array('type' => 'int', 'default' => '5'),
		'EMERGENCYHOUSE_MESSAGE_MAX_LENGTH' => array('type' => 'int', 'default' => '4000'),
		'EMERGENCYHOUSE_SOLICITATION_EXPIRY_DAYS' => array('type' => 'int', 'default' => '7'),
		'EMERGENCYHOUSE_SOLICITATION_DAILY_LIMIT' => array('type' => 'int', 'default' => '20'),
	),
	'matching' => array(
		'EMERGENCYHOUSE_MATCH_DISTANCE_WEIGHT' => array('type' => 'int', 'default' => '30'),
		'EMERGENCYHOUSE_MATCH_CAPACITY_WEIGHT' => array('type' => 'int', 'default' => '25'),
		'EMERGENCYHOUSE_MATCH_DATES_WEIGHT' => array('type' => 'int', 'default' => '20'),
		'EMERGENCYHOUSE_MATCH_TYPE_WEIGHT' => array('type' => 'int', 'default' => '15'),
		'EMERGENCYHOUSE_MATCH_FEATURES_WEIGHT' => array('type' => 'int', 'default' => '10'),
		'EMERGENCYHOUSE_MATCH_DEFAULT_RADIUS_KM' => array('type' => 'int', 'default' => '50'),
		'EMERGENCYHOUSE_MATCH_BATCH_SIZE' => array('type' => 'int', 'default' => '100'),
	),
	'notifications' => array(
		'EMERGENCYHOUSE_NOTIFICATION_BATCH_SIZE' => array('type' => 'int', 'default' => '100'),
		'EMERGENCYHOUSE_NOTIFICATION_MAX_ATTEMPTS' => array('type' => 'int', 'default' => '5'),
	),
	'providers' => array(
		'EMERGENCYHOUSE_OSM_TILE_URL' => array('type' => 'string', 'default' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
		'EMERGENCYHOUSE_GEOCODING_PROVIDER' => array('type' => 'string', 'default' => 'disabled'),
		'EMERGENCYHOUSE_GEOCODING_ENDPOINT' => array('type' => 'string', 'default' => 'https://data.geopf.fr/geocodage/search'),
		'EMERGENCYHOUSE_SMS_PROVIDER' => array('type' => 'string', 'default' => 'disabled'),
	),
	'security' => array(
		'EMERGENCYHOUSE_RATE_LIMIT_HOUR' => array('type' => 'int', 'default' => '20'),
		'EMERGENCYHOUSE_RATE_LIMIT_DAY' => array('type' => 'int', 'default' => '100'),
		'EMERGENCYHOUSE_CSP_EXTRA_CONNECT_SRC' => array('type' => 'string', 'default' => ''),
	),
	'retention' => array(
		'EMERGENCYHOUSE_RETENTION_SESSION_DAYS' => array('type' => 'int', 'default' => '7'),
		'EMERGENCYHOUSE_RETENTION_TOKEN_DAYS' => array('type' => 'int', 'default' => '7'),
		'EMERGENCYHOUSE_RETENTION_ACCOUNT_DAYS' => array('type' => 'int', 'default' => '90'),
		'EMERGENCYHOUSE_RETENTION_ADDRESS_DAYS' => array('type' => 'int', 'default' => '30'),
		'EMERGENCYHOUSE_RETENTION_MESSAGE_DAYS' => array('type' => 'int', 'default' => '90'),
		'EMERGENCYHOUSE_RETENTION_OPERATIONAL_DAYS' => array('type' => 'int', 'default' => '90'),
		'EMERGENCYHOUSE_RETENTION_AUDIT_DAYS' => array('type' => 'int', 'default' => '365'),
	),
	'integrations' => array(
		'EMERGENCYHOUSE_DATAPOLICY_MODE' => array('type' => 'string', 'default' => 'disabled'),
		'EMERGENCYHOUSE_ADHERENT_MODE' => array('type' => 'string', 'default' => 'disabled'),
		'EMERGENCYHOUSE_RESOURCE_MODE' => array('type' => 'string', 'default' => 'disabled'),
	),
	'multicompany' => array(
		'EMERGENCYHOUSE_CROSS_ENTITY_MODE' => array('type' => 'string', 'default' => 'disabled'),
	),
	'advanced' => array(
		'EMERGENCYHOUSE_JOB_BATCH_SIZE' => array('type' => 'int', 'default' => '100'),
		'EMERGENCYHOUSE_AUDIT_IP_RETENTION_HOURS' => array('type' => 'int', 'default' => '72'),
		'EMERGENCYHOUSE_LOG_LEVEL' => array('type' => 'string', 'default' => 'warning'),
	),
);

/**
 * Contextual help shown below advanced settings.
 *
 * @var array<string, string>
 */
$settingHelpKeys = array(
	'EMERGENCYHOUSE_PUBLIC_BASE_URL' => 'HelpPublicBaseUrl',
	'EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL' => 'HelpPublicSupportEmail',
	'EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE' => 'HelpPublicSupportPhone',
	'EMERGENCYHOUSE_PUBLIC_TERMS_HTML' => 'HelpPublicTermsHtml',
	'EMERGENCYHOUSE_OSM_TILE_URL' => 'HelpOsmTileUrl',
	'EMERGENCYHOUSE_GEOCODING_PROVIDER' => 'HelpGeocodingProvider',
	'EMERGENCYHOUSE_GEOCODING_ENDPOINT' => 'HelpGeocodingEndpoint',
	'EMERGENCYHOUSE_SMS_PROVIDER' => 'HelpSmsProvider',
	'EMERGENCYHOUSE_RATE_LIMIT_HOUR' => 'HelpRateLimitHour',
	'EMERGENCYHOUSE_RATE_LIMIT_DAY' => 'HelpRateLimitDay',
	'EMERGENCYHOUSE_CSP_EXTRA_CONNECT_SRC' => 'HelpCspExtraConnectSrc',
);

if ($action === 'set_numbering_model') {
	$constant = GETPOST('constant', 'aZ09');
	$value = GETPOST('value', 'aZ09');
	if (!isset($numberingConstants[$constant]) || $value !== $numberingConstants[$constant]['model']) {
		setEventMessages($langs->trans('ErrorInvalidNumberingModel'), null, 'errors');
	} elseif (dolibarr_set_const($db, $constant, $value, 'chaine', 0, '', (int) $conf->entity) > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('ErrorSetupNotSaved'), null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?tab=general');
	exit;
} elseif (in_array($action, array('set_document_model', 'del_document_model', 'set_default_document_model'), true)) {
	$value = GETPOST('value', 'aZ09');
	$result = -1;
	if ($value === 'emergencyhouse_agreement') {
		if ($action === 'del_document_model') {
			$result = delDocumentModel($value, 'emergencyhouse');
		} else {
			$result = delDocumentModel($value, 'emergencyhouse');
			if ($result >= 0) {
				$result = addDocumentModel($value, 'emergencyhouse', 'EmergencyHouseAgreement', 'emergencyhouse');
			}
			if ($result > 0 && $action === 'set_default_document_model') {
				$result = dolibarr_set_const(
					$db,
					'EMERGENCYHOUSE_ALLOCATION_DEFAULT_MODEL',
					$value,
					'chaine',
					0,
					'',
					(int) $conf->entity
				);
			}
		}
	}
	setEventMessages(
		$langs->trans($result > 0 ? 'SetupSaved' : 'ErrorSetupNotSaved'),
		null,
		$result > 0 ? 'mesgs' : 'errors'
	);
	header('Location: '.$_SERVER['PHP_SELF'].'?tab=general');
	exit;
} elseif ($action === 'generate_security_keys' && $tab === 'security') {
	$encryptionService = new EmergencyHouseEncryptionService();
	$encryptionStatus = $encryptionService->getConfigurationStatus();
	if (!$encryptionStatus['sodium']) {
		setEventMessages($langs->trans('ErrorSodiumUnavailable'), null, 'errors');
	} elseif ($encryptionStatus['available']) {
		setEventMessages($langs->trans('SecurityKeysAlreadyConfigured'), null, 'warnings');
	} else {
		$encryptionName = EmergencyHouseEncryptionService::ENCRYPTION_KEY_NAME;
		$hmacName = EmergencyHouseEncryptionService::HMAC_KEY_NAME;
		$previousEncryptionKey = dolibarr_get_const($db, $encryptionName, 0);
		$previousHmacKey = dolibarr_get_const($db, $hmacName, 0);
		$encryptionKey = base64_encode(getRandomPassword(true, null, 48));
		do {
			$hmacKey = base64_encode(getRandomPassword(true, null, 48));
		} while (hash_equals($encryptionKey, $hmacKey));

		$encryptionResult = dolibarr_set_const($db, $encryptionName, $encryptionKey, 'chaine', 0, '', 0);
		$hmacResult = $encryptionResult > 0
			? dolibarr_set_const($db, $hmacName, $hmacKey, 'chaine', 0, '', 0)
			: -1;
		if ($encryptionResult > 0 && $hmacResult > 0) {
			setEventMessages($langs->trans('SecurityKeysInstalled'), null, 'mesgs');
		} else {
			dolibarr_set_const($db, $encryptionName, $previousEncryptionKey, 'chaine', 0, '', 0);
			dolibarr_set_const($db, $hmacName, $previousHmacKey, 'chaine', 0, '', 0);
			setEventMessages($langs->trans('ErrorSecurityKeysNotInstalled'), null, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?tab=security');
	exit;
} elseif ($action === 'save' && isset($settingsByTab[$tab])) {
	$error = 0;
	$values = array();
	foreach ($settingsByTab[$tab] as $name => $definition) {
		if ($definition['type'] === 'int') {
			$value = GETPOSTINT($name);
			if ($value < 0) {
				$error++;
				setEventMessages($langs->trans('ErrorPositiveIntegerExpected'), null, 'errors');
			}
			$values[$name] = (string) $value;
		} else {
			$values[$name] = GETPOST($name, 'restricthtml');
		}
	}

	if ($tab === 'matching') {
		$sum = (int) $values['EMERGENCYHOUSE_MATCH_DISTANCE_WEIGHT']
			+ (int) $values['EMERGENCYHOUSE_MATCH_CAPACITY_WEIGHT']
			+ (int) $values['EMERGENCYHOUSE_MATCH_DATES_WEIGHT']
			+ (int) $values['EMERGENCYHOUSE_MATCH_TYPE_WEIGHT']
			+ (int) $values['EMERGENCYHOUSE_MATCH_FEATURES_WEIGHT'];
		if ($sum !== 100) {
			$error++;
			setEventMessages($langs->trans('MatchingWeightsMustEqual100'), null, 'errors');
		}
	}
	if ($tab === 'portal') {
		$publicBaseUrl = trim($values['EMERGENCYHOUSE_PUBLIC_BASE_URL']);
		$supportEmail = trim($values['EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL']);
		$supportPhone = trim(dol_string_nohtmltag($values['EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE']));
		$values['EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL'] = $supportEmail;
		$values['EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE'] = $supportPhone;
		if ($publicBaseUrl !== '') {
			$publicBaseUrlParts = parse_url($publicBaseUrl);
			if (
				filter_var($publicBaseUrl, FILTER_VALIDATE_URL) === false
				|| !is_array($publicBaseUrlParts)
				|| !isset($publicBaseUrlParts['scheme'], $publicBaseUrlParts['host'])
				|| strtolower((string) $publicBaseUrlParts['scheme']) !== 'https'
				|| isset($publicBaseUrlParts['user'])
				|| isset($publicBaseUrlParts['pass'])
				|| isset($publicBaseUrlParts['query'])
				|| isset($publicBaseUrlParts['fragment'])
			) {
				$error++;
				setEventMessages($langs->trans('ErrorPublicBaseUrlInvalid'), null, 'errors');
			} else {
				$values['EMERGENCYHOUSE_PUBLIC_BASE_URL'] = rtrim($publicBaseUrl, '/').'/';
			}
		}
		if ($supportEmail !== '' && (filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false || dol_strlen($supportEmail) > 255)) {
			$error++;
			setEventMessages($langs->trans('ErrorSupportEmailInvalid'), null, 'errors');
		}
		if (dol_strlen($supportPhone) > 40) {
			$error++;
			setEventMessages($langs->trans('ErrorSupportPhoneInvalid'), null, 'errors');
		}
	}
	if ($tab === 'providers') {
		$geocodingProvider = $values['EMERGENCYHOUSE_GEOCODING_PROVIDER'];
		$geocodingEndpoint = $values['EMERGENCYHOUSE_GEOCODING_ENDPOINT'];
		$tileUrl = $values['EMERGENCYHOUSE_OSM_TILE_URL'];
		if (
			stripos($tileUrl, 'https://') !== 0
			|| strpos($tileUrl, '{z}') === false
			|| strpos($tileUrl, '{x}') === false
			|| strpos($tileUrl, '{y}') === false
		) {
			$error++;
			setEventMessages($langs->trans('ErrorOsmTileUrlInvalid'), null, 'errors');
		}
		if (!in_array($geocodingProvider, array('disabled', 'geoplateforme'), true)) {
			$error++;
			setEventMessages($langs->trans('ErrorGeocodingProviderNotImplemented'), null, 'errors');
		} elseif ($geocodingProvider !== 'disabled') {
			$endpointParts = parse_url($geocodingEndpoint);
			if (
				filter_var($geocodingEndpoint, FILTER_VALIDATE_URL) === false
				|| !is_array($endpointParts)
				|| !isset($endpointParts['scheme'], $endpointParts['host'])
				|| strtolower($endpointParts['scheme']) !== 'https'
			) {
				$error++;
				setEventMessages($langs->trans('ErrorGeocodingEndpointInvalid'), null, 'errors');
			} elseif ($geocodingProvider === 'geoplateforme' && strtolower($endpointParts['host']) !== 'data.geopf.fr') {
				$error++;
				setEventMessages($langs->trans('ErrorGeocodingEndpointInvalid'), null, 'errors');
			}
		}
		if ($values['EMERGENCYHOUSE_SMS_PROVIDER'] !== 'disabled') {
			$error++;
			setEventMessages($langs->trans('ErrorSmsProviderNotImplemented'), null, 'errors');
		}
	}
	if (!$error) {
		foreach ($values as $name => $value) {
			$result = dolibarr_set_const($db, $name, $value, 'chaine', 0, '', (int) $conf->entity);
			if ($result <= 0) {
				$error++;
			}
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('ErrorSetupNotSaved'), null, 'errors');
	}
}

$form = new Form($db);

llxHeader('', $langs->trans('EmergencyHouseSetup'), '', '', 0, 0, array(), array(), '', 'mod-emergencyhouse page-admin');

$head = emergencyhouseAdminPrepareHead();
print dol_get_fiche_head($head, $tab, $langs->trans('EmergencyHouseSetup'), -1, 'fontawesome_house-user');
print load_fiche_titre($langs->trans('EmergencyHouseSetup'), emergencyhouseAdminLinkBack(), 'title_setup');

if ($tab === 'notifications') {
	print '<div class="info">'.$langs->trans('NativeNotificationsConfigurationInfo').'</div>';
	print '<p><a class="button" href="'.DOL_URL_ROOT.'/admin/notification.php">'.$langs->trans('ConfigureNativeNotifications').'</a></p>';
}
if ($tab === 'multicompany') {
	print '<div class="info">'.$langs->trans('MulticompanyConfigurationInfo').'</div>';
}
if ($tab === 'providers') {
	print '<div class="warning">'.$langs->trans('ProviderSecretsNotStoredInfo').'</div>';
	print '<br>'.load_fiche_titre($langs->trans('ProviderConfigurationGuide'), '', 'map-marker-alt');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Service').'</th><th>'.$langs->trans('Status').'</th>';
	print '<th>'.$langs->trans('ConfigurationSteps').'</th><th>'.$langs->trans('Documentation').'</th></tr>';

	$osmEnabled = getDolGlobalInt('EMERGENCYHOUSE_OSM_TILES_ENABLED', 1) === 1;
	print '<tr class="oddeven"><td>'.$langs->trans('OpenStreetMapTiles').'</td>';
	print '<td>'.img_picto($langs->trans($osmEnabled ? 'Enabled' : 'Disabled'), $osmEnabled ? 'switch_on' : 'switch_off');
	print ' '.$langs->trans($osmEnabled ? 'Enabled' : 'Disabled').'</td>';
	print '<td>'.$langs->trans('OpenStreetMapConfigurationSteps').'</td>';
	print '<td><a target="_blank" rel="noopener noreferrer" href="https://operations.osmfoundation.org/policies/tiles/">';
	print $langs->trans('OpenStreetMapTilePolicy').'</a></td></tr>';

	$geocodingProvider = getDolGlobalString('EMERGENCYHOUSE_GEOCODING_PROVIDER', 'disabled');
	$geocodingEnabled = $geocodingProvider === 'geoplateforme';
	print '<tr class="oddeven"><td>'.$langs->trans('ExactGeocoding').'</td>';
	print '<td>'.img_picto($langs->trans($geocodingEnabled ? 'Enabled' : 'Disabled'), $geocodingEnabled ? 'switch_on' : 'switch_off');
	print ' '.$langs->trans($geocodingEnabled ? 'Enabled' : 'Disabled').'</td>';
	print '<td>'.$langs->trans('GeocodingConfigurationSteps').'</td>';
	print '<td><a target="_blank" rel="noopener noreferrer" href="https://cartes.gouv.fr/aide/fr/guides-utilisateur/utiliser-les-services-de-la-geoplateforme/geocodage/">';
	print $langs->trans('GeoplateformeDocumentation').'</a></td></tr>';

	print '<tr class="oddeven"><td>'.$langs->trans('SmsDelivery').'</td>';
	print '<td>'.img_picto($langs->trans('Unavailable'), 'switch_off').' '.$langs->trans('Unavailable').'</td>';
	print '<td>'.$langs->trans('SmsConfigurationSteps').'</td><td>';
	print '<a href="'.DOL_URL_ROOT.'/admin/sms.php">'.$langs->trans('ConfigureNativeSms').'</a><br>';
	print '<a target="_blank" rel="noopener noreferrer" href="https://wiki.dolibarr.org/index.php/Setup_SMS">';
	print $langs->trans('DolibarrSmsDocumentation').'</a></td></tr>';
	print '</table>';
	print '<div class="info">'.$langs->trans('GeocodingPrivacyHelp').'</div>';
}
if ($tab === 'authentication') {
	print '<div class="info">'.$langs->trans('OfferPublicationSecurityPolicyInfo').'</div>';
}
if ($tab === 'portal') {
	$previewUrl = dol_buildpath('/emergencyhouse/admin/public-preview.php', 1);
	$contactUrl = emergencyhousePublicUrl('contact.php');
	$captchaEnabled = getDolGlobalInt('MAIN_SECURITY_ENABLECAPTCHA', 0) > 0;
	$captchaReady = $captchaEnabled && function_exists('imagecreate') && function_exists('imagepng');
	$nativeUploadLimits = getMaxFileSizeArray();
	$nativeUploadLimitKb = isset($nativeUploadLimits['maxmin']) ? (int) $nativeUploadLimits['maxmin'] : 0;
	$nativeUploadReady = $nativeUploadLimitKb > 0;
	print '<div class="info">'.$langs->trans('PublicPreviewHelp').'</div>';
	print '<p><a class="button" target="_blank" rel="noopener noreferrer" href="'.dol_escape_htmltag($previewUrl).'">';
	print $langs->trans('OpenPublicPreview').'</a></p>';
	print '<div class="info">'.$langs->trans('PublicBaseUrlConfigurationHelp').'</div>';
	print '<p><a class="button" target="_blank" rel="noopener noreferrer" href="'.dol_escape_htmltag($contactUrl).'">';
	print $langs->trans('OpenPublicContactPage').'</a></p>';
	print '<br>'.load_fiche_titre($langs->trans('PublicContactConfiguration'), '', 'address-card');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('SecurityCheck').'</th><th>'.$langs->trans('Status').'</th></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('NativeCaptcha').'</td><td>';
	print img_picto($langs->trans($captchaReady ? 'Available' : 'Unavailable'), $captchaReady ? 'switch_on' : 'switch_off');
	print ' '.$langs->trans($captchaReady ? 'Available' : 'Unavailable').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ContactAttachments').'</td><td>';
	print img_picto($langs->trans($nativeUploadReady ? 'Available' : 'Unavailable'), $nativeUploadReady ? 'switch_on' : 'switch_off');
	print ' '.$langs->trans($nativeUploadReady ? 'Available' : 'Unavailable');
	if ($nativeUploadReady) {
		print ' — '.((int) $nativeUploadLimitKb).' '.$langs->trans('Kb');
	}
	print '</td></tr></table>';
	print '<div class="info">'.$langs->trans('NativeCaptchaConfigurationInfo').'</div>';
	print '<p><a class="button" href="'.DOL_URL_ROOT.'/admin/security_other.php">'.$langs->trans('ConfigureNativeCaptcha').'</a></p>';
	print '<div class="info">'.$langs->trans('NativeUploadConfigurationInfo').'</div>';
	print '<p><a class="button" href="'.DOL_URL_ROOT.'/admin/security_file.php">'.$langs->trans('ConfigureNativeUploads').'</a></p>';
}
if ($tab === 'security') {
	$encryptionService = new EmergencyHouseEncryptionService();
	$encryptionStatus = $encryptionService->getConfigurationStatus();
	$encryptionKeyName = EmergencyHouseEncryptionService::ENCRYPTION_KEY_NAME;
	$hmacKeyName = EmergencyHouseEncryptionService::HMAC_KEY_NAME;

	print '<br>'.load_fiche_titre($langs->trans('SecurityConfigurationGuide'), '', 'shield-alt');
	print '<div class="info">'.$langs->trans('SecurityConfigurationIntroduction').'</div>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('TechnicalKey').'</th><th>'.$langs->trans('Description').'</th></tr>';
	print '<tr class="oddeven"><td><code>'.dol_escape_htmltag($encryptionKeyName).'</code></td>';
	print '<td>'.$langs->trans('EncryptionKeyPurpose').'</td></tr>';
	print '<tr class="oddeven"><td><code>'.dol_escape_htmltag($hmacKeyName).'</code></td>';
	print '<td>'.$langs->trans('HmacKeyPurpose').'</td></tr>';
	print '</table>';
	print '<div class="opacitymedium">'.$langs->trans('SecurityKeysStorageInfo').'</div>';

	print '<br><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('SecurityCheck').'</th><th>'.$langs->trans('Status').'</th></tr>';
	$securityChecks = array(
		'SodiumExtension' => $encryptionStatus['sodium'],
		'EncryptionManagedKey' => $encryptionStatus['encryption_key'],
		'HmacManagedKey' => $encryptionStatus['hmac_key'],
		'ManagedKeysAreDistinct' => $encryptionStatus['distinct'],
		'EncryptionServiceReady' => $encryptionStatus['available'],
	);
	foreach ($securityChecks as $label => $checkPassed) {
		print '<tr class="oddeven"><td>'.$langs->trans($label).'</td><td>';
		print img_picto($langs->trans($checkPassed ? 'Available' : 'Unavailable'), $checkPassed ? 'tick' : 'error');
		print ' '.$langs->trans($checkPassed ? 'Available' : 'Unavailable').'</td></tr>';
	}
	$sourceTranslationKey = 'SecurityKeySourceNone';
	if ($encryptionStatus['source'] === 'dolibarr') {
		$sourceTranslationKey = 'SecurityKeySourceDolibarr';
	} elseif ($encryptionStatus['source'] === 'environment') {
		$sourceTranslationKey = 'SecurityKeySourceEnvironment';
	}
	print '<tr class="oddeven"><td>'.$langs->trans('SecurityKeySource').'</td>';
	print '<td>'.$langs->trans($sourceTranslationKey).'</td></tr>';
	print '</table>';
	if (!$encryptionStatus['available'] && $encryptionStatus['error'] !== '') {
		print '<div class="warning">'.$langs->trans($encryptionStatus['error']).'</div>';
	}
	if ($encryptionStatus['available']) {
		print '<div class="info">'.$langs->trans('SecurityKeysManagedAutomatically').'</div>';
	} elseif ($encryptionStatus['sodium']) {
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="generate_security_keys">';
		print '<input type="hidden" name="tab" value="security">';
		print '<p class="center"><button class="button button-save" type="submit">';
		print $langs->trans('GenerateAndInstallSecurityKeys').'</button></p>';
		print '</form>';
	}
}

if (isset($settingsByTab[$tab])) {
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	print '<input type="hidden" name="tab" value="'.dol_escape_htmltag($tab).'">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';

	foreach ($settingsByTab[$tab] as $name => $definition) {
		$value = getDolGlobalString($name, $definition['default']);
		if ($name === 'EMERGENCYHOUSE_GEOCODING_ENDPOINT' && trim($value) === '') {
			$value = $definition['default'];
		}
		print '<tr class="oddeven"><td>'.$langs->trans($name).'</td><td>';

		$options = array();
		if ($name === 'EMERGENCYHOUSE_DEFAULT_TIMEZONE') {
			foreach (DateTimeZone::listIdentifiers() as $timezoneIdentifier) {
				$options[$timezoneIdentifier] = $timezoneIdentifier;
			}
		} elseif ($name === 'EMERGENCYHOUSE_DEFAULT_LANGUAGE') {
			$options = array('fr_FR' => $langs->trans('LanguageFrench'), 'en_US' => $langs->trans('LanguageEnglish'));
		} elseif ($name === 'EMERGENCYHOUSE_PUBLIC_REQUEST_VISIBILITY') {
			$options = array('private' => $langs->trans('VisibilityPrivate'), 'public' => $langs->trans('VisibilityPublic'));
		} elseif ($name === 'EMERGENCYHOUSE_GEOCODING_PROVIDER') {
			$options = array(
				'disabled' => $langs->trans('Disabled'),
				'geoplateforme' => $langs->trans('ProviderGeoplateforme'),
			);
		} elseif ($name === 'EMERGENCYHOUSE_SMS_PROVIDER') {
			$options = array('disabled' => $langs->trans('Disabled'));
		} elseif (substr($name, -5) === '_MODE') {
			$options = array('disabled' => $langs->trans('Disabled'), 'enabled' => $langs->trans('Enabled'));
		} elseif ($name === 'EMERGENCYHOUSE_LOG_LEVEL') {
			$options = array(
				'error' => $langs->trans('LogLevelError'),
				'warning' => $langs->trans('LogLevelWarning'),
				'info' => $langs->trans('LogLevelInfo'),
			);
		}

		if ($name === 'EMERGENCYHOUSE_PUBLIC_TERMS_HTML') {
			$editor = new DolEditor(
				$name,
				$value,
				'',
				300,
				'dolibarr_notes',
				'',
				false,
				false,
				isModEnabled('fckeditor'),
				12,
				'100%'
			);
			$editor->Create();
		} elseif ($name === 'EMERGENCYHOUSE_FREE_TEXT') {
			print '<textarea class="flat centpercent" rows="5" name="'.dol_escape_htmltag($name).'">'.dol_escape_htmltag($value).'</textarea>';
		} elseif (!empty($options)) {
			print $form->selectarray($name, $options, $value, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
			print ajax_combobox($name);
		} elseif ($definition['type'] === 'int') {
			print '<input class="flat minwidth100" type="number" min="0" name="'.dol_escape_htmltag($name).'" value="'.((int) $value).'">';
		} elseif ($name === 'EMERGENCYHOUSE_PUBLIC_BASE_URL') {
			print '<input class="flat minwidth500" type="url" inputmode="url" placeholder="https://emergencyhouse.example.org/"';
			print ' name="'.dol_escape_htmltag($name).'" value="'.dol_escape_htmltag($value).'">';
		} elseif ($name === 'EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL') {
			print '<input class="flat minwidth500" type="email" inputmode="email" autocomplete="email"';
			print ' name="'.dol_escape_htmltag($name).'" value="'.dol_escape_htmltag($value).'">';
		} elseif ($name === 'EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE') {
			print '<input class="flat minwidth500" type="tel" inputmode="tel" autocomplete="tel" maxlength="40"';
			print ' name="'.dol_escape_htmltag($name).'" value="'.dol_escape_htmltag($value).'">';
		} else {
			print '<input class="flat minwidth500" type="text" name="'.dol_escape_htmltag($name).'" value="'.dol_escape_htmltag($value).'">';
		}
		if (isset($settingHelpKeys[$name])) {
			print '<div class="opacitymedium small">'.$langs->trans($settingHelpKeys[$name]).'</div>';
		}
		print '</td></tr>';
	}
	print '</table>';
	print '<div class="center"><input class="button button-save" type="submit" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
}

if ($tab === 'general') {
	print '<br>'.load_fiche_titre($langs->trans('NumberingModels'), '', 'hashtag');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Object').'</th><th>'.$langs->trans('Name').'</th>';
	print '<th>'.$langs->trans('Description').'</th><th>'.$langs->trans('Example').'</th><th class="center">'.$langs->trans('Status').'</th></tr>';
	foreach ($numberingConstants as $constant => $numberingDefinition) {
		$modelName = $numberingDefinition['model'];
		dol_include_once('/emergencyhouse/core/modules/emergencyhouse/mod_'.$modelName.'.php');
		$className = 'mod_'.$modelName;
		if (!class_exists($className)) {
			continue;
		}
		/** @var ModeleNumRefEmergencyHouse $numberingModel */
		$numberingModel = new $className();
		$configuredModel = getDolGlobalString($constant, $modelName);
		$active = $configuredModel === $modelName || $configuredModel === 'emergencyhouse_standard';
		print '<tr class="oddeven"><td>'.$langs->trans($numberingDefinition['label']).'</td>';
		print '<td>'.dol_escape_htmltag($numberingModel->name).'</td>';
		print '<td>'.$numberingModel->info($langs).'</td>';
		print '<td>'.dol_escape_htmltag($numberingModel->getExample()).'</td><td class="center">';
		if ($active) {
			print img_picto($langs->trans('Activated'), 'switch_on');
		} else {
			$url = $_SERVER['PHP_SELF'].'?tab=general&action=set_numbering_model&token='.newToken();
			$url .= '&constant='.urlencode($constant).'&value='.urlencode($modelName);
			print '<a class="reposition" href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</a>';
		}
		print '</td></tr>';
	}
	print '</table>';

	$documentModel = new pdf_emergencyhouse_agreement($db);
	$sqlDocumentModel = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'document_model';
	$sqlDocumentModel .= " WHERE nom = 'emergencyhouse_agreement' AND type = 'emergencyhouse'";
	$sqlDocumentModel .= ' AND entity = '.((int) $conf->entity);
	$resqlDocumentModel = $db->query($sqlDocumentModel);
	$documentModelActive = $resqlDocumentModel && $db->num_rows($resqlDocumentModel) > 0;
	$documentModelDefault = getDolGlobalString('EMERGENCYHOUSE_ALLOCATION_DEFAULT_MODEL', 'emergencyhouse_agreement') === 'emergencyhouse_agreement';
	print '<br>'.load_fiche_titre($langs->trans('DocumentModels'), '', 'pdf');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Object').'</th><th>'.$langs->trans('Name').'</th>';
	print '<th>'.$langs->trans('Description').'</th><th class="center">'.$langs->trans('Status').'</th>';
	print '<th class="center">'.$langs->trans('Default').'</th><th class="center">'.$langs->trans('ShortInfo').'</th></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Allocation').'</td><td>'.dol_escape_htmltag($documentModel->name).'</td>';
	print '<td>'.$documentModel->info($langs).'</td><td class="center">';
	$documentAction = $documentModelActive ? 'del_document_model' : 'set_document_model';
	$documentUrl = $_SERVER['PHP_SELF'].'?tab=general&action='.$documentAction.'&token='.newToken();
	$documentUrl .= '&value=emergencyhouse_agreement';
	print '<a class="reposition" href="'.dol_escape_htmltag($documentUrl).'">';
	print img_picto($langs->trans($documentModelActive ? 'Enabled' : 'Disabled'), $documentModelActive ? 'switch_on' : 'switch_off');
	print '</a></td><td class="center">';
	if ($documentModelActive && $documentModelDefault) {
		print img_picto($langs->trans('Default'), 'on');
	} elseif ($documentModelActive) {
		$defaultUrl = $_SERVER['PHP_SELF'].'?tab=general&action=set_default_document_model&token='.newToken();
		$defaultUrl .= '&value=emergencyhouse_agreement';
		print '<a class="reposition" href="'.dol_escape_htmltag($defaultUrl).'">'.img_picto($langs->trans('Disabled'), 'off').'</a>';
	}
	print '</td><td class="center">';
	$tooltip = $langs->trans('Type').': PDF<br>'.$langs->trans('Version').': '.dol_escape_htmltag($documentModel->version);
	$tooltip .= '<br>'.$langs->trans('Path').': core/modules/emergencyhouse/doc/pdf_emergencyhouse_agreement.modules.php';
	print $form->textwithpicto('', $tooltip, 1, 0);
	print '</td></tr></table>';
}

if ($tab === 'portal') {
	print '<br><table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('BinaryOptions').'</th><th>'.$langs->trans('Value').'</th></tr>';
	$binarySettings = array(
		'EMERGENCYHOUSE_PUBLIC_PORTAL_ENABLED',
		'EMERGENCYHOUSE_OSM_TILES_ENABLED',
		'EMERGENCYHOUSE_PHOTOS_ENABLED',
		'EMERGENCYHOUSE_API_ENABLED',
	);
	foreach ($binarySettings as $name) {
		print '<tr class="oddeven"><td>'.$langs->trans($name).'</td><td>'.ajax_constantonoff($name).'</td></tr>';
	}
	print '</table>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
