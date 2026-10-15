<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_payment_align.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Drive the real settlement page in an isolated process.

require_once __DIR__ . '/CharacterizationEnv.php';
require_once __DIR__ . '/../finans/support/PaymentFixture.php';
error_reporting(E_ALL);
$connection = CharacterizationEnv::connect(CharacterizationEnv::testDb());
$rows = PaymentFixture::query($connection, 'SELECT * FROM openpost WHERE konto_id=$1 AND udlignet=\'0\' ORDER BY id', [(int)$argv[1]]);
pg_close($connection);
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/includes/udlign_openpost.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_POST = ['submit'=>'udlign','post_id'=>array_column($rows,'id'),'konto_id'=>array_column($rows,'konto_id'),
    'udlign'=>array_fill(0,count($rows),'on'),'kontrol'=>array_fill(0,count($rows),'on'),
    'candidate_id'=>array_column($rows,'id'),'faktnr'=>array_column($rows,'faktnr'),'amount'=>array_column($rows,'amount'),
    'belob'=>str_replace('.',',',$rows[0]['amount']), 'valuta'=>array_column($rows,'valuta'),
    'omregningskurs'=>array_fill(0,count($rows),1),'basisvaluta'=>'DKK','dato_fra'=>'01-01-2026','dato_til'=>'31-12-2026',
    'period_from'=>'01-01-2026','period_to'=>'31-12-2026','konto_fra'=>$rows[0]['konto_nr'],'konto_til'=>$rows[0]['konto_nr'],
    'retur'=>'../debitor/rapport.php','returside'=>'../debitor/rapport.php','layout'=>'grid','diff'=>0,'dkkdiff'=>0,
    'maxdiff'=>0,'diffkto'=>0,'diffdato'=>'15-03-2026','diffbilag'=>0];
ini_set('session.save_path',sys_get_temp_dir());
session_id(PaymentFixture::SESSION_ID);
chdir(CharacterizationEnv::repoRoot().'/includes');
include __DIR__ . '/../../../includes/udlign_openpost.php';
