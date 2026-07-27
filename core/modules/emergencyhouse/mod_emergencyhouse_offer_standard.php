<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/core/modules/emergencyhouse/mod_emergencyhouse_standard.php');

/**
 * Housing offer numbering model.
 */
class mod_emergencyhouse_offer_standard extends mod_emergencyhouse_standard
{
	/** @var string */
	public $name = 'emergencyhouse_offer_standard';
	/** @var string */
	protected $supportedObject = 'offer';
	/** @var string */
	protected $prefix = 'EHO';
	/** @var string */
	protected $example = 'EHO-2607-00001';
}
