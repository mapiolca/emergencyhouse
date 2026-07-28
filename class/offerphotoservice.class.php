<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

dol_include_once('/emergencyhouse/class/offer.class.php');

/**
 * Validate, persist and resolve public accommodation photos.
 *
 * Uploaded images are decoded and encoded again before persistence. This
 * removes metadata such as EXIF/GPS information and makes the file served by
 * the portal independent from its untrusted original name.
 */
class EmergencyHouseOfferPhotoService
{
	public const STATUS_PENDING = 0;
	public const STATUS_APPROVED = 1;
	public const STATUS_REJECTED = 2;
	public const MAX_PHOTOS = 5;

	/** Maximum size of one photo, before the lower native upload limit. */
	private const MAX_PHOTO_BYTES = 5242880;

	/** Reject compressed images that would require excessive decoded memory. */
	private const MAX_PIXELS = 20000000;

	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/** @var array<int, string> */
	public $errors = array();

	/** @var array<int, string> */
	private $createdFiles = array();

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
	 * Return whether all image functions required for safe persistence exist.
	 *
	 * @return bool
	 */
	public static function isAvailable()
	{
		return function_exists('getimagesize')
			&& function_exists('imagecreatefromjpeg')
			&& function_exists('imagecreatefrompng')
			&& function_exists('imagecreatefromwebp')
			&& function_exists('imagejpeg')
			&& function_exists('imagepng')
			&& function_exists('imagewebp')
			&& function_exists('imagealphablending')
			&& function_exists('imagesavealpha');
	}

