<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Prepare module administration tabs.
 *
 * @return array<int, array{0:string,1:string,2:string}>
 */
function emergencyhouseAdminPrepareHead()
{
	global $langs;

	$langs->load('emergencyhouse@emergencyhouse');
	$tabs = array(
		'general' => 'Settings',
		'portal' => 'PublicPortal',
		'authentication' => 'Authentication',
		'verification' => 'Verification',
		'analytics' => 'Supervision',
		'matching' => 'Matching',
		'notifications' => 'Notifications',
		'providers' => 'Providers',
		'security' => 'Security',
		'retention' => 'Retention',
		'integrations' => 'Integrations',
	);
	if (emergencyhouseHasConfiguredObjectSharing()) {
		$tabs['multicompany'] = 'Multicompany';
	}
	$tabs['advanced'] = 'Advanced';

	$head = array();
	foreach ($tabs as $code => $label) {
		$head[] = array(
			dol_buildpath('/emergencyhouse/admin/setup.php', 1).'?tab='.urlencode($code),
			$langs->trans($label),
			$code,
		);
	}
	$head[] = array(dol_buildpath('/emergencyhouse/admin/compatibility.php', 1), $langs->trans('Compatibility'), 'compatibility');
	$head[] = array(dol_buildpath('/emergencyhouse/admin/diagnostic.php', 1), $langs->trans('Diagnostic'), 'diagnostic');
	$head[] = array(dol_buildpath('/emergencyhouse/admin/about.php', 1), $langs->trans('About'), 'about');

	return $head;
}

/**
 * Prepare the queue and immutable-history tabs.
 *
 * @return array<int, array{0:string,1:string,2:string}>
 */
function emergencyhouseVerificationPrepareHead()
{
	global $langs;

	return array(
		array(
			dol_buildpath('/emergencyhouse/verification/list.php', 1).'?view=queue',
			$langs->trans('VerificationQueue'),
			'queue',
		),
		array(
			dol_buildpath('/emergencyhouse/verification/list.php', 1).'?view=history',
			$langs->trans('VerificationHistory'),
			'history',
		),
	);
}

/**
 * Prepare Supervision dashboard tabs.
 *
 * @return array<int, array{0:string,1:string,2:string}>
 */
function emergencyhouseSupervisionPrepareHead()
{
	global $langs;

	$base = dol_buildpath('/emergencyhouse/supervision/index.php', 1);
	return array(
		array($base.'?tab=overview', $langs->trans('Overview'), 'overview'),
		array($base.'?tab=audience', $langs->trans('Audience'), 'audience'),
		array($base.'?tab=contents', $langs->trans('Contents'), 'contents'),
		array($base.'?tab=journeys', $langs->trans('Journeys'), 'journeys'),
		array($base.'?tab=business', $langs->trans('BusinessActivity'), 'business'),
	);
}

/**
 * Return the module-list backlink.
 *
 * @return string
 */
function emergencyhouseAdminLinkBack()
{
	global $langs;

	return '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=emergencyhouse">'
		.$langs->trans('BackToModuleList')
		.'</a>';
}

/**
 * Convert a service error code into a safe user-facing message.
 *
 * Technical database or provider details are deliberately not returned to the
 * browser. They remain available through the service logs.
 *
 * @param string $errorCode Technical error code or message
 * @param string $fallback  Translation key used when no public translation exists
 * @return string
 */
function emergencyhouseGetUserErrorMessage($errorCode, $fallback = 'ErrorInternalError')
{
	global $langs;

	if ($errorCode !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $errorCode)) {
		$translated = $langs->trans($errorCode);
		if ($translated !== $errorCode) {
			return $translated;
		}
	}

	if ($errorCode !== '') {
		dol_syslog(__FUNCTION__.' received an untranslated technical error', LOG_ERR);
	}

	return $langs->trans($fallback);
}

/**
 * Normalize native and historically escaped line breaks in rich text fields.
 *
 * @param string $content Rich or plain text content
 * @return string
 */
