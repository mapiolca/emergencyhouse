<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

/**
 * Deliver public contact requests through the native mail transport.
 *
 * Attachments are validated from PHP temporary uploads and are never persisted
 * by the module.
 */
class EmergencyHousePublicContactService
{
	/** Maximum number of screenshots or photos per request. */
	private const MAX_ATTACHMENTS = 5;

	/** Maximum size per attachment, before the lower native upload limit. */
	private const MAX_ATTACHMENT_BYTES = 5242880;

	/** @var string */
	public $error = '';

	/** @var array<int, string> */
	public $errors = array();

	/**
	 * Send one contact request.
	 *
	 * @param string               $name Visitor name
	 * @param string               $email Visitor email
	 * @param string               $phone Visitor phone
	 * @param string               $requestSubject Visitor subject
	 * @param string               $message Visitor message
	 * @param array<string, mixed> $uploadedFiles Normalized attachments field from PHP
	 * @return int 1 on success, -1 on failure
	 */
	public function send($name, $email, $phone, $requestSubject, $message, array $uploadedFiles)
	{
		global $langs;

		$this->error = '';
		$this->errors = array();
		$name = trim(dol_string_nohtmltag($name));
		$email = trim($email);
		$phone = trim(dol_string_nohtmltag($phone));
		$requestSubject = trim(dol_string_nohtmltag($requestSubject));
		$normalizedSubject = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $requestSubject);
		$requestSubject = is_string($normalizedSubject) ? trim($normalizedSubject) : '';
		$message = trim(dol_string_nohtmltag($message));

