<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$verified = GETPOSTINT('verified') > 0;

$sql = 'SELECT';
$sql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer WHERE entity = '.((int) $account->entity).' AND fk_account = '.((int) $account->id).' AND status NOT IN (5,6)) AS offers,';
$sql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'emergencyhouse_request WHERE entity = '.((int) $account->entity).' AND fk_account = '.((int) $account->id).' AND status <> 6) AS requests,';
$sql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'emergencyhouse_solicitation AS s';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o ON o.rowid = s.fk_offer AND o.entity = s.entity';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r ON r.rowid = s.fk_request AND r.entity = s.entity';
$sql .= ' WHERE s.entity = '.((int) $account->entity).' AND (o.fk_account = '.((int) $account->id).' OR r.fk_account = '.((int) $account->id).') AND s.status IN (0,1)) AS solicitations,';
$sql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'emergencyhouse_allocation AS a';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o2 ON o2.rowid = a.fk_offer AND o2.entity = a.entity';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r2 ON r2.rowid = a.fk_request AND r2.entity = a.entity';
$sql .= ' WHERE a.entity = '.((int) $account->entity).' AND (o2.fk_account = '.((int) $account->id).' OR r2.fk_account = '.((int) $account->id).') AND a.status IN (1,2,3,4,5)) AS allocations';
$resql = $db->query($sql);
$stats = $resql ? $db->fetch_object($resql) : false;
$profile = $account->getDecryptedProfile();

emergencyhousePublicRenderHeader($langs->trans('MySpace'), $account, 'account');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('PublicAccount').'</p>';
print '<h1>'.$langs->trans('WelcomeFirstname', is_array($profile) ? $profile['firstname'] : '').'</h1><p>'.$langs->trans('DashboardIntroduction').'</p></div>';
if ($verified) emergencyhousePublicAlert('EmailVerificationSuccess', 'success');
if (is_object($stats)) {
	print '<div class="eh-stat-grid">';
	print '<a class="eh-stat eh-card-link" href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/index.php', array('mine' => 1))).'"><strong>'.((int) $stats->offers).'</strong><span>'.$langs->trans('MyOffers').'</span></a>';
	print '<a class="eh-stat eh-card-link" href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/index.php', array('mine' => 1))).'"><strong>'.((int) $stats->requests).'</strong><span>'.$langs->trans('MyRequests').'</span></a>';
	print '<a class="eh-stat eh-card-link" href="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/index.php')).'"><strong>'.((int) $stats->solicitations).'</strong><span>'.$langs->trans('OpenSolicitations').'</span></a>';
	print '<a class="eh-stat eh-card-link" href="'.dol_escape_htmltag(emergencyhousePublicUrl('allocation/index.php')).'"><strong>'.((int) $stats->allocations).'</strong><span>'.$langs->trans('CurrentStays').'</span></a>';
	print '</div>';
}
print '<div class="eh-dashboard-grid eh-section-tight"><div class="eh-card"><h2>'.$langs->trans('QuickActions').'</h2><div class="eh-actions">';
print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl('request/edit.php')).'">'.$langs->trans('CreateRequest').'</a>';
print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('offer/edit.php')).'">'.$langs->trans('CreateOffer').'</a>';
print '</div></div>';
print '<aside class="eh-card"><h2>'.$langs->trans('AccountSettings').'</h2><ul class="eh-list">';
print '<li><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('account/profile.php')).'">'.$langs->trans('MyProfile').'</a></li>';
print '<li><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('account/data.php')).'">'.$langs->trans('MyPersonalData').'</a></li>';
print '<li><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('auth/logout.php')).'">'.$langs->trans('Logout').'</a></li>';
print '</ul></aside></div></section>';
emergencyhousePublicRenderFooter();

