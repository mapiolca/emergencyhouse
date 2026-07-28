<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

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
		$analyticsRows = $this->buildAnalyticsDaily($entity, $timestamp);
		if ($analyticsRows < 0) {
			return -1;
		}
		$totalRows += $analyticsRows;
		$previousDay = dol_time_plus_duree($timestamp, -1, 'd');
		$previousAnalyticsRows = $this->buildAnalyticsDaily($entity, $previousDay);
		if ($previousAnalyticsRows < 0) {
			return -1;
		}
		$totalRows += $previousAnalyticsRows;
		return $totalRows;
	}

	/**
	 * Rebuild privacy-preserving audience aggregates for one day.
	 *
	 * Detailed rows remain the source of truth for the configured short
	 * retention window. The daily table contains no visitor identifier.
	 *
	 * @param int $entity Entity
	 * @param int|null $timestamp Metric day
	 * @return int Number of aggregate rows written
	 */
	public function buildAnalyticsDaily($entity, $timestamp = null)
	{
		$timestamp = $timestamp === null ? dol_now() : $timestamp;
		$metricDate = dol_print_date($timestamp, '%Y-%m-%d');
		$year = (int) dol_print_date($timestamp, '%Y');
		$month = (int) dol_print_date($timestamp, '%m');
		$day = (int) dol_print_date($timestamp, '%d');
		$dayStart = dol_mktime(0, 0, 0, $month, $day, $year);
		$dayEnd = dol_time_plus_duree($dayStart, 1, 'd');
		$dateStartSql = $this->db->idate($dayStart);
		$dateEndSql = $this->db->idate($dayEnd);

		$this->db->begin();
		$sqlDelete = 'DELETE FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_daily';
		$sqlDelete .= ' WHERE entity = '.((int) $entity);
		$sqlDelete .= " AND metric_date = '".$this->db->escape($metricDate)."'";
		if (!$this->db->query($sqlDelete)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$rows = 0;
		$visitMetrics = array(
			'visits' => 'COUNT(*)',
			'unique_visitors' => 'COUNT(DISTINCT visitor_hash)',
			'engaged_visits' => 'SUM(CASE WHEN is_engaged = 1 THEN 1 ELSE 0 END)',
			'bounces' => 'SUM(CASE WHEN is_engaged = 0 THEN 1 ELSE 0 END)',
			'active_seconds' => 'SUM(active_seconds)',
			'converted_visits' => 'SUM(CASE WHEN has_conversion = 1 THEN 1 ELSE 0 END)',
			'pageviews_from_visits' => 'SUM(pageview_count)',
		);
		foreach ($visitMetrics as $code => $expression) {
			$sql = 'SELECT '.$expression.' AS metric_value';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit';
			$sql .= ' WHERE entity = '.((int) $entity);
			$sql .= " AND date_start >= '".$dateStartSql."' AND date_start < '".$dateEndSql."'";
			$value = $this->fetchMetricValue($sql);
			if ($value === false || !$this->upsertAnalyticsMetric($entity, $metricDate, $code, '', '', 0, '', 0, $value)) {
				$this->db->rollback();
				return -1;
			}
			$rows++;
		}

		$eventMetrics = array(
			'page_views' => "event_type = 'page_view'",
			'actions' => "event_type = 'action'",
			'conversions' => "event_type = 'conversion'",
		);
		foreach ($eventMetrics as $code => $condition) {
			$sql = 'SELECT COUNT(*) AS metric_value';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event';
			$sql .= ' WHERE entity = '.((int) $entity).' AND '.$condition;
			$sql .= " AND date_event >= '".$dateStartSql."' AND date_event < '".$dateEndSql."'";
			$value = $this->fetchMetricValue($sql);
			if ($value === false || !$this->upsertAnalyticsMetric($entity, $metricDate, $code, '', '', 0, '', 0, $value)) {
				$this->db->rollback();
				return -1;
			}
			$rows++;
		}

		$dimensionQueries = array(
			array(
				'metric' => 'visits',
				'type' => 'source',
				'sql' => 'SELECT referrer_type AS dimension_code, COUNT(*) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit'
					.' WHERE entity = '.((int) $entity)
					." AND date_start >= '".$dateStartSql."' AND date_start < '".$dateEndSql."'"
					.' GROUP BY referrer_type',
			),
			array(
				'metric' => 'visits',
				'type' => 'device',
				'sql' => 'SELECT device_type AS dimension_code, COUNT(*) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit'
					.' WHERE entity = '.((int) $entity)
					." AND date_start >= '".$dateStartSql."' AND date_start < '".$dateEndSql."'"
					.' GROUP BY device_type',
			),
			array(
				'metric' => 'visits',
				'type' => 'referrer_domain',
				'sql' => 'SELECT referrer_domain AS dimension_code, COUNT(*) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit'
					.' WHERE entity = '.((int) $entity)
					." AND referrer_domain <> ''"
					." AND date_start >= '".$dateStartSql."' AND date_start < '".$dateEndSql."'"
					.' GROUP BY referrer_domain',
			),
			array(
				'metric' => 'visits',
				'type' => 'authentication',
				'sql' => 'SELECT auth_context AS dimension_code, COUNT(*) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit'
					.' WHERE entity = '.((int) $entity)
					." AND date_start >= '".$dateStartSql."' AND date_start < '".$dateEndSql."'"
					.' GROUP BY auth_context',
			),
			array(
				'metric' => 'visits',
				'type' => 'landing_page',
				'sql' => 'SELECT landing_page_code AS dimension_code, COUNT(*) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit'
					.' WHERE entity = '.((int) $entity)
					." AND date_start >= '".$dateStartSql."' AND date_start < '".$dateEndSql."'"
					.' GROUP BY landing_page_code',
			),
			array(
				'metric' => 'visits',
				'type' => 'exit_page',
				'sql' => 'SELECT exit_page_code AS dimension_code, COUNT(*) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit'
					.' WHERE entity = '.((int) $entity)
					." AND date_start >= '".$dateStartSql."' AND date_start < '".$dateEndSql."'"
					.' GROUP BY exit_page_code',
			),
			array(
				'metric' => 'unique_visitors',
				'type' => 'visitor_type',
				'sql' => "SELECT CASE WHEN EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS previous'
					.' WHERE previous.entity = current.entity AND previous.visitor_hash = current.visitor_hash'
					." AND previous.date_start < '".$dateStartSql."') THEN 'returning' ELSE 'new' END AS dimension_code,"
					.' COUNT(DISTINCT current.visitor_hash) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS current'
					.' WHERE current.entity = '.((int) $entity)
					." AND current.date_start >= '".$dateStartSql."' AND current.date_start < '".$dateEndSql."'"
					.' GROUP BY dimension_code',
			),
			array(
				'metric' => 'page_views',
				'type' => 'page',
				'sql' => 'SELECT page_code AS dimension_code, COUNT(*) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event'
					.' WHERE entity = '.((int) $entity)." AND event_type = 'page_view'"
					." AND date_event >= '".$dateStartSql."' AND date_event < '".$dateEndSql."'"
					.' GROUP BY page_code',
			),
			array(
				'metric' => 'conversions',
				'type' => 'conversion',
				'sql' => 'SELECT event_code AS dimension_code, COUNT(*) AS metric_value'
					.' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event'
					.' WHERE entity = '.((int) $entity)." AND event_type = 'conversion'"
					." AND date_event >= '".$dateStartSql."' AND date_event < '".$dateEndSql."'"
					.' GROUP BY event_code',
			),
		);
		foreach ($dimensionQueries as $definition) {
			$resql = $this->db->query($definition['sql']);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
			while (is_object($obj = $this->db->fetch_object($resql))) {
				if (!$this->upsertAnalyticsMetric(
					$entity,
					$metricDate,
					$definition['metric'],
					$definition['type'],
					(string) $obj->dimension_code,
					0,
					'',
					0,
					(float) $obj->metric_value
				)) {
					$this->db->rollback();
					return -1;
				}
				$rows++;
			}
		}

		$sqlContent = 'SELECT fk_campaign, content_type, fk_content, COUNT(*) AS metric_value';
		$sqlContent .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event';
		$sqlContent .= ' WHERE entity = '.((int) $entity)." AND event_type = 'page_view'";
		$sqlContent .= " AND content_type <> '' AND fk_content > 0";
		$sqlContent .= " AND date_event >= '".$dateStartSql."' AND date_event < '".$dateEndSql."'";
		$sqlContent .= ' GROUP BY fk_campaign, content_type, fk_content';
		$resqlContent = $this->db->query($sqlContent);
		if (!$resqlContent) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		while (is_object($obj = $this->db->fetch_object($resqlContent))) {
			if (!$this->upsertAnalyticsMetric(
				$entity,
				$metricDate,
				'page_views',
				'content',
				(string) $obj->content_type,
				(int) $obj->fk_campaign,
				(string) $obj->content_type,
				(int) $obj->fk_content,
				(float) $obj->metric_value
			)) {
				$this->db->rollback();
				return -1;
			}
			$rows++;
		}

		$this->db->commit();
		return $rows;
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

	/**
	 * @param string $sql Trusted aggregate SQL
	 * @return float|false
	 */
	private function fetchMetricValue($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return is_object($obj) && $obj->metric_value !== null ? (float) $obj->metric_value : 0.0;
	}

	/**
	 * @param int $entity Entity
	 * @param string $date Date
	 * @param string $metric Metric code
	 * @param string $dimensionType Dimension type
	 * @param string $dimensionCode Dimension code
	 * @param int $campaignId Campaign ID
	 * @param string $contentType Content type
	 * @param int $contentId Content ID
	 * @param float $value Value
	 * @return bool
	 */
	private function upsertAnalyticsMetric($entity, $date, $metric, $dimensionType, $dimensionCode, $campaignId, $contentType, $contentId, $value)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_analytics_daily';
		$sql .= ' (entity, metric_date, metric_code, dimension_type, dimension_code, fk_campaign,';
		$sql .= ' content_type, fk_content, metric_value, date_calculation) VALUES (';
		$sql .= ((int) $entity).", '".$this->db->escape($date)."',";
		$sql .= " '".$this->db->escape($metric)."', '".$this->db->escape($dimensionType)."',";
		$sql .= " '".$this->db->escape($dimensionCode)."', ".((int) $campaignId).',';
		$sql .= " '".$this->db->escape($contentType)."', ".((int) $contentId).', '.((float) $value).',';
		$sql .= " '".$this->db->idate(dol_now())."')";
		$sql .= ' ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value), date_calculation = VALUES(date_calculation)';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}
}
