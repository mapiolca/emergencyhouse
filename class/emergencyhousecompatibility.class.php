<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Central compatibility registry.
 *
 * @phpstan-type CompatibilityFeature array{
 *     label: string,
 *     description: string,
 *     min_dolibarr: string,
 *     min_php: string,
 *     available: bool,
 *     reason: string
 * }
 */
class EmergencyHouseCompatibility
{
	/**
	 * Test Dolibarr version.
	 *
	 * @param string $version Minimum version
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Test PHP version.
	 *
	 * @param string $version Minimum version
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Return feature registry.
	 *
	 * @return array<string, CompatibilityFeature>
	 */
	public static function getCompatibilityFeatures()
	{
		$sodium = extension_loaded('sodium');
		$cron = function_exists('isModEnabled');

		return array(
			'core_module' => array(
				'label' => 'CompatibilityCoreModule',
				'description' => 'CompatibilityCoreModuleDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => self::isDolibarrVersionAtLeast('20.0.0') && self::isPhpVersionAtLeast('8.0.0'),
				'reason' => 'CompatibilityRequiresDolibarr20Php80',
			),
			'public_encryption' => array(
				'label' => 'CompatibilityPublicEncryption',
				'description' => 'CompatibilityPublicEncryptionDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $sodium,
				'reason' => 'CompatibilityRequiresSodium',
			),
			'native_cron' => array(
				'label' => 'CompatibilityNativeCron',
				'description' => 'CompatibilityNativeCronDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $cron,
				'reason' => 'CompatibilityRequiresNativeCron',
			),
			'datapolicy' => array(
				'label' => 'CompatibilityDataPolicy',
				'description' => 'CompatibilityDataPolicyDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => isModEnabled('datapolicy'),
				'reason' => 'CompatibilityOptionalModuleDisabled',
			),
			'adherent' => array(
				'label' => 'CompatibilityAdherent',
				'description' => 'CompatibilityAdherentDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => isModEnabled('adherent'),
				'reason' => 'CompatibilityOptionalModuleDisabled',
			),
			'resource' => array(
				'label' => 'CompatibilityResource',
				'description' => 'CompatibilityResourceDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => isModEnabled('resource'),
				'reason' => 'CompatibilityOptionalModuleDisabled',
			),
		);
	}

	/**
	 * Test named feature.
	 *
	 * @param string $feature Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($feature)
	{
		$features = self::getCompatibilityFeatures();
		return isset($features[$feature]) && $features[$feature]['available'];
	}

	/**
	 * Return unavailable features.
	 *
	 * @return array<string, CompatibilityFeature>
	 */
	public static function getUnavailableFeatures()
	{
		return array_filter(
			self::getCompatibilityFeatures(),
			static function ($feature) {
				return !$feature['available'];
			}
		);
	}
}

