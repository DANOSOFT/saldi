<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/run_t4_claim_docpool.php --- ver 5.0.0 --- 2026.10.06
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
$_SERVER['REQUEST_URI']='/tests/t4-claim-docpool';
$_SERVER['REMOTE_ADDR']='127.0.0.1';
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/mobileUpdates.php';

error_reporting(E_ALL);
$_SERVER['REQUEST_URI']='/tests/t4-claim-docpool';
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$_POST=json_decode($argv[5],true,512,JSON_THROW_ON_ERROR);
$_GET=[];
$GLOBALS['connection']=RestApiEnv::connect($argv[1]);
$GLOBALS['db']=$argv[1];
$GLOBALS['db_type']='postgresql';
$GLOBALS['bruger_id']=(int)$argv[2];
$GLOBALS['brugernavn']=$argv[3];
$GLOBALS['regnaar']=(int)$argv[4];
$GLOBALS['sprog_id']=1;
$GLOBALS['revisor']=false;
$GLOBALS['params']='';
$GLOBALS['db_skriv_id']=0;
chdir(dirname(__DIR__,3).'/includes');
require_once __DIR__ . '/../../../includes/docsIncludes/poolPaths.php';
$folder=dirname(poolPuljePath($argv[1]),2);
require_once __DIR__ . '/../../../includes/docsIncludes/docPool.php';
docPool(0,'',0,0,'',$_POST['poolFile'],$folder,'');
print '\nSTATUS:'.http_response_code();
