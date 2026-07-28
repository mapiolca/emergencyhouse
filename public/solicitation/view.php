<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');
dol_include_once('/emergencyhouse/class/messageservice.class.php');
dol_include_once('/emergencyhouse/class/notificationservice.class.php');
dol_include_once('/emergencyhouse/class/participantservice.class.php');
dol_include_once('/emergencyhouse/class/sensitivedataservice.class.php');

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$participantService = new EmergencyHouseParticipantService($db);
$context = $participantService->fetchSolicitation($account, $id);
if (!is_array($context)) {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('SolicitationNotFound'), $account, 'account');
	print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('SolicitationNotFound').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}
/** @var EmergencyHouseSolicitation $solicitation */
$solicitation = $context['solicitation'];
$role = $context['role'];
$messageService = new EmergencyHouseMessageService($db);
$sensitiveService = new EmergencyHouseSensitiveDataService($db);
$revealedContact = false;
$revealedAddress = false;
$errorKey = '';
$successKey = GETPOSTINT('created') > 0 ? 'SolicitationCreated' : '';
$triggerUser = emergencyhousePublicTriggerUser($db);

if ($action !== '' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'solicitation_action')) {
	if ($action === 'send_message') {
		$body = trim(GETPOST('message', 'restricthtml'));
		if (!emergencyhousePublicConsumeRateLimit(
			$db,
			(int) $account->entity,
			'message_send',
			(string) $account->id.'|'.$emergencyhousePublicIp,
			60,
			3600
		)) {
			$errorKey = 'ErrorRateLimitExceeded';
		} else {
			$messageId = $messageService->createMessage($solicitation, (int) $account->id, null, $body);
			if ($messageId > 0) {
				$notificationService = new EmergencyHouseNotificationService($db);
				if ($notificationService->queueSolicitationParticipants(
					$solicitation,
					(int) $account->id,
					'message_created',
					'message_created',
					'message-'.$messageId
				) < 0) {
					dol_syslog(__FILE__.': '.$notificationService->error, LOG_WARNING);
				}
			$successKey = 'MessageSent';
			} else {
				$errorKey = $messageService->error;
			}
		}
	} elseif (in_array($action, array('accept', 'refuse', 'cancel'), true)) {
		$reasonCode = GETPOST('reason_code', 'alpha');
		$result = $participantService->respond($account, $solicitation, $action, $reasonCode, $triggerUser);
		if ($result >= 0) {
			$successKey = 'SolicitationUpdated';
			$context = $participantService->fetchSolicitation($account, $id);
			if (is_array($context)) {
				$solicitation = $context['solicitation'];
			}
		} else {
			$errorKey = $participantService->error;
		}
	} elseif ($action === 'address_authorization') {
		$authorized = GETPOSTINT('authorized') > 0;
		if ($participantService->setAddressAuthorization($account, $solicitation, $authorized, $triggerUser) > 0) {
			$successKey = $authorized ? 'AddressSharingAuthorized' : 'AddressSharingWithdrawn';
			$context = $participantService->fetchSolicitation($account, $id);
			if (is_array($context)) {
				$solicitation = $context['solicitation'];
			}
		} else {
			$errorKey = $participantService->error;
		}
	} elseif ($action === 'reveal_contact') {
		$revealedContact = $sensitiveService->revealContactForParticipant($solicitation, $account);
		if (!is_array($revealedContact)) {
			$errorKey = $sensitiveService->error;
		}
	} elseif ($action === 'reveal_address') {
		$revealedAddress = $sensitiveService->revealAddressForParticipant($solicitation, $account);
		if (!is_string($revealedAddress)) {
			$errorKey = $sensitiveService->error;
		}
	} elseif ($action === 'mark_read') {
		if ($participantService->markRead($account, $solicitation, $triggerUser) >= 0) {
			$successKey = 'SolicitationMarkedRead';
			$context = $participantService->fetchSolicitation($account, $id);
			if (is_array($context)) {
				$solicitation = $context['solicitation'];
			}
		} else {
			$errorKey = $participantService->error;
		}
	}
} elseif ($action !== '') {
	$errorKey = 'ErrorInvalidCsrfToken';
}

$offer = new EmergencyHouseOffer($db);
$request = new EmergencyHouseRequest($db);
$offerLoaded = $offer->fetch((int) $solicitation->fk_offer) > 0;
$requestLoaded = $request->fetch((int) $solicitation->fk_request) > 0;
$messages = $messageService->fetchMessages($solicitation, (int) $account->id, false, 250);
$messages = is_array($messages) ? $messages : array();
$isInitiator = !empty($solicitation->fk_initiator_account)
	&& (int) $solicitation->fk_initiator_account === (int) $account->id;
