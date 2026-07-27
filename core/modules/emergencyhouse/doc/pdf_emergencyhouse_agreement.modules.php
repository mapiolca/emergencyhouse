<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
dol_include_once('/emergencyhouse/class/allocation.class.php');
dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/class/sensitivedataservice.class.php');

/**
 * Native PDF accommodation agreement.
 */
class pdf_emergencyhouse_agreement
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $name = 'emergencyhouse_agreement';
	/** @var string */
	public $description = 'EmergencyHouseAgreementModelDescription';
	/** @var string */
	public $type = 'pdf';
	/** @var string */
	public $version = 'dolibarr';
	/** @var string */
	public $scandir = 'emergencyhouse';
	/** @var array<int, int> */
	public $phpmin = array(8, 0);
	/** @var int */
	public $update_main_doc_field = 1;
	/** @var int */
	public $marge_gauche = 15;
	/** @var int */
	public $marge_droite = 15;
	/** @var int */
	public $marge_haute = 15;
	/** @var int */
	public $marge_basse = 15;
	/** @var int|float */
	public $page_largeur = 210;
	/** @var int|float */
	public $page_hauteur = 297;
	/** @var array<int|float|string> */
	public $format = array();
	/** @var Societe */
	public $emetteur;
	/** @var string */
	public $watermark = '';
	/** @var string */
	public $error = '';
	/** @var array<int, string> */
	public $errors = array();
	/** @var array<string, string> */
	public $result = array();
	/** @var int */
	private $heightforfooter = 45;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $mysoc;

		$this->db = $db;
		$this->emetteur = $mysoc;
		$format = pdf_getFormat();
		$this->page_largeur = $format['width'];
		$this->page_hauteur = $format['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->heightforfooter = 55;
	}

	/**
	 * Return the translated model description.
	 *
	 * @param Translate $langs Languages
	 * @return string
	 */
	public function info($langs)
	{
		return $langs->trans('EmergencyHouseAgreementModelDescription');
	}

	/**
	 * Generate agreement.
	 *
	 * @param EmergencyHouseAllocation $object Allocation
	 * @param Translate                $outputlangs Output language
	 * @param string                   $srctemplatepath Source template path
	 * @param int                      $hidedetails Hide details
	 * @param int                      $hidedesc Hide description
	 * @param int                      $hideref Hide reference
	 * @param array<mixed>|null        $moreparams Additional generation parameters
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		global $hookmanager, $user;

		if (!is_object($object) || empty($object->id) || !is_object($user)) {
			$this->error = 'ErrorRecordNotFound';
			return 0;
		}
		$outputlangs->loadLangs(array('emergencyhouse@emergencyhouse', 'main'));
		$participants = $this->loadParticipants($object, $user);
		if (!is_array($participants)) {
			return 0;
		}

		$dir = getMultidirOutput($object, 'emergencyhouse', 1);
		if (!is_string($dir) || $dir === '' || dol_mkdir($dir) < 0) {
			$this->error = 'ErrorCanNotCreateDir';
			return 0;
		}
		$file = $dir.'/'.dol_sanitizeFileName((string) $object->ref).'_agreement.pdf';
		$parameters = array(
			'file' => $file,
			'object' => $object,
			'outputlangs' => $outputlangs,
		);
		$action = '';
		if (is_object($hookmanager)) {
			$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);
			if ($reshook < 0) {
				$this->error = (string) $hookmanager->error;
				return 0;
			}
		}

		$pdf = pdf_getInstance($this->format);
		$pdf->SetCreator('Dolibarr');
		$pdf->SetAuthor($this->emetteur->name);
		$pdf->SetTitle($outputlangs->convToOutputCharset($outputlangs->transnoentities('EmergencyHouseAgreementTitle')));
		$pdf->SetSubject($outputlangs->convToOutputCharset((string) $object->ref));
		$this->heightforfooter = $this->calculateFooterHeight($pdf, $outputlangs, is_object($hookmanager));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(true, $this->heightforfooter);
		$pdf->setPageOrientation('', true, $this->heightforfooter);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs));
		$pdf->AddPage();
		$this->renderHeader($pdf, $object, $outputlangs);

		$sections = array(
			array(
				'title' => $outputlangs->transnoentities('AgreementPurpose'),
				'lines' => array($outputlangs->transnoentities('AgreementPurposeText')),
				'height' => 30,
			),
			array(
				'title' => $outputlangs->transnoentities('Host'),
				'lines' => $this->profileLines($participants['host'], $outputlangs),
				'height' => 35,
			),
			array(
				'title' => $outputlangs->transnoentities('HostedParty'),
				'lines' => $this->profileLines($participants['requester'], $outputlangs),
				'height' => 35,
			),
			array(
				'title' => $outputlangs->transnoentities('Accommodation'),
				'lines' => array(
					$outputlangs->transnoentities('ExactAddress').': '.$participants['address'],
					$outputlangs->transnoentities('PublicZone').': '.$participants['offer']->public_zone,
				),
				'height' => 28,
			),
			array(
				'title' => $outputlangs->transnoentities('StayDetails'),
				'lines' => array(
					$outputlangs->transnoentities('DateStart').': '.dol_print_date((int) $object->date_start, 'dayhour', false, $outputlangs),
					$outputlangs->transnoentities('DateEnd').': '.(!empty($object->date_end) ? dol_print_date((int) $object->date_end, 'dayhour', false, $outputlangs) : $outputlangs->transnoentities('NotDefined')),
					$outputlangs->transnoentities('PeopleCount').': '.((int) $object->quantity),
				),
				'height' => 32,
			),
			array(
				'title' => $outputlangs->transnoentities('Commitments'),
				'lines' => array(
					$outputlangs->transnoentities('HostCommitmentsText'),
					$outputlangs->transnoentities('RequesterCommitmentsText'),
				),
				'height' => 48,
			),
			array(
				'title' => $outputlangs->transnoentities('SafetyAndEmergency'),
				'lines' => array($outputlangs->transnoentities('SafetyAndEmergencyText')),
				'height' => 34,
			),
			array(
				'title' => $outputlangs->transnoentities('NoFinancialCommitment'),
				'lines' => array($outputlangs->transnoentities('NoFinancialCommitmentText')),
				'height' => 30,
			),
		);
		foreach ($sections as $section) {
			$this->ensureSpace($pdf, $object, $outputlangs, (int) $section['height']);
			$this->renderSection($pdf, (string) $section['title'], $section['lines'], $outputlangs);
		}
		$this->ensureSpace($pdf, $object, $outputlangs, 50);
		$this->renderSignatures($pdf, $outputlangs);
		$this->_pagefoot($pdf, $object, $outputlangs, 0);

		$pdf->Output($file, 'F');
		if (!file_exists($file)) {
			$this->error = 'ErrorFailedToGeneratePDF';
			return 0;
		}
		$this->result = array('fullpath' => $file);
		if (is_object($hookmanager)) {
			$parameters['file'] = $file;
			$hookmanager->executeHooks('afterPDFCreation', $parameters, $object, $action);
		}
		return 1;
	}

	/**
	 * Reserve the measured footer area, including company details and hook output.
	 *
	 * @param TCPDF     $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param bool      $hooksAvailable Whether PDF hooks may extend the footer
	 * @return int
	 */
	private function calculateFooterHeight(&$pdf, $outputlangs, $hooksAvailable)
	{
		$freeTextHeight = max(0, getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 20));
		$freeText = getDolGlobalString('EMERGENCYHOUSE_FREE_TEXT');
		if ($freeText !== '' && function_exists('pdfGetHeightForHtmlContent')) {
			$measuredHeight = (int) ceil(pdfGetHeightForHtmlContent(
				$pdf,
				$outputlangs->convToOutputCharset($freeText)
			));
			$freeTextHeight = max($freeTextHeight, $measuredHeight);
		}
		$companyDetailsHeight = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0) ? 18 : 0;
		$hookSafetyHeight = $hooksAvailable ? 25 : 12;

		return max(
			55,
			$this->marge_basse + $freeTextHeight + $companyDetailsHeight + $hookSafetyHeight
		);
	}

	/**
	 * Load and audit sensitive agreement data.
	 *
	 * @param EmergencyHouseAllocation $object Allocation
	 * @param User                     $user Operator
	 * @return array{host:array{firstname:string,lastname:string,email:string,phone:string},requester:array{firstname:string,lastname:string,email:string,phone:string},address:string,offer:EmergencyHouseOffer}|false
	 */
	private function loadParticipants($object, $user)
	{
		$sql = 'SELECT o.fk_account AS host_account, r.fk_account AS requester_account';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'emergencyhouse_offer AS o';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'emergencyhouse_request AS r';
		$sql .= ' ON r.rowid = '.((int) $object->fk_request).' AND r.entity = o.entity';
		$sql .= ' WHERE o.rowid = '.((int) $object->fk_offer);
		$sql .= ' AND o.entity = '.((int) $object->entity);
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : false;
		if (!is_object($row)) {
			$this->error = $resql ? 'ErrorRecordNotFound' : $this->db->lasterror();
			return false;
		}
		$offer = new EmergencyHouseOffer($this->db);
		if ($offer->fetch((int) $object->fk_offer) <= 0) {
			$this->error = $offer->error ?: 'ErrorRecordNotFound';
			return false;
		}
		$sensitive = new EmergencyHouseSensitiveDataService($this->db);
		$host = $sensitive->revealContactForOperator(
			(int) $object->entity,
			(int) $row->host_account,
			$user,
			'allocation_agreement_generation',
			'allocation',
			(int) $object->id,
			(int) $object->fk_campaign
		);
		if (!is_array($host)) {
			$this->error = $sensitive->error;
			return false;
		}
		$requester = $sensitive->revealContactForOperator(
			(int) $object->entity,
			(int) $row->requester_account,
			$user,
			'allocation_agreement_generation',
			'allocation',
			(int) $object->id,
			(int) $object->fk_campaign
		);
		if (!is_array($requester)) {
			$this->error = $sensitive->error;
			return false;
		}
		$address = $sensitive->revealAddressForOperator($offer, $user, 'allocation_agreement_generation');
		if (!is_string($address)) {
			$this->error = $sensitive->error;
			return false;
		}
		return array('host' => $host, 'requester' => $requester, 'address' => $address, 'offer' => $offer);
	}

	/**
	 * Build translated profile lines.
	 *
	 * @param array{firstname:string,lastname:string,email:string,phone:string} $profile Profile
	 * @param Translate $outputlangs Output language
	 * @return array<int, string>
	 */
	private function profileLines($profile, $outputlangs)
	{
		$lines = array(
			$outputlangs->transnoentities('Name').': '.trim($profile['firstname'].' '.$profile['lastname']),
			$outputlangs->transnoentities('Email').': '.$profile['email'],
		);
		if ($profile['phone'] !== '') {
			$lines[] = $outputlangs->transnoentities('Phone').': '.$profile['phone'];
		}
		return $lines;
	}

	/**
	 * Configure and render page header.
	 *
	 * @param TCPDF                    $pdf PDF instance
	 * @param EmergencyHouseAllocation $object Allocation
	 * @param Translate                $outputlangs Output language
	 * @return void
	 */
	private function renderHeader(&$pdf, $object, $outputlangs)
	{
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', pdf_getPDFFontSize($outputlangs) + 4);
		$pdf->MultiCell(0, 8, $outputlangs->convToOutputCharset($outputlangs->transnoentities('EmergencyHouseAgreementTitle')), 0, 'C', false, 1);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs));
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($outputlangs->transnoentities('AgreementReference').': '.$object->ref), 0, 'C', false, 1);
		$pdf->Ln(4);
	}

	/**
	 * Reserve a new page before a section reaches the protected footer.
	 *
	 * @param TCPDF                    $pdf PDF instance
	 * @param EmergencyHouseAllocation $object Allocation
	 * @param Translate                $outputlangs Output language
	 * @param int                      $requiredHeight Required height
	 * @return void
	 */
	private function ensureSpace(&$pdf, $object, $outputlangs, $requiredHeight)
	{
		if ($pdf->GetY() + $requiredHeight <= $this->page_hauteur - $this->heightforfooter) {
			return;
		}
		$this->_pagefoot($pdf, $object, $outputlangs, 1);
		$pdf->AddPage();
		$pdf->setPageOrientation('', true, $this->heightforfooter);
		$this->renderHeader($pdf, $object, $outputlangs);
	}

	/**
	 * Render a labelled section.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param string $title Section title
	 * @param array<int, string> $lines Lines
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderSection(&$pdf, $title, $lines, $outputlangs)
	{
		$width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$pdf->SetFillColor(235, 241, 246);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', pdf_getPDFFontSize($outputlangs));
		$pdf->MultiCell($width, 7, $outputlangs->convToOutputCharset($title), 0, 'L', true, 1);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs) - 1);
		foreach ($lines as $line) {
			$pdf->MultiCell($width, 5, $outputlangs->convToOutputCharset((string) $line), 0, 'L', false, 1);
		}
		$pdf->Ln(3);
	}

	/**
	 * Render signature areas.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @return void
	 */
	private function renderSignatures(&$pdf, $outputlangs)
	{
		$availableWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$columnWidth = ($availableWidth - 8) / 2;
		$startX = $this->marge_gauche;
		$startY = $pdf->GetY();
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', pdf_getPDFFontSize($outputlangs));
		$pdf->MultiCell($availableWidth, 7, $outputlangs->convToOutputCharset($outputlangs->transnoentities('Signatures')), 0, 'L', false, 1);
		$startY = $pdf->GetY();
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs) - 1);
		$pdf->SetXY($startX, $startY);
		$pdf->MultiCell($columnWidth, 28, $outputlangs->convToOutputCharset($outputlangs->transnoentities('HostSignature')), 1, 'L', false, 0);
		$pdf->SetXY($startX + $columnWidth + 8, $startY);
		$pdf->MultiCell($columnWidth, 28, $outputlangs->convToOutputCharset($outputlangs->transnoentities('RequesterSignature')), 1, 'L', false, 1);
	}

	/**
	 * Render the native Dolibarr footer.
	 *
	 * @param TCPDF                    $pdf PDF instance
	 * @param EmergencyHouseAllocation $object Allocation
	 * @param Translate                $outputlangs Output language
	 * @param int                      $hidefreetext Hide free text
	 * @return int
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		return pdf_pagefoot(
			$pdf,
			$outputlangs,
			'EMERGENCYHOUSE_FREE_TEXT',
			$this->emetteur,
			$this->marge_basse,
			$this->marge_gauche,
			$this->page_hauteur,
			$object,
			$showdetails,
			$hidefreetext,
			$this->page_largeur,
			$this->watermark
		);
	}
}
