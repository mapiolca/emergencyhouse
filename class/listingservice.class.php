<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

dol_include_once('/emergencyhouse/class/campaign.class.php');
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/geocodingservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/offerphotoservice.class.php');
dol_include_once('/emergencyhouse/class/publicaccount.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/verificationservice.class.php');

/**
 * Secure boundary for public offers and accommodation requests.
 *
 * Controllers pass already typed values. This service validates ownership,
 * encrypts sensitive fields and writes normalized relation tables.
 */
class EmergencyHouseListingService
{
	/** @var DoliDB */
	private $db;
	/** @var EmergencyHouseEncryptionService */
	private $encryption;
	/** @var EmergencyHouseGeocodingService */
	private $geocoding;
	/** @var string */
	public $error = '';
	/** @var array<int, string> */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->encryption = new EmergencyHouseEncryptionService();
		$this->geocoding = new EmergencyHouseGeocodingService();
	}

	/**
	 * Create an offer and its features atomically.
	 *
	 * @param EmergencyHousePublicAccount $account Public account
	 * @param array<string, int|string|null> $data Typed offer data
	 * @param array<int, array{code?:string,number?:float|int|null}> $features Feature values keyed by dictionary ID
	 * @param User $triggerUser Dolibarr trigger user, anonymous on public pages
	 * @param bool $submit Submit for operator verification
	 * @param array<string, mixed> $uploadedPhotos PHP multiple-upload field
	 * @return EmergencyHouseOffer|false
	 */
	public function createOffer($account, array $data, array $features, $triggerUser, $submit, array $uploadedPhotos = array())
	{
		$campaign = $this->fetchPublishedCampaign((int) $account->entity, (int) ($data['fk_campaign'] ?? 0));
		if (!$campaign instanceof EmergencyHouseCampaign) {
			return false;
		}
		if ($submit && empty($account->email_verified)) {
			$this->error = 'ErrorEmailVerificationRequired';
			return false;
		}

		$offer = new EmergencyHouseOffer($this->db);
		$offer->entity = (int) $account->entity;
		$offer->date_creation = dol_now();
		$offer->public_uuid = bin2hex(random_bytes(16));
		$offer->fk_campaign = (int) $campaign->id;
		$offer->fk_account = (int) $account->id;
		$offer->fk_housing_type = (int) ($data['fk_housing_type'] ?? 0);
		$offer->zip = trim((string) ($data['zip'] ?? ''));
		$offer->town = trim((string) ($data['town'] ?? ''));
		$offer->fk_pays = !empty($data['fk_pays']) ? (int) $data['fk_pays'] : null;
		$offer->fk_departement = !empty($data['fk_departement']) ? (int) $data['fk_departement'] : null;
		$offer->public_zone = trim((string) ($data['public_zone'] ?? $offer->town));
		$offer->public_location_precision = $this->validatedChoice(
			(string) ($data['public_location_precision'] ?? 'town'),
			array('town', 'district', 'radius'),
			'town'
		);
		$offer->date_start = (int) ($data['date_start'] ?? 0);
		$offer->date_end = !empty($data['date_end']) ? (int) $data['date_end'] : null;
		$offer->capacity_total = max(0, (int) ($data['capacity_total'] ?? 0));
		$offer->capacity_available = $offer->capacity_total;
		$offer->max_adults = isset($data['max_adults']) ? max(0, (int) $data['max_adults']) : null;
		$offer->max_children = isset($data['max_children']) ? max(0, (int) $data['max_children']) : null;
		$offer->room_count = max(0, (int) ($data['room_count'] ?? 0));
		$offer->bed_count = max(0, (int) ($data['bed_count'] ?? 0));
		$offer->extra_bed_count = max(0, (int) ($data['extra_bed_count'] ?? 0));
		$offer->tent_count = max(0, (int) ($data['tent_count'] ?? 0));
		$offer->title = trim((string) ($data['title'] ?? ''));
		$offer->description_public = $this->nullableTrimmed($data['description_public'] ?? null);
		$offer->minimum_stay_days = max(0, (int) ($data['minimum_stay_days'] ?? 0));
		$offer->maximum_stay_days = !empty($data['maximum_stay_days']) ? max(0, (int) $data['maximum_stay_days']) : null;
		$offer->arrival_window = $this->nullableTrimmed($data['arrival_window'] ?? null);
		$offer->transport_available = !empty($data['transport_available']) ? 1 : 0;
		$offer->direct_solicitation_enabled = array_key_exists('direct_solicitation_enabled', $data)
			? (!empty($data['direct_solicitation_enabled']) ? 1 : 0)
			: 1;
		$offer->status = $submit ? EmergencyHouseOffer::STATUS_PENDING : EmergencyHouseOffer::STATUS_DRAFT;
		$offer->date_expiration = $this->listingExpiration($campaign, $offer->date_start);
		$offer->context['public_account_id'] = (int) $account->id;
		$offer->context['trigger_reason'] = $submit ? 'public_submission' : 'public_draft';
		if (EmergencyHouseOfferPhotoService::hasUploadedFiles($uploadedPhotos)) {
			$offer->context['changed_fields'] = array('photos');
		}

		$context = $this->offerContext($offer);
		$address = trim((string) ($data['address'] ?? ''));
		$encryptedAddress = $this->encryption->encrypt($address, $context.'|address');
		if (!is_string($encryptedAddress)) {
			$this->error = $this->encryption->error;
			return false;
		}
		$offer->address_encrypted = $encryptedAddress;
		if (!$this->geocodeOffer($offer, $address)) {
			return false;
		}
		$privateInstructions = trim((string) ($data['private_instructions'] ?? ''));
		if ($privateInstructions !== '') {
			$encryptedInstructions = $this->encryption->encrypt($privateInstructions, $context.'|private_instructions');
			if (!is_string($encryptedInstructions)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$offer->private_instructions_encrypted = $encryptedInstructions;
		}

		$nextRef = $offer->getNextNumRef();
		if (!is_string($nextRef) || $nextRef === '') {
			$this->error = !empty($offer->error) ? $offer->error : 'ErrorNumberingModel';
			return false;
		}
		$offer->ref = $nextRef;

		$this->db->begin();
		$result = $offer->createInsideServiceTransaction($triggerUser);
		$photoService = new EmergencyHouseOfferPhotoService($this->db);
		$verificationService = new EmergencyHouseVerificationService($this->db);
		if ($result <= 0
			|| !$this->replaceOfferFeatures($offer, $features)
			|| !$photoService->addUploadedPhotos($offer, $uploadedPhotos, (int) $triggerUser->id)
			|| ($submit && $verificationService->enqueueTarget(
				(int) $offer->entity,
				'offer',
				(int) $offer->id,
				dol_now(),
				false
			) <= 0)) {
			$this->error = !empty($offer->error)
				? $offer->error
				: ($photoService->error !== ''
					? $photoService->error
					: ($verificationService->error !== '' ? $verificationService->error : $this->error));
			$this->errors = $offer->errors;
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return $offer;
	}

	/**
	 * Create a request and its compatibility criteria atomically.
	 *
	 * @param EmergencyHousePublicAccount $account Public account
	 * @param array<string, int|string|null> $data Typed request data
	 * @param array<int, string> $housingTypes Housing type IDs and preference levels
	 * @param array<int, string> $criteria Feature IDs and criterion levels
	 * @param User $triggerUser Dolibarr trigger user, anonymous on public pages
	 * @param bool $submit Activate the request
	 * @return EmergencyHouseRequest|false
	 */
	public function createRequest($account, array $data, array $housingTypes, array $criteria, $triggerUser, $submit)
	{
		$campaign = $this->fetchPublishedCampaign((int) $account->entity, (int) ($data['fk_campaign'] ?? 0));
		if (!$campaign instanceof EmergencyHouseCampaign) {
			return false;
		}
		if ($submit && empty($account->email_verified)) {
			$this->error = 'ErrorEmailVerificationRequired';
			return false;
		}

		$request = new EmergencyHouseRequest($this->db);
		$request->entity = (int) $account->entity;
		$request->date_creation = dol_now();
		$request->public_uuid = bin2hex(random_bytes(16));
		$request->fk_campaign = (int) $campaign->id;
		$request->fk_account = (int) $account->id;
		$request->adults_count = max(0, (int) ($data['adults_count'] ?? 0));
		$request->children_infant_count = max(0, (int) ($data['children_infant_count'] ?? 0));
		$request->children_young_count = max(0, (int) ($data['children_young_count'] ?? 0));
		$request->children_teen_count = max(0, (int) ($data['children_teen_count'] ?? 0));
		$request->group_divisible = !empty($data['group_divisible']) ? 1 : 0;
		$request->minimum_group_size = max(1, (int) ($data['minimum_group_size'] ?? 1));
		$request->date_start = (int) ($data['date_start'] ?? 0);
		$request->date_end = !empty($data['date_end']) ? (int) $data['date_end'] : null;
		$request->duration_unknown = !empty($data['duration_unknown']) ? 1 : 0;
		$request->desired_zone = trim((string) ($data['desired_zone'] ?? ''));
		$request->desired_zip = $this->nullableTrimmed($data['desired_zip'] ?? null);
		$request->desired_town = $this->nullableTrimmed($data['desired_town'] ?? null);
		$request->search_radius = max(1, min(1000, (int) ($data['search_radius'] ?? $campaign->default_radius)));
		$request->transport_mode = $this->nullableTrimmed($data['transport_mode'] ?? null);
		$request->pickup_possible = !empty($data['pickup_possible']) ? 1 : 0;
		$request->urgency_level = max(0, min(3, (int) ($data['urgency_level'] ?? 0)));
		$request->title = trim((string) ($data['title'] ?? ''));
		$request->description_public = $this->nullableTrimmed($data['description_public'] ?? null);
		$request->visibility = $this->validatedChoice(
			(string) ($data['visibility'] ?? getDolGlobalString('EMERGENCYHOUSE_PUBLIC_REQUEST_VISIBILITY', 'private')),
			array('private', 'public'),
			'private'
		);
		$request->status = $submit ? EmergencyHouseRequest::STATUS_ACTIVE : EmergencyHouseRequest::STATUS_DRAFT;
		$request->date_expiration = $this->listingExpiration($campaign, $request->date_start);
		$request->context['public_account_id'] = (int) $account->id;
		$request->context['trigger_reason'] = $submit ? 'public_submission' : 'public_draft';

		$context = $this->requestContext($request);
		if (!$this->geocodeRequest($request)) {
			return false;
		}
		$pickup = trim((string) ($data['pickup_location'] ?? ''));
		if ($pickup !== '') {
			$encryptedPickup = $this->encryption->encrypt($pickup, $context.'|pickup_location');
			if (!is_string($encryptedPickup)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$request->pickup_location_encrypted = $encryptedPickup;
		}
		$privateNote = trim((string) ($data['private_note'] ?? ''));
		if ($privateNote !== '') {
			$encryptedNote = $this->encryption->encrypt($privateNote, $context.'|private_note');
			if (!is_string($encryptedNote)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$request->private_note_encrypted = $encryptedNote;
		}

		$nextRef = $request->getNextNumRef();
		if (!is_string($nextRef) || $nextRef === '') {
			$this->error = !empty($request->error) ? $request->error : 'ErrorNumberingModel';
			return false;
		}
		$request->ref = $nextRef;

		$this->db->begin();
		$result = $request->createInsideServiceTransaction($triggerUser);
		$verificationService = new EmergencyHouseVerificationService($this->db);
		if ($result <= 0
			|| !$this->replaceRequestHousingTypes($request, $housingTypes)
			|| !$this->replaceRequestCriteria($request, $criteria)
			|| ($submit && $verificationService->enqueueTarget(
				(int) $request->entity,
				'request',
				(int) $request->id,
				dol_now(),
				false
			) <= 0)) {
			$this->error = !empty($request->error)
				? $request->error
				: ($verificationService->error !== '' ? $verificationService->error : $this->error);
			$this->errors = $request->errors;
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return $request;
	}

	/**
	 * Fetch an account-owned offer.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $id Offer ID
	 * @return EmergencyHouseOffer|false
	 */
	public function fetchOwnedOffer($account, $id)
	{
		$offer = new EmergencyHouseOffer($this->db);
		if ($offer->fetch($id) <= 0 || (int) $offer->entity !== (int) $account->entity || (int) $offer->fk_account !== (int) $account->id) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		return $offer;
	}

	/**
	 * Fetch an account-owned request.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $id Request ID
	 * @return EmergencyHouseRequest|false
	 */
	public function fetchOwnedRequest($account, $id)
	{
		$request = new EmergencyHouseRequest($this->db);
		if ($request->fetch($id) <= 0 || (int) $request->entity !== (int) $account->entity || (int) $request->fk_account !== (int) $account->id) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		return $request;
	}

	/**
	 * Fetch an offer by public UUID when it is public or owned by the viewer.
	 *
	 * @param EmergencyHousePublicAccount|null $account Viewer
	 * @param string $uuid Public UUID
	 * @return EmergencyHouseOffer|false
	 */
	public function fetchViewableOffer($account, $uuid)
	{
		global $conf;

		if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= " AND public_uuid = '".$this->db->escape($uuid)."'";
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		$offer = new EmergencyHouseOffer($this->db);
		if (!is_object($obj) || $offer->fetch((int) $obj->rowid) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		$isOwner = $account instanceof EmergencyHousePublicAccount
			&& (int) $account->entity === (int) $offer->entity
			&& (int) $account->id === (int) $offer->fk_account;
		if (!$isOwner && (int) $offer->status !== EmergencyHouseOffer::STATUS_PUBLISHED) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		return $offer;
	}

	/**
	 * Fetch a request by public UUID when it is public or owned by the viewer.
	 *
	 * @param EmergencyHousePublicAccount|null $account Viewer
	 * @param string $uuid Public UUID
	 * @return EmergencyHouseRequest|false
	 */
	public function fetchViewableRequest($account, $uuid)
	{
		global $conf;

		if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_request';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= " AND public_uuid = '".$this->db->escape($uuid)."'";
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		$request = new EmergencyHouseRequest($this->db);
		if (!is_object($obj) || $request->fetch((int) $obj->rowid) <= 0) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		$isOwner = $account instanceof EmergencyHousePublicAccount
			&& (int) $account->entity === (int) $request->entity
			&& (int) $account->id === (int) $request->fk_account;
		$isPublic = $request->visibility === 'public'
			&& (int) $request->verification_status === EmergencyHouseVerificationService::STATUS_VERIFIED
			&& in_array(
				(int) $request->status,
				array(EmergencyHouseRequest::STATUS_ACTIVE, EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED),
				true
			);
		if (!$isOwner && !$isPublic) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}
		return $request;
	}

	/**
	 * List account-owned offers.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $limit Limit
	 * @param int $offset Offset
	 * @return array<int, array<string, int|string|null>>|false
	 */
	public function fetchOwnedOffers($account, $limit = 100, $offset = 0)
	{
		$sql = 'SELECT o.rowid, o.public_uuid, o.ref, o.fk_campaign, o.public_zone, o.date_start, o.date_end,';
		$sql .= ' o.capacity_available, o.capacity_total, o.title, o.status, o.verification_status, o.tms, c.label AS campaign_label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c ON c.rowid = o.fk_campaign AND c.entity = o.entity';
		$sql .= ' WHERE o.entity = '.((int) $account->entity).' AND o.fk_account = '.((int) $account->id);
		$sql .= ' ORDER BY o.tms DESC, o.rowid DESC';
		$sql .= $this->db->plimit(min(100, max(1, $limit)), max(0, $offset));
		return $this->fetchSafeRows($sql);
	}

	/**
	 * List account-owned requests.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $limit Limit
	 * @param int $offset Offset
	 * @return array<int, array<string, int|string|null>>|false
	 */
	public function fetchOwnedRequests($account, $limit = 100, $offset = 0)
	{
		$sql = 'SELECT r.rowid, r.public_uuid, r.ref, r.fk_campaign, r.desired_zone, r.date_start, r.date_end,';
		$sql .= ' r.person_count, r.remaining_count, r.title, r.status, r.verification_status, r.visibility, r.tms, c.label AS campaign_label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c ON c.rowid = r.fk_campaign AND c.entity = r.entity';
		$sql .= ' WHERE r.entity = '.((int) $account->entity).' AND r.fk_account = '.((int) $account->id);
		$sql .= ' ORDER BY r.tms DESC, r.rowid DESC';
		$sql .= $this->db->plimit(min(100, max(1, $limit)), max(0, $offset));
		return $this->fetchSafeRows($sql);
	}

	/**
	 * Load offer features for display or editing.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @return array<int, array{id:int,code:string,label:string,group:string,type:string,value_code:string,value_number:float|null}>|false
	 */
	public function fetchOfferFeatures($offer)
	{
		$sql = 'SELECT f.rowid, f.code, f.label, f.feature_group, f.value_type,';
		$sql .= ' rel.value_code, rel.value_number';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer_feature AS rel';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_emergencyhouse_feature AS f';
		$sql .= ' ON f.rowid = rel.fk_feature AND f.entity = rel.entity';
		$sql .= ' WHERE rel.entity = '.((int) $offer->entity).' AND rel.fk_offer = '.((int) $offer->id);
		$sql .= ' ORDER BY f.position ASC, f.label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				'id' => (int) $obj->rowid,
				'code' => (string) $obj->code,
				'label' => (string) $obj->label,
				'group' => (string) $obj->feature_group,
				'type' => (string) $obj->value_type,
				'value_code' => (string) $obj->value_code,
				'value_number' => $obj->value_number === null ? null : (float) $obj->value_number,
			);
		}
		return $rows;
	}

	/**
	 * Load requested housing types.
	 *
	 * @param EmergencyHouseRequest $request Request
	 * @return array<int, array{id:int,code:string,label:string,level:string}>|false
	 */
	public function fetchRequestHousingTypes($request)
	{
		$sql = 'SELECT h.rowid, h.code, h.label, rel.preference_level';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_request_housing_type AS rel';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_emergencyhouse_housing_type AS h';
		$sql .= ' ON h.rowid = rel.fk_housing_type AND h.entity = rel.entity';
		$sql .= ' WHERE rel.entity = '.((int) $request->entity).' AND rel.fk_request = '.((int) $request->id);
		$sql .= ' ORDER BY h.position ASC, h.label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				'id' => (int) $obj->rowid,
				'code' => (string) $obj->code,
				'label' => (string) $obj->label,
				'level' => (string) $obj->preference_level,
			);
		}
		return $rows;
	}

	/**
	 * Load requested feature criteria.
	 *
	 * @param EmergencyHouseRequest $request Request
	 * @return array<int, array{id:int,code:string,label:string,group:string,level:string}>|false
	 */
	public function fetchRequestCriteria($request)
	{
		$sql = 'SELECT f.rowid, f.code, f.label, f.feature_group, rel.criterion_level';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_request_criterion AS rel';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'c_emergencyhouse_feature AS f';
		$sql .= ' ON f.rowid = rel.fk_feature AND f.entity = rel.entity';
		$sql .= ' WHERE rel.entity = '.((int) $request->entity).' AND rel.fk_request = '.((int) $request->id);
		$sql .= ' ORDER BY f.position ASC, f.label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				'id' => (int) $obj->rowid,
				'code' => (string) $obj->code,
				'label' => (string) $obj->label,
				'group' => (string) $obj->feature_group,
				'level' => (string) $obj->criterion_level,
			);
		}
		return $rows;
	}

	/**
	 * Update an account-owned offer and reset operator verification when
	 * publication-sensitive content changes.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $id Offer ID
	 * @param array<string, int|string|null> $data Typed data
	 * @param array<int, array{code?:string,number?:float|int|null}> $features Features
	 * @param User $triggerUser Trigger user
	 * @param bool $submit Submit for verification
	 * @param array<string, mixed> $uploadedPhotos PHP multiple-upload field
	 * @return EmergencyHouseOffer|false
	 */
	public function updateOwnedOffer($account, $id, array $data, array $features, $triggerUser, $submit, array $uploadedPhotos = array())
	{
		$offer = $this->fetchOwnedOffer($account, $id);
		if (!$offer instanceof EmergencyHouseOffer || (int) $offer->status === EmergencyHouseOffer::STATUS_CLOSED) {
			$this->error = 'ErrorObjectNotEditable';
			return false;
		}
		$allocated = max(0, (int) $offer->capacity_total - (int) $offer->capacity_available);
		$newCapacity = max(0, (int) ($data['capacity_total'] ?? 0));
		if ($newCapacity < $allocated) {
			$this->error = 'ErrorCapacityBelowAllocated';
			return false;
		}
		$offer->fk_housing_type = (int) ($data['fk_housing_type'] ?? 0);
		$offer->zip = trim((string) ($data['zip'] ?? ''));
		$offer->town = trim((string) ($data['town'] ?? ''));
		$offer->fk_pays = !empty($data['fk_pays']) ? (int) $data['fk_pays'] : null;
		$offer->fk_departement = !empty($data['fk_departement']) ? (int) $data['fk_departement'] : null;
		$offer->public_zone = trim((string) ($data['public_zone'] ?? $offer->town));
		$offer->public_location_precision = $this->validatedChoice(
			(string) ($data['public_location_precision'] ?? 'town'),
			array('town', 'district', 'radius'),
			'town'
		);
		$offer->date_start = (int) ($data['date_start'] ?? 0);
		$offer->date_end = !empty($data['date_end']) ? (int) $data['date_end'] : null;
		$offer->capacity_total = $newCapacity;
		$offer->capacity_available = $newCapacity - $allocated;
		$offer->max_adults = isset($data['max_adults']) ? max(0, (int) $data['max_adults']) : null;
		$offer->max_children = isset($data['max_children']) ? max(0, (int) $data['max_children']) : null;
		$offer->room_count = max(0, (int) ($data['room_count'] ?? 0));
		$offer->bed_count = max(0, (int) ($data['bed_count'] ?? 0));
		$offer->extra_bed_count = max(0, (int) ($data['extra_bed_count'] ?? 0));
		$offer->tent_count = max(0, (int) ($data['tent_count'] ?? 0));
		$offer->title = trim((string) ($data['title'] ?? ''));
		$offer->description_public = $this->nullableTrimmed($data['description_public'] ?? null);
		$offer->minimum_stay_days = max(0, (int) ($data['minimum_stay_days'] ?? 0));
		$offer->maximum_stay_days = !empty($data['maximum_stay_days']) ? max(0, (int) $data['maximum_stay_days']) : null;
		$offer->arrival_window = $this->nullableTrimmed($data['arrival_window'] ?? null);
		$offer->transport_available = !empty($data['transport_available']) ? 1 : 0;
		$offer->direct_solicitation_enabled = !empty($data['direct_solicitation_enabled']) ? 1 : 0;
		$offer->verification_status = 0;
		$offer->status = $submit ? EmergencyHouseOffer::STATUS_PENDING : EmergencyHouseOffer::STATUS_DRAFT;
		$offer->context['public_account_id'] = (int) $account->id;
		$offer->context['trigger_reason'] = $submit ? 'public_resubmission' : 'public_edit';
		$offer->context['changed_fields'] = array('content', 'capacity', 'availability');
		if (EmergencyHouseOfferPhotoService::hasUploadedFiles($uploadedPhotos)) {
			$offer->context['changed_fields'][] = 'photos';
		}

		$context = $this->offerContext($offer);
		$address = trim((string) ($data['address'] ?? ''));
		if ($address !== '') {
			$encryptedAddress = $this->encryption->encrypt($address, $context.'|address');
			if (!is_string($encryptedAddress)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$offer->address_encrypted = $encryptedAddress;
			if (!$this->geocodeOffer($offer, $address)) {
				return false;
			}
		}
		$privateInstructions = trim((string) ($data['private_instructions'] ?? ''));
		if ($privateInstructions !== '') {
			$encryptedInstructions = $this->encryption->encrypt($privateInstructions, $context.'|private_instructions');
			if (!is_string($encryptedInstructions)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$offer->private_instructions_encrypted = $encryptedInstructions;
		}

		$this->db->begin();
		$photoService = new EmergencyHouseOfferPhotoService($this->db);
		$verificationService = new EmergencyHouseVerificationService($this->db);
		if (!$verificationService->lockSubmissionTarget((int) $offer->entity, 'offer', (int) $offer->id)
			|| !$this->lockRow('emergencyhouse_offer', (int) $offer->entity, (int) $offer->id)
			|| $offer->updateInsideServiceTransaction($triggerUser) <= 0
			|| !$this->deleteRelations('emergencyhouse_offer_feature', 'fk_offer', (int) $offer->entity, (int) $offer->id)
			|| !$this->replaceOfferFeatures($offer, $features)
			|| !$photoService->addUploadedPhotos($offer, $uploadedPhotos, (int) $triggerUser->id)
			|| ($submit
				? $verificationService->enqueueTarget(
					(int) $offer->entity,
					'offer',
					(int) $offer->id,
					dol_now(),
					false
				) <= 0
				: $verificationService->cancelTarget(
					(int) $offer->entity,
					'offer',
					(int) $offer->id,
					false
				) <= 0)) {
			$this->error = !empty($offer->error)
				? $offer->error
				: ($photoService->error !== ''
					? $photoService->error
					: ($verificationService->error !== '' ? $verificationService->error : $this->error));
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return $offer;
	}

	/**
	 * Delete one account-owned offer photo and reset operator verification.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $offerId Offer ID
	 * @param int $photoId Photo ID
	 * @param User $triggerUser Trigger user
	 * @return EmergencyHouseOffer|false
	 */
	public function deleteOwnedOfferPhoto($account, $offerId, $photoId, $triggerUser)
	{
		$offer = $this->fetchOwnedOffer($account, $offerId);
		if (!$offer instanceof EmergencyHouseOffer || (int) $offer->status === EmergencyHouseOffer::STATUS_CLOSED) {
			$this->error = 'ErrorObjectNotEditable';
			return false;
		}

		$photoService = new EmergencyHouseOfferPhotoService($this->db);
		$this->db->begin();
		$verificationService = new EmergencyHouseVerificationService($this->db);
		if (!$verificationService->lockSubmissionTarget((int) $offer->entity, 'offer', (int) $offer->id)
			|| !$this->lockRow('emergencyhouse_offer', (int) $offer->entity, (int) $offer->id)) {
			$this->error = $verificationService->error !== '' ? $verificationService->error : $this->error;
			$this->db->rollback();
			return false;
		}
		$photoPath = $photoService->deletePhotoMetadata($offer, $photoId);
		if (!is_string($photoPath)) {
			$this->error = $photoService->error;
			$this->db->rollback();
			return false;
		}

		$offer->verification_status = 0;
		$offer->status = EmergencyHouseOffer::STATUS_DRAFT;
		$offer->context['public_account_id'] = (int) $account->id;
		$offer->context['trigger_reason'] = 'photo_change';
		$offer->context['changed_fields'] = array('photos', 'verification_status', 'status');
		if ($offer->updateInsideServiceTransaction($triggerUser) <= 0
			|| $verificationService->cancelTarget(
				(int) $offer->entity,
				'offer',
				(int) $offer->id,
				false
			) <= 0) {
			$this->error = !empty($offer->error) ? $offer->error : $verificationService->error;
			$this->errors = $offer->errors;
			$this->db->rollback();
			return false;
		}

		$this->db->commit();
		$photoService->deletePhysicalFile($photoPath);
		return $offer;
	}

	/**
	 * Update an account-owned request and its criteria.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param int $id Request ID
	 * @param array<string, int|string|null> $data Typed data
	 * @param array<int, string> $housingTypes Housing preferences
	 * @param array<int, string> $criteria Feature criteria
	 * @param User $triggerUser Trigger user
	 * @param bool $submit Activate
	 * @return EmergencyHouseRequest|false
	 */
	public function updateOwnedRequest($account, $id, array $data, array $housingTypes, array $criteria, $triggerUser, $submit)
	{
		$request = $this->fetchOwnedRequest($account, $id);
		if (!$request instanceof EmergencyHouseRequest || (int) $request->status === EmergencyHouseRequest::STATUS_CLOSED) {
			$this->error = 'ErrorObjectNotEditable';
			return false;
		}
		$request->adults_count = max(0, (int) ($data['adults_count'] ?? 0));
		$request->children_infant_count = max(0, (int) ($data['children_infant_count'] ?? 0));
		$request->children_young_count = max(0, (int) ($data['children_young_count'] ?? 0));
		$request->children_teen_count = max(0, (int) ($data['children_teen_count'] ?? 0));
		$request->group_divisible = !empty($data['group_divisible']) ? 1 : 0;
		$request->minimum_group_size = max(1, (int) ($data['minimum_group_size'] ?? 1));
		$request->date_start = (int) ($data['date_start'] ?? 0);
		$request->date_end = !empty($data['date_end']) ? (int) $data['date_end'] : null;
		$request->duration_unknown = !empty($data['duration_unknown']) ? 1 : 0;
		$request->desired_zone = trim((string) ($data['desired_zone'] ?? ''));
		$request->desired_zip = $this->nullableTrimmed($data['desired_zip'] ?? null);
		$request->desired_town = $this->nullableTrimmed($data['desired_town'] ?? null);
		$request->search_radius = max(1, min(1000, (int) ($data['search_radius'] ?? 50)));
		$request->transport_mode = $this->nullableTrimmed($data['transport_mode'] ?? null);
		$request->pickup_possible = !empty($data['pickup_possible']) ? 1 : 0;
		$request->urgency_level = max(0, min(3, (int) ($data['urgency_level'] ?? 0)));
		$request->title = trim((string) ($data['title'] ?? ''));
		$request->description_public = $this->nullableTrimmed($data['description_public'] ?? null);
		$request->visibility = $this->validatedChoice(
			(string) ($data['visibility'] ?? 'private'),
			array('private', 'public'),
			'private'
		);
		$request->verification_status = 0;
		$request->status = $submit ? EmergencyHouseRequest::STATUS_ACTIVE : EmergencyHouseRequest::STATUS_DRAFT;
		$request->context['public_account_id'] = (int) $account->id;
		$request->context['trigger_reason'] = $submit ? 'public_resubmission' : 'public_edit';
		$request->context['changed_fields'] = array('content', 'group', 'criteria');

		$context = $this->requestContext($request);
		if (!$this->geocodeRequest($request)) {
			return false;
		}
		$pickup = trim((string) ($data['pickup_location'] ?? ''));
		if ($pickup !== '') {
			$encryptedPickup = $this->encryption->encrypt($pickup, $context.'|pickup_location');
			if (!is_string($encryptedPickup)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$request->pickup_location_encrypted = $encryptedPickup;
		}
		$privateNote = trim((string) ($data['private_note'] ?? ''));
		if ($privateNote !== '') {
			$encryptedNote = $this->encryption->encrypt($privateNote, $context.'|private_note');
			if (!is_string($encryptedNote)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$request->private_note_encrypted = $encryptedNote;
		}

		$this->db->begin();
		$verificationService = new EmergencyHouseVerificationService($this->db);
		if (!$verificationService->lockSubmissionTarget((int) $request->entity, 'request', (int) $request->id)
			|| !$this->lockRow('emergencyhouse_request', (int) $request->entity, (int) $request->id)
			|| $request->updateInsideServiceTransaction($triggerUser) <= 0
			|| !$this->deleteRelations('emergencyhouse_request_housing_type', 'fk_request', (int) $request->entity, (int) $request->id)
			|| !$this->deleteRelations('emergencyhouse_request_criterion', 'fk_request', (int) $request->entity, (int) $request->id)
			|| !$this->replaceRequestHousingTypes($request, $housingTypes)
			|| !$this->replaceRequestCriteria($request, $criteria)
			|| ($submit
				? $verificationService->enqueueTarget(
					(int) $request->entity,
					'request',
					(int) $request->id,
					dol_now(),
					false
				) <= 0
				: $verificationService->cancelTarget(
					(int) $request->entity,
					'request',
					(int) $request->id,
					false
				) <= 0)) {
			$this->error = !empty($request->error)
				? $request->error
				: ($verificationService->error !== '' ? $verificationService->error : $this->error);
			$this->db->rollback();
			return false;
		}
		$this->db->commit();
		return $request;
	}

	/**
	 * Decrypt private offer fields for their owner.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param EmergencyHouseOffer $offer Offer
	 * @return array{address:string,private_instructions:string}|false
	 */
	public function decryptOwnedOffer($account, $offer)
	{
		if ((int) $offer->entity !== (int) $account->entity || (int) $offer->fk_account !== (int) $account->id) {
			$this->error = 'ErrorForbidden';
			return false;
		}
		$context = $this->offerContext($offer);
		$address = $this->encryption->decrypt((string) $offer->address_encrypted, $context.'|address');
		if (!is_string($address)) {
			$this->error = $this->encryption->error;
			return false;
		}
		$privateInstructions = '';
		if (!empty($offer->private_instructions_encrypted)) {
			$value = $this->encryption->decrypt((string) $offer->private_instructions_encrypted, $context.'|private_instructions');
			if (!is_string($value)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$privateInstructions = $value;
		}
		return array('address' => $address, 'private_instructions' => $privateInstructions);
	}

	/**
	 * Decrypt private request fields for their owner.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @param EmergencyHouseRequest $request Request
	 * @return array{pickup_location:string,private_note:string}|false
	 */
	public function decryptOwnedRequest($account, $request)
	{
		if ((int) $request->entity !== (int) $account->entity || (int) $request->fk_account !== (int) $account->id) {
			$this->error = 'ErrorForbidden';
			return false;
		}
		$context = $this->requestContext($request);
		$pickup = '';
		if (!empty($request->pickup_location_encrypted)) {
			$value = $this->encryption->decrypt((string) $request->pickup_location_encrypted, $context.'|pickup_location');
			if (!is_string($value)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$pickup = $value;
		}
		$privateNote = '';
		if (!empty($request->private_note_encrypted)) {
			$value = $this->encryption->decrypt((string) $request->private_note_encrypted, $context.'|private_note');
			if (!is_string($value)) {
				$this->error = $this->encryption->error;
				return false;
			}
			$privateNote = $value;
		}
		return array('pickup_location' => $pickup, 'private_note' => $privateNote);
	}

	/**
	 * List safe public offers without encrypted or exact fields.
	 *
	 * @param int $campaignId Campaign ID
	 * @param int $limit Limit
	 * @param int $offset Offset
	 * @param string $town Town filter
	 * @return array<int, array<string, int|string|null>>|false
	 */
	public function fetchPublicOffers($campaignId, $limit = 24, $offset = 0, $town = '')
	{
		global $conf;

		$sql = 'SELECT o.rowid, o.public_uuid, o.ref, o.fk_campaign, o.fk_housing_type, o.public_zone,';
		$sql .= ' o.town, o.date_start, o.date_end, o.capacity_available, o.title, o.description_public, o.verification_status, o.tms,';
		$sql .= ' h.label AS housing_type_label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_emergencyhouse_housing_type AS h';
		$sql .= ' ON h.rowid = o.fk_housing_type AND h.entity = o.entity';
		$sql .= ' WHERE o.entity = '.((int) $conf->entity);
		$sql .= ' AND o.fk_campaign = '.((int) $campaignId);
		$sql .= ' AND o.status = '.EmergencyHouseOffer::STATUS_PUBLISHED;
		$sql .= ' AND o.capacity_available > 0';
		if ($town !== '') {
			$sql .= " AND o.town LIKE '%".$this->db->escape($town)."%'";
		}
		$sql .= ' ORDER BY o.date_start ASC, o.rowid DESC';
		$sql .= $this->db->plimit(min(100, max(1, $limit)), max(0, $offset));
		return $this->fetchSafeRows($sql);
	}

	/**
	 * List safe public requests only when the campaign allows their exposure.
	 *
	 * @param int $campaignId Campaign ID
	 * @param int $limit Limit
	 * @param int $offset Offset
	 * @return array<int, array<string, int|string|null>>|false
	 */
	public function fetchPublicRequests($campaignId, $limit = 24, $offset = 0)
	{
		global $conf;

		$sql = 'SELECT r.rowid, r.public_uuid, r.ref, r.fk_campaign, r.person_count, r.remaining_count,';
		$sql .= ' r.date_start, r.date_end, r.desired_zone, r.search_radius, r.urgency_level, r.title, r.description_public, r.tms';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= ' WHERE r.entity = '.((int) $conf->entity);
		$sql .= ' AND r.fk_campaign = '.((int) $campaignId);
		$sql .= ' AND r.visibility = \'public\'';
		$sql .= ' AND r.verification_status = '.EmergencyHouseVerificationService::STATUS_VERIFIED;
		$sql .= ' AND r.status IN ('.EmergencyHouseRequest::STATUS_ACTIVE.','.EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED.')';
		$sql .= ' AND r.remaining_count > 0';
		$sql .= ' ORDER BY r.urgency_level DESC, r.date_start ASC, r.rowid DESC';
		$sql .= $this->db->plimit(min(100, max(1, $limit)), max(0, $offset));
		return $this->fetchSafeRows($sql);
	}

	/**
	 * Return active dictionary rows for public and back-office forms.
	 *
	 * @param string $dictionary Supported dictionary code
	 * @param int $entity Entity
	 * @return array<int, array{id:int,code:string,label:string,group?:string,type?:string}>|false
	 */
	public function fetchDictionary($dictionary, $entity)
	{
		$allowed = array(
			'housing_type' => array('c_emergencyhouse_housing_type', false),
			'feature' => array('c_emergencyhouse_feature', true),
			'report_reason' => array('c_emergencyhouse_report_reason', false),
		);
		if (!isset($allowed[$dictionary])) {
			$this->error = 'ErrorInvalidDictionary';
			return false;
		}
		$definition = $allowed[$dictionary];
		$sql = 'SELECT rowid, code, label';
		if ($definition[1]) {
			$sql .= ', feature_group, value_type';
		}
		$sql .= ' FROM '.MAIN_DB_PREFIX.$definition[0];
		$sql .= ' WHERE entity = '.((int) $entity).' AND active = 1';
		$sql .= ' ORDER BY position ASC, label ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$row = array('id' => (int) $obj->rowid, 'code' => (string) $obj->code, 'label' => (string) $obj->label);
			if ($definition[1]) {
				$row['group'] = (string) $obj->feature_group;
				$row['type'] = (string) $obj->value_type;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Fetch a published campaign in the account entity.
	 *
	 * @param int $entity Entity
	 * @param int $campaignId Campaign ID
	 * @return EmergencyHouseCampaign|false
	 */
	private function fetchPublishedCampaign($entity, $campaignId)
	{
		$campaign = new EmergencyHouseCampaign($this->db);
		if ($campaignId <= 0 || $campaign->fetch($campaignId) <= 0
			|| (int) $campaign->entity !== $entity
			|| (int) $campaign->status !== EmergencyHouseCampaign::STATUS_PUBLISHED
			|| (!empty($campaign->date_end) && (int) $campaign->date_end < dol_now())) {
			$this->error = 'ErrorCampaignNotOpen';
			return false;
		}
		return $campaign;
	}

	/**
	 * Replace offer feature relations inside the current transaction.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param array<int, array{code?:string,number?:float|int|null}> $features Feature data
	 * @return bool
	 */
	private function replaceOfferFeatures($offer, array $features)
	{
		foreach ($features as $featureId => $value) {
			if (!$this->dictionaryRowExists('c_emergencyhouse_feature', (int) $offer->entity, (int) $featureId)) {
				$this->error = 'ErrorInvalidFeature';
				return false;
			}
			$valueCode = isset($value['code']) ? (string) $value['code'] : 'yes';
			$valueNumber = isset($value['number']) && $value['number'] !== null ? (float) $value['number'] : null;
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_offer_feature';
			$sql .= ' (entity, fk_offer, fk_feature, value_code, value_number, date_creation) VALUES (';
			$sql .= ((int) $offer->entity).', '.((int) $offer->id).', '.((int) $featureId).',';
			$sql .= " '".$this->db->escape($valueCode)."',";
			$sql .= $valueNumber === null ? ' NULL,' : ' '.((float) $valueNumber).',';
			$sql .= " '".$this->db->idate(dol_now())."')";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}
		return true;
	}

	/**
	 * Replace requested housing types inside the current transaction.
	 *
	 * @param EmergencyHouseRequest $request Request
	 * @param array<int, string> $housingTypes Dictionary IDs and levels
	 * @return bool
	 */
	private function replaceRequestHousingTypes($request, array $housingTypes)
	{
		foreach ($housingTypes as $housingTypeId => $level) {
			if (!$this->dictionaryRowExists('c_emergencyhouse_housing_type', (int) $request->entity, (int) $housingTypeId)) {
				$this->error = 'ErrorInvalidHousingType';
				return false;
			}
			$level = $this->validatedChoice($level, array('required', 'wanted', 'indifferent'), 'wanted');
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_request_housing_type';
			$sql .= ' (entity, fk_request, fk_housing_type, preference_level, date_creation) VALUES (';
			$sql .= ((int) $request->entity).', '.((int) $request->id).', '.((int) $housingTypeId).',';
			$sql .= " '".$this->db->escape($level)."', '".$this->db->idate(dol_now())."')";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}
		return true;
	}

	/**
	 * Replace feature criteria inside the current transaction.
	 *
	 * @param EmergencyHouseRequest $request Request
	 * @param array<int, string> $criteria Dictionary IDs and levels
	 * @return bool
	 */
	private function replaceRequestCriteria($request, array $criteria)
	{
		foreach ($criteria as $featureId => $level) {
			if (!$this->dictionaryRowExists('c_emergencyhouse_feature', (int) $request->entity, (int) $featureId)) {
				$this->error = 'ErrorInvalidFeature';
				return false;
			}
			$level = $this->validatedChoice($level, array('required', 'preferred', 'indifferent'), 'indifferent');
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_request_criterion';
			$sql .= ' (entity, fk_request, fk_feature, criterion_level, expected_code, date_creation) VALUES (';
			$sql .= ((int) $request->entity).', '.((int) $request->id).', '.((int) $featureId).',';
			$sql .= " '".$this->db->escape($level)."', 'yes', '".$this->db->idate(dol_now())."')";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return false;
			}
		}
		return true;
	}

	/**
	 * Check a dictionary identifier in the current entity.
	 *
	 * @param string $table Table without prefix
	 * @param int $entity Entity
	 * @param int $id Row ID
	 * @return bool
	 */
	private function dictionaryRowExists($table, $entity, $id)
	{
		if ($id <= 0) {
			return false;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$table;
		$sql .= ' WHERE rowid = '.$id.' AND entity = '.$entity.' AND active = 1';
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) > 0;
	}

	/**
	 * Fetch query rows into safe scalar arrays.
	 *
	 * @param string $sql SQL
	 * @return array<int, array<string, int|string|null>>|false
	 */
	private function fetchSafeRows($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$row = array();
			foreach (get_object_vars($obj) as $key => $value) {
				if (is_int($value) || is_string($value) || $value === null) {
					$row[$key] = $value;
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Lock one module row in a caller-owned transaction.
	 *
	 * @param string $table Table without prefix
	 * @param int $entity Entity
	 * @param int $id Row ID
	 * @return bool
	 */
	private function lockRow($table, $entity, $id)
	{
		$allowed = array('emergencyhouse_offer', 'emergencyhouse_request');
		if (!in_array($table, $allowed, true)) {
			$this->error = 'ErrorInvalidObjectType';
			return false;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$table;
		$sql .= ' WHERE rowid = '.$id.' AND entity = '.$entity.' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) !== 1) {
			$this->error = $resql ? 'ErrorRecordNotFound' : $this->db->lasterror();
			return false;
		}
		return true;
	}

	/**
	 * Delete normalized relations before recreating the submitted set.
	 *
	 * @param string $table Table without prefix
	 * @param string $foreignKey Foreign key
	 * @param int $entity Entity
	 * @param int $objectId Object ID
	 * @return bool
	 */
	private function deleteRelations($table, $foreignKey, $entity, $objectId)
	{
		$allowed = array(
			'emergencyhouse_offer_feature' => 'fk_offer',
			'emergencyhouse_request_housing_type' => 'fk_request',
			'emergencyhouse_request_criterion' => 'fk_request',
		);
		if (!isset($allowed[$table]) || $allowed[$table] !== $foreignKey) {
			$this->error = 'ErrorInvalidRelation';
			return false;
		}
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$table;
		$sql .= ' WHERE entity = '.$entity.' AND '.$foreignKey.' = '.$objectId;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}

	/**
	 * Compute a bounded listing expiration.
	 *
	 * @param EmergencyHouseCampaign $campaign Campaign
	 * @param int $start Start timestamp
	 * @return int
	 */
	private function listingExpiration($campaign, $start)
	{
		$default = dol_time_plus_duree(max(dol_now(), $start), 30, 'd');
		return !empty($campaign->date_end) ? min($default, (int) $campaign->date_end) : $default;
	}

	/**
	 * Offer encryption context.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @return string
	 */
	private function offerContext($offer)
	{
		return 'emergencyhouse|offer|'.$offer->entity.'|'.$offer->public_uuid;
	}

	/**
	 * Request encryption context.
	 *
	 * @param EmergencyHouseRequest $request Request
	 * @return string
	 */
	private function requestContext($request)
	{
		return 'emergencyhouse|request|'.$request->entity.'|'.$request->public_uuid;
	}

	/**
	 * Geocode and protect an offer's exact coordinates when enabled.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param string $address Exact street address
	 * @return bool
	 */
	private function geocodeOffer($offer, $address)
	{
		if (getDolGlobalString('EMERGENCYHOUSE_GEOCODING_PROVIDER', 'disabled') === 'disabled') {
			return true;
		}
		$queryParts = array_filter(
			array($address, trim((string) $offer->zip.' '.(string) $offer->town)),
			static function ($value) {
				return is_string($value) && $value !== '';
			}
		);
		$coordinates = $this->geocoding->geocodeExact(implode(', ', $queryParts));
		if (!is_array($coordinates)) {
			$this->error = $this->geocoding->error;
			return false;
		}
		$encrypted = $this->geocoding->encryptCoordinates(
			(int) $offer->entity,
			(string) $offer->public_uuid,
			(float) $coordinates['latitude'],
			(float) $coordinates['longitude']
		);
		if (!is_array($encrypted)) {
			$this->error = $this->geocoding->error;
			return false;
		}
		$offer->latitude_encrypted = $encrypted['latitude'];
		$offer->longitude_encrypted = $encrypted['longitude'];
		$offer->geo_cell = $this->geocoding->buildCoarseCell(
			(float) $coordinates['latitude'],
			(float) $coordinates['longitude']
		);
		return true;
	}

	/**
	 * Build a coarse request location when geocoding is enabled.
	 *
	 * @param EmergencyHouseRequest $request Request
	 * @return bool
	 */
	private function geocodeRequest($request)
	{
		if (getDolGlobalString('EMERGENCYHOUSE_GEOCODING_PROVIDER', 'disabled') === 'disabled') {
			return true;
		}
		$queryParts = array_filter(
			array($request->desired_zone, $request->desired_zip, $request->desired_town),
			static function ($value) {
				return is_string($value) && trim($value) !== '';
			}
		);
		if (empty($queryParts)) {
			return true;
		}
		$coordinates = $this->geocoding->geocodeExact(implode(', ', $queryParts));
		if (!is_array($coordinates)) {
			$this->error = $this->geocoding->error;
			return false;
		}
		$request->geo_cell = $this->geocoding->buildCoarseCell(
			(float) $coordinates['latitude'],
			(float) $coordinates['longitude']
		);
		return true;
	}

	/**
	 * Normalize an optional string.
	 *
	 * @param int|string|null $value Value
	 * @return string|null
	 */
	private function nullableTrimmed($value)
	{
		$normalized = trim((string) $value);
		return $normalized === '' ? null : $normalized;
	}

	/**
	 * Validate a controlled string.
	 *
	 * @param string $value Value
	 * @param array<int, string> $allowed Allowed values
	 * @param string $default Default
	 * @return string
	 */
	private function validatedChoice($value, array $allowed, $default)
	{
		return in_array($value, $allowed, true) ? $value : $default;
	}
}
