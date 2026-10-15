<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_kassekladde_page.php --- ver 5.0.0 --- 2026.10.05
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

// argv: journal id, seeded online session id, JSON form fields.
if ($argc !== 4) {
    fwrite(STDERR, "usage: run_kassekladde_page.php <journal> <session> <JSON form>\n");
    exit(2);
}
error_reporting(E_ALL);
$_SERVER['REQUEST_URI'] = '/saldi/finans/kassekladde.php';
$_SERVER['PHP_SELF'] = '/saldi/finans/kassekladde.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = ['kladde_id' => $argv[1]];
$_POST = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);
$_POST['kladde_id'] = $argv[1];
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
session_start();
$_SESSION = [];
session_write_close();
$validatorForm = $_POST;
if (!empty($_POST['_validator'])) {
    // Posted journals do not dispatch kontroller() from POST. Exercise its original
    // posted-journal refusal after loading the page without staging a save.
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
}
chdir(dirname(__DIR__, 3) . '/finans');
include 'kassekladde.php';

if (!empty($validatorForm['_validator'])) {
    kontroller(0, 42, '15-05-2026', 'T3 save', 'F', '987001', 'K', '987003',
        'INV-42', '125,00', '', 'K1', '', (int)$argv[1], 0, 0, '', 'DKK', '', '', 0);
}
