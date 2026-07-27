<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/core/modules/emergencyhouse/mod_emergencyhouse_standard.php');

/**
 * Accommodation request numbering model.
 */
class mod_emergencyhouse_request_standard extends mod_emergencyhouse_standard
{
	/** @var string */
	public $name = 'emergencyhouse_request_standard';
	/** @var string */
	protected $supportedObject = 'request';
	/** @var string */
	protected $prefix = 'EHR';
	/** @var string */
	protected $example = 'EHR-2607-00001';
	/** @var string */
	protected $descriptionKey = 'EmergencyHouseRequestNumberingModelDescription';
}