$refusalReasons = $participantService->fetchReasonDictionary('refusal', (int) $account->entity);
$cancellationReasons = $participantService->fetchReasonDictionary('cancellation', (int) $account->entity);
$refusalReasons = is_array($refusalReasons) ? $refusalReasons : array();
$cancellationReasons = is_array($cancellationReasons) ? $cancellationReasons : array();

emergencyhousePublicRenderHeader($langs->trans('Solicitation').' '.$solicitation->ref, $account, 'account');
print '<section class="eh-shell eh-section"><div class="eh-section-heading"><div><p class="eh-eyebrow">'.$langs->trans('ProtectedConversation').'</p>';
print '<h1>'.$langs->trans('Solicitation').' '.dol_escape_htmltag($solicitation->ref).'</h1><p>'.$langs->trans('SolicitationPrivacyReminder').'</p></div>';
print emergencyhousePublicWorkflowStatus('solicitation', (int) $solicitation->status).'</div>';
if ($errorKey !== '') {
	emergencyhousePublicAlert($errorKey, 'error');
}
if ($successKey !== '') {
	emergencyhousePublicAlert($successKey, 'success');
}

print '<div class="eh-dashboard-grid"><div>';
print '<article class="eh-card"><h2>'.$langs->trans('ConversationContext').'</h2><dl class="eh-description-list">';
print '<div><dt>'.$langs->trans('Offer').'</dt><dd>'.($offerLoaded ? dol_escape_htmltag($offer->title) : $langs->trans('Unavailable')).'</dd></div>';
print '<div><dt>'.$langs->trans('Request').'</dt><dd>'.($requestLoaded ? dol_escape_htmltag($request->title) : $langs->trans('Unavailable')).'</dd></div>';
print '<div><dt>'.$langs->trans('YourRole').'</dt><dd>'.$langs->trans($role === 'host' ? 'HostRole' : 'RequesterRole').'</dd></div>';
print '<div><dt>'.$langs->trans('SolicitationDirection').'</dt><dd>'.$langs->trans($isInitiator ? 'OutgoingSolicitation' : 'IncomingSolicitation').'</dd></div>';
print '</dl></article>';

print '<section class="eh-section-tight"><h2>'.$langs->trans('Messages').'</h2>';
if (empty($messages)) {
	print '<div class="eh-empty">'.$langs->trans('NoMessage').'</div>';
} else {
	print '<div class="eh-message-thread" aria-live="polite">';
	foreach ($messages as $message) {
		$isOwn = !empty($message['fk_author_account']) && (int) $message['fk_author_account'] === (int) $account->id;
		print '<article class="eh-message'.($isOwn ? ' eh-message-own' : '').'">';
		print '<p>'.nl2br(dol_escape_htmltag((string) $message['body'])).'</p>';
		print '<small>'.$langs->trans($isOwn ? 'SentByYouAt' : 'ReceivedAt', dol_print_date((int) $message['date_creation'], 'dayhour')).'</small>';
		print '</article>';
	}
	print '</div>';
}
if (in_array((int) $solicitation->status, array(EmergencyHouseSolicitation::STATUS_PENDING, EmergencyHouseSolicitation::STATUS_ACCEPTED), true)) {
	print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/view.php', array('id' => $id))).'" class="eh-form eh-section-tight" data-disable-on-submit>';
	print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'solicitation_action');
	print '<input type="hidden" name="action" value="send_message"><div class="eh-field"><label for="message">'.$langs->trans('Reply').'</label>';
	print '<textarea id="message" name="message" required maxlength="'.max(200, getDolGlobalInt('EMERGENCYHOUSE_MESSAGE_MAX_LENGTH', 4000)).'"></textarea></div>';
	print '<div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('SendMessage').'</button></div></form>';
}
print '</section></div><aside>';

