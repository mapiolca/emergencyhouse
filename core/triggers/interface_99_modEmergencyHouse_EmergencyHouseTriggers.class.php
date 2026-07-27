<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/emergencyhouse/class/auditservice.class.php');
dol_include_once('/emergencyhouse/class/matchingservice.class.php');
dol_include_once('/emergencyhouse/class/notificationservice.class.php');

/**
 * Emergency House CRUD trigger handler.
 */
class InterfaceEmergencyHouseTriggers extends DolibarrTriggers
{
	/** @var string */
	public $family = 'emergencyhouse';
	/** @var string */
	public $description = 'EmergencyHouseTriggerDescription';
	/** @var string */
	public $version = '1.0.0';
	/** @var string */
	public $picto = 'emergencyhouse@emergencyhouse';

	/**
	 * Execute a CRUD trigger.
	 *
	 * @param string          $action Trigger code
	 * @param CommonObject    $object Object
	 * @param User            $user User
	 * @param Translate       $langs Languages
	 * @param Conf            $conf Configuration
	 * @return int
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		unset($langs);

		if (!isModEnabled('emergencyhouse') || strpos($action, 'EMERGENCYHOUSE_') !== 0) {
			return 0;
		}
		if (!preg_match('/^EMERGENCYHOUSE_(CAMPAIGN|OFFER|REQUEST|SOLICITATION|ALLOCATION|REPORT)_(CREATE|UPDATE|DELETE)$/', $action, $matches)) {
			return 0;
		}
		if (!is_object($object) || empty($object->id) || empty($object->entity)) {
			$this->error = 'ErrorInvalidTriggerObject';
			return -1;
		}

		$objectType = strtolower($matches[1]);
		$operation = strtolower($matches[2]);
		$campaignId = $objectType === 'campaign'
			? (int) $object->id
			: (!empty($object->fk_campaign) ? (int) $object->fk_campaign : null);
		$context = is_array($object->context) ? $object->context : array();
		$reason = isset($context['trigger_reason']) && is_string($context['trigger_reason'])
			? $context['trigger_reason']
			: $operation;
		$metadata = array('operation' => $operation, 'reason' => $reason);
		if (isset($context['changed_fields']) && is_array($context['changed_fields'])) {
			$metadata['changed_fields'] = implode(',', array_map('strval', $context['changed_fields']));
		}

		$audit = new EmergencyHouseAuditService($this->db);
		$actorType = !empty($context['public_account_id']) ? 'public_account' : 'dolibarr_user';
		$actorId = !empty($context['public_account_id']) ? (int) $context['public_account_id'] : (int) $user->id;
		if ($audit->record(
			(int) $object->entity,
			$actorType,
			$actorId,
			$action,
			$objectType,
			(int) $object->id,
			$campaignId,
			$reason,
			null,
			$metadata
		) < 0) {
			$this->error = $audit->error;
			return -1;
		}

		if (in_array($objectType, array('offer', 'request'), true) && $operation !== 'delete') {
			$matching = new EmergencyHouseMatchingService($this->db);
			$revision = !empty($object->tms) ? (string) $object->tms : (string) dol_now();
			if ($matching->queueRecalculation(
				(int) $object->entity,
				$objectType,
				(int) $object->id,
				(int) $campaignId,
				$revision
			) < 0) {
				$this->error = $matching->error;
				return -1;
			}
		}

		if ($operation !== 'delete' && $reason !== 'recipient_read' && in_array($objectType, array('solicitation', 'allocation'), true)) {
			$actorAccountId = !empty($context['public_account_id']) ? (int) $context['public_account_id'] : null;
			$notifications = new EmergencyHouseNotificationService($this->db);
			$eventCode = $objectType.'_'.$operation;
			$templateCode = $objectType.'_'.$operation;
			$revision = array(
				'context' => $context,
				'status' => isset($object->status) ? (int) $object->status : null,
				'tms' => isset($object->tms) ? (string) $object->tms : null,
				'date_response' => isset($object->date_response) ? $object->date_response : null,
				'date_closure' => isset($object->date_closure) ? $object->date_closure : null,
				'host_confirmation_date' => isset($object->host_confirmation_date) ? $object->host_confirmation_date : null,
				'requester_confirmation_date' => isset($object->requester_confirmation_date) ? $object->requester_confirmation_date : null,
			);
			$idempotencySource = $objectType.'-'.((int) $object->id).'-'.$operation.'-'.$reason.'-'.hash(
				'sha256',
				json_encode($revision) ?: $reason
			);
			$queueResult = $objectType === 'solicitation'
				? $notifications->queueSolicitationParticipants(
					$object,
					$actorAccountId,
					$eventCode,
					$templateCode,
					$idempotencySource
				)
				: $notifications->queueAllocationParticipants(
					$object,
					$actorAccountId,
					$eventCode,
					$templateCode,
					$idempotencySource
				);
			if ($queueResult < 0) {
				$this->error = $notifications->error;
				return -1;
			}
		}

		return 0;
	}
}
