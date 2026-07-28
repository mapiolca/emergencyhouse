<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!function_exists('imagecreate') || !function_exists('imagepng')) {
	http_response_code(503);
	exit;
}

$coreCaptcha = '';
foreach (
	array(
		'../../core/antispamimage.php',
		'../../../core/antispamimage.php',
		'../../../../core/antispamimage.php',
		'../../../../../core/antispamimage.php',
	) as $candidate
) {
	$resolvedCandidate = realpath(__DIR__.'/'.$candidate);
	if (is_string($resolvedCandidate) && is_file($resolvedCandidate)) {
		$coreCaptcha = $resolvedCandidate;
		break;
	}
}
if ($coreCaptcha === '') {
	http_response_code(404);
	exit;
}

$previousDirectory = getcwd();
chdir(dirname($coreCaptcha));
require $coreCaptcha;
if (is_string($previousDirectory) && $previousDirectory !== '') {
	chdir($previousDirectory);
}
