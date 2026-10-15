<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_product_vat.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Characterize VAT-inclusive pricing against the current or an explicit CLI baseline engine.

error_reporting(E_ALL);
require_once __DIR__ . '/CharacterizationEnv.php';
$fixture = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$tenantDb = $argv[2];
chdir(CharacterizationEnv::repoRoot() . '/debitor');
$_SERVER['REQUEST_URI'] = '/saldi/debitor/ordre.php';
$_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
$_POST = [];
$header = $bg = 'nix';
ob_start();
require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/std_func.php';
$db = $tenantDb;
$registry = db_fetch_array(db_select("SELECT id FROM regnskab WHERE db='" . db_escape_string($db) . "'", __FILE__ . ' linje ' . __LINE__));
$db_id = $db_skriv_id = (int)$registry['id'];
require_once (getenv('SALDI_PRODUCT_VAT_ENGINE_FILE') ?: __DIR__ . '/../../../includes/ordrefunc.php');
$connection = CharacterizationEnv::connect($db);
$regnaar = $fixture['year'];
$afd = $fixture['department'];
$momssats = 25;
$sprog_id = 0;
$webservice = 1;
$bruger_id = 1;
$brugernavn = 'chartest';
$status = $kundedisplay = $procentfakt = $vis_saet = $tilfravalgNy = 0;
$barcodeNew = $formularsprog = $folger = '';
if (!is_dir('../temp/' . $db)) {
    mkdir('../temp/' . $db, 0770, true);
}
$quote = ifset($argv, 3, '') === 'quote';
if ($quote) {
    pg_query($connection, 'BEGIN');
}
$result = opret_ordrelinje($fixture['order_id'], $fixture['product_id'], '', 1, '', ifset($fixture, 'price', 100), 0, 100,
    'DO', $fixture['caller_exemption'], 1, 0, ifset($fixture, 'incl_moms', 0), 0, 'percent', 0, 0, 0, '', 1, 0);
$output = ob_get_clean();
$line = null;
if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_INERROR) {
    $line = CharacterizationEnv::one($connection, 'SELECT momssats,momsfri,omvbet,vat_account,vat_price,pris FROM ordrelinjer WHERE ordre_id=$1 ORDER BY id DESC LIMIT 1', [$fixture['order_id']]);
}
if ($quote) {
    pg_query($connection, 'ROLLBACK');
}
echo json_encode(['result' => $result, 'line' => $line, 'output' => $output], JSON_THROW_ON_ERROR) . "\n";
