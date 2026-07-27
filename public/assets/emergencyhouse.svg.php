<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!headers_sent()) {
	header('Content-Type: image/svg+xml; charset=UTF-8');
	header('Cache-Control: public, max-age=3600');
}
require dirname(__DIR__, 2).'/img/emergencyhouse.svg';
