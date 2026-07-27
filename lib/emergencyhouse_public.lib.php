<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Build a public module URL.
 *
 * @param string $path Path relative to public/
 * @param array<string, int|string> $parameters Query parameters
 * @return string
 */
function emergencyhousePublicUrl($path = 'index.php', array $parameters = array())
{
	$url = dol_buildpath('/emergencyhouse/public/'.ltrim($path, '/'), 1);
	if (!empty($parameters)) {
		$url .= '?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
	}
	return $url;
}

/**
 * Build an absolute public URL for email and external notifications.
 *
 * @param string $path Relative public path
 * @param array<string, scalar> $parameters Query parameters
 * @return string
 */
function emergencyhousePublicAbsoluteUrl($path = 'index.php', array $parameters = array())
{
	$url = dol_buildpath('/emergencyhouse/public/'.ltrim($path, '/'), 2);
	if (!empty($parameters)) {
		$url .= '?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
	}
	return $url;
}

/**
 * Send strict headers before rendering the isolated public surface.
 *
 * @return void
 */
function emergencyhousePublicSendSecurityHeaders()
{
	if (headers_sent()) {
		return;
	}
	header('Content-Security-Policy: default-src \'self\'; base-uri \'self\'; form-action \'self\'; frame-ancestors \'none\'; object-src \'none\'; img-src \'self\' data:; style-src \'self\'; script-src \'self\'; connect-src \'self\'');
	header('Referrer-Policy: no-referrer');
	header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: DENY');
	header('Cross-Origin-Opener-Policy: same-origin');
	header('Cache-Control: no-store, private');
}

/**
 * HTTPS is mandatory outside loopback development.
 *
 * @return bool
 */
function emergencyhousePublicIsSecureTransport()
{
	$https = isset($_SERVER['HTTPS']) && is_string($_SERVER['HTTPS']) ? strtolower($_SERVER['HTTPS']) : '';
	if ($https !== '' && $https !== 'off' && $https !== '0') {
		return true;
	}
	$forwarded = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && is_string($_SERVER['HTTP_X_FORWARDED_PROTO'])
		? strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]))
		: '';
	if ($forwarded === 'https') {
		return true;
	}
	$host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
	$host = preg_replace('/:\d+$/', '', $host);
	return in_array($host, array('localhost', '127.0.0.1', '[::1]'), true);
}

/**
 * Return a bounded remote address for hashing only.
 *
 * @return string
 */
function emergencyhousePublicRemoteAddress()
{
	$value = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	return substr($value, 0, 64);
}

/**
 * Return a bounded user-agent fingerprint source.
 *
 * @return string
 */
function emergencyhousePublicUserAgent()
{
	$value = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	return substr($value, 0, 512);
}

/**
 * Render the standalone public header.
 *
 * @param string $title Translated page title
 * @param EmergencyHousePublicAccount|null $account Authenticated account
 * @param string $active Active navigation code
 * @param bool $allowIndex Permit search indexing for an explicitly public campaign
 * @return void
 */
