<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$action = GETPOST('action', 'aZ09');
$errorKey = '';
$successKey = '';

if ($action === 'export' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'account_export')) {
	$profile = $account->getDecryptedProfile();
	$export = array(
		'exported_at' => dol_print_date(dol_now(), 'dayhourrfc'),
		'account' => is_array($profile) ? $profile : array(),
		'verification' => array(
			'email_verified' => (bool) $account->email_verified,
			'phone_level' => (int) $account->phone_verification_level,
			'manual_level' => (int) $account->manual_verification_level,
		),
		'offers' => emergencyhouseAccountExportRows($db, 'offer', (int) $account->entity, (int) $account->id),
		'requests' => emergencyhouseAccountExportRows($db, 'request', (int) $account->entity, (int) $account->id),
	);
	$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if (is_string($json)) {
		header('Content-Type: application/json; charset=UTF-8');
		header('Content-Disposition: attachment; filename="emergencyhouse-personal-data.json"');
		header('X-Content-Type-Options: nosniff');
		print $json;
		exit;
	}
	$errorKey = 'ErrorJsonEncoding';
}

if ($action === 'delete_request' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'account_delete_request')) {
	if ($account->requestDeletion() > 0) {
		$successKey = 'AccountDeletionRequested';
	} else {
		$errorKey = !empty($account->error) ? $account->error : 'ErrorAccountDeletionRequest';
	}
}

emergencyhousePublicRenderHeader($langs->trans('MyPersonalData'), $account, 'account');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('MyPersonalData').'</h1><p>'.$langs->trans('PersonalDataRightsHelp').'</p></div>';
if ($errorKey !== '') emergencyhousePublicAlert($errorKey, 'error');
if ($successKey !== '') emergencyhousePublicAlert($successKey, 'success');
print '<div class="eh-card-grid">';
print '<article class="eh-card"><h2>'.$langs->trans('DownloadMyData').'</h2><p>'.$langs->trans('DownloadMyDataHelp').'</p>';
print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('account/data.php')).'">'.emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'account_export');
print '<input type="hidden" name="action" value="export"><button class="eh-button" type="submit">'.$langs->trans('DownloadJson').'</button></form></article>';
print '<article class="eh-card"><h2>'.$langs->trans('DeleteMyAccount').'</h2><p>'.$langs->trans('DeleteMyAccountHelp').'</p>';
print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('account/data.php')).'">'.emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'account_delete_request');
print '<input type="hidden" name="action" value="delete_request"><button class="eh-button eh-button-danger" type="submit">'.$langs->trans('RequestAccountDeletion').'</button></form></article>';
print '</div></section>';
emergencyhousePublicRenderFooter();

/**
 * Export non-encrypted business fields for one account.
 *
 * @param DoliDB $db Database
 * @param string $type offer or request
 * @param int $entity Entity
 * @param int $accountId Account
 * @return array<int, array<string, int|string|null>>
 */
function emergencyhouseAccountExportRows($db, $type, $entity, $accountId)
{
	$allowed = array(
		'offer' => 'rowid, ref, public_uuid, fk_campaign, zip, town, public_zone, date_start, date_end, capacity_total, capacity_available, title, description_public, status, date_creation, tms',
		'request' => 'rowid, ref, public_uuid, fk_campaign, adults_count, children_infant_count, children_young_count, children_teen_count, person_count, remaining_count, date_start, date_end, desired_zone, title, description_public, visibility, status, date_creation, tms',
	);
	if (!isset($allowed[$type])) {
		return array();
	}
	$sql = 'SELECT '.$allowed[$type].' FROM '.MAIN_DB_PREFIX.'emergencyhouse_'.$type;
	$sql .= ' WHERE entity = '.$entity.' AND fk_account = '.$accountId.' ORDER BY rowid ASC';
	$resql = $db->query($sql);
	$rows = array();
	if (!$resql) {
		return $rows;
	}
	while (is_object($obj = $db->fetch_object($resql))) {
		$row = array();
		foreach (get_object_vars($obj) as $key => $value) {
			if (is_int($value) || is_string($value) || $value === null) {
				$row[$key] = $value;
			}
		}
		$rows[] = $row;
	}
	return $rows;
}
