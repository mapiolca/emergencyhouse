<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

define('EMERGENCYHOUSE_PUBLIC_SKIP_ACCOUNT_AUTH', 1);
require __DIR__.'/_init.php';

$action = GETPOST('action', 'aZ09');
$activeSeconds = GETPOSTINT('active_seconds');
$engagementToken = GETPOST('engagement_token', 'restricthtml');
if (!$emergencyhousePublicAnalytics->isTrackingAllowed()) {
	http_response_code(204);
	exit;
}
if (
	$action !== 'engage'
	|| !$emergencyhousePublicAnalytics->isSameOriginPost()
	|| !$emergencyhousePublicAnalytics->hasValidVisitorCookie()
	|| $engagementToken === ''
	|| strlen($engagementToken) > 160
	|| $activeSeconds < 0
	|| $activeSeconds > 7200
) {
	http_response_code(400);
	exit;
}

$visitorToken = isset($_COOKIE[EmergencyHousePublicAnalyticsService::VISITOR_COOKIE])
	&& is_string($_COOKIE[EmergencyHousePublicAnalyticsService::VISITOR_COOKIE])
	? $_COOKIE[EmergencyHousePublicAnalyticsService::VISITOR_COOKIE]
	: '';
$rateLimitError = '';
if (
	$visitorToken === ''
	|| strlen($visitorToken) > 256
	|| !emergencyhousePublicConsumeRateLimit(
		$db,
		(int) $conf->entity,
		'public_analytics_engagement',
		$visitorToken,
		240,
		3600,
		$rateLimitError
	)
) {
	http_response_code($rateLimitError === 'ErrorRateLimitExceeded' ? 429 : 400);
	exit;
}

if (!$emergencyhousePublicAnalytics->markEngaged($activeSeconds, $engagementToken)) {
	http_response_code(400);
	exit;
}

http_response_code(204);
exit;