function emergencyhousePublicRenderHeader($title, $account = null, $active = '', $allowIndex = false)
{
	global $langs;

	$language = preg_match('/^[a-z]{2}_[A-Z]{2}$/', $langs->defaultlang) ? str_replace('_', '-', $langs->defaultlang) : 'fr';
	$cssUrl = dol_buildpath('/emergencyhouse/css/public.css.php', 1);
	$jsUrl = dol_buildpath('/emergencyhouse/js/public.js.php', 1);
	$jqueryUrl = DOL_URL_ROOT.'/includes/jquery/js/jquery.min.js';
	$select2CssUrl = DOL_URL_ROOT.'/includes/jquery/plugins/select2/dist/css/select2.min.css';
	$select2JsUrl = DOL_URL_ROOT.'/includes/jquery/plugins/select2/dist/js/select2.full.min.js';
	print '<!doctype html>';
	print '<html lang="'.dol_escape_htmltag($language).'"><head>';
	print '<meta charset="utf-8">';
	print '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
	print '<meta name="robots" content="'.($allowIndex ? 'index,follow' : 'noindex,nofollow').'">';
	print '<title>'.dol_escape_htmltag($title).' — '.$langs->trans('EmergencyHouse').'</title>';
	print '<link rel="stylesheet" href="'.dol_escape_htmltag($select2CssUrl).'">';
	print '<link rel="stylesheet" href="'.dol_escape_htmltag($cssUrl).'">';
	print '<script defer src="'.dol_escape_htmltag($jqueryUrl).'"></script>';
	print '<script defer src="'.dol_escape_htmltag($select2JsUrl).'"></script>';
	print '<script defer src="'.dol_escape_htmltag($jsUrl).'"></script>';
	print '</head><body class="eh-public">';
	print '<a class="eh-skip-link" href="#main">'.$langs->trans('SkipToContent').'</a>';
	print '<header class="eh-site-header"><div class="eh-shell eh-header-inner">';
	print '<a class="eh-brand" href="'.dol_escape_htmltag(emergencyhousePublicUrl()).'">';
	print '<img src="'.dol_escape_htmltag(dol_buildpath('/emergencyhouse/img/emergencyhouse.svg', 1)).'" alt="" width="38" height="38">';
	print '<span><strong>'.$langs->trans('EmergencyHouse').'</strong><small>'.$langs->trans('PublicServiceTagline').'</small></span>';
	print '</a>';
	print '<nav aria-label="'.$langs->trans('PrimaryNavigation').'"><ul>';
	print emergencyhousePublicNavItem('campaigns', $active, emergencyhousePublicUrl(), $langs->trans('Campaigns'));
	print emergencyhousePublicNavItem('offers', $active, emergencyhousePublicUrl('offer/index.php'), $langs->trans('Offers'));
	print emergencyhousePublicNavItem('requests', $active, emergencyhousePublicUrl('request/index.php'), $langs->trans('Requests'));
	if ($account instanceof EmergencyHousePublicAccount) {
		print emergencyhousePublicNavItem('account', $active, emergencyhousePublicUrl('account/index.php'), $langs->trans('MySpace'));
		print emergencyhousePublicNavItem('logout', $active, emergencyhousePublicUrl('auth/logout.php'), $langs->trans('Logout'));
	} else {
		print emergencyhousePublicNavItem('login', $active, emergencyhousePublicUrl('auth/login.php'), $langs->trans('Login'));
		print emergencyhousePublicNavItem('register', $active, emergencyhousePublicUrl('auth/register.php'), $langs->trans('CreateAccount'), 'eh-nav-cta');
	}
	print '</ul></nav>';
	print '</div></header>';
	print '<main id="main">';
}

/**
 * One public navigation item.
 *
 * @param string $code Code
 * @param string $active Active code
 * @param string $url URL
 * @param string $label Label
 * @param string $class Extra class
 * @return string
 */
function emergencyhousePublicNavItem($code, $active, $url, $label, $class = '')
{
	$current = $code === $active ? ' aria-current="page"' : '';
	$classes = trim('eh-nav-link '.$class.($code === $active ? ' is-active' : ''));
	return '<li><a class="'.dol_escape_htmltag($classes).'" href="'.dol_escape_htmltag($url).'"'.$current.'>'
		.dol_escape_htmltag($label).'</a></li>';
}

/**
 * Render public footer.
 *
 * @return void
 */
function emergencyhousePublicRenderFooter()
{
	global $langs;

	print '</main>';
	print '<footer class="eh-site-footer"><div class="eh-shell eh-footer-grid">';
	print '<div><strong>'.$langs->trans('EmergencyHouse').'</strong><p>'.$langs->trans('EmergencyHousePublicDisclaimer').'</p></div>';
	print '<nav aria-label="'.$langs->trans('LegalNavigation').'"><ul>';
	$privacyUrl = getDolGlobalString('EMERGENCYHOUSE_PUBLIC_PRIVACY_URL', '');
	$termsUrl = getDolGlobalString('EMERGENCYHOUSE_PUBLIC_TERMS_URL', '');
	if ($privacyUrl !== '') {
		print '<li><a href="'.dol_escape_htmltag($privacyUrl).'">'.$langs->trans('PrivacyPolicy').'</a></li>';
	}
	if ($termsUrl !== '') {
		print '<li><a href="'.dol_escape_htmltag($termsUrl).'">'.$langs->trans('TermsOfUse').'</a></li>';
	}
	print '<li><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('accessibility.php')).'">'.$langs->trans('Accessibility').'</a></li>';
	print '</ul></nav></div></footer>';
	print '</body></html>';
}

