<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/core/modules/emergencyhouse/modules_emergencyhouse.php');

/**
 * Atomic standard numbering model shared by Emergency House objects.
 */
class mod_emergencyhouse_standard extends ModeleNumRefEmergencyHouse
{
	/** @var string */
	public $name = 'emergencyhouse_standard';

	/**
	 * Description.
	 *
	 * @param Translate $langs Languages
	 * @return string
	 */
	public function info($langs)
	{
		return $langs->trans('EmergencyHouseNumberingModelDescription');
	}

	/**
	 * Example.
	 *
	 * @return string
	 */
	public function getExample()
	{
		return 'EHO-2607-00001';
	}

	/**
	 * Validate supported object.
	 *
	 * @param object $object Object
	 * @return bool
	 */
	public function canBeActivated($object)
	{
		return is_object($object)
			&& !empty($object->element)
			&& isset($this->prefixes()[(string) $object->element]);
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

		$db->begin();
		$sqlInsert = 'INSERT IGNORE INTO '.MAIN_DB_PREFIX.'emergencyhouse_sequence';
		$sqlInsert .= ' (entity, object_type, period_code, next_value)';
		$sqlInsert .= ' VALUES ('.$entity.", '".$db->escape($objectType)."', '".$db->escape($period)."', 0)";
		if (!$db->query($sqlInsert)) {
			$this->error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		$sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_sequence';
		$sqlUpdate .= ' SET next_value = LAST_INSERT_ID(next_value + 1)';
		$sqlUpdate .= ' WHERE entity = '.$entity;
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
		return $this->prefixes()[$objectType].'-'.$period.'-'.$formatted;
	}

	/**
	 * Object prefixes.
	 *
	 * @return array<string, string>
	 */
	private function prefixes()
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

