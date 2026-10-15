<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_insert_doc.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

if (PHP_SAPI !== 'cli' || $argc !== 3) { exit(2); }
require_once __DIR__ . '/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/docsIncludes/poolPaths.php';
error_reporting(E_ALL);
$_SERVER['REQUEST_URI']='/saldi/finans/insert-doc.php';
$_SERVER['PHP_SELF']='/saldi/finans/insert-doc.php';
$s_id='characterization';
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$input=json_decode($argv[2],true,512,JSON_THROW_ON_ERROR);
$db=$argv[1];
$connection=CharacterizationEnv::connect($db);
$db_type='postgresql';
$db_skriv_id=2;
$sqdb=CharacterizationEnv::masterDb();
$bruger_id=0;
$brugernavn='chartest';
$revisor=false;
$userId=0;
$sprog_id=0;
$regnaar=(int)CharacterizationEnv::one($connection,"SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")['year'];
$insertFile=true;
$fokus='beskrivelse';
$processingMultiple=true;
$_POST=$input;
$_GET=[];
$_REQUEST=$input;
chdir(dirname(__DIR__,3).'/finans');
require __DIR__ . '/../../../includes/docsIncludes/insertDoc.php';
