<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');
dol_include_once('/emergencyhouse/class/solicitationservice.class.php');

$account = emergencyhousePublicRequireAccount($emergencyhousePublicAccount);
$offerId = GETPOSTINT('offer');
$requestId = GETPOSTINT('request');
$action = GETPOST('action', 'aZ09');
$listingService = new EmergencyHouseListingService($db);
$solicitationService = new EmergencyHouseSolicitationService($db);
$offer = false;
$request = false;
$direction = '';
$choices = array();
$errorKey = '';
$message = '';

if ($offerId > 0) {
	$offerCandidate = new EmergencyHouseOffer($db);
	if ($offerCandidate->fetch($offerId) > 0
		&& (int) $offerCandidate->entity === (int) $account->entity
		&& (int) $offerCandidate->status === EmergencyHouseOffer::STATUS_PUBLISHED
		&& (int) $offerCandidate->fk_account !== (int) $account->id) {
		$offer = $offerCandidate;
		$direction = 'request_to_offer';
		$sql = 'SELECT rowid, ref, title FROM '.MAIN_DB_PREFIX.'emergencyhouse_request';
		$sql .= ' WHERE entity = '.((int) $account->entity).' AND fk_account = '.((int) $account->id);
		$sql .= ' AND fk_campaign = '.((int) $offer->fk_campaign);
		$sql .= ' AND status IN ('.EmergencyHouseRequest::STATUS_ACTIVE.','.EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED.')';
		$sql .= ' AND remaining_count > 0 ORDER BY tms DESC';
		$resql = $db->query($sql);
		if ($resql) {
			while (is_object($obj = $db->fetch_object($resql))) {
				$choices[(int) $obj->rowid] = (string) $obj->ref.' — '.(string) $obj->title;
			}
		}
	}
} elseif ($requestId > 0) {
	$requestCandidate = new EmergencyHouseRequest($db);
	if ($requestCandidate->fetch($requestId) > 0
		&& (int) $requestCandidate->entity === (int) $account->entity
		&& $requestCandidate->visibility === 'public'
		&& in_array((int) $requestCandidate->status, array(EmergencyHouseRequest::STATUS_ACTIVE, EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED), true)
		&& (int) $requestCandidate->fk_account !== (int) $account->id) {
		$request = $requestCandidate;
		$direction = 'offer_to_request';
		$sql = 'SELECT rowid, ref, title FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer';
		$sql .= ' WHERE entity = '.((int) $account->entity).' AND fk_account = '.((int) $account->id);
		$sql .= ' AND fk_campaign = '.((int) $request->fk_campaign);
		$sql .= ' AND status = '.EmergencyHouseOffer::STATUS_PUBLISHED;
		$sql .= ' AND capacity_available > 0 ORDER BY tms DESC';
		$resql = $db->query($sql);
		if ($resql) {
			while (is_object($obj = $db->fetch_object($resql))) {
				$choices[(int) $obj->rowid] = (string) $obj->ref.' — '.(string) $obj->title;
			}
		}
	}
}

if ($direction === '') {
	http_response_code(404);
	emergencyhousePublicRenderHeader($langs->trans('SolicitationTargetNotFound'), $account, 'account');
	print '<section class="eh-shell eh-section"><div class="eh-empty"><h1>'.$langs->trans('SolicitationTargetNotFound').'</h1></div></section>';
	emergencyhousePublicRenderFooter();
	exit;
}

if ($action === 'create' && emergencyhousePublicVerifyAuthenticatedPost($emergencyhousePublicAuth, 'solicitation_create')) {
	$message = trim(GETPOST('message', 'restricthtml'));
	$consent = GETPOSTINT('contact_consent') > 0;
	if (!$consent) {
		$errorKey = 'ErrorContactConsentRequired';
	} elseif (!emergencyhousePublicConsumeRateLimit(
		$db,
		(int) $account->entity,
		'solicitation_create',
		(string) $account->id.'|'.$emergencyhousePublicIp,
		max(1, getDolGlobalInt('EMERGENCYHOUSE_SOLICITATION_DAILY_LIMIT', 20)),
		86400
	)) {
		$errorKey = 'ErrorRateLimitExceeded';
	} else {
		if ($direction === 'request_to_offer') {
			$requestId = GETPOSTINT('owned_request');
		} else {
			$offerId = GETPOSTINT('owned_offer');
		}
		$created = $solicitationService->create(
			$offerId,
			$requestId,
			null,
			(int) $account->id,
			null,
			$direction,
			$message,
			emergencyhousePublicTriggerUser($db)
		);
		if ($created instanceof EmergencyHouseSolicitation) {
			$campaignId = $offer instanceof EmergencyHouseOffer
				? (int) $offer->fk_campaign
				: ($request instanceof EmergencyHouseRequest ? (int) $request->fk_campaign : 0);
			emergencyhousePublicAnalyticsEvent('solicitation_created', true, 'solicitation_form', $account, $campaignId);
			header('Location: '.emergencyhousePublicUrl('solicitation/view.php', array('id' => (int) $created->id, 'created' => 1)));
			exit;
		}
		$errorKey = $solicitationService->error !== '' ? $solicitationService->error : 'ErrorSolicitationNotCreated';
	}
} elseif ($action === 'create') {
	$errorKey = 'ErrorInvalidCsrfToken';
}

