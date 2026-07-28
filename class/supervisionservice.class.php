<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Read-only queries used by the Supervision dashboard.
 */
class EmergencyHouseSupervisionService
{
	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return exact metrics from retained visit and event details.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
	 * @return array<string, float>|false
	 */
	public function getExactOverview($entities, $dateStart, $dateEnd, $filters)
	{
		$visitWhere = $this->buildVisitWhere($entities, $dateStart, $dateEnd, $filters, 'v');
		$sql = 'SELECT COUNT(*) AS visits, COUNT(DISTINCT v.visitor_hash) AS unique_visitors,';
		$sql .= ' COALESCE(SUM(v.pageview_count), 0) AS page_views,';
		$sql .= ' COALESCE(SUM(v.active_seconds), 0) AS active_seconds,';
		$sql .= ' COALESCE(SUM(CASE WHEN v.is_engaged = 0 THEN 1 ELSE 0 END), 0) AS bounces,';
		$sql .= ' COALESCE(SUM(CASE WHEN v.has_conversion = 1 THEN 1 ELSE 0 END), 0) AS converted_visits';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v'.$visitWhere;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj)) {
			return $this->emptyOverview();
		}

		$visits = (float) $obj->visits;
		$eventWhere = $this->buildEventWhere($entities, $dateStart, $dateEnd, $filters, 'e', 'v');
		$sqlEvents = 'SELECT';
		$sqlEvents .= " COALESCE(SUM(CASE WHEN e.event_type = 'page_view' THEN 1 ELSE 0 END), 0) AS page_views,";
		$sqlEvents .= " COUNT(DISTINCT CASE WHEN e.event_type = 'conversion' THEN e.fk_visit ELSE NULL END) AS converted_visits";
		$sqlEvents .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event AS e';
		$sqlEvents .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
		$sqlEvents .= ' ON v.rowid = e.fk_visit AND v.entity = e.entity'.$eventWhere;
		$resqlEvents = $this->db->query($sqlEvents);
		$eventTotals = $resqlEvents ? $this->db->fetch_object($resqlEvents) : false;
		if (!$resqlEvents || !is_object($eventTotals)) {
			$this->error = $this->db->lasterror();
			return false;
		}

		$pageViews = (float) $eventTotals->page_views;
		$activeSeconds = (float) $obj->active_seconds;
		$bounces = (float) $obj->bounces;
		$converted = (float) $eventTotals->converted_visits;