if ((int) $solicitation->status === EmergencyHouseSolicitation::STATUS_PENDING) {
	print '<div class="eh-card"><h2>'.$langs->trans('RespondToSolicitation').'</h2>';
	if (!$isInitiator) {
		print emergencyhouseSolicitationActionForm($emergencyhousePublicAuth, $id, 'accept', 'AcceptSolicitation', array());
		print emergencyhouseSolicitationActionForm($emergencyhousePublicAuth, $id, 'refuse', 'RefuseSolicitation', $refusalReasons);
	} else {
		print emergencyhouseSolicitationActionForm($emergencyhousePublicAuth, $id, 'cancel', 'CancelSolicitation', $cancellationReasons);
	}
	if (!$isInitiator && empty($solicitation->date_read)) {
		print emergencyhouseSolicitationActionForm($emergencyhousePublicAuth, $id, 'mark_read', 'MarkAsRead', array());
	}
	print '</div>';
} elseif ((int) $solicitation->status === EmergencyHouseSolicitation::STATUS_ACCEPTED) {
	print '<div class="eh-card"><h2>'.$langs->trans('SharedContactDetails').'</h2><p>'.$langs->trans('MutualConsentGranted').'</p>';
	print emergencyhouseSolicitationActionForm($emergencyhousePublicAuth, $id, 'reveal_contact', 'RevealContactDetails', array());
	if (is_array($revealedContact)) {
		print '<div class="eh-alert eh-alert-success"><strong>'.dol_escape_htmltag($revealedContact['firstname'].' '.$revealedContact['lastname']).'</strong>';
		print '<br><a href="mailto:'.dol_escape_htmltag($revealedContact['email']).'">'.dol_escape_htmltag($revealedContact['email']).'</a>';
		if ($revealedContact['phone'] !== '') {
			print '<br><a href="tel:'.dol_escape_htmltag($revealedContact['phone']).'">'.dol_escape_htmltag($revealedContact['phone']).'</a>';
		}
		print '</div>';
	}
	if ($role === 'host') {
		print '<h3>'.$langs->trans('ExactAddressSharing').'</h3><p>'.$langs->trans('ExactAddressSharingHostHelp').'</p>';
		print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/view.php', array('id' => $id))).'" data-disable-on-submit>';
		print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'solicitation_action');
		print '<input type="hidden" name="action" value="address_authorization">';
		print '<input type="hidden" name="authorized" value="'.(!empty($solicitation->address_share_authorized) ? '0' : '1').'">';
		print '<button class="eh-button eh-button-secondary eh-button-small" type="submit">'.$langs->trans(!empty($solicitation->address_share_authorized) ? 'WithdrawAddressSharing' : 'AuthorizeAddressSharing').'</button></form>';
	} elseif (!empty($solicitation->address_share_authorized)) {
		print '<h3>'.$langs->trans('ExactAddress').'</h3>';
		print emergencyhouseSolicitationActionForm($emergencyhousePublicAuth, $id, 'reveal_address', 'RevealExactAddress', array());
		if (is_string($revealedAddress)) {
			print '<div class="eh-alert eh-alert-success">'.nl2br(dol_escape_htmltag($revealedAddress)).'</div>';
		}
	}
	if ($isInitiator) {
		print emergencyhouseSolicitationActionForm($emergencyhousePublicAuth, $id, 'cancel', 'CancelSolicitation', $cancellationReasons);
	}
	print '</div>';
}

print '<div class="eh-card eh-section-tight"><h2>'.$langs->trans('Safety').'</h2><p>'.$langs->trans('SafetyConversationHelp').'</p>';
print '<a href="'.dol_escape_htmltag(emergencyhousePublicUrl('report/create.php', array('type' => 'solicitation', 'object' => $id))).'">'.$langs->trans('ReportConversation').'</a></div>';
print '</aside></div></section>';
emergencyhousePublicRenderFooter();

/**
 * Render a protected workflow action.
 *
 * @param EmergencyHousePublicAuthService $auth Auth service
 * @param int $id Solicitation ID
 * @param string $action Action
 * @param string $labelKey Label
 * @param array<string, string> $reasons Translation keys by reason code
 * @return string
 */
function emergencyhouseSolicitationActionForm($auth, $id, $action, $labelKey, array $reasons)
{
	global $langs;

	$html = '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/view.php', array('id' => $id))).'" class="eh-inline-form" data-disable-on-submit>';
	$html .= emergencyhousePublicCsrfFields($auth, 'solicitation_action');
	$html .= '<input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';
	if (!empty($reasons)) {
		$options = array();
		foreach ($reasons as $code => $translationKey) {
			$options[$code] = $langs->trans($translationKey);
		}
		$html .= '<div class="eh-field"><label for="reason_'.$action.'">'.$langs->trans('Reason').'</label>';
		$html .= '<select class="eh-select2" id="reason_'.$action.'" name="reason_code" required>';
		$html .= '<option value="">'.$langs->trans('SelectAnOption').'</option>';
		foreach ($options as $code => $label) {
			$html .= '<option value="'.dol_escape_htmltag($code).'">'.dol_escape_htmltag($label).'</option>';
		}
		$html .= '</select></div>';
	}
	$html .= '<button class="eh-button '.(in_array($action, array('refuse', 'cancel'), true) ? 'eh-button-danger ' : '').'eh-button-small" type="submit">'.$langs->trans($labelKey).'</button></form>';
	return $html;
}
