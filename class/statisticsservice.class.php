<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Privacy-preserving daily aggregates.
 */
class EmergencyHouseStatisticsService
{
	/** @var DoliDB */
	private $db;
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
	}

	/**
	 * Aggregate all campaigns in one entity.
	 *
	 * @param int $entity Entity
	 * @param int|null $timestamp Metric day
	 * @return int Number of metric rows written
	 */
	public function buildDaily($entity, $timestamp = null)
	{
		$timestamp = $timestamp === null ? dol_now() : $timestamp;
		$metricDate = dol_print_date($timestamp, '%Y-%m-%d');
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
		$sql .= ' WHERE entity = '.((int) $entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$totalRows = 0;
		while (is_object($campaign = $this->db->fetch_object($resql))) {
			$metrics = array(
				'active_offers' => $this->countRows('emergencyhouse_offer', $entity, (int) $campaign->rowid, 'status = 2'),
				'active_requests' => $this->countRows('emergencyhouse_request', $entity, (int) $campaign->rowid, 'status IN (1, 2)'),
				'active_solicitations' => $this->countRows('emergencyhouse_solicitation', $entity, (int) $campaign->rowid, 'status IN (0, 1)'),
				'active_allocations' => $this->countRows('emergencyhouse_allocation', $entity, (int) $campaign->rowid, 'status IN (0, 1, 2, 5)'),
				'open_reports' => $this->countRows('emergencyhouse_report', $entity, (int) $campaign->rowid, 'status IN (0, 1)'),
			);
			foreach ($metrics as $code => $value) {
				if ($value < 0 || !$this->upsertMetric($entity, (int) $campaign->rowid, $metricDate, $code, '', $value)) {
					return -1;
				}
				$totalRows++;
			}
		}
		return $totalRows;
	}

	/**
	 * Count an entity/campaign table with a trusted static condition.
	 *
	 * @param string $table Table suffix
	 * @param int    $entity Entity
	 * @param int    $campaignId Campaign ID
	 * @param string $condition Trusted code condition
	 * @return int
	 */
	private function countRows($table, $entity, $campaignId, $condition)
	{
		$allowed = array(
			'emergencyhouse_offer',
			'emergencyhouse_request',
			'emergencyhouse_solicitation',
			'emergencyhouse_allocation',
			'emergencyhouse_report',
		);
		if (!in_array($table, $allowed, true)) {
			return -1;
		}
		$sql = 'SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$table;
		$sql .= ' WHERE entity = '.((int) $entity).' AND fk_campaign = '.((int) $campaignId).' AND '.$condition;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		return is_object($obj) ? (int) $obj->total : 0;
	}

	/**
	 * Upsert metric.
	 *
	 * @param int    $entity Entity
	 * @param int    $campaignId Campaign ID
	 * @param string $date Date
	 * @param string $code Code
	 * @param string $dimension Dimension
	 * @param int|float $value Value
	 * @return bool
	 */
	private function upsertMetric($entity, $campaignId, $date, $code, $dimension, $value)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_stat_daily';
		$sql .= ' (entity, fk_campaign, metric_date, metric_code, dimension_code, metric_value, date_calculation)';
		$sql .= ' VALUES ('.((int) $entity).', '.((int) $campaignId).", '".$this->db->escape($date)."',";
		$sql .= " '".$this->db->escape($code)."', '".$this->db->escape($dimension)."', ".((float) $value).',';
		$sql .= " '".$this->db->idate(dol_now())."')";
		$sql .= ' ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value), date_calculation = VALUES(date_calculation)';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}
}

