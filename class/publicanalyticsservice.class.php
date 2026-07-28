<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/emergencyhouse/class/encryptionservice.class.php');
dol_include_once('/emergencyhouse/class/campaign.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');

/**
 * First-party, privacy-preserving audience measurement for the public portal.
 *
 * The service never stores an IP address, a complete user agent, a URL, query
 * parameters, a public-account identifier or user-provided content.
 */
class EmergencyHousePublicAnalyticsService
{
	public const VISITOR_COOKIE = 'EH_ANALYTICS';
	public const OPTOUT_COOKIE = 'EH_ANALYTICS_OPTOUT';

	/** @var DoliDB */
	private $db;
	/** @var int */
	private $entity;
	/** @var EmergencyHouseEncryptionService */
	private $encryption;
	/** @var string */
	private $userAgent;
	/** @var string */
	private $referrer;
	/** @var string */
	private $requestHost;
	/** @var int */
	private $requestPort = 0;
	/** @var int */
	private $currentVisitId = 0;
	/** @var int */
	private $currentPageViewEventId = 0;
	/** @var string */
	private $visitorHash = '';
	/** @var string */
	public $error = '';

	/**
	 * @param DoliDB $db Database handler
	 * @param int $entity Entity
	 * @param string $userAgent Raw user agent used only for immediate categorization
	 * @param string $referrer Raw referrer used only for immediate categorization
	 * @param string $requestHost Current HTTP host
	 */
	public function __construct($db, $entity, $userAgent = '', $referrer = '', $requestHost = '')
	{
		$this->db = $db;
		$this->entity = max(1, (int) $entity);
		$this->encryption = new EmergencyHouseEncryptionService();
		$this->userAgent = substr($userAgent, 0, 512);
		$this->referrer = substr($referrer, 0, 2048);
		$authority = parse_url('https://'.trim($requestHost));
		$this->requestHost = is_array($authority) && !empty($authority['host'])
			? $this->normalizeHost((string) $authority['host'])
			: $this->normalizeHost($requestHost);
		$this->requestPort = is_array($authority) && isset($authority['port'])
			? (int) $authority['port']
			: ($this->isSecureTransport() ? 443 : 80);
	}

	/**
	 * Is audience measurement active and available for this request?
	 *
	 * @return bool
	 */
	public function isTrackingAllowed()
	{
		return getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_ENABLED', 0) === 1
			&& !$this->isOptedOut()
			&& !$this->isBot()
			&& $this->encryption->isAvailable();
	}

	/**
	 * Check that the request carries a valid fixed-expiry visitor cookie.
	 *
	 * @return bool
	 */
	public function hasValidVisitorCookie()
	{
		$cookie = isset($_COOKIE[self::VISITOR_COOKIE]) && is_string($_COOKIE[self::VISITOR_COOKIE])
			? $_COOKIE[self::VISITOR_COOKIE]
			: '';
		return $cookie !== '' && $this->isValidVisitorCookie($cookie);
	}

	/**
	 * Record one HTML page view.
	 *
	 * @param string $pageCode Controlled page code
	 * @param bool $authenticated Only an anonymous/authenticated category is stored
	 * @param int $campaignId Public campaign ID
	 * @param string $contentType campaign, offer or request
	 * @param int $contentId Public content ID
	 * @return int Visit ID, zero when tracking is disabled, -1 on error
	 */
	public function recordPageView($pageCode, $authenticated, $campaignId = 0, $contentType = '', $contentId = 0)
	{
		if (!$this->isTrackingAllowed()) {
			return 0;
		}
		if (!in_array($pageCode, self::allowedPageCodes(), true)) {
			$pageCode = 'other';
		}
		$content = $this->normalizePublicContent($campaignId, $contentType, $contentId);

		return $this->writeEvent(
			'page_view',
			$pageCode,
			$pageCode,
			(bool) $authenticated,
			$content['campaign_id'],
			$content['content_type'],
			$content['content_id']
		);
	}

