<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

$privacyHtml = emergencyhousePublicLocalizedHtml('EMERGENCYHOUSE_PUBLIC_PRIVACY_HTML');
if (!emergencyhousePublicLegalPageIsPublished(
	'EMERGENCYHOUSE_PUBLIC_PRIVACY_ENABLED',
	'EMERGENCYHOUSE_PUBLIC_PRIVACY_HTML'
)) {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('PublicPageNotFound'), $emergencyhousePublicAccount);
	print '<section class="eh-shell eh-section"><div class="eh-alert eh-alert-warning" role="alert">';
	print '<h1>'.$langs->trans('PublicPageNotFound').'</h1>';
	print '</div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}

emergencyhousePublicRenderHeader(
	$langs->trans('PrivacyPolicy'),
	$emergencyhousePublicAccount,
	'',
	true,
	false,
	array(
		'description' => $langs->trans('PublicPrivacyDescription'),
		'canonical' => emergencyhousePublicAbsoluteUrl('privacy.php'),
	)
);
print '<section class="eh-shell eh-section">';
print '<div class="eh-page-title"><h1>'.$langs->trans('PrivacyPolicy').'</h1></div>';
print '<article class="eh-card eh-legal-content">'.dolPrintHTML($privacyHtml).'</article>';
print '</section>';
emergencyhousePublicRenderFooter();