$targetTitle = $direction === 'request_to_offer' && $offer instanceof EmergencyHouseOffer
	? $offer->title
	: ($request instanceof EmergencyHouseRequest ? $request->title : '');
$pageTitle = $langs->trans('CreateSolicitation');
emergencyhousePublicRenderHeader($pageTitle, $account, 'account');
print '<section class="eh-shell eh-section"><div class="eh-page-title"><p class="eh-eyebrow">'.$langs->trans('ProtectedConversation').'</p>';
print '<h1>'.$pageTitle.'</h1><p>'.$langs->trans('SolicitationCreateIntroduction', dol_escape_htmltag($targetTitle)).'</p></div>';
if ($errorKey !== '') {
	emergencyhousePublicAlert($errorKey, 'error');
}
if (empty($choices)) {
	print '<div class="eh-empty"><h2>'.$langs->trans($direction === 'request_to_offer' ? 'NoEligibleRequest' : 'NoEligibleOffer').'</h2>';
	print '<p>'.$langs->trans($direction === 'request_to_offer' ? 'NoEligibleRequestHelp' : 'NoEligibleOfferHelp').'</p>';
	print '<a class="eh-button" href="'.dol_escape_htmltag(emergencyhousePublicUrl($direction === 'request_to_offer' ? 'request/edit.php' : 'offer/edit.php')).'">';
	print $langs->trans($direction === 'request_to_offer' ? 'CreateRequest' : 'CreateOffer').'</a></div>';
} else {
	print '<form method="POST" action="'.dol_escape_htmltag(emergencyhousePublicUrl('solicitation/create.php', $direction === 'request_to_offer' ? array('offer' => $offerId) : array('request' => $requestId))).'" class="eh-form" data-disable-on-submit>';
	print emergencyhousePublicCsrfFields($emergencyhousePublicAuth, 'solicitation_create');
	print '<input type="hidden" name="action" value="create">';
	print '<div class="eh-form-section"><div class="eh-field"><label for="'.($direction === 'request_to_offer' ? 'owned_request' : 'owned_offer').'">';
	print $langs->trans($direction === 'request_to_offer' ? 'ChooseYourRequest' : 'ChooseYourOffer').'</label>';
	print emergencyhousePublicSelect($direction === 'request_to_offer' ? 'owned_request' : 'owned_offer', $choices, 0, true, $langs->trans('SelectAnOption'), ' required');
	print '</div></div>';
	print '<div class="eh-form-section"><div class="eh-field"><label for="message">'.$langs->trans('InitialMessage').'</label>';
	print '<textarea id="message" name="message" required minlength="10" maxlength="'.max(200, getDolGlobalInt('EMERGENCYHOUSE_MESSAGE_MAX_LENGTH', 4000)).'">'.dol_escape_htmltag($message).'</textarea>';
	print '<small class="eh-help">'.$langs->trans('SafeMessageHelp').'</small></div></div>';
	print '<div class="eh-form-section"><label class="eh-switch" for="contact_consent"><span><strong>'.$langs->trans('ContactConsent').'</strong><br><small>'.$langs->trans('ContactConsentHelp').'</small></span>';
	print '<input type="checkbox" role="switch" id="contact_consent" name="contact_consent" value="1" required></label></div>';
	print '<div class="eh-form-actions"><button class="eh-button" type="submit">'.$langs->trans('SendSolicitation').'</button></div></form>';
}
print '</section>';
emergencyhousePublicRenderFooter();