	/**
	 * Record a successful controlled public action or conversion.
	 *
	 * @param string $eventCode Controlled event code
	 * @param bool $conversion Whether this event is a conversion
	 * @param string $pageCode Controlled page code
	 * @param bool $authenticated Authentication category
	 * @param int $campaignId Campaign ID
	 * @param string $contentType Public content type
	 * @param int $contentId Public content ID
	 * @return int Visit ID, zero when tracking is disabled, -1 on error
	 */
	public function recordEvent($eventCode, $conversion, $pageCode, $authenticated, $campaignId = 0, $contentType = '', $contentId = 0)
	{
		if (!$this->isTrackingAllowed()) {
			return 0;
		}
		if (!in_array($eventCode, self::allowedEventCodes(), true)) {
			$this->error = 'ErrorAnalyticsEventNotAllowed';
			return -1;
		}
		if (!in_array($pageCode, self::allowedPageCodes(), true)) {
			$pageCode = 'other';
		}
		$content = $this->normalizePublicContent($campaignId, $contentType, $contentId);

		return $this->writeEvent(
			$conversion ? 'conversion' : 'action',
			$eventCode,
			$pageCode,
			(bool) $authenticated,
			$content['campaign_id'],
			$content['content_type'],
			$content['content_id']
		);
	}