/**
 * Render a standalone unavailable page.
 *
 * @param string $messageKey Translation key
 * @return void
 */
function emergencyhousePublicRenderUnavailable($messageKey = 'PublicPortalUnavailable')
{
	global $langs;

	$langs->load('emergencyhouse@emergencyhouse');
	emergencyhousePublicSendSecurityHeaders();
	emergencyhousePublicRenderHeader($langs->trans('PublicPortalUnavailable'));
	print '<section class="eh-shell eh-section"><div class="eh-alert eh-alert-warning" role="alert">';
	print '<h1>'.$langs->trans('PublicPortalUnavailable').'</h1>';
	print '<p>'.$langs->trans($messageKey).'</p>';
	print '</div></section>';
	emergencyhousePublicRenderFooter();
}

/**
 * Require an authenticated public account.
 *
 * @param EmergencyHousePublicAccount|null $account Account
 * @return EmergencyHousePublicAccount
 */
function emergencyhousePublicRequireAccount($account)
{
	if (!$account instanceof EmergencyHousePublicAccount) {
		$next = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
			? emergencyhousePublicSafeRelativePath($_SERVER['REQUEST_URI'])
			: '';
		$url = emergencyhousePublicUrl('auth/login.php', $next === '' ? array() : array('next' => $next));
		header('Location: '.$url);
		exit;
	}
	return $account;
}

/**
 * Accept only a local relative return target.
 *
 * @param string $value Candidate
 * @return string
 */
function emergencyhousePublicSafeRelativePath($value)
{
	if ($value === '' || strpos($value, "\r") !== false || strpos($value, "\n") !== false || strpos($value, '//') === 0) {
		return '';
	}
	$path = parse_url($value, PHP_URL_PATH);
	$query = parse_url($value, PHP_URL_QUERY);
	if (!is_string($path) || strpos($path, '/emergencyhouse/public/') === false) {
		return '';
	}
	return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
}

/**
 * Verify method and the session-bound public CSRF token.
 *
 * Native Dolibarr CSRF remains active and validates the `token` field first.
 *
 * @param EmergencyHousePublicAuthService $auth Authentication service
 * @param string $action Stable action code
 * @return bool
 */
function emergencyhousePublicVerifyAuthenticatedPost($auth, $action)
{
	if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return false;
	}
	$publicToken = GETPOST('public_token', 'alphanohtml');
	return $auth->verifyCsrfToken($action, $publicToken);
}

/**
 * Render both native and public CSRF fields.
 *
 * @param EmergencyHousePublicAuthService|null $auth Auth service
 * @param string $action Stable action
 * @return string
 */
function emergencyhousePublicCsrfFields($auth = null, $action = '')
{
	$html = '<input type="hidden" name="token" value="'.dol_escape_htmltag(newToken()).'">';
	if ($auth instanceof EmergencyHousePublicAuthService && $action !== '') {
		$html .= '<input type="hidden" name="public_token" value="'.dol_escape_htmltag($auth->csrfToken($action)).'">';
	}
	return $html;
}

/**
 * Consume a rate-limit bucket.
 *
 * @param DoliDB $db Database
 * @param int $entity Entity
 * @param string $scope Scope
 * @param string $identity Raw identity
 * @param int $limit Limit
 * @param int $windowSeconds Window
 * @return bool
 */
function emergencyhousePublicConsumeRateLimit($db, $entity, $scope, $identity, $limit, $windowSeconds)
{
	dol_include_once('/emergencyhouse/class/ratelimitservice.class.php');
	$rateLimiter = new EmergencyHouseRateLimitService($db);
	return $rateLimiter->consume($entity, $scope, $identity, $limit, $windowSeconds, $windowSeconds);
}

