<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_debitorkort_page.php --- ver 5.0.0 --- 2026.10.03
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

error_reporting(E_ALL);
$payload = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$_GET = $payload['get'];
$_POST = array_fill_keys(["addr1", "addr2", "ans_ant", "ans_id", "bank_konto", "bank_reg", "betalingsbet", "betalingsdage", "bynavn", "cat_antal", "cat_beskrivelse", "cat_id", "cat_valg", "cvrnr", "ean", "efternavn", "email", "felt_1", "felt_2", "felt_3", "felt_4", "felt_5", "firmanavn", "fokus", "fornavn", "gl_kontotype", "gruppe", "id", "institution", "kontakt", "kontoansvarlig", "kontonr", "kontotype", "kreditmax", "land", "lev_addr1", "lev_addr2", "lev_bynavn", "lev_efternavn", "lev_email", "lev_firmanavn", "lev_fornavn", "lev_kontakt", "lev_land", "lev_postnr", "lev_tlf", "lukket", "mailfakt", "mobile", "notes", "ny_kategori", "ny_kontonr", "ny_status", "ordre_id", "pbs", "pbs_nr", "posnr", "postnr", "rabatgruppe", "rename_category", "rename_status", "returside", "status", "status_beskrivelse", "status_id", "stripe_fravalg", "submit", "tlf", "vis_lev_addr", "web"], '');
$_POST = array_replace($_POST, $payload['post']);
if (!$payload['post']) { $_POST = []; }
if ($_POST) {
    $_POST += ['swift'=>'', 'productlimit'=>'0'];
    foreach (['status_id','cat_id','cat_valg','cat_beskrivelse','status_beskrivelse','posnr','ans_id'] as $key) {
        $_POST[$key] = [];
    }
    $_POST['betalingsdage'] = $_POST['betalingsdage'] ?: '0';
}
$_SERVER['REQUEST_METHOD'] = $_POST ? 'POST' : 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/debitor/debitorkort.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_SERVER['HTTP_HOST'] = 'localhost';
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
chdir(__DIR__ . '/../../../debitor');
require __DIR__ . '/../../../debitor/debitorkort.php';