	/**
	 * Mark the current anonymous visit as engaged and update active duration.
	 *
	 * @param int $activeSeconds Visible active seconds, bounded server-side
	 * @param string $engagementToken Signed page-view event token
	 * @return bool
	 */
	public function markEngaged($activeSeconds, $engagementToken)
	{
		if (!$this->isTrackingAllowed()) {
			return true;
		}
		$visitorHash = $this->getVisitorHash(false);
		$eventId = $this->parseEngagementToken($engagementToken);
		if ($visitorHash === '' || $eventId <= 0) {
			return false;
		}
		$activeSeconds = max(0, min(7200, (int) $activeSeconds));
		$threshold = max(1, min(300, getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_ENGAGEMENT_SECONDS', 10)));
		$cutoff = dol_time_plus_duree(
			dol_now(),
			-max(5, min(1440, getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_SESSION_MINUTES', 30))),
			'i'
		);
		$lockName = 'eh_analytics_'.$this->entity.'_'.substr(hash('sha256', $visitorHash), 0, 32);
		if (!$this->acquireVisitLock($lockName)) {
			return false;
		}
		$this->db->begin();
		$sql = 'SELECT e.rowid, e.fk_visit FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event AS e';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit AS v';
		$sql .= ' ON v.rowid = e.fk_visit AND v.entity = e.entity';
		$sql .= ' WHERE e.entity = '.$this->entity.' AND e.rowid = '.$eventId;
		$sql .= " AND e.event_type = 'page_view'";
		$sql .= " AND v.visitor_hash = '".$this->db->escape($visitorHash)."'";
		$sql .= " AND v.date_last_activity >= '".$this->db->idate($cutoff)."' FOR UPDATE";
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;
		if (!$resql || !is_object($obj)) {
			$this->error = $resql ? 'ErrorAnalyticsVisitUnavailable' : $this->db->lasterror();
			$this->db->rollback();
			$this->releaseVisitLock($lockName);
			return false;
		}
		$visitId = (int) $obj->fk_visit;
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event';
		$sql .= ' SET active_seconds = GREATEST(active_seconds, '.$activeSeconds.')';
		$sql .= ' WHERE entity = '.$this->entity.' AND rowid = '.$eventId;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			$this->releaseVisitLock($lockName);
			return false;
		}
		$sumExpression = '(SELECT COALESCE(SUM(e2.active_seconds), 0)';
		$sumExpression .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event AS e2';
		$sumExpression .= ' WHERE e2.entity = '.$this->entity.' AND e2.fk_visit = '.$visitId;
		$sumExpression .= " AND e2.event_type = 'page_view')";
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit';
		$sql .= ' SET active_seconds = '.$sumExpression.',';
		$sql .= ' is_engaged = CASE WHEN '.$sumExpression.' >= '.$threshold.' THEN 1 ELSE is_engaged END,';
		$sql .= " date_last_activity = '".$this->db->idate(dol_now())."'";
		$sql .= ' WHERE entity = '.$this->entity.' AND rowid = '.$visitId;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			$this->releaseVisitLock($lockName);
			return false;
		}
		$this->db->commit();
		$this->releaseVisitLock($lockName);
		return true;
	}

	/**
	 * Apply the visitor's audience-measurement preference.
	 *
	 * @param bool $optOut True to refuse measurement
	 * @return void
	 */
	public function setOptOut($optOut)
	{
		if ($optOut) {
			$this->setCookie(self::OPTOUT_COOKIE, '1', strtotime('+13 months'));
			$this->deleteCookie(self::VISITOR_COOKIE);
			$this->visitorHash = '';
			$this->currentVisitId = 0;
			$this->currentPageViewEventId = 0;
			return;
		}

		$this->deleteCookie(self::OPTOUT_COOKIE);
		$this->deleteCookie(self::VISITOR_COOKIE);
		$this->visitorHash = '';
		$this->currentVisitId = 0;
		$this->currentPageViewEventId = 0;
	}

	/**
	 * Return HTML body attributes required by the same-origin engagement beacon.
	 *
	 * @param string $csrfToken Native Dolibarr token
	 * @return string
	 */
	public function getBodyAttributes($csrfToken)
	{
		if (
			!$this->isTrackingAllowed()
			|| $this->getVisitorHash(false) === ''
			|| $this->currentPageViewEventId <= 0
		) {
			return '';
		}
		$endpoint = function_exists('emergencyhousePublicUrl')
			? emergencyhousePublicUrl('analytics.php')
			: '';
		if ($endpoint === '') {
			return '';
		}
		$threshold = max(1, min(300, getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_ENGAGEMENT_SECONDS', 10)));
		$engagementPayload = $this->entity.'.'.$this->currentPageViewEventId;
		$engagementSignature = $this->encryption->hashLookup($engagementPayload, 'analytics-engagement-event');
		if (!is_string($engagementSignature)) {
			return '';
		}

		return ' data-analytics-endpoint="'.dol_escape_htmltag($endpoint).'"'
			.' data-analytics-token="'.dol_escape_htmltag($csrfToken).'"'
			.' data-analytics-engagement="'.dol_escape_htmltag($engagementPayload.'.'.$engagementSignature).'"'
			.' data-analytics-threshold="'.$threshold.'"';
	}

	/**
	 * Validate the Origin header of an internal analytics POST.
	 *
	 * @return bool
	 */
	public function isSameOriginPost()
	{
		if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
			return false;
		}
		$origin = isset($_SERVER['HTTP_ORIGIN']) && is_string($_SERVER['HTTP_ORIGIN'])
			? trim($_SERVER['HTTP_ORIGIN'])
			: '';
		$parts = $origin !== '' ? parse_url($origin) : false;
		if (!is_array($parts) || empty($parts['host'])) {
			return false;
		}
		$originHost = $this->normalizeHost((string) $parts['host']);
		$scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
		$originPort = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
		return $originHost !== ''
			&& hash_equals($this->requestHost, $originHost)
			&& $originPort === $this->requestPort
			&& ($scheme === 'https' || in_array($originHost, array('localhost', '127.0.0.1', '[::1]'), true));
	}

	/**
	 * Resolve a controlled page code from the executing public controller.
	 *
	 * @param string $scriptName Script path
	 * @return string
	 */
	public static function pageCodeFromScript($scriptName)
	{
		$script = str_replace('\\', '/', strtolower($scriptName));
		$map = array(
			'/account/data.php' => 'account_data',
			'/account/profile.php' => 'account_profile',
			'/account/index.php' => 'account_dashboard',
			'/allocation/view.php' => 'allocation_detail',
			'/allocation/index.php' => 'allocation_list',
			'/auth/forgot.php' => 'password_forgot',
			'/auth/login.php' => 'login',
			'/auth/magic.php' => 'magic_login',
			'/auth/register.php' => 'register',
			'/auth/resend.php' => 'verification_resend',
			'/auth/reset.php' => 'password_reset',
			'/auth/verify.php' => 'email_verification',
			'/offer/edit.php' => 'offer_form',
			'/offer/view.php' => 'offer_detail',
			'/offer/index.php' => 'offer_list',
			'/request/edit.php' => 'request_form',
			'/request/view.php' => 'request_detail',
			'/request/index.php' => 'request_list',
			'/solicitation/create.php' => 'solicitation_form',
			'/solicitation/view.php' => 'solicitation_detail',
			'/solicitation/index.php' => 'solicitation_list',
			'/report/create.php' => 'report_form',
			'/accessibility.php' => 'accessibility',
			'/audience.php' => 'audience_privacy',
			'/campaign-request.php' => 'campaign_request',
			'/campaign.php' => 'campaign_detail',
			'/contact.php' => 'contact',
			'/privacy.php' => 'privacy',
			'/terms.php' => 'terms',
			'/index.php' => 'home',
		);
		foreach ($map as $suffix => $pageCode) {
			if (substr($script, -strlen($suffix)) === $suffix) {
				return $pageCode;
			}
		}
		return 'other';
	}

	/**
	 * @return list<string>
	 */
	public static function allowedPageCodes()
	{
		return array(
			'home', 'campaign_detail', 'campaign_request', 'offer_list', 'offer_detail', 'offer_form',
			'request_list', 'request_detail', 'request_form', 'contact', 'login',
			'register', 'password_forgot', 'password_reset', 'magic_login',
			'email_verification', 'verification_resend', 'account_dashboard',
			'account_profile', 'account_data', 'solicitation_list',
			'solicitation_form', 'solicitation_detail', 'allocation_list',
			'allocation_detail', 'report_form', 'privacy', 'terms', 'accessibility',
			'audience_privacy', 'not_found', 'other',
		);
	}

	/**
	 * @return list<string>
	 */
	public static function allowedEventCodes()
	{
		return array(
			'registration_completed', 'email_verified', 'login_success',
			'contact_sent', 'campaign_requested', 'offer_draft_saved', 'offer_submitted',
			'request_draft_saved', 'request_submitted', 'solicitation_created',
			'solicitation_updated', 'message_sent', 'report_created',
			'personal_data_exported', 'deletion_requested', 'allocation_updated',
			'allocation_address_revealed', 'address_sharing_updated',
		);
	}

	/**
	 * Write one event and update the current visit in one transaction.
	 *
	 * @param string $eventType page_view, action or conversion
	 * @param string $eventCode Controlled event
	 * @param string $pageCode Controlled page
	 * @param bool $authenticated Authentication category
	 * @param int $campaignId Campaign ID
	 * @param string $contentType Content type
	 * @param int $contentId Content ID
	 * @return int
	 */
	private function writeEvent($eventType, $eventCode, $pageCode, $authenticated, $campaignId, $contentType, $contentId)
	{
		$visitorHash = $this->getVisitorHash(true);
		if ($visitorHash === '') {
			return 0;
		}
		$now = dol_now();
		$sessionMinutes = max(5, min(1440, getDolGlobalInt('EMERGENCYHOUSE_ANALYTICS_SESSION_MINUTES', 30)));
		$cutoff = dol_time_plus_duree($now, -$sessionMinutes, 'i');
		$lockName = 'eh_analytics_'.$this->entity.'_'.substr(hash('sha256', $visitorHash), 0, 32);
		if (!$this->acquireVisitLock($lockName)) {
			return -1;
		}
		$this->db->begin();
		$visit = null;
		$sql = 'SELECT rowid, pageview_count, is_engaged, has_conversion';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit';
		$sql .= ' WHERE entity = '.$this->entity;
		$sql .= " AND visitor_hash = '".$this->db->escape($visitorHash)."'";
		$sql .= " AND date_last_activity >= '".$this->db->idate($cutoff)."'";
		$sql .= ' ORDER BY rowid DESC'.$this->db->plimit(1).' FOR UPDATE';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			$this->releaseVisitLock($lockName);
			return -1;
		}
		$visit = $this->db->fetch_object($resql);
		$isPageView = $eventType === 'page_view';
		$isConversion = $eventType === 'conversion';
		if (is_object($visit)) {
			$visitId = (int) $visit->rowid;
			$pageviews = (int) $visit->pageview_count + ($isPageView ? 1 : 0);
			$engaged = !empty($visit->is_engaged) || $pageviews >= 2 || $isConversion;
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit';
			$sql .= " SET date_last_activity = '".$this->db->idate($now)."',";
			$sql .= ' pageview_count = '.$pageviews.', is_engaged = '.($engaged ? 1 : 0).',';
			$sql .= ' has_conversion = '.(!empty($visit->has_conversion) || $isConversion ? 1 : 0).',';
			$sql .= " auth_context = '".($authenticated ? 'authenticated' : 'anonymous')."'";
			if ($isPageView) {
				$sql .= ", exit_page_code = '".$this->db->escape($pageCode)."'";
			}
			$sql .= ' WHERE entity = '.$this->entity.' AND rowid = '.$visitId;
		} else {
			list($referrerType, $referrerDomain) = $this->classifyReferrer();
			$visitId = 0;
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_analytics_visit';
			$sql .= ' (entity, visitor_hash, date_start, date_last_activity, pageview_count, active_seconds,';
			$sql .= ' is_engaged, has_conversion, landing_page_code, exit_page_code, referrer_type,';
			$sql .= ' referrer_domain, device_type, auth_context, date_creation) VALUES (';
			$sql .= $this->entity.", '".$this->db->escape($visitorHash)."',";
			$sql .= " '".$this->db->idate($now)."', '".$this->db->idate($now)."',";
			$sql .= ($isPageView ? '1' : '0').', 0, '.($isConversion ? '1' : '0').', '.($isConversion ? '1' : '0').',';
			$sql .= " '".$this->db->escape($pageCode)."', '".$this->db->escape($pageCode)."',";
			$sql .= " '".$this->db->escape($referrerType)."', '".$this->db->escape($referrerDomain)."',";
			$sql .= " '".$this->db->escape($this->classifyDevice())."',";
			$sql .= " '".($authenticated ? 'authenticated' : 'anonymous')."',";
			$sql .= " '".$this->db->idate($now)."')";
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			$this->releaseVisitLock($lockName);
			return -1;
		}
		if ($visitId <= 0) {
			$visitId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_analytics_visit');
		}
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_analytics_event';
		$sql .= ' (entity, fk_visit, event_type, event_code, page_code, fk_campaign, content_type, fk_content, active_seconds, date_event) VALUES (';
		$sql .= $this->entity.', '.$visitId.", '".$this->db->escape($eventType)."',";
		$sql .= " '".$this->db->escape($eventCode)."', '".$this->db->escape($pageCode)."',";
		$sql .= ((int) $campaignId).", '".$this->db->escape($contentType)."', ".((int) $contentId).',';
		$sql .= " 0, '".$this->db->idate($now)."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			$this->releaseVisitLock($lockName);
			return -1;
		}
		$eventId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'emergencyhouse_analytics_event');
		if ($eventId <= 0) {
			$this->error = 'ErrorAnalyticsEventNotCreated';
			$this->db->rollback();
			$this->releaseVisitLock($lockName);
			return -1;
		}
		$this->db->commit();
		$this->releaseVisitLock($lockName);
		$this->currentVisitId = $visitId;
		$this->currentPageViewEventId = $isPageView ? $eventId : 0;
		return $visitId;
	}

