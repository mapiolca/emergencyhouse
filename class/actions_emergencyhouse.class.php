<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Hook handlers for Emergency House.
 */
class ActionsEmergencyHouse
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $error = '';
	/** @var array<int, string> */
	public $errors = array();
	/** @var array<string, mixed> */
	public $results = array();
	/** @var string */
	public $resprints = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return the unique Multicompany definition.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function getMulticompanySharingDefinition()
	{
		$elementDefinitions = array();
		$moduleNames = array();
		$elements = array(
			'campaign' => array('emergencyhouse@emergencyhouse', 'CampaignSharingInfo'),
			'offer' => array('home', 'OfferSharingInfo'),
			'request' => array('people-arrows', 'RequestSharingInfo'),
			'solicitation' => array('comment', 'SolicitationSharingInfo'),
			'allocation' => array('calendar-check', 'AllocationSharingInfo'),
			'report' => array('warning', 'ReportSharingInfo'),
		);
		foreach ($elements as $element => $definition) {
			$elementDefinitions[$element] = array(
				'type' => 'element',
				'icon' => $definition[0],
				'lang' => 'emergencyhouse@emergencyhouse',
				'tooltip' => $definition[1],
				'enable' => 'isModEnabled("emergencyhouse")',
				'input' => array(
					'global' => array('showhide' => true, 'hide' => true, 'del' => true),
				),
			);
			$elementDefinitions[$element.'number'] = array(
				'type' => 'objectnumber',
				'icon' => 'hashtag',
				'lang' => 'emergencyhouse@emergencyhouse',
				'tooltip' => $definition[1].'Number',
				'enable' => 'isModEnabled("emergencyhouse")',
				'input' => array(
					'global' => array('showhide' => true, 'hide' => true, 'del' => true),
				),
			);
			$moduleNames[$element] = 'emergencyhouse';
			$moduleNames[$element.'number'] = 'emergencyhouse';
		}

		return array(
			'emergencyhouse' => array(
				'sharingelements' => $elementDefinitions,
				'sharingmodulename' => $moduleNames,
				'dictionary' => array(
					'c_emergencyhouse_housing_type' => array(
						'type' => 'dictionary',
						'icon' => 'home',
						'transkey' => 'HousingTypes',
						'tooltip' => 'HousingTypesSharingInfo',
						'lang' => 'emergencyhouse@emergencyhouse',
						'filepath' => '/emergencyhouse/sql/llx_c_emergencyhouse_housing_type.sql',
					),
				),
			),
		);
	}

	/**
	 * Expose supported notification events.
	 *
	 * @param array<string, mixed> $parameters Hook parameters
	 * @param object               $object     Object
	 * @param string               $action     Action
	 * @param HookManager          $hookmanager Hook manager
	 * @return int
	 */
	public function notifsupported($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array(
			'arrayofnotifsupported' => array(
				'EMERGENCYHOUSE_CAMPAIGN_CREATE',
				'EMERGENCYHOUSE_CAMPAIGN_UPDATE',
				'EMERGENCYHOUSE_CAMPAIGN_DELETE',
				'EMERGENCYHOUSE_OFFER_CREATE',
				'EMERGENCYHOUSE_OFFER_UPDATE',
				'EMERGENCYHOUSE_OFFER_DELETE',
				'EMERGENCYHOUSE_REQUEST_CREATE',
				'EMERGENCYHOUSE_REQUEST_UPDATE',
				'EMERGENCYHOUSE_REQUEST_DELETE',
				'EMERGENCYHOUSE_SOLICITATION_CREATE',
				'EMERGENCYHOUSE_SOLICITATION_UPDATE',
				'EMERGENCYHOUSE_SOLICITATION_DELETE',
				'EMERGENCYHOUSE_ALLOCATION_CREATE',
				'EMERGENCYHOUSE_ALLOCATION_UPDATE',
				'EMERGENCYHOUSE_ALLOCATION_DELETE',
				'EMERGENCYHOUSE_REPORT_CREATE',
				'EMERGENCYHOUSE_REPORT_UPDATE',
				'EMERGENCYHOUSE_REPORT_DELETE',
			),
		);
		return 0;
	}

	/**
	 * Multicompany hook.
	 *
	 * @param array<string, mixed> $parameters Hook parameters
	 * @param object               $object Object
	 * @param string               $action Action
	 * @param HookManager          $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanyExternalModulesSharing($parameters, &$object, &$action, $hookmanager)
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}

	/**
	 * Compatibility alias used by supported Multicompany releases.
	 *
	 * @param array<string, mixed> $parameters Hook parameters
	 * @param object               $object Object
	 * @param string               $action Action
	 * @param HookManager          $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanyExternalModuleSharing($parameters, &$object, &$action, $hookmanager)
	{
		return $this->multicompanyExternalModulesSharing($parameters, $object, $action, $hookmanager);
	}

	/**
	 * Multicompany options hook.
	 *
	 * @param array<string, mixed> $parameters Hook parameters
	 * @param object               $object Object
	 * @param string               $action Action
	 * @param HookManager          $hookmanager Hook manager
	 * @return int
	 */
	public function multicompanySharingOptions($parameters, &$object, &$action, $hookmanager)
	{
		return $this->multicompanyExternalModulesSharing($parameters, $object, $action, $hookmanager);
	}
}
