<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require dirname(__DIR__).'/_init.php';

dol_include_once('/emergencyhouse/class/listingservice.class.php');
dol_include_once('/emergencyhouse/class/offerphotoservice.class.php');

$uuid = GETPOST('offer', 'alphanohtml');
$photoId = GETPOSTINT('photo');
$listingService = new EmergencyHouseListingService($db);
$offer = $listingService->fetchViewableOffer($emergencyhousePublicAccount, $uuid);
if (!$offer instanceof EmergencyHouseOffer || $photoId <= 0) {
	http_response_code(404);
	exit;
}

$isOwner = $emergencyhousePublicAccount instanceof EmergencyHousePublicAccount
	&& (int) $emergencyhousePublicAccount->entity === (int) $offer->entity
	&& (int) $emergencyhousePublicAccount->id === (int) $offer->fk_account;
if (!$isOwner && !getDolGlobalInt('EMERGENCYHOUSE_PHOTOS_ENABLED', 0)) {
	http_response_code(404);
	exit;
}

$photoService = new EmergencyHouseOfferPhotoService($db);
$photo = $photoService->getPhotoFile($offer, $photoId, $isOwner);
if (!is_array($photo)) {
	http_response_code(404);
	exit;
}

$publiclyCacheable = !$isOwner
	&& (int) $offer->status === EmergencyHouseOffer::STATUS_PUBLISHED
	&& $photo['status'] === EmergencyHouseOfferPhotoService::STATUS_APPROVED;
$etag = '"eh-offer-photo-'.$photo['file_hash'].'"';
$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) && is_string($_SERVER['HTTP_IF_NONE_MATCH'])
	? trim($_SERVER['HTTP_IF_NONE_MATCH'])
	: '';
header('X-Content-Type-Options: nosniff');
header($publiclyCacheable
	? 'Cache-Control: public, max-age=86400'
	: 'Cache-Control: private, no-store');
header('ETag: '.$etag);
if ($ifNoneMatch !== '' && hash_equals($etag, $ifNoneMatch)) {
	http_response_code(304);
	exit;
}

$fileSize = filesize($photo['path']);
header('Content-Type: '.$photo['mime']);
header('Content-Disposition: inline; filename="offer-photo-'.((int) $photo['id']).'.'.pathinfo($photo['path'], PATHINFO_EXTENSION).'"');
if (is_int($fileSize) && $fileSize >= 0) {
	header('Content-Length: '.$fileSize);
}
readfile($photo['path']);
exit;
