<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

if (!headers_sent()) {
	header('Content-Type: text/css; charset=UTF-8');
	header('Cache-Control: public, max-age=3600');
}
?>
div.mainmenu.emergencyhouse::before {
	content: "\e1b0";
}

div.mainmenu.emergencyhouse {
	background-image: none;
}
