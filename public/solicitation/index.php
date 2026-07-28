<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/participantservice.class.php');

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$page = max(0, GETPOSTINT('page'));
$limit = 50;
$participantService = new EmergencyHouseParticipantService($db);
$solicitations = $participantService->fetchSolicitations($account, $limit, $page * $limit);
$solicitations = is_array($solicitations) ? $solicitations : array();

emergencyhousePublicRenderHeader($langs->trans('MySolicitations'), $account, 'account');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('MySpace').'</p>';
print '<h1>'.$langs->trans('MySolicitations').'</h1><p>'.$langs->trans('MySolicitationsIntroduction').'</p></div>';
if (empty($solicitations)) {
	print '<div class="eh-empty"><h2>'.$langs->trans('NoSolicitation').'</h2><p>'.$langs->trans('NoSolicitationHelp').'</p></div>';
} else {
	print '<div class="eh-list-cards">';
	foreach ($solicitations as $solicitation) {
		$isOutgoing = !empty($solicitation['fk_initiator_account'])
			&& (int) $solicitation['fk_initiator_account'] === (int) $account->id;
		$url = emergencyhousePublicUrl('solicitation/view.php', array('id' => (int) $solicitation['rowid']));
		print '<article class="eh-card eh-list-card"><div><div class="eh-card-meta">';
		print emergencyhousePublicWorkflowStatus('solicitation', (int) $solicitation['status']);
		print '<span>'.$langs->trans($isOutgoing ? 'OutgoingSolicitation' : 'IncomingSolicitation').'</span>';
		print '<span>'.dol_escape_htmltag((string) $solicitation['campaign_label']).'</span></div>';
		print '<h2><a class="eh-card-link" href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag((string) $solicitation['offer_title']).'</a></h2>';
		print '<p>'.$langs->trans('LinkedRequest').': '.dol_escape_htmltag((string) $solicitation['request_title']).'</p></div>';
		print '<div><a class="eh-button eh-button-secondary eh-button-small" href="'.dol_escape_htmltag($url).'">'.$langs->trans('OpenConversation').'</a></div></article>';
	}
	print '</div>';
	if (count($solicitations) === $limit) {
		print '<p class="eh-pagination"><a class="eh-button eh-button-secondary" href="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/index.php', array('page' => $page + 1))).'">'.$langs->trans('Next').'</a></p>';
	}
}
print '</section>';
emergencyhousePublicRenderFooter();