/**
 * Parse a public ISO date without relying on browser locale.
 *
 * @param string $value YYYY-MM-DD
 * @param string $timezone IANA timezone
 * @return int|null
 */
function emergencyhousePublicParseDate($value, $timezone = 'Europe/Paris')
{
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
		return null;
	}
	try {
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone($timezone));
	} catch (Exception $exception) {
		unset($exception);
		return null;
	}
	return $date instanceof DateTimeImmutable ? $date->getTimestamp() : null;
}

/**
 * Rebuild a timestamp submitted by Form::selectDate().
 *
 * @param string $prefix Field prefix
 * @param bool $allowEmpty Whether an empty date is valid
 * @return int|null
 */
function emergencyhousePublicGetNativeDate($prefix, $allowEmpty = false)
{
	$day = GETPOSTINT($prefix.'day');
	$month = GETPOSTINT($prefix.'month');
	$year = GETPOSTINT($prefix.'year');
	if ($day <= 0 || $month <= 0 || $year <= 0) {
		return $allowEmpty ? null : 0;
	}
	if (!checkdate($month, $day, $year)) {
		return 0;
	}
	return dol_mktime(0, 0, 0, $month, $day, $year);
}

/**
 * Render the native Dolibarr date selector without inline JavaScript.
 *
 * The public surface deliberately has no dependency on the Dolibarr jQuery
 * bundle. Disabling Ajax for the duration of this native helper call makes
 * Form::selectDate() render its accessible day/month/year combo variant,
 * while keeping the configured user date semantics.
 *
 * @param Form $form Form helper
 * @param int|null $timestamp Selected timestamp
 * @param string $prefix Input prefix
 * @param bool $allowEmpty Whether an empty value is allowed
 * @return string
 */
