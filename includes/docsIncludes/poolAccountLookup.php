<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolAccountLookup.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ Doc pool task 2: Created: name and VAT code of the account in a pool Debet/Kredit field, plus the
//                  "sidste 5 posteringer" the other field's lookup panel offers for it. Read-only, JSON.
//
// Request: art=F|D|K  kontonr=<number>  dk=D|K (the field the suggestions are for)  kladde_id=<journal>

ob_start();

@session_start();
$s_id = session_id();
$title = 'poolAccountLookup';
$modulnr = 0;
$bg = 'nix';
$header = 'nix';
$webservice = true;

include(__DIR__ . '/../connect.php');
include(__DIR__ . '/../online.php');
include_once(__DIR__ . '/../std_func.php');
include_once(__DIR__ . '/poolAccountInfo.php');
include_once(__DIR__ . '/../../finans/kassekladde_includes/journalHistory.php');

/**
 * Injected by ../connect.php and ../online.php, included above:
 * @var string $db
 * @var string $sqdb
 * @var int    $regnaar
 * @var int    $sprog_id
 * @var string $db_encode
 */

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (empty($regnaar) || !$db || $db == $sqdb) {
	echo json_encode(array('error' => 'Session expired'));
	exit;
}

$art = strtoupper((string)ifset($_GET, 'art', 'F'));
$kontonr = (string)ifset($_GET, 'kontonr', '');
$dk = strtoupper((string)ifset($_GET, 'dk', ''));
$kladdeId = (int)ifset($_GET, 'kladde_id', 0);
$charset = (isset($db_encode) && $db_encode == 'UTF8') ? 'UTF-8' : 'ISO-8859-1';

$result = array('name' => '', 'moms' => '', 'lastPostings' => array('heading' => '', 'rows' => array()));
if (in_array($art, array('F', 'D', 'K'), true) && ctype_digit($kontonr)) {
	$info = poolAccountInfo($art, $kontonr, $regnaar);
	$result['name'] = $info['name'];
	$result['moms'] = $info['moms'];
	if ($dk == 'D' || $dk == 'K') {
		$result['lastPostings'] = sidste_5_forslag($kontonr, $art, $dk, $kladdeId, $charset, $sprog_id);
	}
}
echo json_encode($result);
