<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/core/modules/emergencyhouse/modules_emergencyhouse.php');

/**
 * Atomic standard numbering engine.
 *
 * Object-specific models extend this class and declare their own supported
 * object, prefix and example. The generic class remains loadable only to
 * migrate installations configured before object-specific models existed.
 */
class mod_emergencyhouse_standard extends ModeleNumRefEmergencyHouse
{
	/** @var string */
	public $name = 'emergencyhouse_standard';
	/** @var string */
	protected $supportedObject = '';
	/** @var string */
	protected $prefix = '';
	/** @var string */
	protected $example = 'EHO-2607-00001';
	/** @var string */
	protected $descriptionKey = 'EmergencyHouseNumberingModelDescription';

	/**
	 * Description.
	 *
	 * @param Translate $langs Languages
	 * @return string
	 */
	public function info($langs)
	{
		return $langs->trans($this->descriptionKey);
	}

	/**
	 * Example.
	 *
	 * @return string
	 */
	public function getExample()
	{
		return $this->example;
	}

	/**
	 * Validate supported object.
	 *
	 * @param object $object Object
	 * @return bool
	 */
	public function canBeActivated($object)
	{
		if (!is_object($object) || empty($object->element)) {
			return false;
		}
		$objectType = (string) $object->element;
		if ($this->supportedObject !== '') {
			return $objectType === $this->supportedObject && $this->prefix !== '';
		}
		return isset($this->legacyPrefixes()[$objectType]);
	}

	/**
	 * Allocate the next value atomically.
	 *
	 * @param object $object Object
	 * @return string|int
	 */
	public function getNextValue($object)
	{
		global $db, $conf;

		if (!$this->canBeActivated($object)) {
			$this->error = 'ErrorNumberingModel';
			return -1;
		}
		$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$date = !empty($object->date_creation) ? (int) $object->date_creation : dol_now();
		$period = dol_print_date($date, '%y%m');
		$objectType = (string) $object->element;
		$sequenceEntity = $this->getSequenceEntity($object, $entity);
		$prefix = $this->supportedObject === '' ? $this->legacyPrefixes()[$objectType] : $this->prefix;

		$db->begin();
		$sqlInsert = 'INSERT IGNORE INTO '.MAIN_DB_PREFIX.'emergencyhouse_sequence';
		$sqlInsert .= ' (entity, object_type, period_code, next_value)';
		$sqlInsert .= ' VALUES ('.$sequenceEntity.", '".$db->escape($objectType)."', '".$db->escape($period)."', 0)";
		if (!$db->query($sqlInsert)) {
			$this->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_sequence';
		$sqlUpdate .= ' SET next_value = LAST_INSERT_ID(next_value + 1)';
		$sqlUpdate .= ' WHERE entity = '.$sequenceEntity;
		$sqlUpdate .= " AND object_type = '".$db->escape($objectType)."'";
		$sqlUpdate .= " AND period_code = '".$db->escape($period)."'";
		if (!$db->query($sqlUpdate)) {
			$this->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		$resql = $db->query('SELECT LAST_INSERT_ID() AS next_value');
		if (!$resql) {
			$this->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		$obj = $db->fetch_object($resql);
		if (!is_object($obj) || (int) $obj->next_value <= 0) {
			$this->error = 'ErrorNumberingModel';
			$db->rollback();
			return -1;
		}
		$db->commit();

		$number = (int) $obj->next_value;
		$formatted = $number > 99999 ? (string) $number : sprintf('%05d', $number);
		return $prefix.'-'.$period.'-'.$formatted;
	}

	/**
	 * Resolve the canonical sequence entity from object and numbering sharing.
	 *
	 * @param object $object Object
	 * @param int    $fallbackEntity Object entity
	 * @return int
	 */
	protected function getSequenceEntity($object, $fallbackEntity)
	{
		$entities = array($fallbackEntity);
		if (function_exists('getEntity') && !empty($object->element)) {
			$scopes = array(
				getEntity((string) $object->element),
				getEntity((string) $object->element.'number', 1, $object),
			);
			foreach ($scopes as $scope) {
				foreach (explode(',', (string) $scope) as $entityId) {
					$entityId = (int) trim($entityId);
					if ($entityId > 0) {
						$entities[] = $entityId;
					}
				}
			}
		}
		$entities = array_values(array_unique($entities));
		sort($entities, SORT_NUMERIC);
		return (int) $entities[0];
	}

	/**
	 * Prefixes kept for migration compatibility with the former generic model.
	 *
	 * @return array<string, string>
	 */
	private function legacyPrefixes()
	{
		return array(
			'campaign' => 'EHC',
			'offer' => 'EHO',
			'request' => 'EHR',
			'solicitation' => 'EHS',
			'allocation' => 'EHA',
			'report' => 'EHI',
		);
	}
}
