<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

$termsHtml = trim(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_TERMS_HTML', ''));
if (!emergencyhousePublicHtmlHasContent($termsHtml)) {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('PublicPageNotFound'), $emergencyhousePublicAccount);
	print '<section class="eh-shell eh-section"><div class="eh-alert eh-alert-warning" role="alert">';
	print '<h1>'.$langs->trans('PublicPageNotFound').'</h1>';
	print '</div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}

emergencyhousePublicRenderHeader($langs->trans('TermsOfUse'), $emergencyhousePublicAccount);
print '<section class="eh-shell eh-section">';
print '<div class="eh-page-title"><h1>'.$langs->trans('TermsOfUse').'</h1></div>';
print '<article class="eh-card eh-legal-content">'.dolPrintHTML($termsHtml).'</article>';
print '</section>';
emergencyhousePublicRenderFooter();
