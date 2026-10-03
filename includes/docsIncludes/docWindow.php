<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/docWindow.php --- ver 5.0.0 --- 2026-10-03 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261003 CL/SZ Doc pool task 1: Created: the detached document window ("Åbn i nyt vindue" in the pool).
//                  Shows only the document, no menus, and follows the pool's selection over the
//                  BroadcastChannel 'saldiDocWindow'. The document itself is loaded through docFile.php.
//
// Request: k and f as for docFile.php (k=doc f=pulje/x.pdf, or k=temp f=xml_preview_<md5>.html)

@session_start();
$s_id = session_id();

$header = 'nix';
$bg = 'nix';
$title = 'docWindow';

// online.php ends the request with its "session expired" box when there is no login
include(__DIR__ . '/../connect.php');
include(__DIR__ . '/../online.php');
include_once(__DIR__ . '/../std_func.php');

/**
 * Injected by ../connect.php and ../online.php, included above:
 * @var string $db
 * @var string $sqdb
 */

// Drop the menu styles online.php prints, so the page starts with its doctype
while (ob_get_level()) {
	ob_end_clean();
}

// After a logout online.php lets the session through on the master database; show its own message, no document
if (!$db || $db == $sqdb) {
	http_response_code(403);
	print tekstboks('&nbsp;Din session er udl&oslash;bet - du skal logge ind igen');
	exit;
}

$kind = ifset($_GET, 'k', 'doc') == 'temp' ? 'temp' : 'doc';
$requested = (string)ifset($_GET, 'f', '');
$src = 'docFile.php?k=' . $kind . '&f=' . rawurlencode($requested) . ($kind == 'doc' ? '#pagemode=none' : '');
$name = htmlspecialchars(basename($requested), ENT_QUOTES);

print "<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>$name</title>
<style>
	html, body { margin: 0; height: 100%; overflow: hidden; background: #525659; }
	#docFrame { display: block; width: 100%; height: 100%; border: 0; }
</style>
</head>
<body>
<iframe id='docFrame' src='" . htmlspecialchars($src, ENT_QUOTES) . "'></iframe>
<script>
(function () {
	if (!('BroadcastChannel' in window)) return;
	var channel = new BroadcastChannel('saldiDocWindow');
	var frame = document.getElementById('docFrame');

	// Only docFile.php is ever loaded here; it checks the login and the tenant itself
	function show(kind, file) {
		if ((kind !== 'doc' && kind !== 'temp') || typeof file !== 'string' || file === '') return;
		frame.src = 'docFile.php?k=' + kind + '&f=' + encodeURIComponent(file) + (kind === 'doc' ? '#pagemode=none' : '');
		document.title = file.split('/').pop();
	}

	channel.onmessage = function (event) {
		var message = event.data || {};
		if (message.type === 'show') show(message.k, message.f);
		if (message.type === 'show' || message.type === 'ping') channel.postMessage({ type: 'open' });
	};
	channel.postMessage({ type: 'open' });
	// Also fires when the journal reuses this window for its own document page
	window.addEventListener('pagehide', function () { channel.postMessage({ type: 'closed' }); });
})();
</script>
</body>
</html>";
