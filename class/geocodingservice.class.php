<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/emergencyhouse/class/encryptionservice.class.php');

/**
 * Privacy-preserving geocoding boundary.
 */
class EmergencyHouseGeocodingService
{
	/** @var EmergencyHouseEncryptionService */
	private $encryption;
	/** @var string */
	public $error = '';

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->encryption = new EmergencyHouseEncryptionService();
	}

	/**
	 * Exact geocoding is allowed only through an explicitly configured
	 * contractual or self-hosted provider. The public Nominatim endpoint is
	 * deliberately rejected for exact addresses.
	 *
	 * @param string $address Exact address
	 * @return array{latitude:float, longitude:float, provider:string}|false
	 */
	public function geocodeExact($address)
	{
		unset($address);
		$provider = getDolGlobalString('EMERGENCYHOUSE_GEOCODING_PROVIDER', 'disabled');
		if ($provider === 'disabled' || $provider === 'public_nominatim') {
			$this->error = 'ErrorExactGeocodingDisabled';
			return false;
		}
		$this->error = 'ErrorGeocodingProviderNotImplemented';
		return false;
	}

	/**
	 * Build a coarse cell safe for matching and public display.
	 *
	 * @param float $latitude Latitude
	 * @param float $longitude Longitude
	 * @return string
	 */
	public function buildCoarseCell($latitude, $longitude)
	{
		$lat = floor(($latitude + 90.0) * 10.0) / 10.0;
		$lon = floor(($longitude + 180.0) * 10.0) / 10.0;
		return sprintf('g1:%0.1f:%0.1f', $lat, $lon);
	}

	/**
	 * Encrypt exact coordinates for storage.
	 *
	 * @param int   $entity Entity
	 * @param string $uuid Object UUID
	 * @param float $latitude Latitude
	 * @param float $longitude Longitude
	 * @return array{latitude:string, longitude:string}|false
	 */
	public function encryptCoordinates($entity, $uuid, $latitude, $longitude)
	{
		$context = 'emergencyhouse|geo|'.$entity.'|'.$uuid;
		$latitudeEncrypted = $this->encryption->encrypt((string) $latitude, $context.'|latitude');
		$longitudeEncrypted = $this->encryption->encrypt((string) $longitude, $context.'|longitude');
		if (!is_string($latitudeEncrypted) || !is_string($longitudeEncrypted)) {
			$this->error = $this->encryption->error;
			return false;
		}
		return array('latitude' => $latitudeEncrypted, 'longitude' => $longitudeEncrypted);
	}
}

