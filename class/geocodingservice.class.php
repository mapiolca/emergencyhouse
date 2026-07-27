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
	 * Exact geocoding is allowed only through the pinned Géoplateforme host.
	 * The public Nominatim endpoint is deliberately rejected for exact
	 * addresses. A dedicated cURL call is used because Dolibarr v20's native
	 * getURLContent() logs the full URL, including the exact address query.
	 *
	 * @param string $address Exact address
	 * @return array{latitude:float, longitude:float, provider:string}|false
	 */
	public function geocodeExact($address)
	{
		$address = trim($address);
		if ($address === '') {
			$this->error = 'ErrorGeocodingAddressRequired';
			return false;
		}

		$provider = getDolGlobalString('EMERGENCYHOUSE_GEOCODING_PROVIDER', 'disabled');
		if ($provider === 'disabled' || $provider === 'public_nominatim') {
			$this->error = 'ErrorExactGeocodingDisabled';
			return false;
		}
		if ($provider !== 'geoplateforme') {
			$this->error = 'ErrorGeocodingProviderNotImplemented';
			return false;
		}

		$endpoint = getDolGlobalString(
			'EMERGENCYHOUSE_GEOCODING_ENDPOINT',
			'https://data.geopf.fr/geocodage/search'
		);
		if (!$this->isAllowedEndpoint($endpoint, $provider)) {
			$this->error = 'ErrorGeocodingEndpointInvalid';
			return false;
		}
		if (!function_exists('curl_init')) {
			$this->error = 'ErrorCurlUnavailable';
			return false;
		}

		$separator = strpos($endpoint, '?') === false ? '?' : '&';
		$url = $endpoint.$separator.'q='.urlencode($address).'&limit=1';
		$timeout = min(60, max(5, getDolGlobalInt('MAIN_USE_CURL_TIMEOUT', 20)));
		$curl = curl_init($url);
		if ($curl === false) {
			$this->error = 'ErrorGeocodingServiceUnavailable';
			return false;
		}
		$curlConfigured = curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_HTTPHEADER => array('Accept: application/json'),
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_USERAGENT => 'Dolibarr Emergency House geocoder',
		));
		if (!$curlConfigured) {
			curl_close($curl);
			$this->error = 'ErrorGeocodingServiceUnavailable';
			return false;
		}
		$content = curl_exec($curl);
		$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$curlError = curl_errno($curl);
		curl_close($curl);
		unset($url);
		if ($curlError !== 0 || $httpCode < 200 || $httpCode >= 300 || !is_string($content)) {
			$this->error = 'ErrorGeocodingServiceUnavailable';
			return false;
		}

		$payload = json_decode($content, true);
		if (!is_array($payload) || empty($payload['features']) || !is_array($payload['features'])) {
			$this->error = 'ErrorGeocodingNoResult';
			return false;
		}
		$feature = reset($payload['features']);
		if (
			!is_array($feature)
			|| !isset($feature['geometry'])
			|| !is_array($feature['geometry'])
			|| !isset($feature['geometry']['coordinates'])
			|| !is_array($feature['geometry']['coordinates'])
		) {
			$this->error = 'ErrorGeocodingResponseInvalid';
			return false;
		}
		$coordinates = $feature['geometry']['coordinates'];
		if (count($coordinates) < 2) {
			$this->error = 'ErrorGeocodingResponseInvalid';
			return false;
		}

		$longitude = filter_var($coordinates[0], FILTER_VALIDATE_FLOAT);
		$latitude = filter_var($coordinates[1], FILTER_VALIDATE_FLOAT);
		if (
			$latitude === false
			|| $longitude === false
			|| $latitude < -90.0
			|| $latitude > 90.0
			|| $longitude < -180.0
			|| $longitude > 180.0
		) {
			$this->error = 'ErrorGeocodingResponseInvalid';
			return false;
		}

		return array(
			'latitude' => (float) $latitude,
			'longitude' => (float) $longitude,
			'provider' => $provider,
		);
	}

	/**
	 * Validate the administrator-provided endpoint before sending an address.
	 *
	 * @param string $endpoint Endpoint
	 * @param string $provider Provider code
	 * @return bool
	 */
	private function isAllowedEndpoint($endpoint, $provider)
	{
		if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
			return false;
		}
		$parts = parse_url($endpoint);
		if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || strtolower($parts['scheme']) !== 'https') {
			return false;
		}
		if ($provider !== 'geoplateforme' || strtolower($parts['host']) !== 'data.geopf.fr') {
			return false;
		}
		return true;
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
