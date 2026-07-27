<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Parent class for Emergency House numbering models.
 */
abstract class ModeleNumRefEmergencyHouse
{
	/** @var string */
	public $error = '';
	/** @var string */
	public $version = 'dolibarr';
	/** @var string */
	public $name = '';

	/**
	 * Model availability.
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return true;
	}

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
	abstract public function getExample();

	/**
	 * Activation check.
	 *
	 * @param object $object Object
	 * @return bool
	 */
	public function canBeActivated($object)
	{
		return is_object($object);
	}

	/**
	 * Next reference.
	 *
	 * @param object $object Object
	 * @return string|int
	 */
	abstract public function getNextValue($object);

	/**
	 * Version.
	 *
	 * @return string
	 */
	public function getVersion()
	{
		return $this->version === 'dolibarr' ? DOL_VERSION : $this->version;
	}
}

/**
 * Native document-model registry used by FormFile::showdocuments().
 */
class ModelePDFEmergencyHouse
{
	/**
	 * List document models activated for the object-owning entity.
	 *
	 * @param DoliDB $db Database handler
	 * @return array<string, string>
	 */
	public static function liste_modeles($db)
	{
		global $conf, $langs;

		$entity = (int) $conf->entity;
		if (isset($GLOBALS['object'])
			&& is_object($GLOBALS['object'])
			&& !empty($GLOBALS['object']->entity)) {
			$entity = (int) $GLOBALS['object']->entity;
		}
		$models = array();
		$sql = 'SELECT nom FROM '.MAIN_DB_PREFIX.'document_model';
		$sql .= " WHERE type = 'emergencyhouse'";
		$sql .= ' AND entity IN (0, '.$entity.')';
		$sql .= " AND nom = 'emergencyhouse_agreement'";
		$resql = $db->query($sql);
		if ($resql) {
			while (is_object($row = $db->fetch_object($resql))) {
				$models[(string) $row->nom] = $langs->trans('EmergencyHouseAgreement');
			}
		}

		return $models;
	}
}