function emergencyhousePublicNativeDateSelector($form, $timestamp, $prefix, $allowEmpty = false)
{
	global $conf;

	$ajaxEnabled = isset($conf->use_javascript_ajax) ? $conf->use_javascript_ajax : null;
	$conf->use_javascript_ajax = 0;
	$selected = $timestamp === null || $timestamp <= 0 ? -1 : $timestamp;
	$html = $form->selectDate($selected, $prefix, 0, 0, $allowEmpty ? 1 : 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzuserrel');
	if ($ajaxEnabled === null) {
		unset($conf->use_javascript_ajax);
	} else {
		$conf->use_javascript_ajax = $ajaxEnabled;
	}
	return $html;
}

/**
 * Render the native Dolibarr country selector for the isolated public surface.
 *
 * @param Form $form Form helper
 * @param int|null $countryId Selected country
 * @param string $name Input name
 * @param bool $allowEmpty Whether an empty value is allowed
 * @return string
 */
function emergencyhousePublicCountrySelector($form, $countryId, $name = 'fk_pays', $allowEmpty = true)
{
	global $conf;

	$ajaxEnabled = isset($conf->use_javascript_ajax) ? $conf->use_javascript_ajax : null;
	$conf->use_javascript_ajax = 0;
	$html = $form->select_country(
		$countryId === null ? '' : (string) $countryId,
		$name,
		'',
		0,
		'eh-select2',
		'',
		$allowEmpty ? 1 : 0,
		0,
		0,
		array(),
		1
	);
	if ($ajaxEnabled === null) {
		unset($conf->use_javascript_ajax);
	} else {
		$conf->use_javascript_ajax = $ajaxEnabled;
	}
	return $html;
}

/**
 * Fetch public campaigns available in the active entity.
 *
 * @param DoliDB $db Database
 * @param bool $onlyOpen Restrict to campaigns open at the current time
 * @return array<int, array{id:int,slug:string,label:string,date_start:int,date_end:int|null}>
 */
function emergencyhousePublicCampaignOptions($db, $onlyOpen = true)
{
	global $conf;

	$sql = 'SELECT rowid, slug, label, date_start, date_end';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
	$sql .= ' WHERE entity = '.((int) $conf->entity).' AND status = 1';
	if ($onlyOpen) {
		$sql .= " AND date_start <= '".$db->idate(dol_now())."'";
		$sql .= " AND (date_end IS NULL OR date_end >= '".$db->idate(dol_now())."')";
	}
	$sql .= ' ORDER BY date_start DESC, rowid DESC';
	$resql = $db->query($sql);
	if (!$resql) {
		return array();
	}
	$rows = array();
	while (is_object($obj = $db->fetch_object($resql))) {
		$rows[] = array(
			'id' => (int) $obj->rowid,
			'slug' => (string) $obj->slug,
			'label' => (string) $obj->label,
			'date_start' => (int) $db->jdate($obj->date_start),
			'date_end' => !empty($obj->date_end) ? (int) $db->jdate($obj->date_end) : null,
		);
	}
	return $rows;
}

/**
 * Render one safe public select.
 *
 * The control is progressively enhanced by the Select2 distribution bundled
 * with Dolibarr. Its native select remains the no-JavaScript fallback.
 *
 * @param string $name Input name
 * @param array<int|string, string> $options Options
 * @param int|string|null $selected Selected value
 * @param bool $allowEmpty Add empty option
 * @param string $emptyLabel Empty option label
 * @param string $attributes Additional trusted attributes
 * @return string
 */
function emergencyhousePublicSelect($name, array $options, $selected = null, $allowEmpty = false, $emptyLabel = '', $attributes = '')
{
	$html = '<select class="eh-select2" name="'.dol_escape_htmltag($name).'" id="'.dol_escape_htmltag($name).'"'.$attributes.'>';
	if ($allowEmpty) {
		$html .= '<option value="">'.dol_escape_htmltag($emptyLabel).'</option>';
	}
	foreach ($options as $value => $label) {
		$isSelected = (string) $selected === (string) $value ? ' selected' : '';
		$html .= '<option value="'.dol_escape_htmltag((string) $value).'"'.$isSelected.'>'.dol_escape_htmltag($label).'</option>';
	}
	$html .= '</select>';
	return $html;
}

/**
 * Read a bounded array of positive integer identifiers through GETPOST().
 *
 * @param string $name Input name
 * @param int $maximum Maximum number of values
 * @return array<int, int>
 */
function emergencyhousePublicGetIntArray($name, $maximum = 100)
{
	$submitted = GETPOST($name, 'array');
	if (!is_array($submitted)) {
		return array();
	}
	$values = array();
	foreach ($submitted as $value) {
		$id = (int) $value;
		if ($id > 0) {
			$values[$id] = $id;
		}
		if (count($values) >= $maximum) {
			break;
		}
	}
	return array_values($values);
}

/**
 * Read a bounded integer-keyed map with controlled string values.
 *
 * @param string $name Input name
 * @param array<int, string> $allowedValues Allowed values
 * @param int $maximum Maximum entries
 * @return array<int, string>
 */
function emergencyhousePublicGetChoiceMap($name, array $allowedValues, $maximum = 100)
{
	$submitted = GETPOST($name, 'array');
	if (!is_array($submitted)) {
		return array();
	}
	$values = array();
	foreach ($submitted as $key => $value) {
		$id = (int) $key;
		$choice = is_string($value) ? $value : '';
		if ($id > 0 && in_array($choice, $allowedValues, true)) {
			$values[$id] = $choice;
		}
		if (count($values) >= $maximum) {
			break;
		}
	}
	return $values;
}

/**
 * Render a public listing status label.
 *
 * @param string $objectType offer or request
 * @param int $status Status
 * @return string
 */
function emergencyhousePublicListingStatus($objectType, $status)
{
	global $langs;

	$labels = $objectType === 'offer'
		? array(
			0 => 'StatusDraft',
			1 => 'StatusPendingValidation',
			2 => 'StatusPublished',
			3 => 'StatusSuspended',
			4 => 'StatusExpired',
			5 => 'StatusClosed',
			6 => 'StatusRejected',
		)
		: array(
			0 => 'StatusDraft',
			1 => 'StatusActive',
			2 => 'StatusPartiallyAllocated',
			3 => 'StatusFulfilled',
			4 => 'StatusSuspended',
			5 => 'StatusExpired',
			6 => 'StatusClosed',
		);
	$key = isset($labels[$status]) ? $labels[$status] : 'StatusUnknown';
	return '<span class="eh-badge">'.dol_escape_htmltag($langs->trans($key)).'</span>';
}

/**
 * Render a solicitation or allocation status.
 *
 * @param string $objectType solicitation or allocation
 * @param int $status Status
 * @return string
 */
function emergencyhousePublicWorkflowStatus($objectType, $status)
{
	global $langs;

	$labels = $objectType === 'solicitation'
		? array(
			0 => 'StatusPending',
			1 => 'StatusAccepted',
			2 => 'StatusRefused',
			3 => 'StatusCancelled',
			4 => 'StatusExpired',
			5 => 'StatusClosed',
		)
		: array(
			0 => 'StatusProposed',
			1 => 'StatusConfirmed',
			2 => 'StatusActive',
			3 => 'StatusCompleted',
			4 => 'StatusCancelled',
			5 => 'StatusIncident',
		);
	$key = isset($labels[$status]) ? $labels[$status] : 'StatusUnknown';
	$class = in_array($key, array('StatusRefused', 'StatusCancelled', 'StatusIncident'), true)
		? ' eh-badge-urgent'
		: '';
	return '<span class="eh-badge'.$class.'">'.dol_escape_htmltag($langs->trans($key)).'</span>';
}

/**
 * Format a database date returned as a SQL string or timestamp.
 *
 * @param DoliDB $db Database
 * @param int|string|null $value Date value
 * @param string $format Dolibarr date format
 * @return string
 */
function emergencyhousePublicDatabaseDate($db, $value, $format = 'day')
{
	if ($value === null || $value === '') {
		return '';
	}
	$timestamp = is_int($value) ? $value : (int) $db->jdate($value);
	return $timestamp > 0 ? dol_print_date($timestamp, $format) : '';
}

/**
 * Return the anonymous Dolibarr user used for public object triggers.
 *
 * @param DoliDB $db Database
 * @return User
 */
function emergencyhousePublicTriggerUser($db)
{
	global $user;

	if (is_object($user) && $user instanceof User) {
		return $user;
	}
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
	return new User($db);
}

/**
 * Load a public campaign by ID or slug.
 *
 * @param DoliDB $db Database
 * @param int $id ID
 * @param string $slug Slug
 * @return EmergencyHouseCampaign|false
 */
function emergencyhousePublicFetchCampaign($db, $id = 0, $slug = '')
{
	global $conf;

	dol_include_once('/emergencyhouse/class/campaign.class.php');
	$campaign = new EmergencyHouseCampaign($db);
	if ($id > 0 && $campaign->fetch($id) > 0
		&& (int) $campaign->entity === (int) $conf->entity
		&& (int) $campaign->status === EmergencyHouseCampaign::STATUS_PUBLISHED) {
		return $campaign;
	}
	if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,190}$/', $slug)) {
		return false;
	}
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_campaign';
	$sql .= ' WHERE entity = '.((int) $conf->entity);
	$sql .= " AND slug = '".$db->escape($slug)."'";
	$sql .= ' AND status = '.EmergencyHouseCampaign::STATUS_PUBLISHED;
	$resql = $db->query($sql);
	$obj = $resql ? $db->fetch_object($resql) : false;
	return is_object($obj) && $campaign->fetch((int) $obj->rowid) > 0 ? $campaign : false;
}

/**
 * Render a translated alert.
 *
 * @param string $translationKey Translation key
 * @param string $type info, success, warning, error
 * @return void
 */
function emergencyhousePublicAlert($translationKey, $type = 'info')
{
	global $langs;

	$allowed = array('info', 'success', 'warning', 'error');
	$type = in_array($type, $allowed, true) ? $type : 'info';
	$publicKey = $translationKey;
	if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $publicKey)
		|| $langs->trans($publicKey) === $publicKey) {
		if ($publicKey !== '') {
			dol_syslog(__FUNCTION__.' received an untranslated technical error', LOG_ERR);
		}
		$publicKey = 'ErrorInternalError';
	}
	print '<div class="eh-alert eh-alert-'.dol_escape_htmltag($type).'" role="'.($type === 'error' ? 'alert' : 'status').'">';
	print $langs->trans($publicKey);
	print '</div>';
}
