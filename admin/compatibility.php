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
dol_include_once('/emergencyhouse/class/emergencyhousecompatibility.class.php');

$langs->loadLangs(array('admin', 'emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'configuration', 'write')) {
	accessforbidden();
}

llxHeader('', $langs->trans('Compatibility'));
$head = emergencyhouseAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('EmergencyHouseSetup'), -1, 'emergencyhouse@emergencyhouse');
print load_fiche_titre($langs->trans('Compatibility'), emergencyhouseAdminLinkBack(), 'technic');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Information').'</th><th>'.$langs->trans('Value').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DetectedDolibarrVersion').'</td><td>'.dol_escape_htmltag(DOL_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DetectedPhpVersion').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumDolibarrVersion').'</td><td>20.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MinimumPhpVersion').'</td><td>8.0</td></tr>';
print '</table><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Feature').'</th><th>'.$langs->trans('Description').'</th><th>'.$langs->trans('MinimumVersions').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Reason').'</th></tr>';
foreach (EmergencyHouseCompatibility::getCompatibilityFeatures() as $feature) {
	$status = $feature['available'] ? $langs->trans('Available') : $langs->trans('Unavailable');
	$badge = $feature['available'] ? '4' : '8';
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>'.$langs->trans($feature['description']).'</td>';
	print '<td>Dolibarr '.dol_escape_htmltag($feature['min_dolibarr']).' / PHP '.dol_escape_htmltag($feature['min_php']).'</td>';
	print '<td><span class="badge badge-status'.$badge.'">'.$status.'</span></td>';
	print '<td>'.($feature['available'] ? '' : $langs->trans($feature['reason'])).'</td>';
	print '</tr>';
}
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
