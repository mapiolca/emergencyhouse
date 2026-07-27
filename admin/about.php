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
dol_include_once('/emergencyhouse/core/modules/modEmergencyHouse.class.php');

$langs->loadLangs(array('admin', 'emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'configuration', 'write')) {
	accessforbidden();
}

llxHeader('', $langs->trans('About'));
$head = emergencyhouseAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('EmergencyHouseSetup'), -1, 'fontawesome_house-user');
print load_fiche_titre($langs->trans('About'), emergencyhouseAdminLinkBack(), 'fontawesome_house-user');

$moduleDescriptor = new modEmergencyHouse($db);
print '<table class="noborder centpercent">';
$rows = array(
	'ModuleName' => 'Emergency House',
	'Version' => $moduleDescriptor->version,
	'Publisher' => $moduleDescriptor->editor_name,
	'Author' => 'Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>',
	'MinimumDolibarrVersion' => '20.0',
	'MinimumPhpVersion' => '8.0',
	'License' => 'GPL-3.0-or-later',
);
foreach ($rows as $label => $value) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans($label).'</td><td>'.dol_escape_htmltag($value).'</td></tr>';
}
print '<tr class="oddeven"><td>'.$langs->trans('Description').'</td><td>'.$langs->trans('EmergencyHouseModuleDescriptionLong').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MainFeatures').'</td><td>'.$langs->trans('EmergencyHouseMainFeatures').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Dependencies').'</td><td>'.$langs->trans('NoMandatoryDependency').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('UsefulLinks').'</td><td><a href="https://github.com/mapiolca/emergencyhouse" target="_blank" rel="noopener noreferrer">GitHub</a></td></tr>';
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
