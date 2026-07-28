<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Return the configured root URL of the public/ directory.
 *
 * An empty return value means that the portal still uses its Dolibarr URL.
 * Invalid legacy values are ignored defensively; the settings page prevents
 * saving them.
 *
 * @return string Absolute URL ending with a slash, or an empty string
 */
function emergencyhousePublicConfiguredBaseUrl()
{
	$baseUrl = trim(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_BASE_URL', ''));
	if ($baseUrl === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
		return '';
	}

	$parts = parse_url($baseUrl);
	if (
		!is_array($parts)
		|| !isset($parts['scheme'], $parts['host'])
		|| strtolower((string) $parts['scheme']) !== 'https'
		|| isset($parts['user'])
		|| isset($parts['pass'])
		|| isset($parts['query'])
		|| isset($parts['fragment'])
	) {
		return '';
	}

	return rtrim($baseUrl, '/').'/';
}

/**
 * Normalize a path relative to public/.
 *
 * @param string $path Candidate
 * @return string
 */
function emergencyhousePublicNormalizePath($path)
{
	$path = ltrim(str_replace('\\', '/', trim($path)), '/');
	if ($path === '' || $path === 'index.php') {
		return 'index.php';
	}
	if (
		strpos($path, "\0") !== false
		|| preg_match('~(?:^|/)\.\.(?:/|$)~', rawurldecode($path))
		|| !preg_match('~^[A-Za-z0-9_./-]+$~', $path)
	) {
		return 'index.php';
	}

	return $path;
}

/**
 * Build a public module URL.
 *
 * When EMERGENCYHOUSE_PUBLIC_BASE_URL is configured, it is the URL of the
 * public/ directory itself. No Dolibarr or /custom/emergencyhouse segment is
 * added to it.
 *
 * @param string $path Path relative to public/
 * @param array<string, int|string> $parameters Query parameters
 * @return string
 */
function emergencyhousePublicUrl($path = 'index.php', array $parameters = array())
{
	$relativePath = emergencyhousePublicNormalizePath($path);
	$configuredBaseUrl = emergencyhousePublicConfiguredBaseUrl();
	if ($configuredBaseUrl === '') {
		$baseUrl = rtrim(dol_buildpath('/emergencyhouse/public/', 1), '/').'/';
		$url = $baseUrl.$relativePath;
	} else {
		$url = $configuredBaseUrl.($relativePath === 'index.php' ? '' : $relativePath);
	}
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
	$baseUrl = emergencyhousePublicConfiguredBaseUrl();
	if ($baseUrl !== '') {
		$relativePath = emergencyhousePublicNormalizePath($path);
		$url = $baseUrl.($relativePath === 'index.php' ? '' : $relativePath);
	} else {
		$url = rtrim(dol_buildpath('/emergencyhouse/public/', 2), '/').'/'.emergencyhousePublicNormalizePath($path);
	}
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
 * @param bool $preview Render private preview links without public actions
 * @return void
 */
function emergencyhousePublicRenderHeader($title, $account = null, $active = '', $allowIndex = false, $preview = false)
{
	global $langs;

	$language = preg_match('/^[a-z]{2}_[A-Z]{2}$/', $langs->defaultlang) ? str_replace('_', '-', $langs->defaultlang) : 'fr';
	$cssUrl = $preview
		? dol_buildpath('/emergencyhouse/css/public.css.php', 1)
		: emergencyhousePublicUrl('assets/public.css.php');
	$jsUrl = $preview
		? dol_buildpath('/emergencyhouse/js/public.js.php', 1)
		: emergencyhousePublicUrl('assets/public.js.php');
	$logoUrl = $preview
		? dol_buildpath('/emergencyhouse/img/emergencyhouse.svg', 1)
		: emergencyhousePublicUrl('assets/emergencyhouse.svg.php');
	$loadDolibarrFrontendLibraries = $preview || emergencyhousePublicConfiguredBaseUrl() === '';
	print '<!doctype html>';
	print '<html lang="'.dol_escape_htmltag($language).'"><head>';
	print '<meta charset="utf-8">';
	print '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
	print '<meta name="robots" content="'.($allowIndex ? 'index,follow' : 'noindex,nofollow').'">';
	print '<title>'.dol_escape_htmltag($title).' — '.$langs->trans('EmergencyHouse').'</title>';
	if ($loadDolibarrFrontendLibraries) {
		print '<link rel="stylesheet" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/select2/dist/css/select2.min.css">';
	}
	print '<link rel="stylesheet" href="'.dol_escape_htmltag($cssUrl).'">';
	if ($loadDolibarrFrontendLibraries) {
		print '<script defer src="'.DOL_URL_ROOT.'/includes/jquery/js/jquery.min.js"></script>';
		print '<script defer src="'.DOL_URL_ROOT.'/includes/jquery/plugins/select2/dist/js/select2.full.min.js"></script>';
	}
	print '<script defer src="'.dol_escape_htmltag($jsUrl).'"></script>';
	print '</head><body class="eh-public">';
	print '<a class="eh-skip-link" href="#main">'.$langs->trans('SkipToContent').'</a>';
	print '<header class="eh-site-header"><div class="eh-shell eh-header-inner">';
	$homeUrl = $preview ? '#main' : emergencyhousePublicUrl();
	print '<a class="eh-brand" href="'.dol_escape_htmltag($homeUrl).'">';
	print '<img src="'.dol_escape_htmltag($logoUrl).'" alt="" width="38" height="38">';
	print '<span><strong>'.$langs->trans('EmergencyHouse').'</strong><small>'.$langs->trans('PublicServiceTagline').'</small></span>';
	print '</a>';
	print '<nav aria-label="'.$langs->trans('PrimaryNavigation').'"><ul>';
	print emergencyhousePublicNavItem('campaigns', $active, $preview ? '#preview-campaigns' : emergencyhousePublicUrl(), $langs->trans('Campaigns'));
	print emergencyhousePublicNavItem('offers', $active, $preview ? '#preview-offers' : emergencyhousePublicUrl('offer/index.php'), $langs->trans('Offers'));
	print emergencyhousePublicNavItem('requests', $active, $preview ? '#preview-requests' : emergencyhousePublicUrl('request/index.php'), $langs->trans('Requests'));
	print emergencyhousePublicNavItem('contact', $active, $preview ? '#preview-contact' : emergencyhousePublicUrl('contact.php'), $langs->trans('ContactUs'));
	if ($account instanceof EmergencyHousePublicAccount) {
		print emergencyhousePublicNavItem('account', $active, emergencyhousePublicUrl('account/index.php'), $langs->trans('MySpace'));
		print emergencyhousePublicNavItem('logout', $active, emergencyhousePublicUrl('auth/logout.php'), $langs->trans('Logout'));
	} else {
		print emergencyhousePublicNavItem('login', $active, $preview ? '#preview-account' : emergencyhousePublicUrl('auth/login.php'), $langs->trans('Login'));
		print emergencyhousePublicNavItem('register', $active, $preview ? '#preview-account' : emergencyhousePublicUrl('auth/register.php'), $langs->trans('CreateAccount'), 'eh-nav-cta');
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
 * Check whether rich HTML contains meaningful visible text.
 *
 * @param string $html Rich HTML
 * @return bool
 */
function emergencyhousePublicHtmlHasContent($html)
{
	$text = dol_string_nohtmltag($html);
	$text = str_replace(array("\xC2\xA0", "\xE2\x80\x8B"), '', $text);
	return trim($text) !== '';
}

/**
 * Render public footer.
 *
 * @param bool $preview Render private preview footer links
 * @return void
 */
function emergencyhousePublicRenderFooter($preview = false)
{
	global $langs;

	print '</main>';
	print '<footer class="eh-site-footer"><div class="eh-shell eh-footer-grid">';
	print '<div><strong>'.$langs->trans('EmergencyHouse').'</strong><p>'.$langs->trans('EmergencyHousePublicDisclaimer').'</p></div>';
	print '<div><strong>'.$langs->trans('ContactUs').'</strong><ul class="eh-footer-contact">';
	print '<li><a href="'.dol_escape_htmltag($preview ? '#preview-contact' : emergencyhousePublicUrl('contact.php')).'">'
		.$langs->trans('ContactForm').'</a></li>';
	$supportPhone = trim(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_SUPPORT_PHONE', ''));
	$supportEmail = trim(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL', ''));
	if ($supportPhone !== '') {
		$phoneUri = preg_replace('/[^0-9+]/', '', $supportPhone);
		print '<li><a href="tel:'.dol_escape_htmltag(is_string($phoneUri) ? $phoneUri : '').'">'
			.dol_escape_htmltag($supportPhone).'</a></li>';
	}
	if (filter_var($supportEmail, FILTER_VALIDATE_EMAIL) !== false) {
		print '<li><a href="mailto:'.dol_escape_htmltag($supportEmail).'">'.dol_escape_htmltag($supportEmail).'</a></li>';
	}
	print '</ul></div>';
	print '<nav aria-label="'.$langs->trans('LegalNavigation').'"><ul>';
	$privacyUrl = getDolGlobalString('EMERGENCYHOUSE_PUBLIC_PRIVACY_URL', '');
	$termsHtml = trim(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_TERMS_HTML', ''));
	$termsEnabled = emergencyhousePublicHtmlHasContent($termsHtml);
	$dataPolicyEnabled = isModEnabled('datapolicy');
	if ($preview) {
		if ($dataPolicyEnabled) {
			print '<li><a href="#main">'.$langs->trans('PrivacyPolicy').'</a></li>';
		}
		if ($termsEnabled) {
			print '<li><a href="#main">'.$langs->trans('TermsOfUse').'</a></li>';
		}
	} elseif ($dataPolicyEnabled && $privacyUrl !== '') {
		print '<li><a href="'.dol_escape_htmltag($privacyUrl).'">'.$langs->trans('PrivacyPolicy').'</a></li>';
	}
	if (!$preview && $termsEnabled) {
		print '<li><a href="'.dol_escape_htmltag(emergencyhousePublicUrl('terms.php')).'">'.$langs->trans('TermsOfUse').'</a></li>';
	}
	print '<li><a href="'.dol_escape_htmltag($preview ? '#main' : emergencyhousePublicUrl('accessibility.php')).'">'.$langs->trans('Accessibility').'</a></li>';
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
			? emergencyhousePublicSafeReturnUrl($_SERVER['REQUEST_URI'])
			: '';
		$url = emergencyhousePublicUrl('auth/login.php', $next === '' ? array() : array('next' => $next));
		header('Location: '.$url);
		exit;
	}
	return $account;
}

/**
 * Accept only a known page below the configured public root.
 *
 * @param string $value Candidate
 * @return string
 */
function emergencyhousePublicSafeReturnUrl($value)
{
	if (
		$value === ''
		|| strpos($value, "\r") !== false
		|| strpos($value, "\n") !== false
		|| strpos($value, '\\') !== false
		|| strpos($value, '//') === 0
	) {
		return '';
	}

	$parts = parse_url($value);
	if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
		return '';
	}
	$path = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '';
	$query = isset($parts['query']) && is_string($parts['query']) ? $parts['query'] : '';

	$configuredBaseUrl = emergencyhousePublicConfiguredBaseUrl();
	$comparisonBaseUrl = $configuredBaseUrl !== ''
		? $configuredBaseUrl
		: rtrim(dol_buildpath('/emergencyhouse/public/', 2), '/').'/';
	$baseParts = parse_url($comparisonBaseUrl);
	if (!is_array($baseParts)) {
		return '';
	}

	if (isset($parts['scheme']) || isset($parts['host'])) {
		$candidateScheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
		$candidateHost = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
		$candidatePort = isset($parts['port']) ? (int) $parts['port'] : 443;
		$baseScheme = isset($baseParts['scheme']) ? strtolower((string) $baseParts['scheme']) : '';
		$baseHost = isset($baseParts['host']) ? strtolower((string) $baseParts['host']) : '';
		$basePort = isset($baseParts['port']) ? (int) $baseParts['port'] : 443;
		if ($candidateScheme !== $baseScheme || $candidateHost !== $baseHost || $candidatePort !== $basePort) {
			return '';
		}
	}

	$basePath = isset($baseParts['path']) && is_string($baseParts['path']) ? $baseParts['path'] : '/';
	$basePath = '/'.trim($basePath, '/').'/';
	if ($basePath === '//') {
		$basePath = '/';
	}
	if ($path === $basePath || $path === rtrim($basePath, '/')) {
		$relativePath = 'index.php';
	} elseif (strpos($path, $basePath) === 0) {
		$relativePath = substr($path, strlen($basePath));
	} else {
		return '';
	}

	$decodedRelativePath = rawurldecode($relativePath);
	if (
		strpos($decodedRelativePath, "\0") !== false
		|| preg_match('~(?:^|/)\.\.(?:/|$)~', $decodedRelativePath)
		|| !preg_match(
			'~^(?:index\.php|campaign\.php|accessibility\.php|contact\.php|(?:account|allocation|auth|offer|report|request|solicitation)/[A-Za-z0-9_-]+\.php)$~',
			$decodedRelativePath
		)
	) {
		return '';
	}

	$url = emergencyhousePublicUrl($relativePath);
	return $url.($query !== '' ? '?'.$query : '');
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
 * @param string|null $errorCode Service error code
 * @return bool
 */
function emergencyhousePublicConsumeRateLimit($db, $entity, $scope, $identity, $limit, $windowSeconds, &$errorCode = null)
{
	dol_include_once('/emergencyhouse/class/ratelimitservice.class.php');
	$rateLimiter = new EmergencyHouseRateLimitService($db);
	$result = $rateLimiter->consume($entity, $scope, $identity, $limit, $windowSeconds, $windowSeconds);
	$errorCode = $result ? '' : ($rateLimiter->error !== '' ? $rateLimiter->error : 'ErrorInternalError');
	if (!$result && $errorCode !== 'ErrorRateLimitExceeded') {
		dol_syslog(__FUNCTION__.': '.$errorCode, LOG_ERR);
	}
	return $result;
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
