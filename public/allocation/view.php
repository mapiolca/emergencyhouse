<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/participantservice.class.php');
dol_include_once('/emergencyhouse/class/sensitivedataservice.class.php');

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$service = new EmergencyHouseParticipantService($db);
$context = $service->fetchAllocation($account, $id);
if (!is_array($context)) {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('AllocationNotFound'), $account, 'account');
	print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('AllocationNotFound').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}
/** @var EmergencyHouseAllocation $allocation */
$allocation = $context['allocation'];
$role = $context['role'];
$errorKey = '';
$successKey = '';
$revealedAddress = false;
$triggerUser = emergencyhousePublicTriggerUser($db);

if ($action !== '' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'allocation_action')) {
	if ($action === 'confirm') {
		if ($service->confirmAllocation($account, $allocation, $triggerUser) >= 0) {
			$successKey = 'AllocationConfirmed';
		} else {
			$errorKey = $service->error;
		}
	} elseif ($action === 'cancel') {
		$reasonCode = GETPOST('reason_code', 'alpha');
		if ($service->cancelAllocation($account, $allocation, $reasonCode, $triggerUser) > 0) {
			$successKey = 'AllocationCancelled';
		} else {
			$errorKey = $service->error;
		}
	} elseif ($action === 'reveal_address') {
		$sensitive = new EmergencyHouseSensitiveDataService($db);
		$revealedAddress = $sensitive->revealAddressForAllocation($allocation, $account);
		if (!is_string($revealedAddress)) {
			$errorKey = $sensitive->error;
		}
	} else {
		$errorKey = 'ErrorInvalidAction';
	}
	if ($successKey !== '') {
		$context = $service->fetchAllocation($account, $id);
		if (is_array($context)) {
			$allocation = $context['allocation'];
			$role = $context['role'];
		}
	}
} elseif ($action !== '') {
	$errorKey = 'ErrorInvalidCsrfToken';
}

$offer = new EmergencyHouseOffer($db);
$request = new EmergencyHouseRequest($db);
$offerLoaded = $offer->fetch((int) $allocation->fk_offer) > 0;
$requestLoaded = $request->fetch((int) $allocation->fk_request) > 0;
$cancellationReasons = $service->fetchReasonDictionary('cancellation', (int) $account->entity);
$cancellationReasons = is_array($cancellationReasons) ? $cancellationReasons : array();
$sideConfirmed = $role === 'host' ? !empty($allocation->host_confirmed) : !empty($allocation->requester_confirmed);
$canConfirm = !$sideConfirmed && in_array(
	(int) $allocation->status,
	array(EmergencyHouseAllocation::STATUS_PROPOSED, EmergencyHouseAllocation::STATUS_CONFIRMED),
	true
);
$canCancel = in_array(
	(int) $allocation->status,
	array(
		EmergencyHouseAllocation::STATUS_PROPOSED,
		EmergencyHouseAllocation::STATUS_CONFIRMED,
		EmergencyHouseAllocation::STATUS_ACTIVE,
		EmergencyHouseAllocation::STATUS_INCIDENT,
	),
	true
);

emergencyhousePublicRenderHeader($langs->trans('Allocation').' '.$allocation->ref, $account, 'account');
print '<section class="eh-shell eh-section"><div class="eh-section-heading"><div>';
print '<p class="eh-eyebrow">'.$langs->trans('StayTracking').'</p><h1>'.$langs->trans('Allocation').' '.dol_escape_htmltag($allocation->ref).'</h1>';
print '<p>'.$langs->trans('AllocationParticipantHelp').'</p></div>';
print emergencyhousePublicWorkflowStatus('allocation', (int) $allocation->status).'</div>';
if ($errorKey !== '') {
	emergencyhousePublicAlert($errorKey, 'error');
}
if ($successKey !== '') {
	emergencyhousePublicAlert($successKey, 'success');
}