		if ($name === '' || dol_strlen($name) > 160) {
			$this->error = 'ErrorContactNameInvalid';
			return -1;
		}
		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || dol_strlen($email) > 255) {
			$this->error = 'ErrorContactEmailInvalid';
			return -1;
		}
		if (dol_strlen($phone) > 40) {
			$this->error = 'ErrorContactPhoneInvalid';
			return -1;
		}
		if ($requestSubject === '' || dol_strlen($requestSubject) > 180) {
			$this->error = 'ErrorContactSubjectInvalid';
			return -1;
		}
		if (dol_strlen($message) < 20 || dol_strlen($message) > 5000) {
			$this->error = 'ErrorContactMessageInvalid';
			return -1;
		}

		$supportEmail = trim(getDolGlobalString('EMERGENCYHOUSE_PUBLIC_SUPPORT_EMAIL', ''));
		if (filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
			$this->error = 'ErrorSupportEmailNotConfigured';
			return -1;
		}

		$attachments = $this->prepareAttachments($uploadedFiles);
		if (!is_array($attachments)) {
			return -1;
		}

		$from = trim(getDolGlobalString('MAIN_MAIL_EMAIL_FROM', ''));
		if ($from === '') {
			$from = trim(getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', ''));
		}
		if ($from === '') {
			$this->error = 'ErrorSenderEmailMissing';
			return -1;
		}

		$subject = $langs->transnoentitiesnoconv('ContactRequestMailSubject', $requestSubject);
		$body = '<h2>'.dol_escape_htmltag($langs->transnoentitiesnoconv('ContactRequestMailTitle')).'</h2>';
		$body .= '<p><strong>'.dol_escape_htmltag($langs->transnoentitiesnoconv('Name')).' :</strong> '
			.dol_escape_htmltag($name).'</p>';
		$body .= '<p><strong>'.dol_escape_htmltag($langs->transnoentitiesnoconv('Email')).' :</strong> '
			.dol_escape_htmltag($email).'</p>';
		if ($phone !== '') {
			$body .= '<p><strong>'.dol_escape_htmltag($langs->transnoentitiesnoconv('Phone')).' :</strong> '
				.dol_escape_htmltag($phone).'</p>';
		}
		$body .= '<p><strong>'.dol_escape_htmltag($langs->transnoentitiesnoconv('Subject')).' :</strong> '
			.dol_escape_htmltag($requestSubject).'</p>';
		$body .= '<hr><p>'.nl2br(dol_escape_htmltag($message)).'</p>';

		$trackId = 'emergencyhouse-contact-'.bin2hex(random_bytes(8));
		// CMailFile applies the configured send mode, mail hooks, forced
		// recipients and MAIN_MAIL_AUTOCOPY_TO permanent BCC.
		$mail = new CMailFile(
			$subject,
			$supportEmail,
			$from,
			$body,
			$attachments['paths'],
			$attachments['mimes'],
			$attachments['names'],
			'',
			'',
			0,
			-1,
			'',
			'',
			$trackId,
			'',
			'standard',
			$email
		);
		if (!empty($mail->error) || !empty($mail->errors) || !$mail->sendfile()) {
			$this->error = !empty($mail->error) || !empty($mail->errors) ? 'ErrorMailTransport' : 'ErrorMailSend';
			dol_syslog(__METHOD__.': '.$this->error, LOG_ERR);
			return -1;
		}

		return 1;
	}

	/**
	 * Validate public image attachments.
	 *
	 * @param array<string, mixed> $uploadedFiles PHP upload field
	 * @return array{paths:array<int, string>,mimes:array<int, string>,names:array<int, string>}|false
	 */
	private function prepareAttachments(array $uploadedFiles)
	{
		$result = array('paths' => array(), 'mimes' => array(), 'names' => array());
		if (empty($uploadedFiles)) {
			return $result;
		}
		if (
			!isset($uploadedFiles['name'], $uploadedFiles['tmp_name'], $uploadedFiles['error'], $uploadedFiles['size'])
			|| !is_array($uploadedFiles['name'])
			|| !is_array($uploadedFiles['tmp_name'])
			|| !is_array($uploadedFiles['error'])
			|| !is_array($uploadedFiles['size'])
		) {
			$this->error = 'ErrorAttachmentUpload';
			return false;
		}

		$indexes = array_keys($uploadedFiles['name']);
		$nonEmptyIndexes = array();
		foreach ($indexes as $index) {
			$uploadError = isset($uploadedFiles['error'][$index]) ? (int) $uploadedFiles['error'][$index] : UPLOAD_ERR_NO_FILE;
			if ($uploadError !== UPLOAD_ERR_NO_FILE) {
				$nonEmptyIndexes[] = $index;
			}
		}
		if (count($nonEmptyIndexes) > self::MAX_ATTACHMENTS) {
			$this->error = 'ErrorTooManyContactAttachments';
			return false;
		}

		$nativeLimits = getMaxFileSizeArray();
		$nativeTotalLimit = isset($nativeLimits['maxmin']) ? (int) $nativeLimits['maxmin'] * 1024 : 0;
		if ($nativeTotalLimit <= 0 && !empty($nonEmptyIndexes)) {
			$this->error = 'ErrorFileUploadDisabled';
			return false;
		}
		$totalSize = 0;
		$allowedExtensions = array(
			'image/jpeg' => array('jpg', 'jpeg'),
			'image/png' => array('png'),
			'image/webp' => array('webp'),
		);

		foreach ($nonEmptyIndexes as $index) {
			$uploadError = isset($uploadedFiles['error'][$index]) ? (int) $uploadedFiles['error'][$index] : UPLOAD_ERR_NO_FILE;
			$tmpName = isset($uploadedFiles['tmp_name'][$index]) && is_string($uploadedFiles['tmp_name'][$index])
				? $uploadedFiles['tmp_name'][$index]
				: '';
			$originalName = isset($uploadedFiles['name'][$index]) && is_string($uploadedFiles['name'][$index])
				? $uploadedFiles['name'][$index]
				: '';
			if ($uploadError !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
				$this->error = 'ErrorAttachmentUpload';
				return false;
			}

			$actualSize = filesize($tmpName);
			if (!is_int($actualSize) || $actualSize <= 0) {
				$this->error = 'ErrorAttachmentUpload';
				return false;
			}
			$totalSize += $actualSize;
			if ($actualSize > self::MAX_ATTACHMENT_BYTES || $totalSize > $nativeTotalLimit) {
				$this->error = 'ErrorContactAttachmentTooLarge';
				return false;
			}

			$safeName = substr(dol_sanitizeFileName($originalName), 0, 160);
			$imageInfo = @getimagesize($tmpName);
			$mime = is_array($imageInfo) && isset($imageInfo['mime']) && is_string($imageInfo['mime'])
				? strtolower($imageInfo['mime'])
				: '';
			$extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
			if (
				$safeName === ''
				|| !isset($allowedExtensions[$mime])
				|| !in_array($extension, $allowedExtensions[$mime], true)
			) {
				$this->error = 'ErrorContactAttachmentType';
				return false;
			}

			$fileNameErrors = dolCheckOnFileName($tmpName, $safeName);
			$virusErrors = dolCheckVirus($tmpName, $safeName);
			if (!empty($fileNameErrors) || !empty($virusErrors)) {
				$this->error = 'ErrorContactAttachmentRejected';
				return false;
			}

			$result['paths'][] = $tmpName;
			$result['mimes'][] = $mime;
			$result['names'][] = $safeName;
		}

		return $result;
	}
}