		return array(
			'page_views' => $pageViews,
			'visits' => $visits,
			'unique_visitors' => (float) $obj->unique_visitors,
			'bounce_rate' => $visits > 0 ? ($bounces * 100 / $visits) : 0.0,
			'average_duration' => $visits > 0 ? ($activeSeconds / $visits) : 0.0,
			'pages_per_visit' => $visits > 0 ? ($pageViews / $visits) : 0.0,
			'conversion_rate' => $visits > 0 ? ($converted * 100 / $visits) : 0.0,
		);
	}

	/**
	 * Return trends from anonymized daily aggregates.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @return array<string, float>|false
	 */
	public function getAggregateOverview($entities, $dateStart, $dateEnd)
	{
		$metricCodes = array(
			'page_views', 'visits', 'unique_visitors', 'bounces',
			'active_seconds', 'converted_visits',
		);
		$sql = 'SELECT metric_code, SUM(metric_value) AS metric_value';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_daily';
		$sql .= $this->buildAggregateWhere($entities, $dateStart, $dateEnd);
		$sql .= " AND dimension_type = '' AND metric_code IN ('".implode("','", $metricCodes)."')";
		$sql .= ' GROUP BY metric_code';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$values = array_fill_keys($metricCodes, 0.0);
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$code = (string) $obj->metric_code;
			if (isset($values[$code])) {
				$values[$code] = (float) $obj->metric_value;
			}
		}
		$visits = $values['visits'];

		return array(
			'page_views' => $values['page_views'],
			'visits' => $visits,
			'unique_visitors' => $values['unique_visitors'],
			'bounce_rate' => $visits > 0 ? ($values['bounces'] * 100 / $visits) : 0.0,
			'average_duration' => $visits > 0 ? ($values['active_seconds'] / $visits) : 0.0,
			'pages_per_visit' => $visits > 0 ? ($values['page_views'] / $visits) : 0.0,
			'conversion_rate' => $visits > 0 ? ($values['converted_visits'] * 100 / $visits) : 0.0,
		);
	}

	/**
	 * Return daily page views and visits for a DolGraph line chart.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @return array<int, array{0:string,1:float,2:float}>|false
	 */
	public function getDailySeries($entities, $dateStart, $dateEnd)
	{
		$sql = 'SELECT metric_date,';
		$sql .= " SUM(CASE WHEN metric_code = 'page_views' THEN metric_value ELSE 0 END) AS page_views,";
		$sql .= " SUM(CASE WHEN metric_code = 'visits' THEN metric_value ELSE 0 END) AS visits";
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_daily';
		$sql .= $this->buildAggregateWhere($entities, $dateStart, $dateEnd);
		$sql .= " AND dimension_type = '' AND metric_code IN ('page_views', 'visits')";
		$sql .= ' GROUP BY metric_date ORDER BY metric_date';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				(string) $obj->metric_date,
				(float) $obj->page_views,
				(float) $obj->visits,
			);
		}
		return $rows;
	}

	/**
	 * Return exact daily series while detailed events are retained.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
	 * @return array<int, array{0:string,1:float,2:float}>|false
	 */
	public function getExactDailySeries($entities, $dateStart, $dateEnd, $filters)
	{
		$series = array();
		$sqlVisits = 'SELECT DATE(v.date_start) AS metric_date, COUNT(*) AS metric_value';
		$sqlVisits .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
		$sqlVisits .= $this->buildVisitWhere($entities, $dateStart, $dateEnd, $filters, 'v');
		$sqlVisits .= ' GROUP BY DATE(v.date_start) ORDER BY metric_date';
		$resqlVisits = $this->db->query($sqlVisits);
		if (!$resqlVisits) {
			$this->error = $this->db->lasterror();
			return false;
		}
		while (is_object($obj = $this->db->fetch_object($resqlVisits))) {
			$date = (string) $obj->metric_date;
			$series[$date] = array($date, 0.0, (float) $obj->metric_value);
		}

		$sqlPages = 'SELECT DATE(e.date_event) AS metric_date, COUNT(*) AS metric_value';
		$sqlPages .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event AS e';
		$sqlPages .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
		$sqlPages .= ' ON v.rowid = e.fk_visit AND v.entity = e.entity';
		$sqlPages .= $this->buildEventWhere($entities, $dateStart, $dateEnd, $filters, 'e', 'v');
		$sqlPages .= " AND e.event_type = 'page_view'";
		$sqlPages .= ' GROUP BY DATE(e.date_event) ORDER BY metric_date';
		$resqlPages = $this->db->query($sqlPages);
		if (!$resqlPages) {
			$this->error = $this->db->lasterror();
			return false;
		}
		while (is_object($obj = $this->db->fetch_object($resqlPages))) {
			$date = (string) $obj->metric_date;
			if (!isset($series[$date])) {
				$series[$date] = array($date, 0.0, 0.0);
			}
			$series[$date][1] = (float) $obj->metric_value;
		}
		ksort($series);
		return array_values($series);
	}

	/**
	 * Return one anonymized aggregate breakdown.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param string $dimensionType Controlled dimension
	 * @param string $metricCode Controlled metric
	 * @param int $limit Result limit
	 * @return array<int, array{code:string,value:float}>|false
	 */
	public function getBreakdown($entities, $dateStart, $dateEnd, $dimensionType, $metricCode, $limit = 20)
	{
		$allowedDimensions = array('source', 'device', 'referrer_domain', 'authentication', 'page', 'landing_page', 'exit_page', 'visitor_type', 'conversion');
		$allowedMetrics = array('visits', 'unique_visitors', 'page_views', 'conversions');
		if (!in_array($dimensionType, $allowedDimensions, true) || !in_array($metricCode, $allowedMetrics, true)) {
			$this->error = 'ErrorBadParameters';
			return false;
		}
		$sql = 'SELECT dimension_code, SUM(metric_value) AS metric_value';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_daily';
		$sql .= $this->buildAggregateWhere($entities, $dateStart, $dateEnd);
		$sql .= " AND dimension_type = '".$this->db->escape($dimensionType)."'";
		$sql .= " AND metric_code = '".$this->db->escape($metricCode)."'";
		$sql .= ' GROUP BY dimension_code ORDER BY metric_value DESC';
		$sql .= $this->db->plimit(max(1, min(100, $limit)));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array('code' => (string) $obj->dimension_code, 'value' => (float) $obj->metric_value);
		}
		return $rows;
	}

	/**
	 * Return an exact breakdown from retained details with all filters applied.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
	 * @param string $dimensionType Controlled dimension
	 * @param string $metricCode Controlled metric
	 * @param int $limit Result limit
	 * @return array<int, array{code:string,value:float}>|false
	 */
	public function getExactBreakdown($entities, $dateStart, $dateEnd, $filters, $dimensionType, $metricCode, $limit = 20)
	{
		$visitDimensions = array(
			'source' => 'v.referrer_type',
			'device' => 'v.device_type',
			'referrer_domain' => 'v.referrer_domain',
			'authentication' => 'v.auth_context',
			'landing_page' => 'v.landing_page_code',
			'exit_page' => 'v.exit_page_code',
		);
		$eventDimensions = array(
			'page' => array('column' => 'e.page_code', 'event_type' => 'page_view', 'metric' => 'page_views'),
			'conversion' => array('column' => 'e.event_code', 'event_type' => 'conversion', 'metric' => 'conversions'),
		);
		$limit = max(1, min(100, (int) $limit));
		if (isset($visitDimensions[$dimensionType]) && $metricCode === 'visits') {
			$column = $visitDimensions[$dimensionType];
			$sql = 'SELECT '.$column.' AS dimension_code, COUNT(*) AS metric_value';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
			$sql .= $this->buildVisitWhere($entities, $dateStart, $dateEnd, $filters, 'v');
			if ($dimensionType === 'referrer_domain') {
				$sql .= " AND v.referrer_domain <> ''";
			}
			$sql .= ' GROUP BY '.$column.' ORDER BY metric_value DESC'.$this->db->plimit($limit);
			return $this->fetchBreakdownRows($sql);
		}
		if ($dimensionType === 'visitor_type' && $metricCode === 'unique_visitors') {
			$dayStart = $this->db->idate($dateStart);
			$expression = "CASE WHEN EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS previous';
			$expression .= ' WHERE previous.entity = v.entity AND previous.visitor_hash = v.visitor_hash';
			$expression .= " AND previous.date_start < '".$dayStart."') THEN 'returning' ELSE 'new' END";
			$sql = 'SELECT '.$expression.' AS dimension_code, COUNT(DISTINCT v.visitor_hash) AS metric_value';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
			$sql .= $this->buildVisitWhere($entities, $dateStart, $dateEnd, $filters, 'v');
			$sql .= ' GROUP BY dimension_code ORDER BY metric_value DESC'.$this->db->plimit($limit);
			return $this->fetchBreakdownRows($sql);
		}
		if (isset($eventDimensions[$dimensionType]) && $metricCode === $eventDimensions[$dimensionType]['metric']) {
			$definition = $eventDimensions[$dimensionType];
			$sql = 'SELECT '.$definition['column'].' AS dimension_code, COUNT(*) AS metric_value';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event AS e';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
			$sql .= ' ON v.rowid = e.fk_visit AND v.entity = e.entity';
			$sql .= $this->buildEventWhere($entities, $dateStart, $dateEnd, $filters, 'e', 'v');
			$sql .= " AND e.event_type = '".$this->db->escape($definition['event_type'])."'";
			$sql .= ' GROUP BY '.$definition['column'].' ORDER BY metric_value DESC'.$this->db->plimit($limit);
			return $this->fetchBreakdownRows($sql);
		}
		$this->error = 'ErrorBadParameters';
		return false;
	}

	/**
	 * Return most viewed public campaigns, offers and requests.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param int $campaignId Campaign filter
	 * @param string $contentType Content filter
	 * @param int $limit Result limit
	 * @return array<int, array{entity:int,campaign_ref:string,content_type:string,content_id:int,content_label:string,value:float}>|false
	 */
	public function getTopContents($entities, $dateStart, $dateEnd, $campaignId = 0, $contentType = '', $limit = 50, $offset = 0)
	{
		$allowedTypes = array('', 'campaign', 'offer', 'request');
		if (!in_array($contentType, $allowedTypes, true)) {
			$contentType = '';
		}
		$sql = 'SELECT a.entity, a.fk_campaign, a.content_type, a.fk_content,';
		$sql .= ' COALESCE(c.ref, \'\') AS campaign_ref,';
		$sql .= " CASE WHEN a.content_type = 'campaign' THEN COALESCE(c.label, c.ref)";
		$sql .= " WHEN a.content_type = 'offer' THEN COALESCE(o.title, o.ref)";
		$sql .= " WHEN a.content_type = 'request' THEN COALESCE(r.title, r.ref) ELSE '' END AS content_label,";
		$sql .= ' SUM(a.metric_value) AS metric_value';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_daily AS a';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c';
		$sql .= ' ON c.rowid = a.fk_campaign AND c.entity = a.entity';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= " ON a.content_type = 'offer' AND o.rowid = a.fk_content AND o.entity = a.entity";
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= " ON a.content_type = 'request' AND r.rowid = a.fk_content AND r.entity = a.entity";
		$sql .= $this->buildAggregateWhere($entities, $dateStart, $dateEnd, 'a');
		$sql .= " AND a.dimension_type = 'content' AND a.metric_code = 'page_views'";
		if ($campaignId > 0) {
			$sql .= ' AND a.fk_campaign = '.((int) $campaignId);
		}
		if ($contentType !== '') {
			$sql .= " AND a.content_type = '".$this->db->escape($contentType)."'";
		}
		$sql .= ' GROUP BY a.entity, a.fk_campaign, a.content_type, a.fk_content, c.ref, c.label, o.title, o.ref, r.title, r.ref';
		$sql .= ' ORDER BY metric_value DESC'.$this->db->plimit(max(1, min(250, $limit)), max(0, $offset));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				'entity' => (int) $obj->entity,
				'campaign_ref' => (string) $obj->campaign_ref,
				'content_type' => (string) $obj->content_type,
				'content_id' => (int) $obj->fk_content,
				'content_label' => (string) $obj->content_label,
				'value' => (float) $obj->metric_value,
			);
		}
		return $rows;
	}

	/**
	 * Return exact public-content consultations from retained events.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
	 * @param int $limit Result limit
	 * @param int $offset Result offset
	 * @return array<int, array{entity:int,campaign_ref:string,content_type:string,content_id:int,content_label:string,value:float}>|false
	 */
	public function getExactTopContents($entities, $dateStart, $dateEnd, $filters, $limit = 50, $offset = 0)
	{
		$sql = 'SELECT e.entity, e.fk_campaign, e.content_type, e.fk_content,';
		$sql .= ' COALESCE(c.ref, \'\') AS campaign_ref,';
		$sql .= " CASE WHEN e.content_type = 'campaign' THEN COALESCE(c.label, c.ref)";
		$sql .= " WHEN e.content_type = 'offer' THEN COALESCE(o.title, o.ref)";
		$sql .= " WHEN e.content_type = 'request' THEN COALESCE(r.title, r.ref) ELSE '' END AS content_label,";
		$sql .= ' COUNT(*) AS metric_value';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event AS e';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
		$sql .= ' ON v.rowid = e.fk_visit AND v.entity = e.entity';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c';
		$sql .= ' ON c.rowid = e.fk_campaign AND c.entity = e.entity';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= " ON e.content_type = 'offer' AND o.rowid = e.fk_content AND o.entity = e.entity";
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= " ON e.content_type = 'request' AND r.rowid = e.fk_content AND r.entity = e.entity";
		$sql .= $this->buildEventWhere($entities, $dateStart, $dateEnd, $filters, 'e', 'v');
		$sql .= " AND e.event_type = 'page_view' AND e.content_type <> '' AND e.fk_content > 0";
		$sql .= ' GROUP BY e.entity, e.fk_campaign, e.content_type, e.fk_content, c.ref, c.label, o.title, o.ref, r.title, r.ref';
		$sql .= ' ORDER BY metric_value DESC'.$this->db->plimit(max(1, min(250, $limit)), max(0, $offset));
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return $this->fetchContentRows($resql);
	}

	/**
	 * Count grouped public contents for native pagination.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param int $campaignId Campaign filter
	 * @param string $contentType Content filter
	 * @return int|false
	 */
	public function countTopContents($entities, $dateStart, $dateEnd, $campaignId = 0, $contentType = '')
	{
		if (!in_array($contentType, array('', 'campaign', 'offer', 'request'), true)) {
			$contentType = '';
		}
		$inner = 'SELECT entity, fk_campaign, content_type, fk_content';
		$inner .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_daily';
		$inner .= $this->buildAggregateWhere($entities, $dateStart, $dateEnd);
		$inner .= " AND dimension_type = 'content' AND metric_code = 'page_views'";
		if ($campaignId > 0) {
			$inner .= ' AND fk_campaign = '.((int) $campaignId);
		}
		if ($contentType !== '') {
			$inner .= " AND content_type = '".$this->db->escape($contentType)."'";
		}
		$inner .= ' GROUP BY entity, fk_campaign, content_type, fk_content';
		$resql = $this->db->query('SELECT COUNT(*) AS total FROM ('.$inner.') AS content_rows');
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return is_object($obj) ? (int) $obj->total : 0;
	}

	/**
	 * Count exact grouped public contents for native pagination.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
	 * @return int|false
	 */
	public function countExactTopContents($entities, $dateStart, $dateEnd, $filters)
	{
		$inner = 'SELECT e.entity, e.fk_campaign, e.content_type, e.fk_content';
		$inner .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event AS e';
		$inner .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
		$inner .= ' ON v.rowid = e.fk_visit AND v.entity = e.entity';
		$inner .= $this->buildEventWhere($entities, $dateStart, $dateEnd, $filters, 'e', 'v');
		$inner .= " AND e.event_type = 'page_view' AND e.content_type <> '' AND e.fk_content > 0";
		$inner .= ' GROUP BY e.entity, e.fk_campaign, e.content_type, e.fk_content';
		$resql = $this->db->query('SELECT COUNT(*) AS total FROM ('.$inner.') AS content_rows');
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		return is_object($obj) ? (int) $obj->total : 0;
	}

	/**
	 * Return the funnel stages from anonymized aggregates.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @return array<string, float>|false
	 */
	public function getFunnel($entities, $dateStart, $dateEnd)
	{
		$pages = $this->getBreakdown($entities, $dateStart, $dateEnd, 'page', 'page_views', 100);
		$conversions = $this->getBreakdown($entities, $dateStart, $dateEnd, 'conversion', 'conversions', 100);
		if ($pages === false || $conversions === false) {
			return false;
		}
		$pageValues = array();
		foreach ($pages as $row) {
			$pageValues[$row['code']] = $row['value'];
		}
		$conversionValues = array();
		foreach ($conversions as $row) {
			$conversionValues[$row['code']] = $row['value'];
		}
		$consultation = 0.0;
		foreach (array('campaign_detail', 'offer_detail', 'request_detail') as $code) {
			$consultation += $pageValues[$code] ?? 0.0;
		}
		$form = 0.0;
		foreach (array('register', 'contact', 'offer_form', 'request_form', 'solicitation_form', 'report_form') as $code) {
			$form += $pageValues[$code] ?? 0.0;
		}
		$submission = 0.0;
		foreach (array('registration_completed', 'contact_sent', 'offer_submitted', 'request_submitted', 'solicitation_created', 'report_created') as $code) {
			$submission += $conversionValues[$code] ?? 0.0;
		}
		return array(
			'home' => $pageValues['home'] ?? 0.0,
			'consultation' => $consultation,
			'form' => $form,
			'submission' => $submission,
			'conversion' => array_sum($conversionValues),
		);
	}

	/**
	 * Return an exact funnel from retained details with all filters applied.
	 *
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
	 * @return array<string, float>|false
	 */
	public function getExactFunnel($entities, $dateStart, $dateEnd, $filters)
	{
		$eventTable = MAIN_DB_PREFIX.'emergencyhouse_analytics_event';
		$visitWhere = $this->buildVisitWhere($entities, $dateStart, $dateEnd, $filters, 'v');
		$homeFrom = ' FROM '.$eventTable.' AS h';
		$homeWhere = ' WHERE h.entity = v.entity AND h.fk_visit = v.rowid';
		$homeWhere .= " AND h.event_type = 'page_view' AND h.page_code = 'home'";
		$consultationJoin = ' INNER JOIN '.$eventTable.' AS c';
		$consultationJoin .= ' ON c.entity = h.entity AND c.fk_visit = h.fk_visit AND c.rowid > h.rowid';
		$consultationJoin .= " AND c.event_type = 'page_view'";
		$consultationJoin .= " AND c.page_code IN ('campaign_detail','offer_detail','request_detail')";
		$formJoin = ' INNER JOIN '.$eventTable.' AS f';
		$formJoin .= ' ON f.entity = c.entity AND f.fk_visit = c.fk_visit AND f.rowid > c.rowid';
		$formJoin .= " AND f.event_type = 'page_view'";
		$formJoin .= " AND f.page_code IN ('register','contact','offer_form','request_form','solicitation_form','report_form')";
		$submissionJoin = ' INNER JOIN '.$eventTable.' AS s';
		$submissionJoin .= ' ON s.entity = f.entity AND s.fk_visit = f.fk_visit AND s.rowid > f.rowid';
		$submissionJoin .= " AND s.event_type IN ('action','conversion')";
		$submissionJoin .= " AND s.event_code IN ('registration_completed','contact_sent','offer_draft_saved','offer_submitted',";
		$submissionJoin .= "'request_draft_saved','request_submitted','solicitation_created','report_created')";
		$conversionJoin = ' INNER JOIN '.$eventTable.' AS x';
		$conversionJoin .= ' ON x.entity = s.entity AND x.fk_visit = s.fk_visit AND x.rowid >= s.rowid';
		$conversionJoin .= " AND x.event_type = 'conversion'";
		$conversionJoin .= " AND x.event_code IN ('registration_completed','contact_sent','offer_submitted',";
		$conversionJoin .= "'request_submitted','solicitation_created','report_created')";

		$stages = array(
			'home' => 'EXISTS (SELECT 1'.$homeFrom.$homeWhere.')',
			'consultation' => 'EXISTS (SELECT 1'.$homeFrom.$consultationJoin.$homeWhere.')',
			'form' => 'EXISTS (SELECT 1'.$homeFrom.$consultationJoin.$formJoin.$homeWhere.')',
			'submission' => 'EXISTS (SELECT 1'.$homeFrom.$consultationJoin.$formJoin.$submissionJoin.$homeWhere.')',
			'conversion' => 'EXISTS (SELECT 1'.$homeFrom.$consultationJoin.$formJoin.$submissionJoin.$conversionJoin.$homeWhere.')',
		);
		$result = array();
		foreach ($stages as $code => $condition) {
			$sql = 'SELECT COUNT(*) AS metric_value';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
			$sql .= $visitWhere.' AND '.$condition;
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return false;
			}
			$obj = $this->db->fetch_object($resql);
			$result[$code] = is_object($obj) ? (float) $obj->metric_value : 0.0;
		}
		return $result;
	}

	/**
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
	 * @param string $alias Visit alias
	 * @return string
	 */
	private function buildVisitWhere($entities, $dateStart, $dateEnd, $filters, $alias)
	{
		$prefix = $alias.'.';
		$where = ' WHERE '.$prefix.'entity IN ('.$this->entityList($entities).')';
		$where .= " AND ".$prefix."date_start >= '".$this->db->idate($dateStart)."'";
		$where .= " AND ".$prefix."date_start < '".$this->db->idate($dateEnd)."'";
		foreach (array('source' => 'referrer_type', 'device' => 'device_type') as $filter => $column) {
			$value = isset($filters[$filter]) ? (string) $filters[$filter] : '';
			if ($value !== '') {
				$where .= " AND ".$prefix.$column." = '".$this->db->escape($value)."'";
			}
		}
		$eventFilters = array();
		$campaign = isset($filters['campaign']) ? (int) $filters['campaign'] : 0;
		if ($campaign > 0) {
			$eventFilters[] = 'e.fk_campaign = '.$campaign;
		}
		$page = isset($filters['page']) ? (string) $filters['page'] : '';
		if ($page !== '') {
			$eventFilters[] = "e.page_code = '".$this->db->escape($page)."'";
		}
		$contentType = isset($filters['content_type']) ? (string) $filters['content_type'] : '';
		if ($contentType !== '') {
			$eventFilters[] = "e.content_type = '".$this->db->escape($contentType)."'";
		}
		if (!empty($eventFilters)) {
			$where .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event AS e';
			$where .= ' WHERE e.entity = '.$prefix.'entity AND e.fk_visit = '.$prefix.'rowid';
			$where .= ' AND '.implode(' AND ', $eventFilters).')';
		}
		return $where;
	}

	/**
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param array{campaign?:int,page?:string,source?:string,device?:string,content_type?:string} $filters Filters
	 * @param string $eventAlias Event alias
	 * @param string $visitAlias Visit alias
	 * @return string
	 */
	private function buildEventWhere($entities, $dateStart, $dateEnd, $filters, $eventAlias, $visitAlias)
	{
		$e = $eventAlias.'.';
		$v = $visitAlias.'.';
		$where = ' WHERE '.$e.'entity IN ('.$this->entityList($entities).')';
		$where .= " AND ".$e."date_event >= '".$this->db->idate($dateStart)."'";
		$where .= " AND ".$e."date_event < '".$this->db->idate($dateEnd)."'";
		$campaign = isset($filters['campaign']) ? (int) $filters['campaign'] : 0;
		if ($campaign > 0) {
			$where .= ' AND '.$e.'fk_campaign = '.$campaign;
		}
		foreach (array('page' => 'page_code', 'content_type' => 'content_type') as $filter => $column) {
			$value = isset($filters[$filter]) ? (string) $filters[$filter] : '';
			if ($value !== '') {
				$where .= " AND ".$e.$column." = '".$this->db->escape($value)."'";
			}
		}
		foreach (array('source' => 'referrer_type', 'device' => 'device_type') as $filter => $column) {
			$value = isset($filters[$filter]) ? (string) $filters[$filter] : '';
			if ($value !== '') {
				$where .= " AND ".$v.$column." = '".$this->db->escape($value)."'";
			}
		}
		return $where;
	}

	/**
	 * @param array<int, int> $entities Entity scope
	 * @param int $dateStart Inclusive timestamp
	 * @param int $dateEnd Exclusive timestamp
	 * @param string $alias Optional alias
	 * @return string
	 */
	private function buildAggregateWhere($entities, $dateStart, $dateEnd, $alias = '')
	{
		$prefix = $alias !== '' ? $alias.'.' : '';
		$where = ' WHERE '.$prefix.'entity IN ('.$this->entityList($entities).')';
		$where .= " AND ".$prefix."metric_date >= '".$this->db->escape(dol_print_date($dateStart, '%Y-%m-%d'))."'";
		$where .= " AND ".$prefix."metric_date < '".$this->db->escape(dol_print_date($dateEnd, '%Y-%m-%d'))."'";
		return $where;
	}

	/**
	 * @param string $sql Trusted breakdown SQL
	 * @return array<int, array{code:string,value:float}>|false
	 */
	private function fetchBreakdownRows($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array('code' => (string) $obj->dimension_code, 'value' => (float) $obj->metric_value);
		}
		return $rows;
	}

	/**
	 * @param resource|object $resql Successful database result
	 * @return array<int, array{entity:int,campaign_ref:string,content_type:string,content_id:int,content_label:string,value:float}>
	 */
	private function fetchContentRows($resql)
	{
		$rows = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$rows[] = array(
				'entity' => (int) $obj->entity,
				'campaign_ref' => (string) $obj->campaign_ref,
				'content_type' => (string) $obj->content_type,
				'content_id' => (int) $obj->fk_content,
				'content_label' => (string) $obj->content_label,
				'value' => (float) $obj->metric_value,
			);
		}
		return $rows;
	}

	/**
	 * @param array<int, int> $entities Entity scope
	 * @return string
	 */
	private function entityList($entities)
	{
		$clean = array();
		foreach ($entities as $entity) {
			if ((int) $entity > 0) {
				$clean[(int) $entity] = (int) $entity;
			}
		}
		return !empty($clean) ? implode(',', $clean) : '0';
	}

	/**
	 * @return array<string, float>
	 */
	private function emptyOverview()
	{
		return array(
			'page_views' => 0.0,
			'visits' => 0.0,
			'unique_visitors' => 0.0,
			'bounce_rate' => 0.0,
			'average_duration' => 0.0,
			'pages_per_visit' => 0.0,
			'conversion_rate' => 0.0,
		);
	}
}
