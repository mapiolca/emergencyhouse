<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/core/modules/emergencyhouse/mod_emergencyhouse_standard.php');

/**
 * Safety report numbering model.
 */
class mod_emergencyhouse_report_standard extends mod_emergencyhouse_standard
{
	/** @var string */
	public $name = 'emergencyhouse_report_standard';
	/** @var string */
	protected $supportedObject = 'report';
	/** @var string */
	protected $prefix = 'EHI';
	/** @var string */
	protected $example = 'EHI-2607-00001';
	/** @var string */
	protected $descriptionKey = 'EmergencyHouseReportNumberingModelDescription';
}