	/**
	 * Validate a page-view token without exposing a visitor identifier.
	 *
	 * @param string $token Signed token
	 * @return int Event ID or zero
	 */
	private function parseEngagementToken($token)
	{
		if (!preg_match('/^([0-9]+)\.([0-9]+)\.([a-f0-9]{64})$/', $token, $matches)) {
			return 0;
		}
		if ((int) $matches[1] !== $this->entity) {
			return 0;
		}
		$payload = $matches[1].'.'.$matches[2];
		$expected = $this->encryption->hashLookup($payload, 'analytics-engagement-event');
		if (!is_string($expected) || !hash_equals($expected, $matches[3])) {
			return 0;
		}
		return (int) $matches[2];
	}

	/**
	 * Return a keyed visitor hash, creating the fixed-expiry cookie when asked.
	 *
	 * @param bool $create Create a cookie when absent
	 * @return string
	 */
	private function getVisitorHash($create)
	{
		if ($this->visitorHash !== '') {
			return $this->visitorHash;
		}
		$cookie = isset($_COOKIE[self::VISITOR_COOKIE]) && is_string($_COOKIE[self::VISITOR_COOKIE])
			? $_COOKIE[self::VISITOR_COOKIE]
			: '';
		if (!$this->isValidVisitorCookie($cookie)) {
			if (!$create || headers_sent()) {
				return '';
			}
			$createdAt = dol_now();
			$random = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
			$payload = 'v1.'.$createdAt.'.'.$random;
			$signature = $this->encryption->hashLookup($payload, 'analytics-cookie-'.$this->entity);
			if (!is_string($signature)) {
				$this->error = $this->encryption->error;
				return '';
			}
			$cookie = $payload.'.'.$signature;
			$expires = strtotime('+13 months', $createdAt);
			$this->setCookie(self::VISITOR_COOKIE, $cookie, is_int($expires) ? $expires : $createdAt + 33696000);
			$_COOKIE[self::VISITOR_COOKIE] = $cookie;
		}
		$hash = $this->encryption->hashLookup($cookie, 'analytics-visitor-'.$this->entity);
		if (!is_string($hash)) {
			$this->error = $this->encryption->error;
			return '';
		}
		$this->visitorHash = $hash;
		return $hash;
	}

