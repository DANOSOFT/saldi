<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/faktura_pdf.php --- 2026.10.22
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
// 20261019 CL/ASR Created: "Download PDF" on the invoice & payment page. Serves the cached
//                 invoice PDF (written when the link mail went out) or renders it with
//                 tools/stripe_invoice_pdf.php. Signed link required like the page itself.
// 20261022 CDX/LH Stream one opened private PDF with its matching size after signed invoice verification.
// 20261022 CL/LH S5 Danish PDF error texts from stripe_txt(); the page footer uses the invoicing company (faktura_common).

include(__DIR__ . '/faktura_common.php');

$pdf = stripe_invoice_pdf_path($inv['id'], true);
if ($pdf === '') faktura_fail(503, stripe_txt('pdf_unavailable'), stripe_txt('pdf_unavailable_txt'), 'pdf');
$stream = fopen($pdf, 'rb');
if (!$stream) faktura_fail(503, stripe_txt('pdf_unavailable'), stripe_txt('pdf_unavailable_txt'), 'pdf');
$pdfStat = fstat($stream);
if (function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.invoice_pdf_downloaded', 'ordre', (string)$inv['id'], json_encode(array('nr' => $inv['nr'], 'ip' => $ip)), 'stripe');
while (ob_get_level()) ob_end_clean();
$name = ($inv['is_credit'] ? 'Kreditnota-' : 'Faktura-') . preg_replace('/[^A-Za-z0-9_-]/', '_', $inv['nr']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . $pdfStat['size']);
header('Content-Disposition: attachment; filename="' . $name . '"');
fpassthru($stream);
fclose($stream);
exit;
