<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/afbrudt.php --- 2026.08.07
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260807 CL/LH Created: Checkout cancel landing page. Renders a retry link
//                ONLY when the id+sig pair verifies - otherwise no link, so
//                the page cannot be used to probe or forward signatures.
//                No DB writes, no Stripe calls.
// 20260818 CL/LH Anchored branch-added include paths to __DIR__.
// 20260820 CL/LH Presentation only: shared shell from stripePage.php. The
//                retry link still renders ONLY on a verified id+sig pair.

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

include(__DIR__ . '/../../includes/connect.php');
include(__DIR__ . '/../../includes/stripeIncludes/stripeBootstrap.php');
include(__DIR__ . '/../../includes/stripeIncludes/stripePage.php');
include(__DIR__ . '/../../includes/stripeIncludes/stripeLink.php');

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sig      = isset($_GET['sig']) ? (string)$_GET['sig'] : '';
$retry    = '';
if ($stripe_boot_ok && $order_id > 0 && stripe_link_verify($order_id, $sig)) {
	$retry = 'subscribe.php?id=' . $order_id . '&sig=' . htmlspecialchars($sig, ENT_QUOTES, 'UTF-8');
}

stripe_status_page(200, 'stop', 'Betalingen blev afbrudt',
	['Der er ikke trukket noget beløb, og der er ikke oprettet noget abonnement.'],
	$retry ? "<a class='btn' href='" . $retry . "'>Prøv igen</a>" : '',
	'Din faktura er stadig gyldig og kan betales som hidtil. Spørgsmål? Besvar blot fakturamailen.');
