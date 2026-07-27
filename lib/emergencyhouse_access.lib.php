<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * \file        lib/emergencyhouse_access.lib.php
 * \ingroup     emergencyhouse
 * \brief       Central access rules that add administrator and entity scope.
 */

/**
 * Return whether a user is an administrator with functional elevation.
 *
 * @param User|null $user User
 * @return bool
 */
function emergencyhouseUserIsFullAdmin($user)
{
	if (!is_object($user)) {
		return false;
	}
	if (!empty($user->admin)) {
		return true;
	}

	if (isModEnabled('multicompany')) {
		if ($user->hasRight('multicompany', 'entities', 'write')
			|| $user->hasRight('multicompany', 'setup', 'write')
			|| $user->hasRight('multicompany', 'admin', 'write')) {
			return true;
		}
	}

	return false;
}

/**
 * Central functional permission check.
 *
 * @param User|null         $user   User
 * @param string            $object Permission object
 * @param string            $action Permission action
 * @param CommonObject|null $record Optional record
 * @return bool
 */
function emergencyhouseCanDo($user, $object, $action, $record = null)
{
	global $conf;

	if (!is_object($user)) {
		return false;
	}
	if (emergencyhouseUserIsFullAdmin($user)) {
		if (is_object($record) && !empty($record->entity)) {
			return emergencyhouseEntityIsAccessible((int) $record->entity, $record);
		}
		return true;
	}
	if (!$user->hasRight('emergencyhouse', $object, $action)) {
		return false;
	}
	if (is_object($record) && !empty($record->entity)) {
		return emergencyhouseEntityIsAccessible((int) $record->entity, $record);
	}

	return !empty($conf->entity);
}

/**
 * Verify that an entity belongs to the configured object scope.
 *
 * @param int               $entity Entity to check
 * @param CommonObject|null $record Optional object
 * @return bool
 */
function emergencyhouseEntityIsAccessible($entity, $record = null)
{
	$element = 'campaign';
	if (is_object($record) && !empty($record->element)) {
		$element = (string) $record->element;
	}

	return emergencyhouseEntityIsAccessibleForElement($entity, $element);
}

/**
 * Verify that an entity belongs to an explicit object scope.
 *
 * @param int    $entity  Entity to check
 * @param string $element Dolibarr element
 * @return bool
 */
function emergencyhouseEntityIsAccessibleForElement($entity, $element)
{
	global $conf;

	if ($entity <= 0 || $element === '') {
		return false;
	}
	if ($entity === (int) $conf->entity) {
		return true;
	}
	if (!isModEnabled('multicompany')) {
		return false;
	}

	$entities = array_filter(array_map('intval', explode(',', (string) getEntity($element))));

	return in_array($entity, $entities, true);
}
