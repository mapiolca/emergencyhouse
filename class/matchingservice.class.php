<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');

/**
 * Deterministic and explainable matching engine.
 */
class EmergencyHouseMatchingService
{
	public const ALGORITHM_VERSION = '1.0';

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
	 * Queue a recalculation job without creating duplicates.
	 *
	 * @param int    $entity Entity
	 * @param string $objectType Object type
	 * @param int    $objectId Object ID
	 * @param int    $campaignId Campaign ID
	 * @param string $revision Stable object revision
	 * @return int
	 */
	public function queueRecalculation($entity, $objectType, $objectId, $campaignId, $revision)
	{
		if (!in_array($objectType, array('offer', 'request', 'campaign'), true)) {
			$this->error = 'ErrorInvalidObjectType';
			return -1;
		}
		$idempotencyKey = hash('sha256', 'matching|'.$entity.'|'.$objectType.'|'.$objectId.'|'.$revision);
		$payload = json_encode(array('revision' => $revision));
		if (!is_string($payload)) {
			$payload = '{}';
		}
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_job';
		$sql .= ' (entity, job_type, object_type, fk_object, fk_campaign, idempotency_key, payload_snapshot, status, priority, attempt_count, next_attempt, date_creation)';
		$sql .= ' VALUES ('.((int) $entity).", 'matching', '".$this->db->escape($objectType)."', ".((int) $objectId).', '.((int) $campaignId).',';
		$sql .= " '".$this->db->escape($idempotencyKey)."', '".$this->db->escape($payload)."', 0, 50, 0,";
		$sql .= " '".$this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."')";
		$sql .= ' ON DUPLICATE KEY UPDATE payload_snapshot = VALUES(payload_snapshot),';
		$sql .= ' status = IF(status = 2, 0, status), next_attempt = LEAST(next_attempt, VALUES(next_attempt))';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Recalculate active offers for one request.
	 *
	 * @param int $requestId Request ID
	 * @return int Number of persisted matches
	 */
	public function recalculateForRequest($requestId)
	{
		$request = new EmergencyHouseRequest($this->db);
		if ($request->fetch($requestId) <= 0) {
			$this->error = !empty($request->error) ? $request->error : 'ErrorRecordNotFound';
			return -1;
		}
		if (!in_array((int) $request->status, array(
			EmergencyHouseRequest::STATUS_ACTIVE,
			EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED,
		), true)) {
			return 0;
		}

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer';
		$sql .= ' WHERE entity = '.((int) $request->entity);
		$sql .= ' AND fk_campaign = '.((int) $request->fk_campaign);
		$sql .= ' AND status = '.EmergencyHouseOffer::STATUS_PUBLISHED;
		$sql .= ' AND capacity_total > 0';
		$sql .= " AND date_start <= '".$this->db->idate(!empty($request->date_end) ? $request->date_end : $request->date_start)."'";
		$sql .= " AND (date_end IS NULL OR date_end >= '".$this->db->idate($request->date_start)."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$count = 0;
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$offer = new EmergencyHouseOffer($this->db);
			if ($offer->fetch((int) $obj->rowid) <= 0) {
				continue;
			}
			$result = $this->score($offer, $request);
			if ($result === false) {
				continue;
			}
			if ($this->persist($offer, $request, $result) > 0) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Recalculate active requests for one offer.
	 *
	 * @param int $offerId Offer ID
	 * @return int Number of persisted matches
	 */
	public function recalculateForOffer($offerId)
	{
		$offer = new EmergencyHouseOffer($this->db);
		if ($offer->fetch($offerId) <= 0) {
			$this->error = !empty($offer->error) ? $offer->error : 'ErrorRecordNotFound';
			return -1;
		}
		if ($offer->status !== EmergencyHouseOffer::STATUS_PUBLISHED) {
			return 0;
		}
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_request';
		$sql .= ' WHERE entity = '.((int) $offer->entity);
		$sql .= ' AND fk_campaign = '.((int) $offer->fk_campaign);
		$sql .= ' AND status IN ('.EmergencyHouseRequest::STATUS_ACTIVE.','.EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED.')';
		$sql .= ' AND remaining_count > 0';
		$sql .= " AND date_start <= '".$this->db->idate(!empty($offer->date_end) ? $offer->date_end : $offer->date_start)."'";
		$sql .= " AND (date_end IS NULL OR date_end >= '".$this->db->idate($offer->date_start)."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$count = 0;
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$request = new EmergencyHouseRequest($this->db);
			if ($request->fetch((int) $obj->rowid) <= 0) {
				continue;
			}
			$result = $this->score($offer, $request);
			if ($result !== false && $this->persist($offer, $request, $result) > 0) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Calculate compatibility and explainable subscores.
	 *
	 * @param EmergencyHouseOffer   $offer Offer
	 * @param EmergencyHouseRequest $request Request
	 * @return array<string, float|int|string|array<int, string>|null>|false
	 */
	public function score($offer, $request)
	{
		if ((int) $offer->entity !== (int) $request->entity
			|| (int) $offer->fk_campaign !== (int) $request->fk_campaign
			|| $offer->status !== EmergencyHouseOffer::STATUS_PUBLISHED
			|| !in_array($request->status, array(EmergencyHouseRequest::STATUS_ACTIVE, EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED), true)) {
			return false;
		}
		if (!$this->periodsOverlap($offer->date_start, $offer->date_end, $request->date_start, $request->date_end)) {
			return false;
		}
		if (!$this->housingTypeCompatible((int) $offer->entity, (int) $offer->id, (int) $request->id, (int) $offer->fk_housing_type)) {
			return false;
		}
		if (!$this->requiredFeaturesCompatible((int) $offer->entity, (int) $offer->id, (int) $request->id)) {
			return false;
		}

		$capacityEvaluated = min((int) $offer->capacity_total, (int) $request->remaining_count);
		if (!$request->group_divisible && $offer->capacity_total < $request->remaining_count) {
			return false;
		}
		if ($request->group_divisible && $offer->capacity_total < $request->minimum_group_size) {
			return false;
		}
		$capacityScore = min(1.0, $offer->capacity_total / max(1, $request->remaining_count));
		$dateScore = $this->dateCoverageScore($offer->date_start, $offer->date_end, $request->date_start, $request->date_end);
		$typeScore = 1.0;
		$featureScore = $this->optionalFeatureScore((int) $offer->entity, (int) $offer->id, (int) $request->id);
		$distanceScore = $this->distanceScore($offer->geo_cell, $request->geo_cell);

		$weights = $this->getWeights();
		$total = (
			$distanceScore * $weights['distance']
			+ $capacityScore * $weights['capacity']
			+ $dateScore * $weights['dates']
			+ $typeScore * $weights['type']
			+ $featureScore * $weights['features']
		) / max(1, array_sum($weights)) * 100;
		// This is a non-financial score. Keep its deterministic precision local
		// instead of applying Dolibarr monetary rounding settings.
		$total = floor(max(0.0, min(100.0, $total)) * 10000 + 0.5) / 10000;
		$scoreClass = $total >= 80 ? 'strong' : ($total >= 60 ? 'medium' : 'weak');

		$warnings = array();
		if ($distanceScore < 0.5) {
			$warnings[] = 'MatchWarningDistanceUnknown';
		}
		if ($capacityEvaluated < $request->remaining_count) {
			$warnings[] = 'MatchWarningPartialCapacity';
		}
		if ($dateScore < 1.0) {
			$warnings[] = 'MatchWarningPartialDates';
		}

		return array(
			'score_total' => $total,
			'score_class' => $scoreClass,
			'score_distance' => $distanceScore * 100,
			'score_capacity' => $capacityScore * 100,
			'score_dates' => $dateScore * 100,
			'score_type' => $typeScore * 100,
			'score_features' => $featureScore * 100,
			'distance_km' => null,
			'capacity_evaluated' => $capacityEvaluated,
			'nights_requested' => $this->numberOfNights($request->date_start, $request->date_end),
			'nights_covered' => $this->numberOfCoveredNights($offer->date_start, $offer->date_end, $request->date_start, $request->date_end),
			'warnings' => $warnings,
		);
	}

	/**
	 * Persist score.
	 *
	 * @param EmergencyHouseOffer   $offer Offer
	 * @param EmergencyHouseRequest $request Request
	 * @param array<string, float|int|string|array<int, string>|null> $score Score data
	 * @return int
	 */
	private function persist($offer, $request, array $score)
	{
		$parametersVersion = $this->getParametersVersion();
		$explanation = json_encode(array(
			'algorithm' => self::ALGORITHM_VERSION,
			'parameters' => $parametersVersion,
			'components' => array(
				'distance' => $score['score_distance'],
				'capacity' => $score['score_capacity'],
				'dates' => $score['score_dates'],
				'type' => $score['score_type'],
				'features' => $score['score_features'],
			),
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$warnings = json_encode($score['warnings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($explanation) || !is_string($warnings)) {
			$this->error = 'ErrorJsonEncoding';
			return -1;
		}
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_match (';
		$sql .= 'entity, fk_campaign, fk_offer, fk_request, algorithm_version, parameters_version, score_total, score_class, score_distance, score_capacity, score_dates, score_type, score_features, distance_km, capacity_evaluated, nights_requested, nights_covered, explanation_snapshot, warnings_snapshot, status, date_calculation';
		$sql .= ') VALUES (';
		$sql .= ((int) $offer->entity).', '.((int) $offer->fk_campaign).', '.((int) $offer->id).', '.((int) $request->id).',';
		$sql .= " '".self::ALGORITHM_VERSION."', '".$this->db->escape($parametersVersion)."',";
		$sql .= ' '.((float) $score['score_total']).", '".$this->db->escape((string) $score['score_class'])."',";
		$sql .= ' '.((float) $score['score_distance']).', '.((float) $score['score_capacity']).', '.((float) $score['score_dates']).',';
		$sql .= ' '.((float) $score['score_type']).', '.((float) $score['score_features']).',';
		$sql .= $score['distance_km'] === null ? ' NULL,' : ' '.((float) $score['distance_km']).',';
		$sql .= ' '.((int) $score['capacity_evaluated']).',';
		$sql .= $score['nights_requested'] === null ? ' NULL,' : ' '.((int) $score['nights_requested']).',';
		$sql .= $score['nights_covered'] === null ? ' NULL,' : ' '.((int) $score['nights_covered']).',';
		$sql .= " '".$this->db->escape($explanation)."', '".$this->db->escape($warnings)."', 1, '".$this->db->idate(dol_now())."')";
		$sql .= ' ON DUPLICATE KEY UPDATE';
		$sql .= ' score_total = VALUES(score_total), score_class = VALUES(score_class),';
		$sql .= ' score_distance = VALUES(score_distance), score_capacity = VALUES(score_capacity),';
		$sql .= ' score_dates = VALUES(score_dates), score_type = VALUES(score_type), score_features = VALUES(score_features),';
		$sql .= ' distance_km = VALUES(distance_km), capacity_evaluated = VALUES(capacity_evaluated),';
		$sql .= ' nights_requested = VALUES(nights_requested), nights_covered = VALUES(nights_covered),';
		$sql .= ' explanation_snapshot = VALUES(explanation_snapshot), warnings_snapshot = VALUES(warnings_snapshot),';
		$sql .= ' status = 1, date_invalidation = NULL, date_calculation = VALUES(date_calculation)';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Get matching weights.
	 *
	 * @return array{distance:int, capacity:int, dates:int, type:int, features:int}
	 */
	private function getWeights()
	{
		return array(
			'distance' => max(0, getDolGlobalInt('EMERGENCYHOUSE_MATCH_DISTANCE_WEIGHT', 30)),
			'capacity' => max(0, getDolGlobalInt('EMERGENCYHOUSE_MATCH_CAPACITY_WEIGHT', 25)),
			'dates' => max(0, getDolGlobalInt('EMERGENCYHOUSE_MATCH_DATES_WEIGHT', 20)),
			'type' => max(0, getDolGlobalInt('EMERGENCYHOUSE_MATCH_TYPE_WEIGHT', 15)),
			'features' => max(0, getDolGlobalInt('EMERGENCYHOUSE_MATCH_FEATURES_WEIGHT', 10)),
		);
	}

	/**
	 * Return a deterministic version for current parameters.
	 *
	 * @return string
	 */
	private function getParametersVersion()
	{
		return substr(hash('sha256', json_encode($this->getWeights())), 0, 16);
	}

	/**
	 * Check requested housing types.
	 *
	 * @param int $entity Entity
	 * @param int $offerId Offer ID
	 * @param int $requestId Request ID
	 * @param int $housingType Housing type
	 * @return bool
	 */
	private function housingTypeCompatible($entity, $offerId, $requestId, $housingType)
	{
		unset($offerId);
		$sql = 'SELECT COUNT(*) AS total,';
		$sql .= ' SUM(CASE WHEN fk_housing_type = '.((int) $housingType).' THEN 1 ELSE 0 END) AS matching';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_request_housing_type';
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_request = '.((int) $requestId);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return !is_object($obj) || (int) $obj->total === 0 || (int) $obj->matching > 0;
	}

	/**
	 * Check every required feature.
	 *
	 * @param int $entity Entity
	 * @param int $offerId Offer ID
	 * @param int $requestId Request ID
	 * @return bool
	 */
	private function requiredFeaturesCompatible($entity, $offerId, $requestId)
	{
		$sql = 'SELECT COUNT(*) AS missing FROM '.MAIN_DB_PREFIX.'emergencyhouse_request_criterion AS rc';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer_feature AS ofe';
		$sql .= ' ON ofe.entity = rc.entity AND ofe.fk_offer = '.((int) $offerId).' AND ofe.fk_feature = rc.fk_feature';
		$sql .= ' WHERE rc.entity = '.((int) $entity).' AND rc.fk_request = '.((int) $requestId);
		$sql .= " AND rc.criterion_level = 'required'";
		$sql .= ' AND (ofe.rowid IS NULL';
		$sql .= ' OR (rc.expected_code IS NOT NULL AND COALESCE(ofe.value_code, \'\') <> rc.expected_code)';
		$sql .= ' OR (rc.expected_number IS NOT NULL AND COALESCE(ofe.value_number, -1) < rc.expected_number))';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return !is_object($obj) || (int) $obj->missing === 0;
	}

	/**
	 * Score preferred features.
	 *
	 * @param int $entity Entity
	 * @param int $offerId Offer ID
	 * @param int $requestId Request ID
	 * @return float
	 */
	private function optionalFeatureScore($entity, $offerId, $requestId)
	{
		$sql = 'SELECT COUNT(*) AS total, SUM(CASE WHEN ofe.rowid IS NOT NULL';
		$sql .= ' AND (rc.expected_code IS NULL OR ofe.value_code = rc.expected_code)';
		$sql .= ' AND (rc.expected_number IS NULL OR ofe.value_number >= rc.expected_number)';
		$sql .= ' THEN 1 ELSE 0 END) AS matching';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_request_criterion AS rc';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer_feature AS ofe';
		$sql .= ' ON ofe.entity = rc.entity AND ofe.fk_offer = '.((int) $offerId).' AND ofe.fk_feature = rc.fk_feature';
		$sql .= ' WHERE rc.entity = '.((int) $entity).' AND rc.fk_request = '.((int) $requestId);
		$sql .= " AND rc.criterion_level = 'preferred'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0.0;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj) || (int) $obj->total === 0) {
			return 1.0;
		}
		return (float) $obj->matching / (float) $obj->total;
	}

	/**
	 * Score coarse public cells without revealing precise coordinates.
	 *
	 * @param string|null $offerCell Offer cell
	 * @param string|null $requestCell Request cell
	 * @return float
	 */
	private function distanceScore($offerCell, $requestCell)
	{
		if (empty($offerCell) || empty($requestCell)) {
			return 0.4;
		}
		return hash_equals($offerCell, $requestCell) ? 1.0 : 0.5;
	}

	/**
	 * Score requested date coverage.
	 *
	 * @param int|null $offerStart Offer start
	 * @param int|null $offerEnd Offer end
	 * @param int|null $requestStart Request start
	 * @param int|null $requestEnd Request end
	 * @return float
	 */
	private function dateCoverageScore($offerStart, $offerEnd, $requestStart, $requestEnd)
	{
		if (empty($requestEnd) || empty($offerEnd)) {
			return 1.0;
		}
		$requested = max(1, $requestEnd - $requestStart);
		$coveredStart = max($offerStart, $requestStart);
		$coveredEnd = min($offerEnd, $requestEnd);
		return max(0.0, min(1.0, ($coveredEnd - $coveredStart) / $requested));
	}

	/**
	 * Period overlap.
	 *
	 * @param int|null $startA Start A
	 * @param int|null $endA End A
	 * @param int|null $startB Start B
	 * @param int|null $endB End B
	 * @return bool
	 */
	private function periodsOverlap($startA, $endA, $startB, $endB)
	{
		$effectiveEndA = empty($endA) ? PHP_INT_MAX : $endA;
		$effectiveEndB = empty($endB) ? PHP_INT_MAX : $endB;
		return $startA <= $effectiveEndB && $startB <= $effectiveEndA;
	}

	/**
	 * Number of requested nights.
	 *
	 * @param int|null $start Start
	 * @param int|null $end End
	 * @return int|null
	 */
	private function numberOfNights($start, $end)
	{
		return empty($start) || empty($end) ? null : max(1, (int) ceil(($end - $start) / 86400));
	}

	/**
	 * Number of covered nights.
	 *
	 * @param int|null $offerStart Offer start
	 * @param int|null $offerEnd Offer end
	 * @param int|null $requestStart Request start
	 * @param int|null $requestEnd Request end
	 * @return int|null
	 */
	private function numberOfCoveredNights($offerStart, $offerEnd, $requestStart, $requestEnd)
	{
		if (empty($offerEnd) || empty($requestEnd)) {
			return null;
		}
		$start = max($offerStart, $requestStart);
		$end = min($offerEnd, $requestEnd);
		return $end < $start ? 0 : max(1, (int) ceil(($end - $start) / 86400));
	}
}
