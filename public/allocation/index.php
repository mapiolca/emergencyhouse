<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/participantservice.class.php');

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$service = new EmergencyHouseParticipantService($db);
$allocations = $service->fetchAllocations($account, 100, 0);
$allocations = is_array($allocations) ? $allocations : array();

emergencyhousePublicRenderHeader($langs->trans('MyAllocations'), $account, 'account');
print '<section class="eh-shell eh-section"><div class="eh-section-heading"><div>';
print '<p class="eh-eyebrow">'.$langs->trans('Stays').'</p><h1>'.$langs->trans('MyAllocations').'</h1>';
print '<p>'.$langs->trans('MyAllocationsHelp').'</p></div></div>';

if (empty($allocations)) {
	print '<div class="eh-empty"><h2>'.$langs->trans('NoAllocation').'</h2>';
	print '<p>'.$langs->trans('NoAllocationHelp').'</p></div>';
} else {
	print '<div class="eh-list-cards">';
	foreach ($allocations as $allocation) {
		$role = (int) $allocation['offer_account'] === (int) $account->id ? 'host' : 'requester';
		print '<article class="eh-card eh-list-card"><div>';
		print '<p class="eh-eyebrow">'.dol_escape_htmltag((string) $allocation['campaign_label']).'</p>';
		print '<h2><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('allocation/view.php', array('id' => (int) $allocation['rowid']))).'">';
		print dol_escape_htmltag((string) $allocation['ref']).'</a></h2>';
		print '<p>'.$langs->trans('AllocationSummary', (int) $allocation['quantity'], dol_print_date($db->jdate($allocation['date_start']), 'day'));
		if (!empty($allocation['date_end'])) {
			print ' '.$langs->trans('UntilDate', dol_print_date($db->jdate($allocation['date_end']), 'day'));
		}
		print '</p><p class="eh-help">'.$langs->trans($role === 'host' ? 'HostRole' : 'RequesterRole').'</p></div>';
		print emergencyhousePublicWorkflowStatus('allocation', (int) $allocation['status']);
		print '</article>';
	}
	print '</div>';
}
print '</section>';
emergencyhousePublicRenderFooter();