function emergencyhouseNormalizeRichTextLineBreaks($content)
{
	$content = str_replace(array("\r\n", "\r"), "\n", $content);

	return str_replace(array('\\r\\n', '\\n', '\\r'), "\n", $content);
}

/**
 * Return tabs for a module object.
 *
 * @param CommonObject $object Object
 * @return array<int, array{0:string,1:string,2:string}>
 */
function emergencyhouseObjectPrepareHead($object)
{
	global $langs;

	$head = array();
	$base = dol_buildpath('/emergencyhouse/'.$object->element.'/card.php', 1).'?id='.((int) $object->id);
	$head[] = array($base, $langs->trans('Card'), 'card');
	if (property_exists($object, 'note_public') && $object->element !== 'report') {
		$head[] = array($base.'&tab=notes', $langs->trans('Notes'), 'notes');
	}
	if (property_exists($object, 'model_pdf') && $object->element !== 'report') {
		$head[] = array($base.'&tab=documents', $langs->trans('AttachedFiles'), 'documents');
	}
	$head[] = array($base.'&tab=agenda', $langs->trans('EventsAgenda'), 'agenda');
	$head[] = array($base.'&tab=audit', $langs->trans('AuditLog'), 'audit');

	return $head;
}

/**
 * Return the entity scope for an object element.
 *
 * @param string $element Dolibarr element
 * @return array<int, int>
 */
function emergencyhouseEntityScope($element)
{
	global $conf;

	$scope = array_filter(array_map('intval', explode(',', (string) getEntity($element))));
	if (empty($scope)) {
		$scope = array((int) $conf->entity);
	}

	return array_values(array_unique($scope));
}

/**
 * Check whether one entity scope is effectively shared through Multicompany.
 *
 * @param array<int, int> $scope Entity IDs
 * @return bool
 */
function emergencyhouseEntityScopeIsShared($scope)
{
	if (!isModEnabled('multicompany')) {
		return false;
	}

	$entities = array();
	foreach ($scope as $entityId) {
		$entityId = (int) $entityId;
		if ($entityId > 0) {
			$entities[$entityId] = $entityId;
		}
	}

	return count($entities) > 1;
}

/**
 * Check whether at least one module business object has configured sharing.
 *
 * @return bool
 */
function emergencyhouseHasConfiguredObjectSharing()
{
	if (!isModEnabled('multicompany')) {
		return false;
	}

	$elements = array('campaign', 'offer', 'request', 'solicitation', 'allocation', 'report');
	foreach ($elements as $element) {
		if (emergencyhouseEntityScopeIsShared(emergencyhouseEntityScope($element))) {
			return true;
		}
	}

	return false;
}

/**
 * Return readable entity labels for one object scope.
 *
 * @param DoliDB $db      Database handler
 * @param string $element Dolibarr element
 * @return array<int, string>
 */
function emergencyhouseEntityOptions($db, $element)
{
	$scope = emergencyhouseEntityScope($element);

	return emergencyhouseEntityOptionsForScope($db, $scope);
}

/**
 * Return readable entity labels for an explicit scope.
 *
 * @param DoliDB          $db    Database handler
 * @param array<int, int> $scope Entity IDs
 * @return array<int, string>
 */
function emergencyhouseEntityOptionsForScope($db, $scope)
{
	global $conf;

	$options = array();
	foreach ($scope as $entityId) {
		$options[$entityId] = $entityId === (int) $conf->entity
			? getDolGlobalString('MAIN_INFO_SOCIETE_NOM', (string) $entityId)
			: (string) $entityId;
	}

	if (isModEnabled('multicompany') && !empty($scope)) {
		$sql = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity';
		$sql .= ' WHERE rowid IN ('.implode(',', $scope).')';
		$resql = $db->query($sql);
		if ($resql) {
			while (is_object($entity = $db->fetch_object($resql))) {
				$options[(int) $entity->rowid] = (string) $entity->label;
			}
		}
	}

	return $options;
}

