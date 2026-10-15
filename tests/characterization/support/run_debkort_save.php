<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_debkort_save.php --- ver 5.0.0 --- 2026.10.03
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

$_SERVER['REQUEST_URI'] = '/saldi/debitor/debkort.php';
require_once __DIR__ . '/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/std_func.php';
error_reporting(E_ALL);
$connection = CharacterizationEnv::connect($argv[1]);
$db_type = 'postgresql';
$sprog_id = 0;
$_GET = ['delete_category'=>'', 'rename_category'=>''];
$_POST = array_fill_keys(["addr1", "addr2", "ans_ant", "ans_id", "bank_konto", "bank_reg", "betalingsbet", "betalingsdage", "bynavn", "cat_antal", "cat_beskrivelse", "cat_id", "cat_valg", "cvrnr", "ean", "efternavn", "email", "felt_1", "felt_2", "felt_3", "felt_4", "felt_5", "firmanavn", "fokus", "fornavn", "gl_kontotype", "gruppe", "id", "institution", "kontakt", "kontoansvarlig", "kontonr", "kontotype", "kreditmax", "land", "lev_addr1", "lev_addr2", "lev_bynavn", "lev_efternavn", "lev_email", "lev_firmanavn", "lev_fornavn", "lev_kontakt", "lev_land", "lev_postnr", "lev_tlf", "lukket", "mailfakt", "mobile", "notes", "ny_kategori", "ny_kontonr", "ny_status", "ordre_id", "pbs", "pbs_nr", "posnr", "postnr", "rabatgruppe", "rename_category", "rename_status", "returside", "status", "status_beskrivelse", "status_id", "stripe_fravalg", "submit", "tlf", "vis_lev_addr", "web"], '');
$_POST = array_replace($_POST, json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR));
foreach (['status_id','cat_id','cat_valg','cat_beskrivelse','status_beskrivelse','posnr','ans_id'] as $key) {
    $_POST[$key] = [];
}
$kategori = '';
$box3 = '';
$box4 = '';
$status_valg = [];
chdir(__DIR__ . '/../../../debitor');
require __DIR__ . '/../../../debitor/debkort_save.php';
echo "\nCHARTEST_CARD_ID=" . (int)$id;
