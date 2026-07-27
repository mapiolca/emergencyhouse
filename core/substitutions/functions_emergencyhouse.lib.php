<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Add Emergency House substitutions to native email/document templates.
 *
 * @param array<string, string> $substitutionarray Substitutions
 * @param Translate             $langs Output language
 * @param object                $object Business object
 * @param array<string, mixed>  $parameters Context
 * @return void
 */
function emergencyhouse_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters)
{
	unset($parameters);

	if (!is_object($object) || empty($object->element)) {
		return;
	}
	if (!in_array((string) $object->element, array('campaign', 'offer', 'request', 'solicitation', 'allocation', 'report'), true)) {
		return;
	}

	$ref = !empty($object->ref) ? (string) $object->ref : '';
	$label = !empty($object->label) ? (string) $object->label : (!empty($object->title) ? (string) $object->title : $ref);
	$status = method_exists($object, 'getLibStatut') ? strip_tags($object->getLibStatut(0)) : '';
	$url = !empty($object->id)
		? dol_buildpath('/emergencyhouse/'.$object->element.'/card.php', 2).'?id='.((int) $object->id)
		: '';

	$substitutionarray['__EMERGENCYHOUSE_REF__'] = $ref;
	$substitutionarray['__EMERGENCYHOUSE_LABEL__'] = $label;
	$substitutionarray['__EMERGENCYHOUSE_STATUS__'] = $status;
	$substitutionarray['__EMERGENCYHOUSE_URL__'] = $url;
	$substitutionarray['__EMERGENCYHOUSE_OBJECT_TYPE__'] = $langs->trans(ucfirst((string) $object->element));
}

/**
 * Line substitutions are intentionally empty because current module documents
 * have no CommonObjectLine implementation.
 *
 * @param array<string, string> $substitutionarray Substitutions
 * @param Translate             $langs Output language
 * @param object                $object Business object
 * @param object                $line Line object
 * @param array<string, mixed>  $parameters Context
 * @return void
 */
function emergencyhouse_completesubstitutionarray_lines(&$substitutionarray, $langs, $object, $line, $parameters)
{
	unset($substitutionarray, $langs, $object, $line, $parameters);
}

