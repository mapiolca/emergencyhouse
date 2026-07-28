<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require __DIR__.'/_init.php';

if (!headers_sent()) {
	header('Content-Type: text/plain; charset=UTF-8');
	header('Cache-Control: public, max-age=3600');
	header('X-Robots-Tag: noindex', true);
}

print "User-agent: OAI-SearchBot\n";
print "Allow: /\n\n";
print "User-agent: ChatGPT-User\n";
print "Allow: /\n\n";
print "User-agent: GPTBot\n";
print getDolGlobalInt('EMERGENCYHOUSE_PUBLIC_GPTBOT_ALLOWED', 0) === 1 ? "Allow: /\n\n" : "Disallow: /\n\n";
print "User-agent: *\n";
print "Allow: /\n\n";
print 'Sitemap: '.emergencyhousePublicAbsoluteUrl('sitemap.php')."\n";
