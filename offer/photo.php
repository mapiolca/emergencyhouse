<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	exit;
}

dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/offerphotoservice.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$offerId = GETPOSTINT('id');
$photoId = GETPOSTINT('photo');
$offer = new EmergencyHouseOffer($db);
if (
	!isModEnabled('emergencyhouse')
	|| $offerId <= 0
	|| $photoId <= 0
	|| $offer->fetch($offerId) <= 0
	|| !emergencyhouseCanDo($user, 'listing', 'read', $offer)
) {
	accessforbidden();
}

$photoService = new EmergencyHouseOfferPhotoService($db);
$photo = $photoService->getPhotoFile($offer, $photoId, true);
if (!is_array($photo)) {
	http_response_code(404);
	exit;
}

$fileSize = filesize($photo['path']);
header('Content-Type: '.$photo['mime']);
header('Content-Disposition: inline; filename="offer-photo-'.((int) $photo['id']).'.'.pathinfo($photo['path'], PATHINFO_EXTENSION).'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
if (is_int($fileSize) && $fileSize >= 0) {
	header('Content-Length: '.$fileSize);
}
readfile($photo['path']);
exit;
