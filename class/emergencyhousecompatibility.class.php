<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/memberservice.class.php');

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
		global $conf, $db;

		$sodium = extension_loaded('sodium');
		$curl = function_exists('curl_init');
		$cron = function_exists('isModEnabled');
		$offerPhotos = function_exists('getimagesize')
			&& function_exists('imagecreatefromjpeg')
			&& function_exists('imagecreatefrompng')
			&& function_exists('imagecreatefromwebp')
			&& function_exists('imagejpeg')
			&& function_exists('imagepng')
			&& function_exists('imagewebp')
			&& function_exists('imagealphablending')
			&& function_exists('imagesavealpha');
		$memberReady = false;
		$memberReason = 'CompatibilityRequiresMemberModule';
		if (is_object($db) && is_object($conf) && isset($conf->entity)) {
			$memberService = new EmergencyHouseMemberService($db);
			$memberReady = $memberService->isReady((int) $conf->entity);
			if ($memberService->error === 'ErrorMemberTypeNotConfigured' || $memberService->error === 'ErrorMemberTypeUnavailable') {
				$memberReason = 'CompatibilityRequiresMemberType';
			}
		}

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
			'geoplateforme_geocoding' => array(
				'label' => 'CompatibilityGeoplateformeGeocoding',
				'description' => 'CompatibilityGeoplateformeGeocodingDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $curl,
				'reason' => 'CompatibilityRequiresCurl',
			),
			'offer_photos' => array(
				'label' => 'CompatibilityOfferPhotos',
				'description' => 'CompatibilityOfferPhotosDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $offerPhotos,
				'reason' => 'CompatibilityRequiresGdImages',
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
				'available' => $memberReady,
				'reason' => $memberReason,
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
