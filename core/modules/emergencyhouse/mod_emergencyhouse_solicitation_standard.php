<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/core/modules/emergencyhouse/mod_emergencyhouse_standard.php');

/**
 * Solicitation numbering model.
 */
class mod_emergencyhouse_solicitation_standard extends mod_emergencyhouse_standard
{
	/** @var string */
	public $name = 'emergencyhouse_solicitation_standard';
	/** @var string */
	protected $supportedObject = 'solicitation';
	/** @var string */
	protected $prefix = 'EHS';
	/** @var string */
	protected $example = 'EHS-2607-00001';
}