	/**
	 * @param string $cookie Cookie value
	 * @return bool
	 */
	private function isValidVisitorCookie($cookie)
	{
		if (!preg_match('/^(v1)\.([0-9]{10})\.([A-Za-z0-9_-]{40,64})\.([a-f0-9]{64})$/', $cookie, $matches)) {
			return false;
		}
		$createdAt = (int) $matches[2];
		$expires = strtotime('+13 months', $createdAt);
		if ($createdAt <= 0 || !is_int($expires) || $expires <= dol_now()) {
			return false;
		}
		$payload = $matches[1].'.'.$matches[2].'.'.$matches[3];
		$expected = $this->encryption->hashLookup($payload, 'analytics-cookie-'.$this->entity);
		return is_string($expected) && hash_equals($expected, $matches[4]);
	}

	/**
	 * @return bool
	 */
	private function isOptedOut()
	{
		return isset($_COOKIE[self::OPTOUT_COOKIE])
			&& is_string($_COOKIE[self::OPTOUT_COOKIE])
			&& hash_equals('1', $_COOKIE[self::OPTOUT_COOKIE]);
	}

	/**
	 * @return bool
	 */
	private function isBot()
	{
		if ($this->userAgent === '') {
			return true;
		}
		return preg_match(
			'/(?:bot|crawler|spider|slurp|bingpreview|headless|monitor|uptime|facebookexternalhit|whatsapp|curl|wget|python-requests|go-http-client|libwww-perl|scrapy)/i',
			$this->userAgent
		) === 1;
	}

