<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/invoiceReuseCheck.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ Doc pool task 3: Created: the pool's check "invoice number already used on this kreditor" for one line.
//                  Read-only, JSON {html}. When a new line has neither kreditor nor invoice number yet, the pool
//                  document's matched kreditor and invoice number are checked, so a copy shows before a line exists.
//
// Request: kontonr=<kreditor>  faktura=<invoice no.>  line=<kassekladde id>  kladde_id  bilag  pool=<pool file name>

ob_start();

@session_start();
$s_id = session_id();
$title = 'invoiceReuseCheck';
$modulnr = 0;
$bg = 'nix';
$header = 'nix';
$webservice = true;

include(__DIR__ . '/../../includes/connect.php');
include(__DIR__ . '/../../includes/online.php');
include_once(__DIR__ . '/../../includes/std_func.php');
include_once(__DIR__ . '/invoiceReuse.php');

/**
 * Injected by connect.php and online.php, included above:
 * @var string $db
 * @var string $sqdb
 * @var int    $regnaar
 */

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (empty($regnaar) || !$db || $db == $sqdb) {
	echo json_encode(array('error' => 'Session expired'));
	exit;
}

$kontonr = trim((string)ifset($_GET, 'kontonr', ''));
$faktura = trim((string)ifset($_GET, 'faktura', ''));
$lineId = (int)ifset($_GET, 'line', 0);
$kladdeId = (int)ifset($_GET, 'kladde_id', 0);
$bilag = trim((string)ifset($_GET, 'bilag', ''));
$poolFile = (string)ifset($_GET, 'pool', '');

// A new line with nothing typed yet: check what the document itself says (an existing line is checked as it is)
if (!$lineId && $kontonr === '' && $faktura === '' && $poolFile !== '' && invoice_reuse_pool_ready()) {
	$qtxt = "select a.kontonr, p.invoice_number from pool_files p, adresser a ";
	$qtxt.= "where a.id = p.vendor_konto_id and a.art = 'K' and p.filename = '" . db_escape_string($poolFile) . "'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$kontonr = trim((string)$r['kontonr']);
		$faktura = trim((string)$r['invoice_number']);
	}
}

$hits = invoice_reuse_find(array('line' => array(
	'kontonr'   => $kontonr,
	'faktura'   => $faktura,
	'line_id'   => $lineId,
	'kladde_id' => $kladdeId,
	'bilag'     => $bilag,
	'pool_file' => $poolFile,
)));
$poolLinkBase = '../includes/documents.php?source=kassekladde&sourceId=' . $lineId . '&kladde_id=' . $kladdeId
	. '&bilag=' . rawurlencode($bilag);
echo json_encode(array('html' => invoice_reuse_html($hits['line'] ?? array(), $faktura, $poolLinkBase)));
