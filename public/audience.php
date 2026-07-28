<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

$action = GETPOST('action', 'aZ09');
if ($action === 'save' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$measurementAllowed = GETPOSTINT('audience_measurement_allowed') > 0;
	$emergencyhousePublicAnalytics->setOptOut(!$measurementAllowed);
	header('Location: '.emergencyhousePublicUrl('audience.php', array('saved' => 1)));
	exit;
}

$enabled = getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_ENABLED', 0) === 1;
$optedOut = isset($_COOKIE[EmergencyHousePublicAnalyticsService::OPTOUT_COOKIE])
	&& is_string($_COOKIE[EmergencyHousePublicAnalyticsService::OPTOUT_COOKIE])
	&& hash_equals('1', $_COOKIE[EmergencyHousePublicAnalyticsService::OPTOUT_COOKIE]);

emergencyhousePublicRenderHeader($langs->trans('AudienceMeasurement'), $emergencyhousePublicAccount);
print '<section class="eh-shell eh-section">';
print '<div class="eh-page-title"><h1>'.$langs->trans('AudienceMeasurement').'</h1>';
print '<p>'.$langs->trans('AudienceMeasurementDescription').'</p></div>';
if (GETPOSTINT('saved') > 0) {
	emergencyhousePublicAlert('AudiencePreferenceSaved', 'success');
}
print '<div class="eh-card">';
print '<h2>'.$langs->trans('AudiencePrivacyTitle').'</h2>';
print '<p>'.$langs->trans('AudiencePrivacyDescription').'</p>';
print '<ul><li>'.$langs->trans('AudiencePrivacyNoPersonalData').'</li>';
print '<li>'.$langs->trans('AudiencePrivacyFirstParty').'</li>';
print '<li>'.$langs->trans(
	'AudiencePrivacyRetention',
	max(7, getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_DETAIL_RETENTION_DAYS', 90)),
	max(1, getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_AGGREGATE_RETENTION_MONTHS', 25))
).'</li></ul>';
print '<p><strong>'.$langs->trans('Status').': </strong>';
if (!$enabled) {
	print $langs->trans('AudienceMeasurementDisabled');
} elseif ($optedOut) {
	print $langs->trans('AudienceMeasurementRefused');
} else {
	print $langs->trans('AudienceMeasurementAccepted');
}
print '</p>';
if ($enabled) {
	print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('audience.php')).'" class="eh-form">';
	print emergencyhousePublicCsrfFields();
	print '<input type="hidden" name="action" value="save">';
	print '<label class="eh-switch"><span>'.$langs->trans('AllowAudienceMeasurement').'</span>';
	print '<input type="checkbox" role="switch" name="audience_measurement_allowed" value="1"'.(!$optedOut ? ' checked' : '').'></label>';
	print '<div class="eh-form-actions"><button type="submit" class="eh-button">'.$langs->trans('Save').'</button></div>';
	print '</form>';
}
print '</div></section>';
emergencyhousePublicRenderFooter();
