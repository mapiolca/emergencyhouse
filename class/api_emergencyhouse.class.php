<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use Luracast\Restler\RestException;

dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/campaign.class.php');
dol_include_once('/emergencyhouse/class/capacityservice.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/report.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/solicitation.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse.lib.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

/**
 * Operator REST API.
 *
 * Sensitive encrypted payloads are intentionally never serialized.
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class EmergencyHouse extends DolibarrApi
{
	/** @var DoliDB */
	public $db;

	/**
	 * @var array<string, array{class:string,table:string,element:string,permission:string}>
	 */
	private $types = array(
		'campaigns' => array('class' => 'EmergencyHouseCampaign', 'table' => 'emergencyhouse_campaign', 'element' => 'campaign', 'permission' => 'campaign'),
		'offers' => array('class' => 'EmergencyHouseOffer', 'table' => 'emergencyhouse_offer', 'element' => 'offer', 'permission' => 'listing'),
		'requests' => array('class' => 'EmergencyHouseRequest', 'table' => 'emergencyhouse_request', 'element' => 'request', 'permission' => 'listing'),
		'solicitations' => array('class' => 'EmergencyHouseSolicitation', 'table' => 'emergencyhouse_solicitation', 'element' => 'solicitation', 'permission' => 'solicitation'),
		'allocations' => array('class' => 'EmergencyHouseAllocation', 'table' => 'emergencyhouse_allocation', 'element' => 'allocation', 'permission' => 'allocation'),
		'reports' => array('class' => 'EmergencyHouseReport', 'table' => 'emergencyhouse_report', 'element' => 'report', 'permission' => 'report'),
	);

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $db, $langs;

		$this->db = $db;
		$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
	}

	/**
	 * List safe object representations.
	 *
	 * @url GET /{type}
	 *
	 * @param string $type Collection code
	 * @param string $sortfield Sort field
	 * @param string $sortorder Sort order
	 * @param int $limit Page size
	 * @param int $page Page number
	 * @param int $status Optional status, -1 for all
	 * @param int $campaign_id Optional campaign ID
	 * @return array<int, array<string, scalar|null>>
	 * @throws RestException
	 */
	public function index($type, $sortfield = 't.date_creation', $sortorder = 'DESC', $limit = 50, $page = 0, $status = -1, $campaign_id = 0)
	{
		$definition = $this->getDefinition($type, 'read');
		$allowedSorts = array('t.rowid', 't.ref', 't.status', 't.date_creation', 't.tms');
		if (!in_array($sortfield, $allowedSorts, true)) {
			$sortfield = 't.date_creation';
		}
		$sortorder = strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC';
		$limit = max(1, min(100, (int) $limit));
		$page = max(0, (int) $page);
		$entities = $this->entityScope($definition['element']);
		$sql = 'SELECT t.rowid FROM '.MAIN_DB_PREFIX.$definition['table'].' AS t';
		$sql .= ' WHERE t.entity IN ('.implode(',', $entities).')';
		if ($status >= 0) {
			$sql .= ' AND t.status = '.((int) $status);
		}
		if ($campaign_id > 0 && $type !== 'campaigns') {
			$sql .= ' AND t.fk_campaign = '.((int) $campaign_id);
		}
		$sql .= ' ORDER BY '.$sortfield.' '.$sortorder;
		$sql .= $this->db->plimit($limit, $page * $limit);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->throwTranslated(503, 'ErrorDatabaseQuery');
		}
		$records = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			/** @var EmergencyHouseCommonObject $object */
			$object = new $definition['class']($this->db);
			if ($object->fetch((int) $row->rowid) > 0) {
				$records[] = $this->serializeObject($object);
			}
		}
		return $records;
	}

	/**
	 * Get one safe object representation.
	 *
	 * @url GET /{type}/{id}
	 *
	 * @param string $type Collection code
	 * @param int $id Object ID
	 * @return array<string, scalar|null>
	 * @throws RestException
	 */
	public function get($type, $id)
	{
		$definition = $this->getDefinition($type, 'read');
		/** @var EmergencyHouseCommonObject $object */
		$object = new $definition['class']($this->db);
		if ($id <= 0 || $object->fetch($id) <= 0) {
			$this->throwTranslated(404, 'ErrorRecordNotFound');
		}
		if (!emergencyhouseCanDo(DolibarrApiAccess::$user, $definition['permission'], 'read', $object)
			&& !emergencyhouseCanDo(DolibarrApiAccess::$user, $definition['permission'], 'write', $object)) {
			$this->throwTranslated(403, 'ErrorForbidden');
		}
		return $this->serializeObject($object);
	}

	/**
	 * Create an allocation through the capacity service.
	 *
	 * @url POST /allocations
	 *
	 * @param array<string, scalar|null>|null $request_data Request payload
	 * @return int Allocation ID
	 * @throws RestException
	 */
	public function postAllocation($request_data = null)
	{
		$this->assertApiAvailable();
		$apiUser = DolibarrApiAccess::$user;
		if (!emergencyhouseCanDo($apiUser, 'allocation', 'write')) {
			$this->throwTranslated(403, 'ErrorForbidden');
		}
		if (!is_array($request_data)) {
			$this->throwTranslated(400, 'ErrorInvalidRequest');
		}
		$offerId = isset($request_data['fk_offer']) ? (int) $request_data['fk_offer'] : 0;
		$requestId = isset($request_data['fk_request']) ? (int) $request_data['fk_request'] : 0;
		$offer = new EmergencyHouseOffer($this->db);
		$request = new EmergencyHouseRequest($this->db);
		if ($offer->fetch($offerId) <= 0
			|| $request->fetch($requestId) <= 0
			|| (int) $offer->entity !== (int) $request->entity
			|| (int) $offer->fk_campaign !== (int) $request->fk_campaign) {
			$this->throwTranslated(400, 'ErrorCampaignMismatch');
		}
		$allocation = new EmergencyHouseAllocation($this->db);
		$allocation->entity = (int) $offer->entity;
		$allocation->fk_campaign = (int) $offer->fk_campaign;
		$allocation->fk_offer = $offerId;
		$allocation->fk_request = $requestId;
		$allocation->fk_solicitation = !empty($request_data['fk_solicitation']) ? (int) $request_data['fk_solicitation'] : null;
		$allocation->quantity = isset($request_data['quantity']) ? (int) $request_data['quantity'] : 0;
		$allocation->date_start = isset($request_data['date_start']) ? (int) $request_data['date_start'] : 0;
		$allocation->date_end = !empty($request_data['date_end']) ? (int) $request_data['date_end'] : null;
		$allocation->address_share_authorized = !empty($request_data['address_share_authorized']) ? 1 : 0;
		$allocation->fk_operator = (int) $apiUser->id;
		$allocation->status = EmergencyHouseAllocation::STATUS_PROPOSED;
		$allocation->model_pdf = getDolGlobalString('EMERGENCYHOUSE_ALLOCATION_DEFAULT_MODEL', 'emergencyhouse_agreement');
		$allocation->context['trigger_reason'] = 'api_allocation';
		$service = new EmergencyHouseCapacityService($this->db);
		$result = $service->reserve($allocation, $apiUser);
		if ($result <= 0) {
			throw new RestException(409, emergencyhouseGetUserErrorMessage((string) $service->error));
		}
		return (int) $allocation->id;
	}

	/**
	 * Apply a controlled object status transition.
	 *
	 * @url PUT /{type}/{id}/status
	 *
	 * @param string $type Collection code
	 * @param int $id Object ID
	 * @param array<string, scalar|null>|null $request_data Request payload
	 * @return array<string, scalar|null>
	 * @throws RestException
	 */
	public function putStatus($type, $id, $request_data = null)
	{
		$definition = $this->getDefinition($type, 'write');
		if (!is_array($request_data) || !isset($request_data['status'])) {
			$this->throwTranslated(400, 'ErrorInvalidRequest');
		}
		/** @var EmergencyHouseCommonObject $object */
		$object = new $definition['class']($this->db);
		if ($id <= 0 || $object->fetch($id) <= 0) {
			$this->throwTranslated(404, 'ErrorRecordNotFound');
		}
		if (!emergencyhouseCanDo(DolibarrApiAccess::$user, $definition['permission'], 'write', $object)) {
			$this->throwTranslated(403, 'ErrorForbidden');
		}
		$newStatus = (int) $request_data['status'];
		$reasonCode = isset($request_data['reason_code']) ? (string) $request_data['reason_code'] : '';
		if ($object instanceof EmergencyHouseAllocation && $newStatus === EmergencyHouseAllocation::STATUS_CANCELLED) {
			$capacity = new EmergencyHouseCapacityService($this->db);
			$result = $capacity->cancel($object, DolibarrApiAccess::$user, $reasonCode);
			if ($result <= 0) {
				throw new RestException(409, emergencyhouseGetUserErrorMessage((string) $capacity->error));
			}
		} elseif (method_exists($object, 'setStatus')) {
			if ($object instanceof EmergencyHouseAllocation || $object instanceof EmergencyHouseSolicitation) {
				$result = $object->setStatus($newStatus, DolibarrApiAccess::$user, 'api_status_change', $reasonCode !== '' ? $reasonCode : null);
			} else {
				$result = $object->setStatus($newStatus, DolibarrApiAccess::$user, 'api_status_change');
			}
			if ($result <= 0) {
				throw new RestException(409, emergencyhouseGetUserErrorMessage((string) $object->error));
			}
		} else {
			$this->throwTranslated(405, 'ErrorActionNotSupported');
		}
		if ($object->fetch($id) <= 0) {
			$this->throwTranslated(500, 'ErrorRecordNotFound');
		}
		return $this->serializeObject($object);
	}

	/**
	 * Resolve a type and permission.
	 *
	 * @param string $type Type
	 * @param string $action Permission action
	 * @return array{class:string,table:string,element:string,permission:string}
	 * @throws RestException
	 */
	private function getDefinition($type, $action)
	{
		$this->assertApiAvailable();
		if (!isset($this->types[$type])) {
			$this->throwTranslated(404, 'ErrorInvalidObjectType');
		}
		$definition = $this->types[$type];
		$requiredAction = $action === 'read' && in_array($type, array('solicitations', 'allocations', 'reports'), true)
			? 'write'
			: $action;
		if (!emergencyhouseCanDo(DolibarrApiAccess::$user, $definition['permission'], $requiredAction)) {
			$this->throwTranslated(403, 'ErrorForbidden');
		}
		return $definition;
	}

	/**
	 * Enforce module and API activation.
	 *
	 * @return void
	 * @throws RestException
	 */
	private function assertApiAvailable()
	{
		if (!isModEnabled('emergencyhouse')
			|| !getDolGlobalInt('EMERGENCYHOUSE_API_ENABLED')
			|| !emergencyhouseCanDo(DolibarrApiAccess::$user, 'api', 'use')) {
			$this->throwTranslated(403, 'ErrorApiDisabled');
		}
	}

	/**
	 * Return an entity scope.
	 *
	 * @param string $element Object element
	 * @return array<int, int>
	 */
	private function entityScope($element)
	{
		global $conf;

		$entities = array_filter(array_map('intval', explode(',', (string) getEntity($element))));
		return empty($entities) ? array((int) $conf->entity) : array_values(array_unique($entities));
	}

	/**
	 * Serialize a strict whitelist of non-sensitive properties.
	 *
	 * @param EmergencyHouseCommonObject $object Object
	 * @return array<string, scalar|null>
	 */
	private function serializeObject($object)
	{
		$data = array(
			'id' => (int) $object->id,
			'entity' => (int) $object->entity,
			'ref' => (string) $object->ref,
			'public_uuid' => (string) $object->public_uuid,
			'status' => (int) $object->status,
			'date_creation' => !empty($object->date_creation) ? (int) $object->date_creation : null,
			'tms' => !empty($object->tms) ? (int) $object->tms : null,
		);
		if ($object instanceof EmergencyHouseCampaign) {
			$data += array(
				'label' => (string) $object->label,
				'slug' => (string) $object->slug,
				'description_public' => (string) $object->description_public,
				'coordinator_name' => (string) $object->coordinator_name,
				'official_phone' => (string) $object->official_phone,
				'official_email' => (string) $object->official_email,
				'date_start' => (int) $object->date_start,
				'date_end' => !empty($object->date_end) ? (int) $object->date_end : null,
				'timezone' => (string) $object->timezone,
			);
		} elseif ($object instanceof EmergencyHouseOffer) {
			$data += array(
				'fk_campaign' => (int) $object->fk_campaign,
				'fk_housing_type' => (int) $object->fk_housing_type,
				'title' => (string) $object->title,
				'description_public' => (string) $object->description_public,
				'public_zone' => (string) $object->public_zone,
				'zip' => (string) $object->zip,
				'town' => (string) $object->town,
				'date_start' => (int) $object->date_start,
				'date_end' => !empty($object->date_end) ? (int) $object->date_end : null,
				'capacity_total' => (int) $object->capacity_total,
				'capacity_available' => (int) $object->capacity_available,
				'verification_status' => (int) $object->verification_status,
			);
		} elseif ($object instanceof EmergencyHouseRequest) {
			$data += array(
				'fk_campaign' => (int) $object->fk_campaign,
				'title' => (string) $object->title,
				'description_public' => (string) $object->description_public,
				'person_count' => (int) $object->person_count,
				'remaining_count' => (int) $object->remaining_count,
				'date_start' => (int) $object->date_start,
				'date_end' => !empty($object->date_end) ? (int) $object->date_end : null,
				'desired_zone' => (string) $object->desired_zone,
				'search_radius' => (int) $object->search_radius,
				'urgency_level' => (int) $object->urgency_level,
				'visibility' => (string) $object->visibility,
				'verification_status' => (int) $object->verification_status,
			);
		} elseif ($object instanceof EmergencyHouseSolicitation) {
			$data += array(
				'fk_campaign' => (int) $object->fk_campaign,
				'fk_offer' => (int) $object->fk_offer,
				'fk_request' => (int) $object->fk_request,
				'fk_match' => !empty($object->fk_match) ? (int) $object->fk_match : null,
				'initiator_direction' => (string) $object->initiator_direction,
				'initiator_contact_consent' => (int) $object->initiator_contact_consent,
				'recipient_contact_consent' => (int) $object->recipient_contact_consent,
				'address_share_authorized' => (int) $object->address_share_authorized,
				'date_expiration' => !empty($object->date_expiration) ? (int) $object->date_expiration : null,
			);
		} elseif ($object instanceof EmergencyHouseAllocation) {
			$data += array(
				'fk_campaign' => (int) $object->fk_campaign,
				'fk_offer' => (int) $object->fk_offer,
				'fk_request' => (int) $object->fk_request,
				'fk_solicitation' => !empty($object->fk_solicitation) ? (int) $object->fk_solicitation : null,
				'quantity' => (int) $object->quantity,
				'date_start' => (int) $object->date_start,
				'date_end' => !empty($object->date_end) ? (int) $object->date_end : null,
				'host_confirmed' => (int) $object->host_confirmed,
				'requester_confirmed' => (int) $object->requester_confirmed,
				'address_share_authorized' => (int) $object->address_share_authorized,
				'incident_open' => (int) $object->incident_open,
			);
		} elseif ($object instanceof EmergencyHouseReport) {
			$data += array(
				'fk_campaign' => (int) $object->fk_campaign,
				'object_type' => (string) $object->object_type,
				'fk_object' => (int) $object->fk_object,
				'fk_report_reason' => (int) $object->fk_report_reason,
				'severity' => (int) $object->severity,
				'fk_assigned_user' => !empty($object->fk_assigned_user) ? (int) $object->fk_assigned_user : null,
				'retention_hold' => (int) $object->retention_hold,
				'date_closure' => !empty($object->date_closure) ? (int) $object->date_closure : null,
			);
		}
		return $data;
	}

	/**
	 * Throw a translated REST error.
	 *
	 * @param int $status HTTP status
	 * @param string $translationKey Translation key
	 * @return never
	 * @throws RestException
	 */
	private function throwTranslated($status, $translationKey)
	{
		global $langs;

		throw new RestException($status, $langs->trans($translationKey));
	}
}
