<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/core/modules/emergencyhouse/mod_emergencyhouse_standard.php');

/**
 * Allocation numbering model.
 */
class mod_emergencyhouse_allocation_standard extends mod_emergencyhouse_standard
{
	/** @var string */
	public $name = 'emergencyhouse_allocation_standard';
	/** @var string */
	protected $supportedObject = 'allocation';
	/** @var string */
	protected $prefix = 'EHA';
	/** @var string */
	protected $example = 'EHA-2607-00001';
}