print '<div class="eh-dashboard-grid"><article class="eh-card"><h2>'.$langs->trans('StayDetails').'</h2>';
print '<dl class="eh-description-list">';
print '<div><dt>'.$langs->trans('YourRole').'</dt><dd>'.$langs->trans($role === 'host' ? 'HostRole' : 'RequesterRole').'</dd></div>';
print '<div><dt>'.$langs->trans('Offer').'</dt><dd>'.($offerLoaded ? dol_escape_htmltag($offer->title) : $langs->trans('Unavailable')).'</dd></div>';
print '<div><dt>'.$langs->trans('Request').'</dt><dd>'.($requestLoaded ? dol_escape_htmltag($request->title) : $langs->trans('Unavailable')).'</dd></div>';
print '<div><dt>'.$langs->trans('PeopleCount').'</dt><dd>'.((int) $allocation->quantity).'</dd></div>';
print '<div><dt>'.$langs->trans('DateStart').'</dt><dd>'.dol_print_date((int) $allocation->date_start, 'day').'</dd></div>';
print '<div><dt>'.$langs->trans('DateEnd').'</dt><dd>'.(!empty($allocation->date_end) ? dol_print_date((int) $allocation->date_end, 'day') : $langs->trans('Unknown')).'</dd></div>';
print '<div><dt>'.$langs->trans('HostConfirmation').'</dt><dd>'.$langs->trans(!empty($allocation->host_confirmed) ? 'Confirmed' : 'AwaitingConfirmation').'</dd></div>';
print '<div><dt>'.$langs->trans('RequesterConfirmation').'</dt><dd>'.$langs->trans(!empty($allocation->requester_confirmed) ? 'Confirmed' : 'AwaitingConfirmation').'</dd></div>';
print '</dl>';
if (!empty($allocation->fk_solicitation)) {
	print '<p><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/view.php', array('id' => (int) $allocation->fk_solicitation))).'">'.$langs->trans('OpenProtectedConversation').'</a></p>';
}
print '</article><aside class="eh-card"><h2>'.$langs->trans('StayActions').'</h2>';
if ($canConfirm) {
	print emergencyhouseAllocationSimpleForm($emergencyhousePublicAuth, $id, 'confirm', 'ConfirmAllocation');
} elseif ($sideConfirmed) {
	print '<p class="eh-alert eh-alert-success">'.$langs->trans('YourConfirmationRecorded').'</p>';
}
if ($role === 'requester' && !empty($allocation->address_share_authorized)
	&& in_array((int) $allocation->status, array(EmergencyHouseAllocation::STATUS_CONFIRMED, EmergencyHouseAllocation::STATUS_ACTIVE, EmergencyHouseAllocation::STATUS_INCIDENT), true)) {
	print emergencyhouseAllocationSimpleForm($emergencyhousePublicAuth, $id, 'reveal_address', 'RevealExactAddress');
	if (is_string($revealedAddress)) {
		print '<div class="eh-alert eh-alert-success">'.nl2br(dol_escape_htmltag($revealedAddress)).'</div>';
	}
}
if ($canCancel) {
	print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('allocation/view.php', array('id' => $id))).'" class="eh-inline-form" data-disable-on-submit>';
	print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'allocation_action');
	print '<input type="hidden" name="action" value="cancel">';
	print '<div class="eh-field"><label for="reason_code">'.$langs->trans('CancellationReason').'</label>';
	print '<select class="eh-select2" id="reason_code" name="reason_code" required><option value="">'.$langs->trans('SelectAnOption').'</option>';
	foreach ($cancellationReasons as $code => $translationKey) {
		print '<option value="'.dol_escape_htmltag($code).'">'.dol_escape_htmltag($langs->trans($translationKey)).'</option>';
	}
	print '</select></div><button class="eh-button eh-button-danger eh-button-small" type="submit">'.$langs->trans('CancelAllocation').'</button></form>';
}
print '</aside></div>';
print '<div class="eh-actions"><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('report/create.php', array('type' => 'allocation', 'object' => $id))).'">'.$langs->trans('ReportStayIncident').'</a></div>';
print '</section>';
emergencyhousePublicRenderFooter();

/**
 * Render a protected allocation action.
 *
 * @param EmergencyHousePublicAuthService $auth Auth
 * @param int $id Allocation
 * @param string $action Action
 * @param string $labelKey Label
 * @return string
 */
function emergencyhouseAllocationSimpleForm($auth, $id, $action, $labelKey)
{
	global $langs;

	$html = '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('allocation/view.php', array('id' => $id))).'" class="eh-inline-form" data-disable-on-submit>';
	$html .= emergencyhousePublicCsrfFields($auth, 'allocation_action');
	$html .= '<input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';
	$html .= '<button class="eh-button eh-button-small" type="submit">'.$langs->trans($labelKey).'</button></form>';
	return $html;
}
