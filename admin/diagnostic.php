<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = include '../../../main.inc.php';
if (!$res && file_exists('../../../../main.inc.php')) $res = include '../../../../main.inc.php';
if (!$res) {
	http_response_code(500);
	exit;
}

dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');

$langs->loadLangs(array('admin', 'emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'configuration', 'write')) {
	accessforbidden();
}

$encryptionService = new EmergencyHouseEncryptionService();
$encryptionStatus = $encryptionService->getConfigurationStatus();
$checks = array(
	'DiagnosticDolibarrVersion' => version_compare(DOL_VERSION, '20.0.0', '>='),
	'DiagnosticPhpVersion' => version_compare(PHP_VERSION, '8.0.0', '>='),
	'DiagnosticSodium' => $encryptionStatus['sodium'],
	'DiagnosticEncryptionKey' => $encryptionStatus['encryption_key'],
	'DiagnosticHmacKey' => $encryptionStatus['hmac_key'],
	'EnvironmentKeysAreDistinct' => $encryptionStatus['distinct'],
	'EncryptionServiceReady' => $encryptionStatus['available'],
	'DiagnosticHttps' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
	'DiagnosticCronModule' => isModEnabled('cron'),
	'DiagnosticNotificationsModule' => isModEnabled('notification'),
);

llxHeader('', $langs->trans('Diagnostic'));
$head = emergencyhouseAdminPrepareHead();
print dol_get_fiche_head($head, 'diagnostic', $langs->trans('EmergencyHouseSetup'), -1, 'fontawesome_house-user');
print load_fiche_titre($langs->trans('Diagnostic'), emergencyhouseAdminLinkBack(), 'technic');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Check').'</th><th>'.$langs->trans('Status').'</th></tr>';
foreach ($checks as $label => $ok) {
	print '<tr class="oddeven"><td>'.$langs->trans($label).'</td><td>';
	print $ok
		? '<span class="badge badge-status4">'.$langs->trans('Available').'</span>'
		: '<span class="badge badge-status8">'.$langs->trans('Unavailable').'</span>';
	print '</td></tr>';
}
print '</table>';
print '<div class="info">'.$langs->trans('DiagnosticSecretsAreNeverDisplayed').'</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
