<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/core/modules/emergencyhouse/mod_emergencyhouse_standard.php');

/**
 * Campaign numbering model.
 */
class mod_emergencyhouse_campaign_standard extends mod_emergencyhouse_standard
{
	/** @var string */
	public $name = 'emergencyhouse_campaign_standard';
	/** @var string */
	protected $supportedObject = 'campaign';
	/** @var string */
	protected $prefix = 'EHC';
	/** @var string */
	protected $example = 'EHC-2607-00001';
	/** @var string */
	protected $descriptionKey = 'EmergencyHouseCampaignNumberingModelDescription';
}