	/**
	 * @return string
	 */
	private function classifyDevice()
	{
		if (
			preg_match('/(?:ipad|tablet|kindle|silk)/i', $this->userAgent)
			|| (stripos($this->userAgent, 'android') !== false && stripos($this->userAgent, 'mobile') === false)
		) {
			return 'tablet';
		}
		if (preg_match('/(?:mobile|iphone|ipod|android.*mobile|windows phone)/i', $this->userAgent)) {
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private function classifyReferrer()
	{
		$parts = $this->referrer !== '' ? parse_url($this->referrer) : false;
		if (!is_array($parts) || empty($parts['host'])) {
			return array('direct', '');
		}
		$host = $this->normalizeHost((string) $parts['host']);
		if ($host === '') {
			return array('direct', '');
		}
		if ($this->requestHost !== '' && hash_equals($this->requestHost, $host)) {
			return array('internal', '');
		}
		if (preg_match('/(?:google\.|bing\.|duckduckgo\.|qwant\.|yahoo\.|ecosia\.)/i', $host)) {
			return array('search', $host);
		}
		if (preg_match('/(?:facebook\.|instagram\.|linkedin\.|twitter\.|x\.com$|t\.co$|tiktok\.|youtube\.|youtu\.be$|threads\.net$|bsky\.app$)/i', $host)) {
			return array('social', $host);
		}
		return array('external', $host);
	}

	/**
	 * Serialize visit creation and updates for one anonymous visitor.
	 *
	 * @param string $lockName Bounded keyed lock name
	 * @return bool
	 */
	private function acquireVisitLock($lockName)
	{
		$sql = "SELECT GET_LOCK('".$this->db->escape($lockName)."', 2) AS acquired";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		if (!is_object($obj) || (int) $obj->acquired !== 1) {
			$this->error = 'ErrorAnalyticsVisitLocked';
			return false;
		}
		return true;
	}

	/**
	 * @param string $lockName Bounded keyed lock name
	 * @return void
	 */
	private function releaseVisitLock($lockName)
	{
		$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lockName)."')");
	}

