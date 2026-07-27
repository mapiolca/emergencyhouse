<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/auditservice.class.php');
dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

/**
 * Audited disclosure of contact details and exact addresses.
 */
class EmergencyHouseSensitiveDataService
{
	/** @var DoliDB */
	private $db;
	/** @var EmergencyHouseEncryptionService */
	private $encryption;
	/** @var string */
	public $error = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->encryption = new EmergencyHouseEncryptionService();
	}

	/**
	 * Return controlled operator disclosure justifications.
	 *
	 * @return array<string, string>
	 */
	public static function getOperatorJustificationTranslationKeys()
	{
		return array(
			'emergency_coordination' => 'DisclosureReasonEmergencyCoordination',
			'participant_support' => 'DisclosureReasonParticipantSupport',
			'safety_investigation' => 'DisclosureReasonSafetyInvestigation',
			'allocation_preparation' => 'DisclosureReasonAllocationPreparation',
			'allocation_agreement_generation' => 'DisclosureReasonAllocationAgreementGeneration',
			'data_subject_request' => 'DisclosureReasonDataSubjectRequest',
		);
	}

	/**
	 * Reveal a public account profile to an authorized operator.
	 *
	 * @param int $entity Entity
	 * @param int $accountId Account ID
	 * @param User $user Operator
	 * @param string $justification Dictionary-backed or controlled justification
	 * @param string $objectType Context object type
	 * @param int $objectId Context object ID
	 * @param int|null $campaignId Campaign
	 * @return array{firstname:string,lastname:string,email:string,phone:string}|false
	 */
	public function revealContactForOperator($entity, $accountId, $user, $justification, $objectType, $objectId, $campaignId = null)
	{
		if (!emergencyhouseCanDo($user, 'sensitive', 'contact')) {
			$this->error = 'ErrorForbidden';
			return false;
		}
		$allowedJustifications = self::getOperatorJustificationTranslationKeys();
		if (!isset($allowedJustifications[$justification])) {
			$this->error = 'ErrorInvalidDisclosureJustification';
			return false;
		}
		$account = new EmergencyHousePublicAccount($this->db, $this->encryption);
		if ($account->fetch($accountId, $entity) <= 0 || (int) $account->entity !== $entity) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		$profile = $account->getDecryptedProfile();
		if (!is_array($profile)) {
			$this->error = $account->error;
			return false;
		}
		if (!$this->audit(
			$entity,
			'dolibarr_user',
			(int) $user->id,
			'EMERGENCYHOUSE_CONTACT_REVEAL',
			$objectType,
			$objectId,
			$campaignId,
			$justification
		)) {
			return false;
		}
		return $profile;
	}

	/**
	 * Reveal an exact offer address to an authorized operator.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param User $user Operator
	 * @param string $justification Controlled justification
	 * @return string|false
	 */
	public function revealAddressForOperator($offer, $user, $justification)
	{
		if (!emergencyhouseCanDo($user, 'sensitive', 'address', $offer)) {
			$this->error = 'ErrorForbidden';
			return false;
		}
		$allowedJustifications = self::getOperatorJustificationTranslationKeys();
		if (!isset($allowedJustifications[$justification])) {
			$this->error = 'ErrorInvalidDisclosureJustification';
			return false;
		}
		$address = $this->decryptOfferAddress($offer);
		if (!is_string($address)) {
			return false;
		}
		if (!$this->audit(
			(int) $offer->entity,
			'dolibarr_user',
			(int) $user->id,
			'EMERGENCYHOUSE_ADDRESS_REVEAL',
			'offer',
			(int) $offer->id,
			(int) $offer->fk_campaign,
			$justification
		)) {
			return false;
		}
		return $address;
	}

	/**
	 * Reveal the other participant's contact after mutual consent.
	 *
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @param EmergencyHousePublicAccount $viewer Viewing account
	 * @return array{firstname:string,lastname:string,email:string,phone:string}|false
	 */
	public function revealContactForParticipant($solicitation, $viewer)
	{
		if (!$solicitation->canRevealContact()) {
			$this->error = 'ErrorMutualConsentRequired';
			return false;
		}
		$participants = $this->fetchParticipants($solicitation);
		if (!is_array($participants)) {
			return false;
		}
		$viewerId = (int) $viewer->id;
		if ($viewerId !== $participants['offer_account'] && $viewerId !== $participants['request_account']) {
			$this->error = 'ErrorForbidden';
			return false;
		}
		$targetId = $viewerId === $participants['offer_account']
			? $participants['request_account']
			: $participants['offer_account'];
		$target = new EmergencyHousePublicAccount($this->db, $this->encryption);
		if ($target->fetch($targetId, (int) $solicitation->entity) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		$profile = $target->getDecryptedProfile();
		if (!is_array($profile)) {
			$this->error = $target->error;
			return false;
		}
		if (!$this->audit(
			(int) $solicitation->entity,
			'public_account',
			$viewerId,
			'EMERGENCYHOUSE_CONTACT_REVEAL',
			'solicitation',
			(int) $solicitation->id,
			(int) $solicitation->fk_campaign,
			'mutual_consent'
		)) {
			return false;
		}
		return $profile;
	}

	/**
	 * Reveal the host address to the requester after separate authorization.
	 *
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @param EmergencyHousePublicAccount $viewer Viewing account
	 * @return string|false
	 */
	public function revealAddressForParticipant($solicitation, $viewer)
	{
		if (!$solicitation->canRevealAddress()) {
			$this->error = 'ErrorAddressConsentRequired';
			return false;
		}
		$participants = $this->fetchParticipants($solicitation);
		if (!is_array($participants) || (int) $viewer->id !== $participants['request_account']) {
			$this->error = 'ErrorForbidden';
			return false;
		}
		$offer = new EmergencyHouseOffer($this->db);
		if ($offer->fetch((int) $solicitation->fk_offer) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		$address = $this->decryptOfferAddress($offer);
		if (!is_string($address)) {
			return false;
		}
		if (!$this->audit(
			(int) $solicitation->entity,
			'public_account',
			(int) $viewer->id,
			'EMERGENCYHOUSE_ADDRESS_REVEAL',
			'solicitation',
			(int) $solicitation->id,
			(int) $solicitation->fk_campaign,
			'host_authorized'
		)) {
			return false;
		}
		return $address;
	}

	/**
	 * Reveal the host address to the requester for an authorized allocation.
	 *
	 * @param EmergencyHouseAllocation $allocation Allocation
	 * @param EmergencyHousePublicAccount $viewer Viewing account
	 * @return string|false
	 */
	public function revealAddressForAllocation($allocation, $viewer)
	{
		if (empty($allocation->address_share_authorized)
			|| !in_array(
				(int) $allocation->status,
				array(
					EmergencyHouseAllocation::STATUS_CONFIRMED,
					EmergencyHouseAllocation::STATUS_ACTIVE,
					EmergencyHouseAllocation::STATUS_INCIDENT,
				),
				true
			)) {
			$this->error = 'ErrorAddressConsentRequired';
			return false;
		}
		$sql = 'SELECT o.fk_account AS offer_account, r.fk_account AS request_account';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= ' ON r.rowid = '.((int) $allocation->fk_request).' AND r.entity = o.entity';
		$sql .= ' WHERE o.rowid = '.((int) $allocation->fk_offer);
		$sql .= ' AND o.entity = '.((int) $allocation->entity);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($obj) || (int) $viewer->id !== (int) $obj->request_account) {
			$this->error = $resql ? 'ErrorForbidden' : $this->db->lasterror();
			return false;
		}
		$offer = new EmergencyHouseOffer($this->db);
		if ($offer->fetch((int) $allocation->fk_offer) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		$address = $this->decryptOfferAddress($offer);
		if (!is_string($address)) {
			return false;
		}
		if (!$this->audit(
			(int) $allocation->entity,
			'public_account',
			(int) $viewer->id,
			'EMERGENCYHOUSE_ADDRESS_REVEAL',
			'allocation',
			(int) $allocation->id,
			(int) $allocation->fk_campaign,
			'allocation_address_authorized'
		)) {
			return false;
		}
		return $address;
	}

	/**
	 * Decrypt an offer address.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @return string|false
	 */
	private function decryptOfferAddress($offer)
	{
		$context = 'emergencyhouse|offer|'.$offer->entity.'|'.$offer->public_uuid.'|address';
		$address = $this->encryption->decrypt((string) $offer->address_encrypted, $context);
		if (!is_string($address)) {
			$this->error = $this->encryption->error;
			return false;
		}
		return $address;
	}

	/**
	 * Fetch account identifiers on both sides.
	 *
	 * @param EmergencyHouseSolicitation $solicitation Solicitation
	 * @return array{offer_account:int,request_account:int}|false
	 */
	private function fetchParticipants($solicitation)
	{
		$sql = 'SELECT o.fk_account AS offer_account, r.fk_account AS request_account';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= ' ON r.rowid = '.((int) $solicitation->fk_request).' AND r.entity = o.entity';
		$sql .= ' WHERE o.rowid = '.((int) $solicitation->fk_offer);
		$sql .= ' AND o.entity = '.((int) $solicitation->entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		return array(
			'offer_account' => (int) $obj->offer_account,
			'request_account' => (int) $obj->request_account,
		);
	}

	/**
	 * Append a disclosure audit event.
	 *
	 * @param int $entity Entity
	 * @param string $actorType Actor type
	 * @param int $actorId Actor ID
	 * @param string $action Action
	 * @param string $objectType Object type
	 * @param int $objectId Object ID
	 * @param int|null $campaignId Campaign
	 * @param string $justification Justification
	 * @return bool
	 */
	private function audit($entity, $actorType, $actorId, $action, $objectType, $objectId, $campaignId, $justification)
	{
		$audit = new EmergencyHouseAuditService($this->db);
		if ($audit->record(
			$entity,
			$actorType,
			$actorId,
			$action,
			$objectType,
			$objectId,
			$campaignId,
			$justification
		) < 0) {
			$this->error = $audit->error;
			return false;
		}
		return true;
	}
}
