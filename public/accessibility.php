<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

emergencyhousePublicRenderHeader($langs->trans('Accessibility'), $emergencyhousePublicAccount);
print '<section class="eh-shell eh-section"><div class="eh-page-title"><h1>'.$langs->trans('Accessibility').'</h1><p>'.$langs->trans('AccessibilityStatementIntro').'</p></div>';
print '<div class="eh-card"><h2>'.$langs->trans('AccessibilityCommitments').'</h2><ul>';
print '<li>'.$langs->trans('AccessibilityKeyboard').'</li>';
print '<li>'.$langs->trans('AccessibilityZoom').'</li>';
print '<li>'.$langs->trans('AccessibilityMobile').'</li>';
print '<li>'.$langs->trans('AccessibilityMapFallback').'</li>';
print '</ul><h2>'.$langs->trans('ReportAccessibilityIssue').'</h2>';
print '<p>'.$langs->trans('ReportAccessibilityIssueHelp').'</p></div></section>';
emergencyhousePublicRenderFooter();

