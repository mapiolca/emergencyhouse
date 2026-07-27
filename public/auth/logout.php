<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$action = GETPOST('action', 'aZ09');
if ($action === 'logout' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'logout')) {
	$emergencyhousePublicAuth->logout();
	header('Location: '.emergencyhousePublicUrl());
	exit;
}

emergencyhousePublicRenderHeader($langs->trans('Logout'), $account, 'logout');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('Logout').'</h1><p>'.$langs->trans('LogoutConfirmation').'</p></div>';
print '<form class="eh-form" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'logout').'<input type="hidden" name="action" value="logout">';
print '<div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('Logout').'</button>';
print '<a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('account/index.php')).'">'.$langs->trans('Cancel').'</a></div></form>';
print '</section>';
emergencyhousePublicRenderFooter();