	/**
	 * Return whether a PHP multiple-upload field contains at least one file.
	 *
	 * @param array<string, mixed> $uploadedFiles PHP upload field
	 * @return bool
	 */
	public static function hasUploadedFiles(array $uploadedFiles)
	{
		if (!isset($uploadedFiles['error']) || !is_array($uploadedFiles['error'])) {
			return false;
		}
		foreach ($uploadedFiles['error'] as $uploadError) {
			if ((int) $uploadError !== UPLOAD_ERR_NO_FILE) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return the translation key for a photo status.
	 *
	 * @param int $status Photo status
	 * @return string
	 */
	public static function getStatusTranslationKey($status)
	{
		if ($status === self::STATUS_APPROVED) {
			return 'OfferPhotoStatusApproved';
		}
		if ($status === self::STATUS_REJECTED) {
			return 'OfferPhotoStatusRejected';
		}
		return 'OfferPhotoStatusPending';
	}

	/**
	 * Add validated uploaded photos to an offer.
	 *
	 * The caller owns the database transaction. Files created by a failed call
	 * are removed before returning.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param array<string, mixed> $uploadedFiles PHP upload field
	 * @param int $userId Creator user ID, or zero for the public technical user
	 * @return bool
	 */
	public function addUploadedPhotos($offer, array $uploadedFiles, $userId)
	{
		$this->error = '';
		$this->errors = array();
		$this->createdFiles = array();

		if (!self::hasUploadedFiles($uploadedFiles)) {
			return true;
		}
		if (!getDolGlobalInt('EMERGENCYHOUSE_PHOTOS_ENABLED', 0)) {
			$this->error = 'ErrorOfferPhotosDisabled';
			return false;
		}
		if (!self::isAvailable()) {
			$this->error = 'ErrorOfferPhotosUnavailable';
			return false;
		}
		if (empty($offer->id) || empty($offer->ref) || empty($offer->entity)) {
			$this->error = 'ErrorRecordNotFound';
			return false;
		}

		$photos = $this->prepareUploads($uploadedFiles);
		if (!is_array($photos)) {
			return false;
		}
		if (empty($photos)) {
			return true;
		}

		$existingCount = $this->countPhotos($offer);
		if ($existingCount < 0) {
			return false;
		}
		if ($existingCount + count($photos) > self::MAX_PHOTOS) {
			$this->error = 'ErrorTooManyOfferPhotos';
			return false;
		}

		$directory = $this->getPhotoDirectory($offer);
		if (!is_string($directory) || $directory === '' || dol_mkdir($directory) < 0) {
			$this->error = 'ErrorOfferPhotoStorage';
			return false;
		}
		$position = $this->getLastPosition($offer);
		if ($position < 0) {
			return false;
		}

		foreach ($photos as $photo) {
			$hashExists = $this->photoHashExists($offer, $photo['hash']);
			if ($hashExists < 0) {
				$this->cleanupCreatedFiles();
				return false;
			}
			if ($hashExists > 0) {
				$this->error = 'ErrorDuplicateOfferPhoto';
				$this->cleanupCreatedFiles();
				return false;
			}
			$position++;
			if (!$this->persistPhoto($offer, $photo, $directory, $position, (int) $userId)) {
				$this->cleanupCreatedFiles();
				return false;
			}
		}

		return true;
	}

	/**
	 * Return photo metadata for an offer.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param bool $includeNonApproved Include pending and rejected photos
	 * @return array<int, array{id:int,file_name:string,file_hash:string,position:int,status:int,date_creation:string}>|false
	 */
	public function fetchPhotos($offer, $includeNonApproved = false)
	{
		$sql = 'SELECT rowid, file_name, file_hash, position, status, date_creation';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer_photo';
		$sql .= ' WHERE entity = '.((int) $offer->entity).' AND fk_offer = '.((int) $offer->id);
		if (!$includeNonApproved) {
			$sql .= ' AND status = '.self::STATUS_APPROVED;
		}
		$sql .= ' ORDER BY position ASC, rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}

		$photos = array();
		while (is_object($photo = $this->db->fetch_object($resql))) {
			$photos[] = array(
				'id' => (int) $photo->rowid,
				'file_name' => (string) $photo->file_name,
				'file_hash' => (string) $photo->file_hash,
				'position' => (int) $photo->position,
				'status' => (int) $photo->status,
				'date_creation' => (string) $photo->date_creation,
			);
		}
		return $photos;
	}

	/**
	 * Resolve one photo to a safe local file.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param int $photoId Photo ID
	 * @param bool $includeNonApproved Include pending and rejected photos
	 * @return array{id:int,path:string,mime:string,file_hash:string,status:int}|false
	 */
	public function getPhotoFile($offer, $photoId, $includeNonApproved = false)
	{
		$photo = $this->fetchPhotoRecord($offer, $photoId, $includeNonApproved);
		if (!is_array($photo)) {
			return false;
		}

		$fileName = $photo['file_name'];
		if (
			$fileName !== basename($fileName)
			|| !preg_match('/^[a-f0-9]{64}-[a-f0-9]{8}\.(?:jpg|png|webp)$/', $fileName)
		) {
			$this->error = 'ErrorOfferPhotoNotFound';
			return false;
		}
		$directory = $this->getPhotoDirectory($offer);
		if (!is_string($directory) || $directory === '') {
			$this->error = 'ErrorOfferPhotoStorage';
			return false;
		}
		$path = $directory.'/'.$fileName;
		if (!is_file($path) || !is_readable($path)) {
			$this->error = 'ErrorOfferPhotoNotFound';
			return false;
		}

		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		$mimes = array(
			'jpg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
		);
		if (!isset($mimes[$extension])) {
			$this->error = 'ErrorOfferPhotoNotFound';
			return false;
		}

		return array(
			'id' => $photo['id'],
			'path' => $path,
			'mime' => $mimes[$extension],
			'file_hash' => $photo['file_hash'],
			'status' => $photo['status'],
		);
	}

	/**
	 * Delete photo metadata and return the physical path to remove after commit.
	 *
	 * The caller owns the transaction and must call deletePhysicalFile() only
	 * after a successful commit.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param int $photoId Photo ID
	 * @return string|false
	 */
	public function deletePhotoMetadata($offer, $photoId)
	{
		$photo = $this->fetchPhotoRecord($offer, $photoId, true);
		if (!is_array($photo)) {
			return false;
		}
		$directory = $this->getPhotoDirectory($offer);
		if (!is_string($directory) || $directory === '') {
			$this->error = 'ErrorOfferPhotoStorage';
			return false;
		}
		if (
			$photo['file_name'] !== basename($photo['file_name'])
			|| !preg_match('/^[a-f0-9]{64}-[a-f0-9]{8}\.(?:jpg|png|webp)$/', $photo['file_name'])
		) {
			$this->error = 'ErrorOfferPhotoNotFound';
			return false;
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer_photo';
		$sql .= ' WHERE rowid = '.((int) $photoId);
		$sql .= ' AND entity = '.((int) $offer->entity).' AND fk_offer = '.((int) $offer->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}

		return $directory.'/'.$photo['file_name'];
	}

	/**
	 * Remove a committed photo file.
	 *
	 * @param string $path Validated path returned by deletePhotoMetadata()
	 * @return bool
	 */
	public function deletePhysicalFile($path)
	{
		if (!is_file($path)) {
			return true;
		}
		$result = dol_delete_file($path);
		if (!$result && is_file($path)) {
			dol_syslog(__METHOD__.': unable to delete an offer photo', LOG_WARNING);
			return false;
		}
		return true;
	}

	/**
	 * Apply an operator verification status to all photos of an offer.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param int $status Photo status
	 * @return bool
	 */
	public function updateStatuses($offer, $status)
	{
		if (!in_array($status, array(self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED), true)) {
			$this->error = 'ErrorBadParameter';
			return false;
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'emergencyhouse_offer_photo';
		$sql .= ' SET status = '.((int) $status);
		$sql .= ' WHERE entity = '.((int) $offer->entity).' AND fk_offer = '.((int) $offer->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}

	/**
	 * Return the entity-aware photo directory.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @return string|false
	 */
	public function getPhotoDirectory($offer)
	{
		$outputDirectory = getMultidirOutput($offer, 'emergencyhouse', 1);
		if (
			!is_string($outputDirectory)
			|| $outputDirectory === ''
			|| strpos($outputDirectory, 'error-') === 0
		) {
			$this->error = 'ErrorOfferPhotoStorage';
			return false;
		}
		return rtrim($outputDirectory, '/\\').'/photos';
	}

	/**
	 * Validate and normalize the PHP multiple-upload structure.
	 *
	 * @param array<string, mixed> $uploadedFiles PHP upload field
	 * @return array<int, array{tmp_name:string,mime:string,extension:string,hash:string,original_name:string}>|false
	 */
	private function prepareUploads(array $uploadedFiles)
	{
		if (
			!isset($uploadedFiles['name'], $uploadedFiles['tmp_name'], $uploadedFiles['error'], $uploadedFiles['size'])
			|| !is_array($uploadedFiles['name'])
			|| !is_array($uploadedFiles['tmp_name'])
			|| !is_array($uploadedFiles['error'])
			|| !is_array($uploadedFiles['size'])
		) {
			$this->error = 'ErrorOfferPhotoUpload';
			return false;
		}

		$indexes = array();
		foreach (array_keys($uploadedFiles['name']) as $index) {
			$uploadError = isset($uploadedFiles['error'][$index])
				? (int) $uploadedFiles['error'][$index]
				: UPLOAD_ERR_NO_FILE;
			if ($uploadError !== UPLOAD_ERR_NO_FILE) {
				$indexes[] = $index;
			}
		}
		if (count($indexes) > self::MAX_PHOTOS) {
			$this->error = 'ErrorTooManyOfferPhotos';
			return false;
		}

		$nativeLimits = getMaxFileSizeArray();
		$nativeTotalLimit = isset($nativeLimits['maxmin']) ? (int) $nativeLimits['maxmin'] * 1024 : 0;
		if ($nativeTotalLimit <= 0 && !empty($indexes)) {
			$this->error = 'ErrorFileUploadDisabled';
			return false;
		}

		$totalSize = 0;
		$allowed = array(
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/webp' => 'webp',
		);
		$prepared = array();
		foreach ($indexes as $index) {
			$uploadError = isset($uploadedFiles['error'][$index])
				? (int) $uploadedFiles['error'][$index]
				: UPLOAD_ERR_NO_FILE;
			$tmpName = isset($uploadedFiles['tmp_name'][$index]) && is_string($uploadedFiles['tmp_name'][$index])
				? $uploadedFiles['tmp_name'][$index]
				: '';
			$originalName = isset($uploadedFiles['name'][$index]) && is_string($uploadedFiles['name'][$index])
				? $uploadedFiles['name'][$index]
				: '';
			if ($uploadError !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
				$this->error = 'ErrorOfferPhotoUpload';
				return false;
			}

			$actualSize = filesize($tmpName);
			if (!is_int($actualSize) || $actualSize <= 0) {
				$this->error = 'ErrorOfferPhotoUpload';
				return false;
			}
			$totalSize += $actualSize;
			if ($actualSize > self::MAX_PHOTO_BYTES || $totalSize > $nativeTotalLimit) {
				$this->error = 'ErrorOfferPhotoTooLarge';
				return false;
			}

			$safeName = substr(dol_sanitizeFileName($originalName), 0, 160);
			$imageInfo = @getimagesize($tmpName);
			$mime = is_array($imageInfo) && isset($imageInfo['mime']) && is_string($imageInfo['mime'])
				? strtolower($imageInfo['mime'])
				: '';
			$width = is_array($imageInfo) && isset($imageInfo[0]) ? (int) $imageInfo[0] : 0;
			$height = is_array($imageInfo) && isset($imageInfo[1]) ? (int) $imageInfo[1] : 0;
			$extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
			$expectedExtensions = array(
				'image/jpeg' => array('jpg', 'jpeg'),
				'image/png' => array('png'),
				'image/webp' => array('webp'),
			);
			if (
				$safeName === ''
				|| !isset($allowed[$mime], $expectedExtensions[$mime])
				|| !in_array($extension, $expectedExtensions[$mime], true)
				|| $width <= 0
				|| $height <= 0
			) {
				$this->error = 'ErrorOfferPhotoType';
				return false;
			}
			if ($width * $height > self::MAX_PIXELS) {
				$this->error = 'ErrorOfferPhotoTooLarge';
				return false;
			}

			$fileNameErrors = dolCheckOnFileName($tmpName, $safeName);
			$virusErrors = dolCheckVirus($tmpName, $safeName);
			if (!empty($fileNameErrors) || !empty($virusErrors)) {
				$this->error = 'ErrorOfferPhotoRejected';
				return false;
			}
			$hash = hash_file('sha256', $tmpName);
			if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
				$this->error = 'ErrorOfferPhotoUpload';
				return false;
			}

			$prepared[] = array(
				'tmp_name' => $tmpName,
				'mime' => $mime,
				'extension' => $allowed[$mime],
				'hash' => $hash,
				'original_name' => $safeName,
			);
		}
		return $prepared;
	}

	/**
	 * Persist one already validated image and its metadata.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param array{tmp_name:string,mime:string,extension:string,hash:string,original_name:string} $photo Photo
	 * @param string $directory Destination
	 * @param int $position Position
	 * @param int $userId User ID
	 * @return bool
	 */
	private function persistPhoto($offer, array $photo, $directory, $position, $userId)
	{
		if ($photo['mime'] === 'image/jpeg') {
			$image = @imagecreatefromjpeg($photo['tmp_name']);
		} elseif ($photo['mime'] === 'image/png') {
			$image = @imagecreatefrompng($photo['tmp_name']);
		} else {
			$image = @imagecreatefromwebp($photo['tmp_name']);
		}
		if ($image === false) {
			$this->error = 'ErrorOfferPhotoType';
			return false;
		}

		if ($photo['mime'] !== 'image/jpeg') {
			imagealphablending($image, false);
			imagesavealpha($image, true);
		}
		$randomSuffix = bin2hex(random_bytes(4));
		$fileName = $photo['hash'].'-'.$randomSuffix.'.'.$photo['extension'];
		$temporaryPath = $directory.'/.'.$fileName.'.part';
		$finalPath = $directory.'/'.$fileName;
		if ($photo['mime'] === 'image/jpeg') {
			$written = @imagejpeg($image, $temporaryPath, 88);
		} elseif ($photo['mime'] === 'image/png') {
			$written = @imagepng($image, $temporaryPath, 6);
		} else {
			$written = @imagewebp($image, $temporaryPath, 85);
		}
		$temporarySize = is_file($temporaryPath) ? filesize($temporaryPath) : false;
		if (!$written || !is_int($temporarySize) || $temporarySize <= 0 || !@rename($temporaryPath, $finalPath)) {
			if (is_file($temporaryPath)) {
				dol_delete_file($temporaryPath);
			}
			$this->error = 'ErrorOfferPhotoStorage';
			return false;
		}
		$this->createdFiles[] = $finalPath;

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'emergencyhouse_offer_photo';
		$sql .= ' (entity, fk_offer, file_name, file_hash, position, status, date_creation, fk_user_creat)';
		$sql .= ' VALUES ('.((int) $offer->entity).', '.((int) $offer->id).',';
		$sql .= " '".$this->db->escape($fileName)."', '".$this->db->escape($photo['hash'])."',";
		$sql .= ' '.((int) $position).', '.self::STATUS_PENDING.", '".$this->db->idate(dol_now())."',";
		$sql .= $userId > 0 ? ' '.((int) $userId).')' : ' NULL)';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}

	/**
	 * Count offer photos.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @return int Negative on error
	 */
	private function countPhotos($offer)
	{
		$sql = 'SELECT COUNT(rowid) AS photo_count FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer_photo';
		$sql .= ' WHERE entity = '.((int) $offer->entity).' AND fk_offer = '.((int) $offer->id);
		$resql = $this->db->query($sql);
		$count = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($count)) {
			$this->error = $resql ? 'ErrorInternalError' : $this->db->lasterror();
			return -1;
		}
		return (int) $count->photo_count;
	}

	/**
	 * Return last position.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @return int Negative on error
	 */
	private function getLastPosition($offer)
	{
		$sql = 'SELECT MAX(position) AS last_position FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer_photo';
		$sql .= ' WHERE entity = '.((int) $offer->entity).' AND fk_offer = '.((int) $offer->id);
		$resql = $this->db->query($sql);
		$position = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($position)) {
			$this->error = $resql ? 'ErrorInternalError' : $this->db->lasterror();
			return -1;
		}
		return $position->last_position === null ? 0 : (int) $position->last_position;
	}

	/**
	 * Test a photo hash inside one offer.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param string $hash SHA-256
	 * @return int 1 when found, 0 when absent, -1 on error
	 */
	private function photoHashExists($offer, $hash)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer_photo';
		$sql .= ' WHERE entity = '.((int) $offer->entity).' AND fk_offer = '.((int) $offer->id);
		$sql .= " AND file_hash = '".$this->db->escape($hash)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return $this->db->num_rows($resql) > 0 ? 1 : 0;
	}

	/**
	 * Fetch one metadata row.
	 *
	 * @param EmergencyHouseOffer $offer Offer
	 * @param int $photoId Photo ID
	 * @param bool $includeNonApproved Include pending and rejected photos
	 * @return array{id:int,file_name:string,file_hash:string,status:int}|false
	 */
	private function fetchPhotoRecord($offer, $photoId, $includeNonApproved)
	{
		if ($photoId <= 0) {
			$this->error = 'ErrorOfferPhotoNotFound';
			return false;
		}
		$sql = 'SELECT rowid, file_name, file_hash, status';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer_photo';
		$sql .= ' WHERE rowid = '.((int) $photoId);
		$sql .= ' AND entity = '.((int) $offer->entity).' AND fk_offer = '.((int) $offer->id);
		if (!$includeNonApproved) {
			$sql .= ' AND status = '.self::STATUS_APPROVED;
		}
		$resql = $this->db->query($sql);
		$photo = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($photo)) {
			$this->error = $resql ? 'ErrorOfferPhotoNotFound' : $this->db->lasterror();
			return false;
		}
		return array(
			'id' => (int) $photo->rowid,
			'file_name' => (string) $photo->file_name,
			'file_hash' => (string) $photo->file_hash,
			'status' => (int) $photo->status,
		);
	}

	/**
	 * Remove files created by the current failed operation.
	 *
	 * @return void
	 */
	private function cleanupCreatedFiles()
	{
		foreach ($this->createdFiles as $path) {
			if (is_file($path)) {
				dol_delete_file($path);
			}
		}
		$this->createdFiles = array();
	}
}