	/**
	 * Keep object identifiers only for content that is currently public.
	 *
	 * @param int $campaignId Campaign ID
	 * @param string $contentType Content type
	 * @param int $contentId Content ID
	 * @return array{campaign_id:int,content_type:string,content_id:int}
	 */
	private function normalizePublicContent($campaignId, $contentType, $contentId)
	{
		$campaignId = max(0, (int) $campaignId);
		$contentId = max(0, (int) $contentId);
		if (!in_array($contentType, array('campaign', 'offer', 'request'), true)) {
			$contentType = '';
			$contentId = 0;
		}
		$now = $this->db->idate(dol_now());
		if ($contentType === 'campaign' && $contentId > 0) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
			$sql .= ' WHERE entity = '.$this->entity.' AND rowid = '.$contentId;
			$sql .= ' AND status = '.EmergencyHouseCampaign::STATUS_PUBLISHED;
			$sql .= " AND date_start <= '".$now."' AND (date_end IS NULL OR date_end >= '".$now."')";
			if (!$this->rowExists($sql)) {
				$contentType = '';
				$contentId = 0;
			} else {
				$campaignId = $contentId;
			}
		} elseif ($contentType === 'offer' && $contentId > 0) {
			$sql = 'SELECT o.fk_campaign FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c';
			$sql .= ' ON c.rowid = o.fk_campaign AND c.entity = o.entity';
			$sql .= ' WHERE o.entity = '.$this->entity.' AND o.rowid = '.$contentId;
			$sql .= ' AND o.status = '.EmergencyHouseOffer::STATUS_PUBLISHED;
			$sql .= ' AND c.status = '.EmergencyHouseCampaign::STATUS_PUBLISHED;
			$sql .= " AND c.date_start <= '".$now."' AND (c.date_end IS NULL OR c.date_end >= '".$now."')";
			$campaignId = $this->fetchCampaignId($sql);
			if ($campaignId <= 0) {
				$contentType = '';
				$contentId = 0;
			}
		} elseif ($contentType === 'request' && $contentId > 0) {
			$sql = 'SELECT r.fk_campaign FROM '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_campaign AS c';
			$sql .= ' ON c.rowid = r.fk_campaign AND c.entity = r.entity';
			$sql .= ' WHERE r.entity = '.$this->entity.' AND r.rowid = '.$contentId;
			$sql .= ' AND r.status IN ('.EmergencyHouseRequest::STATUS_ACTIVE.','.EmergencyHouseRequest::STATUS_PARTIALLY_ALLOCATED.')';
			$sql .= " AND r.visibility = 'public' AND c.status = ".EmergencyHouseCampaign::STATUS_PUBLISHED;
			$sql .= " AND c.date_start <= '".$now."' AND (c.date_end IS NULL OR c.date_end >= '".$now."')";
			$campaignId = $this->fetchCampaignId($sql);
			if ($campaignId <= 0) {
				$contentType = '';
				$contentId = 0;
			}
		} else {
			$contentType = '';
			$contentId = 0;
		}
		if ($campaignId > 0 && !$this->campaignIsPublic($campaignId)) {
			$campaignId = 0;
		}
		return array(
			'campaign_id' => $campaignId,
			'content_type' => $contentType,
			'content_id' => $contentId,
		);
	}

	/**
	 * @param int $campaignId Campaign ID
	 * @return bool
	 */
	private function campaignIsPublic($campaignId)
	{
		$now = $this->db->idate(dol_now());
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
		$sql .= ' WHERE entity = '.$this->entity.' AND rowid = '.((int) $campaignId);
		$sql .= ' AND status = '.EmergencyHouseCampaign::STATUS_PUBLISHED;
		$sql .= " AND date_start <= '".$now."' AND (date_end IS NULL OR date_end >= '".$now."')";
		return $this->rowExists($sql);
	}

	/**
	 * @param string $sql Trusted SQL selecting fk_campaign
	 * @return int
	 */
	private function fetchCampaignId($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		return is_object($obj) ? (int) $obj->fk_campaign : 0;
	}

	/**
	 * @param string $sql Trusted existence SQL
	 * @return bool
	 */
	private function rowExists($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return is_object($this->db->fetch_object($resql));
	}

	/**
	 * @param string $host Host with optional port
	 * @return string
	 */
	private function normalizeHost($host)
	{
		$host = strtolower(trim($host));
		$normalized = preg_replace('/:\d+$/', '', $host);
		if (
			!is_string($normalized)
			|| $normalized === ''
			|| strlen($normalized) > 255
			|| !preg_match('/^[a-z0-9.\-\[\]:]+$/', $normalized)
		) {
			return '';
		}
		return rtrim($normalized, '.');
	}

	/**
	 * @param string $name Cookie name
	 * @param string $value Cookie value
	 * @param int|false $expires Expiry
	 * @return void
	 */
	private function setCookie($name, $value, $expires)
	{
		if (headers_sent()) {
			return;
		}
		$options = array(
			'expires' => is_int($expires) ? $expires : dol_now() + 33696000,
			'path' => '/',
			'secure' => $this->isSecureTransport(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		setcookie($name, $value, $options);
	}

	/**
	 * @param string $name Cookie name
	 * @return void
	 */
	private function deleteCookie($name)
	{
		if (!headers_sent()) {
			setcookie($name, '', array(
				'expires' => dol_now() - 3600,
				'path' => '/',
				'secure' => $this->isSecureTransport(),
				'httponly' => true,
				'samesite' => 'Lax',
			));
		}
		unset($_COOKIE[$name]);
	}

	/**
	 * @return bool
	 */
	private function isSecureTransport()
	{
		$https = isset($_SERVER['HTTPS']) && is_string($_SERVER['HTTPS'])
			? strtolower($_SERVER['HTTPS'])
			: '';
		$forwarded = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && is_string($_SERVER['HTTP_X_FORWARDED_PROTO'])
			? strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]))
			: '';
		return ($https !== '' && $https !== 'off' && $https !== '0') || $forwarded === 'https';
	}
}