/**
 * Normalize a submitted entity multiselect against an allowed scope.
 *
 * @param mixed           $rawSelection Submitted values
 * @param array<int, int> $scope        Allowed entity IDs
 * @return array<int, int>
 */
function emergencyhouseEntitySelection($rawSelection, $scope)
{
	if (!is_array($rawSelection)) {
		return array();
	}

	$selected = array();
	foreach ($rawSelection as $entityId) {
		$entityId = (int) $entityId;
		if ($entityId > 0 && in_array($entityId, $scope, true)) {
			$selected[$entityId] = $entityId;
		}
	}

	return array_values($selected);
}

/**
 * Render the native Multicompany entity badge.
 *
 * @param int                $entityId Entity ID
 * @param array<int, string> $options  Entity labels
 * @return string
 */
function emergencyhouseEntityBadge($entityId, $options)
{
	$label = isset($options[$entityId]) && $options[$entityId] !== ''
		? $options[$entityId]
		: (string) $entityId;

	return '<div class="refidno multicompany-entity-card-container">'
		.'<span class="fa fa-globe"></span>'
		.'<span class="multiselect-selected-title-text">'.dol_escape_htmltag($label).'</span>'
		.'</div>';
}

/**
 * Read entity-specific verification alert thresholds.
 *
 * This direct read is required for shared queues because getDolGlobalInt()
 * resolves only the current entity.
 *
 * @param DoliDB $db     Database handler
 * @param int    $entity Entity
 * @return array{warning:int,critical:int}
 */
function emergencyhouseVerificationThresholds($db, $entity)
{
	$thresholds = array('warning' => 10, 'critical' => 30);
	if ($entity <= 0) {
		return $thresholds;
	}

	$sql = 'SELECT name, value FROM '.MAIN_DB_PREFIX.'const';
	$sql .= ' WHERE entity = '.((int) $entity);
	$sql .= " AND name IN ('EMERGENCYHOUSE_VERIFICATION_WARNING_MINUTES',";
	$sql .= " 'EMERGENCYHOUSE_VERIFICATION_CRITICAL_MINUTES')";
	$resql = $db->query($sql);
	if ($resql) {
		while (is_object($constant = $db->fetch_object($resql))) {
			if ((string) $constant->name === 'EMERGENCYHOUSE_VERIFICATION_WARNING_MINUTES') {
				$thresholds['warning'] = (int) $constant->value;
			} elseif ((string) $constant->name === 'EMERGENCYHOUSE_VERIFICATION_CRITICAL_MINUTES') {
				$thresholds['critical'] = (int) $constant->value;
			}
		}
	}
	if ($thresholds['warning'] < 1 || $thresholds['critical'] <= $thresholds['warning']) {
		return array('warning' => 10, 'critical' => 30);
	}

	return $thresholds;
}

/**
 * Format an elapsed duration without wrapping after 24 hours.
 *
 * @param int $seconds Elapsed seconds
 * @return string HH:MM:SS
 */
function emergencyhouseVerificationFormatDuration($seconds)
{
	$seconds = max(0, (int) $seconds);
	$hours = (int) floor($seconds / 3600);
	$minutes = (int) floor(($seconds % 3600) / 60);
	$remainingSeconds = $seconds % 60;

	return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
}

/**
 * Classify an elapsed duration against entity thresholds.
 *
 * @param int                           $seconds Elapsed seconds
 * @param array{warning:int,critical:int} $thresholds Thresholds in minutes
 * @return string neutral, warning or critical
 */
function emergencyhouseVerificationUrgency($seconds, $thresholds)
{
	$seconds = max(0, (int) $seconds);
	if ($seconds >= ((int) $thresholds['critical']) * 60) {
		return 'critical';
	}
	if ($seconds >= ((int) $thresholds['warning']) * 60) {
		return 'warning';
	}

	return 'neutral';
}
