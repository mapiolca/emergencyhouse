<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$profile = $account->getDecryptedProfile();
if (!is_array($profile)) {
	http_response_code(500);
	$profile = array('firstname' => '', 'lastname' => '', 'email' => '', 'phone' => '');
}

emergencyhousePublicRenderHeader($langs->trans('MyProfile'), $account, 'account');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('MyProfile').'</h1><p>'.$langs->trans('ProfileDataProtectionHelp').'</p></div>';
print '<div class="eh-form"><dl>';
print '<dt>'.$langs->trans('Firstname').'</dt><dd>'.dol_escape_htmltag($profile['firstname']).'</dd>';
print '<dt>'.$langs->trans('Lastname').'</dt><dd>'.dol_escape_htmltag($profile['lastname']).'</dd>';
print '<dt>'.$langs->trans('Email').'</dt><dd>'.dol_escape_htmltag($profile['email']).'</dd>';
print '<dt>'.$langs->trans('Phone').'</dt><dd>'.dol_escape_htmltag($profile['phone']).'</dd>';
print '<dt>'.$langs->trans('VerificationStatus').'</dt><dd><span class="eh-badge">'.($account->email_verified ? $langs->trans('EmailVerified') : $langs->trans('EmailNotVerified')).'</span></dd>';
print '</dl><div class="eh-actions"><a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('account/data.php')).'">'.$langs->trans('ManageMyPersonalData').'</a></div></div>';
print '</section>';
emergencyhousePublicRenderFooter();

