<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');

/**
 * Atomic capacity reservation and release.
 */
class EmergencyHouseCapacityService
{
	/** @var DoliDB */
	private $db;
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
	}

	/**
	 * Create an allocation under locked offer/request capacity.
	 *
	 * @param EmergencyHouseAllocation $allocation Allocation to create
	 * @param User                     $user User
	 * @return int
	 */
	public function reserve($allocation, $user)
	{
		if (!$allocation->validateBusinessRules()) {
			$this->error = $allocation->error;
			$this->errors = $allocation->errors;
			return -1;
		}
		if (empty($allocation->date_creation)) {
			$allocation->date_creation = dol_now();
		}
		if (empty($allocation->ref)) {
			$nextRef = $allocation->getNextNumRef();
			if (!is_string($nextRef) || $nextRef === '') {
				$this->error = !empty($allocation->error) ? $allocation->error : 'ErrorNumberingModel';
				return -1;
			}
			$allocation->ref = $nextRef;
		}
		$this->db->begin();
		$offer = $this->lockOffer((int) $allocation->entity, (int) $allocation->fk_offer);
		$request = $this->lockRequest((int) $allocation->entity, (int) $allocation->fk_request);
		if (!is_object($offer) || !is_object($request)) {
			$this->db->rollback();
			return -1;
		}
		if ((int) $offer->fk_campaign !== (int) $allocation->fk_campaign
			|| (int) $request->fk_campaign !== (int) $allocation->fk_campaign) {
			$this->error = 'ErrorCampaignMismatch';
			$this->db->rollback();
			return -1;
		}
		if ((int) $offer->status !== EmergencyHouseOffer::STATUS_PUBLISHED
			|| !in_array((int) $request->status, array(EmergencyHouseRequest::STATUS_ACTIVE, EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED), true)) {
			$this->error = 'ErrorObjectNotAllocatable';
			$this->db->rollback();
			return -1;
		}
		$allocationEnd = empty($allocation->date_end) ? PHP_INT_MAX : (int) $allocation->date_end;
		$offerEnd = empty($offer->date_end) ? PHP_INT_MAX : (int) $this->db->jdate($offer->date_end);
		$requestEnd = empty($request->date_end) ? PHP_INT_MAX : (int) $this->db->jdate($request->date_end);
		if ((int) $allocation->date_start < (int) $this->db->jdate($offer->date_start)
			|| (int) $allocation->date_start < (int) $this->db->jdate($request->date_start)
			|| $allocationEnd > $offerEnd
			|| $allocationEnd > $requestEnd) {
			$this->error = 'ErrorAllocationOutsideAvailability';
			$this->db->rollback();
			return -1;
		}

		$available = $this->availableForPeriod(
			(int) $allocation->entity,
			(int) $allocation->fk_offer,
			(int) $offer->capacity_total,
			(int) $allocation->date_start,
			$allocation->date_end
		);
		if ($available < $allocation->quantity || (int) $request->remaining_count < $allocation->quantity) {
			$this->error = 'ErrorInsufficientCapacity';
			$this->db->rollback();
			return -1;
		}
		if (!$request->group_divisible && $allocation->quantity !== (int) $request->remaining_count) {
			$this->error = 'ErrorGroupCannotBeSplit';
			$this->db->rollback();
			return -1;
		}
		if ($request->group_divisible && $allocation->quantity < (int) $request->minimum_group_size) {
			$this->error = 'ErrorMinimumGroupSize';
			$this->db->rollback();
			return -1;
		}

		$result = $allocation->createInsideCapacityTransaction($user);
		if ($result <= 0) {
			$this->error = $allocation->error;
			$this->errors = $allocation->errors;
			$this->db->rollback();
			return $result;
		}
		$newRemaining = (int) $request->remaining_count - (int) $allocation->quantity;
		$newRequestStatus = $newRemaining === 0 ? EmergencyHouseRequest::STATUS_FULFILLED : EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED;
		$requestObject = new EmergencyHouseRequest($this->db);
		if ($requestObject->fetch((int) $allocation->fk_request) <= 0) {
			$this->error = $requestObject->error ?: 'ErrorRequestNotFound';
			$this->db->rollback();
			return -1;
		}
		$requestObject->remaining_count = $newRemaining;
		$requestObject->status = $newRequestStatus;
		$requestObject->context['trigger_reason'] = 'allocation_capacity_change';
		$requestObject->context['changed_fields'] = array('remaining_count', 'status');
		if ($requestObject->updateInsideServiceTransaction($user) <= 0) {
			$this->error = $requestObject->error;
			$this->db->rollback();
			return -1;
		}
		if (!$this->refreshCurrentOfferCapacity((int) $allocation->entity, (int) $allocation->fk_offer, (int) $offer->capacity_total, $user)) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return $result;
	}

	/**
	 * Cancel an allocation and release request capacity atomically.
	 *
	 * @param EmergencyHouseAllocation $allocation Allocation
	 * @param User                     $user User
	 * @param string                   $reasonCode Cancellation code
	 * @return int
	 */
	public function cancel($allocation, $user, $reasonCode)
	{
		if ($allocation->status === EmergencyHouseAllocation::STATUS_CANCELLED
			|| $allocation->status === EmergencyHouseAllocation::STATUS_COMPLETED) {
			$this->error = 'ErrorInvalidStatusTransition';
			return -1;
		}
		if ($reasonCode === '') {
			$this->error = 'ErrorCancellationReasonRequired';
			return -1;
		}

		$this->db->begin();
		$offer = $this->lockOffer((int) $allocation->entity, (int) $allocation->fk_offer);
		$request = $this->lockRequest((int) $allocation->entity, (int) $allocation->fk_request);
		if (!is_object($offer) || !is_object($request)) {
			$this->db->rollback();
			return -1;
		}
		$newRemaining = min((int) $request->person_count, (int) $request->remaining_count + (int) $allocation->quantity);
		$newRequestStatus = $newRemaining === (int) $request->person_count
			? EmergencyHouseRequest::STATUS_ACTIVE
			: EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED;
		$requestObject = new EmergencyHouseRequest($this->db);
		if ($requestObject->fetch((int) $allocation->fk_request) <= 0) {
			$this->error = $requestObject->error ?: 'ErrorRequestNotFound';
			$this->db->rollback();
			return -1;
		}
		$requestObject->remaining_count = $newRemaining;
		$requestObject->status = $newRequestStatus;
		$requestObject->context['trigger_reason'] = 'allocation_cancellation';
		$requestObject->context['changed_fields'] = array('remaining_count', 'status');
		if ($requestObject->updateInsideServiceTransaction($user) <= 0) {
			$this->error = $requestObject->error;
			$this->db->rollback();
			return -1;
		}
		$oldStatus = (int) $allocation->status;
		$allocation->status = EmergencyHouseAllocation::STATUS_CANCELLED;
		$allocation->cancellation_reason = $reasonCode;
		$allocation->context['trigger_reason'] = 'cancellation';
		$allocation->context['changed_fields'] = array('status', 'cancellation_reason');
		$allocation->context['old_status'] = $oldStatus;
		$allocation->context['new_status'] = EmergencyHouseAllocation::STATUS_CANCELLED;
		if ($allocation->updateInsideServiceTransaction($user) <= 0) {
			$this->error = $allocation->error;
			$this->db->rollback();
			return -1;
		}
		if (!$this->refreshCurrentOfferCapacity((int) $allocation->entity, (int) $allocation->fk_offer, (int) $offer->capacity_total, $user)) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Capacity for an exact period.
	 *
	 * @param int      $entity Entity
	 * @param int      $offerId Offer ID
	 * @param int      $totalCapacity Total capacity
	 * @param int      $start Start
	 * @param int|null $end End
	 * @return int
	 */
	public function availableForPeriod($entity, $offerId, $totalCapacity, $start, $end)
	{
		$effectiveEnd = empty($end) ? 32503680000 : $end;
		$sql = 'SELECT COALESCE(SUM(quantity), 0) AS reserved';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_allocation';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_offer = '.((int) $offerId);
		$sql .= ' AND status IN ('.EmergencyHouseAllocation::STATUS_PROPOSED.','.EmergencyHouseAllocation::STATUS_CONFIRMED.','.EmergencyHouseAllocation::STATUS_ACTIVE.','.EmergencyHouseAllocation::STATUS_INCIDENT.')';
		$sql .= " AND date_start <= '".$this->db->idate($effectiveEnd)."'";
		$sql .= " AND (date_end IS NULL OR date_end >= '".$this->db->idate($start)."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		return max(0, $totalCapacity - (is_object($obj) ? (int) $obj->reserved : 0));
	}

	/**
	 * Lock offer.
	 *
	 * @param int $entity Entity
	 * @param int $offerId Offer ID
	 * @return object|false
	 */
	private function lockOffer($entity, $offerId)
	{
		$sql = 'SELECT rowid, fk_campaign, capacity_total, status, date_start, date_end FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer';
		$sql .= ' WHERE rowid = '.((int) $offerId).' AND entity = '.((int) $entity).' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			$this->error = 'ErrorOfferNotFound';
			return false;
		}
		return $obj;
	}

	/**
	 * Lock request.
	 *
	 * @param int $entity Entity
	 * @param int $requestId Request ID
	 * @return object|false
	 */
	private function lockRequest($entity, $requestId)
	{
		$sql = 'SELECT rowid, fk_campaign, person_count, remaining_count, group_divisible, minimum_group_size, status, date_start, date_end';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_request';
		$sql .= ' WHERE rowid = '.((int) $requestId).' AND entity = '.((int) $entity).' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			$this->error = 'ErrorRequestNotFound';
			return false;
		}
		return $obj;
	}

	/**
	 * Refresh current capacity indicator.
	 *
	 * @param int $entity Entity
	 * @param int $offerId Offer ID
	 * @param int $totalCapacity Total
	 * @param User $user User
	 * @return bool
	 */
	private function refreshCurrentOfferCapacity($entity, $offerId, $totalCapacity, $user)
	{
		$availableNow = $this->availableForPeriod($entity, $offerId, $totalCapacity, dol_now(), dol_now());
		if ($availableNow < 0) {
			return false;
		}
		$offer = new EmergencyHouseOffer($this->db);
		if ($offer->fetch($offerId) <= 0 || (int) $offer->entity !== (int) $entity) {
			$this->error = $offer->error ?: 'ErrorOfferNotFound';
			return false;
		}
		$offer->capacity_available = $availableNow;
		$offer->context['trigger_reason'] = 'allocation_capacity_change';
		$offer->context['changed_fields'] = array('capacity_available');
		if ($offer->updateInsideServiceTransaction($user) <= 0) {
			$this->error = $offer->error;
			return false;
		}
		return true;
	}
}
