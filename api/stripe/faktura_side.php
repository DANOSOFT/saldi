<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/faktura_side.php --- 2026.10.25
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261025 CL/ASR Created: one page of the invoice as an image for the invoice & payment page,
//                 so the left side shows the invoice exactly as its PDF. Images are rendered by
//                 faktura.php (stripe_invoice_page_images); this endpoint only serves them.
//                 Signed link required like the page itself.
//
//   faktura_side.php?o=<ordre_id>&s=<signature>&p=<page, 1-based>

include(__DIR__ . '/faktura_common.php');

$page = isset($_GET['p']) ? (int)$_GET['p'] : 0;
$pages = stripe_invoice_page_images($inv['id'], false);
if ($page < 1 || !isset($pages[$page - 1])) faktura_fail(404, stripe_txt('pdf_unavailable'), stripe_txt('pdf_unavailable_txt'), 'page');
$stream = fopen($pages[$page - 1], 'rb');
if (!$stream) faktura_fail(503, stripe_txt('pdf_unavailable'), stripe_txt('pdf_unavailable_txt'), 'page');
$imageStat = fstat($stream);
while (ob_get_level()) ob_end_clean();
header('Content-Type: image/png');
header('Content-Length: ' . $imageStat['size']);
fpassthru($stream);
fclose($stream);
exit;
