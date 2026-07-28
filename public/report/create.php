<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/reportservice.class.php');

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$objectType = GETPOST('type', 'aZ09');
$objectId = GETPOSTINT('object');
$action = GETPOST('action', 'aZ09');
$service = new EmergencyHouseReportService($db);
$target = $service->resolveTarget($account, $objectType, $objectId);
if (!is_array($target)) {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('ReportTargetNotFound'), $account, 'account');
	print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('ReportTargetNotFound').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}
$reasons = $service->fetchReasons((int) $account->entity);
$reasons = is_array($reasons) ? $reasons : array();
$errorKey = '';
$createdReport = false;

if ($action === 'create' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'report_create')) {
	if (!emergencyhousePublicConsumeRateLimit(
		$db,
		(int) $account->entity,
		'report_create',
		(string) $account->id.'|'.$emergencyhousePublicIp,
		10,
		86400
	)) {
		$errorKey = 'ErrorRateLimitExceeded';
	} else {
		$createdReport = $service->createPublicReport(
			$account,
			$objectType,
			$objectId,
			GETPOSTINT('reason_id'),
			GETPOSTINT('severity'),
			GETPOST('description', 'restricthtml'),
			emergencyhousePublicTriggerUser($db)
		);
		if (!$createdReport instanceof EmergencyHouseReport) {
			$errorKey = $service->error;
		} else {
			emergencyhousePublicAnalyticsEvent('report_created', true, 'report_form', $account);
		}
	}
} elseif ($action !== '') {
	$errorKey = 'ErrorInvalidCsrfToken';
}

emergencyhousePublicRenderHeader($langs->trans('CreateSafetyReport'), $account, 'account');
print '<section class="eh-shell eh-section eh-narrow"><div class="eh-page-title">';
print '<p class="eh-eyebrow">'.$langs->trans('Safety').'</p><h1>'.$langs->trans('CreateSafetyReport').'</h1>';
print '<p>'.$langs->trans('SafetyReportHelp').'</p></div>';
if ($errorKey !== '') {
	emergencyhousePublicAlert($errorKey, 'error');
}
if ($createdReport instanceof EmergencyHouseReport) {
	print '<div class="eh-alert eh-alert-success"><h2>'.$langs->trans('ReportRecorded').'</h2>';
	print '<p>'.$langs->trans('ReportRecordedHelp', dol_escape_htmltag($createdReport->ref)).'</p></div>';
	print '<p><a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('account/index.php')).'">'.$langs->trans('BackToMyAccount').'</a></p>';
} else {
	print '<div class="eh-alert eh-alert-warning">'.$langs->trans('EmergencyContactReminder').'</div>';
	print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('report/create.php', array('type' => $objectType, 'object' => $objectId))).'" class="eh-form" data-disable-on-submit>';
	print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'report_create');
	print '<input type="hidden" name="action" value="create">';
	print '<div class="eh-field"><label>'.$langs->trans('ReportedElement').'</label><p>'.dol_escape_htmltag($target['label']).'</p></div>';
	print '<div class="eh-field"><label for="reason_id">'.$langs->trans('ReportReason').'</label>';
	print '<select class="eh-select2" id="reason_id" name="reason_id" required><option value="">'.$langs->trans('SelectAnOption').'</option>';
	foreach ($reasons as $reasonId => $translationKey) {
		print '<option value="'.((int) $reasonId).'"'.(GETPOSTINT('reason_id') === (int) $reasonId ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans($translationKey)).'</option>';
	}
	print '</select></div>';
	$severityOptions = array();
	for ($severity = 1; $severity <= 5; $severity++) {
		$severityOptions[$severity] = $langs->trans('SeverityLevel'.$severity);
	}
	print '<div class="eh-field"><label for="severity">'.$langs->trans('Severity').'</label>';
	print emergencyhousePublicSelect('severity', $severityOptions, max(1, min(5, GETPOSTINT('severity') ?: 2)), false, '', ' required');
	print '</div>';
	print '<div class="eh-field"><label for="description">'.$langs->trans('Description').'</label>';
	print '<textarea id="description" name="description" minlength="20" maxlength="5000" required>'.dol_escape_htmltag(GETPOST('description', 'restricthtml')).'</textarea>';
	print '<p class="eh-help">'.$langs->trans('SafetyReportDescriptionHelp').'</p></div>';
	print '<div class="eh-form-actions"><button class="eh-button eh-button-danger" type="submit">'.$langs->trans('SubmitSafetyReport').'</button></div></form>';
}
print '</section>';
emergencyhousePublicRenderFooter();
